<?php
/**
 * 描述 : 基于TCP的网络请求双向代理
 *      代理地址写在 HEDA["PROXY_URL"] 或 GET["_"], 其它参数正常
 *      非apahe服务器, 头信息中可以使用"_"代替关键头, 如"_Authorization"代替"Authorization"
 *      请求时头中会自动包含 Proxy-Url(代理地址) 和 Proxy-Id(唯一ID)
 *      代码中 $tryNum 为尝试送达次数, 每次间隔5s
 *      响应 "error"字符串 或 状态码 不小于 400 为失败
 * 作者 : Edgar.lee
 */

//加载框架
require dirname(dirname(dirname(dirname(__FILE__)))) . '/include/of/of.php';

//不存在中转URL
if (empty($_SERVER['HTTP_PROXY_URL']) && empty($_GET['_'])) {
    $match = '@( \* 描述 : .*?) \* 作者@ms';
    preg_match($match, file_get_contents(__FILE__), $match);
    echo "<pre>/**\n", $match[1], ' */</pre>';
//存在中转URL
} else {
    //尝试次数
    $tryNum = 3;

    //永不超时
    ini_set('max_execution_time', 0);
    //忽略客户端断开
    ignore_user_abort(true);
    //代理ID
    $proxyId = of_base_com_str::uniqid();
    //黑名头列表
    $black = array(
        'HTTP_HOST' => true, 
        'HTTP_CONNECTION' => true, 
        'HTTP_ACCEPT_ENCODING' => true,
        'HTTP_PROXY_URL' => true
    );

    //请求数据
    $data = array(
        //报文类型
        'type' => $_SERVER['REQUEST_METHOD'],
        //GET参数
        'get'  => $_GET,
        //发送报文
        'data' => file_get_contents('php://input')
    );

    //提取代理地址
    if (isset($_SERVER['HTTP_PROXY_URL'])) {
        $url = $_SERVER['HTTP_PROXY_URL'];
    } else {
        $url = $_GET['_'];
        unset($data['get']['_']);
    }

    //生成请求头 getallheaders
    $data['header'] = array('Proxy-Url: ' . $url, 'Proxy-Id: ' . $proxyId);
    //apache 服务器
    if (function_exists('getallheaders')) {
        $temp = getallheaders();
        unset(
            $temp['Host'], $temp['Connection'], 
            $temp['Accept-Encoding'], $temp['Content-Length']
        );
        foreach ($temp as $k => &$v) {
            $data['header'][] = $k . ': ' . $v;
        }
    //其它 服务器
    } else {
        foreach ($_SERVER as $k => &$v) {
            //不在黑名单
            if (empty($black[$k])) {
                $k = explode('_', $k, 2);

                if ($k[0] === 'HTTP') {
                    //关键词头替换
                    $k[1][0] === '_' && $k[1] = substr($k[1], 1);
                    //保存头信息
                    $data['header'][] = strtr($k[1], '_', '-') . ': ' . $v;
                }
            }
        }
    }

    //尝试请求
    while ($tryNum--) {
        //请求数据
        $result = of_base_com_net::request($url, $data);

        //成功 && 结果非error
        if ($result['state'] && $result['response'] !== 'error') {
            $temp = preg_replace('@Transfer-Encoding: .*\r\n@i', '', $result['header']);
            $temp = explode("\r\n", $temp, -2);
            //响应代理地址
            $temp[] = 'Proxy-Url: ' . $url;
            $temp[] = 'Proxy-Id: ' . $proxyId;

            //发送响应
            array_map('header', $temp);
            //发送报文
            echo $result['response'];
            break;
        //失败, 5s 后重试
        } else {
            sleep(5);
        }
    }

    //为 -1 请失败
    if ($tryNum < 0) {
        //请求失败
        if ($result['errno'] >= 400) {
            header('HTTP/1.1 ' . $result['errno']);
        } else {
            header('HTTP/1.1 502 Bad Gateway');
        }
        echo $result['errstr'];
        trigger_error(
            date('Y-m-d H:i:s') . ">{$proxyId}>代理失败:\n--响应--\n" . 
            print_r($result, true) . 
            "\n--请求--\n" . 
            print_r($data, true)
        );
    }
}