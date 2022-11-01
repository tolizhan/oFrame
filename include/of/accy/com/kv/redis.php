<?php
class of_accy_com_kv_redis extends of_base_com_kv {
    //redis对象
    private $redis = null;
    //校验是否连接, true=需校验, false=不校验
    private $check = false;
    //分布式专属 _RedisArray 对象
    private $link = null;

    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $redis = &$this->redis;
        $params = &$this->params;
        $params += array(
            'type' => 'single',
            'persistent' => false
        );

        switch ($params['type']) {
            //单点模式
            case 'single':
                //解析地址与端口
                $host = explode(':', $params['host']);
                isset($host[1]) || $host[1] = (empty($params['port']) ? 6379 : $params['port']);

                $redis = new Redis;
                $redis->{$params['persistent'] ? 'pconnect' : 'connect'}($host[0], $host[1]);
                //授权
                $redis->auth($params['auth']);
                //选择数据库
                $redis->select($params['db']);
                break;
            //集群模式
            case 'cluster':
                $redis = new RedisCluster(null, $params['host'], null, null, $params['persistent'], $params['auth']);
                break;
            //分布模式
            case 'distributed':
                $redis = new RedisArray($params['host'], array(
                    //一致性hash分布
                    'consistent' => true,
                    'persistent' => $params['persistent'],
                    'auth'       => $params['auth']
                ));
                $this->link = new _RedisArray($redis);
                //选择数据库 || 连接失败
                if (!$redis->select($params['db'])) throw new Exception('Failed to connection.');
                break;
        }
    }

    /**
     * 描述 : 添加数据
     * 作者 : Edgar.lee
     */
    protected function _add(&$name, &$value, &$time) {
        $redis = $this->_link(false, false);

        try {
            ($result = $redis->setnx($name, $value)) && $redis->expireAt($name, $time);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $this->check = $result === false;
        return $result;
    }

    /**
     * 描述 : 删除数据
     * 作者 : Edgar.lee
     */
    protected function _del(&$name) {
        $redis = $this->_link(false, false);

        try {
            $result = $redis->del($name);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $this->check = $result === false;
        return $result;
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        $redis = $this->_link(false, false);

        try {
            $result = $redis->setEx($name, $time - time(), $value);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $this->check = $result === false;
        return $result;
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        $redis = $this->_link(false, false);

        try {
            $result = $redis->get($name);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $this->check = $result === false;
        return $result;
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link($check = true, $other = true) {
        $redis = &$this->redis;

        //标记无效(true=需校验 false=不校验) || 强制校验
        if ($this->check || $check) {
            try {
                //检查连接有效性, true=连接中, false=未连接
                switch ($this->params['type']) {
                    case 'cluster':
                        $isOk = $redis->ping('test');
                        break;
                    case 'distributed':
                        $isOk = !in_array(false, $redis->ping(), true);
                        break;
                    default :
                        $isOk = $redis->ping();
                }
            } catch (Exception $e) {
            }

            //尝试重新连接
            try {
                empty($isOk) && $this->_connect();
                $this->check = false;
            } catch (Exception $e) {
                $this->check = true;
                of::event('of::error', true, $e);
            }
        }

        return $this->check ?
            false :
            //应用层使用 && 分布式方式 ? 封装对象 : 原始对象
            ($other && $this->params['type'] === 'distributed' ? $this->link : $redis);
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
        $this->_link(false, false)->close();
    }
}

class _RedisArray {
    //redis对象
    private $redis = null;
    //分布式事务开启状态
    private $multi = false;

    /**
     * 描述 : 构造函数
     * 作者 : Edgar.lee
     */
    public function __construct(&$redis) {
        $this->redis = &$redis;
    }

    /**
     * 描述 : redis分布式(distributed)事务方法重载
     * 作者 : Edgar.lee
     */
    public function __call($func, $argv) {
        //引用redis对象
        $redis = &$this->redis;
        //引用事务预开启状态
        $multi = &$this->multi;

        //事务预开启状态 && 不是multi, exec, discard, unwatch && 开启事务
        $multi && $argv && $redis->multi($redis->_target($argv[0]));
        //判断是否设置预开启状态
        $multi = $func === 'multi';
        //事务预开启状态 ? 不执行multi返回this : 执行命令并返回
        $result = $multi ? $this : call_user_func_array(array($redis, $func), $argv);

        //结果集redis对象 ? 返回this : 返回结果集
        return $result === $redis ? $this : $result;
    }
}