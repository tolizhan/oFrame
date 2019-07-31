<?php
class of_base_error_tool {
    public function __construct() {
        of_base_error_toolBaseClass::init();
        $temp = empty($_POST) ? 'printHtml' : 'response';
        $this->$temp();
    }

    /**
     * 描述 : 返回日志列表
     * 作者 : Edgar.lee
     */
    public function getLogTablePaging($params = array()) {
        $data = array();
        $title = array('时间', '{`_time`}');
        $attr = &$params['attr'];
        unset($params['attr']);

        if (isset($params['path'])) {
            //当前页
            $page = isset($_POST['page']) ? $_POST['page'] : 1;
            //每页数量
            $size = isset($_POST['size']) ? $_POST['size'] : 10;

            //加载详细列表
            if ($params['mode'] === 'detailMain') {
                $totalItems = empty($_POST['items']) ? 
                    of_base_error_toolBaseClass::fileS($params['path']) : $_POST['items'];
                $pageList = of_base_error_toolBaseClass::fileS(
                    $params['path'], $page, $size
                );
            //加载分组概要或明细
            } else {
                //存在分组明细键 ? 读取分组明细 : 读取分组概要
                $temp = isset($params['md5Key']) ? $params['md5Key'] : '';
                //读取对应日志数据
                $temp = of_base_error_toolBaseClass::fileG(
                    $params['path'], $page, $size, $temp
                );
                $totalItems = $temp['count'];
                $pageList = $temp['pList'];
            }

            foreach ($pageList as $k => &$v) {
                $data[$k]['_time'] = date('/Y/m/d H:i:s', $v['time']);
                $data[$k]['_code'] = isset($v['environment']['type']) ? $v['environment']['type'] : $v['errorType'];
                $data[$k]['_file'] = $v['environment']['file'];
                $data[$k]['_line'] = $v['environment']['line'];
                $data[$k]['_message'] = '<pre>' .
                    htmlentities($v['environment']['message'], ENT_QUOTES, 'UTF-8') .
                '</pre>';
                //格式化详细信息
                $data[$k]['_detaile'] = str_replace(
                    //兼容IE6 7 8
                    array("\n", ' '), array('<br>', '&nbsp;'),
                    //防止非UTF8不显示
                    iconv(
                        'UTF-8', 'UTF-8//IGNORE',
                        htmlspecialchars(print_r($v['environment'], true))
                    )
                );

                //分组概要数据
                if (isset($v['groupMd5Key'])) {
                    $data[$k]['_count'] = $v['groupCount'];
                    $data[$k]['_md5Key'] = $v['groupMd5Key'];
                }
            }
        } else {
            //分组概要时, 时间改数量
            $params['mode'] === 'groupMain' && $title = array('次数', '{`_count`}');
            $totalItems = -1;
        }

        $config = array(
            '详细' => array(
                '_attr' => array(
                    'attr' => 'class="center"',
                    'body' => '<input name="radio" type="radio" md5Key="{`_md5Key`}" /><div style="display:none;">{`_detaile`}</div>',
                    'html' =>  '<div class="of-paging_action">' .
                        '<a name="pagingFirst" class="of-paging_first" href="#">&nbsp;</a>' .
                        '<a name="pagingPrev" class="of-paging_prev" href="#">&nbsp;</a>' .
                        '<a name="pagingNext" class="of-paging_next" href="#">&nbsp;</a>' .
                        '<a name="pagingLast" class="of-paging_last" href="#">&nbsp;</a>' .
                        '<span name="pagingCount" class="of-paging_count">0</span>' .
                        '<span name="pagingPage" class="of-paging_page">1/1</span>' .
                        '<input name="pagingJump" class="of-paging_jump" type="text">' .
                        '<input name="pagingSize" class="of-paging_size" type="text">' .
                        '<span name="modeDetail" class="mode_button"
                            onclick="toolObj.tabMode();">detail</span>' .
                        '<span name="modeGroup" class="mode_button"
                            style="color:#000000; font-weight: bold;"
                            onclick="toolObj.tabMode();">group</span>' .
                    '</div>'
                )
            ),
            $title[0] => $title[1],
            '文件' => '{`_file`}',
            '行数' => '{`_line`}',
            '类型' => '{`_code`}',
            '信息' => '{`_message`}',
            '_attr' => array(
                'attr'   => &$attr,
                'data'   => $data,
                'params' => $params,
                'items'  => $totalItems,
                'action' => '',
                'method' => __METHOD__,
            )
        );

        return of_base_com_com::paging($config);
    }

    /**
     * 描述 : 响应请求
     * 作者 : Edgar.lee
     */
    private function response() {
        if (isset($_POST['type'])) {
            switch ($_POST['type']) {
                case 'getDir':                //获取目录(带状态)
                    $dirList = of_base_error_toolBaseClass::getDir($_POST['path'], $_POST['logType']);

                    //两层目录时, 仅显示扩展名为php的文件日志
                    if (substr_count($_POST['path'], '/') === 2) {
                        $temp = array();
                        foreach ($dirList as $k => &$v) {
                            if (pathinfo($k, PATHINFO_EXTENSION) === 'php') {
                                $temp[substr($k, 0, -8)] = false;
                            }
                        }
                        $dirList = $temp;
                    }

                    echo json_encode(array(
                        'state' => is_array($dirList),
                        'data'   => $dirList
                    ));
                    break;
            }
        }
    }

    /**
     * 描述 : 打印html
     * 作者 : Edgar.lee
     */
    private function printHtml() {
        of_view::head(array());
?>

<style>
body{ padding: 0px; margin: 10px; background-color:#FFFFFF; font-family:宋体;}
label{ cursor:pointer;}
a:link{ text-decoration:none;}
a:hover{ text-decoration:underline; cursor:pointer;}
.yellowBg{ background-color:#FFFFCC;}
.clear{ clear:both;}
.center{ text-align:center;}

/*导航区*/
.nav span{ float: right;}

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

/*浮动层*/
.floatPre{ left: 40px; margin:0px; padding:5px; display:none; position:fixed; _position:absolute; height:90%; width:95%; background-color:#FFF; z-index:1; overflow:auto; border:1px dashed #000; /*filter:alpha(opacity=80); opacity:0.8;*/}

/*分页样式*/
.of-paging_action{ 
    position: fixed; background-color: white; bottom: 10px; right: 5px; 
    _position: absolute; _width: 500px; _text-align: right;
    _left: expression(
        document.documentElement.scrollLeft +
        document.documentElement.clientWidth -
        this.offsetWidth - 20
    );
    _top: expression(
        document.documentElement.scrollTop +
        document.documentElement.clientHeight -
        this.parentNode.parentNode.parentNode.parentNode.parentNode.offsetTop -
        this.offsetHeight - 10
    );
}
.of-paging_foot{ display: none;}
.of-paging_action .mode_button{
    display: inline-block;
    height: 23px;
    width: auto;
    margin: 0 5px;
    color: #888;
    cursor: pointer;
}

/*尾部*/
.foot{ height: 40px;}
</style>
</head>

<body>
<!-- 功能栏 -->
<div class="nav">
    <span>Press ESC to switching mode</span>
    <label><input type="radio" value="php" onClick="toolObj.tabSwitch(this)" checked />php</label>
    <label><input type="radio" value="sql" onClick="toolObj.tabSwitch(this)" />sql</label>
    <label><input type="radio" value="js" onClick="toolObj.tabSwitch(this)" />js</label>
</div>
<!-- php -->
<div id="php">
    <!-- 浮动层 -->
    <pre class="floatPre" title="Press ESC to exit"></pre>
    <!-- 工具条 -->
    <div class="tool">
        <span class="url">
            &nbsp;年份
            <select onChange="toolObj.getDir(this.value)">
                <option value="">请选择</option>
                <?php
                if (is_array($years = of_base_error_toolBaseClass::getDir('', 'php'))) {
                    foreach ($years as $k => &$v) {
                        $temp = substr($k, 1);
                        echo "<option value='{$k}'>{$temp}</option>";
                    }
                }
                ?>
            </select>
            <b onClick="toolObj.getDir('..')">..</b>
            <span class="urlBar"></span>
        </span>
        <div class="clear"></div>
    </div>
    <!-- 目录结构 -->
    <div class="disk">
        <!--<div class="dir folder">
            <font class="folder">D</font>
            <font class="file">F</font>
            <span>地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址址地址址地址址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地</span>
        </div>
        <div class="dir file">
            <font class="folder">D</font>
            <font class="file">F</font>
        </div>-->
        <div class="clear"></div>
    </div>
    <!-- 日志区 -->
    <?php
    echo $this->getLogTablePaging(array(
            'attr' => 'mode=detailMain style="display:none;"',
            'mode' => 'detailMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupMain title="Double click to open items"',
            'mode' => 'groupMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupList title="Press ESC to exit" style="display:none;"',
            'mode' => 'groupList'
        ));
    ?>
</div>
<!-- sql -->
<div id="sql" style="display:none;">
    <!-- 浮动层 -->
    <pre class="floatPre" title="Press ESC to exit"></pre>
    <!-- 工具条 -->
    <div class="tool">
        <span class="url">
            &nbsp;年份
            <select onChange="toolObj.getDir(this.value)">
                <option value="">请选择</option>
                <?php
                if (is_array($years = of_base_error_toolBaseClass::getDir('', 'sql'))) {
                    foreach ($years as $k => &$v) {
                        $temp = substr($k, 1);
                        echo "<option value='{$k}'>{$temp}</option>";
                    }
                }
                ?>
            </select>
            <b onClick="toolObj.getDir('..')">..</b>
            <span class="urlBar"></span>
        </span>
        <div class="clear"></div>
    </div>
    <!-- 目录结构 -->
    <div class="disk">
        <!--<div class="dir folder">
            <font class="folder">D</font>
            <font class="file">F</font>
            <span>地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址址地址址地址址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地</span>
        </div>
        <div class="dir file">
            <font class="folder">D</font>
            <font class="file">F</font>
        </div>-->
        <div class="clear"></div>
    </div>
    <!-- 日志区 -->
    <?php
    echo $this->getLogTablePaging(array(
            'attr' => 'mode=detailMain style="display:none;"',
            'mode' => 'detailMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupMain title="Double click to open items"',
            'mode' => 'groupMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupList title="Press ESC to exit" style="display:none;"',
            'mode' => 'groupList'
        ));
    ?>
</div>
<!-- js -->
<div id="js" style="display:none;">
    <!-- 浮动层 -->
    <pre class="floatPre" title="Press ESC to exit"></pre>
    <!-- 工具条 -->
    <div class="tool">
        <span class="url">
            &nbsp;年份
            <select onChange="toolObj.getDir(this.value)">
                <option value="">请选择</option>
                <?php
                if (is_array($years = of_base_error_toolBaseClass::getDir('', 'js'))) {
                    foreach ($years as $k => &$v) {
                        $temp = substr($k, 1);
                        echo "<option value='{$k}'>{$temp}</option>";
                    }
                }
                ?>
            </select>
            <b onClick="toolObj.getDir('..')">..</b>
            <span class="urlBar"></span>
        </span>
        <div class="clear"></div>
    </div>
    <!-- 目录结构 -->
    <div class="disk">
        <!--<div class="dir folder">
            <font class="folder">D</font>
            <font class="file">F</font>
            <span>地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地址址地址址地址址地址地址地址地址地址地址地址地址地址地址地址地址地址地址地</span>
        </div>
        <div class="dir file">
            <font class="folder">D</font>
            <font class="file">F</font>
        </div>-->
        <div class="clear"></div>
    </div>
    <!-- 日志区 -->
    <?php
    echo $this->getLogTablePaging(array(
            'attr' => 'mode=detailMain style="display:none;"',
            'mode' => 'detailMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupMain title="Double click to open items"',
            'mode' => 'groupMain'
        )),
        $this->getLogTablePaging(array(
            'attr' => 'mode=groupList title="Press ESC to exit" style="display:none;"',
            'mode' => 'groupList'
        ));
    ?>
</div>
<div class="foot"></div>
<script>
var toolObj = {
    //切换功能
    'tabSwitch' : function(thisObj){
        $(thisObj).parent().siblings().children('input').prop('checked', false)
            .end().end().end().prop('checked', true);
        $('#' + thisObj.value).siblings('div[id]').hide()
            .end().show();
    },

    //切换日志显示模式
    'tabMode' : function(){
        var logType = $('.nav input:checked').val();
        //当前操作界面
        var showPageObj = $('#' + logType);
        //当前操作模式(true=分组模式, false=明细列表)
        var nowMode = showPageObj.find('.mode_button[name=modeGroup]')
            .get(0).style.fontWeight === 'bold';

        //分组模式=>明细列表
        if (nowMode) {
            $('.mode_button[name=modeDetail]', showPageObj)
                .css({'fontWeight' : 'bold', 'color' : '#000'});
            $('.mode_button[name=modeGroup]', showPageObj)
                .css({'fontWeight' : '', 'color' : ''});

            //隐藏明细分页
            $('table[mode=detailMain]', showPageObj).show();
            //隐藏分组分页
            $('table[mode=groupMain]', showPageObj).hide();
        //明细列表=>分组模式
        } else {
            $('.mode_button[name=modeGroup]', showPageObj)
                .css({'fontWeight' : 'bold', 'color' : '#000'});
            $('.mode_button[name=modeDetail]', showPageObj)
                .css({'fontWeight' : '', 'color' : ''});

            //隐藏明细分页
            $('table[mode=detailMain]', showPageObj).hide();
            //隐藏分组分页
            $('table[mode=groupMain]', showPageObj).show();
        }

        //隐藏分组列表
        $('table[mode=groupList]', showPageObj).hide();
    },

    //获取目录结构
    'getDir' : function(path, thisObj){
        var logType = $('.nav input:checked').val();
        //当前操作界面
        var showPageObj = $('#' + logType);
        //地址栏
        var urlBarObj = showPageObj.find('.urlBar');
        //目录显示区
        var diskObj = showPageObj.find('.disk');
        //响应方法
        var responseFun = function(response) {
            //创建成功
            if (response.state === true) {
                //目录名
                var dirName = '';
                for (var i in response.data) {
                    dirName = i.substr(path.length + 1);
                    diskObj.children('.clear').before('<div title="' + dirName + '" class="dir' + (response.data[i] ? ' folder' : ' file') + '" onclick="toolObj.dirClick(this)">' +
                        '<font class="folder">D</font>' +
                        '<font class="file">F</font>' +
                        '<span>' + dirName + '</span>' +
                    '</div>');
                }
                window.L.open('tip')('加载完成');
            } else {
                window.L.open('tip')(response.data);
            }
        };

        //界面初始化
        diskObj.children('.dir').remove();                //清空目录

        //请求数据
        if ($.trim(path) === '') {                 //空目录
        
            urlBarObj.html('');
        } else if( path.substr(0, 1) === '/' ) {   //切换语言包
            urlBarObj.html(path);
            window.L.open('tip')('正在加载', false);
            $.post(OF_URL + '/index.php?c=of_base_error_tool', {'type' : 'getDir', 'path' : path, 'logType' : logType}, responseFun, 'json');
        } else if( path === '..' ) {              //上级目录
            path = urlBarObj.html();
            if( (temp = path.lastIndexOf('/')) > -1 )    //读取上级目录
            {
                window.L.open('tip')('正在加载', false);
                temp > 0 && (path = path.substr(0, temp));
                urlBarObj.html(path);
                $.post(OF_URL + '/index.php?c=of_base_error_tool', {'type' : 'getDir', 'path' : path, 'logType' : logType}, responseFun, 'json');
            }
        } else if( path === '.' ) {               //刷新目录
            if( (path = $.trim(urlBarObj.html())) !== '' )
            {
                window.L.open('tip')('正在加载', false);
                $.post(OF_URL + '/index.php?c=of_base_error_tool', {'type' : 'getDir', 'path' : path, 'logType' : logType}, responseFun, 'json');
            }
        } else {                                  //常规目录
            path = urlBarObj.html() + '/' + path;
            urlBarObj.html(path);
            window.L.open('tip')('正在加载', false);
            $.post(OF_URL + '/index.php?c=of_base_error_tool', {'type' : 'getDir', 'path' : path, 'logType' : logType}, responseFun, 'json');
        }
    },

    //点击目录
    'dirClick' : function(thisObj){
        var logType = $('.nav input:checked').val();
        var showPageObj = $('#' + logType);    //当前操作界面
        var urlBarObj = showPageObj.find('.urlBar');    //地址栏
        var dirName = (thisObj = $(thisObj)).find('span').html();

        if(thisObj.hasClass('folder'))    //文件夹
        {
            toolObj.getDir(dirName);
        } else {                          //文件
            thisObj.siblings('.dir').removeClass('yellowBg');
            thisObj.addClass('yellowBg');

            //加载明细分页
            $('table[mode=detailMain]', showPageObj).attr('page', '1').get(0)
                .paging({'path' : urlBarObj.html() + '/' + dirName});
            //加载分组分页
            $('table[mode=groupMain]', showPageObj).attr('page', '1').get(0)
                .paging({'path' : urlBarObj.html() + '/' + dirName});
            //隐藏分组列表
            toolObj.tabMode();
            toolObj.tabMode();
        }
    },

    //鼠标点击TR标签触发
    'clickTr' : function(){
        //当前浮动层
        var temp, floatObj = $('#' + $('.nav input:checked').val() + ' .floatPre');

        if (this.getElementsByTagName('td').length > 1) {
            temp = $('td:first input', this)
                .prop('checked', true)
                .siblings('div').html();

            //清除尚未执行的单击(双击会调用两次)
            clearTimeout(toolObj.clickTr.timeout);
            //延迟执行单击(给双击留反映时间)
            toolObj.clickTr.timeout = setTimeout(function () {
                floatObj.html(temp).show();
            }, 300);
        }
    },

    //双击鼠标点击TR标签触发
    'dbClickTr' : function(){
        //当前操作界面
        var showPageObj = $('#' + $('.nav input:checked').val());
        //日志文件路径
        var logPath = $(this).parents('table').hide().get(0).paging().path;
        //分组明细唯一键
        var md5key = $('td:first input', this).attr('md5key');
        //分组明细列表(jq对象)
        var listJobj = $('table[mode=groupList]', showPageObj).show();
        //分组明细列表(js对象)
        var listNode = listJobj.get(0);

        //清除尚未执行的单击
        clearTimeout(toolObj.clickTr.timeout);

        if (
            //md5Key 比对
            listNode.paging().md5Key !== md5key ||
            //日志路径比对
            listNode.paging().path !== logPath
        ) {
            //重置分页位置
            listJobj.attr('page', '1');
            //请求日志数据
            listNode.paging({'path' : logPath, 'md5Key' : md5key});
        }
    },

    //隐藏浮动层
    'hidePre' : function(){
        $('#' + $('.nav input:checked').val() + ' .floatPre').hide();
    }
};

document.onkeydown = function(event){
    if ((window.event || event).keyCode === 27) {
        //当前操作界面
        var showPageObj = $('#' + $('.nav input:checked').val());
        var waitCloseNode = [
            //代码浮动层
            $('.floatPre', showPageObj),
            //分组列表页
            $('table[mode=groupList]', showPageObj)
        ]

        //关闭代码浮动层
        if (waitCloseNode[0].css('display') !== 'none') {
            toolObj.hidePre();
        //明细与分组分页切换
        } else {
            //隐藏分组列表分页
            waitCloseNode[1].css('display') !== 'none' && toolObj.tabMode();
            toolObj.tabMode();
        }
    }
};

L.data('paging.after[]', function () {
    var trObj = $('tbody > tr', this)
        .click(toolObj.clickTr)
        .css('cursor', 'pointer');

    //分组概述列表加双击事件
    this.paging().mode === 'groupMain' && trObj.dblclick(toolObj.dbClickTr);
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