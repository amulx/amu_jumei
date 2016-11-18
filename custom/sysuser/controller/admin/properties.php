<?php

class sysuser_ctl_admin_properties extends desktop_controller {

	public function index()
    {
        return $this->finder(
            'sysuser_mdl_properties',
            array(
                'title'=>app::get('sysuser')->_('属性列表'),
                'actions'=>array(
                    array(
                        'label'=>app::get('sysuser')->_('添加子属性'),
                        'href'=>'?app=sysuser&ctl=admin_properties&act=create','target'=>'dialog::{title:\''.app::get('sysuser')->_('添加子属性').'\',width:500,height:350}'
                    ),
                )
            )
        );
    }

    /**
     * 添加属性页面
     */
    public function create($propertiesId)
    {
        if( $propertiesId )
        {
            $propertiesInfo = app::get('sysuser')->model('properties')->getRow('*',array('properties_id' => $propertiesId));
            $pagedata['propertiesInfo'] = $propertiesInfo;
        }
        return view::make('sysuser/admin/properties.html', $pagedata);
    }

    //添加属性
    public function saveProperties()
    {
        $this->begin();
        $postData = input::get();
        // $data = $_POST;
        $ojbProperties = app::get('sysuser')->model('properties');
        $result = $ojbProperties->save($postData);
        $msg = $result ? app::get('sysuser')->_('保存成功') :app::get('sysuser')->_('保存失败');  
        $this->adminlog("编辑属性[{$postData['properties_name']}]", $result ? 1 : 0);
        $this->end($msg);
    }
}