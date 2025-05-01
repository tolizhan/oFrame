<?php
//兼容旧版配置
$appDir = ROOT_DIR . '/include/application';

switch ($name) {
    //phpExcel
    case 'excel':
        class_exists('PHPExcel', false) || require $appDir .'/PHPOffice/PHPExcel.php';
        break;
    //phpWord
    case 'word' :
        class_exists('PHPWord', false) || require $appDir .'/PHPOffice/PHPWord.php';
        break;
    //phpSoap
    case 'soap' :
        class_exists('nusoap_base', false) || require $appDir .'/nusoap/nusoap.php';
        break;
    //phpMailer
    case 'mail' :
        function_exists('PHPMailerAutoload') || require $appDir .'/PHPMailer/PHPMailerAutoload.php';
        break;
    //tcPdf
    case 'pdf' :
        class_exists('TCPDF', false) || require $appDir .'/tcpdf/tcpdf.php';
        break;
    //phprpc
    case 'phprpc' :
        class_exists('PHPRPC_Client', false) ||
        class_exists('PHPRPC_Server', false) ||
        class_exists('PHPRPC_Date', false) ||
        of::event('of::loadClass', array(
            'filter' => 'PHPRPC_', 'router' => substr($appDir, strlen(ROOT_DIR)) . '/phprpc/PHPRPC_'
        ), true);
        break;
}