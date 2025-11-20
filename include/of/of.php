<?php
//版本号
define('OF_VERSION', 200280);

/**
 * 描述 : 控制层核心
 * 作者 : Edgar.lee
 */
class of {
    //站点配置文件
    private static $config = null;
    //注册的 L 类方法
    private static $links = array(
        /**
         * 描述 : 获取翻译文本
         * 参数 :
         *      s : 翻译文本
         *      o : 翻译参数 {
         *          "key"   :o翻译区分标识符, 默认=""
         *          "trace" :o调用跟踪文件定位(默认0=调用此方法文件, 1=0的父方法, ...)
         *          "file"  :o指定调用磁盘路径, 绝对路径
         *      }
         */
        'getText' => 'public static function &getText($s) {return $s;}',

        /**
         * 描述 : 开关SESSION
         * 参数 :
         *      type : true=开启, false=关闭
         */
        'session' => 'public static function session($type = true) {
            $type ? @session_start() : session_commit();
        }'
    );
    //是否支持命名空间
    private static $isSpace = false;
    //是否抛出语法错误
    private static $isParse = false;
    //工作流程错误队列 [{"code" : 编码, "info" : 错误, "file" : 路径, "line" : 行数, "uuid" : 标识}, ...]
    private static $workErr = array(null);
    //实际最后一次错误
    private static $lastErr = null;

    /**
     * 描述 : 初始化框架
     * 作者 : Edgar.lee
     */
    public static function init() {
        //注册spl
        spl_autoload_register('of::loadClass');
        //注册协议
        stream_wrapper_register('of.eval', __CLASS__);
        //注册::halt事件
        register_shutdown_function('of::event', 'of::halt', true);

        //支持命名空间
        self::$isSpace = version_compare(PHP_VERSION, '5.3.0', '>=');
        //抛出语法错误
        self::$isParse = version_compare(PHP_VERSION, '7.0.0', '>=');
        //加载系统配置
        self::loadSystemEnv();

        //异常调用回溯"getTrace"方法, 返回参数 php>=7.4
        ini_set('zend.exception_ignore_args', '0');
        //隐藏原生错误
        ini_set('display_errors', false);
        //防止禁用错误
        error_reporting(E_ALL);
        //监听系统错误
        set_error_handler('of::saveError');
        //监听系统异常
        set_exception_handler('of::saveError');
        //监听致命错误
        self::event('of::halt', 'of::saveError');
        //监听代码错误
        self::event('of::error', 'of::saveError');
        //监听 SQL错误
        self::event('of_db::error', 'of::saveError');

        //预先加载类
        if (isset(self::$config['_of']['preloaded'])) {
            foreach (self::$config['_of']['preloaded'] as &$v) self::loadClass($v);
        }

        //生成 L 类
        $temp = 'class _ofWorkException extends Exception {public $data;}' .
            'class L {' . join(self::$links) . "\n}";
        if ($temp = self::syntax($temp, true, true)) {
            throw new Exception("{$temp['info']} on line {$temp['line']}\n----\n{$temp['tips']}");
        }
        self::$links = null;
    }

    /**
     * 描述 : 设置调度信息
     * 参数 :
     *      class  : 读取调度或设置类名称, null(默认)=读取调度{"class" : 类名, "action" : 方法}, 字符串=("class" | "action")读取调度值
     *      action : 调用的方法名, null(默认)=读取调度, 字符串=设置方法名
     *      check  : 加载类返回值校验,false=永不通过,null=永远通过
     * 返回 :
     *      返回null
     * 注明 :
     *      of::dispatch事件 : 调度时触发事件,无回调参数
     * 作者 : Edgar.lee
     */
    public static function dispatch($class = null, $action = null, $check = false) {
        static $dh = array('class' => '', 'action' => '');
        //读取调度
        if ($action === null) {
            return isset($dh[$class]) ? $dh[$class] : array(
                'class'  => $dh['class'],
                'action' => $dh['action'],
            );
        } else {
            //记录调度入口
            $dh = array('class' => &$class, 'action' => &$action);

            //触发 of::dispatch 事件
            self::event('of::dispatch', true, array(
                'class'  => &$class,
                'action' => &$action,
                'check'  => &$check,
            ));

            $temp = class_exists($class, false) ? $check : self::loadClass($class);
            if (
                ($temp !== false && class_exists($class, false)) &&
                ($check === null || $check === $temp) &&
                is_callable(array($temp = new $class, &$action))
            ) {
                return $temp->$action();
            }
        }
    }

    /**
     * 描述 : 读取config数据
     * 参数 :
     *      name    : 配置名,以'.'分割读取深层数据
     *      default : 默认值(null), 第一次将被缓存
     *      action  : 功能操作
     *          0=无任何操作
     *          1=读取到的数据格式化成磁盘路径
     *          2=读取到的数据格式化成网络路径
     *          4=本次使用实时配置读取数据
     * 返回 :
     *      成功返回值,失败返回默认值
     * 作者 : Edgar.lee
     */
    public static function config($name = null, $default = null, $action = 0) {
        //缓存配置, 动态配置
        static $memory = array('cache' => null, 'claim' => null);
        //功能操作别名
        static $aAlias = array('off' => 0, 'dir' => 1, 'url' => 2);

        //功能符别名转化
        is_int($action) || $action = &$aAlias[$action];

        //本次使用实时配置
        if ($action & 4) {
            //加载最新配置文件
            $of = self::safeLoad(OF_DIR . '/config.php');
            $of['config'] = isset($of['config']) ? (array)$of['config'] : array();
            empty($of['config'][0]) || $config = self::safeLoad(ROOT_DIR . $of['config'][0], array('of' => &$of));
            $config['_of'] = &self::arrayReplaceRecursive($of, $config['_of']);
        //使用缓存配置
        } else {
            $cache = &$memory['cache'];
            $claim = &$memory['claim'];
            //配置文件引用
            $config = &self::$config;
        }

        //读取of::config事件列表
        $event = &self::event('of::config', null);
        //触发 of::config 事件, (读取实时配置 || 事件有变化)
        if ($action & 4 || $event['change'] && !$event['change'] = false) {
            //事件触发参数
            $params = array('config' => &$config);
            //遍历触发事件
            foreach ($event['list'] as $k => &$v) {
                ($action & 4 || $v['change'] && !$v['change'] = false) && self::callFunc($v['event'], $params);
            }
            //重置事件缓存
            $cache = null;
        }

        //有缓存
        if (isset($cache[$name][$action])) {
            $default = &$cache[$name][$action];
        } else {
            //初始化动态配置
            if (!isset($claim)) {
                $claim = $config['_of']['config'];
                unset($claim[0]);
                ksort($claim);
            }

            //寻找动态配置
            foreach ($claim as $k => &$v) {
                if ($name === $k || !strncmp(
                    $name . '.', $k . '.',
                    min(strlen($name), strlen($k)) + 1
                )) {
                    $vaule = &self::getArrData($k, $config, null, 2);
                    $vaule = include ROOT_DIR . $v;
                    unset($claim[$k]);
                }
            }

            //引用数据
            $vaule = &$cache[$name][$action];
            //数组定位
            $vaule = self::getArrData(array(&$name, &$config, &$default, 2));

            //格式成 磁盘(1) 或 网络(2) 路径 && 值不为NULL
            if ($action & 3 && $vaule !== null) {
                $vaule = self::formatPath($vaule, $action & 1 ? ROOT_DIR : ROOT_URL);
            }

            $default = &$vaule;
        }

        return $default;
    }

    /**
     * 描述 : 为类提供回调事件
     * 参数 :
     *      type   : 事件类型
     *      event  : true=触发事件,false=删除事件,null=管理事件,其它=添加事件(符合回调结构)
     *      params : 触发时=自定义参数[_]键的值,删除时=指定删除的回调,添加时=true为特殊结构(不在触发事件范围内),默认null
     * 返回 :
     *      触发时返回一个数组包含所有触发返回的数据,管理时返回事件数据,其它无返回值
     * 注明 :
     *      事件列表结构如下($eventList) : {
     *          事件类型 : {
     *              "change" : 添加删除时会变true
     *              "list"   : [{
     *                  "isCall" : true=是回调类型,false=特殊结构
     *                  "isExec" : true=可以执行, false=正在执行
     *                  "event"  : 回调事件
     *                  "change" : 新加时会为true
     *              }]
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function &event($type, $event, $params = null) {
        //事件列表
        static $eventList = null;
        //执行列表
        static $waitExec = array();

        //初始化事件列表
        isset($eventList[$type]) || $eventList[$type] = array(
            'change' => false,
            'list'   => array()
        );

        //触发事件
        if ($event === true) {
            //待执行列表
            $waitExec[] = array(
                'type'   => $type,
                'offset' => -1,
                'params' => &$params,
                'result' => array()
            );

            do {
                //创建事件数组引用参数
                extract($waitExec[0], EXTR_REFS);
                $nList = &$eventList[$type]['list'];

                while ($k = array_slice($nList, ++$offset, 1, true)) {
                    //提取引用数据
                    $k = key($k);
                    $v = &$nList[$k];
                    //标准回调 && 可以回调
                    if ($v['isCall'] && $v['isExec']) {
                        //防止循环回调
                        $v['isExec'] = false;
                        try {
                            //保存嵌套事件结果位置
                            $result[$k] = null;
                            $result[$k] = &self::callFunc($v['event'], $params);
                        } catch (Exception $e) {
                            self::event('of::error', true, $e);
                        } catch (Error $e) {
                            self::event('of::error', true, $e);
                        }
                        $v['isExec'] = true;
                    }
                }

                array_shift($waitExec);
            } while (isset($waitExec[0]));
        //管理事件
        } else if ($event === null) {
            $result = &$eventList[$type];
        //增删改事件
        } else {
            //引用当前列表
            $nList = &$eventList[$type]['list'];
            //引用事件
            $event === false ? $index = &$params : $index = &$event;
            //删除事件
            foreach ($nList as $k => &$v) {
                //定位到事件
                if ($v['event'] == $index) {
                    //删除事件
                    if ($event === false) {
                        if (
                            //正在执行事件 && 执行事件为删除事件
                            isset($waitExec[0]) && $waitExec[0]['type'] === $type &&
                            //执行事件在删除的事件之后
                            key(array_slice($nList, $waitExec[0]['offset'], 1, true)) >= $k
                        ) {
                            //执行偏移减一
                            $waitExec[0]['offset'] -= 1;
                        }

                        $eventList[$type]['change'] = true;
                        unset($nList[$k]);
                    //添加事件(仅修改不需额外添加)
                    } else {
                        $v['isCall'] = !$params;
                        $event = false;
                    }

                    //跳出事件循环
                    break;
                }
            }

            //添加事件
            if ($event !== false) {
                $eventList[$type]['change'] = true;
                $nList[] = array(
                    //标准回调
                    'isCall' => !$params,
                    //可以回调
                    'isExec' => true,
                    'event'  => &$event,
                    'change' => true
                );
            }
        }
        return $result;
    }

    /**
     * 描述 : 注册L类的方法, 在预加载完成后生成
     * 参数 :
     *      name   : 方法名, 可以用 &name 来表示返回引用
     *      args   : 参数列表, 参考 create_function
     *      code   : 方法体, 参考 create_function
     *      static : 是否为静态函数, true=静态, false=动态
     * 返回 :
     *      true=成功, false=失败
     * 作者 : Edgar.lee
     */
    public static function link($name, $args, $code, $static = true) {
        $links = &self::$links;

        if ($links === null) {
            return false;
        } else {
            $static = $static ? 'static' : '';
            $links[ltrim($name, '&')] = "\npublic {$static} function {$name}({$args}) {\n{$code}\n}";
            return true;
        }
    }

    /**
     * 描述 : 管理工作流程, 独立的 时间 队列 错误及事务, 让代码更简洁
     *      工作可以嵌套, 产生任何错误, 事务都会回滚, 嵌套工作会创建额外数据库连接
     *      可以使用 try catch 或 回调方式 开始一个工作
     *      可以获取 当前工作开始时间 与 产生的错误
     *      可以抛出 工作异常 并通过捕获简化代码逻辑
     * 参数 :
     *     #开启工作(数组, null)
     *      code : 监听数据库连接, 产生问题会自动回滚, 数组=[连接池, ...], null=自动监听
     *      info : 功能参数
     *          int=增加数据监控, 0为当前工作, 1=父层工作..., 指定工作不存在抛出异常
     *          框架回调结构=回调模式创建工作, 不需要 try catch, 回调接收(params)参数 {
     *                  "result" : &标准结果集
     *                  "data"   : &标准结果集中的data数据
     *              }
     *              返回 false 时, 回滚工作, 等同 of::work(200, 'Successful', params['data'])
     *              返回 array 时, 赋值data, 等同 params['data'] = array;
     *      data : null=启动集成工作, 统一监听子孙工作事务, 启动时自动设置自动监听

     *     #结束工作(布尔)
     *      code : 完结事务, true=提交, false=回滚

     *     #抛出异常(数字)
     *      code : 问题编码, [400, 600)之间的数字
     *      info : 提示信息
     *      data : 扩展参数, 一个数组

     *     #捕捉异常(对象)
     *      code : 异常对象, 通过 try catch 捕获的异常

     *     #获取时间(文本)
     *      code : 固定"time"
     *      info : 返回时间格式, 默认2=格式化时间, 1=时间戳, 3=[时间戳, 格式化, 时区标识, 格林时差]

     *     #操作错误(文本)
     *      code : 固定"error"
     *      info : 默认true=获取错误, false=清除错误

     *     #全局排除监听(文本)
     *      code : 固定"block"
     *      info : 排查的监听列表, {
     *          "数据库连接池" : true=排除, false=移除
     *          ...
     *      }

     *     #工作信息(文本)
     *      code : 固定"info"
     *      info : 获取指定"info"信息, 默认=3(1 | 2), 1=工作ID, 2=监听数据库, 4=注入回调信息

     *     #延迟回调(文本) 在工作事务提交前按队列顺序执行
     *      code : 固定"defer"
     *      info : 回调方法接收参数结构 {"wuid" : 工作ID, "isOk" : true=最终提交 false=最终回滚}
     *          true = 读取指定标识的回调
     *          false = 删除指定标识的回调
     *          框架回调结构 = 不开启工作直接回调, 若报错将影响当前工作执行结果
     *          {"onWork" : 监听数据库, "asCall" : 框架回调, "params" :o回调参数} = 在新工作中回调
     *      data : 回调唯一标识, 默认=随机标识, 字符串=指定标识

     *     #完成回调(文本) 在工作事务提交后(在父级工作中)按队列顺序执行
     *      code : 固定"done"
     *      info : 回调方法接收参数结构 {"wuid" : 工作ID, "isOk" : true=最终提交 false=最终回滚}
     *          true = 读取指定标识的回调
     *          false = 删除指定标识的回调
     *          框架回调结构 = 不开启工作直接回调, 若报错将影响父级工作执行结果
     *          {"onWork" : 监听数据库, "asCall" : 框架回调, "params" :o回调参数} = 在新工作中回调
     *      data : 回调唯一标识, 默认=随机标识, 字符串=指定标识
     * 返回 :
     *     #开启工作(数组)
     *      失败抛出异常, 成功 {"code" : 200, "info" : "Successful", "data" : []}

     *     #结束工作(布尔)
     *      失败抛出异常

     *     #抛出异常(数字)
     *      抛出工作异常

     *     #捕捉异常(对象)
     *      其它异常继续抛出, 为工作异常返回 {
     *          "code" : 异常状态码
     *          "info" : 提示信息
     *          "data" : 问题数据
     *      }

     *     #获取时间("time")
     *      返回当前工作的开始时间, 未在工作中抛出异常

     *     #操作错误("error")
     *      未在工作中依然生效, 没错误返回null, 有错误返回 {
     *          "code" : 编码,
     *          "info" : 错误,
     *          "file" : 路径,
     *          "line" : 行数,
     *          "uuid" : 标识
     *      }

     *     #全局排除监听("block")
     *      排查的监听列表 {
     *          "数据库连接池" : true
     *          ...
     *      }

     *     #工作信息("info")
     *      不在工作中返回 null
     *      指定项存在, 返回项信息, 单项返回值, 多项返回数组 {
     *              1"wuid"  : 工作ID,
     *              2"list"  : [监听连接池, ...],
     *              4"defer" :&{回调ID : 回调信息, ...}
     *              4"done"  :&{回调ID : 回调信息, ...}
     *          }

     *     #延迟回调("defer")
     *     #完成回调("done")
     *      info为true时, 返回回调信息, 不存在返回null, 不在工作中抛出异常
     * 注明 :
     *      监听栈列表结构($sList) : [{
     *          "wuid"  : 工作ID,
     *          "time"  : [时间戳, 格式化, 时区标识 Europe/London, 格林差异 ±00:00],
     *          "dyna"  : 是否监听新连接池, true=是, false=否
     *          "unify" : 集成工作增量, 0=未开启集成工作, >=1为集成工作增量
     *          "list"  : 监听的连接池 {
     *              数据池 : 被克隆数据池,
     *              ...
     *          },
     *          "defer" : 延迟执行回调 {
     *              回调标识 : 框架回调结构,
     *              ...
     *          },
     *          "done"  : 完成执行回调 {
     *              回调标识 : 框架回调结构,
     *              ...
     *          }
     *      }, ...]
     *      返回的状态码一览表
     *          500 : 发生内部错误(代码报错)
     * 作者 : Edgar.lee
     */
    public static function work($code, $info = '', $data = array()) {
        //监听栈列表
        static $sList = array();
        //黑名单列表
        static $block = array();
        //数组传参模式
        $code === 'extr' && extract($info, EXTR_REFS);

        //数组=开启工作
        if (is_array($code) || $code === null) {
            //引用指定工作
            if (is_int($info)) {
                //数据库回调
                if (isset($data['pool'])) {
                    if (
                        //存在工作
                        isset($sList[$info]) &&
                        //已监听的不在执行
                        !isset($sList[$info]['list'][$data['pool']]) &&
                        //在数据库自动监听里
                        $temp = $sList[$info]['dyna']
                    ) {
                        //定位所属的数据库监听工作
                        $info += $temp - 1;
                        //定位所属的集成工作
                        $sList[$info]['unify'] && $info += $sList[$info]['unify'] - 1;
                        //引用增量工作
                        $index = &$sList[$info];
                        //新增的连接池
                        $code[] = $data['pool'];
                    }
                //工作存在
                } else if (isset($sList[$info])) {
                    //统一工作增量
                    $sList[$info]['unify'] && $info += $sList[$info]['unify'] - 1;
                    //引用增量工作
                    $index = &$sList[$info];
                //工作不存在
                } else {
                    throw new Exception('Work does not exist: ' . $info);
                }
            //添加新工作流
            } else {
                //集成工作 && 开启自动监控
                $data === null && $code = null;
                //多段数据库监听, 统一工作增量
                $temp = isset($sList[0]) && $sList[0]['unify'] ?
                    $sList[0]['unify'] + 1 : (int)($data === null);
                //压入工作问题栈表
                array_unshift(self::$workErr, null);
                //压入栈列表
                array_unshift($sList, array(
                    'wuid'  => uniqid(),
                    'time'  => array($time = time()) + explode('|', date('|Y-m-d H:i:s|e|P', $time)),
                    'dyna'  => $code === null ? 1 : (isset($sList[0]) && $sList[0]['dyna'] ?
                        $sList[0]['dyna'] + 1 : 0
                    ),
                    'unify' => $temp,
                    'list'  => array(),
                    'back'  => array(),
                    'defer' => array(),
                    'done'  => array()
                ));
                //引用增量工作
                $index = &$sList[$temp ? $temp - 1 : 0];
            }

            //监听连接池不为空
            if ($code) {
                //当前时区标识
                $nowTz = date_default_timezone_get();
                //当前时区与工作时区相同 || 恢复到工作时区
                $nowTz === $index['time'][2] || date_default_timezone_set($index['time'][2]);
                //遍历开启事务
                foreach ($code as &$v) {
                    //去重数据库连接池 && 不在黑名单中
                    if (empty($index['list'][$v]) && empty($block[$v])) {
                        if (
                            //数据库已连接
                            ($temp = of_db::pool($v, 'info')) &&
                            //在事务中 || 工作时区与数据库时区不同
                            ($temp['level'] || $temp['tzId'] !== $index['time'][3])
                        ) {
                            //克隆连接池, 返回原连接池新名称
                            $index['list'][$v] = of_db::pool($v, 'clone', $v);
                        } else {
                            //使用默认连接源
                            $index['list'][$v] = $v;
                        }
                        //事务开启成功(开启失败连接层会抛出异常)
                        of_db::sql(null, $v);
                    }
                }
                //当前时区与工作时区相同 || 恢复到当前时区
                $nowTz === $index['time'][2] || date_default_timezone_set($nowTz);
            }

            //初始化返回结构
            $result = array('code' => 200, 'info' => 'Successful', 'data' => array());

            //快速回调模式, data为回调方法
            if (!is_int($info) && $info) {
                try {
                    //返回false回滚工作 || 有错误提交工作
                    self::work(($temp = self::callFunc($info, array(
                        'result' => &$result, 'data' => &$result['data']
                    ) + ($data ? $data : array()))) !== false || self::$workErr[0] !== null);

                    //返回数组赋值到data中
                    if (is_array($temp)) {
                        $result['data'] = $temp;
                    //回滚工作 && 状态成功 && 添加回滚提示
                    } else if ($temp === false && $result['code'] < 400) {
                        $result['info'] .= strpos($result['info'], ': ') ?
                            ' (Rollback)' : ': (Rollback)';
                    }
                } catch (Exception $e) {
                    $result = self::work($e);
                } catch (Error $e) {
                    $result = self::work($e);
                }
            }

            //返回结果集
            return $result;
        //布尔=结束工作
        } else if (is_bool($code)) {
            //未开启工作
            if (!$sList) throw new Exception('Did not start work');
            //引用工作错误
            $iwErr = &self::$workErr;
            //引用当前工作
            $index = &$sList[0];
            //提交失败的连接池
            $ePool = false;

            //最终提交事务时检查可提交性
            if ($code && !$iwErr[0]) {
                //为多个连接池
                $temp = count($index['list']) > 1;

                //连接池有效检查
                foreach ($index['list'] as $k => &$v) {
                    //多个连接池 && 连接池已断开
                    if ($temp && !of_db::pool($k, 'ping')) {
                        throw new Exception('Can not connect to database: ' . $k);
                    }
                    //连接池事务层级错误
                    if (of_db::pool($k, 'level') !== 1) {
                        throw new Exception('Database transaction level error: ' . $k);
                    }
                }
            }

            //执行延迟回调
            while ($temp = array_shift($index['defer'])) {
                //在新工作中回调
                if (is_array($temp) && array_key_exists('onWork', $temp)) {
                    self::work($temp['onWork'], $temp, array(
                        'isOk' => $code && !$iwErr[0], 'wuid' => $index['wuid']
                    ));
                //不开工作直接回调
                } else {
                    try {
                        //接受参数{"isOk" : true=最终提交 false=最终回滚, "wuid" : 工作ID}
                        self::callFunc($temp, array('isOk' => $code && !$iwErr[0], 'wuid' => $index['wuid']));
                    } catch (Exception $e) {
                        //记录异常
                        self::event('of::error', true, $e);
                    } catch (Error $e) {
                        //记录异常
                        self::event('of::error', true, $e);
                    }
                }
            }

            //是否提交事务, true=提交, false=回滚
            if ($isOk = $code && !$iwErr[0]) {
                //检查是否有内部最终回滚的连接
                foreach ($index['list'] as $k => &$v) {
                    //数据库连接最终会回滚
                    if (!$isOk = of_db::pool($k, 'state') === true) {
                        $iwErr[0] = array(
                            'code' => 2,
                            'info' => 'Cannot commit transaction: ' . $k,
                            'file' => '/of.php',
                            'line' => __LINE__
                        );
                        break ;
                    }
                }
            }

            //提交或回滚事务
            foreach ($index['list'] as $k => &$v) {
                //事务提交失败 && 接下的事务回滚
                !of_db::sql($isOk, $k) && $isOk && $isOk = !$ePool = $k;
                //切到恢复列表
                $index['back'][$k] = &$v;
            }
            $index['list'] = array();

            //提交失败的连接池
            if ($ePool !== false) {
                throw new Exception('Failed to commit transaction: ' . $ePool);
            //提交事务 && 存在错误, 发生内部错误
            } else if ($code && $iwErr[0]) {
                self::work(500, 'An internal error occurred', array(
                    'file' => $iwErr[0]['file'],
                    'line' => $iwErr[0]['line']
                ));
            //一切正常移除栈列
            } else {
                //是否提交事务, true=提交, false=回滚
                $isOk = $code && !$iwErr[0];

                //移除栈列
                array_shift($iwErr);
                array_shift($sList);

                //执行完成回调
                while ($temp = array_shift($index['done'])) {
                    //在新工作中回调
                    if (is_array($temp) && array_key_exists('onWork', $temp)) {
                        self::work($temp['onWork'], $temp, array(
                            'isOk' => $isOk, 'wuid' => $index['wuid']
                        ));
                    //不开工作直接回调
                    } else {
                        try {
                            //接受参数{"isOk" : true=最终提交 false=最终回滚, "wuid" : 工作ID}
                            self::callFunc($temp, array('isOk' => $isOk, 'wuid' => $index['wuid']));
                        } catch (Exception $e) {
                            //记录异常
                            self::event('of::error', true, $e);
                        } catch (Error $e) {
                            //记录异常
                            self::event('of::error', true, $e);
                        }
                    }
                }

                //恢复原连接池
                foreach ($index['back'] as $k => &$v) {
                    //回滚事务事务 && 清除克隆连接池
                    $k === $v ? of_db::pool($v, 'clean', 1) : of_db::pool($v, 'rename', $k);
                }

                //恢复到工作创建时的时区
                date_default_timezone_set($index['time'][2]);
            }
        //文本=功能操作
        } else if (is_string($code)) {
            switch ($code) {
                //time=获取时间
                case 'time':
                    //返回时间
                    if (isset($sList[0])) {
                        //引用当前工作时间
                        $index = &$sList[0]['time'];
                        //参数为3 ? [时间戳, 格式化] : (默认1为格式时间, 2为时间戳)
                        return $info === 3 ? $index : $index[$info !== 1];
                    //未在工作流程中
                    } else {
                        throw new Exception('Did not start work');
                    }
                //error=操作错误
                case 'error':
                    $info === false && self::$workErr[0] = self::$lastErr = null;
                    return self::$workErr[0];
                //block=排除监听
                case 'block':
                    foreach ($info as $k => &$v) {
                        if ($v) {
                            $block[$k] = true;
                        } else {
                            unset($block[$k]);
                        }
                    }
                    return $block;
                //info=工作信息
                case 'info':
                    if (isset($sList[0])) {
                        $info < 1 && $info = 3;
                        $result = array();

                        //工作ID
                        $info & 1 && $result['wuid'] = $sList[0]['wuid'];
                        //监听数据库
                        $info & 2 && $result['list'] = array_keys($sList[0]['list']);
                        //注入回调
                        $info & 4 && $result += array(
                            'defer' => &$sList[0]['defer'],
                            'done' => &$sList[0]['done'],
                        );

                        //仅有一项 ? 返回单项数据 : 返回二维数据
                        return count($result) === 1 ? reset($result) : $result;
                    }
                    break;
                //defer=延迟回调
                case 'defer':
                //done=完成回调
                case 'done':
                    //未在工作流程中
                    if (!isset($sList[0])) throw new Exception('Did not start work');
                    //已指定回调标识 || 生成随机值
                    is_string($data) || $data = '_' . uniqid();
                    //引用回调类型
                    $index = &$sList[0][$code];

                    //读取回调
                    if ($info === true) {
                        return isset($index[$data]) ? $index[$data] : null;
                    //设置回调
                    } else {
                        //删除延迟回调
                        unset($index[$data]);
                        //添加延迟回调
                        $info && $index[$data] = &$info;
                    }
                    break;
                default :
                    throw new Exception('Invalid work command: ' . $code);
            }
        //对象=捕捉异常, 数字=抛出异常
        } else {
            //处理捕获的异常
            if (is_object($code)) {
                //是内置异常类
                if (get_class($code) === '_ofWorkException') {
                    $result = array(
                        'code' => $code->getCode(),
                        'info' => $code->getMessage(),
                        'data' => &$code->data
                    );
                //其它常规异常
                } else {
                    self::event('of::error', true, $code);
                    $result = array(
                        'code' => 500,
                        'info' => L::getText('An internal error occurred', array('key' => __METHOD__)),
                        'data' => array(
                            'file' => self::$workErr[0]['file'],
                            'line' => self::$workErr[0]['line']
                        )
                    );
                }

                //已开启工作 && 回滚当前工作
                isset($sList[0]) && self::work(false);
                return $result;
            //抛出异常
            } else {
                $code = new _ofWorkException(L::getText($info, array(
                    'key' => __METHOD__, 'trace' => isset($trace) ? $trace : 1, 'mode' => 1
                )), $code);
                $code->data = &$data;
                throw $code;
            }
        }
    }

    /**
     * 描述 : 记录最后一次错误
     * 参数 :
     *     &code : 错误编码, 接收"异常对象,数组格式,错误编码,null"格式
     *      info : 错误信息, 为false时直接存储code数组格式(不显示错误信息)
     *      file : 文件路径
     *      line : 文件行数
     * 作者 : Edgar.lee
     */
    public static function saveError($code = null, $info = null, $file = null, $line = null) {
        //直接存储
        if ($info === false) {
            $error = &$code;
        //致命错误
        } else if ($code === null) {
            //开发显示原生错误, 防止 of::halt 回调中出现致命错误
            OF_DEBUG && ini_set('display_errors', true);

            //非 trigger_error('')
            if (($temp = error_get_last()) && isset($temp['message'][0])) {
                $error = array(
                    'code' => &$temp['type'],
                    'info' => ini_get('error_prepend_string') .
                        $temp['message'] .
                        ini_get('error_append_string'),
                    'file' => &$temp['file'],
                    'line' => &$temp['line']
                );
            }
        //系统异常
        } else if (is_object($code)) {
            $error = array(
                //异常代码
                'code' => $code->getCode(),
                //异常消息
                'info' => $code->getMessage(),
                //异常文件
                'file' => $code->getFile(),
                //异常行
                'line' => $code->getLine(),
            );
        //系统错误启动(php >= 8 "@"最大设置4437) && 不是过期函数
        } else if (error_reporting() & ~4437 && $code !== 8192) {
            //代码错误 ? 补全信息 : 系统错误
            $error = is_array($code) ? $code + array(
                'code' => E_USER_NOTICE, 'info' => 'Unknown error', 'file' => '', 'line' => 0
            ) : array(
                'code' => &$code, 'info' => &$info, 'file' => &$file, 'line' => &$line
            );
        //"@"错误 || 过期函数
        } else {
            //@trigger_error('') 返回 false, php 标准错误处理会接收
            return isset($info[0]);
        }

        //发生错误 && 不是备忘录
        if (isset($error) && empty($error['memo'])) {
            //关联上一次错误
            $_SERVER['_of']['error'] = self::$lastErr;
            //记录最后一次错误
            self::$lastErr = self::$workErr[0] = array(
                'code' => &$error['code'], 'info' => &$error['info'],
                'file' => &$error['file'], 'line' => &$error['line'],
                'uuid' => uniqid()
            );

            //非直接存储
            if ($info !== false) {
                //格式化文件路径
                $error['file'] = strtr(substr($error['file'], strlen(ROOT_DIR)), '\\', '/');
                //开发模式, 打印日志
                if (OF_DEBUG) {
                    $info = htmlentities($error['info'], ENT_QUOTES, 'UTF-8');
                    echo '<pre style="color:#F00; font-weight:bold; margin: 0px;">',
                        "[{$error['code']}] : \"{$info}\" in {$error['file']} on line {$error['line']}",
                        '. Timestamp : ', time(), '</pre>';
                }
            }

            //返回错误标识
            return self::$lastErr['uuid'];
        }
    }

    /**
     * 描述 : 动态加载类
     * 参数 :
     *      name : 需要加载的类名
     * 返回 :
     *      成功类返回的值,默认1,失败返回false
     * 注明 :
     *      of::loadClass事件 : 类路径映射,指定前缀的类将按照其它前缀的类加载
     *          第二参结构 : {
     *              filter : 匹配的类前缀触发回调
     *              router : 映射前缀,字符串=指定的前缀替换该字符串
     *              asCall : 函数回调,不能与router共存
     *              params : 回调参数,用[_]键指定类名位置
     *          }
     * 作者 : Edgar.lee
     */
    public static function loadClass($name) {
        //框架类转命名空间方式
        $alias = self::$isSpace && rtrim(substr($name, 0, 3), '\_') === 'of';
        $alias && $name = strtr($name, '\\', '_');

        //事件回调修改的类名
        $class = $name;
        //读取of::loadClass事件
        $event = &self::event('of::loadClass', null);
        //修改过重新排序
        if ($event['change']) {
            $event['change'] = false;
            //至少有of_这条数据
            foreach ($event['list'] as $k => &$v) {
                if ($v['change']) {
                    $v['change'] = false;
                    $v['filterLen'] = strlen($v['event']['filter']);
                }
                $sortList[$k] = $v['filterLen'];
            }
            //按类名匹配度由大到小重新排序
            array_multisort($sortList, SORT_DESC, SORT_NUMERIC, $event['list']);
        }
        //加载回调, 不用判断一定有数据
        foreach ($event['list'] as &$v) {
            $k = $v['filterLen'];
            $v = &$v['event'];
            if (strncmp($v['filter'], $name, $k) === 0) {
                //回调按照类名匹配度由大到小顺序执行
                if (isset($v['asCall'])) {
                    self::callFunc($v, array('name' => $name, 'code' => &$code, 'file' => &$file));
                //替换按照类名匹配度由大到小执行一次
                } else if ($class === $name) {
                    $class = substr_replace($name, $v['router'], 0, $k);
                }
            }
        }

        //回调未指定路径
        if (!isset($file)) {
            //指定路径 || 转换路径
            $class[0] === '/' || $class = '/' . strtr($class, '_', '/');
            //生成绝对路径
            $file = ROOT_DIR . strtr($class, '\\', '/') . '.php';
        }
        //加载代码
        if (isset($code)) {
            $class = include "of.eval://{$file}\n?>{$code}";
        //加载文件
        } else {
            $class = is_file($file) ? include $file : false;
        }

        //为框架类设置空间别名
        if ($alias && class_exists($name, false)) {
            class_alias($name, strtr($name, '_', '\\'));
        }
        return $class;
    }

    /**
     * 描述 : 加载系统环境
     * 作者 : Edgar.lee
     */
    private static function loadSystemEnv() {
        //默认编码
        ini_set('default_charset', 'UTF-8');
        //输出框架信息
        ini_get('expose_php') && header('X-Powered-By: OF/' . OF_VERSION);
        //强制框架配置
        $of = array();

        //解析cli模式下的请求参数
        if (PHP_SAPI === 'cli') {
            //将参数转成GET POST COOKIE 方式
            foreach ($_SERVER['argv'] as &$v) {
                //"get:a=test&c=demo_index" 模式解析
                $temp = explode(':', $v, 2);

                //"xx:yy"模式的参数
                if (isset($temp[1])) {
                    //保存到全局中
                    $GLOBALS['_ARGV'][$temp[0]] = &$temp[1];
                    $temp[0] = '_' . strtoupper($temp[0]);

                    //设置备用时区
                    if ($temp[0] === '__TZ') {
                        ini_set('date.timezone', $temp[1]);
                    //设置本机IP
                    } else if ($temp[0] === '__IP') {
                        $_SERVER['SERVER_ADDR'] = $temp[1];
                    //设置默认 ROOT_URL
                    } else if ($temp[0] === '__RL') {
                        $of['rootUrl'] = $temp[1];
                    //存在 $GLOBALS 变量中
                    } else if (isset($GLOBALS[$temp[0]])) {
                        //解析到对应超全局变量中
                        parse_str($temp[1], $temp[2]);
                        $GLOBALS[$temp[0]] = $temp[2] + $GLOBALS[$temp[0]];
                        //设置原始请求
                        $temp[0] === '_GET' && $_SERVER['QUERY_STRING'][] = &$temp[1];
                    }
                }
            }
            //本机IP
            isset($_SERVER['SERVER_ADDR']) || $_SERVER['SERVER_ADDR'] = '127.0.0.1';
            //访问路径
            $_SERVER['SCRIPT_FILENAME'] = strtr(current(get_included_files()), '\\', '/');
        //COOKIE重新解析键名含%xx的格式
        } else if (isset($_SERVER['HTTP_COOKIE'])) {
            parse_str(
                str_replace(array('&', '; '), array('%26', '&'), $_SERVER['HTTP_COOKIE']),
                $_COOKIE
            );
            //防注入格式化
            ini_get('magic_quotes_gpc') && self::slashesDeep($_COOKIE);
        }

        //防注入处理的超全局变量
        $temp = array(&$_GET, &$_POST, &$_COOKIE);
        //防注入脚本
        ini_get('magic_quotes_gpc') || self::slashesDeep($temp);
        //固定顺序整合到 request 中, 防止被 php.ini 中 request_order 影响
        $_REQUEST = $_GET + $_POST + $_COOKIE;

        //加载全局配置文件
        $of += (include dirname(__FILE__) . '/config.php') + array(
            'debug' => false, 'config' => array(),
            'dataDir' => '/data', 'ofDir' => '/include/of'
        );
        //未指定根目录
        if (!isset($of['rootDir'])) {
            $scriptFilename = strtr($_SERVER['SCRIPT_FILENAME'], '\\', '/');

            //从框架入口访问
            if (substr($scriptFilename, $temp = -10 - strlen($of['ofDir'])) === $of['ofDir'] . '/index.php') {
                $of['rootDir'] = substr($scriptFilename, 0, $temp);
            //使用遍历磁盘的方式计算根目录
            } else {
                while (($scriptFilename = dirname($temp = $scriptFilename)) !== $temp) {
                    if (is_file($scriptFilename . $of['ofDir'] . '/of.php')) {
                        $of['rootDir'] = $scriptFilename;
                        break ;
                    }
                }
                //根目录未找到
                isset($of['rootDir']) || exit('Path is not under: _of.rootDir');
            }
        }

        //站点根目录,ROOT_DIR
        define('ROOT_DIR', $of['rootDir']);
        //框架根目录,OF_DIR
        define('OF_DIR', ROOT_DIR . $of['ofDir']);
        //加载站点配置文件
        is_array($of['config']) || $of['config'] = array($of['config']);
        empty($of['config'][0]) || self::$config = include ROOT_DIR . $of['config'][0];
        //合并系统配置
        self::$config['_of'] = &self::arrayReplaceRecursive($of, self::$config['_of']);

        //默认节点名称
        isset($of['nodeName']) || $of['nodeName'] = $_SERVER['SERVER_ADDR'] . php_uname();

        //默认时区 框架时区>系统时区>PRC时区
        if (
            ($index = &$of['timezone']) ||
            ($index = ini_get('date.timezone')) ||
            ($index = 'PRC')
        ) {
            date_default_timezone_set($index);
        }

        //cli模式初始化环境信息
        if (PHP_SAPI === 'cli') {
            //设置项目跟目录
            $_SERVER['DOCUMENT_ROOT'] = ROOT_DIR;
            //计算一些路径
            isset($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] = '';
            $_SERVER['QUERY_STRING'] = empty($_SERVER['QUERY_STRING']) ? '' : join('&', $_SERVER['QUERY_STRING']);
            $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_FILENAME'], strlen(ROOT_DIR));
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
            $_SERVER['PATH_TRANSLATED'] = ROOT_DIR . $_SERVER['PATH_INFO'];
            $_SERVER['QUERY_STRING'] && $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            isset($of['rootUrl']) || $of['rootUrl'] = '';
        //web模式自动计算ROOT_URL
        } else if (!isset($of['rootUrl'])) {
            $temp = $_SERVER['SCRIPT_NAME'];
            $scriptFilename = strtr($_SERVER['SCRIPT_FILENAME'], '\\', '/');
            while (true) {
                if ($temp === substr($scriptFilename, -strlen($temp))) {
                    //除虚拟目录外执行脚本所在路径的长度
                    $scriptNameLen = strlen($temp);
                    break;
                } else {
                    $temp = substr($temp, strcspn($temp, '/', 1) + 1);
                }
            }
            //非英文路径解析
            $of['rootUrl'] = str_replace('%2F', '/', rawurlencode(
                //提取虚拟目录, 如: [/虚拟目录]/访问路径
                substr($_SERVER['SCRIPT_NAME'], 0, -$scriptNameLen) .
                //提取站点根路径, 如: (ROOT_DIR[/服务根目录][/站点根路径])/访问路径
                substr(ROOT_DIR, strlen(substr($scriptFilename, 0, -$scriptNameLen)))
            ));
        }

        //站点根路径,ROOT_URL
        define('ROOT_URL', $of['rootUrl']);
        //框架根路径,OF_URL
        define('OF_URL', ROOT_URL . substr(OF_DIR, strlen(ROOT_DIR)));
        //框架可写文件夹
        define('OF_DATA', $of['dataDir']);

        //从 HTTP_REFERER 识别 __OF_DEBUG__
        if (
            !isset($_REQUEST['__OF_DEBUG__']) &&
            isset($_SERVER['HTTP_REFERER']) &&
            strpos($_SERVER['HTTP_REFERER'], '__OF_DEBUG__')
        ) {
            parse_str(strtr($_SERVER['HTTP_REFERER'], '?', '&'), $temp);
            isset($temp['__OF_DEBUG__']) && $_REQUEST['__OF_DEBUG__'] = &$temp['__OF_DEBUG__'];
        }

        //格式化debug
        if ($of['debug'] === true || $of['debug'] === null) {
            //调试或生产模式
            $_SERVER['_of']['debug'] = isset($_REQUEST['__OF_DEBUG__']) ?
                true : $of['debug'];
        } else {
            //生产模式切换, 密码校验
            $_SERVER['_of']['debug'] = isset($_REQUEST['__OF_DEBUG__']) ?
                $of['debug'] == $_REQUEST['__OF_DEBUG__'] : false;
        }
        define('OF_DEBUG', $_SERVER['_of']['debug']);

        //of_类映射
        self::event('of::loadClass', array(
            'filter' => 'of_', 'router' => substr(OF_DIR, strlen(ROOT_DIR) + 1) . '/'
        ), true);
        //动态工作SQL监听
        self::event('of_db::before', array(
            'asCall' => 'of::work', 'params' => array(array(), 0)
        ));
    }

    /**
     * 描述 : 安全加载文件
     * 参数 :
     *      path : 磁盘路径
     *      argv : 环境参数
     * 返回 :
     *      磁盘路径的返回值
     * 作者 : Edgar.lee
     */
    private static function safeLoad($path, $argv = array()) {
        extract($argv, EXTR_REFS);
        return include $path;
    }

    /*********************************************************************工具类
     * 描述 : 深度加/删反斜杠
     * 参数 :
     *     &data : 指定替换的数组
     *      func : addslashes(默认)=添加反斜杠, stripslashes=删除反斜杠
     * 作者 : Edgar.lee
     */
    public static function &slashesDeep(&$data, $func = 'addslashes') {
        //待处理列表
        $waitList = array(&$data);

        do {
            $wk = key($waitList);
            $wv = &$waitList[$wk];
            unset($waitList[$wk]);

            if (is_array($wv)) {
                //结果列表
                $result = array();
                foreach ($wv as $k => &$v) {
                    $result[$func($k)] = &$v;
                    $waitList[] = &$v;
                }
                $wv = $result;
            } else if (is_string($wv)) {
                $wv = $func($wv);
            }
        } while (!empty($waitList));

        return $data;
    }

    /**
     * 描述 : 深度替换属性
     * 参数 :
     *      baseData : 被替换的数据
     *      replace  : 替换的数据
     * 作者 : Edgar.lee
     */
    public static function &arrayReplaceRecursive(&$baseData, &$replace) {
        if (is_array($replace)) {
            //待处理列表
            $waitList = array(&$replace);
            //被替换列表
            $baseList = array(&$baseData);

            do {
                $wk = key($waitList);
                $wv = &$waitList[$wk];
                $bv = &$baseList[$wk];
                unset($waitList[$wk], $baseList[$wk]);

                if (is_array($bv) && is_array($wv)) {
                    foreach ($wv as $k => &$v) {
                        $waitList[] = &$v;
                        $baseList[] = &$bv[$k];
                    }
                } else {
                    $bv = $wv;
                }
            } while (!empty($waitList));
        }

        return $baseData;
    }

    /**
     * 描述 : 回调函数
     * 参数 :
     *      call   :
     *          1.符合 is_callable 格式的, 如 "class::action" 或 [object, action]
     *          2.每次调用创建对象的格式, 如 [class, action], 会将会创建class的对象
     *          3.自定义调用的信息 {
     *              "asCall" : 符合上面两种规范
     *              "params" : call_user_func_array 格式的参数,用[_]键指定类名位置
     *          }
     *          4.含"."的字符串, 会从of::config中读取对应配置后按上述格式解析
     *      params : 传入到 [_] 位置的参数
     *      return : 指定无法回调的返回值, 不指定调用失败时报错
     * 返回 :
     *      返回 调用函数 返回的数据
     * 作者 : Edgar.lee
     */
    public static function &callFunc($call, $params = null, $return = null) {
        //带"."的字符串从配置文件中读取回调
        is_string($call) && strpos($call, '.') && $call = self::config($call);

        //标准调用格式转换
        if (!is_array($call) || !isset($call['asCall'])) {
            $call = array('asCall' => $call);
        }

        //{"asCall": [class, string]} => {"asCall": [new class, string]}
        if (is_array($call['asCall']) && is_string($call['asCall'][0])) {
            $call['asCall'][0] = new $call['asCall'][0];
        }

        //没有默认返回值 || 回调方法有效
        if (($argc = func_num_args()) < 3 || is_callable($call['asCall'])) {
            //初始化回调参数
            ($index = &$call['params']) || $index = array();
            //存在触发参数 ? 合并到回调参数 : 初始回调参数
            $argc > 1 ? $index['_'] = &$params : ($index || $args = $index);
            //兼容 php >= 8 添加的命名参数
            foreach ($index as &$v) $args[] = &$v;
            //调用回调方法
            $return = call_user_func_array($call['asCall'], $args);
        }

        return $return;
    }

    /**
     * 描述 : 格式化路径
     * 参数 :
     *      path   : 指定格式化的路径,'_'开头的去掉'_',其它字符以加上指定前缀,数组则以回调的返回值为值
     *      prefix : path不以'_'开头字符串的前缀
     * 返回 :
     *      格式化的路径
     * 作者 : Edgar.lee
     */
    public static function formatPath($path, $prefix) {
        if (is_array($path) || !preg_match('@^(?:/|_|$)@', $path, $temp)) {
            return self::callFunc($path, array('prefix' => $prefix));
        } elseif ($temp[0] === '_') {
            return substr($path, 1);
        } else {
            return $prefix . $path;
        }
    }

    /**
     * 描述 : 通过字符串获取数组深度数据
     * 参数 :
     *      key     : null(默认)=返回 data, 字符串=以"."作为分隔符表示数组深度, 数组=以数组的方式代替传参[key, data, default, extends]
     *     &data    : 被查找的数组
     *      default : null, 没查找到的代替值
     *      extends : 扩展参数, 使用"|"连接多个功能, 0(默认)=不转义, 1=以"`"作为key的转义字符, 2=默认值赋值到原数据
     * 返回 :
     *      返回指定值 或 代替值
     * 作者 : Edgar.lee
     */
    public static function &getArrData($arg_0, &$arg_1 = null, $arg_2 = null, $arg_3 = 0) {
        //数组转换成变量 $arg_xxx
        is_array($arg_0) && extract($arg_0, EXTR_PREFIX_ALL | EXTR_REFS, 'arg');

        if ($arg_0 === null) {
            return $arg_1;
        } else {
            $index = &$arg_1;
            //转义 key 值
            if (is_string($arg_0)) {
                $list = $arg_3 & 1 ?
                    explode("\0", strtr($arg_0, array('``' => '`', '`.' => '.', '.' => "\0"))) : explode('.', $arg_0);
            //可遍历的定位
            } else {
                $list = $arg_0;
            }

            foreach ($list as &$v) {
                //指定位子存在
                if (isset($index[$v]) || $arg_3 & 2) {
                    $index = &$index[$v];
                //指定位子不存在
                } else {
                    unset($index);
                    break;
                }
            }

            isset($index) || $index = $arg_2;
            return $index;
        }
    }

    /**
     * 描述 : 校验php语法
     * 参数 :
     *      code : 检查的代码, 符合 eval 的规范
     *      exec : 是否执行 false=不执行, true=执行, 字符串=执行且指定文件路径
     *      tips : 是否显示行号源码 false=不显示, true=显示
     *     &data : 执行时返回的数据
     * 返回 :
     *      语法通过返回 null
     *      语法失败返回 {
     *          "info" : 错误信息
     *          "line" : 错误行数
     *          "tips" : 按 tips 参数显示的代码
     *      }
     * 作者 : Edgar.lee
     */
    public static function &syntax($code, $exec = false, $tips = false, &$data = null) {
        //语法错误时异常
        if (self::$isParse) {
            try {
                //是否执行, 屏蔽 __halt_compiler 语法错误
                $temp = $exec === false ? 'if (0) {' . str_replace('__halt_compiler', 'c', $code) . "//<?php\n}" : $code;
                $data = is_bool($exec) ? eval($temp) : include "of.eval://{$exec}\n{$code}";
            //兼容php7
            } catch (Error $e) {
                //是语法错误 ? 返回错误 : 错误日志
                $e instanceof ParseError ?
                    $result = array('info' => $e->getMessage(), 'line' => $e->getLine()) :
                    self::event('of::error', true, $e);
                //执行结果false
                $data = false;
            }
        //语法错误时报错
        } else {
            //是否执行, 屏蔽 __halt_compiler 语法错误
            $temp = $exec === true ? $code : 'if (0) {' . str_replace('__halt_compiler', 'c', $code) . "//<?php\n}";
            //未指定路径 || 校验代码
            if (($data = @eval($temp)) === false) {
                $result = error_get_last();
                $result = array('info' => &$result['message'], 'line' => &$result['line']);
                //清除错误 (php < 7 时 eval 出错时返回 false, 同时没有 error_clear_last 方法)
                @trigger_error('');
            //指定文件路径
            } else if (is_string($exec)) {
                $data = include "of.eval://{$exec}\n{$code}";
            }
        }

        //发生错误
        if (isset($result)) {
            //格式化提示代码
            if ($tips) {
                $tips = explode("\n", $code);
                //最大值的长度
                $line = strlen(count($tips));
                foreach ($tips as $k => &$v) {
                    $v = str_pad(++$k, $line, '0', STR_PAD_LEFT) . '| ' . $v;
                }
                $tips = join("\n", $tips);
            } else {
                $tips = &$code;
            }
            $result['tips'] = &$tips;
        }

        return $result;
    }

    //流配置资源**********************************************************of.eval协议
    public $context;
    //流文件信息
    private $stat;
    //流指针位置
    private $seek = 0;
    //流改写代码
    private $code;

    /**
     * 描述 : 打开文件流
     * 注明 :
     *      路径结构($path) : of.eval://路径\n代码
     * 作者 : Edgar.lee
     */
    public function stream_open($path, $mode, $options, &$file) {
        //拆分代码
        $path = explode("\n", $path, 2);
        //代码路径
        $file = substr($path[0], 10);
        //执行代码
        $this->code = '<?php ' . $path[1];
        //代码长度
        $this->stat['size'] = strlen($this->code);
        //返回结果
        return true;
    }

    /**
     * 描述 : 读取流信息
     * 作者 : Edgar.lee
     */
    public function stream_stat() {
        return $this->stat;
    }

    /**
     * 描述 : 读取流数据
     * 作者 : Edgar.lee
     */
    public function stream_read($count) {
        $code = substr($this->code, $this->seek, $count);
        $this->seek += strlen($code);
        return $code;
    }

    /**
     * 描述 : 判断流结束
     * 作者 : Edgar.lee
     */
    public function stream_eof() {
        return $this->seek >= $this->stat['size'];
    }

    /**
     * 描述 : 设置流参数
     * 作者 : Edgar.lee
     */
    public function stream_set_option($option, $arg1, $arg2) {
        return true;
    }
}

of::init();