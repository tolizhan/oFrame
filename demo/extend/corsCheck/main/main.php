<?php
/**
 * 描述 : 校验并响应CORS预检请求
 * 作者 : Edgar.lee
 */
function check(&$params) {
    if (
        isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) &&
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
    ) {
        //缓存CORS预检请求
        header('Access-Control-Max-Age: 86400');
        //允许公有网络访问私有
        header('Access-Control-Allow-Private-Network: true');
        //允许访问的方法
        header('Access-Control-Allow-Methods: OPTIONS,GET,HEAD,POST,PUT,DELETE,TRACE');
        //允许修改访问头
        header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);

        exit;
    }
}