<?php
/**
 * 描述 : 部署 UI 框架
 * 作者 : Edgar.lee
 */
class of_addin_bsui_setup {
    /**
     * 描述 : 调度回调
     * 作者 : Edgar.lee
     */
    public static function dispatch(&$params) {
        //不是框架层
        if (strncmp('of_', $params['class'], 3)) {
            of_view::head('head', 
                //加载UI核心样式
                '<link rel="stylesheet" href="' .OF_URL. '/addin/bsui/css/lxui.min.css">' .
                //加载UI核心脚本
                '<script src="' .OF_URL. '/addin/bsui/js/lxui.min.js"></script>' .
                //兼容 IE 9
                '<!--[if lt IE 9]>' .
                    '<script src="' .OF_URL. '/addin/bsui/js/respond.min.js"></script>' .
                    '<script src="' .OF_URL. '/addin/bsui/js/html5shiv.min.js"></script>' .
                '<![endif]-->' .
                //兼容 IE 6 7
                '<!--[if lte IE 7]>' .
                    '<link rel="stylesheet" href="' .OF_URL. '/addin/bsui/css/lxui-ie6.min.css">' .
                    '<script src="' .OF_URL. '/addin/bsui/js/lxui-ie.js"></script>' .
                '<![endif]-->'
            );
        }
    }
}

of::event('of::dispatch', 'of_addin_bsui_setup::dispatch');