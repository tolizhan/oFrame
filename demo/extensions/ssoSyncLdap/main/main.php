<?php
/**
 * 描述 : 登录界面拦截
 * 作者 : Edgar.lee
 */
function mgmt() {
    if( empty($_POST) ) {
        //请求 || 未登录
        if( !empty($_GET['referer']) || !isset($_SESSION['_of']['of_base_sso']['mgmt']) ) {
            //修改 登录 页
            $this->_addHook('::halt', array($this, 'modifyPage'), array('type' => 'login'));
            //设置同步计划任务
            empty($_GET['referer']) && $this->setTask();
        //登录管理界面
        } else if( empty($_GET['referer']) && isset($_SESSION['_of']['of_base_sso']['mgmt']) ) {
            //修改 登录 页
            $this->_addHook('::halt', array($this, 'modifyPage'), array('type' => 'mgmt'));
        }
    //界面登录信息
    } else {
        $this->login();
    }
}

/**
 * 描述 : 帐号登录
 * 作者 : Edgar.lee
 */
function login($params = null) {
    if (isset($params['sql'])) {
        //用户登录SQL
        if( strpos($params['sql'], 'AND `pwd`   = MD5(') ) {
            $temp = '@SELECT.*FROM\\s+`_of_sso_user`\\s+WHERE\\s+`name`  = \'(.*)\'\\s+AND `pwd`   = MD5\\(\'(.*)\'\\)\\s+AND `state` <> \'0\'@s';
            //提取帐号密码
            if( preg_match($temp, $params['sql'], $temp) ) {

                //移除监听
                $this->_removeHook('::sqlBefore', array($this, 'login'));

                //连接成功
                if( $conn = ldap_connect('LDAP 地址', 389) ) {
                    //登录成功
                    if( @ldap_bind($conn, stripslashes($temp[1]), stripslashes($temp[2])) ) {
                        //根DN
                        $baseDn = 'OU=youkeshu,DC=youkeshu,DC=com';
                        //查询 帐号 真名 职位
                        $result = ldap_search($conn, "{$baseDn}", "(CN={$temp[1]})", array(
                            'CN', 'description', 'title', 'pwdlastset'
                        ));
                        //读取数据
                        $result = ldap_get_entries($conn, $result);
                        //保存帐号
                        $result[0]['cn'][0] = iconv('UTF-8', 'GB18030//IGNORE', $temp[1]);
                        //保存密码
                        $result[0]['pwd'] = $temp[2];
                        //修改用户数据
                        $this->setUser($result[0]);

                        //登录成功的SQL
                        $params['sql'] = "SELECT
                            `id` `user`, `name`, IF(`nike` = '', `name`, `nike`) `nike`, `time`
                        FROM
                            `_of_sso_user`
                        WHERE
                            `name`  = '{$temp[1]}'";

                        L::sql("DELETE FROM `{$this->_getConst('eDbPre')}unrecorded` WHERE `name` = '{$temp[1]}'");
                    } else {
                        //登录失败的SQL
                        $params['sql'] = "SELECT 1 FROM `_of_sso_user` WHERE FALSE";

                        $sql = "SELECT
                            1
                        FROM
                            `_of_sso_user`
                        WHERE
                            `name`  = '{$temp[1]}'";

                        //备份数据中无帐号
                        if( !L::sql($sql) ) {
                            $sql = "INSERT INTO `{$this->_getConst('eDbPre')}unrecorded` (
                                `name`, `pwd`, `time`
                            ) VALUES (
                                '{$temp[1]}', '{$temp[2]}', NOW()
                            ) ON DUPLICATE KEY UPDATE
                                `pwd`  = '{$temp[2]}',
                                `time` = NOW()";
                            //记录在案
                            L::sql($sql);
                        }
                    }
                    //关闭连接源
                    ldap_close($conn);
                }
            }
        }
    } else {
        //拦截登录帐号
        $this->_addHook('::sqlBefore', array($this, 'login'));
    }
}

/**
 * 描述 : 修改登录及管理界面
 * 参数 :
 *      sysArgs : ::halt参数
 *      params  : {
 *          "type" : login=登录界面, mgmt=管理界面
 *      }
 * 作者 : Edgar.lee
 */
function modifyPage($sysArgs, $params) {
    $pObj = $sysArgs['parse']('obj');
    //登录界面
    if( $params['type'] === 'login' ) {
        $temp = $this->_getConst('eUrl') . '/style/login.css';
        //加载样式
        $pObj->find('link:eq(0)')->after('<link type="text/css" rel="stylesheet" href="' .$temp. '" />');
        //非注册按钮
        $pObj->find('.of_sso-login_func_button a:not(:eq(-2))')
            ->remove();
        $pObj->find('.of_sso-login_func_button')
            ->prepend(
                '<a href="http://userinfo.youkeshu.com/Auser/forgot/password/" target="_blank">忘记密码</a>
                <a href="https://userinfo.youkeshu.com/Auser/change/password/" target="_blank">修改密码</a>'
            );
    //管理界面拦截
    } else if( $params['type'] === 'mgmt' ) {
        $pObj->find('.of_sso-main_tfoot_func td:eq(0)')
            //添加删除冻结
            ->find('.of_sso-main_tfoot_func_add, .of_sso-main_tfoot_func_del, .of_sso-main_tfoot_func_ice')
            ->remove();
        //用户编辑框
        $pObj->find('input, textarea', '#userEdit')->attr('readonly', '');
    }
}

/**
 * 描述 : 用户权限变更强制修改密码
 * 作者 : Edgar.lee
 */
function permit($params = null) {
    //原始权限列表
    static $permit = null;

    if( $params ) {
        $sql = "SELECT
            MD5(GROUP_CONCAT(CONCAT(`realmId`, ':', `packId`) SEPARATOR ',')) `md5`
        FROM
            `_of_sso_permit`
        WHERE
            `userId` = '{$permit['user']}'";
        $temp = L::sql($sql);
        //保存权限校验值
        $permit['nMd5'] = &$temp[0]['md5'];

        //权限有变化
        if( $permit['oMd5'] !== $permit['nMd5'] ) {
            $sql = "UPDATE
                `_of_sso_user`
            SET
                `time` = '2000-01-01 00:00:00'
            WHERE
                `id` = '{$permit['user']}'";
            //用户需要修改密码
            L::sql($sql);
        }
    //监听权限修改
    } else if( $_POST['method'] === 'of_base_sso_main::userPaging' ) {
        //分页共享参数
        $share = json_decode(stripslashes($_POST['params']), true);

        if( isset($share['save']['id']) ) {
            //保存用户ID
            $permit['user'] = $share['save']['id'];
            $sql = "SELECT
                MD5(GROUP_CONCAT(CONCAT(`realmId`, ':', `packId`) SEPARATOR ',')) `md5`
            FROM
                `_of_sso_permit`
            WHERE
                `userId` = '{$permit['user']}'";
            $temp = L::sql($sql);
            //保存权限校验值
            $permit['oMd5'] = &$temp[0]['md5'];

            //比对修改信息
            $this->_addHook('::halt', array($this, 'permit'));
        }
    }
}

/**
 * 描述 : 同步用户数据
 * 作者 : Edgar.lee
 */
function syncUsers() {
    set_time_limit(0);
    L::buffer(false);
    $dnList = array('OU=youkeshu,DC=youkeshu,DC=com');

    //连接成功
    if ($conn = ldap_connect('LDAP 地址', 389)) {
        //登录成功
        if (ldap_bind($conn, 'LDAP 帐号', 'LDAP 密码')) {
            $sql = "UPDATE 
                `_of_sso_user` 
            SET 
                `name` = 'lizhan'
            WHERE
                `id` = 1";
            //修改管理员帐号
            L::sql($sql);

            $sql = "UPDATE 
                `_of_sso_user` 
            SET 
                `state` = '2',
                `time`  = `time`
            WHERE
                `id` > 1";
            //冻结所以帐号
            L::sql($sql);

            do {
                $dk = key($dnList);
                $dn = &$dnList[$dk];
                unset($dnList[$dk]);

                $result = ldap_list($conn, $dn, '(OU=*)', array('OU'));
                //查询部门
                $depts = ldap_get_entries($conn, $result);
                unset($depts['count']);
                //记录子部门
                foreach($depts as &$vd) $dnList[] = $vd['dn'];

                echo '<font color="red">开始同步:</font> ', $dn;

                $count = 0;
                $result = ldap_list(
                    $conn, $dn, 
                    //不包含两种禁用状态
                    '(&(CN=*)(!(useraccountcontrol=514))(!(useraccountcontrol=66050)))', array(
                        'CN', 'description', 'title', 'pwdlastset', 'mobile'
                    )
                );
                //查询部门
                $users = ldap_get_entries($conn, $result);
                unset($users['count']);
                //计数
                $count += count($users);
                foreach($users as &$v) {
                    //修改用户数据
                    $this->setUser($v);

                    //用户名
                    $user = addslashes(iconv('GB18030', 'UTF-8//IGNORE', $v['cn'][0]));
                    //手机号
                    $mobile = isset($v['mobile']) ? addslashes(trim($v['mobile'][0])) : '';
                    $sql = "INSERT INTO `{$this->_getConst('eDbPre')}smstip` (
                        `name`, `mobile`, `pwdTip`
                    ) VALUES (
                        '{$user}', '{$mobile}', DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    ) ON DUPLICATE KEY UPDATE
                        `mobile` = '{$mobile}'";
                    L::sql($sql);
                }

                echo ' <font color="red">同步完成: </font>', $count, "<br>\n";
            } while ($dnList);

            $sql = "UPDATE 
                `_of_sso_user` 
            SET 
                `state` = '0',
                `time`  = `time`
            WHERE
                `state` = '2'";
            //冻结无用帐号
            L::sql($sql);
        }
        //关闭连接源
        ldap_close($conn);
    }

    //过期天数
    $dExp = of::config('_of.sso.expiry', 90);
    //过期描述
    $temp = $_SERVER['REQUEST_TIME'] - $dExp * 86400;
    //过期时间
    $exp = date('Y-m-d', $temp);
    //提醒时间
    $tip = date('Y-m-d', $temp + 7 * 86400);
    $smstip = $this->_getConst('eDbPre') . 'smstip';

    $sqle = "SELECT
        `_of_sso_user`.`name`, `{$smstip}`.`mobile`, 
        DATE_ADD(`_of_sso_user`.`time`, INTERVAL {$dExp} DAY) `dExp`
    FROM
        `_of_sso_user`
            LEFT JOIN `{$smstip}` ON
                `{$smstip}`.`name` = `_of_sso_user`.`name`
    WHERE
        `_of_sso_user`.`state` = '1'                            /*有效帐号*/
    AND `_of_sso_user`.`time` <= '{$tip}'                       /*快过期*/
    AND `_of_sso_user`.`time` >= '{$exp}'                       /*没过期*/
    AND `{$smstip}`.`mobile` <> ''                              /*有效手机号*/
    AND `{$smstip}`.`pwdTip` < `_of_sso_user`.`time`            /*没提示过*/";

    //非debug模式发短信
    if (!OF_DEBUG) {
        //阿里短信接口
        $this->_loadFile('/aliMsg/TopSdk.php');
        $aliMsg = new TopClient;
        $aliMsg->appkey = '    ';
        $aliMsg->secretKey = '    ';
        $aliReq = new AlibabaAliqinFcSmsNumSendRequest;
        $aliReq->setSmsType('normal');
        $aliReq->setSmsFreeSignName('    ');
        $aliReq->setSmsTemplateCode('    ');

        //遍历发送短信
        while ($data = L::sql($sqle)) {
            foreach ($data as &$v) {
                $sql = "UPDATE 
                    `{$smstip}` 
                SET 
                    `pwdTip` = NOW() 
                WHERE
                    `name`='{$v['name']}'";

                //数据库操作成功
                if (L::sql($sql)) {
                    $temp = json_encode(array(
                        'username' => $v['name'],
                        'date'     =>  substr($v['dExp'], 0, 10),
                        'admin'    => ' 刘兴旺 QQ:332189016'
                    ));
                    $aliReq->setSmsParam($temp);
                    $aliReq->setRecNum($v['mobile']);
                    //发送信息
                    $aliMsg->execute($aliReq);
                }
            }
        }
    }

    $this->setTask();
}

/**
 * 描述 : 更新帐号信息
 * 参数 :
 *     &params : LDAP 读取的结构 {
 *          "cn" : 帐号 {
 *              "count" : 1
 *              "0"     : 帐号名
 *          },
 *          "0" : "cn"
 *          "title" : 职位 {
 *              "count" : 1
 *              "0"     : 职位名
 *          },
 *          "1" : "title"
 *          "description" : 用户 {
 *              "count" : 1
 *              "0"     : 用户名
 *          },
 *          "pwdlastset" : winTimestamp 格式的时间戳 {
 *              "count" : 1
 *              "0"     : unixTimestamp = winTimestamp / 10000000 - 11644473600
 *          },
 *          "2" : "description"
 *          "count" : 3
 *          "dn" : 根域 CN=lizhan,OU=IT,OU=youkeshu,DC=youkeshu,DC=com
 *      }
 * 作者 : Edgar.lee
 */
function setUser(&$params) {
    isset($params['dn']) && preg_match('@,OU=([^,]+),@', $params['dn'], $temp);
    $temp = array_map('addslashes', array(
        'user' => iconv('GB18030', 'UTF-8//IGNORE', $params['cn'][0]),
        'name' => isset($params['description'][0]) ? iconv('GB18030', 'UTF-8//IGNORE', $params['description'][0]) : '',
        'dept' => isset($temp[1]) ? $temp[1] : '',
        'post' => isset($params['title'][0]) ? iconv('GB18030', 'UTF-8//IGNORE', $params['title'][0]) : '',
        'time' => date('Y-m-d H:i:s', isset($params['pwdlastset'][0]) ? 
            $params['pwdlastset'][0] / 10000000 - 11644473600 : $_SERVER['REQUEST_TIME']
        ),
        'pwd'  => empty($params['pwd']) ? '' : md5($params['pwd'])
    ));
    //(无密码 || 初始密码) && 使用过期时间
    (!$temp['pwd'] || strlen($params['pwd']) < 8) && $temp['time'] = '2000-01-01 00:00:00';

    $sql = "INSERT INTO `_of_sso_user` (
        `name`, `pwd`, `nike`, `notes`, `state`, `find`, `time`
    ) VALUES (
        '{$temp['user']}', '{$temp['pwd']}', '{$temp['name']}', '{$temp['dept']}::{$temp['post']}', '1', '', '{$temp['time']}'
    ) ON DUPLICATE KEY UPDATE 
        `nike` = IF(VALUES(`nike`), VALUES(`nike`), `nike`),
        `notes` = IF(VALUES(`notes`) = '::', `notes`, VALUES(`notes`)),
        `state` = '1'";

    //明确密码
    if( $temp['pwd'] ) {
        $sql .= ",
        `time` = IF('{$temp['pwd']}' = '' OR `pwd` = '{$temp['pwd']}', `time`, '{$temp['time']}'), 
        `pwd` = '{$temp['pwd']}'";
    //不改变时间
    } else {
        $sql .= ",
        `time` = `time`";
    }

    //更新帐号
    L::sql($sql);
}

/**
 * 描述 : 设置计划任何
 * 作者 : Edgar.lee
 */
function setTask() {
    of_base_com_timer::task(array(
        //十分钟后触发回调
        'time' => $_SERVER['REQUEST_TIME'] - $_SERVER['REQUEST_TIME'] % 300 + 600,
        'call' => array(__class__, 'syncUsers'),
        'try'  => array()
    ));
}