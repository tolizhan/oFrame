<?php
switch ($name) {
    //phpExcel
    case 'excel':
        class_exists('PHPExcel', false) || require OF_DIR . '/addin/PHPOffice/PHPExcel.php';
        break;
    //phpWord
    case 'word' :
        class_exists('PHPWord', false) || require OF_DIR . '/addin/PHPOffice/PHPWord.php';
        break;
    //phpSoap
    case 'soap' :
        class_exists('nusoap_base', false) || require OF_DIR . '/addin/nusoap/nusoap.php';
        break;
    //phpMailer
    case 'mail' :
        function_exists('PHPMailerAutoload') || require OF_DIR . '/addin/PHPMailer/PHPMailerAutoload.php';
        break;
    //tcPdf
    case 'pdf' :
        class_exists('TCPDF', false) || require OF_DIR . '/addin/tcpdf/tcpdf.php';
        break;
    //phprpc
    case 'phprpc' :
        class_exists('PHPRPC_Client', false) || 
        class_exists('PHPRPC_Server', false) || 
        class_exists('PHPRPC_Date', false) || 
        of::event('of::loadClass', array(
            'classPre' => 'PHPRPC_', 'mapping' => substr(OF_DIR, strlen(ROOT_DIR)) . '/addin/phprpc/PHPRPC_'
        ));
        break;
}