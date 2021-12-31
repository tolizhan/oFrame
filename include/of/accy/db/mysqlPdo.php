<?php
class of_accy_db_mysqlPdo extends of_db {
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

        try {
            $this->connection = new PDO(
                "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']}",
                $params['user'],
                $params['password'],
                array(
                    //长连接
                    PDO::ATTR_PERSISTENT => !!$params['persistent'],
                    //关闭错误
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
                )
            );

            if ($this->_ping(false)) {
                //设置字体, GROUP_CONCAT最大长度
                $temp = "SET NAMES '{$params['charset']}', GROUP_CONCAT_MAX_LEN = 4294967295";
                //设置严格模式
                OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
                //设置时区
                $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
                $this->connection->query($temp);
                //设置事务隔离级别
                empty($params['isolation']) || $this->connection->query(
                    'SET SESSION TRANSACTION ISOLATION LEVEL ' . $params['isolation']
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
    protected function _error() {
        //事务回滚动作
        static $rollback = null;
        $error = $this->connection->errorInfo() + array(2 => '');

        //INNODB可能锁超时(1205) || 死锁(1213)
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
                //事务进程权限
                $rollback['lockLog'] = $this->_fetch();

                //事务回滚模式
                $rollback['enable'] = version_compare($this->dbVar['version'], '5.7', '>=');
                $rollback['outBack'] = $this->dbVar['outBack'] === '1';
            }

            //读取锁日志
            if ($rollback['lockLog']) {
                //死锁日志
                if ($error[1] === 1213) {
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
            ($rollback['outBack'] || $error[1] === 1213) &&
            //重新开始事务
            $this->_begin();
        }

        return array('code' => $error[1], 'info' => $error[2], 'note' => &$note);
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
        $this->_ping();

        //因自带方法 beginTransaction 无法二次打开, 故 mysql >= 5.7 在死锁回滚时无法重开事务
        if ($this->transState = !!$this->connection->query('START TRANSACTION')) {
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
        return !!$this->connection->query('COMMIT');
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->sqlList = null;
        $this->transState = false;
        return !!$this->connection->query('ROLLBACK');
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

        if ($this->_ping()) {
            //记录加锁SQL
            of_accy_db_mysqli::setNote($this, $sql);

            return !!$this->query = $this->connection->query($sql, PDO::FETCH_ASSOC);
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