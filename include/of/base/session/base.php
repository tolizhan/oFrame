<?php
/**
 * 描述 : 拦截session接口
 * 作者 : Edgar.lee
 */
class of_base_session_base {
    //session调度类
    private static $adapterClass = null;
    //session状态,开启为2,关闭为1
    private static $status = 1;
    //session最大生存时间小时
    private static $maxLifeTime = null;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    final public static function init($type = true) {
        if ($type === true) {
            //session类
            self::$adapterClass = 'of_accy_session_' . of::config('_of.session.adapter', 'files');
            //session最大生存时间(秒)
            self::$maxLifeTime = of::config('_of.session.maxLifeTime', 60) * 60;
            //自动开启session
            if (ini_get('session.auto_start') === '1') {
                //关闭自启动
                session_write_close();
                //删除默认路径的cookie
                setcookie(session_name(), null, null, ini_get('session.cookie_path'));
            }
            //万分之一的机率清理
            ini_set('session.gc_probability', 1);
            //万分之一的机率清理
            ini_set('session.gc_divisor', 9999);
            //设置httponly标识
            ini_set('session.cookie_httponly', of::config('_of.session.httpOnly', true));
            //设置path根路径
            ini_set('session.cookie_path', ROOT_URL . '/');
            //会话对接
            self::handler();
            //注册L类的session控制
            of::link('session', '$type = true', 'of_base_session_base::control($type);');
            //注入调度自动开启会话
            of::event('of::dispatch', 'of_base_session_base::init');

            //初始化session状态
            if (!function_exists('session_status')) {
                function session_status(&$init = null) {
                    static $static = null;
                    $static === null && $static['status'] = &$init;
                    return $static['status'];
                }

                session_status(self::$status);
            }
        } else if (
            //有效调度
            $type['check'] !== false &&
            //自动开启
            ($temp = of::config('_of.session.autoStart')) &&
            //正则验证
            preg_match($temp, join('::', of::dispatch()))
        ) {
            //自动开启session
            self::control(true);
        }
    }

    /**
     * 描述 : 会话开启与关闭
     * 参数 :
     *      type : true=开启, false=关闭
     * 作者 : Edgar.lee
     */
    final public static function control($type) {
        static $repeat = null;

        //开启
        if ($type) {
            //php < 5.3 每次开启均加载 handler, 第一次init已初始过handler不调用
            $repeat && self::handler();
            //php < 5.3 ?
            $repeat === null && $repeat = version_compare(PHP_VERSION, '5.3', '<');

            //已开启 || 开启会话
            self::$status === 2 || session_start();
        //关闭
        } else {
            session_write_close();
        }
    }

    /**
     * 描述 : 自定义会话函数
     * 作者 : Edgar.lee
     */
    final public static function handler() {
        session_set_save_handler(
            //顺序 1
            'of_base_session_base::open', 
            //顺序 5
            'of_base_session_base::close', 
            //顺序 2
            'of_base_session_base::read', 
            //顺序 4
            'of_base_session_base::write', 
            //read->destroy->close
            'of_base_session_base::destroy', 
            //顺序 3 开启SESSION时调用
            'of_base_session_base::gc'
        );
    }

    /**
     * 描述 : 开始session
     * 作者 : Edgar.lee
     */
    final public static function open($savePath, $sessionName) {
        self::$status = 2;
        call_user_func(self::$adapterClass . '::_open', $savePath, $sessionName);
        return true;
    }

    /**
     * 描述 : 关闭session
     * 作者 : Edgar.lee
     */
    final public static function close() {
        self::$status > 1 && call_user_func(self::$adapterClass . '::_close');
        self::$status = 1;
        return true;
    }

    /**
     * 描述 : 读取session
     * 作者 : Edgar.lee
     */
    final public static function read($sessionId) {
        call_user_func_array(self::$adapterClass . '::_read', array(&$sessionId, &$data));
        return $data;
    }

    /**
     * 描述 : 写入session
     * 作者 : Edgar.lee
     */
    final public static function write($sessionId, $data) {
        call_user_func_array(self::$adapterClass . '::_write', array(&$sessionId, &$data, self::$maxLifeTime));
        return true;
    }

    /**
     * 描述 : 销毁session
     * 作者 : Edgar.lee
     */
    final public static function destroy($sessionId) {
        call_user_func_array(self::$adapterClass . '::_destroy', array(&$sessionId));
        return true;
    }

    /**
     * 描述 : 销毁session
     * 作者 : Edgar.lee
     */
    final public static function gc() {
        call_user_func(self::$adapterClass . '::_gc', self::$maxLifeTime);
        return true;
    }

    /* '/of/com/session/xx.php' 文件继承并实现以下方法
    //开启session,仅写加锁部分
    protected static function _open();
    //关闭连接源
    protected static function _close();
    //读取session,返回字符串
    protected static function _read(&$sessionId, &$data);
    //写入session, $maxLifeTime 最大生命周期
    protected static function _write(&$sessionId, &$data, $maxLifeTime);
    //清除session
    protected static function _destroy(&$sessionId);
    //清理过期session
    protected static function _gc($maxlifetime);
    // */
}

of_base_session_base::init();