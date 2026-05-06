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

    /**
     * 描述 : 异步任务数据
     * 作者 : Edgar.lee
     */
    public static function data($mark, $data) {
        //生成完整标识
        $mark = 'of_accy_com_timer_default::data#' . $mark;

        //修改任务数据
        if (is_array($data)) {
            of_base_com_kv::set($mark, $data, 86400, '_ofSelf');
        //获取或删除任务数据
        } else {
            return $data ? of_base_com_kv::get($mark, null, '_ofSelf') : of_base_com_kv::del($mark, '_ofSelf');
        }
    }
}