<?php

class systrade_api_trade_confirm {

    public $apiDescription = "交易完成";

    public function getParams()
    {
        $return['params'] = array(
            'tid' => ['type'=>'int', 'valid'=>'required|numeric', 'default'=>'', 'example'=>'','description'=>'订单id'],
            'user_id' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单所属用户id'],
            'shop_id' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单所属店铺id'],
        );

        return $return;
    }

    public function confirmTrade($params)
    {
        try
        {
            kernel::single('systrade_data_trade_confirm')
                ->setOperator($params['oauth'])
                ->generate($params['tid'], $params['user_id'], $params['shop_id'] );

                
            /*
            	2016-09-08 min.zhou@yorisun.com
            */ 
            kernel::single('foroms_output_trade')->confirm($params);
        }
        catch(Exception $e)
        {
           throw $e;
        }
        return true;
    }
}
