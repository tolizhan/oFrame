<?php
return array(
    //重写框架配置
    '_of' => array(
        'view' => array(
            'tplPath' => '/demo/view'
        ),
        'preloaded' => array(
            //语言包支持
            'd0' => 'of_base_language_packs',
            //扩展支持
            'd1' => 'of_base_extension_match',
            //初始化演示界面
            'd2' => 'demo_list',
        ),
        'extension' => array(
            'path' => '/demo/extensions'
        ),
        'com' => array(
            'timer' => array(
                //计划任务
                'cron' => array(
                    //静态计划任务文件
                    'path'   => '/demo/timer/crontab.php',
                    //k-v 池
                    'kvPool' => 'default'
                )
            ),
            'mq'    => array(
                //消息队列池
                /*'exchange' => array(
                    //适配器
                    'adapter' => 'mysql',
                    //调度参数
                    'params'  => array(
                        'dbPool' => 'default'
                    ),
                    //绑定事务数据库
                    'bindDb'  => 'default',
                    //队列列表
                    'queues'  => '/demo/queue/queue.php'
                ) // */
            )
        )
    )
);