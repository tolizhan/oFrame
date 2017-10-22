<?php
/**
 * 描述 : 编码 of_view 对象中属性名非"_"开始的所有键及子数据
 * 作者 : Edgar.lee
 */
class of_base_xssFilter_main {

    /**
     * 描述 : 'of::display' 事件触发
     * 作者 : Edgar.lee
     */
    public static function xssFilter($params) {
        foreach ($params['viewObj'] as $k => &$v) {
            //非"_"开始的属性名
            $k[0] === '_' || self::toHtml($v);
        }
    }

    /**
     * 描述 : 编码数据
     * 作者 : Edgar.lee
     */
    private static function toHtml(&$data) {
        //额外的JS关键词
        static $replace = array(array('(', ')', '.', '='), array('&#040;', '&#041;', '&#046;', '&#061;'));
        //待处理列表
        $waits = array(&$data);

        do {
            $wk = key($waits);
            $wv = &$waits[$wk];
            unset($waits[$wk]);

            if (is_array($wv)) {
                //结果列表
                $result = array();
                foreach ($wv as $k => &$v) {
                    $result[str_replace($replace[0], $replace[1], htmlspecialchars($k, ENT_QUOTES, 'UTF-8'))] = &$v;
                    $waits[] = &$v;
                }
                $wv = $result;
            } else if (is_string($wv) && !is_numeric($wv)) {
                $wv = str_replace($replace[0], $replace[1], htmlspecialchars($wv, ENT_QUOTES, 'UTF-8'));
            }
        } while (!empty($waits));
    }
}

of::event('of_view::display', 'of_base_xssFilter_main::xssFilter');