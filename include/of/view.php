<?php
/**
 * 描述 : 视图层核心
 * 作者 : Edgar.lee
 */
class of_view extends stdClass {
    //实例化对象
    private static $instanceObj = null;

    /**
     * 描述 : 初始化方法,仅可通过self::inst()实例化
     * 作者 : Edgar.lee
     */
    final public function __construct() {
        if (self::$instanceObj !== true) {
            //这个类仅能通过 self::inst() 被实例化
            trigger_error('The class can only be instantiate by self::inst()');
            exit;
        }
    }

    /**
     * 描述 : 获取实例化对象
     * 返回 :
     *      返回实例化对象
     * 作者 : Edgar.lee
     */
    public static function inst() {
        if (self::$instanceObj === null) {
            self::$instanceObj = true;
            self::$instanceObj = new self;
        }
        return self::$instanceObj;
    }

    /**
     * 描述 : 读取视图路径
     * 参数 :
     *      isUrl : 默认false=读取磁盘目录,true=网络路径,null=相对根目录
     * 返回 :
     *      读取时返回完整路径,设置时返回true
     * 作者 : Edgar.lee
     */
    public static function path($isUrl = false) {
        $index = &$_COOKIE['of_view']['viewPath'];
        //初始化模板
        isset($index) || $index = of::config('_of.view.tplPath');

        return $isUrl === null ? $index : of::formatPath($index, $isUrl ? ROOT_URL : ROOT_DIR);
    }

    /**
     * 描述 : 批量设置属性
     * 参数 :
     *      prop : 批量设置的属性 {
     *          属性键 : 属性值
     *      }
     * 作者 : Edgar.lee
     */
    public static function prop($prop) {
        $obj = self::inst();

        foreach ($prop as $k => &$v) {
            $obj->$k = &$v;
        }
    }

    /**
     * 描述 : 加载模板页面
     * 参数 :
     *      tpl : 模板名,默认调度方法名.视图扩展名
     *          '/'开头=相对当前视图路径
     *          '_'开头=完整的磁盘目录
     *          其它   =相对视图根目录的调度类结构相同
     * 作者 : Edgar.lee
     */
    public static function display($tpl = null) {
        //模板扩展名
        $tplExt = of::config('_of.view.tplExt');

        //常规模板
        if ($tpl === null || $tpl[0] !== '_' && $tpl[0] !== '/') {
            $temp = of::dispatch();
            $tpl === null && $tpl = $temp['action'] . $tplExt;
            $tpl = '/tpl/' . strtr($temp['class'], '_', '/') . '/' . $tpl;
        }

        if ($tpl[0] === '_') {
            $tpl = substr($tpl, 1);
        } else {
            $tpl = self::path() . $tpl;
        }

        //触发 of_view::display 事件
        of::event('of_view::display', true, array('tplDir' => &$tpl, 'viewObj' => $temp = self::inst()));
        $temp->objDisplay($tpl);
    }

    /**
     * 描述 : 打印通用HTML头
     * 参数 :
     *      params : [array]一个打印的相关信息,如果=false则打印HTML尾信息 {
     *          "title"  : 网页的标题
     *          "js"     : 在网页</body>前加载js路径, 以/view/js为根目录
     *              string = 相当 [string]
     *              array = {
     *                  指定防重判断键, 不指定用值做判断 : js路径,
     *                  ...
     *              },
     *          "css"    : 在网页"head"后加载css路径, 以/view/css为根目录, 同"js"结构
     *          "head"   : 在网页<head>中写入文本, "_/"开头通过include加载, 同"js"结构
     *          "body"   : 在网页<body>中写入属性, 同"js"结构
     *          "before" : 在网页<body>后写入文本, 同"js"结构
     *          "after"  : 在网页</body>前写入文本, 同"js"结构
     *      }
     *      data : 当 params 为数组键时(title, js, ...), 单独添加对应类型数据结构
     * 返回 :
     *      echo 头部或尾部信息
     * 作者 : Edgar.lee
     */
    public static function head($params = array(), $data = null) {
        //保留传入的参数
        static $_ = array('body' => array());

        //输出头信息
        if (empty($_['init']) && is_array($params)) {
            $_['init'] = self::inst();
            //注册结束尾输出
            of::event('of::halt', array(
                'asCall' => 'of_view::head',
                'params' => array(false)
            ));
            //合并html注入数据
            foreach ($params as $k => &$v) self::mergeHtmlData($_[$k], $k, $v);

            echo '<!DOCTYPE html>',
                '<html>',
                '<head>',
                '<title>', isset($_['title']) ? $_['title'] : '', of::config('_of.view.title'), '</title>',
                '<meta http-equiv="X-UA-Compatible" content="IE=edge" />';
                //of.php已发送头,同时IE6 p3p隐私共享会因utf-8导致js cookie不可写
                //'<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            //开始注入html[head]
            $_['init']->objInclude($_['head']);
            //输出css引用样式
            self::eachPrintJsOrCss($_['css'], 'css');
            //输出body属性
            echo '</head><body ', join(' ', $_['body']), '>';

            //开始注入html[before]
            self::eachPrintJsOrCss($_['before']);
        //输出尾信息
        } else if ($params === false && isset($_['init'])) {
            //开始注入html[head]
            $_['init']->objInclude($_['head']);
            //开始注入html[after]
            self::eachPrintJsOrCss($_['after']);
            self::eachPrintJsOrCss($_['css'], 'css');
            self::eachPrintJsOrCss($_['js'], 'js');

            echo '</body></html>';
        } else if (is_string($params)) {
            self::mergeHtmlData($_[$params], $params, $data);
        }
    }

    /**
     * 描述 : 合并HTML注入信息
     * 参数 :
     *     &save : 对应注入内容存储数据
     *     &type : 存储数据类型(title, js, css, head, body, before, after)
     *     &data : 需要存储的数据, string=相当[string], array=存储数据 {
     *          数据键, 如果是数字为对应值 : 注入字符串,
     *          ...
     *      }
     * 作者 : Edgar.lee
     */
    private static function mergeHtmlData(&$save, &$type, &$data) {
        if ($type === 'title') {
            $save = $data;
        } else {
            //字符串转换成数组
            is_string($data) && $data = array($data);

            //遍历存储参数
            foreach ($data as $k => &$v) {
                $save[is_int($k) ? $v : $k] = $v;
            }
        }
    }

    /**
     * 描述 : 循环打印js和css
     * 参数 :
     *      list : 打印列表
     *      type : css=输出样式,js=输出脚本
     * 作者 : Edgar.lee
     */
    private static function eachPrintJsOrCss(&$list, $type = '') {
        static $head = array(
            ''    => array('', ''),
            'js'  => array('<script src="', '" ></script>'),
            'css' => array('<link type="text/css" rel="stylesheet" href="', '" />')
        );

        //数组不为空
        if ($list) {
            $type && $url = self::path(true) .'/'. $type;

            foreach ($list as &$v) {
                if ($v) {
                    echo $head[$type][0],
                        $type ? of::formatPath($v, $url) : $v,
                        $head[$type][1];
                }
            }

            $list = null;
        }
    }

    /**
     * 描述 : 对象加载head部, "<"开头的会输出, 否则会include
     * 参数 :
     *      &list : 头部列表
     * 作者 : Edgar.lee
     */
    private function objInclude(&$_l) {
        $_p =  self::path(false);

        while ($_l) {
            $_k = key($_l);
            $_v = &$_l[$_k];
            unset($_l[$_k]);

            //加载路径
            if (isset($_v[0]) && trim($_v[0], '_/') === '') {
                include of::formatPath($_v, $_p);
            //打印标签
            } else {
                echo $_v;
            }
        };
    }

    /**
     * 描述 : 对象输出页面
     * 参数 :
     *      tpl : 模板名,默认调度方法名(不带扩增名)
     * 作者 : Edgar.lee
     */
    private function objDisplay(&$tpl) {
        include $tpl;
    }
}