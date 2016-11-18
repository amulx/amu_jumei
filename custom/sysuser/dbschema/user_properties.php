<?php

return array (
    'columns' =>
    array (
        'user_id' => array(
            'type'=>'table:user',
            'in_list' => true,
            'label' => app::get('sysuser')->_('会员用户名'),
            'default_in_list' => true,
        ),
        'pps_id' => array(
            'type' => 'string',
            'required' => true,
            'label' => app::get('sysuser')->_('属性id'),
            'in_list' => true,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => app::get('sysuser')->_('创建时间'),
            'width' => 110,
            'editable' => false,
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
        ),
    ),

    'primary' => 'user_id',
    'index' => array(
        'ind_user_id' => ['columns' => ['user_id']],
    ),

    'comment' => app::get('sysuser')->_('用户属性表'),
);
