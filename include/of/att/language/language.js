(function(){
    if (window.L.getText) return ;

    //当前语言包, 语言包参数, 回溯
    var pack = {}, args, getBacktrace;
    var temp = document.getElementsByTagName('script');

    temp = temp[temp.length - 1];
    args = {
        'debug'  : !!temp.getAttribute('debug'),
        'isInit' : !!temp.getAttribute('init'),
        'path'   : ROOT_URL + temp.getAttribute('path'),
        'match'  : eval(temp.getAttribute('match'))
    };

    args.isInit && window.L.ajax({
        'url'     : args.path + '/js.txt',
        'async'   : false,
        'success' : function (data) {
            //语言包初始化
            if (window.L.type(data = window.L.json(data)) === 'object') {
                pack = data;
            }
        }
    });

    if (args.debug) {
        getBacktrace = function() {
            //连接:行号
            var lines = '';
            try {
                lines.lines.lines;
            } catch (e) {
                if (e.stack) {
                    lines = e.stack.split("\n");

                    //火狐
                    if (window.L.browser.mozilla) {
                        lines = lines[2];
                    //L.browser.webkit || L.browser.msie
                    } else {
                        //兼容 Safari
                        lines = lines[3] || lines[2];
                        lines = lines.slice(0, lines.lastIndexOf(':'));
                    }

                    if (lines = lines
                        .slice(lines.indexOf(':')+3)
                        .match(/^[^\/\\?#]+([^?#]*)\/([^?#]*).*:(\d+)$/i)
                    ) {
                        //解析路径
                        lines[1] = lines[1].slice(ROOT_URL.length);
                        //解析文件
                        lines[2] === '' ? lines[2] = 'index.php' : null;
                        lines = lines[1] + '/' + lines[2] + ':' + lines[3];
                    } else {
                        lines = '';
                    }
                }
            }

            return lines && lines.split(':');
        }
    }

    window.L.getText = function(string, params) {
        var tran = '';
        params || (params = {});
        params.key || (params.key = '');

        if (string && (string = string.replace(/(^\s*)|(\s*$)/g, ""))) {
            if (params.mode) {
                if (temp = args.match.exec(string)) {
                    for (var i = 1; i < temp.length; i++) {
                        if (temp[i]) {
                            tran = temp[i];
                            break ;
                        }
                    }
                }
            } else {
                tran = string;
            }

            //提取到翻译文本
            if (tran) {
                pack[tran] || (pack[tran] = {});

                //调试模式 && 语法定位
                args.debug && (temp = getBacktrace()) && window.L.ajax({
                    'url'     : OF_URL + '/index.php?c=of_base_language_packs&a=update&t=' + (new Date).getTime(),
                    'data'    : window.L.param({
                        'string' : tran,
                        'params' : {
                            'class'  : args['class'] || '',
                            'action' : args.action || '',
                            'key'    : params.key,
                            'file'   : temp[0]
                        }
                    })
                });

                //读取并替换翻译文本
                (temp = pack[tran][params.key] || pack[tran]['']) && (string = string.replace(tran, temp));
            }
        }

        return string;
    }

    window.L.getText.init = function (params) {
        args['class'] = params['class'];
        args.action = params.action;
    }
})()