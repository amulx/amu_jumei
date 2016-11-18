<?php
	/*
		2016-09-01  min.zhou@yorisun.com
		输出订单数据到oms
	*/
	class foroms_output_trade extends foroms_output_common
	{
		private $mdlDlytmpl;

		public function __construct($app)
    	{
	    	parent::__construct();
	    	$this->mdlDlytmpl = app::get('syslogistics')->model('dlytmpl');
    	}
		
		public function create($trade)
		{
			// 获取订单商品的快递信息
			foreach( $trade as $key => $value )
			{
				$dlytmplId = $value['order']['dlytmpl_id'];
				$shopId = $value['shop_id'];
				$row = $this->mdlDlytmpl->getRow('name, is_free', array('template_id' => $dlytmplId, 'shop_id' => $shopId));
				if (!empty($row))
				{
					$trade[$key]['order']['dlytmpl_name'] = $row['name'];
					$trade[$key]['order']['dlytmpl_is_free'] = $row['is_free'];
				}
			}
			logger::info('[foroms_output_trade.create] trade:' .json_encode($trade));
			return parent::postData(array('data' => $trade), 'order_bbc_add.php');
			//$res = client::post($this->url, $postData);
			//logger::info('[foroms_output_trade.create] res:' .json_encode($res));
		}

		/*
			退款
		*/
		public function refund($data)
		{
			logger::info('[foroms_output_trade.refund] data:' .json_encode($data)."\n----end----\n");
			return parent::postData(array('data' => $data), 'order_bbc_refund.php');
		}

		/*
			关闭
		*/
		public function confirm($data)
		{
			return parent::postData(array('data' => $data), 'order_bbc_close.php');
		}
		/**
		 * 付款成功
		 * @param  [type] $data [description]
		 * @return [type]       [description]
		 */
		public function payInfo($data){
			logger::info('[foroms_output_trade.payInfo] data:' .json_encode($data)."\n----end----\n");
			return parent::postData(array('data' => $data), 'order_bbc_payinfo.php');
		}
		/**
		 * 取消订单
		 * @param  [type] $data [description]
		 * @return [type]       [description]
		 */
		public function cancelRequest($data){
			logger::info('[foroms_output_trade.cancelRequest] data:' .json_encode($data)."\n----end----\n");
			return parent::postData(array('data' => $data), 'order_bbc_cancelrequest.php');			
		}

		/**
		 * 发送订单处理
		 * @param  [type] $data [description]
		 * @return [type]       [description]
		 */
		// public function dodelivery($data){
		// 	logger::info('[foroms_output_trade.dodelivery] data:' .json_encode($data)."\n----end----\n");
		// 	return parent::postData(array('data' => $data), 'order_bbc_cancelrequest.php');//待修改				
		// }
	}
?>