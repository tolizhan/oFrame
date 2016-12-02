<?php
return array(
    '_of' => array(                                                                     //重写框架配置
        'view' => array(
            'tplPath' => '/demo/view'
        ),
        'preloaded' => array(
            'd0' => 'of_base_language_packs',                                           //语言包支持
            'd1' => 'of_base_extension_match',                                          //扩展支持
            'd2' => 'demo_list',                                                        //初始化演示界面
        ),
        'extension' => array(
            'path' => '/demo/extensions'
        ),
        'com' => array(
            'timer' => array(
                'crontab' => '/demo/timer/crontab.php'
            )
        )
    )
);