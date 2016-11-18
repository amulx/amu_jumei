<?php
class topwap_ctl_cart extends topwap_controller{

    public function __construct()
    {
        parent::__construct();
        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);
    }

    public function addCart()
    {
        $mode = input::get('mode');
        $obj_type = input::get('obj_type');

        $params['obj_type'] = $obj_type ? $obj_type : 'item';
        $params['mode'] = $mode ? $mode : 'cart';
        $params['user_id'] = userAuth::id();
        if( $params['obj_type']=='package' )
        {
            $package_id = input::get('package_id');
            $params['package_id'] = intval($package_id);
            $skuids = input::get('package_item');
            $tmpskuids = array_column($skuids, 'sku_id');
            $params['package_sku_ids'] = implode(',', $tmpskuids);
            $params['quantity'] = input::get('package-item.quantity',1);
        }
        if( $params['obj_type']=='item')
        {
            $quantity = input::get('item.quantity');
            $params['quantity'] = $quantity ? $quantity : 1; //购买数量，如果已有购买则累加
            $params['sku_id'] = intval(input::get('item.sku_id'));
        }

        try
        {
            $data = kernel::single('topwap_cart')->addCart($params);
            if( $data === false )
            {
                $msg = app::get('topwap')->_('加入进货车失败!');
                return $this->splash('error',null,$msg,true);
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }

        $msg = app::get('topwap')->_('成功加入进货车');
        $url = "";
        if( $params['mode'] == 'fastbuy' )
        {
            $url = url::action('topwap_ctl_cart_checkout@index',array('mode'=>'fastbuy') );
            $msg = "";
        }
        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);

        return $this->splash('success',$url,$msg,true);
    }

    public function index()
    {
        theme::setTitle('进货车');
        $this->setLayoutFlag('cart');

        $pagedata['nologin'] = userAuth::check() ? false : true;
        $pagedata['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');

        $cartData = kernel::single('topwap_cart')->getCartInfo();
        $pagedata['aCart'] = $cartData['resultCartData'];
        $pagedata['totalCart'] = $cartData['totalCart'];

        // 店铺可领取优惠券
        foreach ($pagedata['aCart'] as &$v) {
            $params = array(
                'page_no' => 0,
                'page_size' => 1,
                'fields' => '*',
                'shop_id' => $v['shop_id'],
                'platform' => 'wap',
                'is_cansend' => 1,
            );
            $couponListData = app::get('topwap')->rpcCall('promotion.coupon.list', $params, 'buyer');
            if($couponListData['count']>0)
            {
                $v['hasCoupon'] = 1;
            }
        }
        return $this->page('topwap/cart/index.html',$pagedata);
    }

    public function updateCart()
    {
        $postCartId = input::get('cart_id');
        $postCartNum = input::get('cart_num');
        $postPromotionId = input::get('promotionid');
        $params = array();
        foreach ($postCartId as $cartId => $v)
        {
            $data['mode'] = $mode;
            $data['obj_type'] = $obj_type;
            $data['cart_id'] = intval($cartId);
            $data['totalQuantity'] = intval($postCartNum[$cartId]);
            $data['selected_promotion'] = intval($postPromotionId[$cartId]);
            $data['user_id'] = userAuth::id();

            if($v=='1')
            {
                $data['is_checked'] = '1';
            }
            if($v=='0')
            {
                $data['is_checked'] = '0';
            }
            $params[] = $data;
        }

        try
        {
            foreach($params as $updateParams)
            {
                //$data = app::get('topwap')->rpcCall('trade.cart.update',$updateParams);
                $data = kernel::single('topwap_cart')->updateCart($updateParams);
                if( $data === false )
                {
                    $msg = app::get('topwap')->_('更新失败');
                    return $this->splash('error',null,$msg,true);
                }
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }

        $cartData = kernel::single('topwap_cart')->getCartInfo();
        $pagedata['aCart'] = $cartData['resultCartData'];

        // 临时统计购物车页总价数量等信息
        $totalWeight = 0;
        $totalNumber = 0;
        $totalPrice = 0;
        $totalDiscount = 0;
        foreach($cartData['resultCartData'] as $v)
        {
            $totalWeight += $v['cartCount']['total_weight'];
            $totalNumber += $v['cartCount']['itemnum'];
            $totalPrice += $v['cartCount']['total_fee'];
            $totalDiscount += $v['cartCount']['total_discount'];
        }
        $totalCart['totalWeight'] = $totalWeight;
        $totalCart['number'] = $totalNumber;
        $totalCart['totalPrice'] = $totalPrice;
        $totalCart['totalAfterDiscount'] = ecmath::number_minus(array($totalPrice, $totalDiscount));
        $totalCart['totalDiscount'] = $totalDiscount;
        $pagedata['totalCart'] = $totalCart;

        $pagedata['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');

        foreach(input::get('cart_shop') as $shopId => $cartShopChecked)
        {
            $pagedata['selectShop'][$shopId] = $cartShopChecked=='on' ? true : false;
        }
        $pagedata['selectAll'] = input::get('cart_all')=='on' ? true : false;

        // 店铺可领取优惠券
        foreach ($pagedata['aCart'] as &$v) {
            $params = array(
                'page_no' => 0,
                'page_size' => 1,
                'fields' => '*',
                'shop_id' => $v['shop_id'],
                'platform' => 'wap',
                'is_cansend' => 1,
            );
            $couponListData = app::get('topwap')->rpcCall('promotion.coupon.list', $params, 'buyer');
            if($couponListData['count']>0)
            {
                $v['hasCoupon'] = 1;
            }
        }

        $pagedata['nologin'] = userAuth::check() ? false : true;
        $msg = view::make('topwap/cart/cart_main.html', $pagedata)->render();

        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);

        return $this->splash('success',null,$msg,true);
    }

    public function ajaxGetItemPromotion()
    {
        $itemId = intval(input::get('item_id'));
        $platform='wap';

        $itemPromotionTagInfo = app::get('systrade')->rpcCall('item.promotion.get', array('item_id'=>$itemId),'buyer');
        if(!$itemPromotionTagInfo)
        {
            return false;
        }
        $allPromotion = array();
        foreach($itemPromotionTagInfo as $v)
        {
            $basicPromotionInfo = app::get('systrade')->rpcCall('promotion.promotion.get', array('promotion_id'=>$v['promotion_id'], 'platform'=>$platform), 'buyer');
            if($basicPromotionInfo['valid']===true)
            {
                $allPromotion[$v['promotion_id']] = $basicPromotionInfo;
            }
        }
        // 倒序排序，购物车的默认促销规则选择最新添加的促销适用
        ksort($allPromotion);
        $pagedata['promotions'] = $allPromotion;
        return view::make('topwap/cart/item_promotion.html',$pagedata);
    }

    /**
     * @brief 删除购物车中数据
     *
     * @return
     */
    public function removeCart()
    {
        $postCartIdsData = input::get('cart_id');
        $tmpCartIds['cart_id'] = array_filter(explode(',',$postCartIdsData));
        $params['cart_id'] = implode(',',$tmpCartIds['cart_id']);
        if(!$params['cart_id'])
        {
            return $this->splash('error',null,'请选择需要删除的商品！',true);
        }
        $params['user_id'] = userAuth::id();

        try
        {
            $res = kernel::single('topwap_cart')->deleteCart($params);
            if( $res === false )
            {
                throw new Exception(app::get('topwap')->_('删除失败'));
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
        $countData = kernel::single('topc_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);
        
        return $this->splash('success',null,'删除成功',true);
    }

    public function ajaxBasicCart()
    {
        $cartData = kernel::single('topwap_cart')->getCartInfo();

        $pagedata['aCart'] = $cartData['resultCartData'];

        $pagedata['totalCart'] = $cartData['totalCart'];

        $pagedata['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');

        foreach(input::get('cart_shop') as $shopId => $cartShopChecked)
        {
            $pagedata['selectShop'][$shopId] = $cartShopChecked=='on' ? true : false;
        }
        $pagedata['selectAll'] = input::get('cart_all')=='on' ? true : false;

        $msg = view::make('topwap/cart/cart_main.html', $pagedata)->render();

        return $this->splash('success',null,$msg,true);
    }

    private function __getDtyList($shop_ids,$isSelfShop=null,$zitiData)
    {
        $tmpParams = array(
            'shop_id' => implode(',',$shop_ids),
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $dtytmpls = app::get('topwap')->rpcCall('logistics.dlytmpl.get.list',$tmpParams,'buyer');
        $dtytmplsBykey = array();
        if($dtytmpls)
        {
            foreach ($shop_ids as $shopid)
            {
                $dtytmplsBykey[$shopid][] = array('shipping_type' => 'express','name' => '快递配送');
            }
        }


        $ifOpenZiti = app::get('syslogistics')->getConf('syslogistics.ziti.open');
        if( $isSelfShop )
        {
            foreach($isSelfShop as $shopid)
            {
                if( $zitiData && $ifOpenZiti == 'true' )
                {
                    $dtytmplsBykey[$shopid][] = array(
                        'shipping_type' => 'ziti',
                        'name' => '上门自提',
                    );
                }
            }
        }
        return $dtytmplsBykey;
    }

    public function checkout()
    {
        header("cache-control: no-store, no-cache, must-revalidate");

        $postData =utils::_filter_input(input::get());
        $cartFilter['mode'] = $postData['mode'] ? $postData['mode'] :'cart';
        $pagedata['mode'] = $postData['mode'];

        try
        {
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

            if(isset($postData['pay_type']))
            {
                $pagedata['payType'] = array('pay_type'=>$postData['pay_type'],'name'=>$this->payType[$postData['pay_type']]);
            }

            //print_r($pagedata); exit;
            // 商品信息
            $cartFilter['needInvalid'] = false;
            $cartFilter['platform'] = 'wap';
            $cartFilter['user_id'] = userAuth::id();
            $cartInfo = app::get('topwap')->rpcCall('trade.cart.getCartInfo', $cartFilter,'buyer');
            if(!$cartInfo)
            {
                $resetUrl = url::action('topwap_ctl_default@index');
                return $this->splash('error', $resetUrl);
            }

            $isSelfShop = true;
            $pagedata['ifOpenOffline'] = app::get('ectools')->getConf('ectools.payment.offline.open');
            $pagedata['ifOpenZiti'] =app::get('syslogistics')->getConf('syslogistics.ziti.open');

            foreach($cartInfo['resultCartData'] as $key=>$val)
            {
                if($val['shop_type'] != "self")
                {
                    $isSelfShop = false;
                }
                else
                {
                    $isSelfShopArr[] = $val['shop_id'];
                }
            }
            $pagedata['isSelfShop'] = $isSelfShop;
            //echo "<pre>"; print_r($cartInfo);print_r($pagedata); exit;
            $pagedata['cartInfo'] = $cartInfo;

            //用户验证购物车数据是否发生变化
            $md5CartFilter = array('user_id'=>userAuth::id(), 'platform'=>'wap', 'mode'=>$cartFilter['mode'], 'checked'=>1);
            $md5CartInfo = md5(serialize(utils::array_ksort_recursive(app::get('topwap')->rpcCall('trade.cart.getBasicCartInfo', $md5CartFilter, 'buyer'), SORT_STRING)));
            $pagedata['md5_cart_info'] = $md5CartInfo;

            $ifOpenZiti = app::get('syslogistics')->getConf('syslogistics.ziti.open');
            if($isSelfShop && $ifOpenZiti == 'true' && $pagedata['def_addr'])
            {
                $area = explode(':',$pagedata['def_addr']['area']);
                $area = implode(',',explode('/',$area[1]));
                $zitiData = app::get('topwap')->rpcCall('logistics.ziti.list',array('area_id'=>$area));
                $pagedata['zitiDataList'] = $zitiData;
            }

            $shop_ids = array_keys($pagedata['cartInfo']['resultCartData']);
            if( $isSelfShop )
            {
                $pagedata['dtyList'] = $this->__getDtyList($shop_ids,$isSelfShopArr,$zitiData);
            }
            else
            {
                $pagedata['dtyList'] = $this->__getDtyList($shop_ids,$isSelfShop);
            }

            // 优惠券列表
            foreach ($pagedata['cartInfo']['resultCartData'] as &$v)
            {
                $nocoupon = array('0'=>array('coupon_name'=>'不使用优惠券', 'coupon_code'=>'-1'));
                $validcoupon = $this->__getCoupons($v['shop_id']);
                $v['couponList'] = array_merge($nocoupon, $validcoupon);
            }

            // 刷新结算页则失效前面选则的优惠券
            foreach($shop_ids as $sid)
            {
                $apiParams = array(
                    'coupon_code' => '-1',
                    'shop_id' => $sid,
                );
                app::get('topwap')->rpcCall('trade.cart.cartCouponCancel', $apiParams, 'buyer');
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg);
        }

        $pagedata['if_open_point_deduction'] = app::get('topwap')->rpcCall('point.setting.get',['field'=>'open.point.deduction']);

        $curSymbol = app::get('topwap')->rpcCall('currency.get.symbol',array());
        $pagedata['curSymbol'] = $curSymbol;

        // error_log(print_r($pagedata,1),3,DATA_DIR."/cart.log");
        return $this->page('topwap/cart/checkout/index.html', $pagedata);
    }

    /**
     * @brief 计算购物车金额
     *
     * @return
     */

    public function total()
    {
        $postData = input::get();
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
        $cartInfo = app::get('topwap')->rpcCall('trade.cart.getCartInfo', $cartFilter,'buyer');

        $allPayment = 0;
        $objMath = kernel::single('ectools_math');

        foreach ($cartInfo['resultCartData'] as $shop_id => $tval) {
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

    //结算页收货地址列表
    public function addrList()
    {
        $params['user_id'] = userAuth::id();
        //会员收货地址
        $userAddrList = app::get('topwap')->rpcCall('user.address.list',$params,'buyer');
        $count = $userAddrList['count'];
        $userAddrList = $userAddrList['list'];
        if(empty($userAddrList))
        {
            return $this->page('topwap/member/address/empty.html', $pagedata);
        }

        foreach ($userAddrList as $key => $value) {
            $userAddrList[$key]['area'] = explode(":",$value['area'])[0];
        }
        $pagedata['userAddrList'] = $userAddrList;
        $pagedata['userAddrCount'] = $count;
        return $this->page('topwap/cart/checkout/addr_list.html', $pagedata);
    }

    //结算页支付方式和配送方式列表
    public function payDelivery()
    {

        $postdata = input::get();
        $pagedata['filter'] = $postdata;
        $pagedata['ifOpenOffline'] = app::get('ectools')->getConf('ectools.payment.offline.open');
        $pagedata['ifOpenZiti'] =app::get('syslogistics')->getConf('syslogistics.ziti.open');

        return $this->page('topwap/cart/checkout/pay_delivery.html',$pagedata);
    }

    /**
     * @brief 获取上门自取的地址列表
     *
     * @return html
     */
    public function getZitiList()
    {
        $postData = input::get();
        $params['user_id'] = userAuth::id();
        $params['addr_id'] = $postData['addr_id'];
        $params['fields'] = "area";
        $addrInfo= app::get('topwap')->rpcCall('user.address.info',$params);
        $area = explode(':',$addrInfo['area']);
        $area = implode(',',explode('/',$area[1]));
        $pagedata['data'] = app::get('topwap')->rpcCall('logistics.ziti.list',array('area_id'=>$area));
        $pagedata['ziti_id'] = $postData['ziti_id'];
        return  $this->page('topwap/cart/checkout/ziti_list.html', $pagedata);
    }

    public function getCouponList()
    {
        $shopId = intval(input::get('shop_id'));
        $pagedata['couponlist'] = $this->__getCoupons($shopId);
        $pagedata['shop_id'] = $shopId;
        return $this->page('topwap/cart/checkout/coupon_list.html',$pagedata);
    }
}
