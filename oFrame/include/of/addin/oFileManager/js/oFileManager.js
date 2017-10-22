/*部分初始值*/
var windowObj=window;                //window对象,为嵌入式扩展准备的
var dirArr={'folderName..':'/'};        //文件及文件夹结构:dirArr{'..':{文件名:{'filesize':文件大小,'filectime':创建时间,'filemtime':修改时间},...},文件夹名:{folderNum..:文件夹个数,fileNum..:文件个数},...}'..'为该文件中的所有文件
var selectFileCallBackFun=function(url,thisWindow){window.close()};        //选择文件后回调的方法,参数(url[returnUrlPrefix+选择的文件路径],thisWindow[当前页的window])
var parentIframeWidth=null;            //当该页包含在iframe时,该值为iframe的宽度,解决IE6 下的样式问题

/**
* 描  述:后台框架布局所需的JS
* 作  者:Edgar.Lee
**/
//页面布局调整
$(function() {
    var temp;    //临时存储

    //当窗口大小改变时,调整布局
    $(window).resize(function() {
        //调整主tabl中层的高度,解决IE6
        $('td.menu').parent().children().height($(window).height() - 50);
        //调整主操作区预览处的高度,解决IE
        /*var width=$('#preview').width('10').height('10').parent().width();
        var height=$('#operation').css('display','none').parent().height();
        $('#preview').width(width).height(height);
        $('#operation').height(height).css('display','block');*/
        //调整文件列表高度,防止因为文件过多影响样式
        temp = $('#fileList').hide();    //显示在加载列表后完成,兼容IE6
        temp.parent().height('39%').height(temp.parent().height() - 5);    //减5是为了解决IE6下抖动增加2px高度问题,table会自使用5px差值
        temp.show();

        if(typeof JS_OFplayer === 'function' && $('#objectID').length)
        {
            try
            {
                setTimeout(function(){JS_OFplayer('objectID','event','normalScreen')},400);
            }
            catch(e){}
        }
    });
    $(window).trigger('resize');
    
    //解决chrome菜单栏隐藏后样式改变的问题
    if(navigator.userAgent.indexOf("Chrome")!=-1)
    {
        var bodyTableTrArr=$('body table tbody').children('tr');
        bodyTableTrArr.eq(0).children('td').attr('colspan','999');
        bodyTableTrArr.eq(2).children('td').attr('colspan','999');
    }
            
    //添加菜单栏显示隐藏
    $('td.menuSwitch').click(
        function()
        {
            var previewObj=$('#preview');
            var width=previewObj.width();
            if($('td.menu').css('display')=='none')
            {
                previewObj.width(width-160);
            }
            $('td.menu').toggle('fast',
                function()
                {
                    $(window).trigger('resize');
                    $('td.menuSwitch').children('img').attr('src',$('td.menu').css('display')=='none'?'images/menuSwitchOn.gif':'images/menuSwitchOff.gif');
                }
            );
        }
    );
    
    //初始化父类索引及相关方法
    if(window.dialogArguments!=null)
    {
        selectFileCallBackFun=window.dialogArguments;
    }
    else if(window.parent!=window)
    {
        windowObj=window.parent;
    }
    var parentsUntilArr=[window,windowObj];
    while(windowObj.parent!=windowObj)
    {
        windowObj=windowObj.parent;
        parentsUntilArr[parentsUntilArr.length]=windowObj;
    }
    for(var i=parentsUntilArr.length-1;i>-1;i--)        //将最父层拥有oDialogDiv的方法添加到本页索引
    {
        $(parentsUntilArr[i]).ready(
            function()
            {
                for(var j=parentsUntilArr.length-1;j>-1;j--)
                {
                    if(parentsUntilArr[j]!=null&&typeof(parentsUntilArr[j].oDialogDiv)=='function')
                    {
                        windowObj.oDialogDiv=parentsUntilArr[j].oDialogDiv;
                        parentsUntilArr[j]=null;
                        break;
                    }
                    else if(parentsUntilArr[j]==null)
                    {
                        break;
                    }
                }
            }
        )
    }
    
    //初始化操作权限列表
    permissionsInit();
    
    //初始化文件列表
    requestDir({obj:$('.menu div span ul ol:eq(0)')});
});

//------------------------------------------------------------------------初始化权限功能(开始)------------------------------------------------------------------------//
/**
* 描  述:初始化当前权限,主要为功能显示
* 作  者:Edgar.Lee
**/
function permissionsInit()
{
    loading('正在初始化,请稍后...');
    var operationList=$('#operation th');
    operationList.eq(4).html('选择文件').click(
        function()
        {
                var thisDirUrl=$('#operation td').eq(0).children('input').attr('dirUrl');
                if(thisDirUrl != null)
                {
                    if(selectExt.test(thisDirUrl))
                    {
                        selectFileCallBackFun(returnUrlPrefix+thisDirUrl,window);
                    }
                    else
                    {
                        windowObj.oDialogDiv('温馨提示','您只能选择扩展名为<font color="red">'+String(selectExt).replace(/\\|\.|\+|\/i|\/|\$/g,'')+'</font>的格式');
                    }
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请选中选择的文件');
                    loading('请选中要选择的文件',3000);
                }
        }
    ).css('cursor','pointer');
    if(permissions>1)
    {
        operationList.eq(8).html('<input type="file" name="uploadify" id="uploadify" />');
        $("#uploadify").uploadify(
            {
                'uploader'       : 'include/uploadify/scripts/uploadify.swf',                //uploadify.swf 文件的相对路径，该swf文件是一个带有文字BROWSE的按钮，点击后弹出打开文件对话框，默认值：uploadify.swf。
                'script'         : 'include/uploadify/uploadify.php',                //后台处理程序的相对路径 。默认值：uploadify.php
                'cancelImg'      : 'include/uploadify/cancel.png',                        //指定取消上传的图片，默认'cancel.png'
                'folder'         : 'upload',                            //要上传到的服务器路径，默认'/'
                'queueID'        : 'fileQueue',                        //文件队列的ID，该ID与存放文件队列的div的ID一致。
                'auto'           : true,                            //选定文件后是否自动上传，默认false
                'multi'          : true,                            //是否允许同时上传多文件，默认false
                'fileExt'        : fileExt,                    //支持的上传格式
                'fileDesc'       : '支持格式:'+fileExt,                    //选择文件时 '文件类型(T)' 中显示的文字
                'width'          : 50,                                //上传按钮的宽,默认110
                'height'         : 21,                                //上传的高,默认30
                'wmode'          : 'transparent',                        //设置该项为transparent 可以使浏览按钮的flash背景文件透明，并且flash文件会被置为页面的最高层。 默认值：opaque 。
                'buttonImg'      : 'include/uploadify/Browse.jpg',        //浏览按钮的图片
                'simUploadLimit' : 2,                                //允许同时上传文件
                'sizeLimit'      : sizeLimit,                            //上传文件的大小限制btys
                'onSelectOnce'   : function(){
                    var nowFolder = $('.title span:eq(0)').attr('dirUrl');
                    var folder = escape(phpUrlCode.rawurldecode(returnUrlPrefix + nowFolder));
                    $('#uploadify').uploadifySettings('folder',folder);
                    $('#uploadify').uploadifySettings('nowFolder',nowFolder);
                    if($('#fileQueue').css('display')=='none')
                    {
                        $('#fileQueue').css('display','block');
                        //$('#preview').width($('#preview').width()-200);
                        $('#operation').width($('#operation').width()+200);
                    }
                },
                'onCancel'       : function(){
                    if(arguments[3]['fileCount']==0)
                    {
                        $('#fileQueue').css('display','none');
                        $('#operation').width($('#operation').width()-200);
                        //$('#preview').width($('#preview').width()+200);
                    }
                },
                'onAllComplete'  : function(){
                    loading('<font color="#009900">'+(arguments[1]['filesUploaded']-arguments[1]['errors'])+'/'+arguments[1]['filesUploaded']+'的文件已上传成功</font>',2000);
                    loading.lock=true;
                    $('#fileQueue').css('display','none');
                    $('#operation').width($('#operation').width()-200);
                    //$('#preview').width($('#preview').width()+200);
                    
                    //刷新当前文件夹
                    refreshFolder($('#uploadify').uploadifySettings('nowFolder'), false, true);
                }
            }
        );
        jQuery.browser.mozilla && window.frameElement && (function() {    //火狐19 上传按钮不显示
            var temp;
            if(window.frameElement.style.visibility === 'visible' && (temp = document.getElementById('uploadifyUploader')) && document.getElementById('uploadifyUploader').updateSettings)
            {
                temp.style.display = 'inline-block';
                window.setTimeout(function(){
                    temp.style.display = 'inline';
                }, 500);
            } else {
                window.setTimeout(arguments.callee, 100);
            }
        })()

        operationList.eq(9).html('新建').click(
            function()
            {
                nodeNameOperation('请输入文件夹名',function(callBack,parentWindowObj,callBackObj)
                    {
                        if(callBack)
                        {
                            requestDir(
                                {
                                    'obj':refreshFolder($('.title span:eq(0)').attr('dirUrl'), true, true),
                                    'type':'mkdir',
                                    'parameters':callBackObj.oDialogDivObj.find('input').val()
                                }
                            );
                        }
                    }
                )
            }
        ).css('cursor','pointer');
    }
    else
    {
        operationList.eq(8).html('<font color="#CCCCCC" >无操作选项</font>');
    }
    if(permissions>2)
    {
        operationList.eq(5).html('<strong style="margin-left:3.1em">文件移动</strong><br />').click(
            function(e)
            {
                var thisDirUrl=$('#operation td').eq(0).children('input').attr('dirUrl');
                if(thisDirUrl!=null)
                {
                    var thisClickObj=$(this).children('br');
                    var menuItemsHtml='';            //生成的子菜单的html
                    var mouseEnterFun=null;            //菜单的鼠标滑入事件
                    var mouseClickFun=null;            //菜单的鼠标点击事件
                    
                    menuItemsHtml='<ol dirurl="/"><a href="javascript:void(0)">根目录</a></ol>';//floatMenu.getMenuItemsHtml(dirArr);
                    mouseEnterFun=function(obj,callback)
                    {
                        var dirObjItemArr=indexDirArr(obj.attr('dirUrl'));
                        var menuItemsHtml=floatMenu.getMenuItemsHtml(dirObjItemArr);
                        if(!callback&&menuItemsHtml==''&&dirObjItemArr['folderNum..']>0)
                        {
                            var dirObj=refreshFolder(obj.attr('dirUrl'),true,true);
                            dirObj.get(0).callbackFun=function()
                            {
                                mouseEnterFun(obj,true);
                            };
                            dirObj.trigger('click');
                        }
                        else
                        {
                            floatMenu(obj,menuItemsHtml,mouseEnterFun,mouseClickFun);
                        }
                    };
                    mouseClickFun=function(obj,e)
                    {
                        windowObj.oDialogDiv('<font color="red">请注意</fon>','<font color="red">您确定要移动当前文件到该文件夹?!</font>',230,null,[2,
                            function(callBack)
                            {
                                var dirUrl=thisDirUrl.substr(0,thisDirUrl.lastIndexOf('/'));
                                if(callBack)
                                {
                                    requestDir(
                                        {
                                            'obj':refreshFolder(dirUrl,true,true),
                                            'type':'mobile',
                                            'parameters':thisDirUrl.substr(thisDirUrl.lastIndexOf('/')+1)+">|<"+obj.attr('dirUrl')
                                        }
                                    );
                                }
                            }
                        ]);
                        thisClickObj.next('ul').remove();
                        e.stopPropagation();
                    };
                    floatMenu(thisClickObj,menuItemsHtml,mouseEnterFun,mouseClickFun);
                    $(document).click(floatMenu.documentClose);
                    e.stopPropagation();
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请选择要移动的文件');
                    loading('请选择要移动的文件',3000);
                }
            }
        ).css('cursor','pointer');
        operationList.eq(6).html('重命名').click(
            function()
            {
                var thisDirUrl=$('#operation td').eq(0).children('input').attr('dirUrl');
                if(thisDirUrl!=null)
                {
                    var dirUrl=thisDirUrl.substr(0,thisDirUrl.lastIndexOf('/'));
                    var fileName=thisDirUrl.substr(thisDirUrl.lastIndexOf('/')+1);
                    nodeNameOperation('请输入文件名',function(callBack,parentWindowObj,callBackObj)
                        {
                            if(callBack)
                            {
                                requestDir(
                                    {
                                        'obj':refreshFolder(dirUrl,true,true),
                                        'type':'rename',
                                        'parameters':fileName+">|<"+callBackObj.oDialogDivObj.find('input').val()
                                    }
                                );
                            }
                        }
                        ,phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(fileName))
                        ,true
                    )
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请选择要重命名的文件');
                    loading('请选择要重命名的文件',3000);
                }
            }
        ).css('cursor','pointer');
        operationList.eq(7).html('删除').click(
            function()
            {
                var thisDirUrl=$('#operation td').eq(0).children('input').attr('dirUrl');
                if(thisDirUrl!=null)
                {
                    var dirUrl=thisDirUrl.substr(0,thisDirUrl.lastIndexOf('/'));
                    var fileName=thisDirUrl.substr(thisDirUrl.lastIndexOf('/')+1);
                    windowObj.oDialogDiv('<font color="red">请注意</fon>','<font color="red">您确定要删除该文件?!</font>',null,null,[2,
                        function(callBack)
                        {
                            if(callBack)
                            {
                                requestDir(
                                    {
                                        'obj':refreshFolder(dirUrl,true,true),
                                        'type':'delete',
                                        'parameters':fileName
                                    }
                                );
                            }
                        }
                    ]);
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请选择要删除的文件');
                    loading('请选择要删除的文件',3000);
                }
            }
        ).css('cursor','pointer');
        
        operationList.eq(10).html('重命名').click(
            function()
            {
                var thisDirUrl=$('.title span:eq(0)').html();
                if(thisDirUrl!=''&&thisDirUrl!='/')
                {
                    var folderName=thisDirUrl.substr(thisDirUrl.lastIndexOf('/')+1);
                    nodeNameOperation('请输入文件夹名',function(callBack,parentWindowObj,callBackObj)
                        {
                            if(callBack)
                            {
                                requestDir(
                                    {
                                        'obj':refreshFolder($('.title span:eq(0)').attr('dirUrl'), true, true),
                                        'type':'rename',
                                        'parameters':callBackObj.oDialogDivObj.find('input').val()
                                    }
                                );
                            }
                            loading();
                        }
                        ,folderName
                    )
                    loading('<font color="red">文件夹操作请慎重~</font>',3000);
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请指定修改的文件夹,<br/>根目录不能修改');
                    loading('<font color="red">请指定修改的文件夹<br/>根目录不能修改</font>',3000);
                }
            }
        ).css('cursor','pointer');
        operationList.eq(11).html('<strong style="margin-left:1.5em">移动</strong><br />').click(
            function(e)
            {
                var thisDirUrl=$('.title span:eq(0)').attr('dirUrl');
                if(thisDirUrl!=''&&thisDirUrl!='/')
                {
                    var folderObj=refreshFolder(thisDirUrl, true, true);    //当前文件夹对象
                    var thisClickObj=$(this).children('br');
                    var menuItemsHtml='';            //生成的子菜单的html
                    var mouseEnterFun=null;            //菜单的鼠标滑入事件
                    var mouseClickFun=null;            //菜单的鼠标点击事件
                    
                    menuItemsHtml='<ol dirurl="/"><a href="javascript:void(0)">根目录</a></ol>';//floatMenu.getMenuItemsHtml(dirArr,folderObj.attr('dirUrl'));
                    mouseEnterFun=function(obj,callback)
                    {
                        var dirObjItemArr=indexDirArr(obj.attr('dirUrl'));
                        var menuItemsHtml=floatMenu.getMenuItemsHtml(dirObjItemArr,folderObj.attr('dirUrl'));
                        if(!callback&&menuItemsHtml==''&&dirObjItemArr['folderNum..']>0)
                        {
                            var dirObj=refreshFolder(obj.attr('dirUrl'),true,true);
                            dirObj.get(0).callbackFun=function()
                            {
                                mouseEnterFun(obj,dirObj.attr('dirUrl'));
                            };
                            refreshFolder(dirObj,false,true);
                        }
                        else
                        {
                            floatMenu(obj,menuItemsHtml,mouseEnterFun,mouseClickFun);
                        }
                    };
                    mouseClickFun=function(obj,e)
                    {
                        windowObj.oDialogDiv('<font color="red">请注意</fon>','<font color="red">您确定要移动当前文件夹?!</font>',null,null,[2,
                            function(callBack)
                            {
                                if(callBack)
                                {
                                    requestDir(
                                        {
                                            'obj':folderObj,
                                            'type':'mobileDir',
                                            'parameters':obj.attr('dirUrl')
                                        }
                                    );
                                }
                            }
                        ]);
                        thisClickObj.next('ul').remove();
                        e.stopPropagation();
                    };
                    floatMenu(thisClickObj,menuItemsHtml,mouseEnterFun,mouseClickFun);
                    $(document).click(floatMenu.documentClose);
                    e.stopPropagation();
                    loading('<font color="red">文件夹操作请慎重~</font>',3000);
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请指定移动的文件夹,<br/>根目录不能移动');
                    loading('<font color="red">请指定移动的文件夹<br/>根目录不能移动</font>',3000);
                }
            }
        ).css('cursor','pointer');
        operationList.eq(12).html('删除').click(
            function()
            {
                var thisDirUrl=$('.title span:eq(0)').html();
                if(thisDirUrl!=''&&thisDirUrl!='/')
                {
                    windowObj.oDialogDiv('<font color="red">请注意</fon>','<font color="red">您确定要删除该文件夹?!</font>',null,null,[2,
                        function(callBack)
                        {
                            if(callBack)
                            {
                                requestDir(
                                    {
                                        'obj':refreshFolder($('.title span:eq(0)').attr('dirUrl'), true, true),
                                        'type':'delete'
                                    }
                                );
                            }
                            loading();
                        }
                    ]);
                    loading('<font color="red">文件夹操作请慎重~</font>',3000);
                }
                else
                {
                    //windowObj.oDialogDiv('目标不明确','请指定删除的文件夹,<br/>根目录不能删除');
                    loading('<font color="red">请指定删除的文件夹<br/>根目录不能删除</font>',3000);
                }
            }
        ).css('cursor','pointer');
    }
    loading();
}

//------------------------------------------------------------------------初始化权限功能(结束)------------------------------------------------------------------------//



//------------------------------------------------------------------------初始化文件列表(开始)------------------------------------------------------------------------//
/**
* 描  述:添加文件夹点击事件,主要功能是文件夹展开收缩切换,相关子目录加载及子文件展示
* 作  者:Edgar.Lee
**/
function folderSlideToggle()
{
    $('.menu div span ol').unbind('click').click(
        function(e)
        {
            var dirUrl=$(this).attr('dirUrl');
            var arrObj=indexDirArr(dirUrl);
            
            if(typeof($(this).get(0).callbackFun)=='function')
            {
                e.stopPropagation();
            }
            
            //文件夹展开收缩切换,相关子目录加载
            $(this).next('ul').slideToggle('fast');
            if(typeof(arrObj['..'])!=='object'&&$(this).next('ul').length==0&&($(this).attr('folderNum')!='0'||$(this).attr('fileNum')!='0'))
            {
                requestDir({obj:$(this)});
            }
            else
            {
                //子文件展示
                fileListSwitch(dirUrl);
            }
            
            //背景加亮
            $('.menu div span ol a').css('color','#000000');
            $(this).find('a').css('color','#06F');
        }
    );
}

/**
* 描  述:获取指定文件下的文件并生成html添加到fileList中
* 参数名:
*     dirUrl    :请求的路径
* 作  者:Edgar.Lee
**/
function fileListSwitch(dirUrl)
{
    dirUrl = dirUrl != null ? dirUrl : $(this).prev('ol').attr('dirUrl');
    dirUrl === '/' ? dirUrl = '' : null;
    var fileListHtml='';
    var arrObj=indexDirArr(dirUrl);
    for(var i in arrObj['..'])
    {
        fileListHtml+='<span dirUrl="'+dirUrl+'/'+i+'" >'+phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(i))+'</span>';
    }
    if(fileListHtml=='')
    {
        fileListHtml='<font color="#CCCCCC">此文件夹为空</font>';
    }
    $('.title span:eq(0)').html(dirUrl == '' ? '/' : phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(dirUrl)))
                          .attr('dirUrl', dirUrl || '/')
                          .css('margin-left','0px');
    if($(document.body).width()<$('.title').width()+50)
    {
        $('.title span:eq(0)').css('margin-left',$(document.body).width()-$('.title').width()-50+'px');
    }
    $('#fileList').html(fileListHtml);
    
    //清空当前文件信息
    $('#operation td')
    .eq(0).children('input').removeAttr('dirUrl').val('').end().end()
    .eq(1).html('').end()
    .eq(2).html('').end()
    .eq(3).html('');
    
    //添加文件预览事件
    filePreview();
}

/**
* 描  述:指定的文件预览
* 参数名:
*     dirUrl    :预览的文件路径
* 作  者:Edgar.Lee
**/
function filePreview()
{
    var thisDirUrl='';                //服务端发送的rawurlencode字符串
    var thisUnescapeDirUrl='';        //服务端磁盘的字符串
    $('#fileList span').mouseenter(
        function()
        {
            //添加状态栏
            thisDirUrl=$(this).attr('dirUrl');
            thisUnescapeDirUrl=phpUrlCode.rawurldecode(thisDirUrl);
            $('.footer div span').css('opacity',0).html(phpUrlCode.rawurldecode(thisUnescapeDirUrl)).animate({opacity: "1"},"fast");
            
            //增加下载
            $('<div id="fileDownload" ><img src="images/fileDown.gif" /><strong><a href="'+phpUrlCode.rawurlencode(rootDirUrl+thisUnescapeDirUrl)+'" title="一些如swf,txt,jpg等格式请右键另存为" target="_blank" >下载</a></strong></div>').css('opacity',0.4).appendTo($(this))
            .mouseenter(
                function()
                {
                    $(this).animate({'opacity':0.8});
                }
            )
            .mouseleave(
                function()
                {
                    $(this).animate({'opacity':0.4});
                }
            )
            .click(
                function(e)
                {
                    $('#fileDownload').remove();
                    e.stopPropagation();
                }
            );
        }
    ).mouseleave(
        function()
        {
            $('.footer div span').html('');
            $('#fileDownload').remove();
        }
    ).click(function(){
        var arrObj=indexDirArr(thisDirUrl.substr(0,thisDirUrl.lastIndexOf('/')));
        var fileName=thisDirUrl.substr(thisDirUrl.lastIndexOf('/')+1);
        var fileType=fileName.substr(fileName.lastIndexOf('.')+1).toLocaleUpperCase();
        //文件预览
        if(fileType=='JPG'||fileType=='GIF'||fileType=='BMP'||fileType=='PNG')
        {
            //$('#preview').html('<img height="100%" src="'+rootDirUrl+thisDirUrl+'" />');
            $('#preview').html('<img height="100%" src="fileExtension.php?fileUrl='+returnUrlPrefix+thisDirUrl+'&thumbnail=1&redirect=1" />');
        } else if(fileType=='SWF') {
            $('#preview').html(getPlayerHtml(rootDirUrl+thisDirUrl));
        } else if(fileType=='FLV'||fileType=='MP3') {
            //alert(phpUrlCode.rawurlencode(rootDirUrl+thisUnescapeDirUrl));
            $('#preview').html(getPlayerHtml('include/player/ofplayer.swf','SkinURL=skin/defaultSE.zip&JSenable=true&file='+rootDirUrl+thisDirUrl));
            //$('#preview').html(getPlayerHtml('include/player/ofplayer.swf','SkinURL=skin/defaultSE.zip&file=http://localhost/oFileManager/img/wmv%E8%A7%86%E9%A2%91.Flv'));
        } else if(fileType=='WMV') {
            $('#preview').html(
                '<EMBED src="'+rootDirUrl+thisDirUrl+'" width="100%" height="100%" '+($.browser.msie?'windowlessVideo="1"':'')+' type=audio/mpeg controls="smallconsole" autostart="true">'
            );
        } else if(fileType=='TXT') {
            $('#preview').html(
                '<iframe width="100%" height="100%" frameborder="0" src="'+rootDirUrl+thisDirUrl+'"></iframe>'
            );
        } else {
            $('#preview').html('无预览');
        }

        //显示文件基本信息
        var fileSizeStr=arrObj['..'][fileName]['filesize'];
        if(fileSizeStr/1024>1)
        {
            if((fileSizeStr=Math.round(fileSizeStr/1024))/1024>1)
            {
                if((fileSizeStr=Math.round(fileSizeStr/1024))/1024>1)
                {
                    fileSizeStr=Math.round(fileSizeStr/1024)+'GB';
                } else {
                    fileSizeStr+='MB';
                }
            } else {
                fileSizeStr+='KB';
            }
        } else {
            fileSizeStr+='Byte';
        }
        $('#operation td')
        .eq(0).children('input').attr('dirUrl',thisDirUrl).val(thisUnescapeDirUrl).end().end()
        .eq(1).html(fileSizeStr).end()
        .eq(2).html(arrObj['..'][fileName]['filectime']).end()
        .eq(3).html(arrObj['..'][fileName]['filemtime']);
        
        //背景加亮
        $('#fileList span').css('background-color','transparent');
        $(this).css('background-color','#FFFFFF');
    }).dblclick(function(){
        $('#operation th:eq(4)').click();    //触发选择文件
    });
}

/**
* 描  述:获取服务端指定文件夹下的文件夹及文件
* 参数名:
*     object    :请求的对象
*             {
*                 obj:当前文件夹对象,
*                 type:操作类型;mkdir(新建文件夹)|
*                 parameters:参数,当新建文件夹时,为新建的文件名
*             }
* 示  例:
*     requestDir($('.menu div span'),'');
*     请求根目录并将生成的目录文件结构放到$('.menu div span')中去
* 作  者:Edgar.Lee
**/
function requestDir(object)
{
    var obj=$(object.obj);            //
    var dirUrl=obj.attr('dirUrl');        //告诉php要在这个目录操作
    dirUrl==='/'?dirUrl='':null;
    var data='requestDir='+dirUrl;        //Ajax请求参数
    if(object.type)
    {
        data+='&requestType='+object.type;
    }
    if(object.parameters)
    {
        data+='&requestParameters='+phpUrlCode.rawurlencode(object.parameters);
    }

    loading('正在加载文件夹,请稍后...');
    $.ajax(
        {
            url: "jsonDir.php",
            dataType:'json',
            type :'post',
            data :data,
            success: function(msg)
                    {
                        //<ol><a href="javascript:void(0)" >添加课程</a></ol>
                        var oldNextUl={};                                    //本次请求前的文件夹DOM对象
                        var newAddUlObj=null;                                //本次请求后生成的文件夹DOM对象
                        var parentDirUrl=dirUrl.substr(0,dirUrl.lastIndexOf('/'));        //当前文件路径的父路径
                        var parentArrObj=indexDirArr(parentDirUrl);                //当前文件结构对象的父对象
                        var arrObj=indexDirArr(dirUrl);                        //当前文件结构对象
                        var dirHtml='';

                        arrObj['folderNum..']=msg[2]['folderNum..'];
                        arrObj['fileNum..']=msg[2]['fileNum..'];
                        msg[2]['folderName..']=msg[2]['folderName..']!=''&&msg[2]['folderName..']!='.'?msg[2]['folderName..']:'/';
                        
                        //显示系统提示信息
                        if(msg[2]['error..'])
                        {
                            if(msg[2]['error..'].substr(0,3)=='成功:')
                            {
                                loading('<font color="#009900">'+msg[2]['error..'].substr(3)+'</font>',500);
                                loading.lock=true;
                            }
                            else
                            {
                                windowObj.oDialogDiv('温馨提示',msg[2]['error..']);
                            }
                        }

                        //处理客户端响应的操作
                        if(msg[2]['response..'])
                        {
                            var refreshFolderObj=refreshFolder(msg[2]['response..'],true,true);
                            refreshFolderObj.get(0).callbackFun=function(){};
                            refreshFolder(refreshFolderObj,false,true);
                        }

                        //根据不同的操作做出节点调整
                        if(msg[2]['security..']&&(object.type==='delete'&&object.parameters==null||object.type==='mobileDir'))
                        {
                            delete parentArrObj[arrObj['folderName..']];
                            obj.next('ul').remove();
                            obj.remove();
                            refreshFolder(parentDirUrl,true,true).trigger('click').trigger('click');
                            loading();
                            return;
                        }

                        //更改当前文件夹得结构对象
                        if(parentArrObj!=arrObj&&arrObj['folderName..']!==msg[2]['folderName..'])
                        {
                            delete parentArrObj[arrObj['folderName..']];
                            arrObj['folderName..']=msg[2]['folderName..'];
                            parentArrObj[arrObj['folderName..']]=arrObj;
                            dirUrl=dirUrl.substr(0,dirUrl.lastIndexOf('/')+1)+arrObj['folderName..'];
                            obj.attr('dirUrl',dirUrl);
                            arrObj['dirUrl..']=dirUrl;
                        }

                        //更新当前文件显示
                        obj.attr('folderNum',arrObj['folderNum..']);
                        obj.attr('fileNum',arrObj['fileNum..']);
                        obj.children('a').attr('title',phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(arrObj['folderName..']))+'\n(有'+arrObj['folderNum..']+'个文件夹和'+arrObj['fileNum..']+'个文件)').html(phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(arrObj['folderName..']))+'&nbsp;('+arrObj['folderNum..']+'-'+arrObj['fileNum..']+')').attr('class',arrObj['folderNum..']>0?'notEmptyDir':'');

                        //更新当前文件夹子文件夹
                        arrObj['..']=msg[1];        //将文件放入其中
                        for(var i in msg[0])        //将文件夹放入其中
                        {
                            arrObj[i]=msg[0][i];
                            arrObj[i]['dirUrl..']=dirUrl+'/'+i;
                            dirHtml+='<ol dirUrl="'+dirUrl+'/'+i+'" folderNum="'+msg[0][i]['folderNum..']+'" fileNum="'+msg[0][i]['fileNum..']+'" ><a '+(msg[0][i]['folderNum..']?'class="notEmptyDir"':'')+' href="javascript:void(0)" title="'+phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(i))+'\n(有'+msg[0][i]['folderNum..']+'个文件夹和'+msg[0][i]['fileNum..']+'个文件)" >'+phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(i))+'&nbsp;('+msg[0][i]['folderNum..']+'-'+msg[0][i]['fileNum..']+')'+'</a></ol>';
                        }
                        if(dirHtml!=='')
                        {
                            var ulDisplay='none';
                            oldNextUl=obj.next('ul');    //当前文件夹的子文件夹的UL节点
                            if(oldNextUl.length)
                            {
                                oldNextUl.css('display')!='none'?ulDisplay='block':null;
                                oldNextUl.remove();
                                oldNextUl.removeType=true;
                            }
                            newAddUlObj=$('<ul style="display:'+ulDisplay+';" >'+dirHtml+'</ul>').insertAfter(obj);
                        }
                        folderSlideToggle();
                        if(typeof(obj.get(0).callbackFun)=='function')
                        {
                            obj.get(0).callbackFun();
                            obj.removeAttr('callbackFun');
                        }
                        else
                        {
                            if(dirHtml!==''&&!oldNextUl.removeType)
                            {
                                newAddUlObj.slideToggle('fast');
                            }
                            fileListSwitch(dirUrl);
                        }
                        loading();
                    }
        }
    );
}

/**
* 描  述:刷新指定文件夹
* 参数名:
*     obj    :指定文件夹的对象或路径
*     getObj:如果为true不更新文件夹,而是返回文件夹对象
*     encode:如果为true则不再编码,否则编码传入的obj
* 作  者:Edgar.Lee
**/
function refreshFolder(obj,getObj,encode)
{
    if(typeof(obj)=='string')
    {
        if(obj=='')
        {
            obj='/';
        }
        if(!encode)
        {
            obj=phpUrlCode.rawurlencode(phpUrlCode.rawurlencode(obj))
        }
        obj=$('.menu div span ol[dirUrl="'+obj+'"]');
    }
    if(getObj)
    {
        return obj;
    }
    requestDir({obj:obj});
}

/**
* 描  述:获取到dirArr中指定的对象
* 参数名:
*     dir    :请求的路径
* 示  例:
*     requestDir('mm/a');
*     返回dirArr['mm']['a']
* 作  者:Edgar.Lee
**/
function indexDirArr(dir)
{
    var tempObj=dirArr;
    dir==null?dir='':null;
    dir=dir.replace(/(^\/*)|(\/*$)/g, "");
    if(dir!=='')
    {
        var tempArr=dir.split('/');
        for(var i in tempArr)
        {
            tempObj=tempObj[tempArr[i]];
        }
        try
        {
            tempObj['folderName..']=tempArr[i];
        } catch (e)
        {
            //当文件夹刷新时当前对象将被清空
        }
    }
    return tempObj;
}

/**
* 描  述:获取swf播放器的html
* 参数名:
*     url    :swf的路径
*     vars:给swf的参数
* 示  例:
*     getPlayerHtml('aa.swf','a=5&b=6');
*     向aa.swf传递a=5和b=6两个参数并返回播放aa.swf的html
* 作  者:Edgar.Lee
**/
function getPlayerHtml(url, vars) {
    var html = '';
    html += '<object id="objectID" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" ';
    html += 'width="100%" ';
    html += 'height="100%" ';
    html += '>';
    html += '<param name="movie" value="' + url + '" />';
    html += '<param name="allowFullScreen" value="true" />';
    html += '<param name="allowScriptAccess" value="always" />';
    html += '<param name="quality" value="high" />';
    html += '<param name="wmode" value="opaque" />';
    html += '<param name="flashvars" value="' + vars + '" />';
    html += '<embed type="application/x-shockwave-flash" ';
    html += 'name="objectID" ';
    html += 'width="100%" ';
    html += 'height="100%" ';
    html += 'src="' + url + '" ';
    html += 'allowfullscreen="true" ';
    html += 'allowscriptaccess="always" ';
    html += 'quality="high" ';
    html += 'wmode="opaque" ';
    html += 'flashvars="' + vars + '"';
    html += '></embed>';
    html += '</object>';
    return html;
}

/**
* 描  述:js模拟php编码和解码
* 函数名:
        rawurlencode:php的rawurlencode函数(编码)
        rawurldecode:php的rawurldecode函数(解码)
* 参数名:
*     string    :传递要编码或解码的字符串
*    isUrl    :是否要返回URL形式的
* 示  例:
*     phpUrlCode.rawurlencode('我');
*     返回php中rawurlencode函数对'我'编码后的结果
* 作  者:Edgar.Lee
**/
var phpUrlCode = {
    //php的urlencode函数(编码)
    rawurlencode: function(string,isUrl) {
        string=escape(this._utf8_encode(string));
        if(isUrl)
        {
            string=this._encode_url(string);
        }
        return string;
    },
    //php的urldecode函数(解码)
    rawurldecode: function(string) {
        string = string.split('/');
        for(var i = 0, iL = string.length; i < iL; ++i)
        {
            if(/^[\w%-\.]+$/.test(string[i]))
            {
                string[i] = this._utf8_decode(unescape(string[i]))
            }
        }
        return string.join('/');
    },
    _utf8_encode: function(string) {
        string = string.replace(/\r\n/g, "\n");
        var utftext = "";
        for (var n = 0; n < string.length; n++) {
            var c = string.charCodeAt(n);
            if (c < 128) {
                utftext += String.fromCharCode(c)
            } else if ((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            } else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext
    },
    _utf8_decode: function(utftext) {
        var string = "";
        var i = 0;
        var c = c1 = c2 = 0;
        while (i < utftext.length) {
            c = utftext.charCodeAt(i);
            if (c < 128) {
                string += String.fromCharCode(c);
                i++;
            } else if ((c > 191) && (c < 224)) {
                c2 = utftext.charCodeAt(i + 1);
                string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                i += 2;
            } else {
                c2 = utftext.charCodeAt(i + 1);
                c3 = utftext.charCodeAt(i + 2);
                string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                i += 3;
            }
        }
        return string
    },
    //\/:*?"<>|
    _encode_url: function(string) {
        string = string.replace(/%5C/g, "\\").replace(/%2F/g, "/").replace(/%3A/g, ":").replace(/%3F/g, "?");
        return string;
    }
}

/**
* 描  述:节点名字操作,针对文件(夹)新建改名操作
* 函数名:
        rawurlencode:php的rawurlencode函数(编码)
        rawurldecode:php的rawurldecode函数(解码)
* 参数名:
*     string    :传递要编码或解码的字符串
*    isUrl    :是否要返回URL形式的
* 示  例:
*     phpUrlCode.rawurlencode('我');
*     返回php中rawurlencode函数对'我'编码后的结果
* 作  者:Edgar.Lee
**/
function nodeNameOperation(title,mouseClickFun,value,isExtension)
{
    value=value?value:'';
    var replaceName='';    //去除两边'.'的文件名
    var oDialogDivHandle=windowObj.oDialogDiv(title,'<input value="'+value+'" style=" width:100px; margin-right:2px;" /><font color="red">不能包含\\/:*?"<>|</font>',240,'auto',[2,
    {
        mouseDownFun:function(callBack,parentWindowObj,callBackObj)
        {
            if(callBack)
            {
                if(!/^[^\\\/:*?"<>|]+$/.test(replaceName=callBackObj.oDialogDivObj.find('input').val().replace(/^\.+|\.+$/g,'')))
                {
                    windowObj.oDialogDiv('','内容为空或包含非法字符',null,null,[1,function(){callBackObj.oDialogDivObj.find('input').focus();}]);
                }
                else if(isExtension)
                {
                    var reg=new RegExp('('+fileExt.replace(/;/g,'|').replace(/\./g,'\\.').replace(/\*/g,'.+')+')$');
                    if(!reg.test(replaceName))
                    {
                        windowObj.oDialogDiv('文件扩展名非法','仅可以是:<br/>'+fileExt.replace(/\*/g,''),710,null,[1,function(){callBackObj.oDialogDivObj.find('input').focus();}]);
                    }
                }
                callBackObj.oDialogDivObj.find('input').val(replaceName);
            }
        },
        mouseClickFun:mouseClickFun
    }]);
    windowObj.document.getElementById('oDialogDiv_'+oDialogDivHandle).getElementsByTagName('input')[0].focus();
}

/**
* 描  述:生成无限扩展的菜单
* 函数名:
        rawurlencode:php的rawurlencode函数(编码)
        rawurldecode:php的rawurldecode函数(解码)
* 参数名:
*     obj    :将生成的代码插入的该对象下
*    menuItemHtml:需要生成菜单的html(<ol zz>yy</ol><ol kk>xx</ol>形式)
*     mouseEnterFun:鼠标滑入菜单项回调函数
*     mouseClickFun:鼠标点击菜单项回调函数
* 作  者:Edgar.Lee
**/
function floatMenu(obj,menuItemHtml,mouseEnterFun,mouseClickFun)
{
    if(typeof(obj)=='object'&&typeof(menuItemHtml)=='string'&&menuItemHtml!='')
    {
        obj=$(obj);
        //计算并添加子项
        var parentObjArr=obj.parentsUntil('th');
        var singleMenuNum=Math.floor((parentObjArr.last().attr('offsetLeft')+parentObjArr.width())/parentObjArr.width());    //单行菜单数量
        var parentObjArrLen=parentObjArr.length;                    //计算中使用的总共菜单长度,会因为菜单转折而改变值
        var menuItemStyle='';
        
        if(parentObjArrLen>singleMenuNum)
        {
            --parentObjArrLen;
            --singleMenuNum;
        }
        if(parentObjArrLen>1)
        {
            if((parentObjArr.css('right')=='0px')&&parentObjArrLen%singleMenuNum==0)
            {
                menuItemStyle=' style="margin-right:0px; right:-'+parentObjArr.outerWidth()+'px;" ';
            }
            else if(parentObjArr.css('right')!='0px'&&parentObjArrLen%singleMenuNum!=0)
            {
                menuItemStyle=' style="margin-right:0px; right:-'+parentObjArr.outerWidth()+'px;" ';
            }
        }
        $('<ul'+menuItemStyle+'>'+menuItemHtml+'</ul>').insertAfter(obj.siblings('ul').remove().end());
        
        //添加回调事件
        obj.next('ul').children('ol').mouseenter(
            function()
            {
                $(this).siblings('ul').remove();
                if(typeof(mouseEnterFun)=='function')
                {
                    mouseEnterFun($(this));
                }
            }
        ).click(// margin-right:120px;
            function(e)
            {
                if(typeof(mouseClickFun)=='function')
                {
                    mouseClickFun($(this),e);
                }
            }
        );
    }
}

/**
* 描  述:根据文件夹结构生成指定菜单的HTML
* 函数名:
*           dirObjArr:需要遍历的问价数组
*           excludedUrl:排除的路径,排除指定文件夹及子文件夹
* 作  者:Edgar.Lee
**/
floatMenu.getMenuItemsHtml=function(dirObjArr,excludedUrl)
{
    var menuItemsHtml='';
    for(var i in dirObjArr)
    {
        if(i.substr(i.length-2)!='..')
        {
            if(excludedUrl)
            {
                if(dirObjArr[i]['dirUrl..'] === excludedUrl||dirObjArr[i]['dirUrl..'].substr(0,excludedUrl.length+1) === excludedUrl+'/')
                {
                    continue;
                }
            }
            menuItemsHtml+='<ol dirUrl="'+dirObjArr[i]['dirUrl..']+'" ><a href="javascript:void(0)" >'+phpUrlCode.rawurldecode(phpUrlCode.rawurldecode(i))+'</a></ol>';
        }
    }
    return menuItemsHtml;
}
floatMenu.documentClose=function()
{
    $('#operation ul').remove();
}
//------------------------------------------------------------------------初始化文件列表(结束)------------------------------------------------------------------------//

/**
* 描  述:显示或关闭操作提示
* 函数名:loading
* 参数名:
*     text    :传入显示的内容,当传参为true时显示默认加载,当不传值或传入boolean值为false时关闭提示窗口
*     timeout    :超时时间,当超时后自动关闭显示,默认永不超时
* 示  例:
*     loading('请等待...');
*     显示'请等待...'
*     loading(true);
*     显示上次显示的
*     loading();
*     关闭显示
* 作  者:Edgar.Lee
**/
function loading(text,timeout)
{
    if(!loading.lock)
    {
        text=text===true?$('#loading').html():text;
        $('#loading').stop(true,true);
        text?$('#loading').show('fast').html(text):$('#loading').hide('fast');
        clearTimeout(loading.timeoutObj);
        timeout&&text?loading.timeoutObj=setTimeout(function(){loading.lock=false;loading();},timeout):null;                //有效期后自动关闭
    }
}