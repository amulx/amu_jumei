<?php
/**
 * coupon.php 会员优惠券管理
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class topwap_ctl_member_coupon extends topwap_ctl_member {
    
    public $limit = 10;
    
    // 会员优惠券展示，优先展示未使用的
    public function index()
    {
        theme::setTitle('我的优惠券');
        $filter = input::get();
        $pagedata = $this->_getCouponList($filter);
        $pagedata['title'] = app::get('topwap')->_('我的优惠券');
        return $this->page('topwap/member/coupon/index.html', $pagedata);
    }
    
    
    public function ajaxCouponList()
    {
        try {
            $filter = input::get();
            $pagedata = $this->_getCouponList($filter);
            if($pagedata['couponList']){
                $data['html'] = view::make('topwap/member/coupon/list.html',$pagedata)->render();
            }else{
                $data['html'] = view::make('topwap/empty/coupon.html',$pagedata)->render();
            }
            
            $data['pages'] = $pagedata['pages'];
            $data['pagers'] = $pagedata['pagers'];
            $data['is_valid'] = $filter['is_valid'];
            $data['success'] = true;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }
        
        return response::json($data);exit;
    }
    
    // 获取优惠券列表
    protected function _getCouponList($filter)
    {
        if(!$filter['pages'])
        {
             $filter['pages'] = 1;
        }
        $filter['is_valid']=isset($filter['is_valid'])?$filter['is_valid']:'no';
        $filter['is_valid'] = $this->__getValid($filter);
        $pageSize = $this->limit;
        $params = array(
                'page_no' => $filter['pages'],
                'page_size' => $pageSize,
                'fields' =>'*',
                'user_id'=>userAuth::id(),
                'is_valid'=>$filter['is_valid'],
        );
        
        $couponListData = app::get('topwap')->rpcCall('user.coupon.list', $params, 'buyer');
        
        $count = $couponListData['count'];
        $couponList = $couponListData['coupons'];
        $current = $filter['pages'] ? $filter['pages'] : 1;
        $pagedata['couponList']= $couponList;
        $pagedata['count'] = $count;
        $pagedata['pages'] = $current;
        if($count>0) $total = ceil($count/$pageSize);
        $pagedata['pagers'] = array(
                'link'=>'',
                'current'=>$current,
                'total'=>$total,
                'is_valid'=>input::get('is_valid')?input::get('is_valid'):'no',
        );
        return $pagedata;
        
    }
    private function __getValid($filter)
    {
        switch ($filter['is_valid']) {
            case '0':
                $filter['is_valid']=1;
                break;
            case '1':
                $filter['is_valid']=0;
                break;
            case '2':
                $filter['is_valid']=2;
                break;
            default:
                $filter['is_valid']=1;
                break;
        }
        return $filter['is_valid'];
    }
}
 