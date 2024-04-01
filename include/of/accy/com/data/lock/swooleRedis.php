<?php
/**
 * 描述 : swoole下redis模式
 * 作者 : Edgar.lee
 */
class of_accy_com_data_lock_swooleRedis {
    /**
     * 描述 : 
     * 参数 :
     *      name : 锁通道标识
     *      lock : 文件加锁方式 1=共享锁, 2=独享锁, 3=解除锁, 4=非堵塞(LOCK_NB)
     *      nMd5 : 加锁文件标识
     *     &data : 锁资源存储数据
     * 返回 :
     *      true=成功, false=失败
     * 作者 : Edgar.lee
     */
    public static function _lock($name, $lock, $nMd5, &$data) {
        //redis连接
        static $redis;
        //加锁模式, 0=阻塞模式, 4=加锁模式
        $mode = $lock & 4;
        //返回结果
        $result = false;
        //swoole共享数据
        $share = &swoole::data(__CLASS__, array('init' => 0, 'sha1' => '', 'data' => array()));

        //二次加锁
        if (isset($data['mark'])) {
            //移除维持锁
            unset($share['data'][$data['mark']]);
            //非加锁 || 先解锁
            ($lock & 3) === 3 || $redis->evalsha($share['sha1'], array($nMd5, $data['mark'], 3), 1);
        //标记初始化
        } else {
            //初始化环境
            if ($redis === null) {
                //开启redis连接
                self::link($redis, !$share['sha1'], $share['sha1']);
                //启动维持锁任务
                ++$share['init'] === 1 ? swoole::fork('of_accy_com_data_lock_swooleRedis::_renew') : $share['init'] = 1;
            }
            //生成锁标识
            $data['mark'] = of_base_com_str::uniqid();
        }

        try {
            //循环加锁
            do {
                //加锁成功 || 尝试加锁
                if (
                    ($result = !!$redis->evalsha($share['sha1'], array($nMd5, $data['mark'], $lock & 3, $mode), 1)) ||
                    $mode
                ) {
                    break ;
                //阻塞加锁
                } else {
                    usleep(50000);
                }
            } while (true);
        } catch (Throwable $e) {
        }

        //加锁操作 && (加锁成功 ? 标记维持锁 : 检查连接)
        ($lock & 3) < 3 && ($result ? $share['data'][$data['mark']] = $nMd5 : self::link($redis));

        //加锁结果, false=失败, true=成功
        return $result;
    }

    /**
     * 描述 : 维持锁
     * 作者 : Edgar.lee
     */
    public static function _renew() {
        //注册退出信号
        of_base_com_timer::exitSignal();
        //swoole共享数据
        $share = &swoole::data(__CLASS__);

        do {
            try {
                //获取redis连接
                self::link($redis, true, $share['sha1']);
                //连接有效
                if ($redis) {
                    //遍历续签锁过期时间
                    foreach ($share['data'] as $k => &$v) {
                        $redis->evalsha($share['sha1'], array($v, $k, 4), 1);
                    }
                }
            } catch (Throwable $e) {
            }

            //稍后重新续签
            sleep(1);
            //检查退出信号
            $share['data'] || of_base_com_timer::exitSignal();
        } while (true);
    }

    /**
     * 描述 : 获取redis连接
     * 作者 : Edgar.lee
     */
    private static function link(&$redis, $load = false, &$sha1 = null) {
        //检查连接有效性
        try {
            $temp = 1;
            $redis && $temp = $redis->time();
        } catch (Throwable $e) {
        }

        //重新连接redis
        is_array($temp) || $redis = of_base_com_kv::link(of::config('_of.com.data.lock.params.kvPool', 'default'));
        //重新加载锁脚本, 尝试加载 && redis有效 && 需要加载锁脚本
        if ($load && $redis && (!$sha1 || !$redis->script('exists', $sha1)[0])) {
            $sha1 = $redis->script('load', file_get_contents(__DIR__ . '/swooleRedis.bin'));
        }
    }
}