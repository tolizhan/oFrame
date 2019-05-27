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
        $timeout = ($timeout = ini_get('max_execution_time')) ? $timeout - 2 : 600;

        //加锁
        if (of_base_com_kv::add($params['lock'], $params['rand'], 86400, $params['kvPool'], $timeout)) {
            //记录执行信息
            of_base_com_kv::set($params['sKey'] . '~exec~', array(
                'ctrl' => join('::', of::dispatch()),
                'data' => array(
                    'time'    => &$_SERVER['REQUEST_TIME'],
                    '_GET'    => &$_GET,
                    '_POST'   => &$_POST,
                    '_COOKIE' => &$_COOKIE,
                )
            ), 86400, $params['kvPool']);
            //读取
            $data = of_base_com_kv::get($params['sKey'], '', $params['kvPool']);
        } else {
            trigger_error('Session lock occupancy: ' . print_r(
                of_base_com_kv::get($params['sKey'] . '~exec~', '', $params['kvPool']),
                true
            ));
            exit ;
        }
    }

    protected static function _write(&$sessionId, &$data, $maxLifeTime) {
        $params = &self::$params;

        //回写数据
        of_base_com_kv::set($params['sKey'], $data, $maxLifeTime, $params['kvPool']);

        //加锁状态
        if ($params['rand'] === of_base_com_kv::get($params['lock'], '', $params['kvPool'])) {
            //解锁
            of_base_com_kv::del($params['sKey'] . '~exec~', $params['kvPool']);
            of_base_com_kv::del($params['lock'], $params['kvPool']);
        }
    }

    protected static function _destroy(&$sessionId) {
        $params = &self::$params;

        //销毁数据
        of_base_com_kv::del($params['sKey'], $params['kvPool']);

        //加锁状态
        if ($params['rand'] === of_base_com_kv::get($params['lock'], '', $params['kvPool'])) {
            //解锁
            of_base_com_kv::del($params['sKey'] . '~exec~', $params['kvPool']);
            of_base_com_kv::del($params['lock'], $params['kvPool']);
        }
    }

    protected static function _close() {
    }

    protected static function _gc() {
    }
}
of_accy_session_kv::_init();