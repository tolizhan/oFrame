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
        //协程任务, 在共享进程中执行
        if ($params['params'][1]['type'] & 8) {
            swoole::fork($params, 4);
        //独立进程方式执行
        } else {
            swoole::fork($params, 2);
        }
    }
}