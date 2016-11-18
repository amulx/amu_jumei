<?php

class topc_ctl_commodity extends topc_controller{

	//czh 2016.9.2 改成用api接口传递
	public function getCommodity(){

		$postData = input::get();
		$resultItem = app::get('topwap')->rpcCall('item.get.commodity',$postData);
		return $resultItem;
	}

}
