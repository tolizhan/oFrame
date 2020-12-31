<?php
/**
 * 描述 : 部署 UI 框架
 * 作者 : Edgar.lee
 */
class demo_file_bsui_setup {
    /**
     * 描述 : 调度回调
     * 作者 : Edgar.lee
     */
    public static function dispatch(&$params) {
        //不是框架层 && 不是IE
        if (
            strncmp('of_', $params['class'], 3) &&
            !strpos($_SERVER["HTTP_USER_AGENT"], 'MSIE')
        ) {
            $bUrl = ROOT_URL . '/demo/file/bsui';

            of_view::head('head', array(
                //替换框架jQuery
                'jQuery' => '<script src="' . $bUrl . '/js/jquery.js"></script>',
                '<script src="' . $bUrl . '/js/vue.js"></script>',
                //加载UI核心
                '<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">',
                '<link rel="stylesheet" href="' . $bUrl . '/css/bootstrap.css">',
                '<script src="' . $bUrl . '/js/bootstrap.bundle.js"></script>',
            ));
        }
    }
}

of::event('of::dispatch', 'demo_file_bsui_setup::dispatch');