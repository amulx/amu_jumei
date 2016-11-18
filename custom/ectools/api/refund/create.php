<?php
class ectools_api_refund_create{

    public $apiDescription = '创建退款单';
    public function getParams()
    {
        $return['params'] = array(
            'tid' => ['type'=>'string','valid'=>'required', 'description'=>'申请售后的主订单编号', 'default'=>'', 'example'=>''],
            'oid' => ['type'=>'string','valid'=>'', 'description'=>'申请售后的子订单编号', 'default'=>'', 'example'=>''],
            'money' => ['type'=>'json','valid'=>'required', 'description'=>'退款金额', 'default'=>'', 'example'=>''],
            'refund_bank' => ['type'=>'json','valid'=>'required', 'description'=>'退款银行', 'default'=>'', 'example'=>''],
            'refund_account' => ['type'=>'json','valid'=>'required', 'description'=>'退款账号', 'default'=>'', 'example'=>''],
            'refund_people' => ['type'=>'json','valid'=>'required', 'description'=>'退款操作人', 'default'=>'', 'example'=>''],
            'receive_bank' => ['type'=>'json','valid'=>'required', 'description'=>'收款银行', 'default'=>'', 'example'=>''],
            'receive_account' => ['type'=>'json','valid'=>'required', 'description'=>'收款账号', 'default'=>'', 'example'=>''],
            'beneficiary' => ['type'=>'json','valid'=>'required', 'description'=>'收款人', 'default'=>'', 'example'=>''],
            'aftersales_bn' => ['type'=>'json','valid'=>'', 'description'=>'售后单编号', 'default'=>'', 'example'=>''],
            'refunds_type' => ['type'=>'json','valid'=>'required', 'description'=>'退款单类型', 'default'=>'', 'example'=>''],
            'rufund_type' => ['type'=>'string','valid'=>'', 'description'=>'退款方式', 'default'=>'offline', 'example'=>''],
        );
        return $return;
    }

    public function create($params)
    {
        if( $params['refunds_type'] == '0' && (empty($params['aftersales_bn']) || empty($params['oid']) ) )//退款类型，售后退款
        {
            throw new \LogicException('请填写售后单编号或字订单编号');
        }

        $db = app::get('ectools')->database();
        $db->beginTransaction();
        try
        {
            $objRefund = kernel::single('ectools_data_refunds');
            $result = $objRefund->create($params);

            /*
            	2016-09-07  min.zhou@yorisun.commit
            	 输出退款记录到oms
            */
            kernel::single('foroms_output_trade')->refund($params);
        }
        catch(\Exception $e)
        {
            $db->rollback();
            throw new \LogicException(app::get('ectools')->_($e->getMessage()));
            return false;
        }
        $db->commit();
        return $result;
    }
}
