<?php
/**
 * 描述 : k-v方式存储session
 * 作者 : Edgar.lee
 */
class of_accy_session_kv extends of_base_session_base {
    //连接参数
    private static $params = null;

    protected static function _open() {
        ($params = &self::$params) || $params = of::config('_of.session.params', array()) + array('kvPool' => 'default');
    }

    protected static function _validateId(&$sessionId) {
        self::_open();
        return of_base_com_kv::get("of_accy_session_kv::session#{$sessionId}#mark", false, self::$params['kvPool']);
    }

    protected static function _read(&$sessionId, &$data) {
        $params = &self::$params;
        $params['sKey'] = 'of_accy_session_kv::session#' . $sessionId;
        $params['lock'] = $params['sKey'] . '~lock~';
        $timeout = ($timeout = ini_get('max_execution_time')) ? $timeout - 2 : 600;

        //加锁
        if (of_base_com_data::lock($params['lock'], 2)) {
            //读取
            $data = of_base_com_kv::get($params['sKey'] . '#data', '', $params['kvPool']);
        }
    }

    protected static function _write(&$sessionId, &$data, $maxLifeTime) {
        $params = &self::$params;

        //回写数据
        of_base_com_kv::set($params['sKey'] . '#data', $data, $maxLifeTime, $params['kvPool']);
        //回写标识
        of_base_com_kv::set($params['sKey'] . '#mark', true, $maxLifeTime, $params['kvPool']);
    }

    protected static function _destroy(&$sessionId) {
        $params = &self::$params;

        //销毁数据
        of_base_com_kv::del($params['sKey'] . '#data', $params['kvPool']);
        //销毁标记
        of_base_com_kv::del($params['sKey'] . '#mark', $params['kvPool']);
    }

    protected static function _close() {
        //解锁会话
        of_base_com_data::lock(self::$params['lock'], 3);
    }

    protected static function _gc() {
    }
}