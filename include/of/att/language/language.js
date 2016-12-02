(function(){
    if( window.L.getText ) return ;

    //当前语言包, 语言包参数, 回溯
    var pack = {}, args, getBacktrace;
    var temp = document.getElementsByTagName('script');

    temp = temp[temp.length - 1];
    args = {'debug' : !!temp.getAttribute('debug'), 'isInit' : !!temp.getAttribute('init'), 'path' : ROOT_URL + temp.getAttribute('path')};

    args.isInit && window.L.ajax({
        'url'     : args.path + '/js.txt',
        'async'   : false,
        'success' : function (data) {
            //语言包初始化
            if( window.L.type( data = window.L.json(data) ) === 'object' ) {
                pack = data;
            }
        }
    });

    if( args.debug ) {
        getBacktrace = function() {
            //连接:行号
            var lines = '';
            try {
                var Exception;
                //无意义赋值,仅给压缩软件使用
                lines = Exception.dont.exist;
            } catch(e) {
                if (e.stack) {
                    lines = e.stack.split("\n");

                    //火狐
                    if( window.L.browser.mozilla ) {
                        lines = lines[2];
                    //L.browser.webkit || L.browser.msie
                    } else {
                        //兼容 Safari
                        lines = lines[3] || lines[2];
                        lines = lines.substr(0, lines.lastIndexOf(':'));
                    }

                    if( lines = lines.substr(lines.indexOf(':')+3).match(/^[^\/\\?#]+([^\?#]*)\/([^\?#]*).*:(\d+)$/i) ) {
                        //解析路径
                        lines[1] = lines[1].substr(ROOT_URL.length);
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
        params || (params = {});
        params.key || (params.key = '');

        if( string && (string = string.replace(/(^\s*)|(\s*$)/g, "")) ) {
            pack[string] || (pack[string] = {});

            //调试模式 && 语法定位
            args.debug && (temp = getBacktrace()) && window.L.ajax({
                'url'     : OF_URL + '/index.php?c=of_base_language_packs&a=update&t=' + (new Date).getTime(),
                'data'    : window.L.param({
                    'string' : string, 
                    'params' : {
                        'class'  : args['class'] || '',
                        'action' : args.action || '',
                        'key'    : params.key,
                        'file'   : temp[0]
                    }
                })
            });
        }

        return pack[string][params.key] || pack[string][''] || string;
    }

    window.L.getText.init = function (params) {
        args['class'] = params['class'];
        args.action = params.action;
    }
})()