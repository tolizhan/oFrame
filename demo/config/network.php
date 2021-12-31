<?php
return array(
    //获取客户真实IP, 使用nginx反向代理
    'clientIp' => array('HTTP_X_REAL_IP', 'REMOTE_ADDR'),
    //匹配规则
    'control'  => array(
        '演示防火墙拦截IP' => array(
            'matches' => array(
                //全等方式匹配 "ctrl_index::network"
                '全等方式匹配' => array(
                    'action' => 'ctrl_index::network',
                    'values' => array(
                        '_SERVER.REQUEST_METHOD' => 'GET'
                    )
                ),
                //正则方式匹配, "@"开头的正则表达式
                '正则方式匹配'  => array(
                    'action' => '@^ctrl_.*network$@'
                )
            ),
            'ipList'   => array(
                //黑名单优先, 支持 IPv6
                'blocklist' => array(
                    //为固定IP拦截, 客户端为"127.0.0.1"时限制访问
                    '127.0.0.1',
                    //使用IP段拦截, IP v4与v6混用
                    array('::1','255.0.0.1')
                ),
                //白名单次之, 结构同"blocklist"
                'allowlist' => array()
            )
        ),
        '访问OF错误日志' => array(
            'matches' => array(
                //错误日志界面
                '错误日志界面' => array(
                    'action' => '@^of_base_error_tool::@'
                ),
                //错误日志明细
                '错误日志明细' => array(
                    'action' => 'of_base_com_com::paging',
                    'values' => array(
                        '_POST.method' => 'of_base_error_tool::getLogTablePaging'
                    )
                )
            ),
            'ipList' => array(
            )
        )
    )
);