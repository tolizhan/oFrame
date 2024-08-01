<?php
/**
 * 描述 : swoole调试代码
 * 作者 : Edgar.lee
 */
namespace {

    class Co {
        /**
         * 描述 : 代码调试
         * 作者 : Edgar.lee
         */
        public static function debug($fnObj, $path, &$debug) {
            //测试文件不存在
            if (!is_file($path .= '/test.php')) exit('文件不存在: ' . strtr($path, '\\', '/'));
            //配置环境
            extract(Co::mockEnv($fnObj), EXTR_REFS);
            //标记全局空间
            $_GLOBAL_SCOPE_ = 1;
            //打印编译代码
            $debug = 1;

            //加载执行脚本
            include "sw.incl://0\n{$_GLOBAL_SCOPE_}\n" . $_FUNC_mapVar->incStart = $path;
            //释放内存
            swoole::clear();
        }

        /**
         * 描述 : 配置环境
         * 作者 : Edgar.lee
         */
        public static function &mockEnv($fnObj) {
            //关闭默认输出缓存
            ob_get_level() && ob_get_clean();
            //设置标识时区, 用来判断是否需要更改协程时区
            date_default_timezone_set('GMT-0');
            //注册结束执行
            //register_shutdown_function('swoole::clear', true);
            //注册清理空间
            //register_shutdown_function('swoole::clear', false);

            //兼容常量
            foreach (array(
                //定义协程默认状态, 定义抢占调度设置
                'SWOOLE_HOOK_FULL' => 0, 'SWOOLE_SCHEDULER' => true,
                //相对命名空间 xx\yy (314), 绝对命名空间 \xx\yy (312)
                'T_NAME_QUALIFIED' => 0, 'T_NAME_FULLY_QUALIFIED' => 0, 
                //自身命名空间 namespace\xxx (313), 只读关键词 readonly (363)
                'T_NAME_RELATIVE' => 0, 'T_READONLY' => 0,
                //注解"#[" (387)
                'T_ATTRIBUTE' => 0
            ) as $k => $v)  defined($k) || define($k, $v);

            //默认系统配置
            $GLOBALS['system'] = array(
                'port' => 88, 'memory' => ini_get('memory_limit'),
                'hook' => null, 'sysDir' => __DIR__,
                'reinit' => array()
            );
            //引用php配置
            $GLOBALS['phpIni'] = array(
                'max_execution_time' => 30, 'memory_limit' => 128 * 1024,
                'date.timezone' => 'PRC'
            );
            //合并GLOBALS超全局变量
            $super = array(
                '_GET' => &$GLOBALS['_GET'],
                '_POST' => &$GLOBALS['_POST'],
                '_COOKIE' => &$GLOBALS['_COOKIE'],
                '_FILES' => &$GLOBALS['_FILES'],
                '_SERVER' => &$GLOBALS['_SERVER'],
                '_SESSION' => &$GLOBALS['_SESSION'],
                '_REQUEST' => &$GLOBALS['_REQUEST'],
                'GLOBALS' => &$super
            );

            //生成超全局变量
            $attr = &swoole::loadEnv($_SERVER);
            //初始化接管方法对象
            $attr['_FUNC_mapVar'] = $fnObj;
            //临时代码
            foreach ($super as $k => &$v) $attr["{$k}_mapVar"] = &$v;
            //返回超全局变量
            return $attr;
        }

        /**
         * 描述 : 获取协程ID
         * 作者 : Edgar.lee
         */
        public static function getCid() {
            return 0;
        }

        /**
         * 描述 : 响应信息
         * 作者 : Edgar.lee
         */
        public static function end($text) {
            echo $text;
        }

        /**
         * 描述 : 获取'php://input'数据
         * 作者 : Edgar.lee
         */
        public function rawContent() {
            return file_get_contents('php://input');
        }

        /**
         * 描述 : 设置 HTTP 响应的 Header 信息
         * 作者 : Edgar.lee
         */
        public function header($key, $value) {
            header($key . ': ' . $value);
        }

        /**
         * 描述 : 发送 Http 状态码
         * 作者 : Edgar.lee
         */
        public function status($code, $info) {
            header("{$_SERVER['SERVER_PROTOCOL']} {$code} {$info}");
        }

        /**
         * 描述 : 发送 Http 状态码
         * 作者 : Edgar.lee
         */
        public function cookie($name, $value, $expire, $path, $domain, $secure, $httponly) {
            return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }

        /**
         * 描述 : 发送 Http 状态码
         * 作者 : Edgar.lee
         */
        public function rawCookie($name, $value, $expire, $path, $domain, $secure, $httponly) {
            return setrawcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }

        /**
         * 描述 : 协程延迟回调
         * 作者 : Edgar.lee
         */
        public static function defer($callback) {
        }

        /**
         * 描述 : 开启抢占式调度
         * 作者 : Edgar.lee
         */
        public static function enableScheduler() {
        }

        /**
         * 描述 : 关闭抢占式调度
         * 作者 : Edgar.lee
         */
        public static function disableScheduler() {
        }
    }

}

namespace Swoole {

    class Runtime {
        public static function enableCoroutine($enable, $flags = null) {
        }
    }

}

//调试日志
namespace debug {
    //WebSocket测试
    <<<'EOF'
        //创建监听服务
        $serv = new Swoole\WebSocket\Server('0.0.0.0', $GLOBALS['system']['port'], SWOOLE_PROCESS);
        //启动工作并开启协程
        $serv->set(array('enable_coroutine' => true, 'open_websocket_close_frame' => true) + $GLOBALS['server']);

        //接收WebSocket
        $serv->on('open', function ($serv, $requ) {
            echo 'open: ' . print_r($requ, true) . "\n";
        });
        //接收WebSocket
        $serv->on('message', function ($serv, $frame) {
            echo 'message: ' . print_r($frame, true) . "\n";
        });
EOF;
}