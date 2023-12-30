<?php
return array(
    //计划任务
    array(
        'name' => "Scheduled task\nState: <font color='red'>" .
                (of_base_com_timer::state() ? 'running' : 'starting') .
            '</font>',
        'gets' => array(
            'c' => 'of_base_com_timer'
        )
    ),
    //消息队列
    array(
        'name' => "Message queue\nState: <font color='red'>" .
                (of_base_com_mq::state() ? 'running' : 'starting') .
            '</font>',
        'gets' => array(
            'c' => 'of_base_com_mq'
        )
    )
);