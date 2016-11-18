<?php
class systrade_api_trade_create{
    public $apiDescription = "订单创建";
    public function getParams()
    {
        $return['params'] = array(
            'user_id' => ['type'=>'int', 'valid'=>'required', 'default'=>'', 'example'=>'','description'=>'会员id'],
            'user_name' => ['type'=>'int', 'valid'=>'required', 'default'=>'', 'example'=>'','description'=>'会员用户名'],
            'addr_id' => ['type'=>'int', 'valid'=>'required', 'default'=>'', 'example'=>'','description'=>'收货地址'],
            'payment_type' => ['type'=>'string', 'valid'=>'required', 'default'=>'online', 'example'=>'online','description'=>'支付途径,暂时只支持线上支付'],
            'source_from' => ['type'=>'int', 'valid'=>'required', 'default'=>'pc', 'example'=>'pc','description'=>'订单来源'],
            'shipping_type' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'2:express;4:ziti;20:express','description'=>'配送方式，规则为店铺id:配送类型;'],
            'ziti' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'2:3;4:1;20:5','description'=>'自提地址，规则为店铺id:自提点id;'],
            'mode' => ['type'=>'int', 'valid'=>'required', 'default'=>'fastbuy', 'example'=>'cart','description'=>'购买方式,立即购买或加入购物车购买'],
            'need_invoice' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'是否要开发票'],
            'invoice_type' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'发票类型'],
            'invoice_content' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'发票内容'],
            'invoice_title' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'发票抬头'],
            'use_points' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'使用的积分值'],
        );
        return $return;
    }
    public function createTrade($params)
    {
        $data['user_id'] = $params['user_id'];
        $data['use_points'] = $params['use_points'];
        $data['user_name'] = $params['user_name'];
        $data['payment_type'] = $params['payment_type'];
        $data['mode'] = $params['mode'];
        $data['source_from'] = $params['source_from'];
        $data['trade_memo'] = $params['mark'];
        $data['invoice'] = array(
            'need_invoice' => $params['need_invoice'],
            'invoice_type' => $params['invoice_type'],
            'invoice_content' => $params['invoice_content'],
            'invoice_title' => $params['invoice_title'],
            'invoice_vat' => $params['invoice_vat'],
        );
        if($params['addr_id'])
        {
            $addr = app::get('systrade')->rpcCall('user.address.info',array('addr_id'=>$params['addr_id'],'user_id'=>$params['user_id']));
            list($regions,$region_id) = explode(':',$addr['area']);
            list($state,$city,$district) = explode('/',$regions);
            $data['delivery'] = array(
                'buyer_area' => $region_id,
                'addr_id' => $params['addr_id'],
                'receiver_state' => $state,
                'receiver_city' => $city,
                'receiver_district' => $district,
                'receiver_address' => $addr['addr'],
                'receiver_zip' => $addr['zip'],
                'receiver_name' => $addr['name'],
                'receiver_mobile' => $addr['mobile'],
                'receiver_phone' => $addr['tel'],
            );
            $data['region_id'] = str_replace('/', ',', $region_id);
        }
        else
        {
            throw new Exception('收货地址信息有误');
        }

        if($params['shipping_type'])
        {
            $arr1 = explode(';',$params['shipping_type']);
            $arr1 = array_filter($arr1);
            foreach($arr1 as $value)
            {
                $arr2 = explode(':',$value);
                list($key,$val) = $arr2;
                $data['shipping'][$key]['shipping_type'] = $val;
            }
        }
        else
        {
            throw new Exception('配送方式信息有误');
        }

        if($params['ziti'])
        {
            $arr1 = explode(';',$params['ziti']);
            $arr1 = array_filter($arr1);
            foreach($arr1 as $value)
            {
                $arr2 = explode(':',$value);
                list($key,$val) = $arr2;
                $data['ziti'][$key]['ziti_addr'] = $val;
            }
        }

        //获取购物车数据
        $cartFilter = array(
            'mode' => $params['mode'],
            'needInvalid' => false,
            'platform' => $params['source_from'],
            'user_id' => $params['user_id'],
        );
        $cartInfo = app::get('systrade')->rpcCall('trade.cart.getCartInfo', $cartFilter, 'buyer');
        //判断coupon_code是否属于平台优惠券   2016.9.7 czh
        foreach ($params['coupon_use'] as $value) {
            if(strpos($value['coupon_code'],'YS') !== false){
                    $regcouponData = app::get('sysuser')->model('user_regcoupon')->getRow('*', array('coupon_code' => $value['coupon_code']));
                    $condition_value = $regcouponData['condition_value'];
            }
        }
        //对商品进行打折处理   2016.9.7 czh
        foreach ($cartInfo['resultCartData'] as &$tval) {
            if (!empty($condition_value)) {
                $tval['cartCount']['total_discount'] = $tval['cartCount']['total_fee'] * (100.0 - $condition_value)/100;
            }
        }

        if(!$cartInfo)
        {
            throw new Exception('购物车信息有误');
        }

        $obj_order_create = kernel::single("systrade_data_trade_create");
        $tids = $obj_order_create->generate($data, $cartInfo);
        //如果创建订单成功后把优惠卷改成已使用。使用在哪个订单号  2016.9.7 czh
        if (!empty($condition_value)) {
            if($tids){
                foreach ($tids as $value) {
                    $tid.= $value.',';
                }
                $tid = rtrim($tid, ",");
                $regcouponData['tid'] = $tid;
                $regcouponData['is_valid'] = 0;
                logger::info('[systrade_api_trade_create.createTrade] : regcouponData-----------'.json_encode($regcouponData));
                app::get('sysuser')->model('user_regcoupon')->save($regcouponData);
            }
        }

        return $tids;
    }
}

