/**
 * 描述 : 数据填充
 * 参数 :
 *      node : 填充的节点, 可以是数组
 *      data : 匹配数据
 *      argv : 配置参数 {
 *          "bind" : 指定依照的属性值, 数组, 默认=["-data", "name"]
 *          "mark" : 锚点注释, 字符串, 默认="-data", 如: <!---data=bind-->
 *              写法符合url参数编码规则, 填充的数据会替换紧邻的弟文本节点
 *          "call" : 绑定回调, 默认无 {
 *              "fill" : 填充数据时回调 {
 *                  绑定值 : 回调函数, 返回false停止默认匹配, this=结构 {
 *                      "type"  : 触发类型
 *                      "bind"  : 当前匹配键
 *                      "data"  : 传入的匹配数据
 *                      "argv"  : 匹配参数
 *                      "value" : 当前匹配值(回调中可修改)
 *                      "node"  : 匹配的节点
 *                  }
 *              },
 *              "each" : 遍历数据时回调 {
 *                  绑定值 : 回调函数, 返回false停止默认匹配, this=结构 {
 *                      "type"  : 触发类型
 *                      "bind"  : 当前匹配键
 *                      "data"  : 传入的匹配数据
 *                      "argv"  : 匹配参数
 *                      "value" : 当前匹配值(回调中可修改)
 *                      "node"  : 匹配的节点, 可修改(单标签或注释时将依次将模版加入上方)
 *                      "tmpl"  : 遍历模版, 可修改(模版包在一个标签中)
 *                  }
 *              }
 *          }
 *      }
 * 注明 :
 *      默认匹配规则 : 
 *          选框 : 相同值设置选中状态
 *          图片 : 设置src
 *          表单 : 设置value值
 *          其它 : 设置text值
 *      自动循环 : 当绑定值为数组, 0元素为对象时, 遍历数据并克隆子元素, 如: {
 *          绑定 : [
 *              {xxx: yyy, ...},
 *              ...
 *          ]
 *      }
 * 作者 : Edgar.lee
 */
var oFill = function (node, data, argv) {
    var clear = [];
    var fillList = [], temp, sele, fill, bind, value, params;
    //无子节点标签(1=单标签, 2=仅包含文本, 4=包含注释及文本)
    var notChrTag = {
        'script' : 2 /*脚本标签*/, 'noscript' : 2 /*代替脚本标签*/, 
        'noframes' : 2 /*代替框架标签*/, 'style' : 2 /*样式标签*/, 
        'textarea' : 2 /*文本域标签*/, 'title' : 2 /*标题标签*/,  
        'option' : 4/*列表选项(不包含非注释的html标签)*/,
        '!text' : 1 /*字符串标签*/, '!doctype' : 1 /*文档类型标签*/, 
        '!--' : 1 /*注释标签*/, 'base' : 1, 'meta' : 1, 'link' : 1, 
        'hr' : 1, 'br' : 1, 'basefont' : 1, 'param' : 1, 'img' : 1, 
        'area' : 1, 'input' : 1, 'isindex' : 1, 'col' : 1
    };
    //val值填充
    var fillDataTag = {'INPUT' : 1, 'SELECT' : 1, 'TEXTAREA' : 1}
    //填充数据
    var fillData = function () {
        params = {
            'value' : value,
            'node'  : this
        };

        //回调
        if (callFunc('fill')) {
            return ;
        //注释
        } else if (this.nodeType === 8) {
            //文本节点
            if (this.nextSibling && this.nextSibling.nodeType === 3) {
                //替换同行文本到换行
                this.nextSibling.data = params.value;
            } else {
                //添加文本节点
                $(this).after(document.createTextNode(params.value));
            }
        //选框
        } else if (
            this.tagName === 'INPUT' && 
            (this.type === 'radio' || this.type === 'checbox')
        ) {
            this.checked = this.value == params.value;
        //图片
        } else if (this.tagName === 'IMG') {
            this.src = params.value;
        //表单
        } else if (fillDataTag[this.tagName]) {
            $(this).val(params.value);
        //其它
        } else {
            $(this).text(params.value);
        }
    }
    //触发回调, 调用成功返回true, 失败false
    var callFunc = function (type) {
        var result;
        if (argv.call && argv.call[type] && argv.call[type][bind]) {
            result = argv.call[type][bind].call($.extend(params, {
                "type"  : type,
                'data'  : data,
                'bind'  : bind,
                'argv'  : argv
            }));
        }
        //有返回值终止
        return result !== undefined;
    }
    //获取匹配节点
    var getNode = function (node) {
        temp = argv.mark + '=' + bind;
        node = $(node);

        return node.find(sele).add(
            node.find('*').add(node).contents().filter(function() {
                //是注释
                return this.nodeType === 8 &&
                    //是锚点注释
                    decodeURIComponent(this.data.replace(/\s*$/,"")) === temp
            })
        );
    }

    //IE 版本, 非IE 99
    if (!arguments.callee.isIE) {
        temp = navigator.userAgent.match(/MSIE (\d+)/) || [];
        arguments.callee.isIE = parseInt(temp[1]) || 99;
    }

    //防止 IE 6 克隆节点回收
    if (arguments.callee.isIE === 6) {
        arguments.callee.clear || (arguments.callee.clear = []);
        clear = arguments.callee.clear;
    }

    //填充节点转成数组
    node = node.get ? node.get() : ($.type(node) === 'array' ? node : [node]);
    //加入待填充列表
    fillList.push({'node' : node, 'data' : data});
    //初始化配置参数
    argv = $.extend({
        'bind' : ['-data', 'name'],
        'mark' : '-data'
    }, argv);

    //开始填充
    while (fill = fillList.shift()) {
        //遍历数据
        for (bind in fill.data) {
            //数据引用
            value = fill.data[bind];
            //匹配选择器
            sele = '[' + argv.bind.join('=""],[') + '=""]';
            sele = sele.replace(/(=")/g, '$1' + bind);

            //子节点填充
            if ((temp = $.type(value)) === 'object') {
                //加入待填充列表
                fillList.push({'node' : getNode(fill.node).get(), 'data' : value});
            //子节点克隆
            } else if (temp === 'array' && $.type(value[0]) === 'object') {
                for (var i in fill.node) {
                    //读取第一个匹配节点
                    node = getNode(fill.node[i]).eq(0);

                    //遍历前回调
                    params = {
                        'value' : value,
                        'node'  : node.get(0),
                        'tmpl'  : node.clone(true).get(0)
                    };

                    //清空节点(兼容 IE6 写法, 原节点信息销毁会导致克隆节点属性销毁)
                    clear.push(node.contents().remove());

                    //回调
                    temp = callFunc('each');
                    params.node = $(params.node);
                    params.tmpl = $(params.tmpl);

                    //回调失败
                    if (!temp) {
                        for (var n in params.value) {
                            //加入待填充列表
                            fillList.push({
                                'node' : params.tmpl.clone(true).get(), 
                                'data' : params.value[n], 'clone' : params.node
                            });
                        }
                    }

                    //节点没有加入界面 || 就加入界面
                    params.node.parent().length || node.after(params.node).remove();
                }
            //本节点填充
            } else {
                //遍历节点
                for (var i in fill.node) {
                    getNode(fill.node[i]).each(fillData);
                }
            }
        }
        //克隆节点
        if (fill.clone && (temp = fill.clone.get(0))) {
            //是元素节点 && 可以包含子节点
            if (temp.nodeType === 1 && !notChrTag[temp.nodeType]) {
                fill.clone.append($(fill.node).children('*'));
            } else {
                fill.clone.before($(fill.node).children('*'));
            }
        }
    }
}