<?php

return array (
    'columns' =>
    array (
        'properties_id' =>
        array (
            'type' => 'number',
            //'pkey' => true,
            'autoincrement' => true,
            'required' => true,
            'label' => app::get('sysuser')->_('属性id'),
        ),
        'properties_name' =>
        array (
            'type' => 'string',
            'width' => 100,
            'editable' => false,
            'in_list'=>true,
            'default_in_list' => true,
            'unsigned' => false,
            'label' => app::get('sysuser')->_('子属性名'),
            'comment' => app::get('sysuser')->_('子属性名'),
        ),
        'source' =>
        array (
            'type' => array(
                'style' =>app::get('sysuser')->_('风格'),
                'grade' => app::get('sysuser')->_('档次'),
                'places' => app::get('sysuser')->_('产地')
            ),
            'required' => false,
            'label' => app::get('sysuser')->_('属性'),
            'width' => 110,
            'editable' => false,
            'default' =>'pc',
            'in_list' => true,
            'default_in_list' => false,
            'filterdefault' => false,
            'filtertype' => 'normal',
        ),
        
    ),

    'primary' => 'properties_id',
    // 'index' => array(
    //     'ind_regtime' => ['columns' => ['regtime']],
    //     'ind_disabled' => ['columns' => ['disabled']],
    // ),

    'comment' => app::get('sysuser')->_('属性表'),
);
