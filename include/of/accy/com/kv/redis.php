<?php
class of_accy_com_kv_redis extends of_base_com_kv {
    //redis对象
    private $redis = null;

    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $redis = &$this->redis;
        $params = $this->params + array(
            'type' => 'single'
        );

        switch ($params['type']) {
            //单点模式
            case 'single':
                //解析地址与端口
                $host = explode(':', $params['host']);
                isset($host[1]) || $host[1] = (empty($params['port']) ? 6379 : $params['port']);

                $redis = new Redis;
                $redis->connect($host[0], $host[1]);
                //授权
                $redis->auth($params['auth']);
                //选择数据库
                $redis->select($params['db']);
                break;
            //集群模式
            case 'cluster':
                $redis = new RedisCluster(null, $params['host'], null, null, false, $params['auth']);
                break;
            //分布模式
            case 'distributed':
                $redis = new RedisArray($params['host'], array(
                    //一致性hash分布
                    'consistent' => true,
                    'auth'       => $params['auth']
                ));
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
        $redis = $this->redis;

        if ($redis->setnx($name, $value)) {
            $redis->expireAt($name, $time);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 描述 : 删除数据
     * 作者 : Edgar.lee
     */
    protected function _del(&$name) {
        return $this->redis->del($name);
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        return $this->redis->setEx($name, $time - time(), $value);
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        return $this->redis->get($name);
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link() {
        return $this->redis;
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
        $this->redis->close();
    }
}