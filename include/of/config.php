<?php
return array(
    'rootDir'     => strtr(substr(__FILE__, 0, -22), '\\', '/'),                    //用__FILE__表示的站点根目录,修改substr的第二参数即可
    'rootUrl'     => null,                                                          //域名到产品根目录地址,根路径为空字符串,null=自动计算
    'config'      => '/demo/config.php',                                            //站点配置文件路径及文件名
    'debug'       => true,                                                          //统一调试模式, true=是, false=否
    'dataDir'     => '/data',                                                       //可写目录,上传,缓存等路径都将写入该文件夹
    'charset'     => 'GB18030',                                                     //用户群体的系统字符集
    'view'        => array(                                                         //视图层文件夹结构 {'/img' : 图片, '/js' : 脚本, '/css' : 样式, '/tpl' : 模板}
        'tplExt'  => '.tpl.php',                                                    //视图模板文件扩展名
        'tplPath' => '/view',                                                       //默认视图模板模板路径
        'title'   => 'oFrame 框架',                                                 //全局标题
    ),
    'db'          => array(                                                         //数据库连接池
        //*                                                                         //单数据库混合写法
        'adapter'        => 'mysqli',                                               //数据库连接方式(pdoMysql, mysql, mysqli)
        'params'         => array(                                                  //数据库连接参数
            'host'       => '127.0.0.1',
            'port'       => 3306,
            'user'       => 'root',
            'password'   => 'admin',
            'database'   => 'test',
            'charset'    => 'utf8',
            'persistent' => false                                                   //是否长连接
        )                                                                           // */
        /*                                                                          //多数据库混合写法
        array(数据库连接信息),
        array(数据库连接信息),                                                      // */
        /*                                                                          //双数据库读写分离写法
        'write' => array(数据库连接信息),
        'read' => array(数据库连接信息),                                            // */
        /*                                                                          //多数据库读写分离写法
        'write' => array(
            array(数据库连接信息),
            array(数据库连接信息),
        ),
        'read' => array(
            array(数据库连接信息),
            array(数据库连接信息),
        ),                                                                          // */
        /*                                                                          //多数据库读写分离写法
        'default'      => 以上单连接池写法,                                         //'default'为默认连接源,存在时启用多库库操作
        '非默认连接池' => 以上单连接池写法,                                         // */
    ),
    'preloaded'   => array(                                                         //预先装载类
        'of_base_link_setup',                                                       //快捷集成,语言包,扩展都依赖此包
        'of_base_error_writeLog',                                                   //错误日志,依赖快捷集成
        'of_base_session_base',                                                     //session块
        //'of_base_language_packs',                                                 //语言包支持,依赖快捷集成
        'of_base_xssFilter_main',                                                   //xss 防御
        //'of_base_extension_match',                                                //扩展支持,依赖语言包,快捷集成
        'of_base_htmlTpl_engine',                                                   //html模板引擎,实现UI,开发人员分离
    ),

    'error'       => array(
        'gcTime' => 30,                                                             //日志有效时间(天),0=不清理
        'sqlLog' => '/data/error/sqlLog',                                           //sql日志路径,false=关闭
        'phpLog' => '/data/error/phpLog',                                           //php日志路径,false=关闭
        'jsLog'  => '/data/error/jsLog'                                             //js日志路径,false=关闭
    ),
    'session'     => array(
        'adapter'     => 'files',                                                   //存储方式
        'autoStart'   => '@^(?!of_base_com_net:|of_base_sso_api:)@',                //正则匹配"调度类名::方法名"判断是否自动开启
        'maxLifeTime' => 60,                                                        //最大生存时间(分钟)
        'params'      => array(                                                     //各调度参数
            //*                                                                     files 模式, 文件存储方式
            'path'    => '/data/_of/of_accy_session_files'                          //存储的文件路径
            /*                                                                      mysql 模式, mysql存储表信息(推荐Innodb,MEM
            'dbPool' => 'default',                                                  //数据库连接池 // */
            /*                                                                      memcache 模式, 连接信息,可以使用二维数组连接集群
            'host' => '127.0.0.1',
            'port' => 11211,
            // */
        )
    ),
    'language'    => array(
        'path'    => '/data/language/Edgar',                                        //语言包路径
        'default' => 'base'                                                         //默认语言
    ),
    'extension'   => array(
        'path'      => '/data/extensions',                                          //可写的扩展路径
        'format'    => array('', '_', '::', ''),                                    //扩展的匹配字符串,如:['^','-','=','$']中a_b_c::d这个调度会变成'^a-b-c=d$'
        'exclusive' => 'of_ex'                                                      //设置独享页的类名
    ),
    'htmlTpl'     => array(                                                         //html模板解析引擎
        'path'    => '/data/_of/of_base_htmlTpl_engine',                            //编译模版存储路径
        'tagKey'  => '_',                                                           //注释标签标识符 <!--标识符 php代码 -->
        'attrPre' => '_',                                                           //属性的前缀 _value 相当于 value
        'funcPre' => '__'                                                           //功能的前缀 __del 代表删除 当前标签
    ),
    'sso'         => array(                                                         //单点登录
        'dbPool'  => 'default',                                                     //单点登录所使用的数据库
        'expiry'  => 90,                                                            //帐号信息有效期(天), 0=不限制, 期满必须修改
        'openReg' => true,                                                          //开放注册,单点登录系统使用
        'url'     => null,                                                          //对接网址,工具包使用,默认本机接口
        'name'    => 'sso',                                                         //对接帐号,工具包使用
        'key'     => '123456'                                                       //对接密码,工具包使用
    ),
    'com'         => array(
        'com::paging' => array(                                                     //组件分页设置
            'size'  => 10,                                                          //默认展示数量
            'check' => '@paging$@i'                                                 //检查是否有权限调用分页, @开头的字符串=正则验证, 否则=遵循回调规则(返回true=通过)
        ),
        'net' => array(                                                             //网络请求
            'check' => '',                                                          //异步请求安全校验, ""=IP地址核对, url=内网网址, str=校验密码
        ),
        'timer' => array(                                                           //计划任务
            'path'    => '/data/_of/of_base_com_timer',                             //存储的文件路径
            'crontab' => '/data/timer/crontab.php',                                 //静态计划任务文件
            'adapter' => 'files',                                                   //存储方式, files=文件模式, mysql=数据库模式
            'params'  => array(
                /*                                                                  mysql 模式, mysql存储表信息(推荐Innodb)
                'dbPool' => 'default',                                              //数据库连接池 // */
            )
        ),
        'kv' => array(                                                              //key-value 数据存储
            'adapter' => 'files',                                                   //适配文件 of_accy_com_kv_xxx
            'params'  => array(                                                     //对应的配置
                'path' => '/data/_of/of_accy_com_kv_files'                          //files 存储路径

                /*                                                                  //memcache 对应的配置, 可以使用二维数组连接集群
                'host' => '127.0.0.1',                                              //地址
                'port' => 11211,                                                    //端口 // */
            )
        )
    ),
    'addin'       => array(
        'oUpload' => array(
            'filtExt' => '@^(?:exe|php|html|htm|js|css)$@',                         //禁止上传的扩展文件
            'folder'  => '@^(?:/data)/@'                                            //允许上传的文件夹(仅可匹配文件夹)
        )
    )
);