<?php
class topwap_ctl_trade extends topwap_controller{
	var $noCache = true;

    public function __construct(&$app)
    {
        parent::__construct();
        theme::setNoindex();
        theme::setNoarchive();
        theme::setNofolow();
        theme::prependHeaders('<meta name="robots" content="noindex,noarchive,nofollow" />\n');
        $this->title=app::get('topwap')->_('订单中心');
        // 检测是否登录
        if( !userAuth::check() )
        {
            $openid = $_SESSION['user_openid'];
            if (!empty($openid)) {
                    $row = app::get('sysuser')->model('user_distri')->getRow('user_id',['openid' => $openid]);
            }

            if (!empty($row['user_id'])) {
                userAuth::login($row['user_id']);
            }else{
                    if( request::ajax() )
                    {
                        $url = url::action('topwap_ctl_passport@goLogin');
                        return $this->splash('error', $url, app::get('topwap')->_('请登录'), true);
                    }
                    redirect::action('topwap_ctl_passport@goLogin')->send();exit;
            }
        }
    }


	public function create()
	{
		$postData = input::get();
        $postData['mode'] = $postData['mode'] ? $postData['mode'] :'cart';

        $cartFilter['mode'] = $postData['mode'];
        $cartFilter['needInvalid'] = false;
        $cartFilter['platform'] = 'wap';
        $md5CartFilter = array('user_id'=>userAuth::id(), 'platform'=>'wap', 'mode'=>$cartFilter['mode'], 'checked'=>1);
        $cartInfo = app::get('topwap')->rpcCall('trade.cart.getBasicCartInfo', $md5CartFilter, 'buyer');
        // 校验购物车是否为空
        if (!$cartInfo)
        {
            $msg = app::get('topwap')->_("购物车信息为空或者未选择商品");
            return $this->splash('false', '', $msg, true);
        }
        // 校验购物车是否发生变化
        $md5CartInfo = md5(serialize(utils::array_ksort_recursive($cartInfo, SORT_STRING)));
        if( $postData['md5_cart_info'] != $md5CartInfo )
        {
            $msg = app::get('topwap')->_("购物车数据发生变化，请刷新后确认提交");
            return $this->splash('false', '', $msg, true);
        }
        unset($postData['md5_cart_info']);

        if(!$postData['addr_id'])
        {
            $msg .= app::get('topwap')->_("请先确认收货地址");
            return $this->splash('success', '', $msg, true);
        }
        else
        {
            $addr = app::get('topwap')->rpcCall('user.address.info',array('addr_id'=>$postData['addr_id'],'user_id'=>userAuth::id()));
            list($regions,$region_id) = explode(':',$addr['area']);
            list($state,$city,$district) = explode('/',$regions);

            if (!$state )
            {
                $msg .= app::get('topwap')->_("收货地区不能为空！")."<br />";
            }

            if (!$addr['addr'])
            {
                $msg .= app::get('topwap')->_("收货地址不能为空！")."<br />";
            }

            if (!$addr['name'])
            {
                $msg .= app::get('topwap')->_("收货人姓名不能为空！")."<br />";
            }

            if (!$addr['mobile'] && !$addr['phone'])
            {
                $msg .= app::get('topwap')->_("手机或电话必填其一！")."<br />";
            }

            if (strpos($msg, '<br />') !== false)
            {
                $msg = substr($msg, 0, strlen($msg) - 6);
            }
            if($msg)
            {
                return $this->splash('false', '', $msg, true);
            }
         }
        if(!$postData['payment_type'])
        {
            $msg = app::get('topwap')->_("请先确认支付类型");
            return $this->splash('success', '', $msg, true);
        }
        else
        {
            $postData['payment_type'] = $postData['payment_type'] ? $postData['payment_type'] : 'online';
        }

        //发票信息
        if($postData['invoice'])
        {
            foreach($postData['invoice'] as $key=>$val)
            {
                if($key == "invoice_type" && $val == "notuse")
                {
                    $postData['need_invoice'] == 0;
                }
                else
                {
                    $postData['need_invoice'] == 1;
                }
                $postData[$key] = $val;
            }
            unset($postData['invoice']);
        }
        if($postData['invoice_content'])
        {
            $validator = validator::make(
                [$postData['invoice_content']],
                ['max:100'],
                ['发票内容最大为100个字符!']
            );
            if ($validator->fails())
            {
                $messages = $validator->messagesInfo();
                foreach( $messages as $error )
                {
                    return $this->splash('error', '', $error[0], true);
                }
            }
        }

        //店铺配送方式处理
        $shipping = "";
        if( $postData['shipping'])
        {
            foreach($postData['shipping'] as $k=>$v)
            {
                //验证店铺类型
                $shopdata = app::get('topwap')->rpcCall('shop.get.detail',array('shop_id'=>$k,'fields'=>'shop_type'))['shop'];
                $ifOpenZiti = app::get('syslogistics')->getConf('syslogistics.ziti.open');
                $ifOpenOffline = app::get('ectools')->getConf('ectools.payment.offline.open');

                //验证非自营时，支付方式“货到付款”问题
                if(($postData['payment_type'] == "offline" ) )
                {
                    if(($shopdata['shop_type'] != "self") || ($shopdata['shop_type'] == "self" && $ifOpenOffline == "false"))
                    {
                        $msg = app::get('topwap')->_("您的支付方式选择有误");
                        return $this->splash('error', '', $msg, true);
                    }
                }

                $shipping .= $k.":".$v['shipping_type'].";";
                if($v['shipping_type'] == 'ziti')
                {
                    //验证是否有自提资格
                    if( $shopdata['shop_type'] != "self" || $ifOpenZiti == "false")
                    {
                        $msg = app::get('topwap')->_("您的配送方式选择有误");
                        return $this->splash('error', '', $msg, true);
                    }

                    if(!$postData['ziti'][$k]['ziti_addr'])
                    {
                        $msg = app::get('topwap')->_("您已选择自提，请选择自提地址");
                        return $this->splash('error', '', $msg, true);
                    }
                    $zitiAddr = app::get('topwap')->rpcCall('logistics.ziti.get',array('id'=>$postData['ziti'][$k]['ziti_addr']));
                    $ziti .= $k.":".$zitiAddr['area'].$zitiAddr['addr'].";";
                }

                if( !$v['shipping_type'] )
                {
                    $msg = app::get('topwap')->_("请选择店铺配送方式");
                    return $this->splash('error', '', $msg, true);
                }
            }
            unset($postData['shipping']);
            unset($postData['ziti']);
        }
        $postData['shipping_type'] = $shipping;
        if($ziti)
        {
            $postData['ziti'] = $ziti;
        }
        $postData['source_from'] = 'wap';

        $obj_filter = kernel::single('topwap_site_filter');
        $postData = $obj_filter->check_input($postData);

        $postData['user_id'] = userAuth::id();
        $postData['user_name'] = userAuth::getLoginName();

        try
        {
           $createFlag = app::get('topwap')->rpcCall('trade.create',$postData,'buyer');
           if( $createFlag )
           {
               $countData = app::get('topc')->rpcCall('trade.cart.getCount', ['user_id' => userAuth::id()], 'buyer');
               userAuth::syncCookieWithCartNumber($countData['number']);
               userAuth::syncCookieWithCartVariety($countData['variety']);
           }
        }
        catch(Exception $e)
        {
            return $this->splash('error',null,$e->getMessage(),true);
        }

        try{
            if($postData['payment_type'] == "online")
            {
                $params['tid'] = $createFlag;
                $params['user_id'] = userAuth::id();
                $params['user_name'] = userAuth::getLoginName();
                $paymentId = kernel::single('topwap_payment')->getPaymentId($params);
                $redirect_url = url::action('topwap_ctl_paycenter@index',array('payment_id'=>$paymentId,'merge'=>true));
            }
            else
            {
                $redirect_url = url::action('topwap_ctl_paycenter@index',array('tid' => implode(',',$createFlag)));
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            $url = url::action('topwap_ctl_member_trade@tradeList');
            return $this->splash('error',$url,$msg,true);
        }
        return $this->splash('success',$redirect_url,'订单创建成功',true);
    }
	}

