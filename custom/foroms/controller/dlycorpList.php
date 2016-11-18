<?php  

/**
* 
*/
class foroms_ctl_dlycorpList extends foroms_controller
{
	/**
	 * 根据shop_id获取签约物流公司
	 * @return [type] [description]
	 */
	function getList(){
		$shopId = input::get('shopid');
		$shopid = intval($shopId);
		$dlycorp = app::get('foroms')->rpcCall('shop.dlycorp.getlist',['shop_id'=>$shopid]);
		return parent::respData(0, "", array('data' => $dlycorp));
	}
}



?>