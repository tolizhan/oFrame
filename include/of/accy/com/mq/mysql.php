<?php
/**
 * 描述 : 实现 mysql 消息队列
 * 注明 :
 *      建表语句 : CREATE TABLE `_of_com_mq` (
 *          `mark` char(35) NOT NULL COMMENT '消息唯一ID(虚拟主机+队列名称+消息类型+消息ID)',
 *          `vHost` char(50) NOT NULL COMMENT '虚拟主机',
 *          `queue` char(50) NOT NULL COMMENT '队列名称',
 *          `type` char(50) NOT NULL COMMENT '消息类型',
 *          `msgId` char(100) NOT NULL COMMENT '消息ID',
 *          `data` mediumtext NOT NULL COMMENT '队列数据',
 *          `syncCount` int(11) NOT NULL COMMENT '已同步次数',
 *          `updateTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '消息最后更新时间',
 *          `createTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '消息首次创建时间',
 *          `syncLevel` int(11) NOT NULL COMMENT '同步等级, 数值越大优先级越低',
 *          `lockTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '锁定时间, 每 syncLevel * 5 分钟重试',
 *          `lockMark` char(32) NOT NULL COMMENT '锁定时生成的唯一ID',
 *          PRIMARY KEY (`type`,`mark`) USING BTREE,
 *          KEY `常规排序搜索` (`type`,`lockTime`,`queue`) USING BTREE
 *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息队列表'
 *      /*!50100 PARTITION BY KEY (`type`) PARTITIONS 250 * /;
 * 作者 : Edgar.lee
 */
class of_accy_com_mq_mysql extends of_base_com_mq {
    private $dbPool = '';
    private $noTran = true;
    private $vHost = '';
    private $waitList = array();

    /**
     * 描述 : 初始化适配器
     * 作者 : Edgar.lee
     */
    protected function _init($fire) {
        $params = &$this->params;

        //设置虚拟机
        isset($params['params']['vHost']) && $this->vHost = $params['params']['vHost'];

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
            $keys = &$v['keys'];
            $mark = md5($this->vHost . "\1" . $v['queue'] . "\1" . $keys[0] . "\1" . $keys[1]);

            //删除数据
            if ($v['data'] === null) {
                $waitList[$mark] = array(
                    'type' => 'delete',
                    'mark' => $mark,
                    'key'  => $keys[0]
                );
            //增改数据
            } else {
                $temp = addslashes(json_encode($v['data']));
                $temp = "`vHost` = '{$this->vHost}',
                    `queue` = '{$v['queue']}',
                    `mark` = '{$mark}',
                    `type` = '{$keys[0]}',
                    `msgId` = '{$keys[1]}',
                    `data` = '{$temp}',
                    `updateTime` = '{$nowTime}',
                    `syncLevel` = '0',
                    `lockTime` = DATE_ADD('{$nowTime}', INTERVAL {$keys[2]} SECOND),
                    `lockMark` = ''";

                $waitList[$mark] = array(
                    'type'  => 'update',
                    'time'  => &$nowTime,
                    'attr'  => $temp
                );
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
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
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

        //通过先筛选主键后加锁的方式解决同时修改与筛选导致索引死锁的问题
        do {
            //当筛选成功, 锁定失败时保持循环
            $loop = false;
            //读取正在执行并发ID
            $lock = of_base_com_timer::data(null, true);
            //计算消息数据偏移量
            $limit = $lock['info'][$data['this']['cCid']]['sort'] * 5;

            //提取正在执行消息ID
            foreach ($lock['info'] as $k => &$v) {
                //正在执行
                if (($index = &$v['data']['_mq']) && $index['doneTime'] === '') {
                    $v = $index['msgId'];
                //没有执行
                } else {
                    unset($lock['info'][$k]);
                }
            }
            $lock = join('\',\'', $lock['info']);

            //筛选合适消息
            $sql = "SELECT
                `mark`, `data`, `msgId`, `syncLevel`, `lockTime`
            FROM
                `_of_com_mq`
            WHERE
                `vHost` = '{$this->vHost}'
            AND `queue` = '{$data['queue']}'
            AND `type` = '{$data['key']}'
            AND `lockTime` <= '{$nowTime}'
            AND `msgId` NOT IN ('{$lock}')
            ORDER BY
                `lockTime`
            LIMIT
                {$limit}, 1";
            $msgs = of_db::sql($sql, $this->dbPool);

            //筛选成功
            if ($msgs = &$msgs[0]) {
                //锁定数据
                $sql = "UPDATE
                    `_of_com_mq`
                SET
                    `lockTime` = '{$expTime}',
                    `lockMark` = '{$uniqid}'
                WHERE
                    `type` = '{$data['key']}'
                AND `mark` = '{$msgs['mark']}'
                AND `lockTime` = '{$msgs['lockTime']}'";
                $loop = of_db::sql($sql, $this->dbPool);

                //执行出错 ? 跳出执行 : 响应行数决定有效或重试
                $loop === false ? $msgs = null : $loop = !$loop;
            }
        } while ($loop);

        //执行成功
        if ($msgs) {
            $data['msgId'] = $msgs['msgId'];
            $data['count'] = $msgs['syncLevel'] + 1;
            $data['data'] = json_decode($msgs['data'], true);

            //回调结果
            $return = self::callback($call, $data);

            //回调后事务未关闭
            if ($temp = of_db::pool($this->dbPool, 'level')) {
                //若消费成功 && 改成消费失败
                $return === true && $return = false;
                //嵌套回滚
                for ($i = 0; $i < $temp; ++$i) {
                    of_db::sql(false, $this->dbPool);
                }
            }

            //执行成功
            if ($return === true) {
                //执行结果
                $result[] = array('result' => true);

                $sql = "DELETE FROM
                    `_of_com_mq`
                WHERE
                    `type` = '{$data['key']}'
                AND `mark` = '{$msgs['mark']}'
                AND `lockMark` = '{$uniqid}'";
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
                is_int($return) || $return = $data['count'] * 300;
                $expTime = date('Y-m-d H:i:s', time() + $return);

                //修改消息重试次数
                $sql = "UPDATE
                    `_of_com_mq`
                SET
                    `syncCount` = `syncCount` + 1,
                    `syncLevel` = `syncLevel` + 1,
                    `lockTime` = '{$expTime}',
                    `lockMark` = ''
                WHERE
                    `type` = '{$data['key']}'
                AND `mark` = '{$msgs['mark']}'
                AND `lockMark` = '{$uniqid}'";
                of_db::sql($sql, $this->dbPool);
            }
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
                    $sql = "INSERT INTO
                        `_of_com_mq`
                    SET
                        {$v['attr']},
                        `createTime` = '{$v['time']}',
                        `syncCount` = '0'
                    ON DUPLICATE KEY UPDATE
                        `data` = VALUES(`data`),
                        `updateTime` = VALUES(`updateTime`),
                        `syncLevel` = VALUES(`syncLevel`),
                        `lockTime` = VALUES(`lockTime`),
                        `lockMark` = VALUES(`lockMark`)";
                //删除数据
                } else {
                    $sql = "DELETE FROM 
                        `_of_com_mq`
                    WHERE
                        `type` = '{$v['key']}'
                    AND `mark` = '{$v['mark']}'";
                }

                of_db::sql($sql, $this->dbPool);
            }

            $waitList = array();
            return of_db::pool($this->dbPool, 'state');
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
        } else {
            return true;
        }
    }
}