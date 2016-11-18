<?php
class topwap_ctl_member_address extends topwap_ctl_member{
    public function addrList()
    {
        theme::setTitle('收货地址');
        $params['user_id'] = userAuth::id();
        //会员收货地址
        $userAddrList = app::get('topwap')->rpcCall('user.address.list',$params,'buyer');
        $count = $userAddrList['count'];
        $userAddrList = $userAddrList['list'];
        foreach ($userAddrList as $key => $value) {
            $userAddrList[$key]['area'] = explode(":",$value['area'])[0];
        }
        $pagedata['userAddrList'] = $userAddrList;
        $pagedata['userAddrCount'] = $count;
        return $this->page('topwap/member/address/list.html', $pagedata);
    }

    public function newAddress()
    {
        theme::setTitle('收货地址');
        $pagedata['next_page'] = request::server('HTTP_REFERER');
        return $this->page('topwap/member/address/addr.html',$pagedata);
    } 

    public function updateAddr()
    {
        theme::setTitle('收货地址');
        if($addrId = input::get('addr_id'))
        {
            $params['addr_id'] = $addrId;
            $params['user_id'] = userAuth::id();
            $addrInfo = app::get('topwap')->rpcCall('user.address.info',$params);
            list($regions,$region_id) = explode(':', $addrInfo['area']);
            $addrInfo['area'] = $regions;
            $addrInfo['region_id'] = str_replace('/', ',', $region_id);

            $pagedata['addrInfo'] = $addrInfo;
            $pagedata['addrdetail'] = $addrInfo['area'].'/'.$addrInfo['addr'];
        }
        $pagedata['next_page'] = request::server('HTTP_REFERER');
        return $this->page('topwap/member/address/addr.html',$pagedata);
    }

    public function saveAddress()
    {
        $postdata = utils::_filter_input(input::get());
        $nextPage = url::action('topwap_ctl_member_address@addrList');
        if($postdata['next_page'])
        {
            $nextPage = $postdata['next_page'];
            unset($postdata['next_page']);
        }
        $postdata['area'] = input::get('area');
        $postdata['user_id'] = userAuth::id();

        if(empty($postdata['def_addr']))
        {
            $postdata['def_addr']=0;
        }

        $area = app::get('topwap')->rpcCall('logistics.area',array('area'=>$postdata['area']));
        $validator = validator::make(
            [
                'area' => $area,
                'addr' => $postdata['addr'] ,
                'name' => $postdata['name'],
                'mobile' => $postdata['mobile'],
                'user_id' =>$postdata['user_id'],
                'zip' =>$postdata['zip'],
            ],
            [
                'area' => 'required|max:20',
                'addr' => 'required',
                'name' => 'required',
                'mobile' => 'required|mobile',
                'user_id' => 'required',
                'zip' =>'numeric|max:999999',
            ],
            [
                'area' => '地区不存在!',
                'addr' => '会员街道地址必填!',
                'name' => '收货人姓名未填写!',
                'mobile' => '手机号码必填!|手机号码格式不正确!',
                'user_id' => '缺少参数!',
                'zip' =>'邮编必须为6位数的整数|邮编最大为999999',
            ]
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();

            foreach( $messages as $error )
            {
                return $this->splash('error',null,$error[0]);
            }
        }

        $areaId =  rtrim(str_replace(",","/", $postdata['area']),'/');
        $postdata['area'] = $area . ':' . $areaId;

        try
        {
            app::get('topwap')->rpcCall('user.address.add',$postdata);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }

        
        $msg = app::get('topwap')->_('地址保存成功');
        return $this->splash('success',$nextPage,$msg);
    }

    public function setDefault()
    {
        $postdata =utils::_filter_input(input::get());
        $nextPage = url::action('topwap_ctl_member_address@addrList');
        if($postdata['next_page'])
        {
            $nextPage = $postdata['next_page'];
            unset($postdata['next_page']);
        }
        $postdata['user_id'] = userAuth::id();
        try
        {
            app::get('topm')->rpcCall('user.address.setDef',$postdata);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }

        $msg = app::get('topwap')->_('设为默认成功');
        return $this->splash('success',$nextPage,$msg);

    }

    public function removeAddr()
    {
        $postdata =utils::_filter_input(input::get());
        $nextPage = url::action('topwap_ctl_member_address@addrList');
        if($postdata['next_page'])
        {
            $nextPage = $postdata['next_page'];
            unset($postdata['next_page']);
        }
        $postdata['user_id'] = userAuth::id();

        try
        {
            app::get('topm')->rpcCall('user.address.del',$postdata);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }

        $msg = app::get('topwap')->_('地址删除成功');
        return $this->splash('success',$nextPage,$msg);
    }
}


