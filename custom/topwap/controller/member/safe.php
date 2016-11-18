<?php

/**
 * safe.php 会员安全中心
 *
 * @author     Xiaodc
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_member_safe extends topwap_ctl_member {

    public $checkType = ['setmobile','setloginpwd'];
    // 登录密码
    public function verify()
    {
        theme::setTitle('验证登录密码');
        $postData = utils::_filter_input(input::get());
        //跨站攻击
        if(in_array($postData['checkType'], $this->checkType))
        {
            $pagedata['checkType']= $postData['checkType'];
        }
        else
        {
            $pagedata['checkType']= 'setmobile';
        }
        $pagedata['title'] = app::get('topwap')->_('验证登录密码');
        
        return $this->page('topwap/member/safe/checkpwd.html',$pagedata);
    }
    
    // 验证登录密码
    public function CheckSetInfo()
    {
        $postData =utils::_filter_input(input::get());
        $msg = '';
        $validator = validator::make(
                ['password' => $postData['password']],
                ['password' => 'required'],
                ['password' => '密码不能为空!']
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();
            foreach( $messages as $error )
            {
                return $this->splash('error',null,$error[0]);
            }
        }
        $data['password'] = $postData['password'];
        try
        {
            app::get('topwap')->rpcCall('user.login.pwd.check',$data,'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }
        $this->setSessionValue('safe-check-login-pwd', true);
        
        $url = '';
        if(in_array($postData['checkType'], $this->checkType))
        {
            $checkType= $postData['checkType'];
        }
        else
        {
            $checkType= 'setmobile';
        }
        
        if($checkType == 'setmobile')
        {
            $url = url::action('topwap_ctl_member_safe@setUserMobile');
        }
        
        if($checkType == 'setloginpwd')
        {
            $url = url::action('topwap_ctl_member_safe@setUserPwd');
        }
        // 根据前端要求将成功提示置空
        // $msg = app::get('topwap')->_('密码验证成功');
        return $this->splash('success',$url,$msg,true);
    }
    
    // 修改手机号
    public function viewUserMobile()
    {
        theme::setTitle('已绑定手机号');
        $userInfo = userAuth::getUserInfo();
        $pagedata['user'] = $userInfo;
        $pagedata['title'] = app::get('topwap')->_('已绑定手机号');
        return $this->page('topwap/member/safe/viewusermobile.html', $pagedata);
    }
    
    // 解绑手机
    public function unbindMobile()
    {
        theme::setTitle('解绑手机号');
        $userInfo = userAuth::getUserInfo();
        $pagedata['user'] = $userInfo;
        $postdata = input::get();
        $pagedata['op'] = $postdata['op'];
        $pagedata['verifyType']= $postdata['verifyType'];
        $pagedata['title'] = app::get('topwap')->_('解绑手机号');
        return $this->page('topwap/member/safe/unbindusermobile.html', $pagedata);
    }
    
    public function doUnbindMobile()
    {
        $postData = utils::_filter_input(input::get());
        $sendType = $postData['verifyType'];
        $postData['user_id'] = userAuth::id();
        try
        {
            if(!userVcode::verify($postData['vcode'],$postData['uname'],$postData['type']))
            {
                throw new \LogicException(app::get('topwap')->_('验证码错误'));
                return false;
            }
        
            $data['user_id'] = $postData['user_id'];
            $data['user_name'] = $postData['uname'];
            $data['type'] = $postData['op'];
            app::get('topwap')->rpcCall('user.account.update',$data,'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
        $msg = app::get('topwap')->_('解绑成功');
        $url = url::action("topwap_ctl_member@security");
        return $this->splash('success',$url,$msg, true);
    }
    // 设置手机号
    public function setUserMobile()
    {
        theme::setTitle('填写手机号');
        // 判断是否进行了验证
        $safeFlagCheckLogin = $this->getSessionValue('safe-check-login-pwd', false);
        if(!$safeFlagCheckLogin) return redirect::action('topwap_ctl_member_safe@verify', array('checkType'=>'setmobile'));
        $pagedata['title'] = app::get('topwap')->_('填写手机号');
        
        return $this->page('topwap/member/safe/setmobile.html', $pagedata);
    }
    
    public function dosetmobile()
    {
        try {
            $postdata = input::get();
            $validator = validator::make(
                    [$postdata['uname']],['required|mobile'],['您的手机号不能为空!|手机格式错误']
            );
            $validator->newFails();
            // 发送短信验证码
            $this->passport->sendVcode($postdata['uname'],$postdata['type']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }
        
        $url = url::action('topwap_ctl_member_safe@viewSetmobile', array('mobile'=>$postdata['uname'],'type'=>$postdata['type']));
        return $this->splash('success', $url, null, true);
    }
    
    public function viewSetmobile()
    {
        theme::setTitle('手机验证');
        $postdata = input::get();
        $pagedata['mobile'] = $postdata['mobile'];
        $pagedata['type'] = $postdata['type'];
        $pagedata['title'] = app::get('topwap')->_('手机验证');
        
        return $this->page('topwap/member/safe/viewSetmobile.html', $pagedata);
    }
    
    // 保存手机号
    public function saveMobile()
    {
        $postData = utils::_filter_input(input::get());
        $msg = '';
        try
        {
            //$sendType = kernel::single('pam_tools')->checkLoginNameType($postData['uname']);
            $data = array('user_name'=>$postData['uname']);
            $postData['user_id'] = userAuth::id();
            if(!userVcode::verify($postData['vcode'],$postData['uname'],$postData['type']))
            {
                throw new \LogicException(app::get('topwap')->_('验证码错误'));
                return false;
            }
        
            $data['user_id'] = $postData['user_id'];
            $data['user_name'] = $postData['uname'];
            app::get('topwap')->rpcCall('user.account.update',$data,'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg, true);
        }
        $this->setSessionValue('safe-check-login-pwd', false);
        $url = url::action("topwap_ctl_member@security");
        // 根据前端要求将成功提示置空
        $msg = app::get('topwap')->_('保存成功');
        return $this->splash('success',$url,$msg, true);
    }
    
    public function sendVcode()
    {
        $postdata = input::get();
        try {
            $validator = validator::make(
                    [$postdata['uname']],['required|mobile'],['您的手机号不能为空!|手机格式错误']
            );
            $validator->newFails();
            // 发送短信验证码
            $this->passport->sendVcode($postdata['uname'],$postdata['type']);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error', null, $msg, true);
        }
        return $this->splash('success', null, '验证码已发送', true);
    }
    
    // 修改登录密码
    public function setUserPwd()
    {
        theme::setTitle('修改登录密码');
        $safeFlagCheckLogin = $this->getSessionValue('safe-check-login-pwd', false);
        if(!$safeFlagCheckLogin) return redirect::action('topwap_ctl_member_safe@verify', array('checkType'=>'setloginpwd'));
        
        $pagedata['title'] = app::get('topwap')->_('修改登录密码');
        return $this->page('topwap/member/safe/modifypwd.html', $pagedata);
    }
    
    public function saveModifyPwd()
    {
        try{
            $userId = userAuth::id();
            $postData = utils::_filter_input(input::get());
            $msg = '';
            $validator = validator::make(
                    ['password' => $postData['new_password'] , 'password_confirmation' =>$postData['confirm_password']],
                    ['password' => 'min:6|max:20|confirmed','password_confirmation' =>'required'],
                    ['password' => '密码长度不能小于6位!|密码长度不能大于20位!|输入的密码不一致!','password_confirmation' =>'确认密码不能为空!']
            );
            if ($validator->fails())
            {
                $messages = $validator->messagesInfo();
                foreach( $messages as $error )
                {
                    return $this->splash('error',null,$error[0]);
                }
            }
            // $this->checkPassword($postData['new_password']);
            $data = array(
                    'new_pwd' => $postData['new_password'],
                    'confirm_pwd' => $postData['confirm_password'],
                    'user_id' => $userId,
                    'type' => "reset",
            );
            app::get('topwap')->rpcCall('user.pwd.update',$data,'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }
        $this->setSessionValue('safe-check-login-pwd', false);
        
        $url = url::action("topwap_ctl_passport@logout");
        // 根据前端要求将成功提示置空
        $msg = app::get('topwap')->_('修改成功');
    
        return $this->splash('success',$url,$msg);
    }
    
    private function checkPassword($newPassword)
    {
        $a = 0;
        if(preg_match("/(?=.*[0-9])[a-zA-Z0-9]{6,20}/", $newPassword))
            $a += 1;
        if(preg_match("/(?=.*[a-z])[a-zA-Z0-9]{6,20}/", $newPassword))
            $a += 1;
        if(preg_match("/(?=.*[A-Z])[a-zA-Z0-9]{6,20}/", $newPassword))
            $a += 1;
    
        if($a >= 2)
            return true;
    
        throw new LogicException('密码格式错误,请参考密码规则');
    }
    
    private function setSessionValue($key, $value)
    {
        $userId = userAuth::id();
        $key = $key.$userId;
        return cache::store('session')->put($key, $value, 5);
        
    }
    
    private function getSessionValue($key, $default)
    {
        $userId = userAuth::id();
        $key = $key.$userId;
        $value = cache::store('session')->get($key, $default);
        return $value;
    }
}
 