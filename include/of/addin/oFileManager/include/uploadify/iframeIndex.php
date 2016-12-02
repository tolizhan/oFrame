<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>无标题文档</title>
<style>
body{ margin:0px; padding:0px; overflow:hidden;}
</style>
</head>

<body>
<input type="file" name="uploadify" id="uploadify" style="display:none;" />
<script>
var ROOT_URL='';
var oFileManagerMainDir='../..';
</script>
<script src="../../js/jquery.js">//加载jquery</script>
<script src="scripts/jsCalloEditorUploadify.js"></script>
<script src="scripts/swfobject.js"></script>
<script src="scripts/jqueryUploadify.js"></script>
<script>
function initUploadify()
{
	if(typeof(config)=='object')
	{
		//初始化config
		var defaultConfig={
			uploadifyId:'uploadify',
			onAllCompleteFun:function(fileUrl)
						{
							alert(fileUrl);
						},
			fileExt:null,
			buttonText:null,
			width:null,
			height:null,
			hideBackground:null
		};
		for(var i in config)
		{
			defaultConfig[i]=config[i];
		}
		config=defaultConfig;
		
		//初始化Uploadify
		jsCalloEditorUploadify(config.uploadifyId,config.onAllCompleteFun,config.fileExt,config.buttonText,config.width,config.height,config.swfConfig,config.hideBackground);
	}
	else
	{
		setTimeout(initUploadify,1000);
	}
}
$(initUploadify);
</script>
</body>
</html>
