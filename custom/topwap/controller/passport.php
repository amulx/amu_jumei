<?php
class topwap_ctl_passport extends topwap_controller{

    public function __construct()
    {
        parent::__construct();
        kernel::single('base_session')->start();
        $this->passport = kernel::single('topwap_passport');

    }

    /**
     * @brief 进入登录页面
     *
     * @return
     */
    public function goLogin()
    {
        logger::info('[topwap.passport.goLogin] : openid-----------'.$_SESSION['user_openid']);
        $result = kernel::single('base_httpclient')->get('http:://www.baidu.com');
        // 检测是否登录
        if( !userAuth::check())
        {
            if ($this->is_weixin()) {
                    $openid = $_SESSION['user_openid'];

                    if (!empty($openid)) {
                        $row = app::get('sysuser')->model('user_distri')->getRow('user_id',['openid' => $openid]);
                    }else{
                        $conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');
                        $this->appid = $conf['appKey'];
                        $this->appsecret = $conf['appSecret'];
                        $url = url::action('topwap_ctl_passport@goLogin');
                        kernel::single('topwap_wechat_wechat')->get_code($this->appid, $url);
                    }

                    if (!empty($row['user_id'])) {//存在user_id时，自动登录
                        userAuth::login($row['user_id']);
                        //同步购物车数量 add@by cg  增加购物车数量同步
                        kernel::single('topwap_cart')->mergeCart();
                        $countData = kernel::single('topwap_cart')->getCartCount();
                        userAuth::syncCookieWithCartNumber($countData['number']);
                        userAuth::syncCookieWithCartVariety($countData['variety']);
                        // $return_to_url = url::action("topwap_ctl_member@index");
                        $return_to_url = $this->__getFromUrl();
                        logger::info('[topwap.passport.goLogin3] : return_to_url-----------'.$return_to_url);
                        return $this->splash('success',$return_to_url,$msg);
                    }else{
                            theme::setTitle('会员登录');

                            $next_page = $this->__getFromUrl();

                            if (kernel::single('pam_trust_user')->enabled())
                            {
                                $trustInfoList = kernel::single('pam_trust_user')->getTrustInfoList('wap', 'topwap_ctl_trustlogin@callback');
                            }
                            logger::info('[topwap.passport.goLogin4] : openid-----------hava openid but no user_id'.$_SESSION['user_openid']);
                            $isShowVcode = userAuth::isShowVcode('login');
                            $pagedata = compact('trustInfoList','isShowVcode','next_page');
                            return $this->page('topwap/passport/login/index.html',$pagedata);                        
                    }
            }else{
                    return redirect::action('topwap_ctl_passport@goAppLogin');
            }
        }

    }

    /**
     * @brief 进入app登录页面
     *
     * @return
     */
    public function goAppLogin()
    {
        theme::setTitle('会员登录');

        $next_page = $this->__getFromUrl();

        $isShowVcode = userAuth::isShowVcode('login');
        $pagedata = compact('trustInfoList','isShowVcode','next_page');
        return $this->page('topwap/passport/login/appLogin.html',$pagedata);
    }

    /**
     * @brief 完成登录流程
     *
     * @return
     */
    public function doLogin()
    {
        if(userAuth::isShowVcode('login') )
        {
            $url = specialutils::filterCrlf(input::get('next_page', request::server('HTTP_REFERER')));
            $verifycode = input::get('verifycode');
            if( !input::get('key') || empty($verifycode) || !base_vcode::verify(input::get('key'), $verifycode))
            {
                $msg = app::get('topwap')->_('验证码填写错误') ;
                return $this->splash('error',null,$msg);
            }
        }

        try
        {
            //记住密码功能暂无
            //userAuth::setAttemptRemember(input::get('remember',null));

            if (userAuth::attempt(input::get('account'), input::get('password')))
            {
                //商品收藏店铺收藏加入cookie
                $userId = userAuth::id();

                //处理之前用户没有关联openid
                if ($this->is_weixin()) {
                    $openid = $_SESSION['user_openid'];
               
                    if (!empty($openid)) {
                        $filter = array('openid' => $openid);
                        $result = app::get('sysuser')->model('user_distri')->getRow('user_id', $filter);
                    }

                    if (empty($result['user_id'])) {
                        $apiData['parent_user_id']=1;
                        $apiData['user_id'] = $userId;
                        $apiData['openid'] = $openid;
                        app::get('topwap')->rpcCall('user.distri.add', $apiData);                          
                    }
                }

                $collectData = app::get('topwap')->rpcCall('user.collect.info',array('user_id'=>$userId));
                setcookie('collect',serialize($collectData));
                $url = $url ?:$this->__getFromUrl();
                kernel::single('topwap_cart')->mergeCart();

                $countData = kernel::single('topwap_cart')->getCartCount();
                userAuth::syncCookieWithCartNumber($countData['number']);
                userAuth::syncCookieWithCartVariety($countData['variety']);

                return $this->splash('success',$url,$msg);
            }
        }
        catch(Exception $e)
        {
            userAuth::setAttemptNumber();
            if( userAuth::isShowVcode('login') )
            {
                $url = url::action('topwap_ctl_passport@goLogin');
            }
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }
    }


    /**
     * @brief 进入注册页面
     *
     * @return
     */
    public function goRegister()
    {
        if( userAuth::check() ) $this->logout();
        // return $this->page('topwap/passport/register/index.html',$pagedata);
        return $this->page('topwap/passport/register/wIndex.html',$pagedata);
    }

    /**
     * 简化版注册流程
     *edit@by cg  2016/8/10
     * @throws     \LogicException  (description)
     *
     * @return     <type>           ( description_of_the_return_value )
     */
    public function doWRegister(){
        $data = utils::_filter_input(input::get());
        //检测注册协议是否被阅读选中
        if(!input::get('license'))
        {
            $msg = app::get('topwap')->_('请阅读并接受会员注册协议');
            return $this->splash('error','',$msg);
        }
        $openid = $_SESSION['user_openid'];
        $validator = validator::make(
            [$data['uname']],['required|mobile'],['您的手机号不能为空!|请输入正确的手机号码']
        );
        // $verifycode = $data['verifycode'];
        // if( !input::get('key') || empty($verifycode) || !base_vcode::verify(input::get('key'), $verifycode))
        // {
        //     $msg = app::get('topwap')->_('图片验证码填写错误') ;
        //     return $this->splash('error',null,$msg);
        // }

        $uname = $data['uname'];
        //手机短信验证码判断
        $vcode = $data['vcode'];
        $data['password'] = $vcode;
        $data['pwd_confirm'] = $vcode;
        $sendType = $data['type'];
        try
        {
            $vcodeData=userVcode::verify($vcode,$uname,$sendType);
            if(!$vcodeData)
            {
                throw new \LogicException('短信验证码输入错误');
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }
        //当前手机号判断
        $userData = userAuth::getAccountInfo($uname);
        if($userData)
        {
            $msg = app::get('topwap')->_("该手机号已经使用");
            return $this->splash('error','',$msg);
        }


        try
        {
            $db = app::get('sysuser')->database();
            $db->beginTransaction();

            try{
                $userId = userAuth::signUp($uname, $data['password'], $data['pwd_confirm']);
                //***************增加了上下级关系 begin  edit@by cg
                $ralation_arr = app::get('sysuser')->model('user_share')->getRow('user_id',['openid'=>$openid]);//去sysuser_user_share中查询是否有关联的openid
                // echo "<pre>";var_dump($ralation_arr);
                // echo $openid;
                // exit();
                if ($ralation_arr['user_id']) {
                    $apiData['parent_user_id']=$ralation_arr['user_id'];
                    $apiData['user_id'] = $userId;
                    $apiData['openid'] = $openid;
                    app::get('topwap')->rpcCall('user.distri.add', $apiData);
                }else{
                    $apiData['parent_user_id']=1;
                    $apiData['user_id'] = $userId;
                    $apiData['openid'] = $openid;
                    app::get('topwap')->rpcCall('user.distri.add', $apiData);               
                }
                //***************增加了上下级关系  end
                $db->commit();
            }catch(Exception $e){
                $db->rollback();
                throw $e;
            }

            userAuth::login($userId, $uname);

                //获取用户openid和mobile
               $qb = app::get('jumei')->database()->createQueryBuilder();
               $qb->select('A.mobile,D.openid,D.parent_user_id')
               ->from('sysuser_account','A')
               ->leftJoin('A','sysuser_user_distri', 'D','A.user_id=D.user_id')
               ->andWhere("A.user_id = ".userAuth::id());
               $queryresult = $qb->execute()->fetchAll();
               $postDate['step'] = 1;
               $postDate['mobile'] = $queryresult[0]['mobile'];
               $postDate['openid'] = $queryresult[0]['openid'];
               $postDate['parent_user_id'] = $queryresult[0]['parent_user_id'];
               logger::info('[topwap.passposr.addpps] : postDate-----------'.json_encode($postDate));
               //向第三方传送数据
            $hoturl = 'http://app.yinahuo.com/bbc/user_reg.php';
            $result_thired = $this->postbbb($hoturl,$postDate);
            logger::info('[topwap.passposr.addpps_end] : postDate-----------'.json_encode($result_thired));
        }
        catch(Exception $e)
        {
            //$url = url::action('topwap_ctl_passport@goRegister');
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }
        $pagedata['site_name'] = app::get('site')->getConf('site.name');
        $pagedata['site_logo'] = app::get('site')->getConf('site.logo');
        $url = url::action('topwap_ctl_passport@registerSucc');
        return $this->splash('success',$url,$msg,true);
    }

    /**
     * @brief 进入app注册页面
     *
     * @return
     */
    public function goAppRegister()
    {
        if( userAuth::check() ) $this->logout();
        $pagedata['pid'] = input::get('pid');
        $pagedata['openid'] = input::get('openid');
        return $this->page('topwap/passport/register/appRegister.html',$pagedata);
    }
    /**
     * @brief 注册时验证用户名是否有效
     *
     * @return
     */
    public function checkUname()
    {
        $data = utils::_filter_input(input::get());

        $verifycode = $data['verifycode'];
        if( !input::get('key') || empty($verifycode) || !base_vcode::verify(input::get('key'), $verifycode))
        {
            $msg = app::get('topwap')->_('验证码填写错误') ;
            return $this->splash('error',null,$msg);
        }

        $uname = $data['uname'];
        $userData = userAuth::getAccountInfo($uname);
        if($userData)
        {
            $msg = app::get('topwap')->_("该用户名或手机号已经使用");
            return $this->splash('error','',$msg);
        }

        //检测注册协议是否被阅读选中
        if(!input::get('license'))
        {
            $msg = app::get('topwap')->_('请阅读并接受会员注册协议');
            return $this->splash('error','',$msg);
        }

        $accountType = app::get('topwap')->rpcCall('user.get.account.type',array('user_name'=>$uname));
        if($accountType == "mobile")
        {
            $pagedata['data']['mobile'] = $uname;
            $pagedata['data']['type'] = 'signup';
            $pagedata['data']['pid'] = $data['pid'];
            $pagedata['data']['openid'] = $data['openid'];
            return view::make('topwap/passport/verify_vcode.html',$pagedata);
        }
        else
        {
            $msg = app::get('topwap')->_('请输入正确的手机号');
            return $this->splash('error','',$msg);
//            return view::make('topwap/passport/register/set_pwd.html',$data);
        }
    }

    /**
     * @brief 完成注册流程
     *
     * @return
     */
    public function doRegister()
    {
        $data = utils::_filter_input(input::get());
        //检测注册协议是否被阅读选中
        if(!input::get('license'))
        {
            $msg = app::get('topwap')->_('请阅读并接受会员注册协议');
            return $this->splash('error','',$msg);
        }
        $openid = $_SESSION['user_openid'];
        $validator = validator::make(
            [$data['uname']],['required|mobile'],['您的手机号不能为空!|请输入正确的手机号码']
        );


        $uname = $data['uname'];
        //手机短信验证码判断
        $vcode = $data['vcode'];
        $data['password'] = $vcode;
        $data['pwd_confirm'] = $vcode;
        $sendType = $data['type'];
        try
        {
            $vcodeData=userVcode::verify($vcode,$uname,$sendType);
            if(!$vcodeData)
            {
                throw new \LogicException('短信验证码输入错误');
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }
        //当前手机号判断
        $userData = userAuth::getAccountInfo($uname);
        if($userData)
        {
            $msg = app::get('topwap')->_("该手机号已经使用");
            return $this->splash('error','',$msg);
        }


        try
        {
                $db = app::get('sysuser')->database();

                $db->beginTransaction();
                try{
                    $userId = userAuth::signUp($uname, $data['password'], $data['pwd_confirm']);
                    //***************增加了上下级关系 begin  edit@by cg
                        $apiData['parent_user_id']=1;
                        $apiData['user_id'] = $userId;
                        $apiData['openid'] = $openid;
                        app::get('topwap')->rpcCall('user.distri.add', $apiData);               
                    //***************增加了上下级关系  end
                    $db->commit();
                }catch(Exception $e){
                    $db->rollback();
                    throw $e;
                }
            userAuth::login($userId, $uname);
        }
        catch(Exception $e)
        {
            //$url = url::action('topwap_ctl_passport@goRegister');
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }
        $pagedata['site_name'] = app::get('site')->getConf('site.name');
        $pagedata['site_logo'] = app::get('site')->getConf('site.logo');
        $url = url::action('topwap_ctl_passport@registerSucc');
        return $this->splash('success',$url,$msg,true);
        // $data = utils::_filter_input(input::get());
        // $codyKey = $data['key'];
        // $userInfo = $data['pam_user'];
        // try
        // {
        //     $userId = userAuth::signUp($userInfo['account'], $userInfo['password'], $userInfo['pwd_confirm']);
        //     //***************增加了分销关系 begin  edit@by cg
        //     // if ($data['pid']) {
        //     //     $apiData['parent_user_id']=$data['pid'];
        //     //     $apiData['user_id'] = $userId;
        //     //     $apiData['openid'] = $data['openid'];
        //     //     app::get('topwap')->rpcCall('user.distri.add', $apiData);
        //     // }
        //     $apiData['parent_user_id']=1;
        //     $apiData['user_id'] = $userId;
        //     $apiData['openid'] = '';
        //     app::get('topwap')->rpcCall('user.distri.add', $apiData);
        //     //***************增加了分销关系  end
        //     userAuth::login($userId, $userInfo['account']);
        // }
        // catch(Exception $e)
        // {
        //     //$url = url::action('topwap_ctl_passport@goRegister');
        //     $msg = $e->getMessage();
        //     return $this->splash('error',$url,$msg,true);
        // }
        // $pagedata['site_name'] = app::get('site')->getConf('site.name');
        // $pagedata['site_logo'] = app::get('site')->getConf('site.logo');

        // $url = url::action('topwap_ctl_passport@registerSucc');
        // return $this->splash('success',$url,$msg,true);
    }

    public function registerSucc()
    {
        $pagedata['wap_name'] = app::get('sysconf')->getConf('sysconf_setting.wap_name');
        $pagedata['wap_logo'] = app::get('sysconf')->getConf('sysconf_setting.wap_logo');
        $pagedata['sendPointNum'] = app::get('sysconf')->getConf('sendPoint.num');
        $pagedata['open_sendpoint'] = app::get('sysconf')->getConf('open.sendPoint');

        $pagedata['properties'] = app::get('sysuser')->model('properties')->getList();
        $pagedata['protype'] = array('style'=>'风格','grade'=>'档次','places'=>'产地');
        return $this->page('topwap/passport/register/succ.html',$pagedata);
    }

    public function addpps(){

        $data['user_id'] = userAuth::id();
        $data['pps_id'] = input::get('pid');
        $data['create_time'] = time();
        $pps_name = input::get('pname');
        $pps_name_array = array_filter(explode(',',$pps_name));
        $p_index = input::get('p_index');
        $p_index_array = array_filter(explode(',',$p_index));
        foreach ($p_index_array as $key => $value) {
            if (array_key_exists($value, $postDate)) {      //当风格多选时已逗号拼接
                $postDate[$value] = $postDate[$value].','.$pps_name_array[$key];
            } else {
                $postDate[$value] = $pps_name_array[$key];
            }
        }

            //获取用户openid和mobile
           $qb = app::get('jumei')->database()->createQueryBuilder();
           $qb->select('A.mobile,D.openid,D.parent_user_id')
           ->from('sysuser_account','A')
           ->leftJoin('A','sysuser_user_distri', 'D','A.user_id=D.user_id')
           ->andWhere("A.user_id = ".userAuth::id());
           $queryresult = $qb->execute()->fetchAll();
           $postDate['step'] = 2;
           $postDate['mobile'] = $queryresult[0]['mobile'];
           $postDate['openid'] = $queryresult[0]['openid'];
           $postDate['parent_user_id'] = $queryresult[0]['parent_user_id'];
           logger::info('[topwap.passposr.addpps] : postDate-----------'.json_encode($postDate));
           //向第三方传送数据
            $hoturl = 'http://app.yinahuo.com/bbc/user_reg.php';
            $result_thired = $this->postbbb($hoturl,$postDate);
            logger::info('[topwap.passposr.addpps_end] : postDate-----------'.json_encode($result_thired));


        if(!input::get('pid'))
        {
            $msg = app::get('topwap')->_('属性不能为空');
            return $this->splash('error','',$msg);
        }
        $result = app::get('sysuser')->model('user_properties')->insert($data);
        if(!$result){
            $msg = app::get('topwap')->_('保存失败');
            return $this->splash('error','',$msg);
        }
        $url = url::action('topwap_ctl_default@index');
        return $this->splash('success',$url,null,true);
        
    }

    /**
     * @brief 获取用户注册协议
     *
     * @return
     */
    public function registerLicense()
    {
        theme::setTitle('用户注册协议');
        $pagedata['title'] = "用户注册协议";
        $licence = app::get('sysconf')->getConf('sysconf_setting.wap_license');
        if($licence)
        {
            $pagedata['license'] = $licence;
        }
        else
        {
            $pagedata['license'] = app::get('sysuser')->getConf('sysuser.register.setting_user_license');
        }
        return $this->page('topwap/passport/register/license.html', $pagedata);
    }



    /**
     * @brief 找回密码第一步，进入找回密码页面
     *
     * @return  html
     */
    public function goFindPwd()
    {
        theme::setTitle('找回密码');
        return $this->page('topwap/passport/forgotten/verify-uname.html');
    }

    /**
     * @brief 找回密码第二步，验证用户名/手机号
     *
     * @return
     */
    public function verifyUsername()
    {
        $postdata = utils::_filter_input(input::get());

        //验证图片验证码
        $valid = validator::make(
            [$postdata['verifycode']],['required']
        );
        if($valid->fails())
        {
            return $this->splash('error',null,"图片验证码不能为空!");
        }
        if(!base_vcode::verify($postdata['verifycodekey'],$postdata['verifycode']))
        {
            return $this->splash('error',null,"图片验证码错误!");
        }

        //验证用户名
        if($postdata['username'])
        {
            $loginName = $postdata['username'];
            $data = userAuth::getAccountInfo($loginName);
            if($data)
            {
                $data['type'] = "forgot";
                $pagedata['data'] = $data;
                return view::make('topwap/passport/verify_vcode.html',$pagedata);
            }
        }

        $url = url::action('topwap_ctl_passport@goFindPwd');
        $msg = app::get('topwap')->_('账户不存在');
        return $this->splash('error',$url,$msg);
    }

    public function sendVcode()
    {
        $postdata = utils::_filter_input(input::get());
        $validator = validator::make(
            [$postdata['uname']],['required|mobile'],['您的手机号不能为空!|请输入正确的手机号码']
        );

        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();
            $url = url::action('topwap_ctl_passport@goFindPwd');
            foreach( $messages as $error )
            {
                return $this->splash('error',$url,$error[0]);
            }
        }

        try {
            $this->passport->sendVcode($postdata['uname'],$postdata['type']);
        } catch(Exception $e) {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }
        return $this->splash('success',null,"验证码发送成功");
    }

    /**
     * @brief 找回密码第三步 验证手机验证码
     *
     * @return
     */
    public function verifyVcode()
    {
        $postdata = utils::_filter_input(input::get());
        $vcode = $postdata['vcode'];
        $loginName = $postdata['uname'];
        $sendType = $postdata['type'];
        try
        {
            $vcodeData=userVcode::verify($vcode,$loginName,$sendType);
            if(!$vcodeData)
            {
                throw new \LogicException('验证码输入错误');
            }
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }

        $userInfo = userAuth::getAccountInfo($loginName);
        $key = userVcode::getVcodeKey($loginName ,$sendType);
        $userInfo['key'] = md5($vcodeData['vcode'].$key.$userInfo['user_id']);

        if($sendType == "forgot")
        {
            $pagedata['data'] = $userInfo;
            $pagedata['account'] = $loginName;
            return view::make('topwap/passport/forgotten/setting_passport.html', $pagedata);
        }
        else
        {
            $pagedata['uname'] = $loginName;
            return view::make('topwap/passport/register/set_pwd.html',$pagedata);
        }
    }

    /**
     * @brief 找回密码第四部 设置新密码
     *
     * @return
     */
    public function settingPwd()
    {
        $postdata = utils::_filter_input(input::get());
        $userId = $postdata['userid'];
        $account = $postdata['account'];

        $vcodeData = userVcode::getVcode($account,'forgot');
        $key = userVcode::getVcodeKey($account,'forgot');

        if($account !=$vcodeData['account']  || $postdata['key'] != md5($vcodeData['vcode'].$key.$userId) )
        {
            $msg = app::get('topwap')->_('页面已过期,请重新找回密码');
            return $this->splash('failed',null,$msg,true);
        }

        $data['type'] = 'reset';
        $data['new_pwd'] = $postdata['password'];
        $data['user_id'] = $postdata['userid'];
        $data['confirm_pwd'] = $postdata['confirmpwd'];
        try
        {
            app::get('topwap')->rpcCall('user.pwd.update',$data,'buyer');

        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            $url = url::action('topwap_ctl_passport@findPwd');
            return $this->splash('error',$url,$msg,true);
        }

        $msg = "修改成功";
        $url = url::action('topwap_ctl_passport@goLogin');
        return $this->splash('success',$url,$msg,true);
    }

    public function logout()
    {
        $userid = userAuth::id();
        //删除trustinfo表中指定userid的数据  add@by cg 2016/7/28
        // app::get('topwap')->database()->executeUpdate('delete from sysuser_trustinfo where user_id = ?', [$userid]);
        
        userAuth::logout();
        return redirect::action('topwap_ctl_default@index');
    }


    private function __getFromUrl()
    {
        $url = utils::_filter_input(input::get('next_page', request::server('HTTP_REFERER')));
        $validator = validator::make([$url],['url'],['数据格式错误！']);
        if ($validator->fails())
        {
            return url::action('topwap_ctl_default@index');
        }
        if( !is_null($url) )
        {
            if( strpos($url, 'passport') )
            {
                return url::action('topwap_ctl_default@index');
            }
            return $url;
        }else{
            return url::action('topwap_ctl_default@index');
        }
    }

    //检查是否已经注册
    public function checkLoginAccount()
    {
        $signAccount = utils::_filter_input(input::get());
        $loginName = $signAccount['uname'];
        $validator = validator::make(
            [$loginName],['required'],['请输入手机号!']
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();
            foreach( $messages as $error )
            {
                return $this->splash('error',null,$error[0]);
            }
        }
        try
        {
            $data = userAuth::getAccountInfo($loginName);
            if($data)
            {
                throw new \LogicException('该手机号已被使用');
            }
            //$json['needVerify'] = kernel::single('pam_tools')->checkLoginNameType($loginName);
            $json['needVerify'] = app::get('topc')->rpcCall('user.get.account.type',array('user_name'=>$loginName),'buyer');
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg,true);
        }
        return response::json($json);
    }


    /**
     * 模拟登陆
     */
    public function thirdLogin(){
        $mobile = input::get('mobile');
        // // $data = userAuth::getAccountInfo($mobile);
        // var_dump($mobile);exit();
        try {
            $result = app::get(pam_auth_user::appId)->rpcCall('user.get.account.info', ['user_name' =>$mobile], 'buyer');
            if ($result) {
                userAuth::login($result['user_id']);
                return redirect::action('topwap_ctl_member@index');    
            }            
        } catch (Exception $e) {
            echo $e->getMessage();
        }

    }

}
