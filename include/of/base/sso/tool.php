<?php
/**
 * 描述 : API接口类
 * 作者 : Edgar.lee
 */
class of_base_sso_tool extends of_base_sso_api {

    /**
     * 描述 : 检查登录状态
     * 参数 :
     *      type  : 登录类型, false = 跳转登录, true = 接口登录
     *      space : 验证登录的空间
     * 返回 :
     *      str=登录名,false=未登录,exit=未知
     * 作者 : Edgar.lee
     */
    public static function check($type = true, $space = 'default') {
        //工具包session引用
        $tool = &$_SESSION['_of']['of_base_sso']['tool'];
        //引用配置文件
        $config = &self::$config;

        if (
            !$type && 
            isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '=of_base_sso_main') &&
            isset($_POST['data'])
        //跳转回写
        ) {
            //去斜线
            $_POST['data'][1] === '"' || $_POST['data'] = stripslashes($_POST['data']);

            if (
                isset($tool['check']) && isset($_POST['md5']) &&
                md5($_POST['data'] . $tool['check'] . $config['key']) === $_POST['md5'] 
            //校验通过
            ) {
                //解码json
                $data = json_decode($_POST['data'], true);
                $tool['ticket'] = $data['ticket'];
                unset($tool['check'], $data['state'], $data['ticket']);
                //报错登录信息
                $tool['online'][$space] = &$data;
            //校验失败
            } else {
                //跳转登录
                header('Location: ' . of_base_sso_tool::login());
                exit;
            }
        } else if ($type && empty($tool['ticket'])) {
            //接口回写
            if (isset($_COOKIE['of_base_sso']['ticket'][$space])) {
                $tool['ticket'] = $_COOKIE['of_base_sso']['ticket'][$space];
                //删除票据
                setcookie(rawurlencode('of_base_sso[ticket][' .$space. ']'), null, null, null);
            } else {
                echo "<script>var callback = function (json) {
                    if (json.state === 200) {
                        document.cookie = encodeURIComponent('of_base_sso[ticket][{$space}]') + '=' + encodeURIComponent(json.ticket);
                        window.location.reload();
                    } else {
                        alert(json.msg);
                        throw new Error('SSO system response error : ' + json.msg);
                    }
                };</script>",
                "<script src='{$config['url']}&a=ticket&space={$space}&name={$config['name']}&callback=callback'></script>";
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
    public static function login($args = '', $space = 'default') {
        //引用配置文件
        $config = &self::$config;
        //本机请求参数
        $params = &of_base_com_net::$params;
        //工具包session引用
        $tool = &$_SESSION['_of']['of_base_sso']['tool'];

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
            empty($tool['check']) && $tool['check'] = uniqid();
            $data = array(
                'a'       => 'index',
                'c'       => 'of_base_sso_main',
                'referer' => of_base_sso_api::getUrl($args),
                'check'   => $tool['check'],
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
            $tool = &$_SESSION['_of']['of_base_sso']['tool'];
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
                        'nike' => &$data['nike'],
                        'role' => &$data['role']
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
    public static function logout($space = 'default') {
        $params = array(
            'a'     => 'logout',
            'space' => &$space
        );
        $data = &self::request($params, $space);

        //解析成功
        if ($data['state'] === 200) {
            unset($_SESSION['_of']['of_base_sso']['tool']['online'][$space]);
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
     *          "nike"   : 用户昵称
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
    public static function &user($key = null, $space = 'default') {
        if (isset($_SESSION['_of']['of_base_sso']['tool']['online'][$space])) {
            $result = &$_SESSION['_of']['of_base_sso']['tool']['online'][$space];
            if (is_string($key)) {
                isset($result[$key]) ?
                    $result = &$result[$key] : $result = &$index;
            }
        }

        return $result;
    }

    /**
     * 描述 : 用户状态变化回调
     * 作者 : Edgar.lee
     */
    public static function state() {
        if (
            isset($_SESSION['_of']['of_base_sso']['tool']['ticket']) && 
            $_SESSION['_of']['of_base_sso']['tool']['ticket'] === $_GET['ticket'] 
        ) {
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
    public static function role($role, $space = 'default') {
        $index = &$_SESSION['_of']['of_base_sso']['tool'];
        return isset($index['online'][$space]) && !isset($index['online'][$space]['role']['deny']['func'][$role]);
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
    public static function func($func = null, $data = array()) {
        $data['a'] = 'func';
        $data['type'] = &$func;
        return self::request($data);
    }

    /**
     * 描述 : 发送get请求
     * 参数 :
     *     &url : 带GET参数的
     * 返回 :
     *      响应数据
     * 作者 : Edgar.lee
     */
    private static function &request(&$params) {
        static $mode = null;
        //引用配置文件
        $config = &self::$config;

        $params += array(
            'name'   => &$config['name'], 
            'ticket' => $_SESSION['_of']['of_base_sso']['tool']['ticket']
        );
        $url = of_base_sso_api::getUrl($config['url'], $params, $config['key']);
        $mode === null && $mode = preg_match('@^\w+://' .of_base_com_net::$params['host']. '\b@', $url);

        //关闭session
        $mode && session_write_close();
        $response = &of_base_com_net::request($url);
        //引用响应值
        $data = &$response['response'];
        //重启session
        $mode && (function_exists('session_open') ? session_open() : session_start());

        if ($response['state'] && $data = json_decode($data, true)) {
            $_SESSION['_of']['of_base_sso']['tool']['ticket'] = &$data['ticket'];
            unset($data['ticket']);
        } else {
            $data = array(
                'state' => 500,
                'msg'   => '通信失败'
            );
        }

        if ($data['state'] >= 500) {
            unset($_SESSION['_of']['of_base_sso']['tool']);

            if ($data['state'] !== 504) {
                //相关校验信息未通过
                trigger_error("Bad request: " . print_r($response, true));
                exit;
            }
        }
        return $data;
    }
}

return true;