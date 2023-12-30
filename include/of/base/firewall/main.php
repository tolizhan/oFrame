<?php
/**
 * 描述 : 网络防火墙
 * 注明 :
 *      配置文件($config)结构 : 字符串=指定配置路径(结构同数组), 数组={
 *          "clientIp" :o从"$_SERVER"获取访问IP顺序, 如 "HTTP_X_REAL_IP", 默认["REMOTE_ADDR"]
 *          "control"  : 匹配规则, 按顺序通过"matches"匹配来验证客户IP是否有效 {
 *              匹配规则说明 : {
 *                  "matches" : 匹配调度信息, 调度格式为"class::action" {
 *                      注释信息 : 其它过滤信息 {
 *                          "action" : 匹配调度信息, 以"@"开始的字符串=按正则处理, "类名::方法"=全等匹配调度格式
 *                          "values" :o匹配全局变量$GLOBALS中的字符串数据 {
 *                              以"."作为分隔符匹配深度数组 : 以"@"开始按正则, 否则全等对比,
 *                              ...
 *                          }
 *                          "method" :o通过回调方法({"action" : 调度方法})判断匹配,返回 true=匹配, false=未匹配
 *                      }, ...
 *                  },
 *                  "ipList"   : 验证IP列表, 支持IP v4 v6, 字符串=指定配置路径(结构同数组), 数组={
 *                      "blocklist" :o黑名单, 在范围内会被拦截 [
 *                          字符串=为固定IP,
 *                          [小IP, 大IP]=IP范围,
 *                          ...
 *                      ],
 *                      "allowlist" :o白名单, 不在范围内且不为空会被拦截, 结构同"blocklist"
 *                  }
 *              }, ...
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class of_base_firewall_main {
    /**
     * 描述 : 防火墙初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        of::event('of::dispatch', 'of_base_firewall_main::dispatch');
    }

    /**
     * 描述 : 访问限制判断
     * 作者 : Edgar.lee
     */
    public static function dispatch($params) {
        //CLI模式 || 改变调度入口 || 无网络配置
        if (
            PHP_SAPI === 'cli' ||
            $params['check'] === false ||
            !$config = of::config('_of.firewall.network')
        ) {
            return ;
        }

        //调度方法
        $action = "{$params['class']}::{$params['action']}";
        //访问限制配置
        is_string($config) && $config = include ROOT_DIR . $config;
        $config += array('clientIp' => array('REMOTE_ADDR'), 'control'  => array());

        //获取客户IP
        $cIpV6 = '::';
        foreach ($config['clientIp'] as &$v) {
            if (isset($_SERVER[$v])) {
                $cIpV6 = $_SERVER[$v];
                break ;
            }
        }
        $cIpV6 = self::ip2v6($cIpV6);

        //遍历匹配
        foreach ($config['control'] as $vk => &$vm) {
            //请求类型与调度匹配成功 && P列表存在
            if (isset($vm['matches']) && self::matches($vm, $action) && $index = &$vm['ipList']) {
                //是文件路径 && 加载配置文件
                is_string($index) && $index = include ROOT_DIR . $index;
                //初始化结构
                $index += array('blocklist' => array(), 'allowlist' => array());

                if (
                    //黑名单验证通过
                    self::compare($index['blocklist'], $cIpV6) ||
                    //存在白名单 && 白名单验证未通过
                    $index['allowlist'] && !self::compare($index['allowlist'], $cIpV6)
                ) {
                    $temp = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
                    header($temp . ' 401 Unauthorized');
                    trigger_error('IP address blocking: ' . "{$vk}({$action})");
                    exit ;
                }
            }
        }
    }

    /**
     * 描述 : 验证是否符合指定匹配规则
     * 返回 :
     *      匹配=true, 反之=false
     * 作者 : Edgar.lee
     */
    private static function matches(&$match, &$action) {
        //匹配调度信息
        foreach ($match['matches'] as &$v) {
            //验证调度是否匹配 "@"开头 ? 正则匹配 : 全等匹配
            if ($v['action'][0] === '@' ? preg_match($v['action'], $action) : $v['action'] === $action) {
                //匹配GLOBALS全局变量
                if (isset($v['values'])) {
                    foreach ($v['values'] as $kv => &$vv) {
                        //变量未匹配成功
                        if (
                            !is_string($temp = of::getArrData(array($kv, $GLOBALS))) ||
                            !($vv[0] === '@' ? preg_match($vv, $temp) : $vv === $temp)
                        ) {
                            return false;
                        }
                    }
                }

                //通过回调方法判断匹配
                return empty($v['method']) || of::callFunc($v['method'], array(
                    'action' => $action
                ));
            }
        }

        return false;
    }

    /**
     * 描述 : 格式化IP地址
     * 参数 :
     *     &match : IP列表 [对比IP段, ...]
     *     &cIpV6 : 客户端IPv6
     * 返回 :
     *      在ipKey范围内=true, 反之=false
     * 作者 : Edgar.lee
     */
    private static function compare(&$match, &$cIpV6) {
        $result = false;

        foreach ($match as &$ipKey) {
            //字符串
            if (is_string($ipKey)) {
                if ($result = self::ip2v6($ipKey) === $cIpV6) break;
            //数组模式
            } else {
                $temp = array(self::ip2v6(reset($ipKey)), self::ip2v6(end($ipKey)));
                sort($temp);
                if ($result = $temp[0] <= $cIpV6 && $cIpV6 <= $temp[1]) break;
            }
        }

        return $result;
    }

    /**
     * 描述 : IP转换成完整的IPv6格式
     * 作者 : Edgar.lee
     */
    private static function &ip2v6($ip) {
        //IP v6 编码
        if (strpos($ip, '.') === false) {
            //统一转换成小写
            $ip = strtolower(trim($ip));

            //使用缩写模式
            if (strpos($ip, '::') !== false) {
                //分组数量
                $temp = substr_count($ip, ':');
                //生成省略部分
                $temp = join(':', array_fill(0, 8 - $temp, '0000'));
                //补全省略部分
                $ip = str_replace('::', ':' . $temp . ':', $ip);
            }

            //切分成数组
            $ip = explode(':', $ip);
            //转成标准v6
            foreach ($ip as &$v) {
                $v = str_pad($v, 4, '0', STR_PAD_LEFT);
            }
            $ip = join(':', $ip);
        //IP v4 编码
        } else {
            //非标准v6转v4
            ($temp = strrpos($ip, ':')) === false || $ip = substr($ip, $temp + 1);
            //切分成数组
            $ip = explode('.', trim($ip));

            //转成标准v6
            foreach ($ip as &$v) {
                $v = str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
            }
            $ip = '0000:0000:0000:0000:0000:0000:' . $ip[0] . $ip[1] . ':' . $ip[2] . $ip[3];
        }

        return $ip;
    }
}

of_base_firewall_main::init();