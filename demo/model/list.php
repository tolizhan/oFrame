<?php
class ctrl_list {
    /**
     * 描述 : 初始化演示
     * 作者 : Edgar.lee
     */
    public static function init() {
        //ctrl_类映射
        of::event('of::loadClass', array(
            'classPre' => 'ctrl_', 'mapping' => 'demo_ctrl_'
        ), true);
        //model_类映射
        of::event('of::loadClass', array(
            'classPre' => 'model_', 'mapping' => 'demo_model_'
        ), true);
        //serv_类映射
        of::event('of::loadClass', array(
            'classPre' => 'serv_', 'mapping' => 'demo_serv_'
        ), true);

        //非框架入口
        if ($_SERVER['SCRIPT_NAME'] === rawurldecode(ROOT_URL) . '/index.php') {
            //具体演示
            if( isset($_GET['c']) ) {
                //页面输出完成后打印源码
                empty($_POST) && strncmp('of_', $_GET['c'], 3) && of::event('of::halt', 'printCode');
            //打印演示列表
            } else {
                //提取描述与方法名
                $matchFunc = '@^[^\n]*描述 : ([^\n]*)[^(]*?public +function +([^()]+) *\([^;\n]*$@ms';
                preg_match_all($matchFunc, file_get_contents(ROOT_DIR . '/demo/ctrl/index.php'), $match, PREG_SET_ORDER);

                //打印html头
                of_view::head();
                foreach($match as &$v) {
                    //打印演示列表
                    echo "<a href='?c=ctrl_index&a={$v[2]}' target=_blank >{$v[1]}</a><br>\n";
                }

                echo '还有更多的演示在 /demo 中, 请参考 API 使用';
            }

            /**
             * 描述 : 打印源码
             * 作者 : Edgar.lee
             */
            function printCode() {
                //提取方法体
                $matchFunc = '@^( +)public +(?:\w+ +)?function +' .(isset($_GET['a']) ? $_GET['a'] : 'index'). '.*?^\\1}@ms';
                preg_match($matchFunc, file_get_contents(ROOT_DIR . '/demo/ctrl/index.php'), $match);
                echo '<br><br><hr>';
                highlight_string("<?php    源码如下 : \n" . $match[0] . "\n?>");
            }
        }
    }
}

ctrl_list::init();