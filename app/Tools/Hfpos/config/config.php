<?php

$config = array(
    //商户号
    'AGENT_ID'      => '310000015002334998',

    //商户号
    'MEMBER_ID'      => '310000016002336988',

    // 小程序
    'MINI_APPID'     => 'wx70ea133905c65d2e',
    'MINI_SECRET'    => '439f303f406cdf2e0eab2a913c3423f4',

    // 公众号
    'APPID'          => 'wx57d0fe3fedba6fd9',
    'SECRET'         => 'ea6b6b7db845133d119787d0b516a24f',
    'TOKEN'          => 'B0UuzrUGDWLuqmzaQgfgnkZwZNaqO5gu',
    'AES_TOKEN'      => 'hKP4gWfXZMt6rzXlbI021LUN5INys6L5U3F4JgHSHJO',

    //默认分页
    'currentPage'    => '1',
    'pageSize'       => '50',

    // 支付方式
    'payChannelType' => array(
        'A1' => '支付宝',
        'W1' => '微信',
        'U1' => '银联'
    ),
    'ordType' =>array(
        1=>'支付订单',
        2=>'退款订单'
    )

);
