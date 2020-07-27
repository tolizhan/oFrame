<?php
return array(
    //用__FILE__表示的站点根目录,修改substr的第二参数即可
    'rootDir'     => strtr(substr(__FILE__, 0, -22), '\\', '/'),
    //域名到产品根目录地址,根路径为空字符串,null=尝试自动计算
    'rootUrl'     => null,
    //配置文件路径, 数组=动态配置{动态键 : 配置路径}, 字符串=等同{"0" : 全局配置}
    'config'      => '/demo/config/config.php',
    //系统时区, 设置php支持的时区(如: Europe/London 支持夏令时), 读取格式为 ±00:00
    'timezone'    => 'PRC',
    //统一调试模式, true=开发环境, null=测试环境, false=生产环境, 字符串=切换开发环境密码
    'debug'       => true,
    //可写目录,上传,缓存等路径都将写入该文件夹
    'dataDir'     => '/data',
    //用户群体的系统字符集
    'charset'     => 'GB18030',
    //视图层文件夹结构 {'/img' : 图片, '/js' : 脚本, '/css' : 样式, '/tpl' : 模板}
    'view'        => array(
        //视图模板文件扩展名
        'tplExt'  => '.tpl.php',
        //默认视图模板模板路径
        'tplPath' => '/view',
        //全局标题
        'title'   => ' OF 框架 http://phpof.net/',
    ),
    //数据库连接池
    'db'          => array(
        #单数据库混合写法
        //*
        //数据库连接方式(mysqlPdo, mysqli, mysql)
        'adapter'        => 'mysqli',
        //数据库连接参数
        'params'         => array(
            'host'       => '127.0.0.1',
            'port'       => 3306,
            'user'       => 'root',
            'password'   => 'admin',
            'database'   => 'test',
            //mysql > 5.5.3 可配置utf8mb4存储emoji表情
            'charset'    => 'utf8',
            //数据库时区, 默认true=框架时区, false=数据库时区, "±00:00"=指定时区
            'timezone'   => true,
            //设置隔离级别, ""=跟随系统, "READ UNCOMMITTED", "READ COMMITTED", "REPEATABLE READ", "SERIALIZABLE"
            'isolation'  => 'READ COMMITTED',
            //是否长连接
            'persistent' => false
        )
        // */

        #多数据库混合写法
        /*
        array(数据库连接信息),
        array(数据库连接信息),
        // */

        #双数据库读写分离写法
        /*
        'write' => array(数据库连接信息),
        'read'  => array(数据库连接信息),
        // */

        #多数据库读写分离写法
        /*
        'write' => array(
            array(数据库连接信息),
            array(数据库连接信息),
        ),
        'read' => array(
            array(数据库连接信息),
            array(数据库连接信息),
        ),
        // */

        #多数据库读写分离写法
        /*
        //'default'为默认连接源
        'default'      => 以上单连接池写法,
        '非默认连接池' => 以上单连接池写法,
        // */
    ),
    //预先装载类
    'preloaded'   => array(
        //快捷集成
        'of_base_link_setup',
        //错误日志
        'of_base_error_writeLog',
        //session块
        'of_base_session_base',
        //语言包支持
        //'of_base_language_packs',
        //xss 防御
        'of_base_xssFilter_main',
        //扩展支持,依赖快捷集成
        //'of_base_extension_match',
        //html模板引擎,实现UI,开发人员分离
        'of_base_htmlTpl_engine',
        //加载兼容IE6+ bootstrap v3
        //'of_addin_bsui_setup',
        //非生产环境检查最新版本
        'of_base_version_check',
        //加载 composer
        //'include_composer_vendor_autoload'
    ),

    //快捷集成
    'link'        => array(
        //集成插件
        'addin' => array(
            //php扩展配置文件路径, 默认 OF_DIR . '/addin/config.php'
            //'pConfig' => '/include/of/addin/config.php',
            //js 扩展配置文件路径, 默认 OF_URL . '/addin/config.js'
            //'jConfig' => '/include/of/addin/config.js',
        )
    ),
    //错误日志
    'error'       => array(
        //日志有效时间(天),0=不清理
        'gcTime' => 30,
        //相同错误最多记录次数, 大于0时起用
        'repeat' => 999,
        //sql日志路径,false=关闭
        'sqlLog' => '/data/error/sqlLog',
        //php日志路径,false=关闭
        'phpLog' => '/data/error/phpLog',
        //js日志路径,false=关闭
        'jsLog'  => '/data/error/jsLog'
    ),
    //会话封装
    'session'     => array(
        //存储方式, files=文件存储, kv=_of.com.kv方式, mysql=数据库方式
        'adapter'     => 'files',
        //禁止 js 读取 session_id
        'httpOnly'    => true,
        //正则匹配"调度类名::方法名"判断是否自动开启
        'autoStart'   => '@^(?!of_base_com_net:|of_base_sso_api:|of_base_language_packs:)@',
        //最大生存时间(分钟)
        'maxLifeTime' => 60,
        //各调度参数
        'params'      => array(
            #files 模式, 文件存储方式
            //*
            //存储的文件路径
            'path'    => '/data/_of/of_accy_session_files'
            // */

            #mysql 模式, mysql存储表信息(推荐Innodb,MEM
            /*
            //数据库连接池
            'dbPool' => 'default',
            // */

            #k-v 模式
            /*
            //k-v 连接池
            'kvPool' => 'default',
            // */
        )
    ),
    //多语言包
    'language'    => array(
        //语言包路径
        'path'    => '/data/language/Edgar',
        //默认语言
        'default' => 'base'
    ),
    //扩展管理
    'extension'   => array(
        //扩展程序存储路径
        'path'      => '/include/extensions',
        //扩展执行存储路径
        'save'      => '/data/_of/of_base_extension/save',
        //设置独享页的类名
        'exclusive' => 'of_ex'
    ),
    //html模板解析引擎
    'htmlTpl'     => array(
        //编译模版存储路径
        'path'    => '/data/_of/of_base_htmlTpl_engine',
        //注释脚本标识 <!--标识[0] php代码 标识[1]-->, [2]脚本匹配正则如'(?:\<\?|_)(.*?)(?:\?\>|)'
        'tagKey'  => array('<?', '?>'),
        //属性的前缀 _value 相当于 value
        'attrPre' => '_',
        //功能的前缀 __del 代表删除 当前标签
        'funcPre' => '__'
    ),
    //单点登录
    'sso'         => array(
        //单点登录所使用的数据库
        'dbPool'  => 'default',
        //用户密码有效期(天), 0=不限制, 期满必须修改
        'expiry'  => 90,
        //开放注册,单点登录系统使用
        'openReg' => true,
        //对接网址,工具包使用,默认本机接口
        'url'     => null,
        //对接帐号,工具包使用
        'name'    => 'sso',
        //对接密码,工具包使用
        'key'     => '123456'
    ),
    //系统组件
    'com'         => array(
        //组件分页设置
        'com::paging' => array(
            //默认展示数量
            'size'  => 10,
            //检查是否有权限调用分页, @开头的字符串=正则验证, 否则=遵循回调规则(返回true=通过)
            'check' => '@paging$@i'
        ),
        //网络请求
        'net' => array(
            //异步请求方案, ""=当前网址, url=带端口的网址
            'async'  => '',
            //k-v 池, 异步请求时用于安全校验
            'kvPool' => 'default'
        ),
        //计划任务
        'timer' => array(
            //存储的文件路径
            'path' => '/data/_of/of_base_com_timer',
            //动态任务
            'task' => array(
                //存储方式, files=文件模式, mysql=数据库模式
                'adapter' => 'files',
                'params'  => array(
                    #mysql 模式, mysql存储表信息(推荐Innodb)
                    /*
                    //数据库连接池
                    'dbPool' => 'default',
                    // */
                )
            ),
            //静态任务
            'cron' => array(
                //静态计划任务文件
                'path'   => '',
                //k-v 池, 分布式时防重复执行
                'kvPool' => 'default'
            )
        ),
        //key-value 数据存储 可像db分连接池, 'default'为默认连接池
        'kv' => array(
            'default' => array(
                //适配文件 of_accy_com_kv_xxx
                'adapter' => 'files',
                //对应的配置
                'params'  => array(
                    #files 模式
                    //*
                    //files 存储路径
                    'path' => '/data/_of/of_accy_com_kv_files'
                    // */

                    #memcache 对应的配置, 可以使用二维数组连接集群
                    /*
                    //地址
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    // */

                    #redis 单点模式
                    /*
                    //单点模式
                    'type' => 'single',
                    //地址
                    'host' => '127.0.0.1:6379',
                    //密码
                    'auth' => '',
                    //数据库
                    'db'   => 0
                    // */

                    #redis 多点模式, redis扩展 >= 4.3.0
                    /*
                    //多点模式 "cluster"=集群模式, "distributed"=分布模式
                    'type' => 'cluster',
                    //地址
                    'host' => array(
                        '127.0.0.1:6379'
                    ),
                    //密码
                    'auth' => '',
                    //数据库, 集群不支持选择数据库
                    'db'   => 0
                    // */
                )
            )
        ),
        //消息队列
        'mq' => array(
            /*
            消息队列池名 : {
                "adapter" : 适配器(mysql),
                "params"  : 调度参数 {
                    #mysql 模式
                    "dbPool" : _of.db 的连接池
                    "vHost"  : 虚拟主机, 不同主机下的同名队列互不冲突, 默认=""
                },
                "bindDb"  : 事务数据库连接池名, 跟其提交或回滚,
                "queues"  : 队列配置文件数组或路径, 消息会同时发给这些队列, 结构如下 {
                    队列名 : {
                        "mode"   : 队列模式, null=生产及消费,false=仅生产,true=仅消费,
                        "check"  : 自动重载消息队列触发函数,
                            true=(默认)校验"消费回调"加载的文件变动,
                            false=仅校验队列配置文件变动,
                            字符串=以"@"开头的正则忽略路径(软链接使用真实路径), 如: "@/ctrl/@i"
                        "memory" : 单个并发未释放内存积累过高后自动重置, 单位M, 默认50, 0=不限制
                        "keys"   : 消费消息时回调结构 {
                            消息键 : 不存在的键将被抛弃 {
                                "cNum" : 并发数量,
                                "call" : 回调结构
                            }, ...
                        }
                    }, ...
                }
            }, ... // */
        )
    ),
    //系统插件
    'addin'       => array(
        'oUpload' => array(
            //禁止上传的扩展文件, 白名单写法 "@^(?!(exe|php|html|htm|js|css)$)@"
            'filtExt' => '@^(?:exe|php|html|htm|js|css|)$@',
            //允许上传的文件夹(仅可匹配文件夹), 正则结果[1]指定根目录(前端无法重定向)
            'folder'  => '@^(/data)/upload/@'
        )
    )
);