<?php
class demo_index extends L {
    /**
     * 描述 : 请求路径规范
     * 作者 : Edgar.lee
     */
    public function index() {
        echo '返回数组时转成json: ';
        return of::dispatch();
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
                ROOT_URL . '/index.php?c=demo_index&a=index',
                null, 
                array('asCall' => array($this, __FUNCTION__))
            );
            echo '5s 钟后会在 "' . ROOT_DIR . OF_DATA . '/asynTest.txt" 目录中创建一个文件';
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
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
</head>
<body>

<span id="result">按照 jquery 的规则读取这段文本</span>
<div style="background-color:blue;"></div>
<div style="background-color:rgb(15,99,30);"></div>

<div style="background-color:#123456;"></div>
<div style="background-color:#f11;"></div>
<script>
$("div").click(function () {
  var color = $(this).css("background-color");
});

</script>
</body>
</html>');
        echo $hParseObj->find('#result')->text();
    }

    /**
     * 描述 : 多维数组排序
     * 作者 : Edgar.lee
     */
    public function arraySort() {
        $data[] = array('volume' => 67, 'volu.me1' => 67, 'edition' => 2 );
        $data['a'] = array('volume' => 86, 'volu.me1' => 85, 'edition' => 86);
        $data[] = array('volume' => 85, 'volu.me1' => 86, 'edition' => 6 );
        $data[] = array('volume' => 98, 'volu.me1' => 98, 'edition' => 6 );
        $data[] = array('volume' => 86, 'volu.me1' => 86, 'edition' => 98);
        $data['m'] = array('volume' => 67, 'volu.me1' => 67, 'edition' => 0 );
        print_r($data);
        $this->_com->arraySort($data, array('0' => 1, 'a' => 2, '2' => 3, '1' => 7, 'm' => 5, '3' => 6), 'desc');
        print_r($data);
    }

    /*
     * 描述 : 测试缓存
     * 作者 : Edgar.lee
     */
    public function cache() {
        $temp = &$this->_com->cache('demo_index::cache', array('key' => true), 'bb');
        echo $temp, '<br/>';
        echo $this->_com->cache('demo_index::cache', array('key' => false)) === null, '<br/>';
        echo $this->_com->cache('demo_index::cache', array('key' => 1 < 2), 'bb'), '<br/>';
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
        echo '下面的两个错误和一个异常将写到'. ROOT_DIR . of::config('_of.error.phpLog') . date('/Y/m/d') . '这个文件中';
        //错误
        1/0;
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
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header ('Cache-Control: cache, must-revalidate');
        header ('Pragma: public');

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
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header ('Cache-Control: cache, must-revalidate');
        header ('Pragma: public');

        $objWriter = PHPWord_IOFactory::createWriter($PHPWord, 'Word2007');
        $objWriter->save('php://output');
    }

    /**
     * 描述 : 新分页演示
     * 作者 : Edgar.lee
     */
    function &paging($params = array('width' => 100)) {
        //模拟sql语句数据
        if( $this->get('c') === 'of_base_com_com' ) {
            for($i = 0; $i < $_POST['size']; $i++) {
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
    function captcha() {
        echo $this->_com->captcha($this->post('captcha'));
    }
}

//允许外网访问
return true;