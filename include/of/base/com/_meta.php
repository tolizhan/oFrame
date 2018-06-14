<?php
//读取计划任务状态
$state = of_base_com_timer::info(2);
//开启计划任务
of_base_com_timer::timer();

return array(
    //计划任务
    array(
        'name' => "Scheduled task\nState: <font color='red'>" .
                ($state ? 'runing' : 'starting') .
            '</font>',
        'gets' => array(
            'c' => 'of_base_com_timer'
        )
    ),
    //消息队列
    array(
        'name' => "Message queue\nState: <font color='red'>" .
                (of_base_com_mq::state() ? 'runing' : 'starting') .
            '</font>',
        'gets' => array(
            'c' => 'of_base_com_mq'
        )
    )
);