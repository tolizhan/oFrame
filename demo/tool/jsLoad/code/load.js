/**
 * 描述 : 使 js 可以实现 include 功能
 *      示例 <script src="tool.js" include="head.html"></script>
 *      src 定位到该脚本, include 是相对该脚本的.html文件
 * 注明 :
 *      本脚本仅做UI开发使用
 *      为了方便后端语言整合, 不要使用在js中
 *      chrome 浏览器本地调试时需带上 --allow-file-access-from-files 启动参数
 * 作者 : Edgar.lee
 */
+function () {
    var path, root, ajax, temp, nowTag;
    var temp = /\.html$/i;
    var list = document.getElementsByTagName('script');
    var isIe = navigator.userAgent.match(/MSIE ([\d.]+)/);

    //IE <= 9 下使用的方式
    isIe = isIe && isIe[1] < 10 && function (doc) {
        var frag;

        //已独立 script 为元素切割文档块
        list = doc.match(/<script(.|\s)+?<\/script\s*>|(.|\s)*?(?=<script|$)/ig);

        while (doc = list.shift()) {
            document.write(doc);
            //规划 IE 脚本执行顺序
            if (/^<script/i.test(doc) && (doc = list.join(''))) {
                //创建脚本节点
                frag = document.createElement("script");
                frag.setAttribute('src', root);
                frag.setAttribute('include', '');
                frag.include = 'text';
                frag.text = escape(list.join(''));
                nowTag.parentNode.insertBefore(frag, nowTag);
                break ;
            }
        }
    }

    //寻找当前script标签
    for (var i = list.length - 1; i > -1; --i) {
        if (
            //包含 include 属性
            (path = list[i].getAttribute('include')) !== null &&
            //html扩展名 或 代码方式
            (temp.test(path) || list[i].include === 'text')
        ) {
            nowTag = list[i];
            break ;
        }
    }

    //计算相对目录
    if (nowTag) {
        root = nowTag.getAttribute('src');
    } else {
        alert('Invalid parameter: include');
        throw new Error('Invalid parameter: include');
    }

    //文档路径
    if (path) {
        path = root.substr(0, root.lastIndexOf('/') + 1) + path;

        ajax = new (window.ActiveXObject || window.XMLHttpRequest)('Microsoft.XMLHTTP');
        ajax.open('get', path, false);
        ajax.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        ajax.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        ajax.onreadystatechange = function() {
            if (ajax.readyState === 4) {
                path = path.substr(0, path.lastIndexOf('/') + 1);

                //计算文档路径
                temp = /\b(src\s*=|href\s*=|url\s*\()\s*(\'|\"|)([^ "'>]*)\2(\)?)/ig;
                temp = ajax.responseText.replace(temp, function () {
                    return arguments[1] + arguments[2] + path + arguments[3] + arguments[2] + arguments[4];
                });

                isIe ? isIe(temp) : document.write(temp);
            }
        };

        try { ajax.send(); } catch(e) {}
    } else {
        isIe && isIe(unescape(nowTag.text));
    }

    nowTag.parentNode.removeChild(nowTag);
}();