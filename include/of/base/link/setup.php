<?php
/**
 * 描述 : 快速映射类
 * 作者 : Edgar.lee
 */
//插件配置文件
$addin = of::config('_of.link.addin');
$addin['pConfig'] = isset($addin['pConfig']) ?
    ROOT_DIR . $addin['pConfig'] : OF_DIR . '/addin/config.php';
$addin['jConfig'] = isset($addin['jConfig']) ?
    ROOT_URL . $addin['jConfig'] : OF_URL . '/addin/config.js';

//输出控制监听
of::event('of::halt', array('asCall' => 'L::buffer', 'params' => array(true, true)));
//为前端集成 jquery, L 封装 及 框架常量
of_view::head('head', 
    //初始化路径
    '<script>var ROOT_URL="' .ROOT_URL. '", OF_URL="' .OF_URL. '", VIEW_URL="' .of_view::path(true). '";</script>' .
    //加载 jquery.js
    '<script src="' .OF_URL. '/att/link/jquery.js" ></script>' .
    //加载 L.js
    '<script src="' .OF_URL. '/att/link/L.js" addin="' .$addin['jConfig']. '" ></script>'
);

/**
 * 描述 : 魔术方法, 获取com组件及view对象
 * 参数 :
 *      key : 以"_"开头的变量会创建并返回 of_base_com_xxx 对象, "view"时会实例化 of_view
 * 作者 : Edgar.lee
 */
of::link('__get', '$key', 'return of_base_link_extends::get($key);', false);

/**
 * 描述 : 输出页面 of_view::display 映射方法
 * 参数 :
 *      tpl : 模板名,默认调度方法名.视图扩展名
 *          '/'开头=相对当前视图路径
 *          '_'开头=完整的磁盘目录
 *          其它   =相对视图根目录的调度类结构相同
 * 作者 : Edgar.lee
 */
of::link('display', '$tpl = null', 'of_view::display($tpl);');

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
of::link('&sql', '$sql, $key = \'default\'', 'return of_db::sql($sql, $key);');

/**
 * 描述 : 获取get数据
 * 作者 : Edgar.lee
 */
of::link('&get', '$key = null, $default = null', 'return of::getArrData(array(&$key, &$_GET, &$default));');

/**
 * 描述 : 获取post数据
 * 作者 : Edgar.lee
 */
of::link('&post', '$key = null, $default = null', 'return of::getArrData(array(&$key, &$_POST, &$default));');

/**
 * 描述 : 设定cookie
 * 作者 : Edgar.lee
 */
of::link(
    'cookie',
    '$name, $value = null, $expire = null, $path = \'\', $domain = null, $secure = false, $httpOnly = false', 
    'return of_base_link_response::cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);'
);

/**
 * 描述 : 重定向地址
 * 参数 :
 *      code : 数字=指定状态码,字符串=指定头信息
 *      text : text为字符串=指定头信息,text为布尔=指定是否可替换,text为数字=指定code跳转状态码
 * 作者 : Edgar.lee
 */
of::link('header', '$code, $text = null', 'return of_base_link_response::header($code, $text);');

/**
 * 描述 : 输出缓冲控制
 * 参数 :
 *      mode : (false)true=永久缓冲,false=关闭缓冲,null=清除缓冲,字符串=添加缓存内容
 *      pool : (null)null=使用上次级别,字符串=对应缓冲池
 * 返回 :
 *      mode=true              : 保存并返回在服务器中的缓存内容
 *      mode=false             : 保存并返回在服务器中的缓存内容, 同时输出pool缓冲池的内容
 *      mode=字符串            : 保存mode内容并返回在服务器中的缓存内容
 *      mode=null              : 返回并清空缓冲内容
 *      mode=null,pool=false时 : 返回当期状态 {
 *          "mode" : 缓存状态,bool
 *          "pool" : 当前缓存池
 *      }
 * 注明 :
 *      缓存数据($cache)结构 : {
 *          缓冲池名称 : [单次数据, ...], ...
 *      }
 * 作者 : Edgar.lee
 */
of::link('&buffer', '$mode = true, $pool = null', 'return of_base_link_response::buffer($mode, $pool);');

/**
 * 描述 : 请求参数规则验证
 * 参数 :
 *     &rule : 验证的规则 {
 *          调度的方法名 : {
 *              $GLOBALS 中的get post等键名 : {
 *                  符合 of_base_com_data::rule 规则
 *              }
 *          }
 *      }
 *      exit : 校验失败是否停止, true=停止, false=返回
 * 返回 :
 *      无返回, 校验失败直接 exit
 * 作者 : Edgar.lee
 */
of::link('rule', '&$rule, $exit = true', 'return of_base_link_request::rule($rule, $exit);');

/**
 * 描述 : 加载集成插件
 * 参数 :
 *      name : 插件名称, 在 "/addin/config.php" 中定义的
 * 返回 :
 *      "/addin/config.php" 决定返回值
 * 作者 : Edgar.lee
 */
of::link('open', '$name', "return include '{$addin['pConfig']}';");

/**                                  预留接口******************************
 * 描述 : 获取翻译文本,映射 of_base_language_packs::getText
 * 作者 : Edgar.lee
 */
of::link('&getText', '$string', 'return $string;');