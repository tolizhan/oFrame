<?php
/**
 * 描述 : 控制层共享文件, 控制层文件与类名相同, 已$_GET['a']作为方法名(默认index)
 * 作者 : Edgar.lee
 */
//加载核心
require dirname(dirname(dirname(__FILE__))) . '/include/of/of.php';

//调查初始化
$_GET += array('c' => 'main', 'a' => 'index');
//调度代码 : 类名, 动作, 安全校验
$result = of::dispatch('serv_papi_' . $_GET['c'], $_GET['a'], null);
//返回数组转成json
if (is_array($result)) echo of_base_com_data::json($result);