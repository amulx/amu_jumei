<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

return array(
    'columns' => array(
        'history_id' => array(
            'type' => 'number',
            'required' => true,
            //'pkey' => true,
            'autoincrement' => true,
            'label' => 'ID',
            'width' => 110,
            'editable' => false,
            'default_in_list' => true,
            'id_title' => true,
        ),
        'item_id' => array(
            'type' => 'table:item@sysitem',
            'required' => true,
            'label' => app::get('sysuser')->_('商品名称'),
            'in_list' => true,
            'comment' => app::get('sysuser')->_('商品ID'),
        ),
        'shop_id' => array(
            'type' => 'bigint',
            'required' => true,
            'comment' => app::get('sysstat')->_('店铺id'),
            'order' => 2,
        ),
        'user_id' => array(
            'type'=>'table:user',
            'in_list' => true,
            'label' => app::get('sysuser')->_('会员用户名'),
            'default_in_list' => true,
        ),
        'sku_id' => array(
            'type' => 'table:sku@sysitem',
            'default' => null,
            'comment' => app::get('sysuser')->_('货品ID'),
        ),
        'cat_id' => array(
            'type' => 'table:cat@syscategory',
            'required' => true,
            'label' => app::get('sysuser')->_('商品类目ID'),
            'comment' => app::get('sysuser')->_('商品类目ID'),
            'in_list' => true,
        ),
        'goods_name' => array(
            //'type' => 'varchar(200)',
            'type' => 'string',
            'length' => 200,
            'default' => '',
            'label' => app::get('sysuser')->_('商品名称'),
            'width' => 310,
        ),
        'goods_price' => array(
            'type' => 'money',
            'default' => '0',
            'label' => app::get('sysuser')->_('销售价'),
            'width' => 75,
            'editable' => false,
            'filtertype' => 'number',
            'orderby'=>true,
        ),
        'image_default_id' => array(
            //'type' => 'varchar(32)',
            'type' => 'string',
            'label' => app::get('sysuser')->_('默认图片'),
            'width' => 75,
            'hidden' => true,
            'editable' => false,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => app::get('sysuser')->_('浏览时间'),
            'width' => 110,
            'editable' => false,
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
        ),
    ),
    'primary' => 'history_id',
    'index' => array(
        'ind_item_id' => ['columns' => ['item_id']],
        'ind_user_id' => ['columns' => ['user_id']],
        'ind_cat_id' => ['columns' => ['cat_id']],
        'ind_create_time' => ['columns' => ['create_time']],
    ),
    'comment' => app::get('sysuser')->_('历史足迹表'),
);
