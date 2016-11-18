<?php

/**
 * point.php 会员积分成长值
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_member_point extends topwap_ctl_member {

    public $limit = 20;
    
    public function point()
    {
        theme::setTitle('我的积分');
        $filter = input::get();
        $pagedata = $this->__getPoints($filter);
        if (!$pagedata) return redirect::back();
        $pagedata['title'] = app::get('topwap')->_('我的积分');
        
        return $this->page('topwap/member/point/index.html',$pagedata);
    }
    
    // ajax输出数据
    public function ajaxPonint()
    {
        $filter = input::get();
        try {
            $pagedata = $this->__getPoints($filter);
            $data['html'] = view::make('topwap/member/point/list.html',$pagedata)->render();
            $data['pages'] = $pagedata['pages'];
            $data['success'] = true;
            
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }
        
        return response::json($data);exit;
    }
    
    public function __getPoints($filter)
    {
        if(!$filter['pages'])
        {
            $filter['pages'] = 1;
        }
        $pageSize = $this->limit;
        $current = ($filter['pages'] >=1 || $filter['pages'] <= 100) ? $filter['pages'] : 1;
    
        $params = array(
                'page_no' => intval($current),
                'page_size' => intval($pageSize),
                'orderBy' => 'modified_time desc'
        );
        $data = app::get('topwap')->rpcCall('user.pointGet',$params);
    
        //总页数(数据总数除每页数量)
        $pagedata['userpoint'] = $data['datalist']['user'];
        $pagedata['pointdata'] = $data['datalist']['point'];
        if($data['totalnum'] > 0) $total = ceil($data['totalnum']/$pageSize);
        if($total<$filter['pages']) return array();
        $pagedata['count'] = $data['totalnum'];
        $pagedata['pages'] = $filter['pages'];
        $pagedata['pagers'] = array(
                'link'=>'',
                'current'=>$current,
                'total'=>$total,
        );
        return $pagedata;
    }

}
 