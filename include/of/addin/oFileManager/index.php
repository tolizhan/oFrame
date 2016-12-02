<?php
    include('include.php');

    /*处理get请求*/
    //获得get请求参数
    $requestPermissions=isset($_GET['permissions'])?(int)$_GET['permissions']:3;
    $requestBrowseDir=isset($_GET['browseDir'])?(string)$_GET['browseDir']:'/.';
    $requestFileExt=isset($_GET['fileExt'])?(string)$_GET['fileExt']:'';
    $requestSelectExt=isset($_GET['selectExt'])&&$_GET['selectExt']!=''?(string)$_GET['selectExt']:'[^\\.]+';

    //初始化请求
    if($requestPermissions==0)
    {
        $requestPermissions=3;
    }
    if($requestBrowseDir=='')
    {
        $requestBrowseDir='/.';
    }
    if($requestFileExt=='')
    {
        $requestFileExtArr=$fileExtArr;
    }
    else
    {
        $requestFileExtArr=explode(';',$requestFileExt);
    }
    /*成本次有效权限的变量*/
    //本次有效的权限
    $thisPermissions=$_SESSION['_oFileManager']['thisPermissions']=min($_SESSION['_oFileManager']['permissions'],$requestPermissions);
    //本次有效的上传格式
    $thisFileExtArr=$_SESSION['_oFileManager']['thisfileExtArr']=array_intersect($requestFileExtArr,$fileExtArr,$_SESSION['_oFileManager']['fileExtArr']);
    //本次有效的浏览路劲及文件跟路劲
    $thisBrowseDir=rtrim(urlFinishing($browseDir.'/'.$requestBrowseDir),'/');
    $thisRootDirUrl=rtrim(urlFinishing($_rootUrl.$browseDir.'/'.$requestBrowseDir),'/');

    //安全浏览校验
    if(!(
        !$safeBrowse ||    //非安全浏览
        isset($_SERVER['HTTP_REFERER']) &&    //存在请求地址
        ($temp = parse_url($_SERVER['HTTP_REFERER'])) &&    //解析地址
        $temp['host'] === $_SERVER['SERVER_NAME'] &&    //host有效
        $browseDir !== $thisBrowseDir    //浏览路径不为跟路径
    )) {
        exit;    //禁止直接请求浏览
    }

    //访问地址校验
    if(($temp = filterPathChecksum($thisBrowseDir, $filterPathArr)) === false || substr($thisBrowseDir,0,strlen($browseDir))!=$browseDir || !is_dir($_rootDir.$thisBrowseDir))
    {
        if($rowseFailureHandling===0 || $temp === false)            //出于安全考虑,当请求当地址非法或不存在时,终止操作
        {
            exit('请求的地址无数据');
        }
        else if($rowseFailureHandling===1)        //跳转到可浏览的根目录
        {
            $thisBrowseDir=$browseDir;
            $thisRootDirUrl=$_rootUrl.$browseDir;
        }
    }
    $_SESSION['_oFileManager']['thisBrowseDir']=$thisBrowseDir;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>oFileManager文件管理系统</title>
<link href="style/oFileManager.css" rel="stylesheet" />
<link href="style/oDialogDiv.css" rel="stylesheet" />
</head>

<body scroll="no">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td class="nav" colspan="3">
            <div>
                <span class="title" href="#">
                    <span>当前文件夹</span>
                </span>
                <span id="loading">正在加载中...</span>
            </div>
        </td>
    </tr>
    <tr>
        <td class="menu">
            <div>
                <span>
                    <ul>
                        <ol dirUrl='/'><a href="javascript:void(0)" style=" font-weight:bold;" >根目录</a></ol>
                    </ul>
                    <!--<ul>
                        <ol><a href="javascript:void(0)" >添加课程7</a></ol>
                        <ol><a href="javascript:void(0)" >添加课程8</a></ol>
                        <ul>
                            <ol><a href="javascript:void(0)" >添加课程</a></ol>
                            <ol><a href="javascript:void(0)" >添加课程</a></ol>
                        </ul>
                    </ul>-->
                </span>
            </div>
        </td>
        <td class="menuSwitch"><img src="images/menuSwitchOff.gif" width="6" height="40"/></td>
        <td class="main">
            <table width="100%" height="100%" border="1" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="100%" height="60%" align="center">
                    <div id="preview" >
                    无预览
                    </div>
                  </td>
                  <td height="59%">
                    <div id="operation" >
                        <span style="float:left;" >
                          <table width="200" border="0">
                            <tr>
                                <th>文件路径</th>
                                <td colspan="2"><input type="text" style="width:100px;" readonly="readonly" /></td>
                            </tr>
                            <tr>
                                <th>文件大小</th>
                                <td colspan="2">&nbsp;</td>
                            </tr>
                            <tr>
                                <th>创建时间</th>
                                <td colspan="2">&nbsp;</td>
                            </tr>
                            <tr>
                                <th>修改时间</th>
                                <td colspan="2">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>&nbsp;</th>
                                <td colspan="2">&nbsp;</td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align:left; text-indent:4.3em; color:#999" >(文件操作)</td>
                            </tr>
                            <tr>
                                <th style="text-align:right">&nbsp;</th>
                                <th colspan="2" style="text-align:left;" >&nbsp;</th><!--文件移动-->
                            </tr>
                            <tr>
                                <th style="text-align:right">&nbsp;</th>
                                <th colspan="2" style="text-align:left; text-indent:3.1em;">&nbsp;</th>
                            </tr>
                            <tr>
                                <td>&nbsp;</th>
                                <td colspan="2">&nbsp;</td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align:left; text-indent:4.3em; color:#999" >(文件夹操作)</td>
                            </tr>
                            <tr>
                                <th style="text-align:right">&nbsp;</th>
                                <th colspan="2">&nbsp;</th>
                            </tr>
                            <tr>
                                <th style="text-align:right">&nbsp;</th>
                                <th style="text-align:left; width:4em;">&nbsp;</th>
                                <th>&nbsp;</th>
                            </tr>
                          </table>
                        </span>
                        <span id="fileQueue" ></span>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" height="39%" >
                    <div id="fileList" >
                        <!--<span>sadasd</span>-->
                        <!--<div id="fileDownload" ><img src="images/fileDown.gif" /><strong>下载</strong></div>-->
                    </div>
                  </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="footer" colspan="3" valign="middle" >
            <div>
                <span></span>
            </div>
        </td>
    </tr>
</table>
<script>
var returnUrlPrefix='<?php echo $thisBrowseDir; ?>';            //返回文件的前缀
var rootDirUrl='<?php echo $thisRootDirUrl; ?>';    //文件浏览器当前页面相对于被管理目录的url    (/../..形式)
var fileExt='*.<?php echo join(';*.',$thisFileExtArr); ?>';                        //文件的上传格式
var selectExt=/(.+\.<?php echo @preg_replace('/;/','|.+\\.',$requestSelectExt); ?>)$/i;                        //文件的上传格式
var permissions=<?php echo $thisPermissions; ?>;    //操作权限,=1只有浏览功能,=2还包括上传文件和建立文件夹功能,=3还包括重命名,删除,移动文件(夹)
var sizeLimit=<?php echo (int)ini_get('upload_max_filesize')*1048576; ?>;        //最大上传字节
<?php
if( $safeBrowse )    //安全浏览
{
    echo '!window.opener && window.parent == window &&(window.location.href = "/")';
}
?>
</script>
<script src="js/jquery.js">//加载jquery</script>
<script src="js/mouseDrag.js">//加载鼠标拖拽脚本</script>
<script src="js/oDialogDiv.js">//加载alertDiv浮动div脚本</script>
<script src="js/oFileManager.js">//加载oFM主脚本</script>
<script src="include/uploadify/scripts/swfobject.js">//上传swf控件操作</script>
<script src="include/uploadify/scripts/jqueryUploadify.js">//上传JS操作</script>
</body>
</html>