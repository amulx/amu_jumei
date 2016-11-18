<?php
	/*
		2016-09-07  min.zhou@yorisun.com
		提供给oms处理订单的控制器
	*/
	class foroms_ctl_trade extends foroms_controller
	{
		/*
			发货
		*/
		public function delivery()
		{
			$postData = input::get();//	        $corp_code = 'STO';参数中增加一个物流公司编号
			logger::info('[foroms_ctl_trade.delivery] : $postData=' .json_encode($postData));
			// 店铺id
			$shopId = $postData['shop_id'];
			if (empty($shopId))
			{
				return parent::respData(-1, "缺少参数shop_id", array()); 
			}

			// 获取商家签约物流corp_code
			//$dlycorp = app::get('foroms')->rpcCall('shop.dlycorp.getlist',['shop_id'=>$shopId]);
			/**
			 * modify@by cg 2016/9/14  通过递归方法判断提交的物流公司编号是否在签约物流内     begin
			 */
			//$corp_code = $postData['corp_code'];
			$corp_name = $postData['corp_code'];
			$corp_code = $this->getCorpCodeByName($corp_name);
			logger::info('[foroms_ctl_trade.delivery] : $crop_code=' .$corp_code);
			if (empty($corp_code))
			{
				$errCode = -1;
				$errMsg = '快递公司[' .$corp_name .']不存在';
				logger::info('[foroms_ctl_trade.delivery] : $errMsg=' .$errMsg);
				return parent::respData($errCode, $errMsg, array());
			}
			// 检查快递公司是否加入系统物流公司列表
			$mdlDlycorp = app::get('syslogistics')->model('dlycorp');
			$rowDlycorp = $mdlDlycorp->getRow('corp_id', array('corp_code' => $corp_code));
			if (!$rowDlycorp)
			{				
				$corp_id = $mdlDlycorp->save(array('corp_code' => $corp_code, 'corp_name' => $corp_name));
			}
			else
			{
				$corp_id = $rowDlycorp['corp_id'];
			}			
			
			// 检查快递公司是否加入店铺物流公司列表
			$mdlShopRelDlycrop = app::get('sysshop')->model('shop_rel_dlycorp');
			$rowShopRel = $mdlShopRelDlycrop->getRow('corp_id', array('shop_id' => $shopId, 'corp_id' => $corp_id));
			logger::info('[foroms_ctl_trade.delivery] : $rowShopRel=' .json_encode($rowShopRel));
			if (!$rowShopRel)
			{
				$param = array('corp_id' => $corp_id, 'shop_id' => $shopId, 'corp_code' => $corp_code, 'corp_name' => $corp_name);
				logger::info('[foroms_ctl_trade.delivery] : sysshop_shop_rel_dlycorp, $param=' .json_encode($param));
				$mdlShopRelDlycrop->save($param);
			}			
			
	        //$dly_result = $this->recursive($dlycorp['list'],'corp_code',$corp_code);
	        // echo "<pre>";var_dump($result);exit();	
			/**
			 * modify@by cg 2016/9/14  通过递归方法判断提交的物流公司编号是否在签约物流内     end
			 */		
			//if ($dly_result)
			//{
			$postData['corp_code'] = $corp_code;
			//}
			//else
			//{
			//	return parent::respData(-1, "店铺没有关联物流公司", array()); 
			//}

			// 获取seller
			//$paramSeller['shop_id'] = $shopId;
			//$seller = app::get('foroms')->rpcCall('account.shop.user.list', $paramSeller);
			$objMdlSeller = app::get('sysshop')->model('seller');
        	$data = $objMdlSeller->getList('seller_id,seller_type',['shop_id'=>$shopId]);
			logger::info('[foroms_ctl_trade.delivery] : $seller=' .json_encode($data));
			if ($data)
			{
				foreach( $data as $key => $value )
				{
					if ($value['seller_type'] == 0)
					{
						$postData['seller_id'] = $value['seller_id'];
						break;
					}
				}
			}
			else
			{
				logger::info('[foroms_ctl_trade.delivery] : 店铺管理员帐号不存在');
				return parent::respData(-1, "店铺管理员帐号不存在", array()); 
			}

			$postData['ziti_memo'] = '';
			/**
			接口trade.delivery(对指定订单进行发货，交易发货)
			所需参数：
			tid:订单号(required)
			corp_code:物流公司编号(required)
			logi_no:运单号
			isZiti:
			ziti_memo:自提备注
			shop_id:店铺id    (required)
			seller_id:商家操作员id  (required)
			memo:备注
			 */
			try
			{
				$res = app::get('foroms')->rpcCall('trade.delivery', $postData);
				$errCode = $res ? 0 : -1;
				$errMsg = $res ? "" : "发货失败";
				logger::info('[foroms_ctl_trade.delivery] : $errCode=' .$errCode);
				return parent::respData($errCode, $errMsg, array()); 
			}
			catch (Exception $ex)
			{
				logger::error('[foroms_ctl_trade.delivery] : $ex=' .json_encode($ex));
				return parent::respData(-1, $ex->getMessage(), array()); 
			}			
		}

		/*
			退款
		*/
		public function refund()
		{
			$postData = input::get('data');
			// 验证订单是否存在
			$tid = $postData['tid'];
			$oid = $postData['oid'];
			if (empty($tid) || empty($tid))
			{
				return parent::respData(-1, "缺少参数tid或oid", array()); 
			}

			$mdlOrder = app::get('systrade')->model('order');
			$row = $mdlOrder->getRow('shop_id', array('tid' => $tid, 'oid' => $oid));
			if ($row['shop_id'] != $postData['shop_id'])
			{
				return parent::respData(-1, "订单不属于店铺", array()); 
			}

			unset($postData['shop_id']);

			$db = app::get('ectools')->database();
	        $db->beginTransaction();
	        try
	        {
	            $objRefund = kernel::single('ectools_data_refunds');
	            $result = $objRefund->create($params);
	        }
	        catch(\Exception $e)
	        {
	            $db->rollback();
	            return parent::respData(-1, "创建退款单失败" .$e->getMessage(), array());;
	        }
	        $db->commit();
			
			if (isset($res['refund_id']))
			{
				if ($postData['rufund_type'] != 'offline')
				{
					// 更新退款单状态
					$param['tid'] = $postData['tid'];
					$param['refund_id'] = $res['refund_id'];
					$param['status'] = 'succ';
					app::get('foroms')->rpcCall('refund.update', $param);
				}
				return parent::respData(0, '', $res); 
			}
			else
			{
				return parent::respData(-1, "创建退款单失败", array());
			}
		}

		/*
			关闭订单
		*/
		public function confirm()
		{
			$postData = input::get();
			try
			{
				$res =  kernel::single('systrade_data_trade_confirm')->generate($postData['tid'], $postData['user_id'], $postData['shop_id'] );
				return parent::respData(0, "关闭订单成功", array());
			}
			catch (Exception $ex)
			{
				return parent::respData(-1, "关闭订单失败" .$ex->getMessage(), array());	
			}			
		}

		/**
		 * 买家确认收货
		 * @return [type] [description]
		 */
		public function confirmReceipt(){

				//app::get('topwap')->rpcCall('trade.confirm',$params,'buyer');
		       $filter['tid'] = input::get('tid');
		        if( $userId ) $filter['user_id'] = input::get('user_id');
		        // if( $shopId ) $filter['shop_id'] = intval($shopId);

		        $tradeInfo = $this->objTrade->getTradeInfo('status,user_id,shop_id,obtain_point_fee,consume_point_fee,payment,post_fee,points_fee,cancel_status',$filter);

		        $this->__check($tradeInfo);

		        $db = app::get('systrade')->database();
		        $db->beginTransaction();
		        try
		        {

		            $update['filter'] = $filter;
		            $update['data'] = [
		                'status' => 'TRADE_FINISHED',
		                'is_clearing' => 1,
		                'modified_time' => time(),
		                'end_time' => time(),
		            ];

		            if( ! $this->objTrade->updateTrade($update) )
		            {
		                throw new \LogicException("订单完成失败，更新数据库失败");
		            }

		            $objMdlOrder = app::get('systrade')->model('order');
		            if( !$objMdlOrder->update(['status'=>'TRADE_FINISHED','end_time'=>time()], ['tid'=>$tid]) )
		            {
		                throw new \LogicException("订单的子订单完成失败，更新数据库失败");
		            }

		            $db->commit();
		        }
		        catch (Exception $e)
		        {
		            $db->rollback();
					$msg = $e->getMessage();
					return parent::respData(-1, "确认收货失败" .$msg, array());
		        }

		       $this->confirmTradeEvent($tradeInfo);
     	        return parent::respData(0, "确认收货成功", array());
			
		}

	    /**
	     * 递归
	     * @param  [type] $array [description]
	     * @param  [type] $index [description]
	     * @return [type]        [description]
	     */
	    function recursive($array, $index,$corp_code) {  
	        if (!is_array($array)) return false;  //判断是否为数组
	        if (isset($array[$index])){   //是否存在该下标值
	            if ($array[$index] == $corp_code) {
	                return true;
	            }
	        }
	        foreach ($array as $item) {  
	            $return = $this->recursive($item, $index,$corp_code);  
	            if (!is_null($return)) {  
	                return $return;  
	            }  
	        }  
	        return false;  
	    }  

	    /**
		2016-09-17  min.zhou@yorisun.com
		根据快递公司名称获取快递编码
		*/
		function getCorpCodeByName($corpName)
		{
			$objDlycorp = kernel::single('syslogistics_data_dlycorp');
	        $corpList = $objDlycorp->getDlycorp();
	        logger::info('[foroms_ctl_trade.getCorpCodeByName] : $corpList=' .json_encode($corpList));
			foreach( $corpList as $key => $value )
			{
				if (strpos($value, $corpName) !== false)
				{
					return $key;
				}
			}
			return null;
		}
	}

?>