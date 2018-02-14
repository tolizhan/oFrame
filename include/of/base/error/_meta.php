<?php
return class_exists('of_base_error_writeLog', false) ? array(
    'name' => 'Error log',
    'gets' => array(
        'c' => 'of_base_error_tool'
    )
) : array();