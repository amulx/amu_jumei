<?php

/**
 * favorite.php 会员收藏
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_member_favorite extends topwap_ctl_member {

    public $limit = 10;
    // 收藏中心,优先展示商品收藏
    public function index()
    {
        theme::setTitle('我的收藏');
        $filter = array();
        $items = $this->_getItems($filter);
        $shops = $this->_getShops($filter);
        $pagedata['items'] = $items;
        $pagedata['shops'] = $shops;
        $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');

        return $this->page('topwap/member/favorite/index.html', $pagedata);
    }

    // 收藏的商品
    public function ajaxitems()
    {
        $filter = input::get();
        try {
            $result = $this->_getItems($filter);
            $pagedata['items'] = $result;
            $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');
            if($pagedata['items']['favitem'])
            {
                $data['html'] = view::make('topwap/member/favorite/items.html',$pagedata)->render();
            }
            else
            {
                $data['html'] = view::make('topwap/empty/favorite_items.html')->render();
            }

            $data['count'] = $result['count'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg,true);
        }

        return response::json($data);exit;

    }

    // 收藏的店铺
    public function ajaxshops()
    {
        $filter = input::get();
        try {
            $result = $this->_getShops($filter);
            $pagedata['shops'] = $result;
            $pagedata['defaultImageId']= kernel::single('image_data_image')->getImageSetting('item');
             if($pagedata['shops']['favshop'])
            {
                $data['html'] = view::make('topwap/member/favorite/shops.html',$pagedata)->render();
            }
            else
            {
                $data['html'] = view::make('topwap/empty/favorite_shops.html')->render();
            }

            $data['count'] = $result['count'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg,true);
        }

        return response::json($data);exit;
    }

    /**
     * @brief 商品收藏添加
     */
    public function ajaxAddItemCollect() {
        $userId = userAuth::id();
        if(!$userId)
        {
            $url = url::action('topwap_ctl_passport@goLogin');
            return $this->splash('error',$url);
        }
        $params['item_id'] = input::get('item_id');
        $params['user_id'] = $userId;
        $params['objectType'] = input::get('type');
        try{
            if(app::get('topwap')->rpcCall('user.itemcollect.add', $params)){
                $collectData = app::get('topwap')->rpcCall('user.collect.info',array('user_id'=>$userId));
                setcookie('collect',serialize($collectData));
            }else{
                throw new Exception(app::get('topwap')->_('商品收藏失败'));
            }
        }catch(Exception $e){
            $msg = $e->getMessage();
            return $this->splash('error',null, $msg);
        }
        return  $this->splash('success',null,app::get('topwap')->_('商品收藏成功'));
    }

    /**
     * @brief 商品收藏删除
     */

    public function ajaxDelItemCollect()
    {
        $userId = userAuth::id();
        $params['item_id'] = input::get('id');
        $params['user_id'] = $userId;

        if(empty($params['item_id']))
        {
            return $this->splash('error',null, app::get('topwap')->_('商品id不能为空！'));
        }

        if (!app::get('topwap')->rpcCall('user.itemcollect.del', $params))
        {
            return $this->splash('error',null, app::get('topwap')->_('商品收藏删除失败！'));
        }
        else
        {
            $collect = unserialize($_COOKIE['collect']);
            $itemIds = is_array($params['item_id']) ? $params['item_id'] : array($params['item_id']);

            foreach ($itemIds as $value)
            {
                $key = array_search($value, $collect['item']);
                unset($collect['item'][$key]);
            }
            setcookie('collect',serialize($collect));
            return  $this->splash('success',null,app::get('topwap')->_('商品收藏删除成功'));
        }
    }

    /**
     * @brief 添加店铺收藏
     */

    public function ajaxAddShopCollect()
    {
        $userId = userAuth::id();
        if(!$userId)
        {
            $url = url::action('topwap_ctl_passport@goLogin');
            return $this->splash('error',$url);
        }

        $params['shop_id'] = input::get('shop_id');
        $params['user_id'] = $userId;

        try{
            if(app::get('topwap')->rpcCall('user.shopcollect.add', $params)){
                $collectData = app::get('topwap')->rpcCall('user.collect.info',array('user_id'=>$userId));
                setcookie('collect',serialize($collectData));
            }
            else
            {
                throw new Exception(app::get('topwap')->_('店铺收藏失败'));
            }

        }catch(Exception $e){
            $msg = $e->getMessage();
            return $this->splash('error',null, $msg);
        }
        return  $this->splash('success',null,app::get('topwap')->_('店铺收藏成功'));
    }

    /**
     * @brief 删除店铺收藏
     */

    public function ajaxDelShopCollect()
    {
        $userId = userAuth::id();
        $params['shop_id'] = input::get('id');
        $params['user_id'] = $userId;
        if(!$params['shop_id'])
        {
            return $this->splash('error',null, app::get('topwap')->_('店铺id不能为空！'));
        }
        if (!app::get('topwap')->rpcCall('user.shopcollect.del', $params))
        {

            return $this->splash('error',null, app::get('topwap')->_('店铺收藏删除失败！'));
        }
        else
        {

            $collect = unserialize($_COOKIE['collect']);
            $shopIds = is_array($params['shop_id']) ? $params['shop_id'] : array($params['shop_id']);
            foreach ($shopIds as $value)
            {
                $key = array_search($value, $collect['shop']);
                unset($collect['shop'][$key]);
            }
            setcookie('collect',serialize($collect));
            return  $this->splash('success',null,app::get('topwap')->_('店铺收藏删除成功'));
        }
    }

    // 获取收藏的商品
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
        $favData = app::get('topwap')->rpcCall('user.itemcollect.list',$params);
        $count = $favData['itemcount'];
        $favList = $favData['itemcollect'];
        $pagedata['favitem']= $favList;
        //处理翻页数据
        if( $count > 0 ) $totalPage = ceil($count/$this->limit);
        $pagedata['count'] = $totalPage;
        return $pagedata;
    }

    // 获取收藏的店铺
    protected function _getShops($filter)
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
        $favData = app::get('topwap')->rpcCall('user.shopcollect.list',$params);

        $count = $favData['shopcount'];
        $favList = $favData['shopcollect'];
        //处理翻页数据
        $current = $filter['pages'] ? $filter['pages'] : 1;
        $pagedata['favshop']= $favList;
        //处理翻页数据
        if( $count > 0 ) $totalPage = ceil($count/$this->limit);
        $pagedata['count'] = $totalPage;
        return $pagedata;
    }


}

