<?php
/**
 * 描述 : 计划任务,定时回调
 * 注明 :
 *      磁盘结构 : {
 *          "/concurrent"  : 并发任务列表 {
 *              /任务ID     : 单个任务信息 {
 *                  "/info.php" : 存储回调信息
 *                  其它为数字  : 任务并发, 启动时加独享锁, 存储启动时间戳
 *              },
 *              /任务ID.php : 任务启动时加共享锁, 清理时加独享锁, 未存信息
 *          },
 *          "/taskTrigger" : 分布式节点信息 {
 *              /节点ID : 单个节点信息 {
 *                  "/nodeInfo.php" : 节点信息, _of.nodeName,
 *                  "/taskLock"     : 任务进程, 启动时独享锁
 *                  "/protected"    : 保护进程, 启动时独享锁
 *              }, ...
 *          }
 *          "taskList.php" : 动态任务文件模式下存储的文件
 *      }
 * 作者 : Edgar.lee
 */
class of_base_com_timer {
    //日志配置, _of.com.timer
    private static $config = null;
    //当前运行的任务, 并发时生成 {"call" : taskCall参数, "cAvg" : taskCall参数}
    private static $nowTask = null;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        $config = &self::$config;
        //读取节点
        $config = of::config('_of.com.timer', array());

        //操作系统类型(WINNT:windows, Darwin:mac, 其它:linux)
        $config['osType'] = strtolower(substr(PHP_OS, 0, 3));
        //文件锁路径
        empty($config['path']) && $config['path'] = OF_DATA . '/_of/of_base_com_timer';
        $config['path'] = of::formatPath($config['path'], ROOT_DIR);

        //初始 动态任务 配置
        ($index = &$config['task']) || $index = array();
        $index += array('adapter' => 'files', 'params'  => array());

        //初始 静态任务 配置
        ($index = &$config['cron']) || $index = array();
        $index += array('path' => '');
        empty($index['path']) || $index['path'] = of::formatPath($index['path'], ROOT_DIR);

        //空节点名称不生成触发器路径
        if ($nodeName = of::config('_of.nodeName')) {
            //任务触发器路径(分布式)
            $config['addrPath'] = $config['path'] . '/taskTrigger/' . md5($nodeName);
            //记录IP地址
            of_base_com_disk::file($config['addrPath'] . '/nodeInfo.php', array(
                'node' => $nodeName
            ), true);
        }

        //web访问开启计划任务
        if (of::dispatch('class') === 'of_base_com_timer') {
            echo self::info(2) ? 'runing' : 'starting', "<br>\n";
            //开启计划任务
            self::timer();

            if (OF_DEBUG === false) {
                exit('Access denied: production mode.');
            } else {
                echo '<pre><hr>Scheduled task : '; 
                if (is_file(self::$config['cron']['path'])) {
                    print_r(include self::$config['cron']['path']);
                }

                echo '<hr>Concurrent Running : ';
                print_r(of_base_com_timer::info(1));
                echo '</pre>';
            }
        }
    }

    /**
     * 描述 : 定时器
     * 返回 :
     *      true=正在执行, false=开始执行, null=执行完成
     * 作者 : Edgar.lee
     */
    public static function &timer($name = 'taskLock', $type = null) {
        //定时器路径, 空路径(空节点名)不启动触发器
        if (!$path = &self::$config['addrPath']) return $result;
        //打开加锁文件
        $lock = fopen($path . '/' . $name, 'a');

        //加锁失败
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $result = true;
        //主动触发(异步)
        } else if ($type === null) {
            //连接解锁
            flock($lock, LOCK_UN);
            //加载定时器
            of_base_com_net::request('', array(), array(
                'asCall' => 'of_base_com_timer::timer',
                'params' => array($name, true)
            ));
            $result = false;
        //任务列表遍历检查
        } else if ($name === 'taskLock') {
            while (!self::renew()) {
                //休眠后返回任务数量
                $needNum = self::getRunNum();

                //静态计划任务
                ($crontab = &self::crontab($needNum)) && self::fireCalls($crontab);
                //动态计划任务
                ($movTask = &self::taskList($needNum)) && self::fireCalls($movTask);

                //无任何任务
                if ($movTask === false && $crontab === false) {
                    break;
                //有未到期任务
                } else {
                    //保护进程
                    self::timer('protected');
                }
            }
        //保护进程
        } else if ($name === 'protected') {
            $fp = fopen($path . '/taskLock', 'a');

            while (!self::renew()) {
                //连接加锁(非阻塞) 兼容 glusterfs 网络磁盘
                while (!flock($fp, LOCK_EX | LOCK_NB)) {
                    usleep(200);
                }
                //连接解锁
                flock($fp, LOCK_UN);

                //无效保护进程继续运行
                if (self::taskList(null) === false && self::crontab() === false) {
                    //关闭连接
                    fclose($fp);
                    //结束保护
                    break;
                //读取加锁源
                } else {
                    //任务进程
                    self::timer('taskLock');
                    //等待处理
                    sleep(30);
                }
            }
        }

        //连接解锁
        flock($lock, LOCK_UN);
        //关闭连接
        fclose($lock);
        //返回结果
        return $result;
    }

    /**
     * 描述 : 任务定时,每分钟检查触发方法
     * 参数 :
     *      params : 任务参数 {
     *          "time" : 执行时间, 五年内秒数=xx后秒执行, 其它=指定时间
     *          "call" : 框架标准的回调
     *          "cNum" : 并发数量, 0=不设置, n=最大值, []=指定并发ID(最小值1)
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]
     *      }
     * 作者 : Edgar.lee
     */
    public static function task($params) {
        //格式化
        $params += array('time' => 0, 'cNum' => 0, 'try' => array());
        //当前时间
        $nowTime = time();

        //时间戳
        if (is_numeric($params['time'])) {
            //小于5年定义为xx后秒执行
            $params['time'] < 63072000 && $params['time'] += $nowTime;
        //时间格式
        } else {
            //转换为数字
            $params['time'] = strtotime($params['time']);
        }

        //限制并发 && 立刻执行
        if ($params['cNum'] && $params['time'] <= $nowTime) {
            //并发数组
            $cArr = is_array($params['cNum']) ?
                $params['cNum'] : range(1, $params['cNum']);
            //读取当前并发信息
            $temp = self::data(null, 1, $params['call']);

            //当前执行并发数大于等于所需并发数, 直接跳出
            if (!array_diff($cArr, array_keys($temp['info']))) {
                return ;
            }
        }

        //异步模式 && 即时执行
        if (self::$nowTask && $params['time'] <= $nowTime) {
            //直接触发
            self::fireCalls(array(&$params));
        //同步模式 || 延迟执行
        } else {
            //添加到待执行列表中
            self::taskList($params);
        }
    }

    /**
     * 描述 : 获取当期运行的信息
     *      type : 读取类型(可叠加), 1=并发的任务, 2=分布定时器
     * 返回 : 
     *      type为1时 : 并发的任务 {
     *          任务唯一键 : {
     *              "call" : 统一回调结构
     *              "list" : 运行的任务列表 {
     *                  运行的序号 : {
     *                      "datetime"  : 任务启动时间
     *                      "timestamp" : 任务启动时间戳
     *                  }
     *              }
     *          }
     *      }
     *      type为2时 : 分布定时器 {
     *          节点名称 : {}
     *      }
     *      type其它时 : 如1+2为3时 {
     *          "concurrent"  : type为1的结构,
     *          "taskTrigger" : type为2的结构
     *      }
     * 作者 : Edgar.lee
     */
    public static function &info($type) {
        $result = array();

        //读取并发数定时任务
        if ($type & 1) {
            $path = self::$config['path'] . '/concurrent';
            $type === 1 ? $save = &$result : $save = &$result['concurrent'];
            $save = array();

            //遍历并发任务目录
            of_base_com_disk::each($path, $tasks, null);
            foreach ($tasks as $kt => &$vt) {
                //是目录
                if ($vt) {
                    //任务唯一键
                    $taskId = basename($kt);
                    //当前任务磁盘目录
                    $dir = $path . '/' . $taskId;
                    //打卡并发文件流
                    $tFp = fopen($dir . '.php', 'r+');

                    //加锁成功, 任务没运行
                    if (flock($tFp, LOCK_EX | LOCK_NB)) {
                        flock($tFp, LOCK_UN);
                    //任务已运行 && 文件信息存在
                    } else if (is_file($temp = $dir . '/info.php')) {
                        $index = &$save[$taskId];

                        //读取任务回调信息
                        $temp = of_base_com_disk::file($temp, false, true);
                        $index['call'] = json_decode($temp, true);
                        $index['list'] = array();

                        //读取运行中的进程, 打开并发列表
                        of_base_com_disk::each($dir, $taskList, null);
                        foreach ($taskList as $k => &$v) {
                            //是文件 && 是进程加锁
                            if (!$v && is_numeric($cId = basename($k))) {
                                $fp = fopen($k, 'r');

                                //加锁成功, 进程没启动
                                if (flock($fp, LOCK_SH | LOCK_NB)) {
                                    flock($fp, LOCK_UN);
                                } else {
                                    $temp = filemtime($k);
                                    //记录并发执行的时间
                                    $index['list'][$cId] = array(
                                        'datetime' => date('Y-m-d H:i:s', $temp),
                                        'timestamp' => $temp
                                    );
                                }

                                fclose($fp);
                            }
                        }

                        ksort($index['list']);
                    }

                    fclose($tFp);
                }
            }
        }

        //分布定时器执行情况
        if ($type & 2) {
            $path = self::$config['path'] . '/taskTrigger';
            $type === 2 ? $save = &$result : $save = &$result['taskTrigger'];
            $save = array();

            //遍历定时器文件夹
            of_base_com_disk::each($path, $data, null);
            foreach ($data as $k => &$v) {
                //是文件夹 && 任务锁打开成功
                if ($v) {
                    //监控未开启
                    if (flock(fopen($k . '/taskLock', 'a'), LOCK_EX | LOCK_NB)) {
                        //清理未启动监控
                        if ($_SERVER['REQUEST_TIME'] - filemtime($k . '/nodeInfo.php') > 86400) {
                            of_base_com_disk::delete($k);
                        }
                    //监控已开启
                    } else {
                        $temp = of_base_com_disk::file($k . '/nodeInfo.php', true, true);
                        $save[$temp['node']] = array();
                    }
                }
            }

            ksort($save);
        }

        return $result;
    }

    /**
     * 描述 : 为并发提供共享数据
     * 参数 :
     *      data : 读写数据, 仅可操作自身或未运行进程数据
     *          null=不读数据, 仅关注运行状态使用
     *          true=读取数据
     *          false=清空读取, 读取数据后清空原始数据
     *          数组=单层替换, 将数组键的值替换对应共享数据键的值
     *      cIds : 指定任务中的并发ID
     *          null=读取自身
     *          数组=多个并发ID, [并发ID, ...]
     *          1=正在运行
     *          2=停止运行
     *          3=包括停止
     *      call : 指定任务ID
     *          null=读取自身
     *          '/'开头字符串=指定任务ID
     *          符合框架回调结构=指定任务的回调
     * 返回 :
     *      {
     *          "info" : {
     *              并发ID, 从小到大排序 : {
     *                  "isRun" : 是否运行, true=运行, false=停止
     *                  "sort"  : 在返回列表中的位置, 从0开始
     *                  "data"  : 并发所存修改前的数据
     *              }, ...
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function &data($data = true, $cIds = null, $call = null) {
        //返回结构
        $result = array('info' => array());

        if ($cIds !== null && $call !== null || $nowTask = &self::$nowTask) {
            //读取自身任务
            if ($call === null) {
                $call = $nowTask['cAvg']['cMd5'];
            //计算任务路径
            } else if (is_array($call) || $call[0] !== '/') {
                $call = of_base_com_data::digest($call);
            //以'/'开头字符串
            } else {
                $call = substr($call, 1);
            }

            //任务路径
            $path = self::$config['path'] . '/concurrent/'. $call;
            //过滤加锁(true=读取全部, false=读取运行, null=读取停止)
            $filt = $cIds === 2 ? null : $cIds !== 1;
            //并发ID排序计数
            $sort = -1;

            //遍历列表
            if (is_int($cIds)) {
                //重置并发ID
                $cIds = array();

                //读取运行中的进程, 打开并发列表
                of_base_com_disk::each($path, $task, null);
                //筛选并发ID
                foreach ($task as $k => &$v) {
                    //是文件 && 是进程
                    if (!$v && is_numeric($temp = basename($k))) {
                        $cIds[] = (int)$temp;
                    }
                }
            //读取自身
            } else if ($cIds === null) {
                $cIds = array($nowTask['cAvg']['cCid']);
            //其它为数组
            }

            //从小到大排序并发ID
            sort($cIds, SORT_NUMERIC);

            //读取数据
            foreach ($cIds as &$v) {
                //并发文件存在
                $cDir = $path . '/' . $v;
                //自身进程
                $isSelf = isset($nowTask) && $nowTask['cAvg']['cCid'] === $v;
                //进程是否运行, true=运行, false=停止
                $isRun = $isSelf ? true : is_file($cDir) && !flock($lock = fopen($cDir, 'r'), LOCK_SH | LOCK_NB);

                //读取全部进程 || (读取停止 ? 进程停止 : 进程运行)
                if ($filt || ($filt === null ? !$isRun : $isRun)) {
                    //数据键名
                    $dKey = 'of_base_com_timer::data-' . $call . '.' . $v;
                    //引用进程结果
                    $index = &$result['info'][$v];
                    //进程运行状态
                    $index = array(
                        'isRun' => $isRun,
                        'sort'  => ++$sort,
                        'data'  => $data === null ?
                            null : of_base_com_kv::get($dKey, array(), '_ofSelf')
                    );

                    //修改数据(自身进程 || 未运行)
                    if ($isSelf || !$isRun) {
                        //合并写入
                        if (is_array($data)) {
                            //合并数据
                            $data += $index['data'];
                            //数据不为空(防止读失败导致意外清空) && 写入数据
                            $data && of_base_com_kv::set($dKey, $data, 86400, '_ofSelf');
                        //删除数据
                        } else if ($data === false) {
                            of_base_com_kv::del($dKey, '_ofSelf');
                        }
                    }
                }

                //解锁未运行的进程
                unset($lock);
            }
        }

        return $result;
    }

    /**
     * 描述 : 判断已加载文件是否有更新
     * 参数 :
     *      preg : 忽略校验的正则, true=全校验, 字符串=以"@"开头的正则忽略路径
     *      eTip : 有更新时抛出错误, ""=不抛出, 字符串=抛出的错误信息
     * 返回 :
     *      true=有变动, false=未变动
     * 作者 : Edgar.lee
     */
    public static function renew($preg = true, $eTip = '') {
        //已加载路径{完整路径:修改时间}
        static $load = array();
        //当前加载的文件
        $list = get_included_files();

        foreach ($list as &$v) {
            //统一磁盘路径
            $v = strtr($v, '\\', '/');
            //在校验范围内
            if ($preg === true || !preg_match($preg, $v)) {
                //文件存在, 继续验证修改时间
                if (is_file($v)) {
                    //读取文件修改时间
                    $mTime = filemtime($v);

                    //新加载的文件
                    if (empty($load[$v])) {
                        $load[$v] = $mTime;
                    //文件被修改过
                    } else if ($load[$v] !== $mTime) {
                        $eTip && trigger_error($eTip . ': (U)' . $v);
                        return true;
                    }
                //文件删除
                } else {
                    $eTip && trigger_error($eTip . ': (D)' . $v);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 描述 : 回调任务函数
     * 参数 : 
     *      call : task 参数格式
     *      cAvg : 并发参数 false=无并发, 数组=启动并发 {
     *          "cMd5" : 回调唯一值
     *          "cCid" : 并发ID, 从1开始
     *      }
     * 作者 : Edgar.lee
     */
    public static function taskCall($call, $cAvg = false) {
        //记录当前任务
        self::$nowTask = array(
            'call' => $call,
            'cAvg' => $cAvg
        );
        //系统回调参数
        $params = array(
            'call' => &$call['call'],
            'time' => &$call['time'],
            'cNum' => &$call['cNum'],
            'try'  => &$call['try'],
            'this' => &$cAvg
        );

        //保护linux进程不被SIGTERM信号杀掉
        if (function_exists('pcntl_signal')) {
            //将回调转成"类名(::xx)?"的模式
            if (is_array($class = $call['call'])) {
                //{"asCall" : 回调, "params" : 参数} 结构
                isset($class['asCall']) && $class = $class['asCall'];
                //[对象或类名, 方法] 结构
                is_array($class) && $class = $class[0];
                //对象->类名
                is_object($class) && $class = get_class($class);
            }

            //框架类回调 || 信号1~32(9 19 32 linux 无效, 17 mac 无效)
            preg_match('@^\\\\?of(_|\b)@', $class) || pcntl_signal(15, SIG_IGN);
        }

        //清理机制
        if (rand(0, 99) === 1) {
            $path = self::$config['path'] . '/concurrent';

            //读取单层文件夹
            if (of_base_com_disk::each($path, $data, null)) {
                foreach ($data as $k => &$v) {
                    //是进程文件 && 24小时未执行
                    if (!$v && $_SERVER['REQUEST_TIME'] - filemtime($k) > 86400) {
                        //打开并发文件
                        $fp = fopen($k, 'a');

                        //加锁成功
                        if ($clear = flock($fp, LOCK_EX | LOCK_NB)) {
                            of_base_com_disk::delete(substr($k, 0, -4));
                        }

                        //连接解锁
                        flock($fp, LOCK_UN);
                        //关闭连接
                        fclose($fp);
                        //删除文件
                        $clear && @unlink($k);
                    }
                }
            }
        }

        //启用并发
        if (isset($cAvg['cMd5'])) {
            //定时器根路径
            $cDir = self::$config['path'] . '/concurrent/' . $cAvg['cMd5'];
            is_dir($cDir) || @mkdir($cDir, 0777, true);

            //开启共享锁
            $lock = of_base_com_disk::file($temp = $cDir . '.php', null);
            //更新进程触发时间
            touch($temp, $_SERVER['REQUEST_TIME']);

            //打开并发文件
            $fp = fopen($cDir . '/' . $cAvg['cCid'], 'c+');
            //加锁成功
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                //清空文件内容
                ftruncate($fp, 0);
                //更新进程并发的修改时间
                fwrite($fp, $_SERVER['REQUEST_TIME']);
            //并发已开启, 不用继续执行
            } else {
                $params = false;
            }
        }

        //回调失败
        if ($params && of::callFunc($call['call'], $params) === false) {
            //不可重试
            if (($try = array_shift($call['try'])) === null) {
                //达到最大尝试次数
                trigger_error('Reached the maximum number of attempts.');
            //可重试
            } else {
                //重设时间
                $call['time'] += $try;
                //添加计划任务
                self::taskList($call);
            }
        }
    }

    /**
     * 描述 : 根据服务器空闲资源休眠并返回执行并发任务数
     * 作者 : Edgar.lee
     */
    private static function getRunNum() {
        //是否禁用popen, true=禁用, false=启用
        static $offExec = null;
        //引用配置
        $config = &self::$config;

        if (
            $offExec ||
            //未初始化 && popen禁用(安全模式 || 方法被禁用)
            $offExec === null && $offExec = (ini_get('safe_mode') || !function_exists('popen'))
        ) {
            $rate = 5;
        //windows系统
        } else if ($config['osType'] === 'win') {
            //读取CPU使用率, 耗时1s
            $rate = preg_match(
                "@[0-9]+@",
                stream_get_contents(popen('WMIC CPU GET LOADPERCENTAGE /VALUE', 'r'), 1024),
                $rate
            ) ? intval((int)$rate[0] / 10) : 5;
        //类linux系统
        } else {
            //区分mac(Darwin)与linux命令
            $rate = $config['osType'] === 'dar' ?
                'top -l 1 | grep "CPU usage"' :
                'top -b -d 1 -n 2 | grep "Cpu(s)" | tail -n 1';
            //读取CPU使用率, 耗时1s
            $rate = preg_match(
                "@[^,]+id@",
                stream_get_contents(popen($rate, 'r'), 1024),
                $rate
            ) ? intval(10 - (int)$rate[0] / 10) : 5;
        }

        //CPU 占用 <= 50%
        if ($rate < 6) {
            //CPU 占用对应休眠 40% : 1, 50% : 2
            $rate > 3 && sleep($rate - 3);
        //CPU 占用 <= 80%
        } else if ($rate < 9) {
            sleep($rate);
        //CPU 占用 <= 100%
        } else {
            sleep($rate + 9);
        }

        //执行任务数量
        return (11 - $rate) * 10;
    }

    /**
     * 描述 : 添加 删除 读取 判读
     * 参数 :
     *      mode : 数组=添加计划任务, false=返回部分执行列表, null=判断是否有计划
     * 返回 :
     *      false : 无任何任务返回false, 否则当前可执行的任务(可能为空数组)
     *      null  : 无任何任务返回false, 否则返回true
     * 注明 :
     *      files 模式的存储结构 : {
     *          执行时间戳 : {
     *              防重复的 md5 回调校验值 : {
     *                  "time" : 执行时间戳
     *                  "call" : 回调函数
     *                  "try"  : 尝试执行次数
     *              }, ...
     *          }, ...
     *      }
     *      mysql 数据结构创建语句 : CREATE TABLE `_of_com_timer` (
     *          `hash` char(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '唯一标识符(十六进制时间戳+回调序列化的md5)',
     *          `task` mediumtext CHARACTER SET utf8 NOT NULL COMMENT '存储调用数据{"call":回调结构,"try":尝试次数}',
     *          `time` int(11) NOT NULL DEFAULT '0' COMMENT '执行时间戳',
     *          PRIMARY KEY (`hash`),
     *          KEY `根据时间查询执行范围` (`time`) USING BTREE
     *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='框架计划任务存储列表';
     * 作者 : Edgar.lee
     */
    private static function &taskList($mode) {
        //防止宕机
        static $safe = array('task' => array());
        //引用配置
        $config = &self::$config['task'];

        //添加计划任务
        if (is_array($mode)) {
            //生成md5
            $hash = md5(serialize($mode['call']));

            //文本模式
            if ($config['adapter'] === 'files') {
                //读取加锁源
                $fp = of_base_com_disk::file(self::$config['path'] . '/taskList.php', null, null);
                //读取任务列表
                $task = of_base_com_disk::file($fp, true, true);

                $task[$mode['time']][$hash] = &$mode;
                //按时间递增排序
                ksort($task);
                //写回任务列表
                of_base_com_disk::file($fp, $task, true);

                flock($fp, LOCK_UN);
                fclose($fp);
            //数据库模式
            } else {
                $sql = 'INSERT INTO `_of_com_timer` (`hash`, `task`, `time`) VALUES (
                    "' . dechex($mode['time']) . $hash . '", "' . addslashes(serialize($mode)) . '", "' . $mode['time'] . '"
                ) ON DUPLICATE KEY UPDATE
                    `task` = VALUES(`task`)';
                of_db::sql($sql, $config['params']['dbPool']);
            }

            //开启定时器
            self::timer();
        //执行和判断是否有计划
        } else {
            //当期时间戳
            $nowtime = time();

            //文本模式
            if ($config['adapter'] === 'files') {
                //读取加锁源
                $fp = of_base_com_disk::file(self::$config['path'] . '/taskList.php', null, null);

                //任务列表不为空 && 读取详细信息
                if (($result = ftell($fp) > 21) && is_int($mode)) {
                    $result = array();
                    //读取任务列表
                    $task = of_base_com_disk::file($fp, true, true);

                    //有到期任务
                    while (($time = key($task)) && $nowtime >= $time) {
                        foreach ($task[$time] as $k => &$v) {
                            //反序列任务
                            $call = $v += array('rNum' => 0);
                            //执行任务数量, 无并发 ? 1 : 并发数
                            $tNum = empty($call['cNum']) ? 1 : (
                                is_array($call['cNum']) ? count($call['cNum']) : $call['cNum']
                            );
                            //计算执行任务数量
                            $cNum = min($tNum - $v['rNum'], $mode);
                            //更新执行并发数
                            $v['rNum'] += $cNum;
                            //更新剩余任务数
                            $mode -= $cNum;

                            //并发已完全运行, 应执行数 <= 已执行数
                            if ($tNum <= $v['rNum']) {
                                //删除完成的任务
                                unset($task[$time][$k]);
                            }

                            //未指定并发 || 指定最大任务并发数
                            empty($call['cNum']) || $call['cNum'] = range(
                                $v['rNum'] - $cNum + 1, $v['rNum']
                            );
                            $result[] = $call;

                            //所需任务为空, 结束后续列表
                            if (!$mode) break 2;
                        }

                        //删除完成的任务
                        if (!$task[$time]) unset($task[$time]);
                    }

                    //触发过 && 写回任务列表
                    isset($call) && of_base_com_disk::file($fp, $task, true);
                }

                //连接解锁
                flock($fp, LOCK_UN);
                //关闭连接
                fclose($fp);
            //数据库模式
            } else {
                //开启事务
                of_db::sql(null, $config['params']['dbPool']);
                $sql = "SELECT 1 FROM `_of_com_timer` LIMIT 1";
                $temp = of_db::sql($sql, $config['params']['dbPool']);

                //是否有数据 && 读取详细信息
                if (($result = !empty($temp)) && is_int($mode)) {
                    //读取所需数量任务
                    $sql = "SELECT
                        `hash`, `task`
                    FROM
                        `_of_com_timer`
                    WHERE
                        `time` <= '{$nowtime}'
                    LIMIT
                        {$mode}
                    FOR UPDATE";
                    $list = of_db::sql($sql, $config['params']['dbPool']);
                    $result = array();

                    //获取任务数据
                    foreach ($list as &$v) {
                        //反序列任务
                        $call = unserialize($v['task']) + array('rNum' => 0);
                        //执行任务数量, 无并发 ? 1 : 并发数
                        $tNum = empty($call['cNum']) ? 1 : (
                            is_array($call['cNum']) ? count($call['cNum']) : $call['cNum']
                        );
                        //计算执行任务数量
                        $cNum = min($tNum - $call['rNum'], $mode);
                        //更新执行并发数
                        $call['rNum'] += $cNum;
                        //更新剩余任务数
                        $mode -= $cNum;

                        //并发未完全运行, 应执行数 > 已执行数
                        if ($tNum > $call['rNum']) {
                            $sql = 'UPDATE
                                `_of_com_timer`
                            SET
                                `task` = "' .addslashes(serialize($call)). '"
                            WHERE
                                `hash` = "' .$v['hash']. '"';
                        } else {
                            $sql = 'DELETE FROM
                                `_of_com_timer`
                            WHERE
                                `hash` = "' .$v['hash']. '"';
                        }
                        of_db::sql($sql, $config['params']['dbPool']);

                        //未指定并发 || 指定最大任务并发数
                        empty($call['cNum']) || $call['cNum'] = range(
                            $call['rNum'] - $cNum + 1, $call['rNum']
                        );
                        $result[] = $call;

                        //所需任务为空, 结束后续列表
                        if (!$mode) break ;
                    }
                }

                //开启事务
                of_db::sql(true, $config['params']['dbPool']);
            }
        }

        return $result;
    }

    /**
     * 描述 : 读取静态计划任务
     * 返回 :
     *      无任何任务返回false, 否则当前可执行的任务(可能为空数组)
     * 作者 : Edgar.lee
     */
    private static function &crontab($needNum = 0) {
        static $lastTime = null;
        $config = &self::$config['cron'];
        $timeList = $result = array();
        $lastKey = 'of_base_com_timer::crontab#lastTime';
        $entTime = ($entTime = time()) - $entTime % 60;

        //取整分时间(KV缓存时间 || 当前时间前一分钟)
        $lastTime === null && $lastTime = of_base_com_kv::get($lastKey, $entTime - 60, '_ofSelf');
        //记录调用初始化时间
        $initTime = $lastTime;

        //执行间隔60s
        while ($entTime - $lastTime > 59) {
            $lastTime += 60;
            $timeList[] = array(
                'nowTime' => $lastTime,
                //分时日月 星期(0-6)
                'nowNode' => explode(' ', date('i H d m w', $lastTime))
            );
        }

        if ($timeList) {
            //最新静态任务
            is_file($config['path']) && $cron = include $config['path'];

            if (!empty($cron) && is_array($cron)) {
                //无效任务数
                if ($needNum < 1) return $result;

                foreach ($cron as &$vt) {
                    //每项时间分割
                    $item = preg_split('@\s+@', trim($vt['time']));

                    foreach ($timeList as &$timeBox) {
                        foreach ($item as $ki => &$vi) {
                            $index = &$timeBox['nowNode'][$ki];
                            //每列时间集合[14-30/3]
                            preg_match_all('@(\d+|\*)(?:-(\d+))?(?:/(\d+))?(,|$)@', $vi, $list, PREG_SET_ORDER);

                            foreach ($list as &$vl) {
                                //x 模式
                                if ($vl[2] === '') {
                                    $temp = $index == $vl[1] || $vl[1] === '*';
                                //大-小 模式
                                } else if ($vl[1] > $vl[2]) {
                                    $temp = $index >= $vl[1] || $index <= $vl[2];
                                //小-大 模式
                                } else {
                                    $temp = $index >= $vl[1] && $index <= $vl[2];
                                }

                                //范围通过 && 频率通过(不需要 || 在范围内 && 可整除)
                                if (
                                    $temp && (
                                        !$vl[3] ||
                                        $index >= $vl[1] &&
                                        ($index - (int)$vl[1]) % $vl[3] === 0
                                    )
                                ) {
                                    //进入下一项校验
                                    continue 2;
                                }
                            }

                            //当前项校验失败
                            continue 2;
                        }

                        //计算分布标记键
                        $tKey = 'of_base_com_timer::crontab#' . $vt['time'] . serialize($vt['call']);
                        $lock = $tKey . '~lock~';

                        //加锁直到成功
                        if (of_base_com_disk::lock($lock)) {
                            //读取当前任务最后执行信息
                            $info = of_base_com_kv::get($tKey, 0, '_ofSelf');
                            //格式化执行信息
                            is_int($info) && $info = array(
                                //最后执行时间戳
                                'time' => $info,
                                //已执行并发次数
                                'rNum' => 0
                            );

                            //对应时间整点没有执行过
                            if ($info['time'] < $lastTime) {
                                //执行任务数量, 无并发 ? 1 : 并发数
                                $tNum = empty($vt['cNum']) ? 1 : $vt['cNum'];
                                //配置文件修改并发数 && 已执行并发次数归零
                                $tNum > $info['rNum'] || $info['rNum'] = 0;
                                //计算执行任务数量
                                $cNum = min($tNum - $info['rNum'], $needNum);

                                //更新执行并发数
                                $info['rNum'] += $cNum;
                                //更新剩余任务数
                                $needNum -= $cNum;

                                //克隆任务信息
                                $call = array('time' => $timeBox['nowTime']) + $vt;
                                //未指定并发 || 指定最大任务并发数
                                empty($vt['cNum']) || $call['cNum'] = range(
                                    $info['rNum'] - $cNum + 1, $info['rNum']
                                );
                                //记录成功项
                                $result[] = $call;

                                //任务未执行完 ? 记录执行进度 : 记录最后时间戳
                                $temp = $tNum > $info['rNum'] ? $info : $lastTime;
                                //记录最后更新进度
                                of_base_com_kv::set($tKey, $temp, 86400, '_ofSelf');
                            }

                            //解锁
                            of_base_com_disk::lock($lock, LOCK_UN);
                            //任务仅执行一次
                            break ;
                        }
                    }

                    //所需为空, 恢复初始时间
                    if ($needNum === 0) {
                        $lastTime = $initTime;
                        break ;
                    }
                }
            } else {
                $result = false;
            }

            //记录缓存时间
            of_base_com_kv::set($lastKey, $lastTime, 86400, '_ofSelf');
        }

        return $result;
    }

    /**
     * 描述 : 触发计划任务
     * 参数 :
     *     &list : 任务列表 [{
     *          "time" : 标准执行时间戳
     *          "call" : 框架标准的回调
     *          "cNum" : 并发数量, 0=不设置, n=最大值
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]
     *      }, ...]
     * 作者 : Edgar.lee
     */
    private static function fireCalls($list) {
        //定时器根路径
        $path = self::$config['path'] . '/concurrent';

        foreach ($list as &$v) {
            //单计划
            if (empty($v['cNum'])) {
                //触发任务
                of_base_com_net::request('', array(), array(
                    'asCall' => 'of_base_com_timer::taskCall',
                    'params' => array(&$v, false)
                ));
            //多并发
            } else {
                //并发数组
                $cArr = is_array($v['cNum']) ? $v['cNum'] : range(1, $v['cNum']);
                $cMd5 = of_base_com_data::digest($v['call']);
                $cDir = $path . '/' . $cMd5;

                //打开加读锁文件
                $lock = of_base_com_disk::file($cDir . '.php', null);

                //加锁成功
                if ($lock) {
                    //初始化进程信息
                    if (!is_file($temp = $cDir . '/info.php')) {
                        of_base_com_disk::file($temp, json_encode($v['call']), true);
                    }

                    foreach ($cArr as &$cNum) {
                        //加锁成功, 没有使用的并发ID
                        $isRun = flock(fopen($cDir . '/' . $cNum, 'a'), LOCK_EX | LOCK_NB);
                        //触发任务
                        $isRun && of_base_com_net::request('', array(), array(
                            'asCall' => 'of_base_com_timer::taskCall',
                            'params' => array(
                                &$v, array('cMd5' => $cMd5, 'cCid' => $cNum)
                            )
                        ));
                    }
                }

                //连接解锁
                flock($lock, LOCK_UN);
                //关闭连接
                fclose($lock);
            }
        }
    }
}

of_base_com_timer::init();