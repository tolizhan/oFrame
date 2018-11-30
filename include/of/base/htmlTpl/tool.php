<?php
/**
 * 描述 : 一键清理模版缓存
 * 作者 : Edgar.lee
 */
class of_base_htmlTpl_tool {
    /**
     * 描述 : 展示界面
     * 作者 : Edgar.lee
     */
    public static function index() {
        //拷贝GET参数
        $get = $_GET + array('type' => '');

        //删除模版文件夹
        if ($get['type'] === 'clear') {
            //模版根路径
            $root = ROOT_DIR . of::config(
                '_of.htmlTpl.path', OF_DATA . '/_of/of_base_htmlTpl_engine'
            );

            if (is_dir($cDir = $root . '/compile')) {
                //查询状态
                is_dir($dDir = $root . '/delete') || mkdir($dDir, 0777, true);
                //重命名文件
                rename($cDir, $dDir . '/' . of_base_com_str::uniqid());

                //异步删除失效文件
                of_base_com_net::request(
                    OF_URL, array(), 'of_base_htmlTpl_tool::delete'
                );
            }

            unset($get['type']);
            header('Location: ?' . http_build_query($get));
        } else {
            //打印头
            of_view::head(array());

            //显示清除模版按钮
            $get['type'] = 'clear';
            $get = http_build_query($get);
            echo ' <input type="button" onclick="', 
                "window.location.href='?{$get}'",
                '" value="Clear the template cache">';
        }
    }

    /**
     * 描述 : 删除失效文件
     * 作者 : Edgar.lee
     */
    public static function delete() {
        //删除路径
        $dDir = ROOT_DIR . of::config(
            '_of.htmlTpl.path', OF_DATA . '/_of/of_base_htmlTpl_engine'
        ) . '/delete';

        $lFp = fopen($dDir . '/lock', 'a');

        if (
            flock($lFp, LOCK_EX | LOCK_NB) &&
            of_base_com_disk::each($dDir, $data, null)
        ) {
            foreach ($data as $k => &$v) {
                //是文件夹 && 删除文件夹
                $v && of_base_com_disk::delete($k);
            }
        }

        //解锁并关闭
        flock($lFp, LOCK_UN);
        fclose($lFp);
    }
}

if (of::dispatch('class') === 'of_base_htmlTpl_tool') {
    if (OF_DEBUG === false) {
        exit('Access denied: production mode.');
    } else {
        return true;
    }
}