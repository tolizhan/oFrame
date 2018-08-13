<?php
/**
 * 描述 : mysql数据结构更新
 * 注明 : 无法处理'表'与'字段'的更名,会按删除旧名,添加新名处理
 * 权限 : 根据config['matches'][xx]=false会排除对应权限, SELECT:查, INSERT:增, DELETE:删, CREATE:创建, DROP:删除表视图, LOCK TABLES:锁表[, ALTER:修改表视图, TRIGGER:触发器][, CREATE VIEW:创建视图, SHOW VIEW:显示视图][, ALTER ROUTINE:修改存储程序, CREATE ROUTINE:创建存储程序]
 * 支持 : 兼容 mysql 5.0+
 * 方法 : 对外开放的方法
 *      init         : 初始化
 *      sql          : 执行sql语句
 *      backupData   : 备份表数据到指定文件
 *      backupBase   : 备份表结构到指定文件
 *      backupTable  : 备份表语句到指定文件
 *      revertData   : 更新表数据(解析并运行指定文件中的sql语句)
 *      revertBase   : 更新表结构
 *      revertTable  : 恢复表语句(会删除原表及数据)
 *      fetchFileSql : 从文件中提取一条sql语句
 * 作者 : Edgar.lee
 */
class of_base_tool_mysqlSync {
    private static $config = null;

    /**
     * 描述 : 初始化
     * 参数 :
     *      config : 配置参数 {
     *          'adjustSqlParam' : 作为callAdjustSql的第三个参数,默认null
     *          'callAdjustSql'  : 每从文件中提取一条sql,便调用此函数,默认null
     *          'callDb'         : 执行sql语句,执行成功返回二维数据(ASSOC方式查询:SELECT, SHOW)或true,失败返回false,is_callable格式 或 连接配置文件 {
     *              'server'   : mysql_connect 的 server 参数
     *              'username' : mysql_connect 的 username 参数
     *              'password' : mysql_connect 的 password 参数
     *              'database' : 默认数据库,of_base_tool_mysqlSync:init时选填,与config['database']互补
     *              'charset'  : 默认字符集,of_base_tool_mysqlSync:init时选填,与config['charset']互补
     *          }
     *          'callMsg'        : 当发生一些消息时会调用这个函数,默认null
     *          'charset'        : 指定字符集和对照,默认['utf8', 'utf8_general_ci']
     *          'database'       : 指定数据库,默认尝试从数据库获取
     *          'dbVersion'      : 数据库版本'大版本号两位中版本号两位小版本号',会从数据库获取
     *          'prefix'         : 更新程序使用表的前缀,默认'__'
     *          'sqlSplit'       : sql语句分隔符,默认';'
     *          'sqlMark'        : sql标记,将关键位置加上特殊注释,方便callAdjustSql调用时定位, true=加标记,默认false {
     *              "/*`N:T'*\/" : 标志前面是表名,T 可以是['T'(TABLE), 'V'(VIEW), 'P'(PROCEDURE), 'F'(FUNCTION)]之一
     *          }
     *          'checkRole'      : 检查角色所有权限, 数字(默认)=检查, false=不检查
     *          'matches'        : 备份不同匹配数据类型的更新项,默认全匹配 {
     *              'table'      : 表   null(默认)=全匹配,false=禁止备份,数组=按规则过滤{'include' : 一个包含的正则数组, 'exclude' : 一个排除的正则数组(优先级别高)}
     *              'view'       : 视图 同上,默认=false
     *              'procedure'  : 过程 同上,默认=false
     *              'function'   : 函数 同上,默认=false
     *          }
     *      }
     * 返回 :
     *      初始化成功返回true,否则false
     * 作者 : Edgar.lee
     */
    public static function init($config) {
        self::$config = &$config;

        //初始化默认值
        $config += array(
            //callAdjustSql第三参数
            'adjustSqlParam' => null,
            //提取sql回调
            'callAdjustSql'  => null,
            //调用消息
            'callMsg'        => null,
            //字符集
            'charset'        => array('utf8', 'utf8_general_ci'),
            //前缀
            'prefix'         => '__',
            //默认分隔符
            'sqlSplit'       => ';',
            //标记sql语句
            'sqlMark'        => false,
            //检查权限
            'checkRole'      => true,
            //匹配
            'matches'        => array()
        );

        //匹配项默认值
        $config['matches'] = (array)$config['matches'] + array(
            'table'     => null,
            'view'      => false,
            'procedure' => false,
            'function'  => false,
        );
        if ($config['matches']['table'] === null || is_array($config['matches']['table'])) {
            //排除内部使用的表
            $config['matches']['table']['exclude'][] = "@^{$config['prefix']}@";
        }

        //初始化标记
        $config['sqlMark'] = $config['sqlMark'] ? array(
            'table'     => '/*`N:T\'*/',
            'view'      => '/*`N:V\'*/',
            'procedure' => '/*`N:P\'*/',
            'function'  => '/*`N:F\'*/'
        ) : array(
            'table'     => '',
            'view'      => '',
            'procedure' => '',
            'function'  => ''
        );

        //转换标准模式
        is_string($config['callDb']) && $config['callDb'] = array('asCall' => $config['callDb']);
        //是否使用内置连接
        $temp = isset($config['callDb']['server']);
        //sql检查
        if (
            !isset($config['callDb']) ||
            //内置sql
            ($temp && !self::sql($config['callDb'])) ||
            //外部sql
            (!$temp && !is_callable($config['callDb']['asCall']))
        //检查配置可用性
        ) {
            self::message('error', 'config[callDb]不可用', __FUNCTION__);
            return false;
        //提取sql回调
        } else if (isset($config['callAdjustSql']) && !is_callable($config['callAdjustSql'])) {
            self::message('error', 'config[callAdjustSql]不可用', __FUNCTION__);
            return false;
        //默认数据库检查
        } else if (!isset($config['database'])) {
            //查询当前数据库
            $temp = self::sql('SELECT DATABASE() `database`');
            if (isset($temp[0]['database'])) {
                $config['database'] = $temp[0]['database'];
            } else {
                self::message('error', 'config[database]不可用', __FUNCTION__);
                return false;
            }
        }

        ini_set('max_execution_time', 0);
        ignore_user_abort(true);
        //加斜线的库名
        $config['databaseSlashes'] = addslashes($config['database']);
        //替换反引号的库名
        $config['databaseBacktick'] = strtr($config['database'], array('`' => '``'));

        //不直接使用下面语句创建是因为可能没用创建库权限
        $temp = self::sql("SELECT        /*SHOW DATABASES*/
            `SCHEMA_NAME`
        FROM
            information_schema.`SCHEMATA`
        WHERE
            `SCHEMA_NAME` = '{$config['databaseSlashes']}'");
        //数据库不存在
        if (!isset($temp[0]['SCHEMA_NAME'])) {
            $sql = "CREATE DATABASE IF NOT EXISTS 
                `{$config['databaseBacktick']}` 
            CHARACTER SET 
                {$config['charset'][0]} 
            COLLATE 
                {$config['charset'][1]}";
            //创建数据库
            self::sql($sql);
        }

        //切换数据库
        if (self::sql("USE `{$config['databaseBacktick']}`") === false) {
            self::message('error', 'config[database]不可用', __FUNCTION__, $config['database']);
            //指定数据库不可用
            return false;
        }

        //获取版本
        $temp = self::sql('SELECT VERSION() v');
        $temp = explode('.', $temp[0]['v'], 3) + array(0, 0, 0);
        $temp[1] = str_pad((int)$temp[1], 2, '0', STR_PAD_LEFT);
        $temp[2] = str_pad((int)$temp[2], 2, '0', STR_PAD_LEFT);
        $config['dbVersion'] = join($temp);

        //权限判断, 需要的权限
        $needGrants = array(
            //查
            "{$config['database']}:SELECT"      => 0,
            //增
            "{$config['database']}:INSERT"      => 0,
            //删
            "{$config['database']}:DELETE"      => 0,
            //创建表
            "{$config['database']}:CREATE"      => 0,
            //删除表视图
            "{$config['database']}:DROP"        => 0,
            //锁表
            "{$config['database']}:LOCK TABLES" => 0,
        );
        //比对表
        if ($config['matches']['table'] !== false) {
            //修改表视图
            $needGrants["{$config['database']}:ALTER"] = 0;
            if ($config['dbVersion'] > 50105) {
                //触发器 5.1.6
                $needGrants["{$config['database']}:TRIGGER"] = 0;
            }
        }
        //比对视图
        if ($config['matches']['view'] !== false) {
            //创建视图
            $needGrants["{$config['database']}:CREATE VIEW"] = 0;
            //备份视图
            $needGrants["{$config['database']}:SHOW VIEW"] = 0;
        }
        //比对存储程序
        if ($config['matches']['procedure'] !== false || $config['matches']['function'] !== false) {
            //创建存储程序
            $needGrants["{$config['database']}:ALTER ROUTINE"] = 0;
            //备份存储程序
            $needGrants["{$config['database']}:CREATE ROUTINE"] = 0;
            //备份视图
            $needGrants['mysql:SELECT'] = 0;
        }

        //拥有的权限
        $userGrants = array();
        $temp = self::sql("SELECT
            SCHEMA_PRIVILEGES,    /*局部权限*/
            GLOBA_PRIVILEGES      /*全局权限*/
        FROM
            ((SELECT
                NULL SCHEMA_PRIVILEGES,
                `USER_PRIVILEGES`.PRIVILEGE_TYPE GLOBA_PRIVILEGES,
                `USER_PRIVILEGES`.GRANTEE
            FROM
                information_schema.`USER_PRIVILEGES`
            ) UNION ALL (
            SELECT
                CONCAT('{$config['databaseSlashes']}', ':', `SCHEMA_PRIVILEGES`.PRIVILEGE_TYPE),
                NULL,
                `SCHEMA_PRIVILEGES`.GRANTEE
            FROM 
                information_schema.`SCHEMA_PRIVILEGES`
            WHERE
                '{$config['databaseSlashes']}' LIKE `SCHEMA_PRIVILEGES`.TABLE_SCHEMA
            OR  `SCHEMA_PRIVILEGES`.TABLE_SCHEMA = 'mysql'
            )) `data`
        WHERE
            `data`.GRANTEE = CONCAT(    /*用户名*/
                '''',
                LEFT(
                    CURRENT_USER,
                    LENGTH(CURRENT_USER) - LENGTH(SUBSTRING_INDEX(CURRENT_USER, '@', -1)) - 1
                ),
                '''@''',
                SUBSTRING_INDEX(CURRENT_USER, '@', -1),
                ''''
            )");

        foreach ($temp as &$v) {
            if ($v['SCHEMA_PRIVILEGES'] === null) {
                $userGrants["{$config['database']}:{$v['GLOBA_PRIVILEGES']}"] = 0;
                $userGrants["mysql:{$v['GLOBA_PRIVILEGES']}"] = 0;
            } else {
                $userGrants[$v['SCHEMA_PRIVILEGES']] = 0;
            }
        }

        //缺少权限
        if (
            $config['checkRole'] &&
            !isset($userGrants['ALL PRIVILEGES']) &&
            count($temp = array_diff_key($needGrants, $userGrants))
        ) {
            self::message('error', '数据库缺少以下权限(数据库名:权限)', __FUNCTION__, join(', ', array_keys($temp)));
            return false;
        //初始配置
        } else {
            //设置 GROUP_CONCAT 最大值
            self::sql('SET SESSION group_concat_max_len = 4294967295');
        }

        return true;
    }

    /**
     * 描述 : 调整sql语句
     * 参数 :
     *     &sql  : sql语句类型
     *      type : 语句类型
     * 作者 : Edgar.lee
     */
    private static function adjustSql(&$sql, $type) {
        if (isset(self::$config['callAdjustSql'])) {
            call_user_func_array(self::$config['callAdjustSql'], array(&$sql, &$type, &self::$config['adjustSqlParam']));
        }
    }

    /**
     * 描述 : 判断结构名是否匹配
     * 参数 :
     *      name : 结构名称
     *      type : 结构类型,如函数,视图,表,config[matches]中的一个键值
     * 返回 :
     *      匹配成功返回true,否则false
     * 作者 : Edgar.lee
     */
    private static function isMatch($name, $type) {
        //两种类型返回的boolean值
        static $matchBool = array('exclude' => false, 'include' => true);
        $matches = &self::$config['matches'];
        //默认返回boolean值
        $defaultBool = true;

        //不支持当前类型,返回false
        if ($matches[$type] === false) {
            return false;
        }
        foreach ($matchBool as $matchType => &$boolV) {
            //排除或包含匹配项存在
            if (isset($matches[$type][$matchType]) && is_array($matches[$type][$matchType])) {
                //默认返回值
                $defaultBool = !$boolV;
                foreach ($matches[$type][$matchType] as &$v) {
                    //匹配成功
                    if (preg_match($v, $name)) {
                        //返回对应布尔值
                        return $boolV;
                    }
                }
            }
        }

        return $defaultBool;
    }

    /**
     * 描述 : 获取指定类型的匹配列表
     * 参数 :
     *      type : 指定匹配类型,config[matches]中的一个键值
     * 返回 :
     *      返回一个包含有效值的数据
     * 作者 : Edgar.lee
     */
    private static function &getMatches($type) {
        $database = &self::$config['databaseSlashes'];
        //返回数据
        $data = array();

        switch ($type) {
            //表
            case 'table':
            //视图
            case 'view':
                $temp = $type === 'view' ? 'VIEW' : 'BASE TABLE';
                $sql = "SELECT    /*SHOW FULL TABLES WHERE Table_type = ['BASE TABLE' | 'VIEW']*/
                    TABLE_NAME `name`    /*表名*/
                FROM
                    information_schema.`TABLES`
                WHERE 
                    TABLE_SCHEMA = '{$database}'    /*数据库名*/
                AND TABLE_TYPE = '{$temp}'    /*表类型*/";
                break;
            //函数
            case 'procedure':
            //过程
            case 'function':
                $temp = strtoupper($type);
                $sql = "SELECT    /*SHOW PROCEDURE STATUS, SHOW FUNCTION STATUS*/
                    ROUTINE_NAME `name`    /*函数名*/
                FROM
                    information_schema.`ROUTINES`
                WHERE 
                    ROUTINE_SCHEMA = '{$database}'    /*数据库名*/
                AND ROUTINE_TYPE = '{$temp}'    /*表类型*/";
                break;
        }

        $temp = self::sql($sql);
        foreach ($temp as &$v) {
            self::isMatch($v['name'], $type) && $data[] = $v['name'];
        }

        return $data;
    }

    /**
     * 描述 : 创建文件流
     * 参数 :
     *      file : null=读取记忆文件流,字符串=打开指定路径文件流,false=关闭文件流
     *      mode : 仅可以('r'读),('w'写)之一
     * 返回 :
     *      成功返回文件流,失败返回null
     * 作者 : Edgar.lee
     */
    private static function &openFile($file, $mode = null) {
        //记忆文件流
        static $fp = false;

        if ($file !== null) {
            //已打开文件流
            if ($fp !== false) {
                //关闭文件流
                fclose($fp);
                //重置记忆
                $fp = false;
            }

            if (is_string($file)) {
                //读方式
                if ($mode === 'r') {
                    //文件存在
                    if (is_file($file)) {
                        //打开只读流
                        $fp = fopen($file, 'r');
                        //加共享锁
                        flock($fp, LOCK_SH);
                    //文件不存在
                    } else {
                        self::message('error', '指定文件不存在', __FUNCTION__, $file);
                    }
                //写方式
                } else {
                    is_dir($temp = dirname($file)) || mkdir($temp, 0777, true);
                    //打开只写流
                    $fp = @fopen($file, is_file($file) ? 'r+' : 'x+');
                    if ($fp) {
                        //加独享锁
                        flock($fp, LOCK_EX);
                        //清空
                        ftruncate($fp, 0);
                    } else {
                        self::message('tip', '无权操作文件', __FUNCTION__, $file);
                    }
                }
            }
        }

        return $fp;
    }

    /**
     * 描述 : 执行sql语句
     * 参数 :
     *      sql : sql语句
     * 返回 :
     *      执行成功返回二维数据(ASSOC方式查询:SELECT, SHOW, EXPLAIN, DESCRIBE)或true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function sql($sql) {
        //连接方法
        static $func = null;
        //连接源
        static $db = false;
        //返回数组的sql类型
        static $gArr = array(
            'SELECT' => true,
            'SHOW' => true,
            'EXPLAIN' => true,
            'DESCRIBE' => true
        );

        if ($func === null) {
            //选择连接方式
            $func = function_exists('mysqli_connect') ? array(
                'close' => 'mysqli_close',
                'connect' => 'mysqli_connect',
                'set_charset' => 'mysqli_set_charset',
                'query' => 'mysqli_query',
                'error' => 'mysqli_error',
                'insert_id' => 'mysqli_insert_id',
                'affected_rows' => 'mysqli_affected_rows',
                'fetch_assoc' => 'mysqli_fetch_assoc'
            ) : array(
                'close' => 'mysql_close',
                'connect' => 'mysql_connect',
                'set_charset' => 'mysql_set_charset',
                'query' => 'mysql_query',
                'error' => 'mysql_error',
                'insert_id' => 'mysql_insert_id',
                'affected_rows' => 'mysql_affected_rows',
                'fetch_assoc' => 'mysql_fetch_assoc'
            );
        }

        $arg = $func['connect'] === 'mysql_connect' ?
            array(&$arg0, &$db) : array(&$db, &$arg0);

        if (is_array($sql)) {
            //关闭连接
            $db === false || $func['close']($db);
            //使用内部连接
            self::$config['callDb'] = null;

            //mysql 连接方式
            if ($func['connect'] === 'mysql_connect') {
                $db = @$func['connect']($sql['server'], $sql['username'], $sql['password']);
            //mysqli 连接方式
            } else {
                $temp = explode(':', $sql['server']);
                $db = mysqli_connect(
                    $temp[0],
                    $sql['username'],
                    $sql['password'],
                    '',
                    $temp[1]
                );
            }

            //连接数据库
            if ($return = $db) {
                //连接字符集
                $temp = isset($sql['charset']) ?
                    $sql['charset'] : 
                    isset(self::$config['charset']) ? self::$config['charset'][0] : 'utf8';

                //兼容php < 5.2.6
                if (function_exists($func['set_charset'])) {
                    $arg0 = $temp;
                    $func['set_charset']($arg[0], $arg[1]);
                } else {
                    $arg0 = "SET NAMES '{$temp}'";
                    $func['query']($arg[0], $arg[1]);
                }

                //选择数据库
                $temp = isset($sql['database']) ?
                    $sql['database'] :
                    isset(self::$config['database']) ? self::$config['database'] : false;

                //使用数据库
                $arg0 = 'USE `' .strtr($temp, array('`' => '``')). '`';
                $temp && $func['query']($arg[0], $arg[1]);
            } else {
                self::message('error', 'SQL执行出错', __FUNCTION__, $func['error']());
            }
        } else {
            if (self::$config['callDb'] === null) {
                //过滤掉无用的字符串,已正确提取'SELECT', 'INSERT', 'UPDATE', 'DELETE'关键字
                preg_match('/^[\(\s]*(\w+)\s/i', $sql, $sqlTyep);
                $sqlTyep = strtoupper($sqlTyep[1]);
                $arg0 = $sql;
                $re = $func['query']($arg[0], $arg[1]);

                //插入 更新 删除
                if ($re === true) {
                    //插入数据
                    if ($sqlTyep === 'INSERT' || $sqlTyep === 'REPLACE') {
                        $return = $func['insert_id']($db);
                    //更新删除
                    } else {
                        $return = $func['affected_rows']($db);
                    }
                //执行失败
                } else if ($re === false) {
                    $return = false;
                //查询数据
                } else if (isset($gArr[$sqlTyep])) {
                    $data = array();
                    while ($temp = $func['fetch_assoc']($re)) {
                        $data[] = $temp;
                    }
                    $return = $data;
                }
            } else {
                $index = &self::$config['callDb'];
                $index['params']['_'] = &$sql;
                $return = call_user_func_array($index['asCall'], $index['params']);
            }

            //获取错误日志
            if ($return === false && $sql !== 'SHOW ERRORS') {
                $error = self::sql('SHOW ERRORS');
                $error = (isset($error[0]['Message']) ? $error[0]['Message'] . ' : ' : '') . $sql;
                self::message('error', 'SQL执行出错', __FUNCTION__, $error);
            }
        }

        return $return;
    }

    /**
     * 描述 : 回调消息
     * 参数 :
     *      state   : 消息状态,success=成功,error=错误,tip=提示
     *      message : 消息内容
     *      type    : 发出信息的方法
     *      info    : 详细信息
     * 作者 : Edgar.lee
     */
    private static function message($state, $message, $type, $info = null) {
        $msg = array(
            'state'   => &$state,
            'message' => is_callable(array('L', 'getText')) ? 
                L::getText($message, array('key'=>'of_base_tool_mysqlSync::message')) : $message,
            'info'    => &$info,
            'type'    => &$type
        );
        if (is_callable(self::$config['callMsg'])) {
            call_user_func(self::$config['callMsg'], $msg);
        } else if ($state === 'error') {
            trigger_error("of_base_tool_mysqlSync error : " . print_r($msg, true));
        }
    }

    /************************************************************** 更新数据库
     * 描述 : 解析并运行指定文件中的sql语句(更新备份数据)
     * 参数 :
     *      file : 指定解析的文件全路径
     *      config : 导入配置 {
     *          'disableTriggers' : 禁用触发器,默认false不禁用
     *          'showProgress' : 显示进度,默认true显示
     *      }
     * 返回 :
     *      文件不存在或任何一条提取的sql执行错误返回false,否则true
     * 作者 : Edgar.lee
     */
    public static function revertData($file, $config = array()) {
        //数据库
        $database = self::$config['databaseSlashes'];
        $returnBool = null;
        $config += array(
            'disableTriggers' => false,
            'showProgress'    => true
        );

        //删除触发器
        if ($config['disableTriggers']) {
            self::message('tip', '正在关闭触发器', __FUNCTION__);
            $triggers = self::getDatabaseStructure('TRIGGERS');

            foreach ($triggers as &$v) {
                //替换反引号
                $v['TRIGGER_NAME'] = strtr($v['TRIGGER_NAME'], array('`' => '``'));
                //替换反引号
                $v['EVENT_OBJECT_TABLE'] = strtr($v['EVENT_OBJECT_TABLE'], array('`' => '``'));
                self::sql("DROP TRIGGER `{$v['TRIGGER_NAME']}`");
            }
        }

        //批量运行sql
        self::message('tip', '正在导入数据', __FUNCTION__);
        //文件存在
        if (self::fetchFileSql($file) === true) {
            if ($config['showProgress']) {
                //[当前sql长度, 进度]
                $nowSqlProgress = array(0,0);
                $config['showProgress'] = filesize($file);
            }

            while (($sql = self::fetchFileSql()) !== null) {
                if ($sql !== '') {
                    //显示进度
                    if ($config['showProgress']) {
                        $nowSqlProgress[0] += strlen($sql);
                        $temp = ceil($nowSqlProgress[0] * 100 / $config['showProgress']);
                        if ($nowSqlProgress[1] + 10 < $temp) {
                            $nowSqlProgress[1] = $temp;
                            self::message('tip', '已导入百分比', 'revertDataProgress', $temp);
                        }
                    }
                    //调整sql:更新
                    self::adjustSql($sql, 'recover');
                    //执行出错
                    if (self::sql($sql) === false) {
                        $returnBool = false;
                    }
                }
            }
            $returnBool === null && $returnBool = true;
        } else {
            $returnBool = false;
        }

        //创建触发器
        if ($config['disableTriggers']) {
            self::message('tip', '正在开启触发器', __FUNCTION__);
            foreach ($triggers as &$v) {
                self::sql("CREATE TRIGGER `{$v['TRIGGER_NAME']}`
                {$v['ACTION_TIMING']} {$v['EVENT_MANIPULATION']} ON `{$v['EVENT_OBJECT_TABLE']}`
                FOR EACH ROW {$v['ACTION_STATEMENT']}");
            }
        }

        self::message(
            $returnBool ? 'success' : 'error', 
            '数据导入' . ($returnBool ? '完成' : '失败'), 
            $config['showProgress'] ? 'revertDataProgress' : __FUNCTION__, 
            $config['showProgress'] ? 100 : null
        );
        return $returnBool;
    }

    /**
     * 描述 : 更新备份结构
     * 参数 :
     *      file   : 指定解析的文件全路径
     *      config : 恢复的配置文件 {
     *          "setInc" : false(默认)=不操作增量, true=设置增量(AUTO_INCREMENT)
     *      }
     * 返回 :
     *      文件不存在或任何一条提取的sql执行错误返回false,否则true
     * 作者 : Edgar.lee
     */
    public static function revertBase($file, $config = array()) {
        //返回布尔
        $returnBool = true;
        //内部前缀
        $prefix = self::$config['prefix'];
        //数据库版本
        $dbVersion = self::$config['dbVersion'];
        $dropPrefix = "DROP TABLE IF EXISTS `{$prefix}FOREIGNKEY`, `{$prefix}TRIGGERS`, `{$prefix}TABLES`, `{$prefix}COLUMNS`, `{$prefix}STATISTICS`, `{$prefix}PARTITIONS`, `{$prefix}VIEWS`, `{$prefix}ROUTINES`";
        //默认字符集
        $charset = 'DEFAULT CHARACTER SET=' .self::$config['charset'][0]. ' COLLATE=' .self::$config['charset'][1];

        //支持MyISAM引擎
        if (self::isSupportEngines('MyISAM')) {
            self::message('tip', '开始更新结构', __FUNCTION__);

            //删除需求表
            self::sql($dropPrefix);

            self::sql("CREATE TABLE `{$prefix}FOREIGNKEY` (
                `CONSTRAINT_NAME`         varchar(255) NULL DEFAULT NULL COMMENT '外键名' ,
                `TABLE_NAME`              varchar(255) NULL DEFAULT NULL COMMENT '本表名' ,
                `REFERENCED_TABLE_NAME`   varchar(255) NULL DEFAULT NULL COMMENT '外表名' ,
                `COLUMNS_NAME`            longtext     NULL              COMMENT '本表列集' ,
                `REFERENCED_COLUMNS_NAME` longtext     NULL              COMMENT '外表列集' ,
                `UPDATE_RULE`             varchar(255) NULL DEFAULT NULL COMMENT '更新规则' ,
                `DELETE_RULE`             varchar(255) NULL DEFAULT NULL COMMENT '删除规则' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='外键表'");                                                              //创建外键表

            self::sql("CREATE TABLE `{$prefix}TRIGGERS` (
                `TRIGGER_NAME`       varchar(255) NULL DEFAULT NULL COMMENT '触发器名' ,
                `EVENT_MANIPULATION` varchar(255) NULL DEFAULT NULL COMMENT '激活事件' ,
                `EVENT_OBJECT_TABLE` varchar(255) NULL DEFAULT NULL COMMENT '激活表' ,
                `ACTION_STATEMENT`   longtext     NULL              COMMENT '触发语句' ,
                `ACTION_TIMING`      varchar(255) NULL DEFAULT NULL COMMENT '触发位置' ,
                INDEX USING BTREE (`EVENT_OBJECT_TABLE`) 
            ) ENGINE=MyISAM {$charset} COMMENT='触发器表'");                                                            //创建触发器表

            self::sql("CREATE TABLE `{$prefix}TABLES` (
                `TABLE_NAME`      varchar(255) NULL DEFAULT NULL COMMENT '表名' ,
                `ENGINE`          varchar(255) NULL DEFAULT NULL COMMENT '存储引擎' ,
                `ROW_FORMAT`      varchar(255) NULL DEFAULT NULL COMMENT '行格式' ,
                `TABLE_COLLATION` varchar(255) NULL DEFAULT NULL COMMENT '排序字符集' ,
                `AUTO_INCREMENT`  BIGINT(21)   UNSIGNED DEFAULT NULL COMMENT '自增数值' ,
                `CREATE_OPTIONS`  varchar(255) NULL DEFAULT NULL COMMENT '附带参数' ,
                `TABLE_COMMENT`   varchar(255) NULL DEFAULT NULL COMMENT '注释' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='表信息'");                                                              //创建表信息表

            self::sql("CREATE TABLE `{$prefix}COLUMNS` (
                `TABLE_NAME`         varchar(255) NULL DEFAULT NULL COMMENT '表名' ,
                `COLUMN_NAME`        varchar(255) NULL DEFAULT NULL COMMENT '字段名' ,
                `ORDINAL_POSITION`   int(10)      NULL DEFAULT NULL COMMENT '字段位置' ,
                `COLUMN_DEFAULT`     varchar(255) NULL DEFAULT NULL COMMENT '默认值,timestamp可以CURRENT_TIMESTAMP' ,
                `IS_NULLABLE`        varchar(255) NULL DEFAULT NULL COMMENT '允许为空,YES=允许,NO=不运行' ,
                `CHARACTER_SET_NAME` varchar(255) NULL DEFAULT NULL COMMENT '字符集' ,
                `COLLATION_NAME`     varchar(255) NULL DEFAULT NULL COMMENT '排序规则' ,
                `COLUMN_TYPE`        varchar(255) NULL DEFAULT NULL COMMENT '字段信息' ,
                `EXTRA`              varchar(255) NULL DEFAULT NULL COMMENT '附加信息' ,
                `COLUMN_COMMENT`     varchar(255) NULL DEFAULT NULL COMMENT '注释' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='字段表'");                                                              //创建表字段表

            self::sql("CREATE TABLE `{$prefix}STATISTICS` (
                `TABLE_NAME`   varchar(255) NULL DEFAULT NULL COMMENT '表名' ,
                `NON_UNIQUE`   varchar(255) NULL DEFAULT NULL COMMENT '是否唯一(0为UNIQUE INDEX 或 PRIMARY KEY,1为INDEX 或 FULLTEXT INDEX)' ,
                `INDEX_NAME`   varchar(255) NULL DEFAULT NULL COMMENT '索引名(PRIMARY=主键)' ,
                `COLUMNS_NAME` longtext     NULL              COMMENT '字段集名' ,
                `INDEX_TYPE`   varchar(255) NULL DEFAULT NULL COMMENT '索引类型' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='索引表'");                                                              //创建表索引表

            self::sql("CREATE TABLE `{$prefix}PARTITIONS` (
                `TABLE_NAME`      varchar(255) NULL DEFAULT NULL COMMENT '表名' ,
                `PARTITION_SQL`   longtext     NULL              COMMENT '分区语句' ,
                `COLUMNS_COMPARE` varchar(255) NULL DEFAULT NULL COMMENT '比对字段' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='分区表'");                                                              //创建表分区表

            self::sql("CREATE TABLE `{$prefix}VIEWS` (
                `TABLE_NAME`      varchar(255) NULL DEFAULT NULL COMMENT '视图名' ,
                `VIEW_DEFINITION` longtext     NULL              COMMENT '查询语句' ,
                `CHECK_OPTION`    varchar(255) NULL DEFAULT NULL COMMENT '检查选项' ,
                `IS_UPDATABLE`    varchar(255) NULL DEFAULT NULL COMMENT '是否更新,YES为ALGORITHM=MERGE,NO为ALGORITHM=TEMPTABLE' ,
                `SECURITY_TYPE`   varchar(255) NULL DEFAULT NULL COMMENT '安全性' ,
                INDEX USING BTREE (`TABLE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='视图表'");                                                              //创建表视图表

            self::sql("CREATE TABLE `{$prefix}ROUTINES` (
                `ROUTINE_NAME`       varchar(255) NULL DEFAULT NULL COMMENT '存储程序名' ,
                `ROUTINE_TYPE`       varchar(255) NULL DEFAULT NULL COMMENT '类型,FUNCTION=函数,PROCEDURE=过程' ,
                `DTD_IDENTIFIER`     varchar(255) NULL DEFAULT NULL COMMENT '返回类型' ,
                `ROUTINE_DEFINITION` longtext     NULL              COMMENT '结构体' ,
                `IS_DETERMINISTIC`   varchar(255) NULL DEFAULT NULL COMMENT '是否附加DETERMINISTIC,YES=是,NO=非' ,
                `SQL_DATA_ACCESS`    varchar(255) NULL DEFAULT NULL COMMENT '数据访问' ,
                `SECURITY_TYPE`      varchar(255) NULL DEFAULT NULL COMMENT '安全性' ,
                `ROUTINE_COMMENT`    varchar(255) NULL DEFAULT NULL COMMENT '注释' ,
                `PARAM_LIST`         longtext     NULL              COMMENT '参数列表' ,
                INDEX USING BTREE (`ROUTINE_NAME`) 
            ) ENGINE=MyISAM {$charset} COMMENT='存储程序表'");                                                          //创建表存储程序表
        //不支持MyISAM引擎
        } else {
            self::message('error', '不支持引擎', __FUNCTION__, 'MyISAM');
            $returnBool = false;
        }

        //导入数据并比对
        if (
            $returnBool && 
            self::revertData($file, array('showProgress' => false)) && 
            self::isSupportEngines(true) 
        //导入成功
        ) {
            //表过滤条件
            $tableWhere = null;

            //删除匹配外键
            $foreignKey = self::getDatabaseStructure('FOREIGNKEY', $tableWhere);
            if ($temp = count($foreignKey)) {
                self::message('tip', '正在删除外键', __FUNCTION__, $temp);
                foreach ($foreignKey as &$v) {
                    $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                    $v['CONSTRAINT_NAME'] = strtr($v['CONSTRAINT_NAME'], array('`' => '``'));
                    self::sql("ALTER TABLE `{$v['TABLE_NAME']}` DROP FOREIGN KEY `{$v['CONSTRAINT_NAME']}`");
                }
            }
            unset($foreignKey);
            //对比匹配表信息
            self::message('tip', '已更新百分比', 'revertBaseProgress', 10.7);

            //版本 > 5.1.7
            if ($dbVersion > 50107) {
                //批量sql语句
                $bulkSql = array();
                //预处理分区
                $temp = self::getDatabaseStructure('PARTITIONS', $tableWhere);
                //最新分区表信息
                $partitions = self::sql("SELECT * FROM `{$prefix}PARTITIONS`");

                //当前分区信息格式化
                foreach ($temp as &$v) {
                    $bulkSql[$v['TABLE_NAME'] .' '. $v['COLUMNS_COMPARE']] = &$v;
                }
                foreach ($partitions as $k => &$v) {
                    $temp = $v['TABLE_NAME'] .' '. $v['COLUMNS_COMPARE'];
                    //表分区字段未改变
                    if (isset($bulkSql[$temp])) {
                        //表分区语句未改变
                        if ($bulkSql[$temp]['PARTITION_SQL'] === $v['PARTITION_SQL']) {
                            //没有必要更新分区
                            unset($partitions[$k]);
                        }
                        //没有必要移除分区
                        unset($bulkSql[$temp]);
                    }
                }

                empty($bulkSql) || self::message('tip', '正在移除无效分区', __FUNCTION__, count($bulkSql));
                //移除无效分区
                foreach ($bulkSql as &$v) {
                    //反引号表名
                    $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                    //预先移除
                    self::sql("ALTER TABLE `{$v['TABLE_NAME']}` REMOVE PARTITIONING");
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 19.5);                                       //对比匹配表信息
            }

            //最新匹配的表名
            $tablesNew = array();
            //原始表信息
            $tablesOriginal = array();
            //最新表信息
            $tablesActual = self::sql("SELECT * FROM `{$prefix}TABLES`");
            //初始化原始表信息
            $temp = self::getDatabaseStructure('TABLES', $tableWhere);
            foreach ($temp as &$v) {
                $tablesOriginal[$v['TABLE_NAME']] = &$v;
            }
            //比对表信息(创建,修改)
            foreach ($tablesActual as &$v) {
                if (self::isMatch($v['TABLE_NAME'], 'table')) {
                    $tablesNew[addslashes($v['TABLE_NAME'])] = &$v;
                    //加斜线描述
                    $v['TABLE_COMMENT'] = addslashes($v['TABLE_COMMENT']);
                    $v['CREATE_OPTIONS'] = trim(preg_replace('/\s*(?:row_format|key_block_size|partitioned)(=\w+)?\s*/i', ' ', $v['CREATE_OPTIONS']));    //删除创建参数带的行格式,键块大小,分区标识
                    //反引号表名
                    $tableName = strtr($v['TABLE_NAME'], array('`' => '``'));
                    //字符集
                    $charset = explode('_', $v['TABLE_COLLATION'], 2);
                    //字符集
                    $charset = $charset[0];

                    //表存在
                    if (isset($tablesOriginal[$v['TABLE_NAME']])) {
                        $vO = &$tablesOriginal[$v['TABLE_NAME']];
                        //加斜线描述
                        $vO['TABLE_COMMENT'] = addslashes(
                            //替换 mysql 5.1.21 之前的多余注释
                            $dbVersion < 50121 ?
                                preg_replace('@^(.*)(?:(?:; |\1)InnoDB free: \d+ .*)$@', '\1', $vO['TABLE_COMMENT']) :
                                $vO['TABLE_COMMENT']
                        );
                        //删除创建参数带的行格式
                        $vO['CREATE_OPTIONS'] = trim(preg_replace(
                            '/\s*(?:row_format|key_block_size|partitioned)(=\w+)?\s*/i', ' ', 
                            $vO['CREATE_OPTIONS']
                        ));
                        //表信息不同(修改表信息)
                        if ($v !== $vO) {
                            self::message('tip', '正在更新表', __FUNCTION__, $v['TABLE_NAME']);
                            $v['CREATE_OPTIONS'] && $v['CREATE_OPTIONS'] = ',' . strtr($v['CREATE_OPTIONS'], array(' ' => ','));

                            self::sql("ALTER TABLE `{$tableName}`
                            ENGINE={$v['ENGINE']},               /*存储引擎*/
                            DEFAULT CHARACTER SET={$charset},    /*字符集*/
                            COLLATE={$v['TABLE_COLLATION']},     /*排序规则*/
                            COMMENT='{$v['TABLE_COMMENT']}',     /*注释*/
                            ROW_FORMAT={$v['ROW_FORMAT']}        /*行格式*/
                            {$v['CREATE_OPTIONS']}");
                        }
                        //删除已存在的表
                        unset($tablesOriginal[$v['TABLE_NAME']]);
                    //表不存在(创建表)
                    } else {
                        self::message('tip', '正在创建表', __FUNCTION__, $v['TABLE_NAME']);
                        self::sql("CREATE TABLE `{$tableName}` (`__` tinyint NULL)
                        ENGINE={$v['ENGINE']}
                        DEFAULT CHARACTER SET={$charset} COLLATE={$v['TABLE_COLLATION']}
                        COMMENT='{$v['TABLE_COMMENT']}'
                        ROW_FORMAT={$v['ROW_FORMAT']}
                        {$v['CREATE_OPTIONS']}");
                    }
                }
            }
            //删除废弃表
            foreach ($tablesOriginal as &$v) {
                self::message('tip', '正在删除表', __FUNCTION__, $v['TABLE_NAME']);
                //反引号表名
                $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                self::sql("DROP TABLE `{$v['TABLE_NAME']}`");
            }
            unset($tablesOriginal, $tablesActual);
            self::message('tip', '已更新百分比', 'revertBaseProgress', 27);                                             //比对匹配字段

            //完整表过滤条件
            $tableWhere = join('\',\'', array_keys($tablesNew));
            //批量sql语句
            $bulkSql = array();
            //原始字段信息
            $columnsOriginal = array();
            $columnsActual = self::sql("SELECT * FROM `{$prefix}COLUMNS` WHERE TABLE_NAME IN ('{$tableWhere}') ORDER BY TABLE_NAME, ORDINAL_POSITION");    //最新字段信息
            //初始化原始字段信息
            $temp = self::getDatabaseStructure('COLUMNS', $tableWhere);
            foreach ($temp as &$v) {
                $columnsOriginal[$v['TABLE_NAME']][$v['COLUMN_NAME']] = &$v;
            }
            //比对字段(添加,修改)
            foreach ($columnsActual as $k => &$v) {
                //字段名
                $columnName = strtr($v['COLUMN_NAME'], array('`' => '``'));
                //字符集
                $charset = $v['CHARACTER_SET_NAME'] === null ? '' : "CHARACTER SET {$v['CHARACTER_SET_NAME']}";
                //排序规则
                $collationName = $v['COLLATION_NAME'] === null ? '' : " COLLATE {$v['COLLATION_NAME']}";
                //是否允许为空
                $isNullable = $v['IS_NULLABLE'] === 'NO' ? 'NOT' : '';
                //加斜线描述
                $columnComment = addslashes($v['COLUMN_COMMENT']);
                //字段位置
                $position = $v['ORDINAL_POSITION'] === '1' ?
                    'FIRST' : 'AFTER `' .strtr($columnsActual[$k - 1]['COLUMN_NAME'], array('`' => '``')). '`';
                //增加额外索引,保证auto_increment顺利插入
                $addIndex = $v['EXTRA'] === 'auto_increment' ? " ,ADD INDEX (`{$columnName}`)" : '';

                if (
                    //默认值不为 null && 默认值为 null
                    ($v['IS_NULLABLE'] === 'NO' && $v['COLUMN_DEFAULT'] === null) ||
                    //不是虚拟字段(json字段的虚拟字段没有默认值)
                    strpos($v['EXTRA'], 'GENERATED ALWAYS') !== false ||
                    //没有默认值的字段类型
                    preg_match('@blob|text|point|point|polygon|geometry@', $v['COLUMN_TYPE']) 
                //默认值
                ) {
                    //没有默认值
                    $columnDefault = '';
                } else if ($v['COLUMN_DEFAULT'] === null) {
                    $columnDefault = 'DEFAULT NULL';
                } else if ($v['COLUMN_TYPE'] === 'timestamp' && $v['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
                    $columnDefault = 'DEFAULT CURRENT_TIMESTAMP';
                } else {
                    $columnDefault = 'DEFAULT \'' .addslashes($v['COLUMN_DEFAULT']). '\'';
                }

                if (
                    !($temp = isset($columnsOriginal[$v['TABLE_NAME']][$v['COLUMN_NAME']])) || 
                    ($temp = $columnsOriginal[$v['TABLE_NAME']][$v['COLUMN_NAME']] !== $v)
                ) {
                    //修改字段 && 是虚拟字段
                    if ($temp === true && strpos($v['EXTRA'], 'GENERATED ALWAYS') !== false) {
                        //虚拟字段不能直接修改虚拟类型, 为了简便操作需要先删除
                        $bulkSql[$v['TABLE_NAME']][] = "DROP COLUMN `{$columnName}`";
                        //删除后新加字段
                        $temp = false;
                    }
                    $temp = $temp === true ? 'MODIFY' : 'ADD';
                    $bulkSql[$v['TABLE_NAME']][] = "{$temp} COLUMN `{$columnName}` {$v['COLUMN_TYPE']} {$charset}{$collationName} {$v['EXTRA']} {$isNullable} NULL {$columnDefault} COMMENT '{$columnComment}' {$position} {$addIndex}";
                }
                //删除已同步的字段
                unset($columnsOriginal[$v['TABLE_NAME']][$v['COLUMN_NAME']]);
            }
            //删除废弃字段
            foreach ($columnsOriginal as $kO => &$vO) {
                foreach ($vO as $k => &$v) {
                    //字段名
                    $columnName = strtr($k, array('`' => '``'));
                    $bulkSql[$kO][] = "DROP COLUMN `{$columnName}`";
                }
            }
            unset($columnsOriginal, $columnsActual);
            //批量执行sql
            foreach ($bulkSql as $k => &$v) {
                self::message('tip', '正在更新字段', __FUNCTION__, $k);
                //表名
                $tableName = strtr($k, array('`' => '``'));
                self::sql("ALTER TABLE `{$tableName}` " . join(',', $v));
            }

            //设置自增值
            if (!empty($config['setInc'])) {
                foreach ($tablesNew as $k => &$v) {
                    $temp = strtr($v['TABLE_NAME'], array('`' => '``'));
                    self::sql("ALTER TABLE `{$temp}` AUTO_INCREMENT = {$v['AUTO_INCREMENT']}");
                }
            }

            //开始对比匹配索引
            self::message('tip', '已更新百分比', 'revertBaseProgress', 33.3);
            //批量sql语句
            $bulkSql = array();
            //原始字段信息
            $statisticsOriginal = array();
            //最新字段信息
            $statisticsActual = self::sql("SELECT * FROM `{$prefix}STATISTICS` WHERE TABLE_NAME IN ('{$tableWhere}')");
            //初始化原始字段信息
            $temp = self::getDatabaseStructure('STATISTICS', $tableWhere);
            foreach ($temp as &$v) {
                $statisticsOriginal[$v['TABLE_NAME']][$v['INDEX_NAME']] = &$v;
            }
            //对比索引(添加,修改)
            foreach ($statisticsActual as &$v) {
                //字段名
                $indexName = $v['INDEX_NAME'] === 'PRIMARY' ?
                    '' : '`' .strtr($v['INDEX_NAME'], array('`' => '``')). '`';
                //索引类型
                $indexType = $v['INDEX_TYPE'] === 'FULLTEXT' || $v['INDEX_NAME'] === 'PRIMARY' ?
                    '' : "USING {$v['INDEX_TYPE']}";
                //索引关键词
                if ($v['INDEX_NAME'] === 'PRIMARY') {
                    //主键
                    $indexKey = 'PRIMARY KEY';
                } else if ($v['INDEX_TYPE'] === 'FULLTEXT') {
                    //文本索引
                    $indexKey = 'FULLTEXT INDEX';
                } else if ($v['NON_UNIQUE'] === '0') {
                    //唯一索引
                    $indexKey = 'UNIQUE INDEX';
                } else {
                    //常规索引
                    $indexKey = 'INDEX';
                }

                if (
                    !isset($statisticsOriginal[$v['TABLE_NAME']][$v['INDEX_NAME']]) || 
                    $temp = $statisticsOriginal[$v['TABLE_NAME']][$v['INDEX_NAME']] !== $v 
                ) {
                    $temp = $temp === true ? 
                        'DROP ' .($v['INDEX_NAME'] === 'PRIMARY' ? 'PRIMARY KEY' : 'INDEX'). " {$indexName}," : '';    //删除类型
                    $bulkSql[$v['TABLE_NAME']][] = "{$temp} ADD {$indexKey} {$indexName} {$indexType} ({$v['COLUMNS_NAME']})";
                }
                unset($statisticsOriginal[$v['TABLE_NAME']][$v['INDEX_NAME']]);
            }
            //删除废弃索引
            foreach ($statisticsOriginal as $kO => &$vO) {
                //表名
                $tableName = strtr($kO, array('`' => '``'));
                foreach ($vO as $k => &$v) {
                    $temp = $v['INDEX_NAME'] === 'PRIMARY' ? array(
                        'indexName' => '',
                        'dropKey'   => 'PRIMARY KEY'
                    ) : array(
                        'indexName' => '`' .strtr($k, array('`' => '``')). '`',
                        'dropKey'   => 'INDEX'
                    );
                    $bulkSql[$kO][] = "DROP {$temp['dropKey']} {$temp['indexName']}";
                }
            }
            unset($statisticsOriginal, $statisticsActual);
            //批量执行sql
            foreach ($bulkSql as $k => &$v) {
                self::message('tip', '正在更新索引', __FUNCTION__, $k);
                //表名
                $tableName = strtr($k, array('`' => '``'));
                self::sql("ALTER TABLE `{$tableName}` " . join(',', $v));
            }
            self::message('tip', '已更新百分比', 'revertBaseProgress', 41.7);

            //删除匹配触发器
            $triggers = self::getDatabaseStructure('TRIGGERS', $tableWhere);
            if ($temp = count($triggers)) {
                self::message('tip', '正在删除触发器', __FUNCTION__, $temp);
                foreach ($triggers as &$v) {
                    self::sql('DROP TRIGGER `' .strtr($v['TRIGGER_NAME'], array('`' => '``')). '`');
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 50);
            }

            //最新字段信息
            $triggers = self::sql("SELECT * FROM `{$prefix}TRIGGERS` WHERE EVENT_OBJECT_TABLE IN ('{$tableWhere}')");
            //创建匹配触发器
            if ($temp = count($triggers)) {
                self::message('tip', '正在创建触发器', __FUNCTION__, $temp);
                foreach ($triggers as &$v) {
                    $triggerNameSlashes = addslashes($v['TRIGGER_NAME']);
                    //验证重复触发器名
                    do {
                        $temp = self::sql("SELECT COUNT(*) c FROM information_schema.`TRIGGERS` WHERE `TRIGGERS`.TRIGGER_NAME = '{$triggerNameSlashes}'");
                        //false=防止执行错误死循环
                        if ($temp === false || $temp[0]['c'] === '0') {
                            $v['TRIGGER_NAME'] = stripslashes($triggerNameSlashes);
                            break;
                        } else {
                            $triggerNameSlashes = uniqid();
                        }
                    } while (true);
                    $v['TRIGGER_NAME'] = strtr($v['TRIGGER_NAME'], array('`' => '``'));
                    $v['EVENT_OBJECT_TABLE'] = strtr($v['EVENT_OBJECT_TABLE'], array('`' => '``'));
                    self::sql("CREATE TRIGGER `{$v['TRIGGER_NAME']}` {$v['ACTION_TIMING']} {$v['EVENT_MANIPULATION']} ON `{$v['EVENT_OBJECT_TABLE']}` FOR EACH ROW {$v['ACTION_STATEMENT']}");
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 58.3);
            }
            unset($triggers);

            //版本 > 5.1.7
            if ($dbVersion > 50107 && !empty($partitions)) {
                self::message('tip', '正在变更分区', __FUNCTION__, count($partitions));
                //修改变动的分区
                foreach ($partitions as &$v) {
                    $temp = strtr($v['TABLE_NAME'], array('`' => '``'));
                    //执行分区语句
                    self::sql("ALTER TABLE `{$temp}` {$v['PARTITION_SQL']}");
                }
                unset($partitions);
                self::message('tip', '已更新百分比', 'revertBaseProgress', 65.3);
            }

            //删除匹配视图
            $views = self::getDatabaseStructure('VIEWS');
            if ($temp = count($views)) {
                self::message('tip', '正在删除视图', __FUNCTION__, $temp);
                foreach ($views as &$v) {
                    $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                    self::sql("DROP VIEW `{$v['TABLE_NAME']}`");
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 70.7);
            }

            //创建匹配视图
            $views = self::sql("SELECT * FROM `{$prefix}VIEWS`");
            if ($temp = count($views)) {
                self::message('tip', '正在创建视图', __FUNCTION__, $temp);
                foreach ($views as &$v) {
                    if (self::isMatch($v['TABLE_NAME'], 'view')) {
                        $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                        $v['IS_UPDATABLE'] = $v['IS_UPDATABLE'] === 'YES' ? 'MERGE' : 'TEMPTABLE';
                        //检查项
                        $v['CHECK_OPTION'] = $v['CHECK_OPTION'] === 'NONE' ?
                            '' : "WITH {$v['CHECK_OPTION']} CHECK OPTION";
                        self::sql("CREATE ALGORITHM={$v['IS_UPDATABLE']} SQL SECURITY {$v['SECURITY_TYPE']} 
                        VIEW `{$v['TABLE_NAME']}` AS {$v['VIEW_DEFINITION']} {$v['CHECK_OPTION']}");
                    }
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 79);
            }
            unset($views);

            //删除匹配存储程序
            $routines = self::getDatabaseStructure('ROUTINES');
            if ($temp = count($routines)) {
                self::message('tip', '正在删除存储程序', __FUNCTION__, $temp);
                foreach ($routines as &$v) {
                    $v['ROUTINE_NAME'] = strtr($v['ROUTINE_NAME'], array('`' => '``'));
                    self::sql("DROP {$v['ROUTINE_TYPE']} `{$v['ROUTINE_NAME']}`");
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 87.3);
            }

            //创建匹配存储程序
            $routines = self::sql("SELECT * FROM `{$prefix}ROUTINES`");
            if ($temp = count($routines)) {
                self::message('tip', '正在创建存储程序', __FUNCTION__, $temp);
                foreach ($routines as &$v) {
                    if (self::isMatch($v['ROUTINE_NAME'], strtolower($v['ROUTINE_TYPE']))) {
                        $v['ROUTINE_NAME'] = strtr($v['ROUTINE_NAME'], array('`' => '``'));
                        //决定性
                        $v['IS_DETERMINISTIC'] = $v['IS_DETERMINISTIC'] === 'NO' ? '' : 'DETERMINISTIC';
                        //返回值
                        $v['DTD_IDENTIFIER'] && $v['DTD_IDENTIFIER'] = "RETURNS {$v['DTD_IDENTIFIER']}";
                        //注释
                        $v['ROUTINE_COMMENT'] = addslashes($v['ROUTINE_COMMENT']);
                        self::sql("CREATE {$v['ROUTINE_TYPE']} `{$v['ROUTINE_NAME']}`({$v['PARAM_LIST']}) {$v['DTD_IDENTIFIER']}
                            {$v['SQL_DATA_ACCESS']} SQL SECURITY {$v['SECURITY_TYPE']}
                            {$v['IS_DETERMINISTIC']} COMMENT '{$v['ROUTINE_COMMENT']}' {$v['ROUTINE_DEFINITION']}");
                    }
                }
                self::message('tip', '已更新百分比', 'revertBaseProgress', 95.7);
            }
            unset($routines);

            //创建匹配外键
            $foreignKey = self::sql("SELECT * FROM `{$prefix}FOREIGNKEY`");
            if ($temp = count($foreignKey)) {
                self::message('tip', '正在创建外键', __FUNCTION__, $temp);
                foreach ($foreignKey as &$v) {
                    if (self::isMatch($v['TABLE_NAME'], 'table')) {
                        $v['TABLE_NAME'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                        $v['CONSTRAINT_NAME'] = strtr($v['CONSTRAINT_NAME'], array('`' => '``'));
                        $v['REFERENCED_TABLE_NAME'] = strtr($v['REFERENCED_TABLE_NAME'], array('`' => '``'));
                        self::sql("ALTER TABLE `{$v['TABLE_NAME']}`
                        ADD CONSTRAINT `{$v['CONSTRAINT_NAME']}` FOREIGN KEY ({$v['COLUMNS_NAME']})
                        REFERENCES `{$v['REFERENCED_TABLE_NAME']}` ({$v['REFERENCED_COLUMNS_NAME']})
                        ON DELETE {$v['DELETE_RULE']} ON UPDATE {$v['UPDATE_RULE']}");
                    }
                }
            }
            unset($foreignKey);
        } else {
            $returnBool = false;
        }

        //删除内部使用表
        self::sql($dropPrefix);
        self::message($returnBool ? 
            'success' : 'error', '结构更新' . ($returnBool ? '完成' : '失败'), 'revertBaseProgress', 100);
        return $returnBool;
    }

    /**
     * 描述 : 更新备份结构
     * 参数 :
     *      file : 指定解析的文件全路径
     * 返回 :
     *      文件不存在或任何一条提取的sql执行错误返回false,否则true
     * 作者 : Edgar.lee
     */
    public static function revertTable($file) {
        self::message('tip', '开始更新结构', __FUNCTION__);

        //打开文件流
        if ($returnBool = self::fetchFileSql($file)) {
            while ($sql = self::fetchFileSql()) {
                self::sql($sql) === false && $returnBool = false;
            }
        }

        self::message($returnBool ? 
            'success' : 'error', '结构更新' . ($returnBool ? '完成' : '失败'), 'revertBaseProgress', 100);

        return $returnBool;
    }

    /************************************************************** 备份数据库
     * 描述 : 备份表数据到指定文件
     * 参数 :
     *      file   : 指定备份全路径
     *      config : 备份配置 {
     *          'type'  : 导出类型('INSERT', 'REPLACE':默认, 数组:修复模式{表名:[限制字段, ...], ..})之一,使用'INSERT'加入 DELETE FROM `xxx` 清空表
     *          'count' : 扩展数量,默认200
     *      }
     * 作者 : Edgar.lee
     */
    public static function backupData($file, $config = array()) {
        //返回布尔值
        $returnBool = true;
        //数据库
        $database = self::$config['databaseSlashes'];
        //分隔符
        $sqlSplit = self::$config['sqlSplit'];
        //sql标记
        $sqlTableMark = self::$config['sqlMark']['table'];
        //获取过滤列表
        $tableMatches = &self::getMatches('table');
        //打开文件流
        $fp = &self::openFile($file, 'w');
        $config += array(
            'type'  => 'REPLACE',
            'count' => 200
        );

        self::message('tip', '开始备份数据', __FUNCTION__, $tableMatchesNum = count($tableMatches));
        if ($fp === false) {
            $returnBool = false;
        } else if ($tableMatchesNum) {
            //批量替换反引号
            foreach ($tableMatches as &$v) $tableBacktick[$v] = strtr($v, array('`' => '``'));
            //关闭外键限制
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0{$sqlSplit}\n");
            //全表读锁
            self::sql('LOCK TABLES `' .join('` READ, `', $tableBacktick). '` READ');
            //[当前位置, 进度]
            $nowProgress = array(0,0);
            //字符串,插入或替换模式
            if (is_string($config['type'])) {
                $insType = $config['type'] = strtoupper($config['type']);
                $insKey = 'VALUES';
                fwrite($fp, "LOCK TABLES {$sqlTableMark}`" . join("`{$sqlTableMark} WRITE, {$sqlTableMark}`", $tableBacktick). "`{$sqlTableMark} WRITE{$sqlSplit}\n");    //锁全写表
            //数组,修复模式(无匹配指定字段时添加)
            } else {
                $insType = 'REPLACE';
                $insKey = 'SELECT';
            }

            foreach ($tableMatches as $nowProgress[0] => &$tableName) {
                if (($temp = round($nowProgress[0] * 100 / $tableMatchesNum, 1)) > $nowProgress[1] + 10) {
                    self::message('tip', '已备份百分比', 'backupDataProgress', $nowProgress[1] = $temp);
                }
                self::message('tip', '正在备份', __FUNCTION__, $tableName);                                             //提取备份头

                //加斜线的表名
                $tableNameSlashes = addslashes($tableName);
                $temp = self::sql("SELECT
                    GROUP_CONCAT(
                        REPLACE(`COLUMNS`.COLUMN_NAME, '`', '``')
                        ORDER BY `COLUMNS`.ORDINAL_POSITION
                        SEPARATOR '`,`'
                    ) v
                FROM
                    information_schema.`COLUMNS`
                WHERE
                    TABLE_NAME   = '{$tableNameSlashes}'
                AND TABLE_SCHEMA = '{$database}'");
                $nowSqlHead = "{$insType} INTO {$sqlTableMark}`{$tableBacktick[$tableName]}`{$sqlTableMark} (`{$temp[0]['v']}`) {$insKey} ";

                //提取备份数据
                $i = 0;
                //修复模式
                if ($insKey === 'SELECT') {
                    if (
                        //非空存在
                        !empty($config['type'][$tableName]) &&
                        //是数组
                        is_array($config['type'][$tableName])
                    ) {
                        $config['type'][$tableName] = array_flip($config['type'][$tableName]);
                        //转变成{原字段名:加引号的字段名}
                        foreach ($config['type'][$tableName] as $k => &$v) {
                            $v = strtr($k, array('`' => '``'));
                        }
                    } else {
                        $config['type'][$tableName] = null;
                    }
                //INSERT时,加入清空表
                } else if ($config['type'] === 'INSERT') {
                    fwrite($fp, "DELETE FROM {$sqlTableMark}`{$tableBacktick[$tableName]}`{$sqlTableMark}{$sqlSplit}\n");
                }

                while (true) {
                    $dataList = self::sql("SELECT * FROM `{$tableBacktick[$tableName]}` LIMIT {$i}, {$config['count']}");
                    //非空数组
                    if (is_array($dataList) && isset($dataList[0])) {
                        $nowData = array();
                        foreach ($dataList as &$data) {
                            foreach ($data as &$v) {
                                $v = $v === null ? 'NULL' : '\'' .addslashes($v). '\'';
                            }
                            //修复模式
                            if ($insKey === 'SELECT') {
                                $repairWhere = null;
                                //对应表名有限制
                                if (isset($config['type'][$tableName])) {
                                    foreach ($config['type'][$tableName] as $stintName => &$stintBacktickName) {
                                        $repairWhere[] = "`{$stintBacktickName}` = {$data[$stintName]}";
                                    }
                                    $repairWhere = " FROM (SELECT TRUE) `data` WHERE NOT EXISTS(SELECT TRUE FROM {$sqlTableMark}`{$tableBacktick[$tableName]}`{$sqlTableMark} WHERE " . join(' AND ', $repairWhere) . ' LIMIT 1)';
                                }
                                $nowData[] = $nowSqlHead . join(',', $data) . $repairWhere;
                            //插入或替换模式
                            } else {
                                $nowData[] = '(' .join(',', $data). ')';
                            }
                        }

                        //修复模式
                        if ($insKey === 'SELECT') {
                            fwrite($fp, join("{$sqlSplit}\n", $nowData) . "{$sqlSplit}\n");
                        //插入或替换模式时匹配操作
                        } else {
                            fwrite($fp, $nowSqlHead . join(",\n", $nowData) . "{$sqlSplit}\n");
                        }

                        $i += $config['count'];
                    //无数据
                    } else {
                        break;
                    }
                }
            }

            self::sql('UNLOCK TABLES');
            //非修复模式时解锁
            $insKey === 'SELECT' || fwrite($fp, "UNLOCK TABLES{$sqlSplit}\n");
            //开启外键限制
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1{$sqlSplit}\n");
        }

        self::openFile(false);
        self::message($returnBool ? 'success' : 'error', '数据备份' . ($returnBool ? '完成' : '失败'), 'backupDataProgress', 100);
        return $returnBool;
    }

    /**
     * 描述 : 备份表结构到指定文件
     * 参数 :
     *      file   : 指定备份全路径
     *      config : 备份配置 {
     *          'count' : 扩展数量,默认200
     *      }
     * 返回 :
     *      包含错误返回false,否则true
     * 作者 : Edgar.lee
     */
    public static function backupBase($file, $config = array()) {
        //返回的布尔值
        $returnBool = true;
        //数据库
        $database = self::$config['databaseSlashes'];
        //分隔符
        $sqlSplit = self::$config['sqlSplit'];
        //内部前缀
        $prefix = self::$config['prefix'];
        //数据库版本
        $dbVersion = self::$config['dbVersion'];
        //打开文件流
        $fp = &self::openFile($file, 'w');
        $config += array(
            'count' => 200
        );

        if ($fp === false) {
            $returnBool = false;
        //备份表相关
        } else {
            //获取过滤列表
            $tableMatches = &self::getMatches('table');
            self::message('tip', '开始备份表', __FUNCTION__, $temp = count($tableMatches));
            if ($temp) {
                //sql标记
                $sqlTableMark = self::$config['sqlMark']['table'];
                //表名限制条件,格式:"表名','表名','表名..."
                $tableWhere = join('\',\'', array_map('addslashes', $tableMatches));
                //表名反转
                $tableFlip = array_flip($tableMatches);

                //外键规则
                $foreignKeyRule = array();
                //备份外键
                $foreignKey = self::getDatabaseStructure('FOREIGNKEY', $tableWhere);
                if (count($foreignKey)) {
                    self::message('tip', '正在备份外键', __FUNCTION__);
                    foreach ($foreignKey as &$v) {
                        //外键关联表有效
                        if (isset($tableFlip[$v['REFERENCED_TABLE_NAME']])) {
                            //提取外键更新及时删除规则
                            if (!isset($foreignKeyRule[$v['TABLE_NAME']][$v['CONSTRAINT_NAME']])) {
                                $rule = &$foreignKeyRule[$v['TABLE_NAME']];
                                $temp = self::sql('SHOW CREATE TABLE `' .strtr($v['TABLE_NAME'], array('`' => '``')). '`');
                                preg_match_all('@CONSTRAINT `(.*)` FOREIGN KEY .*\) *([\w ]*)@', $temp[0]['Create Table'], $temp, PREG_SET_ORDER);
                                foreach ($temp as &$match) {
                                    $match[2] = explode('ON UPDATE ', trim(strtr($match[2], array('ON DELETE ' => ''))));
                                    $rule[$match[1]] = array(
                                        //删除规则
                                        'DELETE_RULE' => empty($match[2][0]) ? 'RESTRICT' : $match[2][0],
                                        //更新规则
                                        'UPDATE_RULE' => empty($match[2][1]) ? 'RESTRICT' : $match[2][1]
                                    );
                                }
                            }
                            //添加外键规则
                            $v += $foreignKeyRule[$v['TABLE_NAME']][$v['CONSTRAINT_NAME']];
                            $v = array_map('addslashes', $v);
                            $v = "('{$v['CONSTRAINT_NAME']}',{$sqlTableMark}'{$v['TABLE_NAME']}'{$sqlTableMark},{$sqlTableMark}'{$v['REFERENCED_TABLE_NAME']}'{$sqlTableMark},'{$v['COLUMNS_NAME']}','{$v['REFERENCED_COLUMNS_NAME']}','{$v['UPDATE_RULE']}','{$v['DELETE_RULE']}')";
                        } else {
                            self::message('error', '指定表的外键关联表不在备份范围内', __FUNCTION__, "{$v['TABLE_NAME']}->{$v['CONSTRAINT_NAME']}");
                            $returnBool = false;
                        }
                    }

                    //写入
                    fwrite($fp, self::sqlChunkMerger(
                        "INSERT INTO `{$prefix}FOREIGNKEY` (`CONSTRAINT_NAME`,`TABLE_NAME`,`REFERENCED_TABLE_NAME`,`COLUMNS_NAME`,`REFERENCED_COLUMNS_NAME`,`UPDATE_RULE`,`DELETE_RULE`) VALUES ",
                        $foreignKey, $config['count']
                    ));
                    unset($foreignKey);

                    self::message('tip', '已备份百分比', 'backupBaseProgress', 14.3);
                }

                //备份触发器
                $triggers = self::getDatabaseStructure('TRIGGERS', $tableWhere);
                if (count($triggers)) {
                    self::message('tip', '正在备份触发器', __FUNCTION__);
                    foreach ($triggers as &$v) {
                        $v = array_map('addslashes', $v);
                        $v = "('{$v['TRIGGER_NAME']}','{$v['EVENT_MANIPULATION']}',{$sqlTableMark}'{$v['EVENT_OBJECT_TABLE']}'{$sqlTableMark},'{$v['ACTION_STATEMENT']}','{$v['ACTION_TIMING']}')";
                    }

                    //写入
                    fwrite($fp, self::sqlChunkMerger(
                        "INSERT INTO `{$prefix}TRIGGERS` (`TRIGGER_NAME`,`EVENT_MANIPULATION`,`EVENT_OBJECT_TABLE`,`ACTION_STATEMENT`,`ACTION_TIMING`) VALUES ",
                        $triggers, $config['count']
                    ));
                    unset($triggers);

                    self::message('tip', '已备份百分比', 'backupBaseProgress', 28.6);
                }

                //备份表信息
                $tables = self::getDatabaseStructure('TABLES', $tableWhere);
                if (count($tables)) {
                    self::message('tip', '正在备份表信息', __FUNCTION__);
                    foreach ($tables as &$v) {
                        //替换 mysql 5.1.21 之前多余的描述
                        if ($dbVersion < 50121) {
                            $v['TABLE_COMMENT'] = preg_replace('@^(.*)(?:(?:; |\1)InnoDB free: \d+ .*)$@', '\1', $v['TABLE_COMMENT']);
                        }
                        $v = array_map('addslashes', $v);
                        $v = "({$sqlTableMark}'{$v['TABLE_NAME']}'{$sqlTableMark},'{$v['ENGINE']}','{$v['ROW_FORMAT']}','{$v['TABLE_COLLATION']}','{$v['AUTO_INCREMENT']}','{$v['CREATE_OPTIONS']}','{$v['TABLE_COMMENT']}')";
                    }

                    //写入
                    fwrite($fp, self::sqlChunkMerger(
                        "INSERT INTO `{$prefix}TABLES` (`TABLE_NAME`,`ENGINE`,`ROW_FORMAT`,`TABLE_COLLATION`,`AUTO_INCREMENT`,`CREATE_OPTIONS`,`TABLE_COMMENT`) VALUES ",
                        $tables, $config['count']
                    ));
                    unset($tables);

                    self::message('tip', '已备份百分比', 'backupBaseProgress', 42.9);
                }

                //备份字段
                $columns = self::getDatabaseStructure('COLUMNS', $tableWhere);
                if (count($columns)) {
                    self::message('tip', '正在备份字段', __FUNCTION__);
                    foreach ($columns as &$v) {
                        foreach ($v as &$value) {
                            $value = $value === null ? 'NULL' : '\'' .addslashes($value). '\'';
                        }
                        $v = "({$sqlTableMark}{$v['TABLE_NAME']}{$sqlTableMark},{$v['COLUMN_NAME']},{$v['ORDINAL_POSITION']},{$v['COLUMN_DEFAULT']},{$v['IS_NULLABLE']},{$v['CHARACTER_SET_NAME']},{$v['COLLATION_NAME']},{$v['COLUMN_TYPE']},{$v['EXTRA']},{$v['COLUMN_COMMENT']})";
                    }

                    //写入
                    fwrite($fp, self::sqlChunkMerger(
                        "INSERT INTO `{$prefix}COLUMNS` (`TABLE_NAME`,`COLUMN_NAME`,`ORDINAL_POSITION`,`COLUMN_DEFAULT`,`IS_NULLABLE`,`CHARACTER_SET_NAME`,`COLLATION_NAME`,`COLUMN_TYPE`,`EXTRA`,`COLUMN_COMMENT`) VALUES ",
                        $columns, $config['count']
                    ));
                    unset($columns);

                    self::message('tip', '已备份百分比', 'backupBaseProgress', 57.1);
                }

                //备份索引
                $statistics = self::getDatabaseStructure('STATISTICS', $tableWhere);
                if (count($statistics)) {
                    self::message('tip', '正在备份索引', __FUNCTION__);
                    foreach ($statistics as &$v) {
                        $v = array_map('addslashes', $v);
                        $v = "({$sqlTableMark}'{$v['TABLE_NAME']}'{$sqlTableMark},'{$v['NON_UNIQUE']}','{$v['INDEX_NAME']}','{$v['COLUMNS_NAME']}','{$v['INDEX_TYPE']}')";
                    }

                    //写入
                    fwrite($fp, self::sqlChunkMerger(
                        "INSERT INTO `{$prefix}STATISTICS` (`TABLE_NAME`,`NON_UNIQUE`,`INDEX_NAME`,`COLUMNS_NAME`,`INDEX_TYPE`) VALUES ",
                        $statistics, $config['count']
                    ));
                    unset($statistics);

                    self::message('tip', '已备份百分比', 'backupBaseProgress', 65.9);
                }

                //版本 > 5.1.7
                if ($dbVersion > 50107) {
                    //备份分区
                    $partitions = self::getDatabaseStructure('PARTITIONS', $tableWhere);
                    if (count($partitions)) {
                        self::message('tip', '正在备份分区', __FUNCTION__);
                        foreach ($partitions as &$v) {
                            $v = array_map('addslashes', $v);
                            $v = "({$sqlTableMark}'{$v['TABLE_NAME']}'{$sqlTableMark},'{$v['PARTITION_SQL']}','{$v['COLUMNS_COMPARE']}')";
                        }

                        //写入
                        fwrite($fp, self::sqlChunkMerger(
                            "INSERT INTO `{$prefix}PARTITIONS` (`TABLE_NAME`,`PARTITION_SQL`, `COLUMNS_COMPARE`) VALUES ",
                            $partitions, $config['count']
                        ));
                        unset($partitions);

                        self::message('tip', '已备份百分比', 'backupBaseProgress', 74.4);
                    }
                }
            }

            //备份视图相关
            $views = self::getDatabaseStructure('VIEWS');
            if ($temp = count($views)) {
                self::message('tip', '开始备份视图', __FUNCTION__, $temp);
                //sql标记
                $sqlViewMark = self::$config['sqlMark']['view'];
                //分析视图体
                foreach ($views as &$v) {
                    $v['VIEW_DEFINITION'] = strtr($v['TABLE_NAME'], array('`' => '``'));
                    $temp = self::sql("SHOW CREATE VIEW `{$v['VIEW_DEFINITION']}`");
                    preg_match('@^.*? SQL SECURITY \w+ VIEW `.{' .strlen($v['VIEW_DEFINITION']). '}` AS (.*)@', $temp[0]['Create View'], $temp);
                    $v['VIEW_DEFINITION'] = $temp[1];

                    $v = array_map('addslashes', $v);
                    $v = "({$sqlViewMark}'{$v['TABLE_NAME']}'{$sqlViewMark},'{$v['VIEW_DEFINITION']}','{$v['CHECK_OPTION']}','{$v['IS_UPDATABLE']}','{$v['SECURITY_TYPE']}')";
                }

                //写入
                fwrite($fp, self::sqlChunkMerger(
                    "INSERT INTO `{$prefix}VIEWS` (`TABLE_NAME`,`VIEW_DEFINITION`,`CHECK_OPTION`,`IS_UPDATABLE`,`SECURITY_TYPE`) VALUES ",
                    $views, $config['count']
                ));
                unset($views);

                self::message('tip', '已备份百分比', 'backupBaseProgress', 85.7);
            }

            //备份存储程序相关
            $routines = self::getDatabaseStructure('ROUTINES');
            if ($temp = count($routines)) {
                self::message('tip', '开始备份存储程序', __FUNCTION__, $temp);
                //sql标记
                $sqlProcedureMark = self::$config['sqlMark']['procedure'];
                //sql标记
                $sqlFunctionMark = self::$config['sqlMark']['function'];
                foreach ($routines as &$v) {
                    if ($v['ROUTINE_TYPE'] === 'FUNCTION') {
                        $createSql = 'Create Function';
                        $sqlMark = $sqlFunctionMark;
                        $matchParams = '(?=\s+RETURNS)';
                    } else {
                        $createSql = 'Create Procedure';
                        $sqlMark = $sqlProcedureMark;
                        $matchParams = '';
                    }

                    //分析函数参数
                    $v['PARAM_LIST'] = strtr($v['ROUTINE_NAME'], array('`' => '``'));
                    $temp = self::sql("SHOW CREATE {$v['ROUTINE_TYPE']} `{$v['PARAM_LIST']}`");
                    preg_match("@CREATE (?:DEFINER=.* |){$v['ROUTINE_TYPE']} `.{" .strlen($v['PARAM_LIST']). "}`\((.*)\){$matchParams}@", $temp[0][$createSql], $temp);
                    $v['PARAM_LIST'] = $temp[1];

                    $v = array_map('addslashes', $v);
                    $v = "({$sqlMark}'{$v['ROUTINE_NAME']}'{$sqlMark},'{$v['ROUTINE_TYPE']}','{$v['DTD_IDENTIFIER']}','{$v['ROUTINE_DEFINITION']}','{$v['IS_DETERMINISTIC']}','{$v['SQL_DATA_ACCESS']}','{$v['SECURITY_TYPE']}','{$v['ROUTINE_COMMENT']}','{$v['PARAM_LIST']}')";
                }

                //写入
                fwrite($fp, self::sqlChunkMerger(
                    "INSERT INTO `{$prefix}ROUTINES` (`ROUTINE_NAME`,`ROUTINE_TYPE`,`DTD_IDENTIFIER`,`ROUTINE_DEFINITION`,`IS_DETERMINISTIC`,`SQL_DATA_ACCESS`,`SECURITY_TYPE`,`ROUTINE_COMMENT`,`PARAM_LIST`) VALUES ",
                    $routines, $config['count']
                ));
                unset($routines);
            }
        }

        self::openFile(false);
        self::message($returnBool ? 'success' : 'error', '结构备份' . ($returnBool ? '完成' : '失败'), 'backupBaseProgress', 100);
        return $returnBool;
    }

    /**
     * 描述 : 备份表语句
     * 参数 :
     *      file   : 指定备份全路径
     * 返回 :
     *      包含错误返回false,否则true
     * 作者 : Edgar.lee
     */
    public static function backupTable($file) {
        //返回的布尔值
        $returnBool = true;
        //分隔符
        $sqlSplit = self::$config['sqlSplit'];
        //打开文件流
        $fp = &self::openFile($file, 'w');
        //关闭外键限制
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0{$sqlSplit}\n\n");

        //文件打卡成功
        if ($returnBool = $fp !== false) {
            //获取过滤列表
            $tableMatches = &self::getMatches('table');
            self::message('tip', '开始备份表', __FUNCTION__, count($tableMatches));

            //生成创表语句
            foreach ($tableMatches as &$v) {
                //表名
                $name = strtr($v, array('`' => '``'));

                //写入删表语句
                fwrite($fp, "DROP TABLE IF EXISTS `{$name}`{$sqlSplit}\n\n");

                //写入创表语句
                $temp = self::sql('SHOW CREATE TABLE `' . $name . '`');
                //去掉自增
                $temp[0]['Create Table'] = preg_replace(
                    '@\sAUTO_INCREMENT\s*=\s*\d+\s@i',
                    ' ',
                    $temp[0]['Create Table']
                );
                fwrite($fp, $temp[0]['Create Table'] . "{$sqlSplit}\n\n");
            }

            //表语句备份完成
            self::message('tip', '已备份百分比', 'backupBaseProgress', 25);

            //获取过滤列表
            $viewsMatches = &self::getMatches('view');
            self::message('tip', '开始备份视图', __FUNCTION__, count($viewsMatches));

            //生成创图语句
            foreach ($viewsMatches as &$v) {
                //视图名
                $name = strtr($v, array('`' => '``'));

                //写入删图语句
                fwrite($fp, "DROP VIEW IF EXISTS `{$name}`{$sqlSplit}\n\n");

                //写入创图语句
                $temp = self::sql('SHOW CREATE VIEW `' . $name . '`');
                //去掉调用权限
                $temp[0]['Create View'] = preg_replace(
                    '@\sDEFINER\s*=\s*[^ ]+\s@i',
                    ' ',
                    $temp[0]['Create View']
                );
                fwrite($fp, $temp[0]['Create View'] . "{$sqlSplit}\n\n");
            }

            //视图语句备份完成
            self::message('tip', '已备份百分比', 'backupBaseProgress', 50);

            //获取过滤列表
            $procMatches = &self::getMatches('procedure');
            self::message('tip', '开始备份过程', __FUNCTION__, count($procMatches));

            //生成过程语句
            foreach ($procMatches as &$v) {
                //表名
                $name = strtr($v, array('`' => '``'));

                //写入删过程语句
                fwrite($fp, "DROP PROCEDURE IF EXISTS `{$name}`{$sqlSplit}\n\n");

                //写入创过程语句
                $temp = self::sql('SHOW CREATE PROCEDURE `' . $name . '`');
                //去掉调用权限
                $temp[0]['Create Procedure'] = preg_replace(
                    '@\sDEFINER\s*=\s*[^ ]+\s@i',
                    ' ',
                    $temp[0]['Create Procedure']
                );
                fwrite($fp, $temp[0]['Create Procedure'] . "{$sqlSplit}\n\n");
            }

            //过程备份完成
            self::message('tip', '已备份百分比', 'backupBaseProgress', 75);

            //获取过滤列表
            $funcMatches = &self::getMatches('function');
            self::message('tip', '开始备份函数', __FUNCTION__, count($funcMatches));

            //生成过程语句
            foreach ($funcMatches as &$v) {
                //表名
                $name = strtr($v, array('`' => '``'));

                //写入删过程语句
                fwrite($fp, "DROP FUNCTION IF EXISTS `{$name}`{$sqlSplit}\n\n");

                //写入创过程语句
                $temp = self::sql('SHOW CREATE FUNCTION `' . $name . '`');
                //去掉调用权限
                $temp[0]['Create Function'] = preg_replace(
                    '@\sDEFINER\s*=\s*[^ ]+\s@i',
                    ' ',
                    $temp[0]['Create Function']
                );
                fwrite($fp, $temp[0]['Create Function'] . "{$sqlSplit}\n\n");
            }

            //过程备份完成
            self::message('tip', '已备份百分比', 'backupBaseProgress', 100);
        }

        //开启外键限制
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1{$sqlSplit}");
        return $returnBool;
    }

    /************************************************************** sql操作工具
     * 描述 : 从文件中提取一条sql语句
     * 参数 :
     *      file : 文件全路径(null)
     * 返回 :
     *      flie=null : 依次提取下一条sql语句,失败或结束返回null
     *      否则      : 打开一个新的文件流,成功返回true,失败false
     * 作者 : Edgar.lee
     */
    public static function fetchFileSql($file = null) {
        //分析文件的偏移位置
        static $fileOffset = 0;
        //打开文件流
        $fp = &self::openFile($file, 'r');

        //依次提取sql
        if ($file === null) {
            //未打开文件流
            if ($fp === false) {
                self::message('error', '未指定提取文件', __FUNCTION__);
                return null;
            //文件流已打开
            } else {
                //定位起始点
                fseek($fp, $fileOffset);
                //本次分析字符串
                $str = '';
                //最后一次读取的数据
                $lastStr = false;
                //实时相对偏移点
                $nowOffset = 0;
                //分隔符
                $sqlSplit = self::$config['sqlSplit'];
                //临时匹配程序运行时生成
                $tempMatches = null;
                //存储默认匹配值
                $defaultMatches = array(
                    //单引字符串
                    '\''      => true,
                    //双引字符串
                    '"'       => true,
                    //关键反引号
                    '`'       => false,
                    //#单行注释
                    '#'       => false,
                    //-- 单行注释
                    '-- '     => false,
                    //--\t单行注释
                    "--\t"    => false,
                    //  多行注释
                    '/*'      => false,
                    //BEGIN 开始
                    'BEGIN'   => false,
                    //; 语句分隔符
                    $sqlSplit => false
                );

                while (true) {
                    if (!isset($str[$nowOffset])) {
                        //最后读取数据
                        $lastStr = fread($fp, 1024);
                        $str .= $lastStr;
                    }

                    //读取到数据
                    if ($lastStr) {
                        $upper = strtoupper($str);
                        $matchData = of_base_com_str::strArrPos($upper, $tempMatches === null ? $defaultMatches : $tempMatches, $nowOffset);

                        //没查找到数据
                        if ($matchData === false) {
                            //移动偏移量到"最后+1"的字符
                            $nowOffset = strlen($str);
                        //分隔符
                        } else if ($matchData['match'] === $sqlSplit) {
                            //记录文件偏移量
                            $fileOffset += $matchData['position'] + strlen($sqlSplit);
                            //截取完整sql
                            return trim(substr($str, 0, $matchData['position']));
                        //查找到匹配数据
                        } else {
                            //偏移量+1
                            $nowOffset = $matchData['position'] + 1;
                            switch ($matchData['match'][0]) {
                                //关键反引号,当 $tempMatches === null 时,开始寻找下一个结束点,否则正常寻找分隔符
                                case '`':
                                    //匹配下一个字符串
                                    $tempMatches = $tempMatches === null ? array( '`' => false ) : null;
                                    break;
                                //字符串,当 $tempMatches === null 时,开始寻找下一个结束点,否则正常寻找分隔符
                                case '\'':
                                case '"':
                                    //匹配下一个字符串
                                    $tempMatches = $tempMatches === null ?
                                        array( $matchData['match'][0] => true ) : null;
                                    break;
                                //单行注释,当 $tempMatches === null 时,开始寻找下一个换行符,否则正常寻找分隔符
                                case '#':
                                case '-':
                                case "\n":
                                    //匹配下一个字符串
                                    $tempMatches = $tempMatches === null ? array( "\n" => false ) : null;
                                    break;
                                //多行注释,当 $tempMatches === null 时,开始寻找下一个"*/",否则正常寻找分隔符
                                case '/':
                                case '*':
                                    //匹配下一个字符串
                                    $tempMatches = $tempMatches === null ? array( '*/' => false ) : null;
                                    break;
                                //BEGIN 开始存储过程
                                case 'B':
                                //END 结束存储过程
                                case 'E':
                                    $tempMatches = $tempMatches === null ? array('END' => false) : null;
                                    break;
                            }
                        }
                    //无数据或出错,读取到了最后
                    } else if (($temp = strlen($str)) === 0) {
                        self::openFile(false);
                        return null;
                    //无数据或出错,未读到了最后
                    } else {
                        //记录文件偏移量
                        $fileOffset += $temp;
                        return trim($str);
                    }
                }
            }
        //打开新文件流
        } else {
            //重置偏移
            $fileOffset = 0;
            return !!$fp;
        }
    }

    /**
     * 描述 : 获取数据库支持的引擎
     * 参数 :
     *      type : 操作类型,true=检查`__tables`表引擎是否全支持,字符串=检查指定引擎是否支持
     * 返回 :
     *      支持=true,否则=false
     * 作者 : Edgar.lee
     */
    private static function isSupportEngines($type) {
        static $engines = null;

        //初始化支持的引擎列表
        if ($engines === null) {
            $temp = self::sql('SHOW ENGINES');
            foreach ($temp as &$v) {
                //DEFAULT,YES已启用的扩展
                if ($v['Support'] !== 'NO') {
                    $engines[$v['Engine']] = $v['Engine'];
                }
            }
        }

        if ($type === true) {
            //内部前缀
            $prefix = self::$config['prefix'];
            $temp = join('\',\'', $engines);
            $temp = self::sql("SELECT
                GROUP_CONCAT(DISTINCT `{$prefix}TABLES`.`ENGINE` SEPARATOR ',') notEngines    /*不支持的引擎*/
            FROM
                `{$prefix}TABLES`
            WHERE
                `{$prefix}TABLES`.`ENGINE` NOT IN ('{$temp}')");

            $temp[0]['notEngines'] === null || self::message('error', '不支持引擎', __FUNCTION__, $temp[0]['notEngines']);
            return $temp[0]['notEngines'] === null;
        } else {
            return isset($engines[$type]);
        }
    }

    /**
     * 描述 : sql分块合并
     * 参数 :
     *      head  : 追加头
     *     &data  : 指定需要合并的数组
     *      chunk : 分块,大于0时会将data分块,默认0
     * 返回 :
     *      data修改成一个包含合并后字符串的数组
     *      返回一个包含分隔符的字符串
     * 作者 : Edgar.lee
     */
    private static function sqlChunkMerger($head, &$data, $chunk = 0) {
        $sqlSplit = self::$config['sqlSplit'] . "\n";    //分隔符
        $data = $chunk > 0 ? array_chunk($data, $chunk) : array($data);
        foreach ($data as &$v) {
            $v = $head . join(",\n", $v);
        }
        return join($sqlSplit, $data) . $sqlSplit;
    }

    /**
     * 描述 : 获取指定结构数据
     * 参数 :
     *      type  : 查询类型
     *     &where : 限制条件,null=会根据type初始化
     * 返回 :
     *      返回查询的数据
     * 作者 : Edgar.lee
     */
    private static function getDatabaseStructure($type, &$where = null) {
        //数据库
        $database = &self::$config['databaseSlashes'];
        switch ($type) {
            //外键
            case 'FOREIGNKEY':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "SELECT
                    `KEY_COLUMN_USAGE`.CONSTRAINT_NAME,                     /*外键名*/
                    `KEY_COLUMN_USAGE`.TABLE_NAME,                          /*本表名*/
                    `KEY_COLUMN_USAGE`.REFERENCED_TABLE_NAME,               /*外表名*/
                    CONCAT(
                        '`', GROUP_CONCAT(
                            REPLACE(`KEY_COLUMN_USAGE`.COLUMN_NAME, '`', '``') 
                            ORDER BY `KEY_COLUMN_USAGE`.ORDINAL_POSITION
                            SEPARATOR '`,`'
                        ), '`'
                    ) COLUMNS_NAME,                                         /*本表列集*/
                    CONCAT(
                        '`', GROUP_CONCAT(
                            REPLACE(`KEY_COLUMN_USAGE`.REFERENCED_COLUMN_NAME, '`', '``') 
                            ORDER BY `KEY_COLUMN_USAGE`.ORDINAL_POSITION
                            SEPARATOR '`,`'
                        ), '`'
                    ) REFERENCED_COLUMNS_NAME                               /*外表列集*/
                FROM
                    information_schema.`KEY_COLUMN_USAGE`
                WHERE
                    `KEY_COLUMN_USAGE`.CONSTRAINT_SCHEMA = '{$database}'    /*本库约束*/
                AND `KEY_COLUMN_USAGE`.REFERENCED_TABLE_NAME IS NOT NULL
                AND `KEY_COLUMN_USAGE`.TABLE_NAME IN ('{$where}')
                GROUP BY    /*按表名,外键分组*/
                    `KEY_COLUMN_USAGE`.TABLE_NAME,
                    `KEY_COLUMN_USAGE`.CONSTRAINT_NAME";
                break;
            //表信息
            case 'TABLES':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "SELECT
                    `TABLES`.TABLE_NAME,                                    /*表名*/
                    `TABLES`.ENGINE,                                        /*存储引擎*/
                    `TABLES`.ROW_FORMAT,                                    /*行格式*/
                    `TABLES`.TABLE_COLLATION,                               /*排序字符集*/
                    IFNULL(`TABLES`.AUTO_INCREMENT, 0) AUTO_INCREMENT,      /*自增数值*/
                    `TABLES`.CREATE_OPTIONS,                                /*创建表附带参数,如: min_rows=4 max_rows=3 avg_row_length=2 KEY_BLOCK_SIZE=5*/
                    `TABLES`.TABLE_COMMENT                                  /*注释*/
                FROM
                    information_schema.`TABLES`
                WHERE
                    `TABLES`.TABLE_SCHEMA = '{$database}'                   /*库名*/
                AND `TABLES`.TABLE_NAME IN ('{$where}')
                AND `TABLES`.TABLE_TYPE = 'BASE TABLE'                      /*表类型*/";
                break;
            //字段
            case 'COLUMNS':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "SELECT
                    `COLUMNS`.TABLE_NAME,            /*表名*/
                    `COLUMNS`.COLUMN_NAME,           /*字段名*/
                    `COLUMNS`.ORDINAL_POSITION,      /*字段位置*/
                    `COLUMNS`.COLUMN_DEFAULT,        /*默认值,timestamp可以CURRENT_TIMESTAMP*/
                    `COLUMNS`.IS_NULLABLE,           /*允许为空,YES=允许,NO=不运行*/
                    `COLUMNS`.CHARACTER_SET_NAME,    /*字符集*/
                    `COLUMNS`.COLLATION_NAME,        /*排序规则*/
                    `COLUMNS`.COLUMN_TYPE,           /*字段信息*/
                    /*!50700
                    IF(
                        LOCATE(' GENERATED', `COLUMNS`.EXTRA),
                        CONCAT(
                            'GENERATED ALWAYS AS (',
                            `COLUMNS`.`GENERATION_EXPRESSION`,
                            ') ',
                            LEFT(`COLUMNS`.EXTRA, LOCATE(' ', `COLUMNS`.EXTRA) - 1)
                        ),
                    */
                        `COLUMNS`.EXTRA
                    /*!50700
                    )
                    */ EXTRA,                        /*附加信息*/
                    `COLUMNS`.COLUMN_COMMENT         /*注释*/
                FROM
                    `information_schema`.COLUMNS
                WHERE
                    `COLUMNS`.TABLE_SCHEMA='{$database}'
                AND `COLUMNS`.TABLE_NAME IN ('{$where}')";
                break;
            //索引
            case 'STATISTICS':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "SELECT
                    `STATISTICS`.TABLE_NAME,     /*表名*/
                    `STATISTICS`.NON_UNIQUE,     /*是否唯一(0为UNIQUE INDEX 或 PRIMARY KEY,1为INDEX 或 FULLTEXT INDEX)*/
                    `STATISTICS`.INDEX_NAME,     /*索引名(PRIMARY=主键)*/
                    GROUP_CONCAT(
                        CONCAT(
                            '`',
                            REPLACE(`STATISTICS`.COLUMN_NAME, '`', '``'),
                            IF(
                                ISNULL(`STATISTICS`.SUB_PART),
                                '`',
                                CONCAT('`(',`STATISTICS`.SUB_PART,')')
                            )
                        )
                        ORDER BY `STATISTICS`.SEQ_IN_INDEX
                        SEPARATOR ','
                    ) COLUMNS_NAME,              /*字段名*/
                    `STATISTICS`.INDEX_TYPE      /*索引类型*/
                FROM
                    `information_schema`.STATISTICS
                WHERE
                    `STATISTICS`.TABLE_SCHEMA='{$database}'
                AND `STATISTICS`.TABLE_NAME IN ('{$where}')
                GROUP BY
                    `STATISTICS`.TABLE_NAME,
                    `STATISTICS`.INDEX_NAME";
                break;
            //水平分区
            case 'PARTITIONS':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "(SELECT
                    IF(
                        ISNULL(`PARTITIONS`.SUBPARTITION_ORDINAL_POSITION),
                        '',
                        CONCAT(
                            ' SUBPARTITION BY ',
                            `PARTITIONS`.SUBPARTITION_METHOD,
                            '(',
                            `PARTITIONS`.SUBPARTITION_EXPRESSION,
                            ') SUBPARTITIONS ',
                            MAX(`PARTITIONS`.SUBPARTITION_ORDINAL_POSITION)
                        )
                    ) `sub_var`,                                                                /*子分区说明*/
                    IF(
                        ISNULL(`PARTITIONS`.SUBPARTITION_ORDINAL_POSITION),
                        NULL,
                        CONCAT(
                            ' (',
                            GROUP_CONCAT(
                                CONCAT(
                                    'SUBPARTITION `',
                                    REPLACE(`PARTITIONS`.SUBPARTITION_NAME, '`', '``'),
                                    '` COMMENT=\"',
                                    REPLACE(`PARTITIONS`.PARTITION_COMMENT, '\"', '\\\\\"'),
                                    '\"'
                                )
                                ORDER BY `PARTITIONS`.SUBPARTITION_ORDINAL_POSITION
                                SEPARATOR ', '
                            ),
                            ')'
                        )
                    ) `sub_body`,                                                               /*子分区主体*/
                    `PARTITIONS`.TABLE_NAME,
                    `PARTITIONS`.PARTITION_NAME,
                    `PARTITIONS`.PARTITION_ORDINAL_POSITION,
                    `PARTITIONS`.PARTITION_METHOD,
                    `PARTITIONS`.PARTITION_EXPRESSION,
                    `PARTITIONS`.PARTITION_DESCRIPTION,
                    `PARTITIONS`.PARTITION_COMMENT,
                    `PARTITIONS`.SUBPARTITION_EXPRESSION
                FROM
                    `information_schema`.`PARTITIONS`
                WHERE
                    `PARTITIONS`.TABLE_SCHEMA='{$database}'
                AND `PARTITIONS`.TABLE_NAME IN ('{$where}')
                AND `PARTITIONS`.PARTITION_ORDINAL_POSITION IS NOT NULL
                GROUP BY
                    `PARTITIONS`.TABLE_NAME, `PARTITIONS`.PARTITION_NAME) `data`";

                $sql = "SELECT
                    `data`.TABLE_NAME,
                    CONCAT(
                        'PARTITION BY ',
                        `data`.PARTITION_METHOD,
                        '(',
                        `data`.PARTITION_EXPRESSION,
                        ') PARTITIONS ',
                        MAX(`data`.PARTITION_ORDINAL_POSITION),
                        `data`.sub_var,
                        ' (',
                        GROUP_CONCAT(                                                           /*拼出分区主体*/
                            CONCAT(
                                'PARTITION `',
                                REPLACE(`data`.PARTITION_NAME, '`', '``'),
                                '`',
                                IF(
                                    `data`.PARTITION_METHOD = 'RANGE',
                                    CONCAT(' VALUES LESS THAN (', `data`.PARTITION_DESCRIPTION, ')'),
                                    IF(
                                        `data`.PARTITION_METHOD = 'LIST',
                                        CONCAT(' VALUES IN (', `data`.PARTITION_DESCRIPTION, ')'),
                                        ''
                                    )
                                ),
                                IFNULL(
                                    `data`.sub_body,                                            /*子分区*/
                                    CONCAT(
                                        ' COMMENT=\"',
                                        REPLACE(`data`.PARTITION_COMMENT, '\"', '\\\\\"'),
                                        '\"'
                                    )
                                )
                            )
                            ORDER BY `data`.PARTITION_ORDINAL_POSITION
                            SEPARATOR ', '
                        ),
                        ')'
                    ) PARTITION_SQL,
                    CONCAT(
                        `data`.PARTITION_EXPRESSION, ',', 
                        IFNULL(`data`.SUBPARTITION_EXPRESSION, '')
                    ) COLUMNS_COMPARE                                                           /*字段变化证明*/
                FROM
                    {$sql}
                GROUP BY
                    `data`.TABLE_NAME";
                break;
            //触发器
            case 'TRIGGERS':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('table')));
                $sql = "SELECT
                    `TRIGGERS`.TRIGGER_NAME,          /*触发器名*/
                    `TRIGGERS`.EVENT_MANIPULATION,    /*激活事件*/
                    `TRIGGERS`.EVENT_OBJECT_TABLE,    /*激活表*/
                    `TRIGGERS`.ACTION_STATEMENT,      /*触发语句*/
                    `TRIGGERS`.ACTION_TIMING          /*触发位置*/
                FROM
                    information_schema.`TRIGGERS`
                WHERE
                    `TRIGGERS`.TRIGGER_SCHEMA = '{$database}'
                AND `TRIGGERS`.EVENT_OBJECT_TABLE IN ('{$where}')";
                break;
            //视图
            case 'VIEWS':
                $where === null && $where = join('\',\'', array_map('addslashes', self::getMatches('view')));
                $sql = "SELECT
                    `VIEWS`.TABLE_NAME,         /*视图名*/
                    /*`VIEWS`.VIEW_DEFINITION,    查询语句,会加上`库名`,使用 SHOW CREATE VIEW 更好*/
                    `VIEWS`.CHECK_OPTION,       /*检查选项*/
                    `VIEWS`.IS_UPDATABLE,       /*是否更新,YES为ALGORITHM=MERGE,NO为ALGORITHM=TEMPTABLE */
                    `VIEWS`.SECURITY_TYPE       /*安全性*/
                FROM
                    `information_schema`.VIEWS
                WHERE
                    `VIEWS`.TABLE_SCHEMA = '{$database}'
                AND `VIEWS`.TABLE_NAME IN ('{$where}')";
                break;
            //存储程序
            case 'ROUTINES':
                $where === null && $where = array(
                    'function'  => join('\',\'', array_map('addslashes', self::getMatches('function'))),
                    'procedure' => join('\',\'', array_map('addslashes', self::getMatches('procedure')))
                );
                $sql = "SELECT
                    `ROUTINES`.ROUTINE_NAME,          /*存储程序名*/
                    `ROUTINES`.ROUTINE_TYPE,          /*类型,FUNCTION=函数,PROCEDURE=过程*/
                    `ROUTINES`.DTD_IDENTIFIER,        /*返回类型*/
                    `ROUTINES`.ROUTINE_DEFINITION,    /*结构体*/
                    `ROUTINES`.IS_DETERMINISTIC,      /*是否附加DETERMINISTIC,YES=是,NO=非*/
                    `ROUTINES`.SQL_DATA_ACCESS,       /*数据访问*/
                    `ROUTINES`.SECURITY_TYPE,         /*安全性*/
                    `ROUTINES`.ROUTINE_COMMENT        /*注释*/
                FROM
                    `information_schema`.ROUTINES
                WHERE
                    `ROUTINES`.ROUTINE_SCHEMA = '{$database}'
                AND ((
                        `ROUTINES`.ROUTINE_TYPE = 'FUNCTION'
                    AND `ROUTINES`.ROUTINE_NAME IN ('{$where['function']}')
                ) OR (
                        `ROUTINES`.ROUTINE_TYPE = 'PROCEDURE'
                    AND `ROUTINES`.ROUTINE_NAME IN ('{$where['procedure']}')
                ))";
                break;
        }

        return self::sql($sql);
    }
}

/* //演示调用
if( !of_base_tool_mysqlSync::init(array(
    'callAdjustSql' => 'callAdjustSql',
    'callDb'        => array(
        'server'   => 'localhost:3306',
        'username' => 'root',
        'password' => 'admin'
    ),
    'callMsg'       => 'callBackMsg',
    'database'      => 'ots',
    'sqlSplit'      => ';',
    'sqlMark'       => true
)) ) {
    exit;
}
function callAdjustSql(&$sql) {
    //$sql .= '--';
}
function callBackMsg($a) {
    echo join(' : ', $a), "<br>\n";
}
// */
/* //备份结构
of_base_tool_mysqlSync::backupBase('E:/work/product/cs/structure.sql');
// */
/* //备份数据
of_base_tool_mysqlSync::backupData('E:/work/product/cs/backup.sql', array('type' => 'insert'));
// */
/* //修正数据
of_base_tool_mysqlSync::backupData('E:/work/product/cs/backup.sql', array('type' => array('t_adm`in' => array('user`name'))));
// */
/* //更新结构
of_base_tool_mysqlSync::revertBase('E:/work/product/cs/structure.sql');
// */
/* //更新数据
of_base_tool_mysqlSync::revertData('E:/work/product/cs/backup.sql', array(
    'showProgress' => true
));
// */
/* //提取一条sql语句
if( of_base_tool_mysqlSync::fetchFileSql('E:/work/product/cs/structure.sql') === true )
{
    while( ($temp = of_base_tool_mysqlSync::fetchFileSql()) !== null ) {
        echo $temp, "<br>\n";
    }
    echo '输出结束';
}
// */
/* //sql执行演示
print_r(of_base_tool_mysqlSync::sql('select 2 aa'));
// */