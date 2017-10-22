<?php
//扩展配置变量
//$config = null;
//最近一次保存的毫秒
//$mTime = 0;

/**
 * 描述 : 注入监听语句
 * 作者 : Edgar.lee
 */
function initSql($params = null) {
    //SQL调用前
    if (isset($params['sql'])) {
        //移除自身
        of::event('of_db::before', false, array($this, __FUNCTION__));
        //SQL执行前(最后位置)
        of::event('of_db::before', array($this, 'beforeSql'));
        //SQL执行后(最前位置)
        $index = &of::event('of_db::after', null);
        array_unshift($index['list'], array(
            'isCall' => true,
            'event'  => array($this, 'afterSql'),
            'change' => false
        ));
    //调度接入
    } else {
        $this->config = include $this->_getConst('eDir') . '/config.php';
        //监听SQL
        of::event('of_db::before', array($this, __FUNCTION__));
    }
}

/**
 * 描述 : SQL执行前回调
 * 作者 : Edgar.lee
 */
function beforeSql($params) {
    //记录时间戳
    $this->mTime = $this->getMtime();
}

/**
 * 描述 : SQL执行前回调
 * 作者 : Edgar.lee
 */
function afterSql($params) {
    //相差毫秒
    $subTime = $this->getMtime() - $this->mTime;

    //异常毫秒时间
    if ($subTime > $this->config['config']['eMtime']) {
        //回溯列表格式化
        $backtrace = debug_backtrace();

        //生成错误列表
        $logData = array(
            'logType'       => 'profiling',
            'time'          => time(),
            'environment'   => array(
                'type'      => $subTime . 'ms::' . $params['pool'],
                'message'   => &$params['sql'],
                'file'      => '(',
                'line'      => 0,
                'backtrace' => &$backtrace,
                'envVar'    => array(
                    '_GET'     => &$_GET,
                    '_POST'    => &$_POST,
                    '_COOKIE'  => &$_COOKIE,
                    '_SESSION' => &$_SESSION,
                    '_SERVER'  => &$_SERVER,
                    '_REQUEST' => &$_REQUEST,
                    'iStream'  => file_get_contents('php://input'),
                )
            )
        );

        //删除干扰回溯
        foreach ($backtrace as $k => &$v) {
            //SQL基类
            if (
                isset($v['class']) && $v['class'] === 'of_db' &&
                isset($v['function']) && $v['function'] === 'sql'
            ) {
                array_splice($backtrace, 0, $k);
                break;
            }
        }

        //定位SQL路径
        foreach($backtrace as $k => &$v) {
            //大部分正常方式
            if (isset($v['file'])) {
                if (
                    empty($v['class']) && 
                    isset($v['function']) &&
                    $v['function'] === 'trigger_error' &&
                    strpos($v['file'], '(')
                ) {
                    // eval 中的 trigger_error 方法报错为 无效定位
                    continue ;
                }
                $temp = array(strtr($v['file'], '\\', '/'));
            //回调中的类是通过 eval 生成的
            } else if (isset($v['class'])) {
                $temp = array(ROOT_DIR . '/' . strtr($v['class'], '_', '/') . '.php');
            //无法定位文件
            } else {
                continue ;
            }

            if (strncmp(OF_DIR, $temp[0], strlen(OF_DIR))) {
                //在eval中
                if (($temp[1] = strpos($temp[0], '(')) !== false) {
                    $logData['environment']['file'] = substr($temp[0], 0, $temp[1]);
                    $logData['environment']['line'] = substr($temp[0], $temp[1] + 1, strpos($temp[0], ')') - $temp[1] - 1);
                //正常执行的错误
                } else {
                    $logData['environment']['file'] = $temp[0];
                    //通过 eval 编译的类 无 line
                    isset($v['line']) && $logData['environment']['line'] = $v['line'];
                }
                array_splice($backtrace, 0, $k);
                break;
            }
        }

        //写入日志
        $logPath = $this->_getConst('eDir') . '/_info/log' . date('/Y/m/d', $logData['time']) . 'profiling';
        is_dir($temp = dirname($logPath)) || mkdir($temp, 0777, true);
        file_put_contents(
            $logPath, 
            strtr(serialize($logData), array("\r\n" => ' ' . ($temp = chr(0)), "\r" => $temp, "\n" => $temp)) . "\n", 
            FILE_APPEND | LOCK_EX
        );

        //日志有时限 && 1%的机会清理
        if(rand(0, 99) === 1) {
            //日志生命期
            $gcTime = $logData['time'] - 30 * 86400;
            $logPath = dirname($logPath);

            //执行清洗
            if( of_base_com_disk::each($logPath, $data, false) ) {
                foreach($data as $k => &$v) {
                    //是文件 && 文件已过期
                    if( $v === false && filectime($k) <= $gcTime ) {
                        //删除文件及父空文件夹
                        of_base_com_disk::delete($k, true);
                    }
                }
            }
        }
    }
}

/**
 * 描述 : 消息日志
 * 作者 : Edgar.lee
 */
function logMsg($params = null) {
    //::halt 事件
    if (isset($params['parse'])) {
        $parseObj = $params['parse']('obj');
        $logPath = $this->_getConst('eDir') . '/_info/log';

        //性能日志导航
        $pNavObj = $parseObj->find('div.nav')
            ->append('<label><input type="radio" value="profiling" onclick="toolObj.tabSwitch(this)" >profiling</label>');

        //性能日志主体
        $pBodyObj = $parseObj->find('#js')
            ->clones()->insertAfter('#js')
            ->attr('id', 'profiling');

        //修改日志年份
        $pBodyObj->find('.tool select > option:gt(1)')->remove();
        if (of_base_com_disk::each($logPath, $data)) {
            foreach ($data as $k => &$v) {
                $v = '<option value="/' .($k = basename($k)). '">' .$k. '</option>';
            }
            $pBodyObj->find('.tool select')->append(join($data));
        }

        //重定向分页
        $pBodyObj->find('table[name=pagingBlock]')
            ->attr('method', __CLASS__ . '::' . 'logPaging');
    //数据响应
    } else if ($_POST) {
        //请求性能路径
        if (
            isset($_POST['type']) && 
            $_POST['type'] === 'getDir' && 
            $_POST['logType'] === 'profiling'
        ) {
            //响应结果
            $result = array('state' => true);

            $logPath = $this->_getConst('eDir') . '/_info/log';
            $pathLen = strlen($logPath);
            $temp = $logPath . $_POST['path'];
            if (of_base_com_disk::each($temp, $data)) {
                foreach ($data as $k => &$v) {
                    $result['data'][substr($k, $pathLen)] = &$v;
                }
            }

            echo json_encode($result);
            exit;
        }
    //界面载入
    } else {
        $this->_addHook('::halt', array($this, __FUNCTION__));
    }
}

/**
 * 描述 : 读取日志分页
 * 作者 : Edgar.lee
 */
function logPaging($params = array()) {
    $data = array();
    if( isset($params['path']) ) {
        $filePath = $this->_getConst('eDir') . '/_info/log' . $params['path'];
        $totalItems = empty($_POST['items']) ? $this->fileS($filePath) : $_POST['items'];
        $temp = $this->fileS($filePath, isset($_POST['page']) ? $_POST['page'] : 1, isset($_POST['size']) ? $_POST['size'] : 10);

        foreach($temp as $k => &$v) {
            $data[$k]['_time'] = date('/Y/m/d H:i:m', $v['time']);
            $data[$k]['_code'] = isset($v['environment']['type']) ? $v['environment']['type'] : $v['logType'];
            $data[$k]['_file'] = $v['environment']['file'];
            $data[$k]['_line'] = $v['environment']['line'];
            $data[$k]['_message'] = '<pre>' . strtr(htmlentities($v['environment']['message'], ENT_QUOTES, 'UTF-8'), array("\0" => "\n", "\n" => '<br>', ' ' => '&nbsp;')) . '</pre>';
            //防止非UTF8不显示
            $data[$k]['_detaile'] = iconv('UTF-8', 'UTF-8//IGNORE',
                strtr(
                    htmlspecialchars(print_r($v['environment'], true)), 
                    array("\0" => "\n", "\n" => '<br>', ' ' => '&nbsp;')
                )
            );
        }
    } else {
        $totalItems = -1;
    }

    $config = array(
        '详细' => array(
            '_attr' => array(
                'attr' => 'class="center"',
                'body' => '<input name="radio" type="radio" /><div style="display:none;">{`_detaile`}</div>',
                'html' =>  '<div class="of-paging_action"><a name="pagingFirst" class="of-paging_first" href="#">&nbsp;</a><a name="pagingPrev" class="of-paging_prev" href="#">&nbsp;</a><a name="pagingNext" class="of-paging_next" href="#">&nbsp;</a><a name="pagingLast" class="of-paging_last" href="#">&nbsp;</a><span name="pagingPage" class="of-paging_page">1/1</span><input name="pagingJump" class="of-paging_jump" type="text"><input name="pagingSize" class="of-paging_size" type="text"></div>'
            )
        ),
        '时间' => '{`_time`}',
        '文件' => '{`_file`}',
        '行数' => '{`_line`}',
        '类型' => '{`_code`}',
        '信息' => '{`_message`}',
        '_attr' => array(
            'data'   => $data,
            'params' => $params,
            'items'  => $totalItems,
            'action' => '',
            'method' => __METHOD__,
        )
    );

    return of_base_com_com::paging($config);
}

function &fileS($filePath, $curPage = null, $pageSize = 10) {
    $data = array();

    if( is_file($filePath) ) {
        $line = 0;                       //当前行
        $fp = fopen($filePath , 'r');    //打开读写流

        if( $curPage === null ) {
            $data = &$line;
        } else {
            $curPage = abs(($curPage - 1) * $pageSize);
            $curSize = $curPage + $pageSize;
        }

        while( $curPage === null || $curSize > $line ) {
            if( $temp = fgets($fp) ) {
                if($curPage !== null && $curPage <= $line && $curSize > $line ) {
                    $data[$line] = unserialize($temp);
                }
                $line += 1;
            } else {
                break;
            }
        }
        fclose($fp);
    } else {
        $data = self::msg('文件不存在');
    }

    return $data;
}

/**
 * 描述 : 获取当前(后5位秒数+前3位毫秒)的整数
 * 作者 : Edgar.lee
 */
function getMtime() {
    $temp = explode(' ', microtime());
    return intval(substr($temp[1], -5) . substr($temp[0], 2, 3));
}