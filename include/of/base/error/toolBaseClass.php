<?php
class of_base_error_toolBaseClass {
    private static $config            = null;       //配置文件

    /**
     * 描述 : 初始化
     * 参数 :
     *      config : 配置文件
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public static function init($config = array()) {
        self::$config = &$config;
        if (class_exists('of')) {
            $config += array(
                'sql' => ROOT_DIR . of::config('_of.error.sqlLog', OF_DATA. '/error/sqlLog'),
                'php' => ROOT_DIR . of::config('_of.error.phpLog', OF_DATA. '/error/phpLog'),
                'js' => ROOT_DIR . of::config('_of.error.jsLog', OF_DATA. '/error/jsLog'),
            );
        } else {
            $temp = dirname(__FILE__) . '/error';
            $config += array(
                'sql' => $temp,
                'php' => $temp,
                'js' => $temp,
            );
        }

        ini_set('max_execution_time', 0);
        //self::test();    //演示方法
    }

    /**
     * 描述 : 各功能演示
     * 作者 : Edgar.lee
     */
    public static function test() {
        /* 获取指定目录
        print_r(self::getDir('', 'js'));
        // */
        /* 获取文件日志行数
        print_r(self::fileS('/2013/02/26php'));
        // */
        /* 获取日志段
        echo "<pre>";
        //print_r(self::fileS('/2013/02/26php', 1, 10));    //php 日志
        //print_r(self::fileS('/2013/02/26sql', 1, 1));     //sql 日志
        //print_r(self::fileS('/2013/02/26js', 1, 2));      //js  日志
        echo "</pre>";
        // */
    }

    /**
     * 描述 : 获取指定目录
     * 参数 :
     *      path : 相对lRootDir的子目录,默认''
     *      type : 日志类型,['js', 'php', 'php']
     * 返回 :
     *      {目录名 : true为文件夹;false为文件, ...}
     * 作者 : Edgar.lee
     */
    public static function &getDir($path, $type) {
        //文件列表
        $fileList = array();
        //语言包根目录
        $lRootDir = &self::$config[$type];

        if (is_dir($temp = $lRootDir . $path)) {
            $handle = opendir($temp);
            while (($fileName = readdir($handle)) !== false) {
                if ($fileName !== '.' && $fileName !== '..') {
                    $temp = "{$path}/{$fileName}";

                    if (is_dir($lRootDir . $temp)) {
                        $fileList[$temp] = true;
                    //有效日志
                    } else if (substr($temp, -strlen($type)) === $type) {
                        $fileList[$temp] = false;
                    }
                }
            }
            closedir($handle);
            krsort($fileList);    //文件在上,文件夹在下
        } else {
            $fileList = self::msg('路径无效');
        }
        return $fileList;
    }

    /**
     * 描述 : 读取文件段
     * 参数 :
     *      path     : 指定文件路径
     *      curPage  : 当前页数,1为第一页
     *      pageSize : 每页数量,默认10条
     * 返回 :
     *      读取时,成功返回一个数组,失败返回错误内容
     * 作者 : Edgar.lee
     */
    public static function &fileS($path, $curPage = null, $pageSize = 10) {
        //语言包根目录
        $filePath = self::$config[substr($path, -2) === 'js' ? 'js' : substr($path, -3)] . $path;
        $data = array();

        if (is_file($filePath)) {
            //当前行
            $line = 0;
            //打开读写流
            $fp = fopen($filePath, 'r');

            if ($curPage === null) {
                $data = &$line;
            } else {
                $curPage = abs(($curPage - 1) * $pageSize);
                $curSize = $curPage + $pageSize;
            }

            while ($curPage === null || $curSize > $line) {
                if ($temp = fgets($fp)) {
                    if ($curPage !== null && $curPage <= $line && $curSize > $line) {
                        $data[$line] = unserialize($temp);
                    }
                    $line += 1;
                } else {
                    break;
                }
            }
            fclose($fp);
        } else {
            $data = self::msg('文件不存在');
        }

        return $data;
    }

    /**
     * 描述 : 复制指定文件或文件夹
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