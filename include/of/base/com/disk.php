<?php
/**
 * 描述 : 提供磁盘相关封装
 * 作者 : Edgar.lee
 */
class of_base_com_disk {

    /**
     * 描述 : 从一个文件读取或写入数据
     * 参数 :
     *      filePath  : 文件路径,或已经加锁的数据流
     *      data      : 文件数据,false(默认)=读取数据,true=会用unserialize转化,null=返回文件链接源,字符串=写入数据,数组=会用serialize转化
     *      protected : 默认=false, 
     *          数据流 : true=写入锁,false=读取锁,null=尾部写入锁
     *          写入时 : true=向写入的字符串前追加"<?php exit; ?> "15个字符,false=不追加,null=尾部写入
     *          读取时 : true=文件已写入"<?php exit; ?> "保护, false=文件没写入保护
     * 返回 : 
     *      失败返回null,读取成功返回读取数据,写入成功返回true
     * 作者 : Edgar.lee
     */
    public static function &file($filePath, $data = false, $protected = false) {
        //缓存路径
        static $cachePath = null;

        if (is_resource($filePath)) {
            $fp = &$filePath;
        } else {
            //引用文件流
            $fp = &$cachePath[$filePath];

            //防死锁
            isset($fp) && is_resource($fp) && flock($fp, LOCK_UN) && fclose($fp);
            //嵌套创建文件夹
            is_dir($temp = dirname($filePath)) || @mkdir($temp, 0777, true);
            //读写方式打开
            if ($fp = fopen($filePath, 'a+')) {
                //读取文件 || 获得只读源
                if (is_bool($data) || ($data === null && $protected === false)) {
                    //加共享锁
                    flock($fp, LOCK_SH);
                //写入文件 || 获取写入源
                } else {
                    //加独享锁
                    flock($fp, LOCK_EX);
                }
            //打开失败
            } else {
                return $result;
            }
        }

        //返回文件源
        if ($data === null) {
            //追加写入
            if ($protected === null) {
                //移动到最后
                fseek($fp, 0, SEEK_END);
            } else {
                //写入 ? 清空 : 移动
                $protected ? ftruncate($fp, 0) : fseek($fp, 0);
            }
            return $fp;
        //读取数据
        } elseif (is_bool($data)) {
            $temp = array();
            //判断是否跳过保护
            fseek($fp, $protected ? 15 : 0);
            //读取数据
            while (!feof($fp)) {
                $temp[] = fread($fp, 8192);
            }
            $temp = join($temp);
            //反序列化
            $data && $temp = unserialize($temp);
            is_string($filePath) && fclose($fp);
            return $temp;
        //写入数据
        } else {
            //追加写入
            if ($protected === null) {
                //移动到最后
                fseek($fp, 0, SEEK_END);
            //常规写入
            } else {
                ftruncate($fp, 0);
                //写入保护
                $protected && fwrite($fp, '<?php exit; ?> ');
            }
            //写入数据
            $result = is_int(fwrite($fp, is_string($data) ? $data : serialize($data)));
            //防止网络磁盘掉包
            fseek($fp, -1, SEEK_CUR) || fread($fp, 1);
            is_string($filePath) && fclose($fp);
            return $result;
        }
    }

    /**
     * 描述 : 为并发流程创建独占通道
     * 参数 :
     *      name : 锁通道标识
     *      lock : 文件加锁方式 LOCK_EX, LOCK_SH, LOCK_UN, LOCK_NB
     * 返回 :
     *      true=成功, false=失败
     * 作者 : Edgar.lee
     */
    public static function lock($name, $lock = LOCK_EX) {
        static $data = null;

        //加锁文件名
        $file = md5($name);
        //初始化结构
        if ($data === null) {
            //通道路径
            $data['path'] = ROOT_DIR . OF_DATA . '/_of/of_base_com_disk/lock';
            //创建路径
            is_dir($data['path']) || @mkdir($data['path'], 0777, true);
        }

        //垃圾回收
        if (rand(0, 99) === 1) {
            //一分钟前时间戳
            $timestamp = time() - 60;

            //打开加锁文件
            $lfp = fopen($path = $data['path'] . '/lock.gc', 'w');
            //加锁成功
            if (flock($lfp, LOCK_EX | LOCK_NB)) {
                of_base_com_disk::each($data['path'], $list, null);
                foreach ($list as $path => &$isDir) {
                    if (
                        !$isDir && 
                        filemtime($path) < $timestamp &&
                        flock(fopen($path, 'a'), LOCK_EX | LOCK_NB)
                    ) {
                        //清除过期锁通道
                        @unlink($path);
                    }
                }
                //连接解锁
                flock($lfp, LOCK_UN);
            }
            //关闭连接
            fclose($lfp);
        }

        //初始化连接
        ($index = &$data['flie'][$file]) || $index = fopen(
            $data['path'] . '/' . $file, 'w'
        );
        //解锁操作
        if ($lock === LOCK_UN) unset($data['flie'][$file]);
        //加锁操作
        return flock($index, $lock);
    }

    /**
     * 描述 : 遍历目录结构
     * 参数 :
     *     &dir    : 字符串=指定遍历的目录
     *     &data   : 接收的数据, 数组={
     *          磁盘路 : false=文件,true=目录,如果遍历data时将目录其改成false,那么将不会继续遍历
     *      }
     *      single : 遍历方式
     *          true =(默认)每次返回一个文件夹数据
     *          false=一次返回深层数据
     *          null =返回指定子目录不影响已有遍历
     * 返回 :
     *      成功返回true,失败返回false(并结束遍历)
     * 作者 : Edgar.lee
     */
    public static function each(&$dir, &$data, $single = true) {
        static $cahceDir = array();

        //返回指定子目录
        if ($single === null || ($nowDir = &$cahceDir[$dir]) === null) {
            $nowDir = $data = null;
        }

        //读取数据
        if ($data !== false) {
            //初始化连接
            $nowDir === null && $nowDir[$dir] = true;
            //开始读取目录
            $data = false;

            while ($path = key($nowDir)) {
                $index = &$nowDir[$path];
                unset($nowDir[$path]);

                //目录被排除 || 目录不存在
                if ($index === null || !file_exists($path)) {
                    continue;
                //打开目录
                } else if ($handle = opendir($path)) {
                    while (is_string($fileName = readdir($handle))) {
                        if ($fileName !== '.' && $fileName !== '..') {
                            $fileName = $path .'/'. $fileName;
                            if ($data[$fileName] = is_dir($fileName)) {
                                $nowDir[$fileName] = &$data[$fileName];
                            }
                        }
                    }
                    closedir($handle);
                    //不为深度遍历时跳出
                    if ($single !== false) break;
                //目录打开失败
                } else {
                    $data = false;
                    break;
                }
            }
        }

        //操作结束 && 格式化数组
        ($fail = $data === false) && $data = array();
        //(操作结束 || 深度遍历) && 清除缓存
        if ($fail || $single === false) unset($cahceDir[$dir]);
        //按文件顺序排序
        ksort($data);
        //返回结束信息
        return !$fail;
    }

    /**
     * 描述 : 获取临时路径
     * 参数 :
     *      isFile : 是否生成临时文件,true(默认)=返回临时文件路径,false=返回临时文件夹路径
     * 返回 :
     *      返回路径
     * 作者 : Edgar.lee
     */
    public static function temp($isFile = true) {
        static $tempDir = null;
        if ($tempDir === null) {
            //php > 5.2.1
            if (function_exists('sys_get_temp_dir')) {
                $tempDir = sys_get_temp_dir();
            } else {
                //读取相关环境变量
                ($tempDir = getenv('TMP')) || ($tempDir = getenv('TEMP')) || ($tempDir = getenv('TMPDIR'));

                //环境变量读取失败,尝试创建临时文件
                if (!$tempDir && $tempDir = tempnam(__FILE__, '')) {
                    is_file($tempDir) && unlink($tempDir);
                    $tempDir = dirname($tempDir);
                }
            }
        }

        return strtr($isFile ? tempnam($tempDir, '') : rtrim($tempDir, '\\/'), '\\', '/');
    }

    /**
     * 描述 : 删除指定文件或文件夹
     * 参数 :
     *      sPath : 指定删除路径
     *      clear : 清除父层空文件夹, 默认=false
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function delete($sPath, $clear = false) {
        //最终结果
        $result = true;

        //目录拷贝
        if (is_dir($sPath)) {
            //待处理列表
            $wList = array(array(
                'sPath' => $sPath,
                'opDir' => opendir($sPath),
            ));
            //待处理最大角标
            $count = 0;

            do {
                //读取最后一个数据
                $index = &$wList[$count];

                //遍历源路径
                while ($name = readdir($index['opDir'])) {
                    //不是内置目录
                    if ($name !== '.' && $name !== '..') {
                        //是目录
                        if (is_dir($oPath = "{$index['sPath']}/{$name}")) {
                            //待处理脚本加一
                            $count += 1;
                            //追加到待处理列表
                            $wList[] = array(
                                'sPath' => $oPath,
                                'opDir' => opendir($oPath),
                            );
                            //更换引用为当前目录
                            $index = &$wList[$count];
                        //是文件
                        } else {
                            //删除文件
                            unlink($oPath) || $result = false;
                        }
                    }
                }

                //删除遍历完成的目录
                array_pop($wList);
                closedir($index['opDir']);
                //待处理脚本减一
                $count -= 1;
                //删除空文件夹
                rmdir($index['sPath']) || $result = false;
            } while ($count > -1);
        //文件拷贝
        } else if (is_file($sPath)) {
            //删除文件
            unlink($sPath) || $result = false;
        }

        if ($result && $clear) {
            //移除空文件夹
            while (!glob(($sPath = dirname($sPath)) . '/*')) {
                rmdir($sPath) || $result = false;
            }
        }

        return $result;
    }

    /**
     * 描述 : 复制指定文件或文件夹
     * 参数 :
     *      sPath : 指定源路径
     *      dPath : 指定目标路径
     *      ePath : 排除路径 {
     *          排除路径 => 0,
     *          ...
     *      }
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function copy($sPath, $dPath, $ePath = array()) {
        //最终结果
        $result = true;

        //目录拷贝
        if (is_dir($sPath)) {
            //待处理列表
            $wList = array(array(
                'sPath' => $sPath,
                'dPath' => $dPath,
                'opDir' => opendir($sPath),
                'isDir' => false,
            ));
            //待处理最大角标
            $count = 0;

            do {
                //读取最后一个数据
                $index = &$wList[$count];

                //遍历源路径
                while ($name = readdir($index['opDir'])) {
                    if (
                        //不是内置目录
                        $name !== '.' && $name !== '..' &&
                        //不在排除列表
                        !isset($ePath[$oPath = "{$index['sPath']}/{$name}"])
                    ) {
                        //是目录
                        if (is_dir($oPath)) {
                            //待处理脚本加一
                            $count += 1;
                            //目录无效检查创建
                            $index['isDir'] = true;
                            //追加到待处理列表
                            $wList[] = array(
                                'sPath' => $oPath,
                                'dPath' => "{$index['dPath']}/{$name}",
                                'opDir' => opendir($oPath),
                                'isDir' => false,
                            );
                            //更换引用为当前目录
                            $index = &$wList[$count];
                        //是文件
                        } else {
                            //初始化目录
                            if (!$index['isDir']) {
                                $index['isDir'] = true;
                                is_dir($index['dPath']) || @mkdir($index['dPath'], 0777, true);
                            }
                            //拷贝文件
                            copy($oPath, "{$index['dPath']}/{$name}") || $result = false;
                        }
                    }
                }

                //删除遍历完成的目录
                array_pop($wList);
                //待处理脚本减一
                $count -= 1;
                //拷贝空目录
                $index['isDir'] ||
                    is_dir($index['dPath']) || @mkdir($index['dPath'], 0777, true);
            } while ($count > -1);
        //文件拷贝
        } else if (is_file($sPath)) {
            is_dir($temp = dirname($dPath)) || @mkdir($temp, 0777, true);
            copy($sPath, $dPath) || $result = false;
        }

        return $result;
    }
}