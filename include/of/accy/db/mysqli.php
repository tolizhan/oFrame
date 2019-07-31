<?php
class of_accy_db_mysqli extends of_db {
    //连接源
    private $connection = null;
    //事务状态(true=已开启, false=未开启)
    private $transState = false;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;

        $connection = mysqli_connect(
            ($params['persistent'] ? 'p:' : '') . $params['host'],
            $params['user'],
            $params['password'],
            $params['database'],
            $params['port']
        );

        if (mysqli_ping($connection)) {
            $this->connection = $connection;
            //设置字体, GROUP_CONCAT最大长度
            $temp = "SET NAMES '{$params['charset']}', GROUP_CONCAT_MAX_LEN = 4294967295";
            //设置严格模式
            OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
            //设置时区
            $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
            mysqli_query($this->connection, $temp);
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
        return is_resource($this->connection) && mysqli_close($this->connection);
    }

    /**
     * 描述 : 读取当前错误
     * 作者 : Edgar.lee
     */
    protected function _error(&$node) {
        //事务回滚动作
        static $rollback = null;
        $errno = mysqli_errno($this->connection);
        $error = mysqli_error($this->connection);

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
                $rollback['lockLog'] = $this->_fetch();

                //事务回滚模式
                $temp = 'SELECT 
                    @@innodb_rollback_on_timeout outBack,
                    VERSION() version';
                $this->_query($temp);
                $temp = $this->_fetch();
                $rollback['enable'] = version_compare($temp['version'], '5.7', '>=');
                $rollback['outBack'] = $temp['outBack'] === '1';
            }

            //读取死锁日志
            if ($rollback['lockLog']) {
                $temp = 'SHOW ENGINE INNODB STATUS';
                $this->_query($temp);
                $temp = &$this->_fetch();
                //记录死锁日志
                $node = $temp['Status'];
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

        return $errno . ':' . $error;
    }

    /**
     * 描述 : 查看影响行数
     * 作者 : Edgar.lee
     */
    protected function _affectedRows() {
        return mysqli_affected_rows($this->connection);
    }

    /**
     * 描述 : 获取最后插入ID
     * 作者 : Edgar.lee
     */
    protected function _lastInsertId() {
        return mysqli_insert_id($this->connection);
    }

    /**
     * 描述 : 开启事务
     * 作者 : Edgar.lee
     */
    protected function _begin() {
        $this->_linkIdentifier();
        $this->transState = mysqli_query($this->connection, 'START TRANSACTION');
        return $this->transState;
    }

    /**
     * 描述 : 提交事务
     * 作者 : Edgar.lee
     */
    protected function _commit() {
        $this->transState = false;
        return mysqli_query($this->connection, 'COMMIT');
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->transState = false;
        return mysqli_query($this->connection, 'ROLLBACK');
    }

    /**
     * 描述 : 读取一行数据
     * 作者 : Edgar.lee
     */
    protected function &_fetch() {
        $query = mysqli_store_result($this->connection);
        ($result = mysqli_fetch_assoc($query)) || $result = array();
        mysqli_free_result($query);

        return $result;
    }

    /**
     * 描述 : 读取全部数据
     * 作者 : Edgar.lee
     */
    protected function &_fetchAll() {
        $result = array();

        if ($query = mysqli_store_result($this->connection)) {
            while ($row = mysqli_fetch_assoc($query)) {
                $result[] = $row;
            }
            mysqli_free_result($query);
        }

        return $result;
    }

    /**
     * 描述 : 获取多个结果集
     * 作者 : Edgar.lee
     */
    protected function &_moreResults() {
        do {
            $result[] = &$this->_fetchAll();
        } while (
            mysqli_more_results($this->connection) &&
            mysqli_next_result($this->connection)
        );

        return $result;
    }

    /**
     * 描述 : 执行sql语句
     * 作者 : Edgar.lee
     */
    protected function _query(&$sql) {
        if ($this->_linkIdentifier() && mysqli_multi_query($this->connection, $sql)) {
            return true;
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
    private function _linkIdentifier($restLink = true) {
        if (
            //事务状态下不重新检查
            $this->transState ||
            @mysqli_ping($this->connection) ||
            ($restLink && $this->_connect())
        ) {
            return true;
        } else {
            return false;
        }
    }
}