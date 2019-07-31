<?php
/**
 * 描述 : 计划任务,定时回调
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
        $config = of::config('_of.com.timer', array());

        //文件锁路径
        empty($config['path']) && $config['path'] = OF_DATA . '/_of/of_base_com_timer';
        $config['path'] = of::formatPath($config['path'], ROOT_DIR);

        //初始 动态任务 配置
        ($index = &$config['task']) || $index = array();
        $index += array('adapter' => 'files', 'params'  => array());

        //初始 静态任务 配置
        ($index = &$config['cron']) || $index = array();
        $index += array('path' => '', 'kvPool' => 'default');
        empty($index['path']) || $index['path'] = of::formatPath($index['path'], ROOT_DIR);

        //任务触发器路径(分布式)
        $config['addrPath'] = $config['path'] . '/taskTrigger/' . md5($_SERVER['SERVER_ADDR']);
        //记录IP地址
        of_base_com_disk::file($config['addrPath'] . '/address.php', $_SERVER['SERVER_ADDR'], true);

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
        //定时器路径
        $path = self::$config['addrPath'];
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
            of_base_com_net::request(OF_URL, array(), array(
                'asCall' => 'of_base_com_timer::timer',
                'params' => array($name, true)
            ));
            $result = false;
        //任务列表遍历检查
        } else if ($name === 'taskLock') {
            while (true) {
                //静态计划任务
                ($crontab = &self::crontab()) && self::fireCalls($crontab);
                //动态计划任务
                ($movTask = &self::taskList(false)) && self::fireCalls($movTask);

                //无任何任务
                if ($movTask === false && $crontab === false) {
                    break;
                //有未到期任务
                } else {
                    //休眠后重新检查
                    sleep(5);
                    //保护进程
                    self::timer('protected');
                }
            }
        //保护进程
        } else if ($name === 'protected') {
            $fp = fopen($path . '/taskLock', 'a');

            do {
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
            } while (true);
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
     *          "cNum" : 并发数量, 0=不设置, n=最大值
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

        if (
            $params['time'] <= $nowTime &&
            of::dispatch('class') === 'of_base_com_net'
        ) {
            //直接触发
            $temp = array(&$params);
            self::fireCalls($temp);
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
     *          服务器IP : {}
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
                if ($v && $fp = fopen($k . '/taskLock', 'a')) {
                    //任务开启
                    if (!flock($fp, LOCK_EX | LOCK_NB)) {
                        $temp = of_base_com_disk::file($k . '/address.php', false, true);
                        $save[$temp] = array();
                    }
                    //连接解锁
                    flock($fp, LOCK_UN);
                    //关闭连接
                    fclose($fp);
                }
            }

            ksort($save);
        }

        return $result;
    }

    /**
     * 描述 : 为并发提供共享数据
     * 参数 :
     *      data : 读写数据,
     *          null=无锁读取
     *          false=清空读取, 读取数据后清空原始数据
     *          true=写锁读取, 读取数据同时加独享锁, 指针在最后位置
     *          数组=单层替换, 将数组键的值替换对应共享数据键的值
     *      cIds : 指定任务中的并发ID
     *          null=读取自身
     *          数字=指定并发ID
     *          数组=多个并发ID, [并发ID, ...]
     *          true=正在运行
     *          false=包括停止
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
     *                  "wRes"  : 写资源, data为true时有效
     *                  "data"  : 并发所存数据
     *              }, ...
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function &data($data = null, $cIds = null, $call = null) {
        //返回结构
        $result = array('info' => array());

        if ($cIds !== null && $call !== null || $nowTask = &self::$nowTask) {
            //读取自身任务
            if ($call === null) {
                $call = '/' . $nowTask['cAvg']['cMd5'];
            //计算任务路径
            } else if (is_array($call) || $call[0] !== '/') {
                $call = '/' . of_base_com_data::digest($call);
            }

            //任务路径
            $path = self::$config['path'] . '/concurrent'. $call;
            //过滤加锁(true=不过滤)
            $filt = $cIds !== true;
            //并发ID排序计数
            $sort = -1;

            //读取自身
            if (is_bool($cIds)) {
                //重置并发ID
                $cIds = array();

                //读取运行中的进程, 打开并发列表
                of_base_com_disk::each($path, $task, null);
                //筛选并发ID
                foreach ($task as $k => &$v) {
                    //是文件 && 是进程
                    if (!$v && is_numeric($temp = basename($k))) {
                        $cIds[] = $temp;
                    }
                }
            } else if (!is_array($cIds)) {
                $cIds = array($cIds === null ? $nowTask['cAvg']['cCid'] : $cIds);
            }

            //从小到大排序并发ID
            sort($cIds, SORT_NUMERIC);

            //读取数据
            foreach ($cIds as &$v) {
                //并发文件存在
                if (is_file($cDir = $path . '/' . $v)) {
                    //进程是否运行, true=运行, false=停止
                    $isRun = !flock(fopen($cDir, 'r'), LOCK_SH | LOCK_NB);

                    //进程运行 || 不过滤
                    if ($isRun || $filt) {
                        $index = &$result['info'][$v];
                        //进程运行状态
                        $index = array(
                            'isRun' => $isRun, 'wRes' => null, 'sort' => ++$sort
                        );

                        //加写锁(array, bool)
                        if ($data || $data === false) {
                            //打开写锁流
                            $index['wRes'] = &of_base_com_disk::file(
                                $cDir . '.dat', null, null
                            );

                            //文件尺寸
                            $temp = ftell($index['wRes']);
                            //移动到首位
                            fseek($index['wRes'], 0);
                            //解析并发数据
                            $index['data'] = fread($index['wRes'], $temp + 1);
                            $index['data'] = $index['data'] ?
                                unserialize($index['data']) : array();

                            //合并数据
                            if (is_array($data)) {
                                //写入数据
                                $index['data'] = $data + $index['data'];
                                of_base_com_disk::file($index['wRes'], $index['data']);
                            //删除数据
                            } else if ($data === false) {
                                of_base_com_disk::file($index['wRes'], '');
                            }

                            //加锁读取 || 解锁资源
                            $data === true || $index['wRes'] = null;
                        //读数据
                        } else {
                            $index['data'] = &of_base_com_disk::file($cDir . '.dat', true);
                            $index['data'] || $index['data'] = array();
                        }
                    }
                }
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
     *          "cCid" : 并发ID
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

        //清理机制
        if (rand(0, 99) === 1) {
            $path = self::$config['path'] . '/concurrent';

            //读取单层文件夹
            if (of_base_com_disk::each($path, $data, null)) {
                foreach ($data as $k => &$v) {
                    //是文件
                    if (!$v) {
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
                        $clear && unlink($k);
                    }
                }
            }
        }

        //启用并发
        if (isset($cAvg['cMd5'])) {
            //定时器根路径
            $cDir = self::$config['path'] . '/concurrent/' . $cAvg['cMd5'];
            is_dir($cDir) || mkdir($cDir, 0777, true);

            //开启共享锁
            of_base_com_disk::file($cDir . '.php', null);

            //打开并发文件
            $fp = fopen($cDir . '/' . $cAvg['cCid'], 'a+');
            //加锁成功
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                //清空文件内容
                ftruncate($fp, 0);
                //更新文件修改时间
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
                if (($result = ftell($fp) > 21) && $mode === false) {
                    $result = array();
                    //读取任务列表
                    $task = of_base_com_disk::file($fp, true, true);

                    //删除上次计划日志
                    if ($safe['task']) {
                        foreach ($safe['task'] as &$v) {
                            unset($task[$safe['time']][$v]);
                        }

                        if (!$task[$safe['time']]) unset($task[$safe['time']]);
                        $callback = true;
                    }

                    //重置保险时间
                    $safe['time'] = $nowtime + 600;
                    $safe['task'] = array();

                    //有到期任务
                    while (($time = key($task)) && $nowtime >= $time) {
                        foreach ($task[$time] as $k => &$callback) {
                            //引用回调
                            $result[] = &$callback;
                            //保存唯一键
                            $safe['task'][] = $k;
                            //计划任务切换到保险位置
                            $task[$safe['time']][$k] = &$callback;
                        }
                        //删除完成的任务
                        unset($task[$time]);
                    }

                    //触发过 && 写回任务列表
                    isset($callback) && of_base_com_disk::file($fp, $task, true);
                    unset($callback);
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
                if (($result = !empty($temp)) && $mode === false) {
                    //删除上次计划日志
                    if ($safe['task']) {
                        $sql = 'DELETE FROM
                            `_of_com_timer`
                        WHERE
                            `hash` IN ("' .join("','", $safe['task']). '")';
                        of_db::sql($sql, $config['params']['dbPool']);
                    }

                    //重置保险时间
                    $safe['time'] = $nowtime + 600;
                    $safe['task'] = array();

                    $sql = "SELECT
                        `hash`, `task`
                    FROM
                        `_of_com_timer`
                    WHERE
                        `time` <= '{$nowtime}'
                    LIMIT 
                        100
                    FOR UPDATE";
                    $temp = of_db::sql($sql, $config['params']['dbPool']);
                    $result = array();

                    //获取任务数据
                    foreach ($temp as &$v) {
                        $result[] = unserialize($v['task']);

                        //保存唯一键
                        $safe['task'][] = $v['hash'];
                    }

                    $sql = 'UPDATE
                        `_of_com_timer`
                    SET
                        `time` = "' .$safe['time']. '"
                    WHERE
                        `hash` IN ("' .join('","', $safe['task']). '")';
                    of_db::sql($sql, $config['params']['dbPool']);
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
    private static function &crontab() {
        static $lastTime = null;
        $config = &self::$config['cron'];
        $timeList = $result = array();
        $nowTime = time();

        //取整分时间
        $lastTime === null && $lastTime = $nowTime - $nowTime % 60 - 60;
        //执行间隔60s
        while ($nowTime - $lastTime > 59) {
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
                            //读取当前任务最后执行时间戳
                            $temp = of_base_com_kv::get($tKey, 0, $config['kvPool']);

                            //对应时间整点没有执行过
                            if ($temp < $timeBox['nowTime']) {
                                //记录成功项
                                $result[] = array('time' => $timeBox['nowTime']) + $vt;
                                //记录最后更新时间戳
                                of_base_com_kv::set($tKey, $timeBox['nowTime'], 86400, $config['kvPool']);
                            }

                            //解锁
                            of_base_com_disk::lock($lock, LOCK_UN);
                        }
                    }
                }
            } else {
                $result = false;
            }
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
    private static function fireCalls(&$list) {
        //定时器根路径
        $path = self::$config['path'] . '/concurrent';
        //当前时间
        $nowTime = time();

        foreach ($list as &$v) {
            //单计划
            if (empty($v['cNum'])) {
                //触发任务
                of_base_com_net::request(OF_URL, array(), array(
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
                        //打开并发文件
                        $fp = fopen($temp = $cDir . '/' . $cNum, 'a');

                        //加锁成功, 没有使用的并发ID
                        $isRun = flock($fp, LOCK_EX | LOCK_NB);
                        $isRun && touch($temp, $nowTime);

                        //连接解锁
                        flock($fp, LOCK_UN);
                        //关闭连接
                        fclose($fp);

                        //触发任务
                        $isRun && of_base_com_net::request(OF_URL, array(), array(
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