function oDialogDiv(title, content, width, height, type, topNum, effect) {
    var temp;                                                                                                           //任意临时值信息
    var callBackObj = {};                                                                                               //做为第三个参数传给回调函数
    var mouseDownFun = function(){};                                                                                    //鼠标按下按钮的回调方法
    var mouseClickFun = function(){};                                                                                   //点击按钮后的回调方法
    var layoutFun;                                                                                                      //当布局完成回调方法
    var dateTime = String((new Date).getTime());                                                                        //生成唯一浮动层句柄
    var temp_float = new String;                                                                                        //存储浮动层的html
    var eventTarget = null;                                                                                             //事件源
    var buttonList = [];                                                                                                //确定取消按钮文字列表
    var ancestorWindow = oDialogDiv.getAncestorWindow();                                                                //最父层window对象
    var config = {                                                                                                      //辅助配置
        'title'     : title || '',                                                                                      //标题
        'content'   : content || '',                                                                                    //内容
        'width'     : width,                                                                                            //宽度
        'height'    : height,                                                                                           //高度
        'type'      : type || [],                                                                                       //交互
        'topNum'    : topNum,                                                                                           //顶部
        'effect'    : effect,                                                                                           //特效
        'escClose'  : null,                                                                                             //esc关闭窗口(null=自动判断)
        'skinStyle' : 'oDialogDiv'                                                                                      //样式
    };

    //初始化
    if($.type(temp = title) === 'object' || $.type(temp = topNum) === 'object')
    {
        $.extend(config, temp);
    }

    //初始化按钮
    if($.isArray(config.type[0]))                                                                                       //数组模式
    {
        for(var i = 0, iL = config.type[0].length; i < iL; ++i)
        {
            if(typeof config.type[0][i] === 'object' || config.type[0][i] == '')
            {
                config.type[0][i].callBack && (config.type[0][i].callBack = "'" +config.type[0][i].callBack+ "'");
                buttonList.push(config.type[0][i]);
            } else {
                buttonList.push({'value' : config.type[0][i]});
            }
        }
    } else {                                                                                                            //数字模式
        if(config.type[0] == null || config.type[0] == 1)
        {
            buttonList.push('');
        } else {
            if(config.type[0] >= 1)
            {
                buttonList.push({'value' : '确定', 'callBack' : 'true'});
            }
            if(config.type[0] == 2)
            {
                buttonList.push({'value' : '取消', 'callBack' : 'false'});
                config.escClose == null && (config.escClose = false);
            }
        }
    }
    //初始化话回调
    if (typeof(config.type[1]) === 'object') {
        config.type[1]['mouseDownFun'] != null ? mouseDownFun = config.type[1]['mouseDownFun'] : null;
        config.type[1]['mouseClickFun'] != null ? mouseClickFun = config.type[1]['mouseClickFun'] : null;
        config.type[1]['layoutFun'] != null ? layoutFun = config.type[1]['layoutFun'] : null;
    } else if (config.type[1] != null) {
        mouseClickFun = config.type[1];
    }

    if(window.event)    //获取支持全局浏览器事件源DOM
    {
        eventTarget = window.event.srcElement
    } else {    //获取支持句柄浏览器事件源DOM
        try{
            var caller = arguments.callee.caller;
            do{
                temp = caller.arguments[0];
                if(typeof temp === 'object' && typeof temp.target === 'object' && temp.target.nodeType != null)
                {
                    eventTarget = temp.target;
                    break;
                }
            } while(caller = caller.caller)
        } catch(e) {
            //调用者不是一个方法,或者方法索引不到
        }
    }

    //将回调记录到祖先列表中
    ancestorWindow.oDialogDiv.callBackList[dateTime] = callBackObj = {
        'handle'          : dateTime,                                                                                   //句柄地址
        'oDialogDivObj'   : null,                                                                                       //浮动层对象
        'oDialogDivBgObj' : null,                                                                                       //浮动层背景对象
        'customize'       : config.type[2],                                                                             //自定义回调
        'config'          : config,                                                                                     //配置文件
        'ancestorWindow'  : ancestorWindow                                                                              //最父层window对象
    };

    //生成浮动层
    if((temp = $.inArray('', buttonList)) > -1)    //如果只有一个,则按钮显示右上角的关闭按钮
    {
        config.escClose == null && (config.escClose = true);
        buttonList.splice(temp, 1);
        temp = '';
    } else {
        temp = ' notExist style="display:none;"';
    }
    temp_float = '<div id="oDialogDivBg_' +dateTime+ '" class="' +config.skinStyle+ 'Bg" ></div>'
               + '<div id="oDialogDiv_' +dateTime+ '" effect="'+(config.effect ? 'true' : 'false')+'" class="' +config.skinStyle+ '">'
               +    '<div class="borderBgN" ie6Png ></div>'
               +    '<div class="borderBgS" ie6Png ></div>'
               +    '<div class="borderBgW" ie6Png ></div>'
               +    '<div class="borderBgE" ie6Png ></div>'
               +    '<div class="borderBgEN" ie6Png ></div>'
               +    '<div class="borderBgES" ie6Png ></div>'
               +    '<div class="borderBgWS" ie6Png ></div>'
               +    '<div class="borderBgWN" ie6Png ></div>'
               +    '<div class="title">'
               +        '<h4></h4>'    //此处为弹出层标题
               +        '<a callBack="true" onclick="return false;" href="#"' +temp+ '></a>'    //关闭按钮
               +        '<div style="clear:both;"></div><div class="maskLayer" ></div>'    //遮罩层
               +    '</div>'
               +    '<div class="scroll">'    //内容显示区
               +        '<span class="content"></span>'    //内容显示区
               +    '</div>';
    if(buttonList.length)    //如果有两个按钮,则显示在内容显示区中(确认,取消)
    {
        temp_float +=   '<div class="operating">';
        for(var i = 0, iL = buttonList.length; i < iL; ++i)
        {
            temp_float += '<a ' +(buttonList[i].attrStr ? buttonList[i].attrStr : '')+ ' callBack="' +(buttonList[i].callBack || i)+ '" onclick="return false;" href="#">' +buttonList[i].value+ '</a>'
        }
        temp_float +=   '</div><div style="height:1px; width:1px; overflow:hidden; margin-top:-1px;"></div>';    //IE 8,9 当指定高度时不显示 margin-bottom
    }
    temp_float +=   '<iframe frameborder="0" style="position:absolute; filter:alpha(opacity=0); display:none; z-index:-1; top:0px; left:0px;"></iframe>'    //IE6 下用来解决不能遮罩SELECT的问题
               + '</div>';
    $(ancestorWindow.document.body).append(temp_float);

    callBackObj.oDialogDivObj = $("#oDialogDiv_"+dateTime, ancestorWindow.document);           //浮动层对象
    callBackObj.oDialogDivBgObj = $("#oDialogDivBg_"+dateTime, ancestorWindow.document);           //浮动层背景对象

    //初始化浮动层状态
    callBackObj.oDialogDivObj.css({'opacity' : 0, 'zIndex' : ancestorWindow.oDialogDiv.zIndex.zIndex})
                             .css($(eventTarget).offset() || {'top' : 0, 'left' : 0});
    callBackObj.oDialogDivBgObj.css('zIndex', ancestorWindow.oDialogDiv.zIndex.zIndex);

    //添加oDialogDiv相关事件
    $("> .title a", callBackObj.oDialogDivObj).add("> .operating a", callBackObj.oDialogDivObj).each(function(){
        temp = $(this);
        if(temp.attr('notExist') === undefined)
        {
            temp.click(function(){
                var callBack=eval($(this).attr('callBack'));
                if( (typeof mouseClickFun === 'function' ? mouseClickFun(callBack,window,callBackObj) : eval(mouseClickFun)) !== false )
                {
                    ancestorWindow.oDialogDiv.dialogClose(dateTime);
                }
            }).mousedown(function(){
                    var callBack=eval($(this).attr('callBack'));
                    typeof(mouseDownFun)=='function'?mouseDownFun(callBack,window,callBackObj):eval(mouseDownFun);
            }).mouseover(function(){
                    callBackObj.preVisit=eval($(this).attr('callBack'));
            }).mouseout(function(){
                    callBackObj.preVisit=null;
            });
        } else {    //在某种状态下(ajax,iframe),存在又上角叉不应该显示的notExist状态时,不回调,直接关闭
            temp.click(function(){
                ancestorWindow.oDialogDiv.dialogClose(dateTime);
            });
        }
    });

    //添加oDialogDivBg点击抖动当前oDialogDiv事件
    callBackObj.oDialogDivBgObj.click(function(){
        var offsetStyle = callBackObj.oDialogDivObj.offset();
        var movePx = 5;    //移动像素
        var forLen = 5;    //抖动次数

        //调整Y滚动条
        var windowScrollTop = $(ancestorWindow.document).scrollTop();    //当前scrollTop值
        var windowHeight = $(ancestorWindow).height();    //当前窗口高
        var oDialogDivObjScrollTop = callBackObj.oDialogDivObj.prop('offsetTop');    //当前浮动层scrollTop值
        var oDialogDivObjHeight = callBackObj.oDialogDivObj.height();    //当前浮动层的高
        if(windowScrollTop + windowHeight < oDialogDivObjScrollTop + 50)
        {
            $(ancestorWindow.document).scrollTop(oDialogDivObjScrollTop + (windowHeight - oDialogDivObjHeight) / 2);
        } else if(windowScrollTop > oDialogDivObjScrollTop + oDialogDivObjHeight - 50) {
            $(ancestorWindow.document).scrollTop(oDialogDivObjScrollTop - (windowHeight - oDialogDivObjHeight) / 2)
        }

        //调整X滚动条
        var windowScrollLeft = $(ancestorWindow.document).scrollLeft();    //当前scrollLeft值
        var windowWidth = $(ancestorWindow).width();    //当前窗口宽
        var oDialogDivObjScrollLeft = callBackObj.oDialogDivObj.prop('offsetLeft');    //当前浮动层scrollLeft值
        var oDialogDivObjWidth = callBackObj.oDialogDivObj.width();    //当前浮动层的宽
        if(windowScrollLeft + windowWidth < oDialogDivObjScrollLeft + 50)
        {
            $(ancestorWindow.document).scrollLeft(oDialogDivObjScrollLeft + (windowWidth - oDialogDivObjWidth) / 2);
        } else if(windowScrollLeft > oDialogDivObjScrollLeft + oDialogDivObjWidth - 50) {
            $(ancestorWindow.document).scrollLeft(oDialogDivObjScrollLeft - (windowWidth - oDialogDivObjWidth) / 2)
        }

        //抖动窗口
        (function(){
            if(forLen < 1)
            {
                callBackObj.oDialogDivObj.css(offsetStyle);
                return;
            }
            movePx = -movePx;
            forLen -= 1;
            callBackObj.oDialogDivObj.animate({'left' : offsetStyle.left + movePx, 'top' : offsetStyle.top - movePx}, 20, arguments.callee);
        })();
    });

    var contentBack = content = config.content;
    $("> .title h4", callBackObj.oDialogDivObj)[config.title ? 'html' : 'hide'](config.title);
    contentType = content.substring(0, content.indexOf(":"));
    content = content.substring(content.indexOf(":") + 1, content.length);
    if(content === '')
    {
        $("> .scroll > .content", callBackObj.oDialogDivObj).css({'overflow' : 'hidden', 'height' : 1, 'width' : 194 });
    } else {
        switch (contentType) {
            case "url":
                var content_array = content.split("?", 3);
                if(content_array[0].toLowerCase() === 'post')    //解析post模式下的get参数
                {
                    content_array[2] = content.substr(content_array[1].length + 6);
                    if(/^{.*}$/.test(content_array[2]))
                    {
                        try{
                            var paramsObj = (new Function('return ' + content_array[2]))();
                        } catch(e){
                            throw new Error('无效的Json对象:' + contentBack);
                        };
                        if(typeof paramsObj.get !== 'undefined')    //获取get参数
                        {
                            content_array[1] += '?' + $.param(paramsObj.get);
                        }
                        if(typeof paramsObj.post !== 'undefined')
                        {
                            content_array[2] = $.param(paramsObj.post);
                        } else {
                            delete content_array[2];
                        }
                    }
                }
                $.ajax({
                    type  : content_array[0],
                    url   : content_array[1],
                    data  : content_array[2],
                    beforeSend : function() {
                        $("> .title > a:eq(0)", callBackObj.oDialogDivObj).show();
                        $("> .operating", callBackObj.oDialogDivObj).css('visibility', 'hidden');
                        $("> .scroll > .content", callBackObj.oDialogDivObj).html("loading...");
                    },
                    error : function() {
                        $("> .scroll > .content", callBackObj.oDialogDivObj).html("error...");
                        layoutFun && layoutFun(false, window, callBackObj);    //加载错误调用回调
                    },
                    success: function(html) {
                        temp = $("> .title > a:eq(0)", callBackObj.oDialogDivObj);
                        if(temp.attr('notExist') === '')
                        {
                            temp.hide();
                        }
                        $("> .operating", callBackObj.oDialogDivObj).css('visibility', 'visible');
                        $("> .scroll > .content", callBackObj.oDialogDivObj).html(html);
                        oDialogDiv.skinLayout(callBackObj.oDialogDivObj, config.width, config.height, config.topNum, config.effect, layoutFun);
                    }
                });
                break;
            case "id":
                $("> .scroll > .content", callBackObj.oDialogDivObj).html($("#" + content + "").html());
                break;
            case "iframe":
                var postIframeForm = '';    //post方式请求iframe所需的form表单
                if(content.indexOf('?') > -1)    //如果有参数传递,则进一步判断是不是符合json,否则啥也不干
                {
                    var content_array = [content.substr(0, content.indexOf('?')), content.substr(content.indexOf('?') + 1)];
                    if(/^{.*}$/.test(content_array[1]))    //如果符合json,则进一步判断是不是包含get信息,否则啥也不干
                    {
                        try{
                            var paramsObj = (new Function('return ' + content_array[1]))();
                        } catch(e){
                            throw new Error('无效的Json对象:' + contentBack);
                        };
                        if(typeof paramsObj.get !== 'undefined')    //获取get参数
                        {
                            content_array[0] += '?' + $.param(paramsObj.get);
                            content = content_array[0];    //更新get路径
                        }
                        if(typeof paramsObj.post !== 'undefined')
                        {
                            var paramList = $.param(paramsObj.post).split('&');
                            var paramListVlue;    //paramList解析后的[key, value]对应数组
                            content = 'about:blank'    //清空首次加载的路径
                            postIframeForm = '<FORM METHOD=POST STYLE="display:none;" ACTION="' +content_array[0]+ '" TARGET="oDialogDiv_iframe_' +dateTime+ '">';
                            for(var i = 0, iL = paramList.length; i < iL; ++i)    //生成post提交的隐藏表单
                            {
                                paramListVlue = paramList[i].split('=');
                                postIframeForm += '<INPUT TYPE="hidden" NAME="' +decodeURIComponent(paramListVlue[0]).replace(/&/g, '&amp;').replace(/\"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')+ '" value="' +decodeURIComponent(paramListVlue[1]).replace(/&/g, '&amp;').replace(/\"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')+ '">';
                            }
                            postIframeForm += '</FORM>';
                        }
                    }
                }
                $("> .scroll > .content", callBackObj.oDialogDivObj).html("<div>loading...</div><iframe id='oDialogDiv_iframe_"+dateTime+"' name='oDialogDiv_iframe_"+dateTime+"' src='" + content + "' scrolling='no' frameborder='0' marginheight='0' marginwidth='0' style='visibility:hidden;'></iframe>");    //样式中margin-top:-2px;的原因是在IE6下iframe高度会加2,IE7下iframe高度会加1

                //改变按钮状态操作按钮
                $("> .title > a:eq(0)", callBackObj.oDialogDivObj).show();    //显示关闭按钮
                $("> .operating", callBackObj.oDialogDivObj).css('visibility', 'hidden');    //隐藏其它按钮

                $('#oDialogDiv_iframe_' + dateTime, callBackObj.oDialogDivObj).bind('load', function(){
                    var thisObj = $(this).unbind('load', arguments.callee);
                    temp = $("> .title > a:eq(0)", callBackObj.oDialogDivObj);
                    if(temp.attr('notExist') === '')
                    {
                        temp.hide();
                    }
                    $("> .operating", callBackObj.oDialogDivObj).css('visibility', 'visible');
                    try{    //本站加载,计算高宽
                        temp = callBackObj.oDialogDivObj.find('> .scroll > .content')
                            .children('div').remove().end()
                            .children('iframe').css('visibility', 'visible')
                            .get(0).contentWindow.document;

                        $(temp).keyup(oDialogDiv.escClose);                                                             //esc关闭弹出层
                        oDialogDiv.skinLayout(callBackObj.oDialogDivObj, config.width, config.height, config.topNum, config.effect, layoutFun);
                    } catch(e) {}    //跨站加载
                });
                if(postIframeForm !== '')    //需要post方式请求iframe
                {
                    $(postIframeForm).insertAfter("#oDialogDiv_iframe_" +dateTime, callBackObj.oDialogDivObj)
                                     .submit()
                                     .remove();
                } else if($.browser.msie === $.browser.mozilla && content === 'about:blank') {
                    $('#oDialogDiv_iframe_' + dateTime, callBackObj.oDialogDivObj).trigger('load');
                }
                break;
            case "text":
                $("> .scroll > .content", callBackObj.oDialogDivObj).html(content);
                break;
            default:
                $("> .scroll > .content", callBackObj.oDialogDivObj).html(contentBack);
        }
    }

    //将浮动层设置到指定位置
    oDialogDiv.skinLayout(
        callBackObj.oDialogDivObj, 
        config.width, 
        config.height, 
        config.topNum, 
        config.effect, 
        contentType !== 'url' && contentType !== 'iframe' && layoutFun
    );
    //添加浮动层拖拽事件
    if(oDialogDiv.mouseDragObj!=null)
    {
        var tempObj=$("> .title", callBackObj.oDialogDivObj).get(0);
        temp = {"mouseDownFn" : oDialogDiv.mouseDownFn, "mouseMoveFn":oDialogDiv.floatDivMove, "mouseUpFn" : oDialogDiv.mouseUpFn};
        oDialogDiv.mouseDragObj.init(tempObj, temp, ancestorWindow);
        oDialogDiv.mouseDragObj.getObjListO(tempObj)[0].Customize={'dateTime' : dateTime};
        $('div[ie6png]', callBackObj.oDialogDivObj).each(function(){
            this.style.cursor = 'move';
            oDialogDiv.mouseDragObj.init(this, temp, ancestorWindow);
            oDialogDiv.mouseDragObj.getObjListO(this)[0].Customize={'dateTime' : dateTime};
        });
    }
    return dateTime;
};
//回调列表,所有弹出层将在祖先窗口中弹出
oDialogDiv.callBackList = {};

/**
* 描  述:获取祖先窗口对象
* 作  者:Edgar.Lee
**/
oDialogDiv.getAncestorWindow = function()
{
    if(!arguments.callee.ancestorWindow)
    {
        var ancestorWindow = window;    //最父层window对象

        while(ancestorWindow.parent !== ancestorWindow && ancestorWindow.parent != null)
        {
            ancestorWindow = ancestorWindow.parent;
        }
        arguments.callee.ancestorWindow = ancestorWindow;
    }
    return arguments.callee.ancestorWindow;
}
//将所有iframe统一成一个oDialogDiv
if(typeof oDialogDiv.getAncestorWindow().oDialogDiv !== 'function')
{
    oDialogDiv.getAncestorWindow().oDialogDiv = window.oDialogDiv;
} else {
    window.oDialogDiv = oDialogDiv.getAncestorWindow().oDialogDiv;
}
//给oDialogDiv增加附加函数
if(typeof oDialogDiv.getTreeNode !== 'function'){
    /**
    * 描  述:获取指定弹出层回调对象
    * 参数名:
    *       nodeNum : [(int)]节点索引,正数或负数,null=返回所有层数组
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.getTreeNode = function(nodeNum)
    {
        var treeNodeObj = oDialogDiv.getAncestorWindow().oDialogDiv.callBackList;    //节点列表
        var treeNodeArr = [];    //节点数组
        for(var i in treeNodeObj)
        {
            treeNodeArr[treeNodeArr.length] = treeNodeObj[i];
        }
        if(treeNodeArr.length === 0)    //没有弹出层
        {
            return undefined;
        } else
        {
            if(nodeNum == null)
            {
                return treeNodeArr;
            } else if(nodeNum < 0) {
                nodeNum = treeNodeArr.length + nodeNum;
                if(nodeNum < 0)
                {
                    return undefined;
                }
            }
            return treeNodeArr[nodeNum];
        }
    }

    /**
    * 描  述:调整oDialogDiv的zIndex值,全局方法
    * 参数名:
    *       zIndex : 指定zIndex的值
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.zIndex = function(zIndex)
    {
        if(/^\d+$/.test(zIndex) && arguments.callee.zIndex != zIndex)
        {
            var callBackList = oDialogDiv.getAncestorWindow().oDialogDiv.callBackList;
            for(var i in callBackList)
            {
                callBackList[i].oDialogDivObj.css('zIndex', zIndex);
                callBackList[i].oDialogDivBgObj.css('zIndex', zIndex);
            }
            arguments.callee.zIndex = zIndex;
        }
    }
    oDialogDiv.zIndex.zIndex = 2147483584;    //默认oDialogDiv所在层

    /**
    * 描  述:浮动层布局统一调整函数
    * 参数名:
    *       layoutObj : [(int弹出树的节点值|string传入的handle句柄|object弹出层对象)]布局对象,int=弹出层节点数
    *       width     : 重新布局宽度,数值,百分比或'auto',对象{'minWidth' : 最小宽度, 'maxWidht' : 最大宽度}
    *       height    : 重新布局高度,数值,百分比或'auto',对象{'minHeight' : 最小高度, 'maxHeight' : 最大高度}
    *       topNum    : 距离顶部高度,数值
    *       effect    : [(bool)]是否使用动画
    *       onLayout  : 当布局完成时回调
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.skinLayout = function(layoutObj, width, height, topNum, effect, layoutFun)
    {
        //参数初始化
        var ancestorWindow = oDialogDiv.getAncestorWindow();
        var ancestorWindowJqurey = $(ancestorWindow);    //jqurey的ancestorWindow对象
        var ancestorDialogDiv = ancestorWindow.oDialogDiv;
        var oDialogDivCssObj;    //弹出层样式
        var scrollCssObj;    //滚动区样式
        var animateWidth;    //计算的效果宽度(可能由于加载ajax或iframe而得到的信息不准,因此留着width做二次处理)
        var animateHeight;    //计算的效果宽度
        var scrollToLayoutMarginsTB;    //.scroll对象与layout之间上下外边,内边,边框合(标题,操作区)
        var oDialogDivScrollObj;    //oDialogDiv中的.scroll对象
        var oDialogDivIframeObj;    //iframe模式下.content的oDialogDiv_iframe_XXX
        var oDialogDivProperty = {};    //弹出层属性,记录最大最小宽高等设定
        var layoutStatus = true;    //回调函数所用布局状态

            //初始化layoutObj
        if(typeof layoutObj !== 'object' || layoutObj == null)
        {
            if(typeof layoutObj === 'string')    //如果是字符串形式,则认为传递的是handle
            {
                layoutObj = ancestorDialogDiv.callBackList[layoutObj];// ? ancestorDialogDiv.callBackList[layoutObj].callBackObj : null;
            } else {    //否则,则认为是null(当前弹出层)或树索引
                layoutObj = ancestorDialogDiv.getTreeNode(typeof layoutObj === 'number' ? layoutObj : -1);
            }
            if(layoutObj != null)    //如果layoutObj获取到了callBackObj
            {
                layoutObj = layoutObj.oDialogDivObj;
            } else {    //没有找到对应的层
                return false;
            }
        } else {    //转换jqurey对象
            layoutObj = $(layoutObj);
        }
            //初始化.scroll对象
        oDialogDivScrollObj = layoutObj.children('.scroll');
            //.scroll对象上下外边,内边,边框合
        scrollToLayoutMarginsTB = oDialogDivScrollObj.outerHeight(true) - oDialogDivScrollObj.height() + (layoutObj.children('.operating').outerHeight(true) || 0) + layoutObj.children('.title').outerHeight(true);
            //初始化高
        if(height == null)    //初始化话高
        {
            height = 'auto';
        } else if(typeof height === 'object') {    //传入的是对象
            oDialogDivProperty.minHeight = parseInt(height.minHeight || 0);
            oDialogDivProperty.maxHeight = parseInt(height.maxHeight || 0);
            if(/\d+%/.test(height.minHeight))    //百分比最小高度处理
            {
                oDialogDivProperty.minHeight = ancestorWindowJqurey.height() * oDialogDivProperty.minHeight / 100
            }
            if(/\d+%/.test(height.maxHeight))    //百分比最大高度处理
            {
                oDialogDivProperty.maxHeight = ancestorWindowJqurey.height() * oDialogDivProperty.maxHeight / 100
            }
            height = 'auto';
        } else if(height !== 'auto') {    //当字符处理
            if(/\d+%/.test(height)) {
                height = ancestorWindowJqurey.height() * parseInt(height) / 100;
            } else {
                height = parseInt(height);
            }
            height -= scrollToLayoutMarginsTB;
        }
            //初始化宽
        if(width == null)    //初始化话宽
        {
            width = 'auto';
        } else if(typeof width === 'object') {    //传入的是对象
            oDialogDivProperty.minWidth = parseInt(width.minWidth || 0);
            oDialogDivProperty.maxWidth = parseInt(width.maxWidth || 0);
            if(/\d+%/.test(width.minWidth))    //百分比最小宽度处理
            {
                oDialogDivProperty.minWidth = ancestorWindowJqurey.width() * oDialogDivProperty.minWidth / 100
            }
            if(/\d+%/.test(width.maxWidth))    //百分比最大宽度处理
            {
                oDialogDivProperty.maxWidth = ancestorWindowJqurey.width() * oDialogDivProperty.maxWidth / 100
            }
            width = 'auto';
        } else if(width !== 'auto') {    //当字符处理
            if(/\d+%/.test(width)) {
                width = ancestorWindowJqurey.width() * parseInt(width) / 100;
            } else {
                width = parseInt(width);
            }
        }
            //初始化距顶高度
        topNum = topNum == null ? false : parseInt(topNum);
            //是否显示特效
        effect = effect == null ? true : effect;

        //布局调整
        var oDialogDivLayout = function()
        {
            var IE6SelectMaskIframeObj = $.browser.msie && $.browser.version == 6 ? layoutObj.children('iframe').hide() : false;    //解决 IE6 下SELECT元素不能被遮住的bug:调整高度之前隐藏遮罩层,防止影响动画效果
            var oDialogDivContentObj = oDialogDivScrollObj.children('.content');
            var autoHeight = oDialogDivContentObj.height();    //计算出的.scroll高度
            if(width === 'auto')
            {
                //计算当前宽度值
                var operatingWidth = layoutObj.find('> .operating > a').outerWidth(true) * layoutObj.find('> .operating > a').length;
                var titleWidth = layoutObj.find('.title > h4').outerWidth(true) + (layoutObj.find('.title > a[callback=true]:visible').outerWidth(true) || 0);
                animateWidth = oDialogDivContentObj.outerWidth(true);
                if(height !== 'auto' && height < autoHeight)
                {
                    animateWidth += oDialogDiv.getScrollBarWidth();
                }
                animateWidth = animateWidth > operatingWidth ? animateWidth : operatingWidth;    //按钮宽度,内容宽度取其大
                animateWidth = animateWidth > titleWidth ? animateWidth : titleWidth;    //与标题宽度对比,取其大

                //计算 maxWidth 与 minWidth
                if(oDialogDivProperty.minWidth && oDialogDivProperty.minWidth > animateWidth)
                {
                    animateWidth = oDialogDivProperty.minWidth;
                }
                if(oDialogDivProperty.maxWidth && oDialogDivProperty.maxWidth < animateWidth)
                {
                    animateWidth = oDialogDivProperty.maxWidth;
                    height === 'auto' && (autoHeight += oDialogDiv.getScrollBarWidth());    //此时出现了纵向滚动条,如果高为自适应,则增加一个滚动条的高度
                }
            }
            if(height === 'auto')
            {
                animateHeight = autoHeight;

                //计算 maxHeight 与 minHeight
                if(oDialogDivProperty.minHeight && oDialogDivProperty.minHeight > animateHeight + scrollToLayoutMarginsTB)
                {
                    animateHeight = oDialogDivProperty.minHeight - scrollToLayoutMarginsTB;
                    oDialogDivIframeObj.length && oDialogDivIframeObj.height(animateHeight);    //如果是iframe模式,就更新oDialogDivIframeObj高度为最小高度
                }
                if(oDialogDivProperty.maxHeight && oDialogDivProperty.maxHeight < animateHeight + scrollToLayoutMarginsTB)
                {
                    animateHeight = oDialogDivProperty.maxHeight > scrollToLayoutMarginsTB ? oDialogDivProperty.maxHeight - scrollToLayoutMarginsTB : 0;
                    width === 'auto' && (animateWidth += oDialogDiv.getScrollBarWidth());    //此时出现了横向滚动条,如果宽为自适应,则增加一个滚动条的宽度
                }
            }
                //top属性值
            topNum = topNum || (
                ancestorWindowJqurey.height() - 
                (height === 'auto' ? animateHeight : height) - scrollToLayoutMarginsTB
            ) >> 1;
            if(topNum < 0)
            {
                topNum = 0;
            }
                //弹出层样式
            oDialogDivCssObj = {
                'opacity'  : 1,
                'width'    : animateWidth,
                'top'      : topNum + $(ancestorWindow.document).scrollTop(),
                'left'     : $(ancestorWindow.document).scrollLeft() + (ancestorWindowJqurey.width() - parseInt(animateWidth)) / 2
            }
                //滚动区样式
            scrollCssObj = {
                'width'  : animateWidth,
                'height' : animateHeight
            }

            //效果运算
            var animateAfterFun = function()    //动画结束后调用函数
            {
                //jqurey 在使用移动特效后,会改变overflow属性为hidden
                oDialogDivScrollObj.css({'overflow' : 'auto'});

                if(IE6SelectMaskIframeObj)    //如果是IE 6
                {
                    //解决 IE6 下SELECT元素不能被遮住的bug:重新显示遮罩并更新宽高
                    IE6SelectMaskIframeObj.css({'width' : animateWidth, 'height' : layoutObj.height(), 'display' : ''});

                    //IE 6 下激活透明png边框 Layout
                    layoutObj.children('.borderBgS[ie6png]').hide().show();
                }

                //IE 6,7 height === 'auto' 状态,如果横向出现滚动条,那么纵向也会出现滚动条
                if(height === 'auto' && $.browser.msie && $.browser.version < 8 && width !== 'auto' && oDialogDivContentObj.outerWidth(true) > oDialogDivScrollObj.prop('clientWidth'))
                {
                    oDialogDivScrollObj.prop('style').setExpression('height', oDialogDiv.getScrollBarWidth() + " + $('#" +layoutObj.prop('id')+ "').find('.content:eq(0)').outerHeight(true)")    //滚动条高度 + .content的外高
                }

                //布局回调
                if(typeof layoutFun === 'function')
                {
                    var callBackObj;    //回调对象
                    //读取回调对象
                    for(var i in ancestorDialogDiv.callBackList)
                    {
                        if(layoutObj.get(0) === ancestorDialogDiv.callBackList[i].oDialogDivObj.get(0))
                        {
                            callBackObj = ancestorDialogDiv.callBackList[i];
                        }
                    }
                    //调用回调函数(是否成功[当ajax加载失败时返回false], oDialogDiv第一次加载的window, 回调对象)
                    layoutFun(layoutStatus, window, callBackObj);
                }
            }
            layoutObj.children('.title').css('width', 'auto');
            if(effect)
            {
                layoutObj.stop(true, false)
                    .animate(oDialogDivCssObj, "fast")
                    .css({'overflow' : 'visible'});
                oDialogDivScrollObj.stop(true, false)
                    .animate(scrollCssObj, "fast", animateAfterFun);
            } else {
                layoutObj.css(oDialogDivCssObj)
                oDialogDivScrollObj.css(scrollCssObj);
                animateAfterFun();
            }
        }

        //变量相关计算
            //计算浮动层设置位置
        animateWidth = width;    //效果宽度(可能由于加载ajax或iframe而得到的信息不准,因此留着width做二次处理)
        animateHeight = height;    //效果高度
            //计算iframe内部宽度
        oDialogDivIframeObj = layoutObj.find('iframe[id^=oDialogDiv_iframe_]');
        if(oDialogDivIframeObj.length && layoutFun !== false)
        {
            try{
                var iframeWindowObj = $(oDialogDivIframeObj.get(0).contentWindow);
                oDialogDivScrollObj.width(oDialogDivScrollObj.width()).height(oDialogDivScrollObj.height());    //定主长宽,防止因iframe变大影响动画效果
                var iframeWindowResizeFun = function(){
                    var documentObj = $(this.document);
                    if(documentObj.width() >= iframeWindowObj.width())    //当resize事件布局结束时
                    {
                        if(arguments.callee.scrollWidth)    //设置iframe的宽
                        {
                            if(
                                (oDialogDivProperty.maxHeight === undefined && (height === 'auto' || documentObj.height() <= height)) ||        //高度最小值 或 高度自动 或 指定高度值
                                (oDialogDivProperty.maxHeight !== undefined && documentObj.height() <= oDialogDivProperty.maxHeight)            //高度最大值
                            ) {
                                oDialogDivIframeObj.width(arguments.callee.scrollWidth + oDialogDiv.getScrollBarWidth());
                                delete arguments.callee.scrollWidth;
                                if($.browser.msie && $.browser.version < 7)
                                {
                                    iframeWindowObj.resize();
                                }
                                return ;
                            }
                        }

                        //设置iframe的高
                        ancestorWindow.setTimeout(function(){    //火狐浏览器在此处会卡住,因此这样写
                            iframeWindowObj.unbind('resize', iframeWindowResizeFun);    //移除自身绑定
                            if(documentObj.width() > oDialogDivIframeObj.width())    //当内容宽度大于iframe宽度时,调整iframe,达到自动适应的问题
                            {
                                oDialogDivIframeObj.width(documentObj.width());
                            }
                            if(height !== 'auto' && height > documentObj.height())    //当iframe高度不足height时,填充
                            {
                                oDialogDivIframeObj.height(height);
                            } else {
                                oDialogDivIframeObj.height(documentObj.height());
                            }
                            oDialogDivLayout();    //iframe长宽计算结束,开始oDialogDiv布局
                        }, 0);
                    }
                };
                iframeWindowResizeFun.scrollWidth = (width === 'auto' ? 299 : width - 11) - oDialogDiv.getScrollBarWidth();    //给滚动条留15px+边距10px+IE iframe 边框1px
                oDialogDivIframeObj.css({'height' : 'auto', 'width' : iframeWindowResizeFun.scrollWidth});    //width === 'auto' ? width : width - 11
                iframeWindowObj.resize(iframeWindowResizeFun);    //通过resize系统回调处理iframe的布局信息
                if($.browser.msie || /trident/.test(navigator.userAgent.toLowerCase()) )        //IE < 10 || IE = 11
                {
                    iframeWindowObj.resize();
                }
            } catch(e) {    //跨站加载,无法计算高度
                layoutStatus = false;
                oDialogDivLayout();
            }
        } else {
            oDialogDivLayout();
        }
    }

    /**
    * 描  述:关闭弹出层
    * 函数名:dialogClose
    * 参数名:
    *     dateTime :传入的handle句柄
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.dialogClose=function(dateTime){
        var ancestorWindow = oDialogDiv.getAncestorWindow();
        var callBackList = ancestorWindow.oDialogDiv.callBackList[dateTime];
        if(callBackList)
        {
            $('<input style="position:absolute; top:0px; border:0px; width:1px; height:0px; overflow:hidden; z-index:-1;" />')    //IE 7~9 iframe模式移除时会同时移除焦点bug
                .appendTo(callBackList.oDialogDivObj)
                .focus()
                .remove();
            callBackList.oDialogDivBgObj.remove();
            callBackList.oDialogDivObj.animate({opacity: "0"}, 100, function() {
                $(this).remove();
                delete ancestorWindow.oDialogDiv.callBackList[dateTime];
            });
        }
    };

    /**
     * 描述 : 按esc关闭弹出层
     * 作者 : Edgar.lee
     */
    $(document).keyup(oDialogDiv.escClose = function (event) {
        if( event.keyCode === 27 )
        {
            var escClose, callBackObj = window.oDialogDiv.getTreeNode(-1);
            if( callBackObj )
            {
                $.type(escClose = callBackObj.config.escClose) === 'string' && (escClose = "'" + escClose + "'");
                $('a[callback="' +escClose+ '"]', callBackObj.oDialogDivObj).mousedown().click();
            }
        }
    });

    /**
    * 描  述:获取滚动条宽度
    * 函数名:getScrollBarWidth
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.getScrollBarWidth = function()
    {
        if(!arguments.callee.scrollBarWidth)
        {
            var temp = $('<div style="height:50px; width:100px; overflow-y:scroll; z-index:-1; visibility:hidden; top:-100px; position:absolute;"></div>').appendTo(oDialogDiv.getAncestorWindow().document.body);
            arguments.callee.scrollBarWidth = 100 - temp.prop('clientWidth');
            temp.remove();
        }
        return arguments.callee.scrollBarWidth;
    }

    /**
    * 描  述:显示或关闭操作提示
    * 函数名:tip
    * 参数名:
    *     text    :传入显示的内容,当传参为true时显示默认加载,当不传值或传入boolean值为false时关闭提示窗口
    *     timeout    :超时时间,当超时后自动关闭显示,默认永不超时
    * 示  例:
    *     tip('请等待...');
    *     显示'请等待...'
    *     tip(true);
    *     显示上次显示的
    *     tip();
    *     关闭显示
    *   tip('锁定显示');    //显示'锁定显示'
    *     tip.lock=true;    //锁定,这时其他处理均无效
    *     tip.lock=false;    //解锁
    *     tip();            //关闭显示
    *     tip('定时显示',3000);    //三秒后自动关闭
    *     tip('定时显示',false);    //永久显示直到手动关闭
    *     tip.lock=true;    //锁定,三秒钟内禁止修改,但可以使用tip()来解锁
    *     定时与锁定
    * 作  者:Edgar.Lee
    **/
    oDialogDiv.tip = function(text, timeout, nodeNum, mask)
    {
        var ancestorWindow = oDialogDiv.getAncestorWindow();    //诛仙window窗口
        var ancestorDialogDiv = ancestorWindow.oDialogDiv;    //祖先oDialogDiv
        var lastCallBackList = nodeNum === false ? null : ancestorDialogDiv.getTreeNode(nodeNum == null ? -1 : nodeNum);    //最后弹出层回调列表
        var thisFun = arguments.callee;    //本函数索引
        var tipObj = thisFun.tipObj;    //提示对象
        var bgObj = thisFun.bgObj;    //提示对象
        if(typeof text === 'undefined')    //移除提示
        {
            tipObj.animate({'opacity' : 0, 'height'  : 0}, 'fast', function(){
                tipObj.remove();
                bgObj.remove();
                thisFun.lock = false;    //自动解锁
            });
        } else if(!thisFun.lock) {
            text = text === true ? tipObj.html() : text;    //是否显示上次信息
            tipObj.stop(true,true);    //停止提示动画
            var tipObjOuterWidth = tipObj.css({"opacity" : 0, 'height' : 0, 'top' : -50}).html(text).appendTo(ancestorWindow.document.body).outerWidth();    //提示对象宽带
            var oDialogDivScrollWidth;          //.scroll宽度

            if(
                lastCallBackList == null ||     //插入提示框
                (
                    (oDialogDivScrollWidth = lastCallBackList.oDialogDivObj.children('.scroll').width()) < tipObjOuterWidth + 100 || 
                    lastCallBackList.oDialogDivObj.children('.scroll').height() < 100
                )
            ) {         //将提示信息加入到body中
                if($.browser.msie && $.browser.version == "6.0" || document.documentMode === 5)
                {
                    tipObj.get(0).style.setExpression('left', 'eval(document.documentElement.scrollLeft+document.documentElement.clientWidth-this.offsetWidth) / 2');
                    tipObj.get(0).style.setExpression('top', 'eval(document.documentElement.scrollTop)');
                } else {
                    tipObj.css({
                        'position' : 'fixed', 
                        'top'      : 0, 
                        'left'     : ($(ancestorWindow).width() - tipObjOuterWidth) >> 1
                   });
                }
            } else {    //将提示信息加入到oDialogDiv中
                lastCallBackList.oDialogDivObj.append(
                    tipObj.css({
                        'position' : 'absolute', 
                        'top'      : lastCallBackList.oDialogDivObj.find('> .title').outerHeight(), 
                        'left'     : (oDialogDivScrollWidth - tipObjOuterWidth) >> 1
                    })
                );
            }

            if(mask)    //遮罩层
            {
                bgObj.appendTo(ancestorWindow.document.body);
            } else {
                bgObj.remove();
            }
            tipObj.height(0).animate({'opacity' : 1, 'height'  : 14}, 'fast', function(){
                clearTimeout(thisFun.timeoutObj);
                if(timeout !== false)
                {
                    thisFun.timeoutObj = setTimeout(function(){
                        thisFun();
                    }, timeout == null ? 3000 : timeout)
                }
            });
        }
    }
    oDialogDiv.tip.tipObj = $('<div class="tip"></div>');
    oDialogDiv.tip.bgObj = $('<div class="oDialogDivBg" style="z-index:2147483584;" ></div>');
}
//添加鼠标移动对象
if(oDialogDiv.mouseDragObj == null && typeof window.mouseDrag === 'function')
{
    oDialogDiv.mouseDragObj = new mouseDrag();
    //移动弹出层
    oDialogDiv.floatDivMove = function(obj)
    {
        var ancestorWindow = oDialogDiv.getAncestorWindow();
        var oDialogDivObj = oDialogDiv.mouseDragObj.$("oDialogDiv_"+obj.Customize.dateTime, ancestorWindow);
        oDialogDivObj.style.top = oDialogDivObj.offsetTop + obj.nH + "px";
        oDialogDivObj.style.left = oDialogDivObj.offsetLeft + obj.nW + "px";
        //判断拖拽是否溢出上左边框
        var offsetStyle = $(oDialogDivObj).offset();
        offsetStyle.scrollTop = $(ancestorWindow.document).scrollTop();
        offsetStyle.scrollLeft = $(ancestorWindow.document).scrollLeft();
        offsetStyle.scrollWidth = $(ancestorWindow).width();
        offsetStyle.outerWidth = $(oDialogDivObj).outerWidth();

        if( offsetStyle.scrollTop > offsetStyle.top )
        {
            oDialogDivObj.style.top = offsetStyle.scrollTop + 'px';
        }
        if( offsetStyle.scrollLeft > offsetStyle.left )
        {
            oDialogDivObj.style.left = offsetStyle.scrollLeft + 'px';
        }
    }
    //鼠标按下
    oDialogDiv.mouseDownFn = function(obj)
    {
        var ancestorDocument = oDialogDiv.getAncestorWindow().document;
        var oDialogDivObj = $('#oDialogDiv_' + obj.Customize.dateTime, ancestorDocument);
        oDialogDivObj.find('> .title > .maskLayer').height(oDialogDivObj.find('> .scroll').outerHeight(true));    //拉下遮罩层(鼠标上下平滑移动)
    }
    //鼠标弹起
    oDialogDiv.mouseUpFn = function(obj)
    {
        var ancestorDocument = oDialogDiv.getAncestorWindow().document;
        var oDialogDivObj = $('#oDialogDiv_' + obj.Customize.dateTime, ancestorDocument);
        oDialogDivObj.find('> .title > .maskLayer').height(0);
    }
};
