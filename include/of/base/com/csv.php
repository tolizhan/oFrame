<?php
/**
 * 描述 : csv 导入导出
 * 作者 : Edgar.lee
 */
class of_base_com_csv {
    //csv文件数据数组
    public static $fileArr = array();

    /**
     * 描述 : 操作csv内部数组
     * 注明 :
     *      row, col 为 数字=正负位置, null=结尾位置, false=插入操作, true=替换操作
     * 参数 :
     *      val : 数组=插入数据, false=删除数据
     *      row : 指定行位置, 默认null
     *      col : 指定列位置, 默认false
     * 作者 : Edgar.lee
     */
    public static function addRow($val, $row = null, $col = false) {
        //引用数组
        $fileArr = &self::$fileArr;

        //对行操作
        if (is_bool($col)) {
            //整理数据
            $td = &self::arrFill($fileArr, $row < 1 ? 0 : $row, count($val));
            //初始化行
            $row === null && $row = $td['row'];

            array_splice($fileArr, $row, $col, array(&$val));
        //对列操作
        } else if (is_bool($row)) {
            //整理数据
            $td = &self::arrFill($fileArr, count($val), $col < 1 ? 0 : $col);
            //初始化列
            $col === null && $col = $td['col'];
            //初始数据
            for ($i = $col - count($val); $i > 0; $i--) $val[] = '';

            reset($fileArr);
            foreach ($val as &$v) {
                array_splice($fileArr[key($fileArr)], $col, $row, array(&$v));
                next($fileArr);
            }
        }
    }

    /**
     * 描述 : 整理csv数组成字符串
     * 参数 :
     *      path    : 字符串=保存到磁盘路径,默认=null
     *      charset : 转化的字符集, 默认 "UTF-16LE"
     * 返回 :
     *      返回 生成的字符串
     * 作者 : Edgar.lee
     */
    public static function &toString($path = null, $charset = 'UTF-16LE') {
        //字符串列
        $result = array();
        //引用数组
        $fileArr = &self::$fileArr;
        //行分隔符
        $delimiter = $charset === 'UTF-16LE' ? "\t" : ',';

        self::arrFill($fileArr);
        foreach ($fileArr as $vs) {
            foreach ($vs as &$v) {
                //支持字符串和数字两种类型
                if (is_string($v)) {
                    //数字类型 ? 防止科学记数法 : 字符串替换
                    $v = isset($v[8]) && is_numeric($v) ?
                        $v . "\t" : str_replace('"', '""', $v);
                }
            }
            //保存到结果集
            $result[] = '"' . join('"' . $delimiter . '"', $vs) . "\"\r\n";
        }

        $result = join($result);
        //编码转换
        $charset === 'UTF-8' || $result = iconv('UTF-8', $charset . '//IGNORE', $result);
        //保存到文件
        if ($path) {
            $temp = $charset = 'UTF-16LE' ? chr(255) . chr(254) : '';
            of_base_com_disk::file($path, $temp . $result);
        }

        return $result;
    }

    /*
     * 描述:以指定的文件名下载csv
     * 参数:
     *      filename : 字符串=文件名
     *      charset  : 转化的字符集, 默认 "UTF-16LE"
     * 示例:
     *      $ExcelExportObj=new self;
     *      $ExcelExportObj->download('测试.csv');
     *      将弹出'测试.csv'下载框
     * 作者:Edgar.Lee
     */
    public static function download($filename = 'download', $charset = 'UTF-16LE') {
        //需要发送头信息
        static $sendHead = true;
        //引用数组
        $fileArr = &self::$fileArr;
        is_array($filename) && $fileArr[] = $filename;

        if ($sendHead === true) {
            //永不超时
            ini_set('max_execution_time', 0);
            //默认文件名
            is_string($filename) || $filename = 'download';
            //UTF8 文件名
            strpos($_SERVER["HTTP_USER_AGENT"], 'Firefox') || $filename = rawurlencode($filename);
            //字符集
            $sendHead = $charset;
            //下载头
            header('Content-Type: application/download');
            //二进制
            header('Content-Transfer-Encoding: binary');
            //文件名
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            //不缓存
            header('Pragma:no-cache');
            //输出 UTF-16LE BOM 标识
            if ($charset === 'UTF-16LE') echo chr(255) . chr(254);
        }

        if (!empty($fileArr)) {
            echo self::toString(null, $sendHead);
            $fileArr = array();
        }
    }

    /**
     * 描述 : 解析csv
     * 参数 :
     *      path    : 指定路径
     *      charset : 指定解析编码, null=自动识别 UTF-8 UTF-16LE 和 配置_of.charset 字符集
     * 作者 : Edgar.lee
     */
    public static function &parse(&$path, $charset = null) {
        static $list = null;
        $result = false;
        ($index = &$list[$path]) || $index = array(
            //csv 句柄
            'fp' => fopen($path, 'rb'),
            //csv 字符集
            'cs' => $charset,
            //单行分隔符
            'de' => null
        );

        //打开资源
        if ($index['fp']) {
            if (!$index['de']) {
                //尝试识别编码
                if ($index['cs'] === null) {
                    //读取 BOM
                    $temp = array(
                        ord(fgetc($index['fp'])),
                        ord(fgetc($index['fp'])),
                        ord(fgetc($index['fp']))
                    );

                    //识别 UTF-16LE 编码
                    if ($temp[0] === 255 && $temp[1] === 254) {
                        $index['cs'] = 'UTF-16LE';
                        fseek($index['fp'], 2);
                    //识别 UTF-8+BOM 编码
                    } else if ($temp[0] === 239 && $temp[1] === 187 && $temp[2] === 191) {
                        $index['cs'] = 'UTF-8';
                    } else {
                        fseek($index['fp'], 0);
                    }
                }

                //设置分隔符
                $index['de'] = $index['cs'] === 'UTF-16LE' ? "\t" : ',';
            }

            //单行读取成功 && 过滤UTF-16LE低位
            if ($rd = &self::getCsvRow($index)) {
                //定位字符
                $findChar = $rd[0] === '"' ? '"' : $index['de'];
                //最后截取位置
                $lastPos = 0;

                //解析数据
                for ($i = (int)($findChar === '"'), $iL = strlen($rd); $i < $iL; ++$i) {
                    //找到定位符
                    if ($rd[$i] === $findChar) {
                        //双引号
                        if ($findChar === '"') {
                            //下一个字符是'"' ? 移一位继续找'"' : 找分隔符
                            $rd[$i + 1] === '"' ? $i += 1 : $findChar = $index['de'];
                        //分隔符','或'\t'
                        } else {
                            //截取当前字段内容
                            $temp = rtrim(substr($rd, $lastPos, $i - $lastPos), "\r\n");
                            if (isset($temp[0]) && $temp[0] === '"') {
                                $temp = str_replace('""', '"', substr($temp, 1, -1));
                            }
                            //去掉数字字符尾部"\t"
                            $result[] = rtrim($temp, "\t");

                            //下一个字段起始位置
                            $lastPos = $i + 1;
                            //下一个字符是'"' && 找'"' && 移到下一位
                            if (isset($rd[$lastPos]) && $rd[$lastPos] === '"') {
                                $findChar = '"';
                                $i = $lastPos;
                            }
                        }
                    }

                    //需要换行分析
                    if ($findChar === '"' && !isset($rd[$i + 2])) {
                        $rd = substr($rd, 0, -1);
                        $rd .= ($cRow = &self::getCsvRow($index)) ? $cRow : '"' . $index['de'];
                        $iL = strlen($rd);
                    }
                }

                return $result;
            }
        }

        unset($list[$path]);
        return $result;
    }

    /**
     * 描述 : 读取csv一行数据
     * 参数 :
     *     &params  : csv 环境参数
     * 返回 :
     *      成功返回追加分隔符的字符串, 失败返回false
     * 作者 : Edgar.lee
     */
    private static function &getCsvRow(&$params) {
        //读取一行数据
        if ($result = fgets($params['fp'])) {
            //补全换行符
            if ($params['cs'] === 'UTF-16LE') {
                while (
                    ($char = fgetc($params['fp'])) !== false &&
                    ($char !== "\0" || strlen($result) % 2 === 0)
                ) {
                    $result .= $char . fgets($params['fp']);
                }

                //去掉结尾"\n"
                if (strlen($result) % 2 && substr($result, -1) === "\n") {
                    $result = substr($result, 0, -1);
                }
                $result .= "\n\0";
            } else {
                //去掉结尾"\n"
                $result = rtrim($result, "\n") . "\n";
            }

            //初始非ASCII字符集
            if ($params['cs'] === null && preg_match('@[^\x00\x09\x10\x13\x20-\x7F]@', $result)) {
                //是 UTF-8 编码
                if (@iconv('UTF-8', 'UTF-8//IGNORE', $result) === $result) {
                    $params['cs'] = 'UTF-8';
                //是用户群体编码
                } else if (@iconv(
                    $temp = of::config('_of.charset', 'GB18030'), $temp . '//IGNORE', $result
                ) === $result) {
                    $params['cs'] = $temp;
                //未知编码
                } else {
                    $params['cs'] = false;
                }
            }

            //转码为 UTF-8
            $params['cs'] && $params['cs'] !== 'UTF-8' && $result = iconv($params['cs'], 'UTF-8//IGNORE', $result);
            //字符串补全, 换行统一 "\r\n","\r" => "\n"
            $result = str_replace(array("\r\n", "\r"), "\n", $result) . $params['de'];
        }
        return $result;
    }

    /**
     * 描述 : 数组填充
     * 参数 :
     *     &arr : 格式数据
     *      row : 最小行数, 默认=0
     *      col : 最小列数, 默认=0
     * 返回 :
     *      {"row" : 最大行数, "col" : 最大列数}
     * 作者 : Edgar.lee
     */
    private static function &arrFill(&$arr, $row = 0, $col = 0) {
        //最大行
        for ($i = $row - count($arr); $i > 0; $i--) $arr[] = array();
        //最大值
        $result = array('row' => count($arr), 'col' => 0);

        if (!empty($arr)) {
            //最大列
            $col > ($result['col'] = max(array_map('count', $arr))) && $result['col'] = $col;
            //填充列
            foreach ($arr as &$v) for ($i = $result['col'] - count($v); $i > 0; $i--) $v[] = '';
        }

        return $result;
    }
}