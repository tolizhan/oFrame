<?php
require('../../include.php');
require('../../fileExtension.php');

if (!empty($_FILES)) {
    $pathinfoArr = pathinfo($_FILES['Filedata']['name']);
    $fileName    = rawurlencode($_FILES['Filedata']['name']);                        //上传的文件名
    if(isset($_GET['folderUploadType']) && $_GET['folderUploadType'] === 'relative')        //判断文件上传所在文件夹的方式relative为相对方式,默认绝对方式
    {
        $_REQUEST['folder'] = urlFinishing($browseDir . '/' . $_REQUEST['folder']) . '/';
        if( !isset($_GET['fileName']) )
        {
            $_REQUEST['folder'] .= gmdate('Y/m/d/');
            $fileName = gmdate('His') . floor(microtime(true)*100) . '.' .$pathinfoArr['extension'];
        } elseif( $_GET['fileName'] ) {
            $fileName = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileName = rawurlencode($_GET['fileName']) . ($fileName ? '.' . $fileName : '');
        }
    }
    if(!in_array(strtolower($pathinfoArr['extension']),$fileExtArr))
    {
        //文件上传格式非法
        exit(0);
    }
    if(substr(urlFinishing($_REQUEST['folder'] .'/'. $_FILES['Filedata']['name']), 0, strlen($browseDir) + 1) !== $browseDir . '/')
    {
        //文件上传路径非法
        exit(0);
    }

    $folderName  = iconvCodec($_REQUEST['folder'], false);
    $tempFile = $_FILES['Filedata']['tmp_name'];
    $targetPath = $_rootDir . $folderName . '/';
    $targetFile =  str_replace('//','/',$targetPath) . $fileName;

    is_dir($targetPath) || mkdir($targetPath,0777,true);
    move_uploaded_file($tempFile,$targetFile);

    //生成缩略图
    $fileExtensionObj=new fileExtension('../..');
    $fileExtensionObj->getFileUrl(urlFinishing($folderName.'/'.$fileName),true,false);
    echo urlFinishing($_REQUEST['folder'] . '/'.$fileName);
}