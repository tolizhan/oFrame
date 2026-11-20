<?php
/**
 * 描述 : 拦截session接口
 * 作者 : Edgar.lee
 */
class of_base_session_base {
    //会话配置文件
    private static $config = null;
    //session状态,开启为2,关闭为1
    private static $status = 1;
    //严格模式所需的会话Id列表, [客户端传的会话ID, 自动创建的会话ID]
    private static $sIds = array('', '');

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    final public static function init($type = true) {
        //引用配置文件
        $config = &self::$config;

        if ($type === true) {
            //初始化配置文件
            $config = of::config('_of.session', array()) + array(
                'adapter'     => 'files',
                'maxLifeTime' => 60,
                'httpOnly'    => true,
                'strictMode'  => true,
                'autoStart'   => ''
            );
            //会话回调类
            $config['callClass'] = 'of_accy_session_' . $config['adapter'];
            //最长过期秒
            $config['maxGcTime'] = $config['maxLifeTime'] * 60;
            //会话名称
            $sName = session_name();
            //自动开启session
            if (ini_get('session.auto_start') === '1') {
                //关闭自启动
                session_write_close();
                //删除默认路径的cookie
                setcookie($sName, '', 1, ini_get('session.cookie_path'));
            }
            //万分之一的机率清理
            ini_set('session.gc_probability', 1);
            //万分之一的机率清理
            ini_set('session.gc_divisor', 9999);
            //设置httponly标识
            ini_set('session.cookie_httponly', $config['httpOnly']);
            //设置严格会话模式
            ini_set('session.use_strict_mode', $config['strictMode']);
            //设置path根路径
            ini_set('session.cookie_path', ROOT_URL . '/');
            //会话对接
            self::handler(version_compare(PHP_VERSION, '7.0.0', '<'));
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

            //读取客户端会话ID, cookie > get > post
            $temp = self::$sIds[0] = ini_get('session.use_cookies') && !empty($_COOKIE[$sName]) ?
                $_COOKIE[$sName] : (ini_get('session.use_only_cookies') ?
                    '' : (empty($_GET[$sName]) ?
                        (empty($_POST[$sName]) ? '' : $_POST[$sName]) :
                        $_GET[$sName]
                    )
                );
            //会话严格模式检测, 请求含会话ID && 会话不存在 && 自定义会话ID
            $config['strictMode'] && $temp && !self::validateId($temp) && session_id(self::createId());
        } else if (
            //有效调度
            $type['check'] !== false &&
            //自动开启
            $config['autoStart'] &&
            //正则验证
            preg_match($config['autoStart'], join('::', of::dispatch()))
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
            $repeat && self::handler(true);
            //php < 5.3 时打些补丁, write提前到spl之前
            $repeat === null &&
                ($repeat = version_compare(PHP_VERSION, '5.3', '<')) &&
                register_shutdown_function('session_write_close');

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
    final public static function handler($type) {
        $type ? session_set_save_handler(
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
        ) : session_set_save_handler(
            //顺序 1
            'of_base_session_base::open',
            //顺序 7
            'of_base_session_base::close',
            //顺序 4
            'of_base_session_base::read',
            //顺序 6
            'of_base_session_base::write',
            //read->destroy->close
            'of_base_session_base::destroy',
            //顺序 5 开启SESSION时调用
            'of_base_session_base::gc',
            //顺序 3 session.use_strict_mode 使用
            'of_base_session_base::createId',
            //顺序 2 session.use_strict_mode 使用
            'of_base_session_base::validateId'
        );
    }

    /**
     * 描述 : 开始session
     * 作者 : Edgar.lee
     */
    final public static function open($savePath, $sessionName) {
        call_user_func(self::$config['callClass'] . '::_open', $savePath, $sessionName);
        self::$status = 3;
        return true;
    }

    /**
     * 描述 : 关闭session
     * 作者 : Edgar.lee
     */
    final public static function close() {
        self::$status > 1 && call_user_func(self::$config['callClass'] . '::_close');
        self::$status = 1;
        return true;
    }

    /**
     * 描述 : 读取session
     * 作者 : Edgar.lee
     */
    final public static function read($sessionId) {
        call_user_func_array(self::$config['callClass'] . '::_read', array(&$sessionId, &$data));
        self::$status = 2;
        return $data;
    }

    /**
     * 描述 : 写入session
     * 作者 : Edgar.lee
     */
    final public static function write($sessionId, $data) {
        call_user_func_array(self::$config['callClass'] . '::_write', array(&$sessionId, &$data, self::$config['maxGcTime']));
        return true;
    }

    /**
     * 描述 : 销毁session
     * 作者 : Edgar.lee
     */
    final public static function destroy($sessionId) {
        call_user_func_array(self::$config['callClass'] . '::_destroy', array(&$sessionId));
        return true;
    }

    /**
     * 描述 : 清理session
     * 作者 : Edgar.lee
     */
    final public static function gc() {
        call_user_func(self::$config['callClass'] . '::_gc', self::$config['maxGcTime']);
        return true;
    }

    /**
     * 描述 : 创建session
     * 作者 : Edgar.lee
     */
    final public static function createId() {
        $temp = function_exists('session_create_id') ? session_create_id() : of_base_com_str::uniqid();
        self::$status === 3 && self::$sIds[1] = $temp;
        return $temp;
    }

    /**
     * 描述 : 验证session
     * 作者 : Edgar.lee
     */
    final public static function validateId($sessionId) {
        $sIds = &self::$sIds;

        //不检测指定的会话ID(不是客户端带的会话ID && 不是自动生成的会话ID) || 检测其它会话ID
        return $sessionId !== $sIds[0] && $sessionId !== $sIds[1] ||
            call_user_func_array(self::$config['callClass'] . '::_validateId', array(&$sessionId));
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
    //验证session
    protected static function _validateId(&$sessionId);
    // */
}

of_base_session_base::init();