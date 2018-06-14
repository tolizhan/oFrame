<?php
/**
 * 描述 : 数据库基类
 * 注明 :
 *      连接池列表结构($instList) : {
 *          "default" : {
 *              "pool" : 格式化后的连接池结构为 {
 *                  "write' :  {[
 *                      'adapter'        => 数据库连接方式 'mysqli',
 *                      'params'         => 数据库连接参数 array(
 *                          'host'       => '127.0.0.1',
 *                          'port'       => 3306,
 *                          'user'       => 'root',
 *                          'password'   => 'admin',
 *                          'database'   => 'test',
 *                          'charset'    => 'utf8',
 *                          'persistent' => 是否长连接 false
 *                  ], ...},
 *                  "read"  : 同 write 结构,
 *              },
 *              "inst" : 初始化的连接源对象 {
 *                  "write' : 写入连接源,
 *                  "read"  : 读取连接源,
 *                  "back"  : read的备份, 启动事务时有效
 *                  "level" : 嵌套的层次, 启动事务时有效
 *                  "state" : 嵌套未回滚, 启动事务时有效
 *              }
 *          }
 *      }
 * 作者 : Edgar.lee
 */
abstract class of_db {
    //当前与数据库交互的key
    private static $nowDbKey = null;
    //实例化对象{'key' : 实例化的对象}
    private static $instList = null;
    //全部数据库配置文件
    private static $dbConfig = null;
    //数据库连接参数(由of_db类出初始化)
    protected $params = null;

    /**
     * 描述 : 初始化方法,仅可通过self::inst()实例化
     * 作者 : Edgar.lee
     */
    final public function __construct(&$key = '', &$params = null) {
        //初始化连接实例
        if (isset(self::$instList[$key]['allowInst'])) {
            //连接参数
            $this->params = &$params;
            //移除允许实力标识
            unset(self::$instList[$key]['allowInst']);
        } else {
            //防止通过 of_accy_db_xxx 直接实例化
            trigger_error('The class can only be instantiate by of_db::inst()');
            exit;
        }
    }

    /**
     * 描述 : 读取/设置连接池
     * 参数 :
     *      key   : 使用的连接类,如果同一连接类不同的连接推荐使用::分割,如'mysql::我的区分符'
     *      pool  : key连接池的操作参数
     *          数组    : 连接参数,初始化后便不再起作用,默认_of.db, 支持读写分离配置方式
     *          "level" : 查询当前事务层次
     *          "state" : 读写当前事务提交状态, 当SQL执行失败, 改值会变为false
     *      value : 设置pool参数值
     *          "state" : false=强制事务最终回滚
     * 返回 :
     *      pool 为 null 或 数组: 返回 连接池配置
     *      pool 为 "level" 读取: 返回 整数, 0=不在事务里, 1=根事务, n=n层事务里
     *      pool 为 "state" 读取: 返回 null=不在事务里, true=提交事务, false=回滚事务
     * 作者 : Edgar.lee
     */
    final public static function pool($key, $pool = null, $value = null) {
        //引用实例列表
        $instList = &self::$instList;

        //查询当前事务层次
        if ($pool === 'level') {
            return isset($instList[$key]['inst']['level']) ?
                $instList[$key]['inst']['level'] : 0;
        //查写当前提交状态
        } else if ($pool === 'state') {
            //读取
            if ($value === null) {
                return isset($instList[$key]['inst']['level']) ?
                    $instList[$key]['inst']['state'] : null;
            //设置
            } else if (!$value && isset($instList[$key]['inst'])) {
                $instList[$key]['inst']['state'] = false;
            }
        } else {
            if (empty($instList[$key])) {
                //初始配置
                if ($pool === null) {
                    //引用数据库配置
                    $dbConfig = &self::$dbConfig;
                    //数据库配置初始化
                    if ($dbConfig === null) {
                        //读取连接池参数
                        $dbConfig = of::config('_of.db');

                        //单库连接配置
                        if (
                            isset($dbConfig['adapter']) && is_string($dbConfig['adapter']) ||
                            ($key === 'default' && !isset($dbConfig[$key]) && (
                                isset($dbConfig[0]) ||
                                isset($dbConfig['write']) && isset($dbConfig['read'])
                            ))
                        ) {
                            //配置文件格式化
                            $dbConfig = array('default' => $dbConfig);
                        }
                    }

                    //引用连接池
                    if (!$pool = &$dbConfig[$key]) {
                        //指定的数据连接无效
                        trigger_error('Did not find the specified database connection : ' . $key);
                        exit;
                    }
                }

                //引用连接
                $instList[$key] = array('pool' => &$pool);
            }

            //设置当期连接池
            self::$nowDbKey = $key;
            return $instList[$key]['pool'];
        }
    }

    /**
     * 描述 : 执行sql语句,根据不同语句返回不同结果
     *      sql  : 字符串 = 执行传入的sql
     *            null   = 开启事务,
     *            true   = 提交事务,
     *            false  = 回滚事务
     *      pool : 连接池区分符, 默认=default
     * 返回 :
     *      sql为字符串时 :
     *          查询类,返回二维数组
     *          插入类,返回插入ID
     *          删改类,返回影响行数
     *      sql为其它时 : 成功返回 true, 失败返回 false
     * 作者 : Edgar.lee
     */
    final public static function &sql($sql, $pool = 'default') {
        //读模式列表
        static $rAw = array('SHOW' => true, 'SELECT' => true, 'DESCRIBE' => true, 'EXPLAIN' => true);
        //事务 回滚, 提交, 开启
        static $fun = array('_rollBack', '_commit', '' => '_begin');

        //返回结果
        $result = false;
        //执行成功, false=执行失败, true=执行成功
        $isDone = true;
        //触发sql前事件
        of::event('of_db::before', true, array('sql' => &$sql, 'pool' => &$pool));
        //设置连接池
        self::pool($pool);

        //SQL 操作
        if (is_string($sql)) {
            //读取SQL类型, 可以使用"/*强制类型*/SQL"指定类型
            preg_match('@[\w]+@i', $sql, $tags);
            $tags = strtoupper($tags[0]);

            //获取读写分离连接
            if ($dbObj = &self::getConnect(isset($rAw[$tags]) ? 'read' : 'write')) {
                if ($result = $dbObj->_query($sql)) {
                    switch ($tags) {
                        case 'SHOW':
                        case 'SELECT':
                        case 'DESCRIBE':
                        case 'EXPLAIN':
                            $result = &$dbObj->_fetchAll();
                            break;
                        case 'INSERT':
                        case 'REPLACE':
                            $result = $dbObj->_lastInsertId();
                            break;
                        case 'UPDATE':
                        case 'DELETE':
                            $result = $dbObj->_affectedRows();
                            break;
                        case 'CALL':
                            $result = &$dbObj->_moreResults();
                            break;
                    }
                }

                //SQL执行失败 && 事务最终回滚
                if (!$isDone = $result !== false) {
                    self::$instList[$pool]['inst']['state'] = false;
                }
            }
        //事务处理
        } else if ($dbObj = &self::getConnect('write')) {
            //引用连接池
            $index = &self::$instList[$pool]['inst'];

            //启动事务
            if ($sql === null) {
                //嵌套事务
                if (isset($index['level'])) {
                    $index['level'] += 1;
                    $result = true;
                //根级事务 && 开启事务成功
                } else if ($isDone = $result = $dbObj->_begin()) {
                    //开启事务
                    $index['level'] = 1;
                    //最终状态
                    $index['state'] = true;
                    //备份读取连接
                    $index['back'] = &$index['read'];
                    //读取切换主
                    $index['read'] = &$dbObj;
                }
            //结束事务
            } else if (isset($index['level'])) {
                //嵌套事务
                if (--$index['level']) {
                    //嵌套回滚 && 最终回滚
                    $sql || $index['state'] = false;
                    $result = true;
                } else {
                    //执行事务,若子事务回滚->父事务也回滚
                    $isDone = $result = $dbObj->{$fun[$sql && $index['state']]}();
                    //切回原始配置
                    $index['read'] = &$index['back'];
                    //注销事务信息
                    unset($index['back'], $index['level']);
                }

                //返回结果 是回滚 || 执行成功 && 最终提交
                $result = $sql === false || $result && $index['state'];
            }
        }

        //SQL 执行错误
        $isDone || of::event('of_db::error', true, array(
            'note' => &$note, 'type' => $dbObj->_error($note),
            'sql' => &$sql, 'pool' => &$pool
        ));

        //触发sql后事件
        of::event('of_db::after', true, array('sql' => &$sql, 'pool' => &$pool, 'result' => &$result));
        return $result;
    }

    /**
     * 描述 : 获取读或写的数据连接
     * 参数 :
     *      type : write=写, read=读
     * 返回 :
     *      成功返回连接对象, 失败false
     * 作者 : Edgar.lee
     */
    final private static function &getConnect($type) {
        //连接参数引用
        $config = &self::$instList[self::$nowDbKey];
        //数据库连接源
        $dbLink = &$config['inst'][$type];

        //未初始化
        if ($dbLink === null) {
            //引用连接池
            $pool = &$config['pool'];
            //分离模式 || 可能是单个或混合
            ($isMix = isset($pool[$type])) || $pool = array('write' => $pool, 'read' => $pool);

            //引用读写分离
            $pool = &$pool[$type];
            //是单个的
            isset($pool['adapter']) && $pool = array($pool);

            do {
                //随机读取一连接
                $link = &$pool[$rand = rand(0, count($pool) - 1)];
                //设置时区
                if (($index = &$link['params']['timezone']) === null || $index === true) {
                    $index = of::config('_of.timezone');
                }

                //标识允许实例化
                $config['allowInst'] = 'of_accy_db_' . $link['adapter'];
                //实例化连接对象
                $dbLink = new $config['allowInst'](self::$nowDbKey, $link['params']);

                //连接数据库成功 || 没有备用连接
                if (($temp = $dbLink->_connect()) || empty($pool[1])) {
                    //读取失败
                    if ($temp === false) {
                        //分离模式 && 读取模式
                        $dbLink = $isMix && $type === 'read' ? self::getConnect('write') : false;
                    }

                    //分离模式 || 整合一起
                    $isMix || $config['inst'] = array(
                        'write' => &$dbLink,
                        'read'  => &$dbLink
                    );
                    break;
                }

                //删除连接失败的配置
                array_splice($pool, $rand, 1);
            } while (isset($pool[0]));
        }

        return $dbLink;
    }

    // '/of/accy/db/xx.php' 文件使用继承该类,并实现以下方法
    //连接到数据库
    abstract protected function _connect();

    //关闭连接源
    abstract protected function _close();

    //读取当前错误,返回 错误号:错误信息
    abstract protected function _error(&$node);

    //查看影响行数
    abstract protected function _affectedRows();

    //获取最后插入ID
    abstract protected function _lastInsertId();

    //开启事务
    abstract protected function _begin();

    //提交事务
    abstract protected function _commit();

    //事务回滚
    abstract protected function _rollBack();

    //执行sql语句,成功返回true,失败返回false
    abstract protected function _query(&$sql);

    //读取一行数据,失败返回空数组
    abstract protected function &_fetch();

    //读取全部数据,失败返回空数组
    abstract protected function &_fetchAll();

    //获取多个结果集
    abstract protected function &_moreResults();
}