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
     *      data      : 默认=false
     *          数据流 : null=返回文件链接源
     *          写入时 : 字符串=写入数据, 数组=会用serialize转化
     *          读取时 : false=读取数据,true=会用unserialize转化
     *      protected : 默认=false
     *          数据流 : true=写入锁,false=读取锁,null=尾部写入锁
     *          写入时 : true=向写入的字符串前追加"<?php exit; ?> "15个字符,false=不追加,null=尾部写入
     *          读取时 : true=文件已写入"<?php exit; ?> "保护, false=文件没写入保护
     * 返回 : 
     *      数据流 : 成功=资源, 失败=false
     *      写入时 : 成功=true, 失败=false
     *      读取时 : 成功=数据, 失败=null, 反序列失败=异常
     * 作者 : Edgar.lee
     */
    public static function &file($filePath, $data = false, $protected = false) {
        if (is_resource($filePath)) {
            $fp = &$filePath;
        } else {
            //嵌套创建文件夹
            is_dir($temp = dirname($filePath)) || @mkdir($temp, 0777, true);
            //读写方式打开, 不用"a+"是防止有些磁盘"a"写总是追加操作(不受fseek影响)
            if ($fp = fopen($filePath, 'c+')) {
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
                throw new Exception('Failed to open stream: ' . $filePath);
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

            //关闭文件流
            is_string($filePath) && fclose($fp);

            //反序列化
            if ($data) {
                //数据为空
                if (!isset($temp[0])) {
                    $temp = null;
                //为false的序列化
                } else if ($temp === 'b:0;') {
                    $temp = false;
                //反序列化失败抛出异常
                } else if (($temp = unserialize($temp)) === false) {
                    throw new Exception('The passed string is not unserializeable.');
                }
            }

            return $temp;
        //写入数据
        } else {
            //追加写入
            if ($protected === null) {
                //移动到最后
                fseek($fp, 0, SEEK_END);
            //常规写入
            } else {
                //游标移到起始位置, 防止部分磁盘支持超过结尾补"\0"功能
                fseek($fp, 0);
                //清空文件
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
     *      dir    : 字符串=指定遍历的目录
     *     &data   : 接收的目录列表
     *          当type为true时:
     *              在循环遍历时传入false, 则不会继续遍历
     *              将对应目录设置为false, 则不会继续遍历该目录
     *      type : 遍历方式
     *          null =(默认)返回指定目录列表
     *          false=一次返回深层数据
     *          true =循环返回每个目录的数据(单目录大于一万条拆分多次)
     * 返回 :
     *      失败返回false并结束遍历, 成功返回true并将数据存到data中 {
     *          磁盘路 : false=文件, true=目录
     *          ...
     *      }
     * 作者 : Edgar.lee
     */
    public static function each($dir, &$data, $type = null) {
        static $cahceDir = array();
        //指定最大结果集条数
        $maxNum = $type === true ? 10000 : PHP_INT_MAX;

        //返回指定子目录
        if ($type !== true || ($nowDir = &$cahceDir[$dir]) === null) {
            $nowDir = $data = null;
        }

        //读取数据
        if ($data !== false) {
            //初始化连接
            $nowDir === null && $nowDir[$dir] = array('res' => true, 'run' => true);
            //开始读取目录
            $data = null;

            while ($path = key($nowDir)) {
                $index = &$nowDir[$path];

                //目录被排除 || 目录不存在
                if (!$index['run'] || !file_exists($path)) {
                    unset($nowDir[$path]);
                    continue;
                //打开目录
                } else if (is_resource($index['res']) || $index['res'] = opendir($path)) {
                    while (is_string($fileName = readdir($index['res']))) {
                        if ($fileName !== '.' && $fileName !== '..') {
                            //完整的磁盘路径
                            $fileName = $path .'/'. $fileName;
                            //距离待遍历文件夹
                            if ($data[$fileName] = is_dir($fileName)) {
                                $nowDir[$fileName] = array('res' => true, 'run' => &$data[$fileName]);
                            }
                            //达到单次遍历最多数量
                            if (--$maxNum === 0) break 2;
                        }
                    }
                    //遍历结束
                    closedir($index['res']);
                    unset($nowDir[$path]);
                    //(遍历目录 && 存在结果) || 直查目录
                    if (($type === true && $data) || $type === null) break;
                //目录打开失败
                } else {
                    $data = false;
                    unset($nowDir[$path]);
                    break;
                }
            }

            //未查到数据 && 结束遍历
            $data === null && $data = false;
        }

        //操作结束 && 格式化数组
        ($fail = $data === false) && $data = array();
        //(操作结束 && 单层遍历) && 清除缓存
        if ($fail && $type) unset($cahceDir[$dir]);
        //按文件顺序排序
        ksort($data);
        //返回结束信息
        return !$fail;
    }

    /**
     * 描述 : 判断文件夹是否为空
     * 参数 :
     *      path : 磁盘目录
     * 返回 :
     *      true=不存在或空文件夹, false=非空文件夹
     * 作者 : Edgar.lee
     */
    public static function none($path) {
        //结果集
        $result = true;

        //文件夹存在 && 打开成功
        if (is_dir($path) && $hd = opendir($path)) {
            while (is_string($temp = readdir($hd))) {
                if (trim($temp, '.')) {
                    $result = false;
                    break ;
                }
            }
            closedir($hd);
        }

        return $result;
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

                //环境变量读取成功
                if (!$tempDir) {
                    //使用框架临时目录(可能因网络盘效率下降)
                    $tempDir = ROOT_DIR . OF_DATA . '/_of/of_base_com_disk/temp';
                    //创建临时文件夹
                    is_dir($tempDir) || @mkdir($tempDir, 0777, true);
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
                while (is_string($name = readdir($index['opDir']))) {
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
            while (self::none($sPath = dirname($sPath))) {
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
                while (is_string($name = readdir($index['opDir']))) {
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

    /**
     * 描述 : 
     * 参数 :
     *     &name : 锁通道标识, 推荐使用一个"::"分组来提升性能
     *     &lock : 文件加锁方式 1=共享锁, 2=独享锁, 3=解除锁, 4=非堵塞(LOCK_NB)
     *     &nMd5 : 加锁文件标识
     *     &data : 锁资源存储数据
     * 返回 :
     *      true=成功, false=失败
     * 作者 : Edgar.lee
     */
    //abstract public static function _lock(&$name, &$lock, &$nMd5, &$data);
}