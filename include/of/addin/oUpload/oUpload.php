<?php
/**
 * 描述 : 脚步上传参数
 * 注明 :
 *      FILES['fileData'] 结构 : {
 *          "name"     : 带扩展名的文件名
 *          "tmp_name" : 临时文件的路径
 *      }
 *      POST  结构 : {
 *          "folder" : 相对data的文件夹
 *          "file"   : 指定文件路径,默认自动生成
 *      }
 * 作者 : Edgar.lee
 */
//ajax跨站预检
header('Access-Control-Allow-Origin: *');
if ($index = &$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) {
    header('Access-Control-Allow-Headers: ' . $index);
}
//基本参数校验
if (empty($_FILES['fileData']) || !isset($_POST['folder'])) exit;
//加载核心
include dirname(dirname(dirname(__FILE__))) . '/of.php';
//关闭SESSION
session_write_close();
//读取配置文件
$config = of::config('_of.addin.oUpload', array()) + array(
    'filtExt' => '@^(?:exe|php|html|htm|js|css)$@',
    'folder'  => '@^' . OF_DATA . '/upload/@'
);
//保证有字符输出
echo ' ';

if (empty($_POST['file'])) {
    //文件扩展名
    $fExt = strtolower(pathinfo($_FILES['fileData']['name'], PATHINFO_EXTENSION));
    //存储目录
    $temp = date('/Y/m/d/', $_SERVER['REQUEST_TIME']);
    //存储路径
    $path = $temp . of_base_com_str::uniqid() . ($fExt ? '.' . $fExt : '');
} else {
    //存储路径
    $path = $_POST['file'];
}

//真实路径(过滤掉非法字符)
$path = OF_DATA . of_base_com_str::realpath(str_replace(
    array(':', '*', '?', '"', '<', '>', '|'),
    '',
    $_POST['folder'] . $path
));
//文件扩展名
$fExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (
    //路径有效
    $path[0] === '/' &&
    //框架写入路径禁止上传
    strncmp($temp = OF_DATA . '/_of/', $path, strlen($temp)) &&
    //路径验证
    preg_match($config['folder'], $path, $math) &&
    //扩展验证
    !preg_match($config['filtExt'], $fExt)
) {
    //实际存储路径
    $file = ROOT_DIR . $path;
    //创建路径
    is_dir($temp = dirname($file)) || @mkdir($temp, 0777, true);
    //文件移动
    move_uploaded_file($_FILES['fileData']['tmp_name'], $file);
    //输出目录
    echo substr($path, strlen(rtrim($math[isset($math[1])], '/')));
}