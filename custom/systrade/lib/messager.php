<?php
/**
 * 交易相关发送消息
 *
 * 短信，邮件等通知
 */
class systrade_messager {

    /**
     * 订单mdl
     */
    private $objMdlTrade = null;

    /**
     * 发送短信最大次数
     */
    private $sendSmsMax = 5;

    public function __construct()
    {
        $this->objMdlTrade = app::get('systrade')->model('trade');
    }

    /**
     * 发送自提提货码
     *
     * @param $tid 订单号
     * @param $shopId 店铺ID
     * @param $isSync 是否同步发送短信
     */
    public function sendZitiDelivery($tid, $shopId, $isSync=true)
    {

        $objMdlDeliveryCode = app::get('systrade')->model('delivery_code');
        $deliveryCodeRow = $objMdlDeliveryCode->getRow('id,num,status',['tid'=>$tid]);
        if( $deliveryCodeRow && $deliveryCodeRow['num'] >= $this->sendSmsMax  )
        {
            $msg = app::get('systrade')->_('短信提货码最多发送'.$this->sendSmsMax.'次');
            throw new \LogicException($msg);
        }

        if( $deliveryCodeRow['status'] == 'WITH_FINDISH' )
        {
            $msg = app::get('systrade')->_('该提货码已经验证');
            throw new \LogicException($msg);
        }

        $data = $this->objMdlTrade->getRow('tid,shop_id,shipping_type,ziti_addr,receiver_mobile', ['tid'=>$tid]);
        if( !$data || $data['shop_id'] != $shopId || $data['shipping_type'] != 'ziti')
        {
            $msg = app::get('systrade')->_('请输入正确的订单号');
            throw new \LogicException($msg);
        }

        $to = $data['receiver_mobile'];
        if( ! $to )
        {
            $msg = app::get('systrade')->_('收货地址未填写手机号');
            throw new \LogicException($msg);
        }

        $hasher = kernel::single('base_hashing_hasher_bcrypt');
        $vcode = mt_rand('100000',999999);
        $tmpl = 'delivery-ziti';
        $content = [
            'tid' => $tid,
            'ziti_addr' => $data['ziti_addr'],
            'ziti_name' => $data['ziti_name'],
            'vcode' => $vcode,
        ];
        if( $isSync )
        {
            $result = messenger::sendSms($to,$tmpl,$content);
            if( $result['rsp'] == 'fail' )
            {
                $msg = app::get('systrade')->_('短信提货码发送失败');
                throw new \LogicException($msg);
            }
        }
        else
        {
            messenger::send($sendTo,$tmpl,$content);
        }

        $num = $deliveryCodeRow['num'] ? $deliveryCodeRow['num'] + 1 : 1;
        if( $deliveryCodeRow )
        {
            return $objMdlDeliveryCode->update(['vcode'=>$hasher->make($vcode),'modified_time'=>time(),'num'=>$num],['id'=>$deliveryCodeRow['id']]);
        }
        else
        {
            $insertData = [
                'tid' => $tid,
                'num' => $num,
                'shop_id' => $data['shop_id'],
                'vcode' => $hasher->make($vcode),
                'status' => 'WITH_CHECK',
                'modified_time' => time(),
            ];
            return $objMdlDeliveryCode->insert($insertData);
        }
    }

    /**
     * 验证自提订单提货码
     *
     * @param $tid 自提订单号
     * @param $shopId 店铺ID
     * @param $vcode 提货码
     *
     * @return bool true | false
     */
    public function verifyZitiDelivery($tid, $shopId, $vcode)
    {
        $objMdlDeliveryCode = app::get('systrade')->model('delivery_code');
        $deliveryCodeRow = $objMdlDeliveryCode->getRow('id,shop_id,vcode,status',['tid'=>$tid]);
        if( !$deliveryCodeRow || $deliveryCodeRow['shop_id'] != $shopId )
        {
            $msg = app::get('systrade')->_('验证的自提订单号错误');
            throw new \LogicException($msg);
        }

        if( $deliveryCodeRow['status'] == 'WITH_FINDISH' )
        {
            $msg = app::get('systrade')->_('该提货码已经验证');
            throw new \LogicException($msg);
        }

        $hasher = kernel::single('base_hashing_hasher_bcrypt');
        if( $hasher->check($vcode, $deliveryCodeRow['vcode']) )
        {
            return $objMdlDeliveryCode->update(['status'=>'WITH_FINDISH'],['id'=>$deliveryCodeRow['id']]);
        }

        return false;
    }
}

