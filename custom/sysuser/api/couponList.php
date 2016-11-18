<?php
/**
 * ShopEx licence
 * - user.coupon.list
 * - 用于获取用户优惠券列表
 * @copyright Copyright (c) 2005-2016 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license   http://ecos.shopex.cn/ ShopEx License
 * @link      http://www.shopex.cn/
 * @author    shopex 2016-05-27
 */
class sysuser_api_couponList {

    /**
     * api接口的名称
     * @var string
     */
    public $apiDescription = '获取用户优惠券列表';

    /**
     * 定义API传入的应用级参数
     * @desc 用于在调用接口前，根据定义的参数，过滤必填参数是否已经参入，并且定义参数的数据类型，参数是否必填，参数的描述
     * @return array 返回传入参数
     */
    public function getParams()
    {
        //接口传入的参数
        $return['params'] = array(
            'page_no'   => ['type'=>'int',        'valid'=>'',         'title'=>'页码',      'example'=>'', 'desc'=>'分页当前页数,默认为1'],
            'page_size' => ['type'=>'int',        'valid'=>'',         'title'=>'每页条数',   'example'=>'', 'desc'=>'每页数据条数,默认20条'],
            'fields'    => ['type'=>'field_list', 'valid'=>'',         'title'=>'字段列表',   'example'=>'', 'desc'=>'需要的字段'],
            'orderBy'   => ['type'=>'string',     'valid'=>'',         'title'=>'排序',      'example'=>'', 'desc'=>'排序'],
            'user_id'   => ['type'=>'int',        'valid'=>'required', 'title'=>'用户ID',    'example'=>'', 'desc'=>'用户ID必填'],
            'shop_id'   => ['type'=>'int',        'valid'=>'',         'title'=>'店铺ID',    'example'=>'', 'desc'=>'店铺ID'],
            'is_valid'  => ['type'=>'int',        'valid'=>'',         'title'=>'有效优惠券', 'example'=>'', 'desc'=>'获取是否有效的参数'],
            'platform'  => ['type'=>'string',     'valid'=>'',         'title'=>'使用平台',   'example'=>'', 'desc'=>'优惠券使用平台'],
        );

        return $return;
    }

    /**
     * 获取单个商品的详细信息
     * @desc 用于获取单个商品的详细信息
     * @return string coupon_code 优惠券号码
     * @return int user_id 会员ID
     * @return int shop_id 店铺ID
     * @return int coupon_id 会员优惠券ID
     * @return string obtain_desc 领取方式
     * @return timestamp obtain_time 优惠券获得时间
     * @return int tid 订单ID
     * @return string is_valid 会员优惠券是否当前可用(0:已使用；1:有效；2:过期)
     * @return string used_platform 使用平台(0:；1:；2:)
     * @return timestamp canuse_start_time 生效时间
     * @return timestamp canuse_end_time 失效时间
     * @return number limit_money 满足条件金额
     * @return number deduct_money 优惠金额
     * @return string coupon_name 优惠券名称
     * @return string coupon_desc 优惠券描述
     */
    public function couponList($params)
    {
        $objMdlUserCoupon = app::get('sysuser')->model('user_coupon');
        if(!$params['fields'])
        {
            $params['fields'] = '*';
        }

        if($params['shop_id'])
        {
            $filter = array('user_id'=>$params['user_id'], 'shop_id'=>$params['shop_id']);
        }
        else
        {
            $filter = array('user_id'=>$params['user_id']);
        }
        // 平台未选择则默认全选
        if( $params['platform'] == 'pc' )
        {
            $filter['used_platform'] = array('0', '1');
        }
        elseif( $params['platform'] == 'wap' )
        {
            $filter['used_platform'] = array('0', '2');
        }

        if(isset($params['is_valid']))
        {
            $filter['is_valid'] = $params['is_valid'];
        }

        $itemCount = $objMdlUserCoupon->count($filter);
        $pageTotal = ceil($itemCount/$params['page_size']);
        $page =  $params['page_no'] ? $params['page_no'] : 1;
        $limit = $params['page_size'] ? $params['page_size'] : 10;
        $currentPage = ($pageTotal < $page) ? $pageTotal : $page; //防止传入错误页面，返回最后一页信息
        $offset = ($currentPage-1) * $limit;
        $orderBy  = $params['orderBy'] ? $params['orderBy'] : 'obtain_time DESC';
        $aData = $objMdlUserCoupon->getList($params['fields'], $filter, $offset, $limit, $orderBy);
        $couponData = $this->__getUserCouponList($aData);

        //在列表追加优惠卷 2016.9.6 czh
        $mdlRegcoupon = app::get('sysuser')->model('user_regcoupon');
        $rData = $mdlRegcoupon->getList($params['fields'], $filter, $offset, $limit, $orderBy);
        $regcouponCount = $mdlRegcoupon->count($filter);
        $couponData = array_merge($couponData,$rData);
        $itemCount =  $itemCount + $regcouponCount;
        // var_dump($couponData);exit;

        $itemData = array(
                'coupons' => $couponData,
                'count' => $itemCount,
            );

        return $itemData;
    }

    private function __getUserCouponList($userCouponList)
    {
        $nowTime = time();
        foreach($userCouponList as &$v)
        {
            $apiData['user_id'] = $v['user_id'];
            $apiData['coupon_id'] = $v['coupon_id'];
            $couponRule = app::get('sysuser')->rpcCall('promotion.coupon.get', $apiData);
            $v['canuse_start_time'] = $couponRule['canuse_start_time'];
            $v['canuse_end_time'] = $couponRule['canuse_end_time'];
            $v['limit_money'] = $couponRule['limit_money'];
            $v['deduct_money'] = $couponRule['deduct_money'];
            $v['coupon_name'] = $couponRule['coupon_name'];
            $v['coupon_desc'] = $couponRule['coupon_desc'];
        }
        return $userCouponList;
    }

}
