<?php
class of_accy_db_mysqli extends of_db {

    //连接源
    private $connection = null;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;
        $func = $params['persistent'] ? 'mysqli_pconnect' : 'mysqli_connect';

        $connection = $func(
            $params['host'],
            $params['user'],
            $params['password'],
            $params['database'],
            $params['port']
        );

        if (mysqli_ping($connection)) {
            $this->connection = $connection;
            //设置字体
            $temp = "SET NAMES '{$params['charset']}'";
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
    protected function _error() {
        //进程访问权限
        static $process = null;
        $errno = mysqli_errno($this->connection);
        $error = mysqli_error($this->connection);

        //INNODB可能死锁
        if ($errno === 1205 || $errno === 1213) {
            //判断进程权限
            if ($process === null) {
                $process = 'SELECT
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
                $this->_query($process);
                $process = $this->_fetch();
            }

            if ($process) {
                $temp = 'SHOW ENGINE INNODB STATUS';
                $this->_query($temp);
                $temp = &$this->_fetch();
                //死锁日志
                $error .= $temp['Status'];
            }
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
        return mysqli_query($this->connection, 'START TRANSACTION');
    }

    /**
     * 描述 : 提交事务
     * 作者 : Edgar.lee
     */
    protected function _commit() {
        return mysqli_query($this->connection, 'COMMIT');
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
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
        $result = array();

        if (mysqli_more_results($this->connection)) {
            $result[] = &$this->_fetchAll();
            while (mysqli_next_result($this->connection)) {
                $result[] = &$this->_fetchAll();
                if (!mysqli_more_results($this->connection)) {
                    break;
                }
            }
        }

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
            mysqli_ping($this->connection) ||
            ($restLink && $this->_connect())
        ) {
            return true;
        } else {
            return false;
        }
    }
}