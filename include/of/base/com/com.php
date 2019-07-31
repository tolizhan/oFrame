<?php
class of_base_com_com {
    /**
     * 描述 : 分页列表
     * 参数 :
     *      config : 字符串=调用共享分页, 数组=配置原始分页
     *      params : config为字符串时, 提供配置参数 {
     *          'params' : 传递给分页的参数, 默认=缺省值
     *          'config' : 替换 原始配置 非顶级 "_attr" 属性, 默认=原始配置
     *      }
     * 返回 :
     *      html分页代码
     * 注明 :
     *      请求数据交互结构($_POST) : {
     *          'method' :*请求方法 类名::方法名
     *          'items'  : 总数据条目数, null=重新查询
     *          'size'   :*每页显示数
     *          'page'   :*当期页数
     *          'sort'   : 排序字段
     *          'params' : 共享参数
     *          'data'   : 响应的二维数据
     *      }
     *      静态页面元素结构     : {
     *          分页结构 : name="pagingBlock" method="请求方法" items="总条数,空为自动查询" size="每页显示数" page="当期页数" params="json共享数据" event="事件回调脚本" save="保存状态"
     *          单条数据 : name="pagingItem" 使用{`字段名`}替换数据,"`"做为转义字符
     *          空无展示 : name="pagingEmpty"
     *          请求提示 : name="pagingWait"
     *          排序按钮 : name="pagingSort" sort="以','分隔的字段名"
     *          首页按钮 : name="pagingFirst"
     *          上一按钮 : name="pagingPrev"
     *          下一按钮 : name="pagingNext"
     *          尾页按钮 : name="pagingLast"
     *          数据总数 : name="pagingCount" 须拥有innerHTML属性
     *          当期位置 : name="pagingPage" 须拥有innerHTML属性
     *          跳转位置 : name="pagingJump" input[type=text]
     *          数据条数 : name="pagingSize" input[type=text]
     *          额外工具 : name="pagingFbar" 默认分页使用
     *      }
     *      原始配置结构(config) : {
     *          列头    : 默认=同 "_attr.body.html" 属性 {
     *              子列头  : 同 列头 结构
     *              "_attr" : 列属性, 默认=null {
     *                  "attr" : 列头属性, 默认=""
     *                  "body" : 列体信息, 默认="", 字符串=同 "html" 属性, 对象={
     *                      "attr" : 列体属性, 默认=""
     *                      "html" : 列体内容, 默认=""
     *                  }
     *                  "html" : 列头内容, 默认="", 字符串=同 "0" 属性, 数组=[列头前内容, 列头后内容]
     *                  "sort" : 排序字段, 默认=不排序, 可以用","分割排序多个
     *                  "row"  : 纵向跨度, 默认=自动计算, table的rowspan功能
     *              }
     *          }
     *          "_atrr" : 全局属性 {
     *              "call"   : "data" 为sql语句时每页回调一次, 默认=不回调
     *              "data"   : 获取数据, 默认=[], 字符串=sql语句{`LIMIT`}可以自定义limt的位置, 数组=单页二维数据
     *              "dbPool" : 数据库连接池, 默认=default
     *              "items"  : 数据长度,
     *                  数字 = 总数据长度,
     *                  sql  = 以`c`字段做为长度,
     *                  -1   = 不计算页数,
     *                  默认 = data是数组时为-1, sql时查询总长
     *              "params" : 终端与服务的共享数据, 默认={}
     *              "size"   : 每页数据量, 默认=_of.com.com::paging.size || 10

     *              **** 以下存在列头时生效
     *              "action" : 翻页动作html, 默认=系统内部生成
     *              "attr"   : 分页属性, 默认="", 字符串=同 "table" 属性, 数组={
     *                  "table" : 整个分页属性
     *                  "btr"   : 分页体 tr 属性
     *              }
     *              "empty"  : 空数据展示界面, 默认="", 字符串=同 "html" 属性, 数组={
     *                  "attr" : 界面属性, 默认=""
     *                  "html" : 界面内容, 默认=""
     *              }
     *              "event"  : 初始化和翻页前后调用的一段js代码, 默认=不调用
     *              "fbar"   : 额外的html功能条, 默认=""
     *              "method" :*通信时位置(__METHOD__), 必填
     *              "page"   : 初始页数, 默认1
     *              "save"   : 保存浏览状态, 默认=不保存, 字符串=区分不同环境的关键词(如 : 用户ID=区分不同用户)
     *              "space"  : 命名空间, 默认="", 可以实现分页嵌套分页功能
     *          }
     *      }
     *      解析过程数据(env)    : {
     *          "maxRow" : 最大深度
     *          "maxCol" : 最大宽度
     *          "parse"  : [{
     *              "name"   : 列头
     *              "parent" : 引用父节点, 顶层为null
     *              "child"  : 原始子节点, 无子为[]
     *              "attr"   : 格式化的属性 {
     *                  "attr" : 列头属性, 结构=""
     *                  "body" : 列体信息, 结构={
     *                      "attr" : ""
     *                      "html" : ""
     *                  }
     *                  "html" : 列头内容, 结构=["", ""]
     *                  "sort" : 排序字段, 结构=""
     *                  "row"  : 纵向跨度, 结构=1
     *                  "col"  :x实际宽度
     *              }
     *              "rpos"   : 计算后的纵向位置
     *          }, ...]
     *          "coor"   : 二维坐标 {
     *              纵向位置 : [对应parse值的引用, ...]
     *          }
     *          "thead"  : 生成thead标签的html
     *          "tbody"  : 生成tbody标签的html
     *          "tfoot"  : 生成tfoot标签的html
     *          "twait"  : 生成等待提示的html
     *          "empty"  : 生成无数据标签的html
     *          "file"   : 调用的文件
     *      }
     * 作者 : Edgar.lee
     */
    public static function &paging($config = null, $params = null) {
        //全局参数 {'attr' : 顶层属性, 'conf' : 替换配置}
        static $global = array('attr' => null, 'conf' => null, 'func' => null);

        //配置分页
        if (is_array($config)) {
            //无列头分页
            isset($config['_attr']) || $config = array('_attr' => $config);
            //头信息
            of_view::head('head', '<script src="' .OF_URL. '/att/com/com/paging/main.js"></script>');

            //修改过使用方法
            isset($global['func']) && $config['_attr']['method'] = $global['func'];
            //顶级属性
            $global['attr'] = &$config['_attr'];
            //引用全局属性
            $attr = &$global['attr'];
            //替换配置是对象 && 使用替换配置
            isset($global['conf']) && $config = &$global['conf'];
            unset($config['_attr']);

            //全局属性初始化
            $attr += array(
                'call'   => null,
                'data'   => array(),
                'dbPool' => 'default',
                'items'  => null,
                'params' => array(),
                'size'   => of::config('_of.com.com::paging.size', 10),

                'action' => null,
                'attr'   => '',
                'empty'  => '',
                'event'  => null,
                'fbar'   => '',
                'page'   => 1,
                'save'   => null,
                'space'  => ''
            );
            //命名空间补全
            $attr['space'] && $attr['space'] .= ':';
            //共享参数去反斜杠
            of::slashesDeep($attr['params'], 'stripslashes');

            //存在列头, 生成分页头
            if (!empty($config)) {
                //method 不存在
                if (empty($attr['method'])) throw new Exception("_attr.method not found");

                //最大深度和宽度
                $env['maxRow'] = $env['maxCol'] = 0;
                //头数据解析
                $env['parse'] = array();

                //部分全局属性初始化
                is_array($attr['attr']) || $attr['attr'] = array('table' => $attr['attr']);
                $attr['attr'] += array('table' => '', 'btr' => '');
                //修改样式属性
                $attr['attr']['table'] = preg_replace(
                    '@(?:^|\s)style\s*=\s*(?:"|\')?@i', '\0visibility: hidden;', $attr['attr']['table'], 1, $index
                );
                //默认隐藏状态
                $index || $attr['attr']['table'] .= ' style="visibility: hidden;"';

                //空数据展示界面
                is_array($attr['empty']) || $attr['empty'] = array('html' => $attr['empty']);
                $attr['empty'] += array('attr' => '', 'html' => '');

                is_string($attr['action']) || $attr['action'] = '<div class="of-paging_action">' .
                    '<a name="' .$attr['space']. 'pagingFirst" class="of-paging_first" href="#">&nbsp;</a>' .
                    '<a name="' .$attr['space']. 'pagingPrev" class="of-paging_prev" href="#">&nbsp;</a>' .
                    '<a name="' .$attr['space']. 'pagingNext" class="of-paging_next" href="#">&nbsp;</a>' .
                    '<a name="' .$attr['space']. 'pagingLast" class="of-paging_last" href="#">&nbsp;</a>' .
                    '<span name="' .$attr['space']. 'pagingCount" class="of-paging_count"></span>' .
                    '<span name="' .$attr['space']. 'pagingPage" class="of-paging_page"></span>' .
                    '<input name="' .$attr['space']. 'pagingJump" class="of-paging_jump" type="text" />' .
                    '<input name="' .$attr['space']. 'pagingSize" class="of-paging_size" type="text" />' .
                '</div>';

                //数据拷贝
                foreach ($config as $k => &$v) {
                    is_array($v) || $v = array('_attr' => array('body' => array('html' => $v)));
                    $env['parse'][] = array('name' => $k, 'child' => &$v, 'parent' => null, 'attr' => &$v['_attr']);
                    //实际宽度
                    $v['_attr']['col'] = 0;
                    unset($v['_attr']);
                }
                //解析头关系
                while (($kp = key($env['parse'])) !== null) {
                    //引用当前节点
                    $index = &$env['parse'][$kp];

                    //属性初始化
                    $index['attr'] += array(
                        'attr' => '',
                        'sort' => '',
                        'html' => '',
                        'row'  => 1,
                        'body' => ''
                    );
                    //列html转数组
                    $index['attr']['html'] = (array)$index['attr']['html'] + array('', '');
                    is_array($index['attr']['body']) || $index['attr']['body'] = array('html' => $index['attr']['body']);
                    //体html转数组
                    $index['attr']['body'] += array('attr' => '', 'html' => '');

                    //实际位置 = 有父 ? 父位置 + 父跨度 : 0
                    $index['rpos'] = $index['parent'] ?
                        $index['parent']['rpos'] + $index['parent']['attr']['row'] : 0;
                    //更新最大深度
                    ($temp = $index['rpos'] + $index['attr']['row']) > $env['maxRow'] && $env['maxRow'] = $temp;
                    //生成坐标
                    $env['coor'][$index['rpos']][] = &$index;

                    //最深节点
                    if (empty($index['child'])) {
                        $env['tbody'][] = "<td {$index['attr']['body']['attr']}>{$index['attr']['body']['html']}</td>";
                        //最大宽度+1
                        $env['maxCol'] += 1;
                        do {
                            //递归到父节点更新宽度
                            $index['attr']['col'] += 1;
                        } while ($index = &$index['parent']);
                    //存在子节点
                    } else {
                        //引用子节点
                        $temp = array();
                        foreach ($index['child'] as $k => &$v) {
                            is_array($v) || $v = array('_attr' => array('body' => array('html' => $v)));
                            $temp[] = array('name' => $k, 'child' => &$v, 'parent' => &$index, 'attr' => &$v['_attr']);
                            //临时实际宽度
                            $v['_attr']['col'] = 0;
                            unset($v['_attr']);
                        }
                        array_splice($env['parse'], 1, $kp = 0, $temp);
                    }

                    //移除分析后的节点
                    unset($env['parse'][$kp]);
                }

                //调用文件地址
                $temp = explode('::', $attr['method'], 2);
                preg_match_all('@[^:_]+@', $temp[0], $temp);
                $env['flie'] = ROOT_DIR . '/' . join('/', $temp[0]) . '.php';

                foreach ($env['coor'] as $kc => &$vc) {
                    //开始换行
                    $env['thead'][] = '<tr>';
                    foreach ($vc as &$v) {
                        //无子 && 跨度 = 最大深度 - 实际位置
                        empty($v['child']) && $v['attr']['row'] = $env['maxRow'] - $kc;
                        $temp = $v['attr']['sort'] ? "name='{$attr['space']}pagingSort' sort='{$v['attr']['sort']}' class='of-paging_sort_def'" : '';
                        $env['thead'][] = "<th rowspan='{$v['attr']['row']}' colspan='{$v['attr']['col']}' {$v['attr']['attr']}>" .
                            "<font {$temp}>" .
                                $v['attr']['html'][0] .
                                L::getText($v['name'], array('key'  =>'pageTable', 'file' => &$env['flie'])) .
                                $v['attr']['html'][1] .
                            '</font>' .
                        "</th>";
                    }
                    //结束换行
                    $env['thead'][] = '</tr>';
                }

                //完成 thead html
                $env['thead'] = join($env['thead']);
                //完成 tbody html
                $env['tbody'] = join($env['tbody']);
                $env['tbody'] = "<tr name='{$attr['space']}pagingItem' {$attr['attr']['btr']} class='of-paging_item_odd'>" . $env['tbody'] . '</tr>' .
                    "<tr name='{$attr['space']}pagingItem' {$attr['attr']['btr']} class='of-paging_item_even'>" . $env['tbody'] . '</tr>';
                $env['tfoot'] = $attr['fbar'] ? "<div name='{$attr['space']}pagingFbar' class='of-paging_fbar_bro'><div class='of-paging_fbar_con'>" .
                    $attr['fbar'] . '</div><label class="of-paging_fbar_ico"></label></div>' : '';
                //完成 tfoot html
                $env['tfoot'] = "<tr><td colspan='{$env['maxCol']}'>{$env['tfoot']}{$attr['action']}</td></tr>";
                //完成 等待 html
                $env['twait'] = "<tr><td colspan='{$env['maxCol']}'></td></tr>";
                //完成 无数据 html
                $env['empty'] = "<tr {$attr['empty']['attr']}>" .
                    "<td colspan='{$env['maxCol']}'>{$attr['empty']['html']}</td>" .
                '</tr>';

                //总条数
                $temp = 'items="' .(is_numeric($attr['items']) ? $attr['items'] : ''). '"' .
                    //每页显示数, 当期页数
                    ' size="' .$attr['size']. '" page="' .$attr['page']. '"' .
                    //共享参数
                    ' params="' .htmlentities(json_encode($attr['params']), ENT_QUOTES, 'UTF-8'). '"' .
                    //回调脚本
                    ' event="' .htmlentities($attr['event'], ENT_QUOTES, 'UTF-8'). '"' .
                    //保存状态
                    ' save="' .htmlentities($attr['save'], ENT_QUOTES, 'UTF-8'). '"';
                $env['thtml'] = "<table name='{$attr['space']}pagingBlock' method='{$attr['method']}' {$temp} {$attr['attr']['table']} class='of-paging_block'>" .
                    //等待提示
                    "<thead class='of-paging_wait' name='{$attr['space']}pagingWait'>{$env['twait']}</thead>" .
                    //分页列头
                    "<thead class='of-paging_head'>{$env['thead']}</thead>" .
                    //分页数据
                    "<tbody class='of-paging_body'>{$env['tbody']}</tbody>" .
                    //空数据提示
                    "<tbody class='of-paging_empty' name='{$attr['space']}pagingEmpty'>{$env['empty']}</tbody>" .
                    //分页尾数据
                    "<tfoot class='of-paging_foot'>{$env['tfoot']}</tfoot>" .
                '</table>';
            }

            return $env['thtml'];
        //调用分页, 字符串=直调, null=POST
        } else {
            //POST 请求
            if ($config === null) {
                //复制post数据
                $post = $_POST;
                $config = $post['method'] = stripslashes($post['method']);
                //不生成节点
                $params['config'] = array();
                if (isset($post['params'][0])) {
                    //共享参数转成json
                    $params['params'] = json_decode(stripslashes($post['params']), true);
                    //防注入
                    of::slashesDeep($params['params']);
                }
            }

            if (
                //框架内部分页
                preg_match('@^of_\w+::\w+Paging$@', $config) || (
                    //权向验证读取
                    ($temp = of::config('_of.com.com::paging.check', '@paging$@i')) &&
                    //是数组 ? 回调验证 : 正则验证
                    (isset($temp[0]) && $temp[0] === '@' ? preg_match($temp, $config) : of::callFunc($temp, $config))
                )
            ) {
                //重写配置
                isset($params['config']) && $global['conf'] = &$params['config'];
                //使用方法
                $global['func'] = $config;

                $temp = explode('::', $config);
                $result = call_user_func_array(
                    array(new $temp[0], $temp[1]),
                    isset($params['params']) ? array(&$params['params']) : array()
                );

                //开始查询数据
                if (isset($post)) {
                    //引用属性
                    $attr = &$global['attr'];

                    //更新共享参数
                    $post['params'] = $attr['params'];
                    //当期页初始化
                    $post['page'] < 1 && $post['page'] = 1;
                    //单页数初始化
                    $post['size'] < 1 && $post['size'] = $attr['size'];
                    //重新计算分页
                    if (empty($post['items'])) {
                        //数字格式
                        if (is_numeric($attr['items'])) {
                            $post['items'] = $attr['items'] . '';
                        //sql语句
                        } else if (is_string($attr['items'])) {
                            $post['items'] = of_db::sql($attr['items'], $attr['dbPool']);
                            //获取总数量
                            $post['items'] = $post['items'][0]['c'];
                        //数组数据
                        } else if (is_array($attr['data'])) {
                            $post['items'] = '-1';
                        }
                    }

                    //执行sql读取数据
                    if (is_string($attr['data'])) {
                        $temp = array(
                            //关键词位置
                            'pos'    => false,
                            //偏移位置
                            'offset' => 0,
                            //定位关键词
                            'keys'   => array('"' => true, '\'' => true),
                            //匹配信息
                            'match'  => null
                        );
                        //定位 {`LIMIT`} 关键词
                        while (true) {
                            if ($temp['match'] = of_base_com_str::strArrPos($attr['data'], $temp['keys'], $temp['offset'])) {
                                if (
                                    ($temp['pos'] = strpos(
                                        substr($attr['data'], $temp['offset'], $temp['match']['position'] - $temp['offset']),
                                        '{`LIMIT`}'
                                    )) !== false
                                //找到LIMIT
                                ) {
                                    $temp['pos'] += $temp['offset'];
                                    break;
                                }

                                $temp['match'] = of_base_com_str::strArrPos($attr['data'], array($temp['match']['match'] => true), $temp['match']['position'] + 1);
                                if ($temp['match']) {
                                    $temp['offset'] = $temp['match']['position'] + 1;
                                //语法错误
                                } else {
                                    break;
                                }
                            //没找到关键词
                            } else {
                                $temp['pos'] = strpos(substr($attr['data'], $temp['offset']), '{`LIMIT`}');
                                //没找到LIMIT || 真实位置
                                $temp['pos'] === false || $temp['pos'] += $temp['offset'];
                                break;
                            }
                        }

                        $temp['pos'] || $temp['pos'] = strlen($attr['data']);
                        //$temp=LIMIT左[1]右[2]分割
                        $temp[1] = substr($attr['data'], 0, $temp['pos']);
                        preg_match("@^(?:|.{9}(.*))()$@s", substr($attr['data'], $temp['pos']), $temp[2]);
                        $temp[2] = &$temp[2][1];
                        //提取内置排序
                        if (preg_match('@ORDER\s+BY\s+([^()]+)$@i', $temp[1], $temp[3], PREG_OFFSET_CAPTURE)) {
                            $temp[1] = substr($temp[1], 0, $temp[3][0][1]);
                            $post['sort'] = empty($post['sort']) ? $temp[3][1][0] : "{$post['sort']}, {$temp[3][1][0]}";
                        }

                        //生成查询总长度SQL
                        if (empty($post['items'])) {
                            //SELECT后的位置
                            $temp['sLen'] = stripos($temp[1], 'SELECT') + 6;
                            //MYSQL 50700 以上版本 不能使用 UNION ALL方法
                            $post['items'] = '/*CALL*/' .
                                substr($temp[1], 0, $temp['sLen']) .
                                ' SQL_CALC_FOUND_ROWS ' .
                                substr($temp[1]. $temp[2], $temp['sLen']) .
                                ' LIMIT 0; SELECT FOUND_ROWS() c';

                            //提取SQL长度
                            $post['items'] = of_db::sql($post['items'], $attr['dbPool']);
                            //获取总数量
                            $post['items'] = $post['items'][1][0]['c'];
                        }

                        //校正 $post['page']
                        if ($post['items'] > -1) {
                            //总页数 < 当期页数 && 当前页数 = 总页数
                            ($temp[0] = ceil($post['items'] / $post['size'])) < $post['page'] &&
                                //纠正page < 1, ceil 返回 float
                                $post['page'] = $temp[0] ? $temp[0] : 1;
                        }

                        //存在排序
                        empty($post['sort']) || $temp[$temp[2] ? 2 : 1] .= ' ORDER BY ' . $post['sort'];
                        $temp = "{$temp[1]} LIMIT " . ($post['page'] - 1) * $post['size'] . ", {$post['size']}{$temp[2]}";
                        //查询data数据
                        $attr['data'] = of_db::sql($temp, $attr['dbPool']);
                    //校正 $post['page']
                    } else if ($post['items'] > -1) {
                        //总页数 < 当期页数 && 当前页数 = 总页数
                        ($temp = ceil($post['items'] / $post['size'])) < $post['page'] && $post['page'] = $temp;
                    }

                    //数据回调
                    $attr['call'] && of::callFunc($attr['call'], array(
                        'data' => &$attr['data'], 'attr' => &$post
                    ));
                    //响应数据
                    $post['data'] = &$attr['data'];
                    echo of_base_com_data::json($post);
                }

                //重置全局配置
                unset($global['attr'], $global['conf']);
            //无权限调用
            } else {
                throw new Exception('"_of.com.com::paging.check" Permission denied');
            }

            return $result;
        }
    }

    /**
     * 描述 : 获取验证码
     * 参数 :
     *      captcha       : 需要对比验证码
     *      key           : 指定验证key,多验证码使用,调用时使用get方式传入key
     *      _GET[key]     : 指定验证的key,如 key=hh
     *      _GET[width]   : 指定宽度,如 width=60
     *      _GET[height]  : 指定高度,如 height=20
     *      _GET[bgColor] : 指定背景色,如 bgColor = FFFFFF 白色背景
     * 返回 :
     *      不传入captcha时,返回img图片,否则正确返回true,错误返回false
     * 演示 :
     *      captcha();
     *      获得默认长宽的验证码
     *      captcha('1234');
     *      判断验证码是否为1234，如果是返回true,否则返回false
     *      captcha('1234', 'm');
     *      判断key为'm'的验证码是否为1234，如果是返回true,否则返回false
     * 作者 : Edgar.lee
     */
    public static function captcha($captcha = false, $key = 0) {
        $result = &$_SESSION['_of']['of_base_com_com']['captcha'];

        //验证码校验
        if ($captcha !== false) {
            if (isset($result[$key]) && $result[$key] === strtoupper($captcha)) {
                unset($result[$key]);
                return true;
            } else {
                unset($result[$key]);
                return false;
            }
        //生成验证码图片
        } else {
            header("Content-type: image/png");
            session_id() || session_open();

            //图像宽度
            $width = isset($_GET['width']) ? $_GET['width'] : 60;
            //图像高度
            $height = isset($_GET['height']) ? $_GET['height'] : 20;
            //字体位置
            $fontPos = floor($height * 3 / 4);
            //字体大小
            $fontSize = floor($fontPos * 0.8);
            //字体宽度
            $fontWidth = floor($width / 5);
            //字体左边
            $fontLeft = $fontWidth >> 1;
            //制定图片背景大小
            $im = imagecreate($width, $height);

            //指定背景色
            if (isset($_GET['bgColor'])) {
                preg_match_all('/[\da-z]{2}/i', $_GET['bgColor'], $bgColor, PREG_PATTERN_ORDER);
                $bgColor = &$bgColor[0];
                //背景色
                $bgColor = ImageColorAllocate($im, hexdec($bgColor[0]), hexdec($bgColor[1]), hexdec($bgColor[2]));
                //填充背景色
                imagefill($im, 0, 0, $bgColor);
            } else {
                //透明背景色
                imagecolortransparent($im, ImageColorAllocate($im, 255, 255, 255));
            }

            //初始化SESSION[_captcha]
            if (isset($_GET['key'])) {
                $key = $_GET['key'];
            }
            $result[$key] = '';

            //生成四个字母
            for ($i = 0; $i < 4; ++$i) {
                //imagestring($im, rand(2, 5), 10 * $i, rand(0,5), $char, ImageColorAllocate($im, rand(0,255), 0, rand(0,255)));
                //生成大写字母
                $char = chr(rand(65, 90));

                imagettftext(
                    $im, $fontSize, rand(-30, 30), $fontWidth * $i + $fontLeft, $fontPos, ImageColorAllocate($im, 0, 0, 0),
                    OF_DIR . '/accy/com/com/captcha/' . rand(1, 4) . '.ttf', $char
                );
                $result[$key] .= $char;
            }

            /* 加入干扰象素
            for($i = 0, $j = $width * $height * 0.2; $i < $j; ++$i) {
                $randcolor = ImageColorallocate(
                    $im, rand(0, 255), rand(0, 255), rand(0, 255)
                );
                imagesetpixel($im, rand() % $width, rand() % $height, $randcolor);
            } // */

            //发送到浏览器
            imagepng($im);
            //销毁图像
            imagedestroy($im);
        }
    }

    /**
     * 描述 : 批量将text文本转换成html格式
     * 参数 :
     *      &textList     : 指定转换的文本或数组
     *       excludeArr   : 在textList为数组模式下指定排除项,默认null
     *       encodeKey    : 是否编码数组Key,默认false
     *       stripslashes : 是否预先使用stripslashes去掉反斜杠,默认true
     * 作者 : Edgar.lee
     */
    public static function textToHtml(&$textList, $excludeArr = null, $encodeKey = false, $stripslashes = true) {
        if (is_array($textList)) {
            $newTextList = array();
            foreach ($textList as $k => &$v) {
                //下一次递归限制变量
                $nextExcludeArr = null;
                //根据$stripslashes的值得到的新键值
                $newK = $stripslashes ? stripslashes($k) : $k;
                if (
                    !is_array($excludeArr) ||
                    !isset($excludeArr[$k]) || (
                        is_array($nextExcludeArr = &$excludeArr[$k]) &&
                        count($excludeArr[$k])
                    )
                ) {
                    if ($encodeKey) {
                        $newTextList[strtr(htmlspecialchars($newK, ENT_QUOTES, 'UTF-8'), array('\\' => '&#92;'))] = &$v;
                    } else {
                        $newTextList[$newK] = &$v;
                    }
                    self::textToHtml($v, $nextExcludeArr, $encodeKey, $stripslashes);
                } else {
                    $newTextList[$newK] = &$v;
                }
            }
            $textList = $newTextList;
        } elseif (is_string($textList)) {
            $textList = strtr(htmlspecialchars(
                $stripslashes ? stripslashes($textList) : $textList,
                ENT_QUOTES,
                'UTF-8'
            ), array('\\' => '&#92;'));
        }
    }

    /**
     * 描述 : 数据缓存读写
     * 参数 :
     *      key   : 读写缓存池的标识,建议使用(类名:方法名:自定义值)来确保缓存唯一性
     *      where : 判断过期条件,数组字符串均可(所有比对使用'等于'方式),默认null,当写时(value !== null)存储where,当读时(value === null)与写时的where对比,如果相等,则返回存储的值,否则注销原值返回null
     *      value : 被缓存的数据
     * 返回 :
     *      一切无效返回null,否则返回存储值(所有返回值均为引用型)
     * 演示 :
     *      $temp = &cache('demo_ofControllers::cache', array('key' => true), 'bb');
     *      缓存字符串'bb',此时$temp === 'bb'
     *      echo cache('demo_ofControllers::cache', array('key' => false)) === null;
     *      输出true
     *      echo cache('demo_ofControllers::cache', array('key' => 1 < 2), 'bb');
     *      输出'bb'
     * 作者 : Edgar.lee
     */
    public static function &cache($key, $where = null, $value = null) {
        //引用缓存池
        $cachePool = &$_SESSION['_of']['of_base_com_com']['cache'];

        //读取缓存
        if ($value === null && isset($cachePool[$key]) && $cachePool[$key]['where'] == $where) {
            return $cachePool[$key]['value'];
        //写入缓存
        } else {
            $cachePool[$key] = array(
                'where' => $where,
                'value' => &$value
            );
        }
        return $value;
    }

    /**
     * 描述 : 分组查询SQL, 将返回值复制 false 会重置快照
     * 参数 :
     *     &sql  : 查询的sql语句
     *     &data : 接受查询的结果集
     *      key  : 连接键
     *      size : 分页量
     * 返回 :
     *      true=查询成功,false=无数据
     * 作者 : Edgar.lee
     */
    public static function &eachSql(&$sql, &$data, $key = 'default', $size = 1000) {
        static $snapshot = null;
        $index = &$snapshot[$key][$sql];

        //快照无效 && 重置记录
        empty($index['state']) && $index['page'] = 1;
        //每页条数
        $limt = ' LIMIT ' . ($index['page']++ - 1) * $size . ',' . $size;
        //读取数据
        $data = of_db::sql($sql . $limt, $key);
        //无数据时快照过期
        if (!$index['state'] = isset($data[0])) unset($snapshot[$key][$sql]);

        return $index['state'];
    }

    /**
     * 描述 : 数组按深层属性值排序
     * 参数 :
     *     &data : 需排序的数组
     *      sort : 排序规则参数
     *          type未空时, 按照sort键在data定位的值排序 {
     *              定位属性值'.'为分层'`'为转义符 : 去SORT_的array_multisort排序常量, 默认"ASC,REGULAR",
     *              ...
     *          }
     *          type不为时, 按照data键对应sort键的值排序 {
     *              data键名 : 被排序数据
     *          }
     *      type : 控制sort排序方式
     *          ""(默认)=按照sort键在data定位的值排序
     *          去SORT_的array_multisort排序常量组合=按照data键对应sort键的值排序
     * 演示 :
     *      $data[] = array('volume' => 67, 'volu.me1' => 67, 'edition' => 2 );
     *      $data['a'] = array('volume' => 86, 'volu.me1' => 85, 'edition' => 86);
     *      $data[] = array('volume' => 85, 'volu.me1' => 86, 'edition' => 6 );
     *      $data[] = array('volume' => 98, 'volu.me1' => 98, 'edition' => 6 );
     *      $data[] = array('volume' => 86, 'volu.me1' => 86, 'edition' => 98);
     *      $data['m'] = array('volume' => 67, 'volu.me1' => 67, 'edition' => 0 );
     *      此时$data键值为[0,a,1,2,3,m]
     *      arraySort($data, array("volu`.me1" => 'ASC,REGULAR', 'edition' => 'DESC'));
     *      此时$data键值为[0,m,a,3,1,2]
     *      arraySort($data, array('0' => 1, 'a' => 2, '2' => 3, '1' => 7, 'm' => 5, '3' => 6), 'asc,NUMERIC');
     *      此时$data键值为[1,3,m,2,a,0]
     * 作者 : Edgar.lee
     */
    public function arraySort(&$data, $sort, $type = '') {
        //执行参数列表
        $argv = array();
        //排序数据列表
        $list = array();

        //格式化数据键值对照(否则排序后数字键会被重置)
        foreach ($data as $k => &$v) {
            $list['_' . $k] = &$v;
        }

        //按照data键对应sort键的值排序
        if ($type) {
            //格式化键值对照
            $temp = array();
            foreach ($data as $k => &$v) {
                $temp[] = &$sort[$k];
            }

            //添加到执行参数表
            $argv[] = $temp;
            array_splice($argv, 1, 0, array_map(
                'constant',
                //转成大写->添加"SORT_"前缀->切成数组->读取对应常量
                explode(',', 'SORT_' . str_replace(',', ',SORT_', strtoupper($type)))
            ));
        //按照sort键在data定位的值排序
        } else {
            foreach ($sort as $ks => &$vs) {
                //提取data对应定位的值
                $temp = array();
                foreach ($data as &$vd) {
                    $temp[] = &of::getArrData($ks, $vd, null, 1);
                }

                //添加到执行参数表
                $argv[] = $temp;
                array_splice($argv, count($argv), 0, array_map(
                    'constant',
                    //转成大写->添加"SORT_"前缀->切成数组->读取对应常量
                    explode(',', 'SORT_' . str_replace(',', ',SORT_', strtoupper($vs)))
                ));
            }
        }

        //开始排序
        $argv[] = &$list;
        call_user_func_array('array_multisort', $argv);

        //恢复数据键值对照
        $data = array();
        foreach ($list as $k => &$v) {
            $data[substr($k, 1)] = &$v;
        }
    }
}
return true;