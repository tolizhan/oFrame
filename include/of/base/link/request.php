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
     * 返回 :
     *      无返回, 校验失败直接 exit
     * 作者 : Edgar.lee
     */
    public static function rule(&$rule) {
        //获取方法名
        $func = of::dispatch('action');

        //规则存在
        if (isset($rule[$func])) {
            foreach ($rule[$func] as $k => &$v) {
                $k = '_' . strtoupper($k);

                if (isset($GLOBALS[$k])) {
                    $error = of_base_com_data::rule($GLOBALS[$k], $v);

                    //校验失败
                    if ($error) {
                        $json = array(
                            'state' => 400,
                            'data'  => &$error,
                            'info'  => 'Invalid parameter: ' . $k
                        );
                        exit(of_base_com_data::json($json));
                    }
                } else {
                    $json = array(
                        'state' => 500,
                        'data'  => array(),
                        'info'  => "Invalid rule: {$func}.{$k}"
                    );
                    exit(of_base_com_data::json($json));
                }
            }
        }
    }
}