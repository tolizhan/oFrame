<?php
/**
 * 描述 : 所有扩展均继承该类
 * 作者 : Edgar.lee
 */
class of_base_extension_baseClass {
    //共享数据
    private static $shareData = null;
    //存储常量
    private $constants = null;

    public function __construct($constructParams) {
        //包含 __FILE__, eKey
        $constants = unserialize(stripslashes($constructParams));
        //扩展键文件夹
        $temp = '/' . $constants['eKey'];
        $this->constants = &$constants;

        //存储路径
        $constants['sDir'] = of_base_extension_manager::getConstant('extensionSave') . $temp;
        //存储url
        $constants['sUrl'] = of::config(
            '_of.extension.save',
            OF_DATA . '/_of/of_base_extension/save',
            'url'
        ) . $temp;
        //扩展路径
        $constants['eDir'] = of_base_extension_manager::getConstant('extensionDir') . $temp;
        //扩展url
        $constants['eUrl'] = of::config('_of.extension.path', OF_DATA . '/extensions', 'url') . $temp;
        //当前扩展的数据库前缀
        $constants['eDbPre'] = $dbPrefix = strtolower("e_{$constants['eKey']}_");
        //当前匹配的地址
        $constants['matchUri'] = self::$shareData['matchUri'];

        //初始化自定义方法
        method_exists($this, 'main') && $this->main();
    }

    /**
     * 描述 : 获取指定常量
     * 参数 :
     *      key : 指定常量
     * 作者 : Edgar.lee
     */
    protected function _getConst($key = null) {
        $constants = &$this->constants;
        //返回所有常量
        if ($key === null) {
            return $constants;
        //返回指定常量
        } else if (isset($constants[$key])) {
            return $constants[$key];
        //判断是路径
        } else if (strpbrk($key, '\\/')) {
            //eval的地址返回当前__FILE__
            return strpos($key, '(') === false ? $key : $constants['__FILE__'];
        } else {
            return null;
        }
    }

    /**
     * 描述 : 生成动态扩展页面地址
     * 参数 :
     *      a      : 指定a参数
     *      params : 自定义get参数,null=默认
     * 返回 :
     *      生成的URL字符串
     * 作者 : Edgar.lee
     */
    protected function _getExUrl($a, $params = null) {
        //匹配值
        $params['a'] = $a;
        //独享页面类
        $params['c'] = &self::$shareData['exclusive'];
        //扩展名
        isset($params['e']) || $params['e'] = $this->_getConst('eKey');
        return OF_URL . '/index.php?' . http_build_query($params);
    }

    /**
     * 描述 : 获取多语言
     * 参数 :
     *      参考 of_base_language_packs::getText
     * 返回 :
     *      翻译的字符串
     * 作者 : Edgar.lee
     */
    protected function &_getText($string, $params = null) {
        $params['file'] = $this->constants['__FILE__'];
        return L::getText($string, $params);
    }

    /**
     * 描述 : 加载扩展类
     * 参数 :
     *      className : 相对类名
     *      isNew     : 是否返回对象,true(默认)=返回对象,false=返回完整类名
     *      eKey      : 扩展文件名,默认为本扩展名
     * 返回 :
     *      成功返回对象或完整类名,失败返回false
     * 作者 : Edgar.lee
     */
    protected function _loadClass($className, $isNew = true, $eKey = null) {
        //扩展名
        $eKey || $eKey = $this->_getConst('eKey');
        return of_base_extension_match::loadClass($eKey, $className, $isNew);
    }

    /**
     * 描述 : 以不同方式加载文件
     * 参数 :
     *      path       : 以当前扩展为根目录的'/xx/xxx'格式路径
     *      fileExtend : 强制文件扩展名,默认null自动识别
     * 返回 :
     *      php=include方式加载; js=打印script标签,并激活语言包; css=打印link标签; 其他=打印网络路径
     * 作者 : Edgar.lee
     */
    protected function _loadFile($path, $fileExtend = null) {
        is_array($path) || $path = array($path);
        //扩展路径
        $extensionDir = $this->_getConst('eDir');
        //扩展url
        $extensionUrl = $this->_getConst('eUrl');

        foreach ($path as &$v) {
            switch (strtolower($fileExtend === null ? pathinfo($v, PATHINFO_EXTENSION) : $fileExtend)) {
                //include文件
                case 'php':
                    return include $extensionDir . $v;
                //script 标签,同时会激活语言包
                case 'js' :
                    echo '<script eUrl="' .$extensionUrl. '" eKey="' .$this->_getConst('eKey'). '" src="', $extensionUrl, $v, '" ></script>';
                    break;
                //link   标签
                case 'css':
                    echo '<link type="text/css" rel="stylesheet" href="', $extensionUrl, $v, '" />';
                    break;
                //打印网络路径
                default   :
                    echo $extensionUrl, $v;
            }
        }
    }

    /**
     * 描述 : 操作共享数据
     * 参数 :
     *      command : 操作命令, null=共享方式读取数据, true=独享方式读取数据(加锁), false=(解锁)
     * 返回 :
     *      以引用方式返回数据
     * 说明 :
     *      引用数据设为null,会重新读取数据,否则读取缓存数据
     * 作者 : Edgar.lee
     */
    protected function &_shareData($command = null) {
        self::_sharedBaseFun($this->_getConst('eKey'), $command, $receiveData);

        return $receiveData['data'];
    }

    /**
     * 描述 : 获取数据连接或执行sql
     * 参数 :
     *      sql : 字符串 = 执行传入的sql
     *            null   = 开启事务,
     *            true   = 提交事务,
     *            false  = 回滚事务
     * 返回 :
     *      返回连接源或结果集
     * 作者 : Edgar.lee
     */
    protected function &_sql($sql, $key = 'default') {
        return of_db::sql($sql, $key);
    }

    /**
     * 描述 : 添加钩子
     * 参数 :
     *      type   : 钩子类型(以'_'开头为私有钩子)
     *      asCall : 钩子触发时调用
     *      params : 自定义参数,由callback第二个参数接收,null=默认
     * 作者 : Edgar.lee
     */
    protected function _addHook($type, $callback, $params = null) {
        //索引钩子列表
        $hookList = &self::$shareData['hookList'];
        $this->_removeHook($type, $callback, $index);

        $hookList[$this->_getConst('eKey')][$type][] = array(
            'asCall' => &$index['callbackParse'],
            'params' => &$params
        );
    }

    /**
     * 描述 : 触发钩子
     * 参数 :
     *      type   : 钩子类型(以'_'开头为私有钩子)
     *      params : 传递参数,由callback第一个参数接收,null=默认
     * 作者 : Edgar.lee
     */
    protected function _fireHook($type, $params = null) {
        //索引钩子列表
        $hookList = &self::$shareData['hookList'];
        //扩展名
        $eKey = $this->_getConst('eKey');

        //私有钩子
        if ($type[0] === '_') {
            //钩子存在
            if (isset($hookList[$eKey][$type])) {
                foreach ($hookList[$eKey][$type] as &$v) {
                    of_base_extension_match::callExtension($eKey, $v['asCall'], array(&$params, &$v['params']));
                }
            }
        //公有钩子
        } else {
            of_base_extension_match::fireHook($type, $params, false);
        }
    }

    /**
     * 描述 : 移除钩子(仅能移除当前扩展的钩子)
     * 参数 :
     *      type   : 钩子类型(以'_'开头为私有钩子)
     *      asCall : 移除指定的回调钩子,null=默认
     *      index  : 引用数据{'callbackParse' : 解析的移除回调}
     * 作者 : Edgar.lee
     */
    protected function _removeHook($type, $callback = null, &$index = null) {
        //索引钩子列表
        $hookList = &self::$shareData['hookList'];
        //扩展对象引用
        $classObj = &self::$shareData['extensionClassObj'];
        //扩展名
        $eKey    = $this->_getConst('eKey');
        //回调解析,尝试将callback变成字符串数组
        $index['callbackParse'] = &$callback;

        //解析结构
        is_string($callback) && $callback = explode('::', $callback, 2);
        //获取类名(判断是类) && 类已编译 && 为默认对象
        if (($temp = get_class($callback[0])) && isset($classObj[$temp]) && $classObj[$temp] === $callback[0]) {
            $callback[0] = substr($temp, strlen(of_base_extension_manager::getConstant('baseClassName')) + strlen($eKey) + 1);
        }

        if (isset($hookList[$eKey][$type])) {
            $hookList = &$hookList[$eKey];
            if ($callback === null) {
                unset($hookList[$type]);
            } else {
                //当前扩展类型钩子 的 单个回调
                foreach ($hookList[$type] as $k => &$v) {
                    if ($v['asCall'] === $callback) {
                        unset($hookList[$type][$k]);
                        break;
                    }
                }

                //当前钩子数组为空,则删除
                if (count($hookList[$type]) === 0) {
                    unset($hookList[$type]);
                }
            }
        }
    }

    /**
     * 描述 : 初始化共享数据(仅第一次调用有效)
     * 参数 :
     *      shareData : 传递共享数据(数据中的值以引用方式传递)
     * 作者 : Edgar.lee
     */
    public static function _initShareData($shareData) {
        self::$shareData === null && self::$shareData = $shareData;
    }

    /**
     * 描述 : 引入 sharedData(类似 session) 机制
     * 参数 :
     *      eKey        : 字符串=指定操作的扩展名, null=保存已修改的数据
     *      command     : 操作命令, true=独享方式读取数据(加锁), null=共享方式读取数据(解锁), false=保存数据(解锁)
     *     &receiveData : 引用接收数据, null=默认
     * 返回 :
     *      false=数据有效并保存成功返回true,否则返回false
     *      其他 =返回读取数据
     * 结构 : {
     *          '扩展名' : {
     *              'fp'   : 文件流
     *              'lock' : 是否拥有独享锁, true=是,false=否
     *              'data' : apalication 数据
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    private static function _sharedBaseFun($eKey, $command = null, &$receiveData = null) {
        //共享数据缓存列表
        static $sharedDataList = array();
        //扩展根目录
        static $extensionDir   = null;
        //引用扩展
        $indexExtension        = &$sharedDataList[$eKey];
        //引用扩展文件流
        $indexFopen            = null;

        //全局初始化
        if ($extensionDir === null) {
            $extensionDir = of_base_extension_manager::getConstant('extensionSave');
        }

        //局部初始化
        if (!isset($indexExtension['fp'])) {
            //扩展目录
            $temp = "{$extensionDir}/{$eKey}/_info/sharedData";
            //创建共享数据目录
            is_dir($temp) || mkdir($temp, 0777, true);

            //创建文件流
            $indexExtension['fp'] = fopen($filePath = $temp . '/data.php', is_file($filePath) ? 'r+' : 'x+');
            //无独享锁
            $indexExtension['lock'] = false;
            //无数据
            isset($indexExtension['data']) || $indexExtension['data'] = null;
        }
        $indexFopen = &$indexExtension['fp'];

        //保存数据
        if ($command === false) {
            //返回信息
            $temp = false;
            //已加锁 && 文件流有效
            if ($indexExtension['lock'] && $indexFopen !== false) {
                if (is_array($indexExtension['data'])) {
                    //独享锁
                    flock($indexFopen, LOCK_EX);
                    fseek($indexFopen, 0);
                    ftruncate($indexFopen, 0);
                    fwrite($indexFopen, '<?php exit; ?> ' . serialize($indexExtension['data']));
                    //保存成功
                    $temp = true;
                }
                //这样写法默认会代替 flock($indexFopen, LOCK_UN) 和 close($indexFopen)
                $indexFopen = null;
                //标记无锁
                $indexExtension['lock'] = false;
            }

            //数据接收
            $receiveData = array(
                'data' => $temp
            );
        //保存数据 $command 为 null 或 true
        } else {
            //只读模式
            if ($command === null) {
                //共享锁
                flock($indexFopen, LOCK_SH);
            //加锁模式 && 没加锁
            } else if ($indexExtension['lock'] === false) {
                //独享锁
                flock($indexFopen, LOCK_EX);
                //标记独享锁
                $indexExtension['lock'] = true;
                //重置数据
                $indexExtension['data'] = null;
            }

            //不存在缓存数据
            if (!isset($indexExtension['data']) && $indexFopen !== false) {
                fseek($indexFopen, 15);
                do {
                    $data[] = fread($indexFopen, 1024);
                } while (!feof($indexFopen));

                //反序列化
                $indexExtension['data'] = ($data = join($data)) === '' ? array() : unserialize($data);
            }

            //如果 共享方式 则解锁连接
            if ($command === null) {
                //这样写法默认会代替 flock($indexFopen, LOCK_UN) 和 close($indexFopen)
                $indexFopen = null;
            }

            //数据接收
            $receiveData = array(
                'data' => &$indexExtension['data']
            );
        }
    }
}