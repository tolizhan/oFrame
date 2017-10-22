//声明L变量
var L;
//未初始化 && 初始化
L === undefined && (L = {

    /**
     * 描述 : ajax 请求
     * 参数 :
     *      value : 字符串=请求地址(data, func参数启用), 对象=请求参数 {
     *          "async"   : true(默认) = 异步请求,false = 同步请求
     *          "context" : 指定方法回调的this对象,默认window
     *          "data"    : 字符串=post数据,{'get':obj|str, 'post':obj|str}=综合数据
     *          "error"   : 错误时回调(请求对象)
     *          "success" : 成功时回调(请求的文本, 请求对象)
     *          "url"     : 请求地址,默认当前网址
     *      }
     *      data : 方法=代替func, 其它=value.data
     *      func : 相当于value.success
     * 返回 :
     *      请求对象
     * 作者 : Edgar.lee
     */
    'ajax'          : function (value, data, func) {
        var result = {
            //ajax对象
            'ajax'   : new (window.ActiveXObject || window.XMLHttpRequest)('Microsoft.XMLHTTP'),
            'config' : {
                //请求地址
                'url'     : window.location.href,
                //true = 异步请求,false = 同步请求
                'async'   : true,
                //字符串=post数据,{'get':{}, 'post':{}}=综合数据
                'data'    : {},
                //指定方法回调的this对象
                'context' : window
            }
        }
        //初始默认方法
        result.config.success = result.config.error = function () {}

        //完整请求(对象)
        if (typeof value === 'object') {
            L.each(result.config, value);
            typeof result.config.data === 'string' && (result.config.data = {'post' : result.config.data});
        //简单请求(字符串)
        } else {
            //不是空字符串 && 设置请求地址
            value && (result.config.url = value);
            switch (L.type(data)) {
                //作为成功回调
                case 'function':
                    func = data;
                    break;
                //作为post值
                case 'string'  :
                    result.config.post = data
                    break;
                //是对象或数组,集成get和post
                case 'object'  :
                case 'array'   :
                    L.each(result.config.data, data);
            }
            //方法有效 && 最为成功回调
            func && (result.config.success = func);
        }

        //post文本化
        typeof result.config.data.post === 'string' || (result.config.data.post = L.param(result.config.data.post));
        //get文本化
        typeof result.config.data.get === 'string' || (result.config.data.get = L.param(result.config.data.get));
        if (result.config.data.get) {
            //追加?和&
            result.config.url += result.config.url.indexOf('?') == -1 ?
                '?' : result.config.url.slice(-1) === '?' ? '' : '&';
            //整合get到url
            result.config.url += result.config.data.get;
        }

        //打开连接地址
        result.ajax.open(result.config.data.post ? 'post' : 'get', result.config.url, result.config.async);
        //请求方式
        result.ajax.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        //ajax标识
        result.ajax.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        result.ajax.onreadystatechange = function() {
            if (result.ajax.readyState === 4) {
                //网络响应 200 || 本地响应 0
                if (result.ajax.status === 200 || result.ajax.status === 0) {
                    result.config.success.call(result.config.context, result.ajax.responseText, result);
                } else {
                    result.config.error.call(result.config.context, result);
                }
            }
        };

        try { result.ajax.send(result.config.data.post); } catch(e) {}
        return result;
    },

    /**
     * 描述 : 判断浏览器
     * 变量 :
     *     isMSIE    : true=IE
     *     isFirefox : true=Firefox
     *     isChrome  : true=Chrome
     * 作者 : Edgar.Lee
     */
    'browser'       : {
        //IE
        'msie'    : /MSIE|Trident/.test(navigator.appVersion),
        //Firefox
        'mozilla' : (navigator.userAgent.indexOf("Firefox") != -1),
        //Chrome
        'webkit'  : (navigator.userAgent.indexOf("Chrome") != -1),
        //version
        'version' : (navigator.userAgent.match(/.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/i) || [])[1]
    },

    /**
     * 描述 : cookie相关操作
     * 参数 :
     *      value : 操作cookie的值
     *          默认   : 返回完整cookie列表
     *          对象   : true=读取, false=删除, 其它=赋值, 如:{'c' : {'get' : true, 'set' : 'k`k', 'del' : false}}
     *          字符串 : 以'; '为分隔符,'`'使用时用'``','`t'=读取,'`f'=删除,其它赋值, 如:c[get]=`t; c[set]=k``k; c[del]=`f
     *      path  : 指定删除设置的使用路径, '/'(默认)=网站根路径, ''=当前页, 其它=指定页面
     *      expi  : 指定设置的过期时间,数字=有效时间(毫秒),'2012-12-21[ 15:14:35]'=指定过期时间,日期对象=使用给定的时间,其它(默认)=关闭浏览器过期
     * 返回 :
     *      读取单个值时 : 返回读取的值
     *      读取多个值时 : 返回[第一个读取值, 第二个, ...]
     *      成功值为字符串, 失败值为undefined
     * 作者 : Edgar.lee
     */
    'cookie'        : function (value, path, expi) {
        //排序用的正则, 删除用的正则
        var sortRegExp = /(.*?)(?:=|$)/, delRegExp;
        //完整cookie(符合php解析方式)
        var fullCookie = L.param(document.cookie.split('; ').sort(function (a, b) {
            return a.match(sortRegExp)[1].length > b.match(sortRegExp)[1].length ? 1 : -1;
        }).join('; ').replace(/%5B/g, '[').replace(/%5D/g, ']'), '; ');
        //返回的结果, 临时存储, 编码键值, 遍历类型, 是否不可遍历的
        var result = [], temp, ekey, type, mode = !L.type(value, true);

        //返回完整cookie
        if (value == null) return fullCookie;

        //路径设置
        path = path == null && (path = '/') || path ? '; path=' + ROOT_URL + path + (path === '/' ? '' : '/'): '';
        //时间戳格式
        if ((temp = String(expi)).match(/^\d+$/)) {
            (expi = new Date).setTime(expi.getTime() + parseInt(temp));
        //日期格式
        } else if (temp = temp.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?: (\d{1,2}):(\d{1,2}):(\d{1,2}))?$/)) {
            expi = new Date(temp[1], temp[2] - 1, parseInt(temp[3], 10), temp[4] || 0, temp[5] || 0, temp[6] || 0);
        }
        //date对象 ? 指定过期 : 关闭过期
        expi = L.type(expi) === 'date' ? '; expires=' + expi.toGMTString() : '';

        //遍历数据, cookie引用, 前缀
        (function (data, cookie, head) {
            for (var i in data) {
                //编码键值
                ekey = encodeURIComponent(i);
                //编码头
                head += encodeURIComponent(head ? '[' +ekey+ ']' : ekey);

                //是字符串 && 文本参数
                if ((type = L.type(data[i])) === 'string' && mode) {
                    //'`'关键词转换
                    type = typeof (data[i] = data[i] === '`t' || data[i] !== '`f' && data[i].replace(/``/g, '`'));
                }

                switch (type) {
                    //可遍历
                    case 'object' : case 'array':
                        arguments.callee(data[i], cookie[i] || {}, head);
                        break;
                    //读取或删除
                    case 'boolean':
                        //读取数据
                        if( data[i] ) {
                            result[result.length] = data[i] = cookie[ekey];
                            break;
                        }
                    //设置数据
                    default       :
                        delRegExp = [RegExp('(?:^|; )(' +head+ '(?=%5B|=)[^=]*)', 'g'), document.cookie];
                        while (temp = delRegExp[0].exec(delRegExp[1])) {
                            document.cookie = temp[1] + '=' +path+ '; expires=Sat, 31 Jan 1970 16:00:00 GMT';
                        }
                        type === 'boolean' || (document.cookie = head +'='+ encodeURIComponent(data[i]) + path + expi);
                }
            }
        //不可遍历 ? 当字符串解析 : 引用对象
        })(mode ? value = L.param(String(value), '; ') : value, fullCookie, '');

        return result.length > 1 ? result : result[0];
    },

    /**
     * 描述 : 获取数组或对象的实际长度
     * 参数 :
     *      value : 指定计算长度的数据
     *      asNum : 仅数字格式的长度(非bool=数组对象互转),true|array=返回数字最大值 + 1,false(默认)|object=实际长度
     * 返回 :
     *      统计的长度
     * 作者 : Edgar.lee
     */
    'count'         : function(value, asNum) {
        //长度统计, 是否真实长度
        var length = 0, real = asNum ? L.type(asNum) === 'object' : !(asNum = false);

        for (var i in value) {
            //转成对象 || 键是数字
            if (real || /^\d+$/.test(i)) {
                //提取新数组
                asNum[i] = value[i];
                real ? ++length : length = Math.max(parseInt(i, 10) + 1, length);
            }
        }

        return length;
    },

    /**
     * 描述 : 全局数据读取与设置
     * 参数 :
     *      name   : 字符串=读取或设置数据(符合L.val规范), null=删除数据
     *      value  : 
     *          设置时 : 指定设置数据
     *          删除时 : 指定删除位置(name的字符串)
     *      unique : 设置并且定位结尾是"[]"时有效
     *          true  = 完全去重
     *          null  = 方法去重,默认
     *          false = 从不去重
     * 返回 :
     *      设置或查询或删除的数据
     * 作者 : Edgar.lee
     */
    'data'          : (function(){
        //数据列表
        var data = {};
        return function (name, value, unique) {
            var temp;

            //删除数据
            if (typeof name !== 'string') {
                //读取原始数据
                temp = L.val(data, value, L.val(data, value));
                //删除结构
                delete temp.pos[temp.key];
                return temp.val;
            //设置数据
            } else if (arguments.length > 1) {
                //去重
                if (unique !== false && /\[\]$/.test(name)) {
                    temp = L.val(data, name.slice(0, -2));

                    if (
                        //方法去重
                        unique == null && typeof value === 'function' && L.search(temp, value) ||
                        //完整去重
                        unique && L.search(temp, value)
                    ) {
                        return value;
                    }
                }
                //写入数据
                return L.val(data, name, value).val;
            //读取数据
            } else {
                return L.val(data, name);
            }
        }
    })(),

    /**
     * 描述 : 遍历操作
     * 参数 :
     *      arg0 : 指定要遍历的数组,对象
     *      arg1 : 遍历方式,对象数组=单层合并,true=深层合并,false=单层补充,null=深层补充,方法=this为value的调用(arg0键, arg0, ...argX)
     *      argX : 若干元素
     * 返回 :
     *      arg1为方法时,返回方法返回值并终止遍历
     *      arg1遍历方式,返回合并完成的对象
     * 作者 : Edgar.Lee
     */
    'each'          : (function(){
        //数组方法, 替换方式, 回调方法, 默认回调
        var arg, type, call, func = function(data, arg){
            for (var i in arg) {
                //单层合并
                if (type === undefined) {
                    data[i] = arg[i];
                //深度合并 || 深层补充
                } else if (type || type === null) {
                    //参数可遍历 && 数据可遍历
                    if (L.type(arg[i], true) && L.type(data[i], true)) {
                        //深度遍历
                        func(data[i], arg[i]);
                    //深度合并 || 数据无值
                    } else if (type || data[i] === undefined) {
                        data[i] = arg[i];
                    }
                //单层补充
                } else if (data[i] === undefined) {
                    data[i] = arg[i];
                }
            }
        }

        return function () {
            //type(undefined = 单层合并, null=深层补充)
            arg = arguments, type = arg[1] ? undefined : null;

            switch (typeof arg[1]) {
                //自定义回调
                case 'function':
                    //使用指定回调
                    call = Array.prototype.splice.call(arg, 0, 2, 0, arg[0])[1];
                    for(arg[0] in arg[1]) {
                        if( (arg[0] = call.apply(arg[1][arg[0]], arg)) !== undefined ) return arg[0];
                    }
                    break;
                //true=深度替换, false=填补空缺
                case 'boolean' :
                    type = Array.prototype.splice.call(arg, 1, 1)[0];
                //将arg1~argN赋值到arg0
                default        :
                    //提取原始对象
                    call = Array.prototype.shift.call(arg) || {};
                    //遍历数据 IE6 arguments 不能forin
                    for (var i = 0, iL = arg.length; i < iL; ++i) func(call, arg[i]);
                    return call;
            }
        }
    })(),

    /**
     * 描述 : html实体编码互转
     * 参数 :
     *      value  : 指定编码解码数据
     *      encode : true(默认)=编码(&lt;), false=解码(<)
     * 返回 :
     *      编解码后的字符串
     * 作者 : Edgar.lee
     */
    'entity'        : function (value, encode) {
        var t = document.createElement("div");

        //解码
        if (encode === false) {
            t.innerHTML = ('-' + value).replace(/<br>|\r\n|\r|\n/ig, '\1');
            return (t.innerText || t.textContent).substr(1).replace(/\x01/ig, '\n');
        //编码
        } else {
            t[t.innerText === '' ? 'innerText' : 'textContent'] = value;
            return t.innerHTML
                .replace(/"/ig, '&quot;')
                .replace(/'/ig, '&#039;')
                .replace(/\(/ig, '&#040;')
                .replace(/\)/ig, '&#041;')
                .replace(/\./ig, '&#046;')
                .replace(/=/ig, '&#061;')
                .replace(/<br>/ig, '\n');
        }
    },

    /**
     * 描述 : 事件操作
     * 参数 :
     *      添加监听 (元素, 类型, 方法)
     *      移除监听 (元素, 类型, 方法, false)
     *      触发事件 (元素, 类型, 模拟 = {})
     *      移除事件 (元素, 类型, false)
     *      事件对象 (指定窗体window默认当前窗体) 失败返回false
     *      ready监听(方法)
     * 注明 :
     *      事件列表结构(list) : [{
     *          "target" : 事件源
     *          "event"  : {
     *              事件类型 : {
     *                  "call" : 监听使用的函数
     *                  "back" : 回答列表 [
     *                      触发函数, ...
     *                  ]
     *          }
     *      }, ...]
     *      事件参数结构       : {
     *          "target"    : 事件源
     *          "type"      : 事件类型
     *          "event"     : 原始事件
     *          "isDefault" : 是(true)否(false)默认动作
     *          "isBubble"  : 是(true)否(false)默认冒泡
     *      }
     * 作者 : Edgar.lee
     */
    'event'         : (function () {
        //事件列表, 触发时的参数, 是否IE标准, 别名列表
        var list = [], param, isIe = !!document.fireEvent, alias = isIe ? {} : {
            //鼠标穿入
            'mouseenter' : 'mouseover',
            //鼠标穿出
            'mouseleave' : 'mouseout'
        };

        //创建并获取事件源的key
        var core = function (obj, type, func, isAdd) {
            //对象key, 节点window, 事件引用{'call' : , 'back' : }
            var key = false, objWin = L.window(obj), index;

            //解决 IE 多次 resize 问题
            if (isIe && type === 'resize' && obj === objWin) {
                //IE < 9 的 body 还未加载完成
                if (obj.document.body === null) {
                    //自定义参数转移
                    param && (func = param, param = undefined);
                    L.event(function () {
                        //初始化完成后再添加参数
                        L.event(obj, type, func, isAdd);
                    });
                    return ;
                }

                if (!(index = objWin.document.getElementsByTagName('L.event.resize')[0])) {
                    objWin.document.body.appendChild(index = document.createElement('L.event.resize'));
                    L.each(index.runtimeStyle, {'width': '50%', 'height': '50%', 'position' : 'absolute', 'top' : '-100%'});

                    //解决 IE 6 百分比高度失效
                    if (L.browser.version == 6 && objWin.document.body.currentStyle.height === 'auto') {
                        objWin.document.body.style.height = '90%';
                    }
                }
                //转移对象
                obj = index;
            }

            for (var i in list) {
                //找到 && 读取key
                list[i].target === obj && (key = i);
            }
            //未找到时创建新的
            key === false && (list[key = list.length] = {'target' : obj, 'event'  : {}});
            //事件初始并引用
            index = list[key].event[type] || (list[key].event[type] = {'back' : []});

            //函数操作
            if (func) {
                for (var i in index.back) {
                    //找到函数
                    if (index.back[i] === func) {
                        //添加操作 || 删除函数
                        isAdd || index.back.splice(i, 1);
                        //列表不空 || 删除事件
                        index.back.length || core(obj, type, false);
                        return ;
                    }
                }

                //需要监听
                if (!index.call) {
                    index.call = function ( evt ) {
                        //备份事件
                        var o = {'b' : L.event.e};

                        switch (type) {
                            //标准浏览器穿入穿出
                            case 'mouseenter': case 'mouseleave':
                                if (!isIe && (o.t = evt.relatedTarget)) {
                                    do {
                                        if (o.t === obj) return ;
                                    } while ( o.t = o.t.parentNode );
                                }
                                break;
                        }

                        o.c = L.each({'type' : type, 'event' : L.event.e = o.t = evt, 'isDefault' : true, 'isBubble' : true}, true, param);
                        //事件初始化
                        o.c.target || (o.c.target = o.c.event.target || o.c.event.srcElement);
                        //清空参数
                        param = undefined;

                        //遍历调用
                        for (var i in index.back) {
                            if (index.back[i].call(obj, o.c) === false) {
                                //禁止默认, 禁止冒泡
                                o.c.isDefault = o.c.isBubble = false;
                                break;
                            }
                        }

                        o.c.isDefault || (o.t.preventDefault ? o.t.preventDefault() : o.t.returnValue = false);
                        o.c.isBubble || (o.t.stopPropagation ? o.t.stopPropagation() : o.t.cancelBubble = true);
                        //还原事件
                        L.event.e = o.b;
                    }

                    isIe ? obj.attachEvent(alias[type] || 'on' + type, index.call) : obj.addEventListener(alias[type] || type, index.call, false);
                }

                //添加操作
                if (isAdd) {
                    //追加函数
                    index.back[index.back.length] = func;
                    if (type === 'ready' && core.isRead !== false) {
                        core.isRead ? func.call(obj, {'target' : obj, 'type' : type, 'event' : {}}) : (function () {
                            try {
                                //IE && 调用元素是否出错
                                isIe && objWin.document.documentElement.doScroll('left');
                                if( {'interactive' : 1, 'complete' : 1}[objWin.document.readyState] ) {
                                    return core.isRead = !core(obj, type);
                                }
                            } catch (e) {}
                            setTimeout(arguments.callee, 50);
                        //标记read
                        })( core.isRead = false );
                    }
                }
            //删除事件
            } else if (func === false) {
                index.call && (isIe ? 
                    obj.detachEvent(alias[type] || 'on' + type, index.call) : obj.removeEventListener(alias[type] || type, index.call, false)
                );
                delete list[key].event[type];
                //事件不为空 || 移除对象
                L.count(list[key].event) || list.splice(key, 1);
            //触发事件
            } else {
                //IE
                if (isIe) {
                    //系统触发
                    try{
                        obj.fireEvent(alias[type] || 'on' + type);
                    //触发自定义事件
                    } catch(e) {
                        objWin = {};
                        //可触发 && 触发
                        (e = L.val(list, key + '.event.' + type + '.call') || false) && e(objWin);
                        //禁止冒泡 || 有父节点 && 冒泡触发
                        objWin.cancelBubble || obj.parentNode && core(obj.parentNode, type);
                    }
                //标准
                } else {
                    (objWin = document.createEvent('Events')).initEvent(alias[type] || type, true, true);
                    obj.dispatchEvent(objWin);
                }

                //事件不空 || 删除结构
                index.call || list.splice(key, 1);
            }
        }

        return function (a, b, c, d) {
            var p;
            typeof a === 'function' && (c = a, a = document, b = 'ready');

            //事件操作
            if (b) {
                //多事件拆分
                b = b.match(/^\s*(.*?)\s*$/)[1].split(/\s+/);
                //保存触发参数
                L.type(c, true) && (p = c, c = null);

                //事件批量操作
                for (var i in b) (param = p, core(a, b[i], c, d == null || d));
            //获取元素
            } else {
                b = arguments.callee.caller, d = {'l' : []};
                do {
                    //第一个参数
                    c = b.arguments[0];
                    //是对象 && (旧IE事件 || 事件对象)
                    if (L.type(c) === 'object' && (!c.toString || (c + '').indexOf('Event') > 0)) {
                        d.e = c; 
                        break;
                    } else {
                        //保存遍历信息, 标记已遍历
                        d.l[d.l.length] = b, b.__eached = true;
                    }
                } while ( (b = b.caller) && !b.__eached );
                //清除遍历标记
                for (var i in d.l) delete d.l[i].__eached;

                //读取成功 || 尝试两套备用方案
                d.e || (d.e = (a || (a = window)).event || L.val(a, 'L.event.e'));
                //读取成功 && (标准结构 || 转为标准)
                d.e && (d.e.target || (d.e.target = d.e.srcElement));

                return d.e || false;
            }
        }
    })(),

    /**
     * 描述 : json的编码与解码,失败返回undefined
     * 参数 :
     *     value  : 对象=转成字符串, 字符串=转成对象
     *     encode : true=强制编码成字符串
     * 作者 : Edgar.Lee
     */
    'json'          : (function() {
        var escapable = /[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;
        //特殊字符转换
        var meta = {'\b': '\\b', '\t': '\\t', '\n': '\\n', '\f': '\\f', '\r': '\\r', '"': '\\"', '\\': '\\\\'};
        var quote = function (string) {
            escapable.lastIndex = 0;
            return escapable.test(string) ? '"' + string.replace(escapable, function(a) {
                var c = meta[a];
                return typeof c === 'string' ? c: '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice( - 4)
            }) + '"' : '"' + string + '"'
        }
        var str = function (key, holder) {
            var i, k, v, length, partial, value = holder[key];

            if (value == null) return 'null';

            switch (typeof value) {
                case 'string':
                    return quote(value);
                case 'number':
                    return isFinite(value) ? String(value) : 'null';
                case 'boolean':
                    return String(value);
                case 'object':
                    partial = [];
                    if (L.type(value) === 'array' && L.count(value) === value.length && L.count(value, true) === value.length) {
                        length = value.length;
                        for (i = 0; i < length; i += 1) {
                            partial[i] = str(i, value) || 'null'
                        }
                        v = partial.length === 0 ? '[]' : '[' + partial.join(',') + ']';
                        return v
                    }
                    for (k in value) {
                        if (Object.prototype.hasOwnProperty.call(value, k)) {
                            v = str(k, value);
                            if (v) {
                                partial.push(quote(k) + ':' + v)
                            }
                        }
                    }
                    v = partial.length === 0 ? '{}' : '{' + partial.join(',') + '}';
                    return v
            }
        }

        return function (value, encode) {
            try {
                //强制编码 || 不是字符串
                if (encode || typeof value !== 'string') {
                    return str('', {'': value}).replace(/</g, '\\u003C').replace(/>/g, '\\u003E');
                //json解码
                } else {
                    return (new Function('return ' + value + ';'))();
                }
            } catch(e) {}
        }
    }()),

    /**
     * 描述 : 打开对应插件, 根据 OF_URL/addin/config.js 文件
     * 参数 :
     *      name  : 插件名称
     *      param : 传递参数, 默认undefined
     *      win   : 指定window, 默认当前
     * 返回 :
     *      返回对应组件
     * 注明 :
     *      缓存加载结构(cache) : [{
     *          "obj"  : window对象,
     *          "list" : 缓存js,css列表 {
     *              加载的js或css路径 : true
     *          }
     *      }, ...]
     * 作者 : Edgar.lee
     */
    'open'          : (function () {
        //已加载的js列表, 可加载的插件, 同步加载方法
        var cache = [], config, sync = function (url, win) {
            //查找使用的缓存
            var list = L.search(cache, win, 'obj')[0];
            //初始化并读取缓存
            list ? list = cache[list].list : cache[cache.length] = {'obj' : win, 'list' : list = {}};

            //已加载过
            if (list[url]) {
                //无需加载
                return false;
            //未加载过
            } else {
                L.ajax({'url' : OF_URL + '/addin' + url, 'async' : false, 'success' : function (code, ajax) {
                    //css
                    if (/.*css$/.test(url)) {
                        var div = win.document.createElement("div");
                        //提取url(xxx)
                        code = code.replace(/url\((\'|\"|)([\w\/\.]*)\1\)/ig, function(){
                            //计算出绝对路径
                            return 'url("' + arguments[2].replace(/^(?:\.\/|\/)*((?:\.\.\/)+|(?!\w+:))/, function(){
                                var regObj = new RegExp('(/[^/]+){' +(arguments[1].length/3 + 1)+ '}$');
                                return ajax.config.url.replace(regObj, '') + '/';
                            }) + '")';
                        });
                        div.innerHTML = 'div<div><style type="text/css">' +code+ '</style></div>';
                        win.document.getElementsByTagName('head')[0].appendChild(div.getElementsByTagName('div')[0].firstChild);
                    //js
                    } else {
                        win ? (win.execScript || win.eval)(code) : eval(code);
                    }
                }});
                //标记已加载
                return list[url] = true;
            }
        };

        return function (name, param, win) {
            //运行配置
            var run;
            config || sync('/config.js', null), win || (win = window);

            //插件存在
            if (run = config[name]) {
                //加载前调用
                run.ready && run.ready.call(win, param, run, name);
                for(var i in run.list) {
                    sync(i, win) && run.list[i] && run.list[i].call(win, param, run, name);
                }
                //初始化调用
                return run.init.call(win, param, run, name);
            }
        }
    })(),

    /**
     * 描述 : URL请求参数互转
     * 参数 :
     *      value : 字符串=转成对象,对象=转成字符串
     *      separ : 分隔符,默认'&'
     * 作者 : Edgar.lee
     */
    'param'         : function (value, separ) {
        //结果, 临时存储, 匹配URL [内容,键,字符数组,值]
        var result, temp, math;
        //分隔符初始化
        typeof separ === 'string' || (separ = '&');

        //转成对象
        if (typeof value === 'string') {
            math = new RegExp('(?:^|' +separ+ ')((?:(?!' +separ+ ')[^\\[\\]=])+)((?:\\[(?:(?!' +separ+ ')[^\\]=])*\\])*)(?:=((?:(?!' +separ+ ').)*))?', 'g');
            result = {};

            while (temp = math.exec(value)) {
                //键转义
                temp[1] = decodeURIComponent(temp[1].replace(/\+/g, '%20'));
                //值为undefined || 初始化''
                temp[3] = temp[3] ? decodeURIComponent(temp[3].replace(/\+/g, '%20')) : '';

                //字符数组
                if (temp[2]) {
                    //转成实体数组
                    temp[2] = temp[2].substr(1, temp[2].length - 2).split('][');
                    //初始键并引用
                    temp.index = L.type(result[temp[1]], true) ? result[temp[1]] : (result[temp[1]] = {});

                    for (var i = 0, iL = temp[2].length - 1; i <= iL; ++i) {
                        //当前key值
                        temp.key = temp[2][i] ?
                            decodeURIComponent(temp[2][i].replace(/\+/g, '%20')) : L.count(temp.index, true);

                        //最后一个元素
                        if (i === iL) {
                            temp.index[temp.key] = temp[3];
                        //还有其它元素
                        } else {
                            temp.index = L.type(temp.index[temp.key]) === 'object' ? 
                                temp.index[temp.key] : (temp.index[temp.key] = {});
                        }
                    }
                //键没有数组
                } else {
                    result[temp[1]] = temp[3];
                }
            }
        //转成文本
        } else {
            result = [];
            (function (value, preStr) {
                for (var i in value) {
                    temp = encodeURIComponent(i), math = true;
                    //有前缀 ? 前缀[键] : 键
                    temp = preStr ? preStr + '[' +temp+ ']' : temp;

                    //可遍历 && 不为空
                    if (L.type(value[i], true) && (math = L.count(value[i]))) {
                        arguments.callee(value[i], temp);
                    } else {
                        result[result.length] = temp + '=' + (math && value[i] ? encodeURIComponent(value[i]) : '');
                    }
                }
            })(value, '');
            result = result.join(separ);
        }

        return result;
    },

    /**
     * 描述 : 在对象中搜索指定值
     * 参数 :
     *      data  : 指定被搜索的对象
     *      value : 指定要查找的数据
     *      area  : 指定查询区域, 默认='', 如 : 'm.c' 在data[key].m.c中搜索
     * 返回 :
     *      成功返回包含索引键的数组, 失败返回false
     * 作者 : Edgar.lee
     */
    'search'        : function (data, value, area) {
        //返回的结果集
        var result = [];

        for (var i in data) (area ? L.val(data[i], area) : data[i]) === value && (result[result.length] = i);

        return result.length ? result : false;
    },

    /**
     * 描述 : 获取指定参数的原型,类似于typeof
     * 参数 :
     *      value : 指定获取原型的参数
     *      type  : true=是数组或对象, false=是否引用传值, null(默认)=返回原型字符串
     * 作者 : Edgar.lee
     */
    'type'          : function(value, type){
        //匹配类型
        var regex = / (array|object|function|date|regexp|number|boolean|string)/;
        value = value == null ? 
            value + '' : (regex.exec(Object.prototype.toString.call(value).toLowerCase()) || [, 'object'])[1];

        return type == null ? value : RegExp(regex.toString().substr(3, type ? 12 : 33)).test(value);
    },

    /**
     * 描述 : 可以使用'm[-1].c'方式读取或设置对象
     * 参数 :
     *      obj : 对象=操作的对象(默认=window),字符串=参数下移(代替pos)
     *      pos : 定位字符串,'.'代表对象操作,'[负数或空]'代表从尾部开始的位置
     *          '[].`'是关键词,使用时前面加'`',如:'m.a`.c'代表m['a.c']
     *          '[xxx]'的值不存在时创建数组,'.'创建对象
     *          '[]'和'.'对数组和对象均适用,如:window.m = {'c' : []}; L.var('m[c].d', 5); m.c.d === 5;
     *      val : 不写=读取,其它=赋值
     * 返回 :
     *      读取时,返回读取数据,失败返回undefined
     *      赋值时,返回{'obj' : obj对象, 'val' : val值, 'pos' : pos位置的数据}对象, 'key' : 最后一个键
     * 作者 : Edgar.lee
     */
    'val'           : function (obj, pos, val) {
        //匹配数据[全部,字段,是否数组模式], 匹配正则
        var match, regex = /((?:[^\[\].`]|(?:``)*`(?:.|$))*)(])?/g;
        //obj引用{obj : 当前对象, key : 最后匹配值}
        var index = {};

        //obj字符串 && 参数后延
        typeof obj === 'string' && (val = pos, pos = obj, obj = window, ++arguments.length);
        index.obj = obj || (obj = {});

        pos.substr(0, 1) === '[' && (regex.lastIndex = 1);
        //读取下一键值
        while (match = regex.exec(pos)) {
            //更新引用对象
            index.key === undefined || (index.obj = index.obj[index.key]);

            //提取并优化键值
            index.key = match[1].replace(/`(.|\s)/g, '$1');
            //是数组 && (负数 || '')
            if (match[2] && /^(?:-\d+|)$/.test(index.key)) {
                //负数或''键值转换成正数键
                index.key = L.count(index.obj, true) + (index.key ? parseInt(index.key, 10) : 0);
                index.key < 0 && (index.key = 0);
            }

            //读取模式
            if (arguments.length === 2) {
                //对象无效
                if (index.obj[index.key] === undefined) return ;
            } else {
                //可引用 || 赋值 = (数组 ? [] : {})
                L.type(index.obj[index.key], false) || (index.obj[index.key] = match[2] ? [] : {});
            }

            switch (pos.substr(regex.lastIndex, 1)) {
                //(结束 || '[' || '.') && 分析移动一位
                case '': case '[': case '.': regex.lastIndex += 1;
            }
        }

        //读取模式
        if (arguments.length === 2) {
            return index.obj[index.key];
        //设置模式
        } else {
            return {'obj' : obj, 'val' : index.obj[index.key] = val, 'pos' : index.obj, 'key' : index.key};
        }
    },

    /**
     * 描述 : 获得任意节点的window对象
     * 参数 :
     *      node : 指定节点
     * 返回 :
     *      返回window对象,没找到返回undefined
     * 作者 : Edgar.Lee
     */
    'window'        : function(node) {
        return (node.window && node.window === node.window.window && node) || 
            (node = node.ownerDocument || node).defaultView || node.parentWindow;
    },

    /*
    * 描述 : 依赖于此对象的自定义扩展
    * 作者 : Edgar.Lee
    */
    'extension'  : {}
}, L.val('$.browser', L.browser));