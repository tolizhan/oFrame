<?php
//最大上传限制
$size = ini_get('upload_max_filesize');
//字节单位转换率
$bits = array('K' => 1024, 'M' => 1048576, 'G' => 1073741824);
//转换成字节
preg_match('@K|M|G@', strtoupper($size), $match) && $size = $bits[$match[0]] * (float)$size;
//输出json
echo '{"maxSize":', (float)$size, '}';