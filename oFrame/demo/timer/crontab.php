<?php
return array(
    array(
        'time' => '* * * * *',                              //每分钟执行一次
        'call' => array('demo_index', 'asyn'),
        'try'  => array(60, 120, 300)
    )
);