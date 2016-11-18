<?php
class systrade_api_trade_list{
    public $apiDescription = "获取订单列表";
    public function getParams()
    {
        $return['params'] = array(
            'user_id' => ['type'=>'int', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单所属用户id'],
            'shop_id' => ['type'=>'int', 'valid'=>'int', 'default'=>'', 'example'=>'','description'=>'订单所属店铺id'],
            'status' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单状态'],
            'buyer_rate' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单评价状态'],
            'tid' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'订单编号,多个用逗号隔开'],
            'create_time_start' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'查询指定时间内的交易创建时间开始yyyy-MM-dd'],
            'create_time_end' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'查询指定时间内的交易创建时间结束yyyy-MM-dd'],
            'receiver_mobile' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'收货人手机'],
            'receiver_phone' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'收货人电话'],
            'receiver_name' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'收货人姓名'],
            'user_name' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'会员用户名/手机号/邮箱'],
            'is_aftersale' => ['type'=>'bool', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'是否显示售后状态'],
            'pay_type' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'支付方式【offline、online】'],
            'shipping_type' => ['type'=>'string', 'valid'=>'', 'default'=>'', 'example'=>'','description'=>'配送类型'],

            'page_no' => ['type'=>'int','valid'=>'int','description'=>'分页当前页码,1<=no<=499','example'=>'','default'=>'1'],
            'page_size' =>['type'=>'int','valid'=>'int','description'=>'分页每页条数(1<=size<=200)','example'=>'','default'=>'40'],
            'order_by' => ['type'=>'int','valid'=>'','description'=>'排序方式','example'=>'','default'=>'created_time desc'],
            'fields' => ['type'=>'field_list','valid'=>'','description'=>'获取的交易字段集','example'=>'','default'=>''],
        );
        $return['extendsFields'] = ['order','activity'];
        return $return;
    }
    public function tradeList($params)
    {
        if($params['oauth']['account_id'] && $params['oauth']['auth_type'] == "member" )
        {
            $params['user_id'] = $params['oauth']['account_id'];
        }
        elseif($params['oauth']['account_id'] && $params['oauth']['auth_type'] == "shop")
        {
            $sellerId = $params['oauth']['account_id'];
            $params['shop_id'] = app::get('systrade')->rpcCall('shop.get.loginId',array('seller_id'=>$sellerId),'seller');
        }

        $tradeRow = $params['fields']['rows'];
        $orderRow = $params['fields']['extends']['order'];

        $activityRow = $params['fields']['extends']['activity'];

        $orderBy = $params['orderBy'];
        if(!$params['orderBy'])
        {
            $orderBy = "created_time desc";
        }
        $pageNo = $params['page_no'];
        $pageSize = $params['page_size'];
        unset($params['fields'],$params['page_no'],$params['page_size'],$params['order_by'],$params['oauth']);

        foreach($params as $k=>$val)
        {
            if(is_null($val))
            {
                unset($params[$k]);
                continue;
            }
            if($k == "status" || $k == "tid")
            {
                $params[$k] = explode(',',$val);
            }
        }

        if( $params['create_time_start'] )
        {
            $params['created_time|bthan'] = $params['create_time_start'];
            unset($params['create_time_start']);
        }

        if( $params['create_time_end'] )
        {
            $params['created_time|lthan'] = $params['create_time_end'];
            unset($params['create_time_end']);
        }

        if( $params['user_name'] )
        {
            $userIds = app::get('systrade')->rpcCall('user.get.account.id',['user_name'=>$params['user_name']]);
            if(!$userIds)
            {
                return false;
            }
            unset($params['user_name']);
            $params['user_id'] = $userIds;
        }

        $objMdlTrade = app::get('systrade')->model('trade');
        $count = $objMdlTrade->count($params);

        //分页使用
        $page =  $pageNo ? $pageNo : 1;
        $limit = $pageSize ? $pageSize : 40;
        $pageTotal = ceil($count/$limit);
        $currentPage = $pageTotal < $page ? $pageTotal : $page;
        $offset = ($currentPage-1) * $limit;

        $tradeLists = $objMdlTrade->getList($tradeRow,$params,$offset,$limit,$orderBy);
        $tradeLists = array_bind_key($tradeLists,'tid');

        if($orderRow && $tradeLists)
        {
            $orderRow = str_append($orderRow,'tid');
            $objMdlOrder = app::get('systrade')->model('order');
            $tids = array_column($tradeLists,'tid');
            $orderLists = $objMdlOrder->getList($orderRow,array('tid'=>$tids));

        
            //是否需要显示标签促销tag
            if( $activityRow && $orderLists )
            {
                $oids = array_column($orderLists,'oid');
                $promotionActivityData = app::get('systrade')->model('promotion_detail')->getList('promotion_tag,oid',['promotion_type'=>'activity','oid'=>$oids]);
                //一个子订单只可参加一次标签促销活动
                $promotionActivityData = array_bind_key($promotionActivityData,'oid');
            }

            foreach($orderLists as $key=>$value)
            {
                $sous = app::get('sysitem')->model('item')->getRow('source,source_link',array('item_id'=>$value['item_id']));
                $value['source'] = $sous['source'];
                $value['source_link'] = $sous['source_link'];

                if( $promotionActivityData[$value['oid']]['promotion_tag'] )
                {
                    $value['promotion_tag'] = $promotionActivityData[$value['oid']]['promotion_tag'];
                }
                $tradeLists[$value['tid']]['order'][] = $value;
            }

            //获取售后状态
            if($params['is_aftersale'])
            {
                $afterParams = array();
                $afterParams['fields'] = 'tid,progress,aftersales_bn';
                $afterParams['tid'] = $tids;
                $afterParams['shop_id'] = $params['shop_id'];
                $afterList = app::get('sysaftersales')->rpcCall('aftersales.list.get',$afterParams);
                $afterList = $afterList['list'];
                if($afterList)
                {
                    foreach ($afterList as $afterVal)
                    {
                        $tradeLists[$afterVal['tid']]['aftersale'] = $afterVal;
                    }
                }
            }
        }

        $trade['list'] = $tradeLists;
        $trade['count'] = $count;
        return $trade;
    }
}



