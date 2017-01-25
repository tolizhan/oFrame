<?php
return array(
    'properties' => array(                                  //扩展相关属性
        'name'        => '单点登录系统对接LDAP',            //显示给用户的扩展名
        'version'     => '2.0',                             //当前版本号
        'description' => '所有帐号来源均为LDAP系统',        //简单说明扩展的功能
        'changeLog'   => array(                             //按时间升序说明每版本的更新内容
            '2.0' => '添加短信提醒',
            '1.0' => '更新说明',
        )
    ),
    //'options' => 'main_demo::test',                       //选项界面,当点击扩展管理界面中的选项按钮时会调用main_demo对象的test方法
    'matches' => array(
        'main_main::mgmt' => array(
            'of_base_sso_main::index',                      //SSO 登录及管理界面
        ),
        'main_main::login' => array(                        //注入登录信息
            'of_base_sso_api::check'
        ),
        'main_main::permit' => array(                       //用户权限变更强制修改密码
            'of_base_com_com::paging'
        ),
        'main_main::syncUsers' => array(                    //手动同步帐号, c=of_ex&a=syncUsers&e=ssoSyncLdap
            'of_ex::syncUsers'
        )
    )
);