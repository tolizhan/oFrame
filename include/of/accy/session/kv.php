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
        $params['sKey'] = 'of_accy_session_kv::sessionId#' . $sessionId;
        $params['lock'] = $params['sKey'] . '~lock~';
        $timeout = ($timeout = ini_get('max_execution_time')) ? $timeout - 2 : 600;

        //加锁
        if (of_base_com_disk::lock($params['lock'])) {
            //读取
            $data = of_base_com_kv::get($params['sKey'], '', $params['kvPool']);
        }
    }

    protected static function _write(&$sessionId, &$data, $maxLifeTime) {
        $params = &self::$params;

        //回写数据
        of_base_com_kv::set($params['sKey'], $data, $maxLifeTime, $params['kvPool']);
    }

    protected static function _destroy(&$sessionId) {
        $params = &self::$params;

        //销毁数据
        of_base_com_kv::del($params['sKey'], $params['kvPool']);
    }

    protected static function _close() {
        //解锁通道
        of_base_com_disk::lock(self::$params['lock'], LOCK_UN);
    }

    protected static function _gc() {
    }
}
of_accy_session_kv::_init();