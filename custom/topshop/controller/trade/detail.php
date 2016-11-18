<?php
class topshop_ctl_trade_detail extends topshop_controller{
    public function index()
    {
        $tids = input::get('tid');
        //面包屑
        $this->runtimePath = array(
            ['url'=> url::action('topshop_ctl_index@index'),'title' => app::get('topshop')->_('首页')],
            ['url'=> url::action('topshop_ctl_trade_list@index'),'title' => app::get('topshop')->_('订单列表')],
            ['title' => app::get('topshop')->_('订单详情')],
        );

        $params['tid'] = $tids;
        $params['fields'] = "shipping_type,orders.spec_nature_info,user_id,tid,status,payment,points_fee,ziti_addr,ziti_memo,post_fee,pay_type,payed_fee,receiver_state,receiver_city,receiver_district,receiver_address,receiver_zip,trade_memo,shop_memo,receiver_name,receiver_mobile,orders.price,orders.num,orders.title,orders.item_id,orders.pic_path,total_fee,discount_fee,buyer_rate,adjust_fee,orders.total_fee,orders.adjust_fee,created_time,pay_time,consign_time,end_time,shop_id,need_invoice,invoice_name,invoice_type,invoice_main,invoice_vat_main,orders.bn,cancel_reason,orders.refund_fee,orders.aftersales_status";
        $tradeInfo = app::get('topshop')->rpcCall('trade.get',$params,'seller');
        if($tradeInfo['shipping_type'] == 'ziti')
        {
            $pagedata['ziti'] = "true";
        }

        if(!$tradeInfo)
        {
            redirect::action('topshop_ctl_trade_list@index')->send();exit;
        }
        $userInfo = app::get('topshop')->rpcCall('user.get.account.name', ['user_id' => $tradeInfo['user_id']], 'seller');
        $tradeInfo['login_account'] = $userInfo[$tradeInfo['user_id']];

        $sous = app::get('sysitem')->model('item')->getRow('source,source_link',array('item_id'=>$tradeInfo['orders'][0]['item_id']));
        $tradeInfo['orders'][0]['source'] = $sous['source'];
        $tradeInfo['orders'][0]['source_link'] = $sous['source_link'];

        //获取默认图片信息
        $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');

        $pagedata['trade']= $tradeInfo;
        $pagedata['logi'] = app::get('topshop')->rpcCall('delivery.get',array('tid'=>$params['tid']));
        $pagedata['tracking'] = app::get('syslogistics')->getConf('syslogistics.order.tracking');
        $this->contentHeaderTitle = app::get('topshop')->_('订单详情');
        return $this->page('topshop/trade/detail.html', $pagedata);
    }

    public function ajaxGetTrack()
    {
        $postData = input::get();
        $pagedata['track'] = app::get('topshop')->rpcCall('logistics.tracking.get.hqepay',$postData);
        return view::make('topshop/trade/trade_logistics.html',$pagedata);
    }

    public function setTradeMemo()
    {
        $params['tid'] = input::get('tid');
        $params['shop_id'] = $this->shopId;
        try
        {
            $params['shop_memo'] = input::get('shop_memo');
            $result = app::get('topshop')->rpcCall('trade.add.memo',$params);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error','',$msg,true);
        }
        $this->sellerlog('编辑订单备注。订单号是'.$params['tid']);
        $msg = app::get('topshop')->_('备注添加成功');
        $url = url::action('topshop_ctl_trade_detail@index',array('tid'=>$params['tid']));
        return $this->splash('success',$url,$msg,true);
    }
}
