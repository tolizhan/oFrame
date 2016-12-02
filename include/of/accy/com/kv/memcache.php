<?php
class of_accy_com_kv_memcache extends of_base_com_kv {
    //memcache
    private $memcache = null;

    /**
     * 描述 : 存储源连接
     * 作者 : Edgar.lee
     */
    protected function _connect() {
        //格式化为二维
        isset($this->params[0]) || $this->params = array($this->params);

        $this->memcache = $memcache = new Memcache;
        foreach($this->params as &$v) {
            $memcache->addServer($v['host'], $v['port']);
        }
    }

    /**
     * 描述 : 添加数据
     * 作者 : Edgar.lee
     */
    protected function _add(&$name, &$value, &$time) {
        return $this->memcache->add($name, $value, false, $time);
    }

    /**
     * 描述 : 删除数据
     * 作者 : Edgar.lee
     */
    protected function _del(&$name) {
        return $this->memcache->delete($name);
    }

    /**
     * 描述 : 修改数据
     * 作者 : Edgar.lee
     */
    protected function _set(&$name, &$value, &$time) {
        return $this->memcache->set($name, $value, false, $time);
    }

    /**
     * 描述 : 获取数据
     * 作者 : Edgar.lee
     */
    protected function _get(&$name) {
        return $this->memcache->get($name);
    }

    /**
     * 描述 : 返回连接
     * 作者 : Edgar.lee
     */
    protected function _link() {
        return $this->memcache;
    }

    /**
     * 描述 : 关闭连接
     * 作者 : Edgar.lee
     */
    protected function _close() {
        $this->memcache->close();
    }
}