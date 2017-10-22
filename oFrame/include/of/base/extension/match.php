<?php
/*
* 描述 : 针对当前页面加载及处理相应扩展
* 作者 : Edgar.Lee
*/
//添加调度事件
of::event('of::dispatch', 'of_base_extension_match::init');
//删除输出控制监听
of::event('of::halt', false, array('asCall' => 'L::buffer', 'params' => array(true, true)));
//添加结束事件
of::event('of::halt', array('asCall' => 'of_base_extension_match::shutdown', 'params' => array(true)));
//添加类加载拦截
of::event('of::loadClass', array(
    'classPre' => of_base_extension_manager::getConstant('baseClassName'),
    'asCall' => 'of_base_extension_match::ofLoadExtensionClass'
), true);
//添加试图事件
of::event('of_view::display', array('asCall' => 'of_base_extension_match::fireHook', 'params' => array('::view')));
//添加sql前事件
of::event('of_db::before', array('asCall' => 'of_base_extension_match::fireHook', 'params' => array('::sqlBefore')));
//添加sql后事件
of::event('of_db::after', array('asCall' => 'of_base_extension_match::fireHook', 'params' => array('::sqlAfter')));
//添加触发连接
of::link('fireHook', '$type, $params = null', 'of_base_extension_match::fireHook($type, $params, true);');

class of_base_extension_match {
    /**
     * 注明 : 
     *      注册的钩子($hookList)结构,"钩子类型"在"扩展名"后面是为了保证执行顺序 : {
     *          '扩展名' : {
     *              '钩子类型' : [
     *                  {
     *                      'asCall' : 回调,
     *                      'params' : 自定义参数
     *                  }, ...
     *              ]
     *          }
     *      }
     *      加载的扩展类($extensionClassObj)结构 : {
     *          完整类名 : false=加载成功但没有初始化对象,object=初始化的对象
     *      }
     */
    static private $hookList = array();
    static private $extensionClassObj = array();

    /**
     * 描述 : 初始化扩展
     * 作者 : Edgar.lee
     */
    public static function init($params) {
        //移除调度事件
        of::event('of::dispatch', false, 'of_base_extension_match::init');

        //全部扩展信息
        $extensionsInfo = of_base_extension_manager::getExtensionInfo();
        //独享页面的类名
        $exclusive = of::config('_of.extension.exclusive', 'of_ex');
        //生成匹配标识符
        $matchUri = $params['class'] .'::'. $params['action'];
        //限制扩展
        $restricExtension = isset($_GET['e']) && $params['class'] === $exclusive ?
            $_GET['e'] : null;

        //基类共享数据初始化
        of_base_extension_baseClass::_initShareData(array(
            //编译扩展类
            'extensionClassObj' => &self::$extensionClassObj,
            //注册的钩子列表
            'hookList'          => &self::$hookList,
            //匹配的页面地址
            'matchUri'          => &$matchUri,
            //设置独享的类名
            'exclusive'         => &$exclusive
        ));

        foreach ($extensionsInfo as $eKey => &$infoV) {
            //扩展正常运行 && (不限制 || 指定扩展执行)
            if ($infoV['state'] === '1' && ($restricExtension === null || $restricExtension === $eKey)) {
                //按照扩展顺序注册钩子顺序
                self::$hookList[$eKey] = array();

                //开启扩展缓存
                L::buffer(true, __CLASS__);
                //一个扩展一个try
                try {
                    //遍历匹配路径
                    foreach ($infoV['config']['matches'] as $fileDir => &$matches) {
                        foreach ($matches as &$matchUrl) {
                            $temp = false;
                            //正则匹配
                            if ($matchUrl[0] === '@') {
                                preg_match($matchUrl, $matchUri) && $temp = true;
                            //结构匹配
                            } else if ($matchUri === $matchUrl) {
                                $temp = true;
                            }

                            //匹配成功
                            if ($temp) {
                                self::callExtension($eKey, $fileDir, array(&$params));
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    of_base_error_writeLog::phpLog($e);
                }
            }
        }
        //开启根级缓存
        L::buffer(true, '');
    }

    /**
     * 描述 : 加载扩展类
     * 参数 :
     *      eKey      : 扩展文件名
     *      className : 类名
     *      isNew     : 是否返回对象,true(默认)=返回对象,false=返回完整类名
     * 返回 :
     *      成功返回对象或完整类名,失败返回false
     * 作者 : Edgar.lee
     */
    public static function loadClass($eKey, $className, $isNew = true) {
        $classObj = &self::$extensionClassObj;
        //合并类名
        $mergeClassName = of_base_extension_manager::getConstant('baseClassName') . $eKey . '_' . $className;

        if (!isset($classObj[$mergeClassName])) {
            $parseFile = of_base_extension_manager::getConstant('extensionDir') .'/'. $eKey .'/'. strtr($className, '_', '/') . '.php';
            //生成扩展类数据
            if (is_file($parseFile)) {
                //构造参数
                $constructParams = addslashes(serialize(array(
                    //代替 __FILE__ 常量
                    'file' => $parseFile,
                    //当前扩展所在的文件名称
                    'eKey' => $eKey
                )));

                //扩展已加密
                if (strncmp($temp = file_get_contents($parseFile), '<?php', 5) !== 0) {
                    $temp = of_base_com_str::rc4('扩展加密密钥', $temp);
                }
                //扩展类体
                $temp = str_replace('L::getText(', '$this->_getText(', substr($temp, 5));
                $temp = "CLASS {$mergeClassName} EXTENDS of_base_extension_baseClass { function __construct() {parent::__construct('{$constructParams}');} {$temp} }";

                //解析错误
                if ($temp = &of::syntax($temp, true)) {
                    throw new Exception("{$temp['message']} in " . substr($parseFile, strlen(ROOT_DIR)) . " on line {$temp['line']}");
                //标记类创建成功,但没有生成对象(false)
                } else {
                    $classObj[$mergeClassName] = false;
                }
            //文件不存在
            } else {
                throw new Exception('No such file : ' . substr($parseFile, strlen(ROOT_DIR)));
            }
        }

        if ($isNew) {
            $classObj[$mergeClassName] === false && $classObj[$mergeClassName] = new $mergeClassName;
            return $classObj[$mergeClassName];
        } else {
            return $mergeClassName;
        }
    }

    /**
     * 描述 : 调用扩展
     * 参数 :
     *      eKey      : 扩展文件名
     *      callClass : 调用类结构,数组=[相对类名或对象, 函数名],字符串="相对类名::函数名"
     *      paramArr  : 传递的参数
     * 作者 : Edgar.lee
     */
    public static function callExtension($eKey, $callClass, $paramArr = array()) {
        //解析结构
        is_string($callClass) && $callClass = explode('::', $callClass, 2);
        //创建对象
        is_string($callClass[0]) && $callClass[0] = self::loadClass($eKey, $callClass[0]);

        if (is_callable($callClass)) {
            call_user_func_array($callClass, $paramArr);
        } else {
            throw new Exception('Call to undefined method /' . strtr(get_class($callClass[0]), '_', '/') . "::{$callClass[1]}()");
        }
    }

    /**
     * 描述 : 触发钩子(仅能触发公有钩子)
     * 参数 :
     *      type   : 钩子类型
     *      params : 传递参数,由callback第一个参数接收,null=默认
     *      ob     : 是否使用缓存,false=非,默认true=是
     * 作者 : Edgar.lee
     */
    public static function fireHook($type, $params = null, $ob = true) {
        if ($type[0] === '_') {
            //错误:不能触发私有钩子
            trigger_error("Not trigger private hook : {$type}");
        //触发钩子
        } else {
            //每个 扩展名 的 钩子列表
            foreach (self::$hookList as $eKey => &$extensionHooks) {
                //钩子存在
                if (isset($extensionHooks[$type])) {
                    //防止内存溢出
                    isset($memory) || $memory = L::buffer(null, false);
                    //开启缓存
                    $ob && L::buffer(true, __CLASS__);
                    try {
                        //当 前扩展 的 单个钩子
                        foreach ($extensionHooks[$type] as &$v) {
                            self::callExtension($eKey, $v['asCall'], array(&$params, &$v['params']));
                        }
                    } catch (Exception $e) {
                        of_base_error_writeLog::phpLog($e);
                    }
                }
            }
            //恢复缓存级别
            isset($memory) && $ob && L::buffer($memory['mode'], $memory['pool']);
        }
    }

    /**
     * 描述 : 关闭时触发
     * 作者 : Edgar.lee
     */
    public static function shutdown($state) {
        L::buffer(true);
        if ($state) {
            L::buffer(L::buffer(null, __CLASS__), '');
            //添加二级事件
            of::event('of::halt', array('asCall' => 'of_base_extension_match::shutdown', 'params' => array(false)));
        //保证所有输出都结束
        } else {
            $content = &responseStrParseFunction('str');
            //添加全部输出
            $content = L::buffer(null, '');
            //触发::halt钩子
            self::fireHook('::halt', array('parse' => 'responseStrParseFunction'), true);
            echo is_string($content) ? $content : $content->doc('str');
            echo L::buffer(null, __CLASS__);
            ob_flush();
        }
    }

    /**
     * 描述 : of::loadClass扩展类加载拦截
     * 作者 : Edgar.lee
     */
    public static function ofLoadExtensionClass($params) {
        $params = explode('_', substr($params['className'], strlen(of_base_extension_manager::getConstant('baseClassName'))), 2);
        self::loadClass($params[0], $params[1], false);
    }
}

/**
 * 描述 : 扩展::halt钩子触发时使用,主要对输出数据的格式化
 * 参数 :
 *      type : str=将输出数据格式化成字符串并返回
 *             obj=将输出数据格式化成对象(hParse)并返回
 * 返回 :
 *      引用返回不同格式的数据
 * 作者 : Edgar.lee
 */
function &responseStrParseFunction($type) {
    static $data = '';

    switch ($type) {
        case 'str':
            if (is_object($data)) {
                $data = $data->doc('str');
            }
            break;
        case 'obj':
            if (is_string($data)) {
                $data = new of_base_com_hParse($data);
            }
            break;
    }
    return $data;
}