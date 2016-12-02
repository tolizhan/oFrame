<?php
isset($_GET['sessionId']) && $_COOKIE[session_name()] = $_GET['sessionId'];
require_once dirname(dirname(dirname(__FILE__))) . '/of.php';
of::config('_of.session.autoStart') || session_start();
umask(0);

/*系统配置区,主要定义安全范围内最宽松的变量*/
$_rootDir = ROOT_DIR;
$_rootUrl = ROOT_URL;                               //网站虚拟目录(/../..绝对目录形式)
$browseDir = OF_DATA;                               //浏览个根目录(/../..绝对目录形式),一般为$rootDirUrl排除虚拟目录的后半部分
$characterSet         = 'GBK';                      //系统文档编码,如:中文系统为GBK,使用在非http上传方式的编解码,如果不是用如ftp上传,建议置空(一旦开启,数据无法在不同语言系统之间迁移)
//$rootDirUrl         = $_rootUrl.$browseDir;        //文件浏览器当前页面相对于被管理目录的url    (/../..绝对目录形式)
$fileExtArr           = array('rtf','apk','txt','csv','doc','docx','ppt','pptx','xls','xlsx','pdf','chm','wmv','swf','flv','mp3','mp4','gif','jpg','bmp','png','rar','zip','gz','sql');        //允许上传的全部格式(小写)
$safeBrowse           = false;                     //安全浏览(不允许域名外地址访问,不允许URL直接访问,不允许访问$browseDir目录)
$rowseFailureHandling = 0;                //当请求oFileManager浏览不存在,或非安全线内的文件夹时的处理,0=终止操作,1=重定向到跟文件夹($browseDir文件夹)
$filterPathArr        = array(                 //路径过滤规则
    'include' => array(      //包含路径
        //'/img/pictures'
    )
    ,'excluded' => array(    //排除路径
        $browseDir . '/extension'
        ,$browseDir . '/error'
        ,$browseDir . '/language'
    )
);

/*用户session初始区,主要任务是系统没有给该用户分配浏览权限时的默认权限*/
//oFileManager管理系统所有涉及session的变量都放$_SESSION['_oFileManager']中
if(!isset($_SESSION['_oFileManager']))
{
    $_SESSION['_oFileManager']=array();
}
//给用户分配默认权限,分1,2,3
if(!isset($_SESSION['_oFileManager']['permissions']))
{
    $_SESSION['_oFileManager']['permissions'] = 2;
}
//给用户分配默认上传格式
if( isset($_SESSION['_oFileManager']['fileExtArr']) && is_array($_SESSION['_oFileManager']['fileExtArr']) )
{
    $_SESSION['_oFileManager']['fileExtArr'] = array_unique($_SESSION['_oFileManager']['fileExtArr']);
    array_splice($_SESSION['_oFileManager']['fileExtArr'], 0, 0);
    $fileExtArr = array_merge($_SESSION['_oFileManager']['fileExtArr'], $fileExtArr);
} else {
    $_SESSION['_oFileManager']['fileExtArr'] = $fileExtArr;
}
//$_SESSION['_oFileManager']['thisfileExtArr']为本次临时上传格式
//$_SESSION['_oFileManager']['thisRootBrowseDir']为本次浏览路劲
//$_SESSION['_oFileManager']['thisPermissions']为本次浏览的权限
//以上注释变量将在程序运行中产生


/*辅助区*/
if(!function_exists('urlFinishing'))
{
    //整理url,去除[../][./]等多余的形式
    function urlFinishing($url)
    {
        $suppress = array();    //压制数据
        if($url != '')
        {
            $each = explode('/', $url = strtr($url, '\\', '/'));    //按'/'和'\'分组

            //压制过滤
            foreach($each as &$v)
            {
                if($v === '..')    //如果为后退(../)结构
                {
                    if( ($temp = count($suppress)) > 0)    //如果压制包里有数据
                    {
                        if( $suppress[$temp - 1] === '..' )    //并且最后一个为后退结构
                        {
                            $suppress[] = '..';    //压入当前后退结构
                        } else {    //否则
                            array_pop($suppress);    //弹出压制中最后一结构
                        }
                    } else {    //压制包中无数据
                        $suppress[] = '..';    //压入当前后退结构
                    }
                } else if($v !== '' && $v !== '.') {    //如果不为当前结构(./或'')
                    $suppress[] = $v;    //压入常规结构
                }
            }

            //规则整合
            $temp = array(
                $url[0] === '/' ? '/' : '',                  //头标识
                $url[strlen($url) - 1] === '/' ? '/' : ''    //尾标识
            );
            if( ($suppress = join('/', $suppress)) === '' )    //如果整理后为''
            {
                return $temp[0] === $temp[1] ? ($temp[0] === '/' ? '/' : '.') : '';    //头尾标识相同 ? (都为'/' ? 返回'/' : 返回'.') : 返回''
            } else {
                return $temp[0] . $suppress . $temp[1];    //返回 头标识+整理结果+尾标识
            }
        } else {
            return '.';
        }
    }

    /*
    * 描述 : 判断路径是否符合过滤规则
    * 参数 :
    *      path          : 指定判断的路径
    *     &filterPathArr : 过滤数组
    * 返回 :
    *      true=合法;false=不合法
    */
    function filterPathChecksum($path, &$filterPathArr)
    {
        global $browseDir;    //浏览跟路径
        $browseDirLen = strlen($browseDir) + 1;    //跟路径长度
        $path .= '/';
        $returnChecksum = true;
        if(isset($filterPathArr['excluded']) && count($filterPathArr['excluded']) > 0)
        {
            foreach($filterPathArr['excluded'] as &$filterPath)
            {
                if(strncmp($path, $browseDir . $filterPath . '/', strlen($filterPath) + $browseDirLen) === 0)
                {
                    $returnChecksum = false;
                    break;
                }
            }
        }
        if($returnChecksum && isset($filterPathArr['include']) && count($filterPathArr['include']) > 0)
        {
            $returnChecksum = false;
            foreach($filterPathArr['include'] as &$filterPath)
            {
                if(strncmp($path, $browseDir . $filterPath . '/', strlen($filterPath) + $browseDirLen) === 0)
                {
                    $returnChecksum = true;
                    break;
                }
            }
        }
        return $returnChecksum;
    }

    /*
    * 描述 : 字符串编解码
    * 参数 :
    *      str     : 指定转码字符串
    *     &type    : true = 转成UTF8, 转回 $characterSet 编码
    *      monitor : 运行中的一些数据
    * 返回 :
    *      true=合法;false=不合法
    */
    function iconvCodec($str, $type, &$monitor = null) {
        global $characterSet;
        $monitor[0] = $str;
        if(preg_match('/^[\w%-\.\/]+$/', $str))    //符合ASCII编码格式
        {
            return $str;
        } else if($characterSet) {    //启动正反转码
            if($type !== false)    //$type === true时转码 utf8
            {
                $monitor[0] = $str = iconv($characterSet, 'UTF-8//IGNORE', $str);
            }
            if($type !== true)    //$type === false时转码 $characterSet
            {
                $monitor[1] = $str = iconv('UTF-8', $characterSet . '//IGNORE', $str);
            }
            return $str;
        } else {    //编码无效
            return false;
        }
    }
}
