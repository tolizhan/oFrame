<?php
//常规错误
set_error_handler('of_base_error_writeLog::phpLog');
//系统异常
set_exception_handler('of_base_error_writeLog::phpLog');
//致命错误
of::event('of::halt', 'of_base_error_writeLog::phpLog');
//SQL 错误
of::event('of_db::error', 'of_base_error_writeLog::sqlLog');

//隐藏原生错误
ini_set('display_errors', false);
//防止禁用错误
error_reporting(E_ALL);

class of_base_error_writeLog {
    //最后一次错误
    private static $lastError = array('error' => null);
    //错误配置文件
    private static $config = null;

    /**
     * 描述 : 初始化数据
     * 作者 : Edgar.lee
     */
    public static function init() {
        //日志配置
        self::$config = of::config('_of.error', array()) + array(
            //日志有效时间(天),0=不清理
            'gcTime' => 30,
            //sql日志路径
            'sqlLog' => OF_DATA . '/error/sqlLog',
            //php日志路径
            'phpLog' => OF_DATA . '/error/phpLog',
            //js日志路径
            'jsLog'  => OF_DATA . '/error/jsLog'
        );
        self::$config['jsLog'] && of_view::head('head', '<script src="' .OF_URL. '/index.php?a=jsErrScript&c=of_base_error_jsLog"></script>');
    }

    /**
     * 描述 : 获取最后一次错误
     * 作者 : Edgar.lee
     */
    public static function lastError($clean = false) {
        $clean && self::$lastError['error'] = null;
        return self::$lastError['error'];
    }

    /**
     * 描述 : 记录php错误及异常
     * 作者 : Edgar.lee
     */
    public static function phpLog($errno = null, $errstr = null, $errfile = null, $errline = null) {
        //输出日志信息
        static $errorLevel = array(
            0     => 'EXCEPTION',                   //异常
            1     => 'E_ERROR',                     //致命的运行时错误。错误无法恢复。脚本的执行被中断。
            2     => 'E_WARNING',                   //非致命的运行时错误。脚本的执行不会中断。
            4     => 'E_PARSE',                     //编译时语法解析错误。解析错误只应该由解析器生成。
            8     => 'E_NOTICE',                    //运行时提示。可能是错误，也可能在正常运行脚本时发生。
            16    => 'E_CORE_ERROR',                //由 PHP 内部生成的错误。
            32    => 'E_CORE_WARNING',              //由 PHP 内部生成的警告。
            64    => 'E_COMPILE_ERROR',             //由 Zend 脚本引擎内部生成的错误。
            128   => 'E_COMPILE_WARNING',           //由 Zend 脚本引擎内部生成的警告。
            256   => 'E_USER_ERROR',                //由于调用 trigger_error() 函数生成的运行时错误。
            512   => 'E_USER_WARNING',              //由于调用 trigger_error() 函数生成的运行时警告。
            1024  => 'E_USER_NOTICE',               //由于调用 trigger_error() 函数生成的运行时提示。
            2048  => 'E_STRICT',                    //运行时提示。对增强代码的互用性和兼容性有益。
            4096  => 'E_RECOVERABLE_ERROR',         //可捕获的致命错误。
            8192  => 'E_DEPRECATED',                //运行时通知。启用后将会对在未来版本中可能无法正常工作的代码给出警告。
            16384 => 'E_USER_DEPRECATED',           //用户产少的警告信息。
            30719 => 'E_ALL',                       //所有的错误和警告，除了 E_STRICT。
        );

        //致命错误
        if ($errno === null) {
            if (OF_DEBUG) {
                //显示原生错误
                ini_set('display_errors', true);
                //防止禁用错误
                error_reporting(E_ALL);
            }
            //非 trigger_error('')
            if (($backtrace = error_get_last()) && $backtrace['message']) {
                $backtrace['message'] = ini_get('error_prepend_string') . 
                    $backtrace['message'] . 
                    ini_get('error_append_string');
                $backtrace['backtrace'] = array();
                $backtrace = array(
                    'errorType'   => 'error',
                    'environment' => $backtrace
                );
            } else {
                return ;
            }
        //系统异常
        } else if ($errstr === null) {
            $backtrace = array(
                'errorType'     => 'exception',
                'environment'   => array(
                    //异常代码
                    'type'      => $errno->getCode(),
                    //异常消息
                    'message'   => $errno->getMessage(),
                    //异常文件
                    'file'      => $errno->getFile(),
                    //异常行
                    'line'      => $errno->getLine(),
                    //异常追踪
                    'backtrace' => $errno->getTrace()
                )
            );
        //常规错误
        } else if (error_reporting()) {
            $backtrace = array(
                'errorType'     =>'error',
                'environment'   => array(
                    'type'      => $errno,
                    'message'   => $errstr,
                    'file'      => $errfile,
                    'line'      => $errline,
                    //错误回溯
                    'backtrace' => debug_backtrace()
                )
            );
        //"@"错误
        } else {
            //@trigger_error('')
            return !!$errstr;
        }

        //错误类型
        $backtrace['environment']['type'] = $errorLevel[$backtrace['environment']['type']];
        //移除无效字符
        $index = iconv('UTF-8', 'UTF-8//IGNORE', $index = &$backtrace['environment']['message']);
        //格式化日志
        self::formatLog($backtrace);

        $temp = htmlentities($index, ENT_QUOTES, 'UTF-8');
        self::writeLog($backtrace, 'php', "{$backtrace['environment']['type']} : \"{$temp}\" in {$backtrace['environment']['file']} on line {$backtrace['environment']['line']}");
    }

    /**
     * 描述 : 记录sql错误
     * 参数 :
     *     params : sql的错误
     * 作者 : Edgar.lee
     */
    public static function sqlLog($params) {
        $sysBacktrace = debug_backtrace();

        //生成错误列表
        $backtrace = array(
            'errorType'     => 'sqlError',
            'environment'   => array(
                'type'      => $params['pool'] . ':' . $params['type'],
                'message'   => &$params['sql'],
                'file'      => '(',
                'line'      => 0,
                'backtrace' => &$sysBacktrace
            )
        );

        //格式化日志
        self::formatLog($backtrace);

        //输出错误日志
        $temp = htmlentities($backtrace['environment']['message'], ENT_QUOTES, 'UTF-8');
        self::writeLog($backtrace, 'sql', "[{$backtrace['environment']['type']}] : \"{$temp}\" in {$backtrace['environment']['file']} on line {$backtrace['environment']['line']}");
    }

    /**
     * 描述 : 记录日志数据
     * 参数 :
     *     &logData  : 日志数据
     *      logType  : 日志内容[js, php, mysql]
     *      printStr : 显示错误内容,会根据相关配置绝对是否显示
     * 作者 : Edgar.lee
     */
    protected static function writeLog(&$logData, $logType, $printStr) {
        self::$lastError['error'] = &$logData;
        //配置引用
        $config = &self::$config;
        //当前时间戳
        $logData['time'] = time();

        //debug模式
        if (OF_DEBUG && $printStr) {
            //打印日志
            echo '<pre style="color:#F00; font-weight:bold; margin: 0px;">',
                $printStr, 
            ". Timestamp : {$logData['time']}</pre>";
        }

        //写入日志
        if ($index = &$config[$logType . 'Log']) {
            $logPath = ROOT_DIR . $index . date('/Y/m/d', $logData['time']) . $logType;
            is_dir($temp = dirname($logPath)) || mkdir($temp, 0777, true);
            file_put_contents(
                $logPath, 
                strtr(serialize($logData), array("\r\n" => ' ' . ($temp = chr(0)), "\r" => $temp, "\n" => $temp)) . "\n", 
                FILE_APPEND | LOCK_EX
            );
        }

        //错误回调
        of::event('of_base_error_writeLog::error', true, array(
            'type' => &$logType, 'data' => &$logData
        ));

        //日志有时限 && 1%的机会清理
        if (($index = &$config['gcTime']) > 0 && rand(0, 99) === 1) {
            //日志生命期
            $gcTime = $logData['time'] - $index * 86400;

            //执行清洗
            foreach (array('sqlLog', 'phpLog', 'jsLog') as $temp) {
                if (!empty($config[$temp])) {
                    $temp = ROOT_DIR . $config[$temp];
                    //文件遍历成功
                    if (of_base_com_disk::each($temp, $data, false)) {
                        foreach ($data as $k => &$v) {
                            //是文件 && 文件已过期
                            if ($v === false && filectime($k) <= $gcTime) {
                                //删除文件及父空文件夹
                                of_base_com_disk::delete($k, true);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 描述 : 格式化日志
     * 参数 :
     *     &logData : 日志数据
     * 结构 : {
     *      'errorType'   : 错误类型(sqlError, exception, error)
     *      'environment' : 错误体,包括环境,错误细节,回溯 {
     *          'type'    : php=错误级别, sql=错误码及说明
     *          'message' : php=错误描述, sql=错误sql
     *          'file'    : 定位->路径
     *          'line'    : 定位->行数
     *          'envVar'  : 环境变量 {
     *              '_GET'     : 对应超全局变量
     *              '_POST'    : 对应超全局变量
     *              '_COOKIE'  : 对应超全局变量
     *              '_SESSION' : 对应超全局变量
     *              '_SERVER'  : 对应超全局变量
     *              '_REQUEST' : 对应超全局变量
     *              'iStream'  : 原始请求输入流
     *          }
     *          'backtrace' : 回溯信息,js没有 {}
     *      }
     *      'time'        : 生成时间戳
     *  }
     * 作者 : Edgar.lee
     */
    private static function formatLog(&$logData) {
        //引用回溯
        $backtrace = &$logData['environment']['backtrace'];
        $logData['errorType'] === 'exception' || array_splice($backtrace, 0, 1);

        //debug运行追踪
        if (strpos($logData['environment']['file'], '(') !== false) {
            foreach ($backtrace as $k => &$v) {
                //大部分正常方式
                if (isset($v['file'])) {
                    $temp = array(strtr($v['file'], '\\', '/'));

                    //在 eval 中, 尝试精确的地址
                    if (strpos($v['file'], '(')) {
                        if (
                            //通过类名变相定位 && sql类型下有效类名
                            isset($v['class']) && (
                                $logData['errorType'] !== 'sqlError' || 
                                strncmp($v['class'], 'of_', 3) && $v['class'] !== 'L'
                            )
                        ) {
                            $temp = array(ROOT_DIR . '/' . strtr($v['class'], '_', '/') . '.php');
                            $k > 0 && $temp[0] .= "({$backtrace[$k-1]['line']})";
                        } else {
                            //不存在类名 || sql类型无效类名
                            continue ;
                        }
                    }
                //回调中的类是通过 eval 生成的
                } else if (isset($v['class'])) {
                    $temp = array(ROOT_DIR . '/' . strtr($v['class'], '_', '/') . '.php');
                //无法定位文件
                } else {
                    continue ;
                }

                if (
                    $logData['errorType'] !== 'sqlError' || 
                    strncmp(OF_DIR, $temp[0], strlen(OF_DIR))
                ) {
                    //在eval中
                    if (($temp[1] = strpos($temp[0], '(')) !== false) {
                        $logData['environment']['file'] = substr($temp[0], 0, $temp[1]);
                        $logData['environment']['line'] = substr($temp[0], $temp[1] + 1, strpos($temp[0], ')') - $temp[1] - 1);
                    //正常执行的错误
                    } else {
                        $logData['environment']['file'] = $temp[0];
                        //通过 eval 编译的类 无 line
                        if ($logData['errorType'] === 'sqlError' && isset($v['line'])) {
                            $logData['environment']['line'] = $v['line'];
                        }
                    }
                    array_splice($backtrace, 0, $k);
                    break;
                }
            }
        }

        //定位->路径 转化 相对路径
        $logData['environment']['file'] = strtr(
            substr($logData['environment']['file'], strlen(ROOT_DIR)), '\\', '/'
        );

        //添加预定义数据
        $logData['environment']['envVar'] = array(
            '_GET'     => &$_GET,
            '_POST'    => &$_POST,
            '_COOKIE'  => &$_COOKIE,
            '_SESSION' => &$_SESSION,
            '_SERVER'  => &$_SERVER,
            '_REQUEST' => &$_REQUEST,
            'iStream'  => file_get_contents('php://input'),
        );

        //格式化回溯
        if (isset($logData['environment']['backtrace'])) {
            foreach ($logData['environment']['backtrace'] as &$v) {
                if (isset($v['object'])) {
                    unset($v['object']);
                }
                if (isset($v['args'])) {
                    //临时参数拷贝数组
                    $temp = array();
                    foreach ($v['args'] as &$arg) {
                        //是一个标量,资源,null
                        if (is_scalar($arg) || is_resource($arg) || $arg === null) {
                            $temp[] = gettype($arg) . ' (' . var_export($arg, true) . ')';
                        //对象
                        } else if (is_object($arg)) {
                            $temp[] = 'object (' . get_class($arg) . ')';
                        //数组
                        } else if (is_array($arg)) {
                            $temp[] = var_export($arg, true);
                        }
                    }
                    $v['args'] = $temp;
                }
            }
        }
    }
}
of_base_error_writeLog::init();

//trigger_error("A custom error has been triggered");       //错误
//throw new Exception("Value must be 1 or below");          //异常