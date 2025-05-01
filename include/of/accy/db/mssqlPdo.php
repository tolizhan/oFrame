<?php
class of_accy_db_mssqlPdo extends of_db {
    //连接源
    private $connection = null;
    //当前结果集
    private $query = null;
    //事务状态(true=已开启, false=未开启)
    public $transState = false;

    /**
     * 描述 : 连接到数据库
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;

        try {
            $this->connection = new PDO(
                "sqlsrv:Server={$params['host']},{$params['port']};Database={$params['database']}",
                $params['user'],
                $params['password'],
                array(
                    //长连接
                    PDO::ATTR_PERSISTENT => !!$params['persistent'],
                    //关闭错误
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
                )
            );

            //设置事务隔离级别
            empty($params['isolation']) || $this->connection->query(
                'SET TRANSACTION ISOLATION LEVEL ' . $params['isolation']
            );
            return true;
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
        return !$this->connection = null;
    }

    /**
     * 描述 : 读取当前错误
     * 作者 : Edgar.lee
     */
    protected function _error() {
        //事务回滚动作
        static $rollback = null;
        $error = $this->connection->errorInfo() + array(2 => '');

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
        $this->_ping(true);

        //开启事务
        if ($this->transState = !!$this->connection->beginTransaction()) {
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
        $this->transState = false;
        return !!$this->connection->commit();
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->transState = false;
        return !!$this->connection->rollBack();
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

        if ($this->_ping(true)) {
            return !!$this->query = $this->connection->query($sql, PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    }

    /**
     * 描述 : 检测连接有效性
     * 参数 :
     *      mode : false=判断并延长时效, true=非事务尝试重连
     * 返回 :
     *      true=连接, false=断开
     * 作者 : Edgar.lee
     */
    protected function _ping($mode) {
        //事务状态下不重新检查
        if ($mode) {
            return $this->transState || @$this->connection->query('SELECT 1') || $this->_connect();
        //判断连接并延长连接时效
        } else {
            return !!@$this->connection->query('SELECT 1');
        }
    }
}