# orange-framework 模块化轻量级php框架

框架本身是套解决方案, 兼容全浏览器及[php5.2~8.4.x](http://doc.phpof.net/?oFrame/FAQ/namespace.html,oFrame/navigation.html)<br>
它基于MVCS, 同时[支持多种设计模式](http://doc.phpof.net/?oFrame/FAQ/architect.html,oFrame/navigation.html)<br>
它拥有简洁的[开发方式](http://doc.phpof.net/?oFrame/helpManual/controller.html,oFrame/navigation.html,work), 简单的[模板引擎](http://doc.phpof.net/?oFrame/helpManual/htmlTpl.html,oFrame/navigation.html)<br>
它支持原生的[异步操作](http://doc.phpof.net/?oFrame/components/timer.html,oFrame/navigation.html), 分布式[消息队列](http://doc.phpof.net/?oFrame/components/mq.html,oFrame/navigation.html)<br>
它包含完整的[错误日志](http://doc.phpof.net/?oFrame/helpManual/error.html,oFrame/navigation.html), 丰富的[扩展接口](http://doc.phpof.net/?oFrame/FAQ/baseExtends.html,oFrame/navigation.html)<br>
它的理念是易部署, 易扩展, [易伸缩](http://doc.phpof.net/?oFrame/FAQ/issue.html,oFrame/navigation.html,scaling)

手册地址 http://doc.phpof.net/ 或 wiki<br>
问题反馈 tolizhan@qq.com

## 框架部署
### 测试部署
1. 部署一个php >= 5.2的网络环境 [Swoole环境部署](http://doc.phpof.net/?oFrame/helpManual/swoole.html,oFrame/navigation.html)
2. 下载框架代码 https://github.com/tolizhan/oFrame/ 或 https://gitee.com/tolizhan/oFrame/
3. 解压到任意可访问的路径, 如果是 Linux 创建 /data 文件, 给 -R 可读写权限
4. 访问框架根目录, 显示界面并且没有红色报错便部署成功

### 正式部署
0. 打开框架配置/include/of/config.php
1. 修改config键值为null 目的是删除 demo 对框架的重写
2. 修改debug键值, 生产环境一个要改为"字符串"密码, 防止生产环境敏感信息泄漏
3. 修改db键值连接一个数据库
4. 根据实际需求开关 preloaded 中对应的模块
5. 删除/demo文件夹
6. 若为分布式架构, 将各节点/data文件夹挂载到同一个目录, K-V使用非files方式
7. 若为分布式架构, 将/data文件夹共享
8. 如没特殊需求, 以下两点可忽略
9. 框架可放在任意路径下,也可以改名,这里我们确定在"/include/of"
10. 修改rootDir键值为strtr(substr(\_\_FILE\_\_, 0, -22), '\\\\', '/') 目的是定义磁盘根路径到 "/include/of"

### 系统访问
1. URL格式可以通过系统入口定制<br>
    如: /index.php?c=控制类&a=方法名 或 /index.php/控制类/方法名 等
2. CLI模式可以通过"`$GLOBALS键值:url编码`"来设置超全局变量,可通过 _TZ指定时区, _IP指定IP, _RL指定ROOT_URL<br>
    如: `php /index.php "get:c=demo_index&a=index" "post:test=demo"` 设置`$GLOBALS['_GET']`和`$GLOBALS['_POST']`值

### 框架升级
1. 用新版框架替换旧版不包含配置的全部文件 (注意删除新版中不存在的文件)
2. 查看旧版到新版本号的 [变更日志](changelog.txt)
3. 按照日志说明中"-"开头的变化从低到高版升级当前系统

### 入门顺序
0. [开发规范](http://doc.phpof.net/?codingStandard/htmlCssJsPhpMysql/general.html,codingStandard/navigation.html)
1. [了解框架](http://doc.phpof.net/?oFrame/gettingStarted/preface.html)
2. [部署说明](http://doc.phpof.net/?oFrame/gettingStarted/deploy.html)
3. [配置文件](http://doc.phpof.net/?oFrame/gettingStarted/config.html)
4. [入门演示](http://doc.phpof.net/?oFrame/gettingStarted/introduction.html)
5. [错误日志](http://doc.phpof.net/?oFrame/gettingStarted/error.html,oFrame/navigation.html)
6. [php L类 ](http://doc.phpof.net/?oFrame/gettingStarted/Lphp.html)
7. [js L对象](http://doc.phpof.net/?oFrame/gettingStarted/L.js.html)
8. [模版引擎](http://doc.phpof.net/?oFrame/gettingStarted/htmlTpl.html,oFrame/navigation.html)
9. [分页列表](http://doc.phpof.net/?oFrame/gettingStarted/pageTable.html)
10. [上传插件](http://doc.phpof.net/?oFrame/integrated/oUpload.html,oFrame/navigation.html)
11. [工作流开发](http://doc.phpof.net/?oFrame/helpManual/controller.html,oFrame/navigation.html,work)
12. [计划任务](http://doc.phpof.net/?oFrame/components/timer.html,oFrame/navigation.html)
13. [消息队列](http://doc.phpof.net/?oFrame/components/mq.html,oFrame/navigation.html)