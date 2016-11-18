<?php

class systrade_data_trade_create {

    /**
     * 生成的主订单号集合,用于新家订单后返回
     */
    private $tids = [];

    /**
     *  返回当前时间，用于新建订单，保证存储的时间一致
     */
    private $nowtime = '';

    /**
     * 新建订单的所有cart_id 集合，用于新建订单后删除购物车数据
     */
    protected $cartIds = [];

    /**
     * 当前订单使用的店铺优惠券 用于记录使用店铺优惠券日志
     */
    protected $cartUseCoupon = [];

    /**
     * 当前订单优惠的信息 用于记录优惠日志 事件中执行
     */
    protected $cartPromotion = [];


   /**
     * 构造方法
     * @param object app
     */
    public function __construct($app)
    {
        $this->objMdlTrade = app::get('systrade')->model('trade');
        $this->objLibCatServiceRate = kernel::single('sysshop_data_cat');
        $this->objLibTradeTotal = kernel::single('systrade_data_trade_total');
        $this->objMdlCart = app::get('systrade')->model('cart');

        $this->userIdent = $this->objMdlCart->getUserIdentMd5();
    }

    /**
     * 订单标准数据生成
     * @params mixed - 订单数据
     * @param array cart object array
     * @return boolean - 成功与否(mixed 订单数据)
     */
    public function generate($tradeParams, $aCart=array() )
    {
        $db = app::get('systrade')->database();
        $db->beginTransaction();

        try
        {
            //格式化订单数据，不包含订单优惠, 金额数据，运费计算
            $tradeData = $this->_chgdata($tradeParams, $aCart);

            //计算订单价格，运费
            $priceTotalData  = $this->getTradeTotal($tradeParams, $aCart);

            //存储订单数据完整结构
            foreach( $tradeData as $shopId=>$row )
            {
                $tradeData[$shopId] = array_merge($priceTotalData[$shopId], $row);
            }

            //处理积分抵扣后的订单数据
            if( $tradeParams['use_points'] )
            {
                $tradeData = $this->__pointDeductionMoney($tradeData,$tradeParams);
            }

			//===========================================================================
			// 2016-09-01  min.zhou@yorisun.com
			// 输出订单数据给foroms
			//logger::info('[systrade_data_trade_create.generate] 1:' .json_encode($tradeData));
			kernel::single('foroms_output_trade')->create($tradeData);
			//logger::info('[systrade_data_trade_create.generate] 2:' .json_encode($tradeData));
			//===========================================================================
            
            //保存订单数据
            foreach( $tradeData as $shopId=>$row )
            {
                $result = $this->objMdlTrade->save($tradeData[$shopId],null,true);
                if(  !$result )
                {
                    throw new \LogicException(app::get('systrade')->_('订单生成失败'));
                }
            }

            //将已使用的优惠券更新为已使用
            $this->_couponUse($tradeData);

            //SESSION中删除已使用的优惠券
            $this->__unsetCartUseCoupon();

            //触发创建订单事件
            $this->createTradeEventFire($tradeData, $tradeParams);

            $db->commit();
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }

        return $this->getTids();
    }

    //触发订单创建后的事件
    public function createTradeEventFire($tradeData, $tradeParams)
    {
        foreach( $tradeData as $shopId=>$shopTradeData )
        {
            $trade[$shopId]['tid'] = $shopTradeData['tid'];
            $trade[$shopId]['shop_id'] = $shopId;
            $trade[$shopId]['payment'] = $shopTradeData['payment'];
            foreach( $shopTradeData['order'] as $k=>$shopOrderData )
            {
                $trade[$shopId]['order'][$k]['shop_id'] = $shopOrderData['shop_id'];
                $trade[$shopId]['order'][$k]['tid'] = $shopOrderData['tid'];
                $trade[$shopId]['order'][$k]['oid'] = $shopOrderData['oid'];
                $trade[$shopId]['order'][$k]['user_id'] = $shopOrderData['user_id'];
                $trade[$shopId]['order'][$k]['item_id'] = $shopOrderData['item_id'];
                $trade[$shopId]['order'][$k]['sku_id'] = $shopOrderData['sku_id'];
                $trade[$shopId]['order'][$k]['num'] = $shopOrderData['num'];

                $trade[$shopId]['order'][$k]['selected_promotion'] = $shopOrderData['selected_promotion'];
                $trade[$shopId]['order'][$k]['activityDetail'] = $shopOrderData['activityDetail'];
            }
        }

        $data = [
            'user_id' => $tradeParams['user_id'],
            'user_name' => $tradeParams['user_name'],
            'trade' => $trade,
            'invoice' => $tradeParams['invoice'],
        ];
        // 订单创建事件
        event::fire('trade.create', [$data, ['cartIds'=>$this->cartIds,'mode'=>$tradeParams['mode'],'cartPromotion'=>$this->cartPromotion] ]);
    }

    /**
     * 创建订单ID
     *
     * @param int $userId 消费者ID
     * @param bool $isTid 是否为主订单ID
     * @return $tid
     */
    protected function genId($userId, $isTid=true )
    {
        if( ! $this->genidData )
        {
            $data['tradeBaseTime'] = date('ymdHi');
            $data['tradeBaseRandNum'] = rand(0,49);//str_pad($tradeBaseRandNum,2,'0',STR_PAD_LEFT);
            $data['tradeModUserId'] = str_pad($userId%10000,4,'0',STR_PAD_LEFT);

            $this->genidData = $data;
        }

        $id = $this->genidData['tradeBaseTime'].str_pad(++$this->genidData['tradeBaseRandNum'],2,'0',STR_PAD_LEFT).$this->genidData['tradeModUserId'];
        if( $isTid ) $this->tids[] = $id;

        return $id;
    }

    /**
     * 返回创建的主订单号集合
     */
    public function getTids()
    {
        return $this->tids;
    }

    /**
     * 返回当前时间
     */
    public function getTime()
    {
        return $this->nowTime ? $this->nowTime : time();
    }

    /**
     * 返回订单公用的数据结构
     */
    private function __commonTradeData($tradeParams)
    {
        $need_invoice = $tradeParams['invoice']['need_invoice'] ? 1 : 0;
        $invoice_name = $invoice_main = $invoice_vat_main = '';
        if($need_invoice)
        {
            if($tradeParams['invoice']['invoice_type'] == 'normal')
            {
                $invoice_name = $tradeParams['invoice']['invoice_title'];
                $invoice_main = strip_tags($tradeParams['invoice']['invoice_content']);
            }
            if($tradeParams['invoice']['invoice_type'] == 'vat')
            {
                $invoice_vat_main = $tradeParams['invoice']['invoice_vat'];
            }
        }
        return [
            'user_id'           => $tradeParams['user_id'],
            'user_name'         => $tradeParams['user_name'],
            'created_time'      => $this->getTime(),
            'modified_time'     => $this->getTime(),
            'ip'                => request::server('REMOTE_ADDR'),
            'title'             => app::get('systrade')->_('订单明细介绍'),
            'pay_type'          => $tradeParams['payment_type'] ? $tradeParams['payment_type'] : 'online',
            'need_invoice'      => $need_invoice,
            'trade_from'        => $tradeParams['source_from'],
            'invoice_name'      => $invoice_name,
            'invoice_main'      => $invoice_main,
            'invoice_vat_main'  => $invoice_vat_main,
            'invoice_type'      => $tradeParams['invoice']['invoice_type'],
            'receiver_name'     => $tradeParams['delivery']['receiver_name'],
            'receiver_address'  => $tradeParams['delivery']['receiver_address'],
            'receiver_zip'      => $tradeParams['delivery']['receiver_zip'],
            'receiver_tel'      => $tradeParams['delivery']['receiver_tel'],
            'receiver_mobile'   => $tradeParams['delivery']['receiver_mobile'],
            'receiver_state'    => $tradeParams['delivery']['receiver_state'],
            'receiver_city'     => $tradeParams['delivery']['receiver_city'],
            'receiver_district' => $tradeParams['delivery']['receiver_district'],
            'buyer_area'        => $tradeParams['delivery']['buyer_area'],
        ];
    }

    /**
     * 返回子订单结构
     *
     * @param array $tradeParams
     * @param array $cartItem
     * @param array $shopId
     */
    private function __orderItemData($tradeParams, $cartItem, $shopId)
    {
        $subStock = ($tradeParams['payment_type'] == 'online') ? $cartItem['sub_stock'] : '1';
        $oid = $this->genId($tradeParams['user_id'], false);

        $orderData = [
            'oid'              => $oid,
            'tid'              => end($this->getTids()),
            'shop_id'          => $shopId,
            'user_id'          => $tradeParams['user_id'],
            'item_id'          => $cartItem['item_id'],
            'sku_id'           => $cartItem['sku_id'],
            'cat_id'           => $cartItem['cat_id'],
            'bn'               => $cartItem['bn'],
            'price'            => $cartItem['price']['price'],
            'num'              => $cartItem['quantity'],
            'payment'          => ecmath::number_minus(array($cartItem['price']['total_price'],$cartItem['price']['discount_price'])),
            'total_fee'        => $cartItem['price']['total_price'],
            'part_mjz_discount'=> $cartItem['price']['discount_price'],
            'total_weight'     => $cartItem['weight'],
            'pic_path'         => $cartItem['image_default_id'],
            'sub_stock'        => $subStock,
            'cat_service_rate' => $this->objLibCatServiceRate->getCatServiceRate(array('shop_id'=>$shopId, 'cat_id'=>$cartItem['cat_id'])),
            'sendnum'          => 0,
            'created_time'     => $this->getTime(),
            'modified_time'    => $this->getTime(),
            'status'           => $this->__tradeStatus($tradeParams['payment_type']),
            'title'            => $cartItem['title'],
            'spec_nature_info' => $cartItem['spec_info'],
            'order_from'       => $tradeParams['source_from'],
            'selected_promotion' => $cartItem['selected_promotion'],
            'dlytmpl_id'       => $cartItem['dlytmpl_id'],
        ];

        if( $cartItem['promotion_type'] == 'activity' )
        {
            $orderData['promotion_type'] = $cartItem['promotion_type'];
            $orderData['activityDetail'] = $cartItem['activityDetail'];
        }

        return $orderData;
    }

    private function __orderPackageData($tradeParams, $cartItem, $shopId, $opacval)
    {
        $subStock = ($tradeParams['payment_type'] == 'online') ? $opacval['sub_stock'] : '1';
        $oid = $this->genId($tradeParams['user_id'], false);

        $orderInfo = array(
            'oid'              => $oid,
            'tid'              => end($this->getTids()),
            'shop_id'          => $shopId,
            'user_id'          => $tradeParams['user_id'],
            'item_id'          => $opacval['item_id'],
            'sku_id'           => $opacval['sku_id'],
            'cat_id'           => $opacval['cat_id'],
            'bn'               => $opacval['bn'],
            'price'            => $opacval['price']['price'],
            'num'              => $cartItem['quantity'],
            'payment'          => ecmath::number_multiple( array( $opacval['price']['price'], $cartItem['quantity'] ) ),
            'total_fee'        => ecmath::number_multiple( array( $opacval['price']['price'], $cartItem['quantity'] ) ),
            'part_mjz_discount'=> ecmath::number_multiple( array( $opacval['price']['discount_price'], $cartItem['quantity'] ) ),
            'total_weight'     => ecmath::number_multiple( array( $opacval['weight'], $cartItem['quantity'] ) ),
            'pic_path'         => $opacval['image_default_id'],
            'sub_stock'        => $subStock,
            'cat_service_rate' => $this->objLibCatServiceRate->getCatServiceRate(array('shop_id'=>$shopId, 'cat_id'=>$opacval['cat_id'])),
            'sendnum'          => 0,
            'created_time'     => $this->getTime(),
            'modified_time'    => $this->getTime(),
            'status'           => $this->__tradeStatus($tradeParams['payment_type']),
            'title'            => $opacval['title'],
            'spec_nature_info' => $opacval['spec_info'],
            'order_from'       => $tradeParams['source_from'],
            'selected_promotion' => $cartItem['selected_promotion'],
            'dlytmpl_id'       => $opacval['dlytmpl_id'],
        );

        return $orderInfo;
    }

    /**
     * 定义创建订单的默认的订单状态
     *
     * @param string $paymentType offline 线下支付 | online 在线支付
     * @return string
     */
    private function __tradeStatus( $paymentType )
    {
        //如果订单的支付方式为线下支付，订单状态默认为等待发货，否则为等待支付
        return ($paymentType == "offline") ?  "WAIT_SELLER_SEND_GOODS" : "WAIT_BUYER_PAY";
    }

    /**
     * 计算订单价格，运费 基本的优惠已经在获取的购物车数据中计算好了
     *
     * @param array $tradeParams 创建订单参数
     * @param array $aCart 需要创建订单获取的购物车数据
     */
    public function getTradeTotal($tradeParams, $aCart)
    {
        foreach ($aCart['resultCartData'] as $shopId => $shopCartData )
        {
            //计算订单总金额
            $totalParams = array(
                'discount_fee' => $shopCartData['cartCount']['total_discount'],
                'total_fee' => $shopCartData['cartCount']['total_fee'],
                'total_weight' => $shopCartData['cartCount']['total_weight'],
                'shop_id' => $shopCartData['shop_id'],
                'shipping_type' => $tradeParams['shipping'][$shopId]['shipping_type'],
                'region_id' => str_replace('/', ',', $tradeParams['region_id']),
                'usedCartPromotionWeight' => $shopCartData['usedCartPromotionWeight'],
                'usedToPostage' => json_encode($shopCartData['cartByDlytmpl']),
            );
            $totalInfo = $this->objLibTradeTotal->trade_total_method($totalParams);

            $priceTotalData[$shopId]['payment'] = $totalInfo['payment'];
            $priceTotalData[$shopId]['total_fee'] = $totalInfo['total_fee'];
            $priceTotalData[$shopId]['discount_fee'] = $totalInfo['discount_fee'];
            $priceTotalData[$shopId]['obtain_point_fee'] = $totalInfo['obtain_point_fee'];
            $priceTotalData[$shopId]['post_fee'] = $totalInfo['post_fee'];
        }

        return $priceTotalData;
    }

    /**
     * 返回订单保存基本数据
     *
     * @params array $tradeParams
     * @params array $aCart
     * @return array $orderData
     */
    private function _chgdata( $tradeParams, $aCart=array() )
    {
        //创建店铺主订单公用的数据
        $commonTradeData = $this->__commonTradeData($tradeParams);

        foreach ($aCart['resultCartData'] as $shopId => $shopCartData )
        {
            $this->shopIds[] = $shopId;
            $tid = $this->genId($tradeParams['user_id']);
            $shopTradeData = [
                'shop_id'    => $shopId,
                'tid'        => $tid,
                'status'     => $this->__tradeStatus($commonTradeData['pay_type']),
                'trade_memo' => strip_tags($tradeParams['trade_memo'][$shopId]),
                'dlytmpl_ids' => implode(',', array_keys($shopCartData['cartByDlytmpl'])),
                'ziti_addr'  => $tradeParams['ziti'][$shopId]['ziti_addr'],
                'itemnum'    => $shopCartData['cartCount']['itemnum'],
                'shipping_type' => $tradeParams['shipping'][$shopId]['shipping_type'],
                'total_weight' => $shopCartData['cartCount']['total_weight']
            ];

            //主订单基本数据 不包含订单金额数据
            $orderData[$shopId] = array_merge($commonTradeData, $shopTradeData);

            //当前订单使用的店铺优惠券 用于记录使用店铺优惠券日志
            if( $shopCartData['cartCount']['total_coupon_discount'] > 0 && $_SESSION['cart_use_coupon'][$this->userIdent][$shopId] )
            {
                $this->cartUseCoupon[$shopId] = $_SESSION['cart_use_coupon'][$this->userIdent][$shopId];
            }

            //购物车优惠信息
            $this->__setPromotionParams($shopId, $shopCartData);

            // 子订单
            foreach($shopCartData['object'] as $k =>$cartItem)
            {
                if( $cartItem['obj_type'] == 'item' )
                {
                    $shopOrderData = $this->__orderItemData($tradeParams, $cartItem, $shopId);
                    $this->__minusStore($shopOrderData);
                    $orderData[$shopId]['order'][] = $shopOrderData;
                }

                if( $cartItem['obj_type'] == 'package' )
                {
                    foreach($cartItem['skuList'] as $opacval)
                    {
                        $shopOrderData = $this->__orderPackageData($tradeParams, $cartItem, $shopId, $opacval);
                        $this->__minusStore($shopOrderData);
                        $orderData[$shopId]['order'][] = $shopOrderData;
                    }
                }

                $this->cartIds[] = $cartItem['cart_id'];
            }
        }
        return $orderData;
    }

    private function __setPromotionParams($shopId, $shopCartData)
    {
        // 用于生成促销日志表
        $this->cartPromotion[$shopId]['basicPromotionListInfo'] = $shopCartData['basicPromotionListInfo'];
        // 本次购物使用的促销id
        $this->cartPromotion[$shopId]['usedCartPromotion'] = $shopCartData['usedCartPromotion'];

        return true;
    }

    //删除使用的优惠券
    private function __unsetCartUseCoupon()
    {
        foreach( $this->shopIds as $shopId )
        {
            unset($_SESSION['cart_use_coupon'][$this->userIdent][$shopId]);
        }

        return true;
    }

    /**
     * 订单创建成功后将已使用的优惠券更新为已使用
     *
     * @param array $tradeData
     */
    protected function _couponUse($tradeData)
    {
        foreach( (array)$this->cartUseCoupon as $shopId => $couponCode  )
        {
            $data = array(
                'tid' => $tradeData[$shopId]['tid'],
                'coupon_code' => $couponCode,
            );

            if( !app::get('systrade')->rpcCall('user.coupon.useLog', $data) )
            {
                throw new \LogicException(app::get('systrade')->_('优惠券使用失败'));
            }
        }

        return true;
    }

    /**
     * 下单减库存; 支付减库存,下单冻结库存
     *
     * @param $orderData 商品的子订单数据
     */
    private function __minusStore($orderData)
    {
        // 处理sku订单冻结
        $params = array(
            'item_id' => $orderData['item_id'],
            'sku_id' => $orderData['sku_id'],
            'quantity' => $orderData['num'],
            'sub_stock' => $orderData['sub_stock'],
            'status' => 'afterorder',
        );
        $isMinus = app::get('systrade')->rpcCall('item.store.minus',$params);
        if( ! $isMinus )
        {
            throw new \LogicException(app::get('systrade')->_('冻结库存失败'));
        }

        return true;
    }

    /**
     * @brief 下单时使用积分抵钱
     *
     * @param $tradeData
     * @param $postdata
     *
     * @return
     */
    private function __pointDeductionMoney($tradeData,$postdata)
    {
        //积分抵扣不计算运费
        $usePoints = $postdata['use_points'];
        $payment = array_column($tradeData,'payment');
        $totalPayment = array_sum($payment);
        $postFee = array_column($tradeData,'post_fee');
        $totalPostFee = array_sum($postFee);
        foreach($tradeData as $key=>$value)
        {
            $trade_money = $value['payment']-$value['post_fee'];
            $params = array(
                'user_id' => $value['user_id'],
                'use_point' => $usePoints,
                'total_money' => $totalPayment-$totalPostFee,
                'trade_money' => $trade_money,
            );
            $result = app::get('systrade')->rpcCall('point.deduction.num',$params);
            if(!$result) continue;

            $point_deduction_rate = app::get('sysconf')->getConf('point.deduction.rate');
            $point_deduction_rate = $point_deduction_rate ? $point_deduction_rate : 100;

            $min = ecmath::number_div(array(1, $point_deduction_rate) );

            if($result['money'] > $min)
            {
                foreach($value['order'] as $k=>$val)
                {
                    $paramsOrder = array(
                        'user_id' => $value['user_id'],
                        'use_point' => $result['point'],
                        'total_money' => $trade_money,
                        'trade_money' => $val['payment'],
                    );
                    $resultOrder = app::get('systrade')->rpcCall('point.deduction.num',$paramsOrder);
                    $tradeData[$key]['order'][$k]['consume_point_fee'] = $resultOrder['point'];
                    $tradeData[$key]['order'][$k]['points_fee'] = $resultOrder['money'];
                }

                $tradeData[$key]['consume_point_fee'] = $result['point'];
                $tradeData[$key]['points_fee'] = $result['money'];
                $tradeData[$key]['payment'] = ecmath::number_minus(array($value['payment'],$result['money']));
            }
        }
        $this->__consumePoint($tradeData);
        return $tradeData;
    }

    private function __consumePoint($tradeData)
    {
        foreach($tradeData as $key=>$value)
        {
            if(count($value['order']) > 1)
            {
                $behavior = "多个商品：".array_shift($value['order'])['title']."等...; 订单号：".$value['tid'];
            }
            else
            {
                $behavior = array_shift($value['order'])['title']."; 订单号：".$value['tid'];
            }
            if($value['consume_point_fee'])
            {
                // 积分抵扣下单扣减积分
                $updateParams = array(
                    'user_id' => $value['user_id'],
                    'type' => 'consume',
                    'num' => $value['consume_point_fee'],
                    'behavior' => $behavior,
                    'remark' => app::get('systrade')->_('交易扣减'),
                );
                $result = app::get('systrade')->rpcCall('user.updateUserPoint',$updateParams);
            }
        }
    }

}

