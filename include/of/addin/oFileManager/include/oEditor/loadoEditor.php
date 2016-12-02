<?php
	$tempPathinfoArr=pathinfo(preg_replace('/\\\\/', '/',__FILE__));
	include($tempPathinfoArr['dirname'].'/../../include.php');
	//获得oFileManager相对网站虚拟目录的文件路径(/../..形式)
	$oFileManagerMainDir=urlFinishing(substr(__FILE__,strlen($_rootDir)).'/../../..');
	if($_SERVER["SCRIPT_FILENAME"]!=preg_replace('/\\\\/', '/', __FILE__))
	{
		echo "<script>var oFileManagerMainDir='{$oFileManagerMainDir}';</script>";
		echo "<script src='{$_rootUrl}{$oFileManagerMainDir}/js/jsCalloFileManager.js' charset='UTF-8'></script>";
		echo "<script src='{$_rootUrl}{$oFileManagerMainDir}/include/oEditor/oEditor.js' charset='UTF-8'></script>";
	}
	else
	{
		//计算并整理网站路径
		$rootoFileManagerMainDir=rtrim(urlFinishing($_rootUrl.'/'.$oFileManagerMainDir),'/');
		//输出oEditor所需的所有接受加载代码
		echo "var oFileManagerMainDir='{$oFileManagerMainDir}';";																				//设置oFileManager相对网站虚拟目录的文件路径(/../..形式)
		echo "document.write(\"<script src='{$_rootUrl}{$oFileManagerMainDir}/js/jsCalloFileManager.js' charset='UTF-8' ><\\/script>\");";		//加载jsCalloFileManager.js主要用于调用oFileManager
		echo "document.write(\"<script src='{$_rootUrl}{$oFileManagerMainDir}/include/oEditor/oEditor.js' charset='UTF-8' ><\\/script>\");";		//加载oEditor.jsjsCalloFileManager.js主要用于调用oFileManager
	}