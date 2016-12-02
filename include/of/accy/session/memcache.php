<?php
/**
 * 描述 : memcache方式存储session
 * 作者 : Edgar.lee
 */
class of_accy_session_memcache extends of_base_session_base {
    //连接参数
    private static $params = null;
    //memcache
    private static $memcache = null;

    public static function _init() {
        self::$params = of::config('_of.session.params');
        isset(self::$params[0]) || self::$params = array(self::$params);
    }

    protected static function _open() {
        self::$memcache = $memcache = new Memcache;
        foreach(self::$params as &$v) {
            $memcache->addServer($v['host'], $v['port']);
        }
    }

    protected static function _read(&$sessionId, &$data) {
        //加锁
        while( !self::$memcache->add($sessionId . '_LOCK', '', false, 0) ) {
            usleep(200);
        }
        $data = self::$memcache->get($sessionId);
        $data === false && $data = '';
    }

    protected static function _write(&$sessionId, &$data, $maxLifeTime) {
        self::$memcache->set($sessionId, $data, false, $maxLifeTime);
        //解锁
        self::$memcache->delete($sessionId . '_LOCK');
    }

    protected static function _destroy(&$sessionId) {
        self::$memcache->delete($sessionId);
    }

    protected static function _close() {
        self::$memcache->close();
    }

    protected static function _gc() {}
}
of_accy_session_memcache::_init();