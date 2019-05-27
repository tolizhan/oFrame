<?php
if (OF_DEBUG === false) {
    exit('Access denied: production mode.');
} else if (!empty($_POST)) {
    $result = null;

    switch ($_POST['type']) {
        //获取语言包目录
        case 'getDir':
            $_POST += array('path' => '', 'status' => false);

            //路径格式化
            $_POST['path'] = of_base_com_str::realpath($_POST['path']);
            if (empty($_POST['path'][1])) {
                $_POST['path'] = '';
            //安全校验
            } else if ($_POST['path'][0] === '.' || $_POST['path'][1] === '.') {
                exit('路径溢出');
            }

            //读取列表
            $result = &of_base_language_toolBaseClass::getDir($_POST['path']);
            unset($result['/base']);

            //读取状态
            if ($_POST['status']) {
                $_POST['status']['ignore'] = $_POST['status']['ignore'] === 'true';
                $_POST['status']['keyInv'] = $_POST['status']['keyInv'] === 'true';

                foreach ($result as $k => &$v) {
                    $v = array(
                        'isDir'  => $v,
                        'status' => of_base_language_toolBaseClass::status($k, $_POST['status'])
                    );
                }
            }
            break;
        case 'getFile':
            $_POST['status']['ignore'] = $_POST['status']['ignore'] === 'true';
            $_POST['status']['keyInv'] = $_POST['status']['keyInv'] === 'true';

            of_base_language_toolBaseClass::status('/base/source' . $_POST['path'], $_POST['status']);

            $result = &$_POST['status']['pack'];
            break;
        //创建语言包
        case 'createLanguagePage':
            $index = &of_base_language_toolBaseClass::pack('/base');
            of_base_language_toolBaseClass::pack('/' . $_POST['name'], $index);
            break;
        //语言包导入
        case 'languagePageImport':
            of_base_language_toolBaseClass::exportOrImport($_POST['name'], ROOT_DIR . OF_DATA . $_POST['file']);
            break;
        //语言包导出
        case 'languagePageExport':
            of_base_language_toolBaseClass::exportOrImport($_POST['name']);
            exit;
        case 'languagePageUpdate':
            //路径格式化
            $_POST['path'] = of_base_com_str::realpath($_POST['path']);
            if (empty($_POST['path'][1]) || $_POST['path'][1] === '.') {
                exit('路径溢出');
            } else if (isset($_POST['change'])) {
                $_POST['path'] = '/base/source' . $_POST['path'];

                //保存语言包
                if (is_array($_POST['change'])) {
                    $index = &of_base_language_toolBaseClass::getFile($_POST['path']);

                    //忽略语言包
                    if (isset($_POST['change']['ignore'])) {
                        foreach ($_POST['change']['ignore'] as $k => &$v) {
                            $k = json_decode(stripslashes($k), true);
                            if ($v === 'true') {
                                $index[$k['type']][$k['action']][$k['string']][$k['key']]['ignore'] = true;
                            } else {
                                unset($index[$k['type']][$k['action']][$k['string']][$k['key']]['ignore']);
                            }
                        }
                    }

                    //删除语言包
                    if (isset($_POST['change']['delete'])) {
                        foreach ($_POST['change']['delete'] as $k => &$v) {
                            $k = json_decode(stripslashes($k), true);

                            //删除引用
                            if ($k['type'] === 'phpLink' || $k['type'] === 'jsLink') {
                                unset($index[$k['type']][$k['action']][$k['index']]);
                            //删除翻译
                            } else {
                                unset($index[$k['type']][$k['action']][$k['string']][$k['key']]);

                                //清空翻译
                                if (empty($index[$k['type']][$k['action']][$k['string']])) {
                                    unset($index[$k['type']][$k['action']][$k['string']]);
                                }
                            }
                        }
                    }

                    of_base_language_toolBaseClass::getFile($_POST['path'], $index);
                //删除语言包
                } else {
                    of_base_com_disk::delete(
                        of::config('_of.language.path', OF_DATA . '/_of/of_base_language_packs', 'dir') . $_POST['path'], true
                    );
                }
            }
            break;
        case 'mergerFiles':
            $path = of::config('_of.language.path', OF_DATA . '/_of/of_base_language_packs', 'dir');
            $list = array_flip(glob(dirname($path) . '/*'));
            unset($list[$path]);
            $path .= '/base/source';

            foreach ($list as $kp => &$vd) {
                $kp .= '/base/source';
                while (of_base_com_disk::each($kp, $vd)) {
                    foreach ($vd as $k => &$v) {
                        //是文件
                        if ($v === false) {
                            //基类文件路径
                            $temp = $path . substr($k, strlen($kp));
                            $temp = array(
                                'baseFile' => $temp,
                                'basePack' => of_base_com_disk::file($temp, true, true),
                                'newPack'  => of_base_com_disk::file($k, true, true),
                            );

                            of::arrayReplaceRecursive($temp['basePack'], $temp['newPack']);
                            //写回数据
                            of_base_com_disk::file($temp['baseFile'], $temp['basePack'], true);
                        }
                    }
                }
            }
            break;
        case 'mergerGlobal':
            //整理基类
            $index = &of_base_language_toolBaseClass::merge('/base/source', '/base');
            $list = of_base_language_toolBaseClass::getDir();
            unset($list['/base']);

            foreach ($list as $k => &$v) {
                of_base_language_toolBaseClass::pack($k, $index);
            }
            break;
    }

    $temp = $result === null ? 1 : $result;
    echo of_base_com_data::json($temp);
} else {
    of_view::head(array());
?>
<style>
body{ background-color:#FFFFFF; font-family:宋体;}
label{ cursor:pointer;}
a:link{ text-decoration:none;}
a:hover{ text-decoration:underline; cursor:pointer;}
.yellowBg{ background-color:#FFFFCC;}
.clear{ clear:both;}
.center{ text-align:center;}
.table{ border:1px solid #CCC; width:100%; margin-top:10px;}
.table tbody tr:hover{ background-color:#EEE; }
.table tbody th{ font-weight:normal; white-space: nowrap;}
.table th{ padding-top:5px;}
.table td{ padding-left:5px;}
.nav { margin-bottom:5px;}

/*工具条*/
.tool{ overflow:hidden; width:100%; border:1px solid #CCC; margin-bottom:5px;}
.url{ float:left; word-break:keep-all; white-space:nowrap;}
.url b{ border:solid #CCCCCC; border-width:0 1px; cursor:pointer; padding-left:3px; display:inline-block; margin:3px 0;}
.operating{ position:absolute; right:7px; background-color:#FFFFFF;}
.operating .operating{ right:0px; background-color:transparent;}

/*磁盘区*/
.disk{ border:1px solid #CCC; padding-bottom:10px;}
.dir{ position:relative; overflow:hidden; cursor:pointer; float:left; width:100px; height:100px; border:1px solid; margin:10px 0 0 10px;}
.dir font{ position:absolute; top:25px; left:40px; font-size:40px;}
.dir div{ float:right; display:none; overflow:hidden; width:10px; height:10px; border-width:0px 0px 1px 1px; border-style:solid;}
.dir span{ word-break:break-all; word-wrap:break-word;}
.folder .file{ display:none;}    /*是文件夹*/
.file .folder{ display:none;}    /*是文件*/
.noTr .noTr{ display:block; background-color:#F00;}    /*未翻译*/
.discard .discard{ display:block; background-color:#FF0;}    /*有废弃*/
.ignore .ignore{ display:block; background-color:#999;}    /*有忽略*/

/*合并导入*/
.mergerImport td{ padding:10px;}
.mergerImport font, .mergerImport input{ font-size:36px; font-weight:bold;}

/*合并*/
.merger td{ padding:10px;}
.merger font, .merger .mergerInput{ font-size:36px; font-weight:bold;}

/*浮动条*/
.floatBar {
    /*display: none;*/
    position: fixed;
    right: 0;
    top: 200px;
    _position: absolute;
_left:expression(eval(document.documentElement.scrollLeft+document.documentElement.clientWidth-this.offsetWidth)-(parseInt(this.currentStyle.marginLeft, 10)||0)-(parseInt(this.currentStyle.marginRight, 10)||0)-1);
_top:expression(eval(document.documentElement.scrollTop) + 200);
    background: none repeat scroll 0 0 #FFC;
    border: 1px dotted #CCCCCC;
    font-size: 9pt;
    margin-bottom: 10px;
    padding: 6px;
    cursor: pointer;
    text-align: center;
    width: 12px;
    size: 9pt;
    z-index: 2147483647
}
.floatBar span {
    display: block;
}
</style>

<!-- 功能栏 -->
<div class="nav">
    <label><input tag="translate" name="nav" type="radio" checked />翻译</label>
    <label><input tag="merger" name="nav" type="radio" />整理</label>
</div>
<!-- 翻译 -->
<div id="translate">
    <!-- 工具条 -->
    <div class="tool">
        <span class="operating">
            <input id="exportButton" type="button" value="导出" />
            <input id="importButton" type="button" value="导入" />
        </span>
        <span class="url">
            <input id="translateCreateLanguagePage" type="button" value="新建" />
            语言包
            <select id="translateSelectLanguagePage">
                <option value="">请选择</option>
            </select>
            <input id="translateInputLanguagePage" type="text" style="display:none;" />
        </span>
        <div class="clear"></div>
    </div>
</div>
<!-- 导入 -->
<div id="merger" style="display:none;">
    <!-- 浮动栏 -->
    <div class="floatBar">
        <span><a type="save">保存</a>━<a type="delete">删除</a></span>
    </div>
    <!-- 工具条 -->
    <div class="tool">
        <span class="operating">
            <input id="mergerFiles" type="button" value="合并">
            <input id="mergerGlobal" type="button" value="整理">
        </span>
        <span class="url">
            <label><input id="getDiscardState" type="checkbox" />显示忽略</label>
            <label><input name="translateLevel" type="radio" value="key" />键级</label>
            <label><input name="translateLevel" type="radio" value="page" checked />页级</label>
            <b>..</b>
            <span class="urlBar">/</span>
        </span>
        <div class="clear"></div>
    </div>
    <!-- 目录结构 -->
    <div class="disk">
        <div class="dir folder noTr discard ignore">
            <font class="folder">D</font>
            <font class="file">F</font>
            <div class="noTr" title="未翻译"></div>
            <div class="discard" title="有废弃"></div>
            <div class="ignore" title="有忽略"></div>
            <span>地址地址地址地址</span>
        </div>
        <div class="dir file noTr discard ignore">
            <font class="folder">D</font>
            <font class="file">F</font>
            <div class="noTr" title="未翻译"></div>
            <div class="discard" title="有废弃"></div>
            <div class="ignore" title="有忽略"></div>
        </div>
        <div class="clear"></div>
    </div>
    <!-- 翻译区 -->
    <table class="translate table" border="0" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th>类型</th>
                <th>语言</th>
                <th>键值</th>
                <th>信息</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>js</td>
                <td>源语言</td>
                <td>key</td>
                <td>viewTest</td>
                <th><label><input type="checkbox" checked="checked" />忽略</label> <a>删除</a></th>
            </tr>
            <tr>
                <td>php</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <th>&nbsp;</th>
            </tr>
        </tbody>
    </table>
    <!-- 引用区 -->
    <table class="index table" border="0" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th>类型</th>
                <th>引用</th>
                <th>信息</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>js</td>
                <td>/xxx/index.php</td>
                <td>viewTest</td>
                <th><a>删除</a></th>
            </tr>
            <tr>
                <td>js</td>
                <td>/xxx/index.php</td>
                <td>viewTest</td>
                <th><a>删除</a></th>
            </tr>
        </tbody>
    </table>
</div>
<script>
(function () {
    var temp, toolObj = {
        /**
         * 描述 : 整理变更列表
         * 作者 : Edgar.lee
         */
        'changeList' : null,

        /**
         * 描述 : 发送post数据
         * 作者 : Edgar.lee
         */
        'post' : function (data, callback) {
            $.ajax({
                "type"     : "POST",
                "url"      : "?c=of_base_language_tool",
                "async"    : false,
                "data"     : data,
                "success"  : callback,
                "dataType" : "json",
                "error"    : function (jqXHR) {
                    alert('操作失败 : ' + jqXHR.responseText);
                }
            });
        },

        /**
         * 描述 : 获取语言包列表
         * 作者 : Edgar.lee
         */
        'getList' : function (name) {
            toolObj.post({'type' : 'getDir'}, function (json) {
                temp = '';

                $.each(json, function (path) {
                    temp += '<option value="' +path+ '">' +path.substr(1)+ '</option>'
                });

                temp = $('#translateSelectLanguagePage').html(temp);
                name && temp.find('[value="/' +name+ '"]').prop('selected', true);
            })
        },

        /**
         * 描述 : 更新磁盘基类目录
         * 作者 : Edgar.lee
         */
        'getDir' : function (path, isTip) {
            (temp = $('.urlBar').html()) === '/' && (temp = '');

            switch ( $.trim(path) ) {
                case ''  :
                case '.' :
                    path = temp;
                    break;
                case '..':
                    (path = temp.split('/')).pop();
                    path = path.join('/');
                    break;
                default  :
                    path = temp + path;
            }

            $('.urlBar').html((path.substr(0, 1) === '/' ? '' : '/') + path);
            isTip === false || window.L.open('tip')('正在加载');
            $('#merger table tbody').html('');
            $('#merger .floatBar').hide();

            toolObj.post({
                'type'   : 'getDir', 
                'path'   : '/base/source' + path,
                'status' : {
                    'ignore' : $('#getDiscardState').prop('checked'),
                    'keyInv' : $('[name=translateLevel]:checked').val() === 'key'
                }
            }, function (json) {
                temp = '';
                $.each(json, function (path) {
                    temp += '<div class="dir ' + (this.isDir === true ? 'folder' : 'file') + (this.status ? ' discard' : '') + '">' +
                        '<font class="folder">D</font>' +
                        '<font class="file">F</font>' +
                        '<div class="discard" title="有废弃"></div>' +
                        '<span>' +path.substr(path.lastIndexOf('/') + 1)+ '</span>' +
                    '</div>';
                });
                $('.disk').html(temp + '<div class="clear"></div>')
                    //切换目录
                    .children('.folder').click(function () {
                        toolObj.getDir('/' + $(this).children('span').html());
                    }).end()
                    //读取数据
                    .children('.file').click(function () {
                        window.L.open('tip')('正在加载');
                        $(this).parent().children('.yellowBg').removeClass('yellowBg').end().end().addClass('yellowBg');

                        toolObj.post({
                            'type'   : 'getFile', 
                            'path'   : ($('.urlBar').html() + '/' + $(this).children('span').html())
                                .replace('//', '/'),
                            'status' : {
                                'ignore' : $('#getDiscardState').prop('checked'),
                                'keyInv' : $('[name=translateLevel]:checked').val() === 'key'
                            }
                        }, function (json) {
                            //初始变更列表
                            toolObj.changeList = {'ignore' : {}, 'delete' : {}};
                            //显示浮动层
                            $('#merger .floatBar').show();

                            temp = '';
                            $.each({'phpPack' : 1, 'jsPack' : 1}, function (pkey) {
                                json[pkey] && $.each(json[pkey], function (action) {
                                    $.each(this, function (string) {
                                        $.each(this, function (key) {
                                            temp += '<tr ' +(this.invalid ? 'class="yellowBg"' : '')+ '>' +
                                                '<td key="type" value="' +encodeURIComponent(pkey)+ '">' +pkey+ '</td>' +
                                                '<td key="string" value="' +encodeURIComponent(string)+ '">' +string+ '</td>' +
                                                '<td key="key" value="' +encodeURIComponent(key)+ '">' +key+ '</td>' +
                                                '<td key="action" value="' +encodeURIComponent(action)+ '">' +action+ '</td>' +
                                                '<th>' +
                                                    '<label><input key="ignore" type="checkbox" ' +(this.ignore ? 'checked' : '')+ ' />忽略</label> ' +
                                                    '<a key="delete">删除</a>' +
                                                '</th>' +
                                            '</tr>';
                                        });
                                    });
                                });
                            });
                            //翻译列表
                            $('#merger table tbody').eq(0).html(temp);

                            temp = '';
                            $.each({'phpLink' : 1, 'jsLink' : 1}, function (pkey) {
                                json[pkey] && $.each(json[pkey], function (action) {
                                    $.each(this, function (index) {
                                        temp += '<tr ' +(this == true ? 'style="background-color: red;"' : '')+ '>' +
                                            '<td key="type" value="' +encodeURIComponent(pkey)+ '">' +pkey+ '</td>' +
                                            '<td key="index" value="' +encodeURIComponent(index)+ '">' +index+ '</td>' +
                                            '<td key="action" value="' +encodeURIComponent(action)+ '">' +action+ '</td>' +
                                            '<th><a key="delete">删除</a></th>' +
                                        '</tr>';
                                    });
                                });
                            });
                            //引用列表
                            $('#merger table tbody').eq(1).html(temp);

                            $('#merger table tbody th a, #merger table tbody th input').click(function () {
                                temp = {};
                                $(this).parents('tr').children('td').each(function () {
                                    temp[$(this).attr('key')] = decodeURIComponent(this.getAttribute('value'));
                                });
                                temp = L.json(temp);

                                //忽略列表
                                if( $(this).attr('key') === 'ignore' ) {
                                    toolObj.changeList['ignore'][temp] = this.checked;
                                //删除列表
                                } else {
                                    delete toolObj.changeList['ignore'][temp];
                                    toolObj.changeList['delete'][temp] = true;
                                    $(this).parents('tr').remove();
                                }
                            });
                        });
                        window.L.open('tip')();
                    });

                window.L.open('tip')();
            });
        }
    }

    //切换导航
    $('.nav input').click(function () {
        $('.nav').nextAll('div').hide();
        $('#' + $(this).attr('tag')).show();
    });

    /*翻译界面*********************************************************************************************************/
    //初始化列表
    toolObj.getList();

    //新建语言包
    $('#translateCreateLanguagePage').click(function () {
        var thisObj = $(this);
        var sObj = $('#translateSelectLanguagePage');
        var iObj = $('#translateInputLanguagePage');

        //保存新建
        if( sObj.css('display') === 'none' ) {
            if( /^\w+$/.test(temp = $.trim(iObj.val())) ) {
                toolObj.post({
                    'type' : 'createLanguagePage',
                    'name' : temp
                }, function () {
                    thisObj.val("新建");
                    sObj.show();
                    iObj.hide().val('');
                    //更新列表
                    toolObj.getList(temp);
                    window.L.open('tip')('创建成功');
                });
            } else {
                window.L.open('tip')('命名无效');
            }
        //显示创建
        } else {
            thisObj.val("保存");
            sObj.hide();
            iObj.show();
        }
    });

    //试题导出
    $('#exportButton').click(function () {
        if( !arguments.callee.submitObj )
        {
            temp = '<iframe name="languagePageExport" style="display: none;"></iframe>' +
            '<form action="#" method="post" target="languagePageExport" style="display: none;">' +
                '<input type="" name="type" value="languagePageExport">' +
                '<input type="" name="name" value="' +$('#translateSelectLanguagePage').val()+ '">' +
            '</form>';
            arguments.callee.submitObj = $(temp).appendTo(document.body);
        }

        arguments.callee.submitObj.eq(1).submit();
    });

    //上传导入
    window.L.open('oUpload')({
        'node' : $('#importButton').get(0),
        'exts' : 'csv',
        'call' : function () {
            window.L.open('tip')('正在导入');
            toolObj.post({
                'type' : 'languagePageImport',
                'file' : arguments[3],
                'name' : $('#translateSelectLanguagePage').val()
            }, function () {
                window.L.open('tip')('导入成功');
            });
        }
    });

    /*整理界面*********************************************************************************************************/
    //初始目录
    toolObj.getDir('.', false);

    //上一层
    $('#merger .url b').click(function () {
        toolObj.getDir('..');
    });

    //浮动层操作
    $('#merger .floatBar a').click(function () {
        temp = $(this).attr('type') === 'save' ? toolObj.changeList : 'delete';
        toolObj.post({
            'type'   : 'languagePageUpdate', 
            'path'   : $('.urlBar').html() + '/' + $('#merger .disk .yellowBg span').html(), 
            'change' : temp
        }, function () {
            window.L.open('tip')('操作成功');
            temp === 'delete' ? toolObj.getDir('.', false) : $('#merger .disk .yellowBg').click();
        });
    });

    //合并源文本
    $('#mergerFiles').click(function () {
        if( window.confirm('会将并列目录合并到当前当前目录, 如 :\n' +
        '    /cc/base/source : 当前目录\n' +
        '    /xx/base/source : 并列目录\n' +
        '    /yy/base/source : 并列目录\n' +
        '\n是否开始?') ) {
            window.L.open('tip')('开始合并,请等待...');
            toolObj.post({'type' : 'mergerFiles'}, function () {
                window.L.open('tip')('操作成功');
            });
        }
    });

    //整理全局文件
    $('#mergerGlobal').click(function () {
        if( window.confirm('将会生成基类包的全局文件并同步到并列目录中, 如 :\n' +
        '    /cc/base : 基类目录\n' +
        '    /cc/xx   : 并列目录\n' +
        '    /cc/yy   : 并列目录\n' +
        '\n是否开始?') ) {
            window.L.open('tip')('开始整理,请等待...');
            toolObj.post({'type' : 'mergerGlobal'}, function () {
                window.L.open('tip')('操作成功');
            });
        }
    });
})();
</script>
<?php
    of_view::head(false);
}