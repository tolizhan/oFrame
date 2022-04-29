<?php
/**
 * 描述 : API接口类
 * 作者 : Edgar.lee
 */
class of_base_sso_tool {
    //默认空间
    private static $space = 'default';

    /**
     * 描述 : 检查登录状态
     * 参数 :
     *      type  : 登录类型, false = 跳转登录, true = 接口登录
     *      space : 验证的空间, 指定时会更改默认空间, 默认default
     * 返回 :
     *      str=登录名,false=未登录,exit=未知
     * 作者 : Edgar.lee
     */
    public static function check($type = true, $space = '') {
        //指定空间 ? 设置默认空间 : 使用默认空间
        $space ? self::$space = $space : $space = self::$space;
        //工具包session引用
        $tool = &self::session(1, $space);
        //引用配置文件
        $config = &$tool['config'];

        //跳转登录 && 跳转回写
        if (
            !$type &&
            isset($_SERVER['HTTP_REFERER']) &&
            strpos($_SERVER['HTTP_REFERER'], '=of_base_sso_main') &&
            isset($_REQUEST['data']) && isset($_REQUEST['md5'])
        ) {
            //去斜线
            $data = $_REQUEST['data'][1] === '"' ?
                $_REQUEST['data'] : stripslashes($_REQUEST['data']);

            //校验通过
            if (
                isset($tool['check']) && isset($_REQUEST['md5']) &&
                md5($data . $tool['check'] . $config['key']) === $_REQUEST['md5']
            ) {
                //解码json
                $data = json_decode($data, true);
                $tool['ticket'] = $data['ticket'];
                //刷新当前页面
                header('Location: ?' . http_build_query(
                    array_diff_key($_GET, array('data' => 1, 'md5' => 1))
                ));
                exit;
            //校验失败
            } else {
                //跳转登录
                header('Location: ' . self::login('', $space));
                exit;
            }
        //接口登录 && 票据为空
        } else if ($type && empty($tool['ticket'])) {
            //接口回写
            if (isset($_COOKIE['of_base_sso']['ticket'][$space])) {
                $tool['ticket'] = $_COOKIE['of_base_sso']['ticket'][$space];
                //删除票据
                setcookie(rawurlencode('of_base_sso[ticket][' .$space. ']'), null, null, null);
            } else {
                //计算服务端路径
                $temp = of_base_sso_api::getUrl($config['url'], array(
                    'a'        => 'ticket',
                    'c'        => 'of_base_sso_api',
                    'space'    => $space,
                    'name'     => $config['name'],
                    'callback' => 'callback'
                ));

                echo "<script>var callback = function (json) {
                    if (json.state === 200) {
                        document.cookie = encodeURIComponent('of_base_sso[ticket][{$space}]') + '=' + encodeURIComponent(json.ticket);
                        window.location.reload();
                    } else {
                        alert(json.msg);
                        throw new Error('SSO system response error : ' + json.msg);
                    }
                };</script>",
                "<script src='{$temp}'></script>";
                exit;
            }
        }

        //票据存在 && 未登录状态 && 校验登入状态
        isset($tool['ticket']) && empty($tool['online'][$space]['user']) &&
            self::login(null, $space);

        //没登入
        if (empty($tool['online'][$space]['user'])) {
            return false;
        //已登录
        } else {
            return $tool['online'][$space]['name'];
        }
    }

    /**
     * 描述 : 登录用户
     * 参数 :
     *      args  : 登录参数, 
     *          null  = 验证当前用户是否登录, 
     *          str   = 生成跳转模式下跳转的连接, 
     *          array = 接口模式下的登录帐号{
     *              "user" : 用户名
     *              "pwd"  : 登录密码
     *          }
     *      space : ("default")登录的空间
     * 注明 :
     *      帐号与密码为null时,表示查询当前登录用户及权限
     * 返回 :
     *      true=成功,false=失败,数组=出错{"state" : 状态码, "msg" : 错误信息}
     * 作者 : Edgar.lee
     */
    public static function login($args = '', $space = '') {
        //使用默认空间
        $space || $space = self::$space;
        //工具包session引用
        $tool = &self::session(1, $space);
        //引用配置文件
        $config = &$tool['config'];
        //本机请求参数
        $params = &of_base_com_net::$params;

        $data = array(
            'a'      => 'check',
            'space'  => &$space, 
            'notify' => $params['scheme'] . '://' . $params['host'] . ':' . $params['port'] . OF_URL .
                '/index.php?c=of_base_sso_tool&a=state',
            'cookie' => session_name() . '=' . session_id(),
            //获取没有的权限
            'role'   => 3
        );

        //跳转模式的登录路径
        if (is_string($args)) {
            $data = array(
                'a'       => 'index',
                'c'       => 'of_base_sso_main',
                'referer' => of_base_sso_api::getUrl($args),
                'check'   => $tool['check'] = uniqid(),
                'name'    => &$config['name'],
            ) + $data;
            $data = of_base_sso_api::getUrl($config['url'], $data);
            return $data;
        } else {
            if (is_array($args)) {
                //指定帐号密码登入
                !empty($args['user']) && $data += array(
                    'user' => &$args['user'],
                    'pwd'  => &$args['pwd']
                );
            }

            $data = &self::request($data, $space);
            if ($data['state'] === 200) {
                //用户未登录
                if (empty($data['user'])) {
                    //移除登入状态
                    unset($tool['online'][$space]);
                } else {
                    //添加登录信息
                    $tool['online'][$space] = array(
                        'user' => &$data['user'],
                        'name' => &$data['name'],
                        'nick' => &$data['nick'],
                        'role' => &$data['role'],
                        'notes' => &$data['notes'],
                        //兼容历史错误nike应为nick
                        'nike' => &$data['nike']
                    );
                }

                return !empty($data['user']);
            }
            return $data;
        }
    }

    /**
     * 描述 : 退出登录用户
     * 参数 :
     *      space : ("default")登录的空间
     * 作者 : Edgar.lee
     */
    public static function logout($space = '') {
        //使用默认空间
        $space || $space = self::$space;
        //发送退出请求
        $params = array(
            'a'     => 'logout',
            'space' => &$space
        );
        $data = &self::request($params, $space);

        //解析成功
        if ($data['state'] === 200) {
            //退出登录
            self::session(2, $space);
            return true;
        }
        return $data['msg'];
    }

    /**
     * 描述 : 获取当前登录用户信息
     * 返回 :
     *      null=未登录, 数组=登录结构 {
     *          "user"   : SSO中的用户ID
     *          "name"   : 用户帐号
     *          "nick"   : 用户昵称
     *          "notes"  : 用户备注
     *          "role"   : 角色权限包, 如果登录了存在 {
     *              "allow" : 允许访问接口,当获取拥有权限时存在 {
     *                  "pack" : {
     *                      "角色名" : {
     *                          "data" : 角色自带的数据
     *                          "func" : {功能名1：功能名1，功能名2;功能名2...}
     *                      }
     *                  }
     *                  "func" : {
     *                      "功能名" : {
     *                          "data" : 功能自带的数据
     *                      }
     *                  }
     *              },
     *              "deny"  : 拒绝访问接口,当获取没有权限时存在 {
     *                  "pack" : {
     *                      "角色名" : {
     *                          "data" : 角色自带的数据
     *                          "func" : {功能名1：功能名1，功能名2;功能名2...}
     *                      }
     *                  }
     *                  "func" : {
     *                      "功能名" : {
     *                          "data" : 功能自带的数据
     *                      }
     *                  }
     *              }
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function &user($key = null, $space = '') {
        //使用默认空间
        $space || $space = self::$space;

        if (isset($_SESSION['_of']['of_base_sso']['tool']['online'][$space])) {
            $result = &$_SESSION['_of']['of_base_sso']['tool']['online'][$space];
            if (is_string($key)) {
                isset($result[$key]) ?
                    $result = &$result[$key] : $result = &$null;
            }
        }

        return $result;
    }

    /**
     * 描述 : 用户状态变化回调
     * 作者 : Edgar.lee
     */
    public static function state() {
        //工具包session引用
        $tool = &self::session(1, $_GET['space']);

        if (isset($tool['ticket']) && $tool['ticket'] === $_GET['ticket']) {
            self::login(null, $_GET['space']);
        }
    }

    /**
     * 描述 : 验证权限
     * 参数 :
     *      role  : 验证权限的键值
     *      space : ("default")登录的空间
     * 注明 :
     *      同时包含有权和无权的包时,系统认定有权
     * 返回 :
     *      true=有权限, false=无权限
     * 作者 : Edgar.lee
     */
    public static function role($role, $space = '') {
        //使用默认空间
        $space || $space = self::$space;
        //工具包session引用
        $tool = &self::session(1, $space);
        return isset($tool['online'][$space]) && !isset($tool['online'][$space]['role']['deny']['func'][$role]);
    }

    /**
     * 描述 : 集成功能
     * 参数 :
     *      func : 功能名
     *      data : 对应功能的数据参数
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public static function func($func = null, $data = array(), $space = '') {
        $data['a'] = 'func';
        $data['type'] = &$func;
        return self::request($data, $space ? $space : self::$space);
    }

    /**
     * 描述 : 发送get请求
     * 参数 :
     *     &url : 带GET参数的
     * 返回 :
     *      响应数据
     * 作者 : Edgar.lee
     */
    private static function &request(&$params, $space) {
        static $mode = null;
        //工具包session引用
        $tool = &self::session(1, $space);
        //引用配置文件
        $config = &$tool['config'];

        $params += array(
            'c'      => 'of_base_sso_api',
            'name'   => &$config['name'], 
            'ticket' => $tool['ticket']
        );
        $url = of_base_sso_api::getUrl($config['url'], $params, $config['key']);
        $mode === null && $mode = preg_match('@^\w+://' .of_base_com_net::$params['host']. '\b@', $url);

        //关闭session
        $mode && session_write_close();
        $response = &of_base_com_net::request($url);
        //引用响应值
        $data = &$response['response'];
        //重启session
        $mode && L::session();
        //重置会话
        $mode && self::session(8, $space);

        if ($response['state'] && $data = json_decode($data, true)) {
            isset($data['ticket']) && $tool['ticket'] = $data['ticket'];
            unset($data['ticket']);
        } else {
            $data = array(
                'state' => 500,
                'msg'   => '通信失败'
            );
        }

        if ($data['state'] >= 500) {
            self::session(4, $space);

            if ($data['state'] !== 504) {
                //相关校验信息未通过
                trigger_error("Bad request: " . print_r($response, true));
                exit;
            }
        }
        return $data;
    }

    /**
     * 描述 : 操作会话数据
     * 参数 :
     *      order : 操作指令, 1=读取会话, 2=退出会话, 4=清空会话, 8=重置会话
     *      space : 操作空间
     * 返回 :
     *      {
     *          "config" :&SSO配置
     *          "ticket" :&通信票据
     *          "online" :&在线用户
     *          "check"  :&票据校验
     *      }
     * 作者 : Edgar.lee
     */
    private static function &session($order, $space) {
        //登录配置 {空间正则 : SSO配置, ...}
        static $config = null;
        //空间配置 {配置标识 : {"ticket" : 通信票据, "config" : SSO配置}, ...}
        static $sConf = array();
        //缓存结果集
        static $result = null;

        //初始化sso配置
        if ($config === null) {
            //加载接口类, 开启SESSION
            class_exists('of_base_sso_api');
            //读取sso配置
            $config = of::config('_of.sso');
            //追加重置会话
            $order |= 8;

            //单点配置客户端 || 单点配置服务端
            if (isset($config['name']) || isset($config['dbPool'])) {
                //格式sso配置=>{空间正则 : SSO配置, ...}
                $config = array('@.@' => $config);
            }

            //生成空间配置
            foreach ($config as $k => &$v) {
                //含客户端配置
                if (isset($v['name'])) {
                    //初始服务器地址
                    $v['url'] || $v['url'] = OF_URL . '/index.php';
                    //计算服务端标识
                    $v['digest'] = md5($v['url']);
                    //通过标识引用配置
                    $sConf[$v['digest']]['config'] = &$v;
                //移除服务端配置
                } else {
                    unset($config[$k]);
                }
            }
        }

        //工具包session引用
        $tool = &$_SESSION['_of']['of_base_sso']['tool'];

        if ($order & 8) {
            //初始化在线列表
            isset($tool['online']) || $tool['online'] = array();
            //初始客户端ticket列表
            ($index = &$tool['client']) || $index = array();

            //整理客户端连接
            foreach ($index as $k => &$v) {
                //空间配置存在
                if (isset($sConf[$v['digest']])) {
                    //共享相同配置的相同 ticket
                    $sConf[$v['digest']]['ticket'] = &$v['ticket'];
                //清理无效登录
                } else {
                    unset($tool['client'][$k], $tool['online'][$k]);
                }
            }
        }

        //空间连接不存在
        if (empty($tool['client'][$space])) {
            //匹配SSO登录配置
            foreach ($config as $k => &$v) {
                //空间匹配成功
                if (preg_match($k, $space)) {
                    $tool['client'][$space] = array(
                        'digest' => &$v['digest'],
                        'ticket' => &$sConf[$v['digest']]['ticket'],
                        'check'  => ''
                    );
                    break ;
                }
            }
        }

        //客户端有效
        if (isset($tool['client'][$space])) {
            //引用框架客户端连接
            $index = &$tool['client'][$space];

            //退出会话
            if ($order & 2) {
                unset($tool['online'][$space]);
            //清空会话
            } else if ($order & 4) {
                //清空所有共享的ticket
                $index['ticket'] = null;
                //清空所有共享ticket登录信息
                foreach ($tool['online'] as $k => &$v) {
                    if (!$tool['client'][$k]['ticket']) unset($tool['online'][$k]);
                }
            }

            //修改空间会话数据
            $result = array(
                'config' => &$sConf[$index['digest']]['config'],
                'ticket' => &$index['ticket'],
                'online' => &$tool['online'],
                'check'  => &$index['check'],
            );
            return $result;
        //SSO空间无效
        } else {
            throw new Exception('SSO space is invalid: ' . $space);
        }
    }
}

return true;