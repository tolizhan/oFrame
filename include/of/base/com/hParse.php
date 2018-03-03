<?php
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
class of_base_com_hParse {
    //解析节点 { 节点键 : { 默认节点的结构 }, ... }
    private static $parseNode = array();

    //节点计数变量
    private static $nodeCount = 0;

    //默认节点
    private static $defaultNode = array(
        'attr'     => array(),      //按分析顺序的属性,{属性名 : 属性值, ...}
        'cKeys'    => array(),      //按顺序的子节点键,[节点键, 节点键, ...]
        'nodeType' => 'node',       //节点类型,node=普通节点,fragment=碎片节点
        'pKey'     => null,         //父节点键
        'tagName'  => null,         //节点的标签名,为字符串时='!text',nodeType为fragment时='#fragment'
        'refcount' => 0             //对象引用计数器
    );

    //默认属性分隔符
    private static $defaultAttrSplit = array(' ' => false, '/' => false, '>' => false);

    //块级标签
    private static $blockTag = array('p' => 1, 'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1, 'div' => 1, 'ul' => 1, 'ol' => 1, 'dl' => 1, 'menu' => 1, 'dir' => 1, 'pre' => 1, 'hr' => 1, 'blockquote' => 1, 'address' => 1, 'center' => 1, 'noframes' => 1, 'isindex' => 1, 'fieldset' => 1, 'table' => 1);

    //无子节点标签(1=单标签, 2=仅包含文本, 4=包含注释及文本)
    private static $notChrTag = array(
        'script' => 2 /*脚本标签*/, 'noscript' => 2 /*代替脚本标签*/, 'noframes' => 2 /*代替框架标签*/, 'style' => 2 /*样式标签*/, 'textarea' => 2 /*文本域标签*/, 'title' => 2 /*标题标签*/,  'option' => 4/*列表选项(不包含非注释的html标签)*/,
        '!text' => 1 /*字符串标签*/, '!doctype' => 1 /*文档类型标签*/, '!--' => 1 /*注释标签*/, 'base' => 1, 'meta' => 1, 'link' => 1, 'hr' => 1, 'br' => 1, 'basefont' => 1, 'param' => 1, 'img' => 1, 'area' => 1, 'input' => 1, 'isindex' => 1, 'col' => 1
    );

    //不重复标签
    private static $noRepeatTag = array( 'a' => array(), 'li' => array('ul' => 1, 'ol' => 1), 'option' => array('select' => 1) );

    //选择器所用的常量
    private static $selectorsConst = array(
        //属性匹配类型
        'aValueType'     => array('!' => 1 /*不等于指定值*/, '~' => 1 /*空格分割指定值*/, '*' => 1 /*包含指定值*/, '^' => 1 /*以指定值开始*/, '$' => 1 /*以指定值结尾*/, '|' => 1 /*以指定前缀*/),
        //默认关键词匹配项
        'dMatches'       => array('>' => false, '+' => false, '~' => false, ',' => false, '#' => false, '.' => false, ':' => false, '[' => false),
        //需要判定空字符为分隔符的字符
        'emptyCharSplit' => array(
            'pre' => array('>' => false, '+' => false, '~' => false),                    //前缀Prefix
            'suf' => array('#' => false, '.' => false, ':' => false, '[' => false),      //后缀Suffix
        ),
        //需要分组的伪类
        'groupPseudo'    => array('nth-child' => 1, 'eq' => 1, 'gt' => 1, 'lt' => 1, 'first' => 1, 'first-child' => 1, 'last' => 1, 'last-child' => 1, 'even' => 1, 'odd' => 1, 'not' => 1),
        //伪类匹配
        'pMatches'       => array('>' => false, '+' => false, '~' => false, ',' => false, '#' => false, '.' => false, ':' => false, '[' => false, ' ' => false, '(' => false)
    );

    //实体与静态之间的共享数据
    private static $sharedData = array(
        'newInitData' => null,    //实例化时初始数据
    );

    //默认实体属性
    private static $defaultObjAttr = array(
        'callerObj'  => null,      //调用当前实体的实体
        'docNodeKey' => null,      //所属文档节点键
        'nodeList'   => array()    //节点列表 [节点键, ...]
    );

    //实体属性(默认实体属性结构)
    private $objAttr = null;

    /**
     * 描述 : 初始化实体
     * 参数 :
     *      arg : 字符串=解析html,数组=实体赋值
     * 作者 : Edgar.lee
     */
    public function __construct($arg = '') {
        $sharedData = &self::$sharedData;
        $parseNode = &self::$parseNode;

        //使用内部赋值
        if (isset($sharedData['newInitData'])) {
            $this->objAttr = $sharedData['newInitData'];
            $sharedData['newInitData'] = null;

            //更新引用计数器
            foreach ($this->objAttr['nodeList'] as &$v) {
                $parseNode[$v]['refcount'] += 1;
            }
        //字符串:解析html
        } else {
            $this->objAttr = self::$defaultObjAttr;
            //所属文档节点键 = 分析根节点
            $this->objAttr['docNodeKey'] = $this->objAttr['nodeList'][] = self::htmlParse($arg);
            $parseNode[$this->objAttr['docNodeKey']]['refcount'] += 1;
        }
    }

    /**                                                                                     操作区
     * 描述 : 多功能生成器
     * 参数 :
     *      context : 对象=克隆一个新的对象(使用clone关键词),第一位为'<'的字符串=解析html,其他字符串=选择器
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回一个对象
     * 作者 : Edgar.lee
     */
    public function m($context, $rootObj = null) {
        return $this->multiFunction($context, $rootObj, null);
    }

    /**
     * 描述 : 为每个匹配的元素添加指定的类名
     * 参数 :
     *      className : 为每个匹配元素所要增加的一个或多个样式名
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function addClass($className) {
        $className = array_flip(explode(' ', preg_replace('/\s+/', ' ', $className)));
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            $temp = array_flip(explode(' ', self::nodeAttr($nodeKey, 'class')));
            $temp += array_diff_key($className, $temp);
            unset($temp['']);
            self::nodeAttr($nodeKey, 'class', join(' ', array_keys($temp)));
        }
        return $this;
    }

    /**
     * 描述 : 检查匹配的元素是否含有某个特定的类
     * 参数 :
     *      className : 检查的样式名,多个样式用空格分割
     * 返回 :
     *      单个节点包含全部样式返回true,否则false
     * 作者 : Edgar.lee
     */
    public function hasClass($className) {
        $className = explode(' ', preg_replace('/\s+/', ' ', $className));
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            $temp = array_flip(explode(' ', self::nodeAttr($nodeKey, 'class')));
            foreach ($className as &$v) {
                if (!isset($temp[$v])) {
                    $temp = false;
                    break;
                }
            }

            if ($temp !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 描述 : 读取或设置匹配节点的innerHTML值
     * 参数 :
     *      text : false(默认)=获取第一个元素中innerHTML内容, true=获取第一个元素中outerHTML内容, 字符串=设置每一个匹配元素的html内容
     *      mode : text为bool时有效, true=补全未闭合标签, false=按原解析方式
     * 返回 :
     *      读取时返回字符串
     *      设置时返回当前对象
     * 作者 : Edgar.lee
     */
    public function html($text = false, $mode = true) {
        if (is_string($text)) {
            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                self::nodeAttr($nodeKey, 'innerHTML', $text);
            }
            return $this;
        } else if (isset($this->objAttr['nodeList'][0])) {
            return self::nodeAttr($this->objAttr['nodeList'][0], $text ? 'outerHTML' : 'innerHTML', null, $mode);
        }
    }

    /**
     * 描述 : 得到匹配元素集合中每个元素的文本内容结合,包括他们的后代
     * 参数 :
     *      str : true=返回包含每个字符串节点对象的数组,false=返回包含字符串内容的数组,null(默认)=从匹配的元素中获取文本内容,字符串=设置每一个匹配元素的文本内容
     * 返回 :
     *      读取时返回字符串
     *      设置时返回当前对象
     * 作者 : Edgar.lee
     */
    public function text($str = null) {
        //设置
        if (is_string($str)) {
            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                self::nodeAttr($nodeKey, 'textContent', $str);
            }
            return $this;
        } else {
            $rData = $temp = array();
            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                self::htmlFormat($nodeKey, $temp, '!text', false);
            }

            //返回包含每个字符串对象的数组
            if ($str === true) {
                foreach ($temp as $k => &$v) {
                    self::$sharedData['newInitData'] = array(
                        //调用当前实体的实体
                        'callerObj'  => $this,
                        //所属文档节点键
                        'docNodeKey' => $this->objAttr['docNodeKey'],
                        //节点列表 [节点键, ...]
                        'nodeList'   => array($k)
                    );
                    $rData[] = new self;
                }
            //将字符串合并
            } else if ($str === null) {
                $rData = self::entities(join($temp), true);
            //false,返回包含字符串内容的数组
            } else {
                foreach ($temp as $k => &$v) {
                    $rData[] = self::entities($v, true);
                }
            }

            return $rData;
        }
    }

    /**
     * 描述 : 为指定元素设置一个或多个属性
     * 参数 :
     *      attr  : 字符串=读取或设置属性名,null=读取全部真实属性,数组=同时修改多个属性{属性名:属性值}
     *      value : attr为字符串时使用,对应属性值
     * 返回 :
     *      设置时返回当前对象
     *      读取时返回属性值
     * 作者 : Edgar.lee
     */
    public function attr($attr = null, $value = null) {
        $objAttr = &$this->objAttr;
        //存在第一个节点键
        if (isset($objAttr['nodeList'][0])) {
            if ($value !== null) {
                $attr = array($attr => $value);
            }
            //设置属性
            if (is_array($attr)) {
                $nodeKeys = &$objAttr['nodeList'];
                foreach ($nodeKeys as &$nodeKey) {
                    foreach ($attr as $k => &$v) {
                        self::nodeAttr($nodeKey, $k, (string)$v);
                    }
                }
            //读取属性
            } else {
                return self::nodeAttr($objAttr['nodeList'][0], $attr);
            }
        } else if ($value === null && !is_array($attr)) {
            return null;
        }

        return $this;
    }

    /**
     * 描述 : 为匹配的元素集合中的每个元素中移除一个属性
     * 参数 :
     *      attr : 指定一个属性名
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function removeAttr($attr) {
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            self::nodeAttr($nodeKey, $attr, false);
        }
        return $this;
    }

    /**
     * 描述 : 移除每个匹配元素的一个，多个或全部样式
     * 参数 :
     *      className : 为每个匹配元素移除的样式属性名
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function removeClass($className) {
        $className = explode(' ', preg_replace('/\s+/', ' ', $className));
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            $temp = array_flip(explode(' ', self::nodeAttr($nodeKey, 'class')));
            unset($temp['']);
            foreach ($className as &$v) {
                unset($temp[$v]);
            }
            self::nodeAttr($nodeKey, 'class', join(' ', array_keys($temp)));
        }
        return $this;
    }

    /**
     * 描述 : 获取匹配集合中指定的元素
     * 参数 :
     *      index : 数字=指定索引,null(默认)=全部节点
     * 返回 :
     *      数字返回包含指定索引的对象
     *      null返回包含全部索引对象的数组
     * 作者 : Edgar.lee
     */
    public function eq($index = null) {
        if ($index === null) {
            $rData = array();
            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                self::$sharedData['newInitData'] = array(
                    //调用当前实体的实体
                    'callerObj'  => $this,
                    //所属文档节点键
                    'docNodeKey' => $this->objAttr['docNodeKey'],
                    //节点列表 [节点键, ...]
                    'nodeList'   => array($nodeKey)
                );
                $rData[] = new self;
            }
            return $rData;
        } else {
            $index < 0 && $index = count($this->objAttr['nodeList']) + $index;
            self::$sharedData['newInitData'] = array(
                //调用当前实体的实体
                'callerObj'  => $this,
                //所属文档节点键
                'docNodeKey' => $this->objAttr['docNodeKey'],
                //节点列表 [节点键, ...]
                'nodeList'   => isset($this->objAttr['nodeList'][$index]) ?
                                    array($this->objAttr['nodeList'][$index]) : array()
            );
            return new self;
        }
    }

    /**
     * 描述 : 减少匹配元素集合由索引范围到指定的一个子集
     * 参数 :
     *      start : 一个整数，指示0的位置上的元素开始被选中.如果为负,则表示从集合的末尾的偏移量
     *      end   : 一个整数，指示0的位置上被选中的元素停止.如果为负,则表示从集合的末尾的偏移量.null=持续到集合的末尾
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public function slice($start, $end = null) {
        $len = count($this->objAttr['nodeList']);
        $start < 0 && ($start += $len) < 0 && $start = 0;
        $end === null ? $end = $len : $end < 0 && ($end += $len) < 0 && $end = 0;
        if ($start < $end) {
            $nodeKeyList = array_slice($this->objAttr['nodeList'], $start, $end - $start);
        } else {
            $nodeKeyList = array();
        }
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            'nodeList'   => &$nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 获得当前元素匹配集合中每个元素的后代，选择性筛选的选择器
     * 参数 :
     *      selector : 字符串=selector一个用于匹配元素的选择器字符串,对象=一个用于匹配元素的HParse对象
     *      rootObj  : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回包含指定索引的对象
     * 作者 : Edgar.lee
     */
    public function find($selector, $rootObj = '') {
        if (is_string($selector)) {
            self::$sharedData['newInitData'] = $this->multiFunction($selector, $rootObj === '' ? $this : $rootObj, false);
        } else {
            $temp = array_intersect(
                self::selectors($this->objAttr['nodeList'], '*'),
                is_callable(array($selector, 'get')) ?
                    $selector->get() :
                    array()
            );
            array_splice($temp, 0, 0);
            self::$sharedData['newInitData'] = array(
                //调用当前实体的实体
                'callerObj'  => $this,
                //所属文档节点键
                'docNodeKey' => $this->objAttr['docNodeKey'],
                'nodeList'   => &$temp
            );
        }
        return new self;
    }

    /**
     * 描述 : 返回对象内部属性
     * 参数 :
     *      nodeList : true(默认)=返回节点列表, false=返回所有属性
     * 作者 : Edgar.lee
     */
    public function get($nodeList = true) {
        return $nodeList ?
            $this->objAttr['nodeList'] :
            $this->objAttr;
    }

    /**
     * 描述 : 读取或设置匹配的节点的值
     * 参数 :
     *      value : null(默认)=获取匹配的元素集合中第一个元素的当前值,字符串=设置匹配的元素集合中每个元素的值
     * 返回 :
     *      设置时返回当前对象
     *      读取时返回属性值
     * 作者 : Edgar.lee
     */
    public function val($value = null) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        if ($value === null) {
            if (isset($this->objAttr['nodeList'][0])) {
                $tagName = $parseNode[$nodeKey = $this->objAttr['nodeList'][0]]['tagName'];
                //列表标签
                if ($tagName === 'select') {
                    $temp = $childList = array();
                    self::htmlFormat($nodeKey, $childList, null);
                    //遍历子节点
                    foreach ($childList as &$childNodeKey) {
                        //option标签 && value匹配
                        if (
                            $parseNode[$childNodeKey]['tagName'] === 'option' &&
                            self::nodeAttr($childNodeKey, 'selected') !== null
                        ) {
                            $temp[] = self::nodeAttr($childNodeKey, 'textContent');
                        }
                    }

                    //多选列表
                    if (isset($parseNode[$nodeKey]['attr']['multiple'])) {
                        return $temp;
                    } elseif (isset($temp[0])) {
                        return $temp[count($temp) - 1];
                    }
                //input标签
                } elseif ($tagName === 'input') {
                    return self::nodeAttr($nodeKey, 'value');
                //如果子节点仅为文本节点
                } else if (self::hasChildTag($tagName) & 6) {
                    return self::nodeAttr($nodeKey, 'textContent');
                }
            }
            return null;
        } else {
            $valueArr = (array)$value;
            $valueStr = join(',', $valueArr);
            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                $tagName = $parseNode[$nodeKey]['tagName'];
                //列表标签
                if ($tagName === 'select') {
                    $childList = array();
                    self::htmlFormat($nodeKey, $childList, null);
                    //遍历子节点
                    foreach ($childList as &$childNodeKey) {
                        //option标签 && value匹配
                        if (
                            $parseNode[$childNodeKey]['tagName'] === 'option' &&
                            array_search(self::nodeAttr($childNodeKey, 'textContent'), $valueArr) !== false
                        ) {
                            self::nodeAttr($childNodeKey, 'selected', 'selected');
                        }
                    }
                //input标签
                } else if ($tagName === 'input') {
                    switch (self::nodeAttr($nodeKey, 'type')) {
                        //多选按钮
                        case 'checkbox':
                        //单选按钮
                        case 'radio'   :
                            if (array_search(self::nodeAttr($nodeKey, 'value'), $valueArr) !== false) {
                                self::nodeAttr($nodeKey, 'checked', 'checked');
                            }
                            break;
                        //其它类型修改value属性
                        default        :
                            self::nodeAttr($nodeKey, 'value', $valueStr);
                    }
                } else if ($tagName === 'option') {
                    self::nodeAttr($nodeKey, 'value', $valueStr);
                //如果子节点仅为文本节点
                } else if (self::hasChildTag($tagName) & 6) {
                    self::nodeAttr($nodeKey, 'textContent', $valueStr);
                }
            }
            return $this;
        }
    }

    /**
     * 描述 : 为匹配的元素集合中获取第一个元素的样式属性值(仅实现解析赋值,没实现继承关系)
     * 参数 :
     *      name  : 字符串=一个css属性名,数组=同时修改多个属性{属性名:属性值}
     *      value : 一个CSS属性名的值, ""=删除属性
     * 返回 :
     *      设置时返回当前对象
     *      读取时返回样式值
     * 作者 : Edgar.lee
     */
    public function css($name, $value = null) {
        $objAttr = &$this->objAttr;
        //存在第一个节点键
        if (isset($objAttr['nodeList'][0])) {
            if ($value !== null) {
                $name = array($name => $value);
            }
            //设置样式
            if (is_array($name)) {
                $nodeKeys = &$objAttr['nodeList'];
                foreach ($nodeKeys as &$nodeKey) {
                    $style = array();
                    //拆分样式
                    preg_match_all(
                        '@([\w-]+)\s*:\s*((?:[^(;]*(?:\(.*\))*)+)@',
                        self::nodeAttr($nodeKey, 'style'),
                        //接受拆分的数组
                        $temp,
                        PREG_SET_ORDER
                    );
                    //格式化样式
                    foreach ($temp as &$v) {
                        //注意:样式值结尾可能带多余的空字符
                        $style[$v[1]] = $v[2];
                    }
                    $temp = $name + $style;
                    foreach ($temp as $k => &$v) {
                        if ($v) {
                            $style[$k] = $k .':'. $v;
                        } else {
                            unset($style[$k]);
                        }
                    }
                    self::nodeAttr($nodeKey, 'style', join(';', $style));
                }
            //读取样式
            } else {
                //有效的样式名
                if (preg_match('@^[\w-]+$@', $name)) {
                    //匹配指定样式名
                    preg_match(
                        '@' .$name. '\s*:\s*((?:[^(;]*(?:\(.*\))*)+)@',
                        self::nodeAttr($objAttr['nodeList'][0], 'style'),
                        //接受拆分的数组
                        $temp
                    );
                    if (isset($temp[1])) {
                        return trim($temp[1]);
                    }
                }
                return null;
            }
        } else if ($value === null && is_string($name)) {
            return null;
        }

        return $this;
    }

    /**
     * 描述 : 根据参数设定在每一个匹配的元素之后插入内容
     * 参数 :
     *      content : HTML字符串或者HParse对象,用来插在每个匹配元素的后面
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function after($content, $rootObj = null) {
        return $this->insertNode($content, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 在目标后面插入每个匹配的元素
     * 参数 :
     *      content : 选择符,HTML字符串或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function insertAfter($content, $rootObj = null) {
        if (is_string($content)) {
            $content = $this->multiFunction($content, $rootObj, null);
        }
        $content->after($this);
        return $this;
    }

    /**
     * 描述 : 根据参数设定在每个匹配元素里面的末尾处插入内容
     * 参数 :
     *      content : HTML字符串或者HParse对象,用来插在每个匹配元素里面的末尾
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function append($content, $rootObj = null) {
        return $this->insertNode($content, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 根据参数设定在每个匹配元素里面的末尾处插入内容
     * 参数 :
     *      content : 选择符,HTML字符串或者HParse对象,符合的元素们会被插入到由参数指定的目标的末尾
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function appendTo($content, $rootObj = null) {
        if (is_string($content)) {
            $content = $this->multiFunction($content, $rootObj, null);
        }
        $content->append($this);
        return $this;
    }

    /**
     * 描述 : 根据参数设定在匹配元素的前面（外面）插入内容
     * 参数 :
     *      content : HTML字符串或者HParse对象,用来插入到匹配元素前面的内容
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function before($content, $rootObj = null) {
        return $this->insertNode($content, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 在目标前插入所有匹配元素
     * 参数 :
     *      content : 选择符,HTML字符串或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function insertBefore($content, $rootObj = null) {
        if (is_string($content)) {
            $content = $this->multiFunction($content, $rootObj, null);
        }
        $content->before($this);
        return $this;
    }

    /**
     * 描述 : 将参数内容插入到每个匹配元素的前面（元素内部）
     * 参数 :
     *      content : HTML字符串或者HParse对象,将被插入到匹配元素前的内容
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function prepend($content, $rootObj = null) {
        return $this->insertNode($content, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 将所有元素插入到目标前面（元素内）
     * 参数 :
     *      content : 选择符,HTML字符串或者HParse对象,符合的元素们会被插入到由参数指定的目标的开头
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function prependTo($content, $rootObj = null) {
        if (is_string($content)) {
            $content = $this->multiFunction($content, $rootObj, null);
        }
        $content->prepend($this);
        return $this;
    }

    /**
     * 描述 : 用提供的内容替换所有匹配的元素
     * 参数 :
     *      content : 选择符,HTML字符串或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function replaceWith($content, $rootObj = null) {
        return $this->insertNode($content, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 用匹配元素替换所有目标元素
     * 参数 :
     *      content : 选择符,或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function replaceAll($content, $rootObj = null) {
        if (is_string($content)) {
            $content = $this->multiFunction($content, $rootObj, null);
        }
        $content->replaceWith($this);
        return $this;
    }

    /**
     * 描述 : 析构函数
     * 作者 : Edgar.lee
     */
    public function __destruct() {
        $parseNode = &self::$parseNode;

        //更新引用计数器
        foreach ($this->objAttr['nodeList'] as &$v) {
            $parseNode[$v]['refcount'] -= 1;
        }

        //节点清理
        self::nodeCollection($this->objAttr['nodeList']);
    }

    /**
     * 描述 : 多功能解释内部方法
     * 参数 :
     *      context  : 对象=克隆一个新的对象(使用clone关键词),第一位为'<'的字符串=解析html,其他字符串=选择器
     *      rootObj  : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     *      rType    : 返回类型,true(默认)=返回节点列表,false=返回属性列表,null=返回对象
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    private function multiFunction($context, $rootObj = null, $rType = true) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //对象
        if (is_object($context)) {
            $sharedData = $context->get(false);
        } else {
            if (!is_string($context)) {
                $context = (string)$context;
            }
            //对象
            if (is_object($rootObj)) {
                $objAttr = $rootObj->get(false);
            //为字符串或null
            } else {
                $objAttr = array(
                    //所属文档节点键
                    'docNodeKey' => $this->objAttr['docNodeKey'],
                );
            }

            $sharedData = array(
                //所属文档节点键
                'docNodeKey' => &$objAttr['docNodeKey'],
            );
            //强制解析文本节点
            if ($rootObj === false) {
                //新文本节点键
                $parseNode[$temp = ++self::$nodeCount] = array(
                    'attr'    => array('' => $context),
                    'tagName' => '!text'
                ) + self::$defaultNode;
                $sharedData['nodeList'] = array($temp);
            //解析html
            } else if ($rootObj === true || (isset($context[0]) && $context[0] === '<')) {
                $sharedData['nodeList'] = self::nodeConn(self::htmlParse($context), 'child', false, true);
            //选择器
            } else {
                //$rootObj=null时使用根节点
                if ($rootObj === null) {
                    $nodeList = array($objAttr['docNodeKey']);
                //$rootObj=对象时使用对象列表
                } else if (isset($objAttr['nodeList'])) {
                    $nodeList = &$objAttr['nodeList'];
                //$rootObj=字符串时解析字符串列表
                } else {
                    $nodeList = $this->multiFunction($rootObj);
                }
                $sharedData['nodeList'] = self::selectors($nodeList, $context);
            }
        }

        //调用当前实体的实体
        $sharedData['callerObj'] = $this;
        if ($rType === null) {
            self::$sharedData['newInitData'] = &$sharedData;
            return new self;
        } else if ($rType === true) {
            return $sharedData['nodeList'];
        } else {
            return $sharedData;
        }
    }

    /**
     * 描述 : 不同类型的节点插入操作
     * 参数 :
     *     &content : HTML字符串或者HParse对象,用来插入的节点
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     *      type    : 插入类型,after       = 插入内容到节点之后,
     *                         before      = 插入内容到节点之前,
     *                         append      = 插入内容作为节点最后的子节点
     *                         prepend     = 插入内容作为节点最前的子节点
     *                         replaceWith = 插入内容替换匹配节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    private function insertNode(&$content, $rootObj, $type) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        $oNodeKeys = $this->multiFunction($content, $rootObj);

        //如果不是空列表
        if (isset($oNodeKeys[0])) {
            //存储克隆的节点
            $cloneNodeKeys = $oNodeKeys;

            foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                //如果克隆节点为空,则重新克隆
                if ($cloneNodeKeys === null) {
                    //克隆全部插入节点
                    foreach ($oNodeKeys as &$cloneNodeKey) {
                        $cloneNodeKeys[] = self::cloneNode($cloneNodeKey);
                    }
                }

                switch ($type) {
                    case 'after'      :
                    case 'before'     :
                        //当前节点有父节点
                        if (($pNodeKey = $parseNode[$nodeKey]['pKey']) !== null) {
                            if ($type === 'after') {
                                //插入节点位置
                                $insPos = array_search($nodeKey, $parseNode[$pNodeKey]['cKeys']) + 1;
                                //如插入位置无效,则插入到最后
                                $insPos = isset($parseNode[$pNodeKey]['cKeys'][$insPos]) ?
                                    $parseNode[$pNodeKey]['cKeys'][$insPos] : false;
                            } else {
                                $insPos = $nodeKey;
                            }

                            foreach ($cloneNodeKeys as &$cloneNodeKey) {
                                //将克隆节点依次插入当前节点下一个节点的前面
                                self::nodeSplice($cloneNodeKey, $pNodeKey, $insPos);
                            }

                            $cloneNodeKeys = null;
                        }
                        break;
                    case 'append'     :
                    case 'prepend'    :
                        $insPos = $type === 'prepend' && isset($parseNode[$nodeKey]['cKeys'][0]) ?
                            $parseNode[$nodeKey]['cKeys'][0] : false;
                        foreach ($cloneNodeKeys as &$cloneNodeKey) {
                            //将克隆节点依次插入当前节点子节点最后面
                            self::nodeSplice($cloneNodeKey, $nodeKey, $insPos);
                        }

                        $cloneNodeKeys = null;
                        break;
                    case 'replaceWith':
                        //当前节点有父节点
                        if (($pNodeKey = $parseNode[$nodeKey]['pKey']) !== null) {
                            foreach ($cloneNodeKeys as &$cloneNodeKey) {
                                //将克隆节点依次插入当前节点下一个节点的前面
                                self::nodeSplice($cloneNodeKey, $pNodeKey, $nodeKey);
                            }

                            //移除当前节点
                            self::nodeSplice($nodeKey);
                            $cloneNodeKeys = null;
                        }
                        break;
                }
            }
        }

        return $this;
    }

    /**
     * 描述 : 深度复制匹配的元素
     * 返回 :
     *      返回包含克隆节点的对象
     * 作者 : Edgar.lee
     */
    public function clones() {
        $sharedData = &self::$sharedData['newInitData'];
        $sharedData = $this->objAttr;
        $sharedData['nodeList'] = array();
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            $sharedData['nodeList'][] = self::cloneNode($nodeKey);
        }
        return new self;
    }

    /**
     * 描述 : 移除所有匹配节点的子节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function emptys() {
        //解析节点引用
        $parseNode = &self::$parseNode;
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            foreach ($parseNode[$nodeKey]['cKeys'] as $chlidKey) {
                self::nodeSplice($chlidKey);
            }
        }
        return $this;
    }

    /**
     * 描述 : 移除所有匹配节点
     * 参数 :
     *      selector : 一个选择表达死用来过滤匹配的将被移除的元素
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function remove($selector = null) {
        $removeNodeKeys = $selector === null ?
            $this->objAttr['nodeList'] :
            self::selectors($this->objAttr['nodeList'], $selector, false);

        foreach ($removeNodeKeys as &$nodeKey) {
            self::nodeSplice($nodeKey);
        }
        return $this;
    }

    /**
     * 描述 : 将匹配元素的父级元素删除，保留自身（和兄弟元素，如果存在）在原来的位置
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function unwrap() {
        //解析节点引用
        $parseNode = &self::$parseNode;
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            if (isset($parseNode[$nodeKey]['pKey'])) {
                $pNodeKey = $parseNode[$nodeKey]['pKey'];
                foreach ($parseNode[$pNodeKey]['cKeys'] as $siblingKey) {
                    self::nodeSplice($siblingKey, $parseNode[$pNodeKey]['pKey'], $pNodeKey);
                }
                self::nodeSplice($pNodeKey);
            }
        }
        return $this;
    }

    /**
     * 描述 : 在每个匹配的元素外层包上一个html元素
     * 参数 :
     *      wrap    : 选择符,HTML字符串或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function wrap($wrap, $rootObj = null) {
        return $this->wrapOperating($wrap, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 在匹配元素里的内容外包一层结构
     * 参数 :
     *      wrap    : 选择符,HTML字符串或者HParse对象,
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function wrapInner($wrap, $rootObj = null) {
        return $this->wrapOperating($wrap, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 在所有匹配元素外面包一层HTML结构
     * 参数 :
     *      wrap    : 选择符,HTML字符串或者HParse对象,
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    public function wrapAll($wrap, $rootObj = null) {
        return $this->wrapOperating($wrap, $rootObj, __FUNCTION__);
    }

    /**
     * 描述 : 为匹配元素包含标签操作
     * 参数 :
     *     &wrap    : 选择符,HTML字符串或者HParse对象
     *      rootObj : context为文本时有效,与context类型一致,查询的根目录,true=强制文本解析,false=强制解析为不编码的文本节点
     *      type    : wrapAll=在外层共套一个元素,wrap=在每个匹配元素的外层套一个元素,wrapInner=在每个匹配的内层套一个元素
     * 返回 :
     *      返回当前对象
     * 作者 : Edgar.lee
     */
    private function wrapOperating(&$wrap, $rootObj, $type) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //插入的节点
        $insertNodeKey = null;
        //克隆父节点
        $cloneNodekey = null;
        //克隆父节点的所有子节点
        $cloneNodekeyChild = null;

        if (isset($this->objAttr['nodeList'][0])) {
            $nodeKeyList = $this->multiFunction($wrap, $rootObj);

            if (isset($nodeKeyList[0])) {
                foreach ($nodeKeyList as &$nodeKey) {
                    $childNodeKeys = array();
                    self::htmlFormat($nodeKey, $childNodeKeys, null);
                    if (isset($childNodeKeys[0])) {
                        //寻找第一个没有非文本子节点的节点
                        foreach ($childNodeKeys as &$childNodeKey) {
                            //没有非文本子节点
                            if (self::nodeConn($childNodeKey, 'child', 0) === null) {
                                $insertNodeKey = $childNodeKey;
                                break;
                            }
                        }
                    } elseif (
                        //常规解决
                        $parseNode[$nodeKey]['nodeType'] === 'node' &&
                        //不是单标签
                        self::hasChildTag($parseNode[$nodeKey]['tagName']) > 1
                    ) {
                        $insertNodeKey = $nodeKey;
                    }

                    if ($insertNodeKey !== null) {
                        break;
                    }
                }

                //将匹配节点插入到当前节点最后
                if ($insertNodeKey !== null) {
                    //插入节点祖先父节点
                    $parentNodeKey = self::nodeConn($insertNodeKey, 'parent', -1);
                    if ($parentNodeKey === null) {
                        $parentNodeKey = $insertNodeKey;
                        $insertNodeKeyPos = null;
                    } else {
                        $temp = array();
                        self::htmlFormat($parentNodeKey, $temp, null);
                        //插入节点在父节点中位置
                        $insertNodeKeyPos = array_search($insertNodeKey, $temp);
                    }

                    if ($type === 'wrapAll') {
                        $nodeKey = $this->objAttr['nodeList'][0];
                        $cloneNodekeyChild = array();
                        $cloneNodekey = self::cloneNode($parentNodeKey);
                        self::htmlFormat($cloneNodekey, $cloneNodekeyChild, null);
                        //匹配节点插入的节点
                        $cloneNodekeyChild = $insertNodeKeyPos === null ?
                            $cloneNodekey : $cloneNodekeyChild[$insertNodeKeyPos];
                        self::nodeSplice($cloneNodekey, $parseNode[$nodeKey]['pKey'], $nodeKey);
                        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                            self::nodeSplice($nodeKey, $cloneNodekeyChild, false);
                        }
                    } else {
                        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
                            //克隆新节点
                            if ($cloneNodekey === null) {
                                $cloneNodekeyChild = array();
                                $cloneNodekey = self::cloneNode($parentNodeKey);
                                self::htmlFormat($cloneNodekey, $cloneNodekeyChild, null);
                                //匹配节点插入的节点
                                $cloneNodekeyChild = $insertNodeKeyPos === null ?
                                    $cloneNodekey : $cloneNodekeyChild[$insertNodeKeyPos];
                            }
                            switch ($type) {
                                case 'wrap'     :
                                    //克隆节点插入到匹配节点前,匹配节点插入的克隆节点中
                                    if (isset($parseNode[$nodeKey]['pKey'])) {
                                        self::nodeSplice($cloneNodekey, $parseNode[$nodeKey]['pKey'], $nodeKey);
                                        self::nodeSplice($nodeKey, $cloneNodekeyChild, false);
                                        $cloneNodekey = null;
                                    }
                                    break;
                                case 'wrapInner':
                                    //记录匹配节点的子记得
                                    $cKeys = $parseNode[$nodeKey]['cKeys'];
                                    //替换插入(清空匹配节点子节点)
                                    self::nodeSplice($cloneNodekey, $nodeKey, null);
                                    foreach ($cKeys as &$childNodeKey) {
                                        //子节点插入克隆子节点的最后
                                        self::nodeSplice($childNodeKey, $cloneNodekeyChild, false);
                                    }
                                    $cloneNodekey = null;
                                    break;
                            }
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * 描述 : 添加元素到匹配的元素集合
     * 参数 :
     *      selector : 选择符,HTML字符串或者HParse对象
     *      context  : 对指定的范围内添加一些根元素,默认null
     * 返回 :
     *      返回合并后的对象
     * 作者 : Edgar.lee
     */
    public function add($selector, $context = null) {
        $nodeKeyList = array_merge($this->objAttr['nodeList'], $this->multiFunction($selector, $context));
        self::nodeKeysUniqueSort($nodeKeyList, false, $this->objAttr['docNodeKey']);
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            //节点列表 [节点键, ...]
            'nodeList'   => $nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 添加先前的堆栈元素集合到当前组合
     * 返回 :
     *      返回合并后的对象
     * 作者 : Edgar.lee
     */
    public function andSelf() {
        return $this->add($this->end());
    }

    /**
     * 描述 : 终止在当前链的最新过滤操作，并返回匹配的元素集合为它以前的状态
     * 返回 :
     *      返回上次过滤的对象,如果没有,返回一个空对象
     * 作者 : Edgar.lee
     */
    public function end() {
        if ($this->objAttr['callerObj'] === null) {
            self::$sharedData['newInitData'] = $this->objAttr;
            self::$sharedData['nodeList'] = array();
            return new self;
        } else {
            return $this->objAttr['callerObj'];
        }
    }

    /**
     * 描述 : 获得每个匹配元素集合元素的子元素，选择性筛选的选择器
     * 参数 :
     *      selector  : 一个用于匹配元素的选择器字符串
     * 返回 :
     *      返回过滤的子对象
     * 作者 : Edgar.lee
     */
    public function children($selector = '') {
        $nodeKeyList = array();
        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            $nodeKeyList[] = self::nodeConn($nodeKey, 'child');
        }
        if (isset($nodeKeyList[1])) {
            //生成过滤列表
            $nodeKeyList = call_user_func_array('array_merge', $nodeKeyList);
        } elseif (isset($nodeKeyList[0])) {
            $nodeKeyList = $nodeKeyList[0];
        }
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            'nodeList'   => self::selectors($nodeKeyList, $selector, false)
        );
        return new self;
    }

    /**
     * 描述 : 从元素本身开始，逐级向上级元素匹配
     * 参数 :
     *      selector : 选择符,HTML字符串或者HParse对象
     *      context  : 对指定的范围内添加一些根元素,默认null
     * 返回 :
     *      返回包含最先匹配的祖先元素(零个或一个)对象
     * 作者 : Edgar.lee
     */
    public function closest($selector, $context = null) {
        //不为空节点
        if (isset($this->objAttr['nodeList'][0])) {
            $nodeKeyList[0] = $this->objAttr['nodeList'];
            foreach ($nodeKeyList[0] as &$nodeKey) {
                //读取全部父节点
                $nodeKeyList[] = self::nodeConn($nodeKey, 'parent');
            }
            //生成过滤列表
            $nodeKeyList = call_user_func_array('array_merge', $nodeKeyList);

            //过滤匹配数据
            if ($context === null) {
                $nodeKeyList = self::selectors($nodeKeyList, $selector, false);
            //在指定根目录中寻找节点
            } else {
                $nodeKeyList = array_intersect(
                    $this->multiFunction($selector, $context),
                    $nodeKeyList
                );
                array_splice($nodeKeyList, 0, 0);
            }
        } else {
            $nodeKeyList = array();
        }

        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            //节点列表 [节点键, ...]
            'nodeList'   => &$nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 获得每个匹配元素集合元素的子元素,包括文字和注释节点
     * 参数 :
     *      type : true(默认)=获取直接子元素,false=获取子孙全部元素,null=获取子孙非文本类元素,字符串=获取子孙为指定标签名的元素
     * 返回 :
     *      返回包含子节点的对象
     * 作者 : Edgar.lee
     */
    public function contents($type = true) {
        //深度获取指定元素
        is_string($type) && $type = strtolower($type);

        foreach ($this->objAttr['nodeList'] as &$nodeKey) {
            if ($type === true) {
                //读取全部子节点
                $nodeKeyList[] = self::nodeConn($nodeKey, 'child', false, true);
            } else {
                //遍历全部子节点
                self::htmlFormat($nodeKey, $nodeKeyList, $type, false);
            }
        }

        //为查询到数据
        if (empty($nodeKeyList)) {
            $nodeKeyList = array();
        //格式化结构
        } else {
            $nodeKeyList = $type === true ? 
                //生成过滤列表
                call_user_func_array('array_merge', $nodeKeyList) : array_keys($nodeKeyList);
        }

        //去除排序
        self::nodeKeysUniqueSort($nodeKeyList, false, $this->objAttr['docNodeKey']);
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            //节点列表 [节点键, ...]
            'nodeList'   => &$nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 筛选出与指定表达式匹配的元素集合
     * 参数 :
     *      selector : 选择符,HParse对象
     * 返回 :
     *      返回包含子节点的对象
     * 作者 : Edgar.lee
     */
    public function filter($selector) {
        //选择符
        if (is_string($selector)) {
            $nodeKeyList = self::selectors($this->objAttr['nodeList'], $selector, false);
        //对象
        } else {
            $nodeKeyList = $selector->get();
        }
        $nodeKeyList = array_intersect($nodeKeyList, $this->objAttr['nodeList']);
        array_splice($nodeKeyList, 0, 0);

        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            //节点列表 [节点键, ...]
            'nodeList'   => &$nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 输出文档节点
     * 参数 :
     *      type : obj(默认)=返回根文档对象,str=返回字符串
     *      mode : type为str时有效, true=补全未闭合标签, false=按原解析方式
     * 返回 :
     *      返回根文档对象或字符串
     * 作者 : Edgar.lee
     */
    public function doc($type = 'obj', $mode = true) {
        if ($type === 'obj') {
            self::$sharedData['newInitData'] = array(
                //调用当前实体的实体
                'callerObj'  => $this,
                //所属文档节点键
                'docNodeKey' => $this->objAttr['docNodeKey'],
                //节点列表 [节点键, ...]
                'nodeList'   => array($this->objAttr['docNodeKey'])
            );
            return new self;
        } else {
            $temp = array();
            self::htmlFormat($this->objAttr['docNodeKey'], $temp, true, $mode);
            return join($temp);
        }
    }

    /**
     * 描述 : 获取元素集合中第一个元素
     * 返回 :
     *      返回包含第一个节点的对象
     * 作者 : Edgar.lee
     */
    public function first() {
        return $this->eq(0);
    }

    /**
     * 描述 : 获取元素集合中最后一个元素
     * 返回 :
     *      返回包含最后一个节点的对象
     * 作者 : Edgar.lee
     */
    public function last() {
        return $this->eq(-1);
    }

    /**
     * 描述 : 选择含有选择器所匹配的至少一个元素的元素
     * 参数 :
     *      selector : 选择符,HParse对象
     * 返回 :
     *      返回包含过滤后节点对象
     * 作者 : Edgar.lee
     */
    public function has($selector) {
        if (is_string($selector)) {
            $nodeList = self::selectors($this->objAttr['nodeList'], ':has(' .$selector. ')', false);
        } else {
            $nodeList = array_intersect($this->objAttr['nodeList'], $selector->get());
            array_splice($nodeList, 0, 0);
        }
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            'nodeList'   => $nodeList
        );
        return new self;
    }

    /**
     * 描述 : 删除匹配的元素集合中元素
     * 参数 :
     *      selector : 选择符,HParse对象
     * 返回 :
     *      返回包含过滤后节点对象
     * 作者 : Edgar.lee
     */
    public function not($selector) {
        if (is_string($selector)) {
            $nodeList = self::selectors($this->objAttr['nodeList'], ':not(' .$selector. ')', false);
        } else {
            $nodeList = array_diff($this->objAttr['nodeList'], $selector->get());
            array_splice($nodeList, 0, 0);
        }
        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $this,
            //所属文档节点键
            'docNodeKey' => $this->objAttr['docNodeKey'],
            'nodeList'   => &$nodeList
        );
        return new self;
    }

    /**
     * 描述 : 检查当前匹配的元素集合是否匹配
     * 参数 :
     *      selector : 一个选择器或者对象
     * 返回 :
     *      如果这些元素至少一个匹配给定的参数，那么返回true
     * 作者 : Edgar.lee
     */
    public function is($selector) {
        if (is_string($selector)) {
            $nodeList = self::selectors($this->objAttr['nodeList'], $selector, false);
        } else {
            $nodeList = array_intersect($this->objAttr['nodeList'], $selector->get());
            array_splice($nodeList, 0, 0);
        }
        return isset($nodeList[0]);
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素紧邻的后面同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function next($selector = false) {
        return self::relationship($this, $selector, 'next', 'single');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素全部的后面同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function nextAll($selector = false) {
        return self::relationship($this, $selector, 'next', 'all');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素后面直到匹配前的同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function nextUntil($selector = false) {
        return self::relationship($this, $selector, 'next', 'until');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素紧邻的前面同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function prev($selector = false) {
        return self::relationship($this, $selector, 'prev', 'single');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素全部的前面同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function prevAll($selector = false) {
        return self::relationship($this, $selector, 'prev', 'all');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素前面直到匹配前的同辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function prevUntil($selector = false) {
        return self::relationship($this, $selector, 'prev', 'until');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素紧邻的父辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function parent($selector = false) {
        return self::relationship($this, $selector, 'parent', 'single');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素全部的父辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function parents($selector = false) {
        return self::relationship($this, $selector, 'parent', 'all');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素前面直到匹配前的父辈元素的元素集合
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含紧邻节点的对象
     * 作者 : Edgar.lee
     */
    public function parentsUntil($selector = false) {
        return self::relationship($this, $selector, 'parent', 'until');
    }

    /**
     * 描述 : 获得匹配元素集合中每个元素的兄弟元素
     * 参数 :
     *      selector : 一个选择器
     * 返回 :
     *      返回包含兄弟节点的对象
     * 作者 : Edgar.lee
     */
    public function siblings($selector = false) {
        return self::relationship($this, $selector, 'sibling', 'all');
    }

    /**
     * 描述 : 取得一个包含匹配的元素集合中每一个元素紧邻的全部元素集合
     * 参数 :
     *      thisObj  : 查询对象
     *      selector : 字符串=选择器,对象=与对象交集的元素集合,true=包含文本类节点,false=非文本类节点
     *      type     : 临近类型
     *      filter   : single=第一个临近值,all=筛选全部值,until=直到第一个匹配位置
     * 返回 :
     *      返回新对象
     * 作者 : Edgar.lee
     */
    private static function relationship($thisObj, $selector, $type, $filter) {
        $nodeKeyList = array();
        foreach ($thisObj->objAttr['nodeList'] as &$nodeKey) {
            $temp = self::nodeConn($nodeKey, $type, false, $selector === true || is_object($selector));
            if (isset($temp[0])) {
                $nodeKeyList[] = $filter === 'single' ? array($temp[0]) : $temp;
            }
        }

        if ($filter === 'until' && is_string($selector)) {
            $filterNodeKeyList = array(array('' => ''));
            foreach ($nodeKeyList as &$nodeKeys) {
                //过滤数据
                $temp = self::selectors($nodeKeys, $selector, false);
                //查询到结果
                $filterNodeKeyList[] = ($temp = end($temp)) ?
                    array_slice($nodeKeys, 0, array_search($temp, $nodeKeys)) : $nodeKeys;
            }
            //生成过滤列表
            $nodeKeyList = call_user_func_array('array_merge', $filterNodeKeyList);
            unset($nodeKeyList['']);
            //去重排序
            self::nodeKeysUniqueSort($nodeKeyList, false, $thisObj->objAttr['docNodeKey']);
        } else {
            if (isset($nodeKeyList[1])) {
                //生成过滤列表
                $nodeKeyList = call_user_func_array('array_merge', $nodeKeyList);
            } elseif (isset($nodeKeyList[0])) {
                $nodeKeyList = $nodeKeyList[0];
            }
            //是字符串
            if (is_string($selector)) {
                $nodeKeyList = self::selectors($nodeKeyList, $selector, false);
            //是对象
            } else {
                //去重排序
                self::nodeKeysUniqueSort($nodeKeyList, false, $thisObj->objAttr['docNodeKey']);
                if (is_object($selector)) {
                    $nodeKeyList = array_intersect($nodeKeyList, $selector->get());
                    array_splice($nodeKeyList, 0, 0);
                }
            }
        }

        self::$sharedData['newInitData'] = array(
            //调用当前实体的实体
            'callerObj'  => $thisObj,
            //所属文档节点键
            'docNodeKey' => $thisObj->objAttr['docNodeKey'],
            'nodeList'   => $nodeKeyList
        );
        return new self;
    }

    /**
     * 描述 : 从匹配的元素中搜索给定元素的索引值,从0开始计数
     * 参数 :
     *      selector : null(默认)=当前集合第一个节点在它同辈节点的位置
     *                 选择符    =当前集合第一节点在选择器中的位置
     *                 对象      =对象中的第一节点在当前集合中的位置
     * 返回 :
     *      返回节点位置,没查到返回-1
     * 作者 : Edgar.lee
     */
    public function index($selector = null) {
        $rData = false;
        //是对象
        if (is_object($selector)) {
            //当前节点键
            $temp = $this->multiFunction($selector);
            //节点列表
            $nodeList = &$this->objAttr['nodeList'];
        //null或选择符
        } else {
            $temp = $this->objAttr['nodeList'];
        }

        if (isset($temp[0])) {
            //当前节点
            $nodeKey = $temp[0];
            //查询包含自己的兄节点
            if ($selector === null) {
                $nodeList = self::nodeConn($nodeKey, 'prev');
                $nodeList[] = $nodeKey;
            //是字符串
            } elseif (!isset($nodeList)) {
                //节点列表
                $nodeList = $this->multiFunction($selector);
            }
            $rData = array_search($nodeKey, $nodeList);
        }
        return $rData === false ? -1 : $rData;
    }

    /**
     * 描述 : 返回当期对象匹配包含节点数量
     * 作者 : Edgar.lee
     */
    public function size() {
        return count($this->objAttr['nodeList']);
    }

    /**                                                                                     筛选器
     * 描述 : 选择器核心
     * 参数 :
     *      nodeKeys : 指的选择器的根节点键,数组
     *      selector : 过滤字符串,已jQuery为参照
     *      isChild  : true(默认)=从子节点开始,false=从自身节点开始
     * 返回 :
     *      匹配的节点键数组
     * 作者 : Edgar.lee
     */
    public static function selectors($nodeKeys, $selector, $isChild = true) {
        if (!$selector = ltrim($selector)) return array();      //空字符串返回空数组
        $parseNode = &self::$parseNode;                         //解析节点引用
        $const = &self::$selectorsConst;                        //引用选择器常量
        $isChild = $isChild ? ' ' : '';                         //是否从子节点开始查起 ? ' ' : ''
        self::nodeKeysUniqueSort($nodeKeys, true);              //分组去重排序

        $env = array(
            'nMatchPos'     => false,                           //当前匹配结果
            'nowPos'        => 0,                               //当前分析位置
            //'nowType'      => ' ',                             //当前分析类型,'['=分析属性,':'=伪类分析,'.'=分析类,'#'=分析ID,' '=分析节点    ,'>'=分析节点,'+'=分析节点,'~'=分析节点,','=分析节点
            'rNodeKeys'     => array(),                         //返回节点键列表
            'selectParse'   => array(),                         //选择列表解析{'type' : reverse=倒序,filter=过滤,group=分组, 'list' : 列表}
            'selector'      => $isChild . $selector . ',',      //过滤字符串
            'temp'          => array(
                'filterNodeKeys'  => null,                      //循环nodeKeys分组结果集时,存储最后过滤匹配的数据
                'prevEmptyChrPos' => 0,                         //上次判定空字符分隔符位置
                'selectAttr'      => array(                     //单次匹配属性
                    'type' => 'filter',                         //永远是'filter'(过滤)
                    'list' => null
                ),
                'selectGroup'     => false,                     //true=将selectList移动到selectParse,false=继续追加selectList
                'selectList'      => array(                     //多次匹配集合
                    'type' => 'reverse',                        //永远是'reverse'(倒序)
                    'list' => array()
                ),
                'selectType'      => 't'                        //当前分析类型,'['=分析属性名,'='=分析属性值,':'=伪类分析,'.'=分析类,'#'=分析ID,'t'=分析标签
            ),
        );

        //解析过滤字符串
        while ($env['nMatchPos'] = of_base_com_str::strArrPos($env['selector'], $const['dMatches'], $env['nowPos'])) {
            //分组的第一位是是空格
            if (
                (
                    $env['nowPos'] === 0 ||
                    $env['selector'][$env['nowPos'] - 1] === ','
                ) && $env['selector'][$env['nowPos']] === ' '
            ) {
                //记录本次匹配位置
                $env['temp']['prevEmptyChrPos'] = $env['nowPos'] + 1;
                $env['nMatchPos'] = array('match' => ' ', 'position' => $env['nowPos']);
            //判断空字符串分隔符
            } else if (
                preg_match(
                    '@[^ ]+(\s+)(.*)$@',
                    substr($env['selector'], 0, $env['nMatchPos']['position']),
                    $match, PREG_OFFSET_CAPTURE, $env['nowPos']
                ) &&
                trim($match[2][0])
            ) {
                //记录本次匹配位置
                $env['temp']['prevEmptyChrPos'] = $match[1][1] + 1;
                $env['nMatchPos'] = array('match' => ' ', 'position' => $match[1][1]);
            //'[:.#'前一位出现空字符,则空字符为分隔符
            } else if (
                //匹配值为'[:.#'
                isset($const['emptyCharSplit']['suf'][$env['nMatchPos']['match']]) &&
                //匹配位置不是上次匹配位置(防止死循环)
                $env['nMatchPos']['position'] !== $env['temp']['prevEmptyChrPos'] &&
                //匹配位置前一位是空格
                $env['selector'][$env['nMatchPos']['position'] - 1] === ' ' &&
                //空格前面不是分割符
                ($env['nowPos'] === 0 || !isset($const['emptyCharSplit']['pre'][$env['selector'][$env['nowPos'] - 1]]))
            ) {
                //记录本次匹配位置
                $env['temp']['prevEmptyChrPos'] = $env['nMatchPos']['position'];
                $env['nMatchPos'] = array('match' => ' ', 'position' => $env['nMatchPos']['position'] - 1);
            }

            //过滤匹配节点键
            switch ($env['nMatchPos']['match']) {
                //子孙节点
                case ' ':
                //子节点
                case '>':
                //临弟节点
                case '+':
                //全弟节点
                case '~':
                    self::matchKeyword($env, true);
                    $env['temp']['selectType'] = 't';
                    array_unshift($env['temp']['selectList']['list'], array(
                        'type'  => 'split',
                        'value' => $env['nMatchPos']['match']
                    ));
                    break;
                //选择分组
                case ',':
                    self::matchKeyword($env, true);
                    $env['temp']['selectType'] = 't';
                    //selectList不空
                    if (isset($env['temp']['selectList']['list'][0])) {
                        //保存倒序到解析列表中
                        $env['selectParse'][] = $env['temp']['selectList'];
                        //清空选择列表
                        $env['temp']['selectList']['list'] = array();
                    }
                    //保存倒序到解析列表中
                    $env['selectParse'][] = array(
                        //倒序
                        'type' => 'group'
                    );
                    $temp = $env['nMatchPos']['position'] + 1;
                    //清除','之后的多余空字符
                    $env['selector'] = substr($env['selector'], 0, $temp) .
                        $isChild . ltrim(substr($env['selector'], $temp));
                    break;
                //分析ID
                case '#':
                //分析样式
                case '.':
                    self::matchKeyword($env, false);
                    $env['temp']['selectType'] = $env['nMatchPos']['match'];
                    break;
                //分析伪类
                case ':':
                    self::matchKeyword($env, false);
                    $env['temp']['selectType'] = 't';
                    $temp = $env['nMatchPos']['position'] + 1;
                    $match = of_base_com_str::strArrPos($env['selector'], $const['pMatches'], $temp);
                    //生成新查询
                    $newSelectList = array(
                        'type'  => 'pseudo',
                        'value' => strtolower(substr($env['selector'], $temp, $match['position'] - $temp)),
                        'param' => null,
                    );
                    //伪类时,确定分组
                    isset($const['groupPseudo'][$newSelectList['value']]) && $env['temp']['selectGroup'] = true;
                    //生成伪类参数
                    if (
                        //找到'('
                        $match['match'] === '(' &&
                        //找到对应')'
                        $temp = self::getNextBrackets($env['selector'], $match)
                    ) {
                        //更新位置
                        $env['nMatchPos'] = array('match' => ')', 'position' => $temp);
                        $newSelectList['param'] = substr($env['selector'], $match['position'] + 1, $temp - $match['position'] - 1);
                    //不是'('
                    } else {
                        $env['nMatchPos'] = array('match' => '', 'position' => $match['position']);
                    }
                    $env['temp']['selectAttr']['list'][] = $newSelectList;
                    break;
                //分析ID
                case '[':
                    self::matchKeyword($env, false);
                    $env['temp']['selectType'] = 't';
                    //找到对应']'
                    if ($temp = self::getNextBrackets($env['selector'], $env['nMatchPos'])) {
                        //匹配截取位置
                        $match = $env['nMatchPos']['position'] + 1;
                        //更新位置
                        $env['nMatchPos'] = array('match' => ']', 'position' => $temp);
                        //截取[]之间内容
                        if ($matchV = trim(substr($env['selector'], $match, $temp - $match))) {
                            $newSelectList = array(
                                'type'  => 'attr',
                                'value' => null,
                                'param' => null,
                                'name'  => $matchV,
                            );
                            //查询到属性分隔符
                            if ($matchV[0] !== '@' && $match = strpos($matchV, '=', $matchV[0] === '=')) {
                                //生成属性值
                                if (
                                    //截取属性值,并且不为空字符串
                                    ($temp = trim(substr($matchV, $match + 1))) &&
                                    //引号包含的
                                    ($temp[0] === '"' || $temp[0] === '\'')
                                ) {
                                    $temp = substr($temp, 1, -1);
                                }
                                $newSelectList['value'] = $temp;

                                //存在匹配参数
                                if (isset($const['aValueType'][$matchV[$temp = $match - 1]])) {
                                    //指定匹配参数
                                    $newSelectList['param'] = $matchV[$temp];
                                    //指定属性名
                                    $newSelectList['name'] = trim(substr($matchV, 0, $temp));
                                //匹配参数不存在
                                } else {
                                    //指定匹配参数
                                    $newSelectList['param'] = '=';
                                    //指定属性名
                                    $newSelectList['name'] = trim(substr($matchV, 0, $match));
                                }
                            }
                            $env['temp']['selectAttr']['list'][] = $newSelectList;
                        }
                    }
                    break;
            }

            $env['nowPos'] = $env['nMatchPos']['position'] + strlen($env['nMatchPos']['match']);
        }

        //遍历分组的节点匹配列表
        foreach ($nodeKeys as $rootNodeKey => &$filterNodeKeys) {
            //清空匹配节点
            $matchAllNodeKeys = array();
            //过滤模式
            if ($isChild === '') {
                //仅过滤列表(如果使用子查询模式,无法处理移除的节点)
                $matchAllNodeKeys = array_combine($filterNodeKeys, $filterNodeKeys);
            //self::htmlFormat($rootNodeKey, $matchAllNodeKeys, null, false);                                       //(范围过大而低效)读取根节点的所有非文本类型的子节点
            //子查询模式
            } else {
                foreach ($filterNodeKeys as &$nodeKey) {
                    //读取过滤节点子节点
                    self::htmlFormat($nodeKey, $matchAllNodeKeys, null, false);
                    //读取过滤节点之下全部节点
                    $matchAllNodeKeys += self::nodeConn($nodeKey, 'next', false, false, false);
                }
            }
            //初始化匹配过滤结果集
            $env['temp']['filterNodeKeys'] = $filterNodeKeys;
            //初始化话过滤节点
            $matchFilterNodeKeys = $matchAllNodeKeys;

            //遍历匹配结果集
            foreach ($env['selectParse'] as $selectK => &$selectParse) {
                switch ($selectParse['type']) {
                    //倒序方式匹配
                    case 'reverse':
                        foreach ($matchAllNodeKeys as &$matchNodeKey) {
                            //当前分析节点集
                            $nowNodeKeys = array($matchNodeKey);
                            foreach ($selectParse['list'] as $index => &$match) {
                                //分隔符
                                if ($match['type'] === 'split') {
                                    $tempNodeKeys = array();
                                    switch ($match['value']) {
                                        //子孙分隔符
                                        case ' ':
                                            foreach ($nowNodeKeys as &$nodeKey) {
                                                //读取所有父节点
                                                $temp = self::nodeConn($nodeKey, 'parent', false, true, false);
                                                //存在父节点级
                                                if (!empty($temp)) {
                                                    $tempNodeKeys += $temp;
                                                }
                                            }
                                            break;
                                        //直接父节点
                                        case '>':
                                            foreach ($nowNodeKeys as &$nodeKey) {
                                                //读取一级父节点
                                                $temp = self::nodeConn($nodeKey, 'parent', 0, true);
                                                //存在之间父节点
                                                if ($temp !== null) {
                                                    $tempNodeKeys[$temp] = $temp;
                                                }
                                            }
                                            break;
                                        //直接兄节点
                                        case '+':
                                            foreach ($nowNodeKeys as &$nodeKey) {
                                                //读取一级父节点
                                                $temp = self::nodeConn($nodeKey, 'prev', 0, false);
                                                //存在之间父节点
                                                if ($temp !== null) {
                                                    $tempNodeKeys[$temp] = $temp;
                                                }
                                            }
                                            break;
                                        //所有兄节点
                                        case '~':
                                            foreach ($nowNodeKeys as &$nodeKey) {
                                                //读取所有父节点
                                                $temp = self::nodeConn($nodeKey, 'prev', false, false, false);
                                                //存在父节点级
                                                if (!empty($temp)) {
                                                    $tempNodeKeys += $temp;
                                                }
                                            }
                                            break;
                                    }

                                    $nowNodeKeys = $tempNodeKeys;
                                    if (empty($tempNodeKeys)) {
                                        break;
                                    }
                                //=filter,节点过滤
                                } else {
                                    self::filterNodeKeys($nowNodeKeys, $match['list']);
                                }
                            }

                            //父节点与过滤节点重合
                            if (count(
                                array_intersect($nowNodeKeys, $env['temp']['filterNodeKeys'])
                            )) {
                                //当前节加入到过滤节点中
                                $env['temp']['filterNodeKeys'][$matchNodeKey] = $matchNodeKey;
                            //父节点与过滤节点不重合
                            } else {
                                //无效数据,从已匹配节点剔除
                                unset($matchFilterNodeKeys[$matchNodeKey]);
                            }
                        }
                        break;
                    //正序方式过滤
                    case 'filter' :
                        self::filterNodeKeys($matchFilterNodeKeys, $selectParse['list']);
                        //如果本组选择符未结束,读取其子节点
                        if ($env['selectParse'][$selectK + 1]['type'] !== 'group') {
                            $temp = array();
                            foreach ($matchFilterNodeKeys as &$nodeKey) {
                                //读取根节点的所有非文本类型的子节点
                                self::htmlFormat($nodeKey, $temp, null, false);
                            }
                            $matchFilterNodeKeys = $temp;
                        }
                        break;
                    //逗号分组
                    case 'group'  :
                        //本分组只读取第一条记录
                        if (
                            //倒序模式下
                            isset($env['selectParse'][$temp = $selectK - 1]['list'][0]['list']) &&
                            //倒序模式下第一个值是ID类型
                            $env['selectParse'][$temp]['list'][0]['list'][0]['type'] === 'id' ||
                            //过滤模式下第一个值是ID类型
                            $env['selectParse'][$temp]['list'][0]['type'] === 'id'
                        ) {
                            $matchFilterNodeKeys = array_slice($matchFilterNodeKeys, 0, 1, true);
                        }
                        $env['rNodeKeys'] += $matchFilterNodeKeys;
                        //重新初始化话过滤节点
                        $matchFilterNodeKeys = $matchAllNodeKeys;
                        break;
                }
            }
        }
        //不分组去重排序
        self::nodeKeysUniqueSort($env['rNodeKeys'], false);
        return $env['rNodeKeys'];
    }

    /**
     * 描述 : 按规则过滤伪类或属性
     * 参数 :
     *     &nodeKeys   : 节点列表
     *     &filterList : 过滤列表 [{'type' : filter=过滤属性,pseudo=过滤伪类, 'list' : 参看filterAttrNodeKeys和filterPseudoNodeKeys结构}, ...]
     * 作者 : Edgar.lee
     */
    private static function filterNodeKeys(&$nodeKeys, &$filterList) {
        foreach ($filterList as &$filter) {
            //没有数据之间返回
            if (!count($nodeKeys)) return;
            //指定伪类
            if ($filter['type'] === 'pseudo') {
                self::filterPseudoNodeKeys($nodeKeys, $filter);
            //包含tag,ID,attr
            } else {
                self::filterAttrNodeKeys($nodeKeys, $filter);
            }
        }
    }

    /**
     * 描述 : 过滤属性节点键
     * 参数 :
     *     &nodeKeys : 节点列表
     *     &attrList : 属性过滤列表 {
     *          'type'  : 过滤类型,id=指定ID(返回1或0个),tag=指定标签名,attr=过滤属性
     *          'value' : 过滤的值
     *          'name'  : type=attr使用,属性名
     *          'param' : type=attr使用,null=仅判断属性存在,'='=等于指定值,'!'=不等于指定值,'~'=空格分割指定值,'*'=包含指定值,'^'=以指定值开始,'$'=以指定值结尾,'|'=以指定前缀
     *      }
     * 作者 : Edgar.lee
     */
    private static function filterAttrNodeKeys(&$nodeKeys, &$attrList) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        foreach ($nodeKeys as $k => &$nodeKey) {
            switch ($attrList['type']) {
                //指定标签名
                case 'tag' :
                    if ($attrList['value'] !== '*' && $parseNode[$nodeKey]['tagName'] !== $attrList['value']) {
                        unset($nodeKeys[$k]);
                    }
                    break;
                //指定标ID
                case 'id'  :
                    if (
                        //不存在ID属性
                        !isset($parseNode[$nodeKey]['attr']['id']) ||
                        //属性不匹配
                        $parseNode[$nodeKey]['attr']['id'] !== $attrList['value']
                    ) {
                        unset($nodeKeys[$k]);
                    }
                    break;
                //指定标签属性
                case 'attr':
                    //正则匹配
                    if ($attrList['name'][0] === '@') {
                        //读取全部属性
                        $attrs = self::nodeAttr($nodeKey, null);
                        foreach ($attrs as $ka => &$va) {
                            //匹配成功 跳出
                            if (preg_match($attrList['name'], $ka . '=' . $va)) break 2;
                        }
                        unset($nodeKeys[$k]);
                    //存在属性
                    } else if (is_string($attrValue = self::nodeAttr($nodeKey, $attrList['name']))) {
                        if (!(
                            //仅判断存在
                            $attrList['param'] === null || (
                                //等于指定值
                                $attrList['param'] === '=' &&
                                $attrValue === $attrList['value']
                            ) || (
                                //不等于指定值
                                $attrList['param'] === '!' &&
                                $attrValue !== $attrList['value']
                            ) || (
                                //空格分割指定值
                                $attrList['param'] === '~' &&
                                in_array($attrList['value'], explode(' ', $attrValue), true)
                            ) || (
                                //包含指定值
                                $attrList['param'] === '*' &&
                                strpos($attrValue, $attrList['value']) !== false
                            ) || (
                                //以指定值开始
                                $attrList['param'] === '^' &&
                                strncmp($attrValue, $attrList['value'], strlen($attrList['value'])) === 0
                            ) || (
                                //以指定值结尾
                                $attrList['param'] === '$' &&
                                substr($attrValue, -strlen($attrList['value'])) === $attrList['value']
                            ) || (
                                //指定前缀
                                $attrList['param'] === '|' &&
                                (
                                    ($temp = substr($attrValue, 0, strlen($attrList['value']) + 1)) === $attrList['value'] ||
                                    $temp === $attrList['value'] . '-'
                                )
                            )
                        )) {
                            unset($nodeKeys[$k]);
                        }
                    } else if ($attrList['param'] !== '!') {
                        unset($nodeKeys[$k]);
                    }
                    break;
            }
        }
    }

    /**
     * 描述 : 过滤伪类节点键(未实现与样式有关的伪类)
     * 参数 :
     *     &nodeKeys   : 节点列表
     *      pseudoList : 伪类过滤列表 {
     *          'value' : button      = 选择input[type=button]或button标签
     *                    checkbox    = 选择input[type=checkbox]标签
     *                    file        = 选择input[type=file]标签
     *                    password    = 选择input[type=password]元素
     *                    radio       = 选择input[type=radio]元素
     *                    reset       = 选择input[type=reset]元素
     *                    text        = 选择input[type=text]元素
     *                    checked     = 选择input[type=checkbox]或input[type=radio]标签有checked属性的节点
     *                    image       = 选择input[type=image]或image的节点
     *                    input       = 选择input,textarea,select,button的节点
     *                    selected    = 选择option[selected]元素
     *                    submit      = 选择input[type=submit]或button[type=submit]元素
     *                    header      = 选择h1-6的节点
     *                    disabled    = 选择button,input,optgroup,option,select,textarea标签有disabled属性节点
     *                    contains    = 选择所有包含指定文本的元素(读取文本然后查询)
     *                    empty       = 选择无任何子节点的节点
     *                    only-child  = 选择所有其父元素下只有一个子元素的元素
     *                    parent      = 选择所有含有子元素或者文本的父级元素
     *                    enabled     = 选择button,input,optgroup,option,select,textarea标签无disabled属性节点
     *                    eq          = 选择一个给定索引值的元素(从 0 开始计数)
     *                    gt          = 选择所有大于给定索引值的元素
     *                    lt          = 选择所有小于给定索引值的元素
     *                    first       = 选择第一个匹配的元素
     *                    last        = 选择最后一个匹配的元素
     *                    even        = 选择偶数元素,从 0 开始计数
     *                    first-child = 选择所有父级元素下的第一个子元素
     *                    last-child  = 选择所有父级元素下的最后一个子元素
     *                    nth-child   = 每个相匹配子元素的所引值,从1开始,也可以是字符串 even 或 odd,或一个方程式( 例如 :nth-child(even),:nth-child(4n) )
     *                    odd         = 选择奇数元素,从 0 开始计数
     *                    has         = 选择子孙节点含有选择器所匹配的至少一个元素的元素
     *                    not         = 选择所有不匹配给定选择器的元素
     *          'param' : 为对应伪类提供的参数
     *      }
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    private static function filterPseudoNodeKeys(&$nodeKeys, $pseudoList) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //伪类重置
        switch ($pseudoList['value']) {
            case 'first'       :
                $pseudoList['value'] = 'eq';
                $pseudoList['param'] = 0;
                break;
            case 'last'        :
                $pseudoList['value'] = 'eq';
                $pseudoList['param'] = -1;
                break;
            case 'even'        :
            case 'odd'         :
                $pseudoList['value'] = 'nth-child';
                $pseudoList['param'] = $pseudoList['value'];
                break;
            case 'first-child' :
                $pseudoList['value'] = 'nth-child';
                $pseudoList['param'] = 1;
                break;
            case 'last-child' :
                $pseudoList['value'] = 'nth-child';
                $pseudoList['param'] = -1;
                break;
        }

        switch ($pseudoList['value']) {
            //选择input[type=button]或button标签
            case 'button'     :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (!(
                        //input标签
                        $parseNode[$nodeKey]['tagName'] === 'input' &&
                        //type属性为button
                        self::nodeAttr($nodeKey, 'type') === 'button' ||
                        //为button标签
                        $parseNode[$nodeKey]['tagName'] === 'button'
                    )) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择input[type=checkbox]标签
            case 'checkbox'   :
            //选择input[type=file]标签
            case 'file'       :
            //选择input[type=password]元素
            case 'password'   :
            //选择input[type=password]元素
            case 'radio'      :
            //选择input[type=password]元素
            case 'text'       :
                $temp = array(array(
                    'type'  => 'tag',
                    'value' => 'input'
                ), array(
                    'type'  => 'attr',
                    'value' => $pseudoList['value'],
                    'name'  => 'type',
                    'param' => '='
                ));
                self::filterNodeKeys($nodeKeys, $temp);
                break;
            //选择input[type=checkbox]或input[type=radio]标签有checked属性的节点
            case 'checked'    :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (!(
                        //input标签
                        $parseNode[$nodeKey]['tagName'] === 'input' &&
                        //存在checked属性
                        self::nodeAttr($nodeKey, 'checked') !== null &&
                        (
                            //多选
                            ($temp = self::nodeAttr($nodeKey, 'type')) === 'checkbox' ||
                            //单选
                            $temp === 'radio'
                        )
                    )) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择所有包含指定文本的元素
            case 'contains'   :
                //引用块级标签
                $blockTag = &self::$blockTag;
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (strpos(self::nodeAttr($nodeKey, 'textContent'), $pseudoList['param']) === false) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择button,input,optgroup,option,select,textarea标签有disabled属性节点
            case 'disabled'   :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    switch ($parseNode[$nodeKey]['tagName']) {
                        case 'button'  :
                        case 'input'   :
                        case 'optgroup':
                        case 'option'  :
                        case 'select'  :
                        case 'textarea':
                            if (!isset($parseNode[$nodeKey]['attr']['disabled'])) {
                                //删除节点键
                                unset($nodeKeys[$k]);
                            }
                            break;
                        default        :
                            //删除节点键
                            unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择无任何子节点的节点
            case 'empty'      :
            //选择所有其父元素下只有一个子元素的元素
            case 'only-child' :
                $temp = intval($pseudoList['value'] === 'only-child');
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (count(self::nodeConn($nodeKey, 'child', false, true)) !== $temp) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择所有含有子元素或者文本的父级元素
            case 'parent'     :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (count(self::nodeConn($nodeKey, 'child', false, true)) === 0) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择button,input,optgroup,option,select,textarea标签有disabled属性节点
            case 'enabled'    :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    switch ($parseNode[$nodeKey]['tagName']) {
                        case 'button'  :
                        case 'input'   :
                        case 'optgroup':
                        case 'option'  :
                        case 'select'  :
                        case 'textarea':
                            if (isset($parseNode[$nodeKey]['attr']['disabled'])) {
                                //删除节点键
                                unset($nodeKeys[$k]);
                            }
                            break;
                        default        :
                            //删除节点键
                            unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择一个给定索引值的元素(从 0 开始计数)
            case 'eq'         :
                $nodeKeys = array_slice($nodeKeys, $pseudoList['param'], 1, true);
                break;
            //选择所有大于给定索引值的元素
            case 'gt'         :
                $nodeKeys = array_slice($nodeKeys, $pseudoList['param'], null, true);
                break;
            //选择所有小于给定索引值的元素
            case 'lt'         :
                $nodeKeys = array_slice($nodeKeys, 0, $pseudoList['param'], true);
                break;
            //选择input[type=image]或image的节点
            case 'image'      :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (
                        //是图片类型input标签
                        ($parseNode[$nodeKey]['tagName'] === 'input' &&
                        self::nodeAttr($nodeKey, 'type') !== 'image') ||
                        //不是图片标签
                        ($parseNode[$nodeKey]['tagName'] !== 'image' &&
                        $parseNode[$nodeKey]['tagName'] !== 'input')
                    ) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择input,textarea,select,button的节点
            case 'input'      :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    switch ($parseNode[$nodeKey]['tagName']) {
                        case 'button'  :
                        case 'input'   :
                        case 'select'  :
                        case 'textarea':
                            break;
                        default        :
                            //删除节点键
                            unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择option[selected]元素
            case 'selected'   :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if (
                        //不为option标签
                        $parseNode[$nodeKey]['tagName'] !== 'option' ||
                        //没有selected属性
                        self::nodeAttr($nodeKey, 'selected') === null
                    ) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择input[type=submit]或button[type=submit]元素
            case 'submit'     :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    if ((
                        ($parseNode[$nodeKey]['tagName'] === 'input' ||
                        //是提交类型input或button标签
                        $parseNode[$nodeKey]['tagName'] === 'button') &&
                        self::nodeAttr($nodeKey, 'type') !== 'submit'
                    ) || (
                        //不是input,button标签
                        $parseNode[$nodeKey]['tagName'] !== 'button' &&
                        $parseNode[$nodeKey]['tagName'] !== 'input'
                    )) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
                break;
            //选择h1-6的节点
            case 'header'     :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    switch ($parseNode[$nodeKey]['tagName']) {
                        case 'h1':
                        case 'h2':
                        case 'h3':
                        case 'h4':
                        case 'h5':
                        case 'h6':
                            break;
                        default        :
                            //删除节点键
                            unset($nodeKeys[$k]);
                    }
                }
                break;
            //每个相匹配子元素的所引值,从1开始,也可以是字符串 even 或 odd,或一个方程式( 例如 :nth-child(even),:nth-child(4n) )
            case 'nth-child'  :
                $pseudoList['param'] = strtolower($pseudoList['param']);
                //缓存计算
                $cacheCount = array();
                //匹配列表
                $macthList = array();
                if ($pseudoList['param'] === 'even') {
                    $pseudoList['param'] = '2n';
                } elseif ($pseudoList['param'] === 'odd') {
                    $pseudoList['param'] = '2n+1';
                } elseif (strpos($pseudoList['param'], 'n') === false) {
                    $pseudoList['param'] = (int)$pseudoList['param'] - 1;
                }

                foreach ($nodeKeys as $k => &$nodeKey) {
                    //没有父类
                    if (($temp = self::nodeConn($nodeKey, 'parent', 0, true)) === null) {
                        if (
                            //是常规节点
                            $parseNode[$nodeKey]['nodeType'] === 'node' &&
                            //不是文本类节点
                            $parseNode[$nodeKey]['tagName'][0] !== '!'
                        ) {
                            $siblings = array($nodeKey);
                        } else {
                            $siblings = array();
                        }
                    } else {
                        $siblings = self::nodeConn($temp, 'child');
                    }

                    //读取一个节点键
                    if (is_int($pseudoList['param'])) {
                        //支持负数
                        if ($pseudoList['param'] < 0) {
                            $pseudoList['param'] = count($siblings) + $pseudoList['param'] + 1;
                        }
                        if (isset($siblings[$pseudoList['param']])) {
                            $macthList[] = $siblings[$pseudoList['param']];
                        }
                    //读取多个节点键
                    } else {
                        //读取指定规则的节点
                        for ($i = 0, $iL = count($siblings); $i < $iL; ++$i) {
                            $temp = @eval('return ' . strtr($pseudoList['param'], array('n' => '*' . $i)) . ';');
                            //没正确执行 || 数据无效
                            if ($temp === false || $temp >= $iL) {
                                break;
                            //保存匹配节点
                            } else {
                                $macthList[] = $siblings[$temp];
                            }
                        }
                    }
                }

                $nodeKeys = array_intersect($nodeKeys, $macthList);
                break;
            //选择子孙节点含有选择器所匹配的至少一个元素的元素
            case 'has'        :
                foreach ($nodeKeys as $k => &$nodeKey) {
                    $temp = self::selectors(array($nodeKey), $pseudoList['param'], true);
                    if (!isset($temp[0])) {
                        //删除节点键
                        unset($nodeKeys[$k]);
                    }
                }
            //选择所有不匹配给定选择器的元素
            case 'not'        :
                $temp = self::selectors($nodeKeys, $pseudoList['param'], false);
                $nodeKeys = array_diff($nodeKeys, $temp);
                break;
        }
    }

    /**
     * 描述 : 匹配selectors传入的关键词
     * 参数 :
     *     &env     : selectors方法的$env变量
     *      runList : true(默认)=执行匹配列表,false=不执行
     * 作者 : Edgar.lee
     */
    private static function matchKeyword(&$env, $runList = true) {
        if ($substr = trim(substr($env['selector'], $env['nowPos'], $env['nMatchPos']['position'] - $env['nowPos']))) {
            switch ($env['temp']['selectType']) {
                //分析标签
                case 't':
                    $env['temp']['selectAttr']['list'][] = array(
                        'type'  => 'tag',
                        'value' => strtolower($substr)
                    );
                    break;
                //分析ID
                case '#':
                    $env['temp']['selectAttr']['list'][] = array(
                        'type'  => 'id',
                        'value' => $substr
                    );
                    break;
                //分析样式
                case '.':
                    $env['temp']['selectAttr']['list'][] = array(
                        'type'  => 'attr',
                        'value' => $substr,
                        'param' => '~',
                        'name'  => 'class',
                    );
                    break;
            }
        }

        if ($runList) {
            //如果需要分组
            if ($env['temp']['selectGroup']) {
                //selectList不空
                if (isset($env['temp']['selectList']['list'][0])) {
                    //保存倒序到解析列表中
                    $env['selectParse'][] = $env['temp']['selectList'];
                    //清空选择列表
                    $env['temp']['selectList']['list'] = array();
                }
                //保存倒序到解析列表中
                $env['selectParse'][] = $env['temp']['selectAttr'];
            } elseif ($env['temp']['selectAttr']['list'] !== null) {
                array_unshift($env['temp']['selectList']['list'], $env['temp']['selectAttr']);
            }
            $env['temp']['selectAttr']['list'] = null;
            $env['temp']['selectGroup'] = false;
        }
    }

    /**
     * 描述 : 需找下一个右括号'('或'['时调用有效
     * 参数 :
     *     &str   : 查询支付串
     *      match : 匹配位置{'match' : '('或'[', 'position' : match所在位置}
     * 返回 :
     *      失败返回null,成功返回查询位置
     * 作者 : Edgar.lee
     */
    private static function getNextBrackets(&$str, $match) {
        if (($brackets = $match['match']) === '[' || $brackets === '(') {
            //括号数量
            $bracketsNum = 0;
            //开始查询位置
            $nowPos = $match['position'] + 1;
            //反括号
            $antiBrackets = $brackets === '(' ? ')' : ']';
            //当前匹配
            $nMatches = null;
            //默认匹配
            $dMatches = array(
                //引号
                '\''          => false,
                //引号
                '"'           => false,
                //查询括号
                $brackets     => false,
                //对应的反括号
                $antiBrackets => false,
            );
            while ($match = of_base_com_str::strArrPos($str, $nMatches === null ? $dMatches : $nMatches, $nowPos)) {
                switch ($match['match']) {
                    //正括号
                    case $brackets    :
                        //括号数量加1
                        $bracketsNum += 1;
                        break;
                    //反括号
                    case $antiBrackets:
                        //括号数量为零,返回匹配位置
                        if ($bracketsNum === 0) {
                            return $match['position'];
                        //括号位置大于零,括号数量减1
                        } else {
                            $bracketsNum -= 1;
                        }
                        break;
                    //引号
                    case '"'          :
                    case '\''         :
                        if (!$match = of_base_com_str::strArrPos(
                            $str,
                            array($match['match'] => true),
                            $match['position'] + 1
                        )) {
                            //查询失败
                            return ;
                        }
                        break;
                }
                $nowPos = strlen($match['match']) + $match['position'];
            }
        }
    }

    /**
     * 描述 : 节点键去重排序
     * 参数 :
     *     &nodeKeys : 指定排序
     *      isGroup  : true=返回二维分组数据,false(默认)=将分组数据合并
     *      docKey   : 指定当节点键,会将文档节点的元素放到第一位置
     * 作者 : Edgar.lee
     */
    private static function nodeKeysUniqueSort(&$nodeKeys, $isGroup = false, $docKey = null) {
        //去重节点列表
        $nodeKeys = array_flip(array_flip($nodeKeys));
        //父类缓存信息
        $cacheNodeParentKeys = null;
        //分组排序节点键{根节点键 : [排序节点键, ...], ...}
        $groupNodeKeys = array($docKey => null);

        foreach ($nodeKeys as &$nodeKey) {
            //读取父节点列表
            $cacheNodeParentKeys[$nodeKey] = array_reverse(self::nodeConn($nodeKey, 'parent', false, true));
            //追加子节点
            $cacheNodeParentKeys[$nodeKey][] = &$nodeKey;
            //拆分成不同组
            $groupNodeKeys[$cacheNodeParentKeys[$nodeKey][0]][] = &$nodeKey;
        }
        if ($groupNodeKeys[$docKey] === null) {
            unset($groupNodeKeys[$docKey]);
        }

        //初始化自定义排序方法
        self::twoNodeKeySort($cacheNodeParentKeys, false);
        //分组排序
        foreach ($groupNodeKeys as &$group) {
            usort($group, array(__CLASS__, 'twoNodeKeySort'));
        }

        if ($isGroup) {
            $nodeKeys = $groupNodeKeys;
        } else {
            //至少两个数组时
            if (($temp = count($groupNodeKeys)) > 1) {
                //合并分组结构
                $nodeKeys = call_user_func_array('array_merge', $groupNodeKeys);
            //只有一个分组
            } else if ($temp === 1) {
                $nodeKeys = array_shift($groupNodeKeys);
            //没有值返回空数组
            } else {
                $nodeKeys = $groupNodeKeys;
            }
        }
    }

    /**
     * 描述 : 比对两个节点的先后顺序(仅由nodeKeysUniqueSort调用)
     * 参数 :
     *      a : 第一个节点键
     *      b : 第二个节点键
     * 返回 :
     *      a在b前返回1,否则返回-1
     * 作者 : Edgar.lee
     */
    public static function twoNodeKeySort($a = null, $b = false) {
        //缓存节点父节点键
        static $cacheNodeParentKeys = null;
        //缓存节点弟点键
        static $cacheNodeNextKeys = null;
        //清空缓存
        if ($b === false) {
            $cacheNodeParentKeys = $a;
            $cacheNodeNextKeys = null;
        } else {
            //读取所有父节点
            if (!isset($cacheNodeParentKeys[$a])) {
                //读取父节点列表
                $cacheNodeParentKeys[$a] = array_reverse(self::nodeConn($a, 'parent', false, true));
                //追加子节点
                $cacheNodeParentKeys[$a][] = $a;
            }
            //读取所有父节点
            if (!isset($cacheNodeParentKeys[$b])) {
                //读取父节点列表
                $cacheNodeParentKeys[$b] = array_reverse(self::nodeConn($b, 'parent', false, true));
                //追加子节点
                $cacheNodeParentKeys[$b][] = $b;
            }

            //引用父节点列表
            $apList = &$cacheNodeParentKeys[$a];
            //引用父节点列表
            $bpList = &$cacheNodeParentKeys[$b];
            //父节点列表长度
            $apListLen = count($apList);
            //父节点列表长度
            $bpListLen = count($bpList);
            //引用一个节点键
            $apIndex = null;
            //引用一个节点键
            $bpIndex = null;

            //比对父节点
            for ($i = 0; $i < $apListLen && $i < $bpListLen; ++$i) {
                $apIndex = &$apList[$i];
                $bpIndex = &$bpList[$i];
                //父节点不相同,则判断兄弟关系
                if ($apIndex !== $bpIndex) {
                    //读取全部弟节点,并反转
                    if (!isset($cacheNodeNextKeys[$apIndex])) {
                        $cacheNodeNextKeys[$apIndex] = array_flip(self::nodeConn($apIndex, 'next', false, true));
                    }
                    //a在b前
                    if (isset($cacheNodeNextKeys[$apIndex][$bpIndex])) {
                        return -1;
                    } else {
                        return 1;
                    }
                }
            }

            //a是b的父节点 ? -1 : 1
            return $i === $apListLen ? -1 : 1;
        }
    }

    /**                                                                                     工具区
     * 描述 : 读取指定节点键属性
     * 参数 :
     *      nodeKey : 指定遍历的节点键
     *      attr    : 属性名称,null=读取所有真实属性
     *      value   : 字符串=设置属性,null(默认)=读取属性,false=删除属性
     *      mode    : value为null时有效, true=补全未闭合标签, false=按原解析方式
     * 返回 :
     *      返回读取数据,未读到返回null
     * 作者 : Edgar.lee
     */
    public static function nodeAttr($nodeKey, $attr = null, $value = null, $mode = true) {
        //引用节点
        $parseNode = &self::$parseNode;
        if (isset($parseNode[$nodeKey])) {
            $node = &$parseNode[$nodeKey];
            //读取所有属性
            if ($attr === null) {
                return $node['attr'];
            //读取指定属性
            } else if ($value === null) {
                $temp = array();
                switch ($attr) {
                    case 'tagName'    :
                        return $node['tagName'];
                    case 'innerHTML'  :
                        self::htmlFormat($nodeKey, $temp, true, $mode);
                        return join($temp);
                    case 'outerHTML'  :
                        //创建临时节点
                        $parseNode[$tKey = ++self::$nodeCount] = self::$defaultNode;
                        //只有当前子节点
                        $parseNode[$tKey]['cKeys'] = array(&$nodeKey);
                        self::htmlFormat($tKey, $temp, true, $mode);
                        unset($parseNode[$tKey]);
                        return join($temp);
                    case 'textContent':
                        self::htmlFormat($nodeKey, $temp, '!text');
                        return self::entities(join($temp), true);
                    case 'checked'    :
                        if (
                            //存在checked属性
                            isset($node['attr']['checked']) &&
                            //存在name属性
                            isset($node['attr']['name']) &&
                            //是input标签
                            $node['tagName'] === 'input' &&
                            //存在type属性
                            isset($node['attr']['type']) &&
                            //是单选标签
                            $node['attr']['type'] === 'radio'
                        ) {
                            //存在父节点
                            if (($parentNodeKey = self::nodeConn($nodeKey, 'parent', -1, true)) !== null) {
                                //读取所有数据
                                self::htmlFormat($parentNodeKey, $temp, null);
                                //保留当前节点键之后的节点键
                                array_splice($temp, 0, array_search($nodeKey, $temp) + 1);
                                foreach ($temp as &$v) {
                                    //当前节点之后有已选中节点返回null
                                    if (
                                        //存在checked属性
                                        isset($parseNode[$v]['attr']['checked']) &&
                                        //存在name属性
                                        isset($parseNode[$v]['attr']['name']) &&
                                        //name属性与当前节点相同
                                        $parseNode[$v]['attr']['name'] === $node['attr']['name'] &&
                                        //是input标签
                                        $parseNode[$v]['tagName'] === 'input' &&
                                        //存在type属性
                                        isset($parseNode[$v]['attr']['type']) &&
                                        //是单选标签
                                        $parseNode[$v]['attr']['type'] === 'radio'
                                    ) {
                                        unset($node['attr']['checked']);
                                        return null;
                                    }
                                }
                            }
                        }
                        break;
                    case 'selected'   :
                        if (
                            //存在selected属性
                            isset($node['attr']['selected']) &&
                            //是option标签
                            $node['tagName'] === 'option'
                        ) {
                            //全部父节点
                            $parentNodeKeys = self::nodeConn($nodeKey, 'parent', false, true);
                            //存在父节点
                            if (isset($parentNodeKeys[0])) {
                                foreach ($parentNodeKeys as &$parentNodeKey) {
                                    if ($parseNode[$parentNodeKey]['tagName'] === 'select') {
                                        if (!isset($parseNode[$parentNodeKey]['attr']['multiple'])) {
                                            //读取所有数据
                                            self::htmlFormat($parentNodeKey, $temp, null);
                                            //保留当前节点键之后的节点键
                                            array_splice($temp, 0, array_search($nodeKey, $temp) + 1);
                                            foreach ($temp as &$v) {
                                                //当前节点之后有已选中节点返回null
                                                if (
                                                    //存在checked属性
                                                    isset($parseNode[$v]['attr']['selected']) &&
                                                    //是input标签
                                                    $parseNode[$v]['tagName'] === 'option'
                                                ) {
                                                    unset($node['attr']['selected']);
                                                    return null;
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                }

                (!$attr || $attr[0] !== '>') && $attr = strtolower($attr);
                if (isset($node['attr'][$attr])) {
                    return $node['attr'][$attr];
                }
            //删除属性
            } else if ($value === false) {
                unset($node['attr'][strtolower($attr)]);
            } else if (
                //碎片节点仅允许修改innerHTML和textContent
                ($node['nodeType'] === 'node' || $attr === 'innerHTML' || $attr === 'textContent') &&
                //不能修改关闭节点
                $node['tagName'][0] !== '/' &&
                //不为文本类节点
                ($node['tagName'][0] !== '!' || $attr === '') &&
                //不修内部属性
                (!$attr || $attr[0] !== '>')
            ) {
                switch ($attr) {
                    case 'innerHTML'  :
                        //允许有子节点
                        if (self::hasChildTag($node['tagName']) > 1) {
                            self::nodeSplice(self::htmlParse($value), $nodeKey, null);
                        }
                        break;
                    //替换当前节点
                    case 'outerHTML'  :
                        self::nodeSplice(self::htmlParse($value), $parseNode[$nodeKey]['pKey'], $nodeKey);
                        self::nodeSplice($nodeKey);
                        break;
                    case 'textContent':
                        //允许有子节点
                        if (self::hasChildTag($node['tagName']) > 1) {
                            //新文本节点键
                            $parseNode[$temp = ++self::$nodeCount] = array(
                                'attr'    => array(
                                    ''    => self::entities($value, false)
                                ),
                                'tagName' => '!text'
                            ) + self::$defaultNode;
                            self::nodeSplice($temp, $nodeKey, null);
                        }
                        break;
                    default           :
                        $node['attr'][strtolower($attr)] = (string)$value;
                }
            }
        }
    }

    /**
     * 描述 : 读取与指定节点相关系的节点
     * 参数 :
     *      nodeKey  : 指定查询的节点键
     *      type     : 查询类型,sibling=不包含自己全部兄弟节点,next=之后的兄弟节点,prev=之前的兄弟节点,parent=父节点,child=子节点
     *      needle   : 对结果集进行筛选,false(默认)=不筛选返回数组,数字=取出指定位置的节点键(支持负数)
     *      textNode : 是否包含文本或碎片节点,false(默认)=不包含,true=包含
     *      autoKey  : 自增键,needle为false时有效,true(默认)=连续的键值,false=以节点键为键
     * 返回 :
     *      needle=false,返回一个数组,未查到返回空数组
     *      needle=数字, 返回一个节点键,未查到返回null
     * 作者 : Edgar.lee
     */
    public static function nodeConn($nodeKey, $type, $needle = false, $textNode = false, $autoKey = true) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //返回数据
        $rDate = array();

        if (isset($parseNode[$nodeKey])) {
            switch ($type) {
                //不包含自己全部兄弟节点
                case 'sibling':
                    if ($parseNode[$nodeKey]['pKey'] !== null) {
                        //父节点的全部子节点
                        $rDate = $parseNode[$parseNode[$nodeKey]['pKey']]['cKeys'];
                        //删除指定节点并重新生成角标
                        array_splice($rDate, array_search($nodeKey, $rDate), 1);
                    }
                    break;
                //之后的兄弟节点
                case 'next'   :
                    if ($parseNode[$nodeKey]['pKey'] !== null) {
                        //父节点的全部子节点
                        $rDate = $parseNode[$parseNode[$nodeKey]['pKey']]['cKeys'];
                        $rDate = array_slice($rDate, array_search($nodeKey, $rDate) + 1);
                    }
                    break;
                //之前的兄弟节点
                case 'prev'   :
                    if ($parseNode[$nodeKey]['pKey'] !== null) {
                        //父节点的全部子节点
                        $rDate = $parseNode[$parseNode[$nodeKey]['pKey']]['cKeys'];
                        $rDate = array_reverse(array_slice($rDate, 0, array_search($nodeKey, $rDate)));
                    }
                    break;
                //父节点
                case 'parent' :
                    $temp = $nodeKey;
                    //父节点存在
                    while ($parseNode[$temp]['pKey'] !== null) {
                        $rDate[] = $temp = $parseNode[$temp]['pKey'];
                    }
                    break;
                //子节点
                case 'child'  :
                    $rDate = $parseNode[$nodeKey]['cKeys'];
                    break;
            }

            //删除文本节点
            if ($textNode === false) {
                foreach ($rDate as $k => &$v) {
                    if (
                        //清除文本类节点
                        $parseNode[$v]['tagName'][0] === '!' ||
                        //清除碎片节点
                        $parseNode[$v]['nodeType'] === 'fragment'
                    ) {
                        unset($rDate[$k]);
                    }
                }
                //重新生成角标
                array_splice($rDate, 0, 0);
            }
        }

        //返回指定数据
        if ($needle !== false) {
            //计算实际角标
            $needle = $needle >= 0 ? (int)$needle : count($rDate) + (int)$needle;
            $rDate = isset($rDate[$needle]) ? $rDate[$needle] : null;
        } else if ($autoKey === false) {
            $temp = array();
            foreach ($rDate as &$v) {
                $temp[$v] = $v;
            }
            $rDate = &$temp;
        }

        return $rDate;
    }

    /**
     * 描述 : 移除或插入指定节点
     * 参数 :
     *      nNodeKey : 指定移除或插入的节点键
     *      pNodeKey : null=移除节点,数字=插入到的目标节点键(碎片插入仅文本子节点时,除碎片子节点后挑一级文本节点插入)
     *      insType  : 插入时有效,true=插入头部,false(默认)=插入尾部,数字=插入指定节点键前,null=替换插入(移除目标子节点后插入)
     * 作者 : Edgar.lee
     */
    public static function nodeSplice($nNodeKey, $pNodeKey = null, $insType = false) {
        //解析节点引用
        $parseNode = &self::$parseNode;

        //移除节点
        if ($pNodeKey === null) {
            if (isset($parseNode[$nNodeKey]) && ($temp = $parseNode[$nNodeKey]['pKey']) !== null) {
                //移除父节点对子节点关系
                array_splice(
                    $parseNode[$temp]['cKeys'],
                    array_search($nNodeKey, $parseNode[$temp]['cKeys']),
                    1
                );
                $parseNode[$nNodeKey]['pKey'] = null;

                //回收删除节点
                $temp = array($nNodeKey);
                self::nodeCollection($temp);
            }
        //插入节点
        } else if (
            //子节点键有效
            isset($parseNode[$nNodeKey]) &&
            //目标节点键有效
            isset($parseNode[$pNodeKey]) &&
            //目标节点可以有子节点
            self::hasChildTag($parseNode[$pNodeKey]['tagName']) > 1 && (
                //替换操作
                $insType === null ||
                //true=插入第一个位置,false=插入最后一个位置
                is_bool($insType) ||
                //插入指定节点前(参考节点有效 && 是目标节点的一级子节点)
                (isset($parseNode[$insType]) && $pNodeKey === $parseNode[$insType]['pKey'])
            )
        ) {
            $temp = $pNodeKey;
            //pNodeKey不能为nNodeKey或nNodeKey的父节点
            do {
                if ($temp === $nNodeKey) {
                    return ;
                }
            } while (($temp = $parseNode[$temp]['pKey']) !== null);

            //常规节点
            if ($parseNode[$nNodeKey]['nodeType'] === 'node') {
                //移动节点列表
                $insertList = array($nNodeKey);
                //节点未处于移除状态
                if (($temp = $parseNode[$nNodeKey]['pKey']) !== null) {
                    //移除父节点对子节点关系
                    array_splice(
                        $parseNode[$temp]['cKeys'],
                        array_search($nNodeKey, $parseNode[$temp]['cKeys']),
                        1
                    );
                }
            //碎片节点
            } else {
                //移动节点列表
                $insertList = $parseNode[$nNodeKey]['cKeys'];
                //移除父节点对子节点关系
                $parseNode[$nNodeKey]['cKeys'] = array();

                //回收空碎片节点
                $temp = array($nNodeKey);
                self::nodeCollection($temp);
            }

            //不是单节点,并且没有子节点(仅有文本节点)
            if (self::hasChildTag($parseNode[$pNodeKey]['tagName']) & 6) {
                $temp = array();

                foreach ($insertList as $k => &$nodeKey) {
                    //移除子与父节点映射关系
                    $parseNode[$nodeKey]['pKey'] = null;
                    //过滤出文本节点,仅将文本节点插入父节点
                    if ($parseNode[$nodeKey]['tagName'] !== '!text') {
                        unset($insertList[$temp[] = $k]);
                    }
                }

                //回收空碎片节点
                $temp && self::nodeCollection($temp);
            }

            //替换操作
            if ($insType === null) {
                $insPos = 0;
                $temp = $parseNode[$pNodeKey]['cKeys'];

                //移除目标节点全部子节点
                foreach ($temp as &$nodeKey) self::nodeSplice($nodeKey);

                //回收被替换的节点
                self::nodeCollection($temp);
            //插入头部
            } else if ($insType === true) {
                $insPos = 0;
            //插入尾部
            } else if ($insType === false) {
                $insPos = count($parseNode[$pNodeKey]['cKeys']);
            //插入指定节点位置
            } else {
                $insPos = array_search($insType, $parseNode[$pNodeKey]['cKeys']);
            }

            //更新目标节点的子节点
            array_splice($parseNode[$pNodeKey]['cKeys'], $insPos, 0, $insertList);
            //更新nNodeKey节点的父节点
            foreach ($insertList as &$nodeKey) {
                $parseNode[$nodeKey]['pKey'] = $pNodeKey;
            }
        }
    }

    /**
     * 描述 : 判断是否有子节点标签
     * 参数 :
     *      name : 标签名
     * 返回 :
     *      1=是单标签, 2=只包含文本, 4=可注释和文本, 8=可以有子标签
     * 作者 : Edgar.lee
     */
    private static function hasChildTag($name) {
        if (isset(self::$notChrTag[$name])) {
            return self::$notChrTag[$name];
        } else {
            return $name[0] === '/' ? 1 : 8;
        }
    }

    /**
     * 描述 : html实体转换
     * 参数 :
     *      str    : 指定转换的字符串
     *      type   : true=文本转换html,false=html转换文本
     * 返回 :
     *      转换后的字符串
     * 作者 : Edgar.lee
     */
    private static function entities($str, $type) {
        if ($type) {
            return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        } else {
            return htmlentities($str, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * 描述 : 遍历指定节点的子节点,返回格式化的数组
     * 参数 :
     *      nodeKey : 指定遍历的节点键
     *     &dataArr : 接收返回的数据
     *      type    : true=接收html的字符串,false(默认)=接收所有节点键,null=非文本节类点,字符串=指定标签名
     *      autoKey : 
     *          type 为 true时: 闭合标签方式, true=补全未闭合标签, false=按原解析方式
     *          type不为true时: 自增键, true=连续的键值, false=以节点键为键
     * 作者 : Edgar.lee
     */
    private static function htmlFormat($nodeKey, &$dataArr, $type = false, $autoKey = true) {
        //解析节点引用
        $parseNode = &self::$parseNode;

        foreach ($parseNode[$nodeKey]['cKeys'] as &$cNodeKey) {
            //引用节点
            $index = &$parseNode[$cNodeKey];
            $temp = false;

            //生成html的字符串
            if ($type === true) {
                //字符串节点
                if ($index['tagName'] === '!text') {
                    $temp = $index['attr'][''];
                //注释标签
                } else if ($index['tagName'] === '!--') {
                    $temp = '<!--' . $index['attr'][''];
                    ($autoKey || $index['attr']['>tagState::end']) && $temp .= '-->';
                //非字符串节点
                } else if ($index['tagName'][0] !== '/' || !$autoKey) {
                    $temp = '<' . $index['tagName'];
                    if (isset($index['attr'][''])) {
                        //前缀
                        $temp .= ($index['tagName'][0] === '!' ? '' : ' ') .
                            //特殊属性值
                            $index['attr'][''];
                        unset($index['attr']['']);
                    }
                    foreach ($index['attr'] as $k => &$v) {
                        //不输出内部属性
                        if ($k[0] !== '>') {
                            $temp .= ' ' . $k . '="' . self::entities($v, false) . '"';
                        }
                    }
                    $temp .= '>';
                }

                if ($temp) {
                    //存储开始标签
                    $dataArr[] = $temp;
                    self::htmlFormat($cNodeKey, $dataArr, $type, $autoKey);

                    //非单节点存储关闭标签
                    if (
                        self::hasChildTag($index['tagName']) > 1 &&
                        ($autoKey || $index['attr']['>tagState::end'])
                    ) {
                        $dataArr[] = '</' . $index['tagName'] . '>';
                    }
                }
            //读取指定节点的子节点
            } else {
                if (
                    $type === false ||
                    ($type === null && $index['tagName'][0] !== '!') ||
                    $index['tagName'] === $type
                ) {
                    $temp = $type && $type[0] === '!' ? $index['attr'][''] : $cNodeKey;
                    $autoKey ? $dataArr[] = $temp : $dataArr[$cNodeKey] = $temp;
                }
                self::htmlFormat($cNodeKey, $dataArr, $type, $autoKey);
            }
        }
    }

    /**
     * 描述 : 克隆节点
     * 参数 :
     *      nodeKey : 指定遍历的节点键
     * 返回 :
     *      克隆的节点键
     * 作者 : Edgar.lee
     */
    private static function cloneNode($nodeKey) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //引用计数器
        $nodeCount = &self::$nodeCount;

        //节点有效
        if (isset($parseNode[$nodeKey])) {
            //返回的节点键
            $rKey = $nodeCount + 1;
            //克隆列表
            $cloneList = array();
            //克隆映射 {原始节点键 => 克隆节点键}
            $cloneMapping = array($parseNode[$nodeKey]['pKey'] => null);
            //读取克隆列表
            self::htmlFormat($nodeKey, $cloneList, false);
            array_unshift($cloneList, $nodeKey);

            //遍历克隆节点
            foreach ($cloneList as &$nowKey) {
                //新节点键
                $newNodeKey = ++$nodeCount;
                //映射节点
                $cloneMapping[$nowKey] = $newNodeKey;
                $temp = $parseNode[$nowKey];
                //清空子节点
                $temp['cKeys'] = array();
                //存在父节点
                if (($temp['pKey'] = $cloneMapping[$temp['pKey']]) !== null) {
                    //更新父节点子节点
                    $parseNode[$temp['pKey']]['cKeys'][] = $newNodeKey;
                }
                $parseNode[$newNodeKey] = $temp;
            }

            return $rKey;
        }
    }

    /**
     * 描述 : 节点回收机制
     * 参数 :
     *     &nodeKeys : 节点列表数组 [节点键, 节点键, ...]
     * 作者 : Edgar.lee
     */
    private static function nodeCollection(&$nodeKeys) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //根节点列表
        $rootNodes = array();

        foreach ($nodeKeys as &$vc) {
            //节点在本次循环中未被兄节点清理掉 && 引用数为0
            if (isset($parseNode[$vc]) && !$parseNode[$vc]['refcount']) {
                //当前父节点
                $root = $vc;
                //父节点存在
                while ($parseNode[$root]['pKey'] !== null) {
                    //更新父节点
                    $root = $parseNode[$root]['pKey'];
                }

                //父节点未进行垃圾分析
                if (!isset($rootNodes[$root])) {
                    //添加根节点
                    $data = array($root);
                    //读取所有子孙节点
                    self::htmlFormat($root, $data);

                    foreach ($data as &$v) {
                        //有对象引用
                        if ($parseNode[$v]['refcount']) {
                            $rootNodes[$root] = false;
                            break;
                        }
                    }

                    //可以清理
                    if ($rootNodes[$root] = !isset($rootNodes[$root])) {
                        foreach ($data as &$v) unset($parseNode[$v]);
                    }
                }
            }
        }
    }

    /**                                                                                     解析区
     * 描述 : 解析html
     * 参数 :
     *      htmlStr : 解析的html支付串
     * 返回 :
     *      fragment(碎片)类型的根节点键
     * 作者 : Edgar.lee
     */
    private static function htmlParse(&$htmlStr) {
        //字符串初始化
        is_string($htmlStr) || $htmlStr = '';
        //小写的$htmlStr
        $htmlStrL = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), ' ', strtolower($htmlStr));
        //解析节点引用
        $parseNode = &self::$parseNode;
        //根节点键
        $rKey = ++self::$nodeCount;
        //创建碎片节点
        $parseNode[$rKey] = array(
            'nodeType' => 'fragment',
            'tagName'  => '#fragment'
        ) + self::$defaultNode;

        $env = array(
            //默认匹配
            'dMatches'   => array(
                '<'      => false
            ),
            //解析的字符串
            'htmlStr'    => &$htmlStr,
            //解析的字符串(小写)
            'htmlStrL'   => &$htmlStrL,
            //解析的字符串长度
            'htmlStrLen' => strlen($htmlStr),
            //当前匹配
            'nMatches'   => array(
                '<'      => false
            ),
            //当前匹配位置
            'nMatchPos'  => &$matchPos,
            //当前分析位置
            'nowPos'     => $i = 0,
            //当前分析行
            'nowLine'    => 1,
            //父节点键
            'pKey'       => $rKey,
            //当前字符串位置,由$env['nowPos']和$env['temp']['strPos']得出
            'strPos'     => &$strPos,
            //分析过程中需要的临时信息
            'temp'       => array(
                //false=忽略一次属性赋值,true=正常赋值
                'attrAssign'  => true,
                //false=引号处于开启状态,true=引号处于关闭状态
                'closeQuotes' => true,
                //false=未开启内联模式,整数=存储块级标签的父节点键
                'inlineNode'  => false,
                //false=正在分析属性名,字符串=正在分析对应的属性值
                'parseAttr'   => false,
                //false=未标记位置,整数=标记位置
                'strPos'      => false,
            ),
            //临时节点,存储未分析完成的节点
            'tempNode'  => null
        );

        while (isset($htmlStrL[$i])) {
            //更新当前行数
            $htmlStr[$i] === "\n" && $env['nowLine'] += 1;

            if (isset($env['nMatches'][$chr = $htmlStrL[$i]])) {
                $matchPos = array('match' => $chr, 'position' => $i);

                //读取字符串起始位置
                $strPos = $env['temp']['strPos'] === false ? $env['nowPos'] : $env['temp']['strPos'];
                //关闭字符串位置标识
                $env['temp']['strPos'] = false;
                switch ($matchPos['match']) {
                    //起始标签
                    case '<':
                        //有效标签
                        if (
                            //存在下一字符
                            ($temp = isset($htmlStrL[$matchPos['position'] + 1])) &&
                            //从小写a开始
                            ($temp = ord($htmlStrL[$matchPos['position'] + 1])) > 96 &&
                            //到小写z结束
                            $temp < 123
                        ) {
                            //读取标签前的字符串
                            self::createStringNode($env);

                            $env['nMatches'] = self::$defaultAttrSplit;
                            //分析新节点
                            $env['tempNode'] = self::$defaultNode;
                            //标签所在行
                            $env['tempNode']['attr']['>tagLine::start'] = $env['nowLine'];
                            //标签闭合状态
                            $env['tempNode']['attr']['>tagState::end'] = 0;
                        //结束标签'/'
                        } else if ($temp === 47) {
                            self::createStringNode($env);
                            $env['nMatches'] = array(
                                //标签结束符
                                '>' => false
                            );
                        //声明标签'!'
                        } else if ($temp === 33) {
                            self::createStringNode($env);
                            //分析声明节点
                            $env['tempNode'] = self::$defaultNode;
                            //标签所在行
                            $env['tempNode']['attr']['>tagLine::start'] = $env['nowLine'];
                            //声明文档类型
                            if (substr($htmlStrL, $matchPos['position'], 9) === '<!doctype') {
                                $env['tempNode']['tagName'] = '!doctype';
                                if (($temp = strpos($htmlStrL, $matchPos['match'] = '>', $matchPos['position'] + 9)) > 0) {
                                    $env['tempNode']['attr'][''] = substr($htmlStr, $matchPos['position'] + 9, $temp - $matchPos['position'] - 9);
                                    $env['tempNode']['attr']['>tagState::end'] = 1;
                                    $matchPos['position'] = $temp;
                                } else {
                                    $env['tempNode']['attr'][''] = substr($htmlStr, $matchPos['position'] + 9);
                                    $env['tempNode']['attr']['>tagState::end'] = 0;
                                    $matchPos['position'] = $env['htmlStrLen'] - 1;
                                }
                            //声明注释,包括已'!'开始的无效声明
                            } else if (
                                (substr($htmlStrL, $matchPos['position'] + 1, 3) === $matchPos['match'] = '!--') ||
                                $matchPos['match'] = '!'
                            ) {
                                $env['tempNode']['tagName'] = '!--';
                                $temp = array($matchPos['position'] + strlen($matchPos['match']) + 1);
                                if (($temp[1] = strpos($htmlStrL, $matchPos['match'] === '!' ? '>' : '-->', $temp[0])) > 0) {
                                    $env['tempNode']['attr'][''] = substr($htmlStr, $temp[0], $temp[1] - $temp[0]);
                                    $env['tempNode']['attr']['>tagState::end'] = 1;
                                    $matchPos['position'] = $temp[1];
                                } else {
                                    $env['tempNode']['attr'][''] = substr($htmlStr, $temp[0]);
                                    $env['tempNode']['attr']['>tagState::end'] = 0;
                                    $matchPos['position'] = $env['htmlStrLen'] - 3;
                                }
                            }
                            //内属性的换行数
                            $env['nowLine'] += substr_count($env['tempNode']['attr'][''], "\n");
                            //存入正式节点
                            self::tempToFormalNode($env);
                        //无效标签
                        } else {
                            //记录字符串起始位置
                            $env['temp']['strPos'] === false && $env['temp']['strPos'] = $strPos;
                        }
                        break;
                    //关键位分隔符
                    case '=':
                    case ' ':
                    case '/':
                        //区分大小写
                        if ($parseStr = trim(
                            substr($htmlStr, $strPos, $matchPos['position'] - $strPos),
                            //分析非属性值时'/'当' '处理
                            is_string($env['temp']['parseAttr']) ? "\t\n\r\0\x0B " : "\t\n\r\0\x0B /"
                        )) {
                            $env['nMatches'] = self::$defaultAttrSplit + array(
                                //属性分隔符
                                '=' => false,
                            );
                            //设置属性
                            if (isset($env['tempNode']['tagName'])) {
                                self::setTempNodeAttr($env, $parseStr);
                            //设置标签名
                            } else {
                                $env['tempNode']['tagName'] = strtolower($parseStr);
                            }
                        //无效标签
                        } else {
                            //记录字符串起始位置
                            $env['temp']['strPos'] === false && $env['temp']['strPos'] = $strPos;
                        }
                        break;
                    //属性包含符
                    case '"':
                    case '\'':
                        //与上次匹配位置之间的字符串
                        $temp = substr($htmlStr, $strPos, $matchPos['position'] - $strPos);
                        //引号处于开放状态
                        if ($env['temp']['closeQuotes'] = !$env['temp']['closeQuotes']) {
                            $env['nMatches'] = self::$defaultAttrSplit + array(
                                //属性分隔符
                                '=' => false,
                            );
                            self::setTempNodeAttr($env, $temp);
                        //引号处关闭状态
                        } else if (trim($temp) === '') {
                            $env['nMatches'] = array(
                                //寻找闭合引号
                                $matchPos['match'] => false
                            );
                        //引号处关闭状态,但与上个等号间不为空
                        } else {
                            $env['nMatches'] = self::$defaultAttrSplit;
                            $env['temp']['strPos'] = $strPos;
                            //关闭标签开放状态
                            $env['temp']['closeQuotes'] = true;
                        }
                        break;
                    //结束标签
                    case '>':
                        //如果开始分析属性
                        if (isset($env['tempNode']['tagName'])) {
                            //存在未处理字符串
                            if ($parseStr = trim(substr($htmlStr, $strPos, $matchPos['position'] - $strPos))) {
                                self::setTempNodeAttr($env, $parseStr);
                            }
                            //存入正式节点
                            $temp = self::tempToFormalNode($env);

                            //不是单节点 && 已"/"结尾的标签,如:<mm xx />
                            if (
                                self::hasChildTag($temp) > 1 && 
                                $htmlStr[$matchPos['position'] - 1] === '/'
                            ) {
                                //作为单节点关闭
                                self::planNode($env, $parseNode[$temp]['tagName']);
                            }
                        //正在分析有效标签名或结束标签'/'
                        } else {
                            $parseStr = trim(substr($htmlStrL, $strPos, $matchPos['position'] - $strPos));
                            //分析结束标签
                            if ($parseStr[0] === '/') {
                                $temp = explode(' ', substr($parseStr, 1), 2);
                                //对关闭节点容错
                                self::planNode($env, $temp[0]);
                            } else {
                                $env['tempNode']['tagName'] = $parseStr;
                                //存入正式节点
                                self::tempToFormalNode($env);
                            }
                        }
                        break;
                }
                //更新查询位置
                $i = $env['nowPos'] = $matchPos['position'] + strlen($matchPos['match']);
            } else {
                $i += 1;
            }
        }

        //如果没有分析标签,则创建余下字符串
        if ($i !== $env['nowPos']) {
            $env['strPos'] = $env['temp']['strPos'] === false ? $env['nowPos'] : $env['temp']['strPos'];
            $matchPos['position'] = $env['htmlStrLen'];
            self::createStringNode($env);
        }
        return $rKey;
    }

    /**
     * 描述 : 设置临时节点的属性值或名
     * 参数 : 
     *     &env : htmlParse方法的$env变量
     *      str : 指定区分大小写字符串
     * 作者 : Edgar.lee
     */
    private static function setTempNodeAttr(&$env, $str) {
        //设置属性值
        if ($index = &$env['temp']['parseAttr']) {
            //如果属性未初始化,则赋值
            if ($env['temp']['attrAssign']) {
                //编码属性
                $env['tempNode']['attr'][$index] = self::entities($str, true);
                //引号模式(单引 双引 或 空字符)
                $env['tempNode']['attr']['>attrQuote::' . $index] = 
                    ($chr = &$env['nMatchPos']['match']) === '"' || $chr === '\'' ? 
                        $chr : '';
            }
            //开启属性分析
            $index = false;
        //设置属性名(trim是为了处理从'>'调用数据)
        } else if ($str = trim(strtolower($str), '/')) {
            //属性名,如果已生成,忽略一次赋值
            if ($env['temp']['attrAssign'] = !isset($env['tempNode']['attr'][$str])) {
                //添加属性行数属性
                $env['tempNode']['attr']['>attrLine::' . $str] = $env['nowLine'];
                $env['tempNode']['attr'][$str] = '';
                $env['tempNode']['attr']['>attrQuote::' . $str] = '';
            }

            //判断该属性键是否有属性值
            if (
                ($temp = strpos($env['htmlStrL'], '=', $env['nMatchPos']['position'])) === false ||
                rtrim(substr($env['htmlStrL'], $env['nMatchPos']['position'], $temp - $env['nMatchPos']['position']))
            //没有属性值
            ) {
                //开启属性赋值
                $env['temp']['attrAssign'] = true;
                //开启属性分析
                $env['temp']['parseAttr'] = false;
            //有属性值
            } else {
                //开启属性值分析
                $env['temp']['parseAttr'] = $str;
                $env['nMatchPos']['position'] = $temp;
                $env['nMatchPos']['match'] = '=';
                $env['nMatches'] = self::$defaultAttrSplit + array(
                    //字符串起始符
                    '"'  => false,
                    //字符串起始符
                    '\'' => false
                );
                //'/'不是属性值分隔符
                unset($env['nMatches']['/']);
            }
        }
    }

    /**
     * 描述 : 从临时节点转为正式节点
     * 参数 : 
     *     &env : htmlParse方法的$env变量
     * 作者 : Edgar.lee
     */
    private static function tempToFormalNode(&$env) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //解析计数器
        $nodeCount = &self::$nodeCount;
        //新节点键
        $parseNode[$nKey = ++$nodeCount] = null;
        //修改临时节点的父节点
        $env['tempNode']['pKey'] = $env['pKey'];
        //初始属性
        if ($env['tempNode']['tagName'] === 'input') {
            $env['tempNode']['attr'] += array(
                'type' => 'text'
            );
        }

        //脚本标签查找结束标签
        if (self::hasChildTag($env['tempNode']['tagName']) & 6) {
            $temp = array(
                //关闭标签(包括'</')
                'closeTag'    => '</' . $env['tempNode']['tagName'],
                //关闭标签长度(包括'</')
                'closeTagLen' => strlen($env['tempNode']['tagName']) + 2,
                //开始查找位置
                'searchPos'   => 0,
                //字符串截取位置
                'subPos'      => $env['nMatchPos']['position'] + strlen($env['nMatchPos']['match']),
            );
            //初始化查找位置
            $temp['searchPos'] = $temp['subPos'] - $temp['closeTagLen'];
            $env['nMatchPos']['match'] = '';

            while (true) {
                if (($temp['searchPos'] = strpos($env['htmlStrL'], $temp['closeTag'], $temp['searchPos'] + $temp['closeTagLen'])) > 0) {
                    //有效结束标签
                    if (
                        //关闭标签后无字符
                        !isset($env['htmlStrL'][$temp['searchPos'] + $temp['closeTagLen']]) ||
                        //关闭标签名后是分隔符
                        isset(self::$defaultAttrSplit[$env['htmlStrL'][$temp['searchPos'] + $temp['closeTagLen']]])
                    ) {
                        $env['tempNode']['attr'][''] = substr($env['htmlStr'], $temp['subPos'], $temp['searchPos'] - $temp['subPos']);
                        $env['tempNode']['attr']['>tagState::end'] = 1;
                        $env['nMatchPos']['position'] = ($temp['searchPos'] = strpos($env['htmlStrL'], '>', $temp['searchPos'] + $temp['closeTagLen'])) > 0 ? $temp['searchPos'] + 1 : $env['htmlStrLen'];
                        break;
                    }
                //未查找到关闭标签
                } else {
                    $env['tempNode']['attr'][''] = substr($env['htmlStr'], $temp['subPos']);
                    $env['nMatchPos']['position'] = $env['htmlStrLen'];
                    break;
                }
            }
            //摘取文本中的换行数
            $nowLine = $env['nowLine'] += substr_count($env['tempNode']['attr'][''], "\n");

            //不包含非注释的html文本
            if (self::hasChildTag($env['tempNode']['tagName']) === 4) {
                //匹配出注释和标签
                preg_match_all(
                    '@<!((?:--)?)(.*?)\1>|(.+?)(?:(?=<!)|$)@s', 
                    $env['tempNode']['attr'][''], 
                    $temp, PREG_SET_ORDER
                );

                foreach ($temp as $k => &$v) {
                    //创建子节点
                    $parseNode[$k = ++$nodeCount] = array(
                        'attr'     => array(
                            //是文本 ? 去标签 : 注释文本
                            '' =>  isset($v[3]) ? strip_tags($v[3]) : $v[2]
                        ),
                        'pKey'     => $nKey,
                        //节点标签
                        'tagName'  => isset($v[3]) ? '!text' : '!--'
                    ) + self::$defaultNode;
                    $env['tempNode']['cKeys'][] = $k;

                    $nowLine -= substr_count($parseNode[$k]['attr'][''], "\n");
                    //文本标签行数
                    $parseNode[$k]['attr']['>tagLine::start'] = $nowLine;
                    //标签闭合状态
                    $parseNode[$k]['attr']['>tagState::end'] = 1;
                }
            } else {
                //文本域内容解码
                if ($env['tempNode']['tagName'] === 'textarea') {
                    $env['tempNode']['attr'][''] = self::entities(self::entities($env['tempNode']['attr'][''], true), false);
                }

                //将文本字符串挪入文本节点
                if ($env['tempNode']['attr']['']) {
                    //创建字符串节点
                    $parseNode[$k = ++$nodeCount] = array(
                        'attr'     => array(
                            '' =>  $env['tempNode']['attr']['']
                        ),
                        'pKey'     => $nKey,
                        'tagName'  => '!text'
                    ) + self::$defaultNode;
                    $env['tempNode']['cKeys'][] = $k;

                    $nowLine -= substr_count($parseNode[$k]['attr'][''], "\n");
                    //文本标签行数
                    $parseNode[$k]['attr']['>tagLine::start'] = $nowLine;
                    //标签闭合状态
                    $parseNode[$k]['attr']['>tagState::end'] = 1;
                }
            }

            unset($env['tempNode']['attr']['']);
        //更新父节点键(单标签没有子节点)
        } else if (self::hasChildTag($env['tempNode']['tagName']) === 8) {
            $env['pKey'] = $nKey;
        }

        //新节点规划
        self::planNode($env, true);
        //父节点中添加子节点
        $parseNode[$env['tempNode']['pKey']]['cKeys'][] = $nKey;
        //正式插入节点
        $parseNode[$nKey] = $env['tempNode'];
        //结束标签分析
        $env['temp']['parseAttr'] = false;
        //开始分析新标签
        $env['nMatches'] = $env['dMatches'];
        //清空临时节点
        $env['tempNode'] = null;

        return $nKey;
    }

    /**
     * 描述 : 创建字符串节点
     * 参数 : 
     *     &env : htmlParse方法的$env变量
     * 作者 : Edgar.lee
     */
    private static function createStringNode(&$env) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //记录字符串
        if (($temp = $env['nMatchPos']['position'] - $env['strPos']) > 0) {
            //父节点中添加子节点
            $parseNode[$env['pKey']]['cKeys'][] = $key = ++self::$nodeCount;
            $index = &$parseNode[$key];
            //创建字符串节点
            $index = array(
                'attr'     => array(
                    '' => self::entities(self::entities(substr($env['htmlStr'], $env['strPos'], $temp), true), false)
                ),
                'pKey'     => $env['pKey'],
                'tagName'  => '!text'
            ) + self::$defaultNode;
            //标签行数
            $index['attr']['>tagLine::start'] = $env['nowLine'] - substr_count($index['attr'][''], "\n");
            //标签闭合状态
            $index['attr']['>tagState::end'] = 1;
        }
    }

    /**
     * 描述 : 对新节点规划,对关闭节点容错
     * 参数 :
     *     &env  : htmlParse方法的$env变量
     *      type : true(默认)=新加入节点,字符串=封闭节点名
     * 作者 : Edgar.lee
     */
    private static function planNode(&$env, $type = true) {
        //解析节点引用
        $parseNode = &self::$parseNode;
        //对新节点规划
        if ($type === true) {
            //开启内联模式 && 块级标签
            if ($env['temp']['inlineNode'] !== false && isset(self::$blockTag[$env['tempNode']['tagName']])) {
                //修改新节点父节点位置
                $env['tempNode']['pKey'] = $env['temp']['inlineNode'];
                //关闭内联标签
                $env['temp']['inlineNode'] = false;
            }

            //开启内联分析
            if ($env['tempNode']['tagName'] === 'p') {
                $env['temp']['inlineNode'] = $env['tempNode']['pKey'];
            //子标签中不能包含同样的标签
            } else if (isset(self::$noRepeatTag[$env['tempNode']['tagName']])) {
                $temp = array(
                    //结束标签
                    'endTag' => &self::$noRepeatTag[$env['tempNode']['tagName']],
                    //当前节点的父节点
                    'pNode'  => &$parseNode[$env['tempNode']['pKey']]
                );
                do {
                    if (
                        //临时节点被某父节点包含
                        isset($temp['endTag'][$temp['pNode']['tagName']]) ||
                        //临时节点名与某父节点相同
                        $env['tempNode']['tagName'] === $temp['pNode']['tagName'] &&
                        //如果临时节点名与某父节点相同,便改为同节点
                        $env['tempNode']['pKey'] = $temp['pNode']['pKey']
                    ) {
                        break;
                    }
                } while (isset($temp['pNode']['pKey']) && ($temp['pNode'] = &$parseNode[$temp['pNode']['pKey']]) !== null);
            }
        //对关闭节点容错, 不保存 </> 标签
        } else if ($type) {
            //有效结束标签
            if ($parseNode[$env['pKey']]['tagName'] === $type) {
                //标签闭合状态
                $parseNode[$env['pKey']]['attr']['>tagState::end'] = 1;
                //更新父节点键
                $env['pKey'] = $parseNode[$env['pKey']]['pKey'];
                //有效p结束标签,关闭内联标签
                $type === 'p' && $env['temp']['inlineNode'] = false;
            //无效封闭标签(如果为P标签,则追加P节点)
            } else if ($type === 'p') {
                $parseNode[$env['pKey']]['cKeys'][] = ++self::$nodeCount;
                $parseNode[self::$nodeCount] = array(
                    'pKey'    => $env['pKey'],
                    'tagName' => 'p',
                    'attr'    => array(
                        '>tagLine::start' => $env['nowLine'],
                        '>tagState::end'  => 1
                    )
                ) + self::$defaultNode;
            //按双标签处理(匹配父节点列中任意未结束节点)
            } else {
                $temp = $env['pKey'];
                while (($temp = $parseNode[$temp]['pKey']) !== null) {
                    if ($parseNode[$temp]['tagName'] === $type) {
                        //标签闭合状态
                        $parseNode[$temp]['attr']['>tagState::end'] = 1;
                        //更新父节点键
                        $env['pKey'] = $parseNode[$temp]['pKey'];
                        break;
                    }
                }

                //结束标签没匹配到
                if ($temp === null) {
                    $parseNode[$env['pKey']]['cKeys'][] = ++self::$nodeCount;
                    $parseNode[self::$nodeCount] = array(
                        'pKey'    => $env['pKey'],
                        'tagName' => '/' . $type,
                        'attr'    => array(
                            '>tagLine::start' => $env['nowLine'],
                            '>tagState::end'  => 0
                        )
                    ) + self::$defaultNode;
                }
            }
            //开始分析新标签
            $env['nMatches'] = $env['dMatches'];
        }
    }
}