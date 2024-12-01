<?php
/**
 * 描述 : 实现 redis 消息队列
 * 注明 :
 *      消息数据结构 : {
 *          "msgId"      : 消息ID,
 *          "data"       : 消息数据,
 *          "syncCount"  :o同步总数,
 *          "syncLevel"  : 同步等级(更新消息时重置),
 *          "planTime"   :o计划时间戳(不存在时为删除),
 *          "updateTime" : 更新时间戳,
 *          "lockTime"   :o锁定时间戳(在此范围内不执行),
 *          "lockMark"   :o锁定标记(防止执行超时被其它消费)
 *      }
 *      消息存储键名 :
 *          消息数据 : of_accy_com_mq_redis::data::{虚拟主机.队列名称.消息类型.消息主键}
 *          执行队列 : of_accy_com_mq_redis::sort::虚拟主机.队列名称.消息类型.{队列槽名}
 *          失败队列 : of_accy_com_mq_redis::fail::虚拟主机.队列名称.消息类型.{队列槽名}
 * 作者 : Edgar.lee
 */
class of_accy_com_mq_redis extends of_base_com_mq {
    private $kvPool = '';
    private $vHost = '';
    private $redis = null;
    //消费分槽计数
    private $slotCount = 0;
    //生产待处理列表
    private $waitList = array();
    //消息偏移量记录{"time" : 最后更新时间戳, "limit" : 偏移限制, "expire" : 过期时间}
    private $offset = array('time' => 0, 'limit' => null, 'expire' => 600);

    /**
     * 描述 : 获取redis消息队列运行信息
     * 参数 :
     *      params : {
     *          "match"   :o正则匹配队列标识, 以@开头, 默认false不过滤
     *          "mqSlot"  :o过滤队列分槽列表, 默认false=全部消息(消费分槽), true=升级中的(生产与消费分槽差集)
     *          "total"   :o是否统计消息总数, 默认false不统计, true=统计
     *          "overdue" :o是否统计可消费数, 默认false不统计, true=统计
     *          "failNo"  :o是否统计失败总数, 默认false不统计, true=统计
     *          "failed"  :o读取最大失败消息, 默认0不查询, >0为最大长度
     *          "recent"  :o读取即将消费消息, 默认0不查询, >0为最大长度
     *      }
     * 返回 :
     *      {
     *          队列标识, 队列池.队列名.消息键 : {
     *              "mqName"  : 消息名称, 虚拟机.队列名.消息键
     *              "mqSlot"  : 队列槽列表 {
     *                  虚拟机.队列名.消息键.槽编码 : {
     *                      "total"   : 消息总数
     *                      "overdue" : 可消费数
     *                      "failNo"  : 失败总数
     *                  }, ...
     *              }
     *              "total"   : 消息总数
     *              "overdue" : 可消费数
     *              "failNo"  : 失败总数
     *              "failed"  : 失败列表 {
     *                  消息ID : {}, ...
     *              }
     *              "recent"  : 消费列表 {
     *                  消息ID : {}, ...
     *              }
     *              "msgList" : 消息属性汇总列表 {
     *                  消息ID : 消息属性 {
     *                      "msgId"      : 消息ID,
     *                      "data"       : 消息数据,
     *                      "syncCount"  :o失败次数,
     *                      "syncLevel"  : 同步等级(更新消息时重置),
     *                      "planTime"   :o计划时间戳(不存在时为删除),
     *                      "updateTime" : 更新时间戳,
     *                      "lockTime"   :o锁定时间戳(在此范围内不执行),
     *                      "lockMark"   :o锁定标记(防止执行超时被其它消费)
     *                  },
     *                  ...
     *              }
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function getMqInfo($params = array()) {
        //结果集
        $result = array();
        //队列池配置文件
        $config = of::config('_of.com.mq');
        //参数补全
        $params += array(
            'match' => false, 'mqSlot' => false, 'total' => false,
            'overdue' => false, 'failNo' => false, 'failed' => 0,
            'recent' => 0
        );
        //当期时间戳
        $time = time();

        //遍历队列池
        foreach ($config as $kp => &$vp) {
            //提取redis队列
            if ($vp['adapter'] !== 'redis') continue;

            //初始化虚拟机
            ($vHost = &$vp['params']['vHost']) || $vHost = '';
            //读取redis对象
            $redis = of_base_com_kv::link($vp['params']['kvPool']);
            //加载队列表配置
            of_base_com_mq::getQueueConfig($vp['queues'], $kp);

            //遍历队列表
            foreach ($vp['queues'] as $kq => &$vq) {
                //遍历队列键
                foreach ($vq['keys'] as $kk => &$vk) {
                    //队列标识
                    $qMark = "{$kp}.{$kq}.{$kk}";
                    //不过滤 || 匹配成功
                    if (!$params['match'] || preg_match($params['match'], $qMark)) {
                        //初始化消息集
                        $index = &$result[$qMark];
                        $index = array(
                            'mqName' => '', 'mqSlot' => array(), 'total' => 0,
                            'overdue' => 0, 'failNo' => 0, 'failed' => array(),
                            'recent' => array(), 'msgList' => array()
                        );
                        //消息名称
                        $name = $index['mqName'] = "{$vHost}.{$kq}.{$kk}";
                        //过滤分槽列表
                        $list = $params['mqSlot'] ?
                            array_diff($vp['params']['mqSlot'][1], $vp['params']['mqSlot'][0]) :
                            $vp['params']['mqSlot'][1];

                        //生成分槽列表
                        foreach ($list as &$v) {
                            //消息键槽
                            $slot = "{$name}.{{$v}}";
                            //消息有序集
                            $sKey = "of_accy_com_mq_redis::sort::{$slot}";
                            //消息有序集
                            $cKey = "of_accy_com_mq_redis::fail::{$slot}";
                            //统计数量
                            $child = &$index['mqSlot'][$slot];

                            //统计消息总数
                            $params['total'] && $index['total'] += $child['total'] = $redis->zCount(
                                $sKey, '-inf', '+inf'
                            );
                            //统计可消费数
                            $params['overdue'] && $index['overdue'] += $child['overdue'] = $redis->zCount(
                                $sKey, '-inf', $time
                            );
                            //统计失败总数
                            $params['failNo'] && $index['failNo'] += $child['failNo'] = $redis->zCount(
                                $cKey, '-inf', '+inf'
                            );

                            //读取失败消息列表
                            if ($params['failed'] > 0) {
                                //读取消息数据
                                $index['failed'] += $redis->zRevRangeByScore($cKey, '+inf', 0, array(
                                    'withscores' => true, 'limit' => array(0, $params['failed'])
                                ));
                                //倒序后取前几位
                                arsort($index['failed']);
                                $index['failed'] = array_slice($index['failed'], 0, $params['failed'], true);
                            }

                            //读取近期消息列表
                            if ($params['recent'] > 0) {
                                //读取消息数据
                                $index['recent'] += $redis->zRangeByScore($sKey, 0, '+inf', array(
                                    'withscores' => true, 'limit' => array(0, $params['recent'])
                                ));
                                //正序后取前几位
                                asort($index['recent']);
                                $index['recent'] = array_slice($index['recent'], 0, $params['recent'], true);
                            }
                        }

                        //读取失败属性汇总列表
                        foreach ($index['failed'] as $k => &$v) {
                            $v = array();
                            //消息数据集
                            $mark = "of_accy_com_mq_redis::data::{{$name}.{$k}}";
                            //获取消息属性失败
                            if (!$index['msgList'][$k] = $redis->hGetAll($mark)) {
                                //即将过期的无效列表
                                unset($index['msgList'][$k], $index['failed'][$k]);
                            }
                        }

                        //读取近期属性汇总列表
                        foreach ($index['recent'] as $k => &$v) {
                            $v = array();
                            //未读取过
                            if (!isset($index['msgList'][$k])) {
                                //消息数据集
                                $mark = "of_accy_com_mq_redis::data::{{$name}.{$k}}";
                                //获取消息属性失败
                                if (!$index['msgList'][$k] = $redis->hGetAll($mark)) {
                                    //即将过期的无效列表
                                    unset($index['msgList'][$k], $index['recent'][$k]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 描述 : 初始化适配器
     * 作者 : Edgar.lee
     */
    protected function _init($fire) {
        $params = &$this->params;

        //设置虚拟机
        isset($params['params']['vHost']) && $this->vHost = $params['params']['vHost'];
        //防止生产节点配置错误导致数据丢失
        $index = &$params['params']['mqSlot'];
        empty($index[0]) && $index[0] = array(0);
        empty($index[1]) && $index[1] = $index[0];
        //复制连接, 不用随机值是防止在多次工作中redis连接数爆掉
        $this->kvPool = 'of_accy_com_mq_redis::' . $fire['pool'];
        of_base_com_kv::pool($this->kvPool, of_base_com_kv::pool($params['params']['kvPool']));
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
        //当前时间戳
        $time = time();
        //引用待处理
        $wait = &$this->waitList;

        foreach ($msgs as &$v) {
            $keys = &$v['keys'];
            $name = "{$this->vHost}.{$v['queue']}.{$keys[0]}";
            $mark = "{$name}.{$keys[1]}";

            //删除数据 ?: 增改数据
            $wait[$mark] = $v['data'] === null ? array(
                'mode' => 'del',
                'data'  => array(
                    'name' => $name,
                    'time' => $time,
                    'msgId' => $keys[1],
                ),
                'oMsg' => &$v
            ) : array(
                'mode'  => 'set',
                'data'  => array(
                    'name' => $name,
                    'time' => $time + $keys[2],
                    'msgId' => $keys[1],
                    'data' => json_encode($v['data'])
                ),
                'oMsg' => &$v
            );
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
        //消费分槽名列表
        static $slotList = null;
        //升级的队列槽名
        static $upMqSlot = array();

        //初始化静态变量
        if ($slotList === null) {
            //消费分槽配置
            $slotList = $this->params['params']['mqSlot'][1];
            //有序队列名
            $name = "{$this->vHost}.{$data['queue']}.{$data['key']}";
            //生产分槽{分槽项 : 数字, ...}
            $list = array_flip($this->params['params']['mqSlot'][0]);
            //生成分槽列表
            foreach ($slotList as &$v) {
                //消息队列槽
                $slot = $name . '.{' . $v . '}';
                //记录到升级队列槽名中
                isset($list[$v]) || $upMqSlot[$slot] = true;
                //记录到消费分槽名列表
                $v = $slot;
            }
        }

        //设置 120 分钟超时
        ini_set('max_execution_time', 7200);
        //读取redis对象
        $this->redis = of_base_com_kv::link($this->kvPool);
        //消费分槽总数, 循环最大次数
        $sTotal = $doLoop = count($slotList);
        //消费分槽计数
        $sCount = &$this->slotCount;
        //有序队列名
        $name = "{$this->vHost}.{$data['queue']}.{$data['key']}";
        //唯一编码
        $uniqid = of_base_com_str::uniqid();
        //消息数据
        $msgs = array();

        do {
            //当前时间戳
            $time = time();
            //120 + 5分钟过期
            $expTime = $time + 7500;
            //获取消息数据偏移量
            $limit = $this->msgLimit($time, $data['this']['cCid'], $data['lots'], $sTotal);
            //剩余消息数量
            $size = $data['lots'] - count($msgs);

            //固定槽节点
            if ($limit['slot'] > -1) {
                $nTotal = $doLoop = 1;
                $cSlot = $limit['slot'];
            } else {
                $nTotal = $sTotal;
                //分槽移位
                $sCount = ($cSlot = $sCount % $sTotal) + 1;
            }

            //消息队列槽
            $slot = &$slotList[$cSlot];
            //是否为升级消息, true=升级中, false=正常消息
            $isUp = isset($upMqSlot[$slot]);
            //消息有序集
            $sKey = "of_accy_com_mq_redis::sort::{$slot}";
            //消息统计集
            $cKey = "of_accy_com_mq_redis::fail::{$slot}";

            //读取消息主键列表, 升级消息读出所有信息来更换消息槽
            $list = $this->zRangeByScore($sKey, 0, $isUp ? '+inf' : $time, array(
                'limit' => array($limit['sort'], $size)
            ));
            //未查出消息 || 重置消息偏移量
            $list || $this->msgLimit();
            $list ? $doLoop = $nTotal : --$doLoop;

            //变量消息主键
            foreach ($list as &$id) {
                //哈希表ID
                $mark = "of_accy_com_mq_redis::data::{{$name}.{$id}}";
                //监听消息数据
                $this->watch($mark);
                //获取消息属性, 不存在的数据返回空数组, 若异常返回null
                $attr = $this->hGetAll($mark);

                //消息存在
                if ($attr) {
                    $attr += array('syncCount' => 0, 'lockTime'  => 0, 'planTime' => 0, 'slot' => $slot);
                    $attr['lockTime'] < $attr['planTime'] && $attr['lockTime'] = $attr['planTime'];

                    //消息未执行
                    if ($attr['lockTime'] <= $time) {
                        //消息可执行
                        if ($attr['planTime']) {
                            //开始事务
                            $this->multi();
                            //锁定消息数据集
                            $this->hMset($mark, array(
                                'syncLevel' => $attr['syncLevel'] + 1,
                                'syncCount' => $attr['syncCount'] + 1,
                                'lockTime'  => $expTime,
                                'lockMark'  => $uniqid
                            ));
                            //执行成功 exec!=false && exec.0!=false
                            if (($isOk = $this->exec()) && $isOk[0]) {
                                //合并消息
                                $msgs[$attr['msgId']] = $attr;
                                //锁定消息序列集
                                $this->zAdd($sKey, $expTime, $id);
                            }
                        //消息标记删除
                        } else {
                            $this->delMqInfo($mark, $slot, $id);
                        }
                    //消息正在执行 && 升级中的消息
                    } else if ($isUp) {
                        //开始事务
                        $this->multi();
                        //添加到有序集(集合名, 消息ID, 消费时间)
                        $info = $this->setMqSort($name, $id, $attr['lockTime']);
                        //修改消息的统计集次数
                        $attr['syncCount'] && $this->zAdd(
                            "of_accy_com_mq_redis::fail::{$info['slot']}",
                            $attr['syncCount'],
                            $id
                        );
                        //提交事务
                        $this->exec();

                        //确定确实挪到新槽中
                        if ($info['slot'] !== $slot && $this->zScore($info['sKey'], $id)) {
                            //移除升级消息的有续集
                            $this->zRem($sKey, $id);
                            //移除升级消息的统计集
                            $this->zRem($cKey, $id);
                        }

                        //设置 120 分钟超时
                        ini_set('max_execution_time', 7200);
                    //消息正在执行(由多台服务器时间不同或多任务同时读取导致) && 正常消息
                    } else {
                        //开始事务, 若为集群或分布式, 则此命令可能再之后任何一个时间执行
                        $this->multi();
                        //修改消息的序列时间为锁定时间, 减少无意的查询, 同时影响getMqInfo信息延迟性
                        $this->zAdd($sKey, $attr['lockTime'] - $time > 600 ? $time + 600 : $attr['lockTime'], $id);
                        //提交事务
                        $this->exec();
                    }
                //消息不存在补全动作(由读取异常或zRem操作回滚造成)
                } else if (is_array($attr)) {
                    //开始事务
                    $this->multi();
                    //移除不存在消息的有续集
                    $this->zRem($sKey, $id);
                    //移除不存在消息的统计集
                    $this->zRem($cKey, $id);
                    //提交事务
                    $this->exec();
                }
            }
        } while ($doLoop && count($msgs) < $data['lots']);

        //执行成功
        if ($msgs) {
            //记录加锁编码
            $data['extra'] = array('lock'  => $uniqid);
            //解析消费数据
            foreach ($msgs as &$v) {
                $data['msgs'][$v['msgId']] = array(
                    'msgId' => $v['msgId'],
                    'count' => $v['syncLevel'],
                    'data'  => json_decode($v['data'], true),
                    'uTime' => $v['updateTime'],
                );
                $data['extra']['msgs'][] = array(
                    'msgId' => $v['msgId'],
                    'slot' => $v['slot']
                );
            }

            //回调结果
            $return = self::callback($call, $data);

            //执行成功
            if ($return === true) {
                //读取redis对象
                $this->redis = of_base_com_kv::link($this->kvPool);
                //当前时间戳
                $time = time();

                foreach ($data['extra']['msgs'] as &$v) {
                    //哈希表ID
                    $mark = "of_accy_com_mq_redis::data::{{$name}.{$v['msgId']}}";
                    //消息有效集
                    $sKey = "of_accy_com_mq_redis::sort::{$v['slot']}";
                    //消息统计集
                    $cKey = "of_accy_com_mq_redis::fail::{$v['slot']}";
                    //删除失败重试次数
                    $deTry = 100;

                    do {
                        //是否成功, true=成功, false=重试
                        $isOk = true;
                        //移除成功消息的统计集
                        $this->zRem($cKey, $v['msgId']);
                        //监听消息数据
                        $this->watch($mark);
                        //获取消息属性
                        $attr = $this->hmGet($mark, array('lockTime', 'lockMark', 'planTime', 'syncLevel'));

                        //是自身消费的消息
                        if ($attr['lockMark'] === $uniqid) {
                            //标记删除 || 正常消费
                            if (empty($attr['planTime']) || $attr['syncLevel'] > 0) {
                                $isOk = $this->delMqInfo($mark, $v['slot'], $v['msgId']);
                            //锁定时间有效 && 未标记删除 && 执行时重新设置消息 syncLevel='0'
                            } else if ($attr['lockTime'] > $time) {
                                $this->hDel($mark, 'lockTime');
                                $this->hDel($mark, 'lockMark');
                                //修改消息有续集的时间(有机率产生无效插入会在消费读取时删掉)
                                $isOk = is_int($this->zAdd($sKey, $attr['planTime'], $v['msgId']));
                            }
                        }

                        $this->unwatch();
                    } while (!$isOk && --$deTry > 0);

                    //删除失败
                    $isOk || trigger_error('Failed to consume message: ' . $mark);
                }
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
     *          "msgs"  : 消息数据列表
     *      }
     * 作者 : Edgar.lee
     */
    protected function _quit(&$data) {
        //读取redis对象
        $this->redis = of_base_com_kv::link($this->kvPool);
        //引用扩展
        $extra = &$data['extra'];
        //有序队列名
        $name = "{$this->vHost}.{$data['queue']}.{$data['key']}";
        //当前时间戳
        $time = time();

        foreach ($extra['msgs'] as &$v) {
            //哈希表ID
            $mark = "of_accy_com_mq_redis::data::{{$name}.{$v['msgId']}}";
            //消息有效集
            $sKey = "of_accy_com_mq_redis::sort::{$v['slot']}";
            //消息统计集
            $cKey = "of_accy_com_mq_redis::fail::{$v['slot']}";
            //获取消息属性
            $attr = $this->hmGet($mark, array('lockTime', 'lockMark', 'syncCount', 'syncLevel'));

            //是自身消费的消息 && 锁定时间有效
            if ($attr['lockMark'] === $extra['lock'] && $attr['lockTime'] > $time + 300) {
                //重试时间语句
                $delay = $time + (isset($extra['delay']) ?
                    $extra['delay'] :
                    ($attr['syncLevel'] > 0 ? $attr['syncLevel'] * 300 : 300)
                );
                //数据集尝试次数
                $hTry = 100;

                do {
                    //监听消息数据
                    $this->watch($mark);
                    //读取计划时间, 存在为字符串, 不存在为false
                    $seOk = $this->hGet($mark, 'planTime');

                    //消息数据集存在
                    if ($seOk) {
                        $this->multi();
                        //更新计划时间
                        $this->hMset($mark, array('planTime' => $delay));
                        //解锁消息数据集
                        $this->hDel($mark, 'lockTime');
                        $this->hDel($mark, 'lockMark');
                        //更新数据集过期时间
                        $this->expire($mark, $delay + 2592000);
                        //提交事务
                        ($seOk = $this->exec()) && $seOk = $seOk[0];
                    } else {
                        $this->unwatch();
                        //命令执行成功, 数据集不存在
                        if ($seOk === false) break ;
                    }
                } while (!$seOk && --$hTry);

                //数据集存在
                if ($seOk) {
                    //添加到有序集(集合名, 消息ID, 消费时间)
                    $info = $this->setMqSort($name, $v['msgId'], $delay);
                    //操作成功
                    if ($info['isOk']) {
                        //新消息统计集
                        $temp = "of_accy_com_mq_redis::fail::{$info['slot']}";
                        //修改消息的统计集次数
                        $this->zAdd($temp, $attr['syncCount'], $v['msgId']);

                        //消息数据集切换到新槽
                        if ($info['sKey'] !== $sKey) {
                            //移除成功消息的有续集
                            $this->zRem($sKey, $v['msgId']);
                            //移除成功消息的统计集
                            $this->zRem($cKey, $v['msgId']);
                        }
                    }
                }
            }
        }
    }

    /**
     * 描述 : 开启事务
     * 作者 : Edgar.lee
     */
    protected function _begin() {
        return true;
    }

    /**
     * 描述 : 提交事务
     * 参数 :
     *      type : "before"=提交开始回调, "after"=提交结束回调
     * 作者 : Edgar.lee
     */
    protected function _commit($type) {
        //读取redis对象
        $this->redis = of_base_com_kv::link($this->kvPool);

        if ($type === 'after') {
            //当前时间戳
            $time = time();
            //待处理列表
            $wait = &$this->waitList;
            //失败结果集
            $result = array();

            //遍历待处理
            foreach ($wait as $k => &$v) {
                //数据有效集
                $mark = "of_accy_com_mq_redis::data::{{$k}}";
                //引用数据
                $index = &$v['data'];

                //设置消息
                if ($v['mode'] === 'set') {
                    //数据集和有续集是否执行成功, true=成功, false=失败
                    $heOk = $seOk = false;
                    //数据集和有续集尝试次数
                    $hTry = $sTry = 100;

                    do {
                        //开始事务, 不使用事务更新会导致事务中hMset更新失败
                        $this->multi();
                        //添加到哈希表
                        $this->hMset($mark, array(
                            'msgId'      => $index['msgId'],
                            'data'       => $index['data'],
                            'syncLevel'  => 0,
                            //计划执行时间戳
                            'planTime'   => $index['time'],
                            'updateTime' => $time
                        ));
                        $this->expireAt($mark, $index['time'] + 2592000);
                        //开始事务, 成功返回[true, true]
                        ($heOk = $this->exec()) && $heOk = $heOk[0];
                    } while (!$heOk && --$hTry);

                    do {
                        //监听消息数据
                        $this->watch($mark);
                        //读取计划与锁定时间, [存在为字符串, 不存在为false]
                        $seOk = $this->hmGet($mark, array('planTime', 'lockTime'));
                        //判断是否更新, 非计划时间 || 被锁定 时不用更新
                        $seOk = $index['time'] != $seOk['planTime'] || $index['time'] <= $seOk['lockTime'];

                        if ($seOk) {
                            $this->unwatch();
                        } else {
                            $this->multi();
                            //添加到有序集(集合名, 消息ID, 消费时间)
                            $this->setMqSort($index['name'], $index['msgId'], $index['time']);
                            //提交事务
                            ($seOk = $this->exec()) && $seOk = is_int($seOk[0]);
                        }
                    } while (!$seOk && --$sTry);

                    //插入失败记录错误日志(数据 && 序列)成功 || 记录错误
                    $heOk && $seOk || $result[] = &$v['oMsg'];
                //标记删除成功
                } else if (is_int($temp = $this->hDel($mark, 'planTime'))) {
                    //有实际标记 && 尽快删除(集合名, 消息ID, 消费时间)
                    $temp && $this->setMqSort($index['name'], $index['msgId'], $index['time']);
                //标记删除失败, 记录错误
                } else {
                    $result[] = &$v['oMsg'];
                }
            }

            //重置待处理列表
            $wait = array();
            //返回失败结果集
            return $result;
        } else {
            //对象=连接成功, false=连接失败
            return !!$this->redis;
        }
    }

    /**
     * 描述 : 事务回滚
     *      type : "before"=回滚开始回调, "after"=回滚结束回调
     * 作者 : Edgar.lee
     */
    protected function _rollBack($type) {
        $type === 'after' && $this->waitList = array();
        return true;
    }

    /**
     * 描述 : 读取或重置消息偏移量, _fire辅助方法
     * 作者 : Edgar.lee
     */
    private function &msgLimit(&$time = 0, &$cCid = 0, &$lots = 1, &$tSlot = 1) {
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
            //读取正在执行并发数据, 第一位为0
            $count = of_base_com_timer::data(null, 1);

            //当前并发位置, 第一位为0
            $nSort = $count['info'][$cCid]['sort'];
            //总并发数量
            $count = count($count['info']);
            //固定节点线
            $fLine = $count - $count % $tSlot;

            //固定节点
            if ($nSort < $fLine) {
                //计算所属固定节点
                $slot = $nSort % $tSlot;
                //按所属节点计算所处的位置
                $sort = intval($nSort / $tSlot);
            //动态节点
            } else {
                //当期为动态节点
                $slot = -1;
                //按动态节点计算所处的位置
                $sort = intval($fLine / $tSlot) + $nSort - $fLine;
            }

            $index['limit'] = array(
                //所属槽节点, -1=动态节点, -1<固定节点
                'slot' => $slot,
                //计算消息数据偏移量(阶加公式=lots * (sort+1) * 并行概率 * sort/2
                'sort' => (int)ceil($lots * ($sort + 1) * 0.7 * $sort / 2),
            );
        }

        return $index['limit'];
    }

    /**
     * 描述 : 设置消息有续集
     * 作者 : Edgar.lee
     */
    private function setMqSort(&$name, &$msgId, &$time) {
        //计算分槽归属, 公式: hash(i) = hash(i-1) << 5(33) + ord(str[i])
        $slot = 5381;
        //引用分槽配置
        $slotList = &$this->params['params']['mqSlot'][0];
        //生产总分槽数
        $total = count($slotList);
        //md5目的均匀散列, <<5防止叠加干扰(2+1=1+2), 0x7FFFFFFF使32与64位结果相同
        for ($i = 0, $j = md5($msgId); $i < 32; ++$i) $slot += ($slot << 5 & 0x7FFFFFFF) + ord($j[$i]);
        //取模, 0x7FFFFFFF为32位最大值
        $slot = ($slot & 0x7FFFFFFF) % $total;

        //组合队列槽名
        $slot = $name . '.{' . $slotList[$slot] . '}';
        //消息有效集
        $sKey = "of_accy_com_mq_redis::sort::{$slot}";
        //添加到有序集(集合名, 消费时间, 消息ID), 返回新增int(0|1)
        $isOk = is_int($this->zAdd($sKey, $time, $msgId));

        return array('slot' => $slot, 'sKey' => $sKey, 'isOk' => $isOk);
    }

    /**
     * 描述 : 删除消息信息
     * 作者 : Edgar.lee
     */
    private function delMqInfo(&$mark, &$slot, &$msgId, &$deTry = 100) {
        //消息有效集
        $sKey = "of_accy_com_mq_redis::sort::{$slot}";
        //消息统计集
        $cKey = "of_accy_com_mq_redis::fail::{$slot}";

        //开始事务, 上层已监听$mark
        $this->multi();
        //标记删除, 之后该消息锁时间可能会被并行进程推后10分钟
        $this->hDel($mark, 'planTime');
        //若此时有覆盖消息, 将标记失败
        $this->exec();

        //移除消息的统计集 && 移除消息的有序集, 按fail->sort->data的顺序删除是安全的(data会自动过期)
        $isOk = is_int($this->zRem($cKey, $msgId)) && is_int($this->zRem($sKey, $msgId));

        do {
            //监听消息数据
            $this->watch($mark);
            //消息有效(不存在为false), 解锁
            if ($exist = $this->hGet($mark, 'planTime')) {
                //开始事务
                $this->multi();
                //删除锁定时间
                $this->hDel($mark, 'lockTime');
                //删除锁定标记
                $this->hDel($mark, 'lockMark');
                //提交事务(若失败, 则消息被添加, 需确认执行时间)
                ($temp = $this->exec()) && $temp = is_int($temp[0]);
                //添加到有序集(集合名, 消费时间, 消息ID), 返回新增int(0|1)
                $exist = $temp ? is_int($this->zAdd($sKey, $exist, $msgId)) : $temp;
            //执行失败
            } else if ($exist === null) {
                $this->unwatch();
            //删除有续集
            } else {
                //开始事务
                $this->multi();
                //移除消息的数据集
                $this->del($mark);
                //提交事务
                ($exist = $this->exec()) && $exist = is_int($exist[0]);
            }
        } while (!$exist && --$deTry > 0);

        return $isOk;
    }

    /**
     * 描述 : redis方法重载
     * 作者 : Edgar.lee
     */
    public function __call($func, $argv) {
        try {
            //客户端与服务端版本不匹配时可能导致误抛异常
            $result = call_user_func_array(array($this->redis, $func), $argv);
        } catch (Exception $e) {
            try {
                //通过重试可以判断是否真的异常并返回预期数据
                $result = call_user_func_array(array($this->redis, $func), $argv);
            } catch (Exception $r) {
                $result = null;
                trigger_error('Function failed to execute: ' . $e->getMessage());
            }
        }

        return $result;
    }
}