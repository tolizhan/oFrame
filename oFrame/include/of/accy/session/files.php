<?php
/**
 * 描述 : 磁盘方式存储session
 * 作者 : Edgar.lee
 */
class of_accy_session_files extends of_base_session_base {
    //加锁的文件路径源
    private static $fpl = null;

    protected static function _read(&$sessionId, &$data) {
        ($temp = of::config('_of.session.params.path', OF_DATA . '/_of/of_accy_session_files', 'dir')) || 
            $temp = ROOT_DIR . OF_DATA . '/_of/of_accy_session_files';
        //session存储路径
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
        ($dir = of::config('_of.session.params.path', OF_DATA . '/_of/of_accy_session_files', 'dir')) === null &&
        $dir = ROOT_DIR . OF_DATA . '/_of/of_accy_session_files';
        //过期时间戳
        $timestamp = $_SERVER['REQUEST_TIME'] - $maxlifetime;

        //打开加锁文件
        $lock = fopen($path = $dir . '/lock.gc', 'a');
        //修改访问时间
        touch($path, $_SERVER['REQUEST_TIME']);
        //加锁成功
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            while (of_base_com_disk::each($dir, $list)) {
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
            flock($lock, LOCK_UN);
        }

        //关闭连接
        fclose($lock);
    }

    protected static function _open() {
    }
    protected static function _close() {
    }
}