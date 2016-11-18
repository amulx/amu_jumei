<?php

/**
 * trade.php 会员订单中心
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_member_trade extends topwap_ctl_member {
    public $tradeStatus = array(
            '1' => 'WAIT_BUYER_PAY',
            '2' => 'WAIT_SELLER_SEND_GOODS',
            '3' => 'WAIT_BUYER_CONFIRM_GOODS',
            '4' => 'TRADE_FINISHED'
    );
    public $limit = 6;

    // 会员中心全部订单
    public function tradeList()
    {
        theme::setTitle('我的订单');
        $postdata = input::get();
        $pagedata = $this->__getTrade($postdata);
        return $this->page('topwap/member/trade/index.html', $pagedata);
    }


    // 订单详情
    public function detail()
    {
        theme::setTitle('订单详情');
        $this->setLayoutFlag('order_detail');
        $params['tid'] = input::get('tid');
        $params['user_id'] = userAuth::id();
        $params['fields'] = "tid,status,shop_id,user_id,shipping_type,payment,need_invoice,invoice_name,invoice_type,invoice_main,invoice_vat_main,points_fee,post_fee,cancel_status,receiver_state,receiver_city,receiver_district,ziti_addr,ziti_memo,receiver_address,trade_memo,receiver_name,receiver_mobile,created_time,orders.oid,orders.price,orders.num,orders.title,orders.aftersales_status,orders.complaints_status,orders.item_id,orders.pic_path,total_fee,discount_fee,buyer_rate,adjust_fee,orders.spec_nature_info,activity,pay_type,cancel_reason,orders.spec_nature_info";
        $trade = app::get('topwap')->rpcCall('trade.get', $params, 'buyer');
        if ($trade ['shipping_type'] == 'ziti')
        {
            $pagedata ['ziti'] = "true";
        }

        $trade ['is_buyer_rate'] = false;
        foreach ($trade ['orders'] as $orderListData)
        {
            if ($trade ['buyer_rate'] == '0' && $trade ['status'] == 'TRADE_FINISHED')
            {
                $trade ['is_buyer_rate'] = true;
                break;
            }
        }

        if ($trade ['shipping_type'] == 'ziti')
        {
            $pagedata ['ziti'] = "true";
        }
        // 订单配送方式
        $shippingName = array(
                'express' => '快递配送',
                'ziti' => '自提',
                'post' => '平邮',
                'ems' => 'EMS',
                'virtual' => '虚拟发货'
        );
        $trade ['shipping_type_name'] = $shippingName [$trade ['shipping_type']];

        $pagedata ['trade'] = $trade;
        // 获取发货信息
        $pagedata ['logi'] = app::get('topwap')->rpcCall('delivery.get', array(
                'tid' => $params ['tid']
        ));
        $pagedata ['title'] = "订单详情"; // 标题
        $pagedata ['point_rate'] = app::get('topwap')->rpcCall('point.setting.get', [
                'field' => 'point.deduction.rate'
        ]);
        $pagedata ['tracking'] = app::get('syslogistics')->getConf('syslogistics.order.tracking');
        $pagedata ['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');

        return $this->page('topwap/member/trade/detail.html', $pagedata);
    }

    public function confirmReceipt()
    {
        $params['tid'] = input::get('tid');
        $params['user_id'] = userAuth::id();
        try
        {
            app::get('topwap')->rpcCall('trade.confirm',$params,'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
        $url = url::action('topwap_ctl_member_trade@tradeList');
        $msg = app::get('topm')->_('订单确认收货完成');
        return $this->splash('success',$url,$msg,true);
    }

    public function cancel()
    {
        theme::setTitle('取消订单');
        $validator = validator::make([
                input::get('tid')
        ], [
                'numeric'
        ]);
        if ($validator->fails())
        {return $this->splash('error', null, '订单格式错误！', true);}

        $pagedata ['tid'] = $params ['tid'] = input::get('tid');
        $params ['user_id'] = userAuth::id();
        $params ['fields'] = "status,post_fee,payment,points_fee,pay_type";
        $pagedata ['trade'] = app::get('topm')->rpcCall('trade.get', $params);
        $pagedata ['reason'] = config::get('tradeCancelReason');
        $pagedata ['title'] = "取消订单"; // 标题
        return $this->page('topwap/member/trade/cancel/cancel.html', $pagedata);
    }

    public function canceledTradeList()
    {
        theme::setTitle('取消订单列表');
        $postdata = input::get();
        $pagedata=$this->__getCancledTradeList($postdata);
        $pagedata ['title'] = "取消订单列表"; // 标题

        return $this->page('topwap/member/trade/cancel/canceled.html', $pagedata);
    }

    // 订单取消详情
    public function canceledTradeDetail()
    {
        theme::setTitle('取消订单详情');
        $pagedata['title'] = "取消订单详情";  //标题
        $cancelId = input::get('cancel_id');
        $data = app::get('topwap')->rpcCall('trade.cancel.get',['user_id'=>userAuth::id(),'cancel_id'=>$cancelId]);
        $pagedata['data'] = $data;
        return $this->page('topwap/member/trade/cancel/detail.html',$pagedata);
    }

    // 订单详情中查看取消详情
    public function gotoCanceledTradeDetail()
    {
        $tid = input::get('tid');
        $params['tid'] = $tid;
        $params['user_id'] = userAuth::id();
        $params['fields'] = "tid,cancel.cancel_id";
        $trade = app::get('topwap')->rpcCall('trade.get', $params, 'buyer');
        if($trade['cancelInfo']['cancel_id'])
        {
            redirect::action('topwap_ctl_member_trade@canceledTradeDetail',array('cancel_id'=>$trade['cancelInfo']['cancel_id']))->send();exit;
        }
        redirect::action('topwap_ctl_member_trade@canceledTradeList')->send();exit;
    }

    // 处理取消订单
    public function cancelBuyer()
    {
	    $this->setLayoutFlag('cart');
        $reasonSetting = config::get('tradeCancelReason');
        $reasonPost = input::get('cancel_reason');
		logger::info('[topwap_ctl_member_trade.cancelBuyer] : $reasonPost=' .json_encode($reasonPost));

        if(!$reasonPost)
        {
            $msg = app::get('topwap')->_('取消原因必填');
            return $this->splash('error',null,$msg);
        }

        if($reasonPost == "other")
        {
            $cancelReason = input::get('other_reason');
            $validator = validator::make([trim($cancelReason)],['required|max:50'],['取消原因必须填写!|取消原因最多填写50个字']);
            if ($validator->fails())
            {
                $messages = $validator->messagesInfo();
                foreach( $messages as $error )
                {
                    return $this->splash('error',null,$error[0]);
                }
            }
        }
        else
        {
            $cancelReason = $reasonSetting['user'][$reasonPost];
        }

        $params['tid'] = input::get('tid');
        $params['user_id'] = userAuth::id();
        $params['cancel_reason'] = $cancelReason;
        try
        {
	        logger::info('[topwap_ctl_member_trade.cancelBuyer] : $params=' .json_encode($params));
            app::get('topwap')->rpcCall('trade.cancel.create',$params);
        }
        catch(Exception $e)
        {
            //$pagedata['msg'] = $e->getMessage();
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
            //$pagedata['cancelerror'] = true;
        }
        
        /**
         * add @ by cg 2016/9/13  将申请请求传送到OMS
         */
        kernel::single('foroms_output_trade')->cancelRequest($params);
        $url = url::action('topwap_ctl_member_trade@tradeList');
        $msg = app::get('topwap')->_('订单取消成功');
        //return $this->page('topm/member/trade/status/result.html', $pagedata);
        return $this->splash('success', $url, $msg, true);
    }

    // ajax方式获取订单列表
    public function ajaxTradeList()
    {
        try
        {
            $postdata = input::get();
            $pagedata = $this->__getTrade($postdata);

            if($pagedata ['trades'])
            {
                $data ['html'] = view::make('topwap/member/trade/list.html', $pagedata)->render();
            }
            else
            {
                $data ['html'] = view::make('topwap/empty/trade.html', $pagedata)->render();
            }

            $data ['pages'] = $pagedata ['pagers'];
            $data ['s'] = $pagedata ['status'];
            $data ['success'] = true;
        } catch ( Exception $e )
        {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }
        //var_dump($data['pages']['total']);
        return response::json($data);
        //return view::make('topwap/member/trade/list.html',$pagedata);
    }


    public function ajaxCanceledTradeList()
    {
        try {
            $postdata = input::get();
            $pagedata=$this->__getCancledTradeList($postdata);
            $data ['html'] = view::make('topwap/member/trade/cancel/list.html', $pagedata)->render();
            $data ['pages'] = $pagedata ['pagers'];
            $data ['s'] = $pagedata ['status'];
            $data ['success'] = true;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }

        return response::json($data);
        exit();
    }

    /**
     * 分页处理
     * @param int $current 当前页
     *
     * @return $pagers
     */
    private function __pages($current,$filter,$count)
    {
        //处理翻页数据
        $current = ($current && $current <= 100 ) ? $current : 1;

        if( $count > 0 ) $totalPage = ceil($count/$this->limit);
        $pagers = array(
            'link'=>'',
            'current'=>intval($current),
            'total'=>intval($totalPage),
        );
        return $pagers;
    }

    // 订单物流
    public function logistics()
    {
        theme::setTitle('物流详情');
        // 订单id
        $params ['tid'] = input::get('tid');
        $params ['user_id'] = userAuth::id();
        $params ['fields'] = "tid,status,shop_id,user_id,shipping_type,receiver_state,receiver_city,receiver_district,ziti_addr,ziti_memo,receiver_address,receiver_name,receiver_mobile,created_time";
        $trade = app::get('topwap')->rpcCall('trade.get', $params, 'buyer');
        $pagedata ['trade'] = $trade;
        // 获取发货信息
        $pagedata ['logi'] = app::get('topwap')->rpcCall('delivery.get', array(
                'tid' => $params ['tid']
        ));

        $tracking = app::get('syslogistics')->getConf('syslogistics.order.tracking');
        $pagedata['trackFlag'] = $tracking;
        // 获取物流详情
        if($pagedata['logi'] && $pagedata['logi']['corp_code']!='other')
        {
            if($tracking && $tracking =='true')
            {
                $logic_params['logi_no'] = $pagedata['logi']['logi_no'];
                $logic_params['corp_code'] = $pagedata['logi']['corp_code'];
                $log_info = app::get('topwap')->rpcCall('logistics.tracking.get.hqepay',$logic_params);
                krsort($log_info['tracker']);

                $pagedata['track'] = $log_info;
            }
        }

        $pagedata['title'] = app::get('topwap')->_('物流详情');
        return $this->page('topwap/member/trade/logistics.html', $pagedata);
    }

    // 获取取消的订单
    private function __getCancledTradeList($postdata)
    {

        if (isset($postdata['s']) && $postdata['s'])
        {
            if ($postdata['s'] == 4)
            {
                $status = 'TRADE_FINISHED';
                $params['buyer_rate'] = 0;
            }
            else
            {
                $status = $this->tradeStatus[$postdata ['s']];
            }
        }
        else
        {
            $postdata['s'] = 0;
        }

        if( $postdata['tid'] )
        {
            $params['tid'] = $postdata['tid'];
        }

        $params['status'] = $status;
        $params['user_id'] = userAuth::id();
        $params['page_no'] = intval($postdata ['pages']) ? intval($postdata ['pages']) : 1;
        $params['page_size'] =intval($this->limit);
        $params['fields'] = "cancel_id,shop_id,payed_fee,refunds_status,tid";
        $params['order_by'] = 'created_time desc';

        $data = app::get('topwap')->rpcCall('trade.cancel.list.get', $params);
        $count = $data ['total'];
        $pagedata ['list'] = $data ['list'];

        $pagedata ['status'] = $postdata ['s']; // 状态
        $pagedata ['count'] = $count;
        $pagedata ['pages'] = $params ['page_no'];
        $pagedata['pagers'] = $this->__pages($postdata['pages'],$postdata,$count);

        $pagedata ['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');

        return $pagedata;
    }

    // 获取订单列表
    private function __getTrade($postdata)
    {

        if (isset($this->tradeStatus [$postdata ['s']]))
        {
            if ($postdata ['s'] == 4)
            {
                $status = 'TRADE_FINISHED';
                $params ['buyer_rate'] = 0;
            }
            else
            {
                $status = $this->tradeStatus [$postdata ['s']];
            }
        }
        else
        {
            $postdata ['s'] = 0;
        }

        $params ['status'] = $status;
        $params ['user_id'] = userAuth::id();
        $params ['page_no'] = intval($postdata ['pages']) ? intval($postdata ['pages']) : 1;
        $params ['page_size'] = intval($this->limit);
        $params ['order_by'] = 'created_time desc';
        $params ['fields'] = 'tid,shop_id,user_id,status,payment,points_fee,total_fee,cancel_status,itemnum,post_fee,payed_fee,receiver_name,created_time,receiver_mobile,discount_fee,need_invoice,adjust_fee,order.title,order.price,order.num,order.pic_path,order.tid,order.oid,order.aftersales_status,buyer_rate,order.complaints_status,order.item_id,order.shop_id,order.status,activity,pay_type,order.spec_nature_info';

        $tradelist = app::get('topwap')->rpcCall('trade.get.list', $params);

        $count = $tradelist ['count'];
        $tradelist = $tradelist ['list'];

        foreach ($tradelist as $key => $row)
        {
            $tradelist [$key] ['is_buyer_rate'] = false;

            foreach ($row ['order'] as $orderListData)
            {
                if ($row ['buyer_rate'] == '0' && $row ['status'] == 'TRADE_FINISHED')
                {
                    $tradelist [$key] ['is_buyer_rate'] = true;
                    break;
                }
            }

            unset($tradelist [$key] ['order']);
            $tradelist [$key] ['order'] [0] = $row ['order'];

            if (! $tradelist [$key] ['is_buyer_rate'] && $postdata ['s'] == 4)
            {
                unset($tradelist [$key]);
            }
        }

        $pagedata ['trades'] = $tradelist;
        $pagedata ['count'] = $count;
        $pagedata ['status'] = $postdata ['s']; // 状态
        $pagedata['pagers'] = $this->__pages($postdata['pages'],$postdata,$count);
        $pagedata ['defaultImageId'] = kernel::single('image_data_image')->getImageSetting('item');
        return $pagedata;
    }

}

