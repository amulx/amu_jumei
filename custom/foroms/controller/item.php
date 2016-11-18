<?php
	/*
		2016-09-07  min.zhou@yorisun.com
		提供给oms查询商品的控制器
	*/
	class foroms_ctl_item extends foroms_controller
	{
		/*
			获取商品基础数据
		*/
		public function queryItem()
		{
			$param = input::get();

			try
			{
				$item = app::get('foroms')->rpcCall('item.get', $param);
				return parent::respData(0, "", array('data' => $item));
			}
			catch (Exception $ex)
			{
				return parent::respData(-1, "获取失败" .$ex->getMessage(), array());  
			}			
		}

		/*
			获取商品sku数据
		*/
		public function querySku()
		{
			$param = input::get();

			try
			{
				$item = app::get('foroms')->rpcCall('item.sku.get', $param);
				return parent::respData(0, "", array('data' => $item));
			}
			catch (Exception $ex)
			{
				return parent::respData(-1, "获取失败" .$ex->getMessage(), array());  
			}	
		}
	}
?>