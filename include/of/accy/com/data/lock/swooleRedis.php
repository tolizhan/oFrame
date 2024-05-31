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
        static $redis, $unlock;
        //返回结果
        $result = 0;
        //初始化解锁时间
        $unlock || $unlock = of::config('_of.com.data.lock.params.unlock', 15);
        //swoole共享数据 {"init" : 初始标识, "sha1" : 脚本哈希, "outs" : 有效时间, "data" : {锁标识 : 加锁键, ...}}
        $share = &swoole::data(__CLASS__, array('init' => 0, 'sha1' => '', 'outs' => $unlock, 'data' => array()));

        //二次加锁
        if (isset($data['mark'])) {
            //移除维持锁
            unset($share['data'][$data['mark']]);
            //解锁操作
            $result = $redis->evalsha($share['sha1'], array($nMd5, $data['mark'], 3, $share['outs'], $data['lock']), 1);
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

        //加锁操作
        if (($data['lock'] = $lock & 3) < 3) {
            try {
                //加锁模式, 0=阻塞模式, 4=加锁模式
                $mode = $lock & 4;
                //加锁成功键
                $temp = "of_accy_com_data_lock_swooleRedis::done::{{$nMd5}}#{$data['mark']}";
                //循环加锁
                do {
                    //加锁成功 || 尝试加锁
                    if (
                        ($result = $redis->evalsha(
                            $share['sha1'],
                            //加锁键, 锁标记, 锁类型("1"=共享锁, "2"=独享锁), 有效期, 尝试锁("0"=阻塞, "4"=尝试)
                            array($nMd5, $data['mark'], $data['lock'], $share['outs'], $mode),
                            1
                        )) ||
                        $mode
                    ) {
                        break ;
                    //阻塞加锁
                    } else if ($result = $redis->blPop($temp, 10)) {
                        break ;
                    }
                } while (true);
            } catch (Throwable $e) {
            }

            //加锁成功 ? 标记维持锁 : 阻塞加锁检查连接
            $result ? $share['data'][$data['mark']] = $nMd5 : ($mode || self::link($redis));
        }


        //加锁结果, false=失败, true=成功
        return !!$result;
    }

    /**
     * 描述 : 维持锁
     * 作者 : Edgar.lee
     */
    public static function _renew() {
        //不限制内存
        ini_set('memory_limit', -1);
        //注册退出信号
        of_base_com_timer::exitSignal();
        //swoole共享数据
        $share = &swoole::data(__CLASS__);
        //计算协程调度延迟时间
        $time = time();
        //锁续签时间
        $renew = of::config('_of.com.data.lock.params.renew', 5);

        do {
            try {
                //获取redis连接
                self::link($redis);
                //连接有效
                if ($redis) {
                    //遍历续签锁过期时间
                    foreach ($share['data'] as $k => &$v) {
                        $redis->evalsha($share['sha1'], array($v, $k, 4, $share['outs']), 1);
                    }
                }
            } catch (Throwable $e) {
            }

            //稍后重新续签
            sleep($renew);
            //检查退出信号
            $share['data'] || of_base_com_timer::exitSignal();

            //执行一轮后时间
            $temp = time();
            //续签超时报错
            $temp - $time >= $share['outs'] && trigger_error(
                'The data lock delay reaches ' . ($temp - $time) . ' seconds ' .
                '("_of.com.data.lock.params.unlock" is ' . $share['outs'] . ' seconds)'
            );
            //重新计算延迟时间
            $time = $temp;
        } while (true);
    }

    /**
     * 描述 : 获取redis连接
     * 作者 : Edgar.lee
     */
    private static function link(&$redis, $load = false, &$sha1 = '') {
        //检查连接有效性
        try {
            $temp = 1;
            $redis && $temp = $redis->time();
        } catch (Throwable $e) {
        }

        //重新连接redis
        is_array($temp) || $redis = of_base_com_kv::link(of::config('_of.com.data.lock.params.kvPool', 'default'));
        //重新加载锁脚本, 尝试加载 && redis有效
        if ($load && $redis) {
            //脚本sha1值
            $sha1 || $sha1 = sha1($text = file_get_contents(__DIR__ . '/swooleRedis.bin'));
            //脚本已存在 || 加载脚本
            $redis->script('exists', $sha1)[0] || $sha1 = $redis->script(
                'load', isset($text) ? $text : file_get_contents(__DIR__ . '/swooleRedis.bin')
            );
        }
    }
}