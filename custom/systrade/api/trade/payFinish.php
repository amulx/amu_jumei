<?php
class systrade_api_trade_payFinish {

    public $apiDescription = "订单支付状态改变";

    public function getParams()
    {
        $return['params'] = array(
            'tid' => ['type'=>'int', 'valid'=>'required', 'default'=>'', 'example'=>'','description'=>'订单id'],
            'payment' => ['type'=>'int', 'valid'=>'required', 'default'=>'', 'example'=>'','description'=>'已支付金额'],
        );
        return $return;
    }

    public function tradePay($params)
    {
        $tid = $params['tid'];
        $objTrade = kernel::single('systrade_data_trade');
        $tradeInfo = $objTrade->getTradeInfo('payment,status,tid,shop_id',['tid'=>$tid]);
        if($tradeInfo['status'] != 'WAIT_BUYER_PAY' )
        {
            logger::info("支付已成功的订单，不需要重复支付");
            return true;
        }

        $db = app::get('systrade')->database();
        $db->beginTransaction();
        $objMdlOrder = app::get('systrade')->model('order');
        try{

            foreach($tradeInfo['order'] as $orderkey=>$orderData)
            {
                $this->__minusStore($orderData);
            }

            $tradeData['data']['status']='WAIT_SELLER_SEND_GOODS';
            $tradeData['data']['modified_time']=time();
            $tradeData['data']['pay_time']=time();
            $tradeData['data']['payed_fee'] = $params['payment'];
            $tradeData['filter']['tid'] = $tid;

            logger::info("支付成功，更新主订单".var_export($tradeData,1));
            $result = $objTrade->updateTrade($tradeData);
            if(!$result)
            {
                throw new \LogicException("主订单支付状态更新失败");
            }

            $orders = array(
                'status'=>'WAIT_SELLER_SEND_GOODS',
                'pay_time'=> time(),
            );

            logger::info("支付成功，更新子订单".var_export($orders,1));
            if(!$objMdlOrder->update($orders, array('tid'=>$tid) ) )
            {
                $msg = "子订单支付状态修改失败";
                throw new \LogicException($msg);
            }

            $db->commit();

            // 2016-09-16 min.zhou@yorisun.com 订单结果通知oms
            $mdlTradePayBill = app::get('ectools')->model('trade_paybill');
            $payBill = $mdlTradePayBill->getRow('payment_id', array('tid' => $tid));
            if ($payBill)
            {
	            $infodata = array(
        	        		'status'=>'succ',
        	        		'payment_id'=>$payBill['payment_id'],
        	        		'cur_money'=> $tradeInfo['payment'],
        	        		'trade' =>array(
        	        			$payBill['payment_id'] => array(
        	        				'payment_id' => $payBill['payment_id'],
        	        				'tid'=> $tid,
        	        				'payment'=>$tradeInfo['payment'],
        	        				'status'=>'succ',
        	        				'payed_time'=> time(),
        	        				),
        	        			),
        	        	);
        		kernel::single('foroms_output_trade')->payInfo($infodata);
            }
            else
            {
            	logger::warning('[systrade_api_trade_payFinish.tradePay] 支付单不存在，tid:' .$tid);
            }            
        }
        catch(\Exception $e)
        {
	        logger::error('[systrade_api_trade_payFinish.tradePay] ex:' .$e->getMessage());
            $db->rollback();
            throw $e;
        }

        event::fire('trade.pay', [$tid, $params['payment'], $tradeInfo['shop_id']] );

        return true;
    }

    private function __minusStore($orderData)
    {
        // 处理sku订单冻结
        $params = array(
            'item_id' => $orderData['item_id'],
            'sku_id' => $orderData['sku_id'],
            'quantity' => $orderData['num'],
            'sub_stock' => $orderData['sub_stock'],
            'status' => 'afterpay',
        );
        $isMinus = app::get('systrade')->rpcCall('item.store.minus',$params);
        if( ! $isMinus )
        {
            throw new \LogicException(app::get('systrade')->_('冻结库存失败'));
        }
    }
}


