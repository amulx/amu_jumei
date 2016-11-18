<?php

class sysrate_ctl_buyersaid extends desktop_controller {

    public function index()
    {
        return $this->finder(
            'sysrate_mdl_buyersaid',
            array(
                'title'=>app::get('sysrate')->_('买家说'),
                'use_buildin_delete' => true,
                'actions'=>array(
                    array(
                        'label'=>app::get('sysrate')->_('添加买家说'),
                        'href'=>'?app=sysrate&ctl=buyersaid&act=create','target'=>'dialog::{title:\''.app::get('sysrate')->_('添加子属性').'\',width:800,height:550}'
                    ),
                )
            )
        );
    }

    /**
     * 添加编辑页面
     */
    public function create($saidId)
    {
        if( $saidId )
        {
            $buyerInfo = app::get('sysrate')->model('buyersaid')->getRow('*',array('said_id' => $saidId));
            $pagedata['buyerInfo'] = $buyerInfo;
        }
        return view::make('sysrate/buyersaid/create.html', $pagedata);
    }

    //添加与修改
    public function saveBuyerSaid()
    {
        $this->begin();
        $postData = input::get();
        if(!$postData['said_id']){
            $postData['created_time'] = time();
        }
        $ojbBuyerSaid = app::get('sysrate')->model('buyersaid');
        $result = $ojbBuyerSaid->save($postData);
        $msg = $result ? app::get('sysrate')->_('保存成功') :app::get('sysrate')->_('保存失败');  
        $this->adminlog("编辑", $result ? 1 : 0);
        $this->end($msg);
    }
}

