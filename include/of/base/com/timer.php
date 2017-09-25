<?php
/**
 * 描述 : 计划任务,定时回调
 * 作者 : Edgar.lee
 */
class of_base_com_timer {
    private static $config = null;

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

        //嵌套创建文件夹
        is_dir($config['path']) || @mkdir($config['path'], 0777, true);

        //web访问开启计划任务
        if (of::dispatch('class') === 'of_base_com_timer') {
            echo self::timer() ? 'runing' : 'starting', "<br>\n";

            if (OF_DEBUG === false) {
                exit('Access denied: production mode.');
            } else {
                echo "<pre>cron : "; 
                if (is_file(self::$config['cron']['path'])) {
                    print_r(include self::$config['cron']['path']);
                }
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
        $path = self::$config['path'];
        //打开加锁文件
        $lock = fopen($path . '/' . $name, 'a');

        //加锁失败
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $result = true;
        //主动触发(非异步)
        } else if ($type === null) {
            //连接解锁
            flock($lock, LOCK_UN);
            //加载定时器
            of_base_com_net::request(OF_URL . '/index.php', array(), array(
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
                //连接加锁
                if (flock($fp, LOCK_EX)) {
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
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]
     *      }
     * 作者 : Edgar.lee
     */
    public static function task($params) {
        //格式化
        $params += array('time' => 0, 'try' => array());

        //时间戳
        if (is_numeric($params['time'])) {
            //小于5年定义为xx后秒执行
            $params['time'] < 63072000 && $params['time'] += $_SERVER['REQUEST_TIME'];
        //时间格式
        } else {
            //转换为数字
            $params['time'] = strtotime($params['time']);
        }

        //添加计划任务
        self::taskList($params);
    }

    /**
     * 描述 : 回调任务函数
     * 参数 : 
     *      call : task 参数格式
     * 作者 : Edgar.lee
     */
    public static function taskCall($call) {
        //系统回调参数
        $params = array(
            'call' => &$call['call'],
            'time' => &$call['time'],
            'try'  => &$call['try']
        );
        //回调失败
        if (of::callFunc($call['call'], $params) === false) {
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
                                if (!$vl[2]) {
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
                                        ($index - $vl[1]) % $vl[3] === 0
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
                        if (of_base_com_kv::add($lock, '', 60, $config['kvPool'], 1)) {
                            //读取当前任务最后执行时间戳
                            $temp = of_base_com_kv::get($tKey, 0, $config['kvPool']);

                            //对应时间整点没有执行过
                            if ($temp < $timeBox['nowTime']) {
                                //更新当前时间
                                $vt['time'] = $timeBox['nowTime'];
                                //记录成功项
                                $result[] = &$vt;
                                //记录最后更新时间戳
                                of_base_com_kv::set($tKey, $vt['time'], 86400, $config['kvPool']);
                            }

                            //解锁
                            of_base_com_kv::del($lock, $config['kvPool']);
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
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]
     *      }, ...]
     * 作者 : Edgar.lee
     */
    private static function fireCalls(&$list) {
        foreach ($list as &$v) {
            //触发任务
            of_base_com_net::request(OF_URL . '/index.php', array(), array(
                'asCall' => 'of_base_com_timer::taskCall',
                'params' => array(&$v)
            ));
        }
    }
}

of_base_com_timer::init();