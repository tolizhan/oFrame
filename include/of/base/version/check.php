<?php
/**
 * 描述 : 检查更新
 * 作者 : Edgar.lee
 */
class of_base_version_check {
    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        if (
            //非生产环境
            OF_DEBUG !== false &&
            //非框架路径
            strncmp(
                $_SERVER['SCRIPT_NAME'],
                $temp = rawurldecode(OF_URL) . '/',
                strlen($temp)
            )
        ) {
            //获取版本号(版本号|跳转地址)
            $ver = explode('|', of_base_com_kv::get('of_base_version_check::version', ''));

            //读取失败
            if (!$ver[0]) {
                of_base_com_kv::set('of_base_version_check::version', 1, 86400);
                of_base_com_net::request(OF_URL, array(), 'of_base_version_check::version');
            //读取成功 && 有最新版本
            } else if ($ver[0] > OF_VERSION) {
                //默认升级路径
                isset($ver[1]) || $ver[1] = 'https://github.com/tolizhan/oFrame';
                //显示升级提示
                $temp = '<a ' .
                    'href="' . $ver[1] . '"' .
                    'style="position: absolute; background-color: red;" ' .
                    'target="_blank"' .
                '>' .
                    'Framework Update : ' . OF_VERSION . ' -> ' . $ver[0] .
                '</a>';
                of_view::head('before', $temp);
            }
        }
    }

    /**
     * 描述 : 分析版本号
     * 参数 :
     *      params : of_base_com_net::request 回调参数
     * 作者 : Edgar.lee
     */
    public static function version() {
        //尝试列表
        $temp = array(
            //github
            'https://raw.githubusercontent.com/tolizhan/oFrame/master/include/of/of.php'
                => 'https://github.com/tolizhan/oFrame',
            //码云
            'https://gitee.com/tolizhan/oFrame/raw/master/include/of/of.php'
                => 'https://gitee.com/tolizhan/oFrame'
        );

        foreach ($temp as $k => &$v) {
            $params = @of_base_com_net::request($k);
            //请求成功
            if ($params['state']) {
                preg_match('@\bOF_VERSION[^\d]+(\d+)@', $params['response'], $temp);
                $temp = ($temp ? (int)$temp[1] : 1) . '|' . $v;
                of_base_com_kv::set('of_base_version_check::version', $temp, 86400);
                break ;
            }
        }
    }
}

of_base_version_check::init();