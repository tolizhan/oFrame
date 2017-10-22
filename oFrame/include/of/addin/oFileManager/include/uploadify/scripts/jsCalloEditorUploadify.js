/*
* 描述:专为oEditor制作的上传函数
* 参数:
*      uploadifyId      : 需要使用Uploadify插件的ID
*      callbackObj      : 上传各阶段回调函数,
*                         {
*                             'onComplete'    : 每当上传结束回调,传入一个参数为虚拟目录的路径(/../../文件名),
*                             'onInit'        : 初始化完成后回调,没有参数,
*                             'onCancel'      : 点击取消上传后回调({'fileCount' : 取消一个文件后，文件队列中剩余文件的个数, 'allBytesTotal' : 取消一个文件后，文件队列中剩余文件的大小}),
*                             'onProgress'    : 上传进度实时回调({'percentage' : 当前完成的百分比, 'bytesLoaded' : 当前上传的大小, 'allBytesLoaded' : 文件队列中已经上传完的大小, 'speed' : 上传速率 kb/s}),
*                             'onAllComplete' : 当所有文件上传结束调用({'filesUploaded':上传的所有文件个数, 'errors' : 出现错误的个数, 'allBytesLoaded' : 所有上传文件的总大小, 'speed' : 平均上传速率 kb/s})
*                             'onSelectOnce'  : 当选择文件时回调({'fileCount' : 已选择文件个数, 'filesSelected' : 同时选择文件个数, 'filesReplaced' : 重复选择的文件, 'allBytesTotal' : 所选文件总大小})
*                         }, 如果只传入一个方法,相当于'onComplete'函数
*      fileExt          : 扩展名(jpg;gif;png)
*      buttonText       : 按钮文本,默认<font color="#000000" size="12" >上传</font>
*      width            : flash的宽,默认25
*      height           : flash的高,默认14
*      swfConfig        : 是一个对象,针对上传核心(swf)的配置
*                         {
*                                 queueID  : 上传文件列表ID,=true(默认):由弹出层模式显示上传列表; false:在当前uploadifyId的正下方出现上传列表; 字符串ID:由指定的'字符串ID'为上传列表
*                                 auto     : 自动上传,=true(默认):自动上传; =false:等待执行$('#'+uploadifyId).uploadifyUpload();才会上传
*                                 multi    : 多文件上传,=true:允许多文件上传; =false(默认):禁止多文件上传
*                                 folder   : 指定上传到服务器端得文件夹,该文件夹地址相对oFM所能浏览的跟目录,默认'/..quickUpload',(/../..形式)
*                                 fileName : 指定文件名,false(默认)=按日期重写文件名,''=原文件名,字符串=指定文件名
*                         }
*      hideBackground   : 是否隐藏背景,默认true
*      备注 : uploadifyId对象'fileCount'属性为剩余上传文件个数
* 示例:
*     <input type="file" name="uploadify" id="uploadify" style="display:none" />
*     $(
*         function()
*         {
*             jsCalloEditorUploadify('uploadify',
*                 function(fileUrl)
*                 {
*                     alert(fileUrl);
*                 },
*                 'jpg;gif;png'
*             );
*         }
*     )
*     //将ID为uploadify的上传组件改为只能上传jpg;gif;png的文件,当上传结束后弹出上传名
* 备注:
*     本方法需要使用oDialogDiv函数,可以利用下面的方法载入
*     if(typeof(oDialogDiv)!='function')
*     {
*         document.write('<script src="js/oDialogDiv.js"><\/script>');//加载alertDiv浮动div脚本
*     }
*     本方法需要用oFileManagerMainDir变量来确认oFileManager的文件路径,相对于虚拟目录的根目录
*     var oFileManagerMainDir='/oFileManager';
*/
if(typeof(oFileManagerMainDir)=='undefined')
{
    oFileManagerMainDir='';
}
if(typeof(oDialogDiv)!='function')
{
    document.write('<script src="'+ROOT_URL+oFileManagerMainDir+'/js/oDialogDiv.js"><\/script>');//加载alertDiv浮动div脚本
}
function jsCalloEditorUploadify(uploadifyId, callbackObj, fileExt, buttonText, width, height, swfConfig, hideBackground)
{
    var createWindowObj=window;         //当前父类
    var parentsUntilArr=[window];       //从当前窗口向上层的所有窗口
    var buttonImg = null;               //图片按钮链接地址
    var handle=null;                    //产生浮动层的句柄
    var uploadifyClearQueue=null;       //清除全部上传(fn)
    var onProgress=null;                //上传进度监听函数(fn)
    var onSelectOnce=null;              //当选则文件后
    var onComplete=null;                //当上传成功后
    var onError=null;                   //错误后回调
    var uploadifyListFont;              //弹出层的上传组件,一个jqurey对象[上传进度(40%形式),取消上传按钮]
    var errorList = {};                 //错误列表,发生的错误
    var sizeLimit = null;               //上传大小限制
    var uploadifyObj = null;            //uploadifyId的jquery对象
    width == null && (width = 25);      //初始化宽度
    height == null && (height = 14);    //初始化高度

    if(typeof callbackObj === 'function')
    {
        callbackObj = {'onComplete' : callbackObj};
    }
    if(typeof uploadifyId === 'string' && typeof callbackObj.onComplete === 'function')
    {
        uploadifyObj = $("#"+uploadifyId);

        //判断flash支持
        try {
            version = Boolean(navigator.plugins != null && navigator.plugins.length > 0 ?
                navigator.plugins["Shockwave Flash 2.0"] || navigator.plugins["Shockwave Flash"] :
                new ActiveXObject("ShockwaveFlash.ShockwaveFlash"));
        } catch (e) {
            version = false;
        }
        if( version === false )
        {
            var temp = ['<img src="' + ROOT_URL + oFileManagerMainDir + '/images/flashInstall.jpg" style="width:' +width+ 'px; height:' +height+ 'px" />', uploadifyObj.parents('a')];
            if( temp[1].length )
            {
                temp[1].attr({'target' : '_blank', 'href' : 'http://get.adobe.com/flashplayer/', 'onclick' : null})
                    .unbind('click')
                    .html(temp[0]);
            } else {
                uploadifyObj.hide().after('<a target="_blank" href="http://get.adobe.com/flashplayer/">' + temp[0] + '</a>');
            }
            return ;
        }

        //获取最顶层的oDialogDiv方法
        while(createWindowObj.parent!=createWindowObj)
        {
            createWindowObj=createWindowObj.parent;
            parentsUntilArr[parentsUntilArr.length]=createWindowObj;
        }
        for(var j=parentsUntilArr.length-1;j>-1;j--)
        {
            if(parentsUntilArr[j]!=null&&typeof(parentsUntilArr[j].oDialogDiv)=='function')
            {
                createWindowObj=parentsUntilArr[j];
                parentsUntilArr[j]=null;
                break;
            }
            else if(parentsUntilArr[j]==null)
            {
                break;
            }
        }

        //参数初始化
        fileExt==null?fileExt='*':null;
        buttonText==null?buttonText='<font color="#000000" size="12" >上传</font>':null;
        hideBackground==null?hideBackground=true:null;
        uploadifyObj.attr('fileCount', 0);                                                                          //上传文件数量
        typeof swfConfig == 'object'?null:swfConfig={};
        swfConfig.queueID==null?swfConfig.queueID=true:null;                                                        //上传文件列表ID
        swfConfig.auto==null?swfConfig.auto=true:null;                                                              //是否自动上传
        swfConfig.multi==null?swfConfig.multi=false:null;                                                           //多文件上传
        swfConfig.folder==null?swfConfig.folder='/..quickUpload':null;                                              //指定上传到服务器的文件夹
        swfConfig.fileName = typeof swfConfig.fileName === 'string' ? '&fileName=' + swfConfig.fileName : '';       //文件名

        var $imgTagMatchArr = buttonText.match(/^<img [^<>]*?src *?= *?('|")([^<>]*?)\1[^<>]*?\/>$/i);    //尝试提取图片按钮    /^<img [^<>]*?\/>$/i.test(buttonText) ? (buttonImg = $(buttonText).prop('src') , buttonText = null) : null;
        if($imgTagMatchArr !== null && typeof $imgTagMatchArr[2] === 'string')
        {
            buttonText = null;
            buttonImg = $imgTagMatchArr[2];
        }
        $.ajax({
            'url'      : ROOT_URL + oFileManagerMainDir + '/include/uploadify/echoUploadMaxFilesize.php',
            'async'    : false,
            'dataType' : 'json',
            'success'  : function(data) {
                sizeLimit = data.maxFileSize;
                swfConfig.fileName = '&sessionId=' + data.sessionId + swfConfig.fileName;
            }
        });

        //方法初始化
        uploadifyClearQueue=function()
        {
            uploadifyObj.uploadifyClearQueue();
            handle && createWindowObj.oDialogDiv.dialogClose(handle);
            //如果上传中发生了错误
            var tempMsg = '';
            for(var i in errorList)
            {
                tempMsg += errorList[i].join(' , ') + ' - ' + i + ' Error ';
            }
            errorList = {};
            tempMsg === '' || oDialogDiv.tip(tempMsg, 5000);    //错误提示

            //全部上传结束回调
            if(typeof callbackObj.onAllComplete === 'function')
            {
                callbackObj.onAllComplete(arguments[1], arguments[0]);
            }
        }
        onProgress=function()
        {
            if(swfConfig.queueID===true)
            {
                uploadifyListFont.eq(0).html(arguments[3]['percentage']+'% / '+$('#'+uploadifyId).attr('fileCount'));
            }

            //全部上传结束回调
            if(typeof callbackObj.onProgress === 'function')
            {
                callbackObj.onProgress(arguments[3], arguments[2], arguments[1], arguments[0]);
            }
        }
        onSelectOnce=function()
        {
            uploadifyObj.attr('fileCount', arguments[1]['fileCount'])    //当前剩余文件
                              .attr('fileTotal', arguments[1]['fileCount']);   //上传文件总数
            //没有选择或上传文件大小限制导致的没有上传文件
            if(arguments[1]['fileCount'] === 0)
            {
                uploadifyClearQueue();
                return;
            }

            //生成弹出层上传列表
            if(swfConfig.queueID === true)
            {
                handle=createWindowObj.oDialogDiv('上传进度','<div style="margin:0px; padding:0px; height:20px;"><font style="float:left;">0%</font> <font style="float:right; cursor:pointer;" >取消上传</font></div>',200,null,[0]);
    
                uploadifyListFont=$(createWindowObj.document.body).find('#oDialogDiv_'+handle+' > .scroll > .content div font');
                uploadifyListFont.eq(1).click(uploadifyClearQueue);
            }

            if(typeof callbackObj.onSelectOnce === 'function')
            {
                callbackObj.onSelectOnce(arguments[1], arguments[0]);
            }
        }
        onComplete=function()
        {
            uploadifyObj.attr('fileCount', arguments[4]['fileCount']);
            callbackObj.onComplete(arguments[3],{'fileCount' : arguments[4]['fileCount'], 'fileObj' : arguments[2]});
        }
        onError = function(event, queueId, fileObj, errorObj){
            if(typeof errorList[errorObj.type] === 'undefined')
            {
                errorList[errorObj.type] = [];
            }
            //写入错误列表
            errorList[errorObj.type][errorList[errorObj.type].length] = fileObj.name;
        }

        //加载flash上传
        uploadifyObj.uploadify({
            'uploader'       : ROOT_URL+oFileManagerMainDir+'/include/uploadify/scripts/uploadify.swf?v=' + (new Date).getTime(),    //解决360兼容模式下不能上传的问题
            'script'         : encodeURIComponent(ROOT_URL+oFileManagerMainDir+'/include/uploadify/uploadify.php?folderUploadType=relative' + swfConfig.fileName).replace(/%20/g, '+'),
            'cancelImg'      : ROOT_URL+oFileManagerMainDir+'/include/uploadify/cancel.png',
            'folder'         : swfConfig.folder,
            'queueID'        : swfConfig.queueID,
            'auto'           : swfConfig.auto,
            'multi'          : swfConfig.multi,
            'sizeLimit'      : sizeLimit,
            'fileExt'        : '*.'+fileExt.replace(/;/g,';*.'),
            'fileDesc'       : '支持格式(*.'+fileExt.replace(/;/g,';*.')+')',
            'wmode'          : 'transparent',
            'width'          : width,
            'height'         : height,
            'buttonText'     : buttonText,                                //html标签
            'buttonImg'      : buttonImg,                               //图片按钮连接地址
            'hideBackground' : hideBackground,                            //隐藏背景
            'onSelectOnce'   : onSelectOnce,
            'onProgress'     : onProgress,
            'onComplete'     : onComplete,
            'onAllComplete'  : uploadifyClearQueue,
            'onError'        : onError,
            'onCancel'       : function(){
                //取消后回调
                if(typeof callbackObj.onCancel === 'function')
                {
                    uploadifyObj.attr('fileCount', arguments[3]['fileCount']);
                    callbackObj.onCancel(arguments[3], arguments[2], arguments[1], arguments[0]);
                }
            },
            'onInit'         : function(){
                //取消后回调
                if(typeof callbackObj.onInit === 'function')
                {
                    callbackObj.onInit();
                }
            }
        });
    }
}

//修改属性(上传组件ID, 设置变量, 设置内容)
jsCalloEditorUploadify.updateSettings = function(id, name, value)    //更新上传组件属性
{
    var calleeFun = arguments.callee;
    var temp;
    if(document.getElementById(id))
    {
        if((temp = document.getElementById(id + 'Uploader')) && document.getElementById(id + 'Uploader').updateSettings)
        {
            if( typeof name === 'function' )
            {
                name.call(temp, value);
            } else {
                $('#' + id).uploadifySettings(name, value);
            }
        } else {
            window.setTimeout(function(){
                calleeFun(id, name, value);
            }, 100);
        }
    }
}