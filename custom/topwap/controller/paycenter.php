<?php

/**
 * paycenter.php 支付
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_paycenter extends topwap_controller {

    protected $payment_icon = array(
            'wapupacp' => 'bbc-icon-unipay pay-style-unipay',
            'deposit' => 'bbc-icon-qianbao pay-style-qianbao',
            'malipay' => 'bbc-icon-zhifubao pay-style-zhifubao',
            'wxpayjsapi' => 'bbc-icon-weixin pay-style-weixin'
    );
    public function __construct($app)
    {
        parent::__construct($app);
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
    
    public function index($filter=null)
    {
        $this->setLayoutFlag('order_detail');
        if(!$filter)
        {
            $filter = input::get();
        }
        
        // 线下支付
        if(isset($filter['tid']) && $filter['tid'])
        {
            $pagedata['payment_type'] = "offline";
            $ordersMoney = app::get('topwap')->rpcCall('trade.money.get',array('tid'=>$filter['tid']),'buyer');
        
            if($ordersMoney)
            {
                foreach($ordersMoney as $key=>$val)
                {
                    $newOrders[$val['tid']] = $val['payment'];
                    $newMoney += $val['payment'];
                }
                $paymentBill['money'] = $newMoney;
                $paymentBill['cur_money'] = $newMoney;
            }
            $pagedata['trades'] = $paymentBill;
            $pagedata['title'] = app::get('topwap')->_('订单状态');
            theme::setTitle('订单状态');
            return $this->page('topwap/payment/offline.html', $pagedata);
        }
        
        if($filter['newtrade'])
        {
            $newtrade = $filter['newtrade'];
            unset($filter['newtrade']);
        }
        
        //获取可用的支付方式列表
        $payType['platform'] = 'iswap';
        $payments = app::get('topwap')->rpcCall('payment.get.list',$payType,'buyer');
        
        // 微信支付
        foreach($payments as $paymentKey => $payment)
        {
        
            if(in_array($payment['app_id'], ['wxpayjsapi']))
            {
                if(!kernel::single('topwap_wechat_wechat')->from_weixin())
                {
                    unset($payments[$paymentKey]);
                    continue;
                }
        
                $payInfo = app::get('topwap')->rpcCall('payment.get.conf', ['app_id' => 'wxpayjsapi']);
                $wxAppId = $payInfo['setting']['appId'];
                $wxAppsecret = $payInfo['setting']['Appsecret'];
                if(!input::get('code'))
                {
                    $url = url::action('topwap_ctl_paycenter@index',$filter);
                    kernel::single('topwap_wechat_wechat')->get_code($wxAppId, $url);
                }
                else
                {
                    $code = input::get('code');
                    $openid = kernel::single('topwap_wechat_wechat')->get_openid_by_code($wxAppId, $wxAppsecret, $code);
                    if($openid == null)
                        $this->splash('failed', 'back',  app::get('topwap')->_('获取openid失败'));
                    $pagedata['openid'] = $openid;
                }
            }
            
        }
        
        $filter['fields'] = "*";
        $paymentBill = app::get('topwap')->rpcCall('payment.bill.get',$filter,'buyer');
        
        //检测订单中的金额是否和支付金额一致 及更新支付金额
        $trade = $paymentBill['trade'];
        $tids['tid'] = implode(',',array_keys($trade));
        $ordersMoney = app::get('topwap')->rpcCall('trade.money.get',$tids,'buyer');
        
        if($ordersMoney)
        {
            foreach($ordersMoney as $key=>$val)
            {
                $newOrders[$val['tid']] = $val['payment'];
                $newMoney += $val['payment'];
            }
        
            $result = array(
                    'trade_own_money' => json_encode($newOrders),
                    'money' => $newMoney,
                    'cur_money' => $newMoney,
                    'payment_id' => $filter['payment_id'],
            );
        
            if($newMoney != $paymentBill['cur_money'])
            {
                try{
                    app::get('topwap')->rpcCall('payment.money.update',$result);
                }
                catch(Exception $e)
                {
                    $msg = $e->getMessage();
                    $url = url::action('topwap_ctl_member_trade@tradeList');
                    return $this->splash('error',$url,$msg,true);
                }
                $trades['money'] = $newMoney;
                $trades['cur_money'] = $newMoney;
            }
        }
        
        $pagedata['tids'] = $tids['tid'];
        $pagedata['trades'] = $paymentBill;
        $pagedata['payments'] = $payments;
        $pagedata['newtrade'] = $newtrade;
        $pagedata['title'] = app::get('topwap')->_('订单支付');
        $pagedata['payment_icon'] = $this->payment_icon;
        $pagedata['hasDepositPassword'] = app::get('topwap')->rpcCall('user.deposit.password.has', ['user_id'=>userAuth::id()]);

        theme::setTitle('订单支付');
        if ($this->is_weixin()) {
            return $this->page('topwap/payment/index.html', $pagedata);
        } else {
            $pagedata['cur_money_a'] =  sprintf("%.2f",$newMoney);
            return $this->page('topwap/payment/app_index.html', $pagedata);
        }
        
    }
    
    // 预存款支付
    public function depositPay()
    {
        $filter = input::get();
        $filter['fields'] = "*";
        $paymentBill = app::get('topwap')->rpcCall('payment.bill.get',$filter,'buyer');
        //检测订单中的金额是否和支付金额一致 及更新支付金额
        $trade = $paymentBill['trade'];
        $tids['tid'] = implode(',',array_keys($trade));
        $ordersMoney = app::get('topwap')->rpcCall('trade.money.get',$tids,'buyer');
        
        if($ordersMoney)
        {
            foreach($ordersMoney as $key=>$val)
            {
                $newOrders[$val['tid']] = $val['payment'];
                $newMoney += $val['payment'];
            }
        
            $result = array(
                    'trade_own_money' => json_encode($newOrders),
                    'money' => $newMoney,
                    'cur_money' => $newMoney,
                    'payment_id' => $filter['payment_id'],
            );
        
            if($newMoney != $paymentBill['cur_money'])
            {
                try{
                    app::get('topwap')->rpcCall('payment.money.update',$result);
                }
                catch(Exception $e)
                {
                    $msg = $e->getMessage();
                    $url = url::action('topwap_ctl_member_trade@tradeList');
                    return $this->splash('error',$url,$msg,true);
                }
                $trades['money'] = $newMoney;
                $trades['cur_money'] = $newMoney;
            }
        }
        
        $pagedata['payment'] = $filter['payment'];
        $pagedata['tids'] = $tids['tid'];
        $pagedata['trades'] = $paymentBill;
        $pagedata['title'] = app::get('topwap')->_('预存款支付');
        theme::setTitle('预存款支付');

        return $this->page('topwap/payment/depositpay.html',$pagedata);
    }
    // 创建支付
    public function createPay()
    {

        $filter = input::get();
        $filter ['user_id'] = userAuth::id();
        $filter ['user_name'] = userAuth::getLoginName();
        $ifmerge = $filter['merge'];
        try
        {
            $paymentId = kernel::single('topwap_payment')->getPaymentId($filter);
        } catch ( Exception $e )
        {
            $msg = $e->getMessage();
            $url = url::action('topwap_ctl_member_trade@tradeList');
            
            return $this->splash('error', $url, $msg);
        }
        $url = url::action('topwap_ctl_paycenter@index', array(
                'payment_id' => $paymentId, 
                'merge' => $ifmerge 
        ));
        return $this->splash('success', $url, $msg, true);
    }
    
    // 开始支付
    public function dopayment()
    {
        $postdata = input::get();
        $payment = $postdata['payment'];
        $payment['deposit_password'] = $postdata['deposit_password'];

        if(!$payment['pay_app_id'])
        {
            throw new LogicException('请选择支付方式！');
        }
        $payment['user_id'] = userAuth::id();
        $payment['platform'] = "wap";
        $msg = '';
        try
        {
            if($payment['pay_app_id'] == 'deposit' && $postdata['deposit_password'] == '')
            {
                throw new LogicException('请输入预存款支付密码！');
            }

           app::get('topwap')->rpcCall('payment.trade.pay',$payment);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            $url = url::action('topwap_ctl_paycenter@index',array('payment_id'=>$payment['payment_id']));
            if($payment['pay_app_id'] == 'deposit')
            {
                $url = url::action('topwap_ctl_paycenter@depositPay',array('payment_id'=>$payment['payment_id']));
                // return $this->splash('error', $url, $msg, true);
            }
            echo '<meta charset="utf-8"><script>alert("'.$msg.'");location.href="'.$url.'";</script>';
            exit();
        }
        // kernel::single('foroms_output_trade')->payInfo(['payment_id'=>$payment['payment_id']]);
        $url = url::action('topwap_ctl_paycenter@finish',array('payment_id'=>$payment['payment_id']));
        return $this->splash('success', $url, $msg, true);
    }
    
    // 支付完成
    public function finish()
    {
        theme::setTitle('订单支付');
        $this->setLayoutFlag('order_detail');
        $postdata = input::get();
        try
        {
            $params['payment_id'] = $postdata['payment_id'];
            $params['fields'] = 'payment_id,status,pay_app_id,pay_name,money,cur_money,payed_time,created_time';
            $result = app::get('topwap')->rpcCall('payment.bill.get',$params);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
        }
        $trades = $result['trade_own_money'];
        $result['num'] = count($trades);
        $pagedata['msg'] = $msg;
        $pagedata['payment'] = $result;

        return $this->page('topwap/payment/finish.html', $pagedata);
    }

}
 