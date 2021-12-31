<?php
/**
 * 描述 : 使IDE编辑器支持框架命名空间写法的方法跳转
 * 作者 : Edgar.lee
 */
require dirname(dirname(dirname(dirname(__FILE__)))) . '/include/of/of.php';

//框架类列表, 以"of_"开头的类
$ofClassList = array(
    'of_db'   => array('of_db', 'abstract '),
    'of_view' => array('of_view', '')
);
//提取框架类的路径
$ofClassPath = array(
    OF_DIR . '/accy',
    OF_DIR . '/base'
);
//编辑器框架空间导航, {空间名 : [继承类代码, ...]}
$ideNsNav = array();
//L类导航, [[方法, 参数, 主体, 静态]]
$linkNav = array();
//提取框架类名正则
$ofClassPreg = '@\s(abstract\s+)?class\s+(of_[^\s]+)\s@ms';
//L类方法名正则
$ofLinkPreg = '@\bof::link\(.*?(?<!\\\\)(?:\\\\{2})*(?:\'|"|e)\s*\);(?!\s*(?:\'|"))@ms';
//存储路径
$savePath = OF_DIR . '/base/link/codeNav.php';

//开始遍历类路径
foreach ($ofClassPath as &$dir) {
    //一次性读取深层磁盘路径
    of_base_com_disk::each($dir, $paths, false);

    foreach ($paths as $file => &$isDir) {
        //是php文件
        if (!$isDir && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            //提取代码
            $code = file_get_contents($file);
            //提取描述与方法名
            if (preg_match_all($ofClassPreg, $code, $match, PREG_SET_ORDER)) {
                foreach ($match as $k => &$v) $ofClassList[$v[2]] = array($v[2], $v[1]);
            }
            //提取L类方法
            if (preg_match_all($ofLinkPreg, $code, $match)) {
                $linkNav = array_merge($linkNav, $match[0]);
            }
        }
    }
}

//生成对应命名空间的继承类
foreach ($ofClassList as $k => &$v) {
    //生成命名空间
    $k = dirname(strtr($k, '_', '\\'));
    //生成继承类代码
    $ideNsNav[$k][] = '    ' . $v[1] . 'class ' . substr($v[0], strlen($k) + 1) . " extends \\{$v[0]} {\n    }";
}
foreach ($ideNsNav as $k => &$v) $v = "\nnamespace {$k} {\n\n" . join("\n\n", $v) . "\n\n}";
$ideNsNav = join("\n", $ideNsNav);

//生成L类导航文件
@eval('$linkNav = array(); ' . str_replace('of::link', '$linkNav[] = array', join("\n", $linkNav)));
foreach ($linkNav as &$v) {
    $v[3] = isset($v[3]) && $v[3] === false ? '' : 'static ';
    $v = "\n        public {$v[3]}function {$v[0]}({$v[1]}) {\n            /*{$v[2]}*/\n        }\n";
}
$linkNav = '    class L {' . join($linkNav) . '    }';
if ($temp = of::syntax($linkNav, false, $linkNav)) {
    throw new Exception(print_r($temp, true));
} else {
    $linkNav = "\nnamespace {\n\n{$linkNav}\n\n}";
}

//回写成代码导航文件
file_put_contents($savePath, join("\n", array(
    '<?php',
    '/**',
    ' * 描述 : 未实际运行, 仅作IDE编辑器对"of\xxx\yyy"及"L类"的代码跟踪',
    ' * 作者 : Edgar.lee',
    " */{$ideNsNav}\n{$linkNav}"
)));

//更新完成
echo '已更新 ', $savePath, ' 文件';