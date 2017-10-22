<?php
//encoding=utf-8
/*
* 描述 : 演示扩展安装于运行的过程,第一次运行安装相应的扩展,第二次运行加载的扩展
* 方法 : 所有可用方法都在 setupExtension 类中的静态类
*      installation    : 安装/更新指定扩展
*      userStartPause  : 用户暂停暂停切换指定扩展
*      uninstall       : 卸载指定扩展
*      pageUpdate      : 更新指定页面的扩展文件(主要由程序调用)
*      getAllExtension : 获取所有扩展信息
*/
class demo_pageExtension {
    public function index() {
        $info = of_base_extension_manager::getExtensionInfo();
        //安装扩展
        if( $info['test']['state'] !== '1' ) {
            echo "\n安装", 
                of_base_extension_manager::setupExtension('test', 'demo_pageExtension::callBackMsg') ? '成功' : '失败';
        } else {
            echo '扩增输出如下信息 : ';
        }
        //echo "\n移除", of_base_extension_manager::removeExtension('test', 'demo_pageExtension::callBackMsg') ? '成功' : '失败';    //移除扩展
        //echo "\n安装", of_base_extension_manager::setupExtension('test', 'demo_pageExtension::callBackMsg') ? '成功' : '失败';    //安装扩展
        //echo "\n封装", of_base_extension_manager::dataManager('test', 'demo_pageExtension::callBackMsg', 'installData') ? '成功' : '失败';    //扩展数据封装并打包语言包
        //echo "\n备份", of_base_extension_manager::dataManager('test', 'demo_pageExtension::callBackMsg', null) ? '成功' : '失败';    //扩展数据打包
        //echo "\n恢复", of_base_extension_manager::dataManager('test', 'demo_pageExtension::callBackMsg', '20130308093115') ? '成功' : '失败';    //扩展数据恢复
        //echo "\n更新", of_base_extension_manager::updateLanguage() ? '成功' : '失败';    //更新扩展语言包
        //echo "\n打包", of_base_extension_manager::updateLanguage('test', null) ? '成功' : '失败';    //打包扩展语言包
        //echo "\n变更", of_base_extension_manager::changeState('test') ? '成功' : '失败';    //更新扩展状态
    }

    public static function callBackMsg($a) {
        echo join(' : ', $a), "<br>\n";
    }
}
return true;