<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
// 优惠券规则表
return  array(
    'columns' => array(
        'regdis_id' => array(
            'type' => 'number',
            'required' => true,
            //'pkey' => true,
            'autoincrement' => true,
            'width' => 110,
            'label' => app::get('syspromotion')->_('regdis_id'),
            'comment' => app::get('syspromotion')->_('优惠券方案id'),
        ),
        'coupon_name' => array(
            //'type' => 'varchar(255)',
            'type' => 'string',
            'required' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 110,
            'label' => app::get('syspromotion')->_('优惠券名称'),
            'comment' => app::get('syspromotion')->_('优惠券名称'),
        ),
        'coupon_desc' => array(
            //'type' => 'varchar(255)',
            'type' => 'string',
            'required' => true,
            'in_list' => true,
            'width' => 110,
            'label' => app::get('syspromotion')->_('优惠券描述'),
            'comment' => app::get('syspromotion')->_('优惠券描述'),
        ),
        'used_platform' => array(
            'type' => array(
                '0' => app::get('syspromotion')->_('商家全场可用'),
                '1' => app::get('syspromotion')->_('只能用于pc'),
                '2' => app::get('syspromotion')->_('只能用于wap'),
            ),
            'default' => 0,
            'required' => true,
            'in_list' => true,
            'default_in_list' => true,
            'label' => app::get('syspromotion')->_('使用平台'),
            'comment' => app::get('syspromotion')->_('使用平台'),
        ),
        'condition_value' => array(
            'type' => 'string',
            'default' => '',
            'required' => false,
            'in_list' => true,
            'default_in_list' => true,
            'label' => app::get('syspromotion')->_('折扣'),
        ),
        'use_bound' => array(
            'type' => array(
                '0' => app::get('syspromotion')->_('商家全场可用'),
                '1' => app::get('syspromotion')->_('指定商品可用'),
            ),
            'default' => '0',
            'in_list' => true,
            'default_in_list' => true,
            'width' => '50',
            'order' => 14,
            'label' => app::get('syspromotion')->_('使用范围'),
            'comment' => app::get('syspromotion')->_('使用范围'),
        ),
        'canuse_start_time' => array(
            'type' => 'time',
            'in_list' => true,
            'default_in_list' => true,
            'width' => '100',
            'label' => app::get('syspromotion')->_('优惠券生效时间'),
            'comment' => app::get('syspromotion')->_('优惠券生效时间'),
        ),
        'created_time' => array(
            'type' => 'time',
            'in_list' => true,
            'default_in_list' => true,
            'width' => '100',
            'label' => app::get('syspromotion')->_('建券时间'),
            'comment' => app::get('syspromotion')->_('建券时间'),
        ),
        'coupon_status' => array(
            'type' => array(
                'pending' => app::get('syspromotion')->_('待审核'),
                'agree' => app::get('syspromotion')->_('审核通过'),
                'refuse' => app::get('syspromotion')->_('审核拒绝'),
                'cancel' => app::get('syspromotion')->_('已取消'),
            ),
            'default' => 'agree',
            'required' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 110,
            'label' => app::get('syspromotion')->_('促销状态'),
            'comment' => app::get('syspromotion')->_('促销状态'),
        ),
        'regdis_status' => array(
            'type' => array(
                'register' => app::get('syspromotion')->_('注册领取'),
                'other' => app::get('syspromotion')->_('其他'),
            ),
            'in_list' => true,
            'default_in_list' => true,
            'label' => app::get('syspromotion')->_('优惠卷类型'),
            'comment' => app::get('syspromotion')->_('优惠卷类型'),
        ),
    ),
    'primary' => 'regdis_id',
    'index' => array(
        'ind_created_time' => ['columns' => ['created_time']],
        'ind_coupon_status' => ['columns' => ['coupon_status']],
    ),
    'comment' => app::get('syspromotion')->_('新用户注册折扣表'),
);
