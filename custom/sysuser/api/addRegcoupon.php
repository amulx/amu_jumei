<?php

class sysuser_api_addRegcoupon {
	/**
	 * 接口作用说明
	 *
	 * @var        string
	 */
	public $apiDescription = '添加用户领取优惠卷';

	public function getParams(){
		$return['params'] = array(
			'user_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'用户ID必填','default'=>'','example'=>''],
			'regdis_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'优惠券ID必填','default'=>'','example'=>''],
		);
		return $return;
	}

	public function addRegcoupon($params){
		return kernel::single('sysuser_data_user_regcoupon')->saveRegcoupon($params);
	}
}