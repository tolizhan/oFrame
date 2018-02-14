<?php
/**
 * 描述 : 实现 mysql 消息队列
 * 注明 :
 *      建表语句 : CREATE TABLE `_of_com_mq` (
 *          `queue` char(50) NOT NULL COMMENT '队列名称',
 *          `unId` char(32) NOT NULL COMMENT '消息唯一ID(队列名称+消息类型+消息ID)',
 *          `type` char(20) NOT NULL COMMENT '消息类型',
 *          `msId` char(100) NOT NULL COMMENT '消息ID',
 *          `data` mediumtext NOT NULL COMMENT '队列数据',
 *          `syncCount` int(11) NOT NULL COMMENT '已同步次数',
 *          `updateTime` datetime NOT NULL COMMENT '消息最后更新时间',
 *          `createTime` datetime NOT NULL COMMENT '消息首次创建时间',
 *          `syncLevel` int(11) NOT NULL COMMENT '同步等级, 数值越大优先级越低',
 *          `lockTime` datetime NOT NULL COMMENT '锁定时间, 每 syncLevel * 5 分钟重试',
 *          `lockUnid` char(32) NOT NULL COMMENT '锁定时生成的唯一ID',
 *          PRIMARY KEY (`queue`,`unId`),
 *          KEY `常规排序搜索` (`type`,`lockTime`,`queue`,`lockUnid`) USING BTREE
 *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息队列表'
 *      /*!50100 PARTITION BY KEY (queue)
 *      PARTITIONS 250 * /;
 * 作者 : Edgar.lee
 */
class of_accy_com_mq_mysql extends of_base_com_mq {
    private $dbPool = '';
    private $noTran = true;
    private $waitList = array();

    /**
     * 描述 : 初始化适配器
     * 作者 : Edgar.lee
     */
    protected function _init($fire) {
        $params = &$this->params;

        //是相同(停止事务)
        if ($this->noTran = $fire['bind'] === $params['params']['dbPool']) {
            $this->dbPool = $fire['bind'];
        //不相同
        } else {
            $this->dbPool = 'of_accy_com_mq_mysql::' . of_base_com_str::uniqid();
            //复制连接
            of_db::pool($this->dbPool, of_db::pool($params['params']['dbPool']));
        }
    }

    /**
     * 描述 : 设置消息
     * 参数 :
     *     &msgs : 需要设置的消息集合 [{
     *          "keys"  : 消息定位 [消息类型, 消息主键],
     *          "data"  : 消息数据, null=删除 keys 指定的信息, 其它=消息数据
     *          "pool"  : 指定消息队列池,
     *          "bind"  : ""=绑定到手动事务, 字符串=绑定数据池同步事务
     *          "queue" : 队列名称
     *      }, ...]
     * 作者 : Edgar.lee
     */
    protected function _sets(&$msgs) {
        $nowTime = date('Y-m-d H:i:s');
        $waitList = &$this->waitList;

        foreach ($msgs as $k => &$v) {
            $unid = md5($v['queue'] . "\1" . $v['keys'][0] . "\1" . $v['keys'][1]);

            //删除数据
            if ($v['data'] === null) {
                $waitList[$unid] = array(
                    'type'  => 'delete',
                    'unid'  => $unid,
                    'queue' => $v['queue']
                );
            } else {
                $temp = addslashes(json_encode($v['data']));
                $attr = "SET
                    `queue` = '{$v['queue']}',
                    `unId` = '{$unid}',
                    `type` = '{$v['keys'][0]}',
                    `msId` = '{$v['keys'][1]}',
                    `data` = '{$temp}',
                    `updateTime` = '{$nowTime}',
                    `syncLevel` = '0',
                    `lockTime` = '{$nowTime}',
                    `lockUnid` = ''";

                //掺入数据
                $sql = "/*UPDATE*/ INSERT IGNORE INTO 
                    `_of_com_mq` 
                {$attr},
                    `createTime` = '{$nowTime}',
                    `syncCount` = '0'";

                //执行失败
                if (($temp = of_db::sql($sql, $this->dbPool)) === false) {
                    return false;
                //执行成功
                } else if ($temp) {
                    unset($waitList[$unid]);
                //延迟修改
                } else {
                    $waitList[$unid] = array(
                        'type'  => 'update',
                        'unid'  => $unid,
                        'queue' => $v['queue'],
                        'attr'  => $attr
                    );
                }
            }
        }

        return true;
    }

    /**
     * 描述 : 触发消息队列, 根据回调响应值执行对应动作
     * 参数 :
     *     &call : 符合回调结构
     *     &data : 需要设置的消息集合, call的回调参数 {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "data"  :x消息数据, _fire 函数实现
     *      }
     * 返回 :
     *      [{
     *          "result" : 响应结果
     *              true=成功, 删除队列
     *              false=失败, 稍后重试
     *              数字=指定秒数后重试
     *              其它=抛出错误, 稍后重试
     *          "count"  : 调用计数, result为 false, 数字时每5次报错一次
     *          "params" : 调用参数
     *      }, ...]
     * 作者 : Edgar.lee
     */
    protected function _fire(&$call, &$data) {
        //唯一编码
        $uniqid = of_base_com_str::uniqid();
        //当前时间
        $nowTime = date('Y-m-d H:i:s', $time = time());
        //10分钟过期
        $expTime = date('Y-m-d H:i:s', $time + 600);
        //结果集
        $result = array();

        //寻找合适消息
        $sql = "UPDATE
            `_of_com_mq`
        SET
            `lockTime` = '{$expTime}',
            `lockUnid` = '{$uniqid}'
        WHERE
            `queue` = '{$data['queue']}'
        AND `type` = '{$data['key']}'
        AND `lockTime` <= '{$nowTime}'
        ORDER BY
            `lockTime`
        LIMIT 1";

        //执行成功
        if (of_db::sql($sql, $this->dbPool)) {
            $sql = "SELECT
                `unid`, `data`, `syncLevel`
            FROM
                `_of_com_mq`
            WHERE
                `queue` = '{$data['queue']}'
            AND `type` = '{$data['key']}'
            AND `lockTime` = '{$expTime}'
            AND `lockUnid` = '{$uniqid}'
            ORDER BY
                `lockTime`
            LIMIT 1";

            //查询成功
            if ($msgs = of_db::sql($sql, $this->dbPool)) {
                $msgs = &$msgs[0];
                $data['data'] = json_decode($msgs['data']);

                //回调结果
                $return = of::callFunc($call, $data);

                //执行成功
                if ($return === true) {
                    $sql = "DELETE FROM
                        `_of_com_mq`
                    WHERE
                        `queue` = '{$data['queue']}'
                    AND `unid` = '{$msgs['unid']}'
                    AND `lockUnid` = '{$uniqid}'";
                    of_db::sql($sql, $this->dbPool);
                //执行失败
                } else {
                    //执行结果
                    $result[] = array(
                        'result' => $return,
                        'count'  => $msgs['syncLevel'] + 1,
                        'params' => $data
                    );

                    //非指定时间的其它错误(包括 false), 计算下次执行时间(s)
                    is_int($return) || $return = ($msgs['syncLevel'] + 1) * 300;
                    $expTime = date('Y-m-d H:i:s', time() + $return);

                    //修改消息重试次数
                    $sql = "UPDATE
                        `_of_com_mq`
                    SET
                        `syncCount` = `syncCount` + 1,
                        `syncLevel` = `syncLevel` + 1,
                        `lockTime` = '{$expTime}',
                        `lockUnid` = ''
                    WHERE
                        `queue` = '{$data['queue']}'
                    AND `unid` = '{$msgs['unid']}'
                    AND `lockUnid` = '{$uniqid}'";
                    of_db::sql($sql, $this->dbPool);
                }
            }

            //执行成功, 被删除, 修改, 掉线 都视为成功
            $result || $result[] = array('result' => true);
        }

        return $result;
    }

    /**
     * 描述 : 开启事务
     * 作者 : Edgar.lee
     */
    protected function _begin() {
        return $this->noTran || of_db::sql(null, $this->dbPool);
    }

    /**
     * 描述 : 提交事务
     * 参数 :
     *      type : "before"=提交开始回调, "after"=提交结束回调
     * 作者 : Edgar.lee
     */
    protected function _commit($type) {
        if ($type === 'before') {
            $waitList = &$this->waitList;
            ksort($waitList);

            foreach ($waitList as &$v) {
                //修改数据
                if ($v['type'] === 'update') {
                    $sql = "UPDATE
                        `_of_com_mq`
                    {$v['attr']}
                    WHERE 
                        `queue` = '{$v['queue']}'
                    AND `unId` = '{$v['unid']}'";
                //删除数据
                } else {
                    $sql = "DELETE FROM 
                        `_of_com_mq`
                    WHERE
                        `queue` = '{$v['queue']}'
                    AND `unId` = '{$v['unid']}'";
                }

                of_db::sql($sql, $this->dbPool);
            }

            $waitList = array();
        } else {
            return $this->noTran || of_db::sql(true, $this->dbPool);
        }
    }

    /**
     * 描述 : 事务回滚
     *      type : "before"=回滚开始回调, "after"=回滚结束回调
     * 作者 : Edgar.lee
     */
    protected function _rollBack($type) {
        if ($type === 'after') {
            $this->waitList = array();
            return $this->noTran || of_db::sql(false, $this->dbPool);
        }
    }
}