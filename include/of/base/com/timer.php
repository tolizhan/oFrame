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
        $config = of::config('_of.com.timer', array()) + array(
            //存储方式
            'adapter' => 'files',
            'params'  => array()
        );
        //文件锁路径
        empty($config['path']) && $config['path'] = OF_DATA . '/_of/of_base_com_timer';
        //格式化为磁盘路径
        $config['path'] = of::formatPath($config['path'], ROOT_DIR);
        //静态任务
        $config['crontab'] = empty($config['crontab']) ? '' : of::formatPath($config['crontab'], ROOT_DIR);
        $config['adapter'] === 'mysql' && empty($config['params']['dbPool']) && $config['params']['dbPool'] = 'default';
        //嵌套创建文件夹
        is_dir($config['path']) || @mkdir($config['path'], 0777, true);
    }

    /**
     * 描述 : 定时器
     * 作者 : Edgar.lee
     */
    public static function timer($name = 'taskLock', $type = null) {
        //定时器路径
        $path = self::$config['path'];
        //打开加锁文件
        $lock = fopen($path . '/' . $name, 'a');

        //加锁失败
        if( !flock($lock, LOCK_EX | LOCK_NB) ) {
            return ;
        //主动触发(非异步)
        } else if( $type === null ) {
            //连接解锁
            flock($lock, LOCK_UN);
            //加载定时器
            of_base_com_net::request(OF_URL . '/index.php', array(), array(
                'asCall' => 'of_base_com_timer::timer',
                'params' => array($name, true)
            ));
        //任务列表遍历检查
        } else if( $name === 'taskLock' ) {
            while (true) {
                //静态计划任务
                ($crontab = &self::crontab()) && self::fireCalls($crontab);
                //动态计划任务
                ($movTask = &self::taskList(false)) && self::fireCalls($movTask);

                //无任何任务
                if( $movTask === false && $crontab === false ) {
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
        } else if( $name === 'protected' ) {
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
        if( is_numeric($params['time']) ) {
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
        if( of::callFunc($call['call'], $params) === false ) {
            //不可重试
            if( ($try = array_shift($call['try'])) === null ) {
                //达到最大尝试次数
                trigger_error('Reached the maximum number of attempts.' );
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
        //引用配置
        $config = &self::$config;

        //添加计划任务
        if( is_array($mode) ) {
            //生成md5
            $hash = md5(serialize($mode['call']));

            //文本模式
            if( $config['adapter'] === 'files' ) {
                //读取加锁源
                $fp = of_base_com_disk::file($config['path'] . '/taskList.php', null, null);
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
            if( $config['adapter'] === 'files' ) {
                //读取加锁源
                $fp = of_base_com_disk::file($config['path'] . '/taskList.php', null, null);

                //任务列表不为空 && 读取详细信息
                if (($result = ftell($fp) > 21) && $mode === false) {
                    $result = array();
                    //读取任务列表
                    $task = of_base_com_disk::file($fp, true, true);
                    //有到期任务
                    while( ($time = key($task)) && $nowtime >= $time ) {
                        foreach($task[$time] as &$callback) {
                            //引用回调
                            $result[] = &$callback;
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
                    foreach($temp as &$v) {
                        $result[] = unserialize($v['task']);
                        $v = $v['hash'];
                    }

                    $sql = 'DELETE FROM
                        `_of_com_timer`
                    WHERE
                        `hash` IN ("' .join('","', $temp). '")';
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
        $timeList = $result = array();
        $nowTime = time();

        //取整分时间
        $lastTime === null && $lastTime = $nowTime - $nowTime % 60;
        //执行间隔60s
        while( $nowTime - $lastTime > 59 ) {
            $lastTime += 60;
            $timeList[] = array(
                'nowTime' => $lastTime,
                //分时日月 星期(0-6)
                'nowNode' => explode(' ', date('i H d m w', $lastTime))
            );
        }

        if( $timeList ) {
            //最新静态任务
            is_file(self::$config['crontab']) && $crontab = include self::$config['crontab'];

            if( !empty($crontab) && is_array($crontab) ) {
                foreach($crontab as &$vt) {
                    preg_match('@(?:^|\s+)(?:(?:\d+(?:-\d+)?(?:/\d+)?|\*/\d+)(?:,|))+(?:\s+|$)@', $vt['time'], $temp, PREG_OFFSET_CAPTURE);

                    if( $index = &$temp[0] ) {
                        //每星期计划 || 不是
                        $index += strlen($vt['time']) === strlen($index[0]) + $index[1] ?
                            //替换计划
                            array('p' => '@^(\s*[^\s]+){2}@', 'r' => '1 1') : array('p' => '@[^\s]+@', 'r' => '1');

                        //修正计划
                        $vt['time'] = preg_replace($index['p'], $index['r'], substr($vt['time'], 0, $index[1])) .
                            substr($vt['time'], $index[1]);
                    }
                    //每项时间分割
                    $item = preg_split('@\s+@', $vt['time']);

                    foreach($timeList as &$timeBox) {
                        foreach($item as $ki => &$vi) {
                            $index = &$timeBox['nowNode'][$ki];
                            //每列时间集合[14-30/3]
                            preg_match_all('@(\d+|\*)(?:-(\d+))?(?:/(\d+))?(,|$)@', $vi, $list, PREG_SET_ORDER);

                            foreach($list as &$vl) {
                                //x 模式
                                if( !$vl[2] ) {
                                    $temp = $index == $vl[1] || $vl[1] === '*';
                                //大-小 模式
                                } else if( $vl[1] > $vl[2] ) {
                                    $temp = $index >= $vl[1] || $index <= $vl[2];
                                //小-大 模式
                                } else {
                                    $temp = $index >= $vl[1] && $index <= $vl[2];
                                }

                                //范围通过 && 频率通过
                                if( $temp && (!$vl[3] || $index % $vl[3] === 0) ) {
                                    //进入下一项校验
                                    continue 2;
                                }
                            }

                            //当前项校验失败
                            continue 2;
                        }

                        //更新当前时间
                        $vt['time'] = $timeBox['nowTime'];
                        //记录成功项
                        $result[] = $vt;
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
        foreach($list as &$v) {
            //触发任务
            of_base_com_net::request(OF_URL . '/index.php', array(), array(
                'asCall' => 'of_base_com_timer::taskCall',
                'params' => array(&$v)
            ));
        }
    }
}

of_base_com_timer::init();
return join('::', of::dispatch()) === 'of_base_com_timer::timer';