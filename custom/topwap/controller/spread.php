<?php
/**
 * 我要推广
 */
class topwap_ctl_spread extends topwap_controller {
	var $appid = 'wxc2f2c4e972a8f3b1';

	var $appsecret = '28ea3739e709152c6dd657245973d555';

	public function __construct()
	{
		$conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');
		$this->appid = $conf['appKey'];
		$this->appsecret = $conf['appSecret'];
	}

	function spread(){
		// var_dump(input::get());
		$user_id = input::get('user_id');
		//第一步：用户同意授权，获取code
		$code = input::get('code');
		//第二步：通过code换取网页授权access_token
		// https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appid."&secret=".$this->appsecret."&code=".$code."&grant_type=authorization_code";
		$res = $this->httpGet($url);
		$openid = $res['openid'];
		// var_dump($openid);
		$apiData['user_id'] = $user_id;
		$apiData['openid'] = $openid;
        $_SESSION['user_openid'] = $openid;
        try {// add@by cg 2016/9/7   增加异常处理
            app::get('topwap')->rpcCall('user.share.add',$apiData);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            logger::info('[topwap_ctl_spread] : error_msg-----------'.json_encode($msg));
            return $this->splash('error',null,'访问异常',true);
        }
		
		//查询有没有优惠卷
		$mdlReg = app::get('syspromotion')->model('registereddiscount');
		$pagedata['regdis'] = $mdlReg->getRow('regdis_id',array('regdis_status'=>'register'));
        $pagedata['openid'] = $openid;
		return $this->page('topwap/spread/index.html', $pagedata);
	}

	    /**
     * 简化版注册流程
     *edit@by czh  2016/9/6
     * @throws     \LogicException  (description)
     *
     * @return     <type>           ( description_of_the_return_value )
     */
    public function doWRegisterSpread(){
        $data = utils::_filter_input(input::get());
        logger::info('[topwap_ctl_spread.doWRegisterSpread] : data-----------'.json_encode($data));
        $openid = $data['openid'];
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


        try
        {
            $db = app::get('sysuser')->database();
            $db->beginTransaction();

            try{
                $userId = userAuth::signUp($uname, $data['password'], $data['pwd_confirm']);
                logger::info('[topwap_ctl_spread.doWRegisterSpread] : userId-----------'.json_encode($userId));
                //添加用户领取优惠卷 
                app::get('topwap')->rpcCall('user.add.regcoupon', array('user_id'=>$userId,'regdis_id'=>$data['regdis_id']));
                //***************增加了上下级关系 begin  edit@by cg
                if (!empty($openid)) {
                    $ralation_arr = app::get('sysuser')->model('user_share')->getRow('user_id',['openid'=>$openid]);//去sysuser_user_share中查询是否有关联的openid
                }

                if (!empty($ralation_arr['user_id'])) {
                    $apiData['parent_user_id']=$ralation_arr['user_id'];
                    $apiData['user_id'] = $userId;
                    $apiData['openid'] = $openid;
                    logger::info('[topwap_ctl_spread.addpps1] : apiData-----------'.json_encode($apiData));
                    app::get('topwap')->rpcCall('user.distri.add', $apiData);
                }else{
                    $apiData['parent_user_id']=1;
                    $apiData['user_id'] = $userId;
                    $apiData['openid'] = $openid;
                    logger::info('[topwap_ctl_spread.addpps2] : apiData-----------'.json_encode($apiData));
                    app::get('topwap')->rpcCall('user.distri.add', $apiData);               
                }
                $db->commit();
            }catch(Exception $e){
                $db->rollback();
                throw $e;
            }
            //***************增加了上下级关系  end
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
               logger::info('[topwap_ctl_spread.addpps] : postDate-----------'.json_encode($postDate));
               //向第三方传送数据
            $hoturl = 'http://app.yinahuo.com/bbc/user_reg.php';
            $result_thired = $this->postbbb($hoturl,$postDate);
            logger::info('[topwap_ctl_spread.addpps_end] : postDate-----------'.json_encode($result_thired));
            return $this->splash('success','','领取优惠券成功');
            
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }

    }
}