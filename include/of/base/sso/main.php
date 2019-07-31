<?php
/**
 * 描述 : 主权限管理界面
 * 作者 : Edgar.lee
 */
class of_base_sso_main extends of_base_sso_api {

    /**
     * 描述 : 展示控制界面
     * 作者 : Edgar.lee
     */
    public function index() {
        //进入回调或管理
        if (empty($_GET['referer']) ?
                //管理平台未登录
                !isset($_SESSION['_of']['of_base_sso']['mgmt']) :
                //单点用户未登录
                empty($_SESSION['_of']['of_base_sso']['users'][$_GET['space']])
        ) {
            self::loginMain();
        //已登录回跳
        } else if (isset($_GET['referer'])) {
            $_GET['form'] = of_base_sso_api::ticket();
            of_view::display('_' . OF_DIR . '/att/sso/tpl/login.tpl.php');
        //初始化管理权限
        } else {
            if (empty($_SESSION['_of']['of_base_sso']['mgmt'])) {
                self::logoutMain('进入管理界面需要授权账号');
            } else {
                of_view::display('_' . OF_DIR . '/att/sso/tpl/main.tpl.php');
            }
        }
    }

    /**
     * 描述 : 用户分页
     * 作者 : Edgar.lee
     */
    public function userPaging(&$params = array()) {
        //查询字符
        $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        //操作动作
        if ($inStr && !empty($params['action'])) {
            switch ($params['action']) {
                //删除操作
                case 'del':
                    $sql = "DELETE FROM 
                        `_of_sso_user_attr` 
                    WHERE
                        `id` IN ({$inStr})";
                    break;
                //冻结操作
                case 'ice':
                    $sql = "UPDATE 
                        `_of_sso_user_attr` 
                    SET 
                        `state` = IF(`state` <> '0', '0', '1')
                    WHERE 
                        `id` IN ({$inStr})";
                    break;
            }
            $params['tip'] = empty($sql) || of_db::sql($sql, self::$config['dbPool']) === false ? '操作失败' : '操作成功';
        }
        //保存变动
        if (!empty($params['save'])) {
            $sets = array();
            $id = &$params['save']['id'];
            empty($params['save']['answer']) || $params['save']['find'] = strlen($params['save']['question']) . 
                '_' . $params['save']['question'] . md5($params['save']['answer']);
            unset($params['save']['id'], $params['save']['question'], $params['save']['answer']);
            if (empty($params['save']['pwd'])) {
                unset($params['save']['pwd']);
            } else {
                $params['save']['pwd'] = md5($params['save']['pwd']);
                $sets[] = "`time` = IF(`pwd` = '{$params['save']['pwd']}', `time`, NOW())";
            }

            foreach ($params['save'] as $k => &$v) {
                $sets[] = "`{$k}` = '{$v}'";
            }
            $sets = join(',', $sets);

            if ($id) {
                $sql = "UPDATE
                    `_of_sso_user_attr`
                SET
                    {$sets}
                WHERE
                    `id` = '{$id}'";
            } else {
                $sql = "INSERT IGNORE INTO
                    `_of_sso_user_attr`
                SET
                    {$sets}";
            }

            //操作失败
            if (($temp = of_db::sql($sql, self::$config['dbPool'])) === false) {
                $params['tip'] = '用户名冲突';
            //操作成功
            } else {
                $params['tip'] = '保存成功';
                //使用的ID
                $id || $id = $temp;

                //更改所属功能
                if (isset($params['linksel']['pack'])) {
                    $temp = join('","', array_keys($params['linksel']['pack']));
                    $sql= "DELETE FROM
                        `_of_sso_user_pack`
                    WHERE
                        `userId`  = '{$id}'
                    AND `realmId` = '{$params['keys']['realm']}'
                    AND `packId` NOT IN (\"{$temp}\")";
                    //删除无效关联
                    of_db::sql($sql, self::$config['dbPool']);

                    $sql = "INSERT IGNORE INTO `_of_sso_user_pack` (
                        `realmId`, `packId`, `userId`
                    ) SELECT 
                        `realmId`, `id`, '{$id}'
                    FROM
                        `_of_sso_realm_pack`
                    WHERE
                        `id` IN (\"{$temp}\")";
                    //添加缺失关联
                    of_db::sql($sql, self::$config['dbPool']);
                }

                //更改所属封装
                if (isset($params['linksel']['bale'])) {
                    $temp = join('","', array_keys($params['linksel']['bale']));
                    $sql= "DELETE FROM
                        `_of_sso_user_bale`
                    WHERE
                        `userId`  = '{$id}'
                    AND `baleId` NOT IN (\"{$temp}\")";
                    //删除无效关联
                    of_db::sql($sql, self::$config['dbPool']);

                    $sql = "INSERT IGNORE INTO `_of_sso_user_bale` (
                        `baleId`, `userId`
                    ) SELECT 
                        `id`, '{$id}'
                    FROM
                        `_of_sso_bale_attr`
                    WHERE
                        `id` IN (\"{$temp}\")";
                    //添加缺失关联
                    of_db::sql($sql, self::$config['dbPool']);
                }
            }
        }
        //一次性使用
        unset($params['action'], $params['save'], $params['linksel']);

        $sql = "SELECT 
            `id`, `name`, `nike`, `notes`, '' `pwd`, '' `answer`,
            SUBSTR(`find`, POSITION('_' IN `find`) + 1, SUBSTR(`find`, 1, POSITION('_' IN `find`) - 1)) `question`,
            IF(`state`, '', '<img src=\"" .OF_URL. "/att/sso/img/main/iceState.png\">') `_state`,
            IF(FIND_IN_SET(`id`, '{$inStr}'), 0, 1) `sort`
        FROM 
            `_of_sso_user_attr`";

        //查询数据
        if (!empty($params['search'])) {
            $sql .= " WHERE 
                INSTR(`name`, '{$params['search']}')
            OR  INSTR(`nike`, '{$params['search']}')
            OR  INSTR(`notes`, '{$params['search']}')";

            //添加选中项
            //$inStr && $sql .= " OR `id` IN ({$inStr})";
        }

        //开始排序
        $sql .= ' ORDER BY ';
        //选中置顶
        $inStr && $sql .= "`sort`, ";
        $sql .= '`name`';

        $config = array(
            'data'   => &$sql,
            'params' => &$params,
            'size'   => 100,
            'dbPool' => self::$config['dbPool']
        );

        of_base_com_com::paging($config);
    }

    /**
     * 描述 : 集合分页
     * 作者 : Edgar.lee
     */
    public function balePaging(&$params = array()) {
        //查询字符
        $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        //操作动作
        if ($inStr && !empty($params['action'])) {
            switch ($params['action']) {
                //删除操作
                case 'del':
                    $sql = "DELETE FROM 
                        `_of_sso_bale_attr` 
                    WHERE
                        `id` IN ({$inStr})";
                    break;
                //冻结操作
                case 'ice':
                    $sql = "UPDATE 
                        `_of_sso_bale_attr` 
                    SET 
                        `state` = IF(`state` <> '0', '0', '1')
                    WHERE 
                        `id` IN ({$inStr})";
                    break;
            }
            $params['tip'] = empty($sql) || of_db::sql($sql, self::$config['dbPool']) === false ? '操作失败' : '操作成功';
        }
        //保存变动
        if (!empty($params['save'])) {
            $id = &$params['save']['id'];
            unset($params['save']['id']);

            foreach ($params['save'] as $k => &$v) {
                $v = "`{$k}` = '{$v}'";
            }
            $temp = join(',', $params['save']);

            if ($id) {
                $sql = "UPDATE
                    `_of_sso_bale_attr`
                SET
                    {$temp}
                WHERE
                    `id` = '{$id}'";
            } else {
                $sql = "INSERT IGNORE INTO
                    `_of_sso_bale_attr`
                SET
                    {$temp}";
            }

            //操作失败
            if (($temp = of_db::sql($sql, self::$config['dbPool'])) === false) {
                $params['tip'] = '用户名冲突';
            //操作成功
            } else {
                $params['tip'] = '保存成功';

                //更改所属功能
                if (isset($params['linksel']['pack'])) {
                    //使用的ID
                    $id || $id = $temp;

                    $temp = join('","', array_keys($params['linksel']['pack']));
                    $sql= "DELETE FROM
                        `_of_sso_bale_pack`
                    WHERE
                        `baleId` = '{$id}'
                    AND `realmId` = '{$params['keys']['realm']}'
                    AND `packId` NOT IN (\"{$temp}\")";
                    //删除无效关联
                    of_db::sql($sql, self::$config['dbPool']);

                    $sql = "INSERT IGNORE INTO `_of_sso_bale_pack` (
                        `realmId`, `baleId`, `packId`
                    ) SELECT 
                        `realmId`, '{$id}', `id`
                    FROM
                        `_of_sso_realm_pack`
                    WHERE
                        `id` IN (\"{$temp}\")";
                    //添加缺失关联
                    of_db::sql($sql, self::$config['dbPool']);
                }
            }
        }
        //一次性使用
        unset($params['action'], $params['save'], $params['linksel']);

        //添加选中项
        if (!empty($params['linkage']['user'])) {
            $sql = "SELECT
                `_of_sso_bale_attr`.`id`, `_of_sso_bale_attr`.`name`,
                `_of_sso_bale_attr`.`lable`, `_of_sso_bale_attr`.`notes`
            FROM
                `_of_sso_user_bale`
                    LEFT JOIN `_of_sso_bale_attr` ON
                        `_of_sso_bale_attr`.`id` = `_of_sso_user_bale`.`baleId`
            WHERE
                `_of_sso_user_bale`.`userId` = '{$params['linkage']['user']}'";
            $temp = of_db::sql($sql, self::$config['dbPool']);

            foreach ($temp as &$v) {
                //选中用户关联包
                $params['select'][$v['id']] = $v;
            }

            //更新默认排序
            $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        }

        $sql = "SELECT 
            `id`, `name`, `lable`, `notes`,
            IF(`state`, '', '<img src=\"" .OF_URL. "/att/sso/img/main/iceState.png\">') `_state`,
            IF(FIND_IN_SET(`id`, '{$inStr}'), 0, 1) `sort`
        FROM 
            `_of_sso_bale_attr`";

        //查询数据
        if (!empty($params['search'])) {
            $sql .= " WHERE 
                INSTR(`name`, '{$params['search']}')
            OR  INSTR(`lable`, '{$params['search']}')
            OR  INSTR(`notes`, '{$params['search']}')";

            //添加选中项
            //$inStr && $sql .= " OR `id` IN ({$inStr})";
        }

        //开始排序
        $sql .= ' ORDER BY ';
        //选中置顶
        $inStr && $sql .= "`sort`, ";
        $sql .= '`lable`';

        $config = array(
            'data'   => &$sql,
            'params' => &$params,
            'size'   => 100,
            'dbPool' => self::$config['dbPool']
        );

        of_base_com_com::paging($config);
    }

    /**
     * 描述 : 系统分页
     * 作者 : Edgar.lee
     */
    public function realmPaging(&$params = array()) {
        //查询字符
        $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        //操作动作
        if ($inStr && !empty($params['action'])) {
            switch ($params['action']) {
                //删除操作
                case 'del':
                    $sql = "DELETE FROM 
                        `_of_sso_realm_attr` 
                    WHERE
                        `id` IN ({$inStr})";
                    break;
                //冻结操作
                case 'ice':
                    $sql = "UPDATE 
                        `_of_sso_realm_attr` 
                    SET 
                        `state` = IF(`state` <> '0', '0', '1')
                    WHERE 
                        `id` IN ({$inStr})";
                    break;
            }
            $params['tip'] = empty($sql) || of_db::sql($sql, self::$config['dbPool']) === false ? '操作失败' : '操作成功';
        }
        //保存变动
        if (!empty($params['save'])) {
            $id = &$params['save']['id'];
            unset($params['save']['id']);

            foreach ($params['save'] as $k => &$v) {
                $v = "`{$k}` = '{$v}'";
            }
            $temp = join(',', $params['save']);

            if ($id) {
                $sql = "UPDATE
                    `_of_sso_realm_attr`
                SET
                    {$temp}
                WHERE
                    `id` = '{$id}'";
            } else {
                $sql = "INSERT IGNORE INTO
                    `_of_sso_realm_attr`
                SET
                    {$temp}";
            }

            //操作失败
            $params['tip'] = of_db::sql($sql, self::$config['dbPool']) === false ? '用户名冲突' : '保存成功';
        }
        //一次性使用
        unset($params['action'], $params['save']);

        //添加选中项
        if (
            !empty($params['selNode']['key']) && (
                $params['selNode']['type'] === 'user' ||
                $params['selNode']['type'] === 'bale'
            )
        ) {
            //用户选中的系统
            if ($params['selNode']['type'] === 'user') {
                $sql = "SELECT
                    `_of_sso_realm_attr`.`id`, `_of_sso_realm_attr`.`name`,
                    `_of_sso_realm_attr`.`lable`, `_of_sso_realm_attr`.`notes`
                FROM
                    `_of_sso_user_pack`
                        LEFT JOIN `_of_sso_realm_attr` ON
                            `_of_sso_realm_attr`.`id` = `_of_sso_user_pack`.`realmId`
                WHERE
                    `_of_sso_user_pack`.`userId` = '{$params['linkage']['user']}'
                GROUP BY
                    `_of_sso_user_pack`.`realmId`";
            //集合选择的系统
            } else {
                $sql = "SELECT
                    `_of_sso_realm_attr`.`id`, `_of_sso_realm_attr`.`name`,
                    `_of_sso_realm_attr`.`lable`, `_of_sso_realm_attr`.`notes`
                FROM
                    `_of_sso_bale_pack`
                        LEFT JOIN `_of_sso_realm_attr` ON
                            `_of_sso_realm_attr`.`id` = `_of_sso_bale_pack`.`realmId`
                WHERE
                    `_of_sso_bale_pack`.`baleId` = '{$params['linkage']['bale']}'
                GROUP BY
                    `_of_sso_bale_pack`.`realmId`";
            }

            $temp = of_db::sql($sql, self::$config['dbPool']);

            foreach ($temp as &$v) {
                //选中用户关联包
                $params['select'][$v['id']] = $v;
            }

            //更新默认排序
            $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        }

        $sql = "SELECT 
            `id`, `name`, `pwd`, `lable`, `notes`, `trust`,
            IF(`state`, '', '<img src=\"" .OF_URL. "/att/sso/img/main/iceState.png\">') `_state`,
            IF(FIND_IN_SET(`id`, '{$inStr}'), 0, 1) `sort`
        FROM 
            `_of_sso_realm_attr`";

        //查询数据
        if (!empty($params['search'])) {
            $sql .= " WHERE 
                INSTR(`name`, '{$params['search']}')
            OR  INSTR(`lable`, '{$params['search']}')
            OR  INSTR(`notes`, '{$params['search']}')";

            //添加选中项
            //$inStr && $sql .= " OR `id` IN ({$inStr})";
        }

        //开始排序
        $sql .= ' ORDER BY ';
        //选中置顶
        $inStr && $sql .= "`sort`, ";
        $sql .= '`lable`';

        $config = array(
            'data'   => &$sql,
            'params' => &$params,
            'size'   => 100,
            'dbPool' => self::$config['dbPool']
        );

        of_base_com_com::paging($config);
    }

    /**
     * 描述 : 角色分页
     * 作者 : Edgar.lee
     */
    public function packPaging(&$params = array()) {
        //查询字符
        $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        //操作动作
        if ($inStr && !empty($params['action'])) {
            switch ($params['action']) {
                //删除操作
                case 'del':
                    $sql = "DELETE FROM 
                        `_of_sso_realm_pack` 
                    WHERE
                        `id` IN ({$inStr})";
                    break;
                //冻结操作
                case 'ice':
                    $sql = "UPDATE 
                        `_of_sso_realm_pack` 
                    SET 
                        `state` = IF(`state` <> '0', '0', '1')
                    WHERE 
                        `id` IN ({$inStr})";
                    break;
            }
            $params['tip'] = empty($sql) || of_db::sql($sql, self::$config['dbPool']) === false ? '操作失败' : '操作成功';
        }
        //保存变动
        if (!empty($params['save']) && !empty($params['linkage']['realm'])) {
            $params['save']['realmId'] = $params['linkage']['realm'];
            $id = &$params['save']['id'];
            unset($params['save']['id']);

            foreach ($params['save'] as $k => &$v) {
                $v = "`{$k}` = '{$v}'";
            }
            $temp = join(',', $params['save']);

            if ($id) {
                $sql = "UPDATE
                    `_of_sso_realm_pack`
                SET
                    {$temp}
                WHERE
                    `id` = '{$id}'";
            } else {
                $sql = "INSERT IGNORE INTO
                    `_of_sso_realm_pack`
                SET
                    {$temp}";
            }

            //操作失败
            if (($temp = of_db::sql($sql, self::$config['dbPool'])) === false) {
                $params['tip'] = '键名冲突';
            //保存成功
            } else {
                $params['tip'] = '保存成功';
                //更改所属功能
                if (isset($params['linksel']['func'])) {
                    //使用的ID
                    $id || $id = $temp;

                    $temp = join('","', array_keys($params['linksel']['func']));
                    $sql= "DELETE FROM
                        `_of_sso_pack_func`
                    WHERE
                        `packId` = '{$id}'
                    AND `funcId` NOT IN (\"{$temp}\")";
                    //删除无效关联
                    of_db::sql($sql, self::$config['dbPool']);

                    $sql = "INSERT IGNORE INTO `_of_sso_pack_func` (
                        `realmId`, `packId`, `funcId`
                    ) SELECT 
                        `realmId`, '{$id}', `id`
                    FROM
                        `_of_sso_realm_func`
                    WHERE
                        `id` IN (\"{$temp}\")";
                    //添加缺失关联
                    of_db::sql($sql, self::$config['dbPool']);
                }
            }
        }
        //一次性使用
        unset($params['action'], $params['save'], $params['linksel']);

        //无效关联
        if (empty($params['linkage']['realm'])) {
            $sql = array();
        } else {
            //添加选中项
            if (
                !empty($params['selNode']['key']) && (
                    $params['selNode']['type'] === 'user' ||
                    $params['selNode']['type'] === 'bale'
                )
            ) {
                //用户选中的包
                if ($params['selNode']['type'] === 'user') {
                    $sql = "SELECT
                        `_of_sso_realm_pack`.`id`, `_of_sso_realm_pack`.`name`,
                        `_of_sso_realm_pack`.`lable`, `_of_sso_realm_pack`.`data`
                    FROM
                        `_of_sso_user_pack`
                            LEFT JOIN `_of_sso_realm_pack` ON
                                `_of_sso_realm_pack`.`id` = `_of_sso_user_pack`.`packId`
                    WHERE
                        `_of_sso_user_pack`.`realmId` = '{$params['linkage']['realm']}'
                    AND `_of_sso_user_pack`.`userId` = '{$params['linkage']['user']}'";
                //集合选中的包
                } else {
                    $sql = "SELECT
                        `_of_sso_realm_pack`.`id`, `_of_sso_realm_pack`.`name`,
                        `_of_sso_realm_pack`.`lable`, `_of_sso_realm_pack`.`data`
                    FROM
                        `_of_sso_bale_pack`
                            LEFT JOIN `_of_sso_realm_pack` ON
                                `_of_sso_realm_pack`.`id` = `_of_sso_bale_pack`.`packId`
                    WHERE
                        `_of_sso_bale_pack`.`realmId` = '{$params['linkage']['realm']}'
                    AND `_of_sso_bale_pack`.`baleId` = '{$params['linkage']['bale']}'";
                }

                $temp = of_db::sql($sql, self::$config['dbPool']);

                foreach ($temp as &$v) {
                    //选中用户关联包
                    $params['select'][$v['id']] = $v;
                }

                //更新默认排序
                $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
            }

            $sql = "SELECT 
                `id`, `name`, `lable`, `data`,
                IF(`state`, '', '<img src=\"" .OF_URL. "/att/sso/img/main/iceState.png\">') `_state`,
                IF(FIND_IN_SET(`id`, '{$inStr}'), 0, 1) `sort`
            FROM 
                `_of_sso_realm_pack`
            WHERE
                `realmId` = '{$params['linkage']['realm']}'";

            //查询数据
            if (!empty($params['search'])) {
                $temp = "
                    INSTR(`name`, '{$params['search']}')
                OR  INSTR(`lable`, '{$params['search']}')";

                //添加选中项
                //$inStr && $temp .= " OR `id` IN ({$inStr})";
                $sql .= " AND ({$temp})";
            }

            //开始排序
            $sql .= ' ORDER BY ';
            //选中置顶
            $inStr && $sql .= "`sort`, ";
            $sql .= '`lable`';
        }

        $config = array(
            'data'   => &$sql,
            'params' => &$params,
            'size'   => 100,
            'dbPool' => self::$config['dbPool']
        );

        of_base_com_com::paging($config);
    }

    /**
     * 描述 : 功能分页
     * 作者 : Edgar.lee
     */
    public function funcPaging(&$params = array()) {
        //查询字符
        $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
        //操作动作
        if ($inStr && !empty($params['action'])) {
            switch ($params['action']) {
                //删除操作
                case 'del':
                    $sql = "DELETE FROM 
                        `_of_sso_realm_func` 
                    WHERE
                        `id` IN ({$inStr})";
                    break;
                //冻结操作
                case 'ice':
                    $sql = "UPDATE 
                        `_of_sso_realm_func` 
                    SET 
                        `state` = IF(`state` <> '0', '0', '1')
                    WHERE 
                        `id` IN ({$inStr})";
                    break;
            }
            $params['tip'] = empty($sql) || of_db::sql($sql, self::$config['dbPool']) === false ? '操作失败' : '操作成功';
        }
        //保存变动
        if (!empty($params['save']) && !empty($params['linkage']['realm'])) {
            $params['save']['realmId'] = $params['linkage']['realm'];
            $id = &$params['save']['id'];
            unset($params['save']['id']);

            foreach ($params['save'] as $k => &$v) {
                $v = "`{$k}` = '{$v}'";
            }
            $temp = join(',', $params['save']);

            if ($id) {
                $sql = "UPDATE
                    `_of_sso_realm_func`
                SET
                    {$temp}
                WHERE
                    `id` = '{$id}'";
            } else {
                $sql = "INSERT IGNORE INTO
                    `_of_sso_realm_func`
                SET
                    {$temp}";
            }


            $params['tip'] = of_db::sql($sql, self::$config['dbPool']) === false ? '键名冲突' : '保存成功';                 //操作失败
        }
        //一次性使用
        unset($params['action'], $params['save']);

        //无效关联
        if (empty($params['linkage']['realm'])) {
            $sql = array();
        } else {
            //添加选中项
            if (!empty($params['linkage']['pack'])) {
                $sql = "SELECT
                    `_of_sso_realm_func`.`id`, `_of_sso_realm_func`.`name`, `_of_sso_realm_func`.`lable`, `_of_sso_realm_func`.`data`
                FROM
                    `_of_sso_pack_func`
                        LEFT JOIN `_of_sso_realm_func` ON
                            `_of_sso_realm_func`.id = `_of_sso_pack_func`.`funcId`
                WHERE
                    `_of_sso_pack_func`.`realmId` = '{$params['linkage']['realm']}'
                AND `_of_sso_pack_func`.`packId` = '{$params['linkage']['pack']}'";
                $temp = of_db::sql($sql, self::$config['dbPool']);

                foreach ($temp as &$v) {
                    //选中用户关联包
                    $params['select'][$v['id']] = $v;
                }

                //更新默认排序
                $inStr = empty($params['select']) ? '' : join(',', array_keys($params['select']));
            }

            $sql = "SELECT 
                `id`, `name`, `lable`, `data`,
                IF(`state`, '', '<img src=\"" .OF_URL. "/att/sso/img/main/iceState.png\">') `_state`,
                IF(FIND_IN_SET(`id`, '{$inStr}'), 0, 1) `sort`
            FROM 
                `_of_sso_realm_func`
            WHERE
                `realmId` = '{$params['linkage']['realm']}'";

            //查询数据
            if (!empty($params['search'])) {
                $temp = "
                    INSTR(`name`, '{$params['search']}')
                OR  INSTR(`lable`, '{$params['search']}')";

                //添加选中项
                //$inStr && $temp .= " OR `id` IN ({$inStr})";
                $sql .= " AND ({$temp})";
            }

            //开始排序
            $sql .= ' ORDER BY ';
            //选中置顶
            $inStr && $sql .= "`sort`, ";
            $sql .= '`lable`';
        }

        $config = array(
            'data'   => &$sql,
            'params' => &$params,
            'size'   => 100,
            'dbPool' => self::$config['dbPool']
        );

        of_base_com_com::paging($config);
    }

    /**
     * 描述 : 获取用户问题
     * 作者 : Edgar.lee
     */
    public static function getUserInfo() {
        if (isset($_POST['name'])) {
            $sql = "SELECT
                `nike`,
                SUBSTR(`find`, POSITION('_' IN `find`) + 1, SUBSTR(`find`, 1, POSITION('_' IN `find`) - 1)) `question`
            FROM
                `_of_sso_user_attr`
            WHERE
                `_of_sso_user_attr`.`name` = '{$_POST['name']}'";
            $temp = of_db::sql($sql, self::$config['dbPool']);
        }

        echo isset($temp[0]) ? of_base_com_data::json($temp[0]) : "\0";
    }

    /**
     * 描述 : 管理退出
     * 作者 : Edgar.lee
     */
    public static function logoutMain($tip = '') {
        unset($_SESSION['_of']['of_base_sso']['mgmt']);
        L::header(OF_URL . '/index.php?c=of_base_sso_main&a=index' . ($tip ? '&tip=' . $tip : ''));
    }

    /**
     * 描述 : 模版导入
     * 作者 : Edgar.lee
     */
    public static function tplImport() {
        if (is_file($path = ROOT_DIR . OF_DATA . $_POST['path'])) {
            //清空错误日志
            of_base_error_writeLog::lastError(true);
            //开启事务
            of_db::sql(null, self::$config['dbPool']);

            //解析CSV
            while ($data = &of_base_com_csv::parse($path)) {
                $data = array_map('addslashes', $data);
                switch ($data[0]) {
                    //导入系统
                    case 'site':
                        $data[1] = (int)$data[1];
                        ($data[5] = (int)$data[5]) || $data[5] = 1;
                        $sql = "INSERT INTO `_of_sso_realm_attr` (
                            `name`, `pwd`, `state`, `lable`, `trust`, `notes`
                        ) VALUES (
                            '{$data[2]}', '{$data[3]}', '{$data[1]}', '{$data[4]}', '{$data[5]}', '{$data[6]}'
                        ) ON DUPLICATE KEY UPDATE
                            `pwd` = VALUES(`pwd`),
                            `state` = VALUES(`state`),
                            `lable` = VALUES(`lable`),
                            `trust` = VALUES(`trust`),
                            `notes` = VALUES(`notes`)";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                    //导入角色
                    case 'pack':
                        $data[1] = (int)$data[1];
                        $sql = "INSERT INTO `_of_sso_realm_pack` (
                            `realmId`, `name`, `state`, `lable`, `data`
                        ) SELECT
                            `id`, '{$data[3]}', '{$data[1]}', '{$data[4]}', '{$data[5]}'
                        FROM
                            `_of_sso_realm_attr`
                        WHERE
                            `name` = '{$data[2]}'
                        ON DUPLICATE KEY UPDATE
                            `state` = VALUES(`state`),
                            `lable` = VALUES(`lable`),
                            `data` = VALUES(`data`)";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                    //导入功能
                    case 'func':
                        $data[1] = (int)$data[1];
                        $sql = "INSERT INTO `_of_sso_realm_func` (
                            `realmId`, `name`, `state`, `lable`, `data`
                        ) SELECT
                            `id`, '{$data[3]}', '{$data[1]}', '{$data[4]}', '{$data[5]}'
                        FROM
                            `_of_sso_realm_attr`
                        WHERE
                            `name` = '{$data[2]}'
                        ON DUPLICATE KEY UPDATE
                            `state` = VALUES(`state`),
                            `lable` = VALUES(`lable`),
                            `data` = VALUES(`data`)";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                    //导入用户
                    case 'user':
                        preg_match('@^[a-z0-9]{32}$@', $data[3]) || $data[3] = md5($data[3]);
                        preg_match('@^[a-z0-9]{32}$@', $data[6]) || $data[6] = md5($data[6]);
                        $temp = ($temp = strlen($data[5])) ? $temp . '_' . $data[5] . $data[6] : '';
                        $sql = "INSERT INTO `_of_sso_user_attr` (
                            `name`, `pwd`, `state`, `nike`, `notes`, `find`
                        ) VALUES (
                            '{$data[2]}', '{$data[3]}', '{$data[1]}', '{$data[4]}', '{$data[7]}', '{$temp}'
                        ) ON DUPLICATE KEY UPDATE
                            `pwd` = VALUES(`pwd`),
                            `state` = VALUES(`state`),
                            `nike` = VALUES(`nike`),
                            `find` = VALUES(`find`),
                            `notes` = VALUES(`notes`)";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                    //角色关系
                    case 'bind':
                        $sql = "INSERT IGNORE INTO `_of_sso_pack_func` (
                            `realmId`, `packId`, `funcId`
                        ) SELECT
                            `_of_sso_realm_attr`.`id`, `_of_sso_realm_pack`.`id`, `_of_sso_realm_func`.`id`
                        FROM
                            `_of_sso_realm_attr`, `_of_sso_realm_pack`, `_of_sso_realm_func`
                        WHERE
                            `_of_sso_realm_attr`.`name` = '{$data[1]}'
                        AND `_of_sso_realm_pack`.`realmId` = `_of_sso_realm_attr`.`id`
                        AND `_of_sso_realm_pack`.`name` = '{$data[2]}'
                        AND `_of_sso_realm_func`.`realmId` = `_of_sso_realm_attr`.`id`
                        AND `_of_sso_realm_func`.`name` = '{$data[3]}'";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                    //权限关系
                    case 'role':
                        $sql = "INSERT IGNORE INTO `_of_sso_user_pack` (
                            `realmId`, `packId`, `userId`
                        ) SELECT
                            `_of_sso_realm_attr`.`id`, `_of_sso_realm_pack`.`id`, `_of_sso_user_attr`.`id`
                        FROM
                            `_of_sso_realm_attr`, `_of_sso_realm_pack`, `_of_sso_user_attr`
                        WHERE
                            `_of_sso_realm_attr`.`name` = '{$data[1]}'
                        AND `_of_sso_realm_pack`.`realmId` = `_of_sso_realm_attr`.`id`
                        AND `_of_sso_realm_pack`.`name` = '{$data[2]}'
                        AND `_of_sso_user_attr`.`name` = '{$data[3]}'";
                        of_db::sql($sql, self::$config['dbPool']);
                        break;
                }
            }

            //有错误";
            if (of_base_error_writeLog::lastError()) {
                //回滚事务
                of_db::sql(false, self::$config['dbPool']);
                echo '导入产生错误';
            } else {
                //提交事务
                of_db::sql(true, self::$config['dbPool']);
                echo 'done';
            }
            //删除模版
            of_base_com_disk::delete($path, true);
        } else {
            echo '导入文件丢失';
        }
    }

    /**
     * 描述 : 检查单点登录是否安装
     * 作者 : Edgar.lee
     */
    public static function isInstall() {
        $sql = 'SELECT
            TABLE_NAME `name`            /*表名*/
        FROM
            information_schema.`TABLES`
        WHERE 
            TABLE_SCHEMA = DATABASE()    /*数据库名*/
        AND TABLE_TYPE = "BASE TABLE"    /*表类型*/
        AND TABLE_NAME = "_of_sso_user_attr"';

        $temp = of_db::sql($sql, self::$config['dbPool']);
        //连接失败 || 已安装 算完成安装
        return $temp === false || isset($temp[0]);
    }

    /**
     * 描述 : 登录相关操作
     * 作者 : Edgar.lee
     */
    private static function loginMain() {
        //展示登录界面
        if (empty($_POST)) {
            if (empty($_GET['referer'])) {
                if (!self::isInstall()) {
                    $sql = 'SELECT
                        TABLE_NAME `name`            /*表名*/
                    FROM
                        information_schema.`TABLES`
                    WHERE 
                        TABLE_SCHEMA = DATABASE()    /*数据库名*/
                    AND TABLE_TYPE = "BASE TABLE"    /*表类型*/
                    AND TABLE_NAME = "_of_sso_user"';

                    //存在旧表
                    if (of_db::sql($sql, self::$config['dbPool'])) {
                        $temp = array(
                            '_of_sso_user'   => '_of_sso_user_attr',
                            '_of_sso_permit' => '_of_sso_user_pack',
                            '_of_sso_realm'  => '_of_sso_realm_attr',
                            '_of_sso_pack'   => '_of_sso_realm_pack',
                            '_of_sso_func'   => '_of_sso_realm_func',
                            '_of_sso_role'   => '_of_sso_pack_func',
                            '_of_sso_log'    => '_of_sso_login_log'
                        );

                        //旧版升级到200222版
                        foreach ($temp as $k => &$v) {
                            of_db::sql("ALTER TABLE `{$k}` RENAME `{$v}`", self::$config['dbPool']);
                        }

                        $sql = 'UPDATE
                            `_of_sso_realm_func`
                        SET
                            `id` = `id` + "5"
                        WHERE
                            `id` > "18"
                        ORDER BY `id` DESC';
                        //系统功能数据迁移
                        of_db::sql($sql, self::$config['dbPool']);

                        $sql = 'UPDATE
                            `_of_sso_pack_func`
                        SET
                            `id` = `id` + "5"
                        WHERE
                            `id` > "18"
                        ORDER BY `id` DESC';
                        //角色功能数据迁移
                        of_db::sql($sql, self::$config['dbPool']);
                    }

                    $temp = of_base_tool_mysqlSync::init(array(
                        'callDb'  => array(
                            'asCall' => 'of_db::sql', 
                            'params' => array('_' => null, self::$config['dbPool'])
                        ),
                        'matches' => array(
                            'table' => array('include' => array('@^_of_sso_@'))
                        )
                    ));

                    if ($temp) {
                        of_base_tool_mysqlSync::revertBase(OF_DIR . '/base/sso/db/table.sql');
                        of_base_tool_mysqlSync::revertData(OF_DIR . '/base/sso/db/data.sql');
                    }
                }
            }
            of_view::display('_' . OF_DIR . '/att/sso/tpl/login.tpl.php');
        } else if (of_base_com_com::captcha($_POST['captcha'], 'of_base_sso_main')) {
            $result = array('state' => 'done', 'msg' => '操作成功');

            //登录
            if ($_POST['type'] === 'login') {
                if ($temp = of_base_sso_api::getLogin($_POST['name'], $_POST['pwd'])) {
                    if ($temp['time']) {
                        //登录信息
                        empty($_GET['referer']) ?
                            self::getMgmt($temp) : of_base_sso_api::pushState($_GET['space'], $temp);
                        $result = array('state' => 'done', 'msg' => of_base_sso_api::ticket());
                    } else {
                        $result = array('state' => 'error', 'msg' => '需先修改帐号密码');
                    }
                } else {
                    $result = array('state' => 'error', 'msg' => '账号密码错误');
                }
            } else {
                //有效密保
                ($temp = strlen($_POST['question'])) && $temp .= '_' . $_POST['question'] . md5($_POST['answer']);

                //找回操作
                if ($_POST['type'] === 'find') {
                    $md5 = md5($_POST['pwd']);
                    $sql = "UPDATE 
                        `_of_sso_user_attr` 
                    SET 
                        `time` = IF(`pwd` = '{$md5}', `time`, NOW()),
                        `pwd`  = '{$md5}',
                        `find` = '{$temp}',
                        `nike` = '{$_POST['nike']}'
                    WHERE 
                        `name` = '{$_POST['name']}'
                    AND (
                            `pwd` = MD5('{$_POST['pwd']}')
                        OR  `find` = '" . str_pad($temp, 255, "\0") . "')";

                    of_db::sql($sql, self::$config['dbPool']) || $result = array('state' => 'error', 'msg' => '密码或回答错误');
                //插入操作
                } else {
                    $sql = "INSERT IGNORE INTO
                        `_of_sso_user_attr`
                    SET
                        `name`  = '{$_POST['name']}',
                        `pwd`   = MD5('{$_POST['pwd']}'),
                        `find`  = '{$temp}',
                        `nike`  = '{$_POST['nike']}',
                        `state` = '1'";

                    of_db::sql($sql, self::$config['dbPool']) || $result = array('state' => 'error', 'msg' => '账号冲突');
                }
            }

            echo of_base_com_data::json($result);
        } else {
            echo '{"state":"error","msg":"验证码错误"}';
        }
    }

    /**
     * 描述 : 初始化管理权限
     * 作者 : Edgar.lee
     */
    private static function getMgmt(&$user) {
        $index = &$_SESSION['_of']['of_base_sso']['mgmt'];
        $index = array();

        $sql = "SELECT
            `_of_sso_realm_pack`.`data`, GROUP_CONCAT(`_of_sso_realm_func`.`name`) `func`
        FROM
            `_of_sso_user_pack`
                LEFT JOIN `_of_sso_pack_func` ON
                    `_of_sso_pack_func`.`packId` = `_of_sso_user_pack`.`packId`
                LEFT JOIN `_of_sso_realm_pack` ON
                    `_of_sso_realm_pack`.`id` = `_of_sso_pack_func`.`packId`
                LEFT JOIN `_of_sso_realm_func` ON
                    `_of_sso_realm_func`.`id` = `_of_sso_pack_func`.`funcId`
        WHERE
            `_of_sso_user_pack`.`realmId` = '1'
        AND `_of_sso_user_pack`.`userId`  = '{$user['user']}'
        GROUP BY
            `_of_sso_realm_pack`.`id`";

        $temp = of_db::sql($sql, self::$config['dbPool']);
        foreach ($temp as &$v) {
            //添加默认权限
            $index += array_combine(
                $v['temp'] = explode(',', $v['func']),
                array_pad(array(), count($v['temp']), array())
            );
            //存在包数据
            if (is_array($v['data'] = json_decode($v['data'], true))) {
                foreach ($v['data'] as $kf => &$vf) {
                    //无效权限
                    if (strpos($v['func'], $kf) === false) {
                        unset($v['data'][$kf]);
                    }
                }
                //有效权限合并
                $index = array_merge_recursive($index, $v['data']);
            };
        }

        $sql = "SELECT 
            `name` 
        FROM 
            `_of_sso_realm_attr`
        WHERE
            `id` = '1'";
        $temp = of_db::sql($sql, self::$config['dbPool']);
        of_base_sso_api::logingLog($user['name'], $temp[0]['name']);
    }
}

return !empty($_SESSION['_of']['of_base_sso']['mgmt']) || !preg_match('@^[a-z]+Paging$@i', of::dispatch('action'));