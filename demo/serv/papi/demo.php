<?php
class serv_papi_demo extends serv_papi_main {
    //方法规则
    protected $funcRule = array(
        //接口演示 /serv/?c=demo&a=index
        'index' => array(
            //校验get参数
            'GET' => array(
                //必须包含siez并且为int型
                'size' => 'int'
            )
        ),
        //失效演示 /serv/ 会显示接口详情
        'invalid' => array(),
        //断言演示 /serv/?c=demo&a=assert
        'assert' => array()
    );

    /**
     * 描述 : 接口演示
     * 作者 : Edgar.lee
     */
    public function index() {
        return array('code' => 200, 'info' => 'done');
    }

    /**
     * 描述 : 断言演示
     * 作者 : Edgar.lee
     */
    public function assert() {
        //开启事务
        //L::sql(null);

        //模拟GET, POST, COOKIE 等数据
        //$_GET['size'] = '244';

        //断言测试
        $error = of_base_tool_test::check('serv_papi_demo', 'index');

        //事务回滚
        //L::sql(false);

        //验证结果
        if ($error) {
            echo '<pre>';
            echo 'demo::index    ';
            echo '<a href="?c=demo&a=assert&size=1" style="color: red;">传入size为int的get参数会响应正确信息</a> <br>';
            print_r($error);
            echo '</pre>';
        } else {
            echo '测试方法返回 : ' . var_export($error, true);
        }
    }
}