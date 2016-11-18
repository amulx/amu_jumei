<?php
class syspromotion_ctl_admin_registereddiscount extends desktop_controller{

    public function index()
    {
        return $this->finder('syspromotion_mdl_registereddiscount',array(
            'title' => app::get('syspromotion')->_('新用户注册折扣表'),
            'use_buildin_delete' => false,
            'actions' => array(
                array(
                    'label' => app::get('syspromotion')->_('添加优惠券'),
                    'target' => '_blank',
                    'href' => url::route('shopadmin', ['app'=>'syspromotion','act'=>'add','ctl'=>'admin_registereddiscount']),
                ),
            ),
        ));
    }

    public function add()
    {
        $this->contentHeaderTitle = '添加优惠券';
        $pagedata['status'] = array(
            'register' => '注册领取',
            'other' => '其他',
        );
        return $this->singlepage('syspromotion/registereddiscount/add.html',$pagedata);
    }

    public function save(){
  // `coupon_name`  '优惠券名称',
  // `coupon_desc`  '优惠券描述',
  // `used_platform`  '使用平台',
  // `condition_value` 
  // `use_bound`  '使用范围',
  // `canuse_start_time`  '优惠券生效时间',
  // `created_time`  '建券时间',
  // `coupon_status`  '促销状态',
        $postData = input::get('ruledata');
        $postData['used_platform'] = 2;
        $postData['use_bound'] = 0;
        $postData['canuse_start_time'] = strtotime($postData['canuse_start_time']);
        $postData['created_time'] = time();
        $postData['coupon_status'] = 'agree';
        /**
         * 这里需要增加判断 （同一平台只能有一种优惠券）做限制
         */
        $this->begin("?app=syspromotion&ctl=admin_registereddiscount&act=index");
        try
        {
            $discountModel = app::get('syspromotion')->model('registereddiscount');
            $result = $discountModel->save($postData); 
            $this->adminlog("添加活动{$postData['coupon_name']}", 1);
        }
        catch(Exception $e)
        {
            $this->adminlog("添加活动{$postData['coupon_name']}", 0);
            $msg = $e->getMessage();
            $this->end(false,$msg);
        }
        $this->end(true);
    }

    public function edit(){
        $regdis_id = input::get('regdis_id');
        $pagedata['ruleInfo'] = app::get('syspromotion')->model('registereddiscount')->getRow('*', array('regdis_id' => $regdis_id));
        $pagedata['status'] = array(
            'register' => '注册领取',
            'other' => '其他',
        );
        return $this->singlepage('syspromotion/registereddiscount/add.html',$pagedata);
    }

}