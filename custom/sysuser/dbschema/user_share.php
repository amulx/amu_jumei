<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

return array(
    'columns' => array(
        'share_id' => array(
            'type' => 'number',
            'required' => true,
            'autoincrement' => true,
            'label' => 'ID',
            'width' => 110,
            'editable' => false,
            'default_in_list' => true,
            'id_title' => true,
        ),
        'user_id' => array(
            'type'=>'table:user',
            'in_list' => true,
            'required' => true,
            'label' => app::get('sysuser')->_('会员用户id'),
            'default_in_list' => true,
        ),
        'openid' => array(
            //'type' => 'varchar(200)',
            'type' => 'string',
            'required' => true,
            'length' => 35,
//            'default' => '',
            'label' => app::get('sysuser')->_('openid'),
            'width' => 310,
        ),
    ),
    'primary' => 'share_id',
    'index' => array(
        'ind_user_id' => ['columns' => ['user_id']],
        'ind_openid' => ['columns' => ['openid'],'prefix' => 'unique']
    ),
    'comment' => app::get('sysuser')->_('分享链接统计表'),
);
