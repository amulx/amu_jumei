<?php

class sysaftersales_ctl_refunds extends desktop_controller {

    public $workground = 'sysaftersales.workground.aftersale';

    public function index()
    {
        return $this->finder(
            'sysaftersales_mdl_refunds',
            array(
                'title'=>app::get('sysaftersales')->_('申请退款列表'),
                 'use_buildin_delete'=>false,
            )
        );
    }

    public function _views()
    {
        $subMenu = array(
            0=>array(
                'label'=>app::get('systrade')->_('全部'),
                'optional'=>false,
            ),
            1=>array(
                'label'=>app::get('systrade')->_('退款处理'),
                'optional'=>false,
                'filter'=>array(
                    'status'=>array('3','4','5'),
                ),
            ),
            2=>array(
                'label'=>app::get('systrade')->_('待商家审核'),
                'optional'=>false,
                'filter'=>array(
                    'status'=>'0',
                ),
            ),
            3=>array(
                'label'=>app::get('systrade')->_('已完成'),
                'optional'=>false,
                'filter'=>array(
                    'status'=>'1',
                ),
            ),
            4=>array(
                'label'=>app::get('systrade')->_('已关闭'),
                'optional'=>false,
                'filter'=>array(
                    'status'=>['2','4'],
                ),
            ),
        );
        return $subMenu;
    }

    public function rejectView($refundsId)
    {
        if( !$refundsId )
        {
            $refundsId = input::get();
        }
        $data = app::get('sysaftersales')->model('refunds')->getRow('aftersales_bn,refunds_id,oid', array('refunds_id'=>$refundsId));
        $pagedata['aftersalesBn'] = $data['aftersales_bn'];
        $pagedata['refundsId'] = $data['refunds_id'];
        return $this->page('sysaftersales/reject.html', $pagedata);
    }

    public function doTeject()
    {
        $this->begin("?app=sysaftersales&ctl=refunds&act=index");

        $postdata = input::get('data');
        if( empty($postdata['explanation']) )
        {
            $this->end(false,'取消原因必填');
        }
        //$params['confirm_from'] = 'admin';
        try
        {
            app::get('sysaftersales')->rpcCall('aftersales.refunds.reject',$postdata);
            $this->adminlog("平台拒绝商家退款[aftersales_bn:{$postdata['aftersales_bn']}]", 1);
        }
        catch(\LogicException $e)
        {
            $this->adminlog("平台拒绝商家退款[aftersales_bn:{$postdata['aftersales_bn']}]", 0);
            $msg = $e->getMessage();
            $this->end(false,$msg);
        }
        $this->end('true');
    }

    public function refundsPay($refunds_id)
    {
        $this->begin("?app=sysaftersales&ctl=refunds&act=index");
        $data = app::get('sysaftersales')->model('refunds')->getRow('*', array('refunds_id'=>$refunds_id));
        $pagedata['user']['id'] = kernel::single('desktop_user')->get_id();
        $pagedata['user']['name'] = kernel::single('desktop_user')->get_login_name();
        $user = app::get('sysaftersales')->rpcCall('user.get.account.name',array('user_id'=>$data['user_id']),'buyer');
        $data['user_name'] = $user[$data['user_id']];
        $pagedata['data'] = $data;
        return $this->page('sysaftersales/refunds.html', $pagedata);
    }

    public function dorefund()
    {
        //echo "<pre>";print_r(input::get());exit;
        $postdata = input::get('data');
        $refundsData = input::get('refundsData');

        $this->begin("?app=sysaftersales&ctl=refunds&act=index");
        try
        {
            //退款方式为预存款时，重新查询退款信息
            if($refundsData['rufund_type'] == 'deposit')
            {
                $deposit = $refundsData['receive_account_deposit'];
                $refundsData['refund_bank'] = "预存款";
                $refundsData['refund_account'] = "shopadmin";
                $refundsData['receive_bank'] = "预存款";
                $refundsData['receive_account'] = $deposit;
            }

            // 对收款人和退款人信息进行判断
            if($refundsData['rufund_type'] == 'offline' && (is_numeric($refundsData['refund_people']) || is_numeric($refundsData['beneficiary'])))
            {
                throw new \Exception('收款人或退款人姓名不能为纯数字');
            }
            $filter['refunds_id'] = $postdata['refunds_id'];
            $objMdlRefunds = app::get('sysaftersales')->model('refunds');
            $refunds = $objMdlRefunds->getRow('refund_bn,status,aftersales_bn,refund_fee,total_price,refunds_type,user_id,shop_id,tid,oid',$filter);

            if( !in_array($refunds['status'],['3','5','6']) )
            {
                throw new \LogicException(app::get('sysaftersales')->_('当前申请还未审核'));
            }

            $refundsData['refunds_type'] = $refunds['refunds_type'];

            if( $refunds['refunds_type'] != '1' )//退款类型，售后退款
            {
                $refundsData['aftersales_bn'] = $refunds['aftersales_bn'];
            }

            //创建退款单
            $refundsId = app::get('sysaftersales')->rpcCall('refund.create',$refundsData);
            if(!$refundsId)
            {
                throw new \LogicException(app::get('sysaftersales')->_('退款单创建失败'));
            }

            //预存款退款
            if($refundsData['rufund_type'] == 'deposit')
            {
                $params['user_id']  = $refundsData['user_id'];
                $params['operator']  = $this->user->get_login_name();
                $params['fee']  = $refundsData['money'];
                $params['memo']  = "订单退款。订单号： ".$refundsData['tid'];

                $return = app::get('sysaftersales')->rpcCall('user.deposit.refund',$params);
                if(!$return['result'])
                {
                    throw new \LogicException(app::get('sysaftersales')->_('预存款退款失败'));
                }

                $refundsUpdate['refund_id'] = $refundsId;
                $refundsUpdate['tid'] = $refundsData['tid'];
                $refundsUpdate['status'] = 'succ';
                //更新退款单状态
                $result = app::get('sysaftersales')->rpcCall('refund.update',$refundsUpdate);
                if(!$result)
                {
                    throw new \LogicException(app::get('sysaftersales')->_('退款单状态更新失败'));
                }
            }

            //更改退款申请单
            $postdata['return_fee'] = $refundsData['total_price'];
            app::get('sysaftersales')->rpcCall('aftersales.refunds.restore',$postdata);
            $this->adminlog("处理退款[refunds_id:{$postdata['refunds_id']}]", 1);
        }
        catch(\Exception $e)
        {
            $this->adminlog("处理退款[refunds_id:{$postdata['refunds_id']}]", 0);
            $msg = $e->getMessage();
            $this->end(false,$msg);
        }

        $this->end('true');
    }
}


