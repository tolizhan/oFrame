/**
 * 描述 : L.open 使用的配置文件
 * 结构 : {
 *      插件键 : {
 *          "path"  : 插件根目录
 *          "list"  : 加载的列表 {
 *              js或css地址 : false=不调用, 方法=加载后回调函数,
 *              ...
 *          },
 *          "ready" : 方法=加载js列表前调用
 *          "init"  : 方法=加载完成后初始化
 *      }
 *  }
 * 注明 : 
 *      方法调用通用参数 : (调用参数, 当前配置, 插件名), this=指定的window
 * 作者 : Edgar.lee
 */
config = {
    /**
     * 描述 : 日期插件
     * 参数 : 可选 {
     *      obj    : 绑定对象
     *      type   : 事件类型, 默认click, 如 : focus
     *      params : 插件参数
     *  }
     * 返回 :
     *      日期方法
     * 作者 : Edgar.lee
     */
    'wDate' : {
        'path'  : OF_URL + '/addin/WDatePicker',
        'list'  : {
            '/WdatePicker.js' : false
        },
        'ready' : function (p, c) {
            //已加载
            if (!c.loaded) {
                var script = document.createElement("script");
                script.type = "text";
                script.src = c.path + '/WdatePicker.js';
                this.document.getElementsByTagName("head")[0].appendChild(script);
            }
        },
        'init'  : function (p, c) {
            //标记已加载
            c.loaded = true;
            if( L.type(p) === 'object' ) {
                if( p.obj ) {
                    if( p.obj.tagName === 'INPUT' && !/\bWdate\b/.test(p.obj.className) ) {
                        p.obj.className += ' Wdate';
                    }
                    this.L.event(p.obj, p.type || 'click', function(){
                        WdatePicker(p.params);
                    });
                } else {
                    this.WdatePicker(p.params);
                }
            }
            return this.WdatePicker;
        }
    },

    /**
     * 描述 : 树插件
     * 参数 : 可选 {
     *      "expand" : [
     *          额外扩展(可包含 : excheck, exedit)
     *      ]
     *  }
     * 返回 :
     *      树方法
     * 作者 : Edgar.lee
     */
    'zTree' : {
        'path'  : OF_URL + '/addin/zTree',
        'list'  : {
            '/js/jquery.ztree.core.js' : false,
            '/style/zTreeStyle.css'    : false
        },
        'ready' : function (p, c) {
            if (p && p.expand) {
                for(var i in p.expand) {
                    c.list['/js/jquery.ztree.' +p.expand[i]+ '.js'] = false;
                }
            }
        },
        'init'  : function () {
            return this.$.fn.zTree;
        }
    },

    /**
     * 描述 : 图表插件
     * 参数 : 可选 {
     *      "obj"        : 指定展示的对象
     *      调用的方法名 : 调用参数, 如 "setOption" : {} 设置展示数据
     *  }
     * 返回 :
     *      图表方法
     * 作者 : Edgar.lee
     */
    'eCharts' : {
        'path'  : OF_URL + '/addin/echarts',
        'list'  : {
            '/echarts.js' : false
        },
        'init'  : function (p) {
            if( p && p.obj ) {
                var obj = this.echarts.init(p.obj);
                delete p.obj;

                for(var i in p) obj[i](p[i]);
                //返回图表实例
                return obj;
            } else {
                //返回图表类
                return this.echarts;
            }
        }
    },

    /**
     * 描述 : 上传组件
     * 作者 : Edgar.lee
     */
    'oUpload' : {
        'path'  : OF_URL + '/addin/oUpload',
        'list'  : {
            '/oUpload.css' : false,
            '/oUpload.js'  : false
        },
        'init'  : function (p) {
            if( p ) {
                //返回图表实例
                return this.oUpload(p);
            } else {
                //返回图表类
                return this.oUpload;
            }
        }
    },

    /**
     * 描述 : 数据填充工具
     * 作者 : Edgar.lee
     */
    'oFill' : {
        'path'  : OF_URL + '/addin/oFill',
        'list'  : {
            '/oFill.js'  : false
        },
        'init'  : function (p) {
            return this.oFill;
        }
    }
};

/**
 * 描述 : 磁盘管理, 弹出层, 提示, 上传, 富文本
 * 返回 :
 *      调用方法
 * 作者 : Edgar.lee
 */
config.oFM = config.oDialogDiv = config.tip = config.upload = config.oEditor = {
    'path'  : OF_URL + '/addin/oFileManager',
    'list'  : {
        '/js/mouseDrag.js'          : false,
        '/js/oDialogDiv.js'         : false,
        '/style/oDialogDiv.css'     : false,
        '/js/jsCalloFileManager.js' : function () {
            this.oFileManagerMainDir = OF_URL.substr(ROOT_URL.length) + '/addin/oFileManager';
            this.oFileManager.oFileManagerMainDir = OF_URL + '/addin/oFileManager';
        }
    },
    'ready' : function (p, c, n) {
        switch( n ) {
            case 'oEditor' :
                c.list['/include/oEditor/oEditor.js'] = false;
                break;
            case 'upload'  :
                c.list['/include/uploadify/css/uploadify.css'] = false;
                c.list['/include/uploadify/scripts/jsCalloEditorUploadify.js'] = false;
                c.list['/include/uploadify/scripts/swfobject.js'] = false;
                c.list['/include/uploadify/scripts/jqueryUploadify.js'] = false;
                break;
        }
    },
    'init'  : function (p, c, n) {
        switch( n ) {
            case 'oFM'   :
                return this.oFileManager;
            case 'upload':
                return this.jsCalloEditorUploadify;
            case 'tip'   :
                return this.oDialogDiv.tip;
            default      :
                return this[n];
        }
    }
}