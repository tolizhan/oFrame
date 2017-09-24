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
        if (isset($params['path'])) {
            $totalItems = empty($_POST['items']) ? of_base_error_toolBaseClass::fileS($params['path']) : $_POST['items'];
            $temp = of_base_error_toolBaseClass::fileS($params['path'], isset($_POST['page']) ? $_POST['page'] : 1, isset($_POST['size']) ? $_POST['size'] : 10);

            foreach ($temp as $k => &$v) {
                $data[$k]['_time'] = date('/Y/m/d H:i:m', $v['time']);
                $data[$k]['_code'] = isset($v['environment']['type']) ? $v['environment']['type'] : $v['errorType'];
                $data[$k]['_file'] = $v['environment']['file'];
                $data[$k]['_line'] = $v['environment']['line'];
                $data[$k]['_message'] = '<pre>' . strtr(htmlentities($v['environment']['message'], ENT_QUOTES, 'UTF-8'), array("\0" => "\n", "\n" => '<br>', ' ' => '&nbsp;')) . '</pre>';
                //防止非UTF8不显示
                $data[$k]['_detaile'] = iconv('UTF-8', 'UTF-8//IGNORE',
                    strtr(
                        htmlspecialchars(print_r($v['environment'], true)), 
                        array("\0" => "\n", "\n" => '<br>', ' ' => '&nbsp;')
                    )
                );
            }
        } else {
            $totalItems = -1;
            $data = array();
        }

        $config = array(
            '详细' => array(
                '_attr' => array(
                    'attr' => 'class="center"',
                    'body' => '<input name="radio" type="radio" /><div style="display:none;">{`_detaile`}</div>',
                    'html' =>  '<div class="of-paging_action"><a name="pagingFirst" class="of-paging_first" href="#">&nbsp;</a><a name="pagingPrev" class="of-paging_prev" href="#">&nbsp;</a><a name="pagingNext" class="of-paging_next" href="#">&nbsp;</a><a name="pagingLast" class="of-paging_last" href="#">&nbsp;</a><span name="pagingCount" class="of-paging_count">0</span><span name="pagingPage" class="of-paging_page">1/1</span><input name="pagingJump" class="of-paging_jump" type="text"><input name="pagingSize" class="of-paging_size" type="text"></div>'
                )
            ),
            '时间' => '{`_time`}',
            '文件' => '{`_file`}',
            '行数' => '{`_line`}',
            '类型' => '{`_code`}',
            '信息' => '{`_message`}',
            '_attr' => array(
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
    _position: absolute; _width: 300px; _text-align: right;
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

/*尾部*/
.foot{ height: 40px;}
</style>
</head>

<body>
<!-- 功能栏 -->
<div class="nav">
    <label><input type="radio" value="php" onClick="toolObj.tabSwitch(this)" checked />php</label>
    <label><input type="radio" value="sql" onClick="toolObj.tabSwitch(this)" />sql</label>
    <label><input type="radio" value="js" onClick="toolObj.tabSwitch(this)" />js</label>
</div>
<!-- php -->
<div id="php">
    <!-- 浮动层 -->
    <pre class="floatPre" title="ESC 关闭详情"></pre>
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
    <?php echo $this->getLogTablePaging(); ?>
</div>
<!-- sql -->
<div id="sql" style="display:none;">
    <!-- 浮动层 -->
    <pre class="floatPre" title="ESC 关闭详情"></pre>
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
    <?php echo $this->getLogTablePaging(); ?>
</div>
<!-- js -->
<div id="js" style="display:none;">
    <!-- 浮动层 -->
    <pre class="floatPre" title="ESC 关闭详情"></pre>
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
    <?php echo $this->getLogTablePaging(); ?>
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

            $('table[method]', showPageObj).attr('page', '1').get(0)
                .paging({'path' : urlBarObj.html() + '/' + dirName});
        }
    },

    //鼠标点击TR标签触发
    'clickTr' : function(){
        var floatPreObj = $('#' + $('.nav input:checked').val() + ' .floatPre');    //当前浮动层
        if( this.getElementsByTagName('td').length > 1 )
        {
            floatPreObj.html($('td:first input', this).prop('checked', true).siblings('div').html()).show();
        }
    },

    //隐藏浮动层
    'hidePre' : function(){
        $('#' + $('.nav input:checked').val() + ' .floatPre').hide();
    }
};

document.onkeydown = function(event){
    (window.event || event).keyCode === 27 && toolObj.hidePre();
};

L.data('paging.after[]', function () {
    $('tbody > tr', this).click(toolObj.clickTr).each(function(){
        $(this).css('cursor', 'pointer');
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