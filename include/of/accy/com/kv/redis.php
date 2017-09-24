<?php
class of_accy_com_kv_redis extends of_base_com_kv {
    //主库
    private $master = null;
    //从库
    private $slave = null;

    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        $params = &$this->params;
        //格式化为二维
        isset($params[0]) || $params = array($params);
        $this->master = array_shift($params);
        $params ? 
            $this->slave = $params[array_rand($params)] : 
            $this->slave = &$this->master;
    }

    /**
     * 描述 : 添加数据
     * 作者 : Edgar.lee
     */
    protected function _add(&$name, &$value, &$time) {
        $redis = $this->_link('master');

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
        return $this->_link('master')->del($name);
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        return $this->_link('master')->setEx($name, $time - time(), $value);
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        return $this->_link('slave')->get($name);
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link($type = 'master') {
        //读主库 ?: 从库
        $type === 'master' ? $type = &$this->master : $type = &$this->slave;

        //未初始化
        if (is_array($type)) {
            $redis = new Redis;
            $redis->connect($type['host'], $type['port']);
            //授权
            empty($type['auth']) || $redis->auth($type['auth']);
            //选择数据库
            empty($type['db']) || $redis->select($type['db']);
            //存储资源
            $type = $redis;
        }

        return $type;
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
        is_resource($this->master) && $this->master->close();
        is_resource($this->slave) && $this->slave->close();
    }
}