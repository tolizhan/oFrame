<?php
class of_accy_db_pdoMysql extends of_db {
    //连接源
    private $connection = null;
    //当前结果集
    private $query = null;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;

        try {
            $connection = new PDO(
                "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']}",
                $params['user'],
                $params['password'],
                //长连接
                array(PDO::ATTR_PERSISTENT => !!$params['persistent'])
            );

            if (!$connection || $connection->getAttribute(PDO::ATTR_SERVER_INFO) === 'MySQL server has gone away') {
                return false;
            } else {
                $this->connection = $connection;
                //设置字体
                $temp = "SET NAMES '{$params['charset']}'";
                //设置严格模式
                OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
                //设置时区
                $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
                $connection->query($temp);
                return true;
            }
        } catch (PDOException $e) {
            trigger_error($e->getMessage());
            return false;
        }
    }

    /**
     * 描述 : 关闭连接源
     * 作者 : Edgar.lee
     */
    protected function _close() {
        return is_resource($this->connection) && !($this->connection = null);
    }

    /**
     * 描述 : 读取当前错误
     * 作者 : Edgar.lee
     */
    protected function _error() {
        //进程访问权限
        static $process = null;
        $error = $this->connection->errorInfo() + array(2 => '');

        //INNODB可能死锁
        if ($error[1] === 1205 || $error[1] === 1213) {
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
                $error[2] .= $temp['Status'];
            }
        }

        return $error[1] . ':' . $error[2];
    }

    /**
     * 描述 : 查看影响行数
     * 作者 : Edgar.lee
     */
    protected function _affectedRows() {
        return $this->query->rowCount();
    }

    /**
     * 描述 : 获取最后插入ID
     * 作者 : Edgar.lee
     */
    protected function _lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * 描述 : 开启事务
     * 作者 : Edgar.lee
     */
    protected function _begin() {
        return $this->connection->beginTransaction();
    }

    /**
     * 描述 : 提交事务
     * 作者 : Edgar.lee
     */
    protected function _commit() {
        return $this->connection->commit();
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        return $this->connection->rollBack();
    }

    /**
     * 描述 : 读取一行数据
     * 作者 : Edgar.lee
     */
    protected function &_fetch() {
        ($result = $this->query->fetch()) || $result = array();
        return $result;
    }

    /**
     * 描述 : 读取全部数据
     * 作者 : Edgar.lee
     */
    protected function &_fetchAll() {
        $result = $this->query->fetchAll();
        return $result;
    }

    /**
     * 描述 : 获取多个结果集
     * 作者 : Edgar.lee
     */
    protected function &_moreResults() {
        $result = array();

        do {
            $result[] = &$this->_fetchAll();
        } while ($this->query->nextRowset());

        return $result;
    }

    /**
     * 描述 : 执行sql语句
     * 作者 : Edgar.lee
     */
    protected function _query(&$sql) {
        $this->query = false;
        if ($this->_linkIdentifier() && $this->query = $this->connection->query($sql, PDO::FETCH_ASSOC)) {
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
            $this->connection->getAttribute(PDO::ATTR_SERVER_INFO) !== 'MySQL server has gone away' ||
            ($restLink && $this->_connect())
        ) {
            return true;
        } else {
            return false;
        }
    }
}