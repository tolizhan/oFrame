<?php
/**
 * 描述 : 配置测试用例列表
 * 注明 :
 *      配置结构 : {
 *          "title" : 用例故事名称
 *          "cases" : 测试案例列表 {
 *              描述文本 : {
 *                  "php" : 框架回调结构
 *                      返回true或{"code" : < 400}算成功,
 *                      使用of::work(code, info, data) 抛出错误
 *              },
 *              ...
 *          }
 *      }
 */
return array(
    'title' => '测试用例案例',
    'cases' => array(
        '演示测试脚本' => array(
            'php' => 'test_cases_demo::test'
        )
    )
);