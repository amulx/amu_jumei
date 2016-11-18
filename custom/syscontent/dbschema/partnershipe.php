<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 

return array (
    'columns' =>
    array (
        'partnershipe_id' =>array (
            'type' => 'number',
            'required' => true,
            'comment'=> app::get('syscontent')->_('合作ID'),
            'autoincrement' => true,
            'width' => 50,
            'order'=>1,
        ),
        'name' => array (
            //'type' => 'varchar(50)',
            'type' => 'string',
            'length' => 50,
            'label' => app::get('syscontent')->_('昵称'),
            'comment' => app::get('syscontent')->_('昵称'),
            'width' => 75,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'is_title'=>true,
            'default_in_list' => true,
        ),
        'mobile'=>array(
            //'type'=>'varchar(32)',
            'type' => 'string',
            'length' => 32,
            'in_list' => true,
            'is_title'=>true,
            'default_in_list' => true,
            'label' => app::get('syscontent')->_('手机号'),
            'comment' => app::get('syscontent')->_('手机号'),
        ),
        'area' => array (
            'label' => app::get('syscontent')->_('地区'),
            'comment' => app::get('syscontent')->_('地区'),
            'width' => 110,
            //'type' => 'varchar(55)',
            'in_list' => true,
            'is_title'=>true,
            'default_in_list' => true,
            'type' => 'string',
            'length' => 55,
            'editable' => false,
            'filtertype' => 'yes',
            'filterdefault' => 'true',
        ),
        'modified' =>array (
            'type' => 'time',
            'comment' => app::get('syscontent')->_('更新时间（精确到秒）'),
            'label' => app::get('syscontent')->_('更新时间'),
            'editable' => false,
            'width' => 130,
            'in_list' => true,
            'is_title'=>true,
            'default_in_list' => true,
            'order'=>7,
        ),
    ),
    'primary' => 'partnershipe_id',
    'index' => array(
        'ind_name' => ['columns' => ['name']],
    ),
    'comment' => app::get('syscontent')->_('留言表'),
);
