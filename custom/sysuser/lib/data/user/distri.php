<?php

class sysuser_data_user_distri{
	/**
	 * 添加分销关系
	 *
	 * @param      int  $parent_user_id  推荐人的user_id
	 * @param      int  $user_id         自身注册user_id
	 * @param      int  $openid          微信公众号openid
	 *
	 * @return     int  返回新增distri_id
	 */
	public function saveDistri($parent_user_id,$user_id,$openid){
		$userMdlDistri = app::get('sysuser')->model('user_distri');
		return $userMdlDistri->saveDistri($parent_user_id,$user_id,$openid);
	}

}