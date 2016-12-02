<?php
return array(
    'properties' => array(                                  //扩展相关属性
        'name'        => 'MYSQL性能分析工具',               //显示给用户的扩展名
        'version'     => '1.0',                             //当前版本号
        'description' => '是对错误日志的扩展',              //简单说明扩展的功能
        'changeLog'   => array(                             //按时间升序说明每版本的更新内容
            '1.0' => '更新说明'
        )
    ),
    'matches' => array(
        //初始化SQL监听
        'main_main::initSql' => array(
            //所有调度
            '@@',
        ),
        //注入错误日志
        'main_main::logMsg' => array(
            'of_base_error_tool::index',
        ),
    ),
    'config' => array(
        //异常时间, 毫秒, SQL运行时间大于此值时会被保存
        'eMtime' => 1000
    )
);