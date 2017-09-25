<?php
/**
 * 描述 : 提供网络通信相关封装
 * 注明 :
 *      配置文件结构($config) : {
 *          "async"  : 异步请求方案, ""=当前网址, url=指定网址
 *          "kvPool" : k-v 池, 异步请求时用于安全校验
 *          "asUrl"  : 异步请求使用的网络地址解析格式 {
 *              "scheme" : 网络协议, http或https,
 *              "host"   : 请求域名,
 *              "port"   : 请求端口,
 *              "path"   : 请求路径,
 *              "query"  : 请求参数
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class of_base_com_net {
    //默认请求参数
    public static $params = null;
    //静态cookie
    private static $cookie = array();
    //配置文件
    private static $config = null;

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        $temp = empty($_SERVER['HTTP_HOST']) ? array('127.0.0.1') : explode(':', $_SERVER['HTTP_HOST']);
        //当前路径解析地址
        self::$params = array(
            'scheme' => $temp[2] = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https',
            'host'   => &$temp[0],
            'port'   => isset($temp[1]) ? (int)$temp[1] : ($temp[2] === 'https' ? 443 : 80),
            'path'   => $_SERVER['SCRIPT_NAME'],
            'query'  => ''
        );

        $temp = of::config('_of.com.net', array()) + array(
            'async'  => '',
            'kvPool' => 'default'
        );
        //校验模式
        $temp['asUrl'] = strpos($temp['async'], '://') ?
            //异步请求地址
            parse_url($temp['async']) + array('port' => 80) : self::$params;
        //配置信息
        self::$config = $temp;
    }

    /**
     * 描述 : 数据请求
     * 参数 :
     *      url  : 请求的完整路径(非完整路径将用当前站数据填充)
     *      data : 提交数据,含有如下结构的数组,其数据可以是字符串或数组 {
     *          "type"    : 交互类型,('GET')可以是 OPTIONS,GET,HEAD,POST,PUT,DELETE,TRACE
     *          "data"    : 报文主体数据('')
     *          "header"  : 自定义头信息('')
     *          "get"     : get数据(''),
     *          "post"    : post数据,相当于type=POST, data=当期值(''),
     *          "cookie"  : cookie数据(''),
     *          "timeout" : 超时时间(10s)
     *      }
     *      mode : 提交模式,
     *          false(默认) = 同步提交,
     *          true        = 无结果异步提交,
     *          字符串      = 编译并调用方(变量$_接收响应数据),
     *          数组        = {'asCall' : 符合可调用规范, 'params' : 回调参数}, 仅能传递动态参数,不能传递静态函数或资源文件
     * 返回 :
     *      失败时 : {state:false, errno:错误描述, errstr:失败码}
     *      成功时 : {state:true, header:响应头, response:响应数据}
     * 作者 : Edgar.lee
     */
    public static function &request($url = null, $data = array(), $mode = false) {
        //永不超时
        ini_set('max_execution_time', 0);
        //配置引用
        $config = &self::$config;

        //二次请求
        if ($url === null) {
            if (PHP_SAPI === 'cli') {
                $data = $GLOBALS['_ARGV']['_DATA'];
            } else {
                //请求数据流
                $data = file_get_contents('php://input');

                if (
                    isset($_GET['md5']) &&
                    ($temp = of_base_com_kv::get(
                        'of_base_com_net::' . $_GET['md5'], false, $config['kvPool']
                    )) &&
                    $temp === md5($data)
                ) {
                    //忽略客户端断开
                    ignore_user_abort(true);
                    //删除校验kv
                    of_base_com_kv::del('of_base_com_net::' . $_GET['md5'], $config['kvPool']);
                    //默认关闭session
                    session_write_close();
                } else {
                    //怀疑恶意请求
                    trigger_error('Suspected malicious requests');
                    return $url;
                }
            }

            //读取post数据
            $data = unserialize($data);
            $mode = &$data['mode'];
            self::$cookie = &$data['staticCookie'];
            unset($data['staticCookie'], $data['mode']);

            //OF_URL 的无参数地址无需请求
            if (strncmp($data['url'], OF_URL, strlen(OF_URL)) || strpos($data['url'], '=')) {
                $index = &of_base_com_net::request($data['url'], $data);
            } else {
                $index = array('state' => true);
            }

            //字符串 或 数组
            if ($mode !== true) {
                //创建方法
                is_string($mode) && strpos($mode, ';') && $mode = create_function('&$_', $mode);
                //函数回调
                of::callFunc($mode, $index);
            }

            return $url;
        }

        //同步请求
        if ($mode === false) {
            //post格式化
            if (isset($data['post'])) {
                $data['type'] = 'POST';
                $data['data'] = &$data['post'];
            }

            //参数初始化
            $data += array('get' => '', 'data' => '', 'cookie' => '', 'header' => '', 'timeout' => 10);
            //格式化get参数
            is_array($data['get']) && $data['get'] = http_build_query($data['get']);
            //格式化post参数
            is_array($data['data']) && $data['data'] = http_build_query($data['data']);
            //格式化header参数
            is_array($data['header']) && $data['header'] = join("\r\n", $data['header']);

            //解析目标网址
            $data['url'] = parse_url($url);
            //解析到域名
            if (isset($data['url']['host'])) {
                //初始路径
                isset($data['url']['path']) || $data['url']['path'] = '/';
                //外网地址
                if ($data['url']['host'] !== self::$params['host']) {
                    //格式化协议
                    $data['url']['scheme'] = isset($data['url']['scheme']) ? strtolower($data['url']['scheme']) : 'http';
                    //初始化接口
                    isset($data['url']['port']) || $data['url']['port'] = $data['url']['scheme'] === 'https' ? 443 : 80;
                }
            }
            //补全参数
            $data['url'] += self::$params;
            //合并get参数
            $data['url']['query'] .= ($data['url']['query'] === '' || $data['get'] === '' ? '' : '&') . $data['get'];

            //cookie整合
            if ($data['cookie']) {
                is_array($data['cookie']) && $data['cookie'] = http_build_query($data['cookie'], '', '; ');
                $data['cookie'] = explode('; ', $data['cookie']);
                foreach ($data['cookie'] as &$v) {
                    $temp = explode('=', $v, 2);
                    self::cookie(array(
                        'domain' => &$data['url']['host'],
                        //直传的cookie设置为"/"
                        'path'   => '/',
                        'name'   => &$temp[0],
                        'value'  => &$temp[1],
                        'encode' => false
                    ));
                }
            }
            //读取cookie
            $data['cookie'] = self::cookie(array(
                'domain' => &$data['url']['host'],
                'path'   => of_base_com_str::realpath('/' . $data['url']['path'] . 'a/../')
            ));
        //准备二次请求
        } else {
            //二次请求模式
            $data['mode'] = $mode;
            //保存请求网址
            $data['url'] = $url;
            //静态cookie
            $data['staticCookie'] = &self::$cookie;
            //操作系统类型
            $osType = strtolower(substr(PHP_OS, 0, 3));

            //web是否支持命令操作
            if (!isset($config['isExec'])) {
                //类linux系统 && 非安全模式 && 函数启用
                $config['isExec'] = PHP_SAPI === 'cli' || (
                    $osType !== 'win' &&
                    !ini_get('safe_mode') &&
                    !preg_match('@\bpopen\b@', ini_get('disable_functions'))
                );
            }

            //命令行操作
            if ($config['isExec']) {
                //响应结果
                $res = array('state' => true);
                //执行参数
                $exec = array(
                    'php',
                    OF_DIR . '/index.php',
                    'get:a=request&c=of_base_com_net'
                );

                //Windows
                if ($osType === 'win') {
                    //win 异步数据结构
                    $exec[] = 'data:' . str_replace('"', '""', serialize($data));

                    if (empty($config['exeDir'])) {
                        $temp = 'wmic process where processid=' . getmypid() . ' get ExecutablePath';

                        //通道打开
                        if ($pp = popen($temp, 'r')) {
                            $temp = explode("\n", fread($pp, 2048));
                            $config['exeDir'] = trim($temp[1]);
                            pclose($pp);
                        //执行错误
                        } else {
                            trigger_error($temp = "Command error: {$temp}");
                            //状态,内容
                            $res = array('state' => false, 'errno' => 1, 'errstr' => &$temp);
                        }
                    }

                    if (isset($config['exeDir'])) {
                        //真实php执行文件路径
                        $exec[0] = &$config['exeDir'];
                        //真实执行命令
                        $exec = str_replace('"', '""', '"' . join('" "', $exec) . '"');
                        //异步执行命令
                        $exec = "SET data=\"{$exec}\" && cscript //E:vbscript \"" .
                            strtr(OF_DIR, '/', '\\') . '\accy\com\net\asyncProc.bin"';

                        //兼容win php < 5.3
                        version_compare(PHP_VERSION, '5.3.0', '<') && $exec = '"' . $exec . '"';
                    }
                //类 linux
                } else {
                    //linux 异步数据结构
                    $exec[] = 'data:' . addslashes(serialize($data));
                    //exec("ls -l /proc/{$pid}/exe", $output, $state);
                    $exec = 'nohup "' . join('" "', $exec) . '" >/dev/null 2>&1 &';
                }

                is_string($exec) && pclose(popen($exec, 'r'));
                return $res;
            } else {
                $mode = true;
                $data = array(
                    'type'    => 'POST',
                    'data'    => serialize($data), 
                    'header'  => '',
                    'cookie'  => '', //session_name() .'='. session_id() . (isset($_SERVER['HTTP_COOKIE']) ? '; ' . $_SERVER['HTTP_COOKIE'] : ''), 
                    'timeout' => 30
                );

                $data['url'] = $config['asUrl'];
                $data['url']['path'] = OF_URL . '/index.php';
                $data['url']['query'] = 'a=request&c=of_base_com_net';
                //数据校验
                $temp = of_base_com_str::uniqid();
                of_base_com_kv::set(
                    $asMd5 = 'of_base_com_net::' . $temp, md5($data['data']), 300, $config['kvPool']
                );
                $data['url']['query'] .= '&md5=' . $temp;
            }
        }

        //创建连接
        $fp = fsockopen(
            ($data['url']['scheme'] === 'https' ? 'ssl://' : '') . $data['url']['host'], 
            $index = &$data['url']['port'], $errno, $errstr, $data['timeout']
        );
        //连接成功
        if ($fp) {
            //请求类型
            $data['type'] = empty($data['type']) ? 'GET' : strtoupper($data['type']);
            //自定请求头
            $data['header'] = trim($data['header'], "\r\n");
            //简单标准化处理 https(443) 和 http(80) 默认不传端口
            $temp = $index === 443 || $index === 80 ? '' : ':' . $index;

            //组合请求数据
            $out[] = $data['type'] . " {$data['url']['path']}?{$data['url']['query']} HTTP/1.1";
            $out[] = 'Host: ' . $data['url']['host'] . $temp;
            //禁止缓存
            $out[] = 'Connection: Close';
            //禁止压缩
            $out[] = 'Accept-Encoding: none';
            //post数据长度
            $out[] = 'Content-Length: ' . strlen($data['data']);
            //支持的 MIME 类型
            preg_match('@^Accept *:@im', $data['header']) || $out[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
            //报文类型
            preg_match('@^Content-Type *:@im', $data['header']) || $out[] = 'Content-Type: application/x-www-form-urlencoded';
            //发送cookie
            $data['cookie'] && $out[] = 'Cookie: ' . $data['cookie'];
            //会替换下面的默认值
            $data['header'] && $out[] = &$data['header'];
            //使用的浏览器
            $out[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:26.0) Gecko/20100101 Firefox/26.0';
            $out[] = '';
            //post数据
            $out[] = &$data['data'];

            fwrite($fp, join("\r\n", $out));

            $res = null;
            //同步调用
            if ($mode === false) {
                $index = &$res['header'];
                //读取响应
                while ($temp = fgets($fp, 2048)) {
                    $index[] = $temp;
                    if (!isset($res['response']) && $temp === "\r\n") {
                        $index = &$res['response'];
                    }
                }

                //响应头, empty=可能掉线或被GFW屏蔽地址
                if (empty($res['header'])) {
                    $res = array('header' => ' 503 ', 'response' => 'Internet failure');
                } else {
                    $res['header'] = join($res['header']);
                    $res['response'] = empty($res['response']) ? '' : join($res['response']);
                }

                //chunk传输
                if (preg_match('@Transfer-Encoding:\s*chunked@', $res['header'])) {
                    //chunk还原
                    $res['response'] = &self::dechunk($res['response']);
                }

                //请求成功
                if (preg_match('/.* (\d+) .*/', $res['header'], $temp) && $temp[1] < 400) {
                    preg_match_all('@^Set-Cookie: ([^=\s]+)=(.*?)(?:; .*|$)$@m', $res['header'], $match, PREG_SET_ORDER);
                    //提取cookie
                    foreach ($match as &$v) {
                        preg_match('@expires=(.*?)(?:; |$)@', $v[0], $v['expires']);
                        preg_match('@path=(.*?)(?:; |$)@', $v[0], $v['path']);
                        preg_match('@domain=(.*?)(?:; |$)@', $v[0], $v['domain']);

                        //记忆cookie
                        self::cookie(array(
                            'domain'  => isset($v['domain'][1]) ? $v['domain'][1] : $data['url']['host'],
                            'path'    => isset($v['path'][1]) ? 
                                trim($v['path'][1]) : of_base_com_str::realpath('/' . $data['url']['path'] . 'a/../'),
                            'name'    => &$v[1],
                            'value'   => &$v[2],
                            'expires' => &$v['expires'][1],
                            'encode'  => false
                        ));
                    }
                    $res['state'] = true;
                //状态码错误
                } else {
                    $res = array('state' => false, 'errno' => &$temp[1], 'errstr' => &$res['response']);
                }
            //开始二次请求
            } else {
                //等待异步端数据接收成功
                for ($i = 60; --$i;) {
                    usleep(50000);
                    if (!of_base_com_kv::get($asMd5, false, $config['kvPool'])) {
                        break ;
                    }
                }
                $res['state'] = true;
            }

            //关闭连接
            fclose($fp);
        //连接失败
        } else {
            //状态,内容
            $res = array('state' => false, 'errno' => &$errno, 'errstr' => &$errstr);
        }

        return $res;
    }

    /**
     * 描述 : 还原chunk数据
     * 参数 :
     *     &str : 指定解码字符串
     * 返回 :
     *      返回还原的数据,失败返回false
     * 作者 : Edgar.lee
     */
    public static function &dechunk(&$str) {
        $eol = "\r\n";
        //当前偏移量
        $offset = 0;
        //返回结果集
        $result = false;

        while (($nowPos = strpos($str, $eol, $offset)) !== false) {
            //有效数字
            if (is_numeric($len = hexdec(substr($str, $offset, $nowPos - $offset)))) {
                $result[] = substr($str, $nowPos + 2, $len);
                //更新偏移量
                $offset = $len + $nowPos + 2;
            //解析出错
            } else {
                //解码失败
                $result = false;
                break;
            }
        }

        is_array($result) && $result = join($result);
        return $result; 
    }

    /**
     * 描述 : 设置读取请求站的cookie
     * 参数 :
     *      config : {
     *          "domain" :*指定权限域名
     *          "path"   :*指定有效路径
     *          "name"   : empty=读取有效cookie, 字符串=设置cookie
     *          "value"  : null=删除name, 字符串=设置name
     *          "expire" : empty(默认)=不过期, value的过期时间格式
     *          "encode" : 是否对value进行 RFC 1738 编码,默认=true
     *      }
     * 返回 :
     *      
     * 注明 :
     *      列表结构(cookie) : {
     *          反转的域名 : {
     *              有效路径 : {
     *                  cookie键 : {
     *                      "value"  : cookie值
     *                      "expire" : 过期时间戳
     *                  }
     *              }
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function cookie($config) {
        //引用cookie
        $cookie = &self::$cookie;

        //是数组 && 域名有效 && 路径有效
        if (is_array($config) && isset($config['domain']) && isset($config['path'])) {
            //反转域名
            $domain = strrev($config['domain']);
            //路径格式化
            $path   = substr($config['path'], -1) === '/' ? $config['path'] : $config['path'] . '/';

            //读取 cookie
            if (empty($config['name'])) {
                //当期时间戳
                $time = time();
                //有效数据
                $result = array();
                //达到根域在上子域在下的效果
                ksort($cookie);

                foreach ($cookie as $kd => &$vd) {
                    //有效域名
                    if (strncasecmp($kd, $domain, strlen($kd)) === 0) {
                        //根路径在上,子路径在下
                        ksort($vd);

                        foreach ($vd as $kp => &$vp) {
                            //读取根路径数据
                            if (strncasecmp($kp, $path, strlen($kp)) === 0) {
                                foreach ($vp as $k => &$v) {
                                    //数据过期
                                    if (is_numeric($v['expire']) && $v['expire'] < $time) {
                                        unset($cookie[$domain][$kp][$k]);
                                    } else {
                                        $result[$k] = $k .'='. $v['value'];
                                    }
                                }
                            }
                        }
                    }
                }

                return join('; ', $result);
            //设置 cookie
            } else if (isset($config['value'])) {
                $cookie[$domain][$path][$config['name']] = array(
                    //编码 value
                    'value'  => !isset($config['encode']) || $config['encode'] ?
                        urlencode($config['value']) : $config['value'],
                    //时间戳
                    'expire' => empty($config['expire']) ?
                        false : (int)(is_numeric($config['expire']) ? $config['expire'] : strtotime($config['expire']))
                );
            //删除 cookie
            } else {
                unset($cookie[$domain][$path][$config['name']]);
            }
        }
    }
}
of_base_com_net::init();
return true;