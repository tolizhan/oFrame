<?php
return array(
    //扩展相关属性
    'properties' => array(
        //显示给用户的扩展名
        'name'        => '扩展名称',
        //当前版本号
        'version'     => '1.0',
        //简单说明扩展的功能
        'description' => '描述',
        //按时间升序说明每版本的更新内容
        'changeLog'   => array(
            '1.0' => '更新说明'
        )
    ),
    //指定备份数据库
    'database' => array(
        //配置文件连接池 => 其它参数
        'default' => array()
    ),
    //选项界面,当点击扩展管理界面中的选项按钮时会调用main_demo对象的test方法
    'options'  => 'main_demo::test',
    'update'   => array(
        /*接受一个数组参数 {
            'callMsgFun' : 输出消息(call_user_func 支持的格式)
            'nowVersion' : 升级时为旧版本号,新安装为null,
            'newVersion' : 更新后版本,卸载为null,
            'position'   : 触发位置(before或after),
            'state'      : 安装状态,可修改false或字符串打印信息 以停止安装(before)或改变结果(after)
        }*/
        //更新前调用
        'before' => 'main_demo::updateBeforeOrAfter',
        //更新后调用
        'after'  => 'main_demo::updateBeforeOrAfter'
    ),
    'matches' => array(
        //当访问以下路径时会调用main_demo对象的test方法
        'main_demo::test' => array(
            //匹配常规页面,用ADMIN_DIR匹配后台地址
            'ctrl_pageExtension::index',
            'ctrl_index::viewTest'
        ),
        //拦截扩展演示页面
        'main_demo::extendDemo' => array(
            'ctrl_index::extendDemo'
        )
    )
);