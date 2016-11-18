<?php

class sysuser_api_addShare {
	/**
	 * 接口作用说明
	 *
	 * @var        string
	 */
	public $apiDescription = '添加分享信息';

	public function getParams(){
		$return['params'] = array(
			'user_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'用户ID必填','default'=>'','example'=>''],
		);
		return $return;
	}

	public function addShare($apiData){
		return kernel::single('sysuser_data_user_share')->saveShare($apiData['user_id'],$apiData['openid']);
	}
}