<?php

/**
 * history.php 会员足迹
 */
class topwap_ctl_member_history extends topwap_ctl_member {

    public $limit = 10;
    // 足迹中心
    public function index()
    {
        theme::setTitle('我的足迹');
        $filter = array();
        $items = $this->_getItems($filter);

        $pagedata['items'] = $items;
        $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');

        return $this->page('topwap/member/history/index.html', $pagedata);
    }

    // 足迹的商品
    public function ajaxitems()
    {
        $filter = input::get();
        try {
            $result = $this->_getItems($filter);
            $pagedata['items'] = $result;
            // var_dump($result);exit;
            $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');
            if($pagedata['items']['itemhistory'])
            {   
                $data['html'] = view::make('topwap/member/history/items.html',$pagedata)->render();
            }
            else
            {
                $data['html'] = view::make('topwap/member/history/history_items.html')->render();
            }

            $data['count'] = $result['count'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg,true);
        }

        return response::json($data);exit;

    }


    /**
     * @brief 足迹保存
     */
    public function ajaxAddItemHistory() {
        $userId = userAuth::id();
        if(!$userId)
        {
            $url = url::action('topwap_ctl_passport@goLogin');
            return $this->splash('error',$url);
        }
        $params['item_id'] = input::get('item_id');
        $params['user_id'] = $userId;
        app::get('topwap')->rpcCall('user.itemHistory.add', $params);
    }

    /**
     * @brief 足迹删除
     */

    public function ajaxDelItemHistory()
    {
        $userId = userAuth::id();
        $params['item_id'] = array_filter(explode(',',input::get('id')));
        $params['user_id'] = $userId;

        if(empty($params['item_id']))
        {
            return $this->splash('error',null, app::get('topwap')->_('商品id不能为空！'));
        }

        if (!app::get('topwap')->rpcCall('user.itemHistory.del', $params))
        {
            return $this->splash('error',null, app::get('topwap')->_('删除历史足迹失败！'));
        }
        else
        {
            return  $this->splash('success',null,app::get('topwap')->_('删除历史足迹成功'));
        }
    }


    // 获取历史足迹的商品
    protected function _getItems($filter)
    {
        if(!$filter['pages'])
        {
            $filter['pages'] = 1;
        }
        $params = array(
                'page_no' => $filter['pages'],
                'page_size' => $this->limit,
                'fields' =>'*',
                'user_id'=>userAuth::id(),
        );
        $historyData = app::get('topwap')->rpcCall('user.itemHistory.list',$params);
        $count = $historyData['itemcount'];
        $historyList = $historyData['itemhistory'];

        //2016.7.22 hui 商品时间
        $dates = array();
        foreach ($historyList as $key => $value) {
            if (!in_array(date('Y-m-d',$value['create_time']), $dates))
            {
                $dates[] = date('Y-m-d',$value['create_time']);
            }
            $historyList[$key]['datetime']= date('Y-m-d',$value['create_time']);
        }
        $pagedata['historydates']= $dates;
        //---------------------------------------------
        
        $pagedata['itemhistory']= $historyList;
        //处理翻页数据
        if( $count > 0 ) $totalPage = ceil($count/$this->limit);
        $pagedata['count'] = $totalPage;
        return $pagedata;
    }

}

