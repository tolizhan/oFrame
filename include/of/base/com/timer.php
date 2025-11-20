<?php
/**
 * 描述 : 计划任务,定时回调
 * 注明 :
 *      键值结构 : 已"of_base_com_timer::"为前缀的键名
 *          "nodeList" : 完整分布式节点(永不过期), 记录不同"_of.nodeName"节点, 失效时定期清理 {
 *              节点ID : 节点信息 {
 *                  "nodeAddr" : 节点IP地址
 *                  "nodeTime" : 启动时间, 若出现与部署时间差距较大, 可能是当前主机的系统异常重启
 *                  "nodeSort" : 在返回列表中的位置, 从0开始
 *                  "sortTime" : 排序时间, 每次nodeSort变更时更新, 若出现与nodeTime差距较大, 可能是前面节点的主机的系统异常重启
 *                  "prevSort" : 列表位置变更前的位置, 未变更为-1, 若出现比nodeSort小的情况, 可能是_ofSelf的K-V被第三方改写的问题
 *              }, ...
 *          }
 *          "nodeSync" : 分布式节点同步时间戳(30分钟过期), 每隔30s更新下nodeList并更新nodeSync时间

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
        $config = of::config('_of.com.timer', array()) + array('fork' => array('adapter' => 'default'));
        //计算节点ID, 空节点名称不生成触发器路径
        ($nodeName = of::config('_of.nodeName')) && $config['nodeId'] = md5($nodeName);

        //操作系统类型(WINNT:windows, Darwin:mac, 其它:linux)
        $config['osType'] = strtolower(substr(PHP_OS, 0, 3));
        //文件锁路径
        empty($config['path']) && $config['path'] = OF_DATA . '/_of/of_base_com_timer';
        $config['path'] = of::formatPath($config['path'], ROOT_DIR);
        //异步回调方法
        $config['forkFn'] = 'of_accy_com_timer_' . $config['fork']['adapter'] . '::fork';

        //初始 动态任务 配置
        ($index = &$config['task']) || $index = array();
        $index += array('adapter' => 'files', 'params'  => array());

        //初始 静态任务 配置
        ($index = &$config['cron']) || $index = array();
        $index += array('path' => '');
        empty($index['path']) || $index['path'] = of::formatPath($index['path'], ROOT_DIR);
    }

    /**
     * 描述 : 控制台页面
     * 作者 : Edgar.lee
     */
    public function index() {
        echo self::state() ? 'running' : 'starting',
            "<br>\n<style>a{color: #000;} table{border-collapse: collapse;} pre{width: 0;}</style>";
        //永不超时
        ini_set('max_execution_time', 0);

        if (OF_DEBUG === false) {
            exit('Access denied: production mode.');
        } else {
            //路径参数
            $rUrl = '?c=of_base_com_timer' . (isset($_GET['__OF_DEBUG__']) ? '&__OF_DEBUG__=' . $_GET['__OF_DEBUG__'] : '');
            //默认排序
            $sort = isset($_GET['sort']) ? $_GET['sort'] : '';
            //加载消息类型
            $mark = isset($_GET['mark']) ? $_GET['mark'] : '';
            //分组打印信息
            $list = array();
            //当前时间戳
            $time = time();

            //获取并发任务
            if ($info = self::info(1)) {
                //并发任务分组统计
                foreach ($info as $k => &$v) {
                    //任务回调方法
                    $func = is_array($v['call']) ?
                        (is_array($temp = isset($v['call'][0]) ? $v['call'] : $v['call']['asCall']) ?
                            '[' . join(', ', $temp) . ']' : $temp
                        ) : $v['call'];
                    //初始化分类列表
                    isset($list[$func]) || $list[$func] = array(
                        //分类名称, 分类标识, 任务列表
                        'groupName' => $func, 'groupMark' => md5($func), 'taskList' => array(),
                        //执行并发, 最长时间, 最后启动时间
                        'concurrent' => 0, 'maxRunTime' => 0, 'datetime' => '',
                    );
                    //汇总分类引用
                    $list[$func]['taskList'][$k] = &$v;
                    //统计分类数据
                    foreach ($v['list'] as &$vl) {
                        //任务启动时间
                        $vl['datetime'] = date('Y-m-d H:i:s', $vl['timestamp'] = $vl['time']);
                        //格式化时间戳
                        unset($vl['time']);

                        //统计执行并发
                        $list[$func]['concurrent'] += 1;
                        //统计最长时间
                        $list[$func]['maxRunTime'] = max($time - $vl['timestamp'], $list[$func]['maxRunTime']);
                        //最后启动时间
                        $list[$func]['datetime'] = max($vl['datetime'], $list[$func]['datetime']);
                    }
                }

                //排序打印信息
                ($temp = self::getColumn($list, $sort ? $sort : 'maxRunTime')) && array_multisort($temp, SORT_DESC, $list);
                //汇总并发数量
                $temp = array_sum(self::getColumn($list, 'concurrent'));
                //打印分组信息
                echo '<hr>',
                    '<table border="1">',
                        '<tr>',
                            "<th><a href='{$rUrl}&sort=groupName'>groupName</a></td>",
                            "<th><a href='{$rUrl}&sort=concurrent'>concurrent($temp)</a></td>",
                            "<th><a href='{$rUrl}&sort=maxRunTime'>maxRunTime(s)</a></td>",
                            "<th><a href='{$rUrl}&sort=datetime'>lastExecTime</a></td>",
                        '</tr>';
                foreach ($list as &$v) {
                    $temp = array(
                        'mark' => "mark={$v['groupMark']}#{$v['groupMark']}' id='{$v['groupMark']}'",
                        'list' => $mark === $v['groupMark'] ?
                            '<tr><td colspan=4><pre>' . print_r($v['taskList'], true) . '</pre></td></tr>' : ''
                    );
                    echo '<tr>',
                            "<td><a href='{$rUrl}&sort={$sort}&{$temp['mark']}'>{$v['groupName']}</a></td>",
                            "<td>{$v['concurrent']}</td>",
                            "<td>{$v['maxRunTime']}</td>",
                            "<td>{$v['datetime']}</td>",
                        "</tr>", $temp['list'];
                }
                echo '</table>';
            }

            //打印分布式定时器节点
            if ($info = self::info(2)) {
                //生成节点运行时长(分钟)
                foreach ($info as $k => &$v) {
                    $v['warning'] = $v['prevSort'] > -1 && $v['prevSort'] < $v['nodeSort'] ? 'Yes' : 'No';
                    $v['duration'] = round(($v['sortTime'] - $v['nodeTime']) / 60, 1);
                    $v['nodeTime'] = date('Y-m-d H:i:s', $v['nodeTime']);
                    $v['sortTime'] = date('Y-m-d H:i:s', $v['sortTime']);
                }
                //排序打印信息
                ($temp = self::getColumn($info, $sort ? $sort : 'duration')) && array_multisort($temp, SORT_DESC, $info);
                //打印分布式定时器节点表格头
                echo '<hr><table border="1">',
                    '<tr>',
                        "<th><a href='{$rUrl}&sort=nodeAddr'>nodeAddr<a></td>",
                        "<th><a href='{$rUrl}&sort=prevSort'>prevSort<a></td>",
                        "<th><a href='{$rUrl}&sort=nodeSort'>nodeSort<a></td>",
                        "<th><a href='{$rUrl}&sort=warning'>prevSort < nodeSort?<a></td>",
                        "<th><a href='{$rUrl}&sort=duration'>sortTime - nodeTime(i)<a></td>",
                        "<th><a href='{$rUrl}&sort=nodeTime'>nodeTime<a></td>",
                        "<th><a href='{$rUrl}&sort=sortTime'>sortTime<a></td>",
                    '</tr>';
                //格式化节点列表
                foreach ($info as $k => &$v) {
                    echo '<tr>',
                            "<td>{$v['nodeAddr']}</td>",
                            "<td>{$v['prevSort']}</td>",
                            "<td>{$v['nodeSort']}</td>",
                            "<td>{$v['warning']}</td>",
                            "<td>{$v['duration']}</td>",
                            "<td>{$v['nodeTime']}</td>",
                            "<td>{$v['sortTime']}</td>",
                        "</tr>";
                }
                echo '</table>';
            }

            //输出计划任务配置 && 读取计划任务配置
            if ($list = self::getCron()) {
                //格式化任务列表
                foreach ($list as $k => &$v) {
                    //任务详细信息
                    $v['data'] = $mark === ($temp = md5($k)) ?
                        '<tr><td colspan=5><pre>' . print_r($v, true) . '</pre></td></tr>' : '';
                    //并发唯一标识
                    $v['mark'] = $temp;
                    //获取最后执行时间
                    $temp = 'of_base_com_timer::crontab#' . $v['time'] . serialize($v['call']);
                    $v['last'] = ($temp = of_base_com_kv::get($temp, '', '_ofSelf')) ?
                        date('Y-m-d H:i:s', is_array($temp) ? $temp['time'] : $temp) : '--';
                    //并发数量展示数据
                    $v['cNum'] = empty($v['cNum']) ? '' : $v['cNum'];
                    //编码任务名称
                    $v['info'] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');

                    //格式回调数组
                    if (is_array($index = &$v['call'])) {
                        //分析回调结构
                        if (isset($index[0])) {
                            $index = &$index[0];
                        } else {
                            $v['call'] = &$index['asCall'];
                            if (is_array($index['asCall'])) {
                                $index = &$index['asCall'][0];
                            } else {
                                $index = &$index['asCall'];
                            }
                        }
                        //对象转换成易读方式
                        is_object($index) && $index = get_class($index);
                        //回调转成单行数组展示
                        is_array($v['call']) && $v['call'] = '[' . join(', ', $v['call']) . ']';
                    } else {
                        //可能是调用对象 __invoke
                        is_object($index) && $index = 'new ' . get_class($index);
                    }
                }

                //排序打印信息
                ($temp = self::getColumn($list, $sort ? $sort : 'last')) && array_multisort($temp, SORT_DESC, $list);

                //打印计划任务表格头
                echo '<hr><table border="1">',
                    '<tr>',
                        "<th><a href='{$rUrl}&sort=call'>cronName<a></td>",
                        "<th><a href='{$rUrl}&sort=time'>cronTime<a></td>",
                        "<th><a href='{$rUrl}&sort=cNum'>concurrent<a></td>",
                        "<th><a href='{$rUrl}&sort=last'>lastExecTime<a></td>",
                        "<th><a href='{$rUrl}&sort=info'>cronInfo<a></td>",
                    '</tr>';
                //打印计划任务表格体
                foreach ($list as $k => &$v) {
                    //打印任务表单元
                    echo '<tr>',
                        "<td><a href='{$rUrl}&mark={$v['mark']}#{$v['mark']}' id='{$v['mark']}'>{$v['call']}</a></td>",
                        "<td>{$v['time']}</td>",
                        "<td>{$v['cNum']}</td>",
                        "<td>{$v['last']}</td>",
                        "<td>{$v['info']}</td>",
                    "</tr>", $v['data'];
                }
                //打印计划任务表格尾
                echo '</table>';
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
        //已触发的类型(让触发动作仅执行一次[防止浪费资源], 让同步执行阻塞触发动作[防止执行锁被解锁])
        static $once = null;
        //(已触发与需触发类型相同 && 异步触发动作 || 空节点名) && 不启动触发器
        if ($once === $name && $type === null || !$nodeId = &self::$config['nodeId']) return $result;
        //(未标记触发类型 || 同步执行触发) && 记录触发类型
        ($once === null || $type === true) && $once = $name;
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
            self::fireCalls(array(array('call' => array(
                'asCall' => 'of_base_com_timer::timer',
                'params' => array($name, true)
            ))), array('type' => 1));
            $result = false;
        //任务列表遍历检查
        } else if ($name === 'nodeLock') {
            //当前时间
            $time = 0;
            //全局节点列表键
            $listKey = 'of_base_com_timer::nodeList';
            //当前节点启动时间戳
            $nodeTime = time();
            //加锁全局节点列表
            of_base_com_data::lock($listKey, 2);
            //读取全局节点列表
            $list = of_base_com_kv::get($listKey, array(), '_ofSelf') + array($nodeId => array());
            //更新节点信息
            $list[$nodeId] = array(
                'nodeAddr' => $_SERVER['SERVER_ADDR'],
                'nodeTime' => $nodeTime,
                'sortTime' => $nodeTime,
                'prevSort' => -1
            ) + $list[$nodeId] + array('nodeSort' => count($list) - 1);
            //回写全局节点列表(永不过期)
            of_base_com_kv::set($listKey, $list, 0, '_ofSelf');
            //解锁全局节点列表
            of_base_com_data::lock($listKey, 3);

            while (!self::renew()) {
                //休眠后返回任务数量
                $needNum = self::getRunNum();

                //静态计划任务
                ($crontab = &self::crontab($needNum)) && self::fireCalls($crontab, array('type' => 2));
                //动态计划任务
                ($movTask = &self::taskList($needNum)) && self::fireCalls($movTask, array('type' => 4));

                //无任何任务
                if ($movTask === false && $crontab === false) sleep(30);
                //启动保护进程, 每10分钟执行一次
                if (($temp = time()) - $time > 600) {
                    //更新最后执行时间
                    $time = $temp;
                    //启动保护进程
                    self::timer('daemon');
                    //(全局节点列表键丢失 || 当前节点信息丢失 || 启动时间对不上) && 发生异常重启节点
                    if (
                        !($temp = of_base_com_kv::get($listKey, false, '_ofSelf')) ||
                        !isset($temp[$nodeId]) ||
                        $temp[$nodeId]['nodeTime'] !== $nodeTime
                    ) {
                        trigger_error($temp === false ?
                            'The k-v service unstable: _ofSelf' :
                            'The lock service unstable: _of.com.data.lock'
                        );
                        break ;
                    }
                }
            }

            //连接解锁
            of_base_com_data::lock($lock, 3);
        //保护进程
        } else if ($name === 'daemon') {
            //打开任务进程锁文件
            $nLock = 'of_base_com_timer::nodeLock#' . $nodeId;
            //任务回收器键
            $gLock = 'of_base_com_timer::taskIsGc';
            //是否为任务回收器
            $isGc = false;
            //当前时间
            $time = 0;

            //连接加锁(非阻塞) 兼容 glusterfs 网络磁盘
            while (!self::renew() && !of_base_com_data::lock($nLock, 6)) {
                //成为任务回收器 && 清理节点信息(2 | 1073741824)
                ($isGc || $isGc = of_base_com_data::lock($gLock, 6)) && self::info(1073741826);
                //等待30秒
                sleep(30);

                //每10分钟执行一次
                if (($temp = time()) - $time > 600) {
                    //更新最后执行时间
                    $time = $temp;
                    //清理任务列表(1 | 1073741824)
                    self::info(1073741825);
                }
            }
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
     *          #动态任务, 未指定taskObj参数
     *          "call" : 框架标准的回调
     *          "time" : 执行时间, 五年内秒数=xx后秒执行, 其它=指定时间
     *          "cNum" : 并发数量, 0=不设置, n=最大值, []=指定并发ID(最小值1)
     *          "try"  : 尝试相隔秒数, 默认[], 如:[60, 100, ...]

     *          #单子任务, taskObj返回任务对象
     *          "call" : 框架标准的回调

     *          #多子任务, taskObj返回 {任务标识 : 任务对象, ...}
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

            //异步模式 && 即时执行
            if (self::$nowTask && $params['time'] <= $nowTime) {
                //直接触发
                self::fireCalls(array(&$params), array('type' => 4));
            //同步模式 || 延迟执行
            } else {
                //限制并发 && 立刻执行
                if ($params['cNum'] && $params['time'] <= $nowTime) {
                    //任务等待执行并发
                    $temp = is_array($params['cNum']) ? $params['cNum'] : range(1, $params['cNum']);
                    //任务并发运行信息
                    $index = &self::data(null, $temp, $params['call']);
                    //移除已执行的并发
                    foreach ($temp as $k => &$v) {
                        if ($index['info'][$v]['isRun']) unset($temp[$k]);
                    }
                    //所有并发都在执行, 直接跳出, 降低存储压力
                    if (!$temp) return ;
                }

                //添加到待执行列表中
                self::taskList($params);
            }
        //批量任务
        } else if (isset($params['list'])) {
            //跟踪任务列表
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
            )), array('type' => 16));
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
            self::fireCalls(
                array(array('call' => &$params['call'])),
                array('mark' => $mark, 'type' => 8)
            );
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
        //空节点名 && 不启动触发器
        if (!$nodeId = &self::$config['nodeId']) return true;
        //节点进程锁
        $nodeLock = 'of_base_com_timer::nodeLock#' . $nodeId;
        //当前节点是否启动, 未启动解锁
        ($result = !of_base_com_data::lock($nodeLock, 6)) || of_base_com_data::lock($nodeLock, 3);
        //需要开启 && 尝试开启
        $result || $start && self::timer();
        //返回结果
        return $result;
    }

    /**
     * 描述 : 获取当期运行的信息
     *      type : 读取类型(可叠加), 1=并发的任务, 2=分布定时器, 4=当前任务
     * 返回 :
     *      type为1时 : 并发的任务 {
     *          任务唯一键 : {
     *              "call" : 统一回调结构
     *              "list" : 运行的任务列表 {
     *                  运行的序号 : {
     *                      "time" : 任务启动时间戳
     *                  }
     *              }
     *          }
     *      }
     *      type为2时 : 分布定时器 {
     *          节点ID : {
     *              "nodeAddr" : 节点IP地址
     *              "nodeTime" : 启动时间
     *              "nodeSort" : 在返回列表中的位置, 从0开始
     *              "sortTime" : 排序时间, 每次nodeSort变更时更新
     *              "prevSort" : 列表位置变更前的位置, 未变更为-1
     *          }
     *      }
     *      type为4时 : 获取当前任务, null=当前不在异步中, array={
     *          "task" : 同taskCall方法1参数格式
     *          "cArg" : 同taskCall方法2参数格式
     *      }
     *      type其它时 : 如1|2|4为7时 {
     *          "concurrent"  : type为1的结构,
     *          "taskTrigger" : type为2的结构,
     *          "nowTaskInfo" : type为4的结构
     *      }
     * 作者 : Edgar.lee
     */
    public static function &info($type) {
        $result = array();

        //读取并发数定时任务
        if ($type & 1) {
            $type === 1 ? $save = &$result : $save = &$result['concurrent'];
            $save = array();

            //清理模式
            if ($type & 1073741824) {
                //获取分布定时器
                $temp = self::info(2);
                //当前节点正在运行
                if (isset($temp[$nodeId = &self::$config['nodeId']])) {
                    //节点排序信息, [任务并发计数, 运行节点总数, 当前节点位置, 单个任务的首个并发]
                    $sort = array(-1, count($temp), $temp[$nodeId]['nodeSort']);
                //当前节点未运行
                } else {
                    //移除清理模式
                    $type &= ~1073741824;
                }
            }

            //全局任务列表键
            $tLock = 'of_base_com_timer::taskList';
            //读取全局节点列表
            $data = of_base_com_kv::get($tLock, array(), '_ofSelf');
            //遍历全局节点列表{任务ID : {}, ...}
            foreach ($data as $kt => &$vt) {
                //任务状态锁
                $taskLock = "of_base_com_timer::taskLock#{$kt}";
                //任务备注键
                $taskNoteKey = 'of_base_com_timer::taskNote#' . $kt;
                //任务信息键
                $taskInfoKey = 'of_base_com_timer::taskInfo#' . $kt;

                //单个任务备注
                $save[$kt] = $note = of_base_com_kv::get($taskNoteKey, array(), '_ofSelf');
                //读取单个任务信息
                $save[$kt] += of_base_com_kv::get($taskInfoKey, array(), '_ofSelf') + array('list' => array());

                //清理模式
                if ($type & 1073741824) {
                    //获取单个任务的首个并发
                    $sort[3] = key($save[$kt]['list']);
                    //遍历任务信息
                    foreach ($save[$kt]['list'] as $k => &$v) {
                        //任务并发被分配到当前节点, 开始判断是否需清理
                        if (++$sort[0] % $sort[1] === $sort[2]) {
                            //是当前任务的首个并发
                            if ($sort[3] === $k) {
                                //任务没运行(加锁成功)
                                if (of_base_com_data::lock($taskLock, 6)) {
                                    //获取全局任务列表独享锁
                                    of_base_com_data::lock($tLock, 2);
                                    //读取全局节点列表
                                    if ($temp = of_base_com_kv::get($tLock, array(), '_ofSelf')) {
                                        //清理任务列表数据
                                        unset($temp[$kt], $save[$kt]);
                                        //回写全局任务列表(永不过期)
                                        of_base_com_kv::set($tLock, $temp, 0, '_ofSelf');
                                    }
                                    //解锁全局任务列表
                                    of_base_com_data::lock($tLock, 3);

                                    //清理备注数据
                                    of_base_com_kv::del($taskNoteKey, '_ofSelf');
                                    //清理信息数据
                                    of_base_com_kv::del($taskInfoKey, '_ofSelf');

                                    //解除任务独享锁
                                    of_base_com_data::lock($taskLock, 3);
                                //任务已运行, 更新备注有效期(当数据正常时)
                                } else {
                                    of_base_com_kv::set($taskNoteKey, $note, 2592000, '_ofSelf');
                                }
                            }

                            //当前任务的并发未运行
                            if (of_base_com_data::lock($sTaskLock = "{$taskLock}#{$k}", 5)) {
                                //加锁任务信息键
                                of_base_com_data::lock($taskInfoKey, 2);
                                //单个任务信息成功
                                if ($temp = of_base_com_kv::get($taskInfoKey, array(), '_ofSelf')) {
                                    //清理模式使用, 清理列表数据
                                    unset($temp['list'][$k], $save[$kt]['list'][$k]);
                                    //更新信息有效期
                                    of_base_com_kv::set($taskInfoKey, $temp, 2592000, '_ofSelf');
                                }
                                //解除任务信息锁
                                of_base_com_data::lock($taskInfoKey, 3);
                                //解除单任务并发锁
                                of_base_com_data::lock($sTaskLock, 3);
                            }
                        }
                    }
                //读取异常(瞬间清理或网络错误), 不记入结果
                } else if (empty($save[$kt]['call'])) {
                    unset($save[$kt]);
                }
            }
        }

        //分布定时器执行情况
        if ($type & 2) {
            $type === 2 ? $save = &$result : $save = &$result['taskTrigger'];
            //当前时间戳
            $time = time();
            //全局节点列表键
            $listKey = 'of_base_com_timer::nodeList';
            //节点同步时间戳键
            $syncKey = 'of_base_com_timer::nodeSync';
            //读取全局节点列表
            $save = of_base_com_kv::get($listKey, array(), '_ofSelf');

            //清理未启动监控,  && $_SERVER['REQUEST_TIME'] - $v['time'] > 3600
            if ($type & 1073741824) {
                //全局节点列表为空, 发生节点读取异常
                $save || trigger_error('The k-v service unstable: _ofSelf');
                //节点计数, [当前排序位置, 节点排序变更]
                $sort = array(0, false);
                //遍历节点列表{节点ID : {}, ...}
                foreach ($save as $k => &$v) {
                    //节点锁键
                    $nLock = 'of_base_com_timer::nodeLock#' . $k;

                    //监控未开启
                    if (of_base_com_data::lock($nLock, 6)) {
                        //加锁全局节点列表
                        of_base_com_data::lock($listKey, 2);
                        //读取全局节点列表
                        $temp = of_base_com_kv::get($listKey, array(), '_ofSelf');
                        //移除无效节点, 移除未启动监控
                        unset($temp[$k], $save[$k]);
                        //回写全局节点列表(永不过期)
                        of_base_com_kv::set($listKey, $temp, 0, '_ofSelf');
                        //解锁全局节点列表
                        of_base_com_data::lock($listKey, 3);
                        //解锁
                        of_base_com_data::lock($nLock, 3);
                    //监控已开启
                    } else {
                        //节点排序变更
                        if ($save[$k]['nodeSort'] !== $sort[0]) {
                            $save[$k]['prevSort'] = $save[$k]['nodeSort'];
                            $save[$k]['nodeSort'] = $sort[0];
                            $save[$k]['sortTime'] = $time;
                            //标记节点排序变更
                            $sort[1] = true;
                        }
                        //节点排序递增
                        $sort[0] += 1;
                    }
                }

                //节点排序变更
                if ($sort[1]) {
                    //加锁全局节点列表
                    of_base_com_data::lock($listKey, 2);
                    //合并全局节点列表
                    $temp = $save + of_base_com_kv::get($listKey, array(), '_ofSelf');
                    //回写全局节点列表(永不过期)
                    of_base_com_kv::set($listKey, $temp, 0, '_ofSelf');
                    //解锁全局节点列表
                    of_base_com_data::lock($listKey, 3);
                }
                //更新节点同步时间戳
                of_base_com_kv::set($syncKey, time(), 1800, '_ofSelf');
            //节点同步时间戳超时, 节点列表无效
            } else if ($time - of_base_com_kv::get($syncKey, 0, '_ofSelf') > 300) {
                $save = array();
            }
        }

        //当前执行的任务信息
        if ($type & 4) {
            $type === 4 ? $save = &$result : $save = &$result['nowTaskInfo'];
            $save = self::$nowTask;
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
            //使用缓存锁, 通过守护进程更新
            $lock = $cIds === 1 && $data === null;

            //遍历列表
            if (is_int($cIds)) {
                //任务信息键
                $taskInfoKey = 'of_base_com_timer::taskInfo#' . $call;
                //读取消息
                $cIds = of_base_com_kv::get($taskInfoKey, array('list' => array()), '_ofSelf');
                //在异步并发中读取自身时没数据一定是k-v服务不稳定
                if (isset($nowTask['cArg']['cMd5']) && !isset($cIds['list'][$nowTask['cArg']['cCid']])) {
                    trigger_error('The k-v service unstable: _ofSelf');
                    exit;
                //读取并发ID
                } else {
                    $cIds = array_keys($cIds['list']);
                }
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
                $isRun = $isSelf || $lock ? true : !of_base_com_data::lock($taskNumsLock, 5);

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
                            ($cache === null ? $cache = of_base_com_kv::get($dKey, array(), '_ofSelf') : $cache) :
                            of_base_com_kv::get($dKey, array(), '_ofSelf')
                        )
                    );

                    //修改数据(自身进程 || 未运行)
                    if ($isSelf || !$isRun) {
                        //合并写入
                        if (is_array($data)) {
                            //合并数据
                            $data += $index['data'];
                            //数据不为空(防止读失败导致意外清空) && 写入数据并更新缓存
                            if ($data) {
                                of_base_com_kv::set($dKey, $data, 86400, '_ofSelf');
                                $isSelf && $cache = $data;
                            }
                        //删除数据并更新缓存
                        } else if ($data === false) {
                            of_base_com_kv::del($dKey, '_ofSelf');
                            $isSelf && $cache = array();
                        }
                    }
                }

                //并发任务运行中 || 解锁未运行的进程
                $isRun || $lock || of_base_com_data::lock($taskNumsLock, 3);
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
        //清除文件状态缓存
        clearstatcache();

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
     *          "cMd5" :o回调唯一值, 并发时存在
     *          "cCid" :o并发ID, 从1开始, 并发时存在
     *          "mark" :o数据回传标识, 存在时标识回传, 子任务存在
     *          "type" : 任务类型, 1=定时器, 2=静态任务, 4=动态任务, 8=单子任务, 16=多子任务
     *      }
     * 作者 : Edgar.lee
     */
    public static function taskCall($call, $cArg) {
        //保护linux进程不被SIGTERM信号杀掉 && 信号1~32(9 19 32 linux 无效, 17 mac 无效)
        ($cArg['type'] & 1) || function_exists('pcntl_signal') && pcntl_signal(15, SIG_IGN);

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
            'this' => $cArg
        );

        //启用并发
        if (isset($cArg['cMd5'])) {
            //任务状态锁
            $taskLock = 'of_base_com_timer::taskLock#' . $cArg['cMd5'];
            //空间锁参数
            $lockArgv = array('space' => __METHOD__);
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
                //读取全局节点列表
                $temp = of_base_com_kv::get($tLock, array(), '_ofSelf') + array($cArg['cMd5'] => array());
                //回写全局任务列表(永不过期)
                of_base_com_kv::set($tLock, $temp, 0, '_ofSelf');
                //解锁全局任务列表
                of_base_com_data::lock($tLock, 3, $lockArgv);
            }

            //更新任务备注数据, 当前cCid是并发组的第一个
            if ($cArg['cCid'] === (is_array($call['cNum']) ? reset($call['cNum']) : 1)) {
                //格式回调数组
                if (is_array($index = &$call['call'])) {
                    $temp = array('call' => json_decode(json_encode($index), true));
                    //分析回调结构
                    if (isset($index[0])) {
                        $temp += array(&$temp['call'][0], &$index[0]);
                    } else if (is_array($index['asCall'])) {
                        $temp += array(&$temp['call']['asCall'][0], &$index['asCall'][0]);
                    } else {
                        $temp += array(&$temp['call']['asCall'], &$index['asCall']);
                    }
                    //对象转换成易读方式
                    is_object($temp[1]) && $temp[0] = get_class($temp[1]);
                } else {
                    //可能是调用对象 __invoke
                    $temp = array('call' => is_object($index) ? 'new ' . get_class($index) : $index);
                }
                //回写任务备注数据
                of_base_com_kv::set('of_base_com_timer::taskNote#' . $cArg['cMd5'], array(
                    'call' => $temp['call']
                ), 2592000, '_ofSelf');
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
                //按运行序号排序
                ksort($temp['list']);
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
        if (of_base_com_net::isCli()) {
            //windows系统 ? 查询进程是否存在 : 发送进程信号
            $isOk = self::$config['osType'] === 'win' ? !!strpos(
                stream_get_contents(popen("TASKLIST /FO LIST /FI \"PID eq {$pid}\"", 'r'), 1024),
                (string)$pid
            ) : posix_kill($pid, 0);
        }

        return $isOk;
    }

    /**
     * 描述 : 获取计划任务配置
     * 作者 : Edgar.lee
     */
    private static function &getCron() {
        is_file(self::$config['cron']['path']) && $cron = include self::$config['cron']['path'];
        empty($cron) && $cron = array();
        return $cron;
    }

    /**
     * 描述 : 返回数组中指定列值, 实现php<5.5的array_column方法
     * 作者 : Edgar.lee
     */
    private static function getColumn($d, $a) {
        $r = array();
        foreach ($d as $k => &$v) is_array($v) && array_key_exists($a, $v) && $r[] = $v[$a];
        return $r;
    }

    /**
     * 描述 : 根据服务器空闲资源休眠并返回执行并发任务数
     * 作者 : Edgar.lee
     */
    private static function getRunNum() {
        //引用配置
        $config = &self::$config;

        //不支持php命令
        if (!of_base_com_net::isCli()) {
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
     *          KEY `idx_time` (`time`) USING BTREE
     *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='框架计划任务存储列表';
     * 作者 : Edgar.lee
     */
    private static function &taskList($mode) {
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
                try {
                    $task = of_base_com_disk::file($fp, true, true);
                //任务读取失败
                } catch (Exception $e) {
                    of::event('of::error', true, $e);
                    $task = array();
                }

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
                    try {
                        $task = of_base_com_disk::file($fp, true, true);
                    //任务读取失败
                    } catch (Exception $e) {
                        of::event('of::error', true, $e);
                        $task = array();
                    }

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
                $sql = "SELECT 1 FROM `_of_com_timer` LIMIT 1";
                $temp = of_db::sql($sql, $config['params']['dbPool']);

                //是否有数据 && 读取详细信息
                if (($result = !empty($temp)) && is_int($mode)) {
                    //读取所需数量任务
                    $sql = "SELECT
                        `hash`
                    FROM
                        `_of_com_timer`
                    WHERE
                        `time` <= '{$nowtime}'
                    LIMIT
                        {$mode}";
                    $list = of_db::sql($sql, $config['params']['dbPool']);
                    $list = join('\',\'', self::getColumn($list, 'hash'));

                    //开启事务
                    of_db::sql(null, $config['params']['dbPool']);
                    //通过hash加锁读取所需数量任务, 避免idx_time与增删改的PRIMARY发生死锁
                    $sql = "SELECT
                        `hash`, `task`
                    FROM
                        `_of_com_timer`
                    WHERE
                        `hash` IN ('{$list}')
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

                    //提交事务
                    of_db::sql(true, $config['params']['dbPool']);
                }
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
                'nowNode' => explode(' ', date('i H d m w', $lastTime)),
                //每节点最大数
                'maxNums' => array(60, 24, date('t', $lastTime) + 1, 13, 7)
            );
        }

        if ($timeList) {
            //最新静态任务
            if ($cron = self::getCron()) {
                //无效任务数
                if ($needNum < 1) return $result;

                foreach ($cron as &$vt) {
                    //每项时间分割
                    $item = preg_split('@\s+@', trim($vt['time']));

                    foreach ($timeList as &$timeBox) {
                        foreach ($item as $ki => &$vi) {
                            //当前节点时间
                            $tItem = &$timeBox['nowNode'][$ki];
                            //当前节点最大值
                            $mItem = &$timeBox['maxNums'][$ki];
                            //每列时间集合[14-30/3]
                            preg_match_all('@(-?\d+|\*)(?:-(-?\d+))?(?:/(\d+))?(,|$)@', $vi, $list, PREG_SET_ORDER);

                            foreach ($list as &$vl) {
                                //负值转正值
                                (int)$vl[1] < 0 && $vl[1] += $mItem;
                                //负值转正值
                                (int)$vl[2] < 0 && $vl[2] += $mItem;

                                //x 模式
                                if ($vl[2] === '') {
                                    $temp = $tItem == $vl[1] || $vl[1] === '*';
                                //大-小 模式
                                } else if ($vl[1] > $vl[2]) {
                                    $temp = $tItem >= $vl[1] || $tItem <= $vl[2];
                                //小-大 模式
                                } else {
                                    $temp = $tItem >= $vl[1] && $tItem <= $vl[2];
                                }

                                //范围通过 && 频率通过(不需要 || 在范围内 && 可整除)
                                if (
                                    $temp && (
                                        !$vl[3] ||
                                        $tItem >= $vl[1] &&
                                        ($tItem - (int)$vl[1]) % $vl[3] === 0
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
                                //记录最后更新进度(32天过期)
                                of_base_com_kv::set($tKey, $temp, 2764800, '_ofSelf');
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
     *          "type" : 任务类型, 1=定时器, 2=静态任务, 4=动态任务, 8=单子任务, 16=多子任务
     *      }
     * 作者 : Edgar.lee
     */
    private static function fireCalls($list, $cArg = array()) {
        //引用配置
        $config = &self::$config;

        foreach ($list as &$v) {
            //单计划
            if (empty($v['cNum'])) {
                //触发任务
                call_user_func($config['forkFn'], array(
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
                            call_user_func($config['forkFn'], array(
                                'asCall' => 'of_base_com_timer::taskCall',
                                'params' => array(
                                    &$v, array('cMd5' => $cMd5, 'cCid' => $cNum) + $cArg
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
//仅允许访问控制台页面
return join('::', of::dispatch()) === 'of_base_com_timer::index';