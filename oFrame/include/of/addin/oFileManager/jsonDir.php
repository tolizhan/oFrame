<?php
if(
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&   //ajax请求
    isset($_SERVER['HTTP_REFERER']) &&    //存在请求地址
    ($temp = parse_url($_SERVER['HTTP_REFERER'])) &&    //解析地址
    $temp['host'] === $_SERVER['SERVER_NAME'] &&    //host有效
    dirname($temp['path'] . 'temp') . '/jsonDir.php' === $_SERVER['SCRIPT_NAME']    //请求路径有效
) {
    include 'include.php';
} else {
    exit;    //禁止直接请求浏览
}

/*验证区,初级安全限制*/
if(!isset($_SESSION['_oFileManager']['thisfileExtArr'])||!isset($_SESSION['_oFileManager']['thisBrowseDir'])||!isset($_SESSION['_oFileManager']['thisPermissions']))
{
    //这几个值中的任意一值不存在,都说明不是从正常路径访问的,但不是说明都存在就是没问题的
    exit('安全访问限制');
}
else
{
    $thisfileExtArr=$_SESSION['_oFileManager']['thisfileExtArr'];            //有效的上传扩展名array
    $thisBrowseDir=$_SESSION['_oFileManager']['thisBrowseDir'].'/';            //有效的浏览路径
    $thisPermissions=(int)$_SESSION['_oFileManager']['thisPermissions'];            //有效的管理权限
}

/*配置区*/
$rootBrowseDir=urlFinishing($_rootDir.$thisBrowseDir);        //磁盘浏览路径

/*初始区*/
$error=false;            //发送给客户端信息
$security=true;            //发送给客户端说明本次请求类型是是否安全,默认true(安全)
$response=false;        //发送给各户端操作信息
$outDirArr=array();        //输出的文件夹数组,结构为{文件夹名:{folderNum..:文件夹个数,fileNum..:文件个数},...}
$outFileArr=array();    //输出的文件数组,结构为{文件名:{'filesize':文件大小,'filectime':创建时间,'filemtime':修改时间},...}
$dir=null;                //需要索引子目录的目录
$dirIo=false;            //某个目录的目录流
$requestDir        = isset($_REQUEST['requestDir']) ? iconvCodec($_REQUEST['requestDir'], false) : '.';                        //客户端请求的目录
$requestType       = isset($_REQUEST['requestType']) ? $_REQUEST['requestType'] : '';                    //客户端请求的操作
$requestParameters = isset($_REQUEST['requestParameters']) ? $_REQUEST['requestParameters'] : '';        //客户端请求的操作

/*安全区*/
urlSecurityAuthentication($requestDir,'安全请求限制');
if($thisPermissions<3)
{
    if($requestType=='mobile'||$requestType=='mobileDir'||$requestType=='delete'||$requestType=='rename')
    {
        $requestType='Security';
    }
}
if($thisPermissions<2)
{
    if($requestType=='mkdir')
    {
        $requestType='Security';
    }
}

/*工作区*/
$requestDir.=($requestDir=trim($requestDir,'/\\'))===''?'./':'/';
$dir = $rootBrowseDir . $requestDir;

switch($requestType)
{
    case 'mkdir':                //创建文件夹
            if(urlSecurityAuthentication($requestDir.rawurlencode($requestParameters)))
            {
                @mkdir($dir.rawurlencode($requestParameters));
            }
            else
            {
                $error='失败:安全创建限制';
            }
            break;
    case 'rename':                //重命名
            $nameArr=explode('>|<',$requestParameters);
            if(count($nameArr)==2)        //重命名文件
            {
                if(urlSecurityAuthentication($requestDir.rawurldecode($nameArr[0])) && urlSecurityAuthentication($requestDir.rawurldecode($nameArr[1]), null, true))
                {
                    if(!@rename($dir . iconvCodec(rawurldecode($nameArr[0]), false), $dir . rawurlencode($nameArr[1])))
                    {
                        $error='失败:文件不存在或指定的文件名已存在';
                    }
                    /*else
                    {
                        rename($rootBrowseDir.'..Thumbnail/'.$requestDir.rawurldecode($nameArr[0]),$rootBrowseDir.'..Thumbnail/'.$requestDir.rawurlencode($nameArr[1]));
                    }*/
                } else {
                    $error='失败:安全重命名限制';
                }
            }
            else                    //重命名文件夹
            {
                if($dir !== $rootBrowseDir . './' && is_dir($dir))
                {
                    if(urlSecurityAuthentication($requestDir.rawurlencode($nameArr[0])))
                    {
                        $nameArr[0] = iconvCodec(rawurlencode($nameArr[0]), false);
                        if(@rename($dir, $dir . '../' . $nameArr[0]))
                        {
                            $dir=$dir . '../' . $nameArr[0] . '/';
                        }
                        else
                        {
                            $error='失败:文件夹不存在或指定的文件夹名已存在';
                        }
                    } else {
                        $error='失败:安全重命名限制';
                    }
                } else {echo $dir;}
            }
            break;
    case 'delete':
            if($requestParameters!=='')
            {
                if(urlSecurityAuthentication($requestDir.rawurldecode($requestParameters)))
                {
                    if(@unlink($dir . iconvCodec(rawurldecode($requestParameters), false)))
                    {
                        $error='成功:文件已删除';
                    }
                    else
                    {
                        $error='失败:文件不存在';
                    }
                }
                else
                {
                    $error='失败:安全删除限制';
                }
            }
            else
            {
                deletePath($dir);
                $error='成功:文件夹已删除';
                $dir=substr($dir,0,strrpos(substr($dir,0,-1),'/')+1);            //当前文件夹已删除,返回父文件夹得结构
            }
            break;
    case 'mobileDir':
    case 'mobile':
            $nameArr=explode('>|<',$requestParameters);
            if(count($nameArr)==2)        //移动文件
            {
                if(urlSecurityAuthentication($requestDir.rawurldecode($nameArr[0])) && urlSecurityAuthentication(trim(rawurldecode($nameArr[1]),'/\\').'/'.rawurldecode($nameArr[0])))
                {
                    $nameArr[0] = iconvCodec(rawurldecode($nameArr[0]), false);
                    if(!@rename($dir . $nameArr[0], $rootBrowseDir . trim(iconvCodec(rawurldecode($nameArr[1]), false),'/\\') . '/' . $nameArr[0]))
                    {
                        $error='失败:文件不存在或指定的文件名已存在';
                        //$response=$requestDir;
                    }
                    else
                    {
                        $error='成功:文件已移动';
                        $response = $nameArr[1];
                    }
                }
                else
                {
                    $error='失败:安全移动限制';
                }
            } else if(count($nameArr)==1 && is_dir($dir)) {               //移动文件夹
                $folderName=substr($dir,strrpos(substr($dir,0,-1),'/'));
                if(urlSecurityAuthentication(trim(rawurldecode($nameArr[0]),'/\\').$folderName))
                {
                    if(!@rename($dir, $rootBrowseDir . trim(iconvCodec(rawurldecode($nameArr[0]), false), '/\\') . $folderName))
                    {
                        $error='失败:文件夹不存在或指定的文件名已存在';
                        ($response=urlFinishing($requestDir.'/..'))==''?$response='/':null;
                    }
                    else
                    {
                        $error='成功:文件夹已移动';
                        $response=$nameArr[0];
                        $dir=substr($dir,0,strrpos(substr($dir,0,-1),'/')+1);            //当前文件夹已移动,返回父文件夹得结构
                    }
                }
                else
                {
                    $error='失败:安全移动限制';
                }
            }
            break;
    case 'Security':
            $error='您没有相应权限';
            $security=false;
}

$dirIo=@opendir($dir);
if($dirIo)
{
    while (($file = readdir($dirIo)) !== false)
    {
        if(
            $file === '.' || substr($file,0,2) === '..' ||    //过滤'.'及隐藏文件夹
            filterPathChecksum(urlFinishing($thisBrowseDir . '/' . $requestDir . '/' . $file . '/'), $filterPathArr) === false    //判断浏览目录是否安全
        ) {
            continue;
        }

        $temp = array();
        if(iconvCodec($file, null, $temp) === $file){    //符合编码要求
            $temp = rawurlencode($temp[0]);
            if(is_dir($dir . $file))
            {
                $outDirArr[$temp] = isNotEmptyDir($dir.$file);
            } else if(is_file($dir . $file)) {
                $outFileArr[$temp]=array();
                $outFileArr[$temp]['filesize']  = filesize($dir.$file);        //文件大小Byte
                $outFileArr[$temp]['filectime'] = date("Y-m-d H:i",filectime($dir.$file));        //创建时间    Y-m-d H:i:s
                $outFileArr[$temp]['filemtime'] = date("Y-m-d H:i",filemtime($dir.$file));        //修改时间
            }
        }

    }

    ksort($outDirArr);
    ksort($outFileArr);
    closedir($dirIo);
}
//输出json格式的escape编码[{文件夹名:{folderNum..:文件夹个数,fileNum..:文件个数},...},{文件名:{'filesize':文件大小,'filectime':创建时间,'filemtime':修改时间},...},{folderNum..:当前文件夹子文件夹个数,fileNum..:当前文件夹子文件个数,folderName..:当前文件名}]
echo json_encode(
             array(
                 $outDirArr,
                 $outFileArr,
                 array(
                     'folderNum..'=>count($outDirArr),
                     'fileNum..'=>count($outFileArr),
                     'folderName..'=>rawurlencode(iconvCodec(substr($dir,strrpos(substr($dir,0,-1),'/')+1,-1), true)),
                     'error..'=>$error,
                     'response..'=>$response,
                     'security..'=>$security
                     )
                 )
             );

/*辅助区*/
function deletePath($dir)
{
    if(is_file($dir))
    {
        unlink($dir);
    }
    else if(@rmdir($dir)==false)
    {
        if($dp = @opendir($dir))
        {
            while (($file=readdir($dp)) != false)
            {
                if ($file!='.' && substr($file,0,2)!='..')
                {
                    deletePath($dir.'/'.$file);
                }
            }
            closedir($dp);
        }
        @rmdir($dir);
    }
}
//判断文件夹是否不为空,返回值为[文件夹个数,是否有文件个数]
function isNotEmptyDir($directory)
{
    $returnArr=array(
        'folderNum..'=>0,
        'fileNum..'=>0,
    );
    $handle = opendir($directory);
    while (($file = readdir($handle)) !== false)
    {
        if ( $file != "." && strncmp($file, '..', 2) !== 0)//is_dir($directory.'/'.$file) &&
        {
            is_dir($directory.'/'.$file)?$returnArr['folderNum..']++:$returnArr['fileNum..']++;
        }
    }
    closedir($handle);
    return $returnArr;
}
//路径安全验证
function urlSecurityAuthentication($url, $error = null, $checkExtension = false)
{
    global $thisBrowseDir, $fileExtArr;
    $tempSecurityStr = urlFinishing($thisBrowseDir.$url);
    if(substr($tempSecurityStr, 0, strlen($thisBrowseDir)) != $thisBrowseDir)
    {
        if($error != null)
        {
            exit($error);
        }
        return false;
    }
    if($checkExtension)
    {
        return in_array(strtolower(pathinfo($url, PATHINFO_EXTENSION)), $fileExtArr);
    }
    return true;
}