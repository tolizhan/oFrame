/*播放器的扩展对象,包括创建对象,移除对象,内部监听等功能*/
function ofPlayerClass(obj)
{
    /*播放器配置文件,存储当前播放器运行过程中的参数*/
    this.Config={
            "setInterval":{
                    "obj":null,                //setInterval对象
                    "list":[
                        //{"fn":执行方法,"vars":传递参数数组,"count":执行次数,"customize":自定义变量一个空对象}    同时会把当前任务在队列中的位置追加到最后一个参数后
                    ]
                },
            "ofPlayerVar":{
                    "conf":{                //播放器属性
                        "PlayCount":0            //总播放次数
                        },
                    "skin":{},                //皮肤属性
                    "list":{}                //列表属性
                },
            "ready":{                    //播放器加载完成钱注册准备执行的列表
                    "readied":false,            //是否已准备就绪
                    "list":[]                //准备就绪前的方法列表
                }
        };
    /*根据ID返回对象,如果是对象直接返回*/
    this.$=function(id)
    {
        if(typeof(id)=="string")
        {
            return document.getElementById(id);
        }
        else if(typeof(id)=="function")        //注册ready事件
        {
            if(this.Config.ready.readied)
            {
                id.apply(this);
            }
            else
            {
                this.Config.ready.list[this.Config.ready.list.length]=id;
            }
        }
        return id;
    }
    /*--------------------
    //说明:像浏览器最后一个Style追加CSS样式,如果没有Style,将创建一个
    //参数:
    //    attribute:样式头    (div,body,#id)
    //    key:样式属性        (height,width,border)
    //    value:样式值        ("100%","100px","#000")
    //示例:像浏览器追加 html,body{height:100%;}
    //    this.setStyleCSS("html,body","height","100%");
    --------------------*/
    this.setStyleCSS=function(attribute,key,value)
    {
        if(document.styleSheets.length==0)
        {
            document.body.appendChild(document.createElement("style"));
        }
        var styleSheetsObj=document.styleSheets[document.styleSheets.length-1];
        var rulesObj = styleSheetsObj.cssRules ? styleSheetsObj.cssRules: styleSheetsObj.rules;
        var j=null;
        var returnValue="";
        for(var i=0;i<rulesObj.length;i++)
        {
            if(rulesObj[i].selectorText==attribute)
            {
                j=i;
                break;
            }
        }
        if(j==null)
        {
            if(styleSheetsObj.insertRule)
            {
                styleSheetsObj.insertRule(attribute+"{"+key+":"+value+"}", rulesObj.length);
            }
            else
            {
                var attributeArr=attribute.split(',');    //兼容IE6该方法的兼容规则
                for(var i in attributeArr)
                {
                    styleSheetsObj.addRule(attributeArr[i], key+":"+value,rulesObj.length);
                }
            }
            j=rulesObj.length;
        }
        else
        {
            returnValue=rulesObj[j].style[key];
            rulesObj[j].style[key]=value;
        }
        return {"rulesObj":rulesObj,"index":j,"attribute":attribute,"key":key,"value":returnValue};
    }
    /*判断传入参数是否为数组*/
    this.is_array = function(value) {
        return value && typeof value === 'object' && typeof value.length === 'number' && typeof value.splice === 'function' && !(value.propertyIsEnumerable('length'));
    }
    this.ofPlayerAutoFn=function(listI)
    {
        var bFileInfo = this.jsOfPlayerFn('getinfo', 'fileinfo');
        var bPlayBack = this.jsOfPlayerFn('getinfo', 'playback');
        if(bFileInfo&&bPlayBack)
        {
            var bigPlayTemp = this.jsOfPlayerFn('getatt', 'bigPlay','y');
            if(bFileInfo.type=="swf"&&bigPlayTemp>0)
            {
                this.Config.ofPlayerVar.skin.bigPlayY=bigPlayTemp;
                this.jsOfPlayerFn('adjust', 'bigPlay', 'y',-1000);
            }
            else if(bFileInfo.type!="swf"&&bigPlayTemp<0)
            {
                this.jsOfPlayerFn('adjust', 'bigPlay', 'y',this.Config.ofPlayerVar.skin.bigPlayY);
            }
            if(bPlayBack.position<1000)
            {
                this.Config.ofPlayerVar.conf.PlayCountTime=true;
            }
            else if(this.Config.ofPlayerVar.conf.PlayCountTime===true&&bPlayBack.position>bFileInfo.duration-1000)
            {
                this.Config.ofPlayerVar.conf.PlayCountTime=false;
                this.Config.ofPlayerVar.conf.PlayCount++;
                if(isNaN(this.Config.ofPlayerVar.list[bFileInfo.url+bFileInfo.title+"Count"]))
                {
                    this.Config.ofPlayerVar.list[bFileInfo.url+bFileInfo.title+"Count"]=0;
                }
                this.Config.ofPlayerVar.list[bFileInfo.url+bFileInfo.title+"Count"]++;
            }
            
            //ready事件
            var ready=this.Config.ready;
            ready.readied?null:ready.readied=true;
            if(ready.list.length)
            {
                ready.readied=true;
                for(var i=0;i<ready.list.length;i++)
                {
                    if(typeof(ready.list[i])=='function')
                    {
                        ready.list[i].apply(this);
                    }
                }
                ready.list=[];
            }
        }
    }
    /*开始内部循环方法,该方法将每个300毫秒循环调用this.setIntervalFn*/
    this.startIntervalFn=function()
    {
        if(this.Config.setInterval.obj==null)
        {
            this.Config.setInterval.obj=setInterval(this.setIntervalFn.ofPlayerClassClosure(this),300);
        }
    }
    /*停止内部循环方法*/
    this.stopIntervalFn=function()
    {
        if(this.Config.setInterval.obj)
        {
            clearInterval(this.Config.setInterval.obj);
        }
    }
    /*内部循环调用的核心程序,该函数将遍历并执行注册在this.Config.setInterval.list中的方法*/
    this.setIntervalFn=function()
    {
        var list=this.Config.setInterval.list;
        if( typeof JS_OFplayer === 'function' )
        {
            for(var listI in list)
            {
                if(list[listI].count!=0)
                {
                    list[listI].count--;
                    try{
                        list[listI].fn.apply(this,list[listI].vars.concat([listI]))
                    }catch(e){}
                }
                else
                {
                    list.splice(listI,1);
                }
            }
        }
    }
    /*--------------------------
    //说明:将监听方法注册到this.setIntervalFn的循环中中,注册的方法将每个300毫秒被执行一次
    //参数:
    //    fn:需要监听的方法索引或实体            (function(){})
    //    vars:以数组形式传递的参数            ([])
    //    count:需要执行的次数,-1为无限循环        (5,-1)
    //示例:将一个alert(x)的功能注册到监听中,执行3次
    //    this.insterIntervalFn(function(x){alert(x)},["弹出字符串"],3)
    --------------------------*/
    this.insterIntervalFn=function(fn,vars,count)
    {
        if(fn)
        {
            if(!this.is_array(vars))
            {
                vars = [];
            }
            if(isNaN(parseInt(count)))
            {
                count=1;
            }
            this.Config.setInterval.list[this.Config.setInterval.list.length]={"fn":fn,"vars":vars,"count":count,"customize":{}};
        }
    }
    /*移除传如方法索引的全部监听*/
    this.removeIntervalFn=function(fn)
    {
        var list=this.Config.setInterval.list;
        if(typeof(fn)=='function')
        {
            for(var listI in list)
            {
                if(list[listI].fn==fn)
                {
                    list.splice(listI,1);
                }
            }
        }
        else
        {
            list.splice(fn,1);
        }
    }
    /*返回传入监听方法索引的数组*/
    this.getIntervalFn=function(fn)
    {
        var list=this.Config.setInterval.list;
        var returnArr=[];
        
        if(typeof(fn)=='function')
        {
            for(var listI in list)
            {
                if(list[listI].fn==fn)
                {
                    returnArr[returnArr.length]=list[listI];
                }
            }
            return returnArr;
        }
        else
        {
            return list[fn];
        }
    }
    /*设置播放器的宽高*/
    this.setWidth=function(w,h)
    {
        var id=this.ofPlayerObj.id;
        var obj=this.$(id);
        if(obj!=null)
        {
            embedObj=obj.getElementsByTagName("embed")[0];
            if(w!=null)
            {
                obj.width=w;
                if(embedObj!=null)
                {
                    embedObj.width=w;
                }
            }
            if(h!=null)
            {
                obj.height=h;
                if(embedObj!=null)
                {
                    embedObj.height=h;
                }
            }
            this.jsOfPlayerFn('event','normalScreen');
        }
    }
    /*执行播放器的JS_OFplayer方法*/
    this.jsOfPlayerFn=function(a,b,c,d)
    {
        var evalTemp="";
        if(a!=null)
        {
            evalTemp+="JS_OFplayer('"+this.ofPlayerObj.id+"','"+a+"'"
            if(b!=null)
            {
                evalTemp+=",'"+b+"'";
                if(c!=null)
                {
                    evalTemp+=",'"+c+"'";
                    if(d!=null)
                    {
                        evalTemp+=",'"+d+"'";
                    }
                }
            }
            evalTemp+=");"
            return eval(evalTemp);
        }
    }
    /*主要的功能是将播放器对象追加到前台参数节点的末节点,同时启动内部监听,并将this.insterIntervalFn注册到监听*/
    this.instance=function(obj)
    {
        if(this.ofPlayerObj.obj=this.$(obj))
        {
            if(this.ofPlayerObj.ofPlayerObj==null)
            {
                var divTemp=document.createElement("div");
                divTemp.innerHTML=this.ofPlayerObj.html;
                this.ofPlayerObj.ofPlayerObj=divTemp.firstChild;
            }
            this.ofPlayerObj.obj.appendChild(this.ofPlayerObj.ofPlayerObj);
            this.insterIntervalFn(this.ofPlayerAutoFn,"",-1);
            this.startIntervalFn();
        }
    }
    /*移除ofPlayer播放器*/
    this.removeOfPlayer = function()
    {
        this.stopIntervalFn();
        this.ofPlayerObj.ofPlayerObj.parentNode.removeChild(this.ofPlayerObj.ofPlayerObj);
    }
    /*初始化方法,添加参数默认值等功能*/
    this.init=function(obj)
    {
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
            this.$(obj.obj).innerHTML = '<a target="_blank" href="http://get.adobe.com/flashplayer/">' +
                '<img src="' + obj.url.substr(0, obj.url.length - 28) + '/images/flashInstall.jpg"/>' +
            '</a>';
            return ;
        }

        //obj={id:播放器的ID,width:播放器的宽,height:播放器的高,wmode:播放器的窗口模式,url:播放器的实体文件路径,vars:播放器的相关参数,obj:需要追加该播放器的对象,ofPlayerObj:该播放器的实体对象}
        obj.id=obj.id?obj.id:"ofPlayer_id"+new Date().getTime();
        obj.width=obj.width?obj.width:"100%";
        obj.height=obj.height?obj.height:"100%";
        obj.wmode=obj.wmode=="transparent"?obj.wmode:"opaque";
        obj.html = '';
        obj.html += '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" ';
        obj.html += 'width="'+obj.width+'" ';
        obj.html += 'height="'+obj.height+'" ';
        obj.html += 'id="'+obj.id+'"';
        obj.html += '>';
        obj.html += '<param name="movie" value="'+obj.url+'" />';
        obj.html += '<param name="allowFullScreen" value="true" />';
        obj.html += '<param name="allowScriptAccess" value="always" />';
        obj.html += '<param name="quality" value="high" />';
        obj.html += '<param name="wmode" value="'+obj.wmode+'" />';
        obj.html += '<param name="flashvars" value="'+obj.vars+'" />';
        obj.html += '<embed type="application/x-shockwave-flash" ';
        obj.html += 'width="'+obj.width+'" ';
        obj.html += 'height="'+obj.height+'" ';
        obj.html += 'name="'+obj.id+'" ';
        obj.html += 'src="'+obj.url+'" ';
        obj.html += 'allowfullscreen="true" ';
        obj.html += 'allowscriptaccess="always" ';
        obj.html += 'quality="high" ';
        obj.html += 'wmode="'+obj.wmode+'" ';
        obj.html += 'flashvars="'+obj.vars+'"'
        obj.html += '></embed>';
        obj.html += '</object>';
        this.ofPlayerObj=obj;
        this.ofPlayerObj.Config=this.Config;
        this.instance(obj.obj);
    }
    if(Function.prototype.ofPlayerClassClosure==null)
    {
        Function.prototype.ofPlayerClassClosure = function() {
            var __method = this;
            var obj=arguments[0];
            return function(){ return __method.apply(obj);};
        }
    }
    this.setStyleCSS("html,body","height","100%");
    this.init(obj);
}