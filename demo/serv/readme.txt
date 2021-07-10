支持断言测试的接口使用方式

如果希望更好的管控对外接口, 可以试试使用这种方式
结构:
1. /serv/papi 供接口, 为第三方提供接口(Provided Interfaces)
2. /serv/rapi 需接口, 向第三方请求接口(Required Interfaces 建议)

演示:
1. 将该文件夹拷贝到根目录作为服务层
2. 访问 /serv/ 可以看到所有公开接口
3. 通过访问 /serv/?c=demo&a=index&size=4 查看测试接口, 当size不为数字时会抛出错误
4. 通过访问 /serv/?c=demo&a=assert 进行断言测试

使用:
1. 在 /serv/pApi/ 中创建接口类并继承serv_papi_main类
2. 按照 serv_papi_main 类中的注释设置 $funcRule 规则
3. 如果需要可设置 serv_papi_main 中的共用规则 $shareRule
4. 实现接口方法并访问测试 /serv/?c=文件名&a=方法名