<?php
class of_base_test_toolBaseClass {
    //配置文件
    private static $config = null;

    /**
     * 描述 : 初始化
     * 参数 :
     *      config : 配置文件
     * 作者 : Edgar.lee
     */
    public static function init($config = array()) {
        if (self::$config['cPath'] = of::config('_of.test.cPath')) {
            self::$config['cPath'] = ROOT_DIR . self::$config['cPath'];
        }
    }

    /**
     * 描述 : 启动用例库
     * 作者 : Edgar.lee
     */
    public static function fire() {
        $debug = of::config('_of.debug');

        //(开发模式 || 测试模式) && 启动测试
        if (($debug === true || $debug === null) && self::$config['cPath']) {
            of_base_com_timer::task(array(
                'call' => 'of_base_test_case::task',
                'cNum' => 1
            ));
            return true;
        } else {
            return false;
        }
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
        //self::getStories(array('page' => 1, 'size' => 10));
        //exit;
    }

    /**
     * 描述 : 获取用列集合清单
     * 作者 : Edgar.lee
     */
    public static function getStories(&$params, &$search = '') {
        //用例清单
        of_base_com_disk::each(self::$config['cPath'], $data, false);

        if (isset($params['page'])) {
            //根目录长度
            $rLen = strlen(ROOT_DIR);
            //有效清单
            $list = array();

            //整理
            foreach ($data as $k => &$v) {
                //跳过文件夹
                if ($v) continue;
                //加载配置
                $temp = include $k;
                //相对路径
                $k = substr($k, $rLen);

                //筛选信息 && (匹配路径 || 匹配标题)
                if (
                    !$search ||
                    (is_int(stripos($k, $search)) || is_int(stripos($temp['title'], $search)))
                ) {
                    $list[$k] = $temp;
                }
            }

            //截取分页数据
            $data = array_slice($list, ($params['page'] - 1) * $params['size'], $params['size']);
            $list = array();

            //获取配置信息
            foreach ($data as $k => &$v) {
                $v += self::setStoryInfo($k);
                $list[] = &$v;
                $v['info'] = "错误: {$v['info']['error']}/{$v['info']['done']}";
                unset($v['cases']);
            }

            return array('items' => count($list), 'data' => &$list);
        } else {
            return array('items' => count($data), 'data' => array());
        }
    }

    /**
     * 描述 : 更新用例信息
     * 作者 : Edgar.lee
     */
    public static function setStoryInfo($path, $mode = -1) {
        //用例清单分组标识
        $iMd5 = md5($path);
        //用例库配置信息路径
        $file = ROOT_DIR . OF_DATA . '/_of/of_base_test/info/' . $iMd5 . '.php';
        //读取配置信息
        if (is_file($file)) {
            //加锁读取配置信息
            $mode > -1 && $file = of_base_com_disk::file($file, null, null);
            $info = of_base_com_disk::file($file, true, true);

            //用例案例缓存键名
            $cKey = "of_base_test_case::casesProgress.{$iMd5}";
            //读取进度缓存
            if ($temp = of_base_com_kv::get($cKey, null, '_ofSelf')) {
                $info['exec'] = $temp['exec'];
                $info['count'] = $temp['count'];
            }
        } else {
            $info = array(
                //对应配置的路径
                'path' => $path,
                //配置状态(0=手动, 1=循环, 2=运行, 4=暂停, 0=停止)
                'mode' => 0,
                //开始时间
                'start' => '0000-00-00 00:00:00',
                //完成时间
                'done' => '0000-00-00 00:00:00',
                //执行进度(用例列表角标)
                'exec' => 0,
                //总用例量
                'count' => 0,
                //最后一次执行信息
                'info'  => array('done' => 0, 'error' => 0)
            );
        }
        //合并修改数据
        if ($mode > -1) {
            //测试案例从(暂停, 停止) => 启动
            if (($info['mode'] & 2) === 0 && $mode & 2) {
                $info['start'] = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
            }
            //更新状态
            $info['mode'] = $mode;

            //回写配置
            of_base_com_disk::file($file, $info, true);
            //关闭句柄
            $file = null;
            //触发计划任务
            $mode & 2 && self::fire();
        }
        //返回配置
        return $info;
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
    public static function &getDir($path) {
        //文件列表
        $fileList = array();
        //语言包根目录
        $lRootDir = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs';

        if (is_dir($temp = $lRootDir . $path)) {
            $handle = opendir($temp);
            while (($fileName = readdir($handle)) !== false) {
                if ($fileName !== '.' && $fileName !== '..') {
                    $temp = "{$path}/{$fileName}";

                    if (is_dir($lRootDir . $temp)) {
                        $fileList[$temp] = true;
                    //有效日志
                    } else if (substr($fileName, 2, 3) === 'log') {
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
        $rPath = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs' . $path;
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
                        $v = @unserialize(strtr(fgets($fpData), array(
                            "+\1+" => "\n",
                            "+\2+" => "\r",
                        )));
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
                        $data[$line] = @unserialize(strtr($temp, array(
                            "+\1+" => "\n",
                            "+\2+" => "\r",
                        )));
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
        $rPath = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs' . $path;
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
                            strtr(fgets($fpData), array(
                                "+\1+" => "\n",
                                "+\2+" => "\r",
                            ))
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
                            strtr(fgets($fpData), array(
                                "+\1+" => "\n",
                                "+\2+" => "\r",
                            ))
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
     * 描述 : 清空分组明细列表
     * 参数 :
     *      path : 日志路径
     *      md5  : 分组键值, 指定时获取对于的分组键的明细
     * 作者 : Edgar.lee
     */
    public static function emptyGroupDetails($path, $md5) {
        //分组明细列表
        $mPath = ROOT_DIR . OF_DATA . '/_of/of_base_test/logs' . $path .
            'Attr/group/' . $md5 . '.bin';
        //清空分组索引文件
        of_base_com_disk::file($mPath, '');
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

of_base_test_toolBaseClass::init();