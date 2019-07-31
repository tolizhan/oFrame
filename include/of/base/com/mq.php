<?php
/**
 * 描述 : 消息队列封装
 * 注明 :
 *      消息队列配置结构($config) : {
 *          消息队列池名 : {
 *              "adapter" : 适配器,
 *              "params"  : 调度参数 {
 *              },
 *              "bindDb"  : 事务数据库连接池名,
 *              "queues"  : 生产消息时会同时发给队列, 字符串=该结构的配置文件路径 {
 *                  队列名 : {
 *                      "mode"   : 队列模式, null=生产及消费,false=仅生产,true=仅消费,
 *                      "check"  : 自动重载消息队列触发函数,
 *                          true=(默认)校验"消费回调"加载的文件变动,
 *                          false=仅校验队列配置文件变动,
 *                          字符串=以"@"开头的正则忽略路径(软链接使用真实路径), 如: "@/ctrl/@i"
 *                      "memory" : 单个并发未释放内存积累过高后自动重置, 单位M, 默认50, 0=不限制
 *                      "keys"   : 消费消息时回调结构 {
 *                          消息键 : 不存在的键将被抛弃 {
 *                              "cNum" : 并发数量,
 *                              "call" : 回调结构
 *                          }, ...
 *                      }
 *                  }, ...
 *              }
 *          }, ...
 *      }
 *      消息队列列表($mqList) : {
 *          事务数据库连接池名 : {
 *              "leval" : 当前数据库池等级, 0=不在事务里, 1=根事务, n=n层事务里
 *              "state" : 当前事务最终状态, true=提交, false=回滚
 *              "pools" : {
 *                  消息队列池名 : {
 *                      "leval" : 内部事务层级, 0=不在事务里, 1=根事务, n=n层事务里
 *                      "state" : 内部事务最终提交状态, true=提交, false=回滚
 *                      "inst"  : 初始化的对象
 *                      "keys"  : 队列与键对应的配置路径 {
 *                          队列名 : {
 *                              "mode" : 队列模式, null=生产及消费, false=仅生产, true=仅消费
 *                              "data" : 引用加载配置
 *                          }...
 *                      }
 *                  }, ...
 *              }
 *          }
 *      }
 *      待触发队列结构($waitMq) : {
 *          根据内容生成的主键 : {
 *              "pool"  : 消息池名
 *              "queue" : 队列名称
 *              "key"   : 对接键名
 *          }
 *      }
 * 作者 : Edgar.lee
 */
abstract class of_base_com_mq {
    //适配器参数
    protected $params = null;
    //消息队列配置
    private static $config = null;
    //消息队列列表
    private static $mqList = array();
    //待触发队列表
    private static $waitMq = array();
    //依赖根路径
    private static $mqDir = null;
    //队列监听路径
    private static $tPath = null;
    //触发时的环境变量
    private static $fireEnv = array(
        //未释放的内存(默认1M)
        'memory'   => 1048576,
        //时区
        'timezone' => null,
        //当前消息数据
        'mqData'   => null
    );

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        self::$config = of::config('_of.com.mq');
        of::event('of::halt', 'of_base_com_mq::ofHalt');

        self::$mqDir = ROOT_DIR . OF_DATA . '/_of/of_base_com_mq';
        self::$tPath = self::$mqDir . '/queueTrigger/' . md5($_SERVER['SERVER_ADDR']);
        is_dir(self::$tPath) || @mkdir(self::$tPath, 0777, true);

        //web访问开启消息队列
        if (of::dispatch('class') === 'of_base_com_mq') {
            //debug 参数
            $debug = isset($_GET['__OF_DEBUG__']) ?
                '&__OF_DEBUG__=' . $_GET['__OF_DEBUG__'] : '';
            //重新加载消息
            if ($reload = isset($_GET['type']) && $_GET['type'] === 'reload') {
                header('location: ?c=of_base_com_mq' . $debug);
            }
            //输出运行状态(并尝试开启)
            echo self::state() ? 'runing' : 'starting', " ";

            if (OF_DEBUG === false) {
                exit("<br>\nAccess denied: production mode.");
            //重启消息队列
            } else if ($reload) {
                //队列监听列表
                $path = self::$mqDir . '/queueTrigger';
                //遍历并发任务目录
                of_base_com_disk::each($path, $tasks, null);
                //遍历发送重置命令
                foreach ($tasks as $kt => &$vt) {
                    $vt && of_base_com_disk::file($kt . '/command.php', '');
                }
            //展示并发列表
            } else {
                echo '<input type="button" onclick="',
                    'window.location.href=\'?c=of_base_com_mq&type=reload',
                    $debug,
                    '\'" value="Reload the message queue">';

                echo '<pre><hr>Concurrent Running : ';

                //筛选消息队列任务
                $list = of_base_com_timer::info(1);
                foreach ($list as $k => &$v) {
                    if (
                        isset($v['call']['asCall']) &&
                        $v['call']['asCall'] === 'of_base_com_mq::fireQueue'
                    ) {
                        $v = array(
                            'fire' => &$v['call']['params'][0]['fire'],
                            'list' => &$v['list']
                        );

                        //读取消息状态信息
                        foreach ($v['list'] as $kl => &$vl) {
                            $vl['execInfo'] = of_base_com_timer::data(null, $kl, '/' . $k);
                            $vl['execInfo'] = &$vl['execInfo']['info'][$kl]['data']['_mq'];

                            if (isset($vl['execInfo']['minMemory'])) {
                                $vl['execInfo']['minMemory'] = round(
                                    $vl['execInfo']['minMemory'] / 1048576, 2
                                ) . 'M';
                            }
                        }
                    } else {
                        unset($list[$k]);
                    }
                }
                print_r($list);

                echo '</pre>';
            }
        }
    }

    /**
     * 描述 : 获取消息队列状态
     * 参数 :
     *      start : true=尝试开启消息队列, false=仅查询状态
     * 返回 :
     *      true=运行状态, false=停止状态
     * 作者 : Edgar.lee
     */
    public static function state($start = true) {
        //队列监听列表
        $path = self::$mqDir . '/queueTrigger';
        //遍历并发任务目录
        of_base_com_disk::each($path, $tasks, null);
        //最终运行状态
        $result = false;
        //判断所有监听, 一个运行便为运行
        foreach ($tasks as $kt => &$vt) {
            //是目录
            if ($vt) {
                //当前任务磁盘目录
                if (is_file($temp = $kt . '/queueLock')) {
                    //打卡并发文件流
                    $fp = fopen($temp, 'r+');

                    //任务已运行
                    if ($result = !flock($fp, LOCK_EX | LOCK_NB)) {
                        break ;
                    //任务未运行
                    } else {
                        flock($fp, LOCK_UN);
                    }

                    //关闭连接
                    fclose($fp);
                }
            }
        }

        //需要开启 && 尝试开启
        $start && self::listion('queueLock');
        return $result;
    }

    /**
     * 描述 : 设置消息队列
     * 参数 :
     *      keys : 消息定位
     *          事务操作: null=开启事务, true=提交事务, false=回滚事务
     *          生产消息: 字符串=指定消息类型, 数组=[消息类型, 消息ID, 延迟秒数]
     *      data : 消息数据
     *          事务操作: 代替 pool 参数, 指定消息队列池
     *          生产消息: null=删除 [消息类型, 消息ID] 指定的信息, 其它=消息数据
     *      pool : 消息队列池
     *          事务操作: 代替 bind 参数, 指定数据库连接池
     *          生产消息: 指定消息队列池
     *      bind : 事务数据库
     *          事务操作:  被 pool 代替, null=默认绑定, ""=内部事务
     *          生产消息: ""=绑定到内部事务, 字符串=绑定数据池同步事务
     * 作者 : Edgar.lee
     */
    public static function set($keys, $data = null, $pool = 'default', $bind = null) {
        //引用消息队列实例
        $mqList = &self::$mqList;

        //手动事务操作
        if ($keys === null || is_bool($keys)) {
            //初始化
            ($temp = func_num_args()) >= 3 && $bind = $pool;
            $temp >= 2 && $pool = $data;
            $config = &self::pool($pool, $bind);

            //内部事务
            if ($bind === '') {
                //引用当前操作的消息块
                $mqArr = &$mqList[$bind]['pools'][$pool];

                //开启事务
                if ($keys === null) {
                    //开启失败
                    if ($mqArr['leval'] === 0 && !$mqArr['inst']->_begin()) {
                        return false;
                    //开启成功
                    } else {
                        $mqArr['leval'] += 1;
                    }
                //真实提交或回滚事务
                } else if($mqArr['leval'] === 1) {
                    //true ? 提交事务 : 回滚事务
                    $temp = $keys && $mqArr['state'] ? '_commit' : '_rollBack';
                    $mqArr['inst']->$temp('before');
                    $temp = $mqArr['inst']->$temp('after');

                    //执行回滚 || 事务操作成功 && 最终提交
                    $result = $keys === false || $temp && $mqArr['state'];
                    //重置事务层级
                    $mqArr['leval'] = 0;
                    //重置最终提交状态
                    $mqArr['state'] = true;

                    return $result;
                } else if ($mqArr['leval']) {
                    $mqArr['leval'] -= 1;
                    //嵌套事务回滚 || 最终回滚
                    $keys || $mqArr['state'] = false;
                } else {
                    return false;
                }

                return true;
            //数据库事务
            } else {
                return of_db::sql($keys, $bind);
            }
        //添加消息队列
        } else {
            //待处理的消息列表
            $wMsges = array();
            //当前模块配置
            $config = &self::pool($pool, $bind);
            //待触发队列表
            $waitMq = &self::$waitMq;
            //引用当前操作的消息块
            $mqArr = &$mqList[$bind]['pools'][$pool];
            is_array($keys) || $keys = array($keys);
            isset($keys[1]) || $keys[1] = of_base_com_str::uniqid();
            isset($keys[2]) || $keys[2] = 0;

            foreach ($config['queues'] as $k => &$v) {
                //可生产数据 && 有效的键值
                if (empty($v['mode']) && isset($v['keys'][$keys[0]])) {
                    $wMsges[] = array(
                        'keys'  => &$keys,
                        'data'  => &$data,
                        'pool'  => &$pool,
                        'bind'  => &$bind,
                        'queue' => $k
                    );

                    $waitMq[md5("{$pool} {$k} {$keys[0]}")] = array(
                        'pool'  => &$pool,
                        'queue' => $k,
                        'key'   => &$keys[0]
                    );
                }
            }

            if ($wMsges) {
                //开启事务
                self::set(null, $pool, $bind);
                $temp = !!$mqArr['inst']->_sets($wMsges);
                //结束事务(成功提交 && 失败回滚) && 执行是否成功
                $temp = self::set($temp, $pool, $bind) && $temp;
                return $temp;
            } else {
                return true;
            }
        }
    }

    /**
     * 描述 : 触发队列
     * 参数 :
     *      params  : 异步触发 {
     *          "fire" : 触发目标 {
     *              "pool"  : 连接池,
     *              "queue" : 消息队列,
     *              "key"   : 消息键
     *          }
     *      }
     *      nowTask : 定时器触发数据
     * 作者 : Edgar.lee
     */
    public static function fireQueue($params, $nowTask) {
        $data = &$params['fire'];
        $data['this'] = &$nowTask['this'];
        $config = &self::pool($params['fire']['pool'], $bind);
        $config = &$config['queues'][$data['queue']];
        $fireEnv = &self::$fireEnv;

        //有效回调
        if (
            $_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR'] &&
            $call = &$config['keys'][$data['key']]['call']
        ) {
            //校验文件变动, ture=加载的文件, false=不校验, 字符串=@开头的正则白名单
            $check = &$config['check'];
            //检查内存占用峰值
            $memory = $config['memory'] * 1048576;
            //重新加载提示
            $eTip = $data['this']['cCid'] === 1 ? 'MQ auto reload' : '';
            //可以垃圾回收
            ($isGc = function_exists('gc_enable')) && gc_enable();
            //接收环境变化路径
            $path = self::$tPath . '/command.php';
            //消息队列实例对象
            $mqObj = &self::$mqList[$bind]['pools'][$data['pool']]['inst'];
            //记录默认时区
            $fireEnv['timezone'] = date_default_timezone_get();
            //重置当前并发数据
            of_base_com_timer::data(array('_mq' => array()));

            while (true) {
                $cmd = of_base_com_disk::file($path, true);
                $cmd || $cmd = array('taskPid' => '', 'compare' => '');
                isset($tPid) || $tPid = $cmd['taskPid'];

                //运行环境无变化
                if (
                    //有效任务ID && 为当前任务
                    $cmd['taskPid'] && $cmd['taskPid'] === $tPid &&
                    //!(验证文件 && 文件变动)
                    !($check && of_base_com_timer::renew($check, $eTip))
                ) {
                    //存在消息
                    if ($temp = $mqObj->_fire($call, $data)) {
                        //回收内存
                        $isGc && gc_collect_cycles();
                        //检查内存 && 未释放内存过高
                        if ($memory && $fireEnv['memory'] > $memory) {
                            //memory footprint reaches 100mb
                            trigger_error(
                                'MQ auto reload: (M)Unreleased memory takes up ' .
                                "more than {$config['memory']}MB"
                            );
                            //重置消息队列
                            break;
                        }
                    //消息为空
                    } else {
                        sleep(60);
                    }
                //当前任务失效(停止)
                } else {
                    break ;
                }
            }
        }
    }

    /**
     * 描述 : 消息队列监听, 负责启动调度消息
     * 作者 : Edgar.lee
     */
    public static function listion($name = 'queueLock', $type = null) {
        //开始监听
        if ($type === true && $name === 'queueLock') {
            //打开监听触发锁
            $tLock = fopen(self::$mqDir . '/triggerLock', 'a+');
            //加锁监听触发锁
            if (flock($tLock, LOCK_EX)) {
                //监听加锁成功
                if ($lock = self::lockListion($name)) {
                    //已绑定的监听ID
                    $tNum = array(0);
                    //命令配置路径
                    $cPath = self::$tPath . '/command.php';
                    //队列监听列表
                    $qPath = self::$mqDir . '/queueTrigger';

                    //遍历并发任务目录
                    of_base_com_disk::each($qPath, $tasks, null);
                    //读取已启动的监听数据
                    foreach ($tasks as $kt => &$vt) {
                        //是文件夹 && 不是当前监听
                        if ($vt && $kt !== self::$tPath) {
                            $fp = fopen($kt . '/queueLock', 'a+');

                            //监听开启状态
                            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                                //已绑定的监听ID
                                $temp = of_base_com_disk::file($kt . '/params.php', true, true);
                                $tNum[] = $temp['tNum'];
                            } else {
                                flock($fp, LOCK_UN);
                            }

                            fclose($fp);
                        }
                    }

                    //计算最小未绑定的监听ID
                    $tNum = array_diff(range(1, max($tNum) + 1), $tNum);
                    $tNum = reset($tNum);

                    //回写监听参数数据
                    of_base_com_disk::file(self::$tPath . '/params.php', array(
                        //监听编号
                        'tNum' => $tNum,
                        //IP地址
                        'addr' => $_SERVER['SERVER_ADDR']
                    ), true);

                    //关闭监听触发锁
                    flock($tLock, LOCK_UN);

                    //监听标志存在
                    while (true) {
                        //读取命令
                        $cmd = of_base_com_disk::file($cPath, true);
                        $cmd || $cmd = array('taskPid' => '', 'compare' => '');
                        isset($tPid) || $tPid = $cmd['taskPid'];
                        //加载最新配置文件
                        $config = of::config('_of.com.mq', array(), 4);
                        //待回调列表
                        $waitCall = array();

                        //任务ID相同
                        if ($cmd['taskPid'] === $tPid && $config) {
                            //遍历配置文件 队列池 => 参数
                            foreach ($config as $ke => &$ve) {
                                //加载外部配置文件
                                self::getQueueConfig($ve['queues'], $ke);

                                //查找待触发的回调
                                foreach ($ve['queues'] as $kq => &$vq) {
                                    //可消费
                                    if (!isset($vq['mode']) || $vq['mode']) {
                                        foreach ($vq['keys'] as $kk => &$vk) {
                                            //并发起始进程ID
                                            $temp = ($tNum - 1) * $vk['cNum'] + 1;
                                            //待回调列表
                                            $waitCall[] = array(
                                                'time' => 0,
                                                'cNum' => range(
                                                    $temp,
                                                    $temp + $vk['cNum'] - 1
                                                ),
                                                'call' => array(
                                                    'asCall' => 'of_base_com_mq::fireQueue',
                                                    'params' => array(array(
                                                        'fire' => array(
                                                            'pool'  => $ke,
                                                            'queue' => $kq,
                                                            'key'   => $kk
                                                        )
                                                    ))
                                                )
                                            );
                                        }
                                    }
                                }
                            }

                            //比对配置文件变化(更新后所有当前消息队列停止)
                            $temp = of_base_com_data::digest($config);
                            if ($cmd['compare'] !== $temp) {
                                $cmd['compare'] = $temp;
                                $cmd['taskPid'] = $tPid = of_base_com_str::uniqid();
                                of_base_com_disk::file($cPath, $cmd);
                            }

                            //激活消息队列
                            foreach ($waitCall as &$v) {
                                of_base_com_timer::task($v);
                            }

                            sleep(60);
                            //启动保护监听
                            self::listion('protected');
                        //关闭监听器
                        } else {
                            //停止在运行的消息进程
                            $config || of_base_com_disk::file($cPath, '');
                            break ;
                        }
                    }

                    //关闭锁
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            };

            //关闭监听触发锁
            flock($tLock, LOCK_UN);
            fclose($tLock);
        //成功占用监听
        } else if ($lock = self::lockListion($name)) {
            if ($type === null) {
                flock($lock, LOCK_UN);
                //加载定时器
                of_base_com_net::request(OF_URL, array(), array(
                    'asCall' => 'of_base_com_mq::listion',
                    'params' => array($name, true)
                ));
            } else if ($name === 'protected') {
                //打开连接
                $fp = fopen(self::$tPath . '/queueLock', 'a');
                //连接加锁(非阻塞) 兼容 glusterfs 网络磁盘
                while (!flock($fp, LOCK_EX | LOCK_NB)) {
                    usleep(200);
                }
                //连接解锁
                flock($fp, LOCK_UN);
                //关闭锁
                fclose($fp);

                //启动监听
                self::listion('queueLock');
            }

            //关闭锁
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 描述 : 数据库of_db::afeter事件回调
     * 作者 : Edgar.lee
     */
    public static function dbEvent($type, $params) {
        //事务操作
        if (
            $params['pool'] &&
            isset(self::$mqList[$params['pool']]) && (
                $params['sql'] === null || 
                is_bool($params['sql'])
            )
        ) {
            $nowMqList = &self::$mqList[$params['pool']];
            //同步事务等级
            $nowLeval = &$nowMqList['leval'];
            $nowState = &$nowMqList['state'];
            $preLeval = $nowLeval;
            $nowLeval = of_db::pool($params['pool'], 'level');

            if ($type === 'after') {
                //最后提交或回滚
                if (is_bool($params['sql']) && $preLeval === 1 && $nowLeval === 0) {
                    //提交事务 && 提交成功 ? 提交适配器 : 回滚适配器
                    $tFunc = $params['sql'] && $params['result'] ?
                        '_commit' : '_rollBack';
                //开启事务
                } else if (
                    $params['sql'] === null &&
                    $preLeval === 0 &&
                    $nowLeval === 1
                ) {
                    $tFunc = '_begin';
                    $nowState = true;
                } else {
                    return ;
                }
            } else if ($nowLeval === 1 && is_bool($params['sql'])) {
                $tFunc = $params['sql'] ? '_commit' : '_rollBack';
            } else {
                return ;
            }

            //批量触发事务
            foreach ($nowMqList['pools'] as $k => &$v) {
                $temp = $v['inst']->$tFunc($type);
                $nowState && $nowState = $temp;
            }

            //提交事务前 && 消息队列执行失败
            if ($type === 'before' && !$nowState) {
                //强制主事务回滚, 保持数据一致性
                of_db::pool($params['pool'], 'state', false);
            }
        }
    }

    /**
     * 描述 : 框架 of::halt 事件回调
     * 作者 : Edgar.lee
     */
    public static function ofHalt() {
        //有新的队列 && 启动监听
        self::$waitMq && self::listion('queueLock');
        //回调函数意外退出
        self::$fireEnv['mqData'] === null || trigger_error(
            'MQ auto reload: (Q)Callback function "exit" unexpectedly. - ' .
            print_r(self::$fireEnv['mqData'], true)
        );
    }

    /**
     * 描述 : 触发时具体方法回调
     *     &call : 框架标准回调结构
     *     &data : 传入到 [_] 位置的参数 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *          "msgId" : 消息ID
     *          "count" : 调用计数, 首次为 1
     *          "data"  : 消息数据
     *      }
     * 作者 : Edgar.lee
     */
    protected static function &callback(&$call, &$data) {
        //运行次数
        static $count = 0;
        //引用环境
        $fireEnv = &self::$fireEnv;

        //记录消息数据
        $fireEnv['mqData'] = &$data;
        //生成并发日志
        $cLog = array(
            'msgId'     => &$data['msgId'],
            'startTime' => date('Y-m-d H:i:s', time()),
            'doneTime'  => '',
            'runCount'  => ++$count,
            'minMemory' => &$fireEnv['memory']
        );
        //记录监听数据
        of_base_com_timer::data(array('_mq' => &$cLog));

        try {
            //处理消息
            $result = &of::callFunc($call, $data);
        } catch (Exception $e) {
            $result = false;
            of::event('of::error', true, $e);
        }

        //恢复永不超时
        ini_set('max_execution_time', 0);
        //恢复默认时区
        date_default_timezone_set($fireEnv['timezone']);
        //清空消息数据
        $fireEnv['mqData'] = &$null;
        //修改并发日志
        $cLog['doneTime'] = date('Y-m-d H:i:s', time());
        //记录当前内存
        $cLog['minMemory'] = memory_get_peak_usage();
        //记录监听数据
        of_base_com_timer::data(array('_mq' => &$cLog));

        //(执行结果为false && 每5次报错) || (非布尔 && 非数字)
        if (
            $result === false && $data['count'] % 5 === 0 ||
            !is_bool($result) && !is_int($result)
        ) {
            //克隆回调数组
            $func = json_decode(json_encode($call), true);
            if (is_array($call)) {
                if (isset($call[0])) {
                    $temp = array(&$func[0], &$call[0]);
                } else if (is_array($call['asCall'])) {
                    $temp = array(&$func['asCall'][0], &$call['asCall'][0]);
                } else {
                    $temp = array(&$func['asCall'], &$call['asCall']);
                }
            } else {
                $temp = array(&$func, &$call);
            }
            //对象转换成易读方式
            is_object($temp[1]) && $temp[0] = 'new ' . get_class($temp[1]);

            trigger_error(
                'Failed to consume message from queue: ' .
                var_export($result, true) . "\n\n" .
                'call--' . print_r($func, true) . "\n\n" .
                'argv--' . print_r($data, true)
            );
        }

        return $result;
    }

    /**
     * 描述 : 获取队列池
     * 参数 :
     *     &pool : 消息队列池
     *     &bind : 事务数据库
     * 返回 :
     *      消息队列配置结构 config[pool]
     * 作者 : Edgar.lee
     */
    private static function &pool(&$pool, &$bind) {
        //引用消息池配置
        $config = &self::$config;
        //引用消息队列实例
        $mqList = &self::$mqList;

        if (isset($config[$pool])) {
            //初始化数据
            $config[$pool] += array('bindDb' => '');
            //使用默认绑定事务
            $bind === null && $bind = $config[$pool]['bindDb'];
            //引用当前操作的消息块
            $mqArr = &$mqList[$bind]['pools'][$pool];

            //绑定事务初始化
            if (!isset($mqList[$bind]['leval'])) {
                $mqList[$bind]['leval'] = of_db::pool($bind, 'level');
                $mqList[$bind]['state'] = of_db::pool($bind, 'state');
                of::event('of_db::before', array(
                    'asCall' => 'of_base_com_mq::dbEvent',
                    'params' => array('before')
                ));
                of::event('of_db::after', array(
                    'asCall' => 'of_base_com_mq::dbEvent',
                    'params' => array('after')
                ));
            }

            //初始化消息队列
            if (empty($mqArr['inst'])) {
                self::getQueueConfig($config[$pool]['queues'], $pool);

                //加载消息键
                foreach ($config[$pool]['queues'] as $k => &$v) {
                    $v += array(
                        //默认校验加载的文件变动
                        'check'  => true,
                        //检查内存占用峰值
                        'memory' => 50,
                    );
                    //记录消息队列列表数据
                    $mqArr['keys'][$k] = array(
                        'mode' => &$v['mode'],
                        'data' => &$v['keys']
                    );
                }

                //内部事务层级
                $mqArr['leval'] = 0;
                //最终提交状态
                $mqArr['state'] = true;

                //初始化适配器
                $mqArr['inst'] = 'of_accy_com_mq_' . $config[$pool]['adapter'];
                $mqArr['inst'] = new $mqArr['inst'];
                $mqArr['inst']->params = $config[$pool];
                $mqArr['inst']->_init(array(
                    'pool' => $pool,
                    'bind' => $bind
                ));

                //绑定事务已开启
                if ($mqList[$bind]['leval']) {
                    //开始适配器事务
                    $temp = $mqArr['inst']->_begin();
                    //最终提交 && 赋值消息队列开始事务结果
                    $mqList[$bind]['state'] && $mqList[$bind]['state'] = $temp;
                }
            }

            return $config[$pool];
        } else {
            //指定的消息队列连接无效
            trigger_error('Did not find the specified message exchange : ' . $pool);
            exit;
        }
    }

    /**
     * 描述 : 打开并尝试加锁监听
     * 返回 :
     *      成功返回IO流, 失败返回false
     * 作者 : Edgar.lee
     */
    private static function lockListion(&$name = 'queueLock') {
        $fp = fopen(self::$tPath . "/{$name}", 'a+');

        //成功加锁
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            return $fp;
        //加锁失败
        } else {
            fclose($fp);
            return false;
        }
    }

    /**
     * 描述 : 获取队列配置
     * 参数 :
     *     &path : 队列配置文件路径
     *     &pool : 队列连接池
     * 作者 : Edgar.lee
     */
    private static function getQueueConfig(&$config, &$pool) {
        //加载最新队列配置
        $config = include ROOT_DIR . $config;

        if (
            //可能是 {队列池:{队列名:{}, ...}} 方式
            isset($config[$pool]) &&
            //获取第一个队列成功
            ($temp = current($config[$pool])) &&
            //队列中 mode 为 null 或 bool
            (!isset($temp['mode']) || is_bool($temp['mode']))
        ) {
            //{队列池:{队列名:{}, ...}} 转成 {队列名:{}, ...}
            $config = $config[$pool];
        }
    }

    /**
     * 描述 : 初始队列
     * 参数 :
     *      fire : {
     *          "pool" : 消息的队列池
     *          "bind" : 绑定的数据库池
     *      }
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    abstract protected function _init($fire);

    /**
     * 描述 : 设置消息
     * 参数 :
     *     &msgs : 需要设置的消息集合 [{
     *          "keys"  : 消息定位 [消息类型, 消息主键],
     *          "data"  : 消息数据, null=删除 keys 指定的信息, 其它=消息数据
     *          "pool"  : 指定消息队列池,
     *          "bind"  : ""=绑定到手动事务, 字符串=绑定数据池同步事务
     *          "queue" : 队列名称
     *      }, ...]
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    abstract protected function _sets(&$msgs);

    /**
     * 描述 : 触发消息队列, 根据回调响应值执行对应动作
     * 参数 :
     *     &call : 符合回调结构
     *     &data : 需要设置的消息集合, call的回调参数 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "data"  :x消息数据, _fire 函数实现
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *      }
     * 返回 :
     *      [{
     *          "result" : 响应结果
     *              true=成功, 删除队列
     *              false=失败, 稍后重试
     *              数字=指定秒数后重试
     *              其它=抛出错误, 稍后重试
     *          "count"  : 调用计数, result为 false, 数字时每5次报错一次
     *          "params" : 调用参数
     *      }, ...]
     * 作者 : Edgar.lee
     */
    abstract protected function _fire(&$calll, &$data);

    /**
     * 描述 : 开启事务
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    abstract protected function _begin();

    /**
     * 描述 : 提交事务
     * 参数 :
     *      type : "before"=提交开始回调, "after"=提交结束回调
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    abstract protected function _commit($type);

    /**
     * 描述 : 事务回滚
     * 参数 :
     *      type : "before"=回滚开始回调, "after"=回滚结束回调
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    abstract protected function _rollBack($type);
}

of_base_com_mq::init();