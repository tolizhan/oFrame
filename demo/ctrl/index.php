<?php
class ctrl_index extends L {
    /**
     * 描述 : 请求路径规范
     * 作者 : Edgar.lee
     */
    public function index() {
        echo '返回数组时转成json: ';
        return of::dispatch();
    }

    /**
     * 描述 : 防火墙拦截IP
     * 作者 : Edgar.lee
     */
    public function network() {
        echo '在 /demo/config/network.php 文件中查看演示配置';
    }

    /**
     * 描述 : 开启工作演示
     * 作者 : Edgar.lee
     */
    public static function workTest() {
        try {
            //开始工作, 会启动"default"事务, 返回 {"code" : 200, "info" : "Successful", "data" : []}
            $result = of::work(array('default'));

            //生成一个演示错误
            trigger_error('产生一个错误');
            //无错误返回 null, 否则返回 {"code" : 编码, "info" : 错误, "file" : 路径, "line" : 行数, ...}
            echo '是否产生错误: ', of::work('error') ? '是' : '否', "<br>\n";
            //清除当前工作错误
            of::work('error', false);

            //添加延迟调用到工作结束前(依然在事务中)
            of::work('defer', array(
                'onWork' => null,
                'asCall' => 'var_dump',
                'params' => array(
                    "\n<br>执行延迟回调: "
                )
            ), __METHOD__);

            //添加完成调用到工作结束前(在父级工作中)
            of::work('done', array(
                'onWork' => of::work('info', 2),
                'asCall' => 'var_dump',
                'params' => array(
                    "\n<br>执行完成回调: "
                )
            ), __METHOD__);

            //不在工作中返回 null, 反之返回 {"list" : [监听连接池, ...]}
            echo '是否在工作中: ', of::work('info') ? '是' : '否', "<br>\n";

            //查询工作时间
            echo '工作开始时间: ', of::work('time'), "<br>\n";
            echo '工作开始时间戳: ', of::work('time', 1), "\n";

            //演示两种不同异常
            of::work(401, '工作异常不会抛错', array(1, 2, 3));
            throw new Exception('常规异常会被记录');

            //完结工作, true=提交事务, false=回滚事务, 失败会抛出常规异常
            of::work(true);
        } catch (Exception $e) {
            //捕获异常, 常规异常记录日志, 返回"接口响应结构"
            $result = of::work($e);
        }

        //打印结果集
        echo '<br>工作完成结果: ';
        print_r($result);
    }

    /**
     * 描述 : 常规开发演示
     * 作者 : Edgar.lee
     */
    public function viewTest() {
        //在模板中使用$this->str得到
        $this->view->str = '这是从控制层发送过来的字符串';
        //常规分页调用
        $this->view->_pagingHtml = &$this->paging();
        //加载模板
        $this->display();
    }

    /**
     * 描述 : 异步请求测试
     * 作者 : Edgar.lee
     */
    public function asyn($r = null) {
        if ($r) {
            sleep(5);
            file_put_contents(ROOT_DIR . OF_DATA . '/asynTest.txt', print_r($r, true));
        } else {
            of_base_com_net::request(
                //请求一个地址
                ROOT_URL . '/index.php?c=ctrl_index&a=index',
                null,
                array('asCall' => array($this, __FUNCTION__))
            );
            echo '5s 钟后会在 "' . ROOT_DIR . OF_DATA . '/asynTest.txt" 目录中创建一个文件';
        }
    }

    /**
     * 描述 : 任务回调测试
     * 作者 : Edgar.lee
     */
    public function task($r = null) {
        if ($r) {
            sleep(5);
            return '回调返回时间: ' . date('Y-m-d H:i:s', time());
        } else {
            echo '任务开始创建: ' . date('Y-m-d H:i:s', time()), "<br>\n";
            of_base_com_timer::task(array(
                'call' => array($this, __FUNCTION__)
            ), $task);
            echo '任务创建完成: ' . date('Y-m-d H:i:s', time()), "<br><br>\n\n";

            echo '异步读取数据: ' . var_export($task->result(0), true), "<br>\n";
            echo '异步取出时间: ' . date('Y-m-d H:i:s', time()), "<br><br>\n\n";

            echo '同步读取数据: ' . var_export($task->result(), true), "<br>\n";
            echo '同步取出时间: ' . date('Y-m-d H:i:s', time()), "<br>\n";
        }
    }

    /**
     * 描述 : 演示消息队列
     * 作者 : Edgar.lee
     */
    public static function mqTest($params = null) {
        //触发消息队列
        if ($params) {
            //写入当前并发数据
            $data = of_base_com_timer::data(array('params' => $params));
            //记录到日志
            file_put_contents(
                ROOT_DIR . OF_DATA . '/mqTest.txt',
                time() . print_r($data, true),
                FILE_APPEND | LOCK_EX
            );
            return true;
        //生产消息队列
        } else {
            if (of::config('_of.com.mq.exchange')) {
                echo '异步并发消息回调将在此文件中写入数据: ',
                    ROOT_DIR . OF_DATA . '/mqTest.txt';
                L::sql(null);
                //批量创建消息队列, queue1 与 queue2 将同时收到信息, 延迟一分钟执行
                of_base_com_mq::set(array(
                    array('keys' => 'key', 'data' => array(1, 2, 3)),
                    array('keys' => array('key'), 'data' => array(4, 5, 6)),
                    array('keys' => array('key1', '延迟ID', 600), 'data' => '消息信息(可传数组)'),
                ), 'exchange');
                //因 queue2 没有 key1 键, 所以仅 queue1 会收到信息
                of_base_com_mq::set(array('key1', '消息ID'), '消息信息(可传数组)', 'exchange');
                L::sql(true);
            } else {
                echo '先取消/demo/config/config.php下_of.com.mq的注释';
            }
        }
    }

    /**
     * 描述 : html解析演示
     * 作者 : Edgar.lee
     */
    public function hParse() {
        $hParseObj = $this->_hParse->html('<!DOCTYPE html>
<html>
<head>
    <style>
        div { width:60px; height:60px; margin:5px; float:left; }
    </style>
</head>
<body>
<span id="result">按照 jquery 的规则读取这段文本</span>
<div style="background-color:blue;"></div>
<div style="background-color:rgb(15,99,30);"></div>
<div style="background-color:#123456;"></div>
<div style="background-color:#f11;"></div>
</body>
</html>');
        echo $hParseObj->find('#result')->text();
    }

    /**
     * 描述 : 多维数组排序
     * 作者 : Edgar.lee
     */
    public function arraySort() {
        $data[] = array('volume' => array('abc' => 4), 'volu.me1' => 67, 'edition' => 2 );
        $data['a'] = array('volume' => array('abc' => 2), 'volu.me1' => 67, 'edition' => 86);
        $data[] = array('volume' => array('abc' => 13), 'volu.me1' => 86, 'edition' => 6 );
        $data[] = array('volume' => array('abc' => 6), 'volu.me1' => 98, 'edition' => 6 );
        $data[] = array('volume' => array('abc' => 555), 'volu.me1' => 86, 'edition' => 98);
        $data['m'] = array('volume' => array('abc' => 2), 'volu.me1' => 85, 'edition' => 100);
        print_r($data);
        $this->_com->arraySort($data, array("volume.abc" => 'ASC,REGULAR', 'volu`.me1' => 'DESC'));
        print_r($data);
    }

    /*
     * 描述 : 演示会话缓存
     * 作者 : Edgar.lee
     */
    public function cache() {
        $temp = &$this->_com->cache('ctrl_index::cache', array('key' => true), 'bb');
        echo $temp, '<br/>';
        echo $this->_com->cache('ctrl_index::cache', array('key' => false)) === null, '<br/>';
        echo $this->_com->cache('ctrl_index::cache', array('key' => 1 < 2), 'bb'), '<br/>';
    }

    /**
     * 描述 : 文本加密解密
     * 作者 : Edgar.lee
     */
    public function txtEncrypt() {
        echo '加密 : ', $temp = $this->_str->rc4('密码', '测试', true), '<br/>';
        echo '解密 : ', $this->_str->rc4('密码', $temp, false), '<br/>';
    }

    /**
     * 描述 : 扩展开发演示
     * 作者 : Edgar.lee
     */
    public function extendDemo() {
        echo '这段是页面输出的文字 : <font color=red>扩展会修改这段文字, 未修改</font>';
    }

    /**
     * 描述 : html模板开发
     * 作者 : Edgar.lee
     */
    public function htmlTpl() {
        $this->display('/html/htmlTpl.html');
    }

    /**
     * 描述 : debug错误提示
     * 作者 : Edgar.lee
     */
    public function phpError() {
        echo '下面的错误和异常将写到'. ROOT_DIR . of::config('_of.error.phpLog') . date('/Y/m/d') . '这个文件中';
        //错误
        trigger_error("A custom error has been triggered");
        //异常
        throw new Exception("Value must be 1 or below");
    }

    /**
     * 描述 : 插件excel演示
     * 作者 : Edgar.lee
     */
    public function excel() {
        //开启 php excel
        L::open('excel');
        $excelObj = new PHPExcel;
        $excelObj->getProperties()->setCreator("Maarten Balliauw")
             ->setLastModifiedBy("Maarten Balliauw")
             ->setTitle("Office 2007 XLSX Test Document")
             ->setSubject("Office 2007 XLSX Test Document")
             ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
             ->setKeywords("office 2007 openxml php")
             ->setCategory("Test result file");

        $excelObj->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Hello')
            ->setCellValue('B2', 'world!')
            ->setCellValue('C1', 'Hello')
            ->setCellValue('D2', 'world!');

        $excelObj->setActiveSheetIndex(0)
            ->setCellValue('A4', 'Miscellaneous glyphs')
            ->setCellValue('A5', 'UTF-8 编码');

        $excelObj->getActiveSheet()->setTitle('标签名');

        // IE 与 ssl 需要的信息
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        //下载 xls
        header('Content-Disposition: attachment;filename="01simple.xls"');

        $objWriter = PHPExcel_IOFactory::createWriter($excelObj, 'Excel5');
        $objWriter->save('php://output');
    }

    /**
     * 描述 : 插件word演示
     * 作者 : Edgar.lee
     */
    public function word() {
        //开启 php word
        L::open('word');
        $PHPWord = new PHPWord();
        $PHPWord->addFontStyle('rStyle', array('bold' => true, 'italic' => true, 'size' => 16));
        $PHPWord->addParagraphStyle('pStyle', array('align' => 'center', 'spaceAfter' => 100));
        $PHPWord->addTitleStyle(1, array('bold' => true), array('spaceAfter' => 240));

        // New portrait section
        $section = $PHPWord->createSection();

        // Simple text
        $section->addTitle('Welcome to PHPWord', 1);
        $section->addText('UTF-8 编码!');

        // Two text break
        $section->addTextBreak(2);

        // Defined style
        $section->addText('I am styled by a font style definition.', 'rStyle');
        $section->addText('I am styled by a paragraph style definition.', null, 'pStyle');
        $section->addText('I am styled by both font and paragraph style.', 'rStyle', 'pStyle');
        $section->addTextBreak();

        // Inline font style
        $fontStyle['name'] = 'Times New Roman';
        $fontStyle['size'] = 20;
        $fontStyle['bold'] = true;
        $fontStyle['italic'] = true;
        $fontStyle['underline'] = 'dash';
        $fontStyle['strikethrough'] = true;
        $fontStyle['superScript'] = true;
        $fontStyle['color'] = 'FF0000';
        $fontStyle['fgColor'] = 'yellow';
        $section->addText('I am inline styled.', $fontStyle);
        $section->addTextBreak();

        // Link
        $section->addLink('http://www.google.com', null, 'NLink');
        $section->addTextBreak();

        //下载 doc
        header('Content-Disposition: attachment;filename="01simple.docx"');

        // IE 与 ssl 需要的信息
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $objWriter = PHPWord_IOFactory::createWriter($PHPWord, 'Word2007');
        $objWriter->save('php://output');
    }

    /**
     * 描述 :x新分页演示
     * 作者 : Edgar.lee
     */
    public function &paging($params = array('width' => 100)) {
        //模拟sql语句数据
        if ($this->get('c') === 'of_base_com_com') {
            for ($i = 0; $i < $_POST['size']; $i++) {
                $data[] = array('t1' => $_POST['page'] * $_POST['size'] + $i + 1, 'value' => 'data ' . $i, 'id' => $i);
            }
        }
        $config = array(
            ''     => array(
                '_attr' => array(
                    //列头前文本
                    'html' => '<input type="checkbox" id="checkbox" class="checkbox" name="checkbox">'
                )
            ),
            '排序' => array(
                '_attr' => array(
                    //列头属性
                    'attr' => 'style="text-align: left;"',
                    'body' => array(
                        'attr' => 'id={`id`}',
                        'html' => '请查看元素ID'
                    )
                )
            ),
            '题型的标题' => '',
            '分/题'      => '',
            '试题分类'   => '',
            '知识点'     => '',
            '难度'       => array(
                '不限'  => '', '较易' => '{`t1`}', '易' => '测试{`t1`}', '一般' => '', '难' => '', '较难' => '{`value`}', '很难' => '',
            ),
            '_attr' => array(
                'attr'   => "style='width : {$params['width']}%'",
                'data'   => &$data,
                'empty'  => '无数据',
                'size'   => 5,
                //12页数据
                'items'  => 60,
                'params' => &$params,
                'fbar'   => '<a href="#" onclick="return false;" class="">删除</a><a href="#" class="">启用</a><a href="#" class="">禁用</a>' .
                    '<a href="#" onclick="return false;" class="">删除</a><a href="#" class="">启用</a><a href="#" class="">禁用</a>' .
                    '<a href="#" onclick="return false;" class="">删除</a><a href="#" class="">启用</a><a href="#" class="">禁用</a>' .
                    '<a href="#" onclick="return false;" class="">删除</a><a href="#" class="">启用</a><a href="#" class="">禁用</a>',
                'method' => __METHOD__
            )
        );
        return $this->_com->paging($config);
    }

    //验证码校验
    public function captcha() {
        echo $this->_com->captcha($this->post('captcha'));
    }
}

//允许外网访问
return true;