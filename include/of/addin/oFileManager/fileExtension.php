<?php
/*
* 描述:该类主要对文件操作行为扩展,如生成缩略图,数据库寻找索引记录,级联删除等等
* 函数:
*      fileExtension:初始化代码
*          参数:
*               oFileManagerDir:OFileManager文件夹的磁盘路径(也可以是../..形式)
*      getFileUrl:生成缩略图,并返回生成的路径,如果无法生成,则原样返回
*          参数:
*               $url:需要生成的文件路径(相对于虚拟目录的绝对目录,/../..形式)
*               $$Thumbnail:是否生成缩略图(true)
* 示例:
*      $fileExtensionObj=new fileExtension('E:\work\product\oFileManager');
*      $outFileUrl=$fileExtensionObj->getFileUrl('/img/..quickUpload/2011/03/17/032323130033220380.jpg');
*      该函数将生成/img/..quickUpload/2011/03/17/032323130033220380.jpg的缩略图,$outFileUrl为缩略图的地址
* 备注:
*      1.该类只有在数据库模式下才能起到良好的作用,一个页面推荐只实例化一个对象
*      2.缩略图的地址将存储在oFileManagerDir可浏览根目录的..Thumbnail文件夹中
*/
class fileExtension
{
    var $oFileManagerDir;            //OFileManager文件夹的磁盘路径
    var $_rootDir;                    //网站虚拟目录磁盘路径
    var $_rootUrl;                    //网站虚拟目录Url
    var $browseDir;                    //浏览个根目录(/../..绝对目录形式),一般为$rootDirUrl排除虚拟目录的后半部分
    //类初始化
    function fileExtension($oFileManagerDir=null)//OFileManager文件夹的磁盘路径(也可以是../..形式)
    {
        empty($oFileManagerDir)?exit(0):null;
        $this->oFileManagerDir=$oFileManagerDir;

        //初始化话系统路径
        include $this->oFileManagerDir . '/include.php';

        $this->_rootDir=$_rootDir;
        $this->_rootUrl=$_rootUrl;
        $this->browseDir=$browseDir;
    }
    //获取文件地址
    function getFileUrl($url=null,$Thumbnail=true,$cache=true)            //相对网站根目录绝对路径,是否获取缩略图,默认获取
    {
        if(empty($url) || !array_search(strtolower(pathinfo($url, PATHINFO_EXTENSION)), array('gif', 'jpg', 'png', 'bmp')))
        {
            return;
        }
        if(substr($url=urlFinishing($url),0,strlen($this->browseDir))!=$this->browseDir || !is_file($this->_rootDir . $url))
        {
            return $url;
        }
        if($Thumbnail)
        {
            $outFileUrl=urlFinishing($this->browseDir.'/..Thumbnail/'.urlFinishing(substr($url,strlen($this->browseDir)).'/..'));
            $outFileDir=$this->MiniImg(urlFinishing($this->_rootDir.'/'.$url),urlFinishing($this->_rootDir.'/'.$outFileUrl),$cache);                        //生成缩略图
            return substr(urlFinishing($outFileDir),strlen($this->_rootDir));
        }
        else
        {
            return $url;
        }
    }
    
    /**
    * $srcFile - 图形文件
    * $outFileDir - 输出文件路径
    * $cache - 如果有缓存，是否直接用缓存，默认true
    * $pixel - 尺寸大小，如：400*300
    * $_quality - 图片质量，默认75
    * $cut - 是否裁剪，默认1，当$cut=0的时候，将不进行裁剪
    * 示例："< img src=\"".MiniImg('images/image.jpg','300*180',72,0)."\">"
    **/
    private function MiniImg($srcFile, $outFileDir=null, $cache=true, $pixel='300*200', $_quality = 75, $cut=0){
        ini_set('max_execution_time', 0);
        $_type = strtolower(substr(strrchr($srcFile,"."),1));
        $pixelInfo = explode('*', $pixel);
        $pathInfo = pathinfo($srcFile);
        $_cut = intval($cut);
        $searchFileName = preg_replace("/\.([A-Za-z0-9]+)$/isU", "_".$pixelInfo[0]."x".$pixelInfo[1]."_".$cut.".\\1", $pathInfo['basename']);
        $outFileDir == null && $outFileDir = $pathInfo['dirname'];
        is_dir($outFileDir) || mkdir($outFileDir,0777,true);
        $miniFile = $outFileDir.'/'.$searchFileName;
        if($cache and file_exists($miniFile)) return $miniFile;
        $data = GetImageSize($srcFile);
        $FuncExists = 1;
        switch ($data[2]) {
            case 1:            //gif
                if(function_exists('ImageCreateFromGIF')) $_im = ImageCreateFromGIF($srcFile);
                break;
            case 2:            //jpg
                if(function_exists('imagecreatefromjpeg')) $_im = imagecreatefromjpeg($srcFile);
                break;
            case 3:            //png
                if(function_exists('ImageCreateFromPNG')) $_im = ImageCreateFromPNG($srcFile);  
                break;
            case 6:            //bmp，这里需要用到ImageCreateFromBMP
                $_im = $this->ImageCreateFromBMP($srcFile);  
                break;
        }
        if(!@$_im) return $srcFile;
        $sizeInfo['width'] = @imagesx($_im);
        $sizeInfo['height'] = @imagesy($_im);
        if(!$sizeInfo['width'] or !$sizeInfo['height']) return $srcFile;
        if($sizeInfo['width'] == $pixelInfo[0] && $sizeInfo['height'] == $pixelInfo[1] ) {
            return $srcFile;
        }elseif($sizeInfo['width'] < $pixelInfo[0] && $sizeInfo['height'] < $pixelInfo[1] ){//当文件宽度小于要缩放宽度时返回原路径 && @$miniMode=='2'
            return $srcFile;
        }else{
            $resize_ratio = ($pixelInfo[0])/($pixelInfo[1]);
            $ratio = ($sizeInfo['width'])/($sizeInfo['height']);
            if($cut==1){
                $newimg = imagecreatetruecolor($pixelInfo[0],$pixelInfo[1]);
                if($ratio>=$resize_ratio){                                        //高度优先
                    imagecopyresampled($newimg, $_im, 0, 0, 0, 0, $pixelInfo[0],$pixelInfo[1], (($sizeInfo['height'])*$resize_ratio), $sizeInfo['height']);
                    $_result = ImageJpeg ($newimg,$miniFile, $_quality);
                }else{                                                            //宽度优先
                    imagecopyresampled($newimg, $_im, 0, 0, 0, 0, $pixelInfo[0], $pixelInfo[1], $sizeInfo['width'], (($sizeInfo['width'])/$resize_ratio));
                    $_result = ImageJpeg ($newimg,$miniFile, $_quality);
                }
            }else{                                                                //不裁图
                if($ratio>=$resize_ratio){
                    $newimg = imagecreatetruecolor($pixelInfo[0],($pixelInfo[0])/$ratio);
                    imagecopyresampled($newimg, $_im, 0, 0, 0, 0, $pixelInfo[0], ($pixelInfo[0])/$ratio, $sizeInfo['width'], $sizeInfo['height']);
                    $_result = ImageJpeg ($newimg,$miniFile, $_quality);
                }else{
                    $newimg = imagecreatetruecolor(($pixelInfo[1])*$ratio,$pixelInfo[1]);
                    imagecopyresampled($newimg, $_im, 0, 0, 0, 0, ($pixelInfo[1])*$ratio, $pixelInfo[1], $sizeInfo['width'], $sizeInfo['height']);
                    $_result = ImageJpeg ($newimg,$miniFile, $_quality);
                }
            }
            ImageDestroy($_im);
            ImageDestroy($newimg);
            if($_result) return $miniFile;
            return $srcFile;
        }
    }
    
    /**
     * ImageCreateFromBMP() - 支持BMP图片函数
     * $filename - BMP图形文件
     * */
    
    private function ImageCreateFromBMP($fname)
    {
        $buf=@file_get_contents($fname);
        if(strlen($buf)<54) return false;    
        $file_header=unpack("sbfType/LbfSize/sbfReserved1/sbfReserved2/LbfOffBits",substr($buf,0,14));    
        if($file_header["bfType"]!=19778) return false;
        $info_header=unpack("LbiSize/lbiWidth/lbiHeight/sbiPlanes/sbiBitCountLbiCompression/LbiSizeImage/lbiXPelsPerMeter/lbiYPelsPerMeter/LbiClrUsed/LbiClrImportant",substr($buf,14,40));
        if($info_header["biBitCountLbiCompression"]==2) return false;   
        $line_len=round($info_header["biWidth"]*$info_header["biBitCountLbiCompression"]/8);
        $x=$line_len%4;
        if($x>0) $line_len+=4-$x;    
        $img=imagecreatetruecolor($info_header["biWidth"],$info_header["biHeight"]);
        switch($info_header["biBitCountLbiCompression"])
        {
            case 4:
                $colorset=unpack("L*",substr($buf,54,64));
                for($y=0;$y<$info_header["biHeight"];$y++)
                {
                    $colors=array();
                    $y_pos=$y*$line_len+$file_header["bfOffBits"];
                    for($x=0;$x<$info_header["biWidth"];$x++){
                        if($x%2)
                        $colors[]=$colorset[(ord($buf[$y_pos+($x+1)/2])&0xf)+1];
                        else   
                        $colors[]=$colorset[((ord($buf[$y_pos+$x/2+1])>>4)&0xf)+1];
                    }
                    imagesetstyle($img,$colors);
                    imageline($img,0,$info_header["biHeight"]-$y-1,$info_header["biWidth"],$info_header["biHeight"]-$y-1,IMG_COLOR_STYLED);
                }
                break;
            case 8:
                $colorset=unpack("L*",substr($buf,54,1024));
                for($y=0;$y<$info_header["biHeight"];$y++)
                {
                    $colors=array();
                    $y_pos=$y*$line_len+$file_header["bfOffBits"];
                    for($x=0;$x<$info_header["biWidth"];$x++)
                    {
                        $colors[]=$colorset[ord($buf[$y_pos+$x])+1];
                    }
                    imagesetstyle($img,$colors);
                    imageline($img,0,$info_header["biHeight"]-$y-1,$info_header["biWidth"],$info_header["biHeight"]-$y-1,IMG_COLOR_STYLED);
                }
                break;
            case 16:
                for($y=0;$y<$info_header["biHeight"];$y++)
                {
                    $colors=array();
                    $y_pos=$y*$line_len+$file_header["bfOffBits"];
                    for($x=0;$x<$info_header["biWidth"];$x++)
                    {
                        $i=$x*2;
                        $color=ord($buf[$y_pos+$i])|(ord($buf[$y_pos+$i+1])<<8);
                        $colors[]=imagecolorallocate($img,(($color>>10)&0x1f)*0xff/0x1f,(($color>>5)&0x1f)*0xff/0x1f,($color&0x1f)*0xff/0x1f);
                    }
                    imagesetstyle($img,$colors);
                    imageline($img,0,$info_header["biHeight"]-$y-1,$info_header["biWidth"],$info_header["biHeight"]-$y-1,IMG_COLOR_STYLED);
                }
                break;
            case 24:
                for($y=0;$y<$info_header["biHeight"];$y++)
                {
                    $colors=array();
                    $y_pos=$y*$line_len+$file_header["bfOffBits"];
                    for($x=0;$x<$info_header["biWidth"];$x++)
                    {
                        $i=$x*3;
                        $colors[]=imagecolorallocate($img,ord($buf[$y_pos+$i+2]),ord($buf[$y_pos+$i+1]),ord($buf[$y_pos+$i]));
                    }
                    imagesetstyle($img,$colors);
                    imageline($img,0,$info_header["biHeight"]-$y-1,$info_header["biWidth"],$info_header["biHeight"]-$y-1,IMG_COLOR_STYLED);
                }
                break;
            default:
                return false;
                break;
        }
        return $img;
    }
}

/*
* 描述:当直接请求该页时调用下面代码
* 参数:
*      $_GET['fileUrl']:相对于虚拟目录的文件路径
*      $_GET['thumbnail']:是否请求缩略图,如果不是图片则原样返回=1是=0不是(1)
*      $_GET['redirect']:是否需要跳转到请求后的路径=1是=0不是(1)
* 示例:
*      <img src='fileExtension.php?fileUrl=/img/..quickUpload/2011/03/17/032323130033220380.jpg' />
*/
if(strcasecmp(strtr($_SERVER["SCRIPT_FILENAME"], '\\', '/'), strtr(__FILE__, '\\', '/')) === 0)
{
    include 'include.php';
    $fileUrl=isset($_GET['fileUrl']) ? $_GET['fileUrl'] : null;
    $thumbnail=isset($_GET['thumbnail'])?(int)$_GET['thumbnail']:1;
    $redirect=isset($_GET['redirect'])?(int)$_GET['redirect']:1;
    if(!empty($fileUrl))
    {
        $pathinfoArr      = pathinfo($_SERVER["SCRIPT_FILENAME"]);
        $fileExtensionObj = new fileExtension($pathinfoArr['dirname']);
        $outFileUrl       = iconvCodec($fileExtensionObj->getFileUrl(iconvCodec($fileUrl, false), $thumbnail), true);
        if($redirect)
        {
            include($pathinfoArr['dirname'].'/include.php');
            $redirectUrl = urlFinishing($_rootUrl.$outFileUrl);
            $redirectUrl = rawurlencode($redirectUrl);
            $redirectUrl = preg_replace('/%2F/', '/', $redirectUrl);
            header("Location: {$redirectUrl}");
        } else {
            return $outFileUrl;
        }
    }
}