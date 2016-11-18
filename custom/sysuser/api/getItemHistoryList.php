<?php
class sysuser_api_getItemHistoryList {

    /**
     * 接口作用说明
     */
    public $apiDescription = '获取会员历史足迹列表';

    /**
     * 定义应用级参数，参数的数据类型，参数是否必填，参数的描述
     * 用于在调用接口前，根据定义的参数，过滤必填参数是否已经参入
     */
    public function getParams()
    {
        //接口传入的参数
        $return['params'] = array(
            'page_no' => ['type'=>'int','valid'=>'', 'default'=>'', 'example'=>'', 'description'=>'分页当前页数,默认为1','default'=>'','example'=>''],
            'page_size' => ['type'=>'int','valid'=>'', 'default'=>'', 'example'=>'', 'description'=>'每页数据条数,默认20条','default'=>'','example'=>''],
            'fields'=> ['type'=>'field_list','valid'=>'', 'default'=>'', 'example'=>'', 'description'=>'需要的字段','default'=>'','example'=>''],
            'user_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'用户ID必填','default'=>'','example'=>''],
            'cat_id' => ['type'=>'int','valid'=>'', 'default'=>'', 'example'=>'', 'description'=>'商品3级分类','default'=>'','example'=>''],
            'orderBy' => ['type'=>'string','valid'=>'', 'default'=>'', 'example'=>'', 'description'=>'排序','default'=>'','example'=>''],
        );

        return $return;
    }

    public function getItemHistoryList($params)
    {
        $objMdlHistory = app::get('sysuser')->model('user_history');

        if(!$params['fields'])
        {
            $params['fields'] = '*';
        }
        if($params['cat_id'])
        {
            $filter = array('user_id'=>$params['user_id'],'cat_id'=>$params['cat_id']);
        }
        else
        {
            $filter = array('user_id'=>$params['user_id']);
        }

        $itemCount = $objMdlHistory->count($filter);
        $pageTotal = ceil($itemCount/$params['page_size']);
        $page =  $params['page_no'] ? $params['page_no'] : 1;
        $limit = $params['page_size'] ? $params['page_size'] : 40;
        $currentPage = ($pageTotal < $page) ? $pageTotal : $page; //防止传入错误页面，返回最后一页信息
        $offset = ($currentPage-1) * $limit;

        $orderBy    = $params['orderBy'] ? $params['orderBy'] : 'create_time DESC';
        $aData = $objMdlHistory->getList($params['fields'], $filter, $offset,$limit, $orderBy);
        $historyData = [];
        if( $aData )
        {
            $historyData = $this->__itemData($aData);
        }

        $itemData = array(
                'itemhistory' => $historyData,
                'itemcount' => $itemCount,
            );

        return $itemData;
    }

    private function __itemData($data)
    {
        foreach ($data as $key => $value)
        {
            $historyItemId[$key] = $value['item_id'];
            $historyData[$value['item_id']]['history_id'] = $value['history_id'];
            $historyData[$value['item_id']]['item_id'] = $value['item_id'];
            $historyData[$value['item_id']]['user_id'] = $value['user_id'];
            $historyData[$value['item_id']]['sku_id'] = $value['sku_id'];
            $historyData[$value['item_id']]['cat_id'] = $value['cat_id'];
            $historyData[$value['item_id']]['goods_name'] = $value['goods_name'];
            $historyData[$value['item_id']]['goods_price'] = $value['goods_price'];
            $historyData[$value['item_id']]['image_default_id'] = $value['image_default_id'];
            $historyData[$value['item_id']]['create_time'] = $value['create_time'];
        }
        $objMdlItem = app::get('sysitem')->model('item');
        $itemData = $objMdlItem->getList('item_id',array('item_id'=>$historyItemId));
        foreach ($itemData as $key => $value)
        {
            $itemId[$key] = $value['item_id'];
        }

        foreach ($historyItemId as $value)
        {
            if(!in_array($value, $itemId))
            {
                $unItemId[] = $value;
                $historyData[$value]['is_online'] = 'yes';
            }
        }
        return $historyData;
    }
}
