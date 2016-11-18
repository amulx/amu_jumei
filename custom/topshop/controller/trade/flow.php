<?php
class topshop_ctl_trade_flow extends topshop_controller{

    /**
     * 发货订单处理
     * @params null
     * @return null
     */
    public function dodelivery()
    {
        $sdf = input::get();

        //当订单为自提订单并且没有物流配送，可以填写字体备注
        if( isset($sdf['isZiti']) && $sdf['isZiti'] == "true" )
        {
            if(!trim($sdf['logi_no']) && !trim($sdf['ziti_memo']))
            {
                return $this->splash('error',null, '订单为自提订单，运单号和备注至少选择一项必填', true);
            }
            if( mb_strlen(trim($sdf['ziti_memo']),'utf8') > 200)
            {
                return $this->splash('error',null, '自提备注过长', true);
            }
            $sdf['ziti_memo'] = trim($sdf['ziti_memo']) ? trim($sdf['ziti_memo']) : "";
        }
        else
        {
            unset($sdf['isZiti'],$sdf['ziti_memo']);
            if(empty($sdf['logi_no']))
            {
                return $this->splash('error',null, '发货单号不能为空', true);
            }
        }

        if(isset($sdf['logi_no']) && trim($sdf['logi_no']) && strlen(trim($sdf['logi_no'])) < 6)
        {
            return $this->splash('error',null, '运单号过短，请认真核对后填写(大于6)正确的编号', true);
        }

        if(strlen(trim($sdf['logi_no'])) > 20 )
        {
            return $this->splash('error',null, '运单号过长，请认真核对后填写(小于20)正确的编号', true);
        }
        $sdf['logi_no'] = trim($sdf['logi_no']) ? trim($sdf['logi_no']) : "0";
        $sdf['seller_id'] = $this->sellerId;
        $sdf['shop_id'] = $this->shopId;

        try
        {
            app::get('topshop')->rpcCall('trade.delivery',$sdf);
        }
        catch (Exception $e)
        {
            return $this->splash('error',null, $e->getMessage(), true);
        }
        $this->sellerlog('订单发货。订单号是:'.$sdf['tid']);
        $url = url::action('topshop_ctl_trade_list@index');
        return $this->splash('success',$url, '发货成功', true);
    }

    /**
     * 产生订单发货页面
     * @params string order id
     * @return string html
     */

    public function goDelivery()
    {
        //面包屑
        $this->runtimePath = array(
            ['url'=> url::action('topshop_ctl_index@index'),'title' => app::get('topshop')->_('首页')],
            ['url'=> url::action('topshop_ctl_trade_list@index'),'title' => app::get('topshop')->_('订单列表')],
            ['title' => app::get('topshop')->_('订单发货')],
        );
        $this->contentHeaderTitle = app::get('topshop')->_('订单发货');

        $tid = input::get('tid');
        if(!$tid)
        {
            header('Content-Type:application/json; charset=utf-8');
            echo '{error:"'.app::get('topshop')->_("订单号传递出错.").'",_:null}';exit;
        }
        $params['tid'] = $tid;
        $params['fields'] = "orders.spec_nature_info,tid,receiver_name,receiver_mobile,receiver_state,receiver_district,receiver_address,need_invoice,ziti_addr,invoice_type,invoice_name,invoice_main,orders.price,orders.num,orders.title,orders.item_id,orders.pic_path,total_fee,discount_fee,buyer_rate,adjust_fee,orders.total_fee,orders.adjust_fee,created_time,pay_time,consign_time,end_time,shop_id,need_invoice,invoice_name,invoice_type,invoice_main,orders.bn,cancel_reason,orders.refund_fee,orders.aftersales_status,orders.dlytmpl_id,shipping_type";
        $tradeInfo = app::get('topshop')->rpcCall('trade.get',$params,'seller');
          //获取默认图片信息
        $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');

        $pagedata['tradeInfo'] = $tradeInfo;

        //获取用户的物流模板
        if($tradeInfo['shipping_type'] == 'ziti')
        {
            $pagedata['ziti'] = 'true';
        }

        $dlycorp = app::get('topshop')->rpcCall('shop.dlycorp.getlist',['shop_id'=>$this->shopId]);
        $pagedata['dlycorp'] = $dlycorp['list'];

        return $this->page('topshop/trade/godelivery.html', $pagedata);
    }
}

