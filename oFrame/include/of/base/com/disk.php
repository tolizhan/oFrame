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
     *      data      : 文件数据,false(默认)=读取数据,ture=会用unserialize转化,null=返回文件链接源,字符串=写入数据,数组=会用serialize转化
     *      protected : 默认=false, 
     *          数据流 : true=写入锁,false=读取锁,null=尾部写入锁; 
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
     * 描述 : 遍历目录结构
     * 参数 :
     *     &dir    : 字符串=指定遍历的目录
     *     &data   : 接收的数据,false=结束遍历, 数组={
     *          磁盘路 : false=文件,true=目录,如果遍历data时将目录其改成null,那么将不会继续遍历子
     *      }
     *      single : 单层遍历,false=一次返回深层数据,true(默认)=每次返回一个文件夹数据
     * 返回 :
     *      成功返回true,失败返回false(并结束遍历)
     * 作者 : Edgar.lee
     */
    public static function each(&$dir, &$data, $single = true) {
        static $cahceDir = array();
        ($nowDir = &$cahceDir[$dir]) === null && $data = null;

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
                    if ($single) break;
                //目录打开失败
                } else {
                    $data = false;
                    break;
                }
            }
            //深度读取
            if (!$single) unset($cahceDir[$dir]);
        }

        //结束遍历
        if ($data === false) {
            unset($cahceDir[$dir]);
            return false;
        } else {
            return true;
        }
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
     *      path  : 指定删除路径
     *      clear : 清除父层空文件夹, 默认=false
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function delete($path, $clear = false) {
        $result = false;

        if (is_file($path)) {
            $result = unlink($path);
        } else if (is_dir($path)) {
            if ($dp = opendir($path)) {
                while (($file=readdir($dp)) !== false) {
                    if ($file !== '.' && $file !== '..') {
                        self::delete($path .'/'. $file);
                    }
                }
                closedir($dp);
            }
            $result = rmdir($path);
        }

        if ($clear) {
            //移除空文件夹
            while (!glob(($path = dirname($path)) . '/*')) {
                $result = rmdir($path);
            }
        }

        return $result;
    }

    /**
     * 描述 : 复制指定文件或文件夹
     * 参数 :
     *      source  : 指定源路径
     *      dest    : 指定目标路径
     *      exclude : 排除路径
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function copy($source, $dest, &$exclude = array()) {
        if (is_file($source)) {
            is_dir($isDir = dirname($dest)) || mkdir($isDir, 0777, true);    //创建目录
            return copy($source, $dest);
        } else if (is_dir($source)) {
            is_dir($dest) || mkdir($dest, 0777, true);    //创建目录
            if ($dp = opendir($source)) {
                while (($file=readdir($dp)) != false) {
                    if ($file !== '.' && $file !== '..') {
                        if (!isset($exclude[$temp = "{$source}/{$file}"])) {
                            self::copy("{$source}/{$file}", "{$dest}/{$file}", $exclude);
                        }
                    }
                }
                closedir($dp);
            }
            return true;
        }
    }
}