<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class topwap_cart_skuInfo
{

    public function getSkuInfo($skuIds)
    {
        $skuList = app::get('topwap')->rpcCall('sku.list', ['sku_ids'=>implode($skuIds, ','), 'fields'=>'*']);
        return $skuList;
    }

    public function getShopInfo($shopIds)
    {
        if(count($shopIds) == 0)
        {
            return [];
        }
        $shopList = app::get('topwap')->rpcCall('shop.get.list', ['shop_id' => implode($shopIds, ','),'fields' => 'shop_id,shop_name,shop_type']);
        return $shopList;
    }

    public function genCartObject($shopList, $skuList, $cartInfo, $countAll = false)
    {

        //totalCart
        $totalCart = array('totalWeight' => 0, 'number'=>0, 'totalPrice'=>0, 'totalAfterDiscount'=>0,'totalDiscount'=>0, 'variety'=>0);

        $resultCartData = array();

        foreach($shopList as $shop)
        {
            $shopId = $shop['shop_id'];
            $resultCartData[$shopId]['shop_id'] = $shopId;
            $resultCartData[$shopId]['shop_name'] = $shop['shop_name'];
            $resultCartData[$shopId]['shop_type'] = $shop['shop_type'];
            $resultCartData[$shopId]['basicPromotionListInfo'] = [];
            $resultCartData[$shopId]['usedCartPromotion'] = [];
            $resultCartData[$shopId]['usedCartPromotionWeight'] = 0;
            $resultCartData[$shopId]['cartCount'] = [ 'total_weight' => 0, 'itemnum' => 0, 'total_fee'=>0, 'total_discount'=>0, 'total_coupon_discount'=>null];
        }

        //resultCartData
        foreach($skuList as $sku)
        {
            $shopId = $sku['item']['shop_id'];
            $skuId  = $sku['sku_id'];
            $itemId = $sku['item']['item_id'];
            $resultCartData[$shopId]['object'][$skuId]['cart_id']             = $skuId;
            $resultCartData[$shopId]['object'][$skuId]['obj_type']            = 'item';
            $resultCartData[$shopId]['object'][$skuId]['item_id']             = $itemId;
            $resultCartData[$shopId]['object'][$skuId]['sku_id']              = $skuId;
            $resultCartData[$shopId]['object'][$skuId]['user_id']             = null;
            $resultCartData[$shopId]['object'][$skuId]['selected_promotion']  = 0;
            $resultCartData[$shopId]['object'][$skuId]['cat_id']              = $sku['item']['cat_id'];
            $resultCartData[$shopId]['object'][$skuId]['sub_stock']           = $sku['item']['sub_stock'];
            $resultCartData[$shopId]['object'][$skuId]['spec_info']           = $sku['spec_info'];
            $resultCartData[$shopId]['object'][$skuId]['bn']                  = $sku['bn'];
            $resultCartData[$shopId]['object'][$skuId]['store']               = $sku['store'];
            $resultCartData[$shopId]['object'][$skuId]['status']              = $sku['item']['approve_status'];
            $resultCartData[$shopId]['object'][$skuId]['price']['discount_price'] = 0;
            $resultCartData[$shopId]['object'][$skuId]['price']['price']      = $sku['price'];
            $resultCartData[$shopId]['object'][$skuId]['price']['total_price'] = $sku['price'] * $cartInfo[$skuId]['quantity'];
            $resultCartData[$shopId]['object'][$skuId]['quantity']            = $cartInfo[$skuId]['quantity'];
            $resultCartData[$shopId]['object'][$skuId]['title']               = $sku['title'];
            $resultCartData[$shopId]['object'][$skuId]['image_default_id']    = $sku['item']['image_default_id'];
            $resultCartData[$shopId]['object'][$skuId]['weight']              = $sku['weight'];
            $resultCartData[$shopId]['object'][$skuId]['is_checked']          = $cartInfo[$skuId]['is_checked'];
            $resultCartData[$shopId]['object'][$skuId]['promotions']          = false;
            if($sku['item']['approve_status'] == 'onsale' && $sku['store'] > 0 && $sku['status'] == 'normal')
            {
                $resultCartData[$shopId]['object'][$skuId]['valid']           = true;
            }
            else
            {
                $resultCartData[$shopId]['object'][$skuId]['valid']           = false;
            }

            $resultCartData[$shopId]['cartByPromotion'][0]['cart_ids'][$skuId] = $skuId;

            $resultCartData[$shopId]['cartCount']['total_weight'] += $sku['weight'];
            if($cartInfo[$skuId]['is_checked'] == 1)
            {
                $resultCartData[$shopId]['cartCount']['itemnum'] += $cartInfo[$skuId]['quantity'];
                $resultCartData[$shopId]['cartCount']['total_fee'] += $sku['price'] * $cartInfo[$skuId]['quantity'];
            }

            //totalCart
            if($cartInfo[$skuId]['is_checked'] == 1 || $countAll)
            {
                $totalCart['totalWeight'] += $sku['weight'];
                $totalCart['totalPrice'] += $sku['price'] * $cartInfo[$skuId]['quantity'];
                $totalCart['totalAfterDiscount'] += $sku['price'] * $cartInfo[$skuId]['quantity'];
                $totalCart['number'] += $cartInfo[$skuId]['quantity'];
                $totalCart['variety'] += 1;
            }
        }

        return ['resultCartData'=>$resultCartData, 'totalCart'=>$totalCart];
    }

    public function genCartInfo($cookieCart, $countAll = false)
    {
        if(count($cookieCart) == 0)
            return array();
        $skuIds = array_column($cookieCart, 'sku_id');
        $skuList = $this->getSkuInfo($skuIds);
        $shopeIds  = [];
        foreach($skuList as $sku)
        {
            $shopId = $sku['item']['shop_id'];
            if(!in_array($shopId, $shopIds))
                $shopIds[] = $shopId;
        }
        if(count($shopIds) == 0)
            return [];
        $shopList = $this->getShopInfo($shopIds);
        $cartObj = $this->genCartObject($shopList, $skuList, $cookieCart, $countAll);

        return $cartObj;
    }


}
