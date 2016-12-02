<?php
/**
 * 描述 : 提供测试探针类
 * 作者 : Edgar.lee
 */
class of_base_tool_test {

    /**
     * 描述 : 计算运行时间
     * 参数 :
     *       arg1 : 指定过去的的某一时间点与当前比较,默认上次运行该方法时间点
     *       arg2 : 多功能参数,false=不做比较,将当前时间点引用到arg1变量(arg1不会作为上次时间点使用), true=做比较,将当前时间点引用到arg1变量(arg1不会作为上次时间点使用), 某一时间点=代替当前时间点,默认当前时间点
     * 演示 :
     *      profiling();    //a,无输出
     *      profiling();    //b,输出b-a时间区间
     *      profiling($t_o, false);    //c,无输出,将当期时间点引用给$t
     *      profiling($t_n, true);    //d,输出d-c时间区间,将当期时间点引用给$t
     *      profiling($t_o, $t_n);    //输出d-c时间区间
     * 作者 : Edgar.lee
     */
    public static function profiling(&$arg1 = null, $arg2 = null) {
        $nowMicrotime = microtime();
        $argsNum = func_num_args();
        //调用次数
        static $callsNumber = 0;
        static $tag;

        //关闭输出缓存
        if(ob_get_length() !== false) {
            ob_end_flush();
            ob_implicit_flush(true);
        }

        if($callsNumber > 0 && $arg2 !== false) {
            if($argsNum === 1 || is_array($arg2)) {
                $tag = $arg1;
                if(is_array($arg2)) {
                    $nowMicrotime = $arg2['microtime'];
                    $callsNumber = $arg2['callsNumber'];
                }
            }
            $microtime_1 = explode(' ',$tag['microtime']);
            $microtime_2 = explode(' ',$nowMicrotime);
            $microtime = $microtime_2[0] - $microtime_1[0];
            $microtime += $microtime_2[1] - $microtime_1[1];

            echo '<pre>';
            echo '用时 ', sprintf('%f', $microtime), ' s',$callsNumber - $tag['callsNumber'];
            echo '</pre>';
        }
        $tag['microtime'] = &$nowMicrotime;
        $tag['callsNumber'] = $callsNumber;

        if(is_bool($arg2)) {
            $arg1 = $tag;
        }
        $callsNumber += 1;
    }
}