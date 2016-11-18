<?php
	
	/*
		2016-09-07  min.zhou@yorisun.com
		提供给oms查询会员的控制器
	*/
	class foroms_ctl_user extends foroms_controller
	{
		public function query()
		{
			$param = input::get();
			//根据user_id获取会员信息
			$user_id = $param['user_id'];
			try{
				//会员信息表    sysuser_account    sysuser_user   sysuser_user_distri
	            $qb = app::get('jumei')->database()->createQueryBuilder();
	            $qb->select('A.user_id,A.mobile,A.login_account,D.openid,U.sex,U.reg_ip,D.parent_user_id,P.pps_id')
	            ->from('sysuser_account','A')
	            ->leftJoin('A','sysuser_user', 'U','A.user_id=U.user_id')
	            ->leftJoin('A','sysuser_user_distri', 'D','A.user_id=D.user_id')
	            ->leftJoin('A','sysuser_user_properties', 'P','A.user_id=P.user_id')
	            ->andWhere("A.user_id = ".$user_id);
	            $queryresult = $qb->execute()->fetchAll();
				// echo "<pre>"; var_dump($queryresult[0]['pps_id']);exit;
				//会员属性（风格、档次、产地）表  sysuser_properties     sysuser_user_properties
				if (!empty($queryresult[0]['pps_id'])) {
					$p_index_array = array_filter(explode(',',$queryresult[0]['pps_id']));
					// echo "<pre>"; var_dump($p_index_array);exit;
					$result = app::get('sysuser')->model('properties')->getList('properties_name,source', ['properties_id'=>$p_index_array]);
					foreach ($result as $key => $value) {
			            if (array_key_exists($value['source'], $queryresult[0])) {      //当风格多选时用逗号拼接
			                $queryresult[0][$value['source']] = $queryresult[0][$value['source']].','.$value['properties_name'];
			            } else {
			                $queryresult[0][$value['source']] = $value['properties_name'];
			            }							
					}
				}


				return parent::respData(0, "", array('data' => $queryresult[0]));
			}
			catch (Exception $ex)
			{
				return parent::respData(-1, "获取失败" .$ex->getMessage(), array());	
			}
			// echo "<pre>"; var_dump($queryresult);exit;
		}
	}
?>