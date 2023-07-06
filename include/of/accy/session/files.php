<?php
/**
 * 描述 : 磁盘方式存储session
 * 作者 : Edgar.lee
 */
class of_accy_session_files extends of_base_session_base {
    //加锁的文件路径源
    private static $fpl = null;

    protected static function _read(&$sessionId, &$data) {
        //session存储路径
        $temp = of::config('_of.session.params.path', OF_DATA . '/_of/of_accy_session_files', 'dir');
        $temp .= '/' . $sessionId . '.php';

        self::$fpl = of_base_com_disk::file($temp, null, null);
        $data = of_base_com_disk::file(self::$fpl, false, true);
        //修改访问时间
        touch($temp, $_SERVER['REQUEST_TIME']);
    }

    protected static function _write(&$sessionId, &$data) {
        of_base_com_disk::file(self::$fpl, $data, true);
        //连接解锁
        flock(self::$fpl, LOCK_UN);
        fclose(self::$fpl);
    }

    protected static function _destroy(&$sessionId) {
        of_base_com_disk::file(self::$fpl, '');
        //连接解锁
        flock(self::$fpl, LOCK_UN);
        fclose(self::$fpl);
    }

    protected static function _gc($maxlifetime) {
        //SESSION磁盘存储路径
        $dir = of::config('_of.session.params.path', OF_DATA . '/_of/of_accy_session_files', 'dir');
        //过期时间戳
        $timestamp = $_SERVER['REQUEST_TIME'] - $maxlifetime;

        //加锁成功
        if (of_base_com_data::lock('of_accy_session_files::gc', 6)) {
            while (of_base_com_disk::each($dir, $list, true)) {
                foreach ($list as $path => &$isDir) {
                    //清除过期会话
                    if (!$isDir && is_int($temp = fileatime($path)) && $temp < $timestamp) {
                        unlink($path);
                        //移除空文件夹
                        /*while( !glob(($path = dirname($path)) . '/*') ) {
                            rmdir($path);
                        }*/
                    }
                }
            }
            //连接解锁
            of_base_com_data::lock('of_accy_session_files::gc', 3);
        }
    }

    protected static function _open() {
    }
    protected static function _close() {
    }
}