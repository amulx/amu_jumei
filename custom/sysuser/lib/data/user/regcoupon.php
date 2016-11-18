<?php

class sysuser_data_user_regcoupon{
	
	/**
	 * 保存用户领取优惠卷
	 *
	 * @param      数组  $data   
	 *
	 * @return     true or false
	 */
	public function saveRegcoupon($data){

		$userMdlRegcoupon = app::get('sysuser')->model('user_regcoupon');
		$userMdlRegistereddiscount = app::get('syspromotion')->model('registereddiscount');

		$regData = $userMdlRegistereddiscount->getRow('*',array('regdis_id'=>$data['regdis_id']));

		$saveDate['coupon_code'] = $this->makePrefixKey().time();
		$saveDate['user_id'] = $data['user_id'];
		$saveDate['coupon_name'] = $regData['coupon_name'];
		$saveDate['coupon_desc'] = $regData['coupon_desc'];
		$saveDate['obtain_time'] = time();
		$saveDate['used_platform'] = '2';
		$saveDate['condition_value'] = $regData['condition_value'];
		$saveDate['obtain_desc'] = '免费领取';
		$saveDate['regdis_id'] = $data['regdis_id'];
		$saveDate['start_time'] = strtotime(date('Y-m-d',$regData['canuse_start_time']));
		return $userMdlRegcoupon->insert($saveDate);
	}

	public function makePrefixKey($length=8, $prefixFlag='YS') {
        $returnStr='';
        $pattern = '1234567890ABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        for($i = 0; $i < $length; $i ++) {
            $returnStr .= $pattern {mt_rand ( 0, 35 )};
        }
        return $prefixFlag.$returnStr;
    }

}