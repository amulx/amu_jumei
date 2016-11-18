<?php
class topwap_ctl_trustlogin extends topwap_controller{

    public function __construct()
    {
        parent::__construct();
        kernel::single('base_session')->start();
    }

    /**
     * callback返回页, 同时是bind页面
     *
     * @return base_http_response
     */
    public function callback()
    {
        $params = input::get();
        $flag = $params['flag'];
        unset($params['flag']);

        // 信任登陆校验
        $userTrust = kernel::single('pam_trust_user');
        $res = $userTrust->authorize($flag, 'web', 'topwap_ctl_trustlogin@callback', $params);
        $binded = $res['binded'];
        $userinfo = $res['user_info'];
        $openid = $userinfo['openid'];  //增加返回openid  edit@by cg  2016/7/28
        $realname = $userinfo['nickname'];
        $avatar = $userinfo['figureurl'];

        if ($binded)
        {
            $userId = $res['user_id'];

            userAuth::login($userId);
            kernel::single('topwap_cart')->mergeCart();
            $countData = kernel::single('topwap_cart')->getCartCount();
            userAuth::syncCookieWithCartNumber($countData['number']);
            userAuth::syncCookieWithCartVariety($countData['variety']);
            return redirect::action('topwap_ctl_default@index');
        }
        else
        {
            $pagedata['realname'] =  $realname;
            $pagedata['avatar'] = $avatar;
            $pagedata['openid'] = $openid;  //增加返回openid  edit@by cg  2016/7/28
            $pagedata['flag'] = $flag;
            return $this->page('topwap/trustlogin/bind.html', $pagedata);
        }
    }

    // public function bindDefaultCreateUser()
    // {
    //     $params = input::get();
    //     $flag = $params['flag'];
    //     try
    //     {
    //         $userId = kernel::single('pam_trust_user')->bindDefaultCreateUser($flag);
    //         userAuth::login($userId, $loginName);
    //         //redirect::action('topwap_ctl_default@index')->send();exit;
    //         $url = url::action('topwap_ctl_default@index');
    //         return $this->splash('success', $url, $msg, true);

    //     }
    //     catch (\Exception $e)
    //     {
    //         $msg = $e->getMessage();
    //         return $this->splash('error',null,$msg,true);
    //     }
    // }

    public function bindExistsUser()
    {
        $params = input::get();
        $verifyCode = $params['verifycode'];
        $verifyKey = $params['vcodekey'];
        $loginName = $params['uname'];
        $password = $params['password'];

        if( (!$verifyKey) || $b=empty($verifyCode) || $c=!base_vcode::verify($verifyKey, $verifyCode))
        {
            $msg = app::get('topwap')->_('验证码填写错误') ;
            return $this->splash('error', null, $msg, true);
        }

        try
        {
            if (userAuth::attempt($loginName, $password))
            {
                kernel::single('pam_trust_user')->bind(userAuth::id());
                $url = url::action('topwap_ctl_default@index');
                return $this->splash('success', $url, $msg, true);
            }
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
    }

    public function bindSignupUser()
    {
        $params = input::get();
        $verifyCode = $params['verifycode'];
        $vcode = $params['vcode'];
        $verifyKey =  $params['vcodekey'];
        $loginName = $params['pam_account']['login_name'];
        $password = $params['pam_account']['login_password'];
        $confirmedPassword = $params['pam_account']['psw_confirm'];
        $openid = $params['openid'];  //增加返回openid  edit@by cg  2016/7/28
        
//        if( !$verifyKey || empty($verifyCode) || !base_vcode::verify($verifyKey, $verifyCode))
//        {
//            $msg = app::get('topwap')->_('图片验证码填写错误') ;
//            return $this->splash('error', null, $msg, true);
//        }
        $vcodeData=userVcode::verify($vcode,$loginName,'signup');
        if(!$vcodeData)
        {
            $msg = app::get('topwap')->_('手机验证码输入错误');
            return $this->splash('error',null,$msg,true);
        }

        try
        {
            $userId = userAuth::signUp($loginName, $password, $confirmedPassword);
            //***************增加了上下级关系 begin  edit@by cg
            $ralation_arr = app::get('sysuser')->model('user_share')->getRow('user_id',['openid'=>$openid]);//去sysuser_user_share中查询是否有关联的openid
            if ($ralation_arr['user_id']) {
                $apiData['parent_user_id']=$ralation_arr['user_id'];
                $apiData['user_id'] = $userId;
                $apiData['openid'] = $openid;
                app::get('topwap')->rpcCall('user.distri.add', $apiData);
            }else{
                $apiData['parent_user_id']=0;
                $apiData['user_id'] = $userId;
                $apiData['openid'] = $openid;
                app::get('topwap')->rpcCall('user.distri.add', $apiData);               
            }
            //***************增加了上下级关系  end
            userAuth::login($userId, $loginName);
            kernel::single('pam_trust_user')->bind(userAuth::id());

            $url = url::action('topwap_ctl_passport@registerSucc');
            return $this->splash('success', $url, $msg, true);
        }
        catch (\Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
    }
}
