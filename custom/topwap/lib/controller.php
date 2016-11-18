<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_controller extends base_routing_controller
{
    /**
     * 定义当前平台
     */
    public $platform = 'wap';

    /**
     * 控制器指定的布局(layout), 具体到文件
     *
     * @var \Illuminate\View\View
     */
    private $layout = null;

    /**
     * 控制器指定的布局标识, 会调取用户配置, 以决定最终应用的布局.
     *
     * @var \Illuminate\View\View
     */
    private $layoutFlag = 'default';

    var $appid = '';

    var $appsecret = '';
    public function __construct()
    {
        kernel::single('base_session')->start();
        theme::setIcon(app::get('topwap')->res_url.'/favicon.ico');
        logger::info('[topwap.lib.controller] : user_openid-----------'.$_SESSION['user_openid']);
        if ($this->is_weixin()) {
            if (empty($_SESSION['user_openid'])) {
                //获取微信相关配置
                $conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');
                $this->appid = $conf['appKey'];
                $this->appsecret = $conf['appSecret'];

                //第一步：用户同意授权，获取code
                $code = input::get('code');
                //第二步：通过code换取网页授权access_token
                // https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
                if (!empty($code)) {
                    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appid."&secret=".$this->appsecret."&code=".$code."&grant_type=authorization_code";
                    $res = $this->httpGet($url);
                    $_SESSION['user_openid'] = $res['openid'];
                }
            }
        }

    }

    protected function setLayout($layout)
    {
        $this->layout = $layout;
    }

    protected function setLayoutFlag($layoutFlag)
    {
        $this->layoutFlag = $layoutFlag;
    }

    public function set_cookie($name,$value,$expire=false,$path=null){
        if(!$this->cookie_path){
            $this->cookie_path = kernel::base_url().'/';
        }
        $this->cookie_life = $this->cookie_life > 0 ? $this->cookie_life : 315360000;
        $expire = $expire === false ? time()+$this->cookie_life : $expire;
        setcookie($name,$value,$expire,$this->cookie_path);
        $_COOKIE[$name] = $value;
    }
    /**
     * page
     *
     * @param  boolean $realpath
     * @return base_view_object_interface | string
     */
    public function page($view = null, $data = array())
    {
        $themeName = ($params['theme'])?$params['theme']:kernel::single('site_theme_base')->get_default('mobile');
        $theme = theme::uses($themeName);
        $layout = $this->layout;
        if (!$layout)
        {
            $layoutFlag = !is_null($this->layoutFlag) ? $this->layoutFlag : 'defalut';
            $tmplObj = kernel::single('site_theme_tmpl');
            $layout = $tmplObj->get_default($this->layoutFlag, $themeName);
            $layout = $layout ? $layout : (($tmpl_default = $tmplObj->get_default('default', $themeName)) ? $tmpl_default : 'default.html');
        }
        $theme->layout($layout);

        if (! is_null($view))
        {
            $theme->of($view, $data);
        }
        return $theme->render();
    }

    /*
     * 结果处理
     * @var string $status
     * @var string $url
     * @var string $msg
     * @var boolean $ajax
     * @var array $data
     * @access public
     * @return void
     */

    public function splash($status='success', $url=null , $msg=null,$ajax=false){
        $status = ($status == 'failed') ? 'error' : $status;
        //如果需要返回则ajax
        if($ajax==true||request::ajax()){
            return response::json(array(
                $status => true,
                'message'=>$msg,
                'redirect' => $url,
            ));
        }

        if($url && !$msg){//如果有url地址但是没有信息输出则直接跳转
            return redirect::to($url);
        }

        $this->setLayoutFlag('splash');
        $pagedata['msg'] = $msg;
        return $this->page('topwap/splash/error.html', $pagedata);
    }

    /**
     * 用于指示卖家操作者的标志
     * @return array 买家登录用户信息
     */
    public function operator()
    {
        return array(
            'account_type' => 'buyer',
            'op_id' => userAuth::id(),
            'op_account' => userAuth::getLoginName(),
        );
    }

    public function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = json_decode(curl_exec($curl),true);
        curl_close($curl);

        return $res;
    }

    /**
     * curl 模拟post请求  edit@by cg
     *
     * @param      <type>  $url    需要访问的url
     * @param      <type>  $param  参数
     */
     public function postbbb($url,$param)
     {  
        // $header[]="bbb:ddd";//自增请求头信息    没有实际意义  可删除
            $ch = curl_init();  
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //使用自动跳转
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  //获取的信息已文件流的形式返回
            curl_setopt($ch, CURLOPT_TIMEOUT, 5000);  //设置超时时间  防止死循环
            curl_setopt($ch, CURLOPT_POST, 1 );   //发送一个常规的post请求
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param); //post提交的数据包   
            // curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
            curl_setopt($ch, CURLOPT_URL,$url );         //要访问的地址
            $res = curl_exec($ch);  //执行操作
            if (curl_errno($ch)) {
                echo 'Errno '.curl_error($ch);
            }
            curl_close($ch);
            return $res;
     }
     
     /**
      * 是否在微信中登录
      *
      * @return     boolean  True if weixin, False otherwise.
      */
    public function is_weixin(){ 
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) { 
                return true; 
        } 
        return false; 
    }

}


