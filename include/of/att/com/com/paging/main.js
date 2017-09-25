/**
 * 描述 : 对分页操作, 直接调用L.paging()为初始化所有分页, 从分页调用为功能操作
 * 参数 :
 *      type : 仅从分页对象中调用有效
 *          数字 : 跳转到指定页面, 1=第一页, -1=最后一页
 *          +/-n : 以当期页向后/前移动页数, '+1'=下一页, '-1'=上一页
 *          排序 : 排序指定字段, '字段名 ASC/DESC', 如: 'a ASC, b DESC'=正序a,倒序b
 *          条数 : 指定每页展示数量. '数字', 如: '4'=每页显示4条
 *          无值 : 返回共享参数
 *          对象 : 修改共享参数
 *      mode : type为对象时启用
 *          true  = 完全代替参数并刷新
 *          false = 完全代替参数不刷新
 *          null  = 单层替换参数并刷新(默认)
 * 返回 :
 *      
 * 注明 :
 *      已初始化列表结构(list) : [{
 *          'block' : 分页块对象
 *          'items' : 单行数据集 [{
 *              'itemObj' : 一个name='pagingItem'的对象
 *              'parent'  : itemObj对象的父节点
 *          }, ...],
 *          'sorts' : 分页排序状态 {
 *              字段名 : ASC / DESC, ...
 *          }
 *          'fbar'  : 功能条对象, null=没有
 *          'save'  : 保存状态字符串,存入cookie
 *              method + ":" + save + ":" + 默认params = L.json({
 *                  "params" : 最新params, 
 *                  "items"  : 总数据条目数
 *                  "size"   : 每页显示数
 *                  "page"   : 当期页数
 *                  "sort"   : 排序字段 "sorts" 对象
 *              })
 *          'lock'  : 等待锁,true=有请求, false=无请求
 *          'init'  : 是否需要初始化, true=需要, false=无需
 *      }, ...]
 * 作者 : Edgar.lee
 */
L.paging || (function () {
    //已初始化列表, 临时存储, 碎片DIV
    var list = [], temp, frag = document.createElement('div');
    //转换标签
    var wrapMap = {
        "TR" : ["<table><tbody name='root'>", "</tbody></table>"],
        "TD" : ["<table><tbody><tr name='root'>", "</tr></tbody></table>"]
    }
    //获取匹配属性的值
    var getAttrObj = function (obj, name, val, attr, data) {
        var t = {'l' : obj.getElementsByTagName('*')}, result = [];
        //字符串转正则
        typeof val === 'string' && (val = RegExp('^' + val + '$'));

        for(var i = 0, iL = t.l.length; i < iL; ++i) {
            //属性存在 && (不判断值 || 正则匹配)
            if( (t.v = t.l[i].getAttribute(name)) !== null && (val == null || val.test(t.v)) ) {
                //加入返回列表
                result.push(t.l[i]);
                //批量设置属性
                attr && (t.l[i][attr] = data);
            }
        }

        return result;
    }
    //分页事件总响应
    var eventFunc = function (event) {
        var target = event.target, space;
        do {
            if( target === this ) {
                return ;
            } else {
                //命名空间
                space = list[L.search(list, this, 'block')[0]].space;
                switch( target.getAttribute('name') ) {
                    //排序
                    case space + 'pagingSort' :
                        //点击 && 存在排序
                        if( event.type !== 'click' || !(target = target.getAttribute('sort')) ) return ;
                        break;
                    //第一页
                    case space + 'pagingFirst':
                        event.type === 'click' && (target = 1);
                        break;
                    //上一页
                    case space + 'pagingPrev' :
                        event.type === 'click' && (target = '-1');
                        break;
                    //下一页
                    case space + 'pagingNext' :
                        event.type === 'click' && (target = '+1');
                        break;
                    //最后页
                    case space + 'pagingLast' :
                        event.type === 'click' && (target = -1);
                        break;
                    //跳转页
                    case space + 'pagingJump' :
                        //点击跳转
                        if( target.getAttribute('nth') !== null ) {
                            if( event.type === 'click' ) {
                                target = parseInt(target.getAttribute('value'), 10);
                                typeof target === 'number' || (target = 1);
                            }
                            break;
                        }
                    //展示数
                    case space + 'pagingSize' :
                        //全选
                        if( event.type === 'mouseover' ) {
                            target.focus();
                            target.select();
                        //回车
                        } else if( event.type === 'keyup' && event.event.keyCode === 13 ) {
                            if( target.getAttribute('name') === space + 'pagingSize' ) {
                                target = target.value;
                            } else if( !isNaN(parseInt(target.value, 10)) ) {
                                target = parseInt(target.value, 10);
                            }
                        }
                        break;
                }

                if( typeof target !== 'object' ) {
                    L.paging.call(this, target);
                    return false;
                }
            }
        } while ( target = target.parent );
    }
    var layoutFunc = function (data) {
        //当前对象
        var tObj = {
            'b' : list[this].block, 'i' : list[this].items, 's' : list[this].sorts, 
            'd' : L.json(data), 'n' : list[this].space, 'r' : RegExp('(`*){`' +list[this].space+ '(.+?)`}', 'g')
        };

        //json有效
        if( tObj.d ) {
            //操作数据
            data = L.json(data);
            //引用数据
            tObj.b.data = tObj.d.data;

            //设置回调
            tObj.b.setAttribute('method', data.method);
            //设置总长
            tObj.b.setAttribute('items', data.items);
            //当前页面
            tObj.b.setAttribute('page', data.page = parseInt(data.page, 10));
            //共享参数
            tObj.b.setAttribute('params', data.params = L.json(data.params));
            //页面总数
            tObj.b.setAttribute('size', data.size);

            //准备移除单行
            tObj.removes = getAttrObj(tObj.b, 'name', tObj.n + 'pagingItem');
            //最大页面
            tObj.maxPage = data.items > -1 ? Math.ceil(data.items / data.size) : '∞';
            //当前位置
            tObj.w = data.page + '/' + tObj.maxPage;
            //显示位置
            getAttrObj(tObj.b, 'name', tObj.n + 'pagingPage', 'innerHTML', tObj.w);
            //显示尺寸
            getAttrObj(tObj.b, 'name', tObj.n + 'pagingSize', 'value', data.size);
            //显示跳转
            getAttrObj(tObj.b, 'name', tObj.n + 'pagingJump', 'value',
                data.page + 1 > tObj.maxPage ? 1 : data.page + 1
            );

            //隐藏显示总页数
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingCount');
            for(var i in tObj.w) {
                tObj.w[i].innerHTML = data.items;
                tObj.w[i].style.display = data.items > -1 ? '' : 'none';
            }

            //隐藏等待
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingWait');
            for(var i in tObj.w) tObj.w[i].style.display = 'none';

            //隐藏显示最后一页
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingLast');
            for(var i in tObj.w) tObj.w[i].style.display = data.items > -1 ? '' : 'none';

            //隐藏空无
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingEmpty');

            if( L.count(data.data) ) {
                //隐藏无数据
                for(var i in tObj.w) tObj.w[i].style.display = 'none';

                //遍历数据
                for(var i in data.data) {
                    //不以"_"开头的属性进行html编码
                    for(var j in tObj.w = data.data[i]) j.substr(0, 1) === '_' || (tObj.w[j] = L.entity(tObj.w[j]));
                    //使用的数据条
                    tObj.t = tObj.i[i % tObj.i.length];
                    tObj.t.itemObj.outerHTML || (tObj.t.itemObj.outerHTML = document.createElement('div').appendChild(tObj.t.itemObj).parentNode.innerHTML);
                    //反引号, 关键词
                    tObj.w = tObj.t.itemObj.outerHTML.replace(tObj.r, function (all, bac, key) {
                        //单数"`"
                        if( bac.length % 2 ) {
                            return all.substr(Math.ceil(bac.length / 2)) 
                        } else {
                            return bac.substr(bac.length / 2) + (data.data[i][key] == null ? '' : data.data[i][key]);
                        }
                    });

                    //tr td 转换
                    if( tObj.m = wrapMap[tObj.t.itemObj.tagName] ) {
                        frag.innerHTML = 't' + tObj.m[0] + tObj.w + tObj.m[1];
                        tObj.w = getAttrObj(frag, 'name', 'root')[0].firstChild
                    //其它转换
                    } else {
                        frag.innerHTML = 't' + tObj.w;
                        tObj.w = frag.childNodes[1];
                    }
                    //插入父类最后位置
                    tObj.t.parent.appendChild(tObj.w);
                }
            //无数据
            } else {
                //显示无数据
                for(var i in tObj.w) tObj.w[i].style.display = '';
            }

            //移除单行
            for(var i in tObj.removes) tObj.removes[i].parentNode.removeChild(tObj.removes[i]);

            //修改排序图标
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingSort');
            for(var i in tObj.w) {
                for(var j in tObj.t = (tObj.w[i].getAttribute('sort') || '').split(/\s+/)) {
                    //字符串
                    if( typeof tObj.s[tObj.t[j]] === 'string' ) {
                        tObj.w[i].className = tObj.w[i].className.replace(/\b\s*of-paging_sort_(?:asc|desc)\b\s*/g, ' ');
                        tObj.w[i].className += ' of-paging_sort_' + tObj.s[tObj.t[j]].toLowerCase();
                    }
                }
            }

            //保存状态
            if( list[this].save ) {
                L.cookie(list[this].save + L.param({
                    '' : L.json({
                        'params' : data.params,
                        'items'  : data.items,
                        'size'   : data.size,
                        'page'   : data.page,
                        'sort'   : tObj.s
                    })
                }).substr(1));
            }

            //修改跳转按钮
            tObj.w = getAttrObj(tObj.b, 'name', tObj.n + 'pagingJump');
            for(var i in tObj.w) {
                //跳转按钮
                if( (tObj.t = tObj.w[i]).getAttribute('nth') !== null ) {
                    //隐藏元素
                    tObj.t.style.display = 'none';
                    tObj.t = tObj.t.getAttribute('nth').replace(/(`*){`(.+?)`}/g, function (all, bac, key) {
                        //有效跳转次数
                        if( bac.length % 2 === 0 ) {
                            //L.json 代替 eval
                            key = L.json(key.replace(/\bm\b/g, tObj.maxPage)
                                //计算最后结果
                                .replace(/\bp\b/g, tObj.b.getAttribute('page'))
                            );

                            if( 
                                //数值 && > 0
                                typeof key === 'number' && key > 0 &&
                                //小于等于最大页
                                (tObj.maxPage === '∞' || key <= tObj.maxPage)
                            //key值有效
                            ) {
                                //显示元素
                                tObj.t.style.display = '';
                                //赋值计算值
                                tObj.t.setAttribute('value', key);
                                return all.substr(0, bac.length / 2) + key;
                            }
                        }
                    });

                    //展示可见值
                    tObj.w[i].innerHTML = tObj.t;
                }
            }

            //解锁
            list[this].lock = false;

            //触发翻页后事件
            tObj.w = L.data('paging.after');
            for(var i in tObj.w) if( tObj.w[i].call(tObj.b, 'after') === false ) break ;

            L.paging(tObj.b);

            //没有初始化
            if( list[this].init ) {
                //标记初始化
                list[this].init = false;
                //触发初始化事件
                tObj.w = L.data('paging.init');
                for(var i in tObj.w) if( tObj.w[i].call(tObj.b, 'init') === false ) return ;
            }
        }
    }
    var fbarFunc = function (event) {
        //fbar调整
        if( this === document || event.type === 'resize' ) {
            for(var i in list) {
                if(
                    //功能条存在
                    list[i].fbar && (
                        list[i].fbar.currentStyle ? list[i].fbar.currentStyle : getComputedStyle(list[i].fbar, false)
                    ).display !== 'none'
                ) {
                    list[i].fbar.style.width = 
                        (100 - Math.ceil(L.val(list[i].fbar.parentNode.getElementsByTagName('div'), '[-1]').offsetWidth * 100 / list[i].fbar.parentNode.clientWidth)) + '%';
                    if( list[i].fbar.getElementsByTagName('div')[0].offsetWidth < list[i].fbar.offsetWidth - 50 ) {
                        list[i].fbar.style.width = '';
                        L.val(list[i].fbar.getElementsByTagName('label'), '[-1]').style.display = 'none';
                    } else {
                        L.val(list[i].fbar.getElementsByTagName('label'), '[-1]').style.display = '';
                    }
                }
            }
        //fbar展开
        } else {
            this.style.width = '';
        }
        return false;
    }

    //创建样式
    frag.innerHTML = 't<link type="text/css" rel="stylesheet" href="' +OF_URL+ '/att/com/com/paging/main.css">';
    //添加样式
    document.getElementsByTagName('head')[0].appendChild(frag.childNodes[1]);
    //回调脚本
    L.data('paging.init[]', L.data('paging.before[]', L.data('paging.after[]', function(type){
        return (new Function('var type = "' + type + '";\n' + this.getAttribute('event'))).call(this);
    })));

    //分页方法
    L.paging = function (type, mode) {
        //分页调用
        if( this.tagName ) {
            switch( L.type(type) ) {
                //读取参数
                case 'undefined':
                //修改参数
                case 'object'   :
                //内核参数
                case 'null'     :
                    if( type === null ) {
                        type = '+0';
                        mode = false;
                    } else {
                        L.type(temp = L.json(this.getAttribute('params'))) === 'object' || L.count(temp, temp = {});

                        //读取参数
                        if( type === undefined ) {
                            return temp;
                        } else {
                            //单层替换 || 完全替换
                            mode == null ? L.each(temp, type) : (temp = type);
                            //修改参数
                            this.setAttribute('params', L.json(temp));
                            //重新计算总长
                            this.setAttribute('items', '');

                            //不刷新
                            if( mode === false ) {
                                return ;
                            //刷新分页
                            } else {
                                type = '+0';
                            }
                        }
                    }
                case 'string'   :
                    //设置展示数
                    if( /^\d+$/.test(type) ) {
                        this.setAttribute('size', type);
                        type = '+0';
                    //排序字段
                    } else if( !/^(?:\+|-)\d+$/.test(type) ) {
                        temp = {'l' : type.split(/\s*,\s*/), 's' : {}, 'k' : L.search(list, this, 'block')[0]};
                        for(var i in temp.l) {
                            temp.t = temp.l[i].split(' ');
                            //自动切换
                            temp.t[1] || (temp.t[1] = list[temp.k].sorts[temp.t[0]] === 'DESC' ? 'ASC' : 'DESC');
                            //指定切换
                            temp.s[temp.t[0]] = temp.t[1] === 'DESC' ? 'DESC' : 'ASC';
                        }
                        //单层补充
                        list[temp.k].sorts = L.each(temp.s, false, list[temp.k].sorts);
                        type = '+0';
                    }
                    //{'m' : 偏移位置, 'p' : 当期页面}
                    temp = {'m' : parseInt(type, 10), 'p' : parseInt(this.getAttribute('page'), 10)};
                    isNaN(temp.m) && (temp.m = 0);
                    isNaN(temp.p) && (temp.p = 1);
                    //真实位置
                    (type = temp.p + temp.m) < 1 && (type = 1);
                case 'number'   :
                    temp = {
                        //最大条数
                        'i' : parseInt(this.getAttribute('items'), 10),
                        //每页条数
                        's' : parseInt(this.getAttribute('size'), 10),
                        //分页索引
                        'k' : L.search(list, this, 'block')[0],
                        //排序列表
                        't' : []
                    }
                    if( temp.i > -1 && temp.s > 0 ) {
                        //最大页数
                        temp.m = Math.ceil(temp.i / temp.s);
                        //正数溢出纠正
                        type > 0 ? type > temp.m && (type = temp.m) : (type += temp.m + 1);
                    }
                    //负数溢出纠正
                    type < 1 && (type = 1);

                    //分页数据引用
                    temp.d = list[temp.k].sorts,
                    //使用的方法
                    temp.m = this.getAttribute('method');
                    //共享参数
                    temp.p = this.getAttribute('params');
                    if( 
                        //没初始化
                        list[temp.k].init &&
                        //状态保存
                        list[temp.k].save &&
                        //成功读取保存信息
                        (temp.c = L.cookie(list[temp.k].save + '`t'))
                    ) {
                        temp.c = L.json(temp.c);
                        //总条数
                        temp.i = temp.c.items;
                        //显示条数
                        temp.s = temp.c.size;
                        //共享参数
                        temp.p = temp.c.params;
                        //排序状态
                        list[temp.k].sorts = temp.c.sort;
                        //当前页数
                        type = temp.c.page;
                    }

                    //分页基础数据
                    temp.post = {
                        'method' : temp.m,
                        'items'  : temp.i,
                        'size'   : temp.s,
                        'page'   : type
                    };

                    //返回内核参数
                    if( mode === false ) {
                        temp.post.sort = L.each({}, temp.d);
                        return temp.post;
                    //可以发送请求
                    } else if( list[temp.k].lock === false ) {
                        list[temp.k].lock = true;
                        //显示等待
                        temp.w = getAttrObj(list[temp.k].block, 'name', list[temp.k].space + 'pagingWait');
                        for(var i in temp.w) temp.w[i].style.display = '';

                        //触发翻页前事件
                        temp.w = L.data('paging.before');
                        for(var i in temp.w) if( temp.w[i].call(this, 'before') === false ) return ;

                        //整理排序列表
                        for(var i in temp.d) /^[\w.-`]+$/.test(i) && temp.t.push(i + ' ' + temp.d[i]);
                        //排序字段
                        temp.post.sort = temp.t.join(', ');
                        //共享参数
                        temp.post.params = temp.p;

                        //发起请求
                        L.ajax({
                            'url'     : OF_URL + '/index.php',
                            'context' : temp.k,
                            'data'    : {
                                'get'  : 'c=of_base_com_com&a=paging',
                                'post' : temp.post
                            },
                            'success' : layoutFunc
                        });
                    }
            }
        //系统调用
        } else {
            var blocks = getAttrObj(type || (type = document), 'name', /^(\w+:)?pagingBlock$/), index, space;
            for(var i in blocks) {
                temp = blocks[i];
                //父层分页初始会移除子分页
                while( (temp = temp.parentNode) && temp !== type ) {}
                //子分页不初始化
                if( !temp ) continue;

                //开始初始化
                if( !L.search(list, blocks[i], 'block') ) {
                    //命名空间
                    space= (space = blocks[i].getAttribute('name')).indexOf(':') > -1 ?
                        space.split(':')[0] + ':' : '';
                    //读取单行对象
                    temp = getAttrObj(blocks[i], 'name', space + 'pagingItem');

                    //有效分页
                    if( temp.length ) {
                        index = {'block' : blocks[i], 'items' : [], 'sorts' : {}, 'lock' : false, 'init' : true, 'space' : space};

                        for(var j in temp) {
                            //保存节点
                            index.items.push({'itemObj' : temp[j], 'parent' : temp[j].parentNode});
                            //移除节点
                            temp[j].parentNode.removeChild(temp[j]);
                            //删除隐藏属性
                            temp[j].style.display = '';
                        }

                        //读取空无
                        temp = getAttrObj(blocks[i], 'name', space + 'pagingEmpty');
                        //隐藏无数据
                        for(var j in temp) temp[j].style.display = 'none';

                        //保存状态
                        if( index.save = blocks[i].getAttribute('save') || '' ) {
                            temp = blocks[i].getAttribute('method') + ':' + index.save + ':' + (blocks[i].getAttribute('params') || '');
                            index.save = { 'of_base_com_com' : { 'pagingSave' : {} } };
                            index.save.of_base_com_com.pagingSave[temp] = '';
                            index.save = L.param(index.save);
                        }

                        //读取功能条
                        if( index.fbar = getAttrObj(blocks[i], 'name', space + 'pagingFbar')[0] ) {
                            //移动事件
                            L.event(index.fbar, 'mouseover', fbarFunc);
                            index.fbar.style.width = '50%';
                        }

                        //加入列表
                        list.push(index);

                        //点击事件
                        L.event(blocks[i], 'click', eventFunc);
                        //按键事件
                        L.event(blocks[i], 'keyup', eventFunc);
                        //移动事件
                        L.event(blocks[i], 'mouseover', eventFunc);
                        //内置方法
                        blocks[i].paging = L.paging;

                        //显示分页
                        blocks[i].style.visibility = '';
                        //刷新页面
                        L.paging.call(blocks[i], '+0');
                    }
                }
            }
        }
    }

    //完成事件
    L.event(function () {
        L.event(document, 'mouseover', fbarFunc);
        L.event(window, 'resize', fbarFunc);
        L.paging();
    });
})();