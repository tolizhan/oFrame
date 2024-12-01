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
 *                              "lots" : 批量消费, 1=单条消费, >1=一次消费最多数量(消息变成一维数组)
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
 *              "isSet" : 执行过队列信息, true=调用过set, false=未调用过
 *              "level" : 当前数据库池等级, 0=不在事务里, 1=根事务, n=n层事务里
 *              "state" : 当前事务最终状态, true=提交, false=回滚
 *              "pools" : {
 *                  消息队列池名 : {
 *                      "inst" : 初始化的对象
 *                      "keys" : 队列与键对应的配置路径 {
 *                          队列名 : {
 *                              "mode" : 队列模式, null=生产及消费, false=仅生产, true=仅消费
 *                              "data" : 引用加载配置
 *                          }...
 *                      }
 *                      "msgs" : 待处理消息列表 {
 *                          消息唯一标识"队列名\0类型\0消息ID" : {
 *                              "keys"  : [类型, 消息ID, 延迟]
 *                              "data"  : null
 *                              "queue" : 队列名
 *                          }, ...
 *                      }
 *                  }, ...
 *              }
 *          }
 *      }
 *      键值结构 : 已"of_base_com_mq::"为前缀的键名
 *          "nodeList" : 完整分布式节点(永不过期), 记录不同"_of.nodeName"节点, 失效时定期清理 {
 *              节点ID : 节点信息 {
 *                  "tNum" : 队列位置,
 *              }, ...
 *          }
 *      加锁逻辑 : 已"of_base_com_mq::"为前缀
 *          "nodeList" : 当新插入或清理节点时加独享锁
 *          nodeLock#节点ID : 节点进程, 启动时独享锁
 *          daemon#节点ID : 守护进程, 启动时独享锁

 *      磁盘结构 : {
 *          "/failedMsgs"   : 失败的消息列表 {
 *              /连接池名 : {
 *                  md5(队列名\0消息键\0消息ID).php 文件,
 *                  ...
 *              }
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class of_base_com_mq {
    //适配器参数
    protected $params = null;
    //消息队列配置
    private static $config = null;
    //消息队列列表
    private static $mqList = array();
    //待触发队列表
    private static $fireMq = false;
    //依赖根路径
    private static $mqDir = null;
    //当前节点ID
    private static $nodeId = '';
    //触发时变量 {"memory" : 最大内存, "timezone" : 当前时区, "mqData" : 当前消息, "mqClass" : 消费类名}
    private static $fireEnv = null;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        self::$config = of::config('_of.com.mq');
        self::$mqDir = ROOT_DIR . OF_DATA . '/_of/of_base_com_mq';
        //初始节点ID
        ($nodeName = of::config('_of.nodeName')) && self::$nodeId = md5($nodeName);

        of::event('of::halt', 'of_base_com_mq::ofHalt');
        of::event('of_db::rename', array(
            'asCall' => 'of_base_com_mq::dbEvent',
            'params' => array('rename')
        ));
        of::event('of_db::before', array(
            'asCall' => 'of_base_com_mq::dbEvent',
            'params' => array('before')
        ));
        of::event('of_db::after', array(
            'asCall' => 'of_base_com_mq::dbEvent',
            'params' => array('after')
        ));
    }

    /**
     * 描述 : 控制台页面
     * 作者 : Edgar.lee
     */
    public function index() {
        //debug 参数
        $debug = isset($_GET['__OF_DEBUG__']) ?
            '&__OF_DEBUG__=' . $_GET['__OF_DEBUG__'] : '';
        //重新加载消息
        if ($reload = isset($_GET['type']) && $_GET['type'] === 'reload') {
            header('location: ?c=of_base_com_mq' . $debug);
        }
        //输出运行状态(并尝试开启)
        echo self::state() ? 'running' : 'starting', " ";

        if (OF_DEBUG === false) {
            exit("<br>\nAccess denied: production mode.");
        //重启消息队列
        } else if ($reload) {
            //读取全局节点列表
            $nodes = of_base_com_kv::get('of_base_com_mq::nodeList', array(), '_ofSelf');
            //遍历发送重置命令
            foreach ($nodes as $kt => &$vt) {
                of_base_com_kv::del('of_base_com_mq::command::' . basename($kt), '_ofSelf');
            }
        //展示并发列表
        } else {
            //永不超时
            ini_set('max_execution_time', 0);
            //显示重启按钮
            echo '<input type="button" onclick="',
                'window.location.href=\'?c=of_base_com_mq&type=reload',
                $debug,
                '\'" value="Reload the message queue"><pre>';

            //显示异常队列池
            if ($list = self::getFailPools(null)) {
                echo 'Failed queue(', OF_DATA, '/_of/of_base_com_mq/failedMsgs): ',
                    '<font color="red">/', join(', /', $list), '</font>';
            }

            //显示运行中队列
            echo '<hr>Concurrent Running : ';

            //消费超过24小时数量
            $nums = 0;
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
                        //为节省磁盘性能不用 of_base_com_timer::data(true, array($kl), '/' . $k);
                        $temp = 'of_base_com_timer::data-' . $k . '.' . $kl;
                        $temp = of_base_com_kv::get($temp, array(), '_ofSelf');
                        $index = &$temp['_mq'];

                        //格式化执行信息
                        if ($index === null) {
                            $vl['execInfo'] = '<font color=red>Run for more than 24 hours</font>';
                            //统计超长消费数量
                            $nums += 1;
                        } else {
                            $vl['execInfo'] = &$index;

                            //存在执行信息
                            if (isset($index['useMemory'])) {
                                //内存转化成M单位
                                $index['useMemory'] = round(
                                    $index['useMemory'] / 1048576, 2
                                ) . 'M';
                                //单条消费格式结构
                                is_array($index['msgId']) && (
                                    isset($index['msgId'][1]) ?
                                        $index['msgId'] = json_encode($index['msgId']) :
                                        $index['msgId'] = &$index['msgId'][0]
                                );
                                //方便查询运行中队列
                                $index['doneTime'] || $index['doneTime'] = '--';
                                //删除异常消息数据
                                unset($index['quitData']);
                            }
                        }
                    }
                } else {
                    unset($list[$k]);
                }
            }

            //打印队列信息
            $nums && print_r("<font color=red>Exception({$nums})</font> ");
            print_r($list);

            echo '</pre>';
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
        //空监听路径(空节点ID)不触发队列监听路径
        if (!$nodeId = &self::$nodeId) return true;
        //节点进程锁
        $nodeLock = 'of_base_com_mq::nodeLock#' . $nodeId;
        //当前节点是否启动, 未启动解锁
        ($result = !of_base_com_data::lock($nodeLock, 6)) || of_base_com_data::lock($nodeLock, 3);
        //需要开启 && 尝试开启
        $result || $start && self::listen('nodeLock');
        //返回结果
        return $result;
    }

    /**
     * 描述 : 设置消息队列
     * 参数 :
     *      事务操作 :
     *          keys : null=开启事务, true=提交事务, false=回滚事务
     *          data : 指定消息队列池
     *          pool : 指定数据库连接池
     *      生产消息, 单条模式 :
     *          keys : 字符串=指定消息类型, 数组=[消息类型, 消息ID, 延迟秒数]
     *          data : null=删除 [消息类型, 消息ID] 指定的信息, 其它=消息数据
     *          pool : 指定消息队列池
     *          bind : ""=绑定到内部事务, 字符串=绑定数据池同步事务
     *      生产消息, 批量模式 :
     *          keys : 批量消息, [{"keys" : 单条模式keys结构, "data" : 单条模式data结构}]
     *          data : 指定数据库连接池
     * 返回 :
     *      事务操作 : 成功=true, 失败=false
     *      消息操作 : 成功=数组结果, 失败=false
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
                //引用当前消息队列
                $nowMqList = &$mqList[''];
                //引用当前操作的消息块
                $mqArr = &$mqList[$bind]['pools'][$pool];

                //开启事务
                if ($keys === null) {
                    //开启失败
                    if ($nowMqList['level'] === 0 && !$mqArr['inst']->_begin()) {
                        return false;
                    //开启成功
                    } else {
                        $nowMqList['level'] += 1;
                    }
                //真实提交或回滚事务
                } else if ($nowMqList['level'] === 1) {
                    //true ? 提交事务 : 回滚事务
                    $func = $keys && $nowMqList['state'] ? '_commit' : '_rollBack';
                    //提交回滚准备, 失败后$nowMqList['state']设为false
                    self::execMqObjTran('before', '', $func);
                    //true ? 提交事务 : 回滚事务
                    $func = $keys && $nowMqList['state'] ? '_commit' : '_rollBack';

                    //在工作结束后执行消息写入, 事务为提交 && 在工作中
                    if ($func === '_commit' && $call = of::work('info', 4)) {
                        $call['done'] = array('of_base_com_mq::execMqObjTran-' => array(
                            'onWork' => null,
                            'asCall' => 'of_base_com_mq::execMqObjTran',
                            'params' => array('after', '', '_commit')
                        )) + $call['done'];
                    //直接执行消息事务
                    } else {
                        self::execMqObjTran('after', '', $func);
                    }

                    //执行回滚 || 事务操作成功 && 最终提交
                    $result = $keys === false || $nowMqList['state'];
                    //重置事务层级
                    $nowMqList['level'] = 0;
                    //重置最终提交状态
                    $nowMqList['state'] = true;

                    return $result;
                } else if ($nowMqList['level']) {
                    $nowMqList['level'] -= 1;
                    //嵌套事务回滚 || 最终回滚
                    $keys || $nowMqList['state'] = false;
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
            //批量操作模式
            if (is_array($keys[0])) {
                //初始化参数
                ($temp = func_num_args()) >= 3 && $bind = $pool;
                $temp >= 2 && $pool = $data;
            //格式为批量操作
            } else {
                $keys = array(array('keys' => $keys, 'data' => &$data));
            }

            //数组转换成变量
            is_array($pool) && extract($pool, EXTR_OVERWRITE | EXTR_REFS);
            //待处理的消息列表
            $wMsges = $moveMq = array();
            //当前模块配置
            $config = &self::pool($pool, $bind);
            //待触发队列表
            $fireMq = &self::$fireMq;
            //引用当前操作的消息块
            $mqArr = &$mqList[$bind]['pools'][$pool];
            //引用待处理消息列表
            $msgs = &$mqArr['msgs'];

            //批量创建消息
            foreach ($keys as &$vk) {
                //格式化消息键
                $key = &$vk['keys'];
                is_array($key) || $key = array($key);
                //指定了消息ID
                $isFxId = isset($key[1]);
                //是否迁移消息
                $isMove = $isFxId && isset($config['moveMq']);
                //生成随机ID
                $isFxId || $key[1] = of_base_com_str::uniqid();
                //默认延迟时间
                isset($key[2]) || $key[2] = 0;

                //同步的队列
                $queues = isset($vk['queue']) ?
                    (isset($config['queues'][$vk['queue']]) ? array(
                        $vk['queue'] => &$config['queues'][$vk['queue']]
                    ) : array()) : $config['queues'];

                //消息赋值到各队列
                foreach ($queues as $k => &$v) {
                    //可生产数据 && 有效的键值
                    if (empty($v['mode']) && isset($v['keys'][$key[0]])) {
                        //生成处理消息
                        $wMsges[] = array(
                            'keys'  => &$key,
                            'data'  => &$vk['data'],
                            //true=随机ID, false=指定ID
                            'unid'  => !$isFxId,
                            'pool'  => &$pool,
                            'bind'  => &$bind,
                            'queue' => $k
                        );

                        //指定了消息ID
                        if ($isFxId) {
                            //记录到容灾消息中
                            $msgs[$temp = "{$k}\0{$key[0]}\0{$key[1]}"] = array(
                                'keys'  => &$key,
                                'data'  => null,
                                'queue' => $k
                            );
                            //启动了迁移消息 && 记录到迁移队列
                            $isMove && $moveMq[] = $msgs[$temp];
                        }
                    }
                }
            }

            if ($wMsges) {
                //标记监听触发
                $fireMq = true;
                //开启事务
                self::set(null, $pool, $bind);
                //标记调用set
                $mqList[$bind]['isSet'] = true;

                //迁移队列 && 通过死循环检查
                if ($moveMq && !isset($move[$config['moveMq']])) {
                    //加入迁移清单
                    $move[$pool] = $move[$config['moveMq']] = true;
                    //向迁移队列发送删除通知
                    self::set($moveMq, array(
                        'pool' => &$config['moveMq'],
                        'bind' => &$bind,
                        'move' => &$move
                    ));
                }

                //设置消息队列
                $temp = !!$mqArr['inst']->_sets($wMsges);
                //结束事务(成功提交 && 失败回滚) && 执行是否成功
                $temp = self::set($temp, $pool, $bind) && $temp;
                //成功返回 ? 数组结果 : false
                return $temp ? array('setMsges' => $wMsges) : false;
            } else {
                return array('setMsges' => array());
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
        $config = &self::pool($data['pool'], $bind);
        $config = &$config['queues'][$data['queue']];
        $thisMq = &$config['keys'][$data['key']];
        $fireEnv = &self::$fireEnv;

        //有效回调
        if ($thisMq['cNum'] > 0 && $call = &$thisMq['call']) {
            //批量消费数量
            ($data['lots'] = &$thisMq['lots']) < 1 && $data['lots'] = 1;
            //校验文件变动, ture=加载的文件, false=不校验, 字符串=@开头的正则白名单
            $check = &$config['check'];
            //检查内存占用峰值
            $memory = $config['memory'] * 1048576;
            //当前并发ID
            $cCid = $data['this']['cCid'];
            //重置异常消息数据, 不用直等是为了防止多副本启动时间不同导致无法正常重置
            $isFix = $cCid % $thisMq['cNum'] === intval($thisMq['cNum'] > 1);
            //可以垃圾回收
            ($isGc = function_exists('gc_enable')) && gc_enable();
            //接收环境变化键名
            $cKey = 'of_base_com_mq::command::' . md5(of::config('_of.nodeName'));
            //接收队列变化键名
            $qKey = "{$cKey}::{$data['key']}.{$data['queue']}.{$data['pool']}";
            //消息队列实例对象
            $mqObj = &self::$mqList[$bind]['pools'][$data['pool']]['inst'];
            //初始化环境变量
            $fireEnv = array(
                //未释放的内存(默认1M)
                'memory'   => 1048576,
                //时区
                'timezone' => date_default_timezone_get(),
                //当前消息数据
                'mqData'   => null,
                //处理消息的类名
                'mqClass'  => get_class($mqObj),
                //处理消息的对象
                'mqObj'    => &$mqObj
            );

            //安装信号触发器
            of_base_com_timer::exitSignal();
            //重置自身消息数据
            self::resetPaincMqData(null);
            //重置未启动消息数据
            $isFix && self::resetPaincMqData(2);

            while (true) {
                $cmd = of_base_com_kv::get($cKey, array('taskPid' => ''), '_ofSelf');
                $qmd = of_base_com_kv::get($qKey, array('taskPid' => '', 'compare' => ''), '_ofSelf');
                isset($mark) || $mark = $qmd['compare'];

                //运行环境无变化
                if (
                    //有效队列ID
                    $mark &&
                    //队列ID未变
                    $qmd['compare'] === $mark &&
                    //有效任务ID
                    $qmd['taskPid'] === $cmd['taskPid'] &&
                    //当前并发ID有效
                    $qmd['cNumMin'] <= $cCid &&
                    //当前并发ID有效
                    $qmd['cNumMax'] >= $cCid &&
                    //!(验证文件 && 文件变动)
                    !($check && of_base_com_timer::renew($check))
                ) {
                    //检查退出信号
                    of_base_com_timer::exitSignal();

                    //存在消息
                    if ($mqObj->_fire($call, $data)) {
                        //回收内存
                        $isGc && gc_collect_cycles();
                        //检查内存 && 未释放内存过高
                        if ($memory && $fireEnv['memory'] > $memory) {
                            of::event('of::error', true, array(
                                'type' => "{$data['key']}.{$data['queue']}.{$data['pool']}",
                                'code' => E_USER_ERROR,
                                'info' => 'MQ auto reload: (M)Unreleased memory takes up ' .
                                    "more than {$config['memory']}MB"
                            ));
                            //重置消息队列
                            break;
                        }
                    //消息为空
                    } else {
                        sleep(1);
                        //保持_mq运行数据kv有效期
                        of_base_com_timer::data(array());
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
    public static function listen($name = 'nodeLock', $type = null) {
        //空监听路径(空节点ID)不触发队列监听路径
        if (!$nodeId = &self::$nodeId) return ;
        //节点进程锁
        $lock = "of_base_com_mq::{$name}#{$nodeId}";

        //开始监听
        if ($type === true && $name === 'nodeLock') {
            //防止self::restoreMsgs恢复时内存溢出
            ini_set('memory_limit', -1);

            //全局节点列表键
            $listKey = 'of_base_com_mq::nodeList';
            //加锁全局节点列表
            of_base_com_data::lock($listKey, 2);

            //监听加锁成功
            if (of_base_com_data::lock($lock, 6)) {
                //已绑定的监听ID
                $tNum = array(0);
                //命令配置键名
                $cKey = 'of_base_com_mq::command::' . md5(of::config('_of.nodeName'));
                //失败消息列表
                $fPath = self::$mqDir . '/failedMsgs';
                //读取全局节点列表
                $nodes = of_base_com_kv::get($listKey, array(), '_ofSelf');

                //读取已启动的监听数据
                foreach ($nodes as $kt => &$vt) {
                    //是文件夹 && 不是当前监听
                    if ($kt !== $nodeId) {
                        //节点进程锁
                        $nodeLock = 'of_base_com_mq::nodeLock#' . $kt;
                        //队列监听未启动
                        if (of_base_com_data::lock($nodeLock, 6)) {
                            //解除节点进程锁
                            of_base_com_data::lock($nodeLock, 3);
                            //清理未启动队列
                            unset($nodes[$kt]);
                        //队列监听已启动
                        } else {
                            //已绑定的监听ID
                            $tNum[] = $vt['tNum'];
                        }
                    }
                }

                //计算最小未绑定的监听ID
                $tNum = array_diff(range(1, max($tNum) + 1), $tNum);
                $tNum = reset($tNum);
                //回写监听参数数据
                $nodes[$nodeId] = array('tNum' => $tNum);

                //回写全局节点列表(永不过期)
                of_base_com_kv::set($listKey, $nodes, 0, '_ofSelf');
                //解锁全局节点列表
                of_base_com_data::lock($listKey, 3);
                //安装信号触发器
                of_base_com_timer::exitSignal();
                //停止在运行的消息进程
                of_base_com_kv::del($cKey, '_ofSelf');

                //此变量重用做存储队列运行的摘要
                $nodes = array();
                //监听标志存在
                while (!of_base_com_timer::renew()) {
                    //读取命令
                    $cmd = of_base_com_kv::get($cKey, array('taskPid' => ''), '_ofSelf');
                    $cmd['taskPid'] || $cmd['taskPid'] = of_base_com_str::uniqid();
                    isset($tPid) || $tPid = $cmd['taskPid'];
                    of_base_com_kv::set($cKey, $cmd, 86400, '_ofSelf');
                    //加载最新配置文件
                    $config = of::config('_of.com.mq', array(), 4);
                    //待回调列表
                    $waitCall = array();
                    //失败队列路径
                    $failDirs = array();

                    //任务ID相同
                    if ($cmd['taskPid'] === $tPid && $config) {
                        //遍历配置文件 队列池 => 参数
                        foreach ($config as $ke => &$ve) {
                            //加载外部配置文件
                            self::getQueueConfig($ve['queues'], $ke);

                            //查找待触发的回调
                            foreach ($ve['queues'] as $kq => &$vq) {
                                //记录有效失败队列目录
                                $failDirs["{$fPath}/{$ke}/{$kq}"] = $ke;

                                //可消费
                                if (!isset($vq['mode']) || $vq['mode']) {
                                    foreach ($vq['keys'] as $kk => &$vk) {
                                        //并发起始进程ID
                                        $cMin = ($tNum - 1) * $vk['cNum'] + 1;
                                        //并发结束进程ID
                                        $cMax = $cMin + $vk['cNum'] - 1;

                                        //待回调列表
                                        $vk['cNum'] > 0 && $waitCall[] = array(
                                            'time' => 0,
                                            'cNum' => range($cMin, $cMax),
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

                                        //计算队列摘要值
                                        $nodes["{$kk}.{$kq}.{$ke}"] = array(
                                            'taskPid' => $tPid,
                                            'compare' => of_base_com_data::digest(array(
                                                'queues' => array(
                                                    $kq => array(
                                                        'keys' => array(
                                                            $kk => array('cNum' => 0) + $vk
                                                        )
                                                    ) + $vq
                                                )
                                            ) + $ve),
                                            'cNumMin' => $cMin,
                                            'cNumMax' => $cMax
                                        );
                                    }
                                }
                            }
                        }

                        //给已运行的队列退出或保持运行信号
                        foreach ($nodes as $k => &$v) {
                            //本次队列保持运行, 存储比对信息
                            if ($v) {
                                of_base_com_kv::set("{$cKey}::{$k}", $v, 86400, '_ofSelf');
                                //假定下次退出, 若下次配置中不存在或变动则真退出
                                $v = null;
                            //本次队列退出运行
                            } else {
                                of_base_com_kv::del("{$cKey}::{$k}", '_ofSelf');
                                unset($nodes[$k]);
                            }
                        }

                        //30秒后没退出信号则激活消息队列, 目的是等待已有进程可能的退出
                        sleep(30);

                        //激活消息队列
                        foreach ($waitCall as &$v) {
                            of_base_com_timer::task($v);
                        }

                        //30秒后没退出信号则启动保护进程
                        sleep(30);
                        //检查退出信号
                        of_base_com_timer::exitSignal();
                        //启动保护监听
                        self::listen('daemon');
                        //恢复失败消息
                        self::restoreMsgs($failDirs);
                    //关闭监听器
                    } else {
                        break ;
                    }
                }

                //停止在运行的消息进程
                of_base_com_kv::del($cKey, '_ofSelf');
                //关闭锁
                of_base_com_data::lock($lock, 3);
            } else {
                //解锁全局节点列表
                of_base_com_data::lock($listKey, 3);
            }
        //成功占用监听
        } else if (of_base_com_data::lock($lock, 6)) {
            if ($type === null) {
                //关闭锁
                of_base_com_data::lock($lock, 3);
                //加载定时器
                of_base_com_timer::task(array(
                    'call' => array(
                        'asCall' => 'of_base_com_mq::listen',
                        'params' => array($name, true)
                    )
                ));
            //$name === 'daemon'
            } else {
                //恢复linux进程对SIGTERM信号处理
                function_exists('pcntl_signal') && pcntl_signal(15, SIG_DFL);
                //节点进程锁
                $nodeLock = 'of_base_com_mq::nodeLock#' . $nodeId;
                //连接加锁(非阻塞) 兼容 glusterfs 网络磁盘
                while (!of_base_com_timer::renew() && !of_base_com_data::lock($nodeLock, 6)) {
                    sleep(30);
                }
                //连接解锁
                of_base_com_data::lock($nodeLock, 3);
                //关闭锁
                of_base_com_data::lock($lock, 3);
                //启动监听
                self::listen('nodeLock');
            }
        }
    }

    /**
     * 描述 : 数据库of_db事件回调
     * 作者 : Edgar.lee
     */
    public static function dbEvent($type, $params) {
        //引用消息队列列表
        $mqList = &self::$mqList;

        //改名操作
        if ($type === 'rename') {
            //不是内部事务(""定义为内部事务) && 列表存在
            if ($params['oName'] && isset($mqList[$params['oName']])) {
                $mqList[$params['nName']] = &$mqList[$params['oName']];
                unset($mqList[$params['oName']]);
            }
        //事务操作
        } else if (
            $params['pool'] &&
            isset($mqList[$params['pool']]) &&
            ($params['sql'] === null || is_bool($params['sql']))
        ) {
            $nowMqList = &$mqList[$params['pool']];
            //同步事务等级
            $nowLevel = &$nowMqList['level'];
            $nowState = &$nowMqList['state'];
            $preLevel = $nowLevel;
            $nowLevel = of_db::pool($params['pool'], 'level');

            if ($type === 'after') {
                //最后提交或回滚
                if (is_bool($params['sql']) && $preLevel === 1 && $nowLevel === 0) {
                    //提交事务 && 提交成功 ? 提交适配器 : 回滚适配器
                    $tFunc = $params['sql'] && $params['result'] ?
                        '_commit' : '_rollBack';
                //开启事务
                } else if (
                    $params['sql'] === null &&
                    $preLevel === 0 &&
                    $nowLevel === 1
                ) {
                    $tFunc = '_begin';
                    $nowState = true;
                } else {
                    return ;
                }
            } else if ($nowLevel === 1 && is_bool($params['sql'])) {
                $tFunc = $params['sql'] ? '_commit' : '_rollBack';
            } else {
                return ;
            }

            //在工作结束后执行消息写入, 事务为提交 && 提交二阶段 && 在工作中
            if ($tFunc === '_commit' && $type === 'after' && $call = of::work('info', 4)) {
                //执行过队列信息 && 注入工作结束回调
                $nowMqList['isSet'] && $call['done'] = array(
                    'of_base_com_mq::execMqObjTran-' . $params['pool'] => array(
                        'onWork' => null,
                        'asCall' => 'of_base_com_mq::execMqObjTran',
                        'params' => array($type, $params['pool'], '_commit')
                    )
                ) + $call['done'];
                //标记未执行消息状态
                $nowMqList['isSet'] = false;
            //直接执行消息事务
            } else {
                self::execMqObjTran($type, $params['pool'], $tFunc);
            }
        }
    }

    /**
     * 描述 : 调用消息事务
     * 作者 : Edgar.lee
     */
    public static function execMqObjTran($type, $pool, $func) {
        //引用当前消息队列
        $nowMqList = &self::$mqList[$pool];
        //当前事务最终状态
        $nowState = &$nowMqList['state'];
        //是否执行异常清理
        $isClear = $func === '_commit' && $type === 'after';
        //失败消息磁盘路径
        $isClear && $path = self::$mqDir . '/failedMsgs';

        //批量触发事务
        foreach ($nowMqList['pools'] as $kp => &$vp) {
            //事务提交之后执行失败消息的清理
            if ($isClear && isset($vp['msgs'])) {
                //非正常状态, 0=未知状态, -1=无失败消息, 1=有失败信息
                if (of_base_com_kv::get('of_base_com_mq::failed::' . $kp, 0, '_ofSelf') >= 0) {
                    //屏蔽错误
                    $errNo = error_reporting(0);
                    //清理失败消息
                    foreach ($vp['msgs'] as $k => &$v) {
                        $file = "{$path}/{$kp}/{$v['queue']}/" . md5($k) . '.php';
                        is_file($file) && unlink($file);
                    }
                    //恢复错误
                    error_reporting($errNo);
                }
                //清理指定ID的消息
                unset($vp['msgs']);
            //回滚事务, 清理指定ID的消息
            } else if ($func === '_rollBack') {
                unset($vp['msgs']);
            }

            //执行事务对应方法
            $return = $vp['inst']->$func($type);

            //提交操作(!_commit::after)成功状态
            if (!$isClear) {
                $nowState && $nowState = $return;
            //提交事务之后(_commit::after)返回失败消息列表
            } else if ($return) {
                //失败时间
                $time = time();
                //异常状态标记
                $fKey = 'of_base_com_mq::failed::' . $kp;
                //标记存在失败消息
                of_base_com_kv::set($fKey, 1, 86400, '_ofSelf');
                //记录失败消息
                foreach ($return as &$v) {
                    //记录失败时间
                    $v['time'] = &$time;
                    //失败消息路径
                    $file = "{$path}/{$kp}/{$v['queue']}/" .
                        md5("{$v['queue']}\0{$v['keys'][0]}\0{$v['keys'][1]}") . '.php';
                    //存储失败消息
                    of_base_com_disk::file($file, $v, true);
                }
                //标记存在失败消息
                of_base_com_kv::set($fKey, 1, 86400, '_ofSelf');
                //抛出错误
                trigger_error('Failed to produce message: ' . $kp);
            }
        }

        //提交事务前 && 消息队列执行失败
        if ($pool && $type === 'before' && !$nowState) {
            //强制主事务回滚, 保持数据一致性
            of_db::pool($pool, 'state', false);
        }
    }

    /**
     * 描述 : 框架 of::halt 事件回调
     * 作者 : Edgar.lee
     */
    public static function ofHalt() {
        //不在消费中 && 有新的队列 && 启动监听
        !self::$fireEnv && self::$fireMq && self::listen('nodeLock');
        //回调函数意外退出
        if ($index = &self::$fireEnv['mqData']) {
            //意外退出回调
            self::$fireEnv['mqObj']->_quit($index);
            //重置当前并发数据
            of_base_com_timer::data(array('_mq' => array()));
            //记录异常日志
            of::event('of::error', true, array(
                'type' => "{$index['key']}.{$index['queue']}.{$index['pool']}",
                'code' => E_USER_ERROR,
                'info' => 'MQ auto reload: (Q)Callback function "exit" unexpectedly. - ' .
                    print_r($index, true)
            ));
        }
    }

    /**
     * 描述 : 触发时具体方法回调
     *     &call : 框架标准回调结构
     *     &data : 传入到 [_] 位置的参数 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "lots"  : 批量消费数量,
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *          "msgs"  : 完整消息 {
     *              消息ID : 单条消息 {
     *                  "msgId" : 消息ID
     *                  "count" : 调用计数, 首次为 1
     *                  "data"  : 消息数据,
     *                  "uTime" : 更新时间戳
     *              }
     *          }
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
            'msgId'     => array_keys($data['msgs']),
            'startTime' => date('Y-m-d H:i:s', time()),
            'doneTime'  => '',
            'runCount'  => $count += count($data['msgs']),
            'useMemory' => &$fireEnv['memory'],
            'quitData'  => array(
                'class' => &$fireEnv['mqClass'],
                'data'  => &$data
            )
        );
        //记录监听数据
        of_base_com_timer::data(array('_mq' => &$cLog));

        try {
            //单消息处理
            if ($data['lots'] === 1) {
                $data += reset($data['msgs']);
                //计数列表
                $cList = array($data['count']);
            //多消息处理
            } else {
                foreach ($data['msgs'] as $k => &$v) {
                    $data['msgId'][] = &$k;
                    $data['data'][$k] = &$v['data'];
                    $data['count'][$k] = &$v['count'];
                }
                //计数列表
                $cList = $data['count'];
            }

            //清除当前错误
            of::work('error', false);
            //处理消息
            $result = &of::callFunc($call, $data);
        } catch (Exception $e) {
            $result = false;
            of::event('of::error', true, $e);
        }

        //回滚未结束事务
        $trxs = of_db::pool(null);
        //黑名单列表
        $temp = of::work('block', array());
        //筛选事务未结束且不在黑名单的事务
        foreach ($trxs as $k => &$v) {
            if ($v['level'] && empty($temp[$k])) {
                of_db::pool($k, 'clean', 1);
            } else {
                unset($trxs[$k]);
            }
        }

        //恢复永不超时
        ini_set('max_execution_time', 0);
        //恢复默认时区
        date_default_timezone_set($fireEnv['timezone']);
        //清空消息数据
        $fireEnv['mqData'] = &$null;
        //修改并发日志
        $cLog['doneTime'] = date('Y-m-d H:i:s', $time = time());
        //记录当前内存
        $cLog['useMemory'] = memory_get_usage();
        //清空异常消息
        unset($cLog['quitData']);
        //记录监听数据
        of_base_com_timer::data(array('_mq' => &$cLog));

        //计算返回数据(框架响应结构 ? 小于400 : (数字格式 > 5年 ? 指定时间 : 返回值))
        $return  = isset($result['code']) && is_int($result['code']) ?
            $result['code'] < 400 :
            (is_int($result) && $result > 63072000 ? $result - $time : $result);

        //返回false, 每5次报错
        if ($return === false) {
            $tipErr = false;
            foreach ($cList as $k => &$v) {
                if ($v % 5 === 3) {
                    $tipErr = true;
                    break ;
                }
            }
        }

        //(返回false && 每5次报错) || (返回true && 事务未结束) || (非布尔 && 非数字)
        if (
            $return === false && $tipErr ||
            $return === true && $trxs ||
            !is_bool($return) && !is_int($return)
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

            //抛出错误提示
            if ($return === true) {
                $temp = 'The database transaction is not closed: ' . join(', ', array_keys($trxs));
            } else {
                $temp = 'Failed to consume message from queue: ' . var_export($result, true);
            }
            of::event('of::error', true, array(
                'type' => "{$data['key']}.{$data['queue']}.{$data['pool']}",
                'code' => E_USER_WARNING,
                'info' => "{$temp}\n\n" .
                    'call--' . print_r($func, true) . "\n\n" .
                    'argv--' . print_r($data, true)
            ));

            //返回false
            $return = false;
        }

        return $return;
    }

    /**
     * 描述 : 获取队列配置
     * 参数 :
     *     &path : 队列配置文件路径
     *     &pool : 队列连接池
     * 作者 : Edgar.lee
     */
    protected static function getQueueConfig(&$config, &$pool) {
        //加载最新队列配置
        is_string($config) && $config = include ROOT_DIR . $config;

        if (
            //可能是 {队列池:{队列名:{}, ...}} 方式
            isset($config[$pool]) &&
            //获取第一个队列成功
            is_array($temp = current($config[$pool])) &&
            //回调中cNum必须存在, 并且是数字
            (!isset($temp['keys']['cNum']) || is_array($temp['keys']['cNum']))
        ) {
            //{队列池:{队列名:{}, ...}} 转成 {队列名:{}, ...}
            $config = $config[$pool];
        }
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
            //非内部事务 && 追加到动态工作中
            $bind && of::work(array(), 0, array('pool' => $bind));
            //引用当前操作的消息块
            $mqArr = &$mqList[$bind]['pools'][$pool];

            //绑定事务初始化
            if (!isset($mqList[$bind]['level'])) {
                $mqList[$bind]['isSet'] = false;
                $mqList[$bind]['level'] = $bind ? of_db::pool($bind, 'level') : 0;
                $mqList[$bind]['state'] = $bind ? of_db::pool($bind, 'state') : true;
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

                //初始化适配器
                $mqArr['inst'] = 'of_accy_com_mq_' . $config[$pool]['adapter'];
                $mqArr['inst'] = new $mqArr['inst'];
                $mqArr['inst']->params = $config[$pool];
                $mqArr['inst']->_init(array(
                    'pool' => $pool,
                    'bind' => $bind
                ));

                //绑定事务已开启
                if ($mqList[$bind]['level']) {
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
     * 描述 : 重置异常消息数据
     * 参数 :
     *      type : 指定任务中的并发ID, null=重置自身, 2=重置停止
     * 作者 : Edgar.lee
     */
    private static function resetPaincMqData($type = 2) {
        //引用环境
        $fireEnv = &self::$fireEnv;
        //重置自身及未启动消息数据
        $index = &of_base_com_timer::data(array('_mq' => array()), $type);
        //遍历处理异常消息
        foreach ($index['info'] as $k => &$v) {
            if (
                //自身进程 || 未运行进程
                ($type === null || !$v['isRun']) &&
                //存在异常消息
                ($v = &$v['data']['_mq']['quitData']) &&
                //消费类相同
                $v['class'] === $fireEnv['mqClass']
            ) {
                $fireEnv['mqObj']->_quit($v['data']);
            }
        }
    }

    /**
     * 描述 : 恢复失败的消息
     * 作者 : Edgar.lee
     */
    private static function restoreMsgs(&$fDirs) {
        static $time = 0;

        //每5分钟执行一次清理
        if ($time + 300 > ($temp = time())) return ;
        //更新当前时间
        $time = $temp;
        //恢复失败清单{连接池 : true, ...}
        $done = array_fill_keys($fDirs, true);

        //恢复失败消息信息
        foreach ($fDirs as $kf => &$vf) {
            if (
                //队列目录存在
                is_dir($kf) &&
                //尝试加锁成功
                flock($fp = fopen("{$kf}.lock", 'a'), LOCK_EX | LOCK_NB)
            ) {
                //异常状态标记
                $fKey = 'of_base_com_mq::failed::' . $vf;

                //存在失败消息, 0=未知状态, -1=无失败消息, 1=有失败信息
                if (of_base_com_kv::get($fKey, 0, '_ofSelf') > 0) {
                    //标记消息失败状态失效
                    of_base_com_kv::del($fKey, '_ofSelf');
                }

                //批量读取失败消息
                while (of_base_com_disk::each($kf, $list, true)) {
                    //标记消息失败状态失效
                    of_base_com_kv::del($fKey, '_ofSelf');
                    //恢复的消息列表
                    $msgs = array();
                    //遍历恢复消息
                    foreach ($list as $k => &$v) {
                        //可以恢复 失败时间 < 当前时间
                        if (($temp = filemtime($k)) && $temp < $time) {
                            $index = &of_base_com_disk::file($k, true, true);
                            $msgs[] = &$index;

                            //计算延期时间 (失败 + 延迟 - 当前) < 0 && 延迟置0
                            ($temp = $index['time'] + $index['keys'][2] - $time) < 0 && $temp = 0;
                            $index['keys'][2] = $temp;

                            //控制最大内存
                            if (isset($msgs[999])) {
                                of_base_com_mq::set($msgs, $vf);
                                $msgs = array();
                            }
                        //标记有消息无法恢复
                        } else {
                            unset($done[$vf]);
                        }
                    }
                    //批量恢复消息, 恢复时会自动删除问题消息
                    $msgs && of_base_com_mq::set($msgs, $vf);
                }
                //解锁并销毁资源
                unset($fp);
            }
        }

        //标记消息状态异常
        foreach ($done as $kd => &$vd) {
            //存在异常消息 || 标记状态正常
            self::getFailPools($kd) ||
                of_base_com_kv::set('of_base_com_mq::failed::' . $kd, -1, 86400, '_ofSelf');
        }
    }

    /**
     * 描述 : 获取失败的池信息
     * 作者 : Edgar.lee
     */
    private static function getFailPools($pool) {
        $result = array();
        $pool === null ?
            of_base_com_disk::each(self::$mqDir . '/failedMsgs', $dirs) :
            $dirs = array(self::$mqDir . '/failedMsgs/' . $pool => true);

        //遍历消息池列表
        foreach ($dirs as $kd => &$vd) {
            //遍历对应消息池下的队列列表
            of_base_com_disk::each($kd, $list);
            //遍历队列列表
            foreach ($list as $k => &$v) {
                //是队列文件夹 && 存在失败消息
                if ($vd && !of_base_com_disk::none($k)) {
                    //读取消息池名称
                    $result[] = basename($kd);
                    continue 2;
                }
            }
        }

        return $result;
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
    //abstract protected function _init($fire);

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
    //abstract protected function _sets(&$msgs);

    /**
     * 描述 : 触发消息队列, 根据回调响应值执行对应动作
     * 参数 :
     *     &call : 符合回调结构
     *     &data : 需要设置的消息集合, call的回调参数 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "lots"  : 批量消费数量,
     *          "data"  :x消息数据, _fire 函数实现
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *      }
     * 返回 :
     *      true=已匹配到消息, false=未匹配到消息
     * 作者 : Edgar.lee
     */
    //abstract protected function _fire(&$calll, $data);

    /**
     * 描述 : 触发消息队列意外退出时回调
     * 参数 :
     *     &data : 回调参数结构 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "lots"  : 批量消费数量,
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
    //abstract protected function _quit(&$data);

    /**
     * 描述 : 开启事务
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    //abstract protected function _begin();

    /**
     * 描述 : 提交事务
     * 参数 :
     *      type : "before"=提交开始回调, "after"=提交结束回调
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    //abstract protected function _commit($type);

    /**
     * 描述 : 事务回滚
     * 参数 :
     *      type : "before"=回滚开始回调, "after"=回滚结束回调
     * 返回 :
     *      成功 true, 失败 false
     * 作者 : Edgar.lee
     */
    //abstract protected function _rollBack($type);
}

of_base_com_mq::init();
//仅允许访问控制台页面
return join('::', of::dispatch()) === 'of_base_com_mq::index';