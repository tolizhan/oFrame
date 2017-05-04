<?php
/**
 * 描述 : k-v方式存储session
 * 作者 : Edgar.lee
 */
class of_accy_session_kv extends of_base_session_base {
    //连接参数
    private static $params = null;

    public static function _init() {
        self::$params = of::config('_of.session.params', array());
        self::$params += array('kvPool' => 'default');
    }

    protected static function _open() {
    }

    protected static function _read(&$sessionId, &$data) {
        $params = &self::$params;
        $params['rand'] = mt_rand();
        $params['sKey'] = 'of_accy_session_kv::sessionId#' . $sessionId;
        $params['lock'] = $params['sKey'] . '~lock~';

        //加锁
        if (of_base_com_kv::add($params['lock'], $params['rand'], 1800, $params['kvPool'], 600)) {
            //读取
            $data = of_base_com_kv::get($params['sKey'], '', $params['kvPool']);
        } else {
            trigger_error('Session lock failed.');
            exit ;
        }
    }

    protected static function _write(&$sessionId, &$data, $maxLifeTime) {
        $params = &self::$params;

        //加锁状态
        if ($params['rand'] === of_base_com_kv::get($params['lock'], '', $params['kvPool'])) {
            of_base_com_kv::set($params['sKey'], $data, $maxLifeTime, $params['kvPool']);
            //解锁
            of_base_com_kv::del($params['lock'], $params['kvPool']);
        } else {
            trigger_error('Session write failed.');
        }
    }

    protected static function _destroy(&$sessionId) {
        $params = &self::$params;

        //加锁状态
        if ($params['rand'] === of_base_com_kv::get($params['lock'], '', $params['kvPool'])) {
            of_base_com_kv::del($params['sKey'], $params['kvPool']);
            //解锁
            of_base_com_kv::del($params['lock'], $params['kvPool']);
        } else {
            trigger_error('Session destroy failed.');
        }
    }

    protected static function _close() {
    }

    protected static function _gc() {
    }
}
of_accy_session_kv::_init();