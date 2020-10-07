<?php
/**
 * 描述 : 代理请求网址
 * 注明 :
 *      支持缓存与压缩 :
 *          请求 :
 *          Accept-Encoding: gzip, deflate
 *          If-None-Match: "28e7-55c220175eeaf"
 *          If-Modified-Since: Mon, 12 Apr 2010 02:33:24 GMT
 *          响应 :
 *          Content-Encoding: gzip
 *          ETag: "28e7-55c220175eeaf"
 *          Last-Modified: Mon, 12 Apr 2010 02:33:24 GMT
 *          Cache-Control: private, max-age=0, no-cache
 * 作者 : Edgar.lee
 */
if (isset($_GET['url']) && preg_match('@^http(?:s?)://@i', $_GET['url'])) {
    //匹配响应头
    $preg = '@^(?:HTTP/|Content-Encoding|Last-Modified|ETag|Cache-Control)\b@i';
    //准备头信息
    $head = array();

    //请求头信息: 压缩格式
    ($index = &$_SERVER['HTTP_ACCEPT_ENCODING']) && $head[] = 'Accept-Encoding: ' . $index;
    //请求头信息: 缓存哈希
    ($index = &$_SERVER['HTTP_IF_NONE_MATCH']) && $head[] = 'If-None-Match: ' . $index;
    //请求头信息: 修改时间
    ($index = &$_SERVER['HTTP_IF_MODIFIED_SINCE']) && $head[] = 'If-Modified-Since: ' . $index;

    //发起请求
    $data = @file_get_contents($_GET['url'], false, stream_context_create(array(
        'http' => array(
            'timeout' => 60,
            'header'  => join("\r\n", $head)
        )
    )));

    //请求成功
    if (isset($http_response_header)) {
        //响应头信息
        foreach ($http_response_header as &$v) {
            preg_match($preg, $v) && header($v);
        }

        //响应主文本
        echo $data;
    }
}