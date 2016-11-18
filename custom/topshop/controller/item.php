<?php

/**
 * @brief 商家商品管理
 */
class topshop_ctl_item extends topshop_controller {

    public $limit = 10;

    public function add()
    {
        $pagedata['shopCatList'] = app::get('topshop')->rpcCall('shop.cat.get',array('shop_id'=>$this->shopId,'fields'=>'cat_id,cat_name,is_leaf,parent_id,level'));
        $pagedata['shopId'] = $this->shopId;

        // 获取物流模板列表
        $tmpParams = array(
            'shop_id' => $this->shopId,
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $pagedata['dlytmpls'] = app::get('topshop')->rpcCall('logistics.dlytmpl.get.list',$tmpParams);

        $this->contentHeaderTitle = app::get('topshop')->_('添加商品');
        return $this->page('topshop/item/edit.html', $pagedata);
    }
    //商品库存报警设置
    public function storePolice()
    {
        $shopId = $this->shopId;
        $params['shop_id'] = $shopId;
        $params['fields'] = 'police_id,policevalue';

        $storePolice = app::get('topshop')->rpcCall('item.store.info',$params);
        //echo '<pre>';print_r($storePolice);exit();
        $pagedata['storePolice'] = $storePolice;
        $this->contentHeaderTitle = app::get('topshop')->_('设置商品库存报警数');
        return $this->page('topshop/item/storepolice.html', $pagedata);
    }
    //保存库存报警
    public function saveStorePolice()
    {
        $storePolice = intval(input::get('storepolice'));
        $policeId = intval(input::get('police_id'));
        $url = url::action('topshop_ctl_item@storePolice');
        try
        {
            $validator = validator::make(
                    [$storePolice],
                    ['required|integer|min:1|max:99999'],
                    ['库存预警值必填!|库存预警值必须为整数!|库存预警值最小为1!|库存预警值最大为99999!']
                );
            $validator->newFails();
        }
        catch( \LogicException $e )
        {
            return $this->splash('error', $url, $e->getMessage(), true);
        }
        $shopId = $this->shopId;
        $params['shop_id'] = $shopId;
        $params['policevalue'] = $storePolice;
        if(!is_null($policeId))
        {
            $params['police_id'] = $policeId;
        }
        try
        {
            app::get('topshop')->rpcCall('item.store.add',$params);
        }
        catch( \LogicException $e )
        {
            return $this->splash('error', null, $e->getMessage(), true);
        }
        $this->sellerlog('库存预警设置。');
        return $this->splash('success', $url, '保存成功', true);
    }

    public function edit()
    {
        //$pagedata['return_to_url'] = request::server('HTTP_REFERER');
        $itemId = intval(input::get('item_id'));
        $pagedata['shopId'] = $this->shopId;

        // 店铺关联的商品品牌列表
        // 商品详细信息
        $params['item_id'] = $itemId;
        $params['shop_id'] = $this->shopId;
        $params['fields'] = "*,sku,item_store,item_status,item_count,item_desc,item_nature,spec_index";
        $pagedata['item'] = app::get('topshop')->rpcCall('item.get',$params);
        $pagedata['item']['title'] = $pagedata['item']['title'];
        // 商家分类及此商品关联的分类标示selected
        $scparams['shop_id'] = $this->shopId;
        $scparams['fields'] = 'cat_id,cat_name,is_leaf,parent_id,level';
        $pagedata['shopCatList'] = app::get('topshop')->rpcCall('shop.cat.get',$scparams);
        $selectedShopCids = explode(',', $pagedata['item']['shop_cat_id']);
        foreach($pagedata['shopCatList'] as &$v)
        {
            if($v['children'])
            {
                foreach($v['children'] as &$vv)
                {
                    if(in_array($vv['cat_id'], $selectedShopCids))
                    {
                        $vv['selected'] = true;
                    }
                }
            }
            else
            {
                if(in_array($v['cat_id'], $selectedShopCids))
                {
                    $v['selected'] = true;
                }
            }
        }
        // 获取运费模板列表
        $tmpParams = array(
            'shop_id' => $this->shopId,
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $pagedata['dlytmpls'] = app::get('topshop')->rpcCall('logistics.dlytmpl.get.list',$tmpParams);

        $this->contentHeaderTitle = app::get('topshop')->_('添加商品');
        return $this->page('topshop/item/edit.html', $pagedata);
    }

    public function itemList()
    {
        $pagedata['image_default_id'] = kernel::single('image_data_image')->getImageSetting('item');

        $status = input::get('status',false);
        $pages =  input::get('pages',1);
        $pagedata['status'] = $status;
        $filter = array(
            'shop_id' => $this->shopId,
            'approve_status' => $status,
            'page_no' =>intval($pages),
            'page_size' => intval($this->limit),
        );
        $shopCatId = input::get('shop_cat_id',false);
        if( $shopCatId )
        {
            $filter['shop_cat_id'] = $shopCatId;
            $pagersFilter['shop_cat_id'] = $shopCatId;
        }
        $filter['fields'] = 'item_id,list_time,modified_time,title,image_default_id,price,approve_status,store,dlytmpl_id,nospec,source,source_link,mkt_price,cost_price';
        //库存报警判断
        if($status=='oversku')
        {
            $params['shop_id'] = $this->shopId;
            $params['fields'] = 'policevalue';
            $storePolice = app::get('topshop')->rpcCall('item.store.info',$params);
            $filter['store'] = $storePolice['policevalue']?$storePolice['policevalue']:0;
            $itemsList = app::get('topshop')->rpcCall('item.store.police',$filter);
        }
        else
        {
            $itemsList = app::get('topshop')->rpcCall('item.search',$filter);
        }
        $pagedata['item_list'] = $itemsList['list'];
        $pagedata['total'] = $itemsList['total_found'];

        $totalPage = ceil($itemsList['total_found']/$this->limit);
        $pagersFilter['pages'] = time();
        $pagersFilter['status'] = $status;
        $pagers = array(
            'link'=>url::action('topshop_ctl_item@itemList',$pagersFilter),
            'current'=>$pages,
            'use_app' => 'topshop',
            'total'=>$totalPage,
            'token'=>time(),
        );
        $pagedata['pagers'] = $pagers;

        //获取当前店铺商品分类
        $catparams['shop_id'] = $this->shopId;
        //$catparams['fields'] = 'cat_id,cat_name';
        $itemCat = app::get('topshop')->rpcCall('shop.cat.get', $catparams);
        $pagedata['item_cat'] = $itemCat;

        $pagedata['image_default_id'] = kernel::single('image_data_image')->getImageSetting('item');
        //获取运费模板
        $tmpParams = array(
            'shop_id' => $this->shopId,
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $pagedata['dlytmpl'] = app::get('topshop')->rpcCall('logistics.dlytmpl.get.list',$tmpParams);
        $this->contentHeaderTitle = app::get('topshop')->_('商品列表');

        return $this->page('topshop/item/list.html', $pagedata);
    }
    //商品搜所
    public function searchItem()
    {
        $filter = input::get();

        if($filter['min_price']&&$filter['max_price'])
        {
            if($filter['min_price']>$filter['max_price'])
             {
                $msg = app::get('topshop')->_('最大值不能小于最小值！');
                return $this->splash('error', null, $msg, true);
            }
        }
        $pages =  $filter['pages'] ? $filter['pages'] : 1;
        $params = array(
            'shop_id' => $this->shopId,
            'search_keywords' => $filter['item_title'],
            'min_price' => $filter['min_price'],
            'max_price' => $filter['max_price'],
            'page_no' =>intval($pages),
            'page_size' => intval($this->limit),
        );

        if($filter['use_platform'] >= 0)
        {
            $params['use_platform'] = $filter['use_platform'];
        }
        if($filter['item_cat'] && $filter['item_cat'] > 0)
        {
            $params['search_shop_cat_id'] = (int)$filter['item_cat'];
        }
        if($filter['item_no'])
        {
            $params['bn'] = $filter['item_no'];
        }
        if($filter['status'])
        {
            $params['approve_status'] = $filter['status'];
        }

        if($filter['dlytmpl_id']&&$filter['dlytmpl_id']>0)
        {
            $params['dlytmpl_id'] = $filter['dlytmpl_id'];
        }
        if($filter['dlytmpl_id']&&$filter['dlytmpl_id']>0)
        {
            $pagedata['dlytmpl_id'] = $filter['dlytmpl_id'];
        }
        $pagedata['use_platform'] = $filter['use_platform'];
        $pagedata['min_price'] = $filter['min_price'];
        $pagedata['max_price'] = $filter['max_price'];
        $pagedata['search_keywords'] = $filter['item_title'];
        $pagedata['item_cat_id'] = $filter['item_cat'];
        $pagedata['item_no'] = $filter['item_no'];
        $pagedata['status'] = isset($filter['status']) ? $filter['status'] : '';
        $params['fields'] = 'item_id,list_time,modified_time,title,image_default_id,price,approve_status,store,dlytmpl_id,nospec';
        //库存报警判断
        if($filter['status']=='oversku')
        {
            $pparams['shop_id'] = $this->shopId;
            $pparams['fields'] = 'policevalue';
            $storePolice = app::get('topshop')->rpcCall('item.store.info',$pparams);
            $params['store'] = $storePolice['policevalue']?$storePolice['policevalue']:0;
            $itemsList = app::get('topshop')->rpcCall('search.item.oversku',$params);
        }
        else
        {
            $itemsList = app::get('topshop')->rpcCall('item.search',$params);
        }
        //获取运费模板
        $tmpParams = array(
            'shop_id' => $this->shopId,
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $pagedata['dlytmpl'] = app::get('topshop')->rpcCall('logistics.dlytmpl.get.list',$tmpParams);


        $pagedata['item_list'] = $itemsList['list'];
        $pagedata['total'] = $itemsList['total_found'];

        $totalPage = ceil($itemsList['total_found']/$this->limit);
        $pagersFilter['pages'] = time();
        $pagersFilter['min_price'] = $filter['min_price'];
        $pagersFilter['max_price'] = $filter['max_price'];
        $pagersFilter['use_platform'] = $filter['use_platform'];
        $pagersFilter['item_title'] = $filter['item_title'];
        $pagersFilter['item_cat'] = $filter['item_cat'];
        $pagersFilter['item_no'] = $filter['item_no'];
        $pagersFilter['dlytmpl_id'] = $filter['dlytmpl_id'];
        if(isset($filter['status']))
        {
            $pagersFilter['status'] =  $filter['status'];
        }

        $pagers = array(
            'link'=>url::action('topshop_ctl_item@searchItem',$pagersFilter),
            'current'=>$pages,
            'use_app' => 'topshop',
            'total'=>$totalPage,
            'token'=>time(),
        );
        $pagedata['pagers'] = $pagers;

        //获取当前店铺商品分类
        $catparams['shop_id'] = $this->shopId;
        $itemCat = app::get('topshop')->rpcCall('shop.cat.get', $catparams);
        $pagedata['item_cat'] = $itemCat;

        // 是否在搜索中
        $pagedata['is_search'] = true;
        // 表格切换条件
        $searchParams = $pagersFilter;
        $searchArr = array();
        $searchTmp = array();
        if($searchParams)
        {
            unset($searchParams['pages']);
            if(isset($searchParams['status']))
            {
                unset($searchParams['status']);
            }
            $status = array(
                    'onsale' => app::get('topshop')->_('上架中'),
                    'instock' => app::get('topshop')->_('仓库中'),
                    'oversku' => app::get('topshop')->_('库存报警'),
            );
            foreach ($status as $k=>$v)
            {
                $searchParams['status'] = $k;
                $searchTmp['status'] = $k;
                $searchTmp['url'] = url::action('topshop_ctl_item@searchItem', $searchParams);
                $searchTmp['label'] = $v;
                $searchArr[] = $searchTmp;
            }
        }

        $pagedata['search_arr'] = $searchArr;
//echo '<pre>';print_r($pagedata);exit();
        $this->contentHeaderTitle = app::get('topshop')->_('商品列表');
        return $this->page('topshop/item/list.html', $pagedata);

    }
    public function storeItem()
    {
        $postData = input::get();
        //特殊符号转义
        $postData['item']['title'] = htmlspecialchars($postData['item']['title']);
        $postData['item']['sub_title'] = htmlspecialchars($postData['item']['sub_title']);
        $postData['item']['shop_id'] = $this->shopId;
        $postData['item']['cat_id'] = $postData['cat_id'];
        $postData['item']['approve_status'] = 'instock';
        if(!implode(',', $postData['item']['shop_cids']))
        {
            return $this->splash('error', '', '店铺分类至少选择一项!', true);
        }
        $postData['item']['shop_cat_id'] = ','.implode(',', $postData['item']['shop_cids']).',';
         //判断店铺是不是自营店铺 gongjiapeng
        $selfShopType = app::get('topshop')->rpcCall('shop.get',array('shop_id'=>$this->shopId));
        if($selfShopType['shop_type']=='self')
        {
            $postData['item']['is_selfshop'] = 1;
        }
        try
        {
            $postData = $this->_checkPost($postData);
            $result = app::get('topshop')->rpcCall('item.create',$postData);
            //echo '<pre>';print_r($result);exit();
            //$url = $postData['return_to_url'];
            if($result)
            {
                $this->sellerlog('保存商品。名称是'.$postData['item']['title']);
                $url = url::action('topshop_ctl_item@itemList');
                $msg = app::get('topshop')->_('保存成功');
                return $this->splash('success', $url, $msg, true);
            }
        }
        catch (Exception $e)
        {
            return $this->splash('error', '', $e->getMessage(), true);
        }
    }

    private function _checkPost($postData)
    {
        if(!$postData['item']['mkt_price'])
        {
            $postData['item']['mkt_price'] = 0;
        }
        if(!$postData['item']['cost_price'])
        {
            $postData['item']['cost_price'] = 0;
        }
        if(!$postData['item']['weight'])
        {
            $postData['item']['weight'] = 0;
        }
        if(!$postData['item']['order_sort'])
        {
            $postData['item']['order_sort'] = 1;
        }

        if(mb_strlen($postData['item']['title'],'UTF8') > 50)
        {
            throw new Exception('商品名称至多50个字符');
        }

        if($postData['spec_value'])
        {
            foreach($postData['spec_value'] as $val)
            {
                if(mb_strlen($val,'UTF8') > 20)
                {
                    throw new Exception('销售属性名称知道20个字符');
                }
            }
        }
        return $postData;
    }


    public function setItemStatus(){

        $postData = input::get();
        try
        {
            if(!$itemId = $postData['item_id'])
            {
                $msg = app::get('topshop')->_('商品id不能为空');
                return $this->splash('error',null,$msg,true);
            }

            if($postData['type'] == 'tosale')
            {
                $shopdata = app::get('topshop')->rpcCall('shop.get',array('shop_id'=>$this->shopId),'seller');
                if( empty($shopdata) || $shopdata['status'] == "dead" )
                {
                    $msg = app::get('topshop')->_('抱歉，您的店铺处于关闭状态，不能发布(上架)商品');
                    return $this->splash('error',null,$msg,true);
                }
                $status = 'onsale';
                $msg = app::get('topshop')->_('上架成功');
            }
            elseif($postData['type'] == 'tostock')
            {
                $status = 'instock';
                $msg = app::get('topshop')->_('下架成功');
            }
            else
            {
                return $this->splash('error',null,'非法操作!', true);
            }

            $params['item_id'] = intval($itemId);
            $params['shop_id'] = intval($this->shopId);
            $params['approve_status'] = $status;
            app::get('topshop')->rpcCall('item.sale.status',$params);
            $queue_params['item_id'] = intval($itemId);
            $queue_params['shop_id'] = intval($this->shopId);
            //发送到货通知的邮件
            if($status == "onsale")
            {
                system_queue::instance()->publish('sysitem_tasks_userItemNotify', 'sysitem_tasks_userItemNotify', $queue_params);
            }
            $this->sellerlog('操作商品状态。改为 '.$status);
            $url = url::action('topshop_ctl_item@itemList');
            return $this->splash('success', $url, $msg, true);
        }
        catch(Exception $e)
        {
            return $this->splash('error',null,$e->getMessage(), true);
        }
    }

    public function deleteItem()
    {
        $postData = input::get();
        //订单状态
        $orderStatus = array('WAIT_BUYER_PAY', 'WAIT_SELLER_SEND_GOODS', 'WAIT_BUYER_CONFIRM_GOODS');

        try
        {
            if(!$itemId = $postData['item_id'])
            {
                $msg = app::get('topshop')->_('商品id不能为空');
                return $this->splash('error',null,$msg, true);
            }

            //判断商品所在订单的状态
            $orderParams = array();
            $orderParams['item_id'] = (int)$itemId;
            $orderParams['fields'] = 'status';
            $orderList = app::get('topshop')->rpcCall('trade.order.list.get', $orderParams);
            if($orderList)
            {
                $orderArrStatus = array_column($orderList, 'status');
                foreach ($orderStatus as $status)
                {
                    if(in_array($status, $orderArrStatus))
                    {
                        $msg = app::get('topshop')->_('商品存在未完成的订单，不能删除');
                        return $this->splash('error',null,$msg, true);
                    }
                }
            }

            app::get('topshop')->rpcCall('item.delete',array('item_id'=>intval($itemId),'shop_id'=>intval($this->shopId)));
        }
        catch(Exception $e)
        {
            return $this->splash('error',null, $e->getMessage(), true);
        }
        $this->sellerlog('删除商品。 商品ID是'.$itemId);
        return $this->splash('success',null,'删除成功', true);
    }

    public function ajaxGetBrand($cat_id)
    {
        $params['shop_id'] = $this->shopId;
        $params['cat_id'] = input::get('cat_id');
        try
        {
            $brand = app::get('topshop')->rpcCall('category.get.cat.rel.brand',$params);
        }
        catch(Exception $e)
        {
            return $this->splash('error',null, $e->getMessage(), true);
        }
        return response::json($brand);exit;
    }

    public function ajaxGetDlytmpls()
    {
        // 获取物流模板列表
        $tmpParams = array(
            'shop_id' => $this->shopId,
            'status' => 'on',
            'fields' => 'shop_id,name,template_id',
        );
        $pagedata = app::get('topshop')->rpcCall('logistics.dlytmpl.get.list',$tmpParams);
        return response::json($pagedata);exit;
    }

    // 批量更新商品的运费模板
    public function updateItemDlytmpl()
    {
        $postData = input::get();

        try
        {
            if(!$itemIds = $postData['itemids'])
            {
                $msg = app::get('topshop')->_('请至少选择一个商品！');
                return $this->splash('error',null,$msg, true);
            }
            if(!$dlytmpId = $postData['dlytmpl_id'])
            {
                $msg = app::get('topshop')->_('没有选择运费模板！');
                return $this->splash('error',null,$msg, true);
            }
            foreach($itemIds as $itemId)
            {
                if($itemId)
                {
                    app::get('topshop')->rpcCall('item.update.dlytmpl', array('item_id'=>intval($itemId), 'dlytmpl_id'=>intval($dlytmpId),'shop_id'=>intval($this->shopId)));
                }
            }
        }
        catch(Exception $e)
        {
            return $this->splash('error',null, $e->getMessage(), true);
        }
        $this->sellerlog('批量更新商品关联的运费模板。');
        $url = url::action('topshop_ctl_item@itemList');
        return $this->splash('success',$url,'更新运费模板成功', true);
    }

}


