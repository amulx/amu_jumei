<?php

class sysuser_api_addDistri {
	/**
	 * 接口作用说明
	 *
	 * @var        string
	 */
	public $apiDescription = '添加分销关系';

	public function getParams(){
		$return['params'] = array(
			'user_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'用户ID必填','default'=>'','example'=>''],
		);
		return $return;
	}

	public function addDistri($apiData){
		return kernel::single('sysuser_data_user_distri')->saveDistri($apiData['parent_user_id'],$apiData['user_id'],$apiData['openid']);
	}
}