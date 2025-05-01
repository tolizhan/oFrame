<?php
//清除预加载配置
unset($of['preloaded']);

return array(
    //重写框架配置
    '_of' => array(
        //修改视图层目录
        'view'      => array(
            'tplPath' => '/demo/view'
        ),
        //修改预加载
        'preloaded' => array(
            //快捷集成
            'of_base_link_setup',
            //错误日志
            'of_base_error_writeLog',
            //session块
            'of_base_session_base',
            //语言包支持
            'of_base_language_packs',
            //防火墙
            'of_base_firewall_main',
            //xss 防御
            'of_base_xssFilter_main',
            //扩展支持
            'of_base_extension_match',
            //html模板引擎, 实现UI, 开发人员分离
            'of_base_htmlTpl_engine',

            //初始化演示界面
            'd2' => 'demo_model_list',
            //加载 bootstrap
            'd3' => 'demo_file_bsui_setup',
        ),
        //防火墙
        'firewall'    => array(
            'network' => '/demo/config/network.php'
        ),
        //修改扩展路径
        'extension' => array(
            'path' => '/demo/extend',
            'save' => '/demo/extend',
        ),
        //测试用例库
        'test' => array(
            'cPath' => '/demo/test/story'
        ),
        'com' => array(
            //计划任务
            'timer' => array(
                //静态任务
                'cron' => array(
                    //静态计划任务文件
                    'path'   => '/demo/config/crontab.php'
                )
            ),
            'mq'    => array(
                /* 消息队列池
                'exchange' => array(
                    //适配器
                    'adapter' => 'mysql',
                    //调度参数
                    'params'  => array(
                        'dbPool' => 'default'
                    ),
                    //绑定事务数据库
                    'bindDb'  => 'default',
                    //队列列表
                    'queues'  => '/demo/config/queue.php'
                ) // */
            )
        )
    )
);