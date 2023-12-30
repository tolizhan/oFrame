<?php
/**
 * 描述 : 任务默认模式
 * 作者 : Edgar.lee
 */
class of_accy_com_timer_default {
    /**
     * 描述 : 运行异步任务
     * 作者 : Edgar.lee
     */
    public static function fork($params) {
        of_base_com_net::request('', array(), $params);
    }
}