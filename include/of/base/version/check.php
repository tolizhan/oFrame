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
            //非生成环境
            OF_DEBUG !== false &&
            //非框架路径
            strncmp($_SERVER['SCRIPT_NAME'], OF_URL . '/', strlen(OF_URL) + 1)
        ) {
            //获取版本号
            $version = of_base_com_kv::get('of_base_version_check::version');

            //读取失败
            if (!$version) {
                $temp = 'https://raw.githubusercontent.com/tolizhan/oFrame/master/include/of/of.php';
                of_base_com_net::request($temp, array(), 'of_base_version_check::version');
            //读取成功 && 有最新版本
            } else if ($version > OF_VERSION) {
                $temp = '<a ' .
                    'href="https://github.com/tolizhan/oFrame"' .
                    'style="position: absolute; background-color: red; z-index: 100000;" ' .
                    'target="_blank"' .
                '>' .
                    'Frame update : ' . OF_VERSION . ' -> ' .$version .
                '<a>';
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
    public static function version($params) {
        preg_match('@\bOF_VERSION[^\d]+(\d+)@', $params['response'], $temp);
        $temp = $temp ? (int)$temp[1] : 1;
        of_base_com_kv::set('of_base_version_check::version', $temp, 86400);
    }
}

of_base_version_check::init();