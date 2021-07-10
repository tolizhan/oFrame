<?php
/**
 * 描述 : 服务层API主类
 *      所有API继承该类, 并在API类中配置方法规则($funcRule)参数
 *      若有共同规则, 如授权, 可放在共用规则结构($shareRule)中
 * 注明 :
 *      方法规则结构($funcRule) : {
 *          方法名 : 验证规则 {
 *              $GLOBALS 中的GET POST等键名 : {
 *                  符合 of_base_com_data::rule 规则
 *              }
 *          }
 *      }
 *      共用规则结构($shareRule) : {
 *          $GLOBALS 中的GET POST等键名 : {
 *              符合 of_base_com_data::rule 规则
 *          }
 *      }
 * 作者 : Edgar.lee
 */
class serv_papi_main {
    //是否校验规则
    private static $isCheck = true;
    //共用规则
    private static $shareRule = array();
    //方法规则
    protected $funcRule = array();

    /**
     * 描述 : 请求验证
     * 作者 : Edgar.lee
     */
    public function __construct() {
        self::$isCheck && L::rule(self::mergeRule($this));
    }

    /**
     * 描述 : 列举接口
     * 作者 : Edgar.lee
     */
    public function index() {
        //关闭规则校验
        self::$isCheck = false;
        //权限列表
        $ruleList = array();
        //接口文件夹路径
        $path = strtr(dirname(__FILE__), '\\', '/');
        //提取接口注释
        $preg = '@^( *\/\*(?:(?!\*\/).)*?\*\/)[^(]* function +([^()]+) *\(@ms';
        //类前缀长度
        $pLen = strlen(__FILE__) - strlen(__CLASS__) - 4;
        //组前缀长度
        $gLen = strlen(pathinfo(__FILE__, PATHINFO_DIRNAME)) + 1;
        //类空间分隔符
        $char = strpos(of::dispatch('class'), '_') ? '_' : '\\';

        //获取接口文件名
        of_base_com_disk::each($path, $data, false);

        //提取规则与注释
        foreach ($data as $k => &$v) {
            if (!$v && pathinfo($k, PATHINFO_EXTENSION) === 'php') {
                //引用规则包
                $index = &$ruleList[strtr(substr($k, $gLen, -4), '/', $char)];
                //默认规则
                $index = array();

                //获取功能规则
                $temp = strtr(substr($k, $pLen, -4), '/', $char);
                $rule = &self::mergeRule(new $temp);
                foreach ($rule as $kf => &$vf) {
                    $index[$kf] = array(
                        'funcRule' => htmlspecialchars(print_r($vf, true)),
                        'comment'  => false,
                    );
                }

                //提取描述与方法名
                preg_match_all($preg, file_get_contents($k), $match, PREG_SET_ORDER);
                foreach ($match as &$vm) {
                    //方法有效
                    if (isset($index[$vm[2]]['funcRule'])) {
                        $index[$vm[2]]['comment'] = htmlspecialchars(trim(str_replace(
                            array('    /**', '     * ', '     */'),
                            '',
                            $vm[1]
                        )));
                    }
                }
            }
        }

        //恢复规则校验
        self::$isCheck = true;
        //删除main分组
        unset($ruleList['main']);

        //界面展示
        of_view::head(array(
            'css'  => array('_' . OF_URL . '/att/com/com/paging/main.css'),
            'head' => array('<style>pre{margin: 0;}</style>')
        ));
        echo '<table class="of-paging_block">',
            '<thead class="of-paging_head">',
                '<tr>',
                    '<th>接口组</th><th>接口名</th>',
                    '<th>描述</th><th>规则</th>',
                '</tr>',
            '</thead>',
            '<tbody class="of-paging_body" valign="top">';
        foreach ($ruleList as $k => &$v) {
            foreach ($v as $kd => &$vd) {
                $vd['comment'] = $vd['comment'] === false ?
                    '<font color=red>失效</font>' :
                    '<pre>' . $vd['comment'] . '</pre>';

                echo '<tr>',
                    "<td>{$k}</td><td>{$kd}</td>",
                    "<td>{$vd['comment']}</td>",
                    "<td><pre>{$vd['funcRule']}</pre></td>",
                    '</tr>';
            }
        }
        echo '</tbody></table>';
    }

    /**
     * 描述 : 合并共用规则
     * 参数 :
     *      obj : 待合并的对象
     * 返回 :
     *      合并后的规则
     * 作者 : Edgar.lee
     */
    private static function &mergeRule($obj) {
        ($funcRule = &$obj->funcRule) === null && $funcRule = array();

        if (get_class($obj) === __CLASS__) {
            $funcRule = array('index' => array());
        } else {
            //遍历共用规则 GET => RULE
            foreach (self::$shareRule as $k => &$v) {
                //遍历接口规则 方法名 => 共用规则结构
                foreach ($funcRule as &$vo) {
                    isset($vo[$k]) ? $vo[$k] += $v : $vo[$k] = $v;
                }
            }
        }

        return $funcRule;
    }
}

//支持命名空间 && 设置空间别名
function_exists('class_alias') && class_alias('serv_papi_main', 'serv\papi\main');