<?php

class topwap_ctl_paytoapp{

	/**
	 * 支付后返回后处理的事件的动作
	 * @params array - 所有返回的参数，包括POST和GET
	 * @return null
	 */
    public function callbackMalipay(){
            $recv = input::get();
            logger::info('[paytoapp.callbackMalipay.recv] : callbackData-----------'.json_encode($recv));

            $ret['payment_id'] = $recv['out_trade_no'];


            $ret['currency'] = 'CNY';  //货币
            $ret['paycost'] = '0.000';  //支付网关费用
            // // $ret['cur_money'] = $rec['notify']['total_fee']; //支付货币金额
            $ret['trade_no'] = $recv['trade_no']; //支付单交易编号
            $ret['t_payed'] = strtotime($recv['notify_time']) ? strtotime($recv['notify_time']) : time();
            $ret['pay_app_id'] = "malipay";
            $ret['pay_type'] = 'online';
            // $ret['memo'] = $rec['body'];

            $status = $recv['trade_status'];        //返回token
            if($status == 'TRADE_FINISHED' || $status == 'TRADE_SUCCESS'){
	                     //数据表实例化
		        $objMdlPayments = app::get('ectools')->model('payments');
		        $objMdlPayBill = app::get('ectools')->model('trade_paybill');
	        	$tradePayBill = $objMdlPayBill->getList('tid,payment',array('payment_id'=>$ret['payment_id']));

		        //事务开启
		        $db = app::get('ectools')->database();
		        $db->beginTransaction();
		        try {
		        	$ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_HOST'];
		        	$return_url = array("topwap_ctl_paycenter@finish",array('payment_id'=>$ret['payment_id']));
		            $paymentData = array(
		                // 'money' => $params['money'],//需要支付的金额
		                // 'cur_money' => $params['money'],//支付货币金额
		                'status' => 'succ',  //支付状态
		                'pay_name' => 'malipay',
		                'payed_time' => time(),
		                'bank' => '手机支付宝',
		                'pay_account' => '付款帐号',
		                'currency' => $ret['currency'],
		                'paycost' => $ret['paycost'],
		                'ip' => $ip_addr,
		                'trade_no' => $ret['trade_no'],
		                'pay_app_id' => $ret['pay_app_id'],//支付方式名称
		                'return_url' => $return_url,  //支付返回地址
		            );
	            	$paymentFilter['payment_id'] = $ret['payment_id'];
	            	$result = $objMdlPayments->update($paymentData,$paymentFilter);
		            if(!$result)
		            {
		                throw new Exception('支付失败，支付单更新失败');
		            }     

					if (is_array($tradePayBill)) {
			            foreach($tradePayBill as $val)
			            {
			        	    $data['payment'] = $val['payment'];//该订单支付的金额
			                $data['status'] = 'succ';//该订单支付的状态
			                $data['payed_time'] = time();//最后更新时间
			                $data['modified_time'] = time();//最后更新时间
			                $filter['tid'] = $val['tid'];//被支付订单编号
			                $filter['payment_id'] = $ret['payment_id'];
			                $result = $objMdlPayBill->update($data,$filter);
			                if(!$result)
			                {
			                    throw new Exception('支付失败，支付单明细更新失败');
			                }
			                app::get('ectools')->rpcCall('trade.pay.finish',array('tid'=>$val['tid'],'payment'=>$val['payment']));
			            }
					}


		            $db->commit();    	        	
	        	} catch (Exception $e) {
		             $db->rollback();
		             throw $e;       	        	
	        	}
	        	$infodata = ['status'=>'succ','payment_id'=>$ret['payment_id'],'pay_type'=>'online','cur_money'=>$val['payment'],'trade'=>[$ret['payment_id']=>['payment_id'=>$ret['payment_id'],'tid'=>$val['tid'],'payment'=>$val['payment'],'status'=>'succ','payed_time'=>time()]]];
	        	kernel::single('foroms_output_trade')->payInfo($infodata);
				echo "success";
	            logger::info('[topwap_ctl_paytoapp.paytoapp] : success-----------');
            }else{
                echo "fail";
                $ret['status'] = 'failed';
                logger::warning('[topwap_ctl_paytoapp.paytoapp] : failed-----------');
            }
    }//function callbacktomalipay   end

	/**
	 * 支付后返回后处理的事件的动作
	 * @params array - 所有返回的参数，包括POST和GET
	 * @return null
	 */
    public function callbackWxpay(){
    	// 接收回调参数    begin
        $postData = array();
        $postStr = file_get_contents("php://input");//$GLOBALS["HTTP_RAW_POST_DATA"];
        logger::info('[paytoapp.callbackWxpay] $postStr:' .json_encode($postStr));
        // logger::info('paytoapp wxjs data, xml to array :'.var_export($postStr, 1));
        $postArray = kernel::single('site_utility_xml')->xml2array($postStr);
        $postData['weixin_postdata']  = $postArray['xml'];
        $nodify_data = array_merge(input::get(), $postData);
		logger::info('[paytoapp.callbackWxpay.recv] : callbackData-----------'.json_encode($nodify_data));
    	// 接收回调参数    end

        $ret['payment_id'] = $nodify_data['weixin_postdata']['out_trade_no'];//主支付单编号
		logger::info('[paytoapp.callbackWxpay.recv] : payment_id-----------'.$ret['payment_id']);
        $ret['currency'] = 'CNY';  //货币   fee_type
        $ret['paycost'] = '0.000';  //支付网关费用
            // // $ret['cur_money'] = $rec['notify']['total_fee']; //支付货币金额
        $ret['trade_no'] = $recv['transaction_id']; //支付单交易编号
        $ret['t_payed'] = time();
        $ret['pay_app_id'] = "wxpayjsapi";
        $ret['pay_type'] = 'online';
            // $ret['memo'] = $rec['body'];

        $status = $nodify_data['weixin_postdata']['result_code'];        //获取返回状态标志
        if($status == 'SUCCESS'){
                     //数据表实例化
	        $objMdlPayments = app::get('ectools')->model('payments');
	        $objMdlPayBill = app::get('ectools')->model('trade_paybill');
        	$tradePayBill = $objMdlPayBill->getList('tid,payment',array('payment_id'=>$ret['payment_id']));
        	logger::info('[topwap.payment] : tradePayBill-----------'.json_encode($tradePayBill));
	        //事务开启
	        $db = app::get('ectools')->database();
        	$db->beginTransaction();
	        try {
	        	$ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_HOST'];
	        	$return_url = array("topwap_ctl_paycenter@finish",array('payment_id'=>$ret['payment_id']));
	            $paymentData = array(
	                // 'money' => $params['money'],//需要支付的金额
	                // 'cur_money' => $params['money'],//支付货币金额
	                'status' => 'succ',  //支付状态
	                'pay_name' => 'wxjs',
	                'payed_time' => time(),
	                'bank' => '微信app支付',
	                'pay_account' => '付款帐号',
	                'currency' => $ret['currency'],
	                'paycost' => $ret['paycost'],
	                'ip' => $ip_addr,
	                'trade_no' => $ret['trade_no'],
	                'pay_app_id' => $ret['pay_app_id'],//支付方式名称
	                'return_url' => $return_url,  //支付返回地址
	            );
            	$paymentFilter['payment_id'] = $ret['payment_id'];
            	$result = $objMdlPayments->update($paymentData,$paymentFilter);
	            if(!$result)
	            {
	                throw new Exception('支付失败，支付单更新失败');
	            }     

				if (is_array($tradePayBill)) {
		            foreach($tradePayBill as $val)
		            {
			        	   $data['payment'] = $val['payment'];//该订单支付的金额
			                $data['status'] = 'succ';//该订单支付的状态
			                $data['payed_time'] = time();//最后更新时间
			                $data['modified_time'] = time();//最后更新时间
			                $filter['tid'] = $val['tid'];//被支付订单编号
			                $filter['payment_id'] = $ret['payment_id'];
			                $result = $objMdlPayBill->update($data,$filter);
			                if(!$result)
			                {
			                    throw new Exception('支付失败，支付单明细更新失败');
			                }
			                logger::info('[topwap.payment] : payment-----------'.$val['tid']);

			                app::get('ectools')->rpcCall('trade.pay.finish',array('tid'=>$val['tid'],'payment'=>$val['payment']));
		            }
				}


            	$db->commit();    	        	
	        } catch (Exception $e) {
         		$db->rollback();
         		throw $e;       	        	
	        }


	        $infodata = array(
	        		'status'=>'succ',
	        		'payment_id'=>$ret['payment_id'],
	        		'cur_money'=> $val['payment'],
	        		'trade' =>array(
	        			$ret['payment_id'] => array(
	        				'payment_id' => $ret['payment_id'],
	        				'tid'=> $val['tid'],
	        				'payment'=>$val['payment'],
	        				'status'=>'succ',
	        				'payed_time'=>time(),
	        				),
	        			),
	        	);
        	kernel::single('foroms_output_trade')->payInfo($infodata);
			echo "success";
            logger::info('[topwap_ctl_paytoapp.paytoapp] : success-----------');
        }else{
            echo "failwx";
            $ret['status'] = 'failed';
            logger::warning('[topwap_ctl_paytoapp.paytoapp] : failed-----------');
        }
    }

	/**
	 * 支付后返回后处理的事件的动作
	 * @params array - 所有返回的参数，包括POST和GET
	 * @return null
	 */
    public function callbackWxpayForRecharge(){
    	// 接收回调参数    begin
        $postData = array();
        $postStr = file_get_contents("php://input");//$GLOBALS["HTTP_RAW_POST_DATA"];
        logger::info('[paytoapp.callbackWxpay] $postStr:' .json_encode($postStr));
        $postArray = kernel::single('site_utility_xml')->xml2array($postStr);
        $postData['weixin_postdata']  = $postArray['xml'];
        $nodify_data = array_merge(input::get(), $postData);
		logger::info('[paytoapp.callbackWxpay.recv] : callbackData-----------'.json_encode($nodify_data));
    	// 接收回调参数    end

        $ret['payment_id'] = $nodify_data['weixin_postdata']['out_trade_no'];//主支付单编号
		logger::info('[paytoapp.callbackWxpay.recv] : payment_id-----------'.$ret['payment_id']);
        $ret['currency'] = 'CNY';  //货币   fee_type
        $ret['paycost'] = '0.000';  //支付网关费用
        $ret['trade_no'] = $recv['transaction_id']; //支付单交易编号
        $ret['t_payed'] = time();
        $ret['pay_app_id'] = "wxpayjsapi";
        $ret['pay_type'] = 'online';

        $status = $nodify_data['weixin_postdata']['result_code'];        //获取返回状态标志
        if($status == 'SUCCESS'){
            $params['payment_id'] = $ret['payment_id'];
            $params['fields'] = 'status,payment_id,pay_type,user_id,cur_money,user_name';
            try
            {
                $paymentBill = app::get('ectools')->rpcCall('payment.bill.get',$params);
            }
            catch(Exception $e)
            {
            	logger::info('[paytoapp.callbackMalipay.recv] : callbackData-----------'.json_encode($e->getMessage));
                throw $e;
            }
            try {
				app::get('ectools')->rpcCall('user.deposit.recharge', ['user_id'=>$params['user_id'], 'fee'=>$params['cur_money'], 'memo'=>"预存款充值，用户名：{$params['user_name']}；支付单号：{$params['payment_id']}"]);                        	
            } catch (Exception $e) {
                logger::info("支付过程中，处理订单出错后：$e->getMessage() \n".var_export($paymentBill,1)."\n----end----\n");
                throw $e;                        	
            }            
			echo "success";
            logger::info('[topwap_ctl_paytoapp.paytoapp] : success-----------');
        }else{
            echo "failwx";
            $ret['status'] = 'failed';
            logger::warning('[topwap_ctl_paytoapp.paytoapp] : failed-----------');
        }
    }


	/**
	 * 支付后返回后处理的事件的动作
	 * @params array - 所有返回的参数，包括POST和GET
	 * @return null
	 */
    public function callbackMalipayForRecharge(){
            $recv = input::get();
            logger::info('[paytoapp.callbackMalipayForRecharge.recv] : callbackData-----------'.json_encode($recv));

            $ret['payment_id'] = $recv['out_trade_no'];


            $ret['currency'] = 'CNY';  //货币
            $ret['paycost'] = '0.000';  //支付网关费用
            // // $ret['cur_money'] = $rec['notify']['total_fee']; //支付货币金额
            $ret['trade_no'] = $recv['trade_no']; //支付单交易编号
            $ret['t_payed'] = strtotime($recv['notify_time']) ? strtotime($recv['notify_time']) : time();
            $ret['pay_app_id'] = "malipay";
            $ret['pay_type'] = 'online';
            // $ret['memo'] = $rec['body'];

            $status = $recv['trade_status'];        //返回token
            if($status == 'TRADE_FINISHED' || $status == 'TRADE_SUCCESS'){
				$params['payment_id'] = $ret['payment_id'];
	            $params['fields'] = 'status,payment_id,pay_type,user_id,cur_money,user_name';
	            try
	            {
	                $paymentBill = app::get('ectools')->rpcCall('payment.bill.get',$params);
	            }
	            catch(Exception $e)
	            {
	            	logger::info('[paytoapp.callbackMalipayForRecharge.recv] : callbackData-----------'.json_encode($e->getMessage));
	                throw $e;
	            }
	            try {
					app::get('ectools')->rpcCall('user.deposit.recharge', ['user_id'=>$params['user_id'], 'fee'=>$params['cur_money'], 'memo'=>"预存款充值，用户名：{$params['user_name']}；支付单号：{$params['payment_id']}"]);                        	
	            } catch (Exception $e) {
	                logger::info("支付过程中，处理订单出错后：$e->getMessage() \n".var_export($paymentBill,1)."\n----end----\n");
	                throw $e;                        	
	            } 	            
				echo "success";
	            logger::info('[topwap.callbackMalipayForRecharge] : success-----------');
            }else{
                echo "fail";
                $ret['status'] = 'failed';
                logger::warning('[topwap.callbackMalipayForRecharge] : failed-----------');
            }
    }//function callbacktomalipay   end




}