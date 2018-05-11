/**
 * 描述 : 权限主页交互脚本
 * 注明 : 
 *      各分页的参数结构 : {
 *          "search"  : 搜索条件
 *          "select"  : {
 *              选中的复选框ID : 对应的分页数据 {}, 
 *              ...
 *          },
 *          "action"  : 操作动作,一次性使用 "del"=批量删除, "ice"=批量冻结
 *          "save"    : 保存或修改指定数据
 *          "keys"    : 保存或修改时每个分页焦点条目ID
 *          "tip"     : 翻页后的提示信息
 *          "linkage" : 联动选中键 {
 *              "user" : 对应选中为键 || "",
 *              ...
 *          }
 *          "linksel" : 被联动选中的复选框 {
 *              被联动的ID : 对应的分页数据 {}
 *              ...
 *          }
 *      }
 * 作者 : Edgar.lee
 */
var ofBaseSsoMain = {
    /**
     * 描述 : 当前帐号权限
     * 结构 : {
     *          权限名   : 权限数据 {},
     *          "xxxMod" : 修改数据 {
     *              "range" : 包项为 || 关系 [
     *                  {
     *                      分页数据键 : 匹配的正则,如/^test$/
     *                  }, ...
     *              ]
     *          },
     *          "xxxDel" : 删除数据 {
     *              "range" : 包项为 || 关系 [
     *                  {
     *                      分页数据键 : 匹配的正则,如/^test$/
     *                  }, ...
     *              ]
     *          },
     *          "xxxIce" : 冻结数据 {
     *              "range" : 包项为 || 关系 [
     *                  {
     *                      分页数据键 : 匹配的正则,如/^test$/
     *                  }, ...
     *              ]
     *          },
     *          "xxxAdd" : 添加数据 {
     *              "range" : 包项为 || 关系 [
     *                  {
     *                      分页数据键 : 匹配的正则,如/^test$/
     *                  }, ...
     *              ]
     *          },
     *          ...
     *      }
     * 作者 : Edgar.lee
     */
    'permit' : ofBaseSsoMain,

    /**
     * 描述 : 当前状态列表
     * 结构 : {
     *          "selNode" : {
     *              "type" : 分类型
     *              "key"  : 选中条目的ID
     *          },
     *          "editBlock" : 信息编辑区 {
     *              "user" : {
     *                  "selItem"  : 选中的对象 {
     *                      "node" : 选中的节点(key属性为ID),
     *                      "key"  : 选中条目的ID
     *                  }
     *                  "editObj"  : 编辑区的对象
     *                  "titleObj" : 分区的头对象
     *                  "linkBox"  : 选择复选框时,联动到编辑的分页
     *                  "linkage"  : 关联的分区
     *                  "saveLink" : 保存联动需要的权限名
     *              },
     *              ....
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    'state' : {
        'editBlock' : {
            'user'  : {
                'editObj'  : document.getElementById('userEdit'),
                'titleObj' : document.getElementById('userTitle'),
                'linkBox'  : '',
                'linkage'  : ['bale', 'pack'],
                'saveLink' : 'userPack'
            },
            'bale'  : {
                'editObj'  : document.getElementById('baleEdit'),
                'titleObj' : document.getElementById('baleTitle'),
                'linkBox'  : 'user',
                'linkage'  : ['realm', 'pack'],
                'saveLink' : 'balePack'
            },
            'realm' : {
                'editObj'  : document.getElementById('realmEdit'),
                'titleObj' : document.getElementById('realmTitle'),
                'linkBox'  : '',
                'linkage'  : ['pack'],
                'saveLink' : ''
            },
            'pack' : {
                'editObj'  : document.getElementById('packEdit'),
                'titleObj' : document.getElementById('packTitle'),
                'linkBox'  : 'user',
                'linkage'  : ['func'],
                'saveLink' : 'packFunc'
            },
            'func' : {
                'editObj'  : document.getElementById('funcEdit'),
                'titleObj' : document.getElementById('funcTitle'),
                'linkBox'  : 'pack',
                'linkage'  : [],
                'saveLink' : ''
            }
        }
    },

    /**
     * 描述 : 操作指定类型的分页
     * 参数 :
     *      type : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      mode : 操作模式, 默认=搜索, 其它=分页操作
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    'paging' : function (type, mode) {
        //分页对象
        var paging = document.getElementById(type + 'Paging');
        paging.paging(mode);
    },

    /**
     * 描述 : 搜索分页
     * 参数 :
     *      type  : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      event : 触发事件
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    'search' : function (type, event) {
        if( event && event.type === 'keyup' && event.keyCode !== 13 ) return ;

        //搜索内容
        ofBaseSsoMain.paging(type, {'search' : document.getElementById(type + 'SearchInput').value});
    },

    /**
     * 描述 : 分页跳转
     * 参数 :
     *      type  : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      event : 操作的事件
     * 作者 : Edgar.lee
     */
    'jump' : function (type, event) {
        //节点对象
        var temp = document.getElementById(type + 'Jump');
        //是回车 && 有效数字
        if( event.keyCode === 13 && !isNaN(temp = parseInt(temp.value, 10)) ) {
            ofBaseSsoMain.paging(type, temp);
        }
    },

    /**
     * 描述 : 选中分页条目
     * 参数 :
     *      type : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      key  : 选中的条目ID
     * 作者 : Edgar.lee
     */
    'item' : function (type, key) {
        //分页对象
        var paging = document.getElementById(type + 'Paging');
        //获取事件节点
        var temp, node = L.event().target;

        //点击复选框
        if( node.tagName === 'INPUT' ) {
            //初始化选中列表
            (temp = paging.paging()).select || (temp.select = {});
            //添加或删除选中
            node.checked ? temp.select[key] = paging.data[key] : delete temp.select[key];
            //替换不刷新
            paging.paging(temp, false);

            if( 
                //复选框关联所属分页
                (temp = ofBaseSsoMain.state.editBlock[type].linkBox) &&
                //所属分页有选项
                ofBaseSsoMain.state.editBlock[temp].selItem
            ) {
                //重新设置节点
                ofBaseSsoMain.state.selNode = {
                    'type' : temp, 
                    'key'  : ofBaseSsoMain.state.editBlock[temp].selItem.key
                };
                //激活对应分页修改
                ofBaseSsoMain.edit(true);
            }
        } else {
            do {
                if( node.tagName === 'TR' ) {
                    if( ofBaseSsoMain.state.editBlock[type].selItem ) {
                        //清空背景色
                        ofBaseSsoMain.state.editBlock[type].selItem.node.style.backgroundColor = '';
                    }

                    //修改背景色
                    node.style.backgroundColor = '#DDD';
                    //记录选择节点
                    ofBaseSsoMain.state.editBlock[type].selItem = {'node' : node, 'key' : key};

                    if (ofBaseSsoMain.isAuth(type)) {
                        //重新设置节点
                        ofBaseSsoMain.state.selNode = {'type' : type, 'key' : key};
                        //激活修改
                        ofBaseSsoMain.edit(true);
                    }

                    //联动操作
                    ofBaseSsoMain.linkage(type);
                    break;
                }
            } while( node = node.parentNode );
        }
    },

    /**
     * 描述 : 激活或隐藏编辑区
     * 参数 :
     *      mode : 隐藏或展示 ofBaseSsoMain.state.selNode 编辑区, true=显示, false=隐藏
     *      type : 分页类型, 添加时使用, "user"=用户分页, "bale"=集合分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     * 作者 : Edgar.lee
     */
    'edit' : function (mode, type) {
        //编辑区结构
        var editBlock = ofBaseSsoMain.state.editBlock;
        //当前工作区
        var selNode = ofBaseSsoMain.state.selNode, temp;

        if (
            //显示模版 && 激活节点不是用户
            mode && selNode &&
            //授权模式下禁止编辑
            !ofBaseSsoMain.isAuth(selNode.type)
        ) {
            //隐藏编辑区
            ofBaseSsoMain.edit(false);
            return ;
        } else if( type && !ofBaseSsoMain.permit[type + 'Add'] ) {
            ofBaseSsoMain.tipBar('您没有添加本项的权限');
            return ;
        } else if( !type && selNode && !ofBaseSsoMain.permit[selNode.type + 'Mod']) {
            ofBaseSsoMain.tipBar('您没有修改本项的权限');
            return ;
        }

        //隐藏编辑区
        for(var i in editBlock) {
            editBlock[i].editObj.style.display = 'none';
            editBlock[i].titleObj.style.backgroundColor = '';
        }

        //清理分区
        if( type || !mode ) {
            //删除工作区
            delete ofBaseSsoMain.state.selNode;
            //无工作区
            selNode = {};
        }

        //显示工作区
        if( mode && selNode ) {
            if( selNode.type ) {
                //分页类型
                type = selNode.type;
                //分页数据
                mode = document.getElementById(type + 'Paging').data[selNode.key];

                if( 
                    //需要判断权限范围
                    (temp = ofBaseSsoMain.permit[selNode.type + 'Mod'].range) &&
                    //不在匹配范围
                    !ofBaseSsoMain.isRole(temp, mode)
                ) {
                    ofBaseSsoMain.tipBar('您没有修改本条的权限');
                    return ;
                }
            } else {
                mode = {};
            }

            editBlock[type].titleObj.style.backgroundColor = '#DDD';
            //展示界面
            editBlock[type].editObj.style.display = '';
            selNode = editBlock[type].editObj.getElementsByTagName('*');
            for(var i = 0, iL = selNode.length; i < iL; i++) {
                //属性值
                temp = mode[selNode[i].getAttribute('name')] || '';
                switch( selNode[i].tagName ) {
                    //文本域
                    case 'TEXTAREA':
                        selNode[i].innerHTML = L.entity(temp, true);
                    //下拉菜单
                    case 'SELECT':
                    //文本框
                    case 'INPUT':
                        selNode[i].value = temp;
                        break;
                }
            }

            //保存关联属性权限提示
            if( editBlock[type].saveLink && !ofBaseSsoMain.permit[editBlock[type].saveLink] ) {
                ofBaseSsoMain.tipBar('您没有编辑额外复选项的权限');
            }
        }
    },

    /**
     * 描述 : 保存或修改指定数据
     * 作者 : Edgar.lee
     */
    'save' : function () {
        //编辑区结构
        var temp, edit, result = {}, editBlock = ofBaseSsoMain.state.editBlock;

        for(var type in editBlock) {
            if( editBlock[type].editObj.style.display !== 'none' && window.confirm('确认保存吗?')) {
                edit = editBlock[type].editObj.getElementsByTagName('*');
                for(var i = 0, iL = edit.length; i < iL; i++) {
                    if( temp = edit[i].getAttribute('name') ) {
                        switch( edit[i].tagName ) {
                            //下拉菜单
                            case 'SELECT':
                            //文本框
                            case 'INPUT'   :
                                result[temp] = edit[i].value;
                                break;
                            //文本域
                            case 'TEXTAREA':
                                result[temp] = edit[i].value == null ? L.entity(edit[i].innerHTML, false) : edit[i].value;
                                break;
                        }
                    }
                }

                temp = {'save' : result, 'keys' : {}, 'linksel' : {}};
                //联动选中的复选框
                if( editBlock[type].saveLink && ofBaseSsoMain.permit[editBlock[type].saveLink] ) {
                    for (var i in editBlock[type].linkage) {
                        i = editBlock[type].linkage[i];
                        temp['linksel'][i] = document.getElementById(i + 'Paging').paging().select;
                    }
                } else {
                    delete temp['linksel'];
                }
                //每个分页焦点条目ID
                for(var i in editBlock) {
                    temp.keys[i] = editBlock[i].selItem && editBlock[i].selItem.key;
                }
                //添加时隐藏编辑区
                result.id || ofBaseSsoMain.edit(false);
                //添加操作
                ofBaseSsoMain.paging(type, temp);
                break;
            }
        }

        temp || ofBaseSsoMain.tipBar('无可保存的数据');
    },

    /**
     * 描述 : 分页批量操作
     * 参数 :
     *      type : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      mode : 操作模式, "del"=删除, "ice"=冻结
     * 作者 : Edgar.lee
     */
    'action' : function (type, mode) {
        //分页对象
        var paging = document.getElementById(type + 'Paging');
        //选中的ID
        var select = paging.paging().select || {};
        //权限类型
        var permit = type + mode.replace(/(\w)/, function(v){return v.toUpperCase()});
        var filte = {'name' : [], 'key' : []}, range;

        if( !(range = ofBaseSsoMain.permit[permit]) ) {
            ofBaseSsoMain.tipBar('您没有操作本项的权限');
            return ;
        } else if( select && (range = range.range) ) {
            for(var key in select) {
                //不在匹配范围
                if( !ofBaseSsoMain.isRole(range, select[key]) ) {
                    filte.name.push(select[key].name);
                    filte.key.push(key);
                }
            }
        }

        if( window.confirm(filte.name.length ? 
                '是否继续? 您没有权限操作如下数据 : "' + filte.name.join('","') + '"' : 
                '确认操作勾选项吗?'
        )) {
            //所有复选框
            range = ofBaseSsoMain.allBox(type);
            for(var i in filte.key) {
                range[filte.key[i]].click();
            }

            paging.paging({'action' : mode});
        }
    },

    /**
     * 描述 : 联动操作
     * 参数 :
     *      type : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     * 作者 : Edgar.lee
     */
    'linkage' : function (type) {
        //当前引用
        var editBlock = ofBaseSsoMain.state.editBlock;
        var selNode = ofBaseSsoMain.state.selNode;
        var params = {};

        if( editBlock[type].linkage.length ) {
            for(var i in editBlock) {
                params[i] = editBlock[i].selItem ? editBlock[i].selItem.key : '';
            }

            //联动数据, 清空选择
            for (var i in editBlock[type].linkage) {
                i = editBlock[type].linkage[i];
                document.getElementById(i + 'Paging').paging({
                    'selNode' : selNode,
                    'linkage' : params,
                    'select' : {}
                });
            }
        }
    },

    /**
     * 描述 : 获取指定分页类型下钱的复选框
     * 参数 :
     *      type : 分页类型, "user"=用户分页, "realm"=系统分页, "pack"=角色分页, "func"=功能分页
     *      mode : 是否选中, 默认=不操作, bool=选中或取消
     * 返回 : {
     *          数据ID : 复选框对象
     *      }
     * 作者 : Edgar.lee
     */
    'allBox' : function (type, mode) {
        //分页对象
        var paging = document.getElementById(type + 'Paging');
        var result = {}, temp = paging.getElementsByTagName('input');

        for(var i = 0, iL = temp.length; i < iL; i++) {
            //是复选框
            if( temp[i].getAttribute('type') === 'checkbox' ) {
                result[temp[i].parentNode.parentNode.getAttribute('key')] = temp[i];
                //全选或取消
                if( typeof mode === 'boolean' ) {
                    temp[i].checked = !mode;
                    temp[i].click();
                }
            }
        }

        return result;
    },

    /**
     * 描述 : 显示提示信息
     * 参数 :
     *      value : 提示问题
     *      close : false=不关闭消息, 默认=3秒后关闭信息
     * 作者 : Edgar.lee
     */
    'tipBar' : function (value, close) {
        var tipObj = document.getElementById('mainTipBar');
        //取消关闭
        if( ofBaseSsoMain.diff && ofBaseSsoMain.bubble ) {
            clearTimeout(ofBaseSsoMain.diff);
            ofBaseSsoMain.diff = tipObj.innerHTML = '';
        } else if( ofBaseSsoMain.diff === undefined ) {
            //冒泡关闭
            L.event(document.body, 'click', ofBaseSsoMain.tipBar);
        }

        ofBaseSsoMain.bubble = true;
        if( typeof value === 'string' ) {
            //提示信息显示
            tipObj.innerHTML = L.entity(value, true);
            //添加取消
            close === false || (ofBaseSsoMain.diff = setTimeout(ofBaseSsoMain.tipBar, 3000));
            //防止被自身冒泡取消
            ofBaseSsoMain.bubble = !L.event();
        }
    },

    /**
     * 描述 : 判断是否有权限
     * 参数 :
     *      permit : 范围权限
     *      data   : 待判断数据
     * 返回 :
     *      true=有权限 false=无权限
     * 作者 : Edgar.lee
     */
    'isRole' : function (permit, data) {
        //所有子项 || 关系
        for(var i in permit) {
            //所有子项 && 关系
            for(var j in permit[i]) {
                if( !(j = L.json(permit[i][j]).test(data[j])) ) {
                    break ;
                }
            }
            //本项完整匹配
            if( j ) {
                permit = false;
                break;
            }
        }

        return !permit;
    },

    /**
     * 描述 : 
     * 作者 : Edgar.lee
     */
    'isAuth' : function (type) {
        return type === 'user' || document.getElementById('mainModelBox').checked
    },

    /**
     * 描述 : 模版批量导入
     * 作者 : Edgar.lee
     */
    'import' : function () {
        if( arguments[3] ) {
            ofBaseSsoMain.tipBar('正在导入...', false);
            L.ajax(OF_URL + '/index.php?c=of_base_sso_main&a=tplImport', {
                'post' : {'path' : arguments[3]}
            }, function (data) {
                if( data === 'done' ) {
                    document.getElementById('userPaging').paging('+0');
                    ofBaseSsoMain.linkage('realm');
                    ofBaseSsoMain.tipBar('导入成功');
                } else {
                    ofBaseSsoMain.tipBar(data);
                }
            });
        } else {
            ofBaseSsoMain.tipBar('无效导入');
        }
    },

    /**
     * 描述 : 获取符合筛选条件的节点
     * 参数 :
     *      obj  : 指定根节点
     *      name : 属性名称
     *      val  : 属性值, 可以是正则类型
     *      attr : 需要修改的属性名称
     *      data : 修改属性名的数据
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    'getObj' : function (obj, name, val, attr, data) {
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
    },

    /**
     * 描述 : 切换编辑模式 或 切换授权模式
     * 参数 :
     *      node : 编辑节点
     * 作者 : Edgar.lee
     */
    'model' : function (node) {
        //当前引用
        var editBlock = ofBaseSsoMain.state.editBlock;
        var temp, list = document.getElementById('mainModelTip');

        //切换编辑模式
        if (node.checked) {
            temp = [/\bedit\b/, /\buser\b/];
            list.innerHTML = '编辑模式';
            editBlock.pack.linkBox = 'bale';
        //切换授权模式
        } else {
            temp = [/\buser\b/, /\bedit\b/];
            list.innerHTML = '授权模式';
            editBlock.pack.linkBox = 'user';
        }

        //一模式显示
        list = ofBaseSsoMain.getObj(document, 'class', temp[0]);
        for (var i in list) {
            list[i].className = list[i].className.replace(/\s*\bnone\b\s*/g, ' ');
        }

        //一模式隐藏
        list = ofBaseSsoMain.getObj(document, 'class', temp[1]);
        for (var i in list) {
            list[i].className += ' none';
        }

        //隐藏编辑区
        ofBaseSsoMain.edit(false);
    }
};

/**
 * 描述 : 翻页后回调
 * 作者 : Edgar.lee
 */
L.data('paging.after[]', function() {
    //基础类型
    var type = this.id.slice(0, -6);
    //当前页面
    var temp = parseInt(this.getAttribute('page'), 10);
    //选中的ID
    var sels = this.paging().select || {};
    //当前引用
    var edit = ofBaseSsoMain.state.editBlock[type];

    //计算下页地址
    temp = temp < Math.ceil(this.getAttribute('items') / this.getAttribute('size')) ? temp + 1 : 1;
    document.getElementById(type + 'Jump').value = temp;

    temp = {};
    //将数据转化为{ID:数据}
    for(var i in this.data) {
        temp[this.data[i].id] = this.data[i];
    }
    //更新自带数据
    this.data = temp;

    //所有复选框
    temp = ofBaseSsoMain.allBox(type);
    //恢复复选框
    for(var i in temp) {
        sels[i] && (temp[i].checked = true);
    }

    //恢复选择状态
    if( edit.selItem && temp[edit.selItem.key] ) {
        //更新节点
        edit.selItem.node = temp[edit.selItem.key].parentNode.parentNode;
        //修改背景色
        edit.selItem.node.style.backgroundColor = '#DDD';
    } else {
        delete edit.selItem;
        //取消修改
        if( ofBaseSsoMain.state.selNode && type === ofBaseSsoMain.state.selNode.type ) {
            //隐藏修改内容
            ofBaseSsoMain.edit(false);
        }
    }
    //联动操作
    ofBaseSsoMain.linkage(type);

    //添加提示信息
    if( temp = this.paging().tip ) {
        //提示信息显示
        ofBaseSsoMain.tipBar(temp);
        delete ($temp = this.paging()).tip;
        this.paging($temp, false);
    }
});

/**
 * 描述 : 窗口改变时布局代码
 * 作者 : Edgar.lee
 */
(function() {
    var tbodyTdDivList = [], temp = document.getElementById('mainTbodyTr').children;
    for(var i = 0, iL = temp.length; i < iL; ++i) {
        tbodyTdDivList[i] = temp[i].children[0];
    }
    L.event(window, 'resize', function () {
        temp = window.document.documentElement.clientHeight - 300 + 'px';
        for(var i = 0, iL = tbodyTdDivList.length; i < iL; ++i) {
            tbodyTdDivList[i].style.height = temp;
        }
    });
    L.event(window, 'resize');
})();

/**
 * 描述 : 导入模版功能
 * 作者 : Edgar.lee
 */
(function () {
    L.open('oUpload', {
        'node' : document.getElementById('mainTplImport'),
        'auto' : true,
        'exts' : 'csv',
        'call' : ofBaseSsoMain['import']
    });
})();