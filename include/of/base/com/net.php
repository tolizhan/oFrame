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
        //当前请求参数
        $params = &self::$params;

        //反向代理
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $params = parse_url($_SERVER['HTTP_ORIGIN']);
        //常规请求
        } else {
            $temp = empty($_SERVER['HTTP_HOST']) ?
                array('127.0.0.1') : explode(':', $_SERVER['HTTP_HOST']);
            $params = array(
                'scheme' => empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ?
                    'http' : 'https',
                'host'   => &$temp[0]
            );
            isset($temp[1]) && $params['port'] = $temp[1];
        }

        //添加端口, 路径, 参数
        $params += array(
            'port'   => $params['scheme'] === 'http' ? 80 : 443,
            'path'   => $_SERVER['SCRIPT_NAME'],
            'query'  => ''
        );

        //读取网络请求配置
        $temp = of::config('_of.com.net', array()) + array(
            'async'  => '',
            'kvPool' => 'default'
        );
        //校验模式
        $temp['asUrl'] = strpos($temp['async'], '://') ?
            //异步请求地址
            parse_url($temp['async']) + array('port' => 80) : $params;
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
     *          "header"  : 自定义头信息(''), 可数组[不换行头, ...]
     *          "cookie"  : cookie数据(''),
     *          "get"     : get数据(''),
     *          "post"    : post数据,相当于type=POST, data=当期值(''),
     *          "file"    : 上传文件,默认设置type=POST, 结构为 [{
     *              "name" : 附件字段名
     *              "path" : 文件磁盘路径, 设置 data 不用设置此值
     *              "data" : 文件二进制数据, 设置 path 时不用设置此值
     *              "mime" : 附件类型('application/octet-stream')
     *              "head" : 自定义头信息(''), 可数组[不换行头, ...]
     *              "file" : 附件文件名, 默认使用 path 文件名或生成'xx.bin'
     *          }, ...]
     *          "timeout" : 超时时间(10s),
     *              数字=连接超时
     *              数组=[连接超时(10), 响应超时(default_socket_timeout)],
     *          "context" : 配置上下文, 默认={
     *              "ssl" : 关闭 ssl 证书验证 {
     *                  "verify_peer_name" : false
     *              }
     *          }
     *      }
     *      mode : 提交模式,
     *          false(默认) = 同步提交,
     *          true        = 无结果异步提交,
     *          回调结构    = 符合 of::callFunc 结构(不能带递资源参数), 接收请求的响应结果
     * 返回 :
     *      失败时 : {state:false, errno:失败码, errstr:错误描述}
     *      成功时 : {state:true, header:响应头, response:响应数据}
     * 注明 :
     *      异步请求参数结构($data) : {
     *          含同步请求结构 : 数组结构均转成字符串
     *          "mode"         : 回调函数
     *          "staticCookie" : 所有请求过程中记忆的cookie
     *      }
     * 作者 : Edgar.lee
     */
    public static function &request($url = null, $data = array(), $mode = false) {
        //配置引用
        $config = &self::$config;

        //二次请求
        if ($url === null) {
            //永不超时
            ini_set('max_execution_time', 0);

            if (PHP_SAPI === 'cli') {
                $data = $GLOBALS['_ARGV']['data'];
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
            $_SERVER['REMOTE_ADDR'] = &$data['remoteAddr'];
            unset($data['staticCookie'], $data['remoteAddr'], $data['mode']);

            //OF_URL 的无参数地址无需请求
            if (
                strncmp($data['url'], OF_URL, strlen(OF_URL)) ||
                strpos($data['url'], '=')
            ) {
                $index = &of_base_com_net::request($data['url'], $data);
            } else {
                $index = array('state' => true);
            }

            //函数回调
            $mode === true || of::callFunc($mode, $index);
            return $url;
        }

        //同步请求
        if ($mode === false) {
            if (
                //post格式化
                ($temp = isset($data['post'])) ||
                //上传文件
                empty($data['type']) && !empty($data['file'])
            ) {
                $data['type'] = 'POST';
                $temp && $data['data'] = &$data['post'];
            }

            //参数初始化
            $data += array(
                'get' => '', 'data' => '', 'cookie' => '',
                'header' => '', 'timeout' => array()
            );
            //格式化超时设置[连接超时(10), 请求超时(default_socket_timeout)]
            $data['timeout'] = (array)$data['timeout'] + array(10);
            //格式化get参数
            is_array($data['get']) && $data['get'] = http_build_query($data['get']);
            //格式化post参数
            is_array($data['data']) && $data['data'] = http_build_query($data['data']);
            //格式化header参数
            is_array($data['header']) && $data['header'] = join("\r\n", $data['header']);

            //解析目标网址
            $index = &$data['url'];
            $index = parse_url($url);
            //解析到域名
            if (isset($index['host'])) {
                //初始路径
                isset($index['path']) || $index['path'] = '/';
                //格式化协议
                $index['scheme'] = isset($index['scheme']) ?
                    strtolower($index['scheme']) : (
                        $index['host'] === self::$params['host'] ?
                            self::$params['scheme'] : 'http'
                    );
                //初始化接口
                isset($index['port']) ||
                    $index['port'] = $index['scheme'] === 'https' ? 443 : 80;
            }
            //补全参数
            $index += self::$params;
            //合并get参数
            $index['query'] .= ($index['query'] === '' || $data['get'] === '' ? '' : '&') . $data['get'];

            //cookie整合
            if ($data['cookie']) {
                is_array($data['cookie']) && $data['cookie'] = http_build_query($data['cookie'], '', '; ');
                $data['cookie'] = explode('; ', $data['cookie']);
                foreach ($data['cookie'] as &$v) {
                    $temp = explode('=', $v, 2);
                    self::cookie(array(
                        'domain' => &$index['host'],
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
                'domain' => &$index['host'],
                'path'   => of_base_com_str::realpath('/' . $index['path'] . 'a/../')
            ));
        //准备二次请求
        } else {
            //二次请求模式
            $data['mode'] = $mode;
            //保存请求网址
            $data['url'] = $url;
            //静态cookie
            $data['staticCookie'] = &self::$cookie;
            //记录当前IP地址
            $data['remoteAddr'] = &$_SERVER['SERVER_ADDR'];
            //操作系统类型(WINNT:windows, Darwin:mac, 其它:linux)
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
                    'get:a=request&c=of_base_com_net',
                    '_tz:' . date_default_timezone_get(),
                    '_ip:' . $_SERVER['SERVER_ADDR'],
                    '_rl:' . ROOT_URL
                );

                //Windows
                if ($osType === 'win') {
                    //win 异步数据结构
                    $exec[] = 'data:' . str_replace('"', '\"', serialize($data));

                    if (empty($config['exeDir'])) {
                        $temp = 'wmic process where processid=' . getmypid() . ' get ExecutablePath';

                        //通道打开
                        if ($pp = popen($temp, 'r')) {
                            $temp = explode("\n", stream_get_contents($pp, 2048));
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
                    //异步前缀, 是mac系统 || linux 使用 nohup
                    $aPre = $osType === 'dar' ? '' : 'nohup ';
                    //校验有效命令
                    $check = $aPre . 'php -r "echo 1;" 0>/dev/null 2>&1';

                    //命令校验成功
                    if (($temp = fgets(popen($check, 'r'))) === '1') {
                        //linux 异步数据结构
                        $exec[] = 'data:' . addcslashes(serialize($data), '`"\$');
                        //拼成异步命令
                        $exec = $aPre . '"' . join('" "', $exec) . '" >/dev/null 2>&1 &';
                    //命令校验失败
                    } else {
                        trigger_error($temp = "Command error: {$temp}");
                        //状态,内容
                        $res = array('state' => false, 'errno' => 1, 'errstr' => &$temp);
                    }
                }

                //管道执行命令
                is_string($exec) && pclose(popen($exec, 'r'));
                return $res;
            } else {
                $mode = true;
                $data = array(
                    'type'    => 'POST',
                    'data'    => serialize($data), 
                    'header'  => '',
                    'cookie'  => '',
                    'timeout' => array(30)
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
        $fp = stream_socket_client(
            //请求路径
            ($data['url']['scheme'] === 'https' ? 'ssl://' : '') .
                $data['url']['host'] . ':' . ($index = &$data['url']['port']),
            //基础参数
            $errno, $errstr, $data['timeout'][0], STREAM_CLIENT_CONNECT,
            //配置上下文
            stream_context_create(
                (isset($data['context']) ? $data['context'] : array()) + array(
                    'ssl' => array(
                        'verify_peer_name' => false
                    )
                )
            )
        );
        //连接成功
        if ($fp) {
            //设置内存不溢出
            $memory = ini_set('memory_limit', -1);
            //请求类型
            $data['type'] = empty($data['type']) ? 'GET' : strtoupper($data['type']);
            //自定请求头
            $data['header'] = trim($data['header'], "\r\n");
            //简单标准化处理 https(443) 和 http(80) 默认不传端口
            $port = $index === 443 || $index === 80 ? '' : ':' . $index;
            //附件分界线
            $line = '----ofFormBoundaryTCG418T3MwECCs03----';

            //写入报文数据
            if (empty($data['file'])) {
                $body = &$data['data'];
            //上传附件
            } else {
                //发送文本报文
                if ($data['data']) {
                    $temp = str_replace('=', "\"\r\n\r\n", $data['data']);
                    $temp = 'Content-Disposition: form-data; name="' . str_replace(
                        '&',
                        "\r\n--{$line}\r\nContent-Disposition: form-data; name=\"",
                        $temp
                    );
                    $body[] = "--{$line}\r\n{$temp}";
                }

                //发生附件报文
                foreach ($data['file'] as $k => &$v) {
                    //初始化文件名
                    empty($v['file']) && $v['file'] = isset($v['path']) ? 
                        pathinfo($v['path'], PATHINFO_BASENAME) :
                        'file' . $k . '.bin';
                    //初始化附件类型
                    empty($v['mime']) && $v['mime'] = 'application/octet-stream';
                    //初始化自定义头
                    empty($v['head']) && $v['head'] = '';
                    is_array($v['head']) && $v['head'] = join("\r\n", $v['head']);
                    ($v['head'] = trim($v['head'], "\r\n")) && $v['head'] .= "\r\n";

                    //发生字段头
                    $body[] = "--{$line}\r\nContent-Disposition: form-data; " .
                        "name=\"{$v['name']}\"; filename=\"{$v['file']}\"\r\n" .
                        "Content-Type: {$v['mime']}\r\n{$v['head']}";

                    //发送字段报文
                    if (isset($v['data'])) {
                        $body[] = &$v['data'];
                    //发生文件流
                    } else {
                        //文件流
                        $body[] = &of_base_com_disk::file($v['path']);
                    }
                }

                //合并报文数据
                $body[] = '--' . $line . '--';
                $body = join("\r\n", $body);
            }

            //组合请求数据
            $out[] = $data['type'] . " {$data['url']['path']}?{$data['url']['query']} HTTP/1.1";
            $out[] = 'Host: ' . $data['url']['host'] . $port;
            //禁止缓存
            $out[] = 'Connection: Close';
            //禁止压缩
            $out[] = 'Accept-Encoding: none';
            //post数据长度
            $out[] = 'Content-Length: ' . strlen($body);
            //支持的 MIME 类型
            preg_match('@^Accept *:@im', $data['header']) || $out[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
            //默认报文类型
            if (preg_match('@^Content-Type *:@im', $data['header']) === 0) {
                $out[] = empty($data['file']) ? 
                    'Content-Type: application/x-www-form-urlencoded' :
                    'Content-Type: multipart/form-data; boundary=' . $line;
            }
            //发送cookie
            $data['cookie'] && $out[] = 'Cookie: ' . $data['cookie'];
            //会替换下面的默认值
            $data['header'] && $out[] = &$data['header'];
            //使用的浏览器
            $out[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:26.0) Gecko/20100101 Firefox/26.0';
            $out[] = '';
            //报文数据
            $out[] = &$body;

            fwrite($fp, join("\r\n", $out));

            //释放大内存
            unset($out, $body);
            //恢复内存设置
            ini_set('memory_limit', $memory);
            //初始化响应数据
            $res = null;

            //同步调用
            if ($mode === false) {
                //引用头数据
                $index = &$res['header'];

                //设置请求超时
                if (isset($data['timeout'][1])) {
                    stream_set_timeout($fp, $data['timeout'][1]);
                }

                //读取响应
                while ($temp = fgets($fp, 2048)) {
                    $index[] = $temp;
                    if (!isset($res['response']) && $temp === "\r\n") {
                        $index = &$res['response'];
                    }
                }

                //判断超时
                $meta = stream_get_meta_data($fp);

                //响应头, 超时 || empty=可能掉线或被GFW屏蔽地址
                if ($meta['timed_out'] || empty($res['header'])) {
                    $res = array('header' => ' 503 ', 'response' => 'Internet failure');
                } else {
                    $res['header'] = join($res['header']);
                    $res['response'] = empty($res['response']) ? '' : join($res['response']);
                }

                //chunk传输
                if (preg_match('@Transfer-Encoding:\s*chunked@i', $res['header'])) {
                    //chunk还原
                    $res['response'] = &self::dechunk($res['response']);
                }

                //请求成功
                if (preg_match('/.* (\d+) .*/', $res['header'], $temp) && $temp[1] < 400) {
                    preg_match_all('@^Set-Cookie: ([^=\s]+)=(.*?)(?:;.*)?\r$@mi', $res['header'], $match, PREG_SET_ORDER);
                    //提取cookie
                    foreach ($match as &$v) {
                        $v[0] = substr($v[0], 0, -1);
                        preg_match('@expires=(.*?)(?:;|$)@i', $v[0], $v['expires']);
                        preg_match('@path=(.*?)(?:;|$)@i', $v[0], $v['path']);
                        preg_match('@domain=(.*?)(?:;|$)@i', $v[0], $v['domain']);

                        //记忆cookie
                        self::cookie(array(
                            'domain'  => isset($v['domain'][1]) ? $v['domain'][1] : $data['url']['host'],
                            'path'    => isset($v['path'][1]) ? 
                                $v['path'][1] : of_base_com_str::realpath('/' . $data['url']['path'] . 'a/../'),
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