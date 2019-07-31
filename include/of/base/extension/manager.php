<?php
/*
* 描述 : 扩展的安装,暂停,卸载,备份等一系列用户交互操作
* 作者 : Edgar.Lee
*/
class of_base_extension_manager {
    /**
     * 描述 : 获取指定常量
     * 参数 :
     *      key : 指定常量
     * 作者 : Edgar.lee
     */
    public static function getConstant($key) {
        static $constants = null;

        if ($constants === null) {
            $temp = of::config(
                '_of.extension.path',
                OF_DATA . '/include/extensions'
            );
            $constants = array(
                //扩展基类名
                'baseClassName' => strtr(substr($temp, 1), '/', '_') . '_',
                //扩展根目录
                'extensionDir'  => ROOT_DIR . $temp,
                //扩展存储路径
                'extensionSave' => ROOT_DIR . of::config(
                    '_of.extension.save',
                    OF_DATA . '/_of/of_base_extension/save'
                ),
            );

            //创建扩展根目录
            is_dir($constants['extensionDir']) ||
                mkdir($constants['extensionDir'], 0777, true);
            //创建扩展存储路径
            is_dir($constants['extensionSave']) ||
                mkdir($constants['extensionSave'], 0777, true);
        }

        return isset($constants[$key]) ? $constants[$key] : false;
    }

    /**
     * 描述 : 获取扩展信息
     * 参数 :
     *      extensions : 指定扩展列表,null=不加锁读取扩展列表
     * 返回 :
     *      {
     *          扩展文件夹名 : {
     *              'config'  : 扩展的配置文件,包括扩展名,版本描述,匹配信息
     *              'state'   : 扩展状态,0=没有安装,1=运行正常,2=用户暂停(2.1=有一个新版本),3=锁定状态(3.1=配置文件有问题)
     *              'version' : 当前版本,未安装为null
     *          }
     *      }
     * 作者 : Edgar.lee
     */
    public static function getExtensionInfo($extensions = null) {
        //扩展路径
        $extensionDir = self::getConstant('extensionDir');
        //扩展列表
        $extensions === null && $extensions = self::loadConfig(null);

        $handle = opendir($extensionDir);
        while (($fileName = readdir($handle)) !== false) {
            if ($fileName[0] !== '.' && is_dir("{$extensionDir}/{$fileName}")) {
                $temp = $extensions[$fileName]['config'] = self::loadConfig($fileName);
                if (!(
                    //配置是数组
                    is_array($temp) &&
                    //存在版本号
                    isset($temp['properties']['version']) &&
                    //匹配是数组
                    isset($temp['matches']) && is_array($temp['matches'])
                )) {
                    //配置文件有问题
                    $extensions[$fileName]['state'] = '3.1';
                } else if (!isset($extensions[$fileName]['state'])) {
                    //扩展没有安装
                    $extensions[$fileName]['state'] = '0';
                } else if (
                    $extensions[$fileName]['state'] === '3' || 
                    $extensions[$fileName]['state'] === '2' 
                ) {
                    //'锁定'或'暂停'状态
                    //$extensions[$fileName]['state'] = $extensions[$fileName]['state'];
                } else if (
                    isset($extensions[$fileName]['version']) &&
                    $extensions[$fileName]['version'] !== $temp['properties']['version']
                ) {
                    //有一个新版本
                    $extensions[$fileName]['state'] = '2.1';
                } else {
                    //运行正常
                    $extensions[$fileName]['state'] = '1';
                }

                isset($extensions[$fileName]['version']) || $extensions[$fileName]['version'] = null;
            }
        }
        closedir($handle);

        //删除不存在的扩增
        foreach ($extensions as $k => &$v) {
            if (empty($v['config'])) unset($extensions[$k]);
        }
        return $extensions;
    }

    /**
     * 描述 : 安装扩展
     * 参数 :
     *      name    : 安装扩展名
     *      callMsg : 消息回调,默认null
     * 返回 :
     *      成功返回ture,失败返回false
     * 作者 : Edgar.lee
     */
    public static function setupExtension($name, $callMsg = null) {
        //返回值
        $returnBool = true;
        //加锁读取扩展列表
        $extensions = self::loadConfig(true);
        $extensionConfig = self::getExtensionInfo($extensions);
        //扩展引用
        $extensionConfig = &$extensionConfig[$name];

        if (
            //存在扩展
            isset($extensionConfig['state']) &&
            //(没安装扩展 或 需要升级)
            ($extensionConfig['state'] === '0' || $extensionConfig['state'] === '2.1')
        ) {
            //锁定安装扩展
            $extensions[$name]['state'] = '3';
            self::loadConfig($extensions);

            //更新前调用
            if (self::updateCallback('before', $name, $callMsg, $extensionConfig) === true) {
                //安装语言包
                self::updateLanguage($name, true);

                //恢复数据,结构及共享数据
                $returnBool = self::databaseUpdate($name, false, 'installData', $callMsg, array(
                    //升级时不导入默认数据
                    'revertData' => $extensionConfig['state'] === '0'
                ));

                //更新后调用
                $returnBool = self::updateCallback('after', $name, $callMsg, $extensionConfig);
            } else if (is_callable($callMsg)) {
                call_user_func($callMsg, array(
                    'state'   => 'error',
                    'message' => L::getText('无效更新', array('key'=>'of_base_extension_manager::setupExtension')),
                    'info'    => $returnBool === false ? null : $returnBool,
                    'type'    => __FUNCTION__
                ));
                $returnBool = false;
            }
            

            //数据库安装完成,解锁安装扩展
            $extensions = self::loadConfig(true);
            $extensions[$name] = array(
                //版本号
                'version' => $extensionConfig['config']['properties']['version'],
                //扩展状态(真正状态是通过 self::getExtensionInfo 判断得到的)
                'state'   => '1'
            );
            self::loadConfig($extensions);

            return $returnBool;
        //操作失败
        } else {
            //解锁
            self::loadConfig(false);
            return false;
        }
    }

    /**
     * 描述 : 移除扩展
     * 参数 :
     *      name : 移除扩展名
     *      callMsg : 消息回调,默认null
     * 返回 :
     *      成功返回ture,失败返回false
     * 作者 : Edgar.lee
     */
    public static function removeExtension($name, $callMsg = null) {
        //加锁读取扩展列表
        $extensions = self::loadConfig(true);
        $extensionConfig = self::getExtensionInfo($extensions);
        //扩展引用
        $extensionConfig = &$extensionConfig[$name];

        //移除失败
        if (
            //扩展无效
            !isset($extensionConfig['state']) ||
            //锁定状态
            $extensionConfig['state'] === '3' ||
            //未安装
            $extensionConfig['state'] === '0'
        ) {
            //解锁
            self::loadConfig(false);
            return false;
        } else {
            //锁定移除扩展
            $extensions[$name]['state'] = '3';
            self::loadConfig($extensions);
            //更新前调用
            self::updateCallback('remove', $name, $callMsg, $extensionConfig);

            //移除语言包
            self::updateLanguage($name, false);
            //移除数据,结构及共享数据
            self::databaseUpdate($name, null, null, $callMsg);

            //数据库移除完成,删除扩展
            $extensions = self::loadConfig(true);
            //移除扩展
            unset($extensions[$name]);
            self::loadConfig($extensions);

            return true;
        }
    }

    /**
     * 描述 : 扩展备份与恢复
     * 参数 :
     *      name    : 扩展名
     *      callMsg : 消息回调,默认null
     *      dirname : 指定目录名称,默认null=备份数据库,'installData'=更新安装包,字符串=恢复数据库
     * 返回 :
     *      成功返回ture,失败返回false
     * 作者 : Edgar.lee
     */
    public static function dataManager($name, $callMsg = null, $dirname = null) {
        //加锁读取扩展列表
        $extensions = self::loadConfig(true);
        $extensionConfig = self::getExtensionInfo($extensions);
        //扩展引用
        $extensionConfig = &$extensionConfig[$name];

        if (
            //扩展存在
            isset($extensionConfig['state']) &&
            //(没错误没锁定 && 已安装)
            ($extensionConfig['state'] < 3 && $extensionConfig['state'] > 0)
        ) {
            //锁定安装扩展
            $extensions[$name]['state'] = '3';
            self::loadConfig($extensions);

            //开始备份或恢复数据,结构及共享数据
            $returnBool = self::databaseUpdate(
                $name, $dirname === null || $dirname === 'installData', $dirname, $callMsg
            );
            //备份语言包
            $dirname === 'installData' && self::updateLanguage($name, null);

            //数据库安装完成,解锁安装扩展
            $extensions = self::loadConfig(true);
            //恢复原始状态码
            $extensions[$name]['state'] = $extensionConfig['state'];
            self::loadConfig($extensions);

            return $returnBool;
        //操作失败
        } else {
            //解锁
            self::loadConfig(false);
            return false;
        }
    }

    /**
     * 描述 : 更新语言包
     * 参数 :
     *      name   : 扩展名,默认null全部更新
     *      update : false=删除语言包,默认true=更新语言包,null=打包语言包
     * 作者 : Edgar.lee
     */
    public static function updateLanguage($name = null, $update = true) {
        $extensionConfig = self::getExtensionInfo();
        $name === null || $extensionConfig = array($name => &$extensionConfig[$name]);

        if (class_exists('of_base_language_packs', false)) {
            //语言包磁盘路径
            $lRootPath = of::config('_of.language.path', null, 'dir');
            //扩展磁盘路径
            $eRootPath = self::getConstant('extensionDir');
            //扩展相对路径
            $eRelaPath = of::config('_of.extension.path', OF_DATA . '/extensions');

            foreach ($extensionConfig as $name => &$v) {
                //已安装 && 配置文件没问题
                if (isset($v['state']) && $v['state'] > 0 && $v['state'] < 3.1) {
                    //扩展语言包路径
                    $eLanguagePath = "{$eRootPath}/{$name}/_info/language";
                    //有效语言包目录
                    $eLanguageDir = glob($eLanguagePath . '/*', GLOB_ONLYDIR);
                    unset($eLanguageDir[array_search($eLanguagePath . '/base', $eLanguageDir)]);

                    //打包语言包
                    if ($update === null) {
                        //存在语言包
                        if (is_dir($temp = "{$lRootPath}/base/source{$eRelaPath}/{$name}")) {
                            self::deletePath("{$eLanguagePath}/base");
                            self::copyPath($temp, "{$eLanguagePath}/base/source");

                            $index = &of_base_language_toolBaseClass::merge("/base/source{$eRelaPath}/{$name}", "_{$eLanguagePath}/base");
                            //合并基包到其它语言
                            foreach ($eLanguageDir as &$v) {
                                of_base_language_toolBaseClass::pack('_' . $v, $index);
                            }
                        }
                    //卸载或更新安装的语言包
                    } else {
                        foreach ($eLanguageDir as &$v) {
                            //文件夹名称
                            $folderName = basename($v);
                            //系统语言包存在
                            if (is_dir($temp = "{$lRootPath}/{$folderName}")) {
                                //保存原始语言包
                                if (!is_dir("{$temp}/merge")) {
                                    self::copyPath("{$temp}/php.txt", "{$temp}/merge/base/php.txt");
                                    self::copyPath("{$temp}/js.txt", "{$temp}/merge/base/js.txt");
                                }

                                //删除语言包
                                self::deletePath("{$temp}/merge/{$name}");
                                //安装语言包
                                $update && self::copyPath($v, "{$temp}/merge/{$name}");

                                foreach (glob("{$temp}/merge/*", GLOB_ONLYDIR) as $path) {
                                    of::arrayReplaceRecursive($index, of_base_language_toolBaseClass::pack('_' . $path));
                                }
                                of_base_language_toolBaseClass::pack('_' . $temp, $index);
                            };
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * 描述 : 修改状态
     * 参数 :
     *      name  : 扩展名
     *      state : true=强制修改为暂停,1=运行,2=暂停,默认null=自动切换
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    public static function changeState($name, $state = null) {
        //返回布尔
        $returnBool = true;
        //加锁读取扩展列表
        $extensions = self::loadConfig(true);
        $extensionConfig = self::getExtensionInfo($extensions);
        //扩展引用
        $extensionConfig = &$extensionConfig[$name];

        if (isset($extensionConfig['state'])) {
            //运行
            if ($extensionConfig['state'] === '1') {
                $extensions[$name]['state'] = $state === null ? '2' : '1';
            //暂停
            } else if ($extensionConfig['state'] === '2') {
                $extensions[$name]['state'] = $state === null ? '1' : '2';
            //锁定
            } else if ($extensionConfig['state'] === '3' && $state === true) {
                $extensions[$name]['state'] = '2';
            } else {
                $returnBool = false;
            }
        } else {
            $returnBool = false;
        }

        //解锁
        self::loadConfig($returnBool ? $extensions : false);
        return $returnBool;
    }

    /**
     * 描述 : 调整sql语句
     * 作者 : Edgar.lee
     */
    public static function callAdjustSql(&$sql, $type, $param) {
        $sql = preg_replace('@(/\*`N:\w\'\*/)(\'|`)e_[0-9a-z]+_(.*?)\2\1@i', '\2e_' .$param['name']. '_\3\2', $sql);
    }

    /**
     * 描述 : 读取文件
     * 参数 :
     *      dir : null(默认)=普通方式读取扩展列表, true=加锁读取扩展列表, false=解锁扩展文件列表, array=写入扩展文件列表并解锁, 字符串=读取指定扩展文件夹的配置文件, 
     * 返回 :
     *      成功返回文件内容,失败返回false
     * 作者 : Edgar.lee
     */
    private static function loadConfig($dir = null) {
        //引用扩展文件列表流
        static $fopenIndex = null;
        //扩展路径
        $extensionDir = self::getConstant('extensionDir');
        //执行路径
        $extensionRun = self::getConstant('extensionSave');

        //读取配置文件
        if (is_string($dir)) {
            //配置文件存在 && 是数组
            return is_file($temp = "{$extensionDir}/{$dir}/config.php") && is_array($config = include $temp) ?
                $config : false;
        //读取扩展列表
        } else {
            is_dir($extensionRun) || @mkdir($extensionRun, 0777, true);

            if ($fopenIndex === null) {
                //兼容 php<5.2.6 代码
                $fopenIndex = fopen($filePath = "{$extensionRun}/extensions.php", is_file($filePath) ? 'r+' : 'x+');
            }
            //无权限
            if ($fopenIndex === false) {
                trigger_error("'{$extensionRun}/extensions.php' Permission denied"); return array();
            }

            //已加锁或不加锁方式读取扩展列表
            if ($dir === null || $dir === true) {
                //$dir === true ? 独享锁 : 共享锁
                flock($fopenIndex, $dir ? LOCK_EX : LOCK_SH);
                fseek($fopenIndex, 15);
                do {
                    $temp[] = fread($fopenIndex, 1024);
                } while (!feof($fopenIndex));
                //$dir !== true则关闭连接
                !$dir && fclose($fopenIndex) && $fopenIndex = null;

                return ($temp = join($temp)) === '' ? array() : unserialize($temp);
            // $dir 是数组或false
            } else {
                //写入数据
                if (is_array($dir)) {
                    //独享锁
                    flock($fopenIndex, LOCK_EX);
                    fseek($fopenIndex, 0);
                    ftruncate($fopenIndex, 0);
                    fwrite($fopenIndex, '<?php exit; ?> ' . serialize($dir));
                }
                //解锁关闭文件流
                fclose($fopenIndex) && $fopenIndex = null;
            }
        }
    }

    /**
     * 描述 : 扩展安装升级移除回调
     * 参数 :
     *      type   : 回调类型
     *      name   : 扩展键
     *     &call   : 调用消息
     *     &config : 扩展配置文件
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    private static function updateCallback($type, $name, &$call, &$config) {
        //更新调用参数
        $callParam = array(
            //回调消息
            'callMsgFun' => $call,
            //当前版本
            'nowVersion' => isset($config['version']) ? $config['version'] : null,
            //更新版本
            'newVersion' => $type === 'remove' ? null : $config['config']['properties']['version'],
            //回调类型
            'position'   => $type === 'remove' ? 'before' : $type,
            //当前状态(true=更新正常,false=更新失败)
            'state'      => true
        );

        //更新前调用
        try {
            isset($config['config']['update'][$callParam['position']]) && 
            of_base_extension_match::callExtension($name, $config['config']['update'][$callParam['position']], array(&$callParam));
        } catch (Exception $e) {
            of_base_error_writeLog::phpLog($e);
        }

        return $callParam['state'];
    }

    /**
     * 描述 : 删除指定文件或文件夹
     * 参数 :
     *      path : 指定删除路径
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    private static function deletePath($path) {
        if (is_file($path)) {
            return unlink($path);
        } else if (is_dir($path)) {
            if ($dp = opendir($path)) {
                while (($file=readdir($dp)) !== false) {
                    if ($file !== '.' && $file !== '..') {
                        self::deletePath($path .'/'. $file);
                    }
                }
                closedir($dp);
            }
            return rmdir($path);
        }
    }

    /**
     * 描述 : 复制指定文件或文件夹
     * 参数 :
     *      source : 指定源路径
     *      dest   : 指定目标路径
     * 返回 :
     *      成功返回true,失败返回false
     * 作者 : Edgar.lee
     */
    private static function copyPath($source, $dest) {
        if (is_file($source)) {
            //创建目录
            is_dir($isDir = dirname($dest)) || mkdir($isDir, 0777, true);
            return copy($source, $dest);
        } else if (is_dir($source)) {
            //创建目录
            is_dir($dest) || mkdir($dest, 0777, true);
            if ($dp = opendir($source)) {
                while (($file=readdir($dp)) != false) {
                    if ($file !== '.' && $file !== '..') {
                        self::copyPath("{$source}/{$file}", "{$dest}/{$file}");
                    }
                }
                closedir($dp);
            }
            return true;
        }
    }

    /**
     * 描述 : 更新扩展的数据库
     * 参数 :
     *      name    : 扩展名
     *      type    : 更新类型, true=备份, false=还原, null=卸载
     *      dirname : 指定备份恢复的文件夹名, null=自动生成文件夹
     *      callMsg : 信息回调
     *      extra   : 额外数据
     * 返回 :
     *      成功返回true, 失败返回false
     * 作者 : Edgar.lee
     */
    private static function databaseUpdate($name, $type = null, $dirname = null, $callMsg = null, $extra = array()) {
        //最终结果
        $returnBool = true;
        //扩展_info根目录
        $infoPath = self::getConstant('extensionDir') . "/{$name}/_info";
        //扩展执行路径
        $execPath = self::getConstant('extensionSave') . "/{$name}/_info";
        is_dir($execPath) || @mkdir($execPath, 0777, true);
        //读取扩展备份数据库
        $eDbList = self::loadConfig($name);
        $eDbList = isset($eDbList['database']) ? $eDbList['database'] : array(
            'default' => array()
        );

        //遍历备份
        foreach ($eDbList as $kd => &$vd) {
            if ($returnBool && $returnBool = of_base_tool_mysqlSync::init(array(
                'adjustSqlParam' => array('name' => strtolower($name)),
                'callAdjustSql'  => 'of_base_extension_manager::callAdjustSql',
                'callDb'         => array(
                    'asCall' => 'of_db::sql',
                    'params' => array('_' => 1, $kd)
                ),
                'callMsg'        => $callMsg,
                'sqlMark'        => true,
                //匹配项默认值
                'matches'        => array(
                    'table'      => array(
                        'include' => array("@^e_{$name}_@i")
                    ),
                    'view'       => false,
                    'procedure'  => false,
                    'function'   => false,
                )
            ))) {
                //备份
                if ($type === true) {
                    //初始文件夹名
                    if ($dirname === 'installData') {
                        $backupPath = $infoPath . '/backupData/installData/' . $kd;
                    } else {
                        $backupPath = $execPath . '/backupData/' .
                            date('YmdHis/', $_SERVER['REQUEST_TIME']) . $kd;
                    }
                    //删除路径
                    self::deletePath($backupPath);
                    //备份结构
                    if ($returnBool = of_base_tool_mysqlSync::backupBase($backupPath . '/structure.sql')) {
                        $returnBool = of_base_tool_mysqlSync::backupData(
                            $backupPath . '/backupData.sql',
                            array('type' => $dirname === null ? 'INSERT' : 'REPLACE')
                        );

                        //备份数据
                        if ($returnBool) {
                            self::copyPath(
                                "{$execPath}/sharedData/data.php",
                                dirname($backupPath) . '/sharedData.php'
                            );
                        }
                    }

                    //备份失败删除路径
                    $returnBool || self::deletePath(dirname($backupPath));
                //恢复
                } else if ($type === false) {
                    //默认恢复数据
                    $extra += array('revertData' => true);
                    //恢复磁盘路径
                    $ePath = $dirname === 'installData' ? $infoPath : $execPath;
                    $ePath .= '/backupData/' . $dirname;
                    //恢复文件路径
                    $rPath = $ePath . '/' . $kd;

                    if (
                        is_dir($rPath) ||
                        //兼容不支持多数据库版本的模式
                        $kd === 'default' &&
                        is_dir($rPath = $ePath)
                    ) {
                        //恢复结构
                        if (
                            ($returnBool = of_base_tool_mysqlSync::revertBase($rPath . '/structure.sql')) &&
                            $extra['revertData']
                        ) {
                            //恢复数据
                            $returnBool = of_base_tool_mysqlSync::revertData($rPath . '/backupData.sql');
                        }
                    }

                    //恢复共享数据
                    $extra['revertData'] && self::copyPath(
                        "{$ePath}/sharedData.php",
                        "{$execPath}/sharedData/data.php"
                    );
                //卸载(创建临时文件)
                } else if ($returnBool = tempnam(sys_get_temp_dir(), '')) {
                    //删除匹配数据
                    $returnBool = of_base_tool_mysqlSync::revertBase($temp = $returnBool);
                    //删除共享数据
                    self::deletePath($execPath . '/sharedData');
                    //删除临时文件
                    unlink($temp);
                }
            }
        }

        return (boolean)$returnBool;
    }
}

//3.1 : Configuration file error   : 配置文件有问题     : 无
//3   : Upgrading                  : 正在升级           : 强制停止
//2.1 : A new version is available : 有一个新版本       : 更新,卸载,备份,恢复
//2   : Pause                      : 用户暂停           : 运行,卸载,备份,恢复,更新语言包
//1   : Running                    : 正常运行           : 暂停,卸载,备份,恢复,更新语言包
//0   : Not installed              : 指定的扩展没有安装 : 安装