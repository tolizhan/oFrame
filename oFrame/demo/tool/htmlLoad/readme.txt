/**
 * 描述 : 使 js 可以实现 include 功能
 *      示例 <script src="tool.js" include="head.html"></script>
 *      src 定位到该脚本, include 是相对该脚本的.html文件
 * 注明 :
 *      本脚本仅做UI开发使用
 *      为了方便后端语言整合, 不要使用在js中
 *      chrome 浏览器本地调试时需带上 --allow-file-access-from-files 启动参数
 *      firfox 浏览器访问"about:config" 将 security.fileuri.strict_origin_policy 改为 false
 * 作者 : Edgar.lee
 */
<script __del>
/**
 * 描述 : 本地可调用 of 框架中的js扩展
 * 注明 : 需要放在 htmlLoad 的加载文件中
 * 作者 : Edgar.lee
 */
+function () {
    //当前文件, htmlLoad 自动填充不要修改
    var src = "";

    //配置系统根目录
    window.ROOT_URL = src + "../..";
    //配置框架根目录
    window.OF_URL   = ROOT_URL + "/include/of";
    //配置视图根目录
    window.VIEW_URL = ROOT_URL + "/view";

    document.write('<script src' + '="' + OF_URL + '/att/link/jquery.js"></' + 'script>');
    document.write('<script src' + '="' + OF_URL + '/att/link/L.js"></' + 'script>');
}();
</script>