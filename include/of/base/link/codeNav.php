<?php
/**
 * 描述 : 用于IDE编辑器对"of\xxx\yyy"及"L类"的代码跟踪
 * 作者 : Edgar.lee
 */
namespace of {

    abstract class db extends \of_db {
    }

    class view extends \of_view {
    }

}

namespace of\accy\com\data\lock {

    class files extends \of_accy_com_data_lock_files {
    }

    class swooleRedis extends \of_accy_com_data_lock_swooleRedis {
    }

}

namespace of\accy\com\kv {

    class files extends \of_accy_com_kv_files {
    }

    class memcache extends \of_accy_com_kv_memcache {
    }

    class redis extends \of_accy_com_kv_redis {
    }

}

namespace of\accy\com\mq {

    class mysql extends \of_accy_com_mq_mysql {
    }

    class redis extends \of_accy_com_mq_redis {
    }

}

namespace of\accy\com\timer {

    class default extends \of_accy_com_timer_default {
    }

    class swoole extends \of_accy_com_timer_swoole {
    }

}

namespace of\accy\db {

    class mssqlPdo extends \of_accy_db_mssqlPdo {
    }

    class mysql extends \of_accy_db_mysql {
    }

    class mysqlPdo extends \of_accy_db_mysqlPdo {
    }

    class mysqli extends \of_accy_db_mysqli {
    }

    class polar extends \of_accy_db_polar {
    }

    class tidb extends \of_accy_db_tidb {
    }

}

namespace of\accy\session {

    class files extends \of_accy_session_files {
    }

    class kv extends \of_accy_session_kv {
    }

    class mysql extends \of_accy_session_mysql {
    }

}

namespace of\base\com {

    class com extends \of_base_com_com {
    }

    class csv extends \of_base_com_csv {
    }

    class data extends \of_base_com_data {
    }

    class disk extends \of_base_com_disk {
    }

    class hParse extends \of_base_com_hParse {
    }

    class kv extends \of_base_com_kv {
    }

    class mq extends \of_base_com_mq {
    }

    class net extends \of_base_com_net {
    }

    class str extends \of_base_com_str {
    }

    class timer extends \of_base_com_timer {
    }

}

namespace of\base\error {

    class jsLog extends \of_base_error_jsLog {
    }

    class tool extends \of_base_error_tool {
    }

    class toolBaseClass extends \of_base_error_toolBaseClass {
    }

    class writeLog extends \of_base_error_writeLog {
    }

}

namespace of\base\extension {

    class baseClass extends \of_base_extension_baseClass {
    }

    class manager extends \of_base_extension_manager {
    }

    class match extends \of_base_extension_match {
    }

    class tool extends \of_base_extension_tool {
    }

    class toolBaseClass extends \of_base_extension_toolBaseClass {
    }

}

namespace of\base\firewall {

    class main extends \of_base_firewall_main {
    }

}

namespace of\base\htmlTpl {

    class engine extends \of_base_htmlTpl_engine {
    }

    class tool extends \of_base_htmlTpl_tool {
    }

}

namespace of\base\language {

    class packs extends \of_base_language_packs {
    }

    class toolBaseClass extends \of_base_language_toolBaseClass {
    }

}

namespace of\base\link {

    class extend extends \of_base_link_extend {
    }

    class request extends \of_base_link_request {
    }

    class response extends \of_base_link_response {
    }

}

namespace of\base\session {

    class base extends \of_base_session_base {
    }

}

namespace of\base\sso {

    class api extends \of_base_sso_api {
    }

    class main extends \of_base_sso_main {
    }

    class tool extends \of_base_sso_tool {
    }

}

namespace of\base\test {

    class case extends \of_base_test_case {
    }

    class tool extends \of_base_test_tool {
    }

    class toolBaseClass extends \of_base_test_toolBaseClass {
    }

}

namespace of\base\tool {

    class mysqlSync extends \of_base_tool_mysqlSync {
    }

    class test extends \of_base_tool_test {
    }

}

namespace of\base\version {

    class check extends \of_base_version_check {
    }

}

namespace of\base\xssFilter {

    class main extends \of_base_xssFilter_main {
    }

}

namespace {

    class L {
        /**
         * 描述 : 工具组件封装
         * 作者 : Edgar.lee
         */
        public of_base_com_com $_com;

        /**
         * 描述 : csv 导入导出
         * 作者 : Edgar.lee
         */
        public of_base_com_csv $_csv;

        /**
         * 描述 : 提供数据相关封装
         * 作者 : Edgar.lee
         */
        public of_base_com_data $_data;

        /**
         * 描述 : 提供磁盘相关封装
         * 作者 : Edgar.lee
         */
        public of_base_com_disk $_disk;

        /**
         * 描述 : html解析与jquery方式操作
         * 技巧 :
         *      如何创建纯文本节点?
         *          m('</>纯文本');                             //利用任意无效结束标签,如:</>
         *      如何使修改的节点属性值在输出时不被编码(如:value="<?php echo 1; ?>")
         *          attr('', 'value="<?php echo 1; ?>"');       //''是一个特殊的属性,它在输出时紧跟标签名原样输出
         *          removeAttr('value');                        //移除value属性
         * 方法 :
         *      操作区
         *      public  m                       多功能生成器
         *      public  addClass                为每个匹配的元素添加指定的类名
         *      public  hasClass                检查匹配的元素是否含有某个特定的类
         *      public  html                    读取或设置匹配节点的innerHTML值
         *      public  text                    得到匹配元素集合中每个元素的文本内容结合,包括他们的后代
         *      public  attr                    为指定元素设置一个或多个属性
         *      public  removeAttr              为匹配的元素集合中的每个元素中移除一个属性
         *      public  removeClass             移除每个匹配元素的一个，多个或全部样式
         *      public  eq                      获取匹配集合中指定的元素
         *      public  slice                   减少匹配元素集合由索引范围到指定的一个子集
         *      public  find                    获得当前元素匹配集合中每个元素的后代，选择性筛选的选择器
         *      public  get                     返回对象内部属性
         *      public  val                     读取或设置匹配的节点的值
         *      public  css                     为匹配的元素集合中获取第一个元素的样式属性值(仅实现解析赋值,没实现继承关系)
         *      public  after                   根据参数设定在每一个匹配的元素之后插入内容
         *      public  append                  根据参数设定在每个匹配元素里面的末尾处插入内容
         *      public  appendTo                根据参数设定在每个匹配元素里面的末尾处插入内容
         *      public  before                  根据参数设定在匹配元素的前面（外面）插入内容
         *      public  prepend                 将参数内容插入到每个匹配元素的前面（元素内部）
         *      public  prependTo               将所有元素插入到目标前面（元素内）
         *      public  replaceWith             用提供的内容替换所有匹配的元素
         *      public  replaceAll              用匹配元素替换所有目标元素
         *      public  clones                  深度复制匹配的元素
         *      public  emptys                  移除所有匹配节点的子节点
         *      public  remove                  移除所有匹配节点
         *      public  unwrap                  将匹配元素的父级元素删除，保留自身（和兄弟元素，如果存在）在原来的位置
         *      public  wrap                    在每个匹配的元素外层包上一个html元素
         *      public  wrapInner               在匹配元素里的内容外包一层结构
         *      public  wrapAll                 在所有匹配元素外面包一层HTML结构
         *      public  add                     添加元素到匹配的元素集合
         *      public  andSelf                 添加先前的堆栈元素集合到当前组合
         *      public  end                     终止在当前链的最新过滤操作，并返回匹配的元素集合为它以前的状态
         *      public  children                获得每个匹配元素集合元素的子元素，选择性筛选的选择器
         *      public  closest                 从元素本身开始，逐级向上级元素匹配
         *      public  contents                获得每个匹配元素集合元素的子元素,包括文字和注释节点
         *      public  filter                  筛选出与指定表达式匹配的元素集合
         *      public  doc                     输出文档节点的html或对象
         *      public  first                   获取元素集合中第一个元素
         *      public  last                    获取元素集合中最后一个元素
         *      public  has                     选择含有选择器所匹配的至少一个元素的元素
         *      public  not                     删除匹配的元素集合中元素
         *      public  is                      检查当前匹配的元素集合是否匹配
         *      public  next                    取得一个包含匹配的元素集合中每一个元素紧邻的后面同辈元素的元素集合
         *      public  nextAll                 取得一个包含匹配的元素集合中每一个元素全部的后面同辈元素的元素集合
         *      public  nextUntil               取得一个包含匹配的元素集合中每一个元素后面直到匹配前的同辈元素的元素集合
         *      public  prev                    取得一个包含匹配的元素集合中每一个元素紧邻的前面同辈元素的元素集合
         *      public  prevAll                 取得一个包含匹配的元素集合中每一个元素全部的前面同辈元素的元素集合
         *      public  prevUntil               取得一个包含匹配的元素集合中每一个元素前面直到匹配前的同辈元素的元素集合
         *      public  parent                  取得一个包含匹配的元素集合中每一个元素紧邻的父辈元素的元素集合
         *      public  parents                 取得一个包含匹配的元素集合中每一个元素全部的父辈元素的元素集合
         *      public  parentsUntil            取得一个包含匹配的元素集合中每一个元素前面直到匹配前的父辈元素的元素集合
         *      public  siblings                获得匹配元素集合中每个元素的兄弟元素
         *      public  index                   从匹配的元素中搜索给定元素的索引值
         *      public  size                    返回当期对象匹配包含节点数量
         *      public  insertAfter             在目标后面插入每个匹配的元素
         *      public  insertBefore            选择符,HTML字符串或者HParse对象
         *      private multiFunction           多功能生成器
         *      private insertNode              不同类型的节点插入操作
         *      private wrapOperating           为匹配元素包含标签操作
         *      private relationship            取得一个包含匹配的元素集合中每一个元素紧邻的全部元素集合
         *      筛选器
         *      public  selectors               选择器核心
         *      private filterNodeKeys          按规则过滤伪类或属性
         *      private filterAttrNodeKeys      过滤属性节点键
         *      private filterPseudoNodeKeys    过滤伪类节点键(未实现与样式有关的伪类)
         *      private matchKeyword            匹配selectors传入的关键词
         *      private getNextBrackets         需找下一个右括号'('或'['时调用有效
         *      private nodeKeysUniqueSort      节点键去重排序
         *      public  twoNodeKeySort          比对两个节点的先后顺序(仅由nodeKeysUniqueSort调用)
         *      工具区
         *      public  nodeAttr                读取设置指定节点键属性
         *      public  nodeConn                读取与指定节点相关系的节点
         *      public  nodeSplice              移除或插入指定节点
         *      private hasChildTag             判断是否有子节点标签
         *      private entities                html实体转换
         *      private htmlFormat              遍历指定节点的子节点,返回格式化的数组
         *      private cloneNode               克隆节点
         *      private nodeCollection          节点回收工具(GC)
         *      解析区
         *      private htmlParse               解析html
         *      private setTempNodeAttr         设置临时节点的属性值或名
         *      private tempToFormalNode        从临时节点转为正式节点
         *      private createStringNode        创建字符串节点
         *      private planNode                对新节点规划,对关闭节点容错
         * 作者 : Edgar.Lee
         */
        public of_base_com_hParse $_hParse;

        /**
         * 描述 : K-V存储基类
         * 注明 :
         *      连接池列表结构($instList) : {
         *          "default" : {
         *              "pool" : 格式化后的连接池结构为 {
         *                  'adapter' : 适配类型
         *                  'params'  : 适配参数
         *              },
         *              "inst" : 初始化的连接源对象
         *          }
         *      }
         * 作者 : Edgar.lee
         */
        public of_base_com_kv $_kv;

        /**
         * 描述 : 消息队列封装
         * 注明 :
         *      消息队列配置结构($config) : {
         *          消息队列池名 : {
         *              "adapter" : 适配器,
         *              "params"  : 调度参数 {
         *              },
         *              "bindDb"  : 事务数据库连接池名,
         *              "queues"  : 生产消息时会同时发给队列, 字符串=该结构的配置文件路径 {
         *                  队列名 : {
         *                      "mode"   : 队列模式, null=生产及消费,false=仅生产,true=仅消费,
         *                      "check"  : 自动重载消息队列触发函数,
         *                          true=(默认)校验"消费回调"加载的文件变动,
         *                          false=仅校验队列配置文件变动,
         *                          字符串=以"@"开头的正则忽略路径(软链接使用真实路径), 如: "@/ctrl/@i"
         *                      "memory" : 单个并发未释放内存积累过高后自动重置, 单位M, 默认50, 0=不限制
         *                      "keys"   : 消费消息时回调结构 {
         *                          消息键 : 不存在的键将被抛弃 {
         *                              "lots" : 批量消费, 1=单条消费, >1=一次消费最多数量(消息变成一维数组)
         *                              "cNum" : 并发数量,
         *                              "call" : 回调结构
         *                          }, ...
         *                      }
         *                  }, ...
         *              }
         *          }, ...
         *      }
         *      消息队列列表($mqList) : {
         *          事务数据库连接池名 : {
         *              "isSet" : 执行过队列信息, true=调用过set, false=未调用过
         *              "level" : 当前数据库池等级, 0=不在事务里, 1=根事务, n=n层事务里
         *              "state" : 当前事务最终状态, true=提交, false=回滚
         *              "pools" : {
         *                  消息队列池名 : {
         *                      "inst" : 初始化的对象
         *                      "keys" : 队列与键对应的配置路径 {
         *                          队列名 : {
         *                              "mode" : 队列模式, null=生产及消费, false=仅生产, true=仅消费
         *                              "data" : 引用加载配置
         *                          }...
         *                      }
         *                      "msgs" : 待处理消息列表 {
         *                          消息唯一标识"队列名\0类型\0消息ID" : {
         *                              "keys"  : [类型, 消息ID, 延迟]
         *                              "data"  : null
         *                              "queue" : 队列名
         *                          }, ...
         *                      }
         *                  }, ...
         *              }
         *          }
         *      }
         *      键值结构 : 已"of_base_com_mq::"为前缀的键名
         *          "nodeList" : 完整分布式节点(永不过期), 记录不同"_of.nodeName"节点, 失效时定期清理 {
         *              节点ID : 节点信息 {
         *                  "tNum" : 队列位置,
         *              }, ...
         *          }
         *      加锁逻辑 : 已"of_base_com_mq::"为前缀
         *          "nodeList" : 当新插入或清理节点时加独享锁
         *          nodeLock#节点ID : 节点进程, 启动时独享锁
         *          daemon#节点ID : 守护进程, 启动时独享锁
         *      磁盘结构 : {
         *          "/failedMsgs"   : 失败的消息列表 {
         *              /连接池名 : {
         *                  md5(队列名\0消息键\0消息ID).php 文件,
         *                  ...
         *              }
         *          }
         *      }
         * 作者 : Edgar.lee
         */
        public of_base_com_mq $_mq;

        /**
         * 描述 : 提供网络通信相关封装
         * 注明 :
         *      配置文件结构($config) : {
         *          "async" : 异步请求方案, ""=当前网址, url=指定网址
         *          "rCode" : 接受响应压缩编码
         *          "asUrl" : 异步请求使用的网络地址解析格式 {
         *              "scheme" : 网络协议, http或https,
         *              "host"   : 请求域名,
         *              "port"   : 请求端口,
         *              "path"   : 请求路径,
         *              "query"  : 请求参数
         *          }
         *      }
         * 作者 : Edgar.lee
         */
        public of_base_com_net $_net;

        /**
         * 描述 : 提供字符串相关封装
         * 作者 : Edgar.lee
         */
        public of_base_com_str $_str;

        /**
         * 描述 : 计划任务,定时回调
         * 注明 :
         *      键值结构 : 已"of_base_com_timer::"为前缀的键名
         *          "nodeList" : 完整分布式节点(永不过期), 记录不同"_of.nodeName"节点, 失效时定期清理 {
         *              节点ID : 节点信息 {
         *                  "time" : 创建时间
         *              }, ...
         *          }
         *          "taskList" : 完整任务列表(永不过期), 停用的定期清理 {
         *              任务ID : 未来扩展 {},
         *              ...
         *          }
         *          taskNote#任务ID : 单个任务备注(30天过期), 同taskList新增, 定期清理或延期 {
         *              "call" : 回调方法
         *          }
         *          taskInfo#任务ID : 单个任务信息(30天过期), 定期清理或延期 {
         *              "list" : 并发列表 {
         *                  并发数字 : {
         *                      "time" : 启动时间
         *                  }, ...
         *              }
         *          }
         *      加锁逻辑 : 已"of_base_com_timer::"为前缀
         *          "nodeList" : 当新插入或清理节点时加独享锁
         *          nodeLock#节点ID : 节点进程, 启动时独享锁
         *          daemon#节点ID : 守护进程, 启动时独享锁
         *          "taskIsGc" : 标识守护进程为任务回收器
         *          "taskList" : 任务列表锁, 当新插入或清理任务时加独享锁
         *          taskLock#任务ID : 单个任务锁, 任务启动时加共享锁, 清理时加独享锁, 未存信息
         *          taskLock#任务ID#并发数字 : 任务并发锁, 对应并发任务启动时加独享锁
         *          taskInfo#任务ID : 任务信息锁, 修改任务信息时加独享锁
         *      磁盘结构 :
         *          "taskList.php" : 动态任务文件模式下存储的文件
         * 作者 : Edgar.lee
         */
        public of_base_com_timer $_timer;

        /**
         * 描述 : 视图层核心
         * 作者 : Edgar.lee
         */
        public of_view $view;

        /**
         * 描述 : 触发钩子(仅能触发公有钩子)
         * 参数 :
         *      type   : 钩子类型
         *      params : 传递参数,由callback第一个参数接收,null=默认
         *      ob     : 是否使用缓存,false=非,默认true=是
         * 作者 : Edgar.lee
         */
        public static function fireHook($type, $params = null) {
            of_base_extension_match::fireHook($type, $params, true);
        }

        /**
         * 描述 : 视图回调
         * 参数 :
         *      params : 相对 of_view::path() 的路径
         * 返回 :
         *      html扩展名返回of.eval://...协议字符串
         *      其它扩展名原样返回
         * 作者 : Edgar.lee
         */
        public static function &getHtmlTpl($params) {
            return of_base_htmlTpl_engine::getHtmlTpl($params);
        }

        /**
         * 描述 : 获取php端语言包
         * 参数 :
         *     &string : 指定翻译的字符串
         *     &params : 附加数组参数 {
         *          "key"   : 区分键,默认""
         *          "mode"  : 翻译模式, 0=完整翻译, 1=按_of.language.match规则提取翻译文本
         *      }
         * 返回 :
         *      翻译的字符串
         * 作者 : Edgar.lee
         */
        public static function &getText($string, $params = array()) {
            return of_base_language_packs::getText($string, $params);
        }

        /**
         * 描述 : 魔术方法, 获取com组件及view对象
         * 参数 :
         *      key : 以"_"开头的变量会创建并返回 of_base_com_xxx 对象, "view"时会实例化 of_view
         * 作者 : Edgar.lee
         */
        public function __get($key) {
            return of_base_link_extend::get($key);
        }

        /**
         * 描述 : 管理工作流程, 独立的 时间 队列 错误及事务, 让代码更简洁
         *      工作可以嵌套, 产生任何错误, 事务都会回滚, 嵌套工作会创建额外数据库连接
         *      可以使用 try catch 或 回调方式 开始一个工作
         *      可以获取 当前工作开始时间 与 产生的错误
         *      可以抛出 工作异常 并通过捕获简化代码逻辑
         * 参数 :
         *     #开启工作(数组, null)
         *      code : 监听数据库连接, 产生问题会自动回滚, 数组=[连接池, ...], null=自动监听
         *      info : 功能参数
         *          int=增加数据监控, 0为当前工作, 1=父层工作..., 指定工作不存在抛出异常
         *          框架回调结构=回调模式创建工作, 不需要 try catch, 回调接收(params)参数 {
         *                  "result" : &标准结果集
         *                  "data"   : &标准结果集中的data数据
         *              }
         *              返回 false 时, 回滚工作, 等同 of::work(200, 'Successful', params['data'])
         *              返回 array 时, 赋值data, 等同 params['data'] = array;
         *      data : null=启动集成工作, 统一监听子孙工作事务, 启动时自动设置自动监听
         *     #结束工作(布尔)
         *      code : 完结事务, true=提交, false=回滚
         *     #抛出异常(数字)
         *      code : 问题编码, [400, 600)之间的数字
         *      info : 提示信息
         *      data : 扩展参数, 一个数组
         *     #捕捉异常(对象)
         *      code : 异常对象, 通过 try catch 捕获的异常
         *     #获取时间(文本)
         *      code : 固定"time"
         *      info : 返回时间格式, 默认2=格式化时间, 1=时间戳, 3=[时间戳, 格式化, 时区标识, 格林时差]
         *     #操作错误(文本)
         *      code : 固定"error"
         *      info : 默认true=获取错误, false=清除错误
         *     #全局排除监听(文本)
         *      code : 固定"block"
         *      info : 排查的监听列表, {
         *          "数据库连接池" : true=排除, false=移除
         *          ...
         *      }
         *     #工作信息(文本)
         *      code : 固定"info"
         *      info : 获取指定"info"信息, 默认=3(1 | 2), 1=工作ID, 2=监听数据库, 4=注入回调信息
         *     #延迟回调(文本) 在工作事务提交前按队列顺序执行
         *      code : 固定"defer"
         *      info : 回调方法接收参数结构 {"wuid" : 工作ID, "isOk" : true=最终提交 false=最终回滚}
         *          true = 读取指定标识的回调
         *          false = 删除指定标识的回调
         *          框架回调结构 = 不开启工作直接回调, 若报错将影响当前工作执行结果
         *          {"onWork" : 监听数据库, "asCall" : 框架回调, "params" :o回调参数} = 在新工作中回调
         *      data : 回调唯一标识, 默认=随机标识, 字符串=指定标识
         *     #完成回调(文本) 在工作事务提交后(在父级工作中)按队列顺序执行
         *      code : 固定"done"
         *      info : 回调方法接收参数结构 {"wuid" : 工作ID, "isOk" : true=最终提交 false=最终回滚}
         *          true = 读取指定标识的回调
         *          false = 删除指定标识的回调
         *          框架回调结构 = 不开启工作直接回调, 若报错将影响父级工作执行结果
         *          {"onWork" : 监听数据库, "asCall" : 框架回调, "params" :o回调参数} = 在新工作中回调
         *      data : 回调唯一标识, 默认=随机标识, 字符串=指定标识
         * 返回 :
         *     #开启工作(数组)
         *      失败抛出异常, 成功 {"code" : 200, "info" : "Successful", "data" : []}
         *     #结束工作(布尔)
         *      失败抛出异常
         *     #抛出异常(数字)
         *      抛出工作异常
         *     #捕捉异常(对象)
         *      其它异常继续抛出, 为工作异常返回 {
         *          "code" : 异常状态码
         *          "info" : 提示信息
         *          "data" : 问题数据
         *      }
         *     #获取时间("time")
         *      返回当前工作的开始时间, 未在工作中抛出异常
         *     #操作错误("error")
         *      未在工作中依然生效, 没错误返回null, 有错误返回 {
         *          "code" : 编码,
         *          "info" : 错误,
         *          "file" : 路径,
         *          "line" : 行数,
         *          "uuid" : 标识
         *      }
         *     #全局排除监听("block")
         *      排查的监听列表 {
         *          "数据库连接池" : true
         *          ...
         *      }
         *     #工作信息("info")
         *      不在工作中返回 null
         *      指定项存在, 返回项信息, 单项返回值, 多项返回数组 {
         *              1"wuid"  : 工作ID,
         *              2"list"  : [监听连接池, ...],
         *              4"defer" :&{回调ID : 回调信息, ...}
         *              4"done"  :&{回调ID : 回调信息, ...}
         *          }
         *     #延迟回调("defer")
         *     #完成回调("done")
         *      info为true时, 返回回调信息, 不存在返回null, 不在工作中抛出异常
         * 注明 :
         *      监听栈列表结构($sList) : [{
         *          "wuid"  : 工作ID,
         *          "time"  : [时间戳, 格式化, 时区标识 Europe/London, 格林差异 ±00:00],
         *          "dyna"  : 是否监听新连接池, true=是, false=否
         *          "unify" : 集成工作增量, 0=未开启集成工作, >=1为集成工作增量
         *          "list"  : 监听的连接池 {
         *              数据池 : 被克隆数据池,
         *              ...
         *          },
         *          "defer" : 延迟执行回调 {
         *              回调标识 : 框架回调结构,
         *              ...
         *          },
         *          "done"  : 完成执行回调 {
         *              回调标识 : 框架回调结构,
         *              ...
         *          }
         *      }, ...]
         *      返回的状态码一览表
         *          500 : 发生内部错误(代码报错)
         * 作者 : Edgar.lee
         */
        public static function work($code, $info = '', $data = array()) {
            return of::work('extr', array('code' => &$code, 'info' => &$info, 'data' => &$data, 'trace' => 2));
        }

        /**
         * 描述 : 加载模板页面
         * 参数 :
         *      tpl : 模板名,默认调度方法名.视图扩展名
         *          '/'开头=相对当前视图路径
         *          '_'开头=完整的磁盘目录
         *          其它   =相对视图根目录的调度类结构相同
         * 作者 : Edgar.lee
         */
        public static function display($tpl = null) {
            of_view::display($tpl);
        }

        /**
         * 描述 : 执行sql语句,根据不同语句返回不同结果
         *      sql  : 字符串 = 执行传入的sql
         *            null   = 开启事务,
         *            true   = 提交事务,
         *            false  = 回滚事务
         *      pool : 连接池区分符, 默认=default
         * 返回 :
         *      sql为字符串时 :
         *          查询类,返回二维数组
         *          插入类,返回插入ID
         *          删改类,返回影响行数
         *      sql为其它时 : 成功返回 true, 失败返回 false
         * 作者 : Edgar.lee
         */
        public static function &sql($sql, $key = 'default') {
            return of_db::sql($sql, $key);
        }

        /**
         * 描述 : 通过字符串获取数组深度数据
         * 参数 :
         *      key     : null(默认)=返回 data, 字符串=以"."作为分隔符表示数组深度, 数组=以数组的方式代替传参[key, data, default, extends]
         *     &data    : 被查找的数组
         *      default : null, 没查找到的代替值
         *      extends : 扩展参数, 使用"|"连接多个功能, 0(默认)=不转义, 1=以"`"作为key的转义字符, 2=默认值赋值到原数据
         * 返回 :
         *      返回指定值 或 代替值
         * 作者 : Edgar.lee
         */
        public static function &get($key = null, $default = null) {
            return of::getArrData(array(&$key, &$_GET, &$default));
        }

        /**
         * 描述 : 通过字符串获取数组深度数据
         * 参数 :
         *      key     : null(默认)=返回 data, 字符串=以"."作为分隔符表示数组深度, 数组=以数组的方式代替传参[key, data, default, extends]
         *     &data    : 被查找的数组
         *      default : null, 没查找到的代替值
         *      extends : 扩展参数, 使用"|"连接多个功能, 0(默认)=不转义, 1=以"`"作为key的转义字符, 2=默认值赋值到原数据
         * 返回 :
         *      返回指定值 或 代替值
         * 作者 : Edgar.lee
         */
        public static function &post($key = null, $default = null) {
            return of::getArrData(array(&$key, &$_POST, &$default));
        }

        /**
         * 描述 : 设定cookie
         * 参数 :
         *     &name     : 指定cookie名称
         *     &value    : 指定cookie值,null=删除
         *     &expire   : 过期时间,数字=指定x秒后过期,时间=过期时间,默认关闭浏览器过期
         *     &path     : 有效路径,默认''根路径,null=当前路径
         *     &domain   : 有效域,默认当前域
         *     &secure   : true=只在https下有效,false(默认)=不限制
         *     &httpOnly : 仅能通过http协议访问,如js等禁止访问,false(默认)=不限制,true=限制访问
         * 作者 : Edgar.lee
         */
        public static function cookie($name, $value = null, $expire = null, $path = '', $domain = null, $secure = false, $httpOnly = false) {
            return of_base_link_response::cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        }

        /**
         * 描述 : 响应头信息
         * 参数 :
         *     &code : 数字=指定状态码,字符串=指定头信息
         *     &text : text为字符串=指定头信息,text为布尔=指定是否可替换,text为数字=指定code跳转状态码
         * 作者 : Edgar.lee
         */
        public static function header($code, $text = null) {
            return of_base_link_response::header($code, $text);
        }

        /**
         * 描述 : 输出缓冲控制
         * 参数 :
         *      mode : (false)true=永久缓冲,false=关闭缓冲,null=清除缓冲,字符串=添加缓存内容
         *      pool : (null)null=使用上次级别,字符串=对应缓冲池
         * 返回 :
         *      mode=true              : 保存并返回在服务器中的缓存内容
         *      mode=false             : 保存并返回在服务器中的缓存内容, 同时输出pool缓冲池的内容
         *      mode=字符串            : 保存mode内容并返回在服务器中的缓存内容
         *      mode=null              : 返回并清空缓冲内容
         *      mode=null,pool=false时 : 返回当期状态 {
         *          "mode" : 缓存状态,bool
         *          "pool" : 当前缓存池
         *      }
         * 注明 :
         *      缓存数据($cache)结构 : {
         *          缓冲池名称 : [单次数据, ...], ...
         *      }
         * 作者 : Edgar.lee
         */
        public static function &buffer($mode = true, $pool = null) {
            return of_base_link_response::buffer($mode, $pool);
        }

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
            return of_base_link_request::rule($rule, $exit);
        }

        /**
         * 描述 : 安全的json
         * 参数 :
         *      data : 编码或解码的数据
         *      mode : 位运算操作选项
         *          0=解码
         *              2=解码前去掉反斜杠
         *          1=编码
         *              2=编码后添加反斜杠
         * 返回 :
         *      编码解码后的数据
         * 注明 :
         *      JSON预定义常量 :
         *          1   : JSON_HEX_TAG 所有的 < 和 > 转换成 \u003C 和 \u003E, PHP >= 5.3.0
         *          256 : JSON_UNESCAPED_UNICODE 不使用"\uXXXX"方式, PHP >= 5.4.0
         * 作者 : Edgar.lee
         */
        public static function &json($data, $mode = 1) {
            return of_base_com_data::json($data, $mode);
        }

        /**
         * 描述 : 获取更具唯一性的ID
         * 参数 :
         *      prefix : 编码前缀, 不同前缀并发互不影响, ''=全局32位小写唯一编码, 其它=系统级可排序唯一短编码
         *      isShow : 功能操作,
         *          数字   = 代替minLen参数,
         *          布尔   = 显示前缀, true=显示, false=隐藏
         *          字符串 = 时间结构, 用"\"转义, 默认"ymdHis", 如: "\y\m\dymd-"
         *      minLen : 自增值最小长度, prefix不为空时有效, 默认3
         * 返回 : 
         *      prefix 为假时返回 32位小写字母
         *      prefix 为真时返回 大写prefix + 两位年月日时分秒时间结构 + minLen计数
         * 作者 : Edgar.lee
         */
        public static function uniqid($prefix = '', $isShow = true, $minLen = 3) {
            return of_base_com_str::uniqid($prefix, $isShow, $minLen);
        }

        public static function open($name) {
            return include '';
        }

        /**
         * 描述 : 会话开启与关闭
         * 参数 :
         *      type : true=开启, false=关闭
         * 作者 : Edgar.lee
         */
        public static function session($type = true) {
            of_base_session_base::control($type);
        }
    }

}