<?php
/**
 * 描述 : 测试用例测试
 * 作者 : Edgar.lee
 */
class test_cases_demo {
    /**
     * 描述 : 演示测试脚本
     * 作者 : Edgar.lee
     */
    public static function test() {
        //生产一个随机数演示
        $num = rand(1, 10);

        //断言未通过 && 抛出错误
        $num > 5 && of::work(400, '随机断言未通过');
        //返回断言通过
        return true;
    }
}