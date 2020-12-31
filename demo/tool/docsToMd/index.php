<?php
/**
 * 描述 : 将手册文档转换成markdown格式, 用于gitbub的wiki
 * 作者 : Edgar.lee
 */
require dirname(dirname(dirname(dirname(__FILE__)))) . '/include/of/of.php';

//html输入路径
$htmlIn = dirname(ROOT_DIR) . '/api/docs/oFrame';
//html输出路径, markdown生成路径
$htmlOut = 'E:/git/oWiki';
//拷贝输出路径, 相对输出路径 {目标文件, 原文件}
$copys = array(
    //导航文件
    '/_Sidebar.md' => '/navigation.md',
    //主页文件
    '/home.md' => '/gettingStarted-preface.md'
);
//删除输出路径
$delete = array(
    '/navigation.md'
);
//创建文件内容
$create = array(
    '/_Footer.md' => '手册地址 http://phpof.net/'
);

//html输入路径长度
$htmlInLen = strlen($htmlIn);
//UTF-8+BOM 头
$utf8Bom = chr(239) . chr(187) . chr(191);
//markdown 特殊符号
$specialChar = array(
    // '{' => '\{', '}' => '\}', '[' => '\[', ']' => '\]',
    // '(' => '\(', ')' => '\)', '\\' => '\\\\',
    // '#' => '\#', '+' => '\+', '-' => '\-', '.' => '\.',
    // '!' => '\!', '`' => '\`', '*' => '\*', '_' => '\_'
);

//读取输出路径单层目录
of_base_com_disk::each($htmlOut, $dirs, null);
//删除输出路径非"."开头的目录
foreach ($dirs as $k => &$v) {
    substr(basename($k), 0, 1) === '.' || of_base_com_disk::delete($k);
}

//读取输入路径深层目录
of_base_com_disk::each($htmlIn, $dirs, false);
//将html转化成markdown
foreach ($dirs as $k => &$v) {
    //是文件
    if (!$v) {
        //是html文件
        if (pathinfo($k, PATHINFO_EXTENSION) === 'html') {
            //文件输出路径
            $outPath = $htmlOut . '/' . strtr(substr($k, $htmlInLen + 1, -4), '/', '-') . 'md';
            //解析html内容
            $hObj = new of_base_com_hParse(file_get_contents($k));
            //获取html节点ID
            $oKeys = $hObj->get();

            //查找以"#"开头且不包含"://"的A标签
            $hKeys = of_base_com_hParse::selectors($oKeys, 'a[@^href=[^#][^:]+$@], img[@^src=[^:]+$@]');
            //将A标签href属性".html"改成""
            foreach ($hKeys as &$vh) {
                //路径属性名
                $name = of_base_com_hParse::nodeAttr($vh, 'tagName') === 'img' ? 'src' : 'href';
                //读取href属性
                $temp = of_base_com_hParse::nodeAttr($vh, $name);
                //计算真实磁盘路径
                $temp = of_base_com_str::realpath($k . '/../' . $temp);
                //生成相对网络路径
                $temp = substr($temp, $htmlInLen + 1);

                //包含".html"
                if (stripos($temp, '.html')) {
                    //定位到markdown文件
                    $temp = strtr(str_replace('.html', '', $temp), '/', '-');
                //资源文件
                } else {
                    //重定向资源文件
                    $temp = 'https://raw.githubusercontent.com/wiki/tolizhan/oFrame/' . $temp;
                }

                //回写对应属性值
                of_base_com_hParse::nodeAttr($vh, $name, $temp);
            }

            //查找以PRE标签
            $hKeys = of_base_com_hParse::selectors($oKeys, 'pre');
            //将PRE标签中直接文本节点" "改成"&nbsp;"
            foreach ($hKeys as &$vh) {
                //获取子元素键
                $cKeys = of_base_com_hParse::nodeConn($vh, 'child', false, true);

                //筛选文本类型
                foreach ($cKeys as &$vc) {
                    //是文本节点
                    if (of_base_com_hParse::nodeAttr($vc, 'tagName') === '!text') {
                        //读取文本内容
                        $temp = of_base_com_hParse::nodeAttr($vc, '');
                        //转义markdown特殊字符 && " "换成"&nbsp;"
                        $temp = str_replace(' ', '&nbsp;', strtr($temp, $specialChar));
                        //回写文本内容
                        of_base_com_hParse::nodeAttr($vc, '', $temp);
                    }
                }
            }

            //存在body节点 && 摘取body节点内容
            of_base_com_hParse::selectors($oKeys, 'body') && $hObj = $hObj->find('body');
            //保存到输出路径
            of_base_com_disk::file($outPath, $utf8Bom . trim($hObj->html()));
        //其它文件
        } else {
            //文件输出路径
            $outPath = $htmlOut . substr($k, $htmlInLen);
            //拷贝到输出路径
            of_base_com_disk::copy($k, $outPath);
        }
    }
}

//拷贝输出路径
foreach ($copys as $k => &$v) of_base_com_disk::copy($htmlOut . $v, $htmlOut . $k);
//删除输出路径
foreach ($delete as $k => &$v) of_base_com_disk::delete($htmlOut . $v);
//创建文件内容
foreach ($create as $k => &$v) of_base_com_disk::file($htmlOut . $k, $v);

//更新完成
echo '已更新 ', $htmlOut;