<?php

/**
 * 投诉单相关
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_member_complaints extends topwap_ctl_member {

    private $pageSize = 6;

    protected $_complaintsType = [
        '商品问题',
        '配送问题',
        '支付问题',
        '促销活动问题',
        '账户问题',
        '发票问题',
        '系统问题',
        '退货/换货问题',
        '投诉工作人员',
        '其他'
    ];

    /**
     * @brief 投诉单列表页
     *
     */
    public function complaintsList()
    {
        theme::setTitle('我的投诉');
        $userId = userAuth::id();
        $pageSize = $this->pageSize;
        $pageNumber = input::get('pages', 1);

        $pagedata['complaintsList'] = kernel::single('topwap_data_member_complaints')->getList($userId, $pageSize, $pageNumber);
        if( $pagedata['complaintsList']['count'] )
        {
            $pagedata['totalPages']  = ceil($pagedata['complaintsList']['count']/$pageSize);
        }
        else
        {
            $pagedata['totalPages'] = 0;
        }

        if(request::ajax())
        {
            return view::make('topwap/member/complaints/list-main.html', $pagedata);
        }

        return $this->page('topwap/member/complaints/list.html', $pagedata);
    }

    public function complaintsView()
    {
        theme::setTitle('投诉详情');
        $userId = userAuth::id();
        if( input::get('complaints_id') )
        {
            $filter['complaints_id'] = input::get('complaints_id');
        }

        if( input::get('oid') )
        {
            $filter['oid'] = input::get('oid');
        }

        $fields = 'complaints_id,image_url,shop_id,user_id,tid,status,tel,complaints_type,content,memo,buyer_close_reasons,created_time,oid';
        $complaints = kernel::single('topwap_data_member_complaints')->getRow($filter, $userId, $fields);
        if( $complaints['image_url'] )
        {
            $complaints['image_url'] = explode(',',$complaints['image_url']);
        }
        $pagedata['complaints'] = $complaints;

        return $this->page('topwap/member/complaints/detail.html', $pagedata);
    }

    public function complaintsShopFormView()
    {
        theme::setTitle('发起投诉');
        $oid = input::get('oid');
        $tid = input::get('tid');
        $pagedata['oid'] = $oid;
        $pagedata['tid'] = $tid;
        foreach( $this->_complaintsType as $k=>$val )
        {
            $text = app::get('topwap')->_($val);
            $complaintsType[$k]['value'] = $text;
            $complaintsType[$k]['text'] = $text;
        }
        $pagedata['complaintsType'] = json_encode($complaintsType);
        return $this->page('topwap/member/complaints/form.html', $pagedata);
    }

    public function complaintsPostData()
    {
        try
        {
            $data = input::get();
            $validator = validator::make(
                [$data['oid'],$data['complaints_type'],$data['tel'],$data['content']],
                ['required','required','required|mobile','required|min:5|max:300'],
                ['参数错误','投诉类型不能为空','联系方式不能为空|请填写正确的手机号码','问题描述不能为空|投诉原因5-300个字|投诉原因5-300个字']
            );
            $validator->newFails();
            $result = app::get('topwap')->rpcCall('trade.order.complaints.create', $data,'buyer');
        }
        catch(\LogicException $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }

        if( $data['tid'] )
        {
            $url = url::action('topwap_ctl_member_trade@detail',['tid'=>$data['tid']]);
        }

        $msg = app::get('topwap')->_('投诉提交成功');
        return $this->splash('success',$url,$msg,true);
    }

    public function complaintsCloseFormView()
    {
        theme::setTitle('撤销投诉');
        $complaintsId = input::get('complaints_id');
        $pagedata['complaints_id'] = $complaintsId;
        return $this->page('topwap/member/complaints/closeForm.html', $pagedata);
    }

    public function closeComplaints()
    {
        $data['complaints_id'] = input::get('complaints_id');
        $data['buyer_close_reasons'] = input::get('buyer_close_reasons');
        try
        {
            $validator = validator::make(
                [$data['buyer_close_reasons']],
                ['required|min:5|max:200'],
                ['撤销原因不能为空|撤销原因5-200个字|撤销原因5-200个字']
            );
            $validator->newFails();
            $pagedata = app::get('topwap')->rpcCall('trade.order.complaints.buyer.close', $data,'buyer');
        }
        catch( LogicException $e )
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }

        $url = url::action('topwap_ctl_member_complaints@complaintsView',['complaints_id'=>$data['complaints_id']]);
        $msg = app::get('topwap')->_('订单投诉撤销成功');
        return $this->splash('success',$url,$msg,true);
    }
}

