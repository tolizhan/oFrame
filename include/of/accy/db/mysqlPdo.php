<?php
class of_accy_db_mysqlPdo extends of_db {
    //连接源
    private $connection = null;
    //事务状态(true=已开启, false=未开启)
    private $transState = false;
    //当前结果集
    private $query = null;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;

        try {
            $this->connection = new PDO(
                "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']}",
                $params['user'],
                $params['password'],
                //长连接
                array(PDO::ATTR_PERSISTENT => !!$params['persistent'])
            );

            if ($this->_linkIdentifier(false)) {
                //设置字体, GROUP_CONCAT最大长度
                $temp = "SET NAMES '{$params['charset']}', GROUP_CONCAT_MAX_LEN = 4294967295";
                //设置严格模式
                OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
                //设置时区
                $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
                $this->connection->query($temp);
                return true;
            } else {
                $this->connection = null;
                return false;
            }
        } catch (Exception $e) {
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
    protected function _error(&$node) {
        //事务回滚动作
        static $rollback = null;
        $error = $this->connection->errorInfo() + array(2 => '');

        //INNODB可能死锁
        if ($error[1] === 1205 || $error[1] === 1213) {
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
            ($rollback['outBack'] || $error[1] === 1213) &&
            //重新开始事务
            $this->_begin();
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
        $this->_linkIdentifier();
        $this->transState = $this->connection->beginTransaction();
        return $this->transState;
    }

    /**
     * 描述 : 提交事务
     * 作者 : Edgar.lee
     */
    protected function _commit() {
        $this->transState = false;
        return $this->connection->commit();
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->transState = false;
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
            //事务状态下不重新检查
            $this->transState ||
            ($temp = @$this->connection->getAttribute(PDO::ATTR_SERVER_INFO)) &&
            $temp !== 'MySQL server has gone away' ||
            $restLink && $this->_connect()
        ) {
            return true;
        } else {
            return false;
        }
    }
}