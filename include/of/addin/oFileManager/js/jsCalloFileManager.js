/**
* 描  述:JS调用oFileManager管理系统
* 参数名:
*     selectFileCallBackFun:当用户选择指定文件时,回调函数,该函数收参数(oFileManagerSystemParameters:是系统返回的的一些信息,callBackParameters:用户自定义传递的参数)
*                             oFileManagerSystemParameters:{
*                                                             url:[用户选定文件的url],
*                                                             handle:[管理器的句柄,如果是浮动方式,则句柄是浮动层的唯一序列值,如果是showModalDialog形式,则为showModalDialog页面对象]
*                                                           }
*     callBackParameters:回调参数,作为第二个参数传的给selectFileCallBackFun : {
*                                                               closeCallBackFun : 关闭窗口时的回调函数,接受callBackParameters参数
*                                                           }
*     selectClose:选择后自动关闭文件浏览器页面,默认true
*     systemParameters:系统参数,定义权限路径等等操作
*                     {
*                         permissions:[用户权限分1(只有浏览功能),2(还包括上传文件和建立文件夹功能),3(还包括重命名,删除,移动文件[夹]),权限设置只能低于当前用户设置的最高权限],
*                         browseDir:[指定当前管理器的根目录(/../..形式)该目录设置只能在当前用户最大浏览目录的子目录],
*                         fileExt:[允许上传的扩展名(rtf;txt;csv;doc;docx形式),扩展名只能是系统规定扩展的子集],
*                         selectExt:[允许选择的扩展名(rtf;txt;csv;doc;docx形式)
*                     }
*     width:页面的宽,单位像素,默认800
*     height:页面的高,单位像素,默认600
*     oFileManagerMainDir:oFileManager管理主界面的路径,全局有效,相对虚拟网络路径的绝对浏览路径
* 示  例:
*     oFileManager(
*         function(oFileManagerSystemParameters,callBackParameters)
*         {
*             alert(oFileManagerSystemParameters.url);        //弹出用户选择的url
*             alert(callBackParameters);    用户传入的callBackParameters参数
*         },
*         {permissions:1},
*         false,
*         800,
*         600,
*         '.'
*     )
*     生成一个宽800px,高600px,只有文件浏览和选择功能的管理界面
* 作  者:Edgar.Lee
**/
function oFileManager(selectFileCallBackFun,callBackParameters,selectClose,systemParameters,width,height,oFileManagerMainDir)
{
    var createWindowObj=window;        //当前父类
    var parentsUntilArr=[window];    //从当前窗口向上层的所有窗口
    var handle=null;                        //产生浮动层的句柄
    var oFileManagerGetParameters='?';        //调用文件管理器传递的get参数
    var oFileManagerSelectFileCallBackFun=null;    //发送给oFileManager的回调函数,这个函数执行后再调用selectFileCallBackFun函数
    var iframeDOMContentLoadedFun=null;                //iframe网页结构加载成功时回调
    var oDialogDivIframeObj=null;                        //浮动层时当前的iframe对象
    
    /*初始化*/
    
    //给参数添加初始化值
    typeof(selectFileCallBackFun)!='function'?selectFileCallBackFun=function(){}:null;
    typeof(systemParameters)!='object'?systemParameters={}:null;
    width==null?width='800':null;
    height==null?height='600':null;
    selectClose==null?selectClose=true:null;
    oFileManagerMainDir!=null?oFileManager.oFileManagerMainDir=oFileManagerMainDir:null;
    
    //初始化管理器的get参数
    for(var i in systemParameters)
    {
        oFileManagerGetParameters+=i+'='+systemParameters[i]+'&';
    }
    oFileManagerGetParameters=oFileManagerGetParameters.substr(0,oFileManagerGetParameters.length-1);
    
    //iframe网页结构加载成功时回调
    iframeDOMContentLoadedFun=function(e)
    {
        if(typeof(oDialogDivIframeObj.contentWindow.oDialogDiv)=='function')
        {
            oDialogDivIframeObj.contentWindow.selectFileCallBackFun=oFileManagerSelectFileCallBackFun;
        }
    }
    
    //初始化oFileManagerSelectFileCallBackFun回调方法,参数(url[用户选择的文件路径],oFileManagerWindow[oFileManager管理器的window对象])
    oFileManagerSelectFileCallBackFun=function(url,oFileManagerWindow)
    {
        handle==null?handle=oFileManagerWindow:null;
        selectFileCallBackFun(            //回调用户函数
            {'url':url,'handle':handle},
            callBackParameters
        )
        if(selectClose)    //调用回调关闭
        {
            oFileManager.close()
        }
    }
    
    //关闭当前的oFileManager
    oFileManager.close=function()
    {
        oFileManager.close = function(){};    //置空关闭函数
        if(handle != null)
        {
            if(typeof handle === 'object')
            {
                handle.close();
            } else {
                createWindowObj.oDialogDiv.dialogClose(handle);
            }
        }
        if(callBackParameters && typeof callBackParameters.closeCallBackFun === 'function')    //关闭回调函数
        {
            callBackParameters.closeCallBackFun(callBackParameters);
        }
    }
    
    //得到当前oFileManager的window对象
    oFileManager.getWindowObj=function()
    {
        if(typeof(handle)=='object')
        {
            return handle;
        }
        else
        {
            return oDialogDivIframeObj.contentWindow;
        }
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
    
    if(typeof(createWindowObj.oDialogDiv)=='function')
    {
        handle=createWindowObj.oDialogDiv('oFileManager文件管理系统','iframe:'+(oFileManager.oFileManagerMainDir.replace(/\/+$/g,''))+'/index.php'+oFileManagerGetParameters,width,height, [1, function(callBack){
            callBack && oFileManager.close();
        }]);
        oDialogDivIframeObj=createWindowObj.document.getElementById('oDialogDiv_iframe_'+handle);
        if(oDialogDivIframeObj.contentWindow.addEventListener)
        {
            oDialogDivIframeObj.contentWindow.addEventListener("DOMContentLoaded",iframeDOMContentLoadedFun,false);
        }
        else
        {
            oDialogDivIframeObj.contentWindow.document.attachEvent('onreadystatechange',iframeDOMContentLoadedFun);
        }
    }
    else
    {
        window.showModalDialog(oFileManager.oFileManagerMainDir.replace(/\/+$/g,'')+'/index.php'+oFileManagerGetParameters,oFileManagerSelectFileCallBackFun,"dialogWidth="+width+"px;dialogHeight="+height+"px;status=no");
        oFileManager.close();    //阻塞结束关闭窗口回调
    }
}

//关闭当前的oFileManager
oFileManager.close=function(){}

//得到当前oFileManager的window对象
oFileManager.getWindowObj=function(){};

//oFileManager管理系统主页面所在路径
oFileManager.oFileManagerMainDir='.';