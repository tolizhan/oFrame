<?php
//加载框架
include dirname(__FILE__) . '/of.php';

/**
 * 描述 : 工作台
 * 作者 : Edgar.lee
 */
class of_index {
    /**
     * 描述 : 获取实例化对象
     * 返回 :
     *      返回实例化对象
     * 作者 : Edgar.lee
     */
    public static function index() {
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

        //输出模块, 设计上base模块可能不存在, 故不使用of_base_com_disk::each
        if (is_dir($path = OF_DIR . '/base') && $handle = opendir($path)) {
            while (is_string($v = readdir($handle))) {
                //寻找 of/base/xxx/_meta.php 文件
                if ($v[0] !== '.' && $list = &self::loadMeta("{$path}/{$v}/_meta.php")) {
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

        //输出探针
        if (OF_DEBUG !== false) {
            //磁盘空间路径
            is_dir($path = ROOT_DIR . OF_DATA) || $path = ROOT_DIR;
            //读取扩展版本
            $list = get_loaded_extensions();
            natcasesort($list);
            foreach ($list as &$v) $v = $v . '(' . phpversion($v) . ')';
            //磁盘空间
            $disk = array(
                round(disk_free_space($path) / 1073741824, 1),
                round(disk_total_space($path) / 1073741824, 1)
            );
            //提示 popen无法使用
            $tips = ini_get('safe_mode') || !function_exists('popen') ?
                'tips: <font color="red">safe_mode or popen() has been disabled</font><br>\n' : '';

            echo "<hr>\n",
                'OF: ', OF_VERSION, "<br>\n",
                'PHP: ', PHP_VERSION, ' x', PHP_INT_SIZE << 3, "<br>\n",
                'Server: ', $_SERVER['SERVER_SOFTWARE'], "<br>\n",
                'System: ', php_uname(), "<br>\n",
                'Time: ', date('Y-m-d H:i:s P e U', $_SERVER['REQUEST_TIME']), "<br>\n",
                'DiskSpace: ',
                    '<font', $disk[0] < 10 ? ' color="red"' : '', '>',
                        "{$disk[0]}/{$disk[1]}G ({$path})</font><br>\n",
                $tips,
                'Extensions: <span style="font: caption;">', join(', ', $list), '</span>';
        }
    }

    /**
     * 描述 : 加载元数据
     * 作者 : Edgar.lee
     */
    private static function &loadMeta($file) {
        //{"name" : 模块内容, "gets" : {get键 : get值, ...}}
        $list = is_file($file) ? include $file : null;
        //转为二维数组
        isset($list['name']) && $list = array($list);

        return $list;
    }
}

//访问框架类
of::dispatch(
    empty($_GET['c']) ? $_GET['c'] = 'of_index' : $_GET['c'],
    empty($_GET['a']) ? 'index' : $_GET['a'],
    strncmp('of_', $_GET['c'], 3) === 0
);