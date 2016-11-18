<?php

/**
 * 分销表
 */
class sysuser_mdl_user_distri extends dbeav_model{

	/**
	 * 分销上下级关系保存
	 *
	 * @param      <type>  $parent_user_id  The user identifier
	 * @param      <type>  $openid   The openid
	 */
	public function saveDistri($parent_user_id,$user_id,$openid){
		if (!$parent_user_id) return false;

		$row = $this->getRow('user_level,left_num,right_num',['user_id'=>$parent_user_id]);
// var_dump($row['left_num']);
        		$db = app::get('sysuser')->database();

        		$db->beginTransaction();
        		try{
	        		//修改左右节点值
			$db->executeUpdate('UPDATE sysuser_user_distri SET right_num = right_num + 2 WHERE right_num >= ?', [$row['right_num']]);
			$db->executeUpdate('UPDATE sysuser_user_distri SET left_num = left_num + 2 WHERE left_num >= ?', [$row['right_num']]);
// var_dump($db->getSql());
			$saveData['parent_user_id'] = $parent_user_id;
			$saveData['user_id'] = $user_id;
			$saveData['user_level'] = $row['user_level'] + 1;
			$saveData['left_num'] = $row['right_num'];
			$saveData['right_num'] = $row['right_num'] + 1;
			$saveData['openid'] = $openid;
			$result = $this->save($saveData);
			if (!$result) {
				throw new Exception(app::get('sysuser')->_("用户信息创建失败"));
			}
			$db->commit();
		}catch(Exception $e){
			$db->rollback();
			throw $e;
		}
		return $result;
	}
}