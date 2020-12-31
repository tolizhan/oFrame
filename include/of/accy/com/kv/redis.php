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
                $redis->{$params['persistent'] ? 'connect' : 'pconnect'}($host[0], $host[1]);
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
        $redis = $this->_link(false);

        try {
            ($result = $redis->setnx($name, $value)) && $redis->expireAt($name, $time);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $result === false && !isset($redis->check) && $redis->check = true;
        return $result;
    }

    /**
     * 描述 : 删除数据
     * 作者 : Edgar.lee
     */
    protected function _del(&$name) {
        $redis = $this->_link(false);

        try {
            $result = $redis->del($name);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $result === false && !isset($redis->check) && $redis->check = true;
        return $result;
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        $redis = $this->_link(false);

        try {
            $result = $redis->setEx($name, $time - time(), $value);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $result === false && !isset($redis->check) && $redis->check = true;
        return $result;
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        $redis = $this->_link(false);

        try {
            $result = $redis->get($name);
        } catch (Exception $e) {
            $result = false;
        }

        //操作失败 && 标记校验
        $result === false && !isset($redis->check) && $redis->check = true;
        return $result;
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link($check = true) {
        $redis = &$this->redis;

        //标记无效(true=校验 false=失效) || 强制校验
        if (is_bool($index = &$redis->check) || $check) {
            //检查连接有效性
            try {
                $index === false || (
                    $isOk = $this->params['type'] === 'cluster' ?
                        $redis->ping('test') : $redis->ping()
                );
            } catch (Exception $e) {
            }

            //尝试重新连接
            try {
                empty($isOk) && $this->_connect();
            } catch (Exception $e) {
                $redis->check = false;
                of::event('of::error', true, $e);
            }
        }

        return $redis;
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
        $this->_link(false)->close();
    }
}