<?php
return array(
    //生产消息时会同时发给个队列
    'queue1' => array(
        //队列模式, null=生产及消费, false=仅生产, true=仅消费
        'mode' => null,
        //消费消息时回调结构
        'keys' => array(
            //不存在的键将被抛弃
            'key' => array(
                'cNum' => 3,
                'call' => 'demo_index::mqTest'
            )
        )
    ),
    'queue2' => array(
        //队列模式, null=生产及消费, false=仅生产, true=仅消费
        'mode' => null,
        //消费消息时回调结构
        'keys' => array(
            //不存在的键将被抛弃
            'key' => array(
                'cNum' => 4,
                'call' => 'demo_index::mqTest'
            )
        )
    )
);