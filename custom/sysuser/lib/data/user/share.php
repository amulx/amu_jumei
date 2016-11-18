<?php

class sysuser_data_user_share{
	
	/**
	 * 保存用户分享链接
	 *
	 * @param      数组  $data   
	 *
	 * @return     true or false
	 */
	public function saveShare($user_id,$openid){
		$saveDate['user_id'] = $user_id;
		$saveDate['openid'] = $openid;
		$userMdlShare = app::get('sysuser')->model('user_share');
		return $userMdlShare->saveShare($saveDate);
	}

}