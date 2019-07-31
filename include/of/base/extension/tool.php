<?php
class of_base_extension_tool {
    public function __construct() {
        of::dispatch('action') === 'index' && $this->printHtml();
    }

    /**
     * 描述 : 安装更新扩展
     * 作者 : Edgar.lee
     */
    public function setup() {
        if (isset($_GET['eKey'])) {
            L::buffer(false);
            $temp = of_base_extension_manager::setupExtension($_GET['eKey'], array(__CLASS__, 'backMsg'));
            echo '<br>',
                L::getText($temp ? '安装成功' : '安装失败'),
                '<script>',
                'window.parent.$("#oDialogDiv_" + window.frameElement.id.split("_")[2]).find("> .title > a").show();',
                'window.parent.document.getElementById("extensionPaging").paging("+0");',
                'document.body.scrollTop = 10000;',
                '</script>';
        }
    }

    /**
     * 描述 : 移除扩展
     * 作者 : Edgar.lee
     */
    public function remove() {
        if (isset($_GET['eKey'])) {
            L::buffer(false);
            $temp = of_base_extension_manager::removeExtension($_GET['eKey'], array(__CLASS__, 'backMsg'));
            echo '<br>',
                L::getText($temp ? '卸载成功' : '卸载失败'),
                '<script>',
                'window.parent.$("#oDialogDiv_" + window.frameElement.id.split("_")[2]).find("> .title > a").show();',
                'window.parent.document.getElementById("extensionPaging").paging("+0");',
                'document.body.scrollTop = 10000;',
                '</script>';
        }
    }

    /**
     * 描述 : 扩展暂停运行状态切换
     * 作者 : Edgar.lee
     */
    public function pauseOrRun() {
        if (isset($_GET['eKey'])) {
            echo (int)of_base_extension_manager::changeState($_GET['eKey'], isset($_GET['state']) && $_GET['state'] === 'true' ? true : null);
        }
    }

    /**
     * 描述 : 扩展选项
     * 作者 : Edgar.lee
     */
    public function options() {
        if (isset($_GET['eKey'])) {
            $temp = of_base_extension_manager::getExtensionInfo();
            $info = &$temp[$_GET['eKey']];
            if (isset($info['state']) && $info['state'] === '1') {
                of_base_extension_match::callExtension($_GET['eKey'], $info['config']['options']);
            }
        }
    }

    /**
     * 描述 : 扩展数据管理
     * 作者 : Edgar.lee
     */
    public function dataManager() {
        if (isset($_GET['eKey'])) {
            L::buffer(false);
            $temp = of_base_extension_manager::dataManager($_GET['eKey'], array(__CLASS__, 'backMsg'), $_GET['dir'] ? $_GET['dir'] : null);
            echo '<br>',
                L::getText($temp ? '操作成功' : '操作失败'),
                '<script>',
                'window.parent.$("#oDialogDiv_" + window.frameElement.id.split("_")[2]).find("> .title > a").show();',
                'window.parent.document.getElementById("extensionPaging").paging("+0");',
                'document.body.scrollTop = 10000;',
                '</script>';
        }
    }

    /**
     * 描述 : 扩展备份数据
     * 作者 : Edgar.lee
     */
    public function dataList() {
        if (isset($_GET['eKey'])) {
            if (is_dir(
                $temp = of_base_extension_manager::getConstant('extensionSave') .
                    "/{$_GET['eKey']}/_info/backupData"
            )) {
                $temp = array_flip(scandir($temp, 1));
            } else {
                $temp = array();
            }
            unset($temp['.'], $temp['..'], $temp['installData']);
            foreach ($temp as $dir => &$v) {
                echo '<label class="backupList"><input type="radio" name="backupList" value="', 
                    $dir, '" />', date('Y-m-d H:i:s', strtotime($dir)), '</label>';
            }
        }
    }

    /**
     * 描述 : 获取语言包目录
     * 作者 : Edgar.lee
     */
    public function getLanguageDir() {
        if (isset($_GET['eKey'])) {
            if (is_dir($temp = of_base_extension_manager::getConstant('extensionDir') . "/{$_GET['eKey']}/_info/language")) {
                $list = array_flip(glob($temp . '/*', GLOB_ONLYDIR));
                unset($list[$temp . '/base']);
                $list = array_flip($list);
                foreach ($list as $k => &$v) $v = basename($v);

                echo json_encode($list);
            } else {
                echo '{}';
            }
        }
    }

    /**
     * 描述 : 多语言打包与更新
     * 作者 : Edgar.lee
     */
    public function language() {
        if (isset($_GET['eKey']) && !is_numeric($_GET['name'])) {
            $temp = of_base_extension_manager::getConstant('extensionDir') . "/{$_GET['eKey']}/_info/language";
            of_base_language_toolBaseClass::pack("_{$temp}/base", of_base_language_toolBaseClass::pack("_{$temp}/base"));

            $temp .= '/' . $_GET['name'];
            //导入
            if (isset($_GET['file'])) {
                of_base_language_toolBaseClass::exportOrImport('_' . $temp, ROOT_DIR . OF_DATA . $_GET['file']);
                echo '1';
            //导出
            } else {
                of_base_language_toolBaseClass::exportOrImport('_' . $temp);
            }
        }
    }

    /**
     * 描述 : 消息回调
     * 作者 : Edgar.lee
     */
    public static function backMsg($msg = array()) {
        if (is_array($msg)) {
            $msg += array(
                'state'   => 'tip',
                'message' => null,
                'info'    => null
            );
            unset($msg['type']);
            if (!$msg['info']) unset($msg['info']);
        } else {
            $msg = array('state' => 'tip', 'message' => $msg);
        }
        echo join(' : ', $msg), '<script>document.body.scrollTop = 10000;</script>', "<br>\n";
    }

    /**
     * 描述 : 响应请求
     * 作者 : Edgar.lee
     */
    public function encrypt() {
        of_base_extension_toolBaseClass::encryptCode($_GET['eKey']);
        echo 1;
    }

    /**
     * 描述 : 扩展分页
     * 作者 : Edgar.lee
     */
    public function extensionPaging($params = array()) {
        $_POST += array(
            'size' => 10,
            'page' => 1,
        );
        $temp = $_POST['size'];                                         //每页条数
        $start = ($_POST['page'] - 1) * $temp;                          //开始数据
        $end = $start + $temp;                                          //结束数据
        $temp = of_base_extension_manager::getExtensionInfo();          //扩展数据
        $data = array();                                                //本次扩展
        $i = 0;
        $options = '<a class="options" onclick="managerObj.options(this); return false;">选项</a>';

        foreach ($temp as $k => &$v) {
            if (
                ++$i > $start && $i <= $end && (
                    empty($params['find']) || 
                    (strpos($k, $params['find']) !== false || strpos($v['config']['properties']['name'], $params['find']) !== false)
                )
            ) {
                $data[$i] = array(
                    'key'         => $k,                                                                                                                 //扩展夹
                    'name'        => isset($v['config']['properties']['name']) ? $v['config']['properties']['name'] : htmlspecialchars($k),              //扩展名
                    'version'     => isset($v['config']['properties']['version']) ? $v['config']['properties']['version'] : '',                          //版本号
                    'description' => isset($v['config']['properties']['version']) ? htmlspecialchars($v['config']['properties']['description']) : '',    //描述
                    '_options'    => isset($v['config']['options']) && $v['config']['options'] && $v['state'] === '1' ? $options : '',                   //是否有选项界面
                    'state'       => $v['state'] * 10,                                                                                                   //版本状态
                );
                switch ($v['state']) {
                    case '3.1':
                        $data[$i]['stateStr'] = '配置文件有问题';
                        break;
                    case '3':
                        $data[$i]['stateStr'] = '升级中';
                        break;
                    case '2.1':
                        $data[$i]['stateStr'] = '需要升级';
                        break;
                    case '2':
                        $data[$i]['stateStr'] = '已暂停';
                        break;
                    case '1':
                        $data[$i]['stateStr'] = '已运行';
                        break;
                    case '0':
                        $data[$i]['stateStr'] = '未安装';
                        break;
                }
            }
        }

        $config = array(
            '扩展名'   => '<a title="{`description`}" key="{`key`}">{`name`}</a>',
            '扩展键'   => '{`key`}',
            '版本号'   => '{`version`}',
            '当前状态' => '{`stateStr`}',
            '操作'     => array(
                '_attr' => array(
                    'attr' => 'class="align_center" style="width:auto"',
                    'body' => '<div class="extensionTools extensionState{`state`}">
                        <a class="forcedStop" onclick="managerObj.pauseOrRun(this, true); return false;">强制停止</a>
                        <a class="setup" onclick="managerObj.setup(this); return false;">安装</a>
                        <a class="update" onclick="managerObj.setup(this); return false;">升级</a>
                        <a class="run" onclick="managerObj.pauseOrRun(this); return false;">运行</a>
                        <a class="pause" onclick="managerObj.pauseOrRun(this); return false;">暂停</a>
                        {`_options`}
                        <a class="backup" onclick="managerObj.dataManager(this); return false;">备份</a>
                        <a class="restore" onclick="managerObj.restore(this); return false;">恢复</a>
                        <a class="remove" onclick="managerObj.remove(this); return false;">卸载</a>
                        <a class="factory" onclick="managerObj.dataManager(this, \'installData\'); return false;">扩展打包</a>
                        <a class="factory" onclick="managerObj.encrypt(this); return false;">加密</a>
                        <span>
                            <input class="inputLanguagePage" style="display: none;" type="text" onblur="managerObj.switchLanguage(this);">
                            <select class="selectLanguagePage" onchange="managerObj.switchLanguage(this);">
                                <option value="0">语言包</option>
                                <option value="1">新建</option>
                                <option value="">2</option>
                            </select>
                            <a class="factory" onclick="managerObj.exports(this); return false;">导出</a>
                            <a class="factory oUpload" onclick="return false;">导入</a>
                        </span>
                    </div>'
                )
            ),
            '_attr' => array(
                'attr'   => 'id=extensionPaging',
                'items'  => count($temp),
                'params' => &$params,
                'data'   => $data,
                'method' => __METHOD__,
            )
        );

        return of_base_com_com::paging($config);
    }

    /**
     * 描述 : 打印html
     * 作者 : Edgar.lee
     */
    private function printHtml() {
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

/*工具条*/
.tool{ overflow:hidden; width:100%; border:1px solid #CCC; margin-bottom:5px;}
.url{ float:left; word-break:keep-all; white-space:nowrap;}
.url b{ border:solid #CCCCCC; border-width:0 1px; cursor:pointer; padding-left:3px; display:inline-block; margin:3px 0;}

/*磁盘区*/
.disk{ border:1px solid #CCC; padding-bottom:10px; margin-bottom:5px;}
.dir{ position:relative; overflow:hidden; cursor:pointer; float:left; width:100px; height:100px; border:1px solid; margin:10px 0 0 10px;}
.dir font{ position:absolute; top:25px; left:40px; font-size:40px;}
.dir div{ float:right; display:none; overflow:hidden; width:10px; height:10px; border-width:0px 0px 1px 1px; border-style:solid;}
.dir span{ word-break:break-all; word-wrap:break-word;}
.folder .file{ display:none;}    /*是文件夹*/
.file .folder{ display:none;}    /*是文件*/

/*扩展工具*/
.extensionTools a {
    display:none;
    margin-right:5px;
    cursor:pointer;
}
.extensionTools .options {
    display:inline;
}
.extensionTools .factory {
    display:inline;
    color: red;
}
.extensionState30 .forcedStop {
    display:inline;
}
.extensionState21 .update,
.extensionState21 .remove,
.extensionState21 .backup,
.extensionState21 .restore {
    display:inline;
}
.extensionState20 .run,
.extensionState20 .remove,
.extensionState20 .backup,
.extensionState20 .restore {
    display:inline;
}
.extensionState10 .pause,
.extensionState10 .remove,
.extensionState10 .backup,
.extensionState10 .restore {
    display:inline;
}
.extensionState0 .setup {
    display:inline;
}

/*扩展恢复列表*/
.backupList {
    cursor: pointer;
    display: inline-block;
}
</style>
</head>

<body>
<div class="tool">
    <span class="url">
        <input id="search" value="" />
        <input type="button" value="搜索" onclick="document.getElementById('extensionPaging').paging({'find' : document.getElementById('search').value});" />
    </span>
</div>
<?php
echo $this->extensionPaging();
?>
<script>
var managerObj = {
    //强制停止或运行切换
    'pauseOrRun' : function(thisObj, state){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        $.get(OF_URL + '/index.php', {'a' : 'pauseOrRun', 'c' : 'of_base_extension_tool', 'eKey' : eKey, 'state' : state}, function(response){
            if(response === '1')
            {
                document.getElementById("extensionPaging").paging('+0');
                window.L.open('tip')('操作成功');
            } else {
                window.L.open('tip')('操作失败');
            }
        });
    },

    //安装扩展
    'setup' : function(thisObj){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        window.L.open('oDialogDiv')('进度', 'iframe:about:blank', '50%', '50%', [0, {'layoutFun' : function(callBack, windowObj, callBackObj){
            $('#oDialogDiv_iframe_' + callBackObj.handle).css("overflow", "auto").attr('src', OF_URL + '/index.php?a=setup&c=of_base_extension_tool&eKey=' + eKey);
        }}]);
    },

    //卸载扩展
    'remove' : function(thisObj){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        window.L.open('oDialogDiv')(
            '警告', 
            'text:' + '卸载后扩展的相关数据会丢失,您可以先选择扩展备份' 
                    + '<br><font color="red">' 
                    + '是否继续卸载?' 
                    + '</font>',
            'auto', 'auto', [2, function(callback){
                if( callback )
                {
                    window.L.open('oDialogDiv')('进度', 'iframe:about:blank', '50%', '50%', [0, {'layoutFun' : function(callBack, windowObj, callBackObj){
                        $('#oDialogDiv_iframe_' + callBackObj.handle).css("overflow", "auto").attr('src', OF_URL + '/index.php?a=remove&c=of_base_extension_tool&eKey=' + eKey);
                    }}]);
                }
            }]
        );
    },

    //打开选项
    'options' : function(thisObj){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        window.L.open('oDialogDiv')('选项', 'iframe:' + OF_URL + '/index.php?a=options&c=of_base_extension_tool&eKey=' + eKey, {'maxWidth' : '90%'}, {'maxHeight' : '90%'});
    },

    //扩展备份与恢复
    'dataManager' : function(thisObj, dir){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        window.L.open('oDialogDiv')('进度', 'iframe:about:blank', '50%', '50%', [0, {'layoutFun' : function(callBack, windowObj, callBackObj){
            $('#oDialogDiv_iframe_' + callBackObj.handle).css("overflow", "auto").attr('src', OF_URL + '/index.php?a=dataManager&c=of_base_extension_tool&eKey=' + eKey + '&dir=' + (dir || ''));
        }}]);
    },

    //扩展恢复列表
    'restore' : function(thisObj){
        var eKey = $(thisObj).parents('tr').find('td:eq(0) a').attr('key');    //扩展关键值
        window.L.open('oDialogDiv')('备份列表', 'url:get?' + OF_URL + '/index.php?a=dataList&c=of_base_extension_tool&eKey=' + eKey, '60%', {'maxHeight' : '60%'}, [2, function(callBack, windowObj, callBackObj){
            var temp = callBackObj.oDialogDivObj.find('.backupList input:checked');
            if( callBack && temp.length )
            {
                managerObj.dataManager(thisObj, temp.val());
            }
        }]);
    },

    //扩展加密
    'encrypt' : function(thisObj){
        var eKey = thisObj ? $(thisObj).parents('tr').find('td:eq(0) a').attr('key') : '';    //扩展关键值
        window.L.open('tip')('加密中,请稍后...', false);
        $.get(OF_URL + '/index.php?c=of_base_extension_tool', {'a' : 'encrypt', 'eKey' : eKey}, function(r){
            window.L.open('tip')(r === '1' ? '加密成功' : '加密失败');
        });
    },

    //切换语言包
    'switchLanguage' : function (thisObj) {
        var temp, val = $.trim((thisObj = $(thisObj)).val());

        if( val === '1' ) {
            thisObj.parent().children('.selectLanguagePage').hide()
                .end().children('.inputLanguagePage').show().focus();
        //以字母开头的常规字符串
        } else if( !val || /^[a-z]\w*$/i.test(val) ) {
            temp = thisObj.parent().children('.inputLanguagePage').hide().val('')
                .end().children('.selectLanguagePage').show();

            //添加新语言包
            val && !temp.find('[value="' +val+ '"]').length &&
                temp.append('<option value="' +val+ '">' +val+ '</option>');
            temp.val(val);
        } else if( thisObj.prop('tagName') === 'INPUT' ) {
            window.L.open('tip')('命名无效 : 以字母开头的常规字符串');
        }
    },

    //扩展导入导出
    'exports' : function (thisObj) {
        //扩展关键值
        var eKey = thisObj ? $(thisObj).parents('tr').find('td:eq(0) a').attr('key') : '';

        arguments.callee.submitObj && arguments.callee.submitObj.remove();
        temp = '<iframe name="languagePageExport" style="display: none;"></iframe>' +
        '<form action="#" method="get" target="languagePageExport" style="display: none;">' +
            '<input name="a" value="language">' +
            '<input name="c" value="of_base_extension_tool">' +
            '<input name="eKey" value="' +eKey+ '">' +
            '<input name="name" value="' +$('.selectLanguagePage', $(thisObj).parent()).val()+ '">' +
        '</form>';
        arguments.callee.submitObj = $(temp).appendTo(document.body);

        arguments.callee.submitObj.eq(1).submit();
    }
};

L.data('paging.after[]', function () {
    $('.selectLanguagePage', this).each(function () {
        var thisObj = $(this), parentObj = thisObj.parents('tr');
        //扩展关键值
        var eKey = parentObj.find('td:eq(0) a').attr('key');

        $.ajax({
            "type"     : "get",
            "url"      : "?c=of_base_extension_tool",
            "async"    : false,
            "data"     : {'a' : 'getLanguageDir', 'eKey' : eKey},
            "dataType" : "json",
            "success"  : function (json) {
                var temp = '<option value="0">语言包</option><option value="1">新建</option>';
                $.each(json, function () {
                    temp += '<option value="' +this+ '">' +this+ '</option>';
                });
                thisObj.html(temp);
            }
        });

        window.L.open('oUpload')({
            'node' : parentObj.find('.oUpload').get(0),
            'exts' : 'csv',
            'call' : function () {
                window.L.open('tip')('操作中', false);
                $.get(OF_URL + '/index.php?c=of_base_extension_tool', {
                    'a'    : 'language',
                    'eKey' : eKey,
                    'name' : thisObj.val(),
                    'file' : arguments[3]
                },function(response){
                    window.L.open('tip')(response === '1' ? '操作成功' : '操作失败');
                });
            }
        });
    });
});
</script>
<?php
        of_view::head(false);
    }
}

if (OF_DEBUG === false) {
    exit('Access denied: production mode.');
} else {
    return true;
}