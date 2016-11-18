<?php
return  array(
    'columns'=> array(
        'id' => array(
            'type' => 'number',
            'autoincrement' => true,
            'required' => true,
            'comment' => app::get('sysitem')->_('id'),
        ),
        'user_id' => array(
            'type'=>'number',
            'in_list' => true,
            'label' => app::get('sysuser')->_('会员用户id'),
            'default_in_list' => true,
        ),
        'item_id' => array(
            'type' => 'number',
            'required' => true,
            'comment' => app::get('sysitem')->_('商品id'),
        ),
        'mobile'=>array(
            'type' => 'string',
            'length' => 32,
            'comment' => app::get('sysitem')->_('手机号'),
        ),
        'openid' => array(
            //'type' => 'varchar(200)',
            'type' => 'string',
            'length' => 30,
            'default' => '',
            'label' => app::get('sysitem')->_('openid'),
            'width' => 310,
        ),
        'view_time'=>
        array(
            'type'=>'time',
            'comment' => app::get('sysitem')->_('当前时间戳'),
        ),
        'ymd_time'=>
        array(
            'type'=>'time',
            'comment' => app::get('sysitem')->_('当前年月日'),
        ),
        'ip' => array (
            'type' => 'ipaddr',
            'comment' => app::get('sysitem')->_('访问IP'),
        ),
    ),
    'primary' => 'id',
    'comment' => app::get('sysitem')->_('商品浏览统计表'),
);

