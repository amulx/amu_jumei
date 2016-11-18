<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2012 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class topc_ctl_default extends topc_controller
{
    public function index()
    {
        $GLOBALS['runtime']['path'][] = array('title'=>app::get('topc')->_('首页'),'link'=>kernel::base_url(1));
        $this->setLayoutFlag('index');

        //        throw new Exception();
        return $this->page();
    }

    public function redirect()
    {
        return view::make('topc/redirect.html', $pagedata);
    }

    //添加城市合作留言进数据库
    public function message(){
        $postData['name']= input::get('name');
        $postData['mobile']= input::get('mobile');
        $areaId= input::get('area');
        $postData['modified'] = time();

        //数据检测
        $validator = validator::make(
            ['name'=>$postData['name'],
             'mobile'=>$postData['mobile'],
             'area' => $areaId,
            ],
            ['name'=>'required','mobile' => 'required|mobile','area' => 'required'],
            ['name'=>'您的姓名不能为空!','mobile' => '您的手机号不能为空!|手机号格式不对!','area' => '地区不存在!']
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();

            foreach( $messages as $error )
            {
                return $this->splash('error',null,$error[0]);
            }
        }

        $areas = app::get('topc')->rpcCall('logistics.area',array('area'=>$areaId));
        // $postData['area'] = $areas. ':' .$areaId;
        $postData['area'] = $areas;
        try
        {
            $result = app::get('syscontent')->model('partnershipe')->insert($postData);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }
        $msg = app::get('syscontent')->_('提交成功');
        return $this->splash('success',null,$msg);
    }
}
