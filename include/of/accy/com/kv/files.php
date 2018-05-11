<?php
class of_accy_com_kv_files extends of_base_com_kv {
    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        //格式为磁盘路径
        $this->params['path'] = isset($this->params['path']) ?
            of::formatPath($this->params['path'], ROOT_DIR) : 
            ROOT_DIR . OF_DATA . '/_of/of_accy_com_kv_files';

        //垃圾回收
        rand(0, 99) === 1 && $this->_gc();
    }

    /**
     * 描述 : 添加数据
     * 作者 : Edgar.lee
     */
    protected function _add(&$name, &$value, &$time) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //追加写入锁
        $fp = of_base_com_disk::file($path, null, null);

        //不存在 || 失效
        if ($result = !ftell($fp) || fileatime($path) < time()) {
            of_base_com_disk::file($fp, (string)$value, true);
            //修改访问时间
            touch($path, $time);
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

        //修改访问时间
        touch($path, 0);
        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);

        //垃圾回收
        $this->_gc();
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
        $result = of_base_com_disk::file($fp, (string)$value, true);

        //连接解锁
        flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);
        //修改访问时间
        return $result && touch($path, $time);
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        $path = $this->params['path'] . '/' . md5($name) . '.php';
        //当前时间戳
        $time = time();
        //追加写入锁
        $fp = of_base_com_disk::file($path, null, null);

        //存在 && 有效(兼容win php < 5.3.0)
        if (
            $result = ftell($fp) && 
            (filemtime($path) >= $time || fileatime($path) >= $time)
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
        $dir = $this->params['path'];
        //当前时间戳
        $timestamp = time();

        //打开加锁文件
        $lock = fopen($path = $dir . '/lock.gc', 'a');
        //修改访问时间
        touch($path, 2147483647);
        //加锁成功
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            while (of_base_com_disk::each($dir, $list)) {
                foreach ($list as $path => &$isDir) {
                    //清除过期会话(兼容win php < 5.3.0)
                    if (
                        !$isDir && 
                        filemtime($path) < $timestamp && 
                        fileatime($path) < $timestamp
                    ) {
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
}