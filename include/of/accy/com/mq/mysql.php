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
 *          `syncCount` int(11) UNSIGNED NOT NULL COMMENT '已同步次数',
 *          `createTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '生成时间',
 *          `updateTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '消费时间, 2001为删除',
 *          `syncLevel` int(11) UNSIGNED NOT NULL COMMENT '同步等级, 数值越大优先级越低',
 *          `lockTime` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '锁定时间, 每 syncLevel * 5 分钟重试',
 *          `lockMark` char(32) NOT NULL COMMENT '锁定时生成的唯一ID',
 *          PRIMARY KEY (`type`,`mark`) USING BTREE,
 *          KEY `idx_consumer` (`type`,`lockTime`,`queue`) USING BTREE
 *      ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息队列表'
 *      /*!50100 PARTITION BY KEY (`type`) PARTITIONS 251 * /;
 * 作者 : Edgar.lee
 */
class of_accy_com_mq_mysql extends of_base_com_mq {
    private $dbPool = '';
    private $noTran = true;
    private $vHost = '';
    private $waitList = array();
    //消息偏移量记录{"time" : 最后更新时间戳, "limit" : 偏移量, "expire" : 过期时间}
    private $offset = array('time' => 0, 'limit' => 0, 'expire' => 600);

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
        //当前时间
        $nowTime = date('Y-m-d H:i:s', $stamp = time());
        //引用待处理
        $wait = &$this->waitList;

        foreach ($msgs as &$v) {
            $keys = &$v['keys'];
            $mark = md5($this->vHost . "\1" . $v['queue'] . "\1" . $keys[0] . "\1" . $keys[1]);
            //触发时间
            $exeTime = date('Y-m-d H:i:s', $stamp + $keys[2]);

            //删除数据
            if ($v['data'] === null) {
                $wait[$mark] = array(
                    'mode' => 'del',
                    'data' => "(`mark` = '{$mark}' AND `type` = '{$keys[0]}')"
                );
            //增改数据
            } else {
                $temp = addslashes(json_encode($v['data']));
                $wait[$mark] = array(
                    'mode'  => 'set',
                    'data'  => "(
                        '{$mark}', '{$this->vHost}', '{$v['queue']}',
                        '{$keys[0]}', '{$keys[1]}', '{$temp}',
                        '0', '{$exeTime}', '{$nowTime}',
                        '0', '{$exeTime}', ''
                    )"
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
     *          "lots"  : 批量消费数量,
     *          "data"  :x消息数据, _fire 函数实现
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *      }
     * 返回 :
     *      true=已匹配到消息, false=未匹配到消息
     * 作者 : Edgar.lee
     */
    protected function _fire(&$call, $data) {
        //设置 120 分钟超时
        ini_set('max_execution_time', 7200);
        //唯一编码
        $uniqid = of_base_com_str::uniqid();
        //消息数据
        $msgs = array();

        //通过先筛选主键后加锁的方式解决同时修改与筛选导致索引死锁的问题
        do {
            //当筛选成功, 锁定失败时保持循环
            $loop = false;
            //当前时间
            $nowTime = date('Y-m-d H:i:s', $time = time());
            //获取消息数据偏移量
            $limit = $this->msgLimit($time, $data['this']['cCid'], $data['lots']);
            //剩余消息数量
            $size = $data['lots'] - count($msgs);

            //筛选合适消息
            $sql = "SELECT
                `mark`
            FROM
                `_of_com_mq`
            WHERE
                `vHost` = '{$this->vHost}'
            AND `queue` = '{$data['queue']}'
            AND `type` = '{$data['key']}'
            AND `lockTime` <= '{$nowTime}'
            ORDER BY
                `lockTime`
            LIMIT
                {$limit}, {$size}";

            //筛选成功
            if ($marks = of_db::sql($sql, $this->dbPool)) {
                //120 + 5分钟过期
                $expTime = date('Y-m-d H:i:s', $time + 7500);
                //提取消息标识
                foreach ($marks as &$v) $v = $v['mark'];
                $marks = join('\',\'', $marks);

                //锁定数据
                $sql = "UPDATE
                    `_of_com_mq`
                SET
                    `syncCount` = `syncCount` + 1,
                    `syncLevel` = `syncLevel` + 1,
                    `lockTime` = '{$expTime}',
                    `lockMark` = '{$uniqid}'
                WHERE
                    `type` = '{$data['key']}'
                AND `mark` IN ('{$marks}')
                AND `lockTime` <= '{$nowTime}'";
                $loop = of_db::sql($sql, $this->dbPool);

                //修改成功(失败为false)
                if (is_int($loop)) {
                    //其它并发操作 && 重置消息偏移量
                    if ($loop === 0) {
                        $this->msgLimit();
                        $loop = true;
                    //读取加锁成功的消息
                    } else {
                        //读取加锁消息
                        $sql = "SELECT
                            `mark`, `data`, `msgId`, `syncLevel`, `updateTime`
                        FROM
                            `_of_com_mq`
                        WHERE
                            `type` = '{$data['key']}'
                        AND `mark` IN ('{$marks}')
                        AND `lockMark` = '{$uniqid}'";
                        $marks = of_db::sql($sql, $this->dbPool);

                        //将删除与未删除的消息分流
                        foreach ($marks as $k => &$v) {
                            //标记删除的消息
                            if ($v['updateTime'] === '2001-01-01 00:00:00') {
                                $v = $v['mark'];
                            //正常消息
                            } else {
                                $msgs[$v['mark']] = &$v;
                                unset($marks[$k]);
                            }
                        }

                        //清理标记删除的消息
                        if ($loop = join('\',\'', $marks)) {
                            $sql = "DELETE FROM
                                `_of_com_mq`
                            WHERE
                                `type` = '{$data['key']}'
                            AND `mark` IN ('{$loop}')
                            AND `lockMark` = '{$uniqid}'
                            AND `updateTime` = '2001-01-01 00:00:00'";
                            of_db::sql($sql, $this->dbPool);
                        }
                    }
                }
            } else {
                //重置消息偏移量
                $this->msgLimit();
            }
        } while ($loop);

        //执行成功
        if ($msgs) {
            //记录加锁编码
            $data['extra'] = array('lock'  => $uniqid);
            //解析消费数据
            foreach ($msgs as $k => &$v) {
                $data['count'][] = $v['syncLevel'];
                $data['msgId'][] = $v['msgId'];
                $data['data'][$v['msgId']] = json_decode($v['data'], true);
                $data['extra']['mark'][] = $v['mark'];
            }

            //格式化消费数据
            $data['count'] = max($data['count']);

            //回调结果
            $return = self::callback($call, $data);

            //执行成功
            if ($return === true) {
                //删除队列
                $mark = join('\',\'', $data['extra']['mark']);
                $sql = "DELETE FROM
                    `_of_com_mq`
                WHERE
                    `type` = '{$data['key']}'
                AND `mark` IN ('{$mark}')
                AND `lockMark` = '{$uniqid}'
                AND (`syncLevel` > '0' OR `updateTime` = '2001-01-01 00:00:00')";
                $temp = of_db::sql($sql, $this->dbPool);

                //删除队列失败(因消息被更新, `syncLevel` = 0) || 使用新触发时间
                count($data['extra']['mark']) === $temp || of_db::sql("UPDATE
                        `_of_com_mq`
                    SET
                        `lockTime` = `updateTime`,
                        `lockMark` = ''
                    WHERE
                        `type` = '{$data['key']}'
                    AND `mark` IN ('{$mark}')
                    AND `lockMark` = '{$uniqid}'
                    AND `syncLevel` = '0'", $this->dbPool
                );
            //执行失败
            } else {
                //返回数字 && 指定时间(s)
                is_int($return) && $data['extra']['delay'] = $return;
                //修改消息重试次数
                $this->_quit($data);
            }
        }

        return !!$msgs;
    }

    /**
     * 描述 : 触发消息队列意外退出时回调
     * 参数 :
     *     &data : {
     *          "pool"  : 指定消息队列池,
     *          "queue" : 队列名称,
     *          "key"   : 消息键,
     *          "lots"  : 批量消费数量,
     *          "this"  : 当前并发信息 {
     *              "cMd5" : 回调唯一值
     *              "cCid" : 当前并发值
     *          }
     *          "msgId" : 消息ID
     *          "count" : 调用计数, 首次为 1
     *          "data"  : 消息数据
     *      }
     * 作者 : Edgar.lee
     */
    protected function _quit(&$data) {
        //引用扩展
        $extra = &$data['extra'];
        //修改消息重试次数
        $mark = join('\',\'', $extra['mark']);
        //当前时间
        $date = date('Y-m-d H:i:s', time());
        //重试时间语句
        $delay = isset($extra['delay']) ?
            $extra['delay'] : 'IF(`syncLevel` = "0", 300, `syncLevel` * 300)';

        //更新重试数据
        $sql = "UPDATE
            `_of_com_mq`
        SET
            `lockTime` = IF(
                `updateTime` = '2001-01-01 00:00:00',
                `updateTime`,
                DATE_ADD('{$date}', INTERVAL {$delay} SECOND)
            ),
            `lockMark` = ''
        WHERE
            `type` = '{$data['key']}'
        AND `mark` IN ('{$mark}')
        AND `lockMark` = '{$extra['lock']}'";
        of_db::sql($sql, $this->dbPool);
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
            //操作模式
            $mode = '';
            //操作长度
            $sLen = 0;
            //操作列表
            $list = array();
            //待处理列表
            $wait = &$this->waitList;
            ksort($wait);
            //增加结束标识
            $wait['']['data'] = '';

            foreach ($wait as $k => &$v) {
                if (
                    //操作列表有数据
                    isset($list[0]) &&
                    //操作列表已满(>=0.5M) || 处理列表结束 || 模式切换
                    ($sLen >= 524288 || $k === '' || $mode !== $v['mode'])
                ) {
                    //修改数据
                    if ($mode === 'set') {
                        $sql = 'INSERT INTO `_of_com_mq` (
                            `mark`, `vHost`, `queue`,
                            `type`, `msgId`, `data`,
                            `syncCount`, `updateTime`, `createTime`,
                            `syncLevel`, `lockTime`, `lockMark`
                        ) VALUES
                            ' . join(', ', $list) . '
                        ON DUPLICATE KEY UPDATE
                            `data` = VALUES(`data`),
                            `createTime` = VALUES(`createTime`),
                            `updateTime` = VALUES(`updateTime`),
                            `lockTime` = IF(`lockMark` = "", VALUES(`lockTime`), `lockTime`),
                            `syncLevel` = "0"';
                    //删除数据
                    } else {
                        //防止同一消息运行过程中, 删除再创建导致并列执行
                        $sql = 'UPDATE
                            `_of_com_mq`
                        SET
                            `updateTime` = "2001-01-01 00:00:00"
                        WHERE
                            ' . join(' OR ', $list);
                    }

                    //批量执行
                    of_db::sql($sql, $this->dbPool);
                    //重置操作长度
                    $sLen = 0;
                    //重置操作列表
                    $list = array();
                }

                //记录操作模式
                $mode = &$v['mode'];
                //操作长度
                $sLen += strlen($v['data']) + 4;
                //记录操作数据
                $list[] = &$v['data'];
            }

            //重置待处理列表
            $wait = array();
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

    /**
     * 描述 : 读取或重置消息偏移量, _fire辅助方法
     * 作者 : Edgar.lee
     */
    private function msgLimit(&$time = 0, &$cCid = 0, &$lots = 1) {
        $index = &$this->offset;

        //重置偏移量
        if ($time === 0) {
            $index['expire'] = 30;
        //偏移量失效
        } else if ($time - $index['time'] > $index['expire']) {
            //重置更新时间
            $index['time'] = $time;
            //重置过期时间
            $index['expire'] = 600;
            //读取正在执行并发数据
            $data = of_base_com_timer::data(true, true);
            //计算消息数据偏移量
            $index['limit'] = $data['info'][$cCid]['sort'] * $lots * 5;
        }

        return $index['limit'];
    }
}