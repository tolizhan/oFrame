<?php
return class_exists('of_base_htmlTpl_engine', false) ? array(
    'name' => 'Html template',
    'gets' => array(
        'c' => 'of_base_htmlTpl_tool'
    )
) : array();