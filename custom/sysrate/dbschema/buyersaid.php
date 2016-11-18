<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

return  array(
    'columns' => array(
        'said_id' => array(
            'type' => 'number',
            'unsigned' => true,
            'required' => true,
            'autoincrement' => true,
            'comment' => app::get('sysrate')->_('评价ID'),
        ),
        'user_name' => array(
            'type' => 'string',
            'required' => true,
            'in_list' => true,
            'default_in_list' => true,
            'label' => app::get('sysrate')->_('会员名称'),
            'comment' => app::get('sysrate')->_('会员名称'),
        ),
        /*-----------商品信息冗余------------------*/
        'item_id' => array(
            'type' => 'number',
            'required' => true,
            'default_in_list' => true,
            'in_list' => true,
            'label' => app::get('sysrate')->_('评论的商品ID'),
            'comment' => app::get('sysrate')->_('评论的商品ID'),
        ),
        'item_sell' => array(
            'type' => 'number',
            'default' => '0',
            'default_in_list' => true,
            'in_list' => true,
            'label' => app::get('sysrate')->_('商品出售数'),
            'comment' => app::get('sysrate')->_('商品出售数'),
        ),
        'item_pic' => array(
            'type' => 'string',
            'length' => '1024',
            'comment' => app::get('sysrate')->_('商品图片绝对路径'),
        ),
        /*-----------商品信息冗余------------------*/

        /*-----------评论信息-----------------*/
        'result' => array(
            'type' => ['good'=>'好评','neutral'=>'中评','bad'=>'差评'],
            'default' => 'good',
            'label' => app::get('sysrate')->_('评价结果'),
            'comment' => app::get('sysrate')->_('评价结果'),
            'in_list' => true,
            'default_in_list' => false,
        ),
        'content' => array(
            'type' => 'text',
            'default' => '',
            'label' => app::get('sysrate')->_('评价内容'),
            'comment' => app::get('sysrate')->_('评价内容'),
            'in_list' => true,
            'default_in_list' => false,
        ),
        'rate_pic' => array(
            //'type' => 'varchar(255)',
            'type' => 'text',
            'default' => '',
            'label' => app::get('sysrate')->_('晒单图片'),
            'comment' => app::get('sysrate')->_('晒单图片'),
        ),
        'anony' => array( //1 匿名 0 实名
            'type' => 'bool',
            'default' => '0',
            'required' => true,
            'in_list' => true,
            'default_in_list' => true,
            'comment' => app::get('sysrate')->_('是否匿名'),
            'label' => app::get('sysrate')->_('是否匿名'),
        ),
        /*-----------评论信息-----------------*/
        'created_time' => array(
            'type' => 'time',
            'label' => app::get('sysrate')->_('创建时间'),
            'comment' => app::get('sysrate')->_('创建时间'),
            'width' => '100',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'modified_time' => array(
            'type' => 'last_modify',
            'label' => app::get('sysrate')->_('最后修改时间'),
            'comment' => app::get('sysrate')->_('最后修改时间'),
            'in_list' => true,
            'default_in_list' => true,
            'width' => '100',
        ),
    ),
    'primary' => 'said_id',
    'comment' => app::get('sysrate')->_('买家说表'),
);

