<?php
class topwap_ctl_member extends topwap_controller{

    public $limit = 10;
    public function __construct(&$app)
    {
        parent::__construct();
        kernel::single('base_session')->start();
        if(!$this->action) $this->action = 'index';
        $this->action_view = $this->action.".html";
        // 检测是否登录
        if( !userAuth::check() )
        {
            $openid = $_SESSION['user_openid'];
            if (!empty($openid)) {
                $row = app::get('sysuser')->model('user_distri')->getRow('user_id',['openid' => $openid]);
            }
            if (!empty($row['user_id'])) {
                userAuth::login($row['user_id']);
                $return_to_url = url::action("topwap_ctl_member@index");
                logger::info('[topwap.passport.goLogin] : return_to_url-----------'.$return_to_url);
                return $this->splash('success',$return_to_url,$msg);
            }else{
                    if( request::ajax() )
                    {
                        $url = url::action('topwap_ctl_passport@goLogin');
                        return $this->splash('error', $url, app::get('topwap')->_('请登录'), true);
                    }
                    redirect::action('topwap_ctl_passport@goLogin')->send();exit;
            }
        }
        
        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);

        $this->passport = kernel::single('topwap_passport');
    }
    public $verifyArray = array('mobile','email');

    public function index()
    {
        theme::setTitle('我的中心');
        $this->setLayoutFlag('member');
        $userId = userAuth::id();
        $pagedata['account'] = userAuth::getLoginName();

        $userInfo = userAuth::getUserInfo();
        $pagedata['userInfo'] = $userInfo;
        $pagedata['nologin'] = userAuth::check() ? "true" : "false";

        //获取订单各种状态的数量
        $pagedata['nupay'] = app::get('topwap')->rpcCall('trade.count',array('user_id'=>$userId,'status'=>'WAIT_BUYER_PAY'));
        $pagedata['nudelivery'] = app::get('topwap')->rpcCall('trade.count',array('user_id'=>$userId,'status'=>'WAIT_SELLER_SEND_GOODS'));
        $pagedata['nuconfirm'] = app::get('topwap')->rpcCall('trade.count',array('user_id'=>$userId,'status'=>'WAIT_BUYER_CONFIRM_GOODS'));
        $cancelData = app::get('topwap')->rpcCall('trade.cancel.list.get',['user_id'=>$userId,'fields'=>'tid']);
        $pagedata['canceled'] = $cancelData['total'];
        $pagedata['nurate'] = app::get('topwap')->rpcCall('trade.notrate.count',array('user_id'=>$userId));

        //预存款金额
        $pagedata['deposit'] = app::get('topwap')->rpcCall('user.deposit.getInfo',['user_id'=>$userId]);
        $depositConf = app::get('topwap')->rpcCall('payment.get.conf',['app_id'=>'deposit']);
        $pagedata['noDeposit'] = $depositConf['status'] == 'true' ? false : true;

        //优惠劵数量
        $pagedata['coupon'] = app::get('topwap')->rpcCall('user.coupon.list', ['user_id'=>$userId, 'is_valid'=>'1', 'page_size'=>1]);

        return $this->page('topwap/member/index.html', $pagedata);
    }

    /**
     * @brief 安全中心
     *
     * @return html
     */
    public function security()
    {
        theme::setTitle('安全中心设置');
        $pagedata['title'] = app::get('topwap')->_('安全中心');
        // 查看当前会员是否设置了手机
        $userInfo = userAuth::getUserInfo();
        $pagedata['user'] = $userInfo;
        return $this->page('topwap/member/safe_center.html', $pagedata);
    }

    public function setting()
    {
        theme::setTitle('设置');
        return $this->page('topwap/member/setting.html');
    }

    public function detail()
    {
        theme::setTitle('会员信息');
        $userInfo = userAuth::getUserInfo();
        $pagedata['userInfo'] = $userInfo;
        return $this->page('topwap/member/detail.html',$pagedata);
    }

    public function goSetName()
    {
        theme::setTitle('修改昵称');
        $userInfo = userAuth::getUserInfo();
        $pagedata['name'] = $userInfo['name'];
        return $this->page('topwap/member/set/name.html',$pagedata);
    }

    public function goSetUsername()
    {
        theme::setTitle('修改姓名');
        $userInfo = userAuth::getUserInfo();
        $pagedata['username'] = $userInfo['username'];
        return $this->page('topwap/member/set/username.html',$pagedata);
    }

    public function goSetSex()
    {
        theme::setTitle('修改性别');
        $userInfo = userAuth::getUserInfo();
        $pagedata['sex'] = $userInfo['sex'];
        return $this->page('topwap/member/set/sex.html',$pagedata);
    }

    public function goSetLoginAccount()
    {
        theme::setTitle('设置用户名');
        $userInfo = userAuth::getUserInfo();
        return $this->page('topwap/member/set/login_account.html',$pagedata);
    }

    public function goSetBirthday()
    {
        $userInfo = userAuth::getUserInfo();
        $pagedata['name'] = $userInfo['name'];
        return $this->page('topwap/member/set/birthday.html',$pagedata);
    }

    public function saveUserInfo()
    {
        $userId = userAuth::id();
        $postdata = utils::_filter_input(input::get('user'));
        if(!$this->_validator($postdata,$msg))
        {
            return $this->splash('error',null,$msg);
        }

        try
        {
            $data = array('user_id'=>$userId,'data'=>json_encode($postdata));
            app::get('topwap')->rpcCall('user.basics.update',$data);
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg);
        }

        $url = url::action('topwap_ctl_member@detail');
        $msg = app::get('topwap')->_('修改成功');
        return $this->splash('success',$url,$msg,true);
    }

    private function _validator($postdata,&$msg)
    {
        try
        {
            switch(key($postdata)) {
            case "username":
                $rule = ['username'=>'required|max:20'];
                $message = ['username' => '用户姓名不能为空!|用户姓名过长,请输入20个英文或10个汉字!'];
                break;
            case "name":
                $rule = ['name'=>'required|min:4|max:20'];
                $message = ['name' =>'用户昵称不能为空!|用户昵称最少4个字符!|用户昵最多20个字符!'];
                break;
            }
            $validator = validator::make($postdata,$rule,$message);
            $validator->newFails();
        }
        catch(Exception $e)
        {
            $msg = $e->getMessage();
            return false;
        }
        return ture;
    }

    /**
     * 信任登陆用户名密码设置
     */
    public function saveLoginAccount()
    {
        $username = input::get('username');

        $userId = userAuth::id();
        //会员信息
        $userInfo = userAuth::getUserInfo();
        if($userInfo['login_account']){
            $msg = app::get('topwap')->_('您已有用户名，不能再设置');
            return $this->splash('error',null,$msg,true);
        }
        
       
        
        $url = url::action('topwap_ctl_member@detail');
        try
        {
            $this->__checkAccount($username);
            $data = array(
                'user_name'   => $username,
                'user_id' => $userId,
            );
            app::get('topwap')->rpcCall('user.account.update',$data,'buyer');
        }
        catch(\Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',null,$msg,true);
        }
        

        return $this->splash('success',$url,app::get('topwap')->_('修改成功'),true);
    }

    private function __checkAccount($username)
    {

        $validator = validator::make(
            ['username' => $username],
            ['username' => 'loginaccount|required|min:4|max:20'],
            ['username' => '用户名不能为纯数字或邮箱地址!|用户名不能为空!|用户名最少4个字符！|用户名最长为20个字符!']
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();
            foreach( $messages as $error )
            {
                throw new LogicException( $error[0] );
            }
        }
        return true;
    }
    /**
     * 会员中心--客服中心
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function customerService(){
        $filter = ['parent_id'=>[26,30,34]];//分别对应文章栏目ID 26=>订单售后  30=>选购支付 34=>其他问题
        $articleNode = app::get('syscontent')->model('article_nodes')->getList('node_id,node_name,parent_id',$filter);
        foreach ($articleNode as $key => $value) {
            if ($value['parent_id'] == 26) {
                $pagedata['orderAfter'][] = $value;
            }elseif ($value['parent_id'] == 30) {
                $pagedata['chooseBuy'][] = $value;
            }else{
                $pagedata['otherProblems'][] = $value;                
            }
        }
        return $this->page('topwap/member/customer/service.html',$pagedata);
    }
    /**
     * 客服中心--问题查询--订单售后
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function orderAfter(){
        // $getData = input::get();
        $node_ids = app::get('syscontent')->model('article_nodes')->getList('node_id,node_name',['parent_id'=>26]);
        foreach ($node_ids as $key => $value) {
                $pagedata[$value['node_name']] = app::get('syscontent')->model('article')->getList('article_id,title',['node_id'=>$value['node_id']]);
        }
        
        return $this->page('topwap/member/customer/order_after.html',$pagedata);        
    }
    /**
     * 客服中心--问题查询--选择支付
     */
    public function chooseBuy(){
        $node_ids = app::get('syscontent')->model('article_nodes')->getList('node_id,node_name',['parent_id'=>30]);
        foreach ($node_ids as $key => $value) {
                $pagedata[$value['node_name']] = app::get('syscontent')->model('article')->getList('article_id,title',['node_id'=>$value['node_id']]);
        }
        return $this->page('topwap/member/customer/choose_buy.html',$pagedata);
    }
    /**
     * 客服中心--问题查询--其他问题
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function otherProblems(){
        $node_ids = app::get('syscontent')->model('article_nodes')->getList('node_id,node_name',['parent_id'=>34]);
        foreach ($node_ids as $key => $value) {
                $pagedata[$value['node_name']] = app::get('syscontent')->model('article')->getList('article_id,title',['node_id'=>$value['node_id']]);
        }
        return $this->page('topwap/member/customer/other_problems.html',$pagedata);
    }

    public function otherDetail(){
        $postdata = input::get();
        $pagedata['article'] = app::get('syscontent')->model('article')->getRow('article_id,title,content',['article_id'=>$postdata['article_id']]);
        return $this->page('topwap/member/customer/other_detail.html',$pagedata);
    }

}
