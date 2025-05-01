//兼容旧版配置
typeof config === 'string' && (config = ROOT_URL + '/include/application', config = {
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
        'path'  : config + '/WDatePicker',
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
            if (L.type(p) === 'object') {
                if (p.obj) {
                    if (p.obj.tagName === 'INPUT' && !/\bWdate\b/.test(p.obj.className)) {
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
        'path'  : config + '/zTree',
        'list'  : {
            '/js/jquery.ztree.core.js' : false,
            '/style/zTreeStyle.css'    : false
        },
        'ready' : function (p, c) {
            if (p && p.expand) {
                for (var i in p.expand) {
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
        'path'  : config + '/echarts',
        'list'  : {
            '/echarts.js' : false
        },
        'init'  : function (p) {
            if (p && p.obj) {
                var obj = this.echarts.init(p.obj);
                delete p.obj;

                for (var i in p) obj[i](p[i]);
                //返回图表实例
                return obj;
            } else {
                //返回图表类
                return this.echarts;
            }
        }
    }
});

/**
 * 描述 : 弹出层, 提示
 * 返回 :
 *      调用方法
 * 作者 : Edgar.lee
 */
config.oDialogDiv = config.tip = {
    'path'  : OF_URL + '/addin/oDialog',
    'list'  : {
        '/js/mouseDrag.js'          : false,
        '/js/oDialogDiv.js'         : false,
        '/style/oDialogDiv.css'     : false
    },
    'ready' : function (p, c) {
        if (!window.jQuery) {
            var temp = {};
            temp['_' + OF_URL + '/att/link/jquery.js'] = false;
            c.list = L.each(temp, c.list);
        }
    },
    'init'  : function (p, c, n) {
        switch (n) {
            case 'tip'   :
                return this.oDialogDiv.tip;
            default      :
                return this[n];
        }
    }
};

/**
 * 描述 : 上传组件
 * 作者 : Edgar.lee
 */
config.oUpload = {
    'path'  : OF_URL + '/addin/oUpload',
    'list'  : {
        '/oUpload.css' : false,
        '/oUpload.js'  : false
    },
    'init'  : function (p) {
        if (p) {
            //返回图表实例
            return this.oUpload(p);
        } else {
            //返回图表类
            return this.oUpload;
        }
    }
};