<?php
class topwap_ctl_cart_checkout extends topwap_controller{

    public $payType = array(
        'online' => '线上支付',
        'offline' => '货到付款',
    );

    public function index()
    {
        header("cache-control: no-store, no-cache, must-revalidate");
        $this->setLayoutFlag('order_index');
        theme::setTitle('结算订单');
        $postData = input::get();
        //支付方式
        $pagedata['payType'] = array('pay_type'=>'online','name'=>$this->payType['online']);

        $cartFilter['mode'] = $postData['mode'] ? $postData['mode'] :'cart';
        $pagedata['nextPage'] = url::action('topwap_ctl_cart@index');
        $pagedata['mode'] = $postData['mode'];

        try{
            /*获取收货地址 start*/
            if(isset($postData['addr_id']) && $postData['addr_id'])
            {
                $params['user_id'] = userAuth::id();
                $params['addr_id'] = $postData['addr_id'];
                $userDefAddr = app::get('topwap')->rpcCall('user.address.info',$params);
            }
            else
            {
                // 获取默认地址
                $params['user_id'] = userAuth::id();
                $params['def_addr'] = 1;
                $userDefAddr = app::get('topwap')->rpcCall('user.address.list',$params);
                $userDefAddr = $userDefAddr['list']['0'];
                if(!$userDefAddr['list'])
                {
                    $userAddr= app::get('topwap')->rpcCall('user.address.count',array('user_id'=>$params['user_id']));
                    $pagedata['nowcount'] = $userAddr['nowcount'];
                }
            }
            $pagedata['def_addr'] = $userDefAddr;
            /*获取收货地址 end*/

            // 商品信息
            $cartFilter['needInvalid'] = false;
            $cartFilter['platform'] = 'wap';
            $cartFilter['user_id'] = userAuth::id();
            $cartInfo = app::get('topwap')->rpcCall('trade.cart.getCartInfo', $cartFilter,'buyer');
            // var_dump($cartInfo);exit;
            if(!$cartInfo)
            {
                $resetUrl = url::action('topwap_ctl_default@index');
                return $this->splash('error', $resetUrl);
            }

            $isSelfShop = true;

            foreach($cartInfo['resultCartData'] as $key=>&$val)
            {
                if($val['shop_type'] != "self")
                {
                    $isSelfShop = false;
                }
                else
                {
                    $isSelfShopArr[] = $val['shop_id'];
                }

                $val['couponlist'] = $this->__getCoupons($val['shop_id']);

                $pagedata['shipping'][$val['shop_id']]['shipping_type'] = 'express';

                if($cartFilter['mode'] == "fastbuy")
                {
                    $fastBuyItemData = reset($val['object']);
                    $pagedata['nextPage'] = url::action('topwap_ctl_item_detail@index',['item_id'=>$fastBuyItemData['item_id']]);
                }
            }

            $pagedata['ifOpenOffline'] = app::get('ectools')->getConf('ectools.payment.offline.open');
            $pagedata['isSelfShop'] = $isSelfShop;

            $pagedata['cartInfo'] = $cartInfo;

            //用户验证购物车数据是否发生变化
            $md5CartFilter = array('user_id'=>userAuth::id(), 'platform'=>'wap', 'mode'=>$cartFilter['mode'], 'checked'=>1);
            $md5CartInfo = md5(serialize(utils::array_ksort_recursive(app::get('topwap')->rpcCall('trade.cart.getBasicCartInfo', $md5CartFilter, 'buyer'), SORT_STRING)));
            $pagedata['md5_cart_info'] = $md5CartInfo;

            //获取默认图片信息
            $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');

            // 刷新结算页则失效前面选则的优惠券
            $shop_ids = array_keys($pagedata['cartInfo']['resultCartData']);
            foreach($shop_ids as $sid)
            {
                $apiParams = array(
                    'coupon_code' => '-1',
                    'shop_id' => $sid,
                );
                app::get('topwap')->rpcCall('trade.cart.cartCouponCancel', $apiParams, 'buyer');
            }

        }catch(Exception $e){
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg);
        }

        $pagedata['if_open_point_deduction'] = app::get('topwap')->rpcCall('point.setting.get',['field'=>'open.point.deduction']);

        $pagedata['invoice'] = json_decode(redis::scene('sysuser')->hget('invoice_info', userAuth::id()), 1);

        $curSymbol = app::get('topwap')->rpcCall('currency.get.symbol',array());
        $pagedata['curSymbol'] = $curSymbol;

        return $this->page('topwap/cart/checkout/index.html', $pagedata);

    }

     //结算页收货地址列表
    public function addrList()
    {
        $pagedata['mode'] = input::get('mode');
        $pagedata['userAddrList'] = $this->__getAddrList();
        return $this->page('topwap/cart/checkout/addr_list.html', $pagedata);
    }

    //结算页支付方式和配送方式列表
    public function deliveryList()
    {

        $postdata = input::get();
        $isDefault = 1;
        if($postdata['shipping_type'] && $postdata['shipping_type'] != "express")
        {
            $isDefault = 0;
        }

        $pagedata['dtyList'][] = array('shipping_type' => 'express','name' => '快递配送','isDefault' => $isDefault);

        $ifOpenZiti =app::get('syslogistics')->getConf('syslogistics.ziti.open');

        if($ifOpenZiti == 'true' && $postdata['shop_type'] == "self")
        {
            $params['user_id'] = userAuth::id();
            $params['addr_id'] = $postdata['addr_id'];
            $params['fields'] = "area";
            $addrInfo= app::get('topwap')->rpcCall('user.address.info',$params);
            $area = explode(':',$addrInfo['area']);
            $area = implode(',',explode('/',$area[1]));
            $zitiData = app::get('topwap')->rpcCall('logistics.ziti.list',array('area_id'=>$area));

            foreach($zitiData as $key=>$value)
            {
                if($postdata['ziti_id'] == $value['ziti_id']){
                    $zitiData[$key]['isDefault'] = 1;
                }
                else
                {
                    $zitiData[$key]['isDefault'] = 0;
                }
            }

            $isDefault = $postdata['shipping_type'] == "ziti" ? 1 : 0;
            if($zitiData)
            {
                $pagedata['dtyList'][] = array('shipping_type' => 'ziti','name' => '上门自提','isDefault' => $isDefault);
                $pagedata['zitiDataList'] = $zitiData;
            }
        }

        return response::json($pagedata);exit;;
    }

    /**
     * @brief 获取上门自取的地址列表
     *
     * @return html
     */
    public function getZitiList()
    {
        $postData = input::get();
        return  $this->page('topwap/cart/checkout/ziti_list.html', $pagedata);
    }

    public function getCouponList()
    {
        $shopId = intval(input::get('shop_id'));
        $pagedata['couponlist'] = $this->__getCoupons($shopId);
        $pagedata['shop_id'] = $shopId;
        return $this->page('topwap/cart/checkout/coupon_list.html',$pagedata);
    }

    private function __getCoupons($shop_id)
    {
        // 默认取100个优惠券，用作一页显示，一般达不到这个数量一个店铺
        $params = array(
            'page_no' => 0,
            'page_size' => 100,
            'fields' => '*',
            'user_id' => userAuth::id(),
            'shop_id' => intval($shop_id),
            'is_valid' => 1,
            'platform' => 'wap',
        );
        $couponListData = app::get('topwap')->rpcCall('user.coupon.list', $params, 'buyer');
        $couponList = $couponListData['coupons'];

        return $couponList;
    }

    private function __getAddrList()
    {
        $params['user_id'] = userAuth::id();
        //会员收货地址
        $userAddrList = app::get('topwap')->rpcCall('user.address.list',$params,'buyer');
        $count = $userAddrList['count'];
        $userAddrList = $userAddrList['list'];

        foreach ($userAddrList as $key => $value) {
            $userAddrList[$key]['area'] = explode(":",$value['area'])[0];
        }
        return $userAddrList;
    }

    /**
     * @brief 计算购物车金额
     *
     * @return
     */

    public function total()
    {
        $postData = input::get();
        // logger::info('[topwap_ctl_cart_useCoupon] : total-----------'.json_encode($postData['coupon_use']));
        //判断coupon_code是否属于平台优惠券       begin    add@by cg 2016/9/6
        foreach ($postData['coupon_use'] as $key => $value) {
            if(strpos($value['coupon_code'],'YS') !== false){
                    $regcouponDate = app::get('sysuser')->model('user_regcoupon')->getRow('condition_value', array('coupon_code' => $value['coupon_code']));
                    $condition_value = $regcouponDate['condition_value'];
                     logger::info('[topwap_ctl_cart_useCoupon] : condition_value-----------'.json_encode($condition_value));
            }
        }
        //判断coupon_code是否属于平台优惠券       end    add@by cg 2016/9/6

        if($postData['current_shop_id'])
        {
            $current_shop_id = $postData['current_shop_id'];
            unset($postData['current_shop_id']);
        }

        if($addrId = $postData['addr_id'])
        {
            $params['user_id'] = userAuth::id();
            $params['addr_id'] = $addrId;
            $params['fields'] = 'area';
            $addr = app::get('topwap')->rpcCall('user.address.info',$params,'buyer');
            list($regions,$region_id) = explode(':', $addr['area']);
        }

        $cartFilter['mode'] = $postData['mode'] ? $postData['mode'] :'cart';
        $cartFilter['needInvalid'] = $postData['checkout'] ? false : true;
        $cartFilter['platform'] = 'wap';
        $cartFilter['user_id'] = userAuth::id();
        // var_dump($cartFilter);exit;
        $cartInfo = app::get('topwap')->rpcCall('trade.cart.getCartInfo', $cartFilter,'buyer');

        $allPayment = 0;
        $objMath = kernel::single('ectools_math');

        foreach ($cartInfo['resultCartData'] as $shop_id => $tval) {

            // 对商品进行打折处理   begin     add@by cg 2016/9/6
            if (!empty($condition_value)) {
                $tval['cartCount']['total_discount'] = $tval['cartCount']['total_fee'] * (100.0 - $condition_value)/100;
                logger::info('[topwap_ctl_cart_useCoupon] : total_discount-----------'.json_encode($tval['cartCount']['total_discount']));
            }
            // 对商品进行打折处理    end

            $totalParams = array(
                'discount_fee' => $tval['cartCount']['total_discount'],
                'total_fee' => $tval['cartCount']['total_fee'],
                'total_weight' => $tval['cartCount']['total_weight'],
                'shop_id' => $tval['shop_id'],
                'shipping_type' => $postData['shipping'][$tval['shop_id']]['shipping_type'],
                'region_id' => $region_id ? str_replace('/', ',', $region_id) : '0',
                'usedCartPromotionWeight' => $tval['usedCartPromotionWeight'],
                'usedToPostage' => json_encode($tval['cartByDlytmpl']),
            );
            $totalInfo = app::get('topwap')->rpcCall('trade.price.total',$totalParams,'buyer');
            $trade_data['allPayment'] = $objMath->number_plus(array($trade_data['allPayment'] ,$totalInfo['payment']));
            $trade_data['allPostfee'] = $objMath->number_plus(array($trade_data['allPostfee'] ,$totalInfo['post_fee']));
            $trade_data['disCountfee'] = $objMath->number_plus(array($trade_data['disCountfee'] ,$totalInfo['discount_fee']));

            if($current_shop_id && $shop_id != $current_shop_id)
            {
                continue;
            }

            $trade_data['shop'][$shop_id]['payment'] = $totalInfo['payment'];
            $trade_data['shop'][$shop_id]['total_fee'] = $totalInfo['total_fee'];
            $trade_data['shop'][$shop_id]['discount_fee'] = $totalInfo['discount_fee'];
            $trade_data['shop'][$shop_id]['obtain_point_fee'] = $totalInfo['obtain_point_fee'];
            $trade_data['shop'][$shop_id]['post_fee'] = $totalInfo['post_fee'];
            $trade_data['shop'][$shop_id]['totalWeight'] += $tval['cartCount']['total_weight'];
        }
        return response::json($trade_data);exit;
    }

    public function useCoupon()
    {
        // logger::info('[topwap_ctl_cart_useCoupon] : useCoupon-----------'.json_encode(input::get()));
        if(strpos(input::get('coupon_code'),'YS') !== false){     //判断coupon_code是否属于平台优惠券       add@by cg 2016/9/6
                    $msg = app::get('topwap')->_('使用优惠券成功！');
                    return $this->splash('success', null, $msg, true);
        }else{
            try
            {
                $mode = input::get('mode');
                $buyMode = $mode ? $mode :'cart';
                $apiParams = array(
                    'coupon_code' => input::get('coupon_code'),
                    'mode' => $buyMode,
                    'platform' => 'wap',
                );
                if( app::get('topwap')->rpcCall('promotion.coupon.use', $apiParams,'buyer') )
                {
                    $msg = app::get('topwap')->_('使用优惠券成功！');
                    return $this->splash('success', null, $msg, true);
                }
            }
            catch(Exception $e)
            {
                $msg = $e->getMessage();
                return $this->splash('error',null,$msg,true);
            }            
        }     

    }

    public function cancelCoupon()
    {
        try
        {
            $apiParams = array(
                'coupon_code' => input::get('coupon_code'),
                'shop_id' => input::get('shop_id'),
            );
            if( app::get('topwap')->rpcCall('trade.cart.cartCouponCancel', $apiParams,'buyer') )
            {
                $msg = app::get('topwap')->_('取消优惠券成功！');
                return $this->splash('success', null, $msg, true);
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
    }

    public function ajaxGetUserPoint()
    {
        $totalPrice = input::get('total_price');
        $totalPostFee = input::get('post_fee');
        $totalPrice = $totalPrice-$totalPostFee;
        $userId = userAuth::id();
        //根据会员id获取积分总值
        $points = app::get('topwap')->rpcCall('user.point.get',['user_id'=>$userId]);
        $setting = app::get('topwap')->rpcCall('point.setting.get');
        $pagedata['open_point_deduction'] = $setting['open.point.deduction'];
        $pagedata['point_deduction_rate'] = $setting['point.deduction.rate'];
        $pagedata['point_deduction_max'] = floor($setting['point.deduction.max']*$totalPrice*$setting['point.deduction.rate']);
        $pagedata['points'] = $points['point_count'] ? $points['point_count'] : 0;
        //print_r($pagedata);exit;
        return response::json($pagedata);exit;
    }


    private function __postdata($postData,&$pagedata)
    {
        if($postData['checkout_info'])
        {
            $checkout_info = json_decode($postData['checkout_info'],true);
            foreach($checkout_info as $val)
            {
                $name = explode('[',$val['name']);
                $count = count($name);
                if($count == 1)
                {
                    $postData[$name[0]] = $val['value'];
                }
                elseif($count >1)
                {
                    $value = $val['value'];
                    $key = array();
                    for($i=$count-1;$i>=0;$i--){
                        $k = rtrim($name[$i],']');
                        if($i == $count-1){
                            $key[$k] =$value;
                        }
                        else
                        {
                            $key[$k] = $key;
                        }

                        unset($key[rtrim($name[$i+1],']')]);
                    }
                   $pagedata = $this->__mergeArrays($pagedata,$key);
                }
            }

            //支付方式
            if(isset($postData['pay_type']))
            {
                $pagedata['payType'] = array('pay_type'=>$postData['pay_type'],'name'=>$this->payType[$postData['pay_type']]);
                unset($postData['pay_type']);
            }
            else
            {
                $pagedata['payType'] = array('pay_type'=>'online','name'=>$this->payType['online']);
            }

            //配送方式
            if(isset($postData['shipping']))
            {
                foreach($postData['shipping'] as $k=>$val){
                    $pagedata['shipping'][$k] = $val;
                }
                unset($postData['shipping']);
            }

            //配送方式
            if(isset($postData['ziti']))
            {
                foreach($postData['ziti'] as $k=>$val){
                    $pagedata['ziti'][$k] = $val;
                }
                unset($postData['ziti']);
            }

            if(isset($postData['coupon']))
            {
                foreach($postData['coupon'] as $k=>$val){
                    $pagedata['coupon'][$k] = $val;
                }
                unset($postData['coupon']);
            }
            unset($postData['checkout_info']);
            $postData = array_merge($postData,$pagedata);
        }
    }

    private function __mergeArrays($Arr1, $Arr2)
    {
      foreach($Arr2 as $key => $Value)
      {
        if(array_key_exists($key, $Arr1) && is_array($Value))
        {
          $Arr1[$key] = $this->__mergeArrays($Arr1[$key], $Arr2[$key]);
        }
        else
        {
          $Arr1[$key] = $Value;
        }

      }

      return $Arr1;
    }

}
