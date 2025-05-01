function mouseDrag(obj,fn)
{
    this.mouseDragStatus=false;
    this.rFn=[];
    this.objList=[];
    this.objListIndex=0;
    this.objListO={};

    this.$=function(obj, thisWindow)
    {
        if(typeof(obj)!="object")
        {
        obj=(thisWindow || window)['document'].getElementById(obj);
        }
        return obj;
    }
    this.addEvent=function(obj, type, fn)
    {
        (obj.addEventListener) ? obj.addEventListener( type, fn, false ) : obj.attachEvent("on"+type, fn);
    }
    this.removeEvent=function(obj, type, fn)
    {
        (obj.removeEventListener) ? obj.removeEventListener(type,fn,false) : obj.detachEvent('on'+type,fn);
    }
    this.mouseDown=function(e)
    {
        var obj=arguments[arguments.length-1];
        this.setObjList(obj);
        this.disableSelection(e);
        this.getScroll();
        this.objListO.nX = this.objListO.oX = e.clientX + this.objListO.scroll.left;
        this.objListO.nY = this.objListO.oY = e.clientY + this.objListO.scroll.top;
        this.mouseDragStatus=true;

        if(this.rFn[0])
        {
            this.objListO.mouseDownFn=this.rFn[0];
        }
        this.rFn=[];
        this.removeEvent(this.objListO.thisWindow.document,"mousemove",this.objListO.mouseMoveFn);
        this.removeEvent(this.objListO.thisWindow.document,"mouseup",this.objListO.mouseUpFn);
        this.addEvent(this.objListO.thisWindow.document,"mousemove",this.mouseMove.mouseDragClosure(this));
        this.addEvent(this.objListO.thisWindow.document,"mouseup",this.mouseUp.mouseDragClosure(this));
        this.objListO.mouseMoveFn=this.rFn[0];
        this.objListO.mouseUpFn=this.rFn[1];
        this.rFn=[];

        this.objListO.fn.mouseDownFn(this.objListO);
    }
    this.mouseMove=function(e)
    {
        if(this.mouseDragStatus==true)
        {
            this.getScroll();
            this.disableSelection(e);
            this.objListO.uX=this.objListO.nX;
            this.objListO.uY=this.objListO.nY;
            this.objListO.nX=e.clientX + this.objListO.scroll.left;
            this.objListO.nY=e.clientY + this.objListO.scroll.top;
            if(this.objListO.uX>0 && this.objListO.uY>0)
            {
                this.objListO.aW=this.objListO.nX-this.objListO.oX;
                this.objListO.aH=this.objListO.nY-this.objListO.oY;
                this.objListO.nW=this.objListO.nX-this.objListO.uX;
                this.objListO.nH=this.objListO.nY-this.objListO.uY;
                this.objListO.fn.mouseMoveFn(this.objListO);
            }
        }
    }
    this.mouseUp=function(e)
    {
        this.getScroll();
        this.mouseDragStatus=false;
        this.removeEvent(this.objListO.thisWindow.document,"mousemove",this.objListO.mouseMoveFn);
        this.removeEvent(this.objListO.thisWindow.document,"mouseup",this.objListO.mouseUpFn);
        this.objListO.fn.mouseUpFn(this.objListO);
        this.objDefault(this.objListO);
    }
    this.getScroll = function()
    {
        this.objListO.scroll = {
            'top'  : this.objListO.thisWindow.document.documentElement.scrollTop || this.objListO.thisWindow.document.body.scrollTop,
            'left' : this.objListO.thisWindow.document.documentElement.scrollLeft || this.objListO.thisWindow.document.body.scrollLeft
        };
    }
    this.disableSelection = function(e)
    {
        if(e.preventDefault)
        {
            e.preventDefault();
        } else {
            e.returnValue = false;
        }
    }
    this.setObjList=function(obj)
    {
        for(var OIt in this.objList)
        {
            if(this.objList[OIt]["target"]==obj)
            {
                this.objListO=this.objList[OIt];
                this.objListIndex=OIt;
                return ;
            }
        }
        this.objListO=this.objList[this.objList.length]={"target":obj, "thisWindow" : window,"oX":-1,"oY":-1,"uX":-1,"uY":-1,"nX":-1,"nY":-1,"aW":-1,"aH":-1,"nW":-1,"nH":-1,"mouseDownFn":null,"mouseMoveFn":null,"mouseUpFn":null};

        this.objListIndex=this.objList.length-1;
    }
    this.ObjAllExtend=function (CustomConfig,o)
    {
        for(var tempObj in o)
        {
            if(typeof(CustomConfig[tempObj])=="object"&&CustomConfig[tempObj]!=null)
            {
                this.ObjAllExtend(CustomConfig[tempObj],o[tempObj]);
            } else {
                CustomConfig[tempObj]=o[tempObj];
            }
        }
    }
    this.objDefault=function(obj)
    {
        for(var i in obj)
        {
            switch(typeof(obj[i]))
            {
                case "number":
                    obj[i]=-1;
                    break;
                default:
                    break;
            }
        }
    }
    this.toArray=function(iterable)
    {
        var length = iterable.length, results = new Array(length);
        while (length--) { results[length] = iterable[length] };
        return results;
    }
    this.onlyFalse=function()
    {
        return false;
    }
    this.removeDrag=function(ob, thisWindowj)
    {
        if(obj=this.$(obj, thisWindow))
        {
            this.setObjList(obj);
            this.removeEvent(obj,"mousedown",this.objListO.mouseDownFn||this.rFn[0]);
            this.removeEvent(this.objListO.thisWindow.document,"mousemove",this.objListO.mouseMoveFn);
            this.removeEvent(this.objListO.thisWindow.document,"mouseup",this.objListO.mouseUpFn);

            this.objList.splice(this.objListIndex,1);
            this.rFn=[];
        }
    }
    this.getObjListO=function(obj, thisWindow)
    {
        if(obj=this.$(obj, thisWindow))
        {
            this.setObjList(obj);
            return [this.objListO,this.objListIndex];
        }
    }
    this.init=function(obj, fn, thisWindow)
    {
        if(obj=this.$(obj, thisWindow))
        {
            var memory = [this.objListO, this.objListIndex];
            this.setObjList(obj);
            this.objListO.fn=fn;
            this.objListO.thisWindow = thisWindow || window;

            if(!fn.mouseDownFn)
            {
                fn.mouseDownFn=this.onlyFalse;
            }
            if(!fn.mouseMoveFn)
            {
                fn.mouseMoveFn=this.onlyFalse;
            }
            if(!fn.mouseUpFn)
            {
                fn.mouseUpFn=this.onlyFalse;
            }
            if(this.objListIndex==this.objList.length-1)
            {
                this.addEvent(obj,"mousedown",this.mouseDown.mouseDragClosure(this,obj));
            }
            this.objListO = memory[0];
            this.objListIndex = memory[1];
        }
    }
    if(Function.prototype.mouseDragClosure==null)
    {
        Function.prototype.mouseDragClosure = function() {
            var __method = this;
            var obj=arguments[0];
            var args=obj.toArray(arguments);
            return obj.rFn[obj.rFn.length]=function(){ return __method.apply(obj,obj.toArray(arguments).concat(args));};
        }
    }
    this.init(obj,fn);
}