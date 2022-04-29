<?php
return array(
    //扩展相关属性
    'properties' => array(
        //显示给用户的扩展名
        'name'        => '响应CORS预检请求',
        //当前版本号
        'version'     => '1.0',
        //简单说明扩展的功能
        'description' => 'chrome98 加入公网访问私网发送CORS预检请求',
        //按时间升序说明每版本的更新内容
        'changeLog'   => array(
            '1.0' => '更新说明'
        )
    ),
    'matches' => array(
        //当访问以下路径时会调用main_demo对象的test方法
        'main_main::check' => array(
            //匹配常规页面,用ADMIN_DIR匹配后台地址
            '@.@',
        )
    )
);