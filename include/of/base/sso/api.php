<?php
/**
 * 描述 : API接口类
 * 注明 :
 *      统一响应状态码 : {
 *          200 : 成功
 *          401 : 系统账号可能已停用
 *          402 : 帐号密码错误
 *          403 : 功能操作无效
 *          404 : 需先修改密码
 *          501 : 安全校验失败
 *          502 : 通信结构错误
 *          503 : 系统帐号操作权限不够
 *          504 : 票据失效
 *      }
 *      单点登录session存储结构($_SESSION['_of']['of_base_sso']) : {
 *          "users" : 已登录的用户 {
 *              登录类型 : {
 *                  "user"  : 已登陆的用户ID
 *                  "name"  : 已登录的用户名
 *                  "nike"  : 已登录的用户昵称
 *              }
 *          },
 *          "realm" : 已登陆的系统集合 {
 *              已登录系统的帐号 : {
 *                  "realmId" : 系统ID
 *                  "ticket"  : 未使用的票
 *                  "pwd"     : 密码
 *                  "trust"   : 是否允许对接 1=可操作当前用户和系统的数据,3=还可通过帐号密码操作用户数据,7=还可获取用户列表并无限制操作用户
 *                  "cookie"  : 回调 notify 时附带的 cookie
 *                  "notify"  : 当期用户状态发生变化时回调的url
 *              }
 *          },
 *          "mgmt" : 对SSO系统的管理权限 {
 *              功能键 : 整合的数据 {
 *                  标识符 : 判断时, 子项为 || 关系 [
 *                      {
 *                          具体数据, 其子项判断时为 && 关系
 *                      }
 *                  ]
 *              }, ...
 *          },
 *          "tool" : 客户端的信息包 {
 *              "ticket" : 使用的票据
 *              "online" : {
 *                  登录类型 : 在线用户 {
 *                      "user" : SSO中的用户ID
 *                      "name" : 用户名
 *                      "nike" : 昵称
 *                      "role" : 角色权限包, 如果登录了存在 {
 *                          "allow" : 允许访问接口,当获取拥有权限时存在 {
 *                              "pack" : {
 *                                  角色名 : {
 *                                      "data" : 角色自带的数据
 *                                      "func" : {功能名1：功能名1，功能名2;功能名2...}
 *                                  }
 *                              }
 *                              "func" : {
 *                                  功能名 : {
 *                                      "data" : 功能自带的数据
 *                                  }
 *                              }
 *                          },
 *                          "deny"  : 拒绝访问接口,当获取没有权限时存在 {
 *                              "pack" : {
 *                                  角色名 : {
 *                                      "data" : 角色自带的数据
 *                                      "func" : {功能名1：功能名1，功能名2;功能名2...}
 *                                  }
 *                              }
 *                              "func" : {
 *                                  功能名 : {
 *                                      "data" : 功能自带的数据
 *                                  }
 *                              }
 *                          }
 *                      }
 *                  }
 *              }
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class of_base_sso_api {
    protected static $config = null;

    /**
     * 描述 : 析构函数,验证参数,开启session
     * 作者 : Edgar.lee
     */
    public function __construct() {
        $dp = of::dispatch();

        if ($dp['class'] === 'of_base_sso_api') {
            if (
                isset($_GET['name']) && (
                    $dp['action'] === 'ticket' || isset($_GET['md5']) && isset($_GET['ticket'])
                ) 
            ) {
                if ($dp['action'] === 'ticket') {
                    //开启SESSION
                    self::openSession();
                //验证参数
                } else {
                    $temp = array($_GET['md5'], self::getTicket($_GET['ticket']));
                    unset($_GET['md5']);

                    //切换SESSION
                    if (session_id() !== $temp[1]) {
                        session_write_close();
                        //修改session
                        session_id($temp[1]);
                        function_exists('session_open') ? session_open() : session_start();
                    }

                    //读取登录数据
                    $index = &of::getArrData(
                        "_of.of_base_sso.realm.{$_GET['name']}", $_SESSION, null, 2
                    );

                    //存在则校验票据正确性
                    if ($index) {
                        if (
                            //票据相同
                            $index['ticket'] === $_GET['ticket'] &&
                            //md5验证通过
                            md5(stripslashes(join($_GET)) . $index['pwd']) === $temp[0]
                        ) {
                            //更新票据
                            $index['ticket'] = self::getTicket();
                        } else {
                            exit('{"state":501,"msg":"安全校验失败"}');
                        }
                    //不存在且为退出时给新票据(可能是sso系统session过期)
                    } else if ($dp['action'] === 'logout') {
                        self::ticket();
                    } else {
                        exit('{"state":504,"msg":"票据失效"}');
                    }
                }
            } else {
                exit('{"state":502,"msg":"通信结构错误"}');
            }
        }
    }

    /**
     * 描述 : 工具包初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        //引用配置文件
        $config = &self::$config;
        //单点登录配置
        $config = of::config('_of.sso', array()) + array(
            //单点登录所使用的数据库
            'dbPool'  => 'default',
            //开放注册,单点登录系统使用
            'openReg' => true,
            //信息有效期(天)
            'expiry'  => 90,
            //对接网址,工具包使用,默认本机接口
            'url'     => null,
            //对接帐号,工具包使用
            'name'    => null,
            //对接密码,工具包使用
            'key'     => null,
        );
        $config['url'] || $config['url'] = OF_URL . '/index.php?c=of_base_sso_api';

        //开启SESSION
        of::dispatch('class') === 'of_base_sso_api' || self::openSession();
    }

    /**
     * 描述 : 通过前端获取一个可用票据
     * 返回 :
     *      如果有callback参数就输出jsonp格式
     *      否则返回票据整合到$_GET[url]后的网址 {
     *          "static" : "done"=成功, "error"=失败
     *          "ticket" : 一次性票据
     *          "nike"   : null=没登录,字符串=已登录的用户昵称
     *      }
     * 注明 :
     *      GET参数结构 : {
     *          "name"     : 对接系统的系统账号
     *          "url"      : 简单登录时存在
     *          "callback" : jsonp回调时存在
     *      }
     * 作者 : Edgar.lee
     */
    public static function ticket() {
        if (isset($_GET['name'])) {
            $index = &$_SESSION['_of']['of_base_sso'];

            $sql = "SELECT
                `id` `realmId`, `pwd`, `trust`
            FROM
                `_of_sso_realm_attr`
            WHERE
                `name`  = '{$_GET['name']}'
            AND `state` <> '0'";

            //系统账号可用
            if ($temp = of_db::sql($sql, self::$config['dbPool'])) {
                //保存密码和后台授权
                $index['realm'][$_GET['name']] = &$temp[0];
                //对接权限转整型
                $temp[0]['trust'] += 0;
                //加密密钥
                $pwd = $temp[0]['pwd'];
                //响应成功
                $temp = array(
                    'state'  => 200,
                    'ticket' => $temp[0]['ticket'] = self::getTicket()
                );
            } else {
                //响应失败
                $temp = array(
                    'state' => 401,
                    'msg'   => '系统账号可能已停用'
                );
            }

            if (isset($_GET['callback'])) {
                echo $_GET['callback'], '(' .of_base_com_data::json($temp). ');';
            } else if (!empty($_GET['referer'])) {
                if ($temp['state'] === 200 && isset($_GET['check'])) {
                    $temp = array('data' => self::check(true));
                    $temp['md5'] = md5($temp['data'] . $_GET['check'] . $pwd);
                } else {
                    $temp = array('data' => of_base_com_data::json($temp));
                }
                return $temp;
            }
        }
    }

    /**
     * 描述 : 校验票据并获取相应数据
     * 参数 :
     *      isReturn : false=输出json, false=返回json
     * 返回 :
     *      json 数据 : {
     *          "state"  : 200=成功
     *          "ticket" : 可用票据,
     *          "user"   : 用户ID, 如果登录了存在
     *          "name"   : 用户名, 如果登录了存在
     *          "nike"   : 用户昵称
     *          "role"   : 角色包, 如果登录了存在 {
     *              "allow" : 允许访问接口,当获取拥有权限时存在 {
     *                  "pack" : {
     *                      角色名 : {
     *                          "data" : 角色自带的数据
     *                          "func" : {功能名1：功能名1，功能名2;功能名2...}
     *                      }
     *                  }
     *                  "func" : {
     *                      功能名 : {
     *                          "data" : 功能自带的数据
     *                      }
     *                  }
     *              },
     *              "deny"  : 拒绝访问接口,当获取没有权限时存在 {
     *                  "pack" : {
     *                      角色名 : {
     *                          "data" : 角色自带的数据
     *                          "func" : {功能名1：功能名1，功能名2;功能名2...}
     *                      }
     *                  }
     *                  "func" : {
     *                      功能名 : {
     *                          "data" : 功能自带的数据
     *                      }
     *                  }
     *              }
     *          }
     *      }
     * 注明 : 
     *      GET参数结构 : {
     *          "name"   : 对接系统的系统账号
     *          "notify" : 当期用户状态发生变化时回调的url
     *          "cookie" : 回调 notify 时附带的 cookie
     *          "space"  : 登录用户所属空间
     *          "role"   : '0'=不获取权限,'1'=获取拥有的权限,'2'=获取没有的权限,'3'=获取所有权限
     *      }
     * 作者 : Edgar.lee
     */
    public static function check($isReturn = false) {
        $index = &$_SESSION['_of']['of_base_sso']['users'][$_GET['space']];
        $realm = &$_SESSION['_of']['of_base_sso']['realm'][$_GET['name']];
        $json = array(
            'state'  => 200,
            'ticket' => $realm['ticket'],
        );

        if (isset($_GET['user']) && isset($_GET['pwd'])) {
            if ($realm['trust'] & 2) {
                //尝试登录
                if ($temp = self::getLogin($_GET['user'], $_GET['pwd'])) {
                    if ($temp['time']) {
                        //登入回调
                        self::pushState($_GET['space'], $temp);
                        $index = &$_SESSION['_of']['of_base_sso']['users'][$_GET['space']];
                    } else {
                        $json['state'] = 404;
                        $json['msg'] = '需先修改密码';
                    }
                } else {
                    $json['state'] = 402;
                    $json['msg'] = '帐号密码错误';
                }
            } else {
                $json['state'] = 503;
                $json['msg'] = '系统帐号操作权限不够';
            }
        }

        //已登录
        if ($json['state'] === 200) {
            //回调cookie
            $realm['cookie'] = isset($_GET['cookie']) ? $_GET['cookie'] : '';
            //登入回调
            empty($_GET['notify']) || $realm['notify'] = $_GET['notify'];

            if (isset($index['user'])) {
                //登入成功返回值
                $json += array(
                    'user' => &$index['user'],
                    'name' => &$index['name'],
                    'nike' => &$index['nike']
                );

                //获取权限
                if ($role = isset($_GET['role']) ? (int)$_GET['role'] : 0) {
                    $sql = "SELECT
                        GROUP_CONCAT(DISTINCT `_of_sso_realm_pack`.id) packIds,
                        GROUP_CONCAT(DISTINCT `_of_sso_pack_func`.funcId) funcIds
                    FROM
                        `_of_sso_realm_pack`
                            LEFT JOIN `_of_sso_user_pack` ON
                                `_of_sso_user_pack`.`realmId` = '{$realm['realmId']}'
                            AND `_of_sso_user_pack`.`userId` = '{$index['user']}'
                            AND `_of_sso_user_pack`.`packId` = `_of_sso_realm_pack`.`id`
                            LEFT JOIN `_of_sso_user_bale` ON
                                `_of_sso_user_bale`.`userId` = '{$index['user']}'
                            LEFT JOIN `_of_sso_bale_pack` ON
                                `_of_sso_bale_pack`.`realmId` = '{$realm['realmId']}'
                            AND `_of_sso_bale_pack`.`baleId` = `_of_sso_user_bale`.`baleId`
                            AND `_of_sso_bale_pack`.`packId` = `_of_sso_realm_pack`.`id`
                            LEFT JOIN `_of_sso_pack_func` ON
                                `_of_sso_pack_func`.`realmId` = '{$realm['realmId']}'
                            AND `_of_sso_pack_func`.`packId` = `_of_sso_realm_pack`.`id`
                    WHERE
                        `_of_sso_realm_pack`.`realmId` = '{$realm['realmId']}'
                    AND `_of_sso_realm_pack`.`state` <> '0'
                    AND (
                            `_of_sso_user_pack`.`id` IS NOT NULL
                        OR `_of_sso_bale_pack`.`id` IS NOT NULL)";
                    $range = of_db::sql($sql, self::$config['dbPool']);

                    //获取拥有的权限
                    $role & 1 && $json['role']['allow'] = &self::getRole($range[0], '');
                    //获取没有的权限
                    $role & 2 && $json['role']['deny'] = &self::getRole($range[0], 'NOT');

                    self::logingLog($index['name'], $_GET['name']);
                }
            }
        }

        $json = &of_base_com_data::json($json);
        if ($isReturn) return $json; else echo $json;
    }

    /**
     * 描述 : 回调退出登录
     * 参数 : 
     *      name : 排除的系统账号
     * 作者 : Edgar.lee
     */
    public static function logout() {
        $index = &$_SESSION['_of']['of_base_sso'];
        $realm = &$index['realm'][$_GET['name']];

        //移除登录状态
        unset($index['users'][$_GET['space']]);
        //全部退出
        self::pushState($_GET['space']);

        //接口对接
        if (of::dispatch('class') === 'of_base_sso_api') {
            echo '{"state":200,"ticket":"' .$realm['ticket']. '"}';
        //跳转对接
        } else if (!empty($_GET['referer'])) {
            L::header($_GET['referer']);
        }
    }

    /**
     * 描述 : 修改系统数据
     * 注明 :
     *      GET参数结构 : {
     *          "name" : 对接系统的系统账号
     *          "type" : 操作类型, "getUser"=获取用户数据

     *              获取用户数据时,type 为 getUser
     *          "user"     : 要获取的用户

     *              修改用户数据时,type 为 setUser
     *          "user"     : 要修改的用户,可以按照 oAnswer oPwd space 三个任意条件修改数据
     *          "space"    : 按照当前用户修改数据
     *          "oPwd"     : 按照密码修改数据
     *          "oAnswer"  : 按照回答修改数据
     *          "pwd"      : 密码
     *          "nike"     : 昵称
     *          "state"    : 可用状态,0=冻结,1=启用
     *          "question" : 问题
     *          "answer"   : 回答
     *      }
     * 作者 : Edgar.lee
     */
    public static function func() {
        $index = &$_SESSION['_of']['of_base_sso'];
        $json = array('state' => 403, 'msg' => '无效数据');

        //可通过权限修改
        if ($index['realm'][$_GET['name']]['trust'] & 2) {
            switch (L::get('type')) {
                case 'getUser':
                    if (!empty($_GET['user'])) {
                        $sql = "SELECT
                            SUBSTR(
                                `find`, POSITION('_' IN `find`) + 1, SUBSTR(`find`, 1, POSITION('_' IN `find`) - 1)
                            ) `question`, `name`, `nike`
                        FROM
                            `_of_sso_user_attr`
                        WHERE
                            `name` = '{$_GET['user']}'";

                        if ($temp = of_db::sql($sql, self::$config['dbPool'])) {
                            $json = array('state' => 200) + $temp[0];
                        }
                    }
                    break;
                case 'setUser':
                    $sql = $where = array();

                    empty($_GET['nike']) || $sql[] = "`nike` = '{$_GET['nike']}'";
                    empty($_GET['state']) || $sql[] = "`state` = '{$_GET['state']}'";
                    if (!empty($_GET['question']) && !empty($_GET['answer'])) {
                        $sql[] = '`find`=\'' . 
                            str_pad(strlen($_GET['question']) . '_' . $_GET['question'] . md5($_GET['answer']), 255, "\0") . 
                            '\'';
                    }
                    if (!empty($_GET['pwd'])) {
                        $temp = md5($_GET['pwd']);
                        $sql[] = "`time` = IF(`pwd` = '{$temp}', `time`, NOW())";
                        $sql[] = "`pwd` = '{$temp}'";
                    }

                    empty($_GET['space']) || $where[] = 
                        isset($index['users'][$_GET['space']]['name']) &&
                        $index['users'][$_GET['space']]['name'] === $_GET['user'] ? 'TRUE' : 'FALSE';
                    empty($_GET['oPwd']) || $where[] = "`pwd` = MD5('{$_GET['oPwd']}')";
                    empty($_GET['oAnswer']) || $where[] = "`find` = RPAD(CONCAT(
                        SUBSTR(`find`, 1, LENGTH(SUBSTR(`find`, 1, POSITION('_' IN `find`))) + SUBSTR(`find`, 1, POSITION('_' IN `find`) - 1)), 
                        MD5('{$_GET['oAnswer']}')
                    ), 255, '\\0')";
                    //超级权限 && 完全控制
                    empty($where) || $index['realm'][$_GET['name']]['trust'] & 4 && $where[] = 'TRUE';

                    if ($sql && $where) {
                        $sql = 'UPDATE 
                            `_of_sso_user_attr` 
                        SET ' . join(',', $sql) . "
                        WHERE 
                            `name` = '{$_GET['user']}'
                        AND (" .join(' OR ', $where). ")";

                        of_db::sql($sql, self::$config['dbPool']) && $json = array('state' => 200, 'msg' => '操作成功');
                    }
                    break;
            }
        } else {
            $json['state'] = 503;
            $json['msg'] = '系统帐号操作权限不够';
        }

        $json['ticket'] = &$index['realm'][$_GET['name']]['ticket'];
        echo of_base_com_data::json($json);
    }

    /**
     * 描述 : 添加用户登录日志
     * 参数 :
     *     &name : 用户名
     *     &site : 系统名
     * 作者 : Edgar.lee
     */
    protected static function logingLog(&$name, &$site) {
        $sql = "INSERT INTO `_of_sso_login_log` (
            `name`, `site`, `time`, `ip`
        ) VALUES (
            '{$name}', '{$site}', NOW(), '{$_SERVER['REMOTE_ADDR']}'
        )";
        //插入订单日志
        of_db::sql($sql, self::$config['dbPool']);

        if (true || rand(0, 10) === 1) {
            //90 天有效期
            $sql = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] - 7776000);
            $sql = "DELETE FROM 
                `_of_sso_login_log` 
            WHERE 
                `time` < '{$sql}'";
            //删除过期日志
            of_db::sql($sql, self::$config['dbPool']);
        }
    }

    /**
     * 描述 : 验证并获取登录信息
     * 参数 :
     *     &user : 用户名
     *     &pwd  : 用户密码
     * 返回 :
     *      验证失败返回 null, 否则返回 {
     *          "user" : 用户ID, 
     *          "name" : 用户名,
     *          "nike" : 昵称, 如 昵称 不存在 用户名代替
     *          "time" : 最后修改时间, false=已过期
     *      }
     * 作者 : Edgar.lee
     */
    protected static function &getLogin(&$name, &$pwd) {
        //引用配置
        $config = &self::$config;

        $sql = "SELECT
            `id` `user`, `name`, IF(`nike` = '', `name`, `nike`) `nike`, `time`
        FROM
            `_of_sso_user_attr`
        WHERE
            `name`  = '{$name}'
        AND `pwd`   = MD5('{$pwd}')
        AND `state` <> '0'";

        if (
            ($temp = of_db::sql($sql, self::$config['dbPool'])) &&
            //不限制有效时间
            $config['expiry'] > 0 &&
            //信息未过期
            strtotime($temp[0]['time']) < $_SERVER['REQUEST_TIME'] - $config['expiry'] * 86400
        ) {
            $temp[0]['time'] = false;
        }

        return $temp[0];
    }

    /**
     * 描述 : 推送状态变化信息
     * 参数 :
     *     &space : 登录空间
     *     &type  : false=退出当前登录, 数组=登入指定用户 {
     *          "user" : 登入用户ID,
     *          "name" : 登入用户帐号,
     *          "nike" : 登入用户昵称
     *      }
     * 作者 : Edgar.lee
     */
    protected static function pushState(&$space, &$type = false) {
        $index = &$_SESSION['_of']['of_base_sso'];
        is_array($type) && $index['users'][$space] = $type;

        if (isset($index['realm'])) {
            foreach ($index['realm'] as $k => &$v) {
                if (!empty($v['notify']) && $k !== $_GET['name']) {
                    //异步推送
                    of_base_com_net::request(
                        self::getUrl($v['notify'], array('ticket' => $v['ticket'], 'space' => &$space), $v['pwd']), 
                        array('cookie' => $v['cookie']), 
                        true
                    );
                }
            }
        }
    }

    /**
     * 描述 : 合并路径的get参数
     * 参数 :
     *      url : 待合并的URL
     *      get : 整合的get数组
     *      key : ''=不生成md5,字符串=md5的密钥
     * 返回 :
     *      合并后的url
     * 作者 : Edgar.lee
     */
    protected static function getUrl($url, $get = array(), $key = '') {
        $url = $url ? 
            parse_url(stripslashes($url)) : 
            array('query' => $get ? '' : $_SERVER['QUERY_STRING']);
        isset($url['host']) && empty($url['port']) && $url['port'] = '';
        $url += of_base_com_net::$params;
        $url['port'] && $url['port'] = ':' . $url['port'];
        parse_str($url['query'], $url['query']);
        $url['query'] = $get + $url['query'];
        $key && $url['query']['md5'] = md5(join($url['query']) . $key);
        $url = "{$url['scheme']}://{$url['host']}{$url['port']}{$url['path']}?" . http_build_query($url['query']);
        return $url;
    }

    /**
     * 描述 : 获取详细权限结构
     * 参数 :
     *     &range : 拥有的权限列表结构 {
     *          "packIds" : 拥有权限的角色ID, 例:"1,2,3,4"
     *          "funcIds" : 拥有权限的功能ID, 例:"1,2,3,4"
     *      }
     * 返回 : 对应的权限结构 {
     *          "pack" : {
     *              角色名 : {
     *                  "data" : 角色自带的数据
     *                  "func" : {功能名1：功能名1，功能名2;功能名2...}
     *              }
     *          }
     *          "func" : {
     *              功能名 : {
     *                  "data" : 功能自带的数据
     *              }
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    private static function &getRole(&$range, $type = '') {
        $realm = &$_SESSION['_of']['of_base_sso']['realm'][$_GET['name']];
        //允许权限初始化
        $result = array('pack' => array(), 'func' => array());

        $sql = str_replace(',', '","', $range['funcIds']);
        $sql = "SELECT
            `id`, `name`, `data`
        FROM
            `_of_sso_realm_func`
        WHERE
            `realmId` = '{$realm['realmId']}'
        AND `id` {$type} IN (\"{$sql}\")
        AND `state` <> '0'";
        $temp = of_db::sql($sql, self::$config['dbPool']);

        //格式化数据
        foreach ($temp as $k => &$v) {
            //功能权限
            $result['func'][$v['name']] = array('data' => &$v['data']);
            //引用结构
            $func[$v['id']] = &$v['name'];
        }

        $sql = str_replace(',', '","', $range['packIds']);
        $sql = "SELECT
            `_of_sso_realm_pack`.`name`, `_of_sso_realm_pack`.`data`, 
            GROUP_CONCAT(`_of_sso_pack_func`.funcId) `funcIds`
        FROM
            `_of_sso_realm_pack`
                LEFT JOIN `_of_sso_pack_func` ON
                    `_of_sso_pack_func`.`realmId` = '{$realm['realmId']}'
                AND `_of_sso_pack_func`.`packId` = `_of_sso_realm_pack`.`id`
        WHERE
            `_of_sso_realm_pack`.`realmId` = '{$realm['realmId']}'
        AND `_of_sso_realm_pack`.`id` {$type} IN (\"{$sql}\")
        GROUP BY
            `_of_sso_realm_pack`.`id`";
        $temp = of_db::sql($sql, self::$config['dbPool']);

        foreach ($temp as &$v) {
            if ($v['name'] !== null) {
                $v['funcIds'] = explode(',', $v['funcIds']);
                $v['func'] = array();

                foreach ($v['funcIds'] as &$vf) {
                    isset($func[$vf]) && $v['func'][$func[$vf]] = &$func[$vf];
                }
                $result['pack'][$v['name']] = &$v;
            }
            unset($v['name'], $v['funcIds']);
        }

        return $result;
    }

    /**
     * 描述 : 更新票据
     * 参数 :
     *      code : ''=生成新的加密票据, 字符串=返回票据解码后的session
     * 作者 : Edgar.lee
     */
    private static function getTicket($code = '') {
        static $dict = array (
            'Z' => 'A', 'b' => 'B', 'p' => 'C', '6' => 'D', 'K' => 'E', 'R' => 'F', 'd' => 'G',
            'Q' => 'H', 'M' => 'I', 'x' => 'J', 'E' => 'K', 'Y' => 'L', 'N' => 'M', 'O' => 'N',
            'T' => 'O', 'l' => 'P', 'g' => 'Q', 'U' => 'R', 'w' => 'S', 'i' => 'T', '7' => 'U',
            'L' => 'V', 'I' => 'W', 'V' => 'X', 's' => 'Y', 'A' => 'Z', 'q' => 'a', 'z' => 'b',
            'v' => 'c', '1' => 'd', 'u' => 'e', '2' => 'f', 'W' => 'g', 'P' => 'h', 'f' => 'i',
            '5' => 'j', 'k' => 'k', 'n' => 'l', '8' => 'm', '3' => 'n', 'D' => 'o', 'o' => 'p',
            't' => 'q', 'e' => 'r', 'a' => 's', 'y' => 't', 'X' => 'u', 'c' => 'v', '9' => 'w',
            'm' => 'x', 'F' => 'y', 'j' => 'z', 'B' => '1', 'J' => '2', 'S' => '3', '0' => '4',
            'r' => '5', 'H' => '6', 'G' => '7', 'C' => '8', 'h' => '9', '4' => '0',
        );

        //解码
        if ($code) {
            $list = &$dict;
            $code = substr($code, 13);
        //编码
        } else {
            $list = array_flip($dict);
            $code = uniqid() . session_id();
        }

        $code = str_split($code);
        foreach ($code as &$v) {
            isset($list[$v]) && $v = $list[$v];
        }
        return join($code);
    }

    /**
     * 描述 : 强制开启SESSION
     * 作者 : Edgar.lee
     */
    private static function openSession() {
        //安全开启SESSION
        if (function_exists('session_status')) {
            session_status() === 2 || (function_exists('session_open') ? session_open() : session_start());
        //暴力开启SESSION
        } else if (ini_get('session.auto_start') === '0') {
            @session_start();
        }
    }
}

of_base_sso_api::init();
return true;