<?php
class of_base_error_jsLog extends of_base_error_writeLog {

    /**
     * 描述 : 写入错误日志
     * 注明 :
     *      JS 错误类型 : {
     *          "EvalError"      : 错误发生在eval()中
     *          "SyntaxError"    : 语法错误,错误发生在eval()中,因为其它点发生SyntaxError会无法通过解释器
     *          "RangeError"     : 数值超出范围
     *          "ReferenceError" : 引用不可用
     *          "TypeError"      : 变量类型不是预期的
     *          "URIError"       : 错误发生在encodeURI()或decodeURI()
     *      }
     * 作者 : Edgar.lee
     */
    public static function writeJsErr() {
        ignore_user_abort(true);
        ini_set('max_execution_time', 0);

        if (isset($_POST['message']) && isset($_POST['file']) && isset($_POST['line'])) {
            if ($logPath = of::config('_of.error.jsLog', OF_DATA. '/error/jsLog')) {
                $logPath = ROOT_DIR . $logPath . date('/Y/m', $_SERVER['REQUEST_TIME']);
                preg_match('@\w+Error@', $_POST['message'], $match);
                $data = array(
                    'errorType'   => 'jsError',
                    'environment' => &$_POST,
                    'time'        => &$_SERVER['REQUEST_TIME']
                );
                $_POST['envVar'] = array(
                    '_COOKIE'  => &$_COOKIE,
                    '_SESSION' => &$_SESSION,
                    '_SERVER'  => &$_SERVER,
                );
                //js七类错误
                $_POST['code'] = $_POST['type'] = $match ? $match[0] : 'Error';

                self::writeLog($data, 'js', '');
            }
        }
    }

    /**
     * 描述 : 打印js抓错脚本
     * 作者 : Edgar.lee
     */
    public static function jsErrScript() {
        header('Content-type: application/x-javascript');
?>
window.L.extension.jsErrorLog || !function () {
<?php if (OF_DEBUG) { ?>
    var block = document.createElement('div'), count = 0, nodes, refresh;

    block.innerHTML = '<div class="jsDebug_1320681434"><div></div><span>&lt;</span><style type="text/css">.jsDebug_1320681434{display:none;position:fixed;right:0;top:40px;_position:absolute;_left:expression(eval(document.documentElement.scrollLeft+document.documentElement.clientWidth-this.offsetWidth)-(parseInt(this.currentStyle.marginLeft,10)||0)-(parseInt(this.currentStyle.marginRight,10)||0)-1);_top:expression(eval(document.documentElement.scrollTop) + 40);background:none repeat scroll 0 0 red;border:1px dotted #CCCCCC;font-size:9pt;margin-bottom:10px;padding:6px;cursor:pointer;text-align:center;width:12px;size:9pt; z-index:2147483647}.jsDebug_1320681434 span{display:block;}.jsDebug_1320681434 div{width:500px;overflow:auto;margin-top:-7px;display:none;padding:6px 6px 0px 6px;background-color:#EEEEEE;position:absolute;right:30px;border:1px solid #000}.jsDebug_1320681434 div span{margin-bottom:6px;text-align:left;overflow:hidden;border:1px dotted #00F;}.jsDebug_1320681434 div span span{margin:0px -3px;border:0px dotted #00F;}.jsDebug_1320681434 div span span input{background-color:transparent;border:0px;margin-left:8px;}</style></div>';
    //错误块
    block = block.firstChild;
    //[错误列表, 错误标签]
    nodes = block.childNodes;
    //刷新标签
    refresh = function () {
        if( count > 0 ) {
            block.style.display = 'block';
            nodes[1].innerHTML = '>> 错误列表' + count;
            nodes[0].style.display || nodes[1].click();
            if(count >= 10 && nodes[0].style.display === 'block' && nodes[0].style.height === '') {
                nodes[0].style.height = (nodes[0].offsetHeight - 8) / count * 10 + 'px';
            }
        }
    }

    L.event(function () {
        nodes[1].onclick = function () {
            if(nodes[0].style.display !== 'block') {
                (nodes[0].style.display = 'block') && refresh();
                this.innerHTML = this.innerHTML.replace('&gt;&gt;', '&lt;&lt;');
            } else {
                nodes[0].style.display = 'none';
                this.innerHTML = this.innerHTML.replace('&lt;&lt;', '&gt;&gt;');
            }
        }
        document.body.appendChild(block);
        refresh();
    });
<?php } ?>

    window.onerror = function (message ,file ,line) {
        var temp;
<?php if (OF_DEBUG) { ?>
        temp = document.createElement("span");
        temp.innerHTML = '<span><input type="text" value="' +line+ '" style="width:30px;" readonly="readonly" />' + 
            '<input type="text" value="' +window.L.entity(file, true)+ '" style="width:435px;" readonly="readonly" /></span><span>' + 
            '<input type="text" value="' +window.L.entity(message, true)+ '" style="width:475px;" readonly="readonly" /></span>';
        nodes[0].appendChild(temp);
        count += 1;

        nodes[1].onclick && refresh();
<?php } ?>
        if (arguments[4]) {
            //[需查找的信息, 被查找的信息]
            temp = [
                message.substr(message.indexOf(':') + 1),
                arguments[4].stack
            ];
            //js回溯中包含错误信息 ? 使用回溯信息 : 使用错误信息 + 回溯信息
            message = temp[1].indexOf(temp[0]) > -1 ? temp[1] : message + '\n' + temp[1];
        }
        window.L.ajax({
            "url"   : OF_URL + '/index.php?a=writeJsErr&c=of_base_error_jsLog',
            "async" : true,
            "data"  : window.L.param({'message' : message, 'file' : file, 'line' : line})
        });
    }
}(window.L.extension.jsErrorLog = true);
<?php
    }
}
return true;