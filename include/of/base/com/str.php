<?php
/**
 * 描述 : 提供字符串相关封装
 * 作者 : Edgar.lee
 */
class of_base_com_str {
    /**
     * 描述 : 从字符串中提取指定数组中最先出现的位置
     * 参数 :
     *     &str     : 被搜索的字符串
     *      matches : 匹配数组,优先级从高到底 {
     *          匹配字符串 : 是否受'\'影响,true=是,false=否
     *      }
     *      offset  : 偏移量,0=默认
     * 返回 :
     *      没找到返回false,否则{'match' : 最先出现的字符串, 'position' : 出现的位置}
     * 作者 : Edgar.lee
     */
    public static function strArrPos(&$str, $matches, $offset = 0) {
        //分析数据
        $analyData = null;

        //截取字符
        if (isset($str[$offset])) {
            $findStr = substr($str, $offset);
        //偏移溢出
        } else {
            return false;
        }

        //匹配字串 => 是否被'\'约束
        foreach ($matches as $match => &$bind) {
            //当前偏移位置
            $nowFindOffset = 0;
            //查找到的位置
            while (($nowFindOffset = strpos($findStr, $match, $nowFindOffset)) !== false) {
                $nowStrOffset = $nowFindOffset + $offset;

                //受'\'约束
                if ($bind) {
                    //斜线数量
                    $slashesNum = 0;
                    //当前位置
                    $i = $nowStrOffset;
                    //统计结尾'\'连续数量
                    while (--$i > -1) {
                        //统计结尾'\'数量
                        if ($str[$i] === '\\') {
                            $slashesNum += 1;
                        //结束统计
                        } else {
                            break;
                        }
                    }

                    //'\'为偶数
                    if ($slashesNum % 2 === 0) {
                        $analyData[$match] = $nowStrOffset;
                        break;
                    }
                //不受约束
                } else {
                    $analyData[$match] = $nowStrOffset;
                    break;
                }

                //超出字符串长度跳出
                if (!isset($findStr[++$nowFindOffset])) {
                    break;
                }
            }
        }

        //无有效到数据
        if ($analyData === null) {
            return false;
        //排序有效数据
        } else {
            //SORT_NUMERIC,按数字排序
            asort($analyData);
            $p = current($analyData);
            $analyData = array_flip(array_keys($analyData, $p, true));
            foreach ($matches as $m => &$v) {
                if (isset($analyData[$m])) {
                    break;
                }
            }
            return array('match' => &$m, 'position' => &$p);
        }
    }

    /**
     * 描述 : 文本加密与解密
     * 参数 :
     *      key    : 加密解密文本密码
     *      txt    : 需要加解密的文本
     *      base64 : 加密解密标识,null(默认)=无编码加密解密, true=base64加密,false=base64解密
     *      level  : 加密级别 1=简单线性加密, >1 = RC4加密数字越大越安全越慢, 默认=256
     * 返回 :
     *      加密或解密后的明码字符串
     * 演示 :
     *      rc4('密码', '测试', true);
     *      随机加密字符,如:Yegw4WXcMOth/DvC
     *      rc4('密码', rc4('密码', '测试', true), false);
     *      测试
     * 作者 : Edgar.lee
     */
    public static function rc4($pwd, $txt, $base64 = null, $level = 256) {
        //base64解密
        $base64 === false && $txt = base64_decode($txt);
        $result = '';
        $kL = strlen($pwd);
        $tL = strlen($txt);

        //非线性加密
        if ($level > 1) {
            for ($i = 0; $i < $level; ++$i) {
                $key[$i] = ord($pwd[$i % $kL]);
                $box[$i] = $i;
            }

            for ($j = $i = 0; $i < $level; ++$i) {
                $j = ($j + $box[$i] + $key[$i]) % $level;
                $tmp = $box[$i];
                $box[$i] = $box[$j];
                $box[$j] = $tmp;
            }

            for ($a = $j = $i = 0; $i < $tL; ++$i) {
                $a = ($a + 1) % $level;
                $j = ($j + $box[$a]) % $level;

                $tmp = $box[$a];
                $box[$a] = $box[$j];
                $box[$j] = $tmp;

                $k = $box[($box[$a] + $box[$j]) % $level];
                $result .= chr(ord($txt[$i]) ^ $k);
            }
        //简单线性加密
        } else {
            for ($i = 0; $i < $tL; ++$i) {
                $result .= $txt[$i] ^ $pwd[$i % $kL];
            }
        }

        //base64加密
        $base64 && $result = base64_encode($result);
        return $result;
    }

    /**
     * 描述 : mcrypt 加解密封装
     * 参数 :
     *      key  : 加密解密文本密码
     *      txt  : 需要加解密的文本
     *      type : 加密解密标识,1=加密, 0=解密, true=base64加密, false=base64解密
     *      mode : 加密模式(http://php.net/manual/zh/mcrypt.ciphers.php) {
     *          "type" : 算法(algorithm),
     *              MCRYPT_RIJNDAEL_128 : (默认)AES
     *              MCRYPT_3DES         : DES
     *              ...
     *          "mode" : 模式, 默认 MCRYPT_MODE_ECB, 还可大写(MCRYPT_MODE_modename : "ecb"，"cbc"，"cfb"，"ofb"，"nofb" 和 "stream")
     *          "rand" :&随机数, 默认 mcrypt_create_iv(算法位, MCRYPT_RAND)
     *      }
     * 作者 : Edgar.lee
     */
    public static function &mcrypt($key, $txt, $type, $mode = array()) {
        $mode += array(
            //默认算法
            'type' => MCRYPT_RIJNDAEL_128,
            //默认模式
            'mode' => MCRYPT_MODE_ECB,
            //初始项量
            'rand' => ''
        );
        $open = mcrypt_module_open($mode['type'], '', $mode['mode'], '');
        //加密块字节
        $size = mcrypt_enc_get_key_size($open);
        //截取过长密钥
        $key = substr($key, 0, $size);
        //随机项量
        $mode['rand'] || $mode['rand'] = mcrypt_create_iv(mcrypt_enc_get_iv_size($open), MCRYPT_RAND);
        //初始算法到缓存
        mcrypt_generic_init($open, $key, $mode['rand']);

        //加密
        if ($type) {
            switch ($mode['type']) {
                //AES
                case MCRYPT_RIJNDAEL_128:
                //DES
                case MCRYPT_3DES:
                    //PKCS #7/5 32/8字节
                    $temp = $size - strlen($txt) % $size;
                    //文本填充
                    $txt .= str_repeat(chr($temp), $temp);
                    break;
            }

            $txt = mcrypt_generic($open, $txt);
            //base64加密
            $type === true && $txt = base64_encode($txt);
        //解密
        } else {
            //base64解密
            $type === false && $txt = base64_decode($txt);
            $txt = mdecrypt_generic($open, $txt);

            switch ($mode['type']) {
                //AES
                case MCRYPT_RIJNDAEL_128:
                //DES
                case MCRYPT_3DES:
                    $temp = ord($txt[strlen($txt)-1]);
                    //移除填充
                    $txt = substr($txt, 0, -$temp);
                    break;
            }
        }

        //清理缓存
        mcrypt_generic_deinit($open);
        //关闭模块
        mcrypt_module_close($open);
        return $txt;
    }

    /**
     * 描述 : 字符串按位截取,一个utf8为3位,同时保证截取后的字符串完整
     * 参数 :
     *      str    : 字符串
     *      start  : 数字=起始长度, 数组=额外参数 {
     *          "start"    : 起始长度,
     *          "length"   : 截取长度,
     *          "overflow" : 额外溢出字符串
     *      }
     *      length : 截取长度. null(默认)=截取到最后
     * 演示 :
     *      strsub('我a是m', 2);
     *      返回的结果为'是m'
     *      strsub('我a是m', array('start' => 2, 'overflow' => '...'));
     *      返回的结果为'...是m'
     * 作者 : Edgar.lee
     */
    public static function strsub($str, $start, $length = null) {
        //参数初始化
        is_array($start) || $start = array('start' => (int)$start, 'length' => &$length);
        //默认参数
        $start += array('start' => 0, 'overflow' => '');

        //字符串截取
        preg_match_all('/./u', $str, $match);
        $temp[] = join($match[1] = array_slice($match[0], $start['start'], $start['length']));

        //添加溢出符
        if (!empty($start['overflow']) && $temp[0] !== $str) {
            //有数据
            if ($temp[0]) {
                $temp['strlen'] = count($match[0]);
                $temp['sublen'] = count($match[1]);
                $temp['start']  = $start['start'] < 0 ? $temp['strlen'] + $start['start'] : $start['start'];

                //前溢出符
                $temp['start'] > 0 && $temp[0] = $start['overflow'] . $temp[0];
                //后溢出符
                $temp['sublen'] + $temp['start'] < $temp['strlen'] && $temp[0] .= $start['overflow'];
            //无数据
            } else {
                $temp[0] = $start['overflow'];
            }
        }

        return $temp[0];
    }

    /**
     * 描述 : 获取真实路径,去掉多余的'/./', '/../', '//'
     * 参数 :
     *      path : 指定源路径
     * 返回 :
     *      返回规范化的绝对路径名 
     * 作者 : Edgar.lee
     */
    public static function realpath($path) {
        //压制数据
        $suppress = array();

        if (preg_match('@^(\w+:/{2,}[^/#?]*/|\w+:/)?([^?#]*)(.*)$@', $path, $match)) {
            $path = $match[2];
            unset($match[0]);
        }

        if ($path) {
            //按'/'和'\'分组
            $each = explode('/', $path = strtr($path, '\\', '/'));

            //压制过滤
            foreach ($each as &$v) {
                //如果为后退(../)结构
                if ($v === '..') {
                    //如果压制包里有数据
                    if (($temp = count($suppress)) > 0) {
                        //并且最后一个为后退结构
                        if ($suppress[$temp - 1] === '..') {
                            //压入当前后退结构
                            $suppress[] = '..';
                        //否则
                        } else {
                            //弹出压制中最后一结构
                            array_pop($suppress);
                        }
                    //压制包中无数据
                    } else {
                        //压入当前后退结构
                        $suppress[] = '..';
                    }
                //如果不为当前结构(./或'')
                } else if ($v !== '' && $v !== '.') {
                    //压入常规结构
                    $suppress[] = $v;
                }
            }

            //规则整合
            $temp = array(
                //头标识
                $path[0] === '/' ? '/' : '',
                //尾标识
                $path[strlen($path) - 1] === '/' ? '/' : ''
            );
            //整理后不为''
            if ($suppress = join('/', $suppress)) {
                //返回 头标识+整理结果+尾标识
                $match[2] = $temp[0] . $suppress . $temp[1];
            //整理后为''
            } else {
                //头尾标识相同 ? (都为'' ? '.' : '/') : ''
                $match[2] = $temp[0] === $temp[1] ? ($temp[0] === '' ? '.' : '/') : '';
            }
        } else {
            $match[2] = '.';
        }

        return join($match);
    }

    /**
     * 描述 : 获取更具唯一性的ID
     * 参数 :
     *      prefix : 编码前缀, 不同前缀并发互不影响, ''=生成32位小写字母唯一编码, 其它=短小有意义可排序的编码
     *      isShow : 功能操作,
     *          数字   = 代替minLen参数,
     *          布尔   = 显示前缀, true=显示, false=隐藏
     *          字符串 = 时间结构, 用"\"转义, 默认"ymdHis", 如: "\y\m\dymd-"
     *      minLen : 自增值最小长度, prefix不为空时有效, 默认3
     * 返回 : 
     *      prefix 为假时返回 32位小写字母
     *      prefix 为真时返回 大写prefix + 两位年月日时分秒时间结构 + minLen计数
     * 作者 : Edgar.lee
     */
    public static function uniqid($prefix = '', $isShow = true, $minLen = 3) {
        static $lable = null;

        //有意义的编码规则
        if ($prefix) {
            //快速设置参数
            is_int($isShow) && $minLen = $isShow;
            //时间结构格式
            is_string($format = $isShow) ? $isShow = false : $format = 'ymdHis';
            //大小前缀
            $prefix = strtoupper($prefix);
            //依赖磁盘路径
            $path = ROOT_DIR . OF_DATA . "/_of/of_base_com_str/uniqid/{$prefix}.php";
            //写入锁的方式读取数据流
            $fp = of_base_com_disk::file($path, null, null);
            //获取计数数据
            $data = &of_base_com_disk::file($fp, true, true);
            //当前日期时间
            $date = date($format, $time = time());
            //兼容旧版数据
            !isset($data['date']) && isset($data['time']) && $data += array(
                'date'   => date('ymdHis', $data['time']),
                'format' => 'ymdHis'
            );

            //自增计数
            if ($data && $data['format'] === $format && $date <= $data['date']) {
                $data['count'] += 1;
            //重置数据
            } else {
                $data = array(
                    'time' => &$time, 'date' => &$date,
                    'count' => 1, 'format' => &$format
                );
            }

            //写回数据
            of_base_com_disk::file($fp, $data, true);
            //关闭连接
            flock($fp, LOCK_UN) && fclose($fp);

            //生成唯一值
            return ($isShow ? $prefix : '') . $data['date'] .
                str_pad($data['count'], $minLen, '0', STR_PAD_LEFT);
        //生成32位编码
        } else {
            $lable === null &&
            (!$lable = function_exists('com_create_guid')) &&
            ($lable = json_encode($_SERVER));

            if ($lable === true) {
                return strtolower(str_replace(array('{', '}', '-'), '', com_create_guid()));
            } else {
                return md5(uniqid('', true) . $lable .  mt_rand());
            }
        }
    }
}