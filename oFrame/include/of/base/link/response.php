<?php
/**
 * 描述 : 处理客户响应类
 * 作者 : Edgar.lee
 */
class of_base_link_response {
    /**
     * 描述 : 响应头信息
     * 参数 :
     *     &code : 数字=指定状态码,字符串=指定头信息
     *     &text : text为字符串=指定头信息,text为布尔=指定是否可替换,text为数字=指定code跳转状态码
     * 作者 : Edgar.lee
     */
    public static function header(&$code, &$text = null) {
        static $statusTexts = array(
            '100' => 'Continue',
            '101' => 'Switching Protocols',
            '200' => 'OK',
            '201' => 'Created',
            '202' => 'Accepted',
            '203' => 'Non-Authoritative Information',
            '204' => 'No Content',
            '205' => 'Reset Content',
            '206' => 'Partial Content',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Found',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '306' => '(Unused)',
            '307' => 'Temporary Redirect',
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '402' => 'Payment Required',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '405' => 'Method Not Allowed',
            '406' => 'Not Acceptable',
            '407' => 'Proxy Authentication Required',
            '408' => 'Request Timeout',
            '409' => 'Conflict',
            '410' => 'Gone',
            '411' => 'Length Required',
            '412' => 'Precondition Failed',
            '413' => 'Request Entity Too Large',
            '414' => 'Request-URI Too Long',
            '415' => 'Unsupported Media Type',
            '416' => 'Requested Range Not Satisfiable',
            '417' => 'Expectation Failed',
            '500' => 'Internal Server Error',
            '501' => 'Not Implemented',
            '502' => 'Bad Gateway',
            '503' => 'Service Unavailable',
            '504' => 'Gateway Timeout',
            '505' => 'HTTP Version Not Supported',
        );
        //发送状态码
        if (isset($statusTexts[$code])) {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            $text === null && $text = &$statusTexts[$code];
            header("{$protocol} {$code} {$text}");
        //发送指定信息
        } else if (is_bool($text)) {
            header($code, $text);
        //路径跳转
        } else {
            $text === null && $text = 302;
            header("Location: {$code}", true, $text);
            exit;
        }
    }

    /**
     * 描述 : 设定cookie
     * 参数 :
     *     &name     : 指定cookie名称
     *     &value    : 指定cookie值,null=删除
     *     &expire   : 过期时间,数字=指定x秒后过期,时间=过期时间,默认关闭浏览器过期
     *     &path     : 有效路径,默认''根路径,null=当前路径
     *     &domain   : 有效域,默认当前域
     *     &secure   : true=只在https下有效,false(默认)=不限制
     *     &httpOnly : 仅能通过http协议访问,如js等禁止访问,false(默认)=不限制,true=限制访问
     * 作者 : Edgar.lee
     */
    public static function cookie(
        &$name, &$value = null, &$expire = null, &$path = '', 
        &$domain = null, &$secure = false, &$httpOnly = false
    ) {
        //设定有效时间
        if (is_numeric($expire)) {
            $expire += $_SERVER['REQUEST_TIME'];
        //设定过期日期
        } else if ($expire !== null) {
            $expire = strtotime($expire);
        }
        return setcookie(
            rawurlencode($name), $value, $expire, 
            is_string($path) ? ROOT_URL . $path . '/' : null, 
            $domain, $secure, $httpOnly
        );
    }

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
    public static function &buffer($mode = true, $pool = null) {
        static $cache = null;
        static $info = array('mode' => true, 'pool'=> '');

        //清除缓冲
        if ($mode === null) {
            //返回状态数据
            if ($pool === false) {
                $text = $info;
            //字符串 || null
            } else {
                //读取记忆缓冲池
                $pool === null && $pool = $info['pool'];
                //格式化缓冲数据
                $text = isset($cache[$pool]) ? join($cache[$pool]) : '';
                //清空缓冲池
                unset($cache[$pool]);
            }
        //结束事件回调
        } else if ($pool === true) {
            //已使用,缓存,非系统
            if ($cache !== null && ($info['mode'] || $info['pool'])) {
                self::buffer(true, '');
                echo self::buffer(null, '');
            }
        //pool 字符串
        } else {
            //读取并关闭缓存数据
            $text = ob_get_clean();
            //是否开启绝对刷新
            ob_implicit_flush(!$info['mode'] = $mode !== false);
            //文本 ? 代替 : 原始
            is_string($mode) ? $cache[$info['pool']][] = $mode : $cache[$info['pool']][] = &$text;
            //更新缓冲池
            $pool === null || $info['pool'] = &$pool;
            //开启缓冲
            if ($info['mode']) {
                ob_start();
            //输出对应缓存区内容
            } else {
                echo self::buffer(null, $info['pool']);
            }
        }

        return $text;
    }
}