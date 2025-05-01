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
//L类遍历, []
$linkVal = array();
//L类导航, [[方法, 参数, 主体, 静态, 注释]]
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

//生成L类属性列表
of_base_com_disk::each(OF_DIR . '/base/com', $linkVal);
$linkVal['view'] = new ReflectionClass('of_view');
foreach ($linkVal as $k => &$v) {
    if ($k === 'view') {
        //存在注释 && 格式化注释
        ($v = $v->getDocComment()) && $v = "\n        " . preg_replace('@^\s+@m', '         ', $v);
        $v = "{$v}\n        public of_view \$view;\n";
    //是php文件 && 不以"_"开头的文件名
    } else if (pathinfo($k, PATHINFO_EXTENSION) === 'php' && ($v = substr(basename($k), 0, -4)) && $v[0] !== '_') {
        $k = new ReflectionClass("of_base_com_{$v}");
        //存在注释 && 格式化注释
        ($k = $k->getDocComment()) && $k = "\n        " . preg_replace('@^\s+@m', '         ', $k);
        $v = "{$k}\n        public of_base_com_{$v} \$_{$v};\n";
    } else {
        unset($linkVal[$k]);
    }
}
$linkVal = join($linkVal);

//生成L类导航文件
@eval('$linkNav = array(); ' . str_replace('of::link', '$linkNav[] = array', join("\n", $linkNav)));
foreach ($linkNav as &$v) {
    //匹配方法名与参数
    if (preg_match('@([\w\\\\]+)::(\w+)\s*\(@', $v[2], $v[4])) {
        //通过反射机制提取方法的注释
        $r = new ReflectionClass($v[4][1]);
        $f = $r->getMethod($v[4][2]);
        //存在注释 && 格式化注释
        ($v[4] = $f->getDocComment()) && $v[4] = "\n        " . preg_replace('@^\s+@m', '         ', $v[4]);
    } else {
        $v[4] = '';
    }
    $v[3] = isset($v[3]) && $v[3] === false ? '' : 'static ';
    $v = "{$v[4]}\n        public {$v[3]}function {$v[0]}({$v[1]}) {\n            {$v[2]}\n        }\n";
}
$linkNav = join($linkNav);//'    class L {' . join($linkNav) . '    }';

//检查语法
if ($temp = of::syntax($temp = "class L {{$linkNav}}", false, $temp)) {
    throw new Exception(print_r($temp, true));
} else {
    $linkNav = "\nnamespace {\n\n    class L {{$linkVal}{$linkNav}    }\n\n}";
}

//回写成代码导航文件
file_put_contents($savePath, join("\n", array(
    '<?php',
    '/**',
    ' * 描述 : 用于IDE编辑器对"of\xxx\yyy"及"L类"的代码跟踪',
    ' * 作者 : Edgar.lee',
    " */{$ideNsNav}\n{$linkNav}"
)));

//更新完成
echo '已更新 ', $savePath, ' 文件';