<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

return array(
    'columns' => array(
        'distri_id' => array(
            'type' => 'number',
            'required' => true,
            'autoincrement' => true,
            'label' => 'ID',
            'width' => 110,
            'editable' => false,
            'default_in_list' => true,
            'id_title' => true,
        ),
        'parent_user_id' => array(
            'type'=>'table:user',
            'in_list' => true,
            'label' => app::get('sysuser')->_('父类会员用户id'),
            'default_in_list' => true,
        ),
        'user_id' => array(
            'type'=>'table:user',
            'in_list' => true,
            'label' => app::get('sysuser')->_('会员用户id'),
            'default_in_list' => true,
        ),
        'user_level' => array(
            'type' => 'number',
            'required' => true,
            'comment' => app::get('sysstat')->_('楼层'),
            'order' => 2,
        ),
        'left_num' => array(
            'type' => 'number',
            'required' => true,
            'label' => app::get('sysuser')->_('左编号'),
            'comment' => app::get('sysuser')->_('左编号'),
            'in_list' => true,
        ),
        'right_num' => array(
            'type' => 'number',
            'required' => true,
            'label' => app::get('sysuser')->_('右编号'),
            'comment' => app::get('sysuser')->_('右编号'),
            'in_list' => true,
        ),
        'openid' => array(
            //'type' => 'varchar(200)',
            'type' => 'string',
            'length' => 30,
            'default' => '',
            'label' => app::get('sysuser')->_('openid'),
            'width' => 310,
        ),
    ),
    'primary' => 'distri_id',
    'index' => array(
        'ind_user_id' => ['columns' => ['user_id']],
        'ind_left_num' => ['columns' => ['left_num']],
        'ind_right_num' => ['columns' => ['right_num']],
        'ind_openid' => ['columns' => ['openid']],
        //  'ind_unique' => [
        //     'columns' => ['openid'],
        //     'prefix' => 'unique',
        // ],
    ),
    'comment' => app::get('sysuser')->_('分销关系表'),
);
