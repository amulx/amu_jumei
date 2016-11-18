<?php

/**
 * 分享表
 */
class sysuser_mdl_user_share extends dbeav_model{

	/**
	 * 保存用户分享链接
	 *
	 * @param      数组  $data   
	 *
	 * @return     true or false
	 */
	public function saveShare($data){ //user_id 、openid
		return $this->save($data);
	}
}