<?

class topc_ctl_getProps extends topc_controller{

	public function test (){
	        //获取用户openid和mobile
	           $qb = app::get('jumei')->database()->createQueryBuilder();
	           $qb->select('A.user_id,A.mobile,D.openid')
	           ->from('sysuser_account','A')
	           ->leftJoin('A','sysuser_user_distri', 'D','A.user_id=D.user_id');
	           $queryresult = $qb->execute()->fetchAll();


		foreach ($queryresult as $key => $value) {
			$pps_ids = app::get('sysuser')->model('user_properties')->getRow('pps_id', array('user_id' => $value['user_id']));
			if (!empty($pps_ids)) {
				$expDate = array_filter(explode(',',$pps_ids));
				$rows = app::get('sysuser')->model('properties')->getList('properties_name',array('properties_id'=>$expDate));
			}
			$queryresult[$key]['prop'] = $rows;
		}
		echo json_encode($queryresult);exit();
		// var_dump($queryresult);exit();
	 //           $postDate['mobile'] = $result[0]['mobile'];
	 //           $postDate['openid'] = $result[0]['openid'];
	 //           logger::info('[topwap.passposr.addpps] : postDate-----------'.json_encode($postDate));
	 //           //向第三方传送数据
	 //           $hoturl = 'http://app.yinahuo.com/bbc/user_reg.php?reg_data='.json_encode($postDate);
	 //            $this->httpGet($url);		
	}
}
		