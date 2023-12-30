<?php
/**
 * 描述 : 多语言包类
 * 注明 :
 *      如何切换语言包       : 修改 $_COOKIE['of_base_language']['name'] 为对应的语言包名
 *      环境变量数据(envVar) : 环境变量结构 {
 *          "name"  : 当前使用的语言包文件夹名
 *          "path"  : 语言包根路径
 *          "pack"  : 语言包存储的位置 {
 *              "php" : php端语言 {
 *                  源语言 : {
 *                      区分键 : 翻译的语言
 *                  }
 *              },
 *              "js"  : js端语言, 结构同php端语言
 *          }
 *          "block" : 语言包块 {
 *              相对根目录路径(追加.php) : {
 *                  "jsPack"  : js语言包 {
 *                      动作名(action) : {
 *                          源语言 : {
 *                              区分键 : {
 *                                  "translate" : 翻译内容
 *                              }
 *                          }
 *                      }
 *                  },
 *                  "jsLink"  : 引用的js文件路径 {
 *                      动作名(action) : {
 *                          引用文件路径 : true
 *                      }
 *                  }
 *                  "phpPack" : 参考 jsPack 结构
 *                  "phpLink" : 参考 jsPack 结构
 *              }
 *          }
 *      }
 * 作者 : Edgar.Lee
 */
class of_base_language_packs {
    //环境变量
    private static $envVar = array();
    //是否开发模式
    private static $debug = false;

    /**
     * 描述 : 初始化语言包
     * 作者 : Edgar.lee
     */
    public static function init() {
        //引用环境变量
        $envVar = &self::$envVar;
        //初始化配置
        $envVar += of::config('_of.language', array()) + array(
            'path' => OF_DATA . '/_of/of_base_language_packs',
            'match' => '/^([\w "\'-,]+)$|^(\W+)$|^(.+?)(?:: |[.!?]+$)/',
            'default' => 'base'
        );
        //选择语言包
        $envVar['name'] = isset($_COOKIE['of_base_language']['name']) ?
            $_COOKIE['of_base_language']['name'] : $envVar['default'];
        //是否开发模式
        self::$debug = OF_DEBUG !== false || $envVar['default'] === 'base';

        //语言包根路径
        $temp = $envVar['path'] .'/'. $envVar['name'];
        $temp = ' path="' . $envVar['path'] .'/'. $envVar['name'] .
            '" match="' . htmlspecialchars($envVar['match'], ENT_QUOTES, 'UTF-8') .
            '" debug="' . self::$debug .
            '" init="' . !!is_file(ROOT_DIR . $temp . '/js.txt');
        //加载前台语言包
        of_view::head('head', '<script' .$temp. '" src="' .OF_URL. '/att/language/language.js" ></script>');

        of::event('of::dispatch', 'of_base_language_packs::callbackDispatch');

        $envVar['path'] = ROOT_DIR . $envVar['path'];

        //开启调试模式
        self::$debug && register_shutdown_function('of_base_language_packs::save');
        //添加翻译连接
        of::link('&getText', '$string, $params = array()', 'return of_base_language_packs::getText($string, $params);');
    }

    /**
     * 描述 : of::dispatch 回调
     * 作者 : Edgar.lee
     */
    public static function callbackDispatch() {
        //初始化请求地址
        ($temp = of::dispatch()) || $temp = array('class' => '', 'action' => '');
        //加载前台语言包
        of_view::head('head', '<script>window.L.getText.init(' .json_encode($temp). ');</script>');
        //移除回调
        of::event('of::dispatch', false, 'of_base_language_packs::callbackDispatch');
    }

    /**
     * 描述 : 获取php端语言包
     * 参数 :
     *     &string : 指定翻译的字符串
     *     &params : 附加数组参数 {
     *          "key"   : 区分键,默认""
     *          "mode"  : 翻译模式, 0=完整翻译, 1=按_of.language.match规则提取翻译文本
     *      }
     * 返回 :
     *      翻译的字符串
     * 作者 : Edgar.lee
     */
    public static function &getText(&$string, &$params = array()) {
        //引用环境变量
        $envVar = &self::$envVar;
        //初始化参数
        $params += array('key' => '', 'type' => 'php', 'mode' => 0);

        //去空白字符串
        if ($string = trim($string)) {
            //提取翻译
            if ($params['mode']) {
                //匹配成功
                if (preg_match($envVar['match'], $string, $match, PREG_OFFSET_CAPTURE)) {
                    foreach ($match as $k => &$v) {
                        //提取翻译文本
                        if ($k && $v[0]) {
                            $match = &$v;
                            $tran = $v[0];
                            break ;
                        }
                    }
                }
            //完整翻译
            } else {
                $tran = $string;
            }

            //提取到翻译文本
            if (isset($tran)) {
                //debug时,给源语言定位
                self::$debug && self::source($tran, $params);

                //加载对应类型语言包
                $index = &self::load($params['type']);
                //引用翻译数据{区分键 : 翻译的语言, ...}
                $index = &$index[$tran];

                //对应键未翻译, 判断全局键是否翻译
                if (empty($index[$params['key']])) {
                    $index[$params['key']] = '';
                    empty($index['']) || $tran = $index[''];
                //已翻译
                } else {
                    $tran = $index[$params['key']];
                }

                //提取翻译
                if ($params['mode']) {
                    $string = substr_replace($string, $tran, $match[1], strlen($match[0]));
                //完整翻译
                } else {
                    $string = $tran;
                }
            //匹配失败提示, 开发模式报错, 其它模式备注
            } else {
                of::event('of::error', true, array(
                    'memo' => !OF_DEBUG,
                    'info' => 'Translation matching "_of.language.match" failed: ' . $string,
                    'file' => __FILE__,
                    'line' => __LINE__
                ));
            }
        }

        return $string;
    }

    /**
     * 描述 : 保存语言包
     * 作者 : Edgar.lee
     */
    public static function save() {
        //引用环境变量
        $envVar = &self::$envVar;

        //整合基础包
        foreach (array('php', 'js') as $type) {
            //引用当前语言包
            $nowPack = &$envVar['pack'][$type];

            if (is_array($nowPack)) {
                //基础包路径
                $basePath = "{$envVar['path']}/base/{$type}.txt";

                //基础语言包
                if ($basePack = of_base_com_disk::file($basePath)) {
                    //js=>json; php=>serialize
                    $basePack = $type === 'js' ? json_decode($basePack, true) : unserialize($basePack);
                }

                //初始化语言包
                is_array($basePack) || $basePack = array();
                //增量整合
                foreach ($nowPack as $str => &$sv) {
                    foreach ($sv as $key => &$v) $basePack[$str][$key] = '';
                }

                $basePack = $type === 'js' ? json_encode($basePack) : serialize($basePack);
                //写回基础包
                of_base_com_disk::file($basePath, $basePack);
            }
        }

        //保存块翻译
        if (isset($envVar['block'])) {
            foreach ($envVar['block'] as $path => &$v) {
                of_base_com_disk::file($envVar['path'] . '/base/source' . $path, $v, true);
            }
        }
    }

    /**
     * 描述 : 整合JS语言包
     * 作者 : Edgar.lee
     */
    public static function update() {
        if (self::$debug) {
            //js 语言包
            $_POST['params']['type'] = 'js';
            self::getText($_POST['string'], $_POST['params']);
        }

        echo '1';
    }

    /**
     * 描述 : 加载语言包
     * 参数 :
     *      type : php=php端语言, js=js端语言包, /开头=指定单个块文件路径
     * 作者 : Edgar.lee
     */
    private static function &load($type) {
        //引用环境变量
        $envVar = &self::$envVar;

        //指定块文件
        if ($type[0] === '/') {
            $index = &$envVar['block'][$type .= '.php'];
            //读取文件包
            $index || $index = of_base_com_disk::file("{$envVar['path']}/base/source{$type}", true, true);
        } else {
            $index = &$envVar['pack'][$type];
            if (
                //内容未初始化
                empty($index) &&
                //文件存在
                is_file($temp = "{$envVar['path']}/{$envVar['name']}/{$type}.txt") &&
                //打开文件
                $temp = of_base_com_disk::file($temp)
            ) {
                //解码文件 js=>json; php=>serialize
                $index = $type === 'js' ? json_decode($temp, true) : unserialize($temp);
            }
        }

        return $index;
    }

    /**
     * 描述 : 标记源语言位置
     * 参数 :
     *      参考 self::getText 方法
     * 作者 : Edgar.lee
     */
    private static function source(&$string, &$params) {
        static $dispatch = null;
        //引用环境变量
        $envVar = &self::$envVar;

        //当前默认请求
        $dispatch || ($dispatch = of::dispatch()) || $dispatch = array('class' => '', 'action' => '');
        //初始化当前请求
        isset($params['class']) || $params += $dispatch;

        //php 模式下调取回溯得file
        if (empty($params['file']) && $params['type'] === 'php') {
            //追踪层次
            isset($params['trace']) || $params['trace'] = 0;
            $backtrace = debug_backtrace(0);

            foreach ($backtrace as $k => &$v) {
                if ($v['function'] === 'getText' && $v['class'] === 'L') {
                    $k += $params['trace'];
                    $params['file'] = $backtrace[$k]['file'];
                }
            }
        }

        //检查flie有效性
        if (!empty($params['file']) && !strpos($params['file'], '(')) {
            //整理file
            $filePath = strtr(
                $params['type'] === 'js' ? $params['file'] : substr($params['file'], strlen(ROOT_DIR)),
                '\\', '/'
            );

            //存在类
            if ($params['class']) {
                $temp = str_replace(array('_', '\\'), '/', $params['class']);
                strncmp($temp, 'of/', 3) === 0 && $temp = substr(OF_DIR, strlen(ROOT_DIR) + 1) . '/' . substr($temp, 3);

                if (($temp = '/' . $temp . '.php') !== $filePath) {
                    $index = &self::load($temp);
                    //更新引用
                    $index[$params['type'] . 'Link'][$params['action']][$filePath] = true;
                }
            }

            $index = &self::load($filePath);
            //更新行数
            $index[$params['type'] . 'Pack'][$params['action']][$string][$params['key']]['translate'] = '';
        }
    }
}

of_base_language_packs::init();
return true;