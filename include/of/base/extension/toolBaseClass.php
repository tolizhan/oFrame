<?php
class of_base_extension_toolBaseClass {
    /**
     * 描述 : 各功能演示
     * 作者 : Edgar.lee
     */
    public static function test() {
        /* 获取指定目录
        print_r(self::getDir(''));
        // */
        /* 获取文件日志行数
        self::encryptCode();
        // */
    }

    /**
     * 描述 : 加密指定扩展代码
     * 参数 :
     *      eKey : 指定扩展键
     * 返回 :
     *      {目录名 : true为文件夹;false为文件, ...}
     * 作者 : Edgar.lee
     */
    public static function encryptCode($eKey) {
        //扩展根目录
        $eRootDir = ROOT_DIR . of::config('_of.extension.path', OF_DATA . '/extensions');
        $exclude = array(($temp = "{$eRootDir}/{$eKey}/_info/encrypt") => true);
        self::deletePath($temp);
        self::copyPath("{$eRootDir}/{$eKey}", $temp, $exclude);
        //遍历列表
        $eachList = array($temp . '/main' => true);

        do {
            $path = key($eachList);
            $isDir = &$eachList[$path];
            unset($eachList[$path]);

            //分析文件
            if ($isDir === false) {
                if (
                    //扩展名为php
                    pathinfo($path, PATHINFO_EXTENSION) === 'php' &&
                    //未加密
                    strncmp($temp = file_get_contents($path), '<?php', 5) === 0
                ) {
                    $temp = of_base_com_str::rc4('扩展加密密钥', $temp);
                    file_put_contents($path, $temp, LOCK_EX);
                }
            //读取文件夹
            } else if (is_array($temp = self::getDir($path))) {
                $eachList += $temp;
            }
        } while ($eachList);
    }

    /**
     * 描述 : 获取指定目录
     * 参数 :
     *      path : 绝对路径,默认扩展根目录
     *      type : 日志类型,['js', 'php', 'php']
     * 返回 :
     *      {目录名 : true为文件夹;false为文件, ...}
     * 作者 : Edgar.lee
     */
    public static function &getDir($path = null) {
        //文件列表
        $fileList = array();
        //扩展根目录
        $path || $path = ROOT_DIR . of::config('_of.extension.path', OF_DATA . '/extensions');
        if (is_dir($path)) {
            $handle = opendir($path);
            while (($fileName = readdir($handle)) !== false) {
                if ($fileName !== '.' && $fileName !== '..') {
                    $temp = "{$path}/{$fileName}";

                    if (is_dir($temp)) {
                        $fileList[$temp] = true;
                    //有效日志
                    } else {
                        $fileList[$temp] = false;
                    }
                }
            }
            closedir($handle);
            //文件在上,文件夹在下
            krsort($fileList);
        } else {
            $fileList = self::msg('路径无效');
        }
        return $fileList;
    }

    /**
     * 描述 : 复制指定文件或文件夹
     * 参数 :
     *      source : 指定源路径
     *      dest   : 指定目标路径
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    private static function copyPath($source, $dest, &$exclude = array()) {
        if (is_file($source)) {
            //创建目录
            is_dir($isDir = dirname($dest)) || mkdir($isDir, 0777, true);
            return copy($source, $dest);
        } else if (is_dir($source)) {
            //创建目录
            is_dir($dest) || mkdir($dest, 0777, true);
            if ($dp = opendir($source)) {
                while (($file=readdir($dp)) !== false) {
                    if ($file !== '.' && $file !== '..' && !isset($exclude[$temp = "{$source}/{$file}"])) {
                        self::copyPath($temp, "{$dest}/{$file}", $exclude);
                    }
                }
                closedir($dp);
            }
            return true;
        }
    }

    /**
     * 描述 : 删除指定文件或文件夹
     * 参数 :
     *      path : 指定删除路径
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    private static function deletePath($path) {
        if (is_file($path)) {
            return unlink($path);
        } else if (is_dir($path)) {
            if ($dp = opendir($path)) {
                while (($file=readdir($dp)) !== false) {
                    if ($file !== '.' && $file !== '..') {
                        self::deletePath($path .'/'. $file);
                    }
                }
                closedir($dp);
            }
            return rmdir($path);
        }
    }

    /**
     * 描述 : 消息回调函数
     * 参数 :
     *      text : 原始消息
     * 返回 :
     *      返回格式化消息
     * 作者 : Edgar.lee
     */
    private static function msg($text) {
        return $text;
    }
}