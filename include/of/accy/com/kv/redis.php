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
            ($result = $redis->setnx($name, $value)) && $time && $redis->expire($name, $time);
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
            $result = $time ? $redis->setEx($name, $time, $value) : $redis->set($name, $value);
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
    //分布式事务开启状态, -2未开启, >-2命令数
    private $multi = -2;
    //分布式事务命令列表 {节点名 : [[方法, 参数, 位置], ...], ...}
    private $cList = array();

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
        //默认返回自身对象
        $result = $this;

        //开启事务, 返回this
        if ($func === 'multi') {
            //未开启 && 标记开启
            $multi < -1 && $multi = -1;
        //提交事务, 返回array或null
        } else if ($func === 'exec') {
            //在事务中
            if ($multi > -2) {
                //结果集为苏州
                $result = array();
                //批量执行事务
                foreach ($this->cList as $hk => &$hv) {
                    //单节点事务顺序
                    $temp = array();
                    //开启指定节点事务
                    $redis->multi($hk);
                    //批量执行
                    foreach ($hv as &$v) {
                        //生成数组键
                        $temp[] = $v[2];
                        //执行命令
                        call_user_func_array(array($redis, $v[0]), $v[1]);
                    }
                    //提交事务
                    $temp = array($temp, $redis->exec());
                    //生成结果集, 一些命令报错会导致结果集缺失
                    foreach ($temp[0] as $k => &$v) $result[$v] = isset($temp[1][$k]) ? $temp[1][$k] : false;
                }
                //关闭事务
                $multi = -2;
                //清空命令列表
                $this->cList = array();
                //排序结果集
                ksort($result);
            //未在事务中
            } else {
                $result = null;
            }
        //回滚事务, 返回bool
        } else if ($func === 'discard') {
            //在事务中 ? true : false
            $result = $multi > -2;
            //关闭事务
            $multi = -2;
            //清空命令列表
            $this->cList = array();
        //有参数命令 && 在事务中, 返回this
        } else if ($argv && $multi > -2) {
            //记录事务命令
            $this->cList[$redis->_target($argv[0])][] = array($func, $argv, ++$multi);
        //无参数命令 || 不在事务中
        } else {
            //直接执行
            $result = call_user_func_array(array($redis, $func), $argv);
            //结果集redis对象 && 返回this
            $result === $redis && $result = $this;
        }

        //返回结果集
        return $result;
    }
}