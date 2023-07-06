<?php
/**
 * 描述 : 计划任务,定时回调
 * 注明 :
 *      键值结构 : 已"of_base_com_timer::"为前缀的键名
 *          "nodeList" : 完整分布式节点(永不过期), 记录不同"_of.nodeName"节点, 失效时定期清理 {
 *              节点ID : 节点信息 {
 *                  "time" : 创建时间
 *              }, ...
 *          }

 *          "taskList" : 完整任务列表(永不过期), 停用的定期清理 {
 *              任务ID : 未来扩展 {},
 *              ...
 *          }
 *          taskNote#任务ID : 单个任务备注(30天过期), 同taskList新增, 定期清理或延期 {
 *              "call" : 回调方法
 *          }
 *          taskInfo#任务ID : 单个任务信息(30天过期), 定期清理或延期 {
 *              "list" : 并发列表 {
 *                  并发数字 : {
 *                      "time" : 启动时间
 *                  }, ...
 *              }
 *          }
 *      加锁逻辑 : 已"of_base_com_timer::"为前缀
 *          "nodeList" : 当新插入或清理节点时加独享锁
 *          nodeLock#节点ID : 节点进程, 启动时独享锁
 *          daemon#节点ID : 守护进程, 启动时独享锁

 *          "taskIsGc" : 标识守护进程为任务回收器
 *          "taskList" : 任务列表锁, 当新插入或清理任务时加独享锁
 *          taskLock#任务ID : 单个任务锁, 任务启动时加共享锁, 清理时加独享锁, 未存信息
 *          taskLock#任务ID#并发数字 : 任务并发锁, 对应并发任务启动时加独享锁
 *          taskInfo#任务ID : 任务信息锁, 修改任务信息时加独享锁
 *      磁盘结构 :
 *          "taskList.php" : 动态任务文件模式下存储的文件
 * 作者 : Edgar.lee
 */
class of_base_com_timer {
    //日志配置, _of.com.timer
    private static $config = null;
    //当前运行的任务, 并发时生成 {"call" : taskCall参数, "cArg" : taskCall参数}
    private static $nowTask = null;
    //任务数据回传标识
    private $taskMark = '';
    //任务数据监控状态
    private $testOnly = false;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        $config = &self::$config;
        //读取节点
        $config = of::config('_of.com.timer', array());
        //计算节点ID, 空节点名称不生成触发器路径
        ($nodeName = of::config('_of.nodeName')) && $config['nodeId'] = md5($nodeName);

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

        //web访问开启计划任务
        if (of::dispatch('class') === 'of_base_com_timer') {
            echo self::state() ? 'runing' : 'starting', "<br>\n";
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
    public static function &timer($name = 'nodeLock', $type = null) {
        //空节点名 && 不启动触发器
        if (!$nodeId = &self::$config['nodeId']) return $result;
        //加锁节点键
        $lock = "of_base_com_timer::{$name}#{$nodeId}";

        //加锁失败(2 | 4)
        if (!of_base_com_data::lock($lock, 6)) {
            $result = true;
        //主动触发(异步)
        } else if ($type === null) {
            //连接解锁
            of_base_com_data::lock($lock, 3);
            //加载定时器
            of_base_com_net::request('', array(), array(
                'asCall' => 'of_base_com_timer::timer',
                'params' => array($name, true)
            ));
            $result = false;
        //任务列表遍历检查
        } else if ($name === 'nodeLock') {
            //全局节点列表键
            $listKey = 'of_base_com_timer::nodeList';
            //读取全局节点列表
            $temp = of_base_com_kv::get($listKey, array(), '_ofSelf');
            //新节点不在全局列表中
            if (!isset($temp[$nodeId])) {
                //加锁全局节点列表
                of_base_com_data::lock($listKey, 2);
                //读取全局节点列表
                $temp = of_base_com_kv::get($listKey, array(), '_ofSelf') + array(
                    $nodeId => array('time' => time())
                );
                //回写全局节点列表(永不过期)
                of_base_com_kv::set($listKey, $temp, 0, '_ofSelf');
                //解锁全局节点列表
                of_base_com_data::lock($listKey, 3);
            }

            while (!self::renew()) {
                //休眠后返回任务数量
                $needNum = self::getRunNum();

                //静态计划任务
                ($crontab = &self::crontab($needNum)) && self::fireCalls($crontab);
                //动态计划任务
                ($movTask = &self::taskList($needNum)) && self::fireCalls($movTask);

                //无任何任务
                if ($movTask === false && $crontab === false) sleep(30);
                //启动保护进程
                self::timer('daemon');
            }

            //连接解锁
            of_base_com_data::lock($lock, 3);
        //保护进程
        } else if ($name === 'daemon') {
            //当前时间
            $time = time();
            //打开任务进程锁文件
            $nLock = 'of_base_com_timer::nodeLock#' . $nodeId;
            //全局任务列表键
            $tLock = 'of_base_com_timer::taskList';
            //任务回收器键
            $gLock = 'of_base_com_timer::taskIsGc';
            //是否为任务回收器
            $isGc = false;

            //连接加锁(非阻塞) 兼容 glusterfs 网络磁盘
            while (!self::renew() && !of_base_com_data::lock($nLock, 6)) {
                sleep(1);

                //每10分钟执行一次
                if (($temp = time()) - 600 > $time) {
                    //更新最后执行时间
                    $time = $temp;

                    //成为任务回收器
                    if ($isGc || $isGc = of_base_com_data::lock($gLock, 6)) {
                        //获取全局任务列表独享锁
                        of_base_com_data::lock($tLock, 2);
                        //读取全局节点列表
                        if ($data = of_base_com_kv::get($tLock, array(), '_ofSelf')) {
                            //遍历全局节点列表{任务ID : {}, ...}
                            foreach ($data as $k => &$v) {
                                //任务状态锁
                                $taskLock = 'of_base_com_timer::taskLock#' . $k;
                                //任务备注键
                                $taskNoteKey = 'of_base_com_timer::taskNote#' . $k;
                                //任务信息键
                                $taskInfoKey = 'of_base_com_timer::taskInfo#' . $k;

                                //任务未运行
                                if (of_base_com_data::lock($taskLock, 6)) {
                                    //清理备注数据
                                    of_base_com_kv::del($taskNoteKey, '_ofSelf');
                                    //清理信息数据
                                    of_base_com_kv::del($taskInfoKey, '_ofSelf');
                                    //清理列表数据
                                    unset($data[$k]);
                                    //解除状态锁
                                    of_base_com_data::lock($taskLock, 3);
                                //任务信息可修改
                                } else if (of_base_com_data::lock($taskInfoKey, 6)) {
                                    //更新备注有效期
                                    if ($temp = of_base_com_kv::get($taskNoteKey, array(), '_ofSelf')) {
                                        of_base_com_kv::set($taskNoteKey, $temp, 2592000, '_ofSelf');
                                    }
                                    //更新信息有效期
                                    if ($temp = of_base_com_kv::get($taskInfoKey, array(), '_ofSelf')) {
                                        of_base_com_kv::set($taskInfoKey, $temp, 2592000, '_ofSelf');
                                    }
                                    //解除信息锁
                                    of_base_com_data::lock($taskInfoKey, 3);
                                }
                            }
                            //回写全局任务列表(永不过期)
                            of_base_com_kv::set($tLock, $data, 0, '_ofSelf');
                        }
                        //解锁全局任务列表
                        of_base_com_data::lock($tLock, 3);
                        //清理分布式节点信息
                        self::info(6);
                    }
                }
            }

            //是回收器 && 解锁
            $isGc && of_base_com_data::lock($gLock, 3);
            //连接解锁
            of_base_com_data::lock($nLock, 3);
            //连接解锁
            of_base_com_data::lock($lock, 3);
            //启动任务进程
            self::timer('nodeLock');
        }

        //返回结果
        return $result;
    }

    /**
     * 描述 : 定时任务
     * 参数 :
     *      params : 任务参数 {
     *          #定时模式, 未指定taskObj参数
     *          "time" : 执行时间, 五年内秒数=xx后秒执行, 其它=指定时间
     *          "call" : 框架标准的回调
     *          "cNum" : 并发数量, 0=不设置, n=最大值, []=指定并发ID(最小值1)
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]

     *          #单例任务, taskObj返回任务对象
     *          "call" : 框架标准的回调

     *          #批量任务, taskObj返回 {任务标识 : 任务对象, ...}
     *          "list" : 任务列表 {任务标识 : 框架回调结构, ...}
     *          "cNum" : 最大并行任务数量
     *      }
     *     &taskObj : 任务对象, 指定时为任务模式
     * 作者 : Edgar.lee
     */
    public static function task($params, &$taskObj = -234567890) {
        //格式化
        $params += array('time' => 0, 'cNum' => 0, 'try' => array());

        //定时模式
        if ($taskObj === -234567890) {
            //当前时间
            $nowTime = time();
            //开启定时器
            self::timer();

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
        //批量任务
        } else if (isset($params['list'])) {
            $taskObj = $data = array();

            foreach ($params['list'] as $k => &$v) {
                //回传标识
                $mark = $data[] = of_base_com_str::uniqid();

                //创建任务跟踪对象
                $taskObj[$k] = new self;
                $taskObj[$k]->taskMark = $mark;

                //设置任务状态
                of_base_com_kv::set('of_base_com_timer::taskMark#' . $mark, array(
                    //100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                    'code' => 100,
                    'data' => serialize($v)
                ), 86400, '_ofSelf');
            }

            //生成队列容器
            $data && self::fireCalls(array(array(
                'call' => array(
                    'asCall' => 'of_base_com_timer::taskBindBox',
                    'params' => array(array(
                        'list' => &$data,
                        'cNum' => $params['cNum']
                    ))
                )
            )));
        //单例任务
        } else {
            //唯一标识
            $mark = of_base_com_str::uniqid();

            //创建任务跟踪对象
            $taskObj = new self;
            $taskObj->taskMark = $mark;

            //设置任务状态
            of_base_com_kv::set('of_base_com_timer::taskMark#' . $mark, array(
                //100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                'code' => 100
            ), 86400, '_ofSelf');

            //直接触发
            self::fireCalls(array(array('call' => &$params['call'])), array('mark' => $mark));
        }
    }

    /**
     * 描述 : 获取计划任务状态
     * 参数 :
     *      start : true=尝试开启消息队列, false=仅查询状态
     * 返回 :
     *      true=运行状态, false=停止状态
     * 作者 : Edgar.lee
     */
    public static function state($start = true) {
        //最终运行状态
        $result = false;
        //全局节点列表键
        $listKey = 'of_base_com_timer::nodeList';
        //读取全局节点列表
        $nodes = of_base_com_kv::get($listKey, array(), '_ofSelf');

        //判断所有监听, 一个运行便为运行
        foreach ($nodes as $kt => &$vt) {
            //节点进程锁
            $nodeLock = 'of_base_com_timer::nodeLock#' . $kt;
            //队列监听未启动
            if (of_base_com_data::lock($nodeLock, 6)) {
                of_base_com_data::lock($nodeLock, 3);
            //队列监听已启动
            } else {
                $result = true;
                break ;
            }
        }

        //需要开启 && 尝试开启
        $start && self::timer();
        return $result;
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
     *          节点ID : {}
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
            $type === 1 ? $save = &$result : $save = &$result['concurrent'];
            $save = array();

            //全局任务列表键
            $tLock = 'of_base_com_timer::taskList';
            //读取全局节点列表
            $data = of_base_com_kv::get($tLock, array(), '_ofSelf');
            //遍历全局节点列表{任务ID : {}, ...}
            foreach ($data as $kt => &$vt) {
                //任务状态锁
                $taskLock = "of_base_com_timer::taskLock#{$kt}";
                //加锁成功, 任务没运行
                if (of_base_com_data::lock($taskLock, 6)) {
                    of_base_com_data::lock($taskLock, 3);
                //任务已运行 && 文件信息存在
                } else {
                    //任务备注键
                    $taskNoteKey = 'of_base_com_timer::taskNote#' . $kt;
                    //任务信息键
                    $taskInfoKey = 'of_base_com_timer::taskInfo#' . $kt;

                    //读取任务回调信息
                    $index = &$save[$kt];
                    $index['call'] = of_base_com_kv::get($taskNoteKey, array(), '_ofSelf');
                    $index['call'] = &$index['call']['call'];
                    $index['list'] = array();

                    //读取任务信息
                    $list = of_base_com_kv::get($taskInfoKey, array(), '_ofSelf');
                    //遍历任务信息
                    foreach ($list['list'] as $k => &$v) {
                        //加锁成功, 进程没启动
                        if (of_base_com_data::lock($temp = "{$taskLock}#{$k}", 6)) {
                            of_base_com_data::lock($temp, 3);
                        } else {
                            //记录并发执行的时间
                            $index['list'][$k] = array(
                                'datetime' => date('Y-m-d H:i:s', $v['time']),
                                'timestamp' => $v['time']
                            );
                        }
                    }
                    //按运行序号排序
                    ksort($index['list']);
                }
            }
        }

        //分布定时器执行情况
        if ($type & 2) {
            $type === 2 || $type === 6 ? $save = &$result : $save = &$result['taskTrigger'];

            //全局节点列表键
            $listKey = 'of_base_com_timer::nodeList';
            //读取全局节点列表
            $save = of_base_com_kv::get($listKey, array(), '_ofSelf');
            //遍历节点列表{节点ID : {}, ...}
            foreach ($save as $k => &$v) {
                //节点锁键
                $nLock = 'of_base_com_timer::nodeLock#' . $k;

                //监控未开启
                if (of_base_com_data::lock($nLock, 6)) {
                    //清理未启动监控
                    if ($type & 4 && $_SERVER['REQUEST_TIME'] - $v['time'] > 3600) {
                        //加锁全局节点列表
                        of_base_com_data::lock($listKey, 2);
                        //读取全局节点列表
                        $temp = of_base_com_kv::get($listKey, array(), '_ofSelf');
                        //移除无效节点
                        unset($temp[$k]);
                        //回写全局节点列表(永不过期)
                        of_base_com_kv::set($listKey, $temp, 0, '_ofSelf');
                        //解锁全局节点列表
                        of_base_com_data::lock($listKey, 3);
                    }

                    //移除未启动监控
                    unset($save[$k]);
                    //解锁
                    of_base_com_data::lock($nLock, 3);
                }
            }
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
        //缓存自身数据
        static $cache = null;
        //返回结构
        $result = array('info' => array());

        if ($cIds !== null && $call !== null || $nowTask = &self::$nowTask) {
            //读取自身任务
            if ($call === null) {
                $call = $nowTask['cArg']['cMd5'];
            //计算任务路径
            } else if (is_array($call) || $call[0] !== '/') {
                $call = of_base_com_data::digest($call);
            //以'/'开头字符串
            } else {
                $call = substr($call, 1);
            }

            //任务信息键
            $taskLockKey = 'of_base_com_timer::taskLock#' . $call;
            //过滤加锁(true=读取全部, false=读取运行, null=读取停止)
            $filt = $cIds === 2 ? null : $cIds !== 1;
            //并发ID排序计数
            $sort = -1;

            //遍历列表
            if (is_int($cIds)) {
                //任务信息键
                $taskInfoKey = 'of_base_com_timer::taskInfo#' . $call;
                //读取消息
                $cIds = of_base_com_kv::get($taskInfoKey, array('list' => array()), '_ofSelf');
                //读取并发ID
                $cIds = array_keys($cIds['list']);
                //在异步并发中读取自身时没数据一定是k-v服务不稳定
                $cIds || isset($nowTask['cArg']['cMd5']) && trigger_error('K-V service unavailable: _ofSelf');
            //读取自身
            } else if ($cIds === null) {
                $cIds = array($nowTask['cArg']['cCid']);
            //其它为数组
            }

            //从小到大排序并发ID
            sort($cIds, SORT_NUMERIC);

            //读取数据
            foreach ($cIds as &$v) {
                //任务信息锁
                $taskNumsLock = "{$taskLockKey}#{$v}";
                //自身进程
                $isSelf = isset($nowTask) && $nowTask['cArg']['cCid'] === $v;
                //进程是否运行, true=运行, false=停止
                $isRun = $isSelf ? true : !of_base_com_data::lock($taskNumsLock, 5);

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
                        'data'  => $data === null ? null : ($isSelf ?
                            ($cache === null ?
                                $cache = of_base_com_kv::get($dKey, array(), '_ofSelf') : $cache
                            ) : of_base_com_kv::get($dKey, array(), '_ofSelf')
                        )
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

                //并发任务运行中 || 解锁未运行的进程
                $isRun || of_base_com_data::lock($taskNumsLock, 3);
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
     *      cArg : 并发参数, 数组=启动并发 {
     *          "cMd5" : 回调唯一值
     *          "cCid" : 并发ID, 从1开始
     *          "mark" : 数据回传标识, 存在时标识回传
     *      }
     * 作者 : Edgar.lee
     */
    public static function taskCall($call, $cArg) {
        //保护linux进程不被SIGTERM信号杀掉 && 信号1~32(9 19 32 linux 无效, 17 mac 无效)
        function_exists('pcntl_signal') && pcntl_signal(15, SIG_IGN);

        //记录当前任务
        self::$nowTask = array(
            'call' => $call,
            'cArg' => $cArg
        );
        //系统回调参数
        $params = array(
            'call' => &$call['call'],
            'time' => &$call['time'],
            'cNum' => &$call['cNum'],
            'try'  => &$call['try'],
            'this' => &$cArg
        );
        //空间锁参数
        $lockArgv = array('space' => __METHOD__);

        //启用并发
        if (isset($cArg['cMd5'])) {
            //任务状态锁
            $taskLock = 'of_base_com_timer::taskLock#' . $cArg['cMd5'];
            //读锁成功
            of_base_com_data::lock($taskLock, 1, $lockArgv);

            //全局任务列表键
            $tLock = 'of_base_com_timer::taskList';
            //读取全局节点列表
            $temp = of_base_com_kv::get($tLock, array(), '_ofSelf');
            //新任务
            if (!isset($temp[$cArg['cMd5']])) {
                //获取全局任务列表独享锁
                of_base_com_data::lock($tLock, 2, $lockArgv);
                //跟新任务备注数据
                of_base_com_kv::set('of_base_com_timer::taskNote#' . $cArg['cMd5'], array(
                    'call' => $call['call']
                ), 2592000, '_ofSelf');
                //读取全局节点列表
                $temp = of_base_com_kv::get($tLock, array(), '_ofSelf') + array($cArg['cMd5'] => array());
                //回写全局任务列表(永不过期)
                of_base_com_kv::set($tLock, $temp, 0, '_ofSelf');
                //解锁全局任务列表
                of_base_com_data::lock($tLock, 3, $lockArgv);
            }

            //加锁成功
            if (of_base_com_data::lock("{$taskLock}#{$cArg['cCid']}", 6, $lockArgv)) {
                //任务信息键
                $taskInfoKey = 'of_base_com_timer::taskInfo#' . $cArg['cMd5'];
                //加锁消息
                of_base_com_data::lock($taskInfoKey, 2, $lockArgv);
                //读取消息
                $temp = of_base_com_kv::get($taskInfoKey, array(), '_ofSelf');
                //记录并发数字
                $temp['list'][$cArg['cCid']]['time'] = time();
                //更新信息
                of_base_com_kv::set($taskInfoKey, $temp, 2592000, '_ofSelf');
                //加锁消息
                of_base_com_data::lock($taskInfoKey, 3, $lockArgv);
            //并发已开启, 不用继续执行
            } else {
                $params = false;
            }
        //数据回传
        } else if (isset($cArg['mark'])) {
            of_base_com_kv::set('of_base_com_timer::taskMark#' . $cArg['mark'], array(
                //100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                'code' => 150,
                'data' => getmygid()
            ), 86400, '_ofSelf');
            //注入异常
            of::event('of::halt', 'of_base_com_timer::ofHalt');
        }

        //触发回调执行
        if ($params) {
            //调用任务
            $result = of::callFunc($call['call'], $params);

            //返回任务结果
            if (isset($cArg['mark'])) {
                of_base_com_kv::set('of_base_com_timer::taskMark#' . $cArg['mark'], array(
                    //100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                    'code' => 200,
                    'data' => array(
                        'result' => $result
                    )
                ), 86400, '_ofSelf');
                //标记回调成功
                self::$nowTask['cArg']['mark'] = '';
            //回调失败
            } else if ($result === false) {
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
    }

    /**
     * 描述 : 限制并行数的批量任务回调
     * 参数 :
     *      params : 任务参数 {
     *          "list" : 回传标识列表 [回传标识, ...]
     *          "cNum" : 最大并行任务
     *      }
     * 作者 : Edgar.lee
     */
    public static function taskBindBox($params) {
        //最大并行数无效 && 并行数设置为1
        $cNum = $params['cNum'] < 1 ? 1 :  $params['cNum'];
        //待处理列表
        $wait = &$params['list'];
        //监控列表
        $list = array();
        //回传标识
        $mark = array_shift($wait);

        do {
            while ($cNum) {
                //解析回调方法
                $call = of_base_com_kv::get('of_base_com_timer::taskMark#' . $mark, array(), '_ofSelf');
                $call = unserialize($call['data']);
                //生成队列容器
                self::fireCalls(array(array('call' => &$call)), array('mark' => $mark));

                //创建任务跟踪对象
                $list[$mark] = new self;
                $list[$mark]->taskMark = $mark;
                $list[$mark]->testOnly = true;

                //剩余并发数
                $cNum -= 1;
                //待处理列表为空
                if (!$mark = array_shift($wait)) break 2;
            }

            //定时检查状态, 休眠 1/20 秒
            usleep(50000);

            //统计可运行任务数
            foreach ($list as $k => &$v) {
                if ($v->result(0)) {
                    $cNum += 1;
                    unset($list[$k]);
                }
            }
        } while (true);
    }

    /**
     * 描述 : 注册并接收退出信号
     * 作者 : Edgar.lee
     */
    public static function exitSignal($type = true) {
        //初始化状态, null=未初始化, true=信号安装, false=不支持
        static $init = null;

        //首次运行
        if ($init === null) {
            //支持延迟触发信号
            ($init = function_exists('pcntl_signal_dispatch')) ?
                //安装SIGTERM信号处理器
                pcntl_signal(15, 'of_base_com_timer::exitSignal') :
                //恢复linux进程对SIGTERM信号处理
                function_exists('pcntl_signal') && pcntl_signal(15, SIG_DFL);
        //信号处理
        } else if (is_int($type)) {
            exit;
        //检查信号
        } else if ($init) {
            pcntl_signal_dispatch();
        }
    }

    /**
     * 描述 : 由任务回传数据创建
     * 作者 : Edgar.lee
     */
    public static function ofHalt() {
        //出现异常
        if ($mark = &self::$nowTask['cArg']['mark']) {
            of_base_com_kv::set('of_base_com_timer::taskMark#' . $mark, array(
                //100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                'code' => 400
            ), 86400, '_ofSelf');
            //抛出错误, 任务回调异常
            trigger_error('The task exits abnormally: ' . print_r(self::$nowTask['call'], true));
        }
    }

    /**
     * 描述 : 获取任务结果
     * 参数 :
     *      wait : 最大尝试时间(秒), 默认86400(24小时), 0=尝试一次
     * 返回 :
     *      false=任务运行中, true=任务中途退出(exit throw kill), array=任务正常返回 {
     *          "result" : 任务返回的结果
     *      }
     * 作者 : Edgar.lee
     */
    public function result($wait = 86400) {
        $result = &$this->taskMark;

        //读取任务返回数据
        if (is_string($result)) {
            //任务标识
            $mark = 'of_base_com_timer::taskMark#' . $result;

            do {
                //读取任务数据, 100=准备, 150=启动(data存储进程ID), 200=完成(data存储数据), 400=异常
                $data = of_base_com_kv::get($mark, array('code' => 400), '_ofSelf');

                //任务执行完成
                if ($data['code'] === 200) {
                    $result = $data['data'];
                    break ;
                //任务执行失败
                } else if (
                    $data['code'] === 400 ||
                    $data['code'] === 150 && !self::isRunning($data['data'])
                ) {
                    $result = true;
                    break ;
                //延迟重试
                } else if ($wait > 0) {
                    //大体将秒转成次数计算, 用time()判断更准, 性能差些
                    $wait -= 0.05;
                    //休眠 1/20 秒
                    usleep(50000);
                //任务运行中
                } else {
                    return false;
                }
            } while (true);

            //任务继续执行 || 测试任务状态 || 删除任务标识
            is_string($result) || $this->testOnly || of_base_com_kv::del($mark, '_ofSelf');
        }

        return $result;
    }

    /**
     * 描述 : 任务是否运行中
     * 作者 : Edgar.lee
     */
    private static function isRunning($pid) {
        $isOk = true;

        //支持命令调用则判断本机进程是否存在
        if (of_base_com_net::isExec()) {
            //windows系统 ? 查询进程是否存在 : 发送进程信号
            $isOk = self::$config['osType'] === 'win' ? !!strpos(
                stream_get_contents(popen("TASKLIST /FO LIST /FI \"PID eq {$pid}\"", 'r'), 1024),
                (string)$pid
            ) : posix_kill($pid, 0);
        }

        return $isOk;
    }

    /**
     * 描述 : 根据服务器空闲资源休眠并返回执行并发任务数
     * 作者 : Edgar.lee
     */
    private static function getRunNum() {
        //引用配置
        $config = &self::$config;

        //不支持php命令
        if (!of_base_com_net::isExec()) {
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
                        if (of_base_com_data::lock($lock, 2)) {
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
                            of_base_com_data::lock($lock, 3);
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
     *      cArg : 单机会参数 {
     *          "mark" :o数据回传标识, 存在时标识回传
     *      }
     * 作者 : Edgar.lee
     */
    private static function fireCalls($list, $cArg = array()) {
        foreach ($list as &$v) {
            //单计划
            if (empty($v['cNum'])) {
                //触发任务
                of_base_com_net::request('', array(), array(
                    'asCall' => 'of_base_com_timer::taskCall',
                    'params' => array(
                        $v + array('time' => 0, 'cNum' => 0, 'try' => array()),
                        $cArg
                    )
                ));
            //多并发
            } else {
                //并发数组
                $cArr = is_array($v['cNum']) ? $v['cNum'] : range(1, $v['cNum']);
                $cMd5 = of_base_com_data::digest($v['call']);

                //任务状态锁
                $taskLock = 'of_base_com_timer::taskLock#' . $cMd5;
                //读锁成功
                if (of_base_com_data::lock($taskLock, 1)) {
                    foreach ($cArr as &$cNum) {
                        //加锁成功, 没有使用的并发ID
                        if ($isRun = of_base_com_data::lock("{$taskLock}#{$cNum}", 6)) {
                            //释放并发锁
                            of_base_com_data::lock("{$taskLock}#{$cNum}", 3);
                            //触发任务
                            of_base_com_net::request('', array(), array(
                                'asCall' => 'of_base_com_timer::taskCall',
                                'params' => array(
                                    &$v, array('cMd5' => $cMd5, 'cCid' => $cNum)
                                )
                            ));
                        }
                    }
                }

                //连接解锁
                of_base_com_data::lock($taskLock, 3);
            }
        }
    }
}

of_base_com_timer::init();