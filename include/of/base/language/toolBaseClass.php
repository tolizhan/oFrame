<?php
/**
 * 描述 : 提供语言包开发核心功能
 * 注明 :
 *      环境变量数据(envVar) : 环境变量结构 {
 *          "path" : 语言包根路径
 *      }
 * 作者 : Edgar.lee
 */
class of_base_language_toolBaseClass {
    //环境变量
    private static $envVar = null;

    /**
     * 描述 : 初始化
     * 参数 :
     *      config : 配置文件
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public static function init() {
        ini_set('max_execution_time', 0);

        //引用环境变量
        $envVar = &self::$envVar;
        //语言包根路径
        $envVar['path'] = ROOT_DIR . of::config('_of.language.path', OF_DATA . '/_of/of_base_language_packs');
    }

    /**
     * 描述 : 各功能演示
     * 作者 : Edgar.lee
     */
    public static function test() {
        /* 环境变量
        print_r(self::$envVar);
        // */
        /* 获取目录
        print_r(self::getDir());
        // */
        /* 获取基类文件
        print_r(self::getFile('/base/source/demo/view/tpl/demo/index/viewTest.tpl.php.php'));
        // */
        /* 检测语言包状态
        print_r(self::status('/base/source/demo/view/tpl/demo/index/viewTest.tpl.php.php'));
        // */
        /* 生成全局语言包
        print_r(self::pack('/base'));
        // */
        /* 生成全局语言包
        print_r(self::merge('/base/source', '/base'));
        // */
        /* 生成全局语言包
        print_r(self::sort('/base'));
        // */
        /* 读取或写入全局语言包
        $index = &self::pack('/base');
        print_r(self::pack('/base', $index));
        // */
    }

    /**
     * 描述 : 获取目录
     * 参数 :
     *      path   : 相对根目录的路径
     * 返回 :
     *      成功返回数组
     * 作者 : Edgar.lee
     */
    public static function &getDir($path = '') {
        //引用环境变量
        $envVar = &self::$envVar;
        //返回结果集
        $result = array();

        if (is_dir($temp = $envVar['path'] . $path)) {
            $handle = opendir($temp);
            while (($temp = readdir($handle)) !== false) {
                if ($temp[0] !== '.') {
                    $temp = "{$path}/{$temp}";
                    $result[$temp] = is_dir($envVar['path'] . $temp);
                }
            }
            closedir($handle);
            //文件夹在上,文件在下
            arsort($result);
        }

        return $result;
    }

    /**
     * 描述 : 获取基类文件
     * 参数 :
     *      path : 指定文件路径
     *      data : null=读取数据, 数组=写入数据
     * 返回 :
     *      返回 数组
     * 作者 : Edgar.lee
     */
    public static function &getFile($path, &$data = null) {
        //引用环境变量
        $envVar = &self::$envVar;
        //路径格式
        $path = $envVar['path'] . $path;

        if ($data === null) {
            //读取页级语言包
            $data = of_base_com_disk::file($path, true, true);
            $data || $data = array();
        } else {
            //写回页级语言包
            $data = of_base_com_disk::file($path, $data, true);
        }

        return $data;
    }

    /**
     * 描述 : 检测语言包状态
     *      path   : 分析路径
     *     &params : 额外参数 {
     *          "ignore" : 是否显示忽略列表
     *          "keyInv" : 是否显示键级状态
     *      }
     * 注明 :
     *      文件文本结构(fileText) : {
     *          相对语言包根目录的文件路径 : {
     *              "jsPack"  : js提取包 {
     *                  对应源文件提取的文本 : true
     *              }
     *              "phpPack" : php提取包, 参看 jsPack 结构
     *          }
     *      }
     * 返回 :
     *      存在未翻译的返回true, 否则返回false
     * 作者 : Edgar.lee
     */
    public static function &status($path, &$params = array()) {
        //文件文本列表
        static $fileText = null;
        //引用环境变量
        $envVar = &self::$envVar;
        //待处理列表
        $waitList = array($path);
        //路径是文件夹
        $isDir = is_dir($envVar['path'] . $path);
        $result = false;
        $params += array(
            //是否显示忽略列表
            'ignore' => false,
            //是否显示键级无效
            'keyInv' => false
        );

        do {
            $k = key($waitList);
            $path = &$waitList[$k];
            unset($waitList[$k]);

            //如果是目录
            if (is_dir($temp = $envVar['path'] . $path)) {
                foreach (self::getDir($path) as $k => $v) {
                    $waitList[] = $k;
                }
            //是文件
            } else {
                //读取页级语言包
                $pack = of_base_com_disk::file($temp, true, true);
                //初始化数组
                $pack || $pack = array();
                //问题数据包引用
                $params['pack'] = &$pack;

                //未分析文本
                if (!isset($fileText[$path])) {
                    $temp = explode('/', $path, 4);
                    $temp = ROOT_DIR . '/' . substr($temp[3], 0, -4);
                    //读取文件内容
                    $contents = is_file($temp) ? file_get_contents($temp) : '';
                    $fileText[$path] = array();

                    //开始提取文本
                    switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                        case 'php':
                            $token  = token_get_all($contents);
                            $contents = array();

                            foreach ($token as &$v) {
                                switch ($v[0]) {
                                    //php 字符串 (315)
                                    case T_CONSTANT_ENCAPSED_STRING :
                                        ($temp = trim(substr($v[1], 1, -1))) && $fileText[$path]['phpPack'][$temp] = true;
                                        break;
                                    //html字符串 (311)
                                    case T_INLINE_HTML:
                                        $contents[] = $v[1];
                                        break;
                                }
                            }

                            //过滤php后的html
                            $contents = join($contents);
                            //不需要 break;
                        case 'html':
                            $temp = new of_base_com_hParse($contents);
                            $contents = array();
                            foreach ($temp->find('script')->eq() as $v) {
                                $contents[] = $v->text();
                            }

                            //过滤html后的js
                            $contents = join($contents);
                            //不需要 break;
                        case 'js':
                            preg_match_all('@(?:^|[^\\\\]+?)(?:\\\\\\\\)*(\'|")(.*?[^\\\\]+?)(?:\\\\\\\\)*\1@s', $contents, $temp);
                            foreach ($temp[2] as &$v) {
                                $fileText[$path]['jsPack'][$v] = true;
                            }
                            break;
                    }
                }

                foreach (array('jsPack', 'phpPack') as $kp) {
                    if (isset($pack[$kp])) {
                        foreach ($pack[$kp] as &$va) {
                            foreach ($va as $ks => &$vs) {
                                //源文本不存在
                                if (empty($fileText[$path][$kp][$ks])) {
                                    foreach ($vs as &$v) {
                                        //(未忽略 || 关闭忽略)
                                        if (empty($v['ignore']) || $params['ignore']) {
                                            $v['invalid'] = $result = true;
                                            if ($isDir) break 5;
                                        }
                                    }
                                //检测无效键
                                } else if ($params['keyInv']) {
                                    foreach ($vs as $k => &$v) {
                                        if (
                                            $k !== '' &&
                                            //键文本不存在
                                            empty($fileText[$path][$kp][$k]) &&
                                            //(未忽略 || 关闭忽略)
                                            (empty($v['ignore']) || $params['ignore'])
                                        ) {
                                            $v['invalid'] = $result = true;
                                            if ($isDir) break 5;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                foreach (array('jsLink', 'phpLink') as $kp) {
                    if (isset($pack[$kp])) {
                        foreach ($pack[$kp] as &$va) {
                            foreach ($va as $kp => &$vi) {
                                if ($vi = !is_file(ROOT_DIR . $kp)) {
                                    $result = true;
                                    if ($isDir) break 4;
                                }
                            }
                        }
                    }
                }
            }
        } while (!empty($waitList));

        return $result;
    }

    /**
     * 描述 : 整理出全局文件
     * 参数 :
     *      path : 相对语言包根目录的路径
     *      out  : '_'开头的相对磁盘根路径,'/'开头的相对语言包根路径,null=返回结果
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public static function &merge($path, $out) {
        //引用环境变量
        $envVar = &self::$envVar;
        //保存数据
        $data   = array('jsPack' => array(), 'phpPack' => array());
        $source = $envVar['path'] . $path;

        while (of_base_com_disk::each($source, $dirList)) {
            foreach ($dirList as $path => &$isDir) {
                //是目录
                if ($isDir) {
                    $temp = basename($path);
                    //已'.'开始的文件夹排除过滤
                    $temp[0] === '.' && $isDir = null;
                //是文件
                } else {
                    //读取页级语言包
                    $pack = of_base_com_disk::file($path, true, true);

                    foreach (array('jsPack', 'phpPack') as $kp) {
                        if (isset($pack[$kp])) {
                            foreach ($pack[$kp] as &$va) {
                                foreach ($va as $ks => &$vs) {
                                    isset($vs['']) || $vs['']['translate'] = '';
                                    foreach ($vs as $k => &$v) {
                                        if (empty($data[$kp][$ks][$k])) {
                                            $data[$kp][$ks][$k] = $v['translate'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $out && self::pack($out, $data);
        return $data;
    }

    /**
     * 描述 : 排序全局语言包
     * 参数 :
     *      data : 字符串='_'开头的相对磁盘根路径,'/'开头的相对语言包根路径, 数组=包含(jsPack, phpPack)的数组
     * 返回 :
     *      
     * 作者 : Edgar.lee
     */
    public static function &sort($data, &$sort = null) {
        if ($sort === null) {
            is_string($data) && $data = &self::pack($path = $data);
            uasort($data['phpPack'], 'of_base_language_toolBaseClass::sort');
            uasort($data['jsPack'], 'of_base_language_toolBaseClass::sort');
            isset($path) && self::pack($path, $data);
        } else {
            if (empty($data[''])) {
                $data = -1;
            } else if (empty($sort[''])) {
                $data = 1;
            } else if (in_array('', $data, true)) {
                $data = -1;
            } else {
                $data = 1;
            }
        }

        return $data;
    }

    /**
     * 描述 : 读取或写入全局语言包
     * 参数 :
     *      path : 打开或写入的路径
     *      data : null=打开,数组=写入
     *      type : true=以data为准进行整合, false=以path打开的数据为准进行整合
     * 作者 : Edgar.lee
     */
    public static function &pack($path, &$data = null, $type = true) {
        //引用环境变量
        $envVar = &self::$envVar;
        //整理打开目录
        $path = $path[0] === '_' ? substr($path, 1) : $envVar['path'] . $path;

        if ($data === null) {
            $data = array(
                'phpPack' => of_base_com_disk::file($path . '/php.txt', true),
                'jsPack'  => json_decode(of_base_com_disk::file($path . '/js.txt'), true)
            );

            is_array($data['phpPack']) || $data['phpPack'] = array();
            is_array($data['jsPack']) || $data['jsPack'] = array();
        } else {
            $data += array('jsPack' => array(), 'phpPack' => array());
            $goal = &self::pack('_' . $path);

            $index = $type ? array(&$data, &$goal) : array(&$goal, &$data);
            foreach ($index[0] as $kp => &$vp) {
                foreach ($vp as $ks => &$vs) {
                    foreach ($vs as $k => &$v) {
                        empty($index[1][$kp][$ks][$k]) || $v = trim($index[1][$kp][$ks][$k]);
                    }
                }
            }

            of_base_com_disk::file($path . '/js.txt', json_encode($index[0]['jsPack']));
            of_base_com_disk::file($path . '/php.txt', $index[0]['phpPack']);
            $data = &$index[0];
        }

        return $data;
    }

    /**
     * 描述 : 导出csv
     * 参数 :
     *      path : '_'开头的相对磁盘根路径,'/'开头的相对语言包根路径
     *      csv  : 导入csv文件
     * 返回 :
     *      返回数据
     * 作者 : Edgar.lee
     */
    public static function &exportOrImport($path, $csv = null) {
        //导入
        if ($csv) {
            while ($index = &of_base_com_csv::parse($csv)) {
                if ($index[0] === 'jsPack' || $index[0] === 'phpPack') {
                    $data[$index[0]][$index[1]][$index[2]] = $index[3];
                }
            }

            isset($data) && self::pack($path, $data, false);
        //导出
        } else {
            $data = &self::sort(self::pack($path));

            of_base_com_csv::download('export');
            of_base_com_csv::download(array('归属', '源文本', '标识符', '翻译'));

            foreach ($data as $kp => &$vp) {
                foreach ($vp as $ks => &$vs) {
                    foreach ($vs as $k => &$v) {
                        of_base_com_csv::download(array($kp, $ks, $k, $v));
                    }
                }
            }
        }

        return $data;
    }
}
of_base_language_toolBaseClass::init();

/**国家语种缩写
 * en        英文
 * en_US     英文                   (美国)
 * ar        阿拉伯文
 * ar_AE     阿拉伯文               (阿拉伯联合酋长国)
 * ar_BH     阿拉伯文               (巴林)
 * ar_DZ     阿拉伯文               (阿尔及利亚)
 * ar_EG     阿拉伯文               (埃及)
 * ar_IQ     阿拉伯文               (伊拉克)
 * ar_JO     阿拉伯文               (约旦)
 * ar_KW     阿拉伯文               (科威特)
 * ar_LB     阿拉伯文               (黎巴嫩)
 * ar_LY     阿拉伯文               (利比亚)
 * ar_MA     阿拉伯文               (摩洛哥)
 * ar_OM     阿拉伯文               (阿曼)
 * ar_QA     阿拉伯文               (卡塔尔)
 * ar_SA     阿拉伯文               (沙特阿拉伯)
 * ar_SD     阿拉伯文               (苏丹)
 * ar_SY     阿拉伯文               (叙利亚)
 * ar_TN     阿拉伯文               (突尼斯)
 * ar_YE     阿拉伯文               (也门)
 * be        白俄罗斯文
 * be_BY     白俄罗斯文             (白俄罗斯)
 * bg        保加利亚文
 * bg_BG     保加利亚文             (保加利亚)
 * ca        加泰罗尼亚文
 * ca_ES     加泰罗尼亚文           (西班牙)
 * cs        捷克文
 * cs_CZ     捷克文                 (捷克共和国)
 * da        丹麦文
 * da_DK     丹麦文                 (丹麦)
 * de        德文
 * de_AT     德文                   (奥地利)
 * de_CH     德文                   (瑞士)
 * de_DE     德文                   (德国)
 * de_LU     德文                   (卢森堡)
 * el        希腊文
 * el_GR     希腊文                 (希腊)
 * en_AU     英文                   (澳大利亚)
 * en_CA     英文                   (加拿大)
 * en_GB     英文                   (英国)
 * en_IE     英文                   (爱尔兰)
 * en_NZ     英文                   (新西兰)
 * en_ZA     英文                   (南非)
 * es        西班牙文
 * es_BO     西班牙文               (玻利维亚)
 * es_AR     西班牙文               (阿根廷)
 * es_CL     西班牙文               (智利)
 * es_CO     西班牙文               (哥伦比亚)
 * es_CR     西班牙文               (哥斯达黎加)
 * es_DO     西班牙文               (多米尼加共和国)
 * es_EC     西班牙文               (厄瓜多尔)
 * es_ES     西班牙文               (西班牙)
 * es_GT     西班牙文               (危地马拉)
 * es_HN     西班牙文               (洪都拉斯)
 * es_MX     西班牙文               (墨西哥)
 * es_NI     西班牙文               (尼加拉瓜)
 * et        爱沙尼亚文
 * es_PA     西班牙文               (巴拿马)
 * es_PE     西班牙文               (秘鲁)
 * es_PR     西班牙文               (波多黎哥)
 * es_PY     西班牙文               (巴拉圭)
 * es_SV     西班牙文               (萨尔瓦多)
 * es_UY     西班牙文               (乌拉圭)
 * es_VE     西班牙文               (委内瑞拉)
 * et_EE     爱沙尼亚文             (爱沙尼亚)
 * fi        芬兰文
 * fi_FI     芬兰文                 (芬兰)
 * fr        法文
 * fr_BE     法文                   (比利时)
 * fr_CA     法文                   (加拿大)
 * fr_CH     法文                   (瑞士)
 * fr_FR     法文                   (法国)
 * fr_LU     法文                   (卢森堡)
 * hr        克罗地亚文
 * hr_HR     克罗地亚文             (克罗地亚)
 * hu        匈牙利文
 * hu_HU     匈牙利文               (匈牙利)
 * is        冰岛文
 * is_IS     冰岛文                 (冰岛)
 * it        意大利文
 * it_CH     意大利文               (瑞士)
 * it_IT     意大利文               (意大利)
 * iw        希伯来文
 * iw_IL     希伯来文               (以色列)
 * ja        日文
 * ja_JP     日文                   (日本)
 * ko        朝鲜文
 * ko_KR     朝鲜文                 (南朝鲜)
 * lt        立陶宛文
 * lt_LT     立陶宛文               (立陶宛)
 * lv        拉托维亚文(列托)
 * lv_LV     拉托维亚文(列托)       (拉脱维亚)
 * mk        马其顿文
 * mk_MK     马其顿文               (马其顿王国)
 * nl        荷兰文
 * nl_BE     荷兰文                 (比利时)
 * nl_NL     荷兰文                 (荷兰)
 * no        挪威文
 * no_NO     挪威文                 (挪威)
 * pl        波兰文
 * pl_PL     波兰文                 (波兰)
 * pt        葡萄牙文
 * pt_BR     葡萄牙文               (巴西)
 * pt_PT     葡萄牙文               (葡萄牙)
 * ro        罗马尼亚文
 * ro_RO     罗马尼亚文             (罗马尼亚)
 * ru        俄文
 * ru_RU     俄文                   (俄罗斯)
 * sh        塞波尼斯-克罗地亚文
 * sh_YU     塞波尼斯-克罗地亚文    (南斯拉夫)
 * sk        斯洛伐克文
 * sk_SK     斯洛伐克文             (斯洛伐克)
 * sl        斯洛文尼亚文
 * sl_SI     斯洛文尼亚文           (斯洛文尼亚)
 * sq        阿尔巴尼亚文
 * sq_AL     阿尔巴尼亚文           (阿尔巴尼亚)
 * sr        塞尔维亚文
 * sr_YU     塞尔维亚文             (南斯拉夫)
 * sv        瑞典文
 * sv_SE     瑞典文                 (瑞典)
 * th        泰文
 * th_TH     泰文                   (泰国)
 * tr        土耳其文
 * tr_TR     土耳其文               (土耳其)
 * uk        乌克兰文
 * uk_UA     乌克兰文               (乌克兰)
 * zh        中文
 * zh_CN     中文                   (中国)
 * zh_HK     中文                   (香港)
 * zh_TW     中文                   (台湾)
 */