<?php
/**
 * 描述 : 常驻化工具集 php >= 7.1 swoole >= 4.4
 * 说明 :
 *      方法集成归类
 *          协议封装 : stream_open stream_stat stream_read stream_eof stream_set_option
 *          变量隔离 : __construct __set getVar shareVar varMaps $_GLOBAL_SCOPE_
 *          协程隔离 : loadClass loadEnv reinit clear fork autoClass errCtrl dispatch checkExit
 *          服务支撑 : data task space serial yield sentry getBitSize start test http
 *          代码重写 : codeParser codeKey codeHard

 *      已接管会话方法
 *          session_start : 启动新会话或者重用现有会话
 *          session_commit : session_write_close 的别名
 *          session_write_close : 写入会话数据并结束会话
 *          session_destroy : 销毁一个会话中的全部数据
 *          session_unset : 释放所有的会话变量
 *          session_abort : 放弃会话数组更改并完成会话
 *          session_reset : 使用原始值重新初始化会话数组
 *          session_decode : 解码会话数据
 *          session_encode : 将当前会话数据编码为字符串
 *          session_regenerate_id : 使用新生成的会话 ID 更新现有会话 ID
 *          session_gc : 执行会话数据垃圾收集
 *          session_id : 获取/设置当前会话 ID
 *          session_name : 读取/设置会话名称
 *          session_status : 返回当前会话状态
 *      已接管头方法
 *          header : 发送原生 HTTP 头
 *      已接管cookie方法
 *          setcookie : 发送 Cookie
 *          setrawcookie : 发送未经 URL 编码的 cookie
 *      已接管输出控制方法
 *          ob_clean : 清空（擦掉）输出缓冲区
 *          ob_end_clean : 清空（擦除）缓冲区并关闭输出缓冲
 *          ob_end_flush : 冲刷出（送出）输出缓冲区内容并关闭缓冲区
 *          ob_flush : 冲刷出（送出）输出缓冲区中的内容
 *          ob_get_clean : 得到当前缓冲区的内容并删除当前输出缓冲区
 *          ob_get_contents : 返回输出缓冲区的内容
 *          ob_get_flush : 刷新输出缓冲区，将其作为字符串返回并关闭输出缓冲区
 *          ob_get_length : 返回输出缓冲区内容的长度
 *          ob_get_level : 返回输出缓冲机制的嵌套级别
 *          ob_get_status : 得到所有输出缓冲区的状态
 *          ob_list_handlers : 列出所有使用的输出处理程序
 *      已接管的加载类方法
 *          spl_autoload_functions : 返回所有已注册的 __autoload() 函数
 *          spl_autoload_register : 注册指定的函数作为 __autoload 的实现
 *          spl_autoload_unregister : 注销已实现的 __autoload() 函数
 *      已接管的错误处理方法
 *          debug_backtrace : 产生一条回溯跟踪(backtrace)
 *          error_clear_last : 清除最近一次错误
 *          error_get_last : 获取最后发生的错误
 *          error_reporting : 设置应该报告何种 PHP 错误
 *          restore_error_handler : 还原之前的错误处理函数
 *          restore_exception_handler : 恢复之前定义过的异常处理函数
 *          set_error_handler : 设置用户自定义的错误处理函数
 *          set_exception_handler : 设置用户自定义的异常处理函数
 *      已接管的信号处理方法(目前实现了SIGTERM"15"信号的处理)
 *          pcntl_signal : 安装信号处理程序
 *          pcntl_signal_dispatch : 调用等待信号的处理程序
 *      已接管时间相关方法
 *          set_time_limit : 设置脚本最大执行时间
 *          ini_set(max_execution_time) : 设置脚本最大执行时间
 *          ini_get(max_execution_time) : 读取脚本最大执行时间
 *          sleep: 以指定的秒数延迟执行
 *          usleep: 以指定的微秒数延迟执行
 *      已接管文件流相关方法
 *          file_get_contents : 将整个文件读入一个字符串
 *          file_put_contents : 将数据写入文件
 *          fopen : 打开文件或者 URL
 *          flock : 可移植的协同文件锁定
 *      已接管其它方法
 *          php_sapi_name : 固定返回fpm-fcgi
 *          define : 定义一个常量
 *          register_shutdown_function : 注册在关闭时执行的函数
 *          class_alias : 为类创建别名
 *          memory_get_usage : 返回分配给 PHP 的内存量
 *          get_included_files : 返回被 include 和 require 文件名的 array
 *          get_required_files : 别名 get_included_files()

 *      协程隔离列表($attrs)结构 : loadEnv 方法初始化 {
 *          协程ID : {
 *              "state" : 协程状态变量 {
 *                  "code"    : 执行状态码,
 *                      0=默认
 *                      1=已初始化reinit()
 *                      2=追加初始化reinit()
 *                      4=在错误中errCtrl()
 *                      8=被动退出checkExit()
 *                  "pCid"    : 父协程ID, 不存在为0, 存在>0,
 *                  "exeTime" : 协程超时时间 [超时秒数, 设置时间 + 超时秒数],
 *                  "memory"  : 限制内存[设置数据ini_get('memory_limit'), 转换的int数据, 上次检查时间, 上次内存大小]
 *              }
 *              "class" : 类隔离静态数据 {
 *                  归属类名 : 同全局静态数据,
 *                  ...
 *              }
 *              "share" : 全局静态变量 {
 *                  变量位置 : {
 *                      变量键 : 变量值,
 *                      ...
 *                  }, ...
 *              }
 *              "super" : 超全局隔离变量 {
 *                  超全局变量键 : 超全局变量值,
 *                  ...
 *              }
 *              "autoC" : 自动加载类 [
 *                  回调方法,
 *                  ...
 *              ]
 *              "error" : 错误处理数据 {
 *                  "level" : 错误等级 [当前等级, @符压入上次等级, ...]
 *                  "last"  : 最后一次错误
 *                  "eList" : 错误回调列表, 0为当前, 以此类推 [
 *                      [回调, 等级], ...
 *                  ]
 *                  "tList" : 异常回调列表, 0为当前, 以此类推 [
 *                      [回调, 等级], ...
 *                  ]
 *                  "catch" : 异常捕捉列表, 0为当前, 以此类推 [{
 *                      "mark" : 异常块标识
 *                      "eNum" : try之前@控制符数量
 *                      "iNum" : try之前include层数
 *                  }, ...]
 *              }
 *              "signo" : 系统信号列表 {
 *                  信号编码 {
 *                      "call" : 回调方法
 *                  }
 *              }
 *              "sInfo" : 会话数据 {
 *                  "state" : 会话状态, 1=关闭, 2=开启
 *                  "sesId" : 会话状态ID, '',
 *                  "sInis" : 会话配置, []
 *              }
 *              "heads" : 响应头列表 {
 *                  小写头键 : [头值, ...]
 *              }
 *              "halts" : 结束回调列表 [
 *                  [回调列表, 回调参数], ...
 *              ]
 *          }, ...
 *      }

 *      协程挂起任务($yield) : yield与sentry方法使用 {
 *          "state" : 状态信息 {
 *              "code"  : 0=哨兵停止, 1=哨兵启动, 2=存在任务
 *              "space" : 哨兵协程ID
 *          }
 *          "flock" : 文件锁 {
 *              协程ID : {
 *                  "state"   : true=未完成加锁, false=已完成
 *                  "data"    : 锁数据 {
 *                      "stream"  : 锁资源,
 *                      "operate" : 持久锁模式 | 4,
 *                      "block"   :&阻塞标记
 *                  }
 *                  "result"  : 加锁结果
 *              }
 *          }
 *          "async" : 新建协程 {
 *              唯一编码 : 任务回调闭包
 *          }
 *          "resCo" : 响应中的请求, 最大100 {
 *              协程ID : {}
 *          }
 *          "waits" : 排队中的请求, 当resCo不足时, 移动到resCo中 {
 *              协程ID : {
 *                  "state"   : true=未完成, false=已完成
 *                  "data"    : {}
 *                  "result"  : 响应结果
 *              }
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class swoole {
    //流文件信息    #协议封装对象
    private $stat;
    //流指针位置
    private $seek = 0;
    //流改写代码
    private $code;
    //流配置资源
    private $context;
    //流缓存代码, {文件路径 : {"stat" : 文件信息, "code" : 改写的代码}}
    private static $cacheCode = array();

    //全局静态类名  #类静态变量对象
    private $cName;
    //全局静态数据, {静态键 : 静态值, ...}
    private $gAttr;

    //协程隔离ID    #接管方法对象
    private $space = 0;
    //标准输入字符串
    private $input = '';
    //swoole协程响应对象
    private $swRes;
    //动态变量映射列表, 当读取协程内存时, memoryGetUsage方法会使用
    private $vMaps = null;

    //协程隔离列表
    private static $attrs = array();
    //工作是否开始退出
    private static $isEnd = false;
    //串行嵌套层级, include require
    private static $incNo = 0;
    //指定协程空间, null=默认空间, int=指定空间
    private static $onCid;

    //协程挂起任务
    private static $yield = array(
        'state' => array('code' => 1, 'space' => 0),
        'flock' => array(), 'async' => array(),
        'resCo' => array(), 'waits' => array()
    );
    //最近时间戳, 每10s更新
    private static $nTime = 0;
    //代码调试, 1=重写代码
    private static $debug = 0;

    /**
     * 描述 : 打开文件流
     * 作者 : Edgar.lee
     */
    public function stream_open($path, $mode, $options, &$file) {
        //改写只读数据流协议
        if ($path === 'of.incl://input') {
            $this->code = self::$attrs[self::space()]['super']['_FUNC_mapVar']->input;
            $this->stat['size'] = strlen($this->code);
            return true;
        //协议://协程ID://是否全局://[eval ? 路径://代码 : '路径']
        } else {
            //"协程ID://"为防止报错 Failed to open stream: infinite recursion prevented
            $path = explode('://', $path, 5);
        }

        //执行字符串代码
        if (isset($path[4])) {
            //生成eval路径
            $file = "{$path[3]} : eval()'d code";
            //存储改写代码
            $code = '<?php ' . $path[4];
            //缓存标记
            $mark = md5($path[4]);
        //执行文件代码
        } else {
            $mark = $file = realpath($path[3]);
        }

        //存在缓存, 减少改写时间及内存占用量
        if (isset(self::$cacheCode[$mark])) {
            //改后信息
            $this->stat = &self::$cacheCode[$mark]['stat'];
            //改后代码
            $this->code = &self::$cacheCode[$mark]['code'];
            //返回成功
            return true;
        //加载并改写代码
        } else if ($file) {
            //文件加载失败
            if (!isset($code) && ($code = file_get_contents($file)) === false) {
                return false;
            }

            //改写代码
            self::codeParser($code, (int)$path[2], $ctrl);
            //统计代码长度
            $stat['size'] = strlen($code);
            //改后信息
            $this->stat = $stat;
            //改后代码
            $this->code = $code;

            //缓存代码
            if ($ctrl) {
                //删除代码块, 解决重复加载缓存占用额外编译内存问题, 查找到第一个结束位置
                while ($ePos = strpos($code, '/*!swoole rewrite: end!*/')) {
                    //从结束位置匹配最后一个开始位置
                    $sPos = strrpos($code, '/*!swoole rewrite: start!*/', $ePos - $stat['size']);
                    //提取代码块字符串
                    $temp = substr($code, $sPos, $eLen = $ePos - $sPos + 25);
                    //生成代码块对应行数
                    $temp = str_repeat("\n", substr_count($temp, "\n"));
                    //替换代码块为对应行数
                    $code = substr_replace($code, $temp, $sPos, $eLen);
                    //统计新代码长度
                    $stat['size'] = strlen($code);
                }
                //缓存代码
                self::$cacheCode[$mark] = array(
                    'stat' => &$stat,
                    'code' => &$code
                );
            }

            //返回结果
            return true;
        }
    }

    /**
     * 描述 : 读取流信息
     * 作者 : Edgar.lee
     */
    public function stream_stat() {
        return $this->stat;
    }

    /**
     * 描述 : 读取流数据
     * 作者 : Edgar.lee
     */
    public function stream_read($count) {
        $code = substr($this->code, $this->seek, $count);
        $this->seek += strlen($code);
        return $code;
    }

    /**
     * 描述 : 判断流结束
     * 作者 : Edgar.lee
     */
    public function stream_eof() {
        return $this->seek >= $this->stat['size'];
    }

    /**
     * 描述 : 设置流参数
     * 作者 : Edgar.lee
     */
    public function stream_set_option($option, $arg1, $arg2) {
        return true;
    }

    /**
     * 描述 : 初始类静态变量
     * 参数 :
     *      name : 变量归属的类名
     *      attr : 类的静态变量 {变量键 : 原始值, ...}
     * 作者 : Edgar.lee
     */
    public function __construct($name = '', $attr = array()) {
        $this->cName = $name;
        $this->gAttr = $attr;
    }

    /**
     * 描述 : include开始与结束, @符结束, stream配置资源时回调
     * 作者 : Edgar.lee
     */
    public function __set($key, $val) {
        switch ($key) {
            //禁用协程
            case 'incStart':
                //禁用协程
                self::serial(true);
                break;
            //恢复全协程
            case 'incEnd':
                self::serial(false);
                break;
            //恢复错误级别
            case 'errOff':
                //@符结束
                $index = &self::$attrs[$this->space]['error']['level'];
                isset($index[1]) && array_shift($index);
                break;
            //协议封装资源"context"
            case 'context':
                //stream流可以读取私有变量
                $this->context = $val;
                break;
            //加载回调结构类
            case 'callable':
                //静态调用转成数组
                is_string($val) && $val = explode('::', $val);
                //提取类名并尝试加载
                is_array($val) && isset($val[1]) && is_string($val[0]) && $this->loadClass($val[0]);
                break;
            //创建对象"newObj"
        }
    }

    /**
     * 描述 : 读取类隔离静态变量
     * 返回 :
     *      引用返回隔离静态变量
     * 作者 : Edgar.lee
     */
    public function &getVar() {
        //引用子属性 && 子属性初始
        ($index = &self::$attrs[self::space()]['class'][$this->cName]) || $index = $this->gAttr;
        //返回属性值
        return $index;
    }

    /**
     * 描述 : 共享变量隔离
     * 参数 :
     *      list : 变量列表 {变量名 : 变量值, ...}
     *      site : 变量位置, bool=全局变量, __METHOD__=方法静态变量
     * 返回 :
     *      变量列表
     * 作者 : Edgar.lee
     */
    public function &shareVar($list, $site = '') {
        //返回结果集
        $result = array();

        //全局变量
        if (is_bool($site)) {
            //引用隔离位置的变量
            $index = &self::$attrs[$this->space]['super']['GLOBALS_mapVar'];
            //遍历变量列表
            foreach ($list as $k => &$v) {
                //记录全局变量 ? 初始化全局变量 : 读取全局变量
                $site ? $index[$k] = &$v : $result[$k] = &$index[$k];
            }
        //静态变量
        } else {
            //引用隔离位置的变量
            ($index = &self::$attrs[$this->space]['share'][$site]) || $index = array();
            //遍历变量列表
            foreach ($list as $k => &$v) {
                //初始化隔离变量
                array_key_exists($k, $index) || $index[$k] = $v;
                //引用结果集
                $result[$k] = &$index[$k];
            }
        }

        return $result;
    }

    /**
     * 描述 : 对象动态属性映射回调方法
     * 参数 :
     *      vMaps : 对应映射列表 {
     *          属性名(含私有属性) : &属性值,
     *          ...
     *      }
     * 作者 : Edgar.lee
     */
    public function varMaps(&$vMaps) {
        $this->vMaps = &$vMaps;
    }

    /**
     * 描述 : 接管 session_start
     * 作者 : Edgar.lee
     */
    public function sessionStart($options = array()) {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //未启动
        if ($sInfo['state'] === 1) {
            //读取会话配置
            $sInis = &$sInfo['sInis'];
            //合并会话配置
            foreach ($options as $k => &$v) {
                $k = 'session.' . $k;
                isset($sInis[$k]) && $sInis[$k] = $v;
            }

            //打开session流
            if (of_base_session_base::open($sInis['session.save_path'], $sInis['session.name'])) {
                //引用cookie
                $cookie = &self::$attrs[$this->space]['super']['_COOKIE_mapVar'];
                //读取session ID
                if (!$sesId = &$sInfo['sesId']) {
                    //使用uuid
                    if (empty($cookie[$sInis['session.name']])) {
                        $sesId = $this->sessionCreateId();

                        //设置session cookie
                        $this->swRes && $this->swRes->cookie(
                            $sInis['session.name'], $sesId, $sInis['session.cookie_lifetime'],
                            $sInis['session.cookie_path'], $sInis['session.cookie_domain'],
                            $sInis['session.cookie_secure'], $sInis['session.cookie_httponly']
                        );
                    //从cookie中读取
                    } else {
                        $sesId = $cookie[$sInis['session.name']];
                    }
                }

                //标记会话已开启
                $sInfo['state'] = 2;
                //读取会话数据
                $data = of_base_session_base::read($sesId);
                //解码会话数据
                $this->sessionDecode($data);

                //启动会话清理
                rand(0, $sInis['session.gc_divisor'] / $sInis['session.gc_probability'] << 0) === 1 &&
                    of_base_session_base::gc();
                //结束回写
                if (empty($options['read_and_close'])) {
                    $this->registerShutdownFunction(array($this, 'sessionCommit'));
                //关闭会话
                } else {
                    //标记会话已关闭
                    $sInfo['state'] = 1;
                    //关闭会话
                    of_base_session_base::close();
                }

                //返回成功
                return true;
            } else {
                trigger_error(
                    'session_start(): Failed to initialize storage module: user (path: ' .
                    $sInis['save_path'].
                    ')'
                );
                exit;
            }
        } else {
            trigger_error('A session had already been started - ignoring session_start()');
        }

        //返回失败
        return false;
    }

    /**
     * 描述 : 接管 session_commit 和 session_write_close
     * 作者 : Edgar.lee
     */
    public function sessionCommit() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //回写会话数据
            of_base_session_base::write($sInfo['sesId'], $this->sessionEncode());
            //关闭会话
            of_base_session_base::close();
            //标记会话已关闭
            $sInfo['state'] = 1;
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_destroy
     * 作者 : Edgar.lee
     */
    public function sessionDestroy() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //标记会话已关闭
            $sInfo['state'] = 1;
            //回写会话数据
            of_base_session_base::write($sInfo['sesId'], '');
            //关闭会话
            of_base_session_base::close();
        } else {
            trigger_error('session_destroy(): Trying to destroy uninitialized session', E_USER_WARNING);
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_unset
     * 作者 : Edgar.lee
     */
    public function sessionUnset() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            self::$attrs[$this->space]['super']['_SESSION_mapVar'] = array();
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_abort
     * 作者 : Edgar.lee
     */
    public function sessionAbort() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //关闭会话
            of_base_session_base::close();
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_reset
     * 作者 : Edgar.lee
     */
    public function sessionReset() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //读取会话数据
            $data = of_base_session_base::read($sInfo['sesId']);
            //解码会话数据
            $this->sessionDecode($data);
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }
    /**
     * 描述 : 接管 session_create_id
     * 作者 : Edgar.lee
     */
    public function sessionCreateId($prefix = '') {
        //有前缀 && 格式校验未通过
        if ($prefix && preg_match('@^[a-z0-9,\-]+$@i', $prefix) === 0) {
            trigger_error('session_create_id(): Prefix cannot contain special characters. Only the A-Z, a-z, 0-9, "-", and "," characters are allowed', E_USER_WARNING);
        }

        //使用com_create_guid
        if (function_exists('com_create_guid')) {
            $sesId = strtolower(str_replace(array('{', '}', '-'), '', com_create_guid()));
        //生成唯一值
        } else {
            $sesId = md5(
                uniqid('', true) .
                json_encode(self::$attrs[$this->space]['super']['_SERVER_mapVar']) .
                mt_rand()
            );
        }

        //创建唯一会话ID
        return $sesId;
    }

    /**
     * 描述 : 接管 session_regenerate_id
     * 作者 : Edgar.lee
     */
    public function sessionRegenerateId($delSesId = false) {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //删除原会话数据 ? 调用销毁 : 调用提交
            $delSesId ? $this->sessionDestroy() : $this->sessionCommit();
            //标记会话已启动
            $sInfo['state'] = 2;
            //重置当前会话ID
            $sInfo['sesId'] = $this->sessionCreateId();
            //引用会话配置
            $index = &$sInfo['sInis'];
            //设置session cookie
            $this->swRes && $this->swRes->cookie(
                $index['session.name'], $sInfo['sesId'], $index['session.cookie_lifetime'],
                $index['session.cookie_path'], $index['session.cookie_domain'],
                $index['session.cookie_secure'], $index['session.cookie_httponly']
            );
        } else {
            trigger_error('session_regenerate_id(): Session ID cannot be regenerated when there is no active session', E_USER_WARNING);
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_decode
     * 作者 : Edgar.lee
     */
    public function sessionDecode($data) {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //解析会话数据, php格式
            if (ini_get('session.serialize_handler') === 'php') {
                //总偏移量
                $pPos = 0;
                //正在解析的会话键
                $nKey = null;
                //会话列表, [会话值为对象或数组, 会话值开始位置, 会话值结束位置]
                $list = array();

                //正则定位会话键, 与标准的区别不含";"与"}", 因为实现方便^^
                preg_match_all(
                    '@[|;](s):(\d+):|(?:^|[;}])([^;}|]+)(?=\|)@',
                    $data, $match,
                    PREG_SET_ORDER | PREG_OFFSET_CAPTURE
                );

                foreach ($match as &$v) {
                    //未解析部分
                    if ($pPos <= $v[0][1]) {
                        //字符串
                        if ($v[1][1] > 0) {
                            //计算新解析位置, 初始位置 + 标记长度 + 实体长度 + 2个'"'
                            $pPos = $v[0][1] + strlen($v[0][0]) + $v[2][0] + 2;
                        //会话键
                        } else {
                            //计算新解析位置, 初始位置 + 标记长度
                            $pPos = $v[0][1] + strlen($v[0][0]);
                            //未在解析会话键 || 解析会话值
                            $nKey === null || $list[$nKey] = unserialize(substr(
                                $data, $list[$nKey][0], $v[0][1] + 1 - $list[$nKey][0]
                            ));
                            //更新解析会话键 && 记录会话值开始位置(当前位置 + 1个'|')
                            $list[$nKey = $v[3][0]][0] = $temp = $pPos + 1;
                        }
                    }
                }
                //解析会话值
                $nKey === null || $list[$nKey] = unserialize(substr(
                    $data, $list[$nKey][0], strlen($data) - $list[$nKey][0]
                ));

                //更新会话数据
                self::$attrs[$this->space]['super']['_SESSION_mapVar'] = $list;
            //解析会话数据, php_serialize格式, 其它格式未实现
            } else {
                self::$attrs[$this->space]['super']['_SESSION_mapVar'] = unserialize($data);
            }
        } else {
            trigger_error('session_decode(): Session data cannot be decoded when there is no active session', E_USER_WARNING);
        }

        //启动返回true, 反之false
        return $sInfo['state'] === 2;
    }

    /**
     * 描述 : 接管 session_encode
     * 作者 : Edgar.lee
     */
    public function sessionEncode() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //会话数据
            $data = self::$attrs[$this->space]['super']['_SESSION_mapVar'];

            //生成会话数据, php格式
            if (ini_get('session.serialize_handler') === 'php') {
                foreach ($data as $k => &$v) {
                    if (strpos($k, '|') === false) {
                        $v = $k . '|' . serialize($v);
                    } else {
                        $data = array();
                        break ;
                    }
                }
                $data = join($data);
            //生成会话数据, php_serialize格式, 其它格式未实现
            } else {
                $data = serialize($data);
            }

            //返回编码数据
            return $data;
        } else {
            trigger_error('session_encode(): Cannot encode non-existent session', E_USER_WARNING);
            return false;
        }
    }

    /**
     * 描述 : 接管 session_gc
     * 作者 : Edgar.lee
     */
    public function sessionGc() {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //已启动
        if ($sInfo['state'] === 2) {
            //会话回收
            of_base_session_base::gc();

            //返回删除数量
            return 1;
        } else {
            trigger_error('session_gc(): Session cannot be garbage collected when there is no active session', E_USER_WARNING);
            return false;
        }
    }

    /**
     * 描述 : 接管 session_id
     * 作者 : Edgar.lee
     */
    public function sessionId($sesId = '') {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];

        //当前会话ID
        $result = $sInfo['sesId'];

        //修改会话ID
        if ($sesId) {
            //已启动
            if ($sInfo['state'] === 2) {
                trigger_error('session_id(): Session ID cannot be changed when a session is active', E_USER_WARNING);

                //操作失败
                return false;
            } else {
                $sInfo['sesId'] = $sesId;
            }
        }

        //返回会话ID
        return $result;
    }

    /**
     * 描述 : 接管 session_name
     * 作者 : Edgar.lee
     */
    public function sessionName($name = '') {
        //引用会话数据
        $sInfo = &self::$attrs[$this->space]['sInfo'];
        //当前会话ID
        $result = $sInfo['sInis']['session.name'];

        //修改会话ID
        if ($name) {
            //已启动
            if ($sInfo['state'] === 2) {
                trigger_error('session_name(): Session name cannot be changed when a session is active', E_USER_WARNING);

                //操作失败
                return false;
            } else {
                $sInfo['sInis']['session.name'] = $name;
            }
        }

        //返回会话ID
        return $result;
    }

    /**
     * 描述 : 接管 session_status
     * 作者 : Edgar.lee
     */
    public function sessionStatus() {
        //返回会话状态
        return self::$attrs[$this->space]['sInfo']['state'];
    }

    /**
     * 描述 : 接管 ob_clean
     * 作者 : Edgar.lee
     */
    public function obClean() {
        return ob_get_level() > 1 ? ob_clean() : false;
    }

    /**
     * 描述 : 接管 ob_end_clean
     * 作者 : Edgar.lee
     */
    public function obEndClean() {
        return ob_get_level() > 1 ? ob_end_clean() : false;
    }

    /**
     * 描述 : 接管 ob_end_flush
     * 作者 : Edgar.lee
     */
    public function obEndFlush() {
        return ob_get_level() > 1 ? ob_end_flush() : false;
    }

    /**
     * 描述 : 接管 ob_flush
     * 作者 : Edgar.lee
     */
    public function obFlush() {
        return ob_get_level() > 1 ? ob_flush() : false;
    }

    /**
     * 描述 : 接管 ob_get_clean
     * 作者 : Edgar.lee
     */
    public function obGetClean() {
        return ob_get_level() > 1 ? ob_get_clean() : false;
    }

    /**
     * 描述 : 接管 ob_get_contents
     * 作者 : Edgar.lee
     */
    public function obGetContents() {
        return ob_get_level() > 1 ? ob_get_contents() : false;
    }

    /**
     * 描述 : 接管 ob_get_flush
     * 作者 : Edgar.lee
     */
    public function obGetFlush() {
        return ob_get_level() > 1 ? ob_get_flush() : false;
    }

    /**
     * 描述 : 接管 ob_get_length
     * 作者 : Edgar.lee
     */
    public function obGetLength() {
        return ob_get_level() > 1 ? ob_get_length() : false;
    }

    /**
     * 描述 : 接管 ob_get_level
     * 作者 : Edgar.lee
     */
    public function obGetLevel() {
        return ob_get_level() - 1;
    }

    /**
     * 描述 : 接管 ob_get_status
     * 作者 : Edgar.lee
     */
    public function obGetStatus($type = false) {
        if (ob_get_level() > 1) {
            //读取缓冲区状态
            $result = ob_get_status($type);

            //返回二维数组(所有缓冲区)
            if ($type) {
                array_shift($result);
                foreach ($result as &$v) --$v['level'];
            //返回一维数组(当前缓冲区)
            } else {
                --$result['level'];
            }
        } else {
            $result = array();
        }

        return $result;
    }

    /**
     * 描述 : 接管 ob_list_handlers
     * 作者 : Edgar.lee
     */
    public function obListHandlers() {
        if (ob_get_level() > 1) {
            //列出所有使用的输出处理程序
            $result = ob_list_handlers();
            //移除根缓冲区信息
            array_shift($result);
        } else {
            $result = array();
        }

        return $result;
    }

    /**
     * 描述 : 接管 spl_autoload_register
     * 作者 : Edgar.lee
     */
    public function splAutoloadRegister($call = null, $throw = true, $prepend = false) {
        static $init = null;

        //监听系统错误
        $init === null && $init = spl_autoload_register('swoole::autoClass');
        //未指定回调 && 使用默认回调
        $call === null && $call = 'spl_autoload';
        //引用自动加载类
        $index = &self::$attrs[$this->space]['autoC'];
        //检查是否已注册
        foreach ($index as &$v) {
            //已注册
            if ($call === $v) {
                $call = null;
                break ;
            }
        }
        //未注册 && (优先回调 ? 压入队列 : 追加队列)
        $call && ($prepend ? array_unshift($index, $call) : $index[] = $call);

        //固定返回
        return true;
    }

    /**
     * 描述 : 接管 spl_autoload_unregister
     * 作者 : Edgar.lee
     */
    public function splAutoloadUnregister($call) {
        //未指定回调 && 使用默认回调
        $call === null && $call = 'spl_autoload';
        //引用自动加载类
        $index = &self::$attrs[$this->space]['autoC'];
        //检查是否已注册
        foreach ($index as $k => &$v) {
            //已找到
            if ($call === $v) {
                array_splice($index, $k, 1);
                return true;
            }
        }

        //未找到
        return false;
    }

    /**
     * 描述 : 接管 spl_autoload_functions
     * 作者 : Edgar.lee
     */
    public function splAutoloadFunctions() {
        return self::$attrs[$this->space]['autoC'];
    }

    /**
     * 描述 : 接管 set_error_handler
     * 作者 : Edgar.lee
     */
    public function setErrorHandler($call, $level = E_ALL) {
        static $init = true;

        //监听系统错误
        $init === true && $init = set_error_handler('swoole::errCtrl');
        //引用错误处理数据
        $index = &self::$attrs[$this->space]['error']['eList'];
        //注册错误处理程序
        array_unshift($index, array($call, $level));

        //返回之前处理程序
        return isset($index[1]) ? $index[1][0] : null;
    }

    /**
     * 描述 : 接管 set_exception_handler
     * 作者 : Edgar.lee
     */
    public function setExceptionHandler($call) {
        static $init = true;

        //监听系统错误
        $init === true && $init = set_exception_handler('swoole::errCtrl');
        //引用异常处理数据
        $index = &self::$attrs[$this->space]['error']['tList'];
        //注册异常处理程序
        array_unshift($index, array($call, 1));

        //返回之前处理程序
        return isset($index[1]) ? $index[1][0] : null;
    }

    /**
     * 描述 : 接管 restore_error_handler
     * 作者 : Edgar.lee
     */
    public function restoreErrorHandler() {
        //移除当前错误处理程序
        array_shift(self::$attrs[$this->space]['error']['eList']);

        return true;
    }

    /**
     * 描述 : 接管 restore_exception_handler
     * 作者 : Edgar.lee
     */
    public function restoreExceptionHandler() {
        //移除当前异常处理程序
        array_shift(self::$attrs[$this->space]['error']['tList']);

        return true;
    }

    /**
     * 描述 : 接管 debug_backtrace
     * 作者 : Edgar.lee
     */
    public function debugBacktrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit = 0) {
        //读取回溯
        $result = debug_backtrace($options);

        //移除本函数记录
        array_shift($result);
        //移除本类记录
        for ($i = count($result) - 3; --$i >= 0;) {
            if (isset($result[$i]['file']) && $result[$i]['file'] === __FILE__) {
                array_splice($result, $i, 1);
            }
        }
        //限制返回堆栈数量
        $limit > 0 && $result = array_splice($result, 0, $limit);

        //返回格式化回溯
        return $result;
    }

    /**
     * 描述 : 接管 error_reporting
     * 作者 : Edgar.lee
     */
    public function errorReporting($level = null) {
        //引用异常处理等级
        $index = &self::$attrs[$this->space]['error']['level'];
        //当前处理级别
        $result = $index[0];
        //设置处理级别
        is_int($level) && $index = array($level);

        return $result;
    }

    /**
     * 描述 : 接管 error_get_last
     * 作者 : Edgar.lee
     */
    public function errorGetLast() {
        return self::$attrs[$this->space]['error']['last'];
    }

    /**
     * 描述 : 接管 error_clear_last
     * 作者 : Edgar.lee
     */
    public function errorClearLast() {
        self::$attrs[$this->space]['error']['last'] = null;
    }

    /**
     * 描述 : 接管 header
     * 作者 : Edgar.lee
     */
    public function header($info, $replace = true, $code = 0) {
        //引用响应头列表
        $heads = &self::$attrs[$this->space]['heads'];

        //在沙盒容器中
        if ($this->swRes === null) {
            return ;
        //发送头信息
        } else if (is_object($info)) {
            //发送头信息
            foreach ($heads as $k => &$v) $info->header($k, join(', ', $v));
        //发送状态
        } else if (preg_match('@^http/[^ ]+ +(\d+)\s+(.*)@i', $info, $match)) {
            $this->swRes->status($match[1], $match[2]);
        //存储头信息
        } else if (strpos($info, ':')) {
            //分隔头信息
            $temp = explode(':', $info, 2);
            //格式化[小写头, 去空格]
            $temp = array(strtolower($temp[0]), trim($temp[1]));
            //是跳转, 附带302状态码
            if ($temp[0] === 'location') {
                $code || $code = 302;
                $this->swRes->header($temp[0], $temp[1]);
            //其它头
            } else {
                //替换操作
                $replace && $heads[$temp[0]] = array();
                //追加操作
                $heads[$temp[0]][] = $temp[1];
            }
            //发送状态
            $code && $this->swRes->status($code, $info);
        }
    }

    /**
     * 描述 : 接管 setcookie
     * 作者 : Edgar.lee
     */
    public function setCookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = false, $httponly = false) {
        return $this->swRes && $this->swRes->cookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 描述 : 接管 setrawcookie
     * 作者 : Edgar.lee
     */
    public function setRawCookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = false, $httponly = false) {
        return $this->swRes && $this->swRes->rawCookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 描述 : 接管 pcntl_signal
     * 作者 : Edgar.lee
     */
    public function pcntlSignal($signal, $handler, $syscalls = true) {
        //引用系统信号列表
        $index = &self::$attrs[$this->space]['signo'];

        //默认处理, SIG_DFL
        if ($handler === 0) {
            unset($index[$signal]);
        //忽略或回调处理, SIG_IGN(1) 和 回调处理
        } else {
            $index[$signal]['call'] = $handler;
        }
    }

    /**
     * 描述 : 接管 pcntl_signal_dispatch
     * 作者 : Edgar.lee
     */
    public function pcntlSignalDispatch() {
        //工作开始退出(仅实现SIGTERM信号)
        if (self::$isEnd) {
            //引用系统信号列表
            $index = &self::$attrs[$this->space]['signo'];
            //默认处理
            if (empty($index[15])) {
                exit ;
            //回调处理
            } else if ($index[15]['call'] !== 1) {
                call_user_func($index[15]['call'], 15, null);
            }
        }
    }

    /**
     * 描述 : 接管 sleep
     * 作者 : Edgar.lee
     */
    public function sleep($seconds) {
        //休眠时间
        sleep($seconds);
        //检查退出
        $this->checkExit();
    }

    /**
     * 描述 : 接管 usleep
     * 作者 : Edgar.lee
     */
    public function usleep($microseconds) {
        //休眠时间, swoole定时器最小值是 1ms, 若低于 1ms 时，将使用 sleep 系统调用, 可能会短暂的睡眠阻塞
        usleep($microseconds < 1000 ? 1000 : $microseconds);
        //检查退出
        $this->checkExit();
    }

    /**
     * 描述 : 接管 ini_set
     * 作者 : Edgar.lee
     */
    public function iniSet($option, $value) {
        switch ($option) {
            //设置脚本最大执行时间
            case 'max_execution_time':
                //引用超时设置
                $index = &self::$attrs[$this->space]['state']['exeTime'];
                //返回数据
                $result = $index[0];
                //设置超时秒数
                $index = array($value, time() + $value);
                //返回原数据
                return $result;
            //禁止内存调整, 会影响全局
            case 'memory_limit':
                //引用内存设置
                $index = &self::$attrs[$this->space]['state']['memory'];
                //返回数据
                $result = $index[0];
                //设置超时秒数
                $index = array($value, self::getBitSize($value), $index[2], $index[3]);
                //返回原数据
                return $result;
            default :
                return ini_set($option, $value);
        }
    }

    /**
     * 描述 : 接管 ini_get
     * 作者 : Edgar.lee
     */
    public function iniGet($option) {
        switch ($option) {
            //设置脚本最大执行时间
            case 'max_execution_time':
                //返回超时设置
                return self::$attrs[$this->space]['state']['exeTime'][0];
            //不限制单协程内存
            case 'memory_limit':
                //返回超时设置
                return self::$attrs[$this->space]['state']['memory'][0];
            default :
                return ini_get($option);
        }
    }

    /**
     * 描述 : 接管 set_time_limit
     * 作者 : Edgar.lee
     */
    public function setTimeLimit($value) {
        self::$attrs[$this->space]['state']['exeTime'] = array($value, time() + $value);
        return true;
    }

    /**
     * 描述 : 接管 php_sapi_name
     * 作者 : Edgar.lee
     */
    public function phpSapiName() {
        return 'fpm-fcgi';
    }

    /**
     * 描述 : 接管 define
     * 作者 : Edgar.lee
     */
    public function define($name, $value) {
        return defined($name) || define($name, $value);
    }

    /**
     * 描述 : 接管 register_shutdown_function
     * 作者 : Edgar.lee
     */
    public function registerShutdownFunction() {
        $args = func_get_args();
        self::$attrs[$this->space]['halts'][] = array(array_shift($args), $args);
    }

    /**
     * 描述 : 接管 class_alias, 增加类名判断防止报类重复报错
     * 作者 : Edgar.lee
     */
    public function classAlias($class, $alias, $load = true) {
        return class_exists($alias, false) ? true : class_alias($class, $alias, $load);
    }

    /**
     * 描述 : 接管 memory_get_usage
     * 注明 :
     *      内存计算解释
     *          变量声明: 40 => (a = 36 + 变量名长度) => a + 8 - a % 8
     *          空数组: 120
     *          字符串: 48 => 40 + 1引导 + 字符串长度
     *          空对象: 144
     *          ini float bool null: 32
     *          资源: 552
     *          引用赋值: 8
     * 作者 : Edgar.lee
     */
    public function memoryGetUsage($real = false) {
        //任务进程返回真实内存
        if ($GLOBALS['system']['type'] === 'task') {
            return memory_get_usage(!!$real);
        //真实计算
        } else if (is_array($real)) {
            //标记字段
            $mark = "__\0swookMemoryMark\0__";
            //待分析列表
            $wait = array(&self::$attrs[$this->space]);
            //完成恢复工作引用
            $ends = array();
            //占用内存字节
            $size = 0;

            do {
                //引用工作变量
                $index = &$wait[0];
                array_shift($wait);

                //判断变量类型
                switch (gettype($index)) {
                    //数组, 键名声明 40 => (a = 36 + 变量名长度)向上8倍数取整
                    case 'array':
                        //递归标记存在
                        if (isset($index[$mark])) {
                            //引用赋值: 8
                            $size += 8;
                        } else {
                            //空数组占用: 120
                            $size += 120;
                            //添加递归标记
                            foreach ($index as $k => &$v) {
                                //统计实际字节, 36 + (是字符串 ? 按长度 : 按4字节)
                                $temp = 36 + (is_string($k) ? strlen($k) : 4);
                                //向上8倍数取整, (a + 8 - 1) & ~(8 - 1)
                                $size += ($temp + 7) & ~7;
                                //内存计算列表
                                $wait[] = &$v;
                            }
                            //添加递归标记
                            $index[$mark] = true;
                            //记录移除标记列表
                            $ends[] = &$index;
                        }
                        break;
                    case 'object':
                        //空对象: 144
                        $size += 144;
                        //存在动态变量映射方法(克隆方式需额外内存的浅拷贝且Redis等失败会抛异常或致命错误)
                        if (method_exists($index, '__normalPropertiesMaps')) {
                            //读取全部动态变量
                            $index->__normalPropertiesMaps($this);
                            //动态变量加到待分析列表
                            $wait[] = &$this->vMaps;
                        }
                        break;
                    //字符串: 48 => (40 + 1引导 + 字符串长度)向上8倍数取整
                    case 'string':
                        $size += (48 + strlen($index)) & ~7;
                        break;
                    //ini float bool null: 32
                    case 'integer':
                    case 'double':
                    case 'boolean':
                    case 'NULL':
                        $size += 32;
                        break;
                    //资源类型
                    default :
                        $size += 552;
                }
            } while ($wait);

            //清理标记空间
            foreach ($ends as &$v) unset($v[$mark]);
            //清理映射空间
            $this->vMaps = null;

            //总是返回协程实际使用内存
            return $size;
        //返回缓存
        } else {
            return self::$attrs[$this->space]['state']['memory'][3];
        }
    }

    /**
     * 描述 : 接管 get_included_files get_required_files
     * 作者 : Edgar.lee
     */
    public function getIncludedFiles() {
        //已加载的文件
        $list = get_included_files();
        //排除框架存储路径
        $path = of::config('_of.htmlTpl.path', OF_DATA . '/_of/of_base_htmlTpl_engine');
        //过滤无用路径
        for ($i = count($list); --$i;) {
            //eval路径(包含"("字符串) || /data/_of/下路径
            if (strpos($list[$i], '(') || strpos($list[$i], $path)) {
                array_splice($list, $i, 1);
            }
        }
        //清理eval后的路径
        return $list;
    }

    /**
     * 描述 : 拦截调度入口回调事件, 解决多次调度正确返回校验值
     * 作者 : Edgar.lee
     */
    public function dispatch($type, $event, $params) {
        //类文件返回值列表 {类名 : 返回值, ...}
        static $list = array('of_index' => true);

        //触发自定义重新初始化
        foreach ($GLOBALS['system']['reinit'] as &$v) of::callFunc($v);
        //触发 of::dispatch 事件
        of::event('of::dispatch', true, $params);

        //非绝对禁用或绝对通过
        if ($params['check'] !== false && $params['check'] !== null) {
            //未初始化 && 加载返回值
            ($index = &$list[$params['class']]) === null && $index = of::callFunc(array(
                'asCall' => 'of::loadClass',
                'params' => array($params['class'])
            ));
            //返回值有效 ? null : false
            $params['check'] = $params['check'] === $index ? null : false;
        }
    }

    /**
     * 描述 : 检查是否退出协程
     * 作者 : Edgar.lee
     */
    public function checkExit($mode = 7) {
        //刷新内存使用量, 0=可以刷新, 其它=正在刷新
        static $refresh = 0;
        //待刷新内存的协程列表[协程ID, ...]
        static $reList = array();

        //引用协程数据
        $index = &self::$attrs[$this->space];
        //引用协程状态
        $state = &$index['state'];
        //协程执行时间
        $exeTime = &$state['exeTime'];
        //协程执行内存
        $memory = &$state['memory'];

        //追加初始化(2) || 处理错误(4) || 被动退出(8), 无需在再执行
        if ($state['code'] & 14) {
            return ;
        //工作退出信号(仅实现SIGTERM信号) && 默认处理 && (无父协程 || 父协程已退出)
        } else if (
            $mode & 1 &&
            self::$isEnd && empty($index['signo'][15]) &&
            (!$state['pCid'] || !isset(self::$attrs[$state['pCid']]))
        ) {
            //标记被动退出
            $state['code'] |= 8;
            exit;
        //协程超时退出
        } else if (
            $mode & 2 &&
            $exeTime[0] && $exeTime[1] < self::$nTime
        ) {
            //标记被动退出
            $state['code'] |= 8;
            //读取代码行数
            $temp = $this->debugBacktrace();
            //生成协程超时错误
            of::event('of::error', true, array(
                'code' => E_ERROR,
                'info' => "Maximum execution time of {$exeTime[0]} second exceeded",
                'file' => $temp[0]['file'] ?? __FILE__,
                'line' => $temp[0]['line'] ?? __LINE__
            ));
            exit;
        //检查内存溢出(未锁定 && 内存缓存过期 && 内存刷新未加锁)
        } else if (
            $mode & 4 &&
            $memory[2] && self::$nTime - $memory[2] >= 600 && ++$refresh === 1
        ) {
            //协程限制内存 && (设置永不超时 || 任务模式), 检查内存
            if ($memory[1] > -1 && (!$exeTime[0] || $GLOBALS['system']['type'] === 'task')) {
                //当前应用协程ID列表
                $temp = array_fill_keys(array_keys(self::$attrs), 1);
                //初始化协程内存刷新列表
                $reList = $reList ? array_intersect_key($reList, $temp) : $temp;

                //本轮协程内存未刷新
                if (isset($reList[$this->space])) {
                    //锁定内存检查
                    $memory[2] = 0;
                    //计算协程内存
                    $memory[3] = $this->memoryGetUsage(array());

                    //内存已溢出
                    if ($memory[3] > $memory[1]) {
                        //标记被动退出
                        $state['code'] |= 8;
                        //读取代码行数
                        $temp = $this->debugBacktrace();
                        //生成协程超时错误
                        of::event('of::error', true, array(
                            'code' => E_ERROR,
                            'info' => "Failed to set memory limit to {$memory[1]} bytes (Current memory usage is {$memory[3]} bytes)",
                            'file' => $temp[0]['file'] ?? __FILE__,
                            'line' => $temp[0]['line'] ?? __LINE__
                        ));
                        exit;
                    }
                }
            }

            //移除本轮刷新
            unset($reList[$this->space]);
            //更新检查时间
            $memory[2] = time();
            //内存刷新完成
            $refresh = 0;
        }
    }

    /**
     * 描述 : 接管 file_get_contents
     * 作者 : Edgar.lee
     */
    public function fileGetContents(...$params) {
        //原始数据流
        if ($params[0] === 'php://input') {
            return self::$attrs[$this->space]['super']['_FUNC_mapVar']->input;
        //打开资源
        } else {
            return call_user_func_array('file_get_contents', $params);
        }
    }

    /**
     * 描述 : 接管 file_put_contents
     * 作者 : Edgar.lee
     */
    public function filePutContents($file, $data, $flags = 0, $context = null) {
        //初始空上下文流
        $context || $context = stream_context_create();
        //仅写方式打开
        $fp = fopen($file, 'c', !!($flags & FILE_USE_INCLUDE_PATH), $context);
        //加独享锁
        $flags & LOCK_EX && $this->flock($fp, LOCK_EX);
        //追加模式
        if ($flags & FILE_APPEND) {
            //移动到最后
            fseek($fp, 0, SEEK_END);
        //覆盖模式
        } else {
            //游标移到起始位置, 防止部分磁盘支持超过结尾补"\0"功能
            fseek($fp, 0);
            //清空文件
            ftruncate($fp, 0);
        }
        //写入数据成功 && 防止网络磁盘掉包
        is_int($result = fwrite($fp, $data)) && (fseek($fp, -1, SEEK_CUR) || fread($fp, 1));
        //解锁
        $flags & LOCK_EX && flock($fp, LOCK_UN);
        //关闭连接
        fclose($fp);
        //返回写入结果
        return $result;
    }

    /**
     * 描述 : 接管 fopen
     * 作者 : Edgar.lee
     */
    public function fopen(...$params) {
        //原始数据流
        if ($isOk = $params[0] === 'php://input') {
            $params[0] = 'of.incl://input';
            self::serial(true);
        }
        //打开资源
        $result = call_user_func_array('fopen', $params);
        //启用协程
        $isOk && self::serial(false);
        //返回资源
        return $result;
    }

    /**
     * 描述 : 接管 flock
     * 作者 : Edgar.lee
     */
    public function flock($stream, $operate, &$block = null) {
        //加锁操作, swoole阻塞加锁相互冲突, 尝试锁不会
        if (($operate & 3) < 3) {
            //尝试锁成功 || 阻塞式加锁 && 等待加锁, 防止同一资源加两把锁时夯住
            return flock($stream, $operate | 4, $block) || ($operate & 7) < 3 && self::yield('flock', array(
                'stream' => $stream,
                'operate' => $operate | 4,
                'block' => &$block,
            ));
        //解锁操作, 尝试解锁可能失败, 阻塞不会
        } else {
            return flock($stream, 3, $block);
        }
    }

    /**
     * 描述 : 主动加载类
     *      spl_autoload_register回调中禁止加载相同类, 导致并发协程出现类不存在问题
     * 作者 : Edgar.lee
     */
    public function loadClass($class, $isNew = false) {
        //类已存在 || 尝试加载类
        class_exists($class, false) || self::autoClass($class);
        //返回类
        return $isNew ? $this : $class;
    }

    /**
     * 描述 : 初始并加载环境
     * 参数 :
     *     &requ : 请求对象
     *     &resp : 响应对象
     * 返回 :
     *      超全局变量
     * 作者 : Edgar.lee
     */
    public static function &loadEnv(&$requ = null, &$resp = null) {
        //初始化接管方法
        if ($requ) {
            //清理空间数据
            Co::defer('swoole::clear');
            //捕获所有输出
            ob_start();
            //协程ID
            $space = Co::getCid();

            //网络请求
            if ($isObj = is_object($requ)) {
                //启动串行化
                self::serial(true);
                //无待响的请求 && 运行的请求未满 && 放入执行池
                if ($temp = !self::$yield['waits'] && count(self::$yield['resCo']) <= SWOOLE_MAX_REQUEST) {
                    self::$yield['resCo'][$space] = array();
                }
                //禁用串行化
                self::serial(false);
                //(有待响的请求 || 运行的请求已满) && 放入排队池
                $temp === false && self::yield('waits');

                //设置超全局隔离变量
                $result = array(
                    '_ENV_mapVar' => $_ENV,
                    '_SERVER_mapVar' => array(),
                    '_GET_mapVar' => $requ->get ?? array(),
                    '_POST_mapVar' => $requ->post ?? array(),
                    '_COOKIE_mapVar' => $requ->cookie ?? array(),
                    '_FILES_mapVar' => $requ->files ?? array(),
                    '_SESSION_mapVar' => array(),
                    '_FUNC_mapVar' => new self
                );

                //初始化接管方法对象
                $result['_FUNC_mapVar']->space = $space;
                $result['_FUNC_mapVar']->input = $resp ? $requ->rawContent() : '';
                $result['_FUNC_mapVar']->swRes = $resp;
                //合并_REQUEST超全局变量
                $result['_REQUEST_mapVar'] =
                    $result['_GET_mapVar'] + $result['_POST_mapVar'] + $result['_COOKIE_mapVar'];
                //合并_SERVER超全局变量
                $result['_SERVER_mapVar'] = array(
                    'USER' => 'www-data',
                    'REDIRECT_STATUS' => 200,
                    'SERVER_NAME' => parse_url($requ->header['host'], PHP_URL_HOST),
                    'SERVER_ADDR' => $_SERVER['SERVER_ADDR'],
                    'SERVER_SOFTWARE' => 'swoole/' . SWOOLE_VERSION,
                    'GATEWAY_INTERFACE' => 'CGI/1.1',
                    'REQUEST_SCHEME' => 'http',
                    'DOCUMENT_ROOT' => ROOT_DIR,
                    'DOCUMENT_URI' => $temp = $requ->server['path_info'],
                    'REQUEST_URI' => $temp,
                    'SCRIPT_NAME' => $temp,
                    'PHP_SELF' => $temp,
                    'CONTENT_LENGTH' => '',
                    'CONTENT_TYPE' => '',
                    'SCRIPT_FILENAME' => ROOT_DIR . $temp,
                    'FCGI_ROLE' => 'RESPONDER',
                    'QUERY_STRING' => '',
                    'ofDebug' => false
                );
                //合并swoole->server
                foreach ($requ->server as $k => &$v) {
                    $result['_SERVER_mapVar'][strtoupper($k)] = $v;
                }
                //合并swoole->header
                foreach ($requ->header as $k => &$v) {
                    $result['_SERVER_mapVar']['HTTP_' . strtoupper(strtr($k, '-', '_'))] = $v;
                }
            //沙盒环境
            } else {
                //设置超全局隔离变量
                $result = array(
                    '_ENV_mapVar' => $_ENV,
                    '_SERVER_mapVar' => $requ,
                    '_GET_mapVar' => array(),
                    '_POST_mapVar' => array(),
                    '_COOKIE_mapVar' => array(),
                    '_FILES_mapVar' => array(),
                    '_SESSION_mapVar' => array(),
                    '_FUNC_mapVar' => new self
                );
                //初始化接管方法对象
                $result['_FUNC_mapVar']->space = $space;
            }

            //当前时间
            $time = time();
            //更新启动时间
            $result['_SERVER_mapVar']['REQUEST_TIME'] = $result['_SERVER_mapVar']['REQUEST_TIME_FLOAT'] = $time;
            //合并GLOBALS超全局变量
            $result['GLOBALS_mapVar'] = array(
                '_GET' => &$result['_GET_mapVar'],
                '_POST' => &$result['_POST_mapVar'],
                '_COOKIE' => &$result['_COOKIE_mapVar'],
                '_FILES' => &$result['_FILES_mapVar'],
                '_SERVER' => &$result['_SERVER_mapVar'],
                '_SESSION' => &$result['_SESSION_mapVar'],
                '_REQUEST' => &$result['_REQUEST_mapVar'],
                'GLOBALS' => &$result['GLOBALS_mapVar']
            );
            //初始属性结构
            self::$attrs[$space] = array(
                'super' => &$result,
                'autoC' => array(),
                'error' => array(
                    'level' => array(E_ALL),
                    'last'  => null,
                    'eList' => array(),
                    'tList' => array(),
                    'catch' => array()
                ),
                'sInfo' => array(
                    'state' => 1,
                    'sesId' => '',
                    'sInis' => ini_get_all('session', false)
                ),
                'heads' => array(
                    'content-type' => array(
                        'text/html; charset=UTF-8'
                    )
                ),
                'halts' => array(),
                'state' => array(
                    'code'    => 0,
                    'pCid'    => 0,
                    'exeTime' => array($temp = $GLOBALS['phpIni']['max_execution_time'], $time + $temp),
                    'memory'  => array($temp = $GLOBALS['phpIni']['memory_limit'], $temp, $time, 1048576)
                )
            );

            //沙盒环境
            if (!$isObj) {
                //引用协程数据
                $index = &self::$attrs[$space];
                //不超时的协程
                $index['state']['exeTime'][0] = 0;
                //记录父协程ID
                $index['state']['pCid'] = $resp;
            }
        //超全局隔离变量存在, 判断是否退出
        } else if ($result = &self::$attrs[self::space()]['super']) {
            //检查退出
            $result['_FUNC_mapVar']->checkExit();
        //超全局隔离变量不存在
        } else {
            trigger_error('You need to create a OF environment: swoole::fork()');
            exit ;
        }

        return $result;
    }

    /**
     * 描述 : 重新初始回调
     * 参数 :
     *      call : 注册初始化方法, class::init
     * 作者 : Edgar.lee
     */
    public static function reinit($call = null) {
        //重新初始化回调列表
        static $list = array(0);
        //回调列表长度($list.count)
        static $lLen = 1;

        //执行初始化回调
        if ($call === null) {
            //协程状态码
            $code = &self::$attrs[Co::getCid()]['state']['code'];
            //预计回调次数
            $count = 0;
            //遍历初始化
            foreach ($list as &$v) {
                //$v不为0, 执行回调
                $v && call_user_func($v);
                //达到了预计回调次数
                if (++$count === $lLen) {
                    //禁用协程
                    self::serial(true);
                    //无更多回调(防止其它协程新增回调) && 标记初始完成
                    $count === $lLen && $code |= 1;
                    //启用协程
                    self::serial(false);
                    //初始化完成, 跳出遍历
                    if ($code & 1) break ;
                }
            }
        //首次注册并执行
        } else if (!isset($list[$call])) {
            //禁用协程
            self::serial(true);
            //未被并行的协程初始, 初始化其它已初始化过的协程
            if (!isset($list[$call])) {
                //记录回调列表
                $list[$call] = $call;
                //计算回调数量
                $lLen += 1;
                //记录当前空间状态
                $onCid = self::$onCid;

                //遍历所有协程空间
                foreach (self::$attrs as $k => &$v) {
                    //已初始化的空间
                    if ($v['state']['code'] & 1) {
                        //标记try开始
                        self::errCtrl(true, __FUNCTION__);
                        //标记追加初始化
                        $v['state']['code'] |= 2;
                        //初始化并执行逻辑代码
                        try {
                            //切换到指定协程
                            self::$onCid = $k;
                            //执行类初始化
                            call_user_func($call);
                        //含exit的所有错误
                        } catch (Throwable $e) {
                            of::event('of::error', true, $e);
                        }
                        //结束追加初始化
                        $v['state']['code'] &= ~2;
                        //标记try结束
                        self::errCtrl(false, __FUNCTION__);
                    }
                }

                //恢复默认协程ID
                self::$onCid = $onCid;
            }
            //启用协程
            self::serial(false);
        }
    }

    /**
     * 描述 : 清理隔离数据
     * 作者 : Edgar.lee
     */
    public static function clear($type = null) {
        //进程关闭回调
        if ($type === true) {
            //发生错误 && 记录错误日志到到操作系统层面
            ($temp = error_get_last()) && print_r(
                date('[Y-m-d H:i:s] ') . join(', ', array_slice($temp, 1, 3)) . "\n"
            );
        //协程执行结束
        } else if ($type === false) {
            //协程ID
            $space = self::space();
            //引用功能映射对象
            $index = &self::$attrs[$space]['super']['_FUNC_mapVar'];

            //标记try开始
            self::errCtrl(true, __FUNCTION__);
            //触发结束执行回调
            try {
                //触发完结回答
                foreach (self::$attrs[$space]['halts'] as &$v) call_user_func_array($v[0], $v[1]);
            //拦截exit
            } catch (Swoole\ExitException $e) {
                //打印字符串exit(str)
                if (is_string($e = $e->getStatus())) echo $e;
            //含exit的所有错误
            } catch (Throwable $e) {
                of::event('of::error', true, $e);
            }
            //标记try结束
            self::errCtrl(false, __FUNCTION__);
        //协程资源释放
        } else {
            //关闭串行化
            self::serial(0);
            //协程ID
            $space = self::space();
            //引用功能映射对象
            $index = &self::$attrs[$space]['super']['_FUNC_mapVar'];

            //网络请求
            if ($index->swRes) {
                //发送响应头信息
                $index->header($index->swRes);
                //完结输出缓存
                for ($i = ob_get_level(); --$i;) ob_end_flush();
                //响应输出信息
                $index->swRes->end(ob_get_clean());
            //沙盒环境
            } else {
                //完结输出缓存
                for ($i = ob_get_level() + 1; --$i;) ob_end_clean();
            }
            //清理请求池, 再清理协程隔离变量
            unset(self::$yield['resCo'][$space], self::$attrs[$space]);
            //释放内存给进程
            gc_collect_cycles();
        }
    }

    /**
     * 描述 : 进程内共享数据
     * 参数 :
     *      name : 数据名称
     *      data : 初始数据, 默认[]
     * 返回 :
     *      引用返回, 默认空数组
     * 作者 : Edgar.lee
     */
    public static function &data($name, $data = array()) {
        static $list = array();
        $list += array($name => &$data);
        return $list[$name];
    }

    /**
     * 描述 : 异步任务执行器
     * 参数 :
     *      params : 启动参数 {
     *          "serv" : 是否由服务启动, true=是, false=否
     *      }
     * 作者 : Edgar.lee
     */
    public static function task($params = array('serv' => true)) {
        //引用协程列表
        $attrs = &self::$attrs;
        //可执行任务数
        $count = 150;
        //唯一ID计数
        $uuid = 0;
        //任务列表 [任务ID, ...]
        $list = array();
        //共享内存磁盘路径
        $path = "/dev/shm/swoole/{$GLOBALS['system']['port']}";
        //服务状态锁路径
        $serv = $path . '/serv.lock';
        //任务目录
        $task = $path . '/task';

        //尝试获取监听服务权限
        for ($i = 0; $i < 10; ++$i) {
            //尝试成功
            if (flock($work = fopen("{$path}/taskServ{$i}.lock", 'c+'), 6)) {
                break ;
            //尝试失败
            } else {
                $work = null;
            }
        }

        //已获取到服务权限
        if ($work) {
            //关闭内存检查与工作周期时间
            $GLOBALS['system']['memory'] = $GLOBALS['system']['cycle'] = 0;
            //等待获取消费权
            flock($lock = fopen($path . '/taskWatch.lock', 'c+'), 2);
        //未获取到服务权限
        } else {
            return ;
        }

        //读取并执行任务
        do {
            //读取到任务数据列表, 按修改时间先到后取前N个
            if ($data = trim(stream_get_contents(popen("ls -tr '{$task}' | head -{$count}", 'r'), 10240))) {
                //切割出文件名
                $data = explode("\n", $data);
                //遍历执行任务
                foreach ($data as &$v) {
                    //任务文件绝对路径
                    $v = $task . '/'. $v;
                    //执行任务
                    self::fork(unserialize(file_get_contents($v)), 1, array('space' => &$list[++$uuid]));
                    //执行计数
                    --$count;
                    //删除任务
                    unlink($v);
                }
                //任务溢出
                if ($count < 1) break ;
            //服务已停止
            } else if (flock(fopen($serv, 'c+'), 5)) {
                break ;
            }

            //稍后继续
            usleep(5000);
            //清理执行完成的任务, 删除(协程已启动 && 执行完成)协程ID
            foreach ($list as $k => &$v) if ($v && !isset($attrs[$v])) unset($list[$k]);
        //有执行任务的空间
        } while (($count = 150 - count($list)) > 0);

        //释放工作锁
        flock($work, 3);
        //释放消费权
        flock($lock, 3);
        //无更多任务 || 新启动任务执行器
        of_base_com_disk::none($task) || self::fork('swoole::task', 2);

        //等待所有任务执行完成, 结束任务执行器进程
        while ($list) {
            //协程执行时间
            sleep(10);
            //清理执行完成的任务, 删除(协程已启动 && 执行完成)协程ID
            foreach ($list as $k => &$v) if ($v && !isset($attrs[$v])) unset($list[$k]);
        }
    }

    /**
     * 描述 : 新建协程环境的回调
     * 参数 :
     *      call : 符合框架回调结构
     *      mode : 协程模式
     *          1=工作协程, 启动快, 可共享变量, 过多影响调度速度, 适合跨协程操作的任务
     *          2=独立进程, 启动慢, 支持更多"工作协程", 适合运行长时间的任务
     *          4=共享进程, 启动快, 支持少量"工作协程", 适合运行短时间的任务
     * 返回 :
     *      工作协程: 非串行化状态返回协程ID
     * 作者 : Edgar.lee
     */
    public static function fork($call, $mode = 1, $data = array()) {
        //协程唯一值
        static $uuid = 0;

        //创建共享进程
        if ($mode & 4) {
            //生成唯一文件名
            $name = of_base_com_str::uniqid();
            //共享内存磁盘路径
            $path = "/dev/shm/swoole/{$GLOBALS['system']['port']}";
            //[任务临时路径, 任务执行路径]
            $temp = array("{$path}/temp/{$name}", "{$path}/task/{$name}");
            //写入回调任务
            file_put_contents($temp[0], serialize($call));
            //防止被读取一半, 无锁安全操作
            rename($temp[0], $temp[1]);

            //当前为任务模式
            $GLOBALS['system']['type'] === 'task' &&
                //任务执行器已停止
                flock(fopen("{$path}/taskWatch.lock", 'c+'), 5) &&
                //启动任务执行器
                self::fork('swoole::task', 2);
        //创建独立进程
        } else if ($mode & 2) {
            //执行参数
            $exec = array(
                'php',
                __FILE__,
                'type:task',
                'data:' . rawurlencode(serialize($call)),
                '_tz:'  . date_default_timezone_get(),
                '_ip:'  . $_SERVER['SERVER_ADDR'],
                $GLOBALS['system']['config']
            );
            //异步前缀, 是mac系统 || linux 使用 nohup
            $aPre = strtolower(substr(PHP_OS, 0, 3)) === 'dar' ? '' : 'nohup ';
            //拼成异步命令
            $exec = $aPre . '"' . join('" "', $exec) . '" >/dev/null 2>&1 &';
            //管道执行命令
            is_string($exec) && pclose(popen($exec, 'r'));

            return ;
        //当前协程ID
        } else if (isset(self::$attrs[$pCid = Co::getCid()]['super'])) {
            $attr = &self::$attrs[$pCid]['super']['_SERVER_mapVar'];
            $exec = function () use ($attr, $pCid, $call, &$data) {
                //初始化沙盒环境
                self::loadEnv($attr, $pCid);
                //记录自身协程ID
                $data['space'] = Co::getCid();
                //标记try开始
                self::errCtrl(true, __FUNCTION__);
                //初始化并执行逻辑代码
                try {
                    //初始化代码
                    self::reinit();
                    //设置调度信息
                    of::dispatch('swoole', 'fork', false);
                    //加载脚本
                    of::callFunc($call);
                //拦截exit
                } catch (Swoole\ExitException $e) {
                //含exit的所有错误
                } catch (Throwable $e) {
                    of::event('of::error', true, $e);
                }
                //标记try结束
                self::errCtrl(false, __FUNCTION__);

                //触发关闭回调
                self::clear(false);
            };

            //串行状态
            if (self::$incNo) {
                //新建协程
                self::$yield['async'][++$uuid] = $exec;
                //标记有待办任务
                self::$yield['state']['code'] |= 2;
            } else {
                return go($exec);
            }
        } else {
            trigger_error('Failed to create fork');
        }
    }

    /**
     * 描述 : 自动加载类
     * 作者 : Edgar.lee
     */
    public static function autoClass($class) {
        //开启串行化, 尽量降低在回调中无法寻找同类的问题
        self::serial(true);
        //引用自动加载类
        foreach (self::$attrs[self::space()]['autoC'] as &$v) {
            //触发加载回调
            call_user_func($v, $class);
            //类加载成功
            if (class_exists($class, false)) break;
        }
        //关闭串行化
        self::serial(false);
    }

    /**
     * 描述 : 错误控制
     * 返回 :
     *      error为null时, 返回接管方法对象
     * 注明 :
     *      方法调用区分参数($error)
     *          null  : @符代码开始时调用, 结束时调用__set
     *          true  : try 开始时调用
     *          false : eMark=''时为try结束时调用, 否则为catch开始时调用
     *          其它  : 错误与异常捕获回调
     * 作者 : Edgar.lee
     */
    public static function errCtrl($error = null, $eMark = '', $file = '', $line = '', $vars = array()) {
        //swoole自身产生的错误, Co::resume错误 ? 不做记录 : 发给进程
        if (empty(self::$attrs[$space = self::space()])) return !!strpos($eMark, '::resume');
        //引用错误处理数据
        $eData = &self::$attrs[$space]['error'];

        //屏蔽错误, @符代码处调用
        if ($error === null) {
            //设置当前协程错误级别为0
            array_unshift($eData['level'], 0);

            //返回控制对象
            return self::$attrs[$space]['super']['_FUNC_mapVar'];
        //调用异常处理块, 记录错误等级池数量
        } else if ($error === true) {
            array_unshift($eData['catch'], array(
                'mark' => $eMark, 'eNum' => count($eData['level']), 'iNum' => self::$incNo
            ));
        //移除异常处理块
        } else if ($error === false) {
            //异常被捕捉
            if ($eMark) {
                foreach ($eData['catch'] as $k => &$v) {
                    //匹配到对应异常块
                    if ($v['mark'] === $eMark) {
                        //保留记录的等级池数量
                        array_splice($eData['level'], 0, -$v['eNum']);
                        //移除自身及未拦截的捕捉列表
                        array_splice($eData['catch'], 0, $k + 1);
                        //恢复包含文件层级(恢复前有值 && 恢复后无值 && 重写设置协程)
                        self::$incNo === $v['iNum'] || self::serial($v['iNum']);
                        //恢复完成
                        break ;
                    }
                }
            //异常块正常结束
            } else {
                array_shift($eData['catch']);
            }
        //错误和异常处理, 由 setErrorHandler 初始化
        } else if ((($code = &self::$attrs[$space]['state']['code']) & 4) === 0) {
            //移除全局变量(移除7.2.0弃用的set_error_handler.errcontext参数)
            $vars = array();

            //是异常, 引用异常回调列表
            if ($isObj = is_object($error)) {
                $index = &$eData['tList'];
                $errno = 1;
            //是错误, 引用错误回调列表
            } else {
                $index = &$eData['eList'];
                $errno = $error;
            }

            //存在回调 && 错误码在处理范围内
            if ($done = $index && $index[0][1] & $errno) {
                try {
                    //标记错误处理
                    $code |= 4;
                    //回调处理函数
                    $done = call_user_func($index[0][0], $error, $eMark, $file, $line);
                //屏蔽二次异常
                } catch (Throwable $e) {
                } finally {
                    //错误处理结束
                    $code &= ~4;
                }
            }

            //完成结果, false=未完成, true=已完成
            $done === false && $eData['last'] = $isObj ? array(
                //异常代码, 异常消息
                'type' => $error->getCode(), 'message' => $error->getMessage(),
                //异常文件, 异常行
                'file' => $error->getFile(), 'line' => $error->getLine(),
            ) : array(
                //异常代码, 异常消息
                'type' => $error, 'message' => $eMark,
                //异常文件, 异常行
                'file' => $file, 'line' => $line,
            );
        }
    }

    /**
     * 描述 : 获取协程ID
     * 作者 : Edgar.lee
     */
    private static function space() {
        return self::$onCid ?? Co::getCid();
    }

    /**
     * 描述 : 开关串行化
     * 参数 :
     *      type : 是否开启串行, true=开启, false=禁用, int=恢复到指定层级
     * 返回 :
     *      返回操作前串行层次
     * 作者 : Edgar.lee
     */
    private static function serial($type) {
        //引用串行化层级
        $incNo = &self::$incNo;
        //返回操作前串行层次
        $result = $incNo;

        //禁用协程
        if ($type) {
            //未禁用协程
            if ($incNo === 0) {
                //关闭抢占式调度
                SWOOLE_SCHEDULER && Co::disableScheduler();
                //临时关闭协程(防止类加载不全, spl_autoload_register回调报错等问题)
                Swoole\Runtime::enableCoroutine(false);
            }
            //非数字 ? 增加包含层次 : 设置指定值
            $type === true ? ++$incNo : $incNo = $type;
        //恢复协程
        } else {
            //是数字 && 串行状态 && 准备关闭串行
            $type === 0 && $incNo && $incNo = 1;

            //串行状态 && 可以关闭串行
            if ($incNo && --$incNo === 0) {
                //开启函数协程化
                Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_FULL);
                //开启抢占式调度
                SWOOLE_SCHEDULER && Co::enableScheduler();
            }

            //引用状态信息
            $state = &self::$yield['state'];
            //守望者已挂起 && 有待办任务
            while ($incNo === 0 && ($state['code'] & 3) === 2) {
                //尝试恢复协程成功
                if ($incNo || @Co::resume($state['space'])) {
                    break ;
                //10ms后重试
                } else {
                    usleep(10000);
                }
            }
        }

        return $result;
    }

    /**
     * 描述 : 暂停协程并在任务完成后恢复
     * 参数 :
     *      type : 任务类型
     *      data : 任务数据
     * 作者 : Edgar.lee
     */
    private static function yield($type, $data = array()) {
        //当前空间
        $space = self::space();

        //移除历史任务
        unset(self::$yield[$type][$space]);
        //添加挂起任务到结尾
        self::$yield[$type][$space] = array('state' => true, 'data' => &$data, 'result' => &$reslut);

        //标记有待办任务且守望者停止 && 恢复守望者
        $incNo = (self::$yield['state']['code'] |= 2) === 2 ? self::serial(0) : 0;
        //挂起协程
        Co::yield();
        //恢复串行状态
        $incNo && self::serial($incNo);

        //移除当前任务
        unset(self::$yield[$type][$space]);
        //检查退出
        isset(self::$attrs[$space]) && self::$attrs[$space]['super']['_FUNC_mapVar']->checkExit(3);
        //返回结果
        return $reslut;
    }

    /**
     * 描述 : 任务哨兵, 协程后台运行
     * 作者 : Edgar.lee
     */
    private static function sentry($serv, $mCid) {
        //毫秒计数器
        $count = 0;
        //不限制内存
        ini_set('memory_limit', -1);

        //健康及退出检查
        go(function () use (&$serv, &$mCid) {
            //健康检查网址
            $health = &$GLOBALS['system']['health'];
            //引用进程最大内存
            $memory = &$GLOBALS['system']['memory'];
            //引用工作周期时间
            ($cycle = &$GLOBALS['system']['cycle']) && $cycle += time();
            //支持延迟触发信号 && 安装SIGTERM信号处理器
            $mCid && function_exists('pcntl_signal_dispatch') && pcntl_signal(15, function () {
                self::$isEnd = true;
            });

            do {
                sleep(10);
                //更新最近时间
                self::$nTime = time();
                //网络服务 && 健康检查 && 负载低
                $serv && $health && !self::$yield['waits'] &&
                    //概率选中 && 发送健康检查
                    rand(1, $GLOBALS['server']['worker_num']) === 1 && file_get_contents($health);
                //归还内存给系统
                gc_mem_caches();

                //检查退出条件, 任务模式
                if ($mCid) {
                    if (
                        //主协程结束
                        !Co::exists($mCid) ||
                        //内存溢出检查
                        ($memory > 0 && memory_get_usage() > $memory) ||
                        //工作周期检查
                        $cycle && self::$nTime > $cycle
                    ) {
                        self::$isEnd = true;
                    } else {
                        //信号检查
                        pcntl_signal_dispatch();
                    }
                //检查退出条件, 网络服务
                } else {
                    //工作周期检查
                    if ($cycle && self::$nTime > $cycle) {
                        $serv->stop($serv->worker_id, true);
                    //内存溢出检查
                    } else if ($memory > 0) {
                        $temp = 0;
                        //计算协程总内存
                        foreach (self::$attrs as &$v) $temp += $v['state']['memory'][3];
                        //编译内存 > 1G && 重启工作进程
                        memory_get_usage() - $temp > $memory && $serv->stop($serv->worker_id, true);
                    }
                }
            } while (!self::$isEnd);

            //引用守望者状态信息
            $state = &self::$yield['state'];
            //守望者已挂起
            while (!$state['code']) {
                //尝试恢复协程成功
                if (@Co::resume($state['space'])) {
                    break ;
                //10ms后重试
                } else {
                    usleep(10000);
                }
            }
        });

        //启动守望者
        go(function () {
            //工作是否开始退出
            $isEnd = &self::$isEnd;
            //引用协程列表
            $attrs = &self::$attrs;
            //引用持久锁
            $flock = &self::$yield['flock'];
            //引用协程任务
            $async = &self::$yield['async'];
            //引用响应请求
            $resCo = &self::$yield['resCo'];
            //引用排队请求
            $waits = &self::$yield['waits'];
            //引用状态信息
            $state = &self::$yield['state'];
            //当前协程ID
            $state['space'] = Co::getCid();

            do {
                //每10ms遍历一遍
                usleep(10000);
                //无持久锁 && 无待请求
                if (!$flock && !$waits) {
                    //标记守望者已挂起
                    $state['code'] = 0;
                    //(已退出 || 有持久锁 || 有异步任务 || 有待请求) || 挂起协程
                    $isEnd || $flock || $async || $waits || Co::yield();
                    //标记守望者已恢复
                    $state['code'] = 1;
                }

                //遍历暂停队列
                foreach ($flock as $k => &$v) {
                    //引用任务数据
                    $data = &$v['data'];
                    //引用超时配置
                    $exeTime = &$attrs[$k]['state']['exeTime'];

                    //任务未完成 && 尝试锁成功
                    if ($v['state'] && flock($data['stream'], $data['operate'], $data['block'])) {
                        //标记已完成
                        $v['state'] = false;
                        //回写结果集
                        $v['result'] = true;
                    }

                    if (
                        //任务已完成
                        !$v['state'] ||
                        //设置了超时 && 已超时
                        $exeTime[0] && $exeTime[1] < self::$nTime
                    ) {
                        //移除引用资源, 防止引用stream导致应用层无法通过unset释放锁
                        unset($v, $data);
                        //恢复暂停协程(true=成功, false=协程未挂起)
                        Co::resume($k);
                    }
                }

                //启动异步协程
                foreach ($async as $k => &$v) {
                    unset($async[$k]);
                    go($v);
                }

                //计算接受请求数
                $num = SWOOLE_MAX_REQUEST - count($resCo);
                //响应排队中的请求
                foreach ($waits as $k => &$v) {
                    //已接受请求, 恢复协程
                    if (!$v['state']) {
                        Co::resume($k);
                    //有空闲调度空间
                    } else if (--$num > -1) {
                        //标记已完成
                        $v['state'] = false;
                        //移到响应请求队列中
                        $resCo[$k] = array();
                        //尝试恢复协程
                        Co::resume($k);
                    } else {
                        break ;
                    }
                }
            //没退出工作 || 有协程处理 || 有异步任务 || 有等待请求
            } while (!$isEnd || $attrs || $async || $waits);
        });
    }

    /**
     * 描述 : 获取K M G结尾的字节数
     * 作者 : Edgar.lee
     */
    private static function getBitSize($size) {
        //字节单位转换率
        static $bits = array('K' => 1024, 'M' => 1048576, 'G' => 1073741824);
        //进程最大内存转换成字节
        return preg_match('@K|M|G@', strtoupper($size), $temp) ?
            $bits[$temp[0]] * (float)$size : (float)$size;
    }

    /**
     * 描述 : 代码改写
     * 参数 :
     *     &code : 改写的代码
     *      gVar : 是否收集全局变量, 0=不收集, 1=在全局, 3=在全局且为入口文件
     *      ctrl : 含逻辑流程代码, true=含有, false=不含
     * 注明 :
     *      代码改写逻辑
     *          代码加载协议封装, include => include 'of.incl://' . \Co::getCid() . '://是否全局://路径'
     *          代码执行方法改写, eval => include 'of.incl://' . \Co::getCid() . '://是否全局://路径://代码'
     *          共享变量声明改写, global static => swoole::shareVar
     *          静态变量调用改写, self:: parent:: static:: space\class:: => ReflectionClass映射
     *          超全局变量初始化, $XXX 替换$XXX_mapVar, 增加初始方法 swoole::loadEnv
     *          系统方法列表替换, $func的"键方法"替换为"$_FUNC_mapVar->值方法"
     *          框架特性改写逻辑
     *              在of::dispatch方法中将self::event替换为$_FUNC_mapVar->dispatch
     *              class外有class::init调用, 记录下来
     *      待处理列表($wait) : [{
     *          "pos" : 位置,
     *          "len" : 长度,
     *          "str" : 替换
     *      }, ...]
     *      代码树列表($tree) : [{
     *          "type" : 类型(1=g全局, 2=a方法, 4=c类, 8=i接口, 16=t复用),
     *          "sPos" : 最近低风险代码插入位置
     *          "life" : 生命周期, 每个"{"+1, 每个"}"-1, 为0时弹出
     *          "name" : 结构名字, 仅类有值
     *          "uuid" : 代码块唯一编码
     *          "isStatic" : 是否进入静态变量, 0=未进入, 1=已进入
     *          "isGlobal" : 是否进入全局变量, 0=未进入, 1=已进入
     *          "isQuotes" : 是否进入双引号字符串中, 0=未进入, 1=已进入
     *          "varList"  : 静态或全局的变量列表["变量名 => 变量值", ...]
     *      }, ...]
     *      异常处理块列表($trys) : {{
     *          "mode" : 1=try, 2=catch
     *          "save" : "life"记忆点, 识别到"}"且与此相同时退出异常块
     *          "mark" : 处理块唯一标识
     *      }, ...}
     *      硬缓存代码处理($hard) : 将无法一次编译的代码放到方法中执行, 解决php每次编译文件均占用内存问题 {
     *          "isOn" : 是否需要缓存, 0=不需要, 1=需要
     *          "save" : "life"记忆点, 识别到匹配的"}"或结尾时插入缓存代码
     *          "cPos" : 缓存代码起始位置
     *      }
     * 作者 : Edgar.lee
     */
    private static function codeParser(&$code, $gVar, &$ctrl = false) {
        //超全局变量列表
        static $super = array(
            '$GLOBALS' => 1, '$_SERVER' => 1, '$_ENV' => 1,
            '$_GET' => 1, '$_POST' => 1, '$_FILES' => 1,
            '$_COOKIE' => 1, '$_SESSION' => 1, '$_REQUEST' => 1,
        );
        //类的修饰符
        static $cMode = array(T_WHITESPACE => 1, T_ABSTRACT => 1, T_FINAL => 1, T_READONLY => 1);
        //默认树结构
        static $strur = array(
            'type' => 1, 'sPos' => 0, 'life' => 0,
            'isStatic' => 0, 'isGlobal' => 0, 'isQuotes' => 0,
            'varList' => array(), 'name' => '', 'uuid' => ''
        );
        //接管方法列表
        static $func = array(
            //会话方法
            'session_abort' => 'sessionAbort', 'session_commit' => 'sessionCommit',
            'session_decode' => 'sessionDecode', 'session_destroy' => 'sessionDestroy',
            'session_encode' => 'sessionEncode', 'session_name' => 'sessionName',
            'session_regenerate_id' => 'sessionRegenerateId', 'session_id' => 'sessionId',
            'session_write_close' => 'sessionCommit', 'session_gc' => 'sessionGc',
            'session_reset' => 'sessionReset', 'session_start' => 'sessionStart',
            'session_status' => 'sessionStatus', 'session_unset' => 'sessionUnset',
            'session_create_id' => 'sessionCreateId',
            //头方法
            'header' => 'header',
            //cookie方法
            'setcookie' => 'setCookie', 'setrawcookie' => 'setRawCookie',
            //输出控制
            'ob_clean' => 'obClean', 'ob_end_clean' => 'obEndClean',
            'ob_end_flush' => 'obEndFlush', 'ob_flush' => 'obFlush',
            'ob_get_clean' => 'obGetClean', 'ob_get_contents' => 'obGetContents',
            'ob_get_flush' => 'obGetFlush', 'ob_get_length' => 'obGetLength',
            'ob_get_level' => 'obGetLevel', 'ob_get_status' => 'obGetStatus',
            'ob_list_handlers' => 'obListHandlers',
            //已接管的加载类方法
            'spl_autoload_functions' => 'splAutoloadFunctions', 'spl_autoload_register' => 'splAutoloadRegister',
            'spl_autoload_unregister' => 'splAutoloadUnregister',
            //错误控制
            'debug_backtrace' => 'debugBacktrace', 'error_clear_last' => 'errorClearLast',
            'error_reporting' => 'errorReporting', 'set_exception_handler' => 'setExceptionHandler',
            'restore_error_handler' => 'restoreErrorHandler', 'set_error_handler' => 'setErrorHandler',
            'restore_exception_handler' => 'restoreExceptionHandler', 'error_get_last' => 'errorGetLast',
            //信号处理
            'pcntl_signal' => 'pcntlSignal', 'pcntl_signal_dispatch' => 'pcntlSignalDispatch',
            //时间相关方法
            'sleep' => 'sleep', 'usleep' => 'usleep',
            'ini_set' => 'iniSet', 'ini_get' => 'iniGet',
            'set_time_limit' => 'setTimeLimit',
            //文件流相关方法
            'file_get_contents' => 'fileGetContents', 'file_put_contents' => 'filePutContents',
            'fopen' => 'fopen', 'flock' => 'flock',
            //其它方法
            'php_sapi_name' => 'phpSapiName', 'define' => 'define',
            'register_shutdown_function' => 'registerShutdownFunction', 'class_alias' => 'classAlias',
            'memory_get_usage' => 'memoryGetUsage', 'get_included_files' => 'getIncludedFiles',
            'get_required_files' => 'getIncludedFiles', 'call_user_func_array' => 'callable',
            'call_user_func' => 'callable',
        );
        //唯一ID计数
        static $uuid = 0;

        //待处理列表
        $wait = array();
        //代码树列表, 记录类和方法的顺序
        $tree = array(array('isGlobal' => $gVar, 'life' => 1) + $strur);
        //异常处理块列表
        $trys = array();
        //硬缓存代码块
        $hard = array('save' => 1, 'cPos' => 0, 'isOn' => 0);
        //解析关键词 php >= 7.0 支持第二参数
        $keys = token_get_all($code, TOKEN_PARSE);
        //解析偏移量
        $kPos = 0;

        //定位关键词
        foreach ($keys as $kk => &$vk) {
            //引用自身代码树
            $self = &$tree[0];

            //是数组
            if (isset($vk[2])) {
                //关键词长度
                $kLen = strlen($vk[1]);
                //生成待替换清单
                switch ($vk[0]) {
                    //开始标签
                    case T_OPEN_TAG:
                        //在全局中
                        if ($self['type'] === 1 && $self['life'] === 1) {
                            //更新低风险代码插入位置
                            $self['sPos'] = $kPos + $kLen;
                            //未记录缓存位置 && 更新缓存位置并记录life层级
                            if (!$hard['cPos']) {
                                $hard['cPos'] = $self['sPos'];
                                $hard['save'] = 1;
                            }
                        }
                        break;
                    //use关键词
                    case T_USE:
                        //在全局更新低风险代码插入位置, 在全局执行标记映射
                        if ($self['type'] !== 1) {
                            //在类中 && 标记映射
                            $self['type'] === 4 && $self['varList']['use'] = 'use';
                            break ;
                        }
                    //声明declare, 声明namespace
                    case T_DECLARE:
                    case T_NAMESPACE:
                        //在全局中更新低风险代码插入位置
                        $temp = 1;
                        //寻找";"或"{"位置
                        for ($i = $kk; ++$i;) {
                            if ($keys[$i][0] === ';' || $keys[$i][0] === '{') {
                                //当前代码位置 + 关键词长度 + 目标位置
                                $self['sPos'] = $kPos + $kLen + $temp;
                                //更新缓存位置
                                $hard['cPos'] = $self['sPos'];
                                //记录缓存life层级
                                $hard['save'] = $keys[$i][0] === ';' ? 1 : 2;
                                break ;
                            } else {
                                $temp += isset($keys[$i][2]) ? strlen($keys[$i][1]) : 1;
                            }
                        }
                        break;
                    //代码加载 include require
                    case T_INCLUDE:
                    case T_INCLUDE_ONCE:
                    case T_REQUIRE:
                    case T_REQUIRE_ONCE:
                        //更改代码加载协议
                        $wait[] = array(
                            'pos' => $kPos,
                            'len' => $kLen,
                            'str' => "\$_FUNC_mapVar->incEnd = {$vk[1]} 'of.incl://' . " .
                                '\Co::getCid() . \'://\' . isset($_GLOBAL_SCOPE_) . \'://\' . ' .
                                '$_FUNC_mapVar->incStart = '
                        );
                        break;
                    //代码执行
                    case T_EVAL:
                        //读取后面"("位置
                        self::codeKey($keys, $kk, 1, $temp);
                        //更改代码执行方法
                        $wait[] = array(
                            'pos' => $kPos,
                            'len' => $kLen + $temp['eLen'],
                            'str' => '($_FUNC_mapVar->incEnd = include \'of.incl://\' . ' .
                                '\Co::getCid() . \'://\' . isset($_GLOBAL_SCOPE_) . \'://\' . ' .
                                '($_FUNC_mapVar->incStart = __FILE__ . \'(\' . __LINE__ . \')\') . \'://\' . '
                        );
                        break;
                    //类
                    case T_CLASS:
                        //读取类名
                        self::codeKey($keys, $kk, 1, $temp);
                        //计算重复声明判断插入位置
                        $temp['pStr'] = '';
                        for ($i = $kk; --$i;) {
                            //类修饰符 抽象 锁定 只读 空格
                            if (isset($cMode[$keys[$i][0]])) {
                                $temp['pStr'] .= $keys[$i][1];
                            } else {
                                break ;
                            }
                        }
                        //插入重复声明类判断
                        $wait[] = array(
                            'pos' => $kPos - strlen(rtrim($temp['pStr'])),
                            'len' => 0,
                            'str' => ($self['type'] === 1 && $self['life'] === $hard['save'] ?
                                    '/*!swoole rewrite: start!*/' : ''
                                ) . "if (!class_exists(__NAMESPACE__ . '\\{$temp['text']}', false)) {"
                        );
                        //硬缓存代码未开启 && 在全局 && 在空间里的"{"中 && 开启硬缓存代码
                        !$hard['isOn'] && $self['type'] === 1 && $self['life'] > $hard['save'] && $hard['isOn'] = 1;
                        //压入未初始化的类
                        array_unshift($tree, array('type' => 4, 'name' => $temp['text'], 'uuid' => ++$uuid) + $strur);
                        break;
                    //接口
                    case T_INTERFACE:
                        //读取接口名
                        $temp = self::codeKey($keys, $kk, 1);
                        //插入重复声明复用判断
                        $wait[] = array(
                            'pos' => $kPos,
                            'len' => 0,
                            'str' => ($self['type'] === 1 && $self['life'] === $hard['save'] ?
                                    '/*!swoole rewrite: start!*/' : ''
                                ) . "if (!interface_exists(__NAMESPACE__ . '\\{$temp}', false)) {"
                        );
                        //硬缓存代码未开启 && 在全局 && 在空间里的"{"中 && 开启硬缓存代码
                        !$hard['isOn'] && $self['type'] === 1 && $self['life'] > $hard['save'] && $hard['isOn'] = 1;
                        //压入未初始化的方法
                        array_unshift($tree, array('type' => 8, 'uuid' => ++$uuid) + $strur);
                        break;
                    //复用
                    case T_TRAIT:
                        //读取复用名
                        $temp = self::codeKey($keys, $kk, 1);
                        //插入重复声明复用判断
                        $wait[] = array(
                            'pos' => $kPos,
                            'len' => 0,
                            'str' => ($self['type'] === 1 && $self['life'] === $hard['save'] ?
                                    '/*!swoole rewrite: start!*/' : ''
                                ) . "if (!trait_exists(__NAMESPACE__ . '\\{$temp}', false)) {"
                        );
                        //硬缓存代码未开启 && 在全局 && 在空间里的"{"中 && 开启硬缓存代码
                        !$hard['isOn'] && $self['type'] === 1 && $self['life'] > $hard['save'] && $hard['isOn'] = 1;
                        //压入未初始化的方法
                        array_unshift($tree, array('type' => 16, 'uuid' => ++$uuid) + $strur);
                        break;
                    //方法
                    case T_FUNCTION:
                        if (strtolower(self::codeKey($keys, $kk, -1)) !== 'use') {
                            //读取方法名(可能是匿名方法)
                            ($temp = self::codeKey($keys, $kk, 1, $data)) === '(' && $temp = '';
                            //引用返回符 && 获取后面的方法名
                            $temp === '&' && $temp = self::codeKey($keys, $data['ePos'], 1);

                            //在类 || 接口 || 复用中
                            if ($self['type'] & 28) {
                                //退出静态变量(复用不用此代码, 为的是不执行独立方法中的代码)
                                $self['isStatic'] = 0;
                            //独立方法
                            } else if ($temp) {
                                //插入重复声明方法判断
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => 0,
                                    'str' => ($self['type'] === 1 && $self['life'] === $hard['save'] ?
                                            '/*!swoole rewrite: start!*/' : ''
                                        ) . "if (!function_exists(__NAMESPACE__ . '\\{$temp}')) {"
                                );
                                //硬缓存代码未开启 && 在全局 && 在空间里的"{"中 && 开启硬缓存代码
                                !$hard['isOn'] && $self['type'] === 1 && $self['life'] > $hard['save'] && $hard['isOn'] = 1;
                            }
                            //压入未初始化的方法
                            array_unshift($tree, array('type' => 2, 'name' => $temp, 'uuid' => ++$uuid) + $strur);
                        }
                        break;
                    //try关键词
                    case T_TRY:
                        array_unshift($trys, array(
                            'mode' => 1,
                            'save' => $self['life'],
                            'mark' => ++$uuid,
                            'uuid' => $self['uuid']
                        ));
                        break;
                    //catch关键词
                    case T_CATCH:
                        array_unshift($trys, array(
                            'mode' => 2,
                            'save' => $self['life'],
                            'mark' => $trys[0]['mark'],
                            'uuid' => $self['uuid']
                        ));
                        break;
                    //global关键词
                    case T_GLOBAL:
                        //进入全局变量
                        $self['type'] === 2 && $self['isGlobal'] = 1;
                        break;
                    //static关键词
                    case T_STATIC:
                        //不是静态操作
                        if (self::codeKey($keys, $kk, 1) !== '::') {
                            //进入静态变量
                            $self['isStatic'] = 1;
                            //寻找 static int $var 结构的 int
                            self::codeKey($keys, $kk, 1, $temp);
                            //是纯字母的文本(文本长度大于1 && 不是$开头)
                            if ($temp['tLen'] > 1 && $temp['text'][0] !== '$') {
                                //寻找 static int $var 结构的 $var
                                $temp['text'] = self::codeKey($keys, $temp['ePos'], 1);
                                //是变量 && 删除变量类型结构
                                $temp['text'][0] === '$' && $wait[] = array(
                                    //当前代码位置 + 关键词长度 + 目标位置
                                    'pos' => $kPos + $kLen + $temp['eLen'] - $temp['tLen'],
                                    'len' => $temp['tLen'],
                                    'str' => ''
                                );
                            }
                        }
                        break;
                    //变量
                    case T_VARIABLE:
                        //前后非空关键词
                        $temp = array(
                            'lc' => self::codeKey($keys, $kk, -1),
                            'rc' => self::codeKey($keys, $kk, 1)
                        );

                        //调用静态变量, 前为"::" && 后非"("
                        if ($temp['lc'] === '::' && $temp['rc'] !== '(') {
                            //插入映射方法改写静态变量为对象
                            $wait[] = array(
                                'pos' => $kPos + $kLen,
                                'len' => 0,
                                'str' => '->getVar()[\'' . substr($vk[1], 1) . '\']'
                            );
                        //独立变量代码, 前后都不为::和->
                        } else if (
                            $temp['lc'] !== '::' &&
                            $temp['lc'] !== '->' &&
                            $temp['rc'] !== '::' &&
                            $temp['rc'] !== '->'
                        ) {
                            //超全局变量映射
                            if (isset($super[$vk[1]])) {
                                //插入映射方法改写静态变量为对象
                                $wait[] = array(
                                    'pos' => $kPos + $kLen,
                                    'len' => 0,
                                    'str' => '_mapVar'
                                );
                            //在全局中 && 在全局变量中
                            } else if ($self['type'] === 1 && $self['isGlobal']) {
                                //进入catch异常块中
                                if ($trys && $trys[0]['mode'] === 2 && $trys[0]['save'] === $self['life']) {
                                    //记录"{"绝对偏移量
                                    $temp = 1;
                                    //寻找"{"位置
                                    for ($i = $kk; ++$i;) {
                                        if ($keys[$i][0] === '{') {
                                            //"catch (Exception $e) {..."改为"...$e) {$GLOBALS_mapVar['e'] = $e;..."
                                            $wait[] = array(
                                                'pos' => $kPos + $kLen + $temp,
                                                'len' => 0,
                                                'str' => '$GLOBALS_mapVar[\'' . substr($vk[1], 1) . "'] = {$vk[1]};"
                                            );
                                            break ;
                                        } else {
                                            $temp += isset($keys[$i][2]) ? strlen($keys[$i][1]) : 1;
                                        }
                                    }
                                } else {
                                    //改为直接操作全局变量
                                    $wait[] = array(
                                        'pos' => $kPos,
                                        'len' => $kLen,
                                        'str' => '$GLOBALS_mapVar[\'' . substr($vk[1], 1) . '\']'
                                    );
                                }
                            //不在复用中的共享声明 || 在全局变量中
                            } else if (
                                $self['type'] !== 16 && ($self['isStatic'] || $self['isGlobal'])
                            ) {
                                //记录全局或静态变量
                                $self['varList'][$vk[1]] = '\'' . substr($vk[1], 1) . "' => &{$vk[1]}";
                            }
                        }
                        break;
                    //接管方法
                    case T_STRING:
                        if (
                            (
                                //接管指定常量
                                $vk[1] === 'PHP_SAPI' || $vk[1] === 'OF_DEBUG' ||
                                //命中接管名称 && 是方法调用
                                isset($func[$lf = strtolower($vk[1])]) && self::codeKey($keys, $kk, 1, $data) === '('
                            ) && (
                                //独立语句
                                strlen($temp = self::codeKey($keys, $kk, -1)) === 1 ||
                                //不为方法名
                                $temp !== 'function' &&
                                //不为类名
                                $temp !== 'class' &&
                                //不为复用名
                                $temp !== 'trait' &&
                                //非静态调用
                                $temp !== '::' &&
                                //非动态调用
                                $temp !== '->'
                            )
                        ) {
                            //改写PHP_SAPI常量
                            if ($vk[1] === 'PHP_SAPI') {
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => $kLen,
                                    'str' => '\'fpm-fcgi\''
                                );
                            //改写OF_DEBUG常量
                            } else if ($vk[1] === 'OF_DEBUG') {
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => $kLen,
                                    'str' => '$_SERVER_mapVar[\'ofDebug\']'
                                );
                            //调用回调函数
                            } else if ($func[$lf] === 'callable') {
                                $wait[] = array(
                                    'pos' => $kPos + strlen($lf) + $data['eLen'],
                                    'len' => 0,
                                    'str' => "\$_FUNC_mapVar->callable = "
                                );
                            //接管指定方法
                            } else {
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => $kLen,
                                    'str' => "\$_FUNC_mapVar->{$func[$lf]}"
                                );
                            }
                        }
                        break;
                    //创建对象new
                    case T_NEW:
                        //不在全局变量中 && 常规类名
                        if ($self['isGlobal'] === 0 && self::codeClass($keys, $kk, 1, $data)) {
                            //插入主动加载类
                            $wait[] = array(
                                //插入位置
                                'pos' => $kPos,
                                //当前位置
                                'len' => 0,
                                //主动加载类
                                'str' => "\$_FUNC_mapVar->loadClass({$data['name']}, true)->newObj = "
                            );
                        }
                        break;
                    //静态操作::
                    case T_DOUBLE_COLON:
                        //在代码体中 && 常规类名
                        if ($self['life'] > 0 && self::codeClass($keys, $kk, -1, $data)) {
                            //获取右侧方法名
                            self::codeKey($keys, $kk, 1, $temp);

                            //在全局中 && 调用init方法
                            if (
                                $self['type'] === 1 &&
                                trim($temp['text'], '_') === 'init'
                            ) {
                                //右侧括号位置
                                self::codeKey($keys, $temp['ePos'], 1, $temp['right']);
                                //注册init方法
                                $wait[] = array(
                                    //当前位置 - 到类名长度
                                    'pos' => $kPos - $data['eLen'],
                                    //当前位置到类名长度 + 2(::) + 到init长度 + 到(长度
                                    'len' => $data['eLen'] + 2 + $temp['eLen'] + $temp['right']['eLen'],
                                    //获取命名含空间的类名::init
                                    'str' => "\swoole::reinit({$data['name']} . '::{$temp['text']}'"
                                );
                            //主动加载类
                            } else if ($temp['text'] !== 'class') {
                                $wait[] = array(
                                    //插入位置, 当前位置 - 类名长度
                                    'pos' => $kPos - $data['eLen'],
                                    //当前位置到类名长度 + 2(::) + 到init长度 + 到(长度
                                    'len' => $data['eLen'],
                                    //获取命名含空间的类名::init
                                    'str' => '$_FUNC_mapVar->loadClass(' . $data['name'] . ')'
                                );
                            }
                        }
                        break;
                    //heredoc开始
                    case T_START_HEREDOC:
                        $self['isQuotes'] = 1;
                        break;
                    //heredoc结束
                    case T_END_HEREDOC:
                        $self['isQuotes'] = 0;
                        break;
                    //字符串
                    case T_CONSTANT_ENCAPSED_STRING:
                        switch (trim($vk[1], '"\'')) {
                            case 'of::dispatch':
                                if (
                                    //在of::dispatch方法中
                                    $self['type'] === 2 && $self['name'] === 'dispatch' &&
                                    //定位到self::event('of::dispatch'代码
                                    substr($code, $kPos - 12, 11) === 'self::event'
                                ) {
                                    $wait[] = array(
                                        //替换self::event为swoole::dispatch
                                        'pos' => $kPos - 12,
                                        'len' => 11,
                                        'str' => '$_FUNC_mapVar->dispatch'
                                    );
                                }
                                break;
                        }
                        break;
                }
            //是字符
            } else {
                //关键词长度
                $kLen = 1;
                //标识符定位
                switch ($vk) {
                    case '"':
                        $self['isQuotes'] = ~$self['isQuotes'] & 1;
                        break;
                    case '{':
                        //首次进入在方法中
                        if ($self['type'] === 2 && $self['life'] === 0) {
                            //引用超全局变量
                            $wait[] = array(
                                'pos' => $kPos + 1,
                                'len' => 0,
                                'str' => ' extract(\swoole::loadEnv(), EXTR_REFS);'
                            );
                        //进入try catch异常块中
                        } else if ($trys && $trys[0]['save'] === $self['life'] && $trys[0]['uuid'] === $self['uuid']) {
                            //区分try与catch模式
                            $temp = $trys[0]['mode'] === 1 ? 'true' : 'false';
                            //插入"记录@堆栈位置"代码
                            $wait[] = array(
                                'pos' => $kPos + 1,
                                'len' => 0,
                                'str' => "\swoole::errCtrl({$temp}, {$trys[0]['mark']});"
                            );
                        }
                        //标记含有逻辑流程代码
                        $ctrl = true;
                        //更新低风险代码插入位置
                        $self['life'] || $self['sPos'] = $kPos + 1;
                        //记录生命周期
                        $self['isQuotes'] || ++$self['life'];
                        break;
                    case '}':
                        //销毁代码树
                        if ($self['isQuotes'] === 0 && --$self['life'] === 0) {
                            //类的结束
                            if ($self['type'] === 4) {
                                //插入映射方法改写静态变量为对象并结束重复声明类判断
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => 1,
                                    //有静态变量 ? 通过映射改写静态变量 : 结束重复声明类判断
                                    'str' =>
                                        //动态变量映射
                                        'public function __normalPropertiesMaps($sObj) {' .
                                            'if (get_class($sObj) === \'swoole\') {' .
                                                '$vMaps = array();' .
                                                'foreach ($this as $k => &$v) {$vMaps[$k] = &$v;}' .
                                                '$sObj->varMaps($vMaps);' .
                                            '}' .
                                        '}' .
                                        //静态变量映射
                                        ($self['varList'] ?
                                            'public static function __staticPropertiesMaps() {' .
                                                '$r = new \ReflectionClass(__CLASS__);' .
                                                '$r = $r->getStaticProperties();' .
                                                '$o = new \swoole(__CLASS__, $r);' .
                                                'foreach ($r as $k => &$v) {' .
                                                    //已是swoole对象(继承父类已实现) || 改为映射
                                                    'is_object(self::$$k) || self::$$k = $o;' .
                                                '}' .
                                            '}}' . $self['name'] . '::__staticPropertiesMaps();' :
                                            '}') .
                                        //插入重复声明复用结束
                                        ($tree[1]['type'] === 1 && $tree[1]['life'] === 1 ?
                                            '}/*!swoole rewrite: end!*/' : '}'
                                        )
                                );
                            //复用与接口的结束
                            } else if ($self['type'] & 24) {
                                //结束重复声明复用判断
                                $wait[] = array(
                                    'pos' => $kPos + 1,
                                    'len' => 0,
                                    'str' => ($tree[1]['type'] === 1 && $tree[1]['life'] === 1 ?
                                        '}/*!swoole rewrite: end!*/' : '}'
                                    )
                                );
                            //方法的结束 && 非匿名方法 && 独立方法(在全局中 || 在方法中)
                            } else if ($self['type'] === 2 && $self['name'] && $tree[1]['type'] & 3) {
                                //结束重复声明方法判断
                                $wait[] = array(
                                    'pos' => $kPos + 1,
                                    'len' => 0,
                                    'str' => ($tree[1]['type'] === 1 && $tree[1]['life'] === 1 ?
                                        '}/*!swoole rewrite: end!*/' : '}'
                                    )
                                );
                            }

                            //父层是全局环境
                            if ($tree[1]['type'] === 1) {
                                //若"}"后面是";"则用";"位置
                                $temp = self::codeKey($keys, $kk, 1, $temp) === ';' ? $temp['eLen'] : 0;
                                //更新上层表达式位置
                                $tree[1]['sPos'] = $kPos + 1 + $temp;
                            }
                            //销毁代码树
                            array_shift($tree);
                        //异常块结束
                        } else if ($trys && $trys[0]['save'] === $self['life'] && $trys[0]['uuid'] === $self['uuid']) {
                            //try代码块
                            if ($trys[0]['mode'] === 1) {
                                //插入"记录@堆栈位置"代码
                                $wait[] = array(
                                    'pos' => $kPos,
                                    'len' => 0,
                                    'str' => '\swoole::errCtrl(false);} catch (\Swoole\ExitException $e) {' .
                                        //不拦截exit异常
                                        "\swoole::errCtrl(false, {$trys[0]['mark']}); throw \$e;"
                                );
                            //catch结束
                            } else {
                                //弹出当前代码块
                                array_shift($trys);
                            }
                            //try-catch完整结束
                            strtolower(self::codeKey($keys, $kk, 1)) === 'catch' || array_shift($trys);
                        //需要硬缓存 && 在全局中 && 在对应的空间中
                        } else if ($hard['isOn'] && $self['type'] === 1 && $hard['save'] === $self['life'] + 1) {
                            self::codeHard($wait, $hard, $kPos);
                        }
                        break;
                    case ';':
                        //抽象方法
                        if ($self['life'] === 0) {
                            //弹出抽象方法
                            array_shift($tree);
                            //重新引用上级类
                            $self = &$tree[0];
                        }
                        //不在类中 && 有共享变量
                        if ($self['type'] !== 4 && $self['varList']) {
                            //改写静态或全局的变量列表
                            $wait[] = array(
                                'pos' => $kPos,
                                'len' => $kLen,
                                'str' => '; extract($_FUNC_mapVar->shareVar(array(' .
                                    join(', ', $self['varList']) .
                                '), ' . ($self['isStatic'] ? '__METHOD__' : 'false') . '), EXTR_REFS);'
                            );
                            //重置变量列表
                            $self['varList'] = array();
                        }
                        //退出静态变量
                        $self['isStatic'] = 0;
                        //在方法中 && 退出全局变量
                        $self['type'] === 2 && $self['isGlobal'] = 0;
                        break;
                    case '@':
                        $wait[] = array(
                            //当前位置
                            'pos' => $kPos,
                            'len' => $kLen,
                            'str' => '\swoole::errCtrl()->errOff = '
                        );
                        break;
                }
            }
            //更新偏移量
            $kPos += $kLen;
        }

        //插入硬缓存连接
        $hard['isOn'] && self::codeHard($wait, $hard, $kPos, "\n");
        //入口或配置类文件开启协程
        (($gVar & 2) || !$ctrl && self::$incNo === 1) && $hard['cPos'] && $wait[] = array(
            //当前位置
            'pos' => $hard['cPos'],
            'len' => 0,
            'str' => '$_FUNC_mapVar->incEnd = true;'
        );

        //待处理位置从小到大排序
        array_multisort(array_column($wait, 'pos'), $wait);
        //改写代码
        while ($temp = array_pop($wait)) {
            $code = substr_replace($code, $temp['str'], $temp['pos'], $temp['len']);
        }

        //打印调试信息
        if (self::$debug) {
            //打印重写的代码
            (self::$debug & 1) && print_r("{$code}\n^^^^^^^^^^^^^\n");
            //输出关键词信息
            if (self::$debug & 2) {
                foreach ($keys as &$vk) isset($vk[2]) && $vk['name'] = token_name($vk[0]);
                print_r($keys);
            }
            //打印指定的代码
            //strpos($code, '存储错误日志') && print_r($code);//echo "\n^^^^^^^^^^^^^\n";
            //print_r($tree);
            //print_r($wait);
        }
    }

    /**
     * 描述 : 查询代码前后非空格的关键词
     * 参数 :
     *     &keys : 解析的关键词列表
     *      kPos : 起始定位
     *      move : 寻找方向, -1=向前, 1=向后
     *     &data : 结果相关 {
     *          "ePos" => 关键词偏移位置
     *          "eLen" => 从(起始定位, 含文本信息]长度
     *          "tLen" => 文本信息长度
     *          "type" => 关键词类型, 单字符或int型
     *          "text" => 文本信息
     *      }
     * 返回 :
     *      关键词或单字符
     * 作者 : Edgar.lee
     */
    private static function codeKey(&$keys, $kPos, $move, &$data = null) {
        $data = array('ePos' => &$kPos, 'eLen' => 0);
        do {
            //移动位置
            $kPos += $move;

            //偏移量存在
            if (isset($keys[$kPos])) {
                //数组结构
                if (isset($keys[$kPos][2])) {
                    //统计关键词长度
                    $data['eLen'] += $data['tLen'] = strlen($keys[$kPos][1]);
                    //不为空返回索引
                    if ($keys[$kPos][0] !== T_WHITESPACE) {
                        $data['type'] = $keys[$kPos][0];
                        return $data['text'] = $keys[$kPos][1];
                    }
                //返回单字符
                } else {
                    //统计关键词长度
                    $data['eLen'] += $data['tLen'] = 1;
                    return $data['type'] = $data['text'] = $keys[$kPos];
                }
            //不存在返回失败
            } else {
                return 0;
            }
        } while (true);
    }

    /**
     * 描述 : 获取完整代码类名
     * 参数 :
     *     &keys : 解析的关键词列表
     *      kPos : 起始定位
     *      move : 寻找方向, -1=向前, 1=向后
     *     &data : 结果相关 {
     *          "eLen" : 从(起始定位, 含文本信息]长度
     *          "text" : 不含无效字符得类名
     *          "name" : 获取类名代码
     *      }
     * 返回 :
     *      true=类名有效, false=关键词类名
     * 作者 : Edgar.lee
     */
    public static function codeClass(&$keys, $kPos, $move, &$data = null) {
        //相关结果
        $data = array('eLen' => 0);
        //结果集
        $list = array();
        //递归命令
        $loop = null;
        //解析结构命令集
        $parse = $move > 0 ? array(
            //结束符: 常规命名空间, 绝对命名空间, 相对命名空间
            T_NAME_QUALIFIED => 1, T_NAME_FULLY_QUALIFIED => 1, T_NAME_RELATIVE => 1,
            //跳过符: 变量 $xx, "$"符, 标识符 parent, "\"空间隔离符
            T_VARIABLE => 2, '$' => 2, T_STRING => 2, T_NS_SEPARATOR => 2,
            //递归符
            '{' => array('{' => 1, '}' => -1, 'num' => 1),
            '[' => array('[' => 1, ']' => -1, 'num' => 1),
        ) : array(
            //结束符: 常规命名空间, 绝对命名空间, 相对命名空间
            T_NAME_QUALIFIED => 1, T_NAME_FULLY_QUALIFIED => 1, T_NAME_RELATIVE => 1,
            //结束符: 变量 $xx, "$"符
            T_VARIABLE => 1, '$' => 1,
            //跳过符: 标识符 parent, "\"空间隔离符
            T_STRING => 2, T_NS_SEPARATOR => 2,
            //递归符
            '}' => array('}' => 1, '{' => -1, 'num' => 1),
            ']' => array(']' => 1, '[' => -1, 'num' => 1),
            ')' => array(')' => 1, '(' => -1, 'num' => 1),
        );

        //偏移量存在
        while (isset($keys[$kPos += $move])) {
            //单字符或数字标识
            $code = $keys[$kPos][0];
            //完整代码文本
            $text = is_int($code) ? $keys[$kPos][1] : $code;

            //在递归中
            if ($loop) {
                //记录代码
                $list[] = $text;
                //是关键符
                isset($loop[$code]) &&
                    //完成递归闭环
                    ($loop['num'] += $loop[$code]) === 0 &&
                    //标记递归结束
                    $loop = null;
            //在结构集中
            } else if (isset($parse[$code])) {
                //记录代码
                $list[] = $text;
                //结束符
                if ($parse[$code] === 1) {
                    break ;
                //进入递归
                } else if (is_array($parse[$code])) {
                    $loop = $parse[$code];
                }
            //未知字符 && 已有结果, 结束查询
            } else if ($list) {
                break ;
            //未知字符 && 没有结果, 记录长度
            } else {
                $data['eLen'] += strlen($text);
            }
        }

        //反向查询 && 翻转结果
        $move < 0 && $list = array_reverse($list);
        //合并字符串
        $data['text'] = $data['name'] = $text = join($list);
        //记录完成长度
        $data['eLen'] += strlen($text);
        //未动态类 || 静态类追加"::class"
        $text[0] === '$' || $text[0] === '(' || $data['name'] .= '::class';
        //类名有效
        return $text !== 'self' && $text !== 'static' && $text !== 'parent' && $text !== 'class';
    }

    /**
     * 描述 : 插入硬缓存代码
     * 作者 : Edgar.lee
     */
    private static function codeHard(&$wait, &$hard, &$kPos, $pStr = '') {
        //硬缓存随机方法名
        $temp = '_' . uniqid();
        //插入硬缓存起始代码
        $wait[] = array(
            'pos' => $hard['cPos'],
            'len' => 0,
            'str' => '/*!swoole rewrite: start!*/' .
                'function ' . $temp . '(&$_) {' .
                    'extract($_, EXTR_REFS);' .
                    'unset($_);'
        );
        //插入硬缓存结束代码
        $wait[] = array(
            'pos' => $kPos,
            'len' => 0,
            'str' => $pStr . '}/*!swoole rewrite: end!*/' .
                'unset($_);' .
                '$_ = array(get_defined_vars());' .
                'foreach ($_[0] as $_[1] => &$_[2]) $_[0][$_[1]] = &${$_[1]};' .
                $temp . '($_[0]);' .
                'unset($_);'
        );
        //重置硬缓存
        $hard = array('save' => 1, 'cPos' => $kPos + 1, 'isOn' => 0);
    }

    /**
     * 描述 : 准备配置并调度服务
     * 作者 : Edgar.lee
     */
    public static function start() {
        //仅cli模式下运行
        if (PHP_SAPI !== 'cli') exit(self::test());
        //注入协议封装
        stream_wrapper_register('of.incl', __CLASS__);

        //默认启动配置
        $config = array(
            'system' => array(
                'port' => 8888, 'type' => 'http',
                'memory' => 0, 'cycle' => 0,
                'health' => '', 'reinit' => array(),
                'config' => '', 'define' => array(
                    //系的根路径, 框架根路径
                    'ROOT_DIR' => __DIR__, 'OF_DIR' => __DIR__ . '/include/of',
                    //相对命名空间 xx\yy, 绝对命名空间 \xx\yy
                    'T_NAME_QUALIFIED' => 314, 'T_NAME_FULLY_QUALIFIED' => 312, 
                    //自身命名空间 namespace\xxx, 只读关键词 readonly
                    'T_NAME_RELATIVE' => 313, 'T_READONLY' => 363
                )
            ),
            'server' => array(
                'hook_flags' => null, 'enable_preemptive_scheduler' => false,
                'max_wait_time' => 86400, 'worker_num' => swoole_cpu_num(),
                'dispatch_mode' => 1, 'max_request' => 1000
            ),
            'phpIni' => array(
                'max_execution_time' => 30, 'memory_limit' => ini_get('memory_limit')
            )
        );
        //读取启动参数
        foreach ($_SERVER['argv'] as &$v) {
            //"conf:配置文件绝对路径
            $temp = explode(':', $v, 2);

            //"xx:yy"模式的参数
            if (isset($temp[1])) {
                //保存到全局中
                $GLOBALS['_ARGV'][$temp[0]] = &$temp[1];

                switch ($temp[0]) {
                    //加载配置
                    case 'conf':
                        $config = array_replace_recursive($config, include $temp[1]);
                        $config['system']['config'] = 'conf:' . $temp[1];
                        break;
                    //启动类型, http=服务, task=任务
                    case 'type':
                        $config['system']['type'] = $temp[1];
                        break;
                    //设置备用时区
                    case '_tz':
                        ini_set('date.timezone', $temp[1]);
                        break;
                }
            }
        }
        //进程最大内存转换成字节
        $config['system']['memory'] = self::getBitSize($config['system']['memory']);
        //脚本最大内存转换成字节
        $config['phpIni']['memory_limit'] = self::getBitSize($config['phpIni']['memory_limit']);
        //初始化协程程度(兼容swoole 4.3+ SWOOLE_HOOK_NATIVE_CURL >= 4.6 SWOOLE_HOOK_CURL >= 4.4)
        isset($config['server']['hook_flags']) || $config['server']['hook_flags'] = SWOOLE_HOOK_ALL | (
            !defined('SWOOLE_HOOK_NATIVE_CURL') && defined('SWOOLE_HOOK_CURL') ? SWOOLE_HOOK_CURL : 0
        );

        //最大运行请求数
        define('SWOOLE_MAX_REQUEST', 100);
        //定义协程默认状态
        define('SWOOLE_HOOK_FULL', $config['server']['hook_flags']);
        //定义抢占调度设置
        define('SWOOLE_SCHEDULER', $config['server']['enable_preemptive_scheduler']);
        //定义常量初始值
        foreach ($config['system']['define'] as $k => &$v) defined($k) || define($k, $v);

        //强制开启抢占调度
        unset($config['server']['enable_preemptive_scheduler']);
        //读取服务器IP
        $temp = explode("\n", stream_get_contents(popen(
            'ifconfig -a|grep inet|grep -v 127.0.0.1|grep -v ::1|grep -v %|awk \'{print $2}\'',
            'r'
        ), 1024));
        $_SERVER['SERVER_ADDR'] = $temp[0] ? $temp[0] : '127.0.0.1';
        //引用系统配置
        $GLOBALS['system'] = &$config['system'];
        //引用服务配置
        $GLOBALS['server'] = &$config['server'];
        //引用php配置
        $GLOBALS['phpIni'] = &$config['phpIni'];
        //根目录长度
        $GLOBALS['rootLen'] = strlen(ROOT_DIR);

        //激活循环引用收集器
        gc_enable();
        //注册异常回调
        register_shutdown_function(__CLASS__ . '::clear', true);
        //开启全协程, 抢占模式
        Co::set(array('hook_flags' => SWOOLE_HOOK_FULL, 'enable_preemptive_scheduler' => true));
        //已开启抢占调度 || 禁用抢占调度(开启再禁用可以建立挂起的协程, 防止串行下的新协程直接执行)
        SWOOLE_SCHEDULER || Co::disableScheduler();

        //加载框架启动任务
        Co\run(function () {
            //准备环境数据
            $temp = new stdClass;
            $temp->header['host'] = 'http://127.0.0.1/';
            $temp->server = array(
                'path_info' => OF_DIR . '/index.php',
                'request_time' => $_SERVER['REQUEST_TIME'],
                'request_time_float' => $_SERVER['REQUEST_TIME_FLOAT'],
            );
            //注册超全局变量
            extract(self::loadEnv($temp), EXTR_REFS);
            //初始化代码
            self::reinit();
            //加载框架文件
            include 'of.incl://1://0://' . OF_DIR . '/of.php';
            //任务模式
            if ($GLOBALS['system']['type'] === 'task') {
                //执行任务回调
                $mCid = self::fork(unserialize(rawurldecode($GLOBALS['_ARGV']['data'])));
                //启动哨兵
                self::sentry(null, $mCid);
            }
        });
        //启动服务, 未来扩展为 WebSocket
        $config['system']['type'] === 'http' && self::http();
    }

    /**
     * 描述 : 调试环境
     * 作者 : Edgar.lee
     */
    private static function test() {
        if (is_file($temp = __DIR__ . '/demo/tool/swoole/debug.php')) {
            //加载调试代码(Co类名)
            require_once $temp;
            //初始化接管方法对象
            $temp = new self;
            //初始化接管方法对象
            $temp->swRes = new Co;
            //执行调试
            Co::debug($temp, __DIR__, self::$debug);
        }
    }

    /**
     * 描述 : 启动HTTP服务
     * 作者 : Edgar.lee
     */
    private static function http() {
        //创建监听服务
        $serv = new Swoole\Http\Server('0.0.0.0', $GLOBALS['system']['port'], SWOOLE_PROCESS);
        //启动工作并开启协程
        $serv->set(array('enable_coroutine' => true) + $GLOBALS['server']);
        //主进程启动
        $serv->on('start', function ($serv) {
            //共享内存磁盘路径
            $path = "/dev/shm/swoole/{$GLOBALS['system']['port']}";
            //创建异步任务目录
            is_dir($temp = $path . '/task') || mkdir($temp, 0777, true);
            //创建临时任务目录
            is_dir($temp = $path . '/temp') || mkdir($temp, 0777, true);
            //服务加锁, 标记服务已启动
            flock($GLOBALS['lock'] = fopen($path . '/serv.lock', 'c+'), LOCK_EX);
            //启动主动异步任务执行器
            for ($i = 0; $i < 10; ++$i) self::fork('swoole::task', 2);
        });
        //工作启动
        $serv->on('workerStart', function ($serv) {
            //启动哨兵
            self::sentry($serv, 0);
        });
        //工作退出
        $serv->on('workerExit', function () {
            //标记工作开始关闭
            self::$isEnd = true;
            //停止所有定时器, swoole >= 4.4 有此方法
            Swoole\Timer::clearAll();
        });
        //请求回调
        $serv->on('request', function ($requ, $resp) {
            //引用请求信息
            $index = &$requ->server;
            //访问文件不存在
            if (!is_file($file = ROOT_DIR . rtrim($index['path_info'], '/'))) {
                //是目录 && 主文件存在
                if (is_file($file .= '/index.php')) {
                    //访问目录不是以"/"结尾
                    if (substr($index['path_info'], -1) !== '/') {
                        $temp = empty($index['query_string']) ? '' : "?{$index['query_string']}";
                        $resp->redirect("{$index['path_info']}/{$temp}", 301);
                        return ;
                    }
                } else {
                    $resp->status(404);
                    return ;
                }
            //访问静态文件
            } else if (strtolower(substr($file, -4)) !== '.php') {
                $resp->sendfile($file);
                return ;
            }

            //设置准确访问地址
            $index['path_info'] = substr($file, $GLOBALS['rootLen']);
            //标记全局空间
            $_GLOBAL_SCOPE_ = 1;

            //注册超全局变量
            extract(self::loadEnv($requ, $resp), EXTR_REFS);
            //标记try开始
            self::errCtrl(true, __FUNCTION__);
            //初始化并执行逻辑代码
            try {
                //初始化代码
                self::reinit();
                //加载脚本
                include 'of.incl://' . Co::getCid() . '://3://' . $_FUNC_mapVar->incStart = $file;
            //拦截exit
            } catch (Swoole\ExitException $e) {
                //打印字符串exit(str)
                if (is_string($e = $e->getStatus())) echo $e;
            //含exit的所有错误
            } catch (Throwable $e) {
                of::event('of::error', true, $e);
            }
            //标记try结束
            self::errCtrl(false, __FUNCTION__);

            //触发关闭回调
            self::clear(false);
        });
        //开启服务
        $serv->start();
    }
}

swoole::start();