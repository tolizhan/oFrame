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
        ini_set('memory_limit', '1024M');
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
                    } else if (substr($fileName, 2, strlen($type)) === $type) {
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
        //日志根目录
        $rPath = self::$config[substr($path, -2) === 'js' ? 'js' : substr($path, -3)] . $path;
        $data = array();

        if (is_file($temp = $rPath . 'Data.php')) {
            //当前行
            $line = 0;
            //打开日志数据读流
            $fpData = fopen($temp, 'r');

            //读取总日志条数
            if ($curPage === null) {
                //通过索引计算总长度
                if (is_file($temp = $rPath . 'Attr/index.bin')) {
                    $data = floor(filesize($temp) / 8);
                    return $data;
                //使用原始方式
                } else {
                    $data = &$line;
                }
            //读取日志数据
            } else {
                //开始条数
                $curPage = abs(($curPage - 1) * $pageSize);
                //结束条数
                $curSize = $curPage + $pageSize;

                //通过索引读取日志
                if (is_file($temp = $rPath . 'Attr/index.bin')) {
                    //打开日志索引读流
                    $fpIndex = fopen($temp, 'r');
                    //跳转到指定行数
                    fseek($fpIndex, $curPage * 8);
                    //读取指定行数的索引信息
                    $data = str_split(fread($fpIndex, $pageSize * 8), 8);

                    //读取具体日志数据
                    foreach ($data as $k => &$v) {
                        //定位日志数据偏移量(36=>10进制)
                        fseek($fpData, base_convert($v, 36, 10));
                        //读取一行数据并反序列化
                        $v = @unserialize(strtr(fgets($fpData), array("+\1+" => "\n")));
                        //仅读取正确日志
                        if (!$v) unset($data[$k]);
                    }
                    return $data;
                }
            }

            //跳过文件保护
            fseek($fpData, 15);
            //当无索引文件时通过日志数据进行计数及读取数据
            while ($curPage === null || $curSize > $line) {
                if ($temp = fgets($fpData)) {
                    if ($curPage !== null && $curPage <= $line && $curSize > $line) {
                        $data[$line] = @unserialize(strtr($temp, array("+\1+" => "\n")));
                        //仅读取正确日志
                        if (!$data[$line]) unset($data[$line]);
                    }
                    $line += 1;
                } else {
                    break;
                }
            }
        } else {
            $data = self::msg('文件不存在');
        }

        return $data;
    }

    /**
     * 描述 : 读取文件组
     * 参数 :
     *      path : 日志路径
     *      page : 指定页面
     *      size : 页面数量
     *      md5  : 分组键值, 指定时获取对于的分组键的明细
     * 返回 :
     *      {"count" : 日志总数量, "pList" : 指定分页数据}
     * 作者 : Edgar.lee
     */
    public static function &fileG($path, $page, $size, $md5 = '') {
        //日志根目录
        $rPath = self::$config[substr($path, -2) === 'js' ? 'js' : substr($path, -3)] . $path;
        //开始条数
        $curPage = abs(($page - 1) * $size);
        //结果集
        $result = array('count' => 0, 'pList' => array());

        if (is_file($temp = $rPath . 'Data.php')) {
            //打开日志数据读流
            $fpData = fopen($temp, 'r');

            //指定分组明细
            if ($md5) {
                if (is_file($temp = $rPath . "Attr/group/{$md5}.bin")) {
                    $result['count'] = floor(filesize($temp) / 8);
                    //打开日志数据读流
                    $fpList = fopen($temp, 'r');
                    //跳转到指定行数
                    fseek($fpList, $curPage * 8);
                    //读取指定行数的索引信息
                    $data = str_split(fread($fpList, $size * 8), 8);

                    //读取具体日志数据
                    foreach ($data as &$v) {
                        //定位日志数据偏移量(36=>10进制)
                        fseek($fpData, base_convert($v, 36, 10));
                        //读取一行数据并反序列化
                        $v = @unserialize(
                            strtr(fgets($fpData), array("+\1+" => "\n"))
                        );
                        //仅读取正确日志
                        $v && $result['pList'][] = &$v;
                    }
                }
            //获取分组概要
            } else {
                if (is_file($temp = $rPath . 'Attr/group.bin')) {
                    $result['count'] = floor(filesize($temp) / 40);
                    //打开日志数据读流
                    $fpGroup = fopen($temp, 'r');
                    //跳转到指定行数
                    fseek($fpGroup, $curPage * 40);
                    //读取指定行数的索引信息
                    $data = str_split(fread($fpGroup, $size * 40), 40);

                    //读取具体日志数据
                    foreach ($data as &$v) {
                        $v = array(
                            base_convert(substr($v, 0, 8), 36, 10),
                            substr($v, 8)
                        );
                        //定位日志数据偏移量(36=>10进制)
                        fseek($fpData, $v[0]);
                        //读取一行数据并反序列化
                        $log = @unserialize(
                            strtr(fgets($fpData), array("+\1+" => "\n"))
                        );
                        //仅读取正确日志
                        $log && $result['pList'][] = $log + array(
                            'groupMd5Key' => $v[1],
                            'groupCount'  => is_file(
                                $temp = $rPath . 'Attr/group/' . $v[1] . '.bin'
                            ) ? floor(filesize($temp) / 8) : 0
                        );
                    }
                }
            }
        }

        return $result;
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