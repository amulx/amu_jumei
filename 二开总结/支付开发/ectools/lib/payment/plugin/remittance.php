<?php
/**
 * Created by zhoumin.
 * User: min.zhou@yorisun.com
 * Date: 2015/12/8
 * Time: 14:17
 * 线下汇款支付
 */

final class ectools_payment_plugin_remittance extends ectools_payment_app implements ectools_interface_payment_app {
    /**
     * @var string 支付方式名称
     */
    public $name = '线下汇款';
    /**
     * @var string 支付方式接口名称
     */
    public $app_name = '线下汇款接口';
    /**
     * @var string 支付方式key
     */
    public $app_key = 'remittance';
    /**
     * @var string 中心化统一的key
     */
    public $app_rpc_key = 'remittance';
    /**
     * @var string 统一显示的名称
     */
    public $display_name = '线下汇款';
    /**
     * @var string 货币名称
     */
    public $curname = 'CNY';
    /**
     * @var string 当前支付方式的版本号
     */
    public $ver = '1.0';
    /**
     * @var string 当前支付方式所支持的平台
     */
    public $platform = 'iscommon';

    /**
     * 构造方法
     * @param object 传递应用的app
     * @return null
     */
    public function __construct($app){
        parent::__construct($app);

        //$this->callback_url = $this->app->base_url(true)."/apps/".basename(dirname(__FILE__))."/".basename(__FILE__);
        $this->callback_url = "";
        $this->submit_url = '';
        $this->submit_method = 'POST';
        $this->submit_charset = 'utf-8';
    }

    /**
     * 显示支付接口表单基本信息
     * @params null
     * @return string - description include account.
     */
    public function admin_intro(){
        return app::get('ectools')->_('线下汇款后台自定义描述');
    }

    /**
     * 前台支付方式列表关于此支付方式的简介
     * @param null
     * @return string 简介内容
     */
    public function intro(){
        return app::get('ectools')->_('线下汇款客户自定义描述');
    }

    /**
     * 显示支付接口表单选项设置
     * @params null
     * @return array - 字段参数
     */
    public function setting(){
        return array(
            'pay_name'=>array(
                'title'=>app::get('ectools')->_('支付方式名称'),
                'type'=>'string',
            ),
            'order_by' =>array(
                'title'=>app::get('ectools')->_('排序'),
                'type'=>'string',
                'label'=>app::get('ectools')->_('整数值越小,显示越靠前,默认值为1'),
            ),
            'support_cur'=>array(
                'title'=>app::get('ectools')->_('支持币种'),
                'type'=>'text hidden',
                'options'=>$this->arrayCurrencyOptions,
            ),
            'pay_brief'=>array(
                'title'=>app::get('ectools')->_('支付方式简介'),
                'type'=>'textarea',
            ),
            'pay_desc'=>array(
                'title'=>app::get('ectools')->_('描述'),
                'type'=>'html',
                'includeBase' => true,
            ),
            'pay_type'=>array(
                'title'=>app::get('ectools')->_('支付类型(是否在线支付)'),
                'type'=>'hidden',
                'name' => 'pay_type',
            ),
            'status'=>array(
                'title'=>app::get('ectools')->_('是否开启此支付方式'),
                'type'=>'radio',
                'options'=>array('false'=>app::get('ectools')->_('否'),'true'=>app::get('ectools')->_('是')),
                'name' => 'status',
            ),
        );
    }

    /**
     * 提交支付信息的接口
     * 支付接口表单提交方式
     * @param array 提交信息的数组
     * @return mixed false or null
     */
    public function dopay($payment)
    {
        // 线下汇款，直接修改支付单据
        //var_dump('ectools_payment_plugin_remittance.dopay:' .json_encode($payment));
        $objPayments = app::get('ectools')->model('payments');
        $data['pay_type'] = $this->app_rpc_key;
        $filter = array(
            'payment_id' => $payment['payment_id'],
        );

        // 根据payment_id获取订单id
        $mdlTrade = app::get('systrade')->model('trade');
        $tid = app::get('ectools')->model('trade_paybill')->getRow('tid', array('payment_id' => $payment['payment_id']));
        //var_dump('ectools_payment_plugin_remittance.dopay:' .json_encode($tid));
        $db = app::get('ectools')->database();
        $db->beginTransaction();
        try
        {
            if ($objPayments->update($data, $filter))
            {
                $mdlTrade->update($data, array('tid'=>$tid['tid']));
                $db->commit();
            }
            else
            {
                return false;
            }
        }
        catch (Exception $ex)
        {
            $db->rollback();
            throw $ex;
        }
        return true;
    }

    /**
     * 校验方法
     * @param null
     * @return boolean
     */
    public function is_fields_valiad(){
        return true;
    }

    /**
     * 支付回调的方法
     * @param array 回调参数数组
     * @return array 处理后的结果
     */
    public function callback(&$recv)
    {
    }

    /**
     * 生成form的方法
     * @param null
     * @return string html
     */
    public function gen_form()
    {
        return '';
    }
}


