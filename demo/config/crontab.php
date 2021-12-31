<?php
return array(
    array(
        'time' => '* * * * *',                              //每分钟执行一次
        'call' => array('ctrl_index', 'asyn'),
        'cNum' => 1,
        'try'  => array(60, 120, 300)
    )
);