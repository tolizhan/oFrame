# orange-framework 模块化轻量级php框架

框架本身是套解决方案, 兼容全浏览器及[php5.2~7.x](http://doc.phpof.net/?oFrame/FAQ/namespace.html,oFrame/navigation.html)<br>
它基于MVCS, 同时[支持多种设计模式](http://doc.phpof.net/?oFrame/FAQ/architect.html,oFrame/navigation.html)<br>
它拥有简洁的[开发方式](http://doc.phpof.net/?oFrame/helpManual/controller.html,oFrame/navigation.html,work), 巧思的[模板引擎](http://doc.phpof.net/?oFrame/helpManual/htmlTpl.html,oFrame/navigation.html)<br>
它支持原生的[并发操作](http://doc.phpof.net/?oFrame/components/timer.html,oFrame/navigation.html), 分布式[消息队列](http://doc.phpof.net/?oFrame/components/mq.html,oFrame/navigation.html)<br>
它包含完整的[错误日志](http://doc.phpof.net/?oFrame/helpManual/error.html,oFrame/navigation.html), 丰富的[开发接口](http://doc.phpof.net/?oFrame/FAQ/baseExtends.html,oFrame/navigation.html)<br>
它的思想是易部署, 易分布, 易迁移

手册地址 http://doc.phpof.net/<br>
问题反馈 tolizhan@qq.com

## 框架部署
### 测试部署
1. 部署一个php >= 5.2的网络环境
2. [下载 of 框架](https://github.com/tolizhan/oFrame/archive/master.zip)
3. 解压到任意可访问的路径, 如果是 Linux 创建 /data 文件, 给 -R 可读写权限
4. 访问框架根目录, 显示界面并且没有红色报错便部署成功

### 正式部署
1. 打开框架配置/include/of/config.php
2. 修改config键值为null 目的是删除 demo 对框架的重写
3. 修改db键值连接一个数据库
4. 根据实际需求开关 preloaded 中对应的模块
5. 删除/demo文件夹
6. 若为分布式架构, 将/data文件夹共享
7. 如没特殊需求, 以下两点可忽略
8. 框架可放在任意路径下,也可以改名,这里我们确定在"/include/of"
9. 修改rootDir键值为strtr(substr(\_\_FILE\_\_, 0, -22), '\\\\', '/') 目的是定义磁盘根路径到 "/include/of"

### 系统访问
1. URL格式可以通过系统入口定制<br>
    如: /index.php?c=控制类&a=方法名 或 /index.php/控制类/方法名 等
2. CLI模式可以通过"$GLOBALS键值:url编码"来设置超全局变量,可通过 _TZ指定时区, _IP指定IP, _RL指定ROOT_URL<br>
    如: php /index.php "get:c=demo_index&a=index" "post:test=demo" 设置$GLOBALS['_GET'] 和 $GLOBALS['_POST']值

### 框架升级
1. 用新版框架替换旧版不包含配置的全部文件 (注意新版中不存在的文件)
2. 查看旧版到新版本号的 [变更日志](https://github.com/tolizhan/oFrame/blob/master/changelog.txt)
3. 按照日志说明中"-"开头的变化从低到高版升级当前系统