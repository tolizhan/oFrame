<?php
/**
 * 描述 : 任务swoole模式
 * 作者 : Edgar.lee
 */
class of_accy_com_timer_swoole {
    /**
     * 描述 : 运行异步任务
     * 作者 : Edgar.lee
     */
    public static function fork($params) {
        //启动监听(php 7.0+)或未指定并发数, 当前工作进程中执行
        if (empty($params['params'][0]['cNum'])) {
            swoole::fork($params);
        //指定了并发数, 独立进程方式执行
        } else {
            swoole::fork($params, 2);
        }
    }
}