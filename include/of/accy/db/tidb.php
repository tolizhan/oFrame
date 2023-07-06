<?php
/**
 * 描述 : tidb连接
 * 注明 :
 *      特别注意 :
 *          不支持共享锁 LOCK IN SHARE MODE
 *          自增ID不能用作先后排序
 *          REPEATABLE READ 隔离级别没有GAP锁
 *          PARTITION 不支持 KEY 方式(tidb < 7.0)
 * 作者 : Edgar.lee
 */
class of_accy_db_tidb extends of_db {
    //连接源
    private $connection = null;
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
            //设置严格模式, SQL_MODE = REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', '');
            OF_DEBUG === false || $temp .= ', SQL_MODE = "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION"';
            //设置时区
            $params['timezone'] && $temp .= ", TIME_ZONE = '{$params['timezone']}'";
            mysqli_query($this->connection, $temp);
            //设置事务隔离级别
            empty($params['isolation']) || mysqli_query(
                $this->connection, 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $params['isolation']
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
        return is_resource($this->connection) && mysqli_close($this->connection);
    }

    /**
     * 描述 : 读取当前错误
     * 作者 : Edgar.lee
     */
    protected function _error() {
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
                    $temp = "SELECT
                        `list`.TRY_LOCK_TRX_ID, `list`.TRX_HOLDING_LOCK,
                        `list`.CURRENT_SQL_DIGEST_TEXT, `list`.KEY_INFO,
                        IFNULL(`lock`.SESSION_ID, '{$this->dbVar['linkCid']}') SESSION_ID
                    FROM
                        INFORMATION_SCHEMA.DEADLOCKS AS `main`
                            LEFT JOIN INFORMATION_SCHEMA.DEADLOCKS AS `list` ON
                                `list`.DEADLOCK_ID = `main`.DEADLOCK_ID
                            LEFT JOIN `INFORMATION_SCHEMA`.CLUSTER_TIDB_TRX AS `lock` ON
                                `lock`.`ID` = `list`.TRY_LOCK_TRX_ID
                    WHERE
                        `main`.TRY_LOCK_TRX_ID = '{$this->dbVar['linkTid']}'";
                    $this->_query($temp);
                    $note = $this->_fetchAll();
                }
                //$note === null ? 锁超时 : 死锁
                of_accy_db_tidb::getNote($this, $note);
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
        $this->_ping(true);

        if ($this->transState = mysqli_query($this->connection, 'START TRANSACTION')) {
            //记录逻辑回溯
            of_accy_db_tidb::setNote($this, 'tidb');

            //读取事务ID
            $temp = 'SELECT
                `ID`
            FROM
                `INFORMATION_SCHEMA`.`TIDB_TRX`
            WHERE
                `SESSION_ID` = ' . $this->dbVar['linkCid'];
            $this->_query($temp);
            $temp = $this->_fetch();
            //存储事务ID
            $this->dbVar['linkTid'] = $temp['ID'];

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
        return mysqli_query($this->connection, 'COMMIT');
    }

    /**
     * 描述 : 事务回滚
     * 作者 : Edgar.lee
     */
    protected function _rollBack() {
        $this->sqlList = null;
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
        if ($this->_ping(true)) {
            //记录加锁SQL
            of_accy_db_tidb::setNote($this, 'tidb', $sql);

            return mysqli_multi_query($this->connection, $sql);
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
            return $this->transState || @mysqli_ping($this->connection) || $this->_connect();
        //判断连接并延长连接时效
        } else {
            return !!@mysqli_query($this->connection, 'SELECT 1');
        }
    }

    /**
     * 描述 : 设置SQL锁备注信息
     * 参数 :
     *      obj : 连接对象
     *      dba : 数据库适配名
     *     &sql : null=记录逻辑回溯, str=记录加锁SQL
     * 作者 : Edgar.lee
     */
    public static function setNote($obj, $dba, &$sql = null) {
        //开启锁超时日志
        if ($obj->params['errorTrace'][0]) {
            try {
                //引用跟踪配置
                $trace = &$obj->params['errorTrace'];

                //记录逻辑回溯
                if ($sql === null) {
                    //重置记录跟踪(true=匹配到$trace[0]的sql, 开始记录以后sql)
                    $obj->dbVar['onTrace'] = $obj->sqlList = null;
                    //清除SQL缓存
                    $temp = 'of_accy_db_tidb::sqls-' . $obj->dbVar['linkMark'];
                    of_base_com_kv::del($temp, '_ofSelf');
                    //记录事务追踪
                    $temp = 'of_accy_db_tidb::trace-' . $obj->dbVar['linkMark'];
                    of_base_com_kv::set($temp, array_slice(debug_backtrace(), 2), 3600, '_ofSelf');
                    //开启超时监听
                    $pMd5 = md5("{$obj->params['user']}@{$obj->params['host']}:{$obj->params['port']}");
                    of_base_com_kv::set('of_accy_db_tidb::pool-' . $pMd5, $obj->params, 3600, '_ofSelf');
                    of_base_com_timer::task(array(
                        'call' => array(
                            'asCall' => 'of_accy_db_tidb::listenLockTimeout',
                            'params' => array($pMd5, $dba)
                        ),
                        'cNum' => 1
                    ));
                //记录加锁SQL
                } else if (
                    //事务已开启
                    $obj->transState &&
                    //开始锁超时记录
                    $trace[0] > 0 &&
                    //记录SQL列表未满
                    !isset($obj->sqlList[$trace[0]]) &&
                    //已开启记录 || 匹配跟踪SQL语句
                    ($obj->dbVar['onTrace'] || preg_match($trace[1], $sql)) &&
                    //匹配加锁SQL语句
                    preg_match('@(^|\s)(?:INSERT|UPDATE|DELETE|REPLACE|LOCK|ALTER)(\s|$)@i', $sql)
                ) {
                    //开启记录跟踪
                    $obj->dbVar['onTrace'] = true;
                    //保存到加锁SQL列表
                    $obj->sqlList[] = date('H:i:s > ', time()) . $sql;
                    //写入k-v缓存
                    $temp = 'of_accy_db_tidb::sqls-' . $obj->dbVar['linkMark'];
                    of_base_com_kv::set($temp, $obj->sqlList, 3600, '_ofSelf');
                }
            } catch (Exception $e) {
            }
        }
    }

    /**
     * 描述 : 读取SQL锁备注信息
     * 参数 :
     *      obj : 连接对象
     *     &note : null=锁超时日志, str=死锁日志
     * 作者 : Edgar.lee
     */
    public static function getNote($obj, &$note) {
        //开启锁超时日志
        if ($obj->params['errorTrace']) {
            try {
                //锁超时日志
                if ($note === null) {
                    $temp = array(
                        'host' => "@{$obj->params['host']}:{$obj->params['port']}",
                        'key'  => 'of_accy_db_tidb::waits-' . $obj->dbVar['linkMark']
                    );

                    //阻塞列表读取成功
                    if ($temp['wait'] = of_base_com_kv::get($temp['key'], null, '_ofSelf')) {
                        //记录超时客户端ID 与 阻塞信息
                        $note = array(
                            'requestId' => $obj->dbVar['linkCid'],
                            'lockInfo' => &$temp['wait']['bInfo']
                        );

                        //生成超时追踪信息
                        foreach ($temp['wait']['bCids'] as &$v) {
                            //读取阻塞SQL
                            $temp['key'] = 'of_accy_db_tidb::sqls-' . $v . $temp['host'];
                            $note['lockSqls'][$v] = of_base_com_kv::get($temp['key'], null, '_ofSelf');
                            //读取阻塞追踪
                            $temp['key'] = 'of_accy_db_tidb::trace-' . $v . $temp['host'];
                            $note['lockTrace'][$v] = of_base_com_kv::get($temp['key'], null, '_ofSelf');
                        }
                    }
                //死锁日志
                } else {
                    $note = array(
                        'requestId' => $obj->dbVar['linkCid'],
                        'lockSqls'  => array(),
                        'lockTrace' => array(),
                        'lockLogs'  => $note
                    );

                    //匹配死锁连接ID
                    $temp = array(
                        'host' => "@{$obj->params['host']}:{$obj->params['port']}"
                    );

                    //记录死锁跟踪日志
                    foreach ($note['lockLogs'] as &$v) {
                        //引用会话ID
                        $index = &$v['SESSION_ID'];

                        //自身连接ID
                        if ($index === $note['requestId']) {
                            $note['lockSqls'][$index] = $obj->sqlList;
                        //死锁连接ID
                        } else {
                            //读取阻塞SQL
                            $temp['key'] = 'of_accy_db_tidb::sqls-' . $index . $temp['host'];
                            $note['lockSqls'][$index] = of_base_com_kv::get($temp['key'], null, '_ofSelf');
                            //读取阻塞追踪
                            $temp['key'] = 'of_accy_db_tidb::trace-' . $index . $temp['host'];
                            $note['lockTrace'][$index] = of_base_com_kv::get($temp['key'], null, '_ofSelf');
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }
    }


    /**
     * 描述 : 记录MySql锁超时阻塞列表
     * 参数 :
     *      pool : 数据库连接参数
     *      name : 数据库连接对象
     * 注明 :
     *      被阻列表结构($bList) : {
     *          被阻ID : {
     *              "bCids" : 阻塞列表 {
     *                  阻塞ID : 阻塞ID, ...
     *              }
     *              "bInfo" : 阻塞结构 {
     *                  被阻时间@阻塞ID : {
     *                      "rWait" : 被阻ID等待时间
     *                      "bInfo" : 递归阻塞, 同阻塞结构
     *                  }, ...
     *              }
     *          }, ...
     *      }
     * 作者 : Edgar.lee
     */
    public static function listenLockTimeout($pool, $name) {
        //恢复linux进程对SIGTERM信号处理
        function_exists('pcntl_signal') && pcntl_signal(15, SIG_DFL);

        //连接池配置
        $pool = of_base_com_kv::get('of_accy_db_tidb::pool-' . $pool, null, '_ofSelf');
        //配置连接池
        of_db::pool(__METHOD__, array(
            'adapter' => $name,
            'params'  => $pool
        ));

        //获取基础属性(版本, 时区)
        $attr = of_db::sql(
            'SELECT VERSION() ver, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP, NOW()) tz',
            __METHOD__
        );
        //跟随 SYSTEM_TIME_ZONE 时区
        of_db::sql('SET TIME_ZONE = "SYSTEM"', __METHOD__);
        //获取时区时间戳
        $tz = of_db::sql('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP, NOW()) stz', __METHOD__);
        //计算系统时区[系统时区, 连接时区]
        $tz = array(
            (($tz = round($tz[0]['stz'], -2)) < 0 ? '-' : '+') . abs($tz / 3600) . ':00',
            (($tz = round($attr[0]['tz'], -2)) < 0 ? '-' : '+') . abs($tz / 3600) . ':00'
        );
        //是否启动监听
        $isOn = version_compare($attr[0]['ver'], '5.5', '>=');
        //被阻列表
        $bList = array();

        //锁阻塞关系
        $sql = "SELECT
            rTrx.SESSION_ID rCid,
            CONVERT_TZ(rTrx.WAITING_START_TIME, '{$tz[0]}', '{$tz[1]}') rTime,
            TIMESTAMPDIFF(SECOND, rTrx.WAITING_START_TIME, NOW()) rWait,
            COUNT(bTrx.SESSION_ID) `count`,
            GROUP_CONCAT(bTrx.SESSION_ID) bList
        FROM
            INFORMATION_SCHEMA.DATA_LOCK_WAITS wait
                LEFT JOIN INFORMATION_SCHEMA.CLUSTER_TIDB_TRX rTrx ON
                    rTrx.`ID` = wait.TRX_ID
                LEFT JOIN INFORMATION_SCHEMA.CLUSTER_TIDB_TRX bTrx ON
                    bTrx.`ID` = wait.CURRENT_HOLDING_TRX_ID
        WHERE
            TIMESTAMPDIFF(SECOND, rTrx.WAITING_START_TIME, NOW()) > 2
        GROUP BY
            rCid";

        //加载的文件未变动
        while (!of_base_com_timer::renew()) {
            //休眠5s重新缓存
            sleep(5);

            //SQL执行成功
            if ($isOn && ($temp = of_db::sql($sql, __METHOD__)) !== false) {
                //递归阻塞列表, 被阻数据
                $wList = $bData = array();

                //格式化被阻数据
                foreach ($temp as &$vb) {
                    $bData[$vb['rCid']] = &$vb;
                    $vb['bList'] = array_flip(explode(',', $vb['bList']));
                }

                //排序阻塞列表
                foreach ($bData as $vk => &$vb) {
                    foreach ($vb['bList'] as $k => &$v) {
                        $v = isset($bData[$k]) ? $bData[$k]['count'] : 0;
                    }
                    arsort($vb['bList'], SORT_NUMERIC);
                }

                //生成被阻列表
                foreach ($bData as &$vb) {
                    //被阻ID不存在 || 被阻时间不同(换了一阻塞SQL)
                    if (!($index = &$bList[$vb['rCid']]) || $index['rTime'] !== $vb['rTime']) {
                        $index = array(
                            'rTime' => &$vb['rTime'],
                            'bCids' => array(),
                            'bInfo' => array()
                        );
                    }

                    //本次查询已解析的阻塞ID
                    $pCids = array();
                    //递归阻塞列表, 生成阻塞结构
                    $wList[] = array(
                        'bInfo' => &$index['bInfo'],
                        'rTime' => &$vb['rTime'],
                        'rWait' => &$vb['rWait'],
                        'bList' => $vb['bList']
                    );

                    do {
                        //本轮锁定的阻塞ID
                        $lCids = array();
                        //弹出最后一条待处理信息
                        $wait = array_pop($wList);

                        //生成阻塞结构
                        foreach ($wait['bList'] as $kw => &$vw) {
                            //跳过已锁定或解析的阻塞ID
                            if (isset($lCids[$kw]) || isset($pCids[$kw])) continue ;
                            //"被阻时间@阻塞ID"
                            $iKey = $wait['rTime'] .'@'. $kw;
                            //记录到被阻ID的阻塞列表中
                            $index['bCids'][$kw] = $pCids[$kw] = $kw;
                            //记录到递归阻塞结构列表中
                            $wait['bInfo'][$iKey]['rWait'] = &$wait['rWait'];

                            //递归阻塞ID, 生成阻塞结构
                            if (isset($bData[$kw]) && !isset($lCids[$kw])) {
                                //本轮锁定阻塞ID
                                $lCids += $bData[$kw]['bList'];
                                //加入递归阻塞列表
                                $wList[] = array(
                                    'bInfo' => &$wait['bInfo'][$iKey]['bInfo'],
                                    'rTime' => &$bData[$kw]['rTime'],
                                    'rWait' => &$bData[$kw]['rWait'],
                                    'bList' => $bData[$kw]['bList']
                                );
                            }
                        }
                    } while (isset($wList[0]));
                }

                //缓存被阻列表
                foreach ($bList as $kb => &$vb) {
                    //更新缓存
                    if (isset($bData[$kb])) {
                        //被阻列表缓存5分钟
                        $temp = 'of_accy_db_tidb::waits-' .
                            "{$kb}@{$pool['host']}:{$pool['port']}";
                        of_base_com_kv::set($temp, $vb, 300, '_ofSelf');
                    //释放无效内存
                    } else {
                        unset($bList[$kb]);
                    }
                }
            } else {
                break ;
            }
        }
    }
}

mysqli_report(MYSQLI_REPORT_OFF);