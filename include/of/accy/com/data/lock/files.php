<?php
/**
 * 描述 : 磁盘锁文件模式
 * 作者 : Edgar.lee
 */
class of_accy_com_data_lock_files {
    /**
     * 描述 : 
     * 参数 :
     *      name : 锁通道标识
     *      lock : 文件加锁方式 1=共享锁, 2=独享锁, 3=解除锁, 4=非堵塞(LOCK_NB)
     *      nMd5 : 加锁文件标识
     *     &data : 锁资源存储数据
     * 返回 :
     *      true=成功, false=失败
     * 作者 : Edgar.lee
     */
    public static function _lock($name, $lock, $nMd5, &$data) {
        static $config = null;

        //垃圾回收
        rand(0, 9999) === 1 && of_base_com_timer::task(array(
            'call' => 'of_accy_com_data_lock_files::_lockGc',
            'cNum' => 1
        ));

        //连接未初始化
        if (!isset($data['lock'])) {
            //初始化结构
            $config === null && $config = of::config('_of.com.data.lock.params', array()) + array(
                'path' => OF_DATA . '/_of/of_accy_com_data_lock_files',
                'slot' => 1
            );
            //计算加锁路径
            $dir = ROOT_DIR . "{$config['path']}/" . self::getSlot($nMd5, $config['slot']) . "/{$nMd5[0]}{$nMd5[1]}";
            //创建路径
            is_dir($dir) || @mkdir($dir, 0777, true);
            //初始化连接
            $data['lock'] = fopen($dir . '/' . $nMd5, 'c+');
        }

        //锁操作成功 && 为加锁操作
        if (($result = flock($data['lock'], $lock)) && ($lock & 3) < 3) {
            //锁已删除
            if (fgetc($data['lock'])) {
                //重新初始化连接
                $data['lock'] = fopen($dir . '/' . $nMd5, 'c+');
                //重新加锁
                $result = flock($data['lock'], $lock);
            }
        }

        //更新修改时间
        ftruncate($data['lock'], 0);
        return $result;
    }

    /**
     * 描述 : lock辅助方法, 异步回收数据
     * 作者 : Edgar.lee
     */
    public static function _lockGc() {
        //是否为windos系统
        $isWin = strstr(PHP_OS, 'WIN');
        //删除是否需要加锁
        $noLock = $isWin && version_compare(PHP_VERSION, '7.3.0', '<');
        //清理目录
        $cPath = ROOT_DIR . of::config('_of.com.data.lock.params.path', OF_DATA . '/_of/of_accy_com_data_lock_files');
        //安装信号触发器
        of_base_com_timer::exitSignal();

        while (!of_base_com_timer::renew()) {
            //十分钟前时间戳
            $timestamp = time() - 600;

            while (of_base_com_disk::each($cPath, $list, true)) {
                foreach ($list as $path => &$isDir) {
                    //是文件 && 一段时间未操作
                    if (!$isDir && filemtime($path) < $timestamp) {
                        //windows不支持异步删除的版本
                        if ($noLock) {
                            //无加锁 && 删除过期锁, 同步删除有打开连接的锁会报错
                            flock(fopen($path, 'a'), LOCK_EX | LOCK_NB) && @unlink($path);
                        //支持异步删除 && 尝试加锁成功
                        } else if (flock($fp = fopen($path, 'a'), LOCK_EX | LOCK_NB)) {
                            //windows环境php >= 7.3 打开已删除文件报错"无权限"问题
                            $isWin && rename($path, $path .= '_');
                            //清除过期锁
                            unlink($path);
                            //标记已删除, 异步删除时可能其它待加锁的连接已打开
                            fwrite($fp, '1');
                        }
                        //解锁并关闭连接
                        unset($fp);
                    }
                }
            }

            //检查退出信号
            of_base_com_timer::exitSignal();
            //5分钟后继续
            sleep(300);
            //检查退出信号
            of_base_com_timer::exitSignal();
        }
    }

    /**
     * 描述 : 获取加锁分区
     * 作者 : Edgar.lee
     */
    private static function &getSlot(&$nMd5, &$total) {
        //计算分槽归属, 公式: hash(i) = hash(i-1) << 5(33) + ord(str[i])
        $slot = 5381;
        //md5目的均匀散列, <<5防止叠加干扰(2+1=1+2), 0x7FFFFFFF使32与64位结果相同
        for ($i = 0; $i < 32; ++$i) $slot += ($slot << 5 & 0x7FFFFFFF) + ord($nMd5[$i]);
        //取模, 0x7FFFFFFF为32位最大值
        $slot = ($slot & 0x7FFFFFFF) % $total;

        return $slot;
    }
}