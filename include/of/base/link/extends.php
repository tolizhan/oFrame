<?php
/**
 * 描述 : 集成 L 类的扩展方法
 * 作者 : Edgar.lee
 */
class of_base_link_extends {
    /**
     * 描述 : 魔术方法, 获取com组件及view对象
     * 参数 :
     *      key : 以"_"开头的变量会创建并返回 of_base_com_xxx 对象, "view"时会实例化 of_view
     * 作者 : Edgar.lee
     */
    public static function get($key) {
        //组件对象列表,存放在
        static $comObjs = null;
        //加载com组件
        if ($key[0] === '_') {
            isset($comObjs[$temp = 'of_base_com' . $key]) || $comObjs[$temp] = new $temp;
            return $comObjs[$temp];
        //加载view视图
        } else if ($key === 'view') {
            return of_view::inst();
        }
    }
}