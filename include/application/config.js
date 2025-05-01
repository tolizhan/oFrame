//插件访问根路径
var appUrl = config.slice(0, config.lastIndexOf('/'));

/**
 * 描述 : L.open 使用的配置文件
 * 注明 : 
 *      config 默认为配置文件路径的字符串, 须配置成插件对象的结构 {
 *          插件键 : {
 *              "path"  : 插件根目录
 *              "list"  : 加载的列表 {
 *                  相对path的js或css地址(_开头的为绝对路径或网址) : false=不调用, 方法=加载后回调函数,
 *                  ...
 *              },
 *              "ready" : 方法=加载js列表前调用
 *              "init"  : 方法=加载完成后初始化
 *          }
 *      }
 *      所有方法接受三个参数 : (调用参数, 当前配置, 插件名), this=指定的window
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
        'path'  : appUrl + '/WDatePicker',
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
        'path'  : appUrl + '/zTree',
        'list'  : {
            '/js/jquery.ztree.core.js' : false,
            '/style/zTreeStyle.css'    : false
        },
        'ready' : function (p, c) {
            if (!window.jQuery) {
                var temp = {};
                temp['_' + OF_URL + '/att/link/jquery.js'] = false;
                //将jQuery插入到列表最前面
                c.list = L.each(temp, c.list);
            }
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
        'path'  : appUrl + '/echarts',
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
};