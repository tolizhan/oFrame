<?php
class of_base_com_data {
    protected static $rule = array(
        'return' => true,
        'result' => null
    );

    /**
     * 描述 : 安全的json
     * 参数 :
     *     &data : 编码或解码的数据
     *      isEn : true=编码, false=解码
     * 返回 :
     *      编码解码后的数据
     * 作者 : Edgar.lee
     */
    public static function &json(&$data, $isEn = true) {
        if ($isEn) {
            $result = json_encode($data);
            $result = str_replace(array('<', '>'), array('\u003C', '\u003E'), $result);
        } else {
            $result = json_decode($data, true);
        }

        return $result;
    }

    /**
     * 描述 : 计算数据的唯一摘要值
     * 参数 :
     *      data : 指定计算的数据
     * 返回 :
     *      返回32位摘要字符串
     * 作者 : Edgar.lee
     */
    public static function digest($data) {
        //数据拷贝(过滤内部循环引用)
        $data = unserialize(preg_replace('@;R(:\d+;)@', ';i\1', serialize($data)));

        //数组, 对象
        if (!is_scalar($data) && $data !== null) {
            //等待处理列表
            $list = array(&$data);

            do {
                $lk = key($list);
                $lv = &$list[$lk];
                unset($list[$lk]);

                //数组, 对象
                $temp = is_array($lv) ? array(
                    'data' => array(),
                    'type' => 'array'
                ) : array(
                    'data' => array(),
                    'name' => get_class($lv),
                    'type' => 'object'
                );

                foreach ($lv as $k => &$v) {
                    $temp['data'][$k] = &$v;
                    !is_scalar($v) && $v !== null && $list[] = &$v;
                }

                $lv = $temp;
                ksort($lv['data']);
            } while ($list);
        }

        //数据摘要
        return md5(json_encode($data));
    }

    /**
     * 描述 : 数据格式校验
     * 参数 :
     *     &data : 待填充校验的数据
     *      rule : 校验规则 {
     *          "."与"*"为关键词, "`"为转义字符的分割键名 : 参数结构如下, 字符串代表数组的type {
     *              "default" : 默认值, null=必存在, 其它=默认值
     *              "keys"    : 按顺序验证键名中各"*"的类型 [
     *                  null为不验证,
     *                  type字符串验证类型(正则或内置),
     *                  {"type" => 同"值的类型", "min" => 数组最小个数, "max" => 数组最大个数},
     *                  ...
     *              ],
     *              "type"    : 值的类型
     *                  数组=验证子节点键{
     *                      子节点键 : 同"值的类型",
     *                      ...
     *                  },
     *                  "@"开头字符串=正则验证,
     *                  字符串=内置类型
     *                      "int"   : 整形, argv参数 {
     *                          "idem" : 类型一致, 默认false,
     *                          "min"  : 最小值,
     *                          "max"  : 最大值,
     *                      }
     *                      "float" : 包括整型的浮点型, argv参数 {
     *                          "idem" : 类型一致, 默认false,
     *                          "min"  : 最小值,
     *                          "max"  : 最大值,
     *                      }
     *                      "text"  : 包含数字类型文本, argv参数 {
     *                          "min" : 最小长度,
     *                          "max" : 最大长度
     *                      }
     *                      "bool"  : 布尔类型, argv参数 {
     *                          "format" : 转换布尔,
     *                              默认=枚举方式["ok", "true", "success", "on", "yes", "done", 1] 转true,
     *                              false=强制验证布尔类型,
     *                              true=弱类型为true的均转true
     *                      }
     *                      "date"  : 时间类型, argv参数 {
     *                          "format" : 格式化样式, 默认="Y-m-d H:i:s", false=不格式,
     *                          "min"    : 最小时间,
     *                          "max"    : 最大时间,
     *                      }
     *                      "mail"   : 验证邮箱, argv无参数
     *                      "call"   : 回调验证, null=验证成功, 其它=提示错误, argv参数符合回调结构, 接收参数 {
     *                          "check" :&引用的验证数据
     *                      }
     *              "argv"    : 对应类型提供的参数 {
     *                  ...
     *              }
     *          }
     *      }
     * 返回 :
     *      null=成功, array=失败 {
     *          点分割的键名 : 错误描述信息,
     *          ...
     *      }
     * 作者 : Edgar.lee
     */
    public static function rule(&$data, $rule) {
        static $revert = array('`' => '``', '.' => '`.', '*' => '`*');
        //处理列表, [校验数据, 校验规则, 相对定位, 类型验证(true=定位键值, false=验证键值, null=验证键名)]
        $list[] = array(&$data, &$rule, '', true);
        self::$rule['result'] = &$error;

        do {
            $shift = array_shift($list);
            //数组结构
            if ($shift[3]) {
                foreach ($shift[1] as $ks => &$vs) {
                    //简写方式转换
                    is_array($vs) || $vs = array('type' => $vs);
                    //默认初始化 type 为 null
                    $index = &$vs['type'];

                    if (isset($vs['boot'])) {
                        $ks = $vs['boot'];
                    } else {
                        //解析定位符 "\1" 为遍历数组
                        $ks = substr(strtr('.' . $ks . '.', '.*.', ".\1."), 1, -1);
                        $ks = explode("\0", strtr($ks, array(
                            '``' => '`', '`.' => '.', '`' => '', '.' => "\0"
                        )));
                    }

                    //开始定位
                    $postr = $shift[2];
                    $index = &$shift[0];
                    foreach ($ks as $kp => &$pos) {
                        $postr && $postr .= '.';

                        //模糊键
                        if ($pos === "\1") {
                            //格式模糊键配置
                            $aKey = empty($vs['keys']) ? array() : array_shift($vs['keys']);
                            is_array($aKey) || $aKey = array('type' => $aKey);
                            $aKey += array('min' => 0, 'max' => PHP_INT_MAX);

                            //可遍历 && 不为空
                            if (is_array($index)) {
                                //数组条数合规
                                if (($temp = count($index)) >= $aKey['min'] && $temp <= $aKey['max']) {
                                    //余下路径
                                    $vs['boot'] = array_slice($ks, $kp + 1);
                                    foreach ($index as $k => &$v) {
                                        $temp = $postr . strtr($k, $revert);

                                        //键名验证
                                        isset($aKey['type']) && $list[] = array($k, &$aKey, $temp, null);

                                        //继续引导定位
                                        if ($vs['boot']) {
                                            $list[] = array(&$v, array($vs), $temp, true);
                                        //分组定位
                                        } else if (is_array($vs['type'])) {
                                            $list[] = array(&$v, &$vs['type'], $temp, true);
                                        //最后类型判断
                                        } else {
                                            $list[] = array(&$v, &$vs, $temp, false);
                                        }
                                    }
                                } else {
                                    $error[$postr . '*'] = 'Key illegal, array length should ' .
                                        ">= {$aKey['min']} and <= {$aKey['max']}";
                                }

                                $postr .= '*';
                                break ;
                            //选填项 && 数组可为空
                            } else if (isset($vs['default']) && $aKey['min'] > -1) {
                                $index = array();
                                break ;
                            //必填项
                            } else {
                                $error[$postr . '*'] = 'Key illegal : *';
                                break ;
                            }
                        //确定键
                        } else {
                            //还原定位键
                            $postr .= strtr($pos, $revert);

                            //有效定位
                            if (($isArr = is_array($index)) && isset($index[$pos])) {
                                $index = &$index[$pos];

                                //最后一项
                                if (!isset($ks[$kp + 1])) {
                                    //分组定位
                                    if (is_array($vs['type'])) {
                                        $list[] = array(&$index, &$vs['type'], $postr, true);
                                    //类型判断
                                    } else {
                                        $list[] = array(&$index, &$vs, $postr, false);
                                    }
                                }
                            //无效定位, 可初始化
                            } else if (($isArr || $index === null) && isset($vs['default'])) {
                                //不是最后一个 ? [] : $vs['default']
                                $index[$pos] = isset($ks[$kp + 1]) ? array() : $vs['default'];
                                $index = &$index[$pos];
                            //无法定位, 但必填
                            } else {
                                $error[$postr] = "Key illegal : {$pos}";
                                break ;
                            }
                        }
                    }
                }
            //数据类型验证(不在范围内的均通过)
            } else if (isset($shift[1]['type'][0])) {
                $index = &$shift[1];

                //正则匹配
                if ($index['type'][0] === '@') {
                    if (
                        !is_string($shift[0]) && !is_numeric($shift[0]) ||
                        !preg_match($index['type'], $shift[0])
                    ) {
                        $temp = str_replace(':', '&#058;', htmlspecialchars($index['type'], ENT_QUOTES, 'UTF-8'));
                        $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') .
                            " illegal, should be regexp \"{$temp}\" : " .
                            (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                    }
                } else {
                    $argv = &$index['argv'];
                    switch ($index['type']) {
                        //文本
                        case 'text':
                            //转成字符串
                            is_numeric($shift[0]) && $shift[0] .= '';
                            if (
                                !is_string($shift[0]) ||
                                isset($argv['min']) && $argv['min'] > 0 && !isset($shift[0][$argv['min'] - 1]) ||
                                isset($argv['max']) && isset($shift[0][$argv['max']])
                            ) {
                                $temp = array();
                                isset($argv['min']) && $temp[] = ' >= ' . $argv['min'];
                                isset($argv['max']) && $temp[] = ' <= ' . $argv['max'];
                                ($temp = join(' and', $temp)) && $temp = ', length' . $temp;
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') .
                                    " illegal, should be {$index['type']}{$temp} : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            }
                            break;
                        //整型
                        case 'int':
                            if (
                                !(empty($argv['idem']) ? 
                                    is_numeric($shift[0]) && strpos($shift[0], '.') === false :
                                    is_int($shift[0])
                                ) ||
                                isset($argv['min']) && $shift[0] < $argv['min'] ||
                                isset($argv['max']) && $shift[0] > $argv['max']
                            ) {
                                $temp = array();
                                isset($argv['min']) && $temp[] = ' >= ' . $argv['min'];
                                isset($argv['max']) && $temp[] = ' <= ' . $argv['max'];
                                ($temp = join(' and', $temp)) && $temp = ', value' . $temp;
                                empty($argv['idem']) || $temp = ', strict type' . $temp;
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    " illegal, should be {$index['type']}{$temp} : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            } else {
                                $shift[0] += 0;
                            }
                            break;
                        //浮点
                        case 'float':
                            if (
                                !(empty($argv['idem']) ? is_numeric($shift[0]) : is_float($shift[0])) || 
                                isset($argv['min']) && $shift[0] < $argv['min'] ||
                                isset($argv['max']) && $shift[0] > $argv['max']
                            ) {
                                $temp = array();
                                isset($argv['min']) && $temp[] = ' >= ' . $argv['min'];
                                isset($argv['max']) && $temp[] = ' <= ' . $argv['max'];
                                ($temp = join(' and', $temp)) && $temp = ', value' . $temp;
                                empty($argv['idem']) || $temp = ', strict type' . $temp;
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    " illegal, should be {$index['type']}{$temp} : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            } else {
                                $shift[0] += 0;
                            }
                            break;
                        //布尔
                        case 'bool':
                            isset($argv['format']) || $argv['format'] = array(
                                "ok", "true", "success", "on", "yes", "done", 1
                            );

                            //宽松模式 true
                            if ($argv['format'] === true) {
                                $shift[0] = !!$shift[0];
                            //枚举模式 array
                            } else if (is_array($argv['format'])) {
                                $shift[0] = in_array($shift[0], $argv['format']);
                            //严格模式 false
                            } else if (!is_bool($shift[0])) {
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    " illegal, should be {$index['type']}, strict type : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            }
                            break;
                        //日期
                        case 'date':
                            isset($argv['format']) || $argv['format'] = 'Y-m-d H:i:s';

                            if (
                                ($temp = is_numeric($shift[0]) ? $shift[0] : strtotime($shift[0])) &&
                                (
                                    empty($argv['min']) || 
                                    (is_numeric($argv['min']) ? $argv['min'] : strtotime($argv['min'])) <= $temp 
                                ) && 
                                (
                                    empty($argv['max']) || 
                                    (is_numeric($argv['max']) ? $argv['max'] : strtotime($argv['max'])) >= $temp
                                )
                            ) {
                                $shift[0] = date($argv['format'], $temp);
                            } else {
                                $temp = array();
                                isset($argv['min']) && $temp[] = ' >= ' . (
                                    is_numeric($argv['min']) ? date('Y-m-d H:i:s', $argv['min']) : $argv['min']
                                );
                                isset($argv['max']) && $temp[] = ' <= ' . (
                                    is_numeric($argv['max']) ? date('Y-m-d H:i:s', $argv['max']) : $argv['max']
                                );
                                ($temp = join(' and', $temp)) && $temp = ', value' . $temp;
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    " illegal, should be {$index['type']}{$temp} : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            }
                            break;
                        //邮件
                        case 'mail':
                            if (!preg_match('/^[\w-.]+@([\w-]+\.)+[a-z]+$/i', $shift[0])) {
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    " illegal, should be {$index['type']} : " .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            }
                            break;
                        //回调
                        case 'call':
                            if ($temp = of::callFunc($argv, array('check' => &$shift[0]))) {
                                $error[$shift[2]] = ($shift[3] === null ? 'Key' : 'Val') . 
                                    ' illegal, ' . str_replace(':', '&#058;', $temp) . ' : ' .
                                    (is_scalar($shift[0]) ? var_export($shift[0], true) : print_r($shift[0], true));
                            }
                            break;
                    }
                }
            }
        } while ($list);

        if (self::$rule['return']) {
            return $error;
        }
    }
}