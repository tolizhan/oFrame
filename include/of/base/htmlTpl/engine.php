<?php
/**
 * 描述 : html模板引擎,实现UI,开发人员分离
 * 注明 :
 *      配置变量(config) : 配置变量(注释前*代表有默认值修改时要慎重, x代表不能修改) {
 *          "tagKey"  :*默认=['<?', '?>', '<\?(.*)\?>'],注释标签标识符 <!--标识符[0] php代码 标识符[1]-->
 *          "attrPre" :*默认='_',属性的前缀 _value 相当于 value
 *          "funcPre" :*默认='__',功能的前缀 __del 代表删除 当前标签
 *          "path"    :*默认=可写路径/_of/of_base_htmlTpl_engine/compile,编译文件存储的路径
 *          "tplFile" :x当前解析的模板
 *          "tplRoot" :x模板根路径
 *      }
 * 作者 : Edgar.lee
 */
class of_base_htmlTpl_engine {
    //html模板解析引擎
    private static $config = array(
        //注释标签标识符 <!--标识符[0] php代码 标识符[1]-->
        'tagKey'  => array('<?', '?>', '<\?(.*)\?>'),
        //属性的前缀 _value 相当于 value
        'attrPre' => '_',
        //功能的前缀 __del 代表删除 当前标签
        'funcPre' => '__',
    );

    /**
     * 描述 : 初始化
     * 作者 : Edgar.lee
     */
    public static function init() {
        //引用配置文件
        $config = &self::$config;
        $config = of::config('_of.htmlTpl', array()) + $config;

        //初始化注释标识符
        $index = &$config['tagKey'];
        //兼容旧版
        $index = (array)$index + array(1 => '');
        isset($index[2]) || $index[2] = preg_quote($index[0]) . '(.*?)' . preg_quote($index[1]);
        $index[3] = '@^' . $index[2] . '$@s';

        //编译文件存储的路径
        $config['path'] = ROOT_DIR . (
            isset($config['path']) ? $config['path'] : OF_DATA . '/_of/of_base_htmlTpl_engine'
        );

        //添加视图事件
        of::event('of_view::display', 'of_base_htmlTpl_engine::getHtmlTpl');
        //添加模版连接
        of::link('&getHtmlTpl', '$params', 'return of_base_htmlTpl_engine::getHtmlTpl($params);');
    }

    /**
     * 描述 : 视图回调
     * 参数 :
     *      params : 相对 of_view::path() 的路径
     * 作者 : Edgar.lee
     */
    public static function &getHtmlTpl($params) {
        //引用配置文件
        $config = &self::$config;
        //引用模板文件
        is_string($params) ? $tplFile = of::formatPath($params, of_view::path()) : $tplFile = &$params['tplDir'];

        //扩展是html
        if (strtolower(pathinfo($tplFile, PATHINFO_EXTENSION)) === 'html') {
            //模版相对路径
            $tplDir = substr($tplFile, strlen(ROOT_DIR));
            //编译文件路径
            $comFile = $config['path'] . '/compile' . substr($tplDir, 0, -4) . 'php';

            if (
                //编译文件存在
                is_file($comFile) ?
                    //debug && 模板存在 && 模板有变动
                    OF_DEBUG !== false && is_file($tplFile) && filemtime($tplFile) !== filemtime($comFile) :
                    //模板存在
                    is_file($tplFile)
            ) {
                //当前解析的模板
                $config['tplFile'] = $tplFile;
                //模板根路径
                $config['tplRoot'] = dirname($tplDir);
                //解析html文本
                $parseObj = new of_base_com_hParse(file_get_contents($tplFile));

                //触发 模版编译 事件
                of::event('of_base_htmlTpl_engine::compile', true, array(
                    'tplObj' => &$parseObj, 'tplDir' => $tplFile
                ));
                //解析功能符
                self::parseFuncChar($parseObj);
                //解析模板头部
                self::parseTplHead($parseObj);
                //解析模板内容
                self::parseTplBody($parseObj);
                //记录模板路径
                $parseObj->append('<!-- tplDir : ' . $tplDir . ' -->');

                //输出编译文件
                of_base_com_disk::file($comFile, $parseObj->html(false, false));
                //模板修改时间
                touch($comFile, filemtime($tplFile));
            }

            $tplFile = $comFile;
        }

        return $tplFile;
    }

    /**
     * 描述 : 解析功能符
     * 参数 :
     *     &parseObj : 指定解析的对象
     * 作者 : Edgar.lee
     */
    private static function parseFuncChar(&$parseObj) {
        //引用配置文件
        $config = &self::$config;

        //处理__del功能符
        $temp = array($config['funcPre'] . 'del');
        foreach ($parseObj->find('[' . $temp[0] . ']')->eq() as $nodeObj) {
            //删除属性
            if ($temp[1] = $nodeObj->attr($temp[0])) {
                $nodeObj->removeAttr($temp[0]);
                foreach (explode(' ', $temp[1]) as $temp[2]) {
                    $nodeObj->removeAttr($temp[2]);
                }
            //删除节点
            } else {
                $textObj = $nodeObj->next(true);
                $nodeObj->remove();

                //是文本节点
                if ($textObj->attr('tagName') === '!text') {
                    //删除换行符
                    $textObj->attr('', preg_replace('@(?:\n|\r\n|\r)(.*)@s', '\1', $textObj->attr('')));
                }
            }
        }

        //处理__html功能符
        $temp = array($config['funcPre'] . 'html');
        foreach ($parseObj->find('[' . $temp[0] . '][tagName!=title]')->eq() as $nodeObj) {
            $line = $nodeObj->attr('>attrLine::' . $temp[0]);
            //读取__html值
            $temp[1] = $nodeObj->attr($temp[0]);
            //移除__html属性
            $nodeObj->removeAttr($temp[0])
                //脚本化__html值
                ->text('')->contents()->attr('', self::formatAttr($line, $temp[1]));
        }
    }

    /**
     * 描述 : 解析模板头
     * 参数 :
     *     &parseObj : 指定解析的对象
     * 作者 : Edgar.lee
     */
    private static function parseTplHead(&$parseObj) {
        //引用配置文件
        $config = &self::$config;
        //head对象
        $headObj = $parseObj->find('head:eq(0)');
        //添加 of_view::head
        if ($headObj->size()) {
            //打印头数据
            $printHeadArr = null;
            //body对象
            $bodyObj = $headObj->nextAll('body');
            //在第一个节点上加入一个节点
            $beforeNode = $bodyObj->m('</> ')->prependTo($bodyObj);
            //在第二个节点上加入一个节点
            $afterNode = $bodyObj->m('</> ')->insertAfter($beforeNode);

            //处理 head 中的 htmlLoad
            $printHeadArr['head'] = self::htmlLoadParse($headObj, false);

            //过滤节点
            $filterObj = $headObj->find('link, script, style, meta')
                ->add($headObj->contents('!--'));
            foreach ($filterObj->eq() as $nodeObj) {
                //读取标签名
                $tagName = $nodeObj->attr('tagName');
                //获取连接属性名
                $attrName = $nodeObj->attr('tagName') === 'link' ? 'href' : 'src';

                //脚本移动到最先执行
                if ($tagName === '!--') {
                    //是注释脚本
                    if (preg_match($config['tagKey'][3], $nodeObj->attr(''))) {
                        $beforeNode->before($nodeObj);
                    }
                //meta标签 原样输出
                } else if ($tagName === 'meta') {
                    $temp = '"' . addslashes($nodeObj->html(true)) . '"';
                    stripos($temp, 'charset') || $printHeadArr['head'][] = $temp;
                //移动到 body 标签中
                } else if (
                    //是 style 或 非引用js
                    $tagName === 'style' || (
                        $tagName === 'script' &&
                        $nodeObj->attr('src') === null && 
                        $nodeObj->attr($config['attrPre'] . 'src') === null 
                    )
                ) {
                    $afterNode->before("</>\n")->before($nodeObj);
                //样式 和 脚本 转移到 头中
                } else if (
                    (
                        ($temp = $nodeObj->attr($config['attrPre'] . $attrName)) ||
                        $temp = $nodeObj->attr($attrName)
                    ) && $temp = self::formatAttr($nodeObj->attr('>attrLine::' . $attrName), $temp, false)
                ) {
                    $printHeadArr[$tagName][] = "'_' . ( {$temp} )";
                }
            }

            //读取body属性
            $temp = $bodyObj->attr();
            foreach ($temp as $k => &$v) {
                $k[0] === '>' || $printHeadArr['body'][] = $k . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
            }

            //生成 head
            $printHeadArr[''][] = "<!--{$config['tagKey'][0]}\n";

            //分页模版
            if ($bodyObj->find('[method*="::"]')->size()) {
                $printHeadArr[''][] = "of_view::head('head', '<script src=\"' .OF_URL. '/att/com/com/paging/main.js\"></script>');\n";
            }

            //获取标题
            $printHeadArr[''][] = "of_view::head(array(\n    'title' => ";
            $temp = array($headObj->find('title'));
            if ($temp[1] = $temp[0]->attr($config['funcPre'] . 'html')) {
                $printHeadArr[''][] = self::formatAttr($temp[0]->attr('>tagLine::start'), $temp[1], false);
            } else {
                $printHeadArr[''][] = 'L::getText(\'' . addslashes($temp[0]->text()) . '\')';
            }
            $printHeadArr[''][] = ",\n";
            $temp = str_repeat(' ', 8);

            //生成head
            if ($printHeadArr['head']) {
                $printHeadArr[''][] = "    'head'  => array(\n{$temp}";
                $printHeadArr[''][] = join(",\n{$temp}", $printHeadArr['head']);
                $printHeadArr[''][] = "\n    ),\n";
            }

            //生成css
            if (isset($printHeadArr['link'])) {
                $printHeadArr[''][] = "    'css'   => array(\n{$temp}";
                $printHeadArr[''][] = join(",\n{$temp}", $printHeadArr['link']);
                $printHeadArr[''][] = "\n    ),\n";
            }

            //生成js
            if (isset($printHeadArr['script'])) {
                $printHeadArr[''][] = "    'js'    => array(\n{$temp}";
                $printHeadArr[''][] = join(",\n{$temp}", $printHeadArr['script']);
                $printHeadArr[''][] = "\n    ),\n";
            }

            //生成body attr
            if (isset($printHeadArr['body'])) {
                $printHeadArr[''][] = "    'body'  => array(\n{$temp}'";
                $printHeadArr[''][] = join("',\n{$temp}'", $printHeadArr['body']);
                $printHeadArr[''][] = "'\n    ),\n";
            }

            //完成 head
            $printHeadArr[''][] = "));\n{$config['tagKey'][1]}-->\n";
            $beforeNode->after(join($printHeadArr['']))->remove();
            $afterNode->remove();

            //更新解析位置
            $parseObj = $bodyObj;
        }
    }

    /**
     * 描述 : 解析模板内容
     * 参数 :
     *     &parseObj : 指定解析的对象
     * 作者 : Edgar.lee
     */
    private static function parseTplBody(&$parseObj) {
        //引用配置文件
        $config = &self::$config;

        //处理 body 中的 htmlLoad
        self::htmlLoadParse($parseObj, true);

        //路径格式化
        foreach ($parseObj->find('link, img, iframe, script, a')->eq() as $nodeObj) {
            //读取标签名
            $tagName = $nodeObj->attr('tagName');
            //确定属性名
            $attrName = $tagName === 'link' || $tagName === 'a' ? 'href' : 'src';
            //使用模版专用标签
            $tagName === 'script' && $nodeObj->attr('type') === 'tpl' && $nodeObj->removeAttr('type');

            if (
                //不存在属性符
                $nodeObj->attr($config['attrPre'] . $attrName) === null &&
                //有效的属性
                $temp = $nodeObj->attr($attrName)
            ) {
                //属性行数
                $line = $nodeObj->attr('>attrLine::' . $attrName);
                //替代属性
                $nodeObj->attr('', $attrName . '="' . self::formatUrl($line, $temp, true) . '"');
                //移除原始属性
                $nodeObj->removeAttr($attrName);
            }
        }

        //属性前缀长度
        $attrPreLen = strlen($config['attrPre']);
        //替换属性操作
        foreach ($parseObj->find('[@^' .$config['attrPre']. '@]')->get() as $nodeKey) {
            //读取所有属性
            $attrList = of_base_com_hParse::nodeAttr($nodeKey);
            $emptyAttr = empty($attrList['']) ? array() : array($attrList['']);

            foreach ($attrList as $k => &$v) {
                //是替换属性
                if (strncmp($k, $config['attrPre'], $attrPreLen) === 0) {
                    //替换属性的行数
                    $line = isset($attrList[$line = '>attrLine::' . $k]) ? $attrList[$line] : null;
                    //引号模式(单引 双引 或 空字符)
                    $quote = empty($attrList[$quote = '>attrQuote::' . $k]) ? '"' : $attrList[$quote];
                    //原属性值
                    $attrName = substr($k, $attrPreLen);
                    //格式化属性
                    $temp = self::formatAttr($line, $v, true);
                    //解析新属性
                    $emptyAttr[] = $attrName ? "{$attrName}={$quote}{$temp}{$quote}" : $temp;
                    //移除功能属性及元属性
                    of_base_com_hParse::nodeAttr($nodeKey, $attrName, false);
                    of_base_com_hParse::nodeAttr($nodeKey, $k, false);
                }
            }

            //替换全部功能属性
            empty($emptyAttr) || of_base_com_hParse::nodeAttr($nodeKey, '', join(' ', $emptyAttr));
        }

        $checkSyntaxList = array();
        //解析注释中的脚本
        foreach ($parseObj->contents('!--')->eq() as $nodeObj) {
            //是注释脚本
            if (preg_match($config['tagKey'][3], $nodeObj->attr(''), $temp)) {
                //存在脚本
                if (ltrim($temp[1])) {
                    //注释行数
                    $temp = ' #line : ' . $nodeObj->attr('>tagLine::start') . "\n" . $temp[1];
                    //存入语法检测列表
                    $checkSyntaxList[] = $temp;
                    //创建' '字符串
                    $textObj = $nodeObj->m('</> ')
                        //插入节点之后
                        ->insertAfter($nodeObj)
                        //加入php脚本
                        ->attr('', "<?php{$temp}?>")
                        //获取下一任意节点
                        ->next(true);
                } else {
                    //获取下一任意节点
                    $textObj = $nodeObj->next(true);
                }

                //是文本节点
                if ($textObj->attr('tagName') === '!text') {
                    $temp = explode("\n", $textObj->attr(''), 2);
                    //删除相邻的字符串
                    $temp[0] = '';
                    $textObj->attr('', join("\n", $temp));
                }

                //移除注释节点
                $nodeObj->remove();
            }
        }
        //语法检查(异常)
        self::checkSyntax($checkSyntaxList);

        //添加语言包
        foreach ($parseObj->contents('!text')->get() as $nodeKey) {
            //文本父节点键
            $parentKey = of_base_com_hParse::nodeConn($nodeKey, 'parent', 0);
            //父节点标签
            $tagName = of_base_com_hParse::nodeAttr($parentKey, 'tagName');
            //注释行数
            $line = ' #line : ' . of_base_com_hParse::nodeAttr($nodeKey, '>tagLine::start') . "\n";
            $text = of_base_com_hParse::nodeAttr($nodeKey, '');

            //是 js 脚本
            if ($tagName === 'script') {
                //待校验列表
                $checkSyntaxList = array();
                preg_match_all(
                    //匹配<!--_ -->标签
                    '@<!--' . $config['tagKey'][2] . '-->@s',
                    $text, $temp, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
                );

                for ($i = count($temp); --$i >= 0;) {
                    $index = &$temp[$i];
                    $checkSyntaxList[] = $line . $index[1][0];
                    $text = substr_replace($text, "<?php{$line}{$index[1][0]}?>", $index[0][1], strlen($index[0][0]));
                }

                //语法检查(异常)
                self::checkSyntax($checkSyntaxList);
                //属性写回
                of_base_com_hParse::nodeAttr($nodeKey, '', $text);
            //纯文本节点
            } else if (
                $tagName !== 'style' &&
                preg_match('@^(\s*)([^<\s].*?)(\s*)$@s', $text, $temp)
            ) {
                $temp[2] = "<?php{$line} echo L::getText('{$temp[2]}'); ?>\n";
                //修改为php标签
                of_base_com_hParse::nodeAttr($nodeKey, '', $temp[1] . $temp[2]. $temp[3]);
            }
        }
    }

    /**
     * 描述 : 格式化属性操作的代码
     * 参数 :
     *      line    : 属性所在行数
     *      code    : 指定转换的代码
     *      isPrint : true(默认)=返回带打印的代码, false=返回可赋值的代码
     *     &tagName : 标签名,已/开头时使用
     * 返回 :
     *      成功返回格式化的字符串,失败 异常
     * 作者 : Edgar.lee
     */
    private static function &formatAttr($line, &$code, $isPrint = true) {
        //代码备份
        $format = trim($code);

        //是路径格式
        if (preg_match('@^(?:\w+:/)?(?:[\w\-.%]*(?:/[\w\-.%]*|[\w\-.%]+\.\w+|^))+([?#].*)?$@s', $format)) {
            $format = &self::formatUrl($line, $format, $isPrint);
        //以;和}结尾的脚本
        } else if (preg_match('@^.*[;}]\s*$@s', $format)) {
            if ($isPrint) {
                $format = '<?php #line : ' .$line. "\n " . $format . ' ?>';
            } else {
                $temp = substr(self::$config['tplFile'], strlen(ROOT_DIR));
                //无法解析成赋值模式
                throw new Exception("File '{$temp}' unable to parse assignment mode : {$code}");
            }
        //简单运算
        } else {
            $isPrint && $format = '<?php #line : ' .$line. "\n echo " . $format . '; ?>';
        }

        $temp = $isPrint ? '?>' . $format : $format . ';';
        //脚本校验
        self::checkSyntax($temp, ' #line : ' .$line. "\n " . $code);
        return $format;
    }

    /**
     * 描述 : 格式化并编码URL
     * 参数 :
     *      line    : 属性所在行数
     *     &url     : 指定转换的代码
     *      isPrint : true(默认)=返回带打印的代码, false=返回可赋值的代码
     * 返回 :
     *      成功返回格式化的字符串, 失败 异常
     * 作者 : Edgar.lee
     */
    private static function &formatUrl($line, &$url, $isPrint = true) {
        $format = htmlentities($url, ENT_QUOTES, 'UTF-8');
        $line = "#line : {$line}\n";

        //有属性值 && 不是网络路径
        if (isset($format[0]) && !strpos($format, ':')) {
            //非'/'开始的定位到 模板路径 下
            $format[0] === '/' || $format = of_base_com_str::realpath(self::$config['tplRoot'] . '/' . $format);
            $format = $isPrint ? "<?php {$line} echo ROOT_URL; ?>" . $format : $line . ' ROOT_URL . \'' . $format . '\'';
        } else if (!$isPrint) {
            $format = $line . ' \'' .$format. '\'';
        }

        return $format;
    }

    /**
     * 描述 : 解析 htmlLoad 功能
     * 参数 :
     *     &parseObj : 解析对象
     *      isBody   : 是否主体解析
     * 返回 :
     *      [格式化的路径, ...]
     * 作者 : Edgar.lee
     */
    private static function &htmlLoadParse(&$parseObj, $isBody = false) {
        //引用配置文件
        $config = &self::$config;
        //返回结果
        $result = array();

        $temp = array(substr($config['tplRoot'], strlen(of_view::path(null))));
        foreach ($parseObj->find('script[include]')->eq() as $nodeObj) {
            //计算相对路径
            $temp[1] = $temp[0] . '/' . $nodeObj->attr('src') . '/../' . $nodeObj->attr('include');
            $temp[1] = of_base_com_str::realpath(strtr($temp[1], '?', '_'));

            //转换注释标签
            if ($isBody) {
                $nodeObj->after("<!--{$config['tagKey'][0]} include L::getHtmlTpl('{$temp[1]}'); {$config['tagKey'][1]}-->");
            } else {
                $result[] = "'_' . L::getHtmlTpl('{$temp[1]}')";
            }

            $nodeObj->remove();
        }

        return $result;
    }

    /**
     * 描述 : php 语法检查
     * 参数 :
     *     &code : 检测脚本
     *     &tip  : 提示信息,null(默认)=自动提取
     * 返回 :
     *      通过返回 true, 失败 异常
     * 作者 : Edgar.lee
     */
    private static function checkSyntax(&$code, $tip = null) {
        is_array($code) ? $check = join("?>\n<?php", $code) : $check = &$code;

        //语法检查
        if ($check && $check = &of::syntax($check)) {
            $check['file'] = substr(self::$config['tplFile'], strlen(ROOT_DIR));

            //生成提示信息
            if ($tip === null) {
                //脚本行数计数
                $line = array(0, 0);
                //是数组
                if (is_array($code)) {
                    foreach ($code as &$v) {
                        //脚本换行数
                        $line[0] += substr_count($v, "\n") + 1;

                        //在错误范围
                        if ($line[0] >= $check['line']) {
                            //重新定位行
                            $check['line'] -= $line[1];
                            $tip = &$v;
                            break;
                        } else {
                            $line[1] = $line[0];
                        }
                    }
                //是字符串
                } else {
                    $tip = &$code;
                }
            }

            $check['tip'] = explode("\n", $tip);
            //最大值的长度
            $line = strlen(count($check['tip']));
            foreach ($check['tip'] as $k => &$v) {
                $v = str_pad(++$k, $line, '0', STR_PAD_LEFT) . ($check['line'] === $k ? '>>' : '| ') . $v;
            }
            $check['tip'] = join("\n", $check['tip']);
            //未通过检查
            throw new Exception("File '{$check['file']}' {$check['message']} :\n" . $check['tip']);
        } else {
            return true;
        }
    }
}

of_base_htmlTpl_engine::init();