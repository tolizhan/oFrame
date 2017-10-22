<?php
function main() {
    //初始化时调用
}

/**
 * 描述 : 扩展开发演示
 * 作者 : Edgar.lee
 */
function extendDemo($event = null) {
    //触发 "页面运行结束" 事件
    if( $event ) {
        //通过hParse解析页面数据(包括扩展输出的数据)
        $hParse = &$event['parse']('obj');
        $hParse->find('font:eq(0)')->text('扩展会修改这段文字,已修改');                                                 //修改页面内容
        $hParse->find('font:eq(1)')->text('不管在代码什么位置输出,都会在输出到页面的结尾处');                           //修改扩展内容
    //拦截页面调用
    } else {
        //添加 "页面运行结束" 事件
        $this->_addHook('::halt', array($this, __FUNCTION__));
        echo '<br>这段是扩展输出的内容 : <font color=red></font>';       //文字输出
    }
}

/**
 * 描述 : 配置文件中 'main_demo::test' 的入口文件
 * 作者 : Edgar.lee
 */
function test(&$params) {
    echo '<br>扩展的简单演示<pre>';
    //*
    echo '打印调度信息 ';
    print_r($params);
    // */
    //*
    echo '获取常量数据 ';
    print_r($this->_getConst());
    echo '</pre>';
    // */
    /* //生成动态页面地址(一个仅有本扩展使用的地址)
    echo $this->_getExUrl('sd');
    // */
    /* //执行sql演示
    $ePrefix = $this->_getConst('eDbPre');    //当前扩展的数据库前缀
    print_r($this->_sql("SELECT * FROM `{$ePrefix}cc`"));      //使用扩展数据库
    print_r($this->_sql('SELECT "sql演示" test'));             //使用常规数据库
    // */
    /* //加载扩展类演示
    $this->_loadClass('main_loadClassTest')    //加载main_loadClassTest类
         ->callTest();                         //调用callTest方法
    // */
    /* //加载文件演示
    $this->_loadFile('/main/loadFileTest.php', 'dir');
    $this->_loadFile('/main/loadFileTest.php', 'css');    //指定css方式加载
    $this->_loadFile(array(                               //匹配加载
        '/main/loadFileTest.js',
        '/main/loadFileTest.php'
    ));
    // */
    /* //输出控制
    L::buffer();             //返回本扩展之前输出的内容
    echo '测试输出';                //输出一段文本
    $temp = &L::buffer();    //返回'测试输出'
    $temp = '修改输出';             //修改刚刚输出的文本内容
    // */
    /* //钩子演示
    $this->_addHook('ss', array($this, 'callbackPublicHookTest'), array('自定义公有参数'));         //添加公有钩子
    $this->_fireHook('ss', array('触发时公有参数'));                                                //触发公有钩子
    $this->_addHook('_s', array($this, 'callbackPrivateHookTest'), array('自定义私有钩子参数'));    //添加私有钩子
    $this->_fireHook('_s', array('触发时私有钩子参数'));                                            //触发私有钩子
    $this->_removeHook('ss');                                                                       //移除全部钩子
    $this->_removeHook('_s', array($this, 'callbackPrivateHookTest'));                              //移除指定钩子
    // */
    /* //hParse演示
    echo '这段文字不输出<font>这段文字不变色</font><div>将这段文字变成红色</div><!--输出注释中的内容-->';
    $this->_addHook('::halt', array($this, 'callbackHaltHookTest'));                                //添加关闭钩子
    // */
    /* //sharedData演示
    $data = &$this->_shareData();        //读取原始数据 {'test' : '原始数据'}
    $data['test'] = '演示数据';          //修改缓存数据 {'test' : '演示数据'} 本次会话中数据是共享的,但不允许保存
    $this->_shareData();                 //读取缓存数据 {'test' : '演示数据'}
    $data = null;                        //重置缓存数据 null
    $this->_shareData();                 //读取原始数据 {'test' : '原始数据'}
    $data['test'] = '演示数据';          //修改缓存数据 {'test' : '演示数据'}
    $this->_shareData(true);             //加锁原始数据 {'test' : '原始数据'}
    $data['test'] = '测试数据';          //修改缓存数据 {'test' : '测试数据'} 本次会话中数据是共享的,并且允许保存
    $temp = $this->_shareData(false);    //解锁保存数据 {'test' : '测试数据'}
    echo $temp ? '成功' : '失败';        //缓存数据是数组 且 保存成功 ? true : false
    print_r($data);
    // */
    /* //异常及错误演示
    trigger_error('演示扩展错误');          //错误
    throw new Exception('演示扩展异常');    //异常
    // */
}

/**
 * 描述 : 停止钩子回调测试(主要演示hParse文档)
 * 作者 : Edgar.lee
 */
function callbackHaltHookTest($a) {
    $parseObj = &$a['parse']('obj');
    $comment = $parseObj->contents()
        //文档变色
        ->filter('div')->css('color', 'red')
        //删除第一段文本
        ->end()->eq(0)->remove()
        //读取注释
        ->end()->eq(3)->attr('');
    //注释加入节点最后
    $parseObj->doc()->append(
        //强制解析文本
        $parseObj->m($comment, true)
    );
    $parseObj->doc()->find($parseObj);
}

/**
 * 描述 : 回调公有钩子回调测试
 * 作者 : Edgar.lee
 */
function callbackPublicHookTest($a, $b) {
    //触发时公有钩子参数
    print_r($a);
    //自定义公有钩子参数
    print_r($b);
}

/**
 * 描述 : 回调私有钩子回调测试
 * 作者 : Edgar.lee
 */
function callbackPrivateHookTest($a, $b) {
    //触发时私有钩子参数
    print_r($a);
    //自定义私有钩子参数
    print_r($b);
}

/**
 * 描述 : 升级前后触发测试
 * 作者 : Edgar.lee
 */
function updateBeforeOrAfter($param) {
    echo '更新触发 : ';
    var_dump($param);    //{'callMsgFun' : 输出消息, 'nowVersion' : 当前版本(安装时为null), 'newVersion' : 更新后版本, 'position' : 触发位置(before或after), 'state' : 安装状态,可修改停止(before)或改变结果(after)}
}