<?php
/**
 * 描述 : 处理客户请求类
 * 作者 : Edgar.lee
 */
class of_base_link_request {
    /**
     * 描述 : 请求参数规则验证
     * 参数 :
     *     &rule : 验证的规则 {
     *          调度的方法名 : {
     *              $GLOBALS 中的get post等键名 : {
     *                  符合 of_base_com_data::rule 规则
     *              }
     *          }
     *      }
     *      exit : 校验失败是否停止, true=停止, false=返回
     * 返回 :
     *      无返回, 校验失败直接 exit
     * 作者 : Edgar.lee
     */
    public static function rule(&$rule, $exit = true) {
        //获取方法名
        $func = of::dispatch('action');

        //规则存在
        if (isset($rule[$func])) {
            foreach ($rule[$func] as $k => &$v) {
                //读取 GET, POST, COOKIE 等
                $k = '_' . strtoupper($k);

                //判断项不存在
                if (!isset($GLOBALS[$k])) {
                    $error[$k] = 'Invalid item : ' . $k;
                //规则校验失败
                } else if ($temp = of_base_com_data::rule($GLOBALS[$k], $v)) {
                    $error[$k] = $temp;
                }
            }
        //方法禁止调用
        } else {
            $error = 'Invalid func: ' . $func;
        }

        if (isset($error)) {
            $json = array(
                'code' => 400,
                'data' => &$error,
                'info' => 'Rule verification failed'
            );

            if ($exit) {
                exit(of_base_com_data::json($json));
            } else {
                return $json;
            }
        }
    }
}