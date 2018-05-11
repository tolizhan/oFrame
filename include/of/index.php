<?php
include dirname(__FILE__) . '/of.php';

//访问框架类
if (isset($_GET['c'])) {
    of::dispatch(
        $_GET['c'],
        isset($_GET['a']) ? $_GET['a'] : 'index',
        strncmp('of_', $_GET['c'], 3) === 0
    );
//访问管理页
} else {
    of_view::head(array('head' => array(
       '<style>
        .module {
            padding: 10px;
            border: 1px solid #000;
            margin: 10px 5px 0px;
            display: inline-block;
            white-space: pre;
            text-decoration: none;
            color: #000;
        }
        </style>'
    )));

    function &loadMeta($file) {
        //{"name" : 模块内容, "gets" : {get键 : get值, ...}}
        $list = is_file($file) ? include $file : null;
        //转为二维数组
        isset($list['name']) && $list = array($list);

        return $list;
    }

    if (is_dir($path = OF_DIR . '/base') && $handle = opendir($path)) {
        while (is_string($v = readdir($handle))) {
            //寻找 of/base/xxx/_meta.php 文件
            if ($v[0] !== '.' && $list = &loadMeta("{$path}/{$v}/_meta.php")) {
                foreach ($list as &$v) {
                    //初始化数据
                    isset($v['gets']) || $v['gets'] = array();

                    //保持debug状态
                    if (isset($_GET['__OF_DEBUG__'])) {
                        $v['gets']['__OF_DEBUG__'] = stripslashes($_GET['__OF_DEBUG__']);
                    }

                    //输出模块列表
                    echo '<a class="module" target="_blank" href="', 
                        OF_URL . '/index.php?', http_build_query($v['gets']),
                        '">', $v['name'],
                    '</a>';
                }
            }
        }
        closedir($handle);
    }
}