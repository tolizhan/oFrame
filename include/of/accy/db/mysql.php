<?php
class of_accy_db_mysql extends of_db {
    //连接源
    private $connection = null;
    //当前结果集
    private $query = null;
    //事务状态(true=已开启, false=未开启)
    public $transState = false;
    //数据库属性{"outBack" : 超时回滚, "timeout" : 加锁超时, "linkCid" : 连接ID, "version" : 版本号, "onTrace" : 开始跟踪}
    public $dbVar = null;
    //超时SQL记录列表
    public $sqlList = null;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;
        $func = $params['persistent'] ? 'mysql_pconnect' : 'mysql_connect';

        $connection = $func(
            $params['host'] . ':' . $params['port'],
            $params['user'],
            $params['password']
        );

        if (mysql_ping($connection) && mysql_select_db($params['database'], $connection)) {
            $this->connection = $connection;
            //设置字体, GROUP_CONCAT最大长度
            $temp = "SET NAMES '{$params['charset']}', GROUP_CONCAT_MAX_LEN = 4294967295";
            //设置严格模式
            OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
            //设置时区
            $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
            mysql_query($temp, $this->connection);
            //设置事务隔离级别
            empty($params['isolation']) || mysql_query(
                'SET SESSION TRANSACTION ISOLATION LEVEL ' . $params['isolation'], $this->connection
            );
            //是否开启锁超时日志
            ($index = &$params['errorTrace']) || $index = array();
            $index = (array)$index + array(0, '@.@');
            //事务回滚模式
            $temp = 'SELECT 
                @@innodb_rollback_on_timeout outBack,
                @@innodb_lock_wait_timeout timeout,
                CONNECTION_ID() linkCid,
                VERSION() version';
            $this->_query($temp);
            $this->dbVar = $this->_fetch();
            //连接标识
            $this->dbVar['linkMark'] = "{$this->dbVar['linkCid']}@{$params['host']}:{$params['port']}";
            return true;
        } else {
            return false;
        }
    }

    /**
     * 描述 : 关闭连接源
     * 作者 : Edgar.lee
     */
    protected function _close() {
        return is_resource($this->connection) && mysql_close($this->connection);
    }

    /**
     * 描述 : 读取当前错误
     * 作者 : Edgar.lee
     */
    protected function _error() {
        //事务回滚动作
        static $rollback = null;
        $errno = mysql_errno();
        $error = mysql_error();

        //INNODB可能锁超时(1205) || 死锁(1213)
        if ($errno === 1205 || $errno === 1213) {
            //初始化回滚属性
            if ($rollback === null) {
                //判断进程权限
                $temp = 'SELECT
                    1 c
                FROM
                    information_schema.`USER_PRIVILEGES`
                WHERE
                    `USER_PRIVILEGES`.GRANTEE = CONCAT(    /*用户名*/
                        "\'",
                        LEFT(
                            CURRENT_USER,
                            LENGTH(CURRENT_USER) - LENGTH(SUBSTRING_INDEX(CURRENT_USER, "@", -1)) - 1
                        ),
                        "\'@\'",
                        SUBSTRING_INDEX(CURRENT_USER, "@", -1),
                        "\'"
                    )
                AND `USER_PRIVILEGES`.PRIVILEGE_TYPE = "PROCESS"';
                $this->_query($temp);
                //事务进程权限
                $rollback['lockLog'] = $this->_fetch();

                //事务回滚模式
                $rollback['enable'] = version_compare($this->dbVar['version'], '5.7', '>=');
                $rollback['outBack'] = $this->dbVar['outBack'] === '1';
            }

            //读取锁日志
            if ($rollback['lockLog']) {
                //死锁日志
                if ($errno === 1213) {
                    $temp = 'SHOW ENGINE INNODB STATUS';
                    $this->_query($temp);
                    $temp = &$this->_fetch();
                    ($note = &$temp['Status']) && of_accy_db_mysqli::getNote($this, $note);
                //超时日志
                } else {
                    of_accy_db_mysqli::getNote($this, $note);
                }
            }

            //mysql版本>=5.7回滚事务后需手动重启事务
            $rollback['enable'] &&
            //处于开启事务中
            $this->transState &&
            //(超时回滚事务 || 死锁回滚事务)
            ($rollback['outBack'] || $errno === 1213) &&
            //重新开始事务
            $this->_begin();
        }

        return array('code' => $errno, 'info' => $error, 'note' => &$note);
    }

    /**
     * 描述 : 查看影响行数
     * 作者 : Edgar.lee
     */
    protected function _affectedRows() {
        return mysql_affected_rows($this->connection);
    }

    /**
     * 描述 : 获取最后插入ID
     * 作者 : Edgar.lee
     */
    protected function _lastInsertId() {
        return mysql_insert_id($this->connection);
    }

    /**
     * 描述 : 开启事务
     * 作者 : Edgar.lee
     */
    protected function _begin() {
        $this->_ping();

        if ($this->transState = mysql_query('START TRANSACTION', $this->connection)) {
            //记录逻辑回溯
            of_accy_db_mysqli::setNote($this);

            return true;
        } else {
            throw new Exception('Failed to open transaction.');
        }
    }

    /**
     * 描述 : 提交事务
     * 作者 : Edgar.lee
     */
    protected function _commit() {
        $this->sqlList = null;
        $this->transState = false;
        return mysql_query('COMMIT', $this->connection);
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->sqlList = null;
        $this->transState = false;
        return mysql_query('ROLLBACK', $this->connection);
    }

    /**
     * 描述 : 读取一行数据
     * 作者 : Edgar.lee
     */
    protected function &_fetch() {
        ($result = mysql_fetch_assoc($this->query[0])) || $result = array();
        mysql_free_result($this->query[0]);

        return $result;
    }

    /**
     * 描述 : 读取全部数据
     * 作者 : Edgar.lee
     */
    protected function &_fetchAll($pos = 0) {
        $result = array();

        while ($row = mysql_fetch_assoc($this->query[$pos])) {
            $result[] = $row;
        }
        mysql_free_result($this->query[$pos]);

        return $result;
    }

    /**
     * 描述 : 获取多个结果集, mysql的方式只能获取一个结果集
     * 作者 : Edgar.lee
     */
    protected function &_moreResults() {
        $result = array();

        foreach ($this->query as $k => &$v) {
            if (is_resource($v)) {
                $result[] = &$this->_fetchAll($k);
            } else {
                $result[] = array();
            }
        }

        return $result;
    }

    /**
     * 描述 : 执行sql语句
     * 作者 : Edgar.lee
     */
    protected function _query(&$sql) {
        $this->query = false;

        //可能为多段 SQL, 需要拆分
        if (strpos($sql, ';')) {
            $fSql = rtrim($sql, "; \t\n\r\0\x0B") . ';';
            $offset = $pSqlPos = 0;
            $dMatch = array(
                ';'  => false,
                '/*' => false,
                '\'' => false,
                '"'  => false,
                '('  => false,
            );
            $stacks[] = $dMatch;

            while ($nMatch = of_base_com_str::strArrPos($fSql, end($stacks), $offset)) {
                switch ($nMatch['match']) {
                    //分隔符
                    case ';':
                        //提取一段SQL
                        $sqlList[] = substr(
                            $fSql, $pSqlPos, 
                            $nMatch['position'] - $pSqlPos
                        );
                        $pSqlPos = $nMatch['position'] + 1;
                        break;
                    //注释
                    case '/*':
                        $stacks[] = array('*/' => false);
                        break;
                    //左括号
                    case  '(':
                        $stacks[] = array(')' => false);
                        break;
                    //引号
                    case  '"':
                    case '\'':
                        //已开启引号
                        if (isset($attr['quote'])) {
                            array_pop($stacks);
                            unset($attr['quote']);
                        //需要开启引号
                        } else {
                            $stacks[] = array($nMatch['match'] => true);
                            $attr['quote'] = true;
                        }
                        break;
                    //关闭符
                    case  ')':
                    case '*/':
                        array_pop($stacks);
                        break;
                }

                $offset = $nMatch['position'] + 1;
            }
        //单条 SQL
        } else {
            $sqlList[] = $sql;
        }

        if ($this->_ping()) {
            //记录加锁SQL
            of_accy_db_mysqli::setNote($this, $sql);

            foreach ($sqlList as &$v) {
                //执行成功
                if ($temp = mysql_query($v, $this->connection)) {
                    //记录连接源
                    $this->query[] = $temp;
                //执行失败
                } else {
                    //后续流程停止执行
                    break ;
                }
            }

            return !!$this->query;
        } else {
            return false;
        }
    }

    /**
     * 描述 : 检测连接有效性
     * 参数 :
     *      restLink : 是否重新连接,true(默认)=是,false=否
     * 作者 : Edgar.lee
     */
    protected function _ping($restLink = true) {
        if (
            //事务状态下不重新检查
            $this->transState ||
            @mysql_ping($this->connection) ||
            ($restLink && $this->_connect())
        ) {
            return true;
        } else {
            return false;
        }
    }
}