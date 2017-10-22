//encoding="utf-8"
/* NicEdit - Micro Inline WYSIWYG
 * Copyright 2007-2008 Brian Kirchoff
 *
 * NicEdit is distributed under the terms of the MIT license
 * For more information visit http://nicedit.com/
 * Do not remove this copyright message
 */
//李占增加按钮自定义参数:属性全继承
window.ROOT_URL || (window.ROOT_URL = '');
var ObjAllExtend=function (CustomConfig,o)
{
    for(var tempObj in o)
    {
        if(typeof(CustomConfig[tempObj])=="object"&&CustomConfig[tempObj]!=null)
        {
            ObjAllExtend(CustomConfig[tempObj],o[tempObj]);
        }
        else if(typeof(o[tempObj])=="object"&&o[tempObj]!=null)
        {
            CustomConfig[tempObj]={};
            ObjAllExtend(CustomConfig,o);
        }
        else
        {
            CustomConfig[tempObj]=o[tempObj];
        }
    }
}
//结束
var bkExtend = function(){
    var args = arguments;
    if (args.length == 1) args = [this, args[0]];
    for (var prop in args[1]) args[0][prop] = args[1][prop];
    return args[0];
};
function bkClass() { }
bkClass.prototype.construct = function() {};
bkClass.extend = function(def) {
  var classDef = function() {
      if (arguments[0] !== bkClass) { return this.construct.apply(this, arguments); }
  };
  var proto = new this(bkClass);
  bkExtend(proto,def);
  classDef.prototype = proto;
  classDef.extend = this.extend;      
  return classDef;
};

var bkElement = bkClass.extend({
    construct : function(elm,d) {
        if(typeof(elm) == "string") {
            elm = (d || document).createElement(elm);
        }
        elm = $BK(elm);
        return elm;
    },
    
    appendTo : function(elm) {
        elm.appendChild(this);    
        return this;
    },
    
    appendBefore : function(elm) {
        elm.parentNode.insertBefore(this,elm);    
        return this;
    },
    
    addEvent : function(type, fn) {
        bkLib.addEvent(this,type,fn);
        return this;    
    },
    
    setContent : function(c) {
        this.innerHTML = c;
        return this;
    },
    
    pos : function() {
        var curleft = curtop = 0;
        var o = obj = this;
        if (obj.offsetParent) {
            do {
                curleft += obj.offsetLeft;
                curtop += obj.offsetTop;
            } while (obj = obj.offsetParent);
        }
        var b = (!window.opera) ? parseInt(this.getStyle('border-width') || this.style.border) || 0 : 0;
        return [curleft+b,curtop+b+this.offsetHeight];
    },
    
    noSelect : function() {
        bkLib.noSelect(this);
        return this;
    },
    
    parentTag : function(t) {
        var elm = this;
         do {
            if(elm && elm.nodeName && elm.nodeName.toUpperCase() == t) {
                return elm;
            }
            elm = elm.parentNode;
        } while(elm);
        return false;
    },
    
    hasClass : function(cls) {
        return this.className.match(new RegExp('(\\s|^)nicEdit-'+cls+'(\\s|$)'));
    },
    
    addClass : function(cls) {
        if (!this.hasClass(cls)) { this.className += " nicEdit-"+cls };
        return this;
    },
    
    removeClass : function(cls) {
        if (this.hasClass(cls)) {
            this.className = this.className.replace(new RegExp('(\\s|^)nicEdit-'+cls+'(\\s|$)'),' ');
        }
        return this;
    },

    setStyle : function(st) {
        var elmStyle = this.style;
        for(var itm in st) {
            try{
                switch(itm) {
                    case 'float':
                        elmStyle['cssFloat'] = elmStyle['styleFloat'] = st[itm];
                        break;
                    case 'opacity':
                        elmStyle.opacity = st[itm];
                        elmStyle.filter = "alpha(opacity=" + Math.round(st[itm]*100) + ")"; 
                        break;
                    case 'className':
                        this.className = st[itm];
                        break;
                    default:
                        //if(document.compatMode || itm != "cursor") { // Nasty Workaround for IE 5.5
                        elmStyle[itm] = st[itm];
                        //}
                }
            } catch (e) {
                //alert(e +':'+ itm + '|' +st[itm]);    //如果发生异常,弹出赋值错误的信息
            }
        }
        return this;
    },
    
    getStyle : function( cssRule, d ) {
        var doc = (!d) ? document.defaultView : d; 
        if(this.nodeType == 1)
        return (doc && doc.getComputedStyle) ? doc.getComputedStyle( this, null ).getPropertyValue(cssRule) : this.currentStyle[ bkLib.camelize(cssRule) ];
    },
    
    remove : function() {
        this.parentNode.removeChild(this);
        return this;    
    },
    
    setAttributes : function(at) {
        for(var itm in at) {
            this[itm] = at[itm];
        }
        return this;
    }
});

var bkLib = {
    isMSIE    : (navigator.appVersion.indexOf("MSIE") != -1),
    isFirefox : (navigator.userAgent.indexOf("Firefox") != -1),
    isChrome  : (navigator.userAgent.indexOf("Chrome") != -1),
    version   : (navigator.userAgent.match(/.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/i) || [])[1],
    
    addEvent : function(obj, type, fn) {
        (obj.addEventListener) ? obj.addEventListener( type, fn, false ) : obj.attachEvent("on"+type, fn);    
    },
    
    toArray : function(iterable) {
        var length = iterable.length, results = new Array(length);
        while (length--) { results[length] = iterable[length] };
        return results;    
    },
    
    noSelect : function(element) {
        if(element.setAttribute && element.nodeName.toLowerCase() != 'input' && element.nodeName.toLowerCase() != 'textarea') {
            element.setAttribute('unselectable','on');
        }
        for(var i=0;i<element.childNodes.length;i++) {
            bkLib.noSelect(element.childNodes[i]);
        }
    },
    camelize : function(s) {
        return s.replace(/\-(.)/g, function(m, l){return l.toUpperCase()});
    },
    inArray : function(arr,item) {
        return (bkLib.search(arr,item) != null);
    },
    search : function(arr,itm) {
        for(var i=0; i < arr.length; i++) {
            if(arr[i] == itm)
                return i;
        }
        return null;    
    },
    cancelEvent : function(e) {
        e = e || window.event;
        if(e.preventDefault && e.stopPropagation) {
            e.preventDefault();
            e.stopPropagation();
        }
        return false;
    },
    domLoad : [],
    domLoaded : function() {
        if (arguments.callee.done) return;
        arguments.callee.done = true;
        for (i = 0;i < bkLib.domLoad.length;i++) bkLib.domLoad[i]();
    },
    onDomLoaded : function(fireThis) {
        this.domLoad.push(fireThis);
        if (document.addEventListener) {
            document.addEventListener("DOMContentLoaded", bkLib.domLoaded, null);
        } else if(bkLib.isMSIE) {
            document.write("<style>.nicEdit-main p { margin: 0; }</style><scr"+"ipt id=__ie_onload defer " + ((location.protocol == "https:") ? "src='javascript:void(0)'" : "src=//0") + "><\/scr"+"ipt>");
            $BK("__ie_onload").onreadystatechange = function() {
                if (this.readyState == "complete"){bkLib.domLoaded();}
            };
        }
        window.onload = bkLib.domLoaded;
    }
};

function $BK(elm) {
    if(typeof(elm) == "string") {
        elm = document.getElementById(elm);
    }
    return (elm && !elm.appendTo) ? bkExtend(elm,bkElement.prototype) : elm;
}

var bkEvent = {
    addEvent : function(evType, evFunc) {
        if(evFunc) {
            this.eventList = this.eventList || {};
            this.eventList[evType] = this.eventList[evType] || [];
            this.eventList[evType].push(evFunc);
        }
        return this;
    },
    fireEvent : function() {
        var args = bkLib.toArray(arguments), evType = args.shift();
        if(this.eventList && this.eventList[evType]) {
            for(var i=0;i<this.eventList[evType].length;i++) {
                this.eventList[evType][i].apply(this,args);
            }
        }
    }    
};

function __(s) {
    return s;
}

Function.prototype.closure = function() {
  var __method = this, args = bkLib.toArray(arguments), obj = args.shift();
  return function() { if(typeof(bkLib) != 'undefined') { return __method.apply(obj,args.concat(bkLib.toArray(arguments))); } };
}
    
Function.prototype.closureListener = function() {
      var __method = this, args = bkLib.toArray(arguments), object = args.shift(); 
      return function(e) { 
      e = e || window.event;
      if(e.target) { var target = e.target; } else { var target =  e.srcElement };
          return __method.apply(object, [e,target].concat(args) ); 
    };
}        

//李占像W3C标准中增加outerHTML方法
if(!bkLib.isMSIE)
{
    window.HTMLElement.prototype.__defineGetter__("outerHTML",function(){
        var oP = document.createElement("p");
        oP.appendChild(this.cloneNode(true));
        return oP.innerHTML;
    });
    window.HTMLElement.prototype.__defineSetter__("outerHTML",function(s){     
        var r = this.ownerDocument.createRange();
        //r.setStartBefore(this);
        var df = r.createContextualFragment(s);
        this.parentNode.replaceChild(df, this);
        return s;
    });
}
var outerHTML=function(def)
{
    var oP = document.createElement("p");
    oP.appendChild(def);
    return oP.innerHTML;
}
//结束

/* START CONFIG */

var nicEditorConfig = bkClass.extend({
    buttons : {
        'bold' : {name : __('加粗'), command : 'Bold', tags : ['B','STRONG'], css : {'font-weight' : 'bold'}, key : 'b'},
        'italic' : {name : __('倾斜'), command : 'Italic', tags : ['EM','I'], css : {'font-style' : 'italic'}, key : 'i'},
        'underline' : {name : __('下划线'), command : 'Underline', tags : ['U'], css : {'text-decoration' : 'underline'}, key : 'u'},
        'left' : {name : __('左对齐'), command : 'justifyleft', noActive : true},
        'center' : {name : __('居中'), command : 'justifycenter', noActive : true},
        'right' : {name : __('右对齐'), command : 'justifyright', noActive : true},
        'justify' : {name : __('两端对齐'), command : 'justifyfull', noActive : true},
        'ol' : {name : __('编号列表'), command : 'insertorderedlist', tags : ['OL']},
        'ul' :     {name : __('项目列表'), command : 'insertunorderedlist', tags : ['UL']},
        'subscript' : {name : __('下标'), command : 'subscript', tags : ['SUB']},
        'superscript' : {name : __('上标'), command : 'superscript', tags : ['SUP']},
        'strikethrough' : {name : __('删除线'), command : 'strikeThrough', css : {'text-decoration' : 'line-through'}},
        'removeformat' : {name : __('移除格式'), command : 'removeformat', noActive : true},
        //'indent' : {name : __('增加缩进'), command : 'indent', noActive : true},
        //'outdent' : {name : __('减少缩进'), command : 'outdent', noActive : true},
        'hr' : {name : __('插入水平线'), command : 'insertHorizontalRule', noActive : true}
    },
    iconsPath : ROOT_URL+oFileManagerMainDir+'/include/oEditor/resources/oEditorIcons.gif',//ROOT_URL+'/templates/default/images/orEditorIcons.gif'
    buttonList : ['save','bold','italic','underline','left','center','right','justify','ol','ul','fontSize','fontFamily','fontFormat','indent','outdent','image','upload','link','unlink','forecolor','bgcolor','table','subscript','superscript','strikethrough','removeformat','hr','ImageArea','AnswerSelect',"media",'xhtml'],
    iconList : {"xhtml":1,"bgcolor":2,"forecolor":3,"bold":4,"center":5,"hr":6,"indent":7,"italic":8,"justify":9,"left":10,"ol":11,"outdent":12,"removeformat":13,"right":14,"save":25,"strikethrough":16,"subscript":17,"superscript":18,"ul":19,"underline":20,"image":21,"link":22,"unlink":23,"close":24,"arrow":26,"upload":27,"table":28,"AnswerSelect":29,"ImageArea":31,"media":32}
    
});
/* END CONFIG */


var nicEditors = {
    nicPlugins : [],
    editors : [],
    
    registerPlugin : function(plugin,options) {
        this.nicPlugins.push({p : plugin, o : options});
    },

    allTextAreas : function(nicOptions) {
        var textareas = document.getElementsByTagName("textarea");
        for(var i=0;i<textareas.length;i++) {
            nicEditors.editors.push(new nicEditor(nicOptions).panelInstance(textareas[i]));
        }
        return nicEditors.editors;
    },
    
    findEditor : function(e) {
        var editors = nicEditors.editors;
        for(var i=0;i<editors.length;i++) {
            if(editors[i].instanceById(e)) {
                return editors[i].instanceById(e);
            }
        }
    }
};


var nicEditor = bkClass.extend({
    construct : function(o) {
        this.options = new nicEditorConfig();
        bkExtend(this.options,o);
        this.nicInstances = new Array();
        this.loadedPlugins = new Array();
        //李占增加按钮自定义参数
        this.CustomConfig={
                oFileManager : {
                        quickUploadDir : {}
                        ,browseDir     : {}
                    }
            };
        //结束
        //李占增加支持虚拟目录
        if(window.ROOT_URL==null)
        {
            window.ROOT_URL="";
        }
        //结束
        var plugins = nicEditors.nicPlugins;
        for(var i=0;i<plugins.length;i++) {
            this.loadedPlugins.push(new plugins[i].p(this,plugins[i].o));
        }
        //李占增加按钮自定义参数
        ObjAllExtend(this.CustomConfig,o.CustomConfig);
        window.CustomConfig=this.CustomConfig;
        //结束
        nicEditors.editors.push(this);
        //李占优化代码删除
        //bkLib.addEvent(document.body,'mousedown', this.selectCheck.closureListener(this) );
        //结束
    },
    
    panelInstance : function(e,o) {
        //李占增加整合ots配置
        o=o||{};
        o.hasPanel=true;
        var ll=$BK(e);
        if(ll)
        {
            /*if(ll.tagName!="TEXTAREA")
            {
                ll.outerHTML=ll.outerHTML.replace(/\r/g,"").replace(/\n/g,"").replace(/^<textarea(.*)<\/textarea>$/i, "<div$1</div>");
                e=document.getElementById(e);
                e.innerHTML=this.HTML_Text(e.innerHTML);
            }*/
            //结束
            e = this.checkReplace(ll);
            //李占增加移入工具条获取编辑框节点修改
            /*源码为
                var panelElm = new bkElement('DIV').setStyle({width : (parseInt(e.getStyle('width')) || e.clientWidth)+'px'}).appendBefore(e);
                this.setPanel(panelElm);
            */
            this.panelElm = new bkElement('DIV').setStyle({width : (e.getStyle("width") || e.clientWidth)}).appendBefore(e);
            this.setPanel(this.panelElm);
            /*结束*/
            return this.addInstance(e,o);
        }
    },

    checkReplace : function(e) {
        var r = nicEditors.findEditor(e);
        if(r) {
            r.removeInstance(e);
            r.removePanel();
        }
        return e;
    },

    addInstance : function(e,o) {
        e = this.checkReplace($BK(e));
        if( e.contentEditable || !!window.opera ) {
            var newInstance = new nicEditorInstance(e,o,this);
        } else {
            var newInstance = new nicEditorIFrameInstance(e,o,this);
        }
        this.nicInstances.push(newInstance);
        return this;
    },
    
    removeInstance : function(e) {
        e = $BK(e);
        var instances = this.nicInstances;
        for(var i=0;i<instances.length;i++) {    
            if(instances[i].e == e) {
                instances[i].remove();
                this.nicInstances.splice(i,1);
            }
        }
    },

    removePanel : function(e) {
        if(this.nicPanel) {
            this.nicPanel.remove();
            this.nicPanel = null;
        }    
    },

    instanceById : function(e) {
        e = $BK(e);
        var instances = this.nicInstances;
        for(var i=0;i<instances.length;i++) {
            if(instances[i].e == e) {
                return instances[i];
            }
        }    
    },

    setPanel : function(e) {
        this.nicPanel = new nicEditorPanel($BK(e),this.options,this);
        this.fireEvent('panel',this.nicPanel);
        return this;
    },
    
    nicCommand : function(cmd,args) {    
        if(this.selectedInstance) {
            this.selectedInstance.nicCommand(cmd,args);
        }
    },
    
    getIcon : function(iconName,options) {
        var icon = this.options.iconList[iconName];
        var file = (options.iconFiles) ? options.iconFiles[iconName] : '';
        return {backgroundImage : "url('"+((icon) ? this.options.iconsPath : file)+"')", backgroundPosition : ((icon) ? ((icon-1)*-18) : 0)+'px 0px'};    
    },
        
    selectCheck : function(e,t) {
        var found = false;
        do{
            if(t.className && t.className.indexOf('nicEdit') != -1) {
                return false;
            }
        } while(t = t.parentNode);
        this.fireEvent('blur',this.selectedInstance,t);
        this.lastSelectedInstance = this.selectedInstance;
        this.selectedInstance = null;
        return false;
    },
    //李占增加安全模块
    Text_HTML:function (str)
    {
        var t=document.createElement("div");
        if(typeof(t.innerText)=='undefined')
        {
            t.textContent=str;
        }
        else
        {
            t.innerText = str;
        }
        return t.innerHTML
                .replace(/\"/ig, '&quot;')
                .replace(/\'/ig, '&#039;')
                .replace(/<BR>/ig, '');
    },
    
    HTML_Text:function (str)
    {
        var t=document.createElement("div");
        t.innerHTML=str;
        if(typeof(t.innerText)=='undefined')
        {
            return t.textContent;
        }
        else
        {
            return t.innerText;
        }
    },
    //结束
    setSize:function(obj, e)
    {
        var eParentNode;    //指定编辑区的编辑器

        //获取指定的编辑器
        if(e)
        {
            var instances = this.nicInstances;
            e = $BK(e);
            for(var i = 0;i<instances.length;i++) {    
                if(instances[i].e === e) {
                    e = instances[i].elm;
                    break;
                }
            }
        } else {
            e = this.elm;
        }

        //获取指定编辑器的工具条对象
        var ePanelElm = e.parentNode.previousSibling;
        do
        {
            if(ePanelElm.tagName === 'DIV')
            {
                eParentNode = e.parentNode;
                break;
            }
        } while(ePanelElm = ePanelElm.previousSibling);

        if(obj.width != null)
        {
            eParentNode.style.width = ePanelElm.style.width = obj.width;
            e.style.width = (eParentNode.clientWidth-10>0?eParentNode.clientWidth-10:0)+"px";
        }
        if(obj.height != null)
        {
            eParentNode.style.maxHeight = eParentNode.style.height = obj.height;
            eParentNode.style.maxHeight = eParentNode.style.height = (eParentNode.clientHeight-ePanelElm.offsetHeight>0?eParentNode.clientHeight-ePanelElm.offsetHeight:0)+"px";
            e.style.minHeight = e.style.height = (eParentNode.clientHeight-12>0 ? eParentNode.clientHeight-12:0) + "px";
            //e.style.height = eParentNode.clientHeight+"px";
        }
        e.style.overflow = "visible";
        eParentNode.style.overflowY = "scroll";
    }
});
nicEditor = nicEditor.extend(bkEvent);

 
var nicEditorInstance = bkClass.extend({
    isSelected : false,
    
    construct : function(e,options,nicEditor) {
        this.ne = nicEditor;
        this.elm = this.e = e;
        this.options = options || {};
        
        newX = e.getStyle("width")||e.clientWidth;
        newY = parseInt(e.getStyle('height')) || e.clientHeight;
        this.initialHeight = newY-8;
        
        var isTextarea = (e.nodeName.toLowerCase() == "textarea");
        if(isTextarea || this.options.hasPanel) {
            var ie7s = (bkLib.isMSIE && !((typeof document.body.style.maxHeight != "undefined") && document.compatMode == "CSS1Compat"))
            var s = {width: newX, border : '1px solid #ccc', borderTop : 0, overflowY : 'auto', overflowX: 'hidden' };
            s['maxHeight'] = (this.ne.options.maxHeight) ? this.ne.options.maxHeight+'px' : null;
            this.editorContain = new bkElement('DIV').setStyle(s).appendBefore(e);
            if(ie7s && this.ne.options.maxHeight)    //IE6 增加maxHeight属性
            {
                this.editorContain.style.setExpression('height', 'this.scrollHeight > ' +this.ne.options.maxHeight+ ' ? "' +this.ne.options.maxHeight+ 'px" : "auto"')
            }

            //添加编辑区的样式
            var cssText="#"+e.id+"_Editer { padding:5px; white-space:normal; word-break:break-all; }\
                    #"+e.id+"_Editer p{ margin:0px; padding:0px;}\
                    #"+e.id+"_Editer ul { list-style-type:disc; padding-left:40px; margin-left:2em;}\
                    #"+e.id+"_Editer ol { list-style-type:decimal; padding-left:0px; margin-left:2em;}\
                    #"+e.id+"_Editer ol li { list-style:decimal outside none;}\
                    #"+e.id+"_Editer ul li { list-style:disc outside none;}";
            var t = new bkElement('style');
            t.setAttribute('type', 'text/css');
            this.editorContain.appendChild(t);
            try{
                bkLib.isMSIE?t.styleSheet.cssText=cssText:t.appendChild(document.createTextNode(cssText));//火狐下可以 this.editorContain.innerHTML="<style type='text/css'> p{ text-indent:2em;}</style>";
            } catch (temp) {}
            //结束
            
            e.style.display = 'block';
            var editorElm = new bkElement('DIV').setStyle({width : e.clientWidth-10, margin: '1px 0px', minHeight : newY-15+'px'}).addClass('main').appendTo(this.editorContain);//,border:'1px solid #Fcc'
            editorElm.id=e.id+"_Editer";
            if(editorElm.offsetHeight>this.editorContain.clientHeight&&bkLib.isMSIE)
            {
                editorElm.setStyle({width : editorElm.clientWidth-18});
            }
            else if(bkLib.isMSIE)
            {
                editorElm.setStyle({width : e.clientWidth});
            }
            e.setStyle({display : 'none'});
            //alert(newY);
                
            editorElm.innerHTML = e.innerHTML;
            if(isTextarea) {
                editorElm.setContent(e.value);
                this.copyElm = e;
                var f = e.parentTag('FORM');
                if(f) { bkLib.addEvent( f, 'submit', this.saveContent.closure(this)); }
            }
            editorElm.setStyle((ie7s) ? {height : newY+'px'} : {overflow: 'hidden'});
            
            this.elm = editorElm;
        }
        this.ne.addEvent('blur',this.blur.closure(this));

        this.init();
        this.blur();
    },
    
    init : function() {
        this.ne.elm=this.elm;
        this.elm.setAttribute('contentEditable','true');
        //李占增加移入工具条获取编辑框节点
        this.ne.panelElm.addEvent('mouseover',this.focus.closure(this));
        this.ne.panelElm.addEvent('mouseover',this.selected.closure(this));
        //结束
        //李占去除默认初始内容
        if(this.getContent().replace(/(^\s*)|(\s*$)/g, "")=="") {
            this.setContent('<p>&nbsp;</p>');
        }
        //答案框数量初始化
        var imgArr=this.elm.getElementsByTagName('img');    //编辑区中所有的IMG标签
        for(var i=0,imgLen=imgArr.length; i<imgLen; i++)
        {
            if(imgArr[i].getAttribute("AnswerType")=='textbox'||imgArr[i].getAttribute("AnswerType")=='select')
            {
                if(parseInt(imgArr[i].getAttribute("Num"))>this.ne.CustomConfig["AnswerSelect"]["AnswerNum"])
                {
                    this.ne.CustomConfig["AnswerSelect"]["AnswerNum"]=parseInt(imgArr[i].getAttribute("Num"));
                }
            }
        }
        //结束
        //李占代码可以优化
        this.instanceDoc = document.defaultView;
        this.elm.addEvent('mouseover',this.focus.closure(this)).addEvent('mouseover',this.selected.closure(this)).addEvent('click',this.MouseClick.closure(this)).addEvent('keypress',this.keyDown.closureListener(this)).addEvent('paste',this.keyDown.closureListener(this)).addEvent('blur',this.blur.closure(this)).addEvent('keyup',this.selected.closure(this));
        //结束
        //李占插入图片域
        this.ne.elm.addEvent('mouseup',function()
                    {
                        if(window.CustomConfig["ImageArea"]["mousedownObj"]!=null)
                        {
                            var kk=window.CustomConfig["ImageArea"]["mousedownObj"];
                            var lk=kk.getElementsByTagName("div")[0];
                            if(lk)
                            {
                                lk.style.width=kk.offsetWidth;
                                lk.style.height=kk.offsetHeight;
                            }
                            //window.CustomConfig["ImageArea"]["mousedownObj"]=null;
                        }
                    });
        //结束
        this.ne.fireEvent('add',this);
    },
    
    remove : function() {
        this.saveContent();
        if(this.copyElm || this.options.hasPanel) {
            this.editorContain.remove();
            this.e.setStyle({'display' : 'block'});
            this.ne.removePanel();
        }
        this.disable();
        this.ne.fireEvent('remove',this);
    },
    
    disable : function() {
        this.elm.setAttribute('contentEditable','false');
    },
    
    getSel : function() {
        return (window.getSelection) ? window.getSelection() : document.selection;    
    },
    
    getRng : function() {
        var s = this.getSel();
        if(!s) { return null; }
        return (s.rangeCount > 0) ? s.getRangeAt(0) : s.createRange();
    },
    
    selRng : function(rng,s) {
        if(window.getSelection) {
            s.removeAllRanges();
            s.addRange(rng);
        } else {
            rng.select();
        }
    },
    
    selElm : function() {
        var r = this.getRng();
        if(r.startContainer) {
            var contain = r.startContainer;
            if(r.cloneContents().childNodes.length == 1) {
                for(var i=0;i<contain.childNodes.length;i++) {
                    var rng = contain.childNodes[i].ownerDocument.createRange();
                    rng.selectNode(contain.childNodes[i]);                    
                    if(r.compareBoundaryPoints(Range.START_TO_START,rng) != 1 && 
                        r.compareBoundaryPoints(Range.END_TO_END,rng) != -1) {
                        return $BK(contain.childNodes[i]);
                    }
                }
            }
            return $BK(contain);
        } else {
            return $BK((this.getSel().type == "Control") ? r.item(0) : r.parentElement());
        }
    },
    
    saveRng : function() {
        this.savedRange = this.getRng();
        this.savedSel = this.getSel();
    },
    
    restoreRng : function() {
        if(this.savedRange) {
            this.selRng(this.savedRange,this.savedSel);
        }
    },
    
    MouseClick:function(e,t)
    {
        this.ne.fireEvent('click',this.ne.selectedInstance,t);
    },
    
    keyDown : function(e,t) {
        if(e.ctrlKey) {
            this.ne.fireEvent('key',this,e);
        }
        /*for(var mm in e)
        {
            alert(e[mm]);
        }*/
            //alert(e.keyCode);
        //alert(e.ctrlKey+"  "+e.which);
        //alert(e["clipboardData"].getData("Text"))    google
        //alert(clipboardData.getData("Text"))        IE
        
        this.ne.fireEvent('click',this.ne.selectedInstance,t);
        try
        {
            var tdiv=new bkElement('div');
            bkLib.isMSIE?tdiv.innerHTML=this.ne.selectedInstance.getRng().htmlText:tdiv.appendChild(this.ne.selectedInstance.getRng().cloneContents());
            if(this.elm.innerHTML.search(/^(<br>)+$/i)>-1||this.elm.innerHTML=='')
            {
                this.elm.innerHTML="<p>&nbsp;</p>";
                this.elm.blur();
                this.elm.focus();
            }
            if(this.elm.innerHTML.search(/^<p>&nbsp;<\/p>$/i)>-1||(this.elm.innerHTML==tdiv.innerHTML&&e.keyCode!=13&&e.keyCode!=8&&e.keyCode!=46&&!(e.ctrlKey&&e.which==120)))//!e.shiftKey&&e.keyCode==13&&    ||(this.elm.innerHTML==tdiv.innerHTML)
            {
                //var t = new bkElement('p');
                //this.ne.selectedInstance.getRng().surroundContents(t);
                //this.elm.innerHTML="<p>&nbsp;</p>";
                if(bkLib.isMSIE)
                {
                    this.ne.selectedInstance.getRng().findText("&nbsp;");
                    this.ne.selectedInstance.getRng().select();
                }
                else if(bkLib.isFirefox)
                {
                    this.elm.innerHTML="<p>&nbsp;</p>";
                    var t=this.elm.getElementsByTagName("p")[0];
                    this.ne.selectedInstance.getRng().selectNodeContents(t);
                }
                //e.returnValue=false;
                //e.preventDefault();
            }
            else if(!bkLib.isMSIE&&e.keyCode==13&&!e.shiftKey&&this.elm.innerHTML!=tdiv.innerHTML)
            {
                var t = new bkElement('p');
                this.ne.selectedInstance.getRng().insertNode(t);//surroundContents
                if(t.parentNode!=this.elm)
                {
                    t.parentNode.removeChild(t);
                }
                else
                {
                    this.ne.selectedInstance.getRng().selectNodeContents(t);
                }
            }
        }
        catch(m)
        {
            bkLib.isMSIE?e.returnValue=false:e.preventDefault();
        }
    },
    
    selected : function(e,t) {
        if(!t) {t = this.selElm()}
        if(!e.ctrlKey) {
            var selInstance = this.ne.selectedInstance;
            if(selInstance != this) {
                if(selInstance) {
                    this.ne.fireEvent('blur',selInstance,t);
                }
                this.ne.selectedInstance = this;    
                this.ne.fireEvent('focus',selInstance,t);
            }
            this.ne.fireEvent('selected',selInstance,t);
            this.isFocused = true;
            this.elm.addClass('selected');
        }
        return false;
    },
    
    blur : function() {
        //alert(1);
        this.isFocused = false;
        this.elm.removeClass('selected');
        //李占内容处理
        this.e.value = this.ne.HTML_Text(this.e.innerHTML = this.ne.Text_HTML(    //转换成html字符串
            this.contentProcessing(    //转义内容,符合oTring规范
                this.cleanWord(    //过滤word
                    this.getContent()    //获得源码
                )
            )
        ));
        //结束
    },

    //李占内容处理
    contentProcessing:function(content,rootObj)
    {
        var oP = document.createElement("div");
        oP.innerHTML=content;//.replace(/\r/g, "").replace(/\n/g, "");
        if(rootObj==null)
        {
            rootObj=oP;
        }
        var temp_obj_arr=oP.childNodes;
        for(var i=temp_obj_arr.length-1;i>=0;i--)
        {
            if(temp_obj_arr[i].getAttribute)
            {
                if( temp_obj_arr[i].tagName === 'SCRIPT' )
                {
                    oP.removeChild(temp_obj_arr[i]);
                    continue;
                }
                if(temp_obj_arr[i].childNodes.length>0)
                {
                    if(temp_obj_arr[i].tagName!="TABLE")
                    {
                        try
                        {
                            temp_obj_arr[i].innerHTML=this.contentProcessing(temp_obj_arr[i].innerHTML,rootObj);
                        }
                        catch(e){}
                    }
                    else
                    {
                        var rows=temp_obj_arr[i].rows;
                        for(var rows_i=0;rows_i<rows.length;rows_i++)
                        {
                            var cells=temp_obj_arr[i].rows[rows_i].cells;
                            for(var cells_i=0;cells_i<cells.length;cells_i++)
                            {
                                if(cells[cells_i].childNodes.length)
                                {
                                    cells[cells_i].innerHTML=this.contentProcessing(cells[cells_i].innerHTML,rootObj);
                                }
                            }
                        }
                    }
                }
                if(temp_obj_arr[i].getAttribute("AnswerType"))
                {
                    //alert(temp_obj_arr[i].getAttribute("AnswerType"));
                    if(temp_obj_arr[i].getAttribute("eval"))
                    {
                        var evalTemp=temp_obj_arr[i].getAttribute("eval").replace(/\bthis\b/g,"temp_obj_arr[i]").replace(/\bthisObj\b/g,"this").replace(/\bthisOpObj\b/g,"oP").replace(/\bthisRootObj\b/g,"rootObj");
                        //eval("temp_obj_arr[i].eval=function(){"+temp_obj_arr[i].getAttribute("eval")+"}");
                        //temp_obj_arr[i].eval();
                        if(typeof(temp_obj_arr[i])=="object"||temp_obj_arr[i].tagName=="OBJECT"||temp_obj_arr[i].tagName=="EMBED")
                        {
                            try
                            {
                                temp_obj_arr[i].removeAttribute("eval");
                                temp_obj_arr[i].removeAttribute("onmousedown");
                                temp_obj_arr[i].removeAttribute("AnswerType");
                            }
                            catch(e){alert(e)}
                        }
                        eval(evalTemp);
                    }
                    else
                    {
                        temp_obj_arr[i].outerHTML="["+temp_obj_arr[i].getAttribute("AnswerType")+"]"+temp_obj_arr[i].getAttribute("Num")+"[/"+temp_obj_arr[i].getAttribute("AnswerType")+"]";
                    }
                }
            }
        }
        return oP.innerHTML;
    },
    //结束

    //过滤word标签
    cleanWord : function(html)
    {
        html = html.replace(/<o:p>\s*<\/o:p>/g, '')
                   .replace(/<o:p>[\s\S]*?<\/o:p>/g, '&nbsp;');

        // 删除 mso-xxx 样式
        html = html.replace( /\s*mso-[^:]+:[^;"]+;?/gi, '' );

        // 删除边距样式
        html = html.replace( /\s*MARGIN: 0cm 0cm 0pt\s*;/gi, '' )
                   .replace( /\s*MARGIN: 0cm 0cm 0pt\s*"/gi, "\"" )
                   .replace( /\s*TEXT-INDENT: 0cm\s*;/gi, '' )
                   .replace( /\s*TEXT-INDENT: 0cm\s*"/gi, "\"" )
                   //.replace( /\s*TEXT-ALIGN: [^\s;]+;?"/gi, "\"" )
                   .replace( /\s*PAGE-BREAK-BEFORE: [^\s;]+;?"/gi, "\"" )
                   .replace( /\s*FONT-VARIANT: [^\s;]+;?"/gi, "\"" )
                   .replace( /\s*tab-stops:[^;"]*;?/gi, '' )
                   .replace( /\s*tab-stops:[^"]*/gi, '' );

        // 删除 Class 属性
        html = html.replace(/<(\w[^>]*) class=([^ |>]*)([^>]*)/gi, "<$1$3");

        // 删除内置样式及link等
        html = html.replace( /<STYLE[^>]*>[\s\S]*?<\/STYLE[^>]*>/gi, '' )
                   .replace( /<(?:META|LINK)[^>]*>\s*/gi, '' );

        // 删除空样式及空span标签
        html = html.replace( /\s*style="\s*"/gi, '' )
                   .replace( /<SPAN\s*[^>]*>\s*&nbsp;\s*<\/SPAN>/gi, '&nbsp;' )
                   .replace( /<SPAN\s*[^>]*><\/SPAN>/gi, '' );

        // 删除 Lang 属性
        html = html.replace(/<(\w[^>]*) lang=([^ |>]*)([^>]*)/gi, "<$1$3")
                   .replace( /<SPAN\s*>([\s\S]*?)<\/SPAN>/gi, '$1' )
                   .replace( /<FONT\s*>([\s\S]*?)<\/FONT>/gi, '$1' );

        // 删除 XML 所有节点
        html = html.replace(/<\\?\?xml[^>]*>/gi, '' );

        // 删除 w: 开始的标签内容
        html = html.replace( /<w:[^>]*>[\s\S]*?<\/w:[^>]*>/gi, '' );

        // 删除 XML 命名空间 : <o:p><\/o:p>
        html = html.replace(/<\/?\w+:[^>]*>/gi, '' );

        // 删除 comments [SF BUG-1481861].
        html = html.replace(/<\!--[\s\S]*?-->/g, '' )
                   .replace( /<(U|I|STRIKE)>&nbsp;<\/\1>/g, '&nbsp;' )
                   .replace( /<H\d>\s*<\/H\d>/gi, '' );

        // 删除 "display:none" 属性
        html = html.replace( /<(\w+)[^>]*\sstyle="[^"]*DISPLAY\s?:\s?none[\s\S]*?<\/\1>/ig, '' );

        // 删除 language 属性
        html = html.replace( /<(\w[^>]*) language=([^ |>]*)([^>]*)/gi, "<$1$3");

        // 删除 onmouseover 和 onmouseout 事件
        html = html.replace( /<(\w[^>]*) onmouseover="([^\"]*)"([^>]*)/gi, "<$1$3")
                   .replace( /<(\w[^>]*) onmouseout="([^\"]*)"([^>]*)/gi, "<$1$3");

        // 将 <H\d style="margin-top:0px;margin-bottom:0px"> 标签转换成 <H\d>
        html = html.replace( /<H(\d)([^>]*)>/gi, '<h$1>' );

        // 有时 H 标签中会插入 fount 标签
        html = html.replace( /<(H\d)><FONT[^>]*>([\s\S]*?)<\/FONT><\/\1>/gi, '<$1>$2<\/$1>' )
                   .replace( /<(H\d)><EM>([\s\S]*?)<\/EM><\/\1>/gi, '<$1>$2<\/$1>' );

        return html;
    },

    //李占增加移入工具条获取编辑框节点
    focus : function() {
        /*var tme_arr=document.getElementsByTagName("div");
        for(var i=0;i<tme_arr.length;i++)
        {
            if(tme_arr[i].className==" nicEdit-pane")
            {
                return;
            }
        }*/
        if(document.activeElement!=this.elm)
        {
            //alert(this.editorContain.scrollTop)
            var scrollTop=this.editorContain.scrollTop;
            this.elm.focus();
            this.editorContain.scrollTop=scrollTop;
        }
    },
    //结束
    
    saveContent : function() {
        //李占,删除提交时将文本导出
        /*
        if(this.copyElm || this.options.hasPanel) {
            this.ne.fireEvent('save',this);
            (this.copyElm) ? this.copyElm.value = this.getContent() : this.e.innerHTML = this.getContent();
        }
        */
        //结束
    },
    
    getElm : function() {
        return this.elm;
    },
    
    getContent : function() {
        this.content = this.getElm().innerHTML;
        this.ne.fireEvent('get',this);
        return this.content;
    },
    
    setContent : function(e) {
        this.content = e;
        this.ne.fireEvent('set',this);
        this.elm.innerHTML = this.content;    
    },
    
    nicCommand : function(cmd,args) {
        document.execCommand(cmd,false,args);
    }        
});

var nicEditorIFrameInstance = nicEditorInstance.extend({
    savedStyles : [],
    
    init : function() {    
        var c = this.elm.innerHTML.replace(/^\s+|\s+$/g, '');
        this.elm.innerHTML = '';
        (!c) ? c = "<br />" : c;
        this.initialContent = c;
        
        this.elmFrame = new bkElement('iframe').setAttributes({'src' : 'javascript:;', 'frameBorder' : 0, 'allowTransparency' : 'true', 'scrolling' : 'no'}).setStyle({height: '100px', width: '100%'}).addClass('frame').appendTo(this.elm);

        if(this.copyElm) { this.elmFrame.setStyle({width : (this.elm.offsetWidth-4)+'px'}); }
        
        var styleList = ['font-size','font-family','font-weight','color'];
        for(itm in styleList) {
            this.savedStyles[bkLib.camelize(itm)] = this.elm.getStyle(itm);
        }
         
        setTimeout(this.initFrame.closure(this),50);
    },
    
    disable : function() {
        this.elm.innerHTML = this.getContent();
    },
    
    initFrame : function() {
        var fd = $BK(this.elmFrame.contentWindow.document);
        fd.designMode = "on";        
        fd.open();
        var css = this.ne.options.externalCSS;
        fd.write('<html><head>'+((css) ? '<link href="'+css+'" rel="stylesheet" type="text/css" />' : '')+'</head><body id="nicEditContent" style="margin: 0 !important; background-color: transparent !important;">'+this.initialContent+'</body></html>');
        fd.close();
        this.frameDoc = fd;

        this.frameWin = $BK(this.elmFrame.contentWindow);
        this.frameContent = $BK(this.frameWin.document.body).setStyle(this.savedStyles);
        this.instanceDoc = this.frameWin.document.defaultView;
        
        this.heightUpdate();
        this.frameDoc.addEvent('mousedown', this.selected.closureListener(this)).addEvent('keyup',this.heightUpdate.closureListener(this)).addEvent('keydown',this.keyDown.closureListener(this)).addEvent('keyup',this.selected.closure(this));
        this.ne.fireEvent('add',this);
    },
    
    getElm : function() {
        return this.frameContent;
    },
    
    setContent : function(c) {
        this.content = c;
        this.ne.fireEvent('set',this);
        this.frameContent.innerHTML = this.content;    
        this.heightUpdate();
    },
    
    getSel : function() {
        return (this.frameWin) ? this.frameWin.getSelection() : this.frameDoc.selection;
    },
    
    heightUpdate : function() {    
        this.elmFrame.style.height = Math.max(this.frameContent.offsetHeight,this.initialHeight)+'px';
    },
    
    nicCommand : function(cmd,args) {
        this.frameDoc.execCommand(cmd,false,args);
        setTimeout(this.heightUpdate.closure(this),100);
    }

    
});
var nicEditorPanel = bkClass.extend({
    construct : function(e,options,nicEditor) {
        this.elm = e;
        this.options = options;
        this.ne = nicEditor;
        this.panelButtons = new Array();
        this.buttonList = bkExtend([],this.ne.options.buttonList);
        
        this.panelContain = new bkElement('DIV').setStyle({overflow : 'hidden', width : '100%', border : '1px solid #cccccc', backgroundColor : '#efefef'}).addClass('panelContain');
        this.panelElm = new bkElement('DIV').setStyle({margin : '2px', marginTop : '0px', zoom : 1, overflow : 'hidden'}).addClass('panel').appendTo(this.panelContain);
        this.panelContain.appendTo(e);

        var opt = this.ne.options;
        var buttons = opt.buttons;
        for(button in buttons) {
                this.addButton(button,opt,true);
        }
        this.reorder();
        e.noSelect();
    },
    
    addButton : function(buttonName,options,noOrder) {
        var button = options.buttons[buttonName];
        var type = (button['type']) ? eval('(typeof('+button['type']+') == "undefined") ? null : '+button['type']+';') : nicEditorButton;
        var hasButton = bkLib.inArray(this.buttonList,buttonName);
        if(type && (hasButton || this.ne.options.fullPanel)) {
            this.panelButtons.push(new type(this.panelElm,buttonName,options,this.ne));
            if(!hasButton) {    
                this.buttonList.push(buttonName);
            }
        }
    },
    
    findButton : function(itm) {
        for(var i=0;i<this.panelButtons.length;i++) {
            if(this.panelButtons[i].name == itm)
                return this.panelButtons[i];
        }    
    },
    
    reorder : function() {
        var bl = this.buttonList;
        for(var i=0;i<bl.length;i++) {
            var button = this.findButton(bl[i]);
            if(button) {
                this.panelElm.appendChild(button.margin);
            }
        }    
    },
    
    remove : function() {
        this.elm.remove();
    }
});
var nicEditorButton = bkClass.extend({
    
    construct : function(e,buttonName,options,nicEditor) {
        this.options = options.buttons[buttonName];
        this.name = buttonName;
        this.ne = nicEditor;
        this.elm = e;

        this.margin = new bkElement('DIV').setStyle({'float' : 'left', marginTop : '2px'}).appendTo(e);
        this.contain = new bkElement('DIV').setStyle({width : '20px', height : '20px'}).addClass('buttonContain').appendTo(this.margin);
        this.border = new bkElement('DIV').setStyle({backgroundColor : '#efefef', border : '1px solid #efefef'}).appendTo(this.contain);
        this.button = new bkElement('DIV').setStyle({width : '18px', height : '18px', overflow : 'hidden', zoom : 1, cursor : 'pointer'}).addClass('button').setStyle(this.ne.getIcon(buttonName,options)).appendTo(this.border);
        this.button.addEvent('mouseover', this.hoverOn.closure(this)).addEvent('mouseout',this.hoverOff.closure(this)).addEvent('mousedown',this.mouseClick.closure(this)).noSelect();
        
        if(!window.opera) {
            this.button.onmousedown = this.button.onclick = bkLib.cancelEvent;
        }
        
        //nicEditor.addEvent('selected', this.enable.closure(this)).addEvent('blur', this.disable.closure(this)).addEvent('key',this.key.closure(this));
        nicEditor.addEvent('selected', this.enable.closure(this)).addEvent('key',this.key.closure(this));
        
        this.disable();
        this.init();
    },
    
    init : function() {  },
    
    hide : function() {
        this.contain.setStyle({display : 'none'});
    },
    
    updateState : function() {
        if(this.isDisabled) { this.setBg(); }
        else if(this.isHover) { this.setBg('hover'); }
        else if(this.isActive) { this.setBg('active'); }
        else { this.setBg(); }
    },
    
    setBg : function(state) {
        switch(state) {
            case 'hover':
                var stateStyle = {border : '1px solid #666', backgroundColor : '#ddd'};
                break;
            case 'active':
                var stateStyle = {border : '1px solid #666', backgroundColor : '#ccc'};
                break;
            default:
                var stateStyle = {border : '1px solid #efefef', backgroundColor : '#efefef'};    
        }
        this.border.setStyle(stateStyle).addClass('button-'+state);
    },
    
    checkNodes : function(e) {
        var elm = e;    
        do {
            if(!elm.getAttribute||(elm.getAttribute&&elm.getAttribute("AnswerType")==this.options.AnswerType))//李占为增加答案标签AnswerType
            {
                if(this.options.tags && bkLib.inArray(this.options.tags,elm.nodeName)) {
                    this.activate();
                    return true;
                }
            }
        } while(elm = elm.parentNode && elm.className != "nicEdit");
        elm = $BK(e);
        while(elm.nodeType == 3) {
            elm = $BK(elm.parentNode);
        }
        if(this.options.css) {
            for(itm in this.options.css) {
                if(elm.getStyle(itm,this.ne.selectedInstance.instanceDoc) == this.options.css[itm]) {
                    this.activate();
                    return true;
                }
            }
        }
        this.deactivate();
        return false;
    },
    
    activate : function() {
        if(!this.isDisabled) {
            this.isActive = true;
            this.updateState();    
            this.ne.fireEvent('buttonActivate',this);
        }
    },
    
    deactivate : function() {
        this.isActive = false;
        this.updateState();    
        if(!this.isDisabled) {
            this.ne.fireEvent('buttonDeactivate',this);
        }
    },
    
    enable : function(ins,t) {
        this.isDisabled = false;
        this.contain.setStyle({'opacity' : 1}).addClass('buttonEnabled');
        this.updateState();
        this.checkNodes(t);
    },
    
    disable : function(ins,t) {        
        this.isDisabled = false;
        this.contain.setStyle({'opacity' : 1}).removeClass('buttonEnabled');
        this.updateState();
    },
    
    toggleActive : function() {
        (this.isActive) ? this.deactivate() : this.activate();    
    },
    
    hoverOn : function() {
        if(!this.isDisabled) {
            this.isHover = true;
            this.updateState();
            this.ne.fireEvent("buttonOver",this);
        }
    }, 
    
    hoverOff : function() {
        this.isHover = false;
        this.updateState();
        this.ne.fireEvent("buttonOut",this);
    },
    
    mouseClick : function() {
        if(this.options.command) {
            this.ne.nicCommand(this.options.command,this.options.commandArgs);
            if(!this.options.noActive) {
                this.toggleActive();
            }
        }
        this.ne.fireEvent("buttonClick",this);
    },
    
    key : function(nicInstance,e) {
        if(this.options.key && e.ctrlKey && String.fromCharCode(e.keyCode || e.charCode).toLowerCase() == this.options.key) {
            this.mouseClick();
            if(e.preventDefault) e.preventDefault();
        }
    }
    
});

 
var nicPlugin = bkClass.extend({
    
    construct : function(nicEditor,options) {
        this.options = options;
        this.ne = nicEditor;
        //李占增加按钮自定义参数
        ObjAllExtend(this.ne.CustomConfig,options.CustomConfig);
        //结束
        this.ne.addEvent('panel',this.loadPanel.closure(this));
        
        this.init();
    },

    loadPanel : function(np) {
        var buttons = this.options.buttons;
        for(var button in buttons) {
            np.addButton(button,this.options);
        }
        np.reorder();
    },

    init : function() {  }
});



 
 /* START CONFIG */
var nicPaneOptions = { };
/* END CONFIG */

var nicEditorPane = bkClass.extend({
    construct : function(elm,nicEditor,options,openButton) {
        this.ne = nicEditor;
        this.elm = elm;
        this.pos = elm.pos();
        
        this.contain = new bkElement('div').setStyle({zIndex : '99999', overflow : 'hidden', position : 'absolute', left : this.pos[0]+'px', top : this.pos[1]+'px'})
        this.pane = new bkElement('div').setStyle({fontSize : '12px', border : '1px solid #ccc', 'overflow': 'hidden', padding : '4px', textAlign: 'left', backgroundColor : '#ffffc9'}).addClass('pane').setStyle(options).appendTo(this.contain);
        
        if(openButton && !openButton.options.noClose) {
            this.close = new bkElement('div').setStyle({'float' : 'right', height: '16px', width : '16px', cursor : 'pointer'}).setStyle(this.ne.getIcon('close',nicPaneOptions)).addEvent('mousedown',openButton.removePane.closure(this)).appendTo(this.pane);
        }
        
        this.contain.noSelect().appendTo(document.body);
        
        this.position();
        this.init();    
    },
    
    init : function() { },
    
    position : function() {
        if(this.ne.nicPanel) {
            var panelElm = this.ne.nicPanel.elm;    
            var panelPos = panelElm.pos();
            var newLeft = panelPos[0]+parseInt(panelElm.clientWidth) - (parseInt(this.pane.clientWidth) + 8);
            if(newLeft < this.pos[0]) {
                this.contain.setStyle({left : newLeft+'px'});
            }
        }
    },
    
    toggle : function() {
        this.isVisible = !this.isVisible;
        this.contain.setStyle({display : ((this.isVisible) ? 'block' : 'none')});
    },
    
    remove : function() {
        if(this.contain) {
            this.contain.remove();
            this.contain = null;
        }
    },
    
    append : function(c) {
        c.appendTo(this.pane);
    },
    
    setContent : function(c) {
        this.pane.setContent(c);
    }
    
});


 
var nicEditorAdvancedButton = nicEditorButton.extend({
    
    init : function() {
        this.ne.addEvent('click',this.removePane.closure(this)).addEvent('blur',this.removePane.closure(this));    
    },
    
    mouseClick : function() {
        if(!this.isDisabled&&this.ne.selectedInstance) {//李占为支持去除焦点锁定按钮,添加"&&this.ne.selectedInstance"
            if(this.pane && this.pane.pane) {
                this.removePane();
            } else {
                this.pane = new nicEditorPane(this.contain,this.ne,{width : (this.width || '270px'), backgroundColor : '#fff'},this);
                this.addPane();
                this.ne.selectedInstance.saveRng();
            }
        }
    },
    
    addForm : function(f,elm) {
        this.form = new bkElement('form').addEvent('submit',this.submit.closureListener(this));
        this.pane.append(this.form);
        this.inputs = {};
        
        for(itm in f) {
            var field = f[itm];
            var val = '';
            //李占修改顶层样式
            var temp_obj={overflow : 'hidden', clear : 'both'};
            bkExtend(temp_obj,f[itm].p_css);
            
            if(elm) {
                val = elm.getAttribute(itm);
            }
            if(!val) {
                val = field['value'] || '';
            }
            var type = f[itm].type;
            
            if(type == 'title') {
                new bkElement('div').setContent(field.txt).setStyle({fontSize : '14px', fontWeight: 'bold', padding : '0px', margin : '2px 0'}).setStyle(temp_obj).appendTo(this.form);
            } else {
                //结束
                var contain = new bkElement('div').setStyle(temp_obj).appendTo(this.form);
                if(field.txt) {
                    //李占修改label样式
                    temp_obj={margin : '2px 4px', fontSize : '13px', width: '50px', lineHeight : '20px', textAlign : 'right', 'float' : 'left'};
                    bkExtend(temp_obj,f[itm].s_css);
                    //结束
                    new bkElement('label').setAttributes({'for' : itm}).setContent(field.txt).setStyle(temp_obj).appendTo(contain);
                }
                
                switch(type) {
                    case 'text':
                        this.inputs[itm] = new bkElement('input').setAttributes({id : itm, 'value' : val, 'type' : 'text'}).setStyle({margin : '2px 0', fontSize : '13px', 'float' : 'left', height : '20px', border : '1px solid #ccc', overflow : 'hidden'}).setStyle(field.style).appendTo(contain);
                        break;
                    case 'select':
                        this.inputs[itm] = new bkElement('select').setAttributes({id : itm}).setStyle({border : '1px solid #ccc', 'float' : 'left', margin : '2px 0'}).appendTo(contain);
                        for(opt in field.options) {
                            var o = new bkElement('option').setAttributes({value : opt, selected : (opt == val) ? 'selected' : ''}).setContent(field.options[opt]).appendTo(this.inputs[itm]);
                        }
                        break;
                    case 'checkbox':
                        this.inputs[itm] = new bkElement('input').setAttributes({id : itm, 'value' : val, 'type' : 'checkbox', 'checked' : f[itm].checked}).setStyle({border : '0px', overflow : 'hidden'}).setStyle(field.style).appendTo(contain);
                        //this.inputs[itm].setAttribute('checked','checked');
                        break;
                    case 'content':
                        this.inputs[itm] = new bkElement('textarea').setAttributes({id : itm}).setStyle({border : '1px solid #ccc', 'float' : 'left'}).setStyle(field.style).appendTo(contain);
                        this.inputs[itm].value = val;
                }    
            }
        }
        new bkElement('input').setAttributes({'type' : 'submit'}).setStyle({backgroundColor : '#efefef',border : '1px solid #ccc', margin : '3px 0', 'float' : 'left', 'clear' : 'both'}).appendTo(this.form);
        this.form.onsubmit = bkLib.cancelEvent;    
    },
    
    submit : function() { },
    
    findElm : function(tag,attr,val) {
        var list = this.ne.selectedInstance.getElm().getElementsByTagName(tag);
        for(var i=0;i<list.length;i++) {
            if(list[i].getAttribute(attr) == val) {
                return $BK(list[i]);
            }
        }
    },
    
    removePane : function() {
        if(this.pane) {
            this.pane.remove();
            this.pane = null;
            this.ne.selectedInstance.restoreRng();
        }    
    }    
});


var nicButtonTips = bkClass.extend({
    construct : function(nicEditor) {
        this.ne = nicEditor;
        nicEditor.addEvent('buttonOver',this.show.closure(this)).addEvent('buttonOut',this.hide.closure(this));

    },
    
    show : function(button) {
        this.timer = setTimeout(this.create.closure(this,button),400);
    },
    
    create : function(button) {
        this.timer = null;
        if(!this.pane) {
            this.pane = new nicEditorPane(button.button,this.ne,{fontSize : '12px', marginTop : '5px'});
            this.pane.setContent(button.options.name);
        }        
    },
    
    hide : function(button) {
        if(this.timer) {
            clearTimeout(this.timer);
        }
        if(this.pane) {
            this.pane = this.pane.remove();
        }
    }
});
nicEditors.registerPlugin(nicButtonTips);


 
 /* START CONFIG */
var nicSelectOptions = {
    buttons : {
        'fontSize' : {name : __('字体大小'), type : 'nicEditorFontSizeSelect', command : 'fontsize'},
        'fontFamily' : {name : __('选择字体'), type : 'nicEditorFontFamilySelect', command : 'fontname'},
        'fontFormat' : {name : __('字体格式'), type : 'nicEditorFontFormatSelect', command : 'formatBlock'}
    }
};
/* END CONFIG */
var nicEditorSelect = bkClass.extend({
    
    construct : function(e,buttonName,options,nicEditor) {
        this.options = options.buttons[buttonName];
        this.elm = e;
        this.ne = nicEditor;
        this.name = buttonName;
        this.selOptions = new Array();
        
        this.margin = new bkElement('div').setStyle({'float' : 'left', margin : '2px 1px 0 1px'}).appendTo(this.elm);
        this.contain = new bkElement('div').setStyle({width: '90px', height : '20px', cursor : 'pointer', overflow: 'hidden'}).addClass('selectContain').addEvent('click',this.toggle.closure(this)).appendTo(this.margin);
        this.items = new bkElement('div').setStyle({overflow : 'hidden', zoom : 1, border: '1px solid #ccc', paddingLeft : '3px', backgroundColor : '#fff'}).appendTo(this.contain);
        this.control = new bkElement('div').setStyle({overflow : 'hidden', 'float' : 'right', height: '18px', width : '16px'}).addClass('selectControl').setStyle(this.ne.getIcon('arrow',options)).appendTo(this.items);
        this.txt = new bkElement('div').setStyle({overflow : 'hidden', 'float' : 'left', width : '66px', height : '14px', marginTop : '1px', fontFamily : 'sans-serif', textAlign : 'center', fontSize : '12px'}).addClass('selectTxt').appendTo(this.items);
        
        if(!window.opera) {
            this.contain.onmousedown = this.control.onmousedown = this.txt.onmousedown = bkLib.cancelEvent;
        }
        
        this.margin.noSelect();
        
        this.ne.addEvent('selected', this.enable.closure(this));//.addEvent('blur', this.disable.closure(this))
        
        this.disable();
        this.init();
    },
    
    disable : function() {
        this.isDisabled = true;
        this.close();
        this.contain.setStyle({opacity :1});
    },
    
    enable : function(t) {
        this.isDisabled = false;
        this.ne.elm.addEvent('click',this.close.closure(this)).addEvent('keypress',this.close.closureListener(this));
        //this.close();
        this.contain.setStyle({opacity : 1});
    },
    
    setDisplay : function(txt) {
        this.txt.setContent(txt);
    },
    
    toggle : function() {
        if(!this.isDisabled) {
            (this.pane) ? this.close() : this.open();
        }
    },
    
    open : function() {
        this.pane = new nicEditorPane(this.items,this.ne,{width : '88px', padding: '0px', borderTop : 0, borderLeft : '1px solid #ccc', borderRight : '1px solid #ccc', borderBottom : '0px', backgroundColor : '#fff'});
        
        for(var i=0;i<this.selOptions.length;i++) {
            var opt = this.selOptions[i];
            var itmContain = new bkElement('div').setStyle({overflow : 'hidden', borderBottom : '1px solid #ccc', width: '88px', textAlign : 'left', overflow : 'hidden', cursor : 'pointer'});
            var itm = new bkElement('div').setStyle({padding : '0px 4px'}).setContent(opt[1]).appendTo(itmContain).noSelect();
            itm.addEvent('click',this.update.closure(this,opt[0])).addEvent('mouseover',this.over.closure(this,itm)).addEvent('mouseout',this.out.closure(this,itm)).setAttributes('id',opt[0]);
            this.pane.append(itmContain);
            if(!window.opera) {
                itm.onmousedown = bkLib.cancelEvent;
            }
        }
    },
    
    close : function() {
        if(this.pane) {
            this.pane = this.pane.remove();
        }    
    },
    
    over : function(opt) {
        opt.setStyle({backgroundColor : '#ccc'});            
    },
    
    out : function(opt) {
        opt.setStyle({backgroundColor : '#fff'});
    },
    
    
    add : function(k,v) {
        this.selOptions.push(new Array(k,v));    
    },
    
    update : function(elm) {
        if(bkLib.isMSIE&&this.options.command=="formatBlock")
        {
            elm=/^<(.*)>$/.exec(elm)[1];
            var t="<"+elm+">"+this.ne.selectedInstance.getRng().htmlText+"</"+elm+">";
            this.ne.selectedInstance.getRng().pasteHTML(t);
        }
        else
        {
            this.ne.nicCommand(this.options.command,elm);
        }
        this.close();    
    }
});

var nicEditorFontSizeSelect = nicEditorSelect.extend({
    sel : {1 : '1号字', 2 : '2号字', 3 : '3号字', 4 : '4号字', 5 : '5号字', 6 : '6号字'},
    init : function() {
        this.setDisplay('字体大小');
        for(itm in this.sel) {
            this.add(itm,'<font size="'+itm+'">'+this.sel[itm]+'</font>');
        }        
    }
});

var nicEditorFontFamilySelect = nicEditorSelect.extend({
    sel : {'宋体':'宋体','楷体_gb2312':'楷体','隶书':'隶书','幼圆':'幼圆','黑体':'黑体','arial' : 'Arial','comic sans ms' : 'Comic Sans','courier new' : 'Courier New','georgia' : 'Georgia', 'helvetica' : 'Helvetica', 'impact' : 'Impact', 'times new roman' : 'Times', 'trebuchet ms' : 'Trebuchet', 'verdana' : 'Verdana'},
    init : function() {
        this.setDisplay('选择字体');
        for(itm in this.sel) {
            this.add(itm,'<font face="'+itm+'">'+this.sel[itm]+'</font>');
        }
    }
});

var nicEditorFontFormatSelect = nicEditorSelect.extend({
        sel : {'p' : '段落', 'pre' : '代码', 'h6' : '标题六', 'h5' : '标题五', 'h4' : '标题四', 'h3' : '标题三', 'h2' : '标题二', 'h1' : '标题一'},
        
    init : function() {
        this.setDisplay('字体格式');
        for(itm in this.sel) {
            var tag = itm.toUpperCase();
            this.add('<'+tag+'>','<'+itm+' style="padding: 0px; margin: 0px;">'+this.sel[itm]+'</'+tag+'>');
        }
    }
});

nicEditors.registerPlugin(nicPlugin,nicSelectOptions);



/* START CONFIG */
var nicLinkOptions = {
    buttons : {
        'link' : {name : '增加链接/附件', type : 'nicLinkButton', tags : ['A']},
        'unlink' : {name : '删除链接',  command : 'unlink', noActive : true}
    }
};
/* END CONFIG */

var nicLinkButton = nicEditorAdvancedButton.extend({
    width:'220px',
    addPane : function() {
        var roundStr=new Date().getTime();
        this.att = this.ne.selectedInstance.selElm().parentTag('A');
        var href = 'http://';
        //alert(href + '    ' + this.att.getAttribute('delurl'));
        if(this.att.getAttribute)    //确定是个链接
        {
            href = this.att.href;
            if(this.att.getAttribute('delurl'))    //如果存在删减路径
            {
                href = href.substr(href.indexOf('/', 7) + this.att.getAttribute('delurl').length);
            }
        }
        this.addForm({
        ''          : {
            type : 'title', 
            txt  : '添加链接/附件'
        },
        'title'     : {
            type  : 'text', 
            txt   : '标题', 
            value : 'Link',
            style : {width:'150px'}
        },
        'hrefInput' : {
            type  : 'text', 
            txt   : '地址', 
            value : href, 
            style : {width: '150px'}
        },
        'target'    : {
            type    : 'select', 
            txt     : '打开方式', 
            options : {'' : '默认', '_blank' : '新窗口'},
            style   : {width : '100px'},
            s_css   : {width:'60px'}, 
            p_css   : {'float' : 'left'}},
            'alt1'  : {
                type : 'title', 
                txt     : '<iframe id="oEditUploadify'+roundStr+'" frameborder="0" style="width:25px; height:19px; border:0px; float:right; margin-top:-2px;" src="'+ROOT_URL+oFileManagerMainDir+'/include/uploadify/iframeIndex.php"></iframe><font style="font-weight:normal; font-size:12px; cursor:pointer; margin-left:5px; float:right;" onclick="selectFile(\'hrefInput\',null,\''+this.ne.CustomConfig["oFileManager"]["browseDir"]["attachment"]+'\');">选择文件</font>',
                p_css   : {'float' : 'right', 'textAlign':'right','height':'15px', 'clear' : 'none'}
            }
        },this.att);

        document.getElementById('oEditUploadify'+roundStr).contentWindow.config={
            uploadifyId:'uploadify',
            onAllCompleteFun:function(fileUrl)
                        {
                            document.getElementById('hrefInput').value=fileUrl;
                        }
            ,swfConfig:{
                        folder : this.ne.CustomConfig["oFileManager"]["quickUploadDir"]["attachment"]
                    }
        };
    },
    submit : function(e) {
        var url = this.inputs['hrefInput'].value;
        var delUrl = '';    //记录删除路径
        if(url.replace(/(^\s*)|(\s*$)/g, "")==""){
            alert("请输入文件地址！");
            return false;
        }
        if(url.search(/^\w+:\/\//i))
        {
            delUrl = 'delUrl="' + window.ROOT_URL + '"';
            url = window.ROOT_URL + url;
        }
        var Num=/((?!\/).)*$/.exec(url)[0];
        this.removePane();

        if(!this.att) {
            var tmp = 'javascript:nicTemp();';
            this.ne.nicCommand("createlink",tmp);
            this.att = this.findElm('A','href',tmp);
        }
        if(this.att) {
            this.att.setAttributes({
                href : url,
                title : this.inputs['title'].value,
                target : this.inputs['target'].options[this.inputs['target'].selectedIndex].value
            });
            delUrl && this.att.setAttribute("delUrl", window.ROOT_URL);
        } else {
            if(!this.inputs['title'].value)    //标题为空不插入
            {
                return false;
            }
            if (bkLib.isMSIE && bkLib.version < 9)
            {
                var t = '<a href="' +url+ '" title="' +this.inputs['title'].value+ '" target="' +this.inputs['target'].options[this.inputs['target'].selectedIndex].value+ '" ' +delUrl+ '>' +this.inputs['title'].value+ '</a>';
                this.ne.selectedInstance.getRng().pasteHTML(t);
            } else {
                var t = new bkElement('a')
                            .setAttributes({
                                'href'   : url,
                                'title'  : this.inputs['title'].value,
                                'target' : this.inputs['target'].options[this.inputs['target'].selectedIndex].value
                            });
                delUrl && t.setAttribute("delUrl", window.ROOT_URL);
                t.innerHTML = this.inputs['title'].value;
                this.ne.selectedInstance.getRng().insertNode(t);
            }
        }
    }
});

nicEditors.registerPlugin(nicPlugin,nicLinkOptions);



/* START CONFIG */
var nicColorOptions = {
    buttons : {
        'forecolor' : {name : __('文本颜色'), type : 'nicEditorColorButton', noClose : true},
        'bgcolor' : {name : __('背景颜色'), type : 'nicEditorBgColorButton', noClose : true}
    }
};
/* END CONFIG */

var nicEditorColorButton = nicEditorAdvancedButton.extend({    
    addPane : function() {
            var colorList = {0 : '00',1 : '33',2 : '66',3 :'99',4 : 'CC',5 : 'FF'};
            var colorItems = new bkElement('DIV').setStyle({width: '270px'});
            
            for(var r in colorList) {
                for(var b in colorList) {
                    for(var g in colorList) {
                        var colorCode = '#'+colorList[r]+colorList[g]+colorList[b];
                        
                        var colorSquare = new bkElement('DIV').setStyle({'cursor' : 'pointer', 'height' : '15px', 'float' : 'left'}).appendTo(colorItems);
                        var colorBorder = new bkElement('DIV').setStyle({border: '2px solid '+colorCode}).appendTo(colorSquare);
                        var colorInner = new bkElement('DIV').setStyle({backgroundColor : colorCode, overflow : 'hidden', width : '11px', height : '11px'}).addEvent('click',this.colorSelect.closure(this,colorCode)).addEvent('mouseover',this.on.closure(this,colorBorder)).addEvent('mouseout',this.off.closure(this,colorBorder,colorCode)).appendTo(colorBorder);
                        
                        if(!window.opera) {
                            colorSquare.onmousedown = colorInner.onmousedown = bkLib.cancelEvent;
                        }

                    }    
                }    
            }
            this.pane.append(colorItems.noSelect());    
    },
    
    colorSelect : function(c) {
        this.ne.nicCommand('foreColor',c);
        this.removePane();
    },
    
    on : function(colorBorder) {
        colorBorder.setStyle({border : '2px solid #000'});
    },
    
    off : function(colorBorder,colorCode) {
        colorBorder.setStyle({border : '2px solid '+colorCode});        
    }
});

var nicEditorBgColorButton = nicEditorColorButton.extend({
    colorSelect : function(c) {
        if (bkLib.isMSIE)
        {
            this.ne.nicCommand('BackColor',c);
        }
        else
        {
            this.ne.nicCommand('hiliteColor',c);
        }
        this.removePane();
    }    
});

nicEditors.registerPlugin(nicPlugin,nicColorOptions);



/* START CONFIG */
var nicImageOptions = {
    buttons : {
        'image' : {name : '添加图片', type : 'nicImageButton', tags : ['IMG']}    
    }
    
};
/* END CONFIG */

function selectFile(id,selectExt,browseDir) {
         /*page = "?use=" + parameter;
    
        var top = (window.screen.availHeight-30-430)/2;   
          var left= (window.screen.availWidth-10-640)/2;
    
        myWin = window.open(ROOT_URL+'/include/filemanager/index.php'+page, 'tdFiles', 'width=640, height=430, left='+left+',top='+top+',status=no, menubar=no, location=no, resizable=yes, toolbar=no, scrollbars=yes');*/
        if(oFileManager.oFileManagerMainDir=='.')
        {
            oFileManager.oFileManagerMainDir=ROOT_URL+oFileManagerMainDir;
        }
        oFileManager(
            function(systemObj)
            {
                $BK(id).value=systemObj.url;
            },null,true,
            {
                selectExt:selectExt?selectExt:''
                ,browseDir:browseDir?browseDir:'/.'
            }
        );
}

var nicImageButton = nicEditorAdvancedButton.extend({
    width:'220px',
    addPane : function() {
        var roundStr=new Date().getTime();
        this.im = this.ne.selectedInstance.selElm().parentTag('IMG');
        //李占为了增加答案标签AnswerType
        if(this.im&&this.im.AnswerType)
        {
            this.im=false;
        }
        //结束
        this.addForm({
            '' : {type : 'title', txt : '添加修改图片'},
            'src' : {type : 'text', txt : '路径', 'value' : 'http://', style : {width: '150px'}},
            'alt1':{type:"title",txt:'<iframe id="oEditUploadify'+roundStr+'" frameborder="0" style="width:25px; height:19px; border:0px; float:right; margin-top:-2px;" src="'+ROOT_URL+oFileManagerMainDir+'/include/uploadify/iframeIndex.php"></iframe><font style="font-weight:normal; font-size:12px; cursor:pointer; margin-left:5px; float:right;" onclick="selectFile(\'src\',\'jpg;png;gif;bmp\',\''+this.ne.CustomConfig["oFileManager"]["browseDir"]["img"]+'\');">选择图片</font>',p_css:{'textAlign':'right','height':'15px'}},
            'width' : {type : 'text', txt : '宽', style : {width: '40px'},p_css:{clear:'none', 'float': 'left'},s_css:{width: '60px'}},
            'height' : {type : 'text', txt : '高', style : {width: '40px'},p_css:{clear:'none', 'float': 'left'}},
            'alt' : {type : 'text', txt : '描述', style : {width: '100px'}},
            'align' : {type : 'select', txt : '样式', options : {'baseline' : '默认','left' : '左对齐', 'right' : '右对齐'}}
        },this.im);
        if(this.im)
        {
            if(!this.im.getAttribute('src').indexOf(window.ROOT_URL))
            {
                $BK('src').value=this.im.getAttribute('src').substr(window.ROOT_URL.length);
                $BK('width').value=parseInt(this.im.style.width);
                $BK('height').value=parseInt(this.im.style.height);
            }
        }
        
        
        document.getElementById('oEditUploadify'+roundStr).contentWindow.config={
            uploadifyId:'uploadify'
            ,onAllCompleteFun:function(fileUrl)
                        {
                            document.getElementById('src').value=fileUrl;
                        }
            ,fileExt:'jpg;png;gif;bmp'
            ,swfConfig:{
                            folder : this.ne.CustomConfig["oFileManager"]["quickUploadDir"]["img"]
                        }
        };
    },
    
    submit : function(e) {
        var src = this.inputs['src'].value;
        var style = {};
        this.inputs['width'].value && (style.width = parseInt(this.inputs['width'].value) + 'px');
        this.inputs['height'].value && (style.height = parseInt(this.inputs['height'].value) + 'px');
        if(src == "" || src == "http://") {
            alert("您必须插入图片路径");
            return false;
        }
        this.removePane();

        if(!this.im) {
            var tmp = 'javascript:nicImTemp();';
            this.ne.nicCommand("insertImage",tmp);
            this.im = this.findElm('IMG','src',tmp);
        }
        if(this.im) {
            this.errorfn(src);
            this.im.setAttributes({
                src : /^http:/.test(this.inputs['src'].value)?this.inputs['src'].value:(window.ROOT_URL+this.inputs['src'].value),
                alt : this.inputs['alt'].value,
                align : this.inputs['align'].value
            }).setStyle(style);
        }
    },
    
    errorfn:function(src)
    {
        this.im.setAttribute("onerror",'if(this.defaultSrc==null){this.defaultSrc="'+src+'"}else{this.defaultSrc="http:"+this.defaultSrc}if(!window.ROOT_URL){window.ROOT_URL=""}if(!/^http:/.test(this.defaultSrc)){this.onload=function(){this.removeAttribute("defaultSrc")};this.src=window.ROOT_URL+this.defaultSrc}else{if(this.src.indexOf(window.ROOT_URL+"'+oFileManagerMainDir+'/include/oEditor/resources/onerror.gif")<0){this.src=window.ROOT_URL+"'+oFileManagerMainDir+'/include/oEditor/resources/onerror.gif"}else{this.removeAttribute("defaultSrc")}}');

        if (bkLib.isMSIE)
        {
            this.im.errorfn=function()
            {
                if(this.defaultSrc==null)
                {
                    this.defaultSrc = src;
                }
                else
                {
                    this.defaultSrc="http:"+this.defaultSrc;
                }
                if (!window.ROOT_URL) {
                    window.ROOT_URL = "";
                }
                if (!/^http:/.test(this.defaultSrc)) {
                    this.onload=function(){this.removeAttribute("defaultSrc");};
                    this.src = window.ROOT_URL + this.defaultSrc;
                }
                else{
                    if(this.src.indexOf(window.ROOT_URL + oFileManagerMainDir+"/include/oEditor/resources/onerror.gif")<0)
                    {
                        this.src=window.ROOT_URL + oFileManagerMainDir+"/include/oEditor/resources/onerror.gif";
                    }
                    else
                    {
                        this.removeAttribute("defaultSrc");
                    }
                }
            }
            this.im.addEvent('error',this.im.errorfn.closure(this.im));
        }
    }
});

nicEditors.registerPlugin(nicPlugin,nicImageOptions);




/* START CONFIG */
var nicSaveOptions = {
    buttons : {
        'save' : {name : __('Save this content'), type : 'nicEditorSaveButton'}
    }
};
/* END CONFIG */

var nicEditorSaveButton = nicEditorButton.extend({
    init : function() {
        if(!this.ne.options.onSave) {
            this.margin.setStyle({'display' : 'none'});
        }
    },
    mouseClick : function() {
        var onSave = this.ne.options.onSave;
        var selectedInstance = this.ne.selectedInstance;
        onSave(selectedInstance.getContent(), selectedInstance.elm.id, selectedInstance);
    }
});

nicEditors.registerPlugin(nicPlugin,nicSaveOptions);

var nicXHTML = bkClass.extend({
    stripAttributes : ['_moz_dirty','_moz_resizing','_extended'],
    noShort : ['style','title','script','textarea','a'],
    cssReplace : {'font-weight:bold;' : 'strong', 'font-style:italic;' : 'em'},
    sizes : {1 : 'xx-small', 2 : 'x-small', 3 : 'small', 4 : 'medium', 5 : 'large', 6 : 'x-large'},
    
    construct : function(nicEditor) {
        this.ne = nicEditor;
        if(this.ne.options.xhtml) {
            nicEditor.addEvent('get',this.cleanup.closure(this));
        }
    },
    
    cleanup : function(ni) {
        var node = ni.getElm();
        var xhtml = this.toXHTML(node);
        ni.content = xhtml;
    },
    
    toXHTML : function(n,r,d) {
        var txt = '';
        var attrTxt = '';
        var cssTxt = '';
        var nType = n.nodeType;
        var nName = n.nodeName.toLowerCase();
        var nChild = n.hasChildNodes && n.hasChildNodes();
        var extraNodes = new Array();
        
        switch(nType) {
            case 1:
                var nAttributes = n.attributes;
                
                switch(nName) {
                    case 'b':
                        nName = 'strong';
                        break;
                    case 'i':
                        nName = 'em';
                        break;
                    case 'font':
                        nName = 'span';
                        break;
                }
                
                if(r) {
                    for(var i=0;i<nAttributes.length;i++) {
                        var attr = nAttributes[i];
                        
                        var attributeName = attr.nodeName.toLowerCase();
                        var attributeValue = attr.nodeValue;
                        
                        if(!attr.specified || !attributeValue || bkLib.inArray(this.stripAttributes,attributeName) || typeof(attributeValue) == "function") {
                            continue;
                        }
                        
                        switch(attributeName) {
                            case 'style':
                                var css = attributeValue.replace(/ /g,"");
                                for(itm in this.cssReplace) {
                                    if(css.indexOf(itm) != -1) {
                                        extraNodes.push(this.cssReplace[itm]);
                                        css = css.replace(itm,'');
                                    }
                                }
                                cssTxt += css;
                                attributeValue = "";
                            break;
                            case 'class':
                                attributeValue = attributeValue.replace("Apple-style-span","");
                            break;
                            case 'size':
                                cssTxt += "font-size:"+this.sizes[attributeValue]+';';
                                attributeValue = "";
                            break;
                        }
                        
                        if(attributeValue) {
                            attrTxt += ' '+attributeName+'="'+attributeValue+'"';
                        }
                    }

                    if(cssTxt) {
                        attrTxt += ' style="'+cssTxt+'"';
                    }

                    for(var i=0;i<extraNodes.length;i++) {
                        txt += '<'+extraNodes[i]+'>';
                    }
                
                    if(attrTxt == "" && nName == "span") {
                        r = false;
                    }
                    if(r) {
                        txt += '<'+nName;
                        if(nName != 'br') {
                            txt += attrTxt;
                        }
                    }
                }
                

                
                if(!nChild && !bkLib.inArray(this.noShort,attributeName)) {
                    if(r) {
                        txt += ' />';
                    }
                } else {
                    if(r) {
                        txt += '>';
                    }
                    
                    for(var i=0;i<n.childNodes.length;i++) {
                        var results = this.toXHTML(n.childNodes[i],true,true);
                        if(results) {
                            txt += results;
                        }
                    }
                }
                    
                if(r && nChild) {
                    txt += '</'+nName+'>';
                }
                
                for(var i=0;i<extraNodes.length;i++) {
                    txt += '</'+extraNodes[i]+'>';
                }

                break;
            case 3:
                //if(n.nodeValue != '\n') {
                    txt += n.nodeValue;
                //}
                break;
        }
        
        return txt;
    }
});
nicEditors.registerPlugin(nicXHTML);



var nicBBCode = bkClass.extend({
    construct : function(nicEditor) {
        this.ne = nicEditor;
        if(this.ne.options.bbCode) {
            nicEditor.addEvent('get',this.bbGet.closure(this));
            nicEditor.addEvent('set',this.bbSet.closure(this));
            
            var loadedPlugins = this.ne.loadedPlugins;
            for(itm in loadedPlugins) {
                if(loadedPlugins[itm].toXHTML) {
                    this.xhtml = loadedPlugins[itm];
                }
            }
        }
    },
    
    bbGet : function(ni) {
        var xhtml = this.xhtml.toXHTML(ni.getElm());
        ni.content = this.toBBCode(xhtml);
    },
    
    bbSet : function(ni) {
        ni.content = this.fromBBCode(ni.content);
    },
    
    toBBCode : function(xhtml) {
        function rp(r,m) {
            xhtml = xhtml.replace(r,m);
        }
        
        rp(/\n/gi,"");
        rp(/<strong>(.*?)<\/strong>/gi,"[b]$1[/b]");
        rp(/<em>(.*?)<\/em>/gi,"[i]$1[/i]");
        rp(/<span.*?style="text-decoration:underline;">(.*?)<\/span>/gi,"[u]$1[/u]");
        rp(/<ul>(.*?)<\/ul>/gi,"[list]$1[/list]");
        rp(/<li>(.*?)<\/li>/gi,"[*]$1[/*]");
        rp(/<ol>(.*?)<\/ol>/gi,"[list=1]$1[/list]");
        rp(/<img.*?src="(.*?)".*?>/gi,"[img]$1[/img]");
        rp(/<a.*?href="(.*?)".*?>(.*?)<\/a>/gi,"[url=$1]$2[/url]");
        rp(/<br.*?>/gi,"\n");
        rp(/<.*?>.*?<\/.*?>/gi,"");
        
        return xhtml;
    },
    
    fromBBCode : function(bbCode) {
        function rp(r,m) {
            bbCode = bbCode.replace(r,m);
        }        
        
        rp(/\[b\](.*?)\[\/b\]/gi,"<strong>$1</strong>");
        rp(/\[i\](.*?)\[\/i\]/gi,"<em>$1</em>");
        rp(/\[u\](.*?)\[\/u\]/gi,"<span style=\"text-decoration:underline;\">$1</span>");
        rp(/\[list\](.*?)\[\/list\]/gi,"<ul>$1</ul>");
        rp(/\[list=1\](.*?)\[\/list\]/gi,"<ol>$1</ol>");
        rp(/\[\*\](.*?)\[\/\*\]/gi,"<li>$1</li>");
        rp(/\[img\](.*?)\[\/img\]/gi,"<img src=\"$1\" />");
        rp(/\[url=(.*?)\](.*?)\[\/url\]/gi,"<a href=\"$1\">$2</a>");
        rp(/\n/gi,"<br />");
        //rp(/\[.*?\](.*?)\[\/.*?\]/gi,"$1");
        
        return bbCode;
    }

    
});
nicEditors.registerPlugin(nicBBCode);



oEditor=nicEditor = nicEditor.extend({
        floatingPanel : function() {
                this.floating = new bkElement('DIV').setStyle({position: 'absolute', top : '-1000px'}).appendTo(document.body);
                this.addEvent('focus', this.reposition.closure(this)).addEvent('blur', this.hide.closure(this));
                this.setPanel(this.floating);
        },
        
        reposition : function() {
                var e = this.selectedInstance.e;
                this.floating.setStyle({ width : (parseInt(e.getStyle('width')) || e.clientWidth)+'px' });
                var top = e.offsetTop-this.floating.offsetHeight;
                if(top < 0) {
                        top = e.offsetTop+e.offsetHeight;
                }
                
                this.floating.setStyle({ top : top+'px', left : e.offsetLeft+'px', display : 'block' });
        },
        
        hide : function() {
                this.floating.setStyle({ top : '-1000px'});
        }
});



/* START CONFIG */
var nicCodeOptions = {
    buttons : {
        'xhtml' : {name : '编辑源码', type : 'nicCodeButton'}
    }
    
};
/* END CONFIG */

var nicCodeButton = nicEditorAdvancedButton.extend({
    width : '350px',
        
    addPane : function() {
        var html = this.ne.selectedInstance.cleanWord(    //过滤word
            this.ne.selectedInstance.getContent()    //获得源码
        );
        this.addForm({
            '' : {type : 'title', txt : '编辑源码'},
            'code' : {type : 'content', 'value' : html, style : {width: '340px', height : '200px'}}
        });
    },
    
    submit : function(e) {
        var code = this.inputs['code'].value;
        this.ne.selectedInstance.setContent(code);
        this.removePane();
    }
});

nicEditors.registerPlugin(nicPlugin,nicCodeOptions);


/* **************************** TABLE ************************************ */

/* START CONFIG */
var tableOptions = {
   buttons : {
      'table' : {name : '添加表格', type : 'nicEditorTableButton', tags : ['TABLE']}
   }/* NICEDIT_REMOVE_START *///,iconFiles : {'table' : '../table.gif'}/* NICEDIT_REMOVE_END */
};
/* END CONFIG */

var nicEditorTableButton = nicEditorAdvancedButton.extend({

    width: '220px',

    addPane : function() {
        this.ln = this.ne.selectedInstance.selElm().parentTag('TABLE');
        this.addForm({
            '' : {type : 'title', txt : '添加表格'},
            'rowsLength' : {type : 'text', txt : '行', value : '1', style : {width: '30px'},p_css:{display:"inline",clear:"none"}},
            'colsLength' : {type : 'text', txt : '列', value : '1', style : {width: '30px'},p_css:{display:"inline",clear:"none"}},
            'border' : {type : 'text', txt : '边框', value : '1', style : {width: '30px'},p_css:{display:"inline",clear:"none"}},
            'cellpadding' : {type : 'text', txt : '填充', value : '1', style : {width: '30px'},p_css:{display:"inline",clear:"none"}},
            'cellspacing' : {type : 'text', txt : '间距', value : '1', style : {width: '30px'},p_css:{display:"inline",clear:"none"}}
            //'target' : {type : 'select', txt : 'Open In', options : {'' : 'Current Window', '_blank' : 'New Window'},style : {width : '100px'}}
        },this.ln);
    },
    
    submit : function(e) {
        var rows = this.inputs['rowsLength'].value;
        var cols =this.inputs['colsLength'].value;
        var border =this.inputs['border'].value;
        var cellpadding =this.inputs['cellpadding'].value;
        var cellspacing =this.inputs['cellspacing'].value;
        this.removePane();
        
        var cTable = '<table border='+border+' cellpadding='+cellpadding+' cellspacing='+cellspacing+' >';
        for (var y=0;y<rows;y++)
        {
            cTable += "<tr>";
            for (var x=0;x<cols;x++)
            {
                cTable += "<td>&nbsp;</td>";
            }
            cTable += "</tr>";
        }
        cTable += '</table>';
        if (bkLib.isMSIE && bkLib.version < 9)
        {
            this.ne.selectedInstance.getRng().pasteHTML(cTable);
            //this.ne.selectedInstance.getRng().htmlText    IE获取HTML文本
        }
        else
        {
            var temp = new bkElement('div');
            temp.innerHTML = cTable;
            cTable = temp.getElementsByTagName('table')[0];
            this.ne.selectedInstance.getRng().insertNode(cTable);
            //this.ne.selectedInstance.getRng().removeAllRanges();
            //this.ne.selectedInstance.getRng().surroundContents(t);    //把文本包含到t标签中
            //outerHTML(this.ne.selectedInstance.getRng().cloneContents())    //获取HTML文本
        }
    }

});

nicEditors.registerPlugin(nicPlugin,tableOptions);

/* **************************** 插入答案框 ************************************ */

/* START CONFIG */
var AnswerOptions = {
    buttons : {
        'AnswerSelect' : {name : '插入答案框', type : 'nicEditorAnswerButton', tags : ['IMG'], AnswerType:'select'}
    },
    //李占增加按钮自定义参数
    CustomConfig:
    {
        AnswerSelect:
        {
            AnswerType:"select",
            ExtensionFun:null,
            AnswerNum:"0"
        }
    }
    //结束
    //iconFiles : {'table' : '../table.gif'}
};
/* END CONFIG */

var nicEditorAnswerButton = nicEditorAdvancedButton.extend({

    width: '220px',

    addPane : function() {
        if (bkLib.isMSIE && bkLib.version < 9)
        {
            var t ='<img AnswerType='+this.ne.CustomConfig["AnswerSelect"]["AnswerType"]+' alt="答案'+(++this.ne.CustomConfig["AnswerSelect"]["AnswerNum"])+'" Num="'+this.ne.CustomConfig["AnswerSelect"]["AnswerNum"]+'" style="width:80px; height: 25px; border:0px; background-color:#FFFFCC; color:#FF0000;" />';
            try
            {
                var rng=this.ne.selectedInstance.getRng();
                rng.pasteHTML(t);
                //this.ne.selectedInstance.getRng().moveEnd("character",-10);
            }
            catch(e){}
        }
        else
        {
            var t = new bkElement('img');
            t.style.cssText="width:80px; height: 25px; border:0px; background-color:#FFFFCC; color:#FF0000;";
            t.setAttribute("AnswerType",this.ne.CustomConfig["AnswerSelect"]["AnswerType"]);
            t.src="http://";
            t.alt="答案"+(++this.ne.CustomConfig["AnswerSelect"]["AnswerNum"]);
            t.setAttribute("Num",this.ne.CustomConfig["AnswerSelect"]["AnswerNum"]);
            this.ne.selectedInstance.getRng().insertNode(t);
        }
        this.removePane();
        
        try
        {
            for(var tempFun in this.ne.CustomConfig["AnswerSelect"]["ExtensionFun"])
            {
                eval(this.ne.CustomConfig["AnswerSelect"]["ExtensionFun"][tempFun]+"(this);");
            }
            rng.moveStart("character",0);
            //rng.moveEnd("character",-10);
            rng.select();
        }
        catch(e){}
    }

});

nicEditors.registerPlugin(nicPlugin,AnswerOptions);

/* **************************** 插入图片域 ************************************ */
/* START CONFIG */
var nicImageOptions = {
    buttons : {
        'ImageArea' : {name : '插入图片域', type : 'nicImageAreaButton', tags : ['DIV'], AnswerType:'ImageArea'}    
    },
    CustomConfig:
    {
        ImageArea:
        {
            mousedownObj:null
        }
    }
};
/* END CONFIG */

var nicImageAreaButton = nicEditorAdvancedButton.extend({
    width : '220px',
    addPane : function() {
        var roundStr=new Date().getTime();
        this.addForm({
            '' : {type : 'title', txt : '插入图片域'},
            'src' : {type : 'text', txt : '路径', 'value' : 'http://', style : {width: '150px'}},
            'alt1':{type:"title",txt:'<iframe id="oEditUploadify'+roundStr+'" frameborder="0" style="width:25px; height:19px; border:0px; float:right; margin-top:-2px;" src="'+ROOT_URL+oFileManagerMainDir+'/include/uploadify/iframeIndex.php"></iframe><font style="font-weight:normal; font-size:12px; cursor:pointer; margin-left:5px; float:right;" onclick="selectFile(\'src\',\'jpg;png;gif;bmp\',\''+this.ne.CustomConfig["oFileManager"]["browseDir"]["img"]+'\');" >选择图片</font>',p_css:{'textAlign':'right','height':'15px'}},
            'width' : {type : 'text', txt : '宽', style : {width: '40px'},p_css:{clear:'none', 'float': 'left'},s_css:{width: '60px'}},
            'height' : {type : 'text', txt : '高', style : {width: '40px'},p_css:{clear:'none', 'float': 'left'}},
            'alt' : {type : 'text', txt : '描述', style : {width: '100px'}},
            'Description' : {type : 'content', txt : '注明', value:"", style : {width: '150px'}}
        });

        document.getElementById('oEditUploadify'+roundStr).contentWindow.config={
            uploadifyId:'uploadify',
            onAllCompleteFun:function(fileUrl)
                        {
                            document.getElementById('src').value=fileUrl;
                        },
            fileExt:'jpg;png;gif;bmp'
            ,swfConfig:{
                            folder : this.ne.CustomConfig["oFileManager"]["quickUploadDir"]["img"]
                        }
        };
    },
    
    submit : function(e) {
        var src = this.inputs['src'].value;
        var alt =this.inputs['alt'].value;
        var width = this.inputs['width'].value;
        var height =this.inputs['height'].value;
        var Description = this.ne.Text_HTML(this.inputs['Description'].value);
        if(Description==""||src=="" || src == "http://")
        {
            alert("图片地址和注明必须填");
            return false;
        }
        if(!/^\d+(?:px|%)$/i.test(width))
        {
            if(/^\d+$/i.test(width))
            {
                width+='px';
            } else {
                width='100px';
            }
        }
        if(!/^\d+(?:px|%)$/i.test(height))
        {
            if(/^\d+$/i.test(height))
            {
                height+='px';
            } else {
                height='100px';
            }
        }
        this.removePane();
        
        var cTable='<div style=" position:absolute; z-index:-100; width:'+width+'; height:'+height+';">\
                    <img src="'+src+'" alt="'+alt+'" style="height:80%; width:100%;" onerror=\'if(this.defaultSrc==null){this.defaultSrc="'+src+'"}else{this.defaultSrc="http:"+this.defaultSrc}if(!window.ROOT_URL){window.ROOT_URL=""}if(!/^http:/.test(this.defaultSrc)){this.onload=function(){this.removeAttribute("defaultSrc")};this.src=window.ROOT_URL+this.defaultSrc}else{if(this.src.indexOf(window.ROOT_URL+"'+oFileManagerMainDir+'/include/oEditor/resources/onerror.gif")<0){this.src=window.ROOT_URL+"'+oFileManagerMainDir+'/include/oEditor/resources/onerror.gif"}else{this.removeAttribute("defaultSrc")}}\' />\
                    <div style="overflow:auto; height:20%; width:100%;">'+Description+'</div>\
                </div>';
        if (bkLib.isMSIE && bkLib.version < 9)
        {
            var t ='<div AnswerType="ImageArea" eval="this.getElementsByTagName(\'div\')[0].style.zIndex=0;" style=" float:left;width:'+width+'; height:'+height+';" onmousedown="window.CustomConfig[\'ImageArea\'][\'mousedownObj\']=this;">'+cTable+'</div>';
            try{
                this.ne.selectedInstance.getRng().pasteHTML(t);
            }
            catch(e)
            {
                alert("文本域不可以覆盖");
            }
            //this.ne.selectedInstance.getRng().htmlText    IE获取HTML文本
        }
        else
        {
            var t = new bkElement('div')
                    .setContent(cTable);
            t.style.cssText="float:left; width:100px; height:100px;";
            t.setAttribute("AnswerType","ImageArea");
            t.setAttribute("eval","this.getElementsByTagName(\'div\')[0].style.zIndex=0;");
            t.setAttribute("onmousedown","window.CustomConfig[\'ImageArea\'][\'mousedownObj\']=this;");
            t.onmousedown=function(){window.CustomConfig['ImageArea']['mousedownObj']=this;};
            
            this.ne.selectedInstance.getRng().insertNode(t);
            //this.ne.selectedInstance.getRng().removeAllRanges();
            //this.ne.selectedInstance.getRng().surroundContents(t);    //把文本包含到t标签中
            //outerHTML(this.ne.selectedInstance.getRng().cloneContents())    //获取HTML文本
        }
    }
});

nicEditors.registerPlugin(nicPlugin,nicImageOptions);

/* **************************** 插入媒体播放器 ************************************ */

/* START CONFIG */
var nicMediaButton = {
   buttons : {
      'media' : {name : '插入媒体', type : 'nicEditorMediaButton', tags : ['OBJECT','EMBED']}
   }/* NICEDIT_REMOVE_START *///,iconFiles : {'table' : '../table.gif'}/* NICEDIT_REMOVE_END */
};
/* END CONFIG */

var nicEditorMediaButton = nicEditorAdvancedButton.extend({

    width: '220px',

    addPane : function() {
        var roundStr=new Date().getTime();
        var formArr={
                'src'            :'http://',
                'AutoPlay'        :false,
                'Ctn'            :false,
                'ProgressBar'    :false,
                'PlayCount'        :'0',
                'width'        :250,
                'height'        :250
            };
        var selfNodeObj=this.ne.selectedInstance.selElm();
        this.ln = this.ne.selectedInstance.selElm().parentTag('OBJECT');
        if(this.ln)
        {
            //bkLib.isMSIE
            var paramNodeArr=this.ln.getElementsByTagName('param');
            for(var i in paramNodeArr)
            {
                if(paramNodeArr[i].name=='flashvars')
                {
                    var flashvarsArr=paramNodeArr[i].value.split('&');
                    for(var j=0;j<flashvarsArr.length;j++)
                    {
                        var tempArr=flashvarsArr[j].split('=');
                        switch(tempArr[0])
                        {
                            case 'file':
                                formArr['src']=tempArr[1].substr(0,7) === 'http://' ? tempArr[1] : tempArr[1].substr((window.ROOT_URL||"").length);
                                break;
                            case 'AutoPlay':
                                formArr['AutoPlay']=eval(tempArr[1]);
                                break;
                            case 'Ctn':
                                formArr['Ctn']=eval(tempArr[1]);
                                break;
                        }
                    }
                    //JSenable=true&Ctn=false&AutoPlay=false&SkinURL=skin/Audio.zip&file=http://localhost/orEditor/0.7/data/cs.mp3
                    break;
                }
            }
            //alert(this.ln.getAttribute('ProgressBar'));
            formArr['ProgressBar']=eval(this.ln.getAttribute('ProgressBar'));
            formArr['PlayCount']=this.ln.getAttribute('playcount')==null?'0':this.ln.getAttribute('playcount');
            formArr['width']=this.ln.width;
            formArr['height']=this.ln.height;
        }
        else if(selfNodeObj.nodeName=='EMBED')    //这说明是加入的wmv系列的媒体
        {    
            this.ln=selfNodeObj;
            formArr['src']=this.ln.src;
            formArr['AutoPlay']=eval(this.ln.getAttribute('autostart'));
            formArr['Ctn']=eval(this.ln.getAttribute('loop'));
            formArr['width']=this.ln.width;
            formArr['height']=this.ln.height;
            //flashvars=this.ln.
            //paramNodeArr=this.ln.getElementsByTagName('param');
        }
        this.addForm({
                '' : {type : 'title', txt : '插入媒体'},
                'src' : {type : 'text', txt : '路径', 'value' : formArr.src, style : {width: '150px'}},
                'alt1':{type:"title",txt:'<iframe id="oEditUploadify'+roundStr+'" frameborder="0" style="width:25px; height:19px; border:0px; float:right; margin-top:-2px;" src="'+ROOT_URL+oFileManagerMainDir+'/include/uploadify/iframeIndex.php"></iframe><font style="font-weight:normal; font-size:12px; cursor:pointer; margin-left:5px; float:right;" onclick="selectFile(\'src\',\'swf;flv;mp3;wmv\',\''+this.ne.CustomConfig["oFileManager"]["browseDir"]["media"]+'\');">选择媒体</font>',p_css:{'textAlign':'right','height':'15px'}},
                'AutoPlay' : {type : 'checkbox', txt : '自动播放', checked:formArr.AutoPlay,s_css:{width: '60px'},p_css:{'float': 'left','width':'95px'}},
                'Ctn' : {type : 'checkbox', txt : '循环播放', checked:formArr.Ctn,s_css:{width: '60px'},p_css:{clear:'none', 'float': 'left'}},
                'ProgressBar' : {type : 'checkbox', txt : '禁用进度', checked:formArr.ProgressBar,s_css:{width: '60px'},p_css:{'float': 'left','width':'95px'}},
                'PlayCount' : {type : 'text', txt : '播放次数', 'value' : formArr.PlayCount, style : {width: '30px',height:'10px'},s_css:{width: '60px'},p_css:{clear:'none', 'width':'110px', 'float': 'left'}},
                'width' : {type : 'text', txt : '音/视频宽', 'value' : formArr.width, style : {width: '30px'},p_css:{'width':'110px', 'float': 'left'},s_css:{width: '60px'}},
                'height' : {type : 'text', txt : '音/视频高', 'value' : formArr.height, style : {width: '30px'},p_css:{clear:'none', 'width':'110px', 'float': 'left'}},
                'palySwf' : {type : 'checkbox', txt : 'SWF单独播放', checked:true, s_css:{width: '80px'}}
            });
        
        document.getElementById('oEditUploadify'+roundStr).contentWindow.config={
            uploadifyId:'uploadify',
            onAllCompleteFun:function(fileUrl)
                        {
                            document.getElementById('src').value=fileUrl;
                        },
            fileExt:'wmv;mp3;flv;swf'
            ,swfConfig:{
                            folder : this.ne.CustomConfig["oFileManager"]["quickUploadDir"]["media"]
                        }
        };
    },
    
    submit : function(e) {
        var src = this.inputs['src'].value;
        var AutoPlay=this.inputs['AutoPlay'].checked?"true":"false";
        var Ctn=this.inputs['Ctn'].checked?"true":"false";
        var ProgressBar=this.inputs['ProgressBar'].checked?"true":"false";
        var PlayCount=this.inputs['PlayCount'].value.replace(/(^\s*)|(\s*$)/g, "").match(/^\d+/);
        var palySwf=this.inputs['palySwf'].checked?true:false;
        var ProgressBarJs="";
        var width=parseInt(this.inputs['width'].value)>250?parseInt(this.inputs['width'].value):250;
        var height=27;
        var SkinURL="skin/Audio.zip";
        var localPath=true;
        var mediaType=src.substr(src.lastIndexOf(".")+1);
        if(src.lastIndexOf(".")==-1)
        {
            alert("非法媒体路径");
            return false;
        }
        else if(mediaType.toUpperCase()=="FLV"||mediaType.toUpperCase()=="SWF"||mediaType.toUpperCase()=="WMV")
        {
            SkinURL=mediaType.toUpperCase()=="SWF"?"skin/defaultSWF.zip":"skin/defaultSE.zip";
            height=parseInt(this.inputs['height'].value)>220?parseInt(this.inputs['height'].value):220;
        }
        else if(mediaType.toUpperCase()!="MP3")
        {
            alert("暂只支持MP3,FLV,SWF,WMV");
            return false;
        }
        if(/^http:\/\//i.test(src))
        {
            localPath=false;
        }
        this.removePane();
        if(!bkLib.isChrome&&this.ln)
        {
            //alert(this.ln.movie);
            this.ln.outerHTML='';
            //this.ne.elm.removeChild(this.ln);
        }
        var player_id = "player_id"+new Date().getTime();
        if(ProgressBar=="true")
        {
            ProgressBarJs='document.getElementById("'+player_id+'").ProgressBarInterval=setInterval('+
                            'function()'+
                            '{'+
                                'try {'+
                                    'if(document.getElementById("'+player_id+'").getAttribute("JSenable")!="false")'+
                                    '{'+
                                        'JS_OFplayer("'+player_id+'", "adjust", "progress","prop","");'+
                                    '}'+
                                    'clearInterval(document.getElementById("'+player_id+'").ProgressBarInterval);'+
                                '} catch(e) {}'+
                            '},500);'
        }
        var script_flashvars="JSenable=true&Ctn="+Ctn+"&AutoPlay="+AutoPlay+"&SkinURL="+SkinURL+"&file=";
        var script='<script language=javascript >'+
                        'var object_ofplayer_temp=document.getElementById("'+player_id+'");'+
                        'if(object_ofplayer_temp!=null)'+
                        '{'+
                            'try'+
                            '{'+
                                'if(object_ofplayer_temp.movie!=window.ROOT_URL+"'+oFileManagerMainDir+'/include/player/ofplayer.swf?'+script_flashvars+(localPath?"\"+window.ROOT_URL+\"":"")+src+'")'+
                                '{'+
                                    'object_ofplayer_temp.flashvars="'+script_flashvars+(localPath?"\"+window.ROOT_URL+\"":"")+src+'";'+
                                    'object_ofplayer_temp.movie=window.ROOT_URL+"'+oFileManagerMainDir+'/include/player/ofplayer.swf?'+script_flashvars+(localPath?"\"+window.ROOT_URL+\"":"")+src+'";'+
                                '}'+
                                'if(object_ofplayer_temp.getElementsByTagName("embed")[0].src!=window.ROOT_URL+"'+oFileManagerMainDir+'/include/player/ofplayer.swf")'+
                                '{'+
                                    'object_ofplayer_temp.getElementsByTagName("embed")[0].setAttribute("flashvars","'+script_flashvars+(localPath?"\"+window.ROOT_URL+\"":"")+src+'");'+
                                    'object_ofplayer_temp.getElementsByTagName("embed")[0].src=window.ROOT_URL+"'+oFileManagerMainDir+'/include/player/ofplayer.swf";'+
                                '}'+
                            '}'+
                            'catch(e){}'+
                            ProgressBarJs+
                        '}'+
                    '</script>';
        var t='<object id='+player_id+' classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" '+(String(PlayCount)=='0'||PlayCount==""?"":'PlayCount="'+PlayCount+'"')+' ProgressBar="'+(ProgressBar=="true"?'true':'false')+'" width="'+width+'" height="'+height+'" AnswerType="media" eval="this.outerHTML+=unescape(\''+escape(script)+'\');" >\
                            <param name="movie" value="'+window.ROOT_URL+oFileManagerMainDir+'/include/player/ofplayer.swf?'+script_flashvars+(localPath?window.ROOT_URL:"")+src+'" />\
                            <param name="allowFullScreen" value="true" />\
                            <param name="allowScriptAccess" value="always" />\
                            <param name="quality" value="high" />\
                            <param name="wmode" value="opaque" />\
                            <param name="flashvars" value="'+script_flashvars+(localPath?window.ROOT_URL:"")+src+'" />\
                            <embed name="'+player_id+'" type="application/x-shockwave-flash" '+(String(PlayCount)=='0'||PlayCount==""?"":'PlayCount="'+PlayCount+'"')+' ProgressBar="'+(ProgressBar=="true"?'true':'false')+'" width="'+width+'" height="'+height+'" src="'+window.ROOT_URL+oFileManagerMainDir+'/include/player/ofplayer.swf" allowfullscreen="true" allowscriptaccess="always" quality="high" wmode="opaque" flashvars="'+script_flashvars+(localPath?window.ROOT_URL:"")+src+'"></embed>\
                    </object>';
        if(mediaType.toUpperCase()=="WMV")
        {
            script='<script language=javascript >'+
                        'var embedObj=document.getElementById("'+player_id+'");'+
                        'if(embedObj!=null&&embedObj.src!="'+(localPath?"\"+window.ROOT_URL+\"":"")+encodeURI(src)+'")'+
                        '{'+
                            'var newEmbedObj=document.createElement("EMBED");'+
                            'newEmbedObj.setAttribute("autostart",'+AutoPlay+');'+
                            'newEmbedObj.setAttribute("loop",'+Ctn+');'+
                            'newEmbedObj.setAttribute("width",'+(width>290?width:290)+');'+
                            'newEmbedObj.setAttribute("height",'+height+');'+
                            'newEmbedObj.setAttribute("src","'+(localPath?"\"+window.ROOT_URL+\"":"")+encodeURI(src)+'");'+
                            'newEmbedObj.setAttribute("flename","mp");'+
                            'newEmbedObj.setAttribute("type","application/x-oleobject");'+
                            'newEmbedObj.setAttribute("codeBase","http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#version=10,0,0,0");'+
                            'embedObj.parentNode.insertBefore(newEmbedObj,embedObj.nextSibling);'+
                            'embedObj.parentNode.removeChild(embedObj);'+
                        '}'+
                    '</script>';
            //t='<embed id='+player_id+' width="'+(width>290?width:290)+'" height="'+height+'" src="'+(localPath?window.ROOT_URL:"")+src+'" autostart="'+AutoPlay+'" loop="'+Ctn+'" AnswerType="media" type=audio/mpeg controls="smallconsole" eval="this.outerHTML+=unescape(\''+escape(script)+'\');" >';
            t='<embed id='+player_id+' width="'+(width>290?width:290)+'" height="'+height+'" src="'+(localPath?window.ROOT_URL:"")+src+'" autostart="'+AutoPlay+'" loop="'+Ctn+'" AnswerType="media" type=audio/mpeg controls="smallconsole" eval="this.outerHTML+=unescape(\''+escape(script)+'\');" ></embed>';
        }
        else if(mediaType.toUpperCase()=="SWF"&&palySwf)
        {
            t='<object id='+player_id+' height="'+height+'" width="'+width+'" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" >\
                    <param value="'+(localPath?window.ROOT_URL:"")+src+'" name="movie">\
                    <param value="true" name="allowFullScreen">\
                    <param value="always" name="allowScriptAccess">\
                    <param value="high" name="quality">\
                    <param value="opaque" name="wmode">\
                    <param value="undefined" name="flashvars">\
                    <embed name="'+player_id+'" height="'+height+'" width="'+width+'" flashvars="undefined" wmode="opaque" quality="high" allowscriptaccess="always" allowfullscreen="true" src="'+(localPath?window.ROOT_URL:"")+src+'" type="application/x-shockwave-flash">\
                </object>'
        }
        if (bkLib.isMSIE && bkLib.version < 9)
        {
            this.ne.selectedInstance.getRng().pasteHTML(t);
        }
        else
        {
            var temp = new bkElement('div');
            temp.innerHTML = t;
            //var documentFragment=this.ne.selectedInstance.getRng().createContextualFragment(t);    //去掉的原因是IE 9不支持
            this.ne.selectedInstance.getRng().insertNode(temp.firstChild);
            //this.ne.selectedInstance.getRng().removeAllRanges();
            //this.ne.selectedInstance.getRng().surroundContents(t);    //把文本包含到t标签中
            //outerHTML(this.ne.selectedInstance.getRng().cloneContents())    //获取HTML文本
        }
        if(bkLib.isChrome&&this.ln)
        {
            //alert(this.ln.movie);
            this.ln.parentNode.insertBefore($BK(player_id),this.ln.nextSibling);
            this.ln.outerHTML='';
            //this.ne.elm.removeChild(this.ln);
        }
    }

});

nicEditors.registerPlugin(nicPlugin,nicMediaButton);