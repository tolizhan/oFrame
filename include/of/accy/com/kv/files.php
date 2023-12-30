<?php
class of_accy_com_kv_files extends of_base_com_kv {
    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        //格式为磁盘路径
        $this->params['path'] = isset($this->params['path']) ?
            ROOT_DIR . $this->params['path'] :
            ROOT_DIR . OF_DATA . '/_of/of_accy_com_kv_files';

        //垃圾回收
        rand(0, 9999) === 1 && $this->_gc();
    }

    /**
     * 描述 : 添加数据
     * 作者 : Edgar.lee
     */
    protected function _add(&$name, &$value, &$time) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //追加写入锁
        $fp = of_base_com_disk::file($path, null, null);

        //不存在 || 失效(会失效 && 已失效)
        if ($result = !ftell($fp) || ($temp = filemtime($path)) && $temp < time()) {
            of_base_com_disk::file($fp, $value, true);
            //修改时间
            touch($path, $time ? $time + time() : 0);
        }

        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);
        return $result;
    }

    /**
     * 描述 : 删除数据
     * 作者 : Edgar.lee
     */
    protected function _del(&$name) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //追加写入锁
        $fp = of_base_com_disk::file($path, null, null);

        //修改时间
        touch($path, $_SERVER['REQUEST_TIME'] - 600);
        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);

        //垃圾回收
        rand(0, 9999) === 1 && $this->_gc();
        return true;
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //追加写入锁
        $fp = of_base_com_disk::file($path, null, null);

        //写入数据
        $result = of_base_com_disk::file($fp, $value, true);
        //修改时间
        touch($path, $time ? $time + time() : 0);

        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);
        //修改时间
        return $result;
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //当前时间戳
        $time = time();
        //打开共享锁
        $fp = of_base_com_disk::file($path, null, false);

        //存在 && 有效(永久有效 || 尚未失效)
        if (
            $result = filesize($path) && 
            (($temp = filemtime($path)) === 0 || $temp >= $time)
        ) {
            $result = of_base_com_disk::file($fp, false, true);
        }

        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);
        return $result;
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link() {
        //返回根路径
        return $this->params['path'];
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
    }

    /**
     * 描述 : 过期回收
     * 作者 : Edgar.lee
     */
    private function _gc() {
        //删除方式, true=直接删除, false=加锁删除
        static $noLock = null;

        if (
            //文件夹存在
            is_dir($dir = $this->params['path']) &&
            //加锁成功
            of_base_com_data::lock('of_accy_com_kv_files::gc', 6)
        ) {
            //windows && php < 7.3 ? 直接删除 : 加锁删除
            $noLock === null && $noLock = strstr(PHP_OS, 'WIN') && version_compare(PHP_VERSION, '7.3.0', '<');
            //过期时间
            $timeout = time() - 300;

            //遍历存储路径
            while (of_base_com_disk::each($dir, $list, true)) {
                foreach ($list as $path => &$isDir) {
                    if (
                        //是文件
                        !$isDir &&
                        //文件不是永久有效
                        ($temp = filemtime($path)) &&
                        //文件已过期
                        $temp < $timeout &&
                        //不用加锁 || 加锁成功
                        ($noLock || flock($fp = fopen($path, 'c+'), LOCK_EX | LOCK_NB))
                    ) {
                        //清除过期会话
                        @unlink($path);
                        //连接解锁
                        $noLock || flock($fp, LOCK_UN);
                    }
                }
            }

            //连接解锁
            of_base_com_data::lock('of_accy_com_kv_files::gc', 3);
        }
    }
}