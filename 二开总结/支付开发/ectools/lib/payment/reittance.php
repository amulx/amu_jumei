<?php
/**
 * Created by zhoumin.
 * User: min.zhou@yorisun.com
 * Date: 2015/12/10
 * Time: 12:12
 * 线下汇款处理
 */
class ectools_payment_reittance
{
    /**
     * @var app object
     */
    public $app;

    /**
     * 构造方法
     * @param object app
     * @return null
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param $para 支付完成处理
     */
    public function paymentCompled($para)
    {
        $db = app::get('ectools')->database();
        $db->beginTransaction();
        try
        {
            // 更新payments表
            $mdlPayments = app::get('ectools')->model('payments');
            $data = array(
                'op_id' => pamAccount::getAccountId(),
                'account' => $para['pay']['account'],
                'bank' => $para['pay']['bank'],
                'pay_account' => $para['pay']['pay_account'],
                'trade_no' => $para['pay']['trade_no'],
                'memo' => $para['pay']['memo'],
                'status' => 'succ',
                'pay_name' => '线下汇款',
                'payed_time' => time(),
                'modified_time' => time(),
            );
            $filter = array('payment_id' => $para['payment']['payment_id']);
            if ($mdlPayments->update($data, $filter))
            {
                $mdlTradePaybill = app::get('ectools')->model('trade_paybill');
                $data = array(
                  'status' => 'succ',
                  'payed_time' => time(),
                  'modified_time' => time(),
                );
                if ($mdlTradePaybill->update($data, $filter))
                {
                    $db->commit();
                    return true;
                }
                else
                {
                    $db->rollback();
                }
            }
        }
        catch (Exception $ex)
        {
            $db->rollback();
            throw $ex;
        }
        return false;
    }
}