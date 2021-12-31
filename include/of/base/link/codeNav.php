<?php
/**
 * 描述 : 未实际运行, 仅作IDE编辑器对"of\xxx\yyy"及"L类"的代码跟踪
 * 作者 : Edgar.lee
 */
namespace of {

    abstract class db extends \of_db {
    }

    class view extends \of_view {
    }

}

namespace of\accy\com\kv {

    class files extends \of_accy_com_kv_files {
    }

    class memcache extends \of_accy_com_kv_memcache {
    }

    class redis extends \of_accy_com_kv_redis {
    }

}

namespace of\accy\com\mq {

    class mysql extends \of_accy_com_mq_mysql {
    }

}

namespace of\accy\db {

    class mysql extends \of_accy_db_mysql {
    }

    class mysqlPdo extends \of_accy_db_mysqlPdo {
    }

    class mysqli extends \of_accy_db_mysqli {
    }

    class tidb extends \of_accy_db_tidb {
    }

}

namespace of\accy\session {

    class files extends \of_accy_session_files {
    }

    class kv extends \of_accy_session_kv {
    }

    class mysql extends \of_accy_session_mysql {
    }

}

namespace of\base\com {

    class com extends \of_base_com_com {
    }

    class csv extends \of_base_com_csv {
    }

    class data extends \of_base_com_data {
    }

    class disk extends \of_base_com_disk {
    }

    class hParse extends \of_base_com_hParse {
    }

    class kv extends \of_base_com_kv {
    }

    abstract class mq extends \of_base_com_mq {
    }

    class net extends \of_base_com_net {
    }

    class str extends \of_base_com_str {
    }

    class timer extends \of_base_com_timer {
    }

}

namespace of\base\error {

    class jsLog extends \of_base_error_jsLog {
    }

    class tool extends \of_base_error_tool {
    }

    class toolBaseClass extends \of_base_error_toolBaseClass {
    }

    class writeLog extends \of_base_error_writeLog {
    }

}

namespace of\base\extension {

    class baseClass extends \of_base_extension_baseClass {
    }

    class manager extends \of_base_extension_manager {
    }

    class match extends \of_base_extension_match {
    }

    class tool extends \of_base_extension_tool {
    }

    class toolBaseClass extends \of_base_extension_toolBaseClass {
    }

}

namespace of\base\firewall {

    class main extends \of_base_firewall_main {
    }

}

namespace of\base\htmlTpl {

    class engine extends \of_base_htmlTpl_engine {
    }

    class tool extends \of_base_htmlTpl_tool {
    }

}

namespace of\base\language {

    class packs extends \of_base_language_packs {
    }

    class toolBaseClass extends \of_base_language_toolBaseClass {
    }

}

namespace of\base\link {

    class extend extends \of_base_link_extend {
    }

    class request extends \of_base_link_request {
    }

    class response extends \of_base_link_response {
    }

}

namespace of\base\session {

    class base extends \of_base_session_base {
    }

}

namespace of\base\sso {

    class api extends \of_base_sso_api {
    }

    class main extends \of_base_sso_main {
    }

    class tool extends \of_base_sso_tool {
    }

}

namespace of\base\test {

    class case extends \of_base_test_case {
    }

    class tool extends \of_base_test_tool {
    }

    class toolBaseClass extends \of_base_test_toolBaseClass {
    }

}

namespace of\base\tool {

    class mysqlSync extends \of_base_tool_mysqlSync {
    }

    class test extends \of_base_tool_test {
    }

}

namespace of\base\version {

    class check extends \of_base_version_check {
    }

}

namespace of\base\xssFilter {

    class main extends \of_base_xssFilter_main {
    }

}

namespace {

    class L {
        public static function fireHook($type, $params = null) {
            /*of_base_extension_match::fireHook($type, $params, true);*/
        }

        public static function &getHtmlTpl($params) {
            /*return of_base_htmlTpl_engine::getHtmlTpl($params);*/
        }

        public static function &getText($string, $params = null) {
            /*return of_base_language_packs::getText($string, $params);*/
        }

        public function __get($key) {
            /*return of_base_link_extend::get($key);*/
        }

        public static function work($code, $info = '', $data = array()) {
            /*return of::work('extr', array('code' => &$code, 'info' => &$info, 'data' => &$data, 'trace' => 2));*/
        }

        public static function display($tpl = null) {
            /*of_view::display($tpl);*/
        }

        public static function &sql($sql, $key = 'default') {
            /*return of_db::sql($sql, $key);*/
        }

        public static function &get($key = null, $default = null) {
            /*return of::getArrData(array(&$key, &$_GET, &$default));*/
        }

        public static function &post($key = null, $default = null) {
            /*return of::getArrData(array(&$key, &$_POST, &$default));*/
        }

        public static function cookie($name, $value = null, $expire = null, $path = '', $domain = null, $secure = false, $httpOnly = false) {
            /*return of_base_link_response::cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);*/
        }

        public static function header($code, $text = null) {
            /*return of_base_link_response::header($code, $text);*/
        }

        public static function &buffer($mode = true, $pool = null) {
            /*return of_base_link_response::buffer($mode, $pool);*/
        }

        public static function rule(&$rule, $exit = true) {
            /*return of_base_link_request::rule($rule, $exit);*/
        }

        public static function open($name) {
            /*return include '';*/
        }
    }

}