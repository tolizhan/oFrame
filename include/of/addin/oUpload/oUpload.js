var oUpload = (function () {
    /**
     * 描述 : 前端相关封装
     * 注明 :
     *      上传组件列表(fileList)结构 : {
     *          组件ID : {
     *              "context" : 对外返回的对象 {
     *                  "uniqid"  : 唯一区分值
     *                  "node"    : 上传节点对象
     *                  "cancel"  : 取消上传(文件ID(默认取消全部))
     *                  "upload"  : 上传文件(文件ID(默认取消全部), 检查回调(false=默认尝试,true=上传))
     *                  "setting" : 设置读取(读写值, 对应值(不写便是读取))
     *              },
     *              "fileObj" : 真实的上传组件,
     *              "params'" : 初始化时的参数,
     *              "guiNode" : 队列进度相关节点 {
     *                  "block"  : 整体块节点
     *                  "cancel" : 清除队列节点,
     *                  "upload" : 全部上传节点,
     *                  "count"  : 队列数量提示
     *                  "queue"  : 队列节点
     *                  "items"  : 具体列表 {
     *                      文件ID : 对应的文档节点 {
     *                          "item"     : 文件块
     *                          "close"    : 关闭按钮
     *                          "speed"    : 速度展示
     *                          "progress" : 进度条
     *                      },
     *                      ...
     *                  }
     *              }
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    var share = {
        //服务器上传目录
        'rootDir'  : OF_URL + '/addin/oUpload',
        //碎片节点
        'fragment' : document.createElement('div'),
        //最大上传字节
        'maxSize'  : 0,
        //上传组件列表
        'fileList' : {},
        //支持的突变对象
        'mutation' : false,//window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver,

        /**
         * 描述 : 将子节点插入父节点所在的文档里
         * 参数 :
         *      pNode : 父节点
         *      cNode : 子节点, 可以是html
         * 返回 :
         *      对象化的子节点
         * 作者 : Edgar.lee
         */
        'append'   : function (pNode, cNode) {
            if( typeof cNode === 'string' ) {
                share.fragment.innerHTML = cNode;
                cNode = share.fragment.firstChild;
            }
            L.window(pNode).document.body.appendChild(cNode);

            return cNode;
        },

        /**
         * 描述 : 触发回调
         * 参数 :
         *      target : this 对象
         *      params : 数组的二维参数
         * 注明 :
         *      回调函数(target.callback)结构 : [{
         *          "select"      : 当选择一个文件后,
         *          "selectOnce"  : 当一次选择结束后,
         *          "queueFull"   : 选择上传数超过最大限制,
         *          "checkExist"  : 校验文本是否存在,
         *          "open"        : 单文件开始上传时,
         *          "progress"    : 单文件上传进度,
         *          "complete"    : 单文件上传完成,
         *          "allComplete" : 全部上传完成,
         *          "cancel"      : 删除指定上传,
         *          "clearQueue"  : 清除上传队列,
         *          "error"       : 出现相关错误
         *      }, ...]
         * 作者 : Edgar.lee
         */
        'fire'     : function (target, params) {
            var callback = target.callback, temp;
            target = share.fileList[target.id].context;
            //去除第一个" "
            params[0] === 'complete' && (params[3] = params[3].substr(1));
            //console.log(params);

            if( callback ) {
                //遍历调用函数
                for(var i in callback) {
                    try {
                        //事件回调
                        (temp = callback[i][params[0]]) && temp.apply(target, params);
                    } catch (e) {}
                }
            }
        },

        /**
         * 描述 : 返回节点的边界信息
         * 参数 :
         *      node : 指定的节点
         * 返回 : 相对窗口的边距(px) {
         *          "top"    : 上边距
         *          "left"   : 左边距
         *          "right"  : 右边距
         *          "bottom" : 下边距
         *      }
         * 作者 : Edgar.lee
         */
        'getRect' : function(node) {
            var box = node.getBoundingClientRect(), clientTop, clientLeft, scrollTop, scrollLeft, top, left;

            clientTop  = document.clientTop  || document.body.clientTop  || 0;
            clientLeft = document.clientLeft || document.body.clientLeft || 0;
            scrollTop  = window.pageYOffset || document.scrollTop || 0;
            scrollLeft = window.pageXOffset || document.scrollLeft || 0;
            top        = scrollTop  - clientTop;
            left       = scrollLeft - clientLeft;

            return {
                "top"    : box.top  + top,
                "left"   : box.left + left,
                "right"  : box.right + left,
                "bottom" : box.bottom + top
            };
        },

        /**
         * 描述 : 突变服务监听
         * 参数 :
         *      type : true=触发监听, false=初始化监听
         * 作者 : Edgar.lee
         */
        'obServer' : function (type) {
            var block, rect, guiNode, offest;

            if( type === false ) {
                if( share.mutation ) {
                    //启动突发监听
                    (new share.mutation(arguments.callee)).observe(document.body, {
                        'attribute'         : true,
                        'attributeOldValue' : true,
                        'childList'         : true,
                        'subtree'           : true
                    });
                    L.event(window, 'resize', arguments.callee);
                }
            } else {
                for(var i in share.fileList) {
                    guiNode = share.fileList[i].guiNode;
                    //节点位置
                    rect = share.getRect(share.fileList[i].context.node);
                    //队列位置
                    block = share.getRect(guiNode.block);
                    if(
                        //节点不可见
                        rect.left === rect.right ||
                        //节点不可见
                        rect.bottom === rect.top ||
                        //队列不可见
                        block.left === block.right ||
                        //队列不可见
                        block.bottom === block.top ||
                        //上传列表空
                        L.count(share.fileList[i].guiNode.items) === 0
                    ) {
                        guiNode.block.style.top = '-1000px';
                        guiNode.block.style.left = '-1000px';
                    } else {
                        guiNode.block.style.top = rect.top - (block.bottom - block.top) / 2 + 'px';
                        guiNode.block.style.left = rect.right + (block.left - block.right) / 2 + 'px';
                    }
                }
            }

            //启动蛮力监听
            share.mutation || type === true || window.setTimeout(arguments.callee, 500);
        },

        /**
         * 描述 : 初始化上传界面
         * 参数 :
         *      params : 数据结构 {
         *          "context" : 对外返回的对象,
         *          "fileObj" : 真实的上传组件,
         *          "params'" : 初始化时的参数,
         *      }
         * 返回 :
         *      
         * 作者 : Edgar.lee
         */
        'initGui' : function (params) {
            share.fileList[params.context.uniqid = params.fileObj.id] = params;
            params.guiNode = '<div ' + (typeof params.params.show === 'string' ? params.params.show : '') +
                ' class="of_addin_oUpload-block">' +
                '<div class="of_addin_oUpload-queue">' +
                    '<div class="of_addin_oUpload-items"></div>' +
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderN" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderS" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderW" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderE" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderEN" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderES" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderWS" ie6Png></div>' + 
                    '<div class="of_addin_oUpload-border of_addin_oUpload-borderWN" ie6Png></div>' + 
                '</div>' +
                '<div class="of_addin_oUpload-count" ie6Png></div>' +
                '<div class="of_addin_oUpload-upload" ie6Png></div>' +
                '<div class="of_addin_oUpload-cancel" ie6Png></div>' +
            '</div>';
            //生成对象
            params.guiNode = share.append(params.fileObj, params.guiNode);
            //格式化对应结构
            params.guiNode = {
                "block"  : params.guiNode,
                "cancel" : params.guiNode.children[3],
                "upload" : params.guiNode.children[2],
                "count"  : params.guiNode.children[1],
                "queue"  : params.guiNode.children[0].firstChild,
                "items"  : {}
            };

            params.guiNode.block.style.display = params.params.show ? 'block' : 'none';
            params.fileObj.callback = [{
                //删除上传,开始上传
                'open'     : share.onOpen,
                //添加队列,更新数量
                'select'   : share.onSelect,
                //添加类名,显示错误
                'error'    : share.onError,
                //更改进度
                'progress' : share.onProgress,
                //删除队列,更新数量
                'complete' : share.onComplete,
                //删除队列,更新数量
                'cancel'   : share.onComplete
            }];

            //点击显示与隐藏
            L.event(params.guiNode.count, 'click', share.queueShow);
            //点击上传全部文件
            L.event(params.guiNode.upload, 'click', params.context.upload);
            //点击清除全部文件
            L.event(params.guiNode.cancel, 'click', params.context.cancel);
        },

        /**
         * 描述 : 鼠标穿入穿出展示或隐藏队列信息
         * 参数 :
         *      event : mouseenter 与 mouseleave 事件
         * 作者 : Edgar.lee
         */
        'queueShow' : function (event) {
            var queue = this.previousSibling;
            var rect = share.getRect(queue);
            if( rect.left === rect.right ) {
                queue.style.display = 'block';

                rect = share.getRect(queue);
                //完全展示
                rect.left < 0 && (queue.style.right = rect.left + 'px');
            } else {
                queue.style.display = 'none';
                queue.style.right = '';
            }
        },

        /**
         * 描述 : 当开始上传时
         * 作者 : Edgar.lee
         */
        'onOpen'   : function () {
            var item = share.fileList[this.uniqid].guiNode.items[arguments[1]];

            //移除样式
            if( item.speed.className.indexOf('of_addin_oUpload-item_open') > -1 ) {
                //移除事件
                L.event(item.speed, 'click', false);
                //移除样式
                item.speed.className = item.speed.className.replace(/\s*of_addin_oUpload-item_open(\s|$)*/, '$1');
            }
            //开始上传
            arguments[0] === 'click' && this.upload(arguments[1]);
        },

        /**
         * 描述 : 当选择一个文件后
         * 作者 : Edgar.lee
         */
        'onSelect'   : function () {
            //界面相关节点
            var guiNode = share.fileList[this.uniqid].guiNode, list = {}, uniqid = arguments[1], context = this, temp;

            share.fragment.innerHTML = '<div class="of_addin_oUpload-item">' +
                '<a class="of_addin_oUpload-item_close">&nbsp;</a>' +
                '<span class="of_addin_oUpload-item_data" title="' +(temp = L.entity(arguments[2].name))+ '">' +
                    '<span class="of_addin_oUpload-item_data_fileName">&nbsp;</span>' +
                    '<span class="of_addin_oUpload-item_data_progress">&nbsp;</span>' +
                    '<span class="of_addin_oUpload-item_data_title">' +temp+ '</span>&nbsp;' +
                '</span>' +
                '<span class="of_addin_oUpload-item_speed">&nbsp;</span>' +
            '</div>';

            //文件块
            list.item = share.fragment.firstChild;
            //关闭按钮
            list.close = list.item.children[0];
            //进度条
            list.progress = list.item.children[1].children[1];
            //速度展示
            list.speed = list.item.children[2];

            //添加到列表
            guiNode.items[uniqid] = list;
            //显示队列
            guiNode.queue.appendChild(list.item);
            //更新队列数
            guiNode.count.innerHTML = this.setting('queueSize');

            //点击取消
            L.event(list.close, 'click', function () {
                context.cancel(uniqid);
            });
            //非错误 && 非自动上传
            if( arguments.length === 3 && !this.setting('auto') ) {
                //开始样式
                list.speed.className += ' of_addin_oUpload-item_open';
                //点击开始上传
                L.event(list.speed, 'click', function () {
                    share.onOpen.apply(context, ['click', uniqid]);
                });
            }
        },

        /**
         * 描述 : 当产生一个错误后
         * 作者 : Edgar.lee
         */
        'onError'    : function () {
            //单文件节点列表
            var items = share.fileList[this.uniqid].guiNode.items, temp;

            switch( arguments['3'].type ) {
                case 'SIZE':
                    temp = 'File size: ' + (arguments['3'].info / 1048576) + 'M';
                    break;
                case 'TYPE':
                    temp = 'File type: ' + arguments['3'].info;
                    break;
            }

            share.onSelect.apply(this, arguments);
            items[arguments[1]].speed.className += ' of_addin_oUpload-item_error';
            items[arguments[1]].speed.innerHTML = arguments['3'].type;
            items[arguments[1]].item.title = temp || arguments['3'].info;
        },

        /**
         * 描述 : 当上传产生进度时
         * 作者 : Edgar.lee
         */
        'onProgress' : function () {
            //单文件节点
            var item = share.fileList[this.uniqid].guiNode.items[arguments[1]], speed;

            if( arguments[3].speed < 1000 ) {
                speed = Math.round(arguments[3].speed) + 'k/s';
            } else {
                speed = (arguments[3].speed / 1024).toFixed(1) + 'm/s';
            }

            item.progress.style.width = arguments[3].percentage + '%';
            item.speed.innerHTML = speed;
        },

        /**
         * 描述 : 当单文件上传完成时
         * 作者 : Edgar.lee
         */
        'onComplete' : function () {
            //界面相关节点
            var guiNode = share.fileList[this.uniqid].guiNode, temp = guiNode.items[arguments[1]].item;

            //移除节点
            temp.parentNode.removeChild(temp);
            //删除列表
            delete guiNode.items[arguments[1]];
            //更新队列数
            guiNode.count.innerHTML = this.setting('queueSize');
        }
    }

    /**
     * 描述 : html5上传封装
     * 注明 :
     *      上传组件列表(list)结构 : [{
     *          "tagObj" : 上传组件, 扩增了如下属性 {
     *              "upload"  : 组件上传调用方法
     *              "cancel"  : 取消指定ID文件
     *              "clear"   : 清除全部文件
     *              "setting" : 读取设置配置
     *          },
     *          "config" : {
     *              "node"   : 上传对象,支持innerHTML属性的表情
     *              "auto"   : 是否自动上传, true=是, false(默认)=否
     *              "check"  : (未实现)上传时检查文件是否存在服务器脚本,触发 checkExist 时使用
     *              "exts"   : 扩展名 Extend
     *              "folder" : 上传的文件夹
     *              "multi"  : 是否允许选择多文件, true=是, false(默认)=否
     *              "queue"  : 最大队列数量
     *              "size"   : 最大单文件字节数
     *          },
     *          "files"  : 选择的文件列表 {
     *              唯一值 : 文件上传相关属性 {
     *                  "ajax" : 上传对象,仅上传过程中存在
     *                  "attr" : {
     *                      "size" : 文件字节
     *                      "type" : 区分大小写的扩展(.Txt)
     *                      "name" : 带扩展的文件名
     *                      "modificationDate" : {
     *                          "time" : 最后修改时间戳
     *                      }
     *                  },
     *                  "file"   : file类型的文件
     *                  "uniqid" : 生成唯一ID(初略认为 "attr.size-attr.modificationDate.time-attr.name")
     *              }
     *          },
     *          "status" : 上传标识,仅上传过程中存在 {
     *              "size"  : 总文件大小(b)
     *              "error" : 发生错误次数
     *              "speed" : 长度为成功个数 [
     *                  成功上传文件的速度(kb/s), ...
     *              ]
     *          }
     *      }, ...]
     * 作者 : Edgar.lee
     */
    var html5 = {
        //上传组件列表
        'list' : {},

        /**
         * 描述 : 实例化对象
         * 参数 :
         *      params : 等同对外方法参数
         * 作者 : Edgar.lee
         */
        'inst' : function (params) {
            //组件对象
            var file = '<input style="position: absolute; left: -1000px; width: 0px;" type="file" name="[]"';
            //允许多选
            params.multi && (file += ' multiple');
            //扩展限制
            params.exts && (file += ' accept=".' + params.exts.replace(/;/g, ',.') + '"');
            file += ' id="oUpload' + (new Date).getTime().toString(36) + '" >';

            //插入界面
            file = share.append(params.node, file);
            //加入组件列表
            html5.list[file.id] = {
                //组件对象
                'tagObj' : file,
                //配置文件
                'config' : params,
                //待上传文件
                'files'  : {},
                //全部完成前状态
                'status' : {'size' : 0, 'error' : 0, 'speed' : []}
            };

            //选择文件后
            L.event(file, 'change', html5.onSel);
            //点击选择文件
            L.event(params.node, 'click', function () {
                var index = html5.list[file.id].files;
                //正在上传 禁止选择
                for(var i in index) if( index[i].ajax ) return ;
                file.click();
            });
            //上传调用函数
            file.upload = function (fileId, check) {
                html5.upload.call(file, fileId, check);
            };
            //取消指定ID文件
            file.cancel = function (fileId) {
                html5.cancel.call(file, fileId);
            };
            //读取设置配置
            file.setting = function (name, value) {
                return html5.setting.call(file, name, value);
            };

            //触发初始化回调
            window.setTimeout(function () {share.fire(file, ['init']);}, 0);
            return file;
        },

        /**
         * 描述 : 当上传组件选择文件后
         * 作者 : Edgar.lee
         */
        'onSel' : function (event) {
            //上传组件节点
            var target = event.target, attr, uniqid, file, temp;
            //已经选中的文件
            var files  = target.files;
            //引用上传对接列表
            var index  = html5.list[target.id];
            //selectOnce 回调内容
            var count  = {
                //目前已选择文件总字节
                'allBytesTotal' : 0,
                //目前已选择文件个数
                'fileCount'     : L.count(index.files),
                //本次选择有效文件个数
                'filesSelected' : 0,
                //本次重复选择的文件
                'filesReplaced' : 0
            };

            //允许多个 || 清空列表
            index.config.multi || (index.files = {}, count.fileCount = 0);
            //保存上传的文件
            for(var i = 0, iL = files.length; i < iL; i++) {
                //生成唯一值
                uniqid = (new Date).getTime().toString(36) + L.count(index.files);
                file = {
                    //格式化的属性
                    'attr'   : attr = {
                        //文件字节
                        'size' : files[i].size,
                        //扩展(.Txt)
                        'type' : files[i].name.match(/^.*?(\.[^\.]+|)$/)[1],
                        //带扩展的文件名
                        'name' : files[i].name,
                        'modificationDate' : {
                            //最后修改时间戳
                            'time' : files[i].lastModified || files[i].lastModifiedDate.getTime()
                        }
                    },
                    'file'   : files[i],
                    //生成唯一ID(初略认为)
                    'uniqid' : attr.size + '-' + attr.modificationDate.time + '-' + attr.name
                };

                temp = new RegExp('(^|;)' + file.attr.type.substr(1) + '(;|$)', 'i');
                //扩展名不符
                if (index.config.exts && !temp.test(index.config.exts)) {
                    share.fire(target, ['error', uniqid, attr, {
                        //单文件允许的最大字节
                        'info' : index.config.exts,
                        //错误类型
                        'type' : 'TYPE'
                    }]);
                //触发 单文件太大 回调
                } else if( attr.size > index.config.size ) {
                    share.fire(target, ['error', uniqid, attr, {
                        //单文件允许的最大字节
                        'info' : index.config.size,
                        //错误类型
                        'type' : 'SIZE'
                    }]);
                //已经选择过文件
                } else if( L.search(index.files, file.uniqid, 'uniqid') ) {
                    //重复选择文件 +1
                    count.filesReplaced += 1;
                //达到了最大队列
                } else if( count.fileCount == index.config.queue ) {
                    //触发 队列已满 回调
                    share.fire(target, ['queueFull', index.config.queue]);
                    break;
                //没有选择过文件
                } else {
                    //目前已选择文件个数 +1
                    count.fileCount += 1;
                    //本次选择文件个数 +1
                    count.filesSelected += 1;
                    //生成唯一值并保存文件流
                    index.files[uniqid] = file;
                    //触发 选择一个有效文件 回调
                    share.fire(target, ['select', uniqid, attr]);
                }
            }
            //计算队列中总字节数
            for(var i in index.files) {
                count.allBytesTotal += index.files[i].attr.size;
            }

            //清空选择记录
            target.value = '';
            //触发 本次选择完成 回调
            share.fire(target, ['selectOnce', count]);

            //自动上传
            index.config.auto && index.tagObj.upload();
        },

        /**
         * 描述 : 上传时调用此函数
         * 参数 :
         *      fileId : 指定上传的文件ID, null(默认)=操作全部, 字符串=操作指定ID文件
         *      check  : 回调 checkExist, false=校验文件 true=上传文件
         * 作者 : Edgar.lee
         */
        'upload' : function (fileId, check) {
            //引用上传组件
            var index = html5.list[this.id], temp = {};

            //上传单个文件
            if( fileId ) {
                index.files[fileId] && (temp[fileId] = index.files[fileId]);
            //上传全部文件
            } else {
                L.each(temp, index.files);
            }

            //上传文件 || 无效校验路径
            if( check || !index.config.check ) {
                //指定上传
                if( fileId ) {
                    //ajax 上传文件
                    temp[fileId] && html5.send(index, fileId, true);
                //全部上传
                } else {
                    //激活上传列表
                    html5.active(index);
                }
            } else {
                for(var i in temp) {
                    temp[i] = temp[i].attr.name;
                }
                //触发 本次选择完成 回调
                share.fire(this, ['checkExist', index.config.check, temp, index.config.folder, !!fileId]);
            }
        },

        /**
         * 描述 : 激活等待列表
         * 参数 :
         *      index : 指定上传组件的对象
         * 作者 : Edgar.lee
         */
        'active' : function (index) {
            //可上传列表, 正在上传数量
            var list = [], count = 0;

            for(var i in index.files) {
                //统计正在上传数
                if( index.files[i].ajax ) {
                    count += 1;
                //可以上传列表
                } else {
                    list.push(i);
                }
            }

            for(var i = 0, iL = index.config.limit - count; i < iL; ++i) {
                //ajax 上传文件
                list[i] && html5.send(index, list[i], false);
            }
        },

        /**
         * 描述 : 取消指定上传的文件
         * 参数 :
         *      fileId : 指定ID文件, 默认=删除取消全部文件
         * 作者 : Edgar.lee
         */
        'cancel' : function (fileId) {
            //引用上传组件
            var index = html5.list[this.id], size = 0, count = L.count(index.files), temp = {};

            //上传单个文件
            if( fileId ) {
                temp[fileId] = index.files[fileId] || {
                    'attr'  : {'size' : 0},
                    'count' : ++count
                };
            //上传全部文件
            } else {
                L.each(temp, index.files);
            }

            //统计完整字节
            for(var i in index.files) size += index.files[i].attr.size;
            for(var i in temp) {
                //删除上传文件
                delete index.files[i];
                //正在上传 && 停止上传
                temp[i].ajax && temp[i].ajax.abort();
                //触发 删除指定上传 回调
                share.fire(index.tagObj, ['cancel', i, temp[i].attr, {
                    //剩余文件字节
                    'allBytesTotal' : size -= temp[i].attr.size,
                    //剩余文件数
                    'fileCount'     : --count
                }]);
            }

            //触发 清除上传队列 回调
            fileId || share.fire(index.tagObj, ['clearQueue']);
        },

        /**
         * 描述 : 读取设置属性
         * 参数 :
         *      name  : 属性名
         *      value : 属性值,默认=读取
         * 返回 :
         *      读取时返回读取的值
         * 作者 : Edgar.lee
         */
        'setting' : function (name, value) {
            //引用上传组件
            var index = html5.list[this.id];

            //设置数据
            if (value != null) {
                index.config[name] = value;

                if (name === 'multi') {
                    this.multiple = !!value;
                } else if (name === 'exts') {
                    this.accept = '.' + value.replace(/;/g, ',.');
                }
            //读取队列数
            } else if (name === 'queueSize') {
                return L.count(index.files);
            //读取配置
            } else {
                return index.config[name];
            }
        },

        /**
         * 描述 : 通过 ajax 发送数据
         * 参数 :
         *      index  : 组件的引用
         *      fileId : 上传文件ID
         *      single : true=单文件长传, false=全文件上传
         * 作者 : Edgar.lee
         */
        'send' : function (index, fileId, single) {
            //文件引用
            var file = index.files[fileId], form = new FormData, temp, time, ajax;

            //已经开始上传
            if (file.ajax) return ;
            //创建ajax对象
            file.ajax = ajax = window.XMLHttpRequest ? new window.XMLHttpRequest : ActiveXObject('Msxml12.XMLHTTP');
            //触发 单文件开始上传 回调
            share.fire(index.tagObj, ['open', fileId, file.attr]);
            //添加上传文件到表单
            form.append('fileData', file.file);
            //上传的文件夹
            form.append('folder', index.config.folder);
            //指定上传的文件名
            form.append('file', index.config.file);

            //上传进度
            ajax.upload.addEventListener("progress", function(e) {
                //已上传的字节
                temp = e.loaded > file.attr.size ? file.attr.size : e.loaded;
                temp = {
                    //全部文本字节
                    "allBytesLoaded" : file.attr.size,
                    //已上传字节
                    "bytesLoaded"    : temp,
                    //当前百分比
                    "percentage"     : Math.round(temp * 100 / file.attr.size),
                    //平均速度 kb/s
                    "speed"          : temp / (e.timeStamp - time)
                }
                //触发 单文件上传进度 回调
                share.fire(index.tagObj, ['progress', fileId, file.attr, temp]);
            }, false);

            //上传结果
            ajax.onreadystatechange = function(e) {
                //连接成功
                if(ajax.readyState === 1) {
                    //记录时间戳
                    time = e.timeStamp;
                //传输完成
                } else if(ajax.readyState === 4) {
                    //删除待上传文件
                    delete index.files[fileId];
                    //上传成功
                    if(ajax.status === 200) {
                        //记录上传字节
                        index.status.size += file.attr.size;
                        //记录上传速度
                        index.status.speed.push(temp = file.attr.size / (e.timeStamp - time));
                        //回调数据
                        temp = {'fileCount' : L.count(index.files), 'speed' : temp};
                        //触发 单文件上传完成 回调
                        share.fire(index.tagObj, ['complete', fileId, file.attr, ajax.responseText, temp]);
                    //HTTP 错误
                    } else {
                        //删除ajax对象
                        //delete file.ajax;
                        //IO, 跨域 : HTTP 错误
                        temp = {'type' : ajax.status < 100 ? 'IO' : 'HTTP', 'info' : ajax.status};
                        //触发 error错误 回调
                        share.fire(index.tagObj, ['error', fileId, file.attr, temp]);
                    }
                    //单文件上传 || 继续激活上传
                    single || html5.active(index);

                    //全部上传完成
                    if( L.count(index.files) === 0 ) {
                        temp = 0;
                        for(var i in index.status.speed) temp += index.status.speed[i];
                        //平均速度
                        temp /= index.status.speed.length;

                        //触发 全部上传完成 回调
                        share.fire(index.tagObj, ['allComplete', {
                            //成功上传字节
                            'allBytesLoaded' : index.status.size,
                            //出现错误次数
                            'errors'         : index.status.error,
                            //成功上传个数
                            'filesUploaded'  : index.status.speed.length,
                            //平均速度 kb/s
                            'speed'          : temp
                        }]);

                        //清空上传状态
                        index.status = {'size' : 0, 'error' : 0, 'speed' : []};
                    }
                }
            };

            //上传路径
            (temp = index.config.script).indexOf('://') > 0 || (temp = index.config.path + temp);
            //打开连接
            ajax.open('POST', temp, true);
            //ajax标识
            ajax.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            //发送数据
            try { ajax.send(form); } catch (e) {}
        }
    };

    /**
     * 描述 : flash上传封装
     * 作者 : Edgar.lee
     */
    var flash = {
        /**
         * 描述 : 实例化对象
         * 参数 :
         *      params : 等同对外方法参数
         * 作者 : Edgar.lee
         */
        'inst' : function (params) {
            var file = '*.'+ (params.exts ? params.exts.replace(/;/g,';*.') : '*');

            file = {
                //组件ID
                'id'             : 'oUpload' + (new Date).getTime().toString(36),
                //检查服务器文件存在
                'checkScript'    : params.check,
                //上传根目录
                'pagepath'       : params.path,
                //上传脚步路径
                'script'         : params.script,
                //上传的文件夹
                'folder'         : params.folder,
                //上传模式
                'method'         : 'POST',
                //最大选择队列
                'queueSizeLimit' : params.queue,
                //同时上传文件数
                'simUploadLimit' : params.limit,
                //支持的扩展名
                'fileExt'        : file,
                //显示内容
                'fileDesc'       : '(' + file + ')',
                //支持多选
                'multi'          : params.multi ? 'true' : '',
                //单文件最大字节
                'sizeLimit'      : params.size,
                //文件数据名称
                'fileDataName'   : 'fileData',
                //按钮透明化
                'hideButton'     : 'true',
                //指定的文件名
                'scriptData'     : L.param({'file' : params.file})
            };
            file = '<object ' +
                (L.browser.msie ? 'classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"' : 'type="application/x-shockwave-flash"')+ 
                ' data="' + params.path+ '/oUpload.swf" id="' +file.id+ 
                '" style="position: absolute; top: -9px; width: 1px; height: 1px;">' +
                    '<param name="movie" value="' +params.path+ '/oUpload.swf" >' +
                    '<param name="allowScriptAccess" value="always">' +
                    '<param name="wmode" value="transparent">' +
                    '<param name="flashvars" value="' +L.param(file)+ '">' +
                '</object>';
            //插入界面
            file = share.append(params.node, file);

            file._callback = function () {
                //执行设置列表
                arguments[0] === 'init' && file.setting();
                //触发回调函数
                share.fire(file, arguments);
                //自动上传
                params.auto && arguments[0] === 'selectOnce' && file.upload();
            }
            //上传调用函数
            file.upload = function (fileId, check) {
                file._upload && file._upload(fileId, check);
            };
            //取消指定ID文件
            file.cancel = function (fileId) {
                file._cancel && file[fileId ? '_cancel' : '_clear'](fileId);
            };
            //读取设置配置
            file.setting = function (name, value) {
                name === 'exts' && (value = '*.'+ (value ? value.replace(/;/g,';*.') : '*'));
                //初始化完成
                if( file._setting ) {
                    if( file.setting.wait.length ) {
                        for(var i in file.setting.wait) {
                            //按顺序执行设置
                            file._setting(
                                file.setting.map[file.setting.wait[i][0]] || file.setting.wait[i][0], 
                                file.setting.wait[i][1]
                            );
                        }
                        file.setting.wait = [];
                    }

                    //执行函数
                    if( name ) return file._setting(name, value);
                //未初始化
                } else {
                    file.setting.wait.push([name, value]);
                }
            };
            //等等处理列表
            file.setting.wait = [];
            //属性映射列表
            file.setting.map = {
                'path'   : 'pagepath',
                'script' : 'script',
                'folder' : 'folder',
                'queue'  : 'queueSizeLimit',
                'exts'   : 'fileExt',
                'multi'  : 'multi',
                'size'   : 'sizeLimit',
                'check'  : 'checkScript',
                'limit'  : 'simUploadLimit'
            };

            //鼠标传入节点
            L.event(params.node, 'mouseenter', function(event) {
                flash.modeEnter.call(params.node, event, file);
            });
            //鼠标离开组件
            L.event(file, 'mouseleave', flash.fileLeave);

            return file;
        },

        /**
         * 描述 : 鼠标传入节点调整组件位置
         * 参数 :
         *      event : 指定的节点
         *      file  : 上传组件
         * 作者 : Edgar.lee
         */
        'modeEnter' : function (event, file) {
            var rect = share.getRect(this);

            file.style.top = rect.top + 'px';
            file.style.left = rect.left + 'px';
            file.style.width = rect.right - rect.left - 2 + 'px';
            file.style.height = rect.bottom - rect.top + 'px';
        },

        /**
         * 描述 : 鼠标离开组件调整组件位置
         * 作者 : Edgar.lee
         */
        'fileLeave' : function (event) {
            this.style.top = '-9px';
            this.style.height = '1px';
            this.style.width = '1px';
        }
    };

    //获取最大字节
    L.ajax({
        "url"     : share.rootDir + '/maxSize.php',
        "async"   : false,
        "success" : function (data) {
            share.maxSize = L.json(data).maxSize;
        }
    });
    //启动节点变动监听
    L.event(function(){share.obServer(false);});

    /**
     * 描述 : 实例化对象
     * 参数 :
     *      params : {
     *          "node"   : 上传对象(原始js节点对象)
     *          "auto"   : (true)是否自动上传, true=是, false(默认)=否
     *          "check"  : ("")上传时检查文件是否存在服务器脚本,触发 checkExist 时使用
     *          "exts"   : ("")扩展名 Extend
     *          "file"   : ("")指定带路径扩增名的文件,默认自动生成相同扩展的文件名
     *          "folder" : ("/upload")上传的文件夹
     *          "limit"  : (3)并发上传数量
     *          "multi"  : (false)是否允许选择多文件, true=是, false(默认)=否
     *          "path"   : (集成环境地址)script 参数前缀,上传插件的根目录
     *          "queue"  : (99)最大队列数量
     *          "script" : ("/oUpload.php")接收上传文件的脚步路径
     *          "show"   : (true)是否显示进度条.true(默认)=显示, false=隐藏, 字符串=自定义属性(class="abc")
     *          "size"   : (服务器的限制)最大单文件字节数
     *          "call"   : (null)方法=单个上传成功后回调,对象=对应的事件回调 {
     *              "select"      : 当选择一个文件后,
     *              "selectOnce"  : 当一次选择结束后,
     *              "queueFull"   : 选择上传数超过最大限制,
     *              "checkExist"  : 校验文本是否存在,
     *              "open"        : 单文件开始上传时,
     *              "progress"    : 单文件上传进度,
     *              "complete"    : 单文件上传完成,
     *              "allComplete" : 全部上传完成,
     *              "cancel"      : 删除指定上传,
     *              "clearQueue"  : 清除上传队列,
     *              "error"       : 出现相关错误
     *          }
     *      }
     * 返回 : {
     *          "uniqid"  : 唯一区分值
     *          "node"    : 上传节点对象
     *          "cancel"  : 取消上传(文件ID(默认取消全部))
     *          "upload"  : 上传文件(文件ID(默认取消全部), 检查回调(false=默认尝试,true=上传))
     *          "setting" : 设置读取(读写值, 对应值(不写便是读取))
     *      }
     * 作者 : Edgar.lee
     */
    return function (params) {
        var file, context = {
            'node'    : params.node,
            'cancel'  : function () {
                var items = typeof arguments[0] === 'string' ? 
                    L.val({}, arguments[0], true).obj : share.fileList[context.uniqid].guiNode.items;
                //删除对应文件
                for(var i in items) file.cancel.call(file, i);
            },
            'upload'  : function () {
                file.upload.apply(file, typeof arguments[0] === 'string' ? arguments : []);
            },
            'setting' : function () {
                var result = file.setting.apply(file, arguments);

                //设置模式
                if( arguments.length > 1 ) {
                    params[arguments[0]] = arguments[1];
                    if( arguments[0] === 'show' ) {
                        share.fileList[context.uniqid].guiNode.block.style.display = arguments[1] ? 'block' : 'none';
                    }
                }

                return result === undefined ? params[arguments[0]] : result;
            }
        };

        params = L.each({
            'auto'   : true,
            'check'  : '',
            'exts'   : '',
            'file'   : '',
            'folder' : '/upload',
            'limit'  : 3,
            'multi'  : false,
            'path'   : share.rootDir,
            'queue'  : 99,
            'script' : '/oUpload.php',
            'show'   : true
        }, params);

        //最大字节校验
        (!params.size || params.size > share.maxSize) && (params.size = share.maxSize);
        //初始化对象
        file = (window.FormData ? html5 : flash).inst(params);
        //初始化GUI接口
        share.initGui({'context' : context, 'fileObj' : file, 'params'  : params});

        if( params.call ) {
            typeof params.call === 'function' && (params.call = {'complete' : params.call});
            file.callback.push(params.call);
        }

        return context;
    }
})();