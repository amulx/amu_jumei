<?php
class topwap_cart{

    //获取购物车的信息，这里是获取购物车的一大堆数据，包括商品详情，但是不包括商家的促销信息
    public function getCartInfo()
    {

        if( userAuth::check())
        {
            $cartData = app::get('topwap')->rpcCall('trade.cart.getCartInfo', array('platform'=>'wap','user_id'=>userAuth::id()), 'buyer');
        }
        else
        {
            $obj = kernel::single('topwap_cart_offline');
            $cartData = $obj->getCartInfo();
        }

        return $cartData;
    }


    //获取购物车的数量，包括数量总量和种类总量
    public function getCartCount()
    {
        if( userAuth::check() )
        {
            $countData = app::get('topwap')->rpcCall('trade.cart.getCount', ['user_id' => userAuth::id()], 'buyer');
        }
        else
        {
            $obj = kernel::single('topc_cart_offline');
            $countData = $obj->getCartCount();
        }
        return $countData;

    }

    public function setCookieCartCount($countInfo)
    {
        userAuth::syncCookieWithCartNumber($countInfo['number']);
        userAuth::syncCookieWithCartVariety($countInfo['variety']);
        return true;
    }

    //添加购物车
    public function addCart($params)
    {
        if( userAuth::check() )
        {
            $data = app::get('topwap')->rpcCall('trade.cart.add', $params, 'buyer');
        }
        else
        {
            //这里需要判断obj_type只能是item
            //这里还需要判断购物车数量，数量待定
            kernel::single('topwap_cart_check')->addCheck($params);
            $skuId = $params['sku_id'];
            $quantity = $params['quantity'];
            $obj = kernel::single('topwap_cart_offline');
            $data = $obj->addCart($skuId, $quantity);
        }
        return $data;
    }

    public function deleteCart($params)
    {
        if( userAuth::check() )
        {
            $res = app::get('topwap')->rpcCall('trade.cart.delete',$params);
        }
        else
        {
            $skuId = $params['cart_id'];
            $obj = kernel::single('topwap_cart_offline');
            $res = $obj->removeCart($skuId);
        }
        return $res;
    }

    public function updateCart($params)
    {
        if( userAuth::check() )
        {
            //$res = app::get('topwap')->rpcCall('trade.cart.delete',$params);
            $data = app::get('topwap')->rpcCall('trade.cart.update',$params);
        }
        else
        {
            //这里需要判断obj_type只能是item
            //这里还需要判断购物车数量，数量待定
            $skuId = $params['cart_id'];
            $quantity = $params['totalQuantity'];
            $isChecked = $params['is_checked'];

            $obj = kernel::single('topwap_cart_offline');
            $data = $obj->updateCart($skuId, $quantity, $isChecked);
        }
        return $data;
    }

    //用于登陆的时候合并购物车
    public function mergeCart()
    {
        $cookieCart = kernel::single('topwap_cart_offline')->getCart();
        $cookieCartCount = kernel::single('topwap_cart_offline')->getCartCount($cookieCart);
        $countData = app::get('topwap')->rpcCall('trade.cart.getCount', ['user_id' => userAuth::id()], 'buyer');

        if($cookieCartCount['variety'] > 0 )
        {
            if($countData['variety'] + $cookieCartCount['variety'] > 50)
            {
                //这里要清空购物车然后再合并
                app::get('topwap')->rpcCall('trade.cart.delete', array('user_id'=>userAuth::id()));
            }

            foreach($cookieCart as $cart )
            {
                $params = [
                    'sku_id' => $cart['sku_id'],
                    'obj_type' => 'item',
                    'quantity' => $cart['quantity'],
                    'mode' => 'cart',
                    'user_id' => userAuth::id(),
                    ];

                try
                {
                    $data = $this->addCart($params);
                }
                catch(Exception $d)
                {
                    //这里把不能加入购物车的商品就忽略掉了。
                    continue;
                }
            }
            kernel::single('topwap_cart_offline')->cleanCart();
        }
        return true;
    }


}
