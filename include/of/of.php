<?php
//版本号
define('OF_VERSION', 200234);

/**
 * 描述 : 控制层核心
 * 作者 : Edgar.lee
 */
class of {
    //站点配置文件
    private static $config = null;
    //注册的 L 类方法
    private static $links = array();
    //是否支持命名空间
    private static $isSpace = false;

    /**
     * 描述 : 初始化框架
     * 作者 : Edgar.lee
     */
    public static function init() {
        //支持命名空间
        self::$isSpace = version_compare(PHP_VERSION, '5.3.0', '>=');
        //过期函数不会报错
        error_reporting(error_reporting() & ~8192);
        //注册spl
        spl_autoload_register('of::loadClass');
        //加载系统配置文件
        self::loadSystemEnv();
        //注册::halt事件
        register_shutdown_function('of::event', 'of::halt', true);

        //预先加载类
        if (isset(self::$config['_of']['preloaded'])) {
            foreach (self::$config['_of']['preloaded'] as &$v) {
                self::loadClass($v);
            }
        }

        //生成 L 类
        $temp = 'class L {' . join(self::$links) . "\n}";
        if ($temp = self::syntax($temp, true, true)) {
            throw new Exception("{$temp['message']} on line {$temp['line']}\n----\n{$temp['tips']}");
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
     *      default : 默认值(null)
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
            $cache = $claim = null;
            //加载最新配置文件
            $of = self::safeLoad(OF_DIR . '/config.php');
            $of['config'] = isset($of['config']) ?
                (array)$of['config'] : array();
            empty($of['config'][0]) || $config = self::safeLoad(
                ROOT_DIR . $of['config'][0],
                array('of' => &$of)
            );
            $config['_of'] = &self::arrayReplaceRecursive($of, $config['_of']);
        //使用缓存配置
        } else {
            $cache = &$memory['cache'];
            $claim = &$memory['claim'];
            //配置文件引用
            $config = &self::$config;
        }

        //有缓存
        if (isset($cache[$name][$action])) {
            $default = &$cache[$name][$action];
        } else {
            //初始化动态配置
            if ($claim === null) {
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
                    $vaule = &of::getArrData($k, $config, null, 2);
                    $vaule = include ROOT_DIR . $v;
                    unset($claim[$k]);
                }
            }

            //引用数据
            $vaule = &$cache[$name][$action];
            //数组定位
            $vaule = of::getArrData(array(&$name, &$config, &$default, 2));

            //格式成 磁盘 或 网络 路径
            if ($action & 1 || $action & 2) {
                if ($vaule !== null) {
                    $vaule = self::formatPath(
                        $vaule, $action & 1 ? ROOT_DIR : ROOT_URL
                    );
                }
            }

            $default = &$vaule;
        }

        return $default;
    }

    /**
     * 描述 : 为类提供回调事件
     * 参数 :
     *      key    : 事件类型
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
     *                  "event"  : 回调事件
     *                  "change" : 新加时会为true
     *              }]
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function &event($key, $event, $params = null) {
        static $eventList = null;

        //初始化事件列表
        isset($eventList[$key]) || $eventList[$key] = array(
            'change' => false,
            'list'   => array()
        );
        //引用当前列表
        $nList = &$eventList[$key]['list'];

        //触发事件
        if ($event === true) {
            //返回结果集
            $result = array();
            //重置指针
            reset($nList);

            while (($k = key($nList)) !== null) {
                next($nList);
                $v = &$nList[$k];
                //是回调 && 回调
                $v['isCall'] && $result[$k] = &self::callFunc($v['event'], $params);
            }
        //管理事件
        } else if ($event === null) {
            $result = &$eventList[$key];
        //增删改事件
        } else {
            //引用事件
            $event === false ? $index = &$params : $index = &$event;
            //创建临时副本,防止打乱触发内循环
            $temp = $nList;
            //删除事件
            foreach ($temp as $k => &$v) {
                if ($v['event'] == $index) {
                    $eventList[$key]['change'] = true;
                    unset($nList[$k]);
                    break;
                }
            }

            //添加事件
            if ($event !== false) {
                $eventList[$key]['change'] = true;
                $nList[] = array(
                    'isCall' => !$params,
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
     * 描述 : 加载系统环境
     * 作者 : Edgar.lee
     */
    private static function loadSystemEnv() {
        //默认编码
        ini_set('default_charset', 'UTF-8');
        //of磁盘路径
        define('OF_DIR', strtr(dirname(__FILE__), '\\', '/'));

        //加载全局配置文件
        $of = (include OF_DIR . '/config.php') + array('debug' => false, 'config' => array());
        //站点根目录,ROOT_DIR
        define('ROOT_DIR', $of['rootDir']);

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
            //设置项目跟目录
            $_SERVER['DOCUMENT_ROOT'] = ROOT_DIR;
            //计算一些路径
            $temp = get_included_files();
            $_SERVER['SCRIPT_FILENAME'] = strtr($temp[0], '\\', '/');
            isset($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] = '';
            $_SERVER['QUERY_STRING'] = empty($_SERVER['QUERY_STRING']) ? '' : join('&', $_SERVER['QUERY_STRING']);
            $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_FILENAME'], strlen(ROOT_DIR));
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
            $_SERVER['PATH_TRANSLATED'] = ROOT_DIR . $_SERVER['PATH_INFO'];
            $_SERVER['QUERY_STRING'] && $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            //本机IP
            isset($_SERVER['SERVER_ADDR']) || $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        }

        //防注入处理的超全局变量
        $temp = array(&$_GET, &$_POST, &$_COOKIE);
        //防注入脚本
        get_magic_quotes_gpc() || self::slashesDeep($temp);
        //固定顺序整合到 request 中, 防止被 php.ini 中 request_order 影响
        $_REQUEST = $_GET + $_POST + $_COOKIE;

        //加载站点配置文件
        $of['config'] = (array)$of['config'];
        empty($of['config'][0]) || self::$config = include ROOT_DIR . $of['config'][0];
        //引用配置
        $config = &self::$config;
        //合并系统配置
        $config['_of'] = &self::arrayReplaceRecursive($of, $config['_of']);

        //默认时区 框架时区>系统时区>PRC时区
        if (
            ($index = &$of['timezone']) ||
            ($index = ini_get('date.timezone')) ||
            ($index = 'PRC')
        ) {
            date_default_timezone_set($index);
        }
        $index = date('P', $_SERVER['REQUEST_TIME']);

        //自动计算ROOT_URL
        if (!isset($config['_of']['rootUrl'])) {
            //cli模式
            if (PHP_SAPI === 'cli') {
                $config['_of']['rootUrl'] = '';
            //web模式
            } else {
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
                $config['_of']['rootUrl'] = str_replace('%2F', '/', rawurlencode(
                    substr($_SERVER['SCRIPT_NAME'], 0, -$scriptNameLen) .
                    substr(ROOT_DIR, strlen(substr($scriptFilename, 0, -$scriptNameLen)))
                ));
            }
        }

        //站点根路径,ROOT_URL
        define('ROOT_URL', $config['_of']['rootUrl']);
        //框架根路径,OF_URL
        define('OF_URL', ROOT_URL . substr(OF_DIR, strlen(ROOT_DIR)));
        //框架可写文件夹
        define('OF_DATA', $config['_of']['dataDir']);

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
        if ($config['_of']['debug'] === true || $config['_of']['debug'] === null) {
            //调试或生产模式
            define('OF_DEBUG', isset($_REQUEST['__OF_DEBUG__']) ?
                true : $config['_of']['debug']
            );
        } else {
            //生产模式切换, 密码校验
            define('OF_DEBUG', isset($_REQUEST['__OF_DEBUG__']) ?
                $config['_of']['debug'] == $_REQUEST['__OF_DEBUG__'] : false
            );
        }

        //输出框架信息
        $temp = OF_DEBUG === false ? '' : ' ' . OF_VERSION;
        ini_get('expose_php') && header('X-Powered-By: OF' . $temp);

        //of_类映射
        self::event('of::loadClass', array(
            'classPre' => 'of_', 'mapping' => substr(OF_DIR, strlen(ROOT_DIR) + 1) . '/'
        ), true);
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

    /**
     * 描述 : 动态加载类
     * 参数 :
     *      className : 需要加载的类名
     * 返回 :
     *      成功类返回的值,默认1,失败返回false
     * 注明 :
     *      of::loadClass事件 : 类路径映射,指定前缀的类将按照其它前缀的类加载
     *          第二参结构 : {
     *              classPre : 类前缀
     *              mapping  : 映射前缀,字符串=指定的前缀替换该字符串
     *              asCall   : 函数回调,不能与mapping共存
     *              params   : 回调参数,用[_]键指定类名位置
     *          }
     * 作者 : Edgar.lee
     */
    private static function loadClass($className) {
        //读取of::loadClass事件
        $event = &self::event('of::loadClass', null);
        //修改过重新排序
        if ($event['change']) {
            $event['change'] = false;
            //至少有of_这条数据
            foreach ($event['list'] as $k => &$v) {
                $sortList[$k] = &$v['event']['classPre'];
                if ($v['change']) {
                    $v['change'] = false;
                    $v['classPreLen'] = strlen($v['event']['classPre']);
                }
            }
            //重新排序
            array_multisort($sortList, SORT_DESC, SORT_STRING, $event['list']);
        }

        //框架类转命名空间方式
        $isAlias = self::$isSpace && rtrim(substr($className, 0, 3), '\_') === 'of';
        $isAlias && $isAlias = $className = strtr($className, '\\', '_');

        //加载回调, 不用判断一定有数据
        foreach ($event['list'] as &$v) {
            $k = $v['classPreLen'];
            $v = &$v['event'];
            if (strncmp($v['classPre'], $className, $k) === 0) {
                if (isset($v['asCall'])) {
                    $v['params']['_']['className'] = $className;
                    $temp = call_user_func_array($v['asCall'], $v['params']);
                    if ($temp !== false) return $temp;
                } else {
                    $className = substr_replace($className, $v['mapping'], 0, $k);
                    break;
                }
            }
        }

        if ($className) {
            //指定路径 || 转换路径
            $className[0] === '/' || $className = '/' . str_replace(array('_', '\\'), '/', $className);
            //生成绝对路径
            $className = ROOT_DIR . $className . '.php';
            //加载文件
            $className = is_file($className) ? include $className : false;
            //为框架类设置空间别名
            if ($isAlias && class_exists($isAlias, false)) {
                class_alias($isAlias, strtr($isAlias, '_', '\\'));
            }
            return $className;
        }
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
     *      params : 传入到 [_] 位置的参数
     * 返回 :
     *      返回 调用函数 返回的数据
     * 作者 : Edgar.lee
     */
    public static function &callFunc($call, $params = null) {
        //标准调用格式转换
        if (!is_array($call) || !isset($call['asCall'])) {
            $call = array('asCall' => $call);
        }

        if (is_array($call['asCall']) && is_string($call['asCall'][0])) {
            $call['asCall'][0] = new $call['asCall'][0];
        }
        $call['params']['_'] = &$params;
        $call = call_user_func_array($call['asCall'], $call['params']);
        return $call;
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
     *     &code : 检查的代码, 符合 eval 的规范
     *      exec : 是否执行 false=不执行, true=执行
     *      tips : 是否显示行号源码 false=不显示, true=显示, 字符串=显示指定的代码
     * 返回 :
     *      语法通过返回 null
     *      语法失败返回 {
     *          "message" : 错误信息
     *          "line"    : 错误行数
     *          "tips"    : 按 tips 参数显示的代码
     *      }
     * 作者 : Edgar.lee
     */
    public static function &syntax(&$code, $exec = false, $tips = false) {
        try {
            $temp = ini_set('error_append_string', "\n----\n" . $code);

            //是否执行, 屏蔽 __halt_compiler 语法错误
            $exec ? $exec = &$code : $exec = 'if (0) {' . str_replace('__halt_compiler', 'c', $code) . "//<?php\n}";
            if (@eval($exec) === false) {
                $result = error_get_last();
                unset($result['type'], $result['file']);
                //清除错误
                @trigger_error('');
            }

            ini_set('error_append_string', $temp);
        //兼容php7
        } catch (Error $e) {
            $result = array(
                //异常消息
                'message' => $e->getMessage(),
                //异常行
                'line'    => $e->getLine()
            );
        }

        //发生错误
        if (isset($result)) {
            //格式化提示代码
            if ($tips) {
                $tips = explode("\n", $tips === true ? $code : $tips);
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
}

of::init();