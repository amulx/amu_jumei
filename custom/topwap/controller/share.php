<?php
/**
 * Created by Yorisun.
 * Author: sb.zhang@yorisun.com
 * Date: 16/7/27
 * Time: 上午10:48
 */

class topwap_ctl_share extends topwap_controller {

    var $appid = 'wxc2f2c4e972a8f3b1';

    var $appsecret = '28ea3739e709152c6dd657245973d555';

    public function __construct()
    {
        $conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');
        $this->appid = $conf['appKey'];
        $this->appsecret = $conf['appSecret'];
    }

    public function index(){

        theme::setTitle('分享');
        // 检测是否登录
        if( !userAuth::check() )
        {
            $openid = $_SESSION['user_openid'];
            if (!empty($openid)) {
                $row = app::get('sysuser')->model('user_distri')->getRow('user_id',['openid' => $openid]);
            }
            if (!empty($row['user_id'])) {
                userAuth::login($row['user_id']);
                $return_to_url = url::action("topwap_ctl_share@index");
                logger::info('[topwap.share.index] : return_to_url-----------'.$return_to_url);
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

        $pagedata = array();
        //添加微信相关数据
        $pagedata['timestamp'] = $timestamp = time();     //当前时间戳
        $pagedata['nonceStr'] = $nonceStr = $this->createNonceStr();    //16位随机字符串
        // $url = url::action('topwap_ctl_share@index');
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $jsapiTicket = $this->getJsApiTicket();
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";  //url待解决
        $pagedata['signature'] = sha1($string);
        $pagedata['appid'] = $this->appid;
        $pagedata['url'] = $url;
        $pagedata['redirect_uri'] = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->appid.'&redirect_uri='.url::action('topwap_ctl_spread@spread',array('user_id'=>userAuth::id())).'&response_type=code&scope=snsapi_base&state=1';
        $pagedata['qrcode'] = getQrcodeUri($pagedata['redirect_uri'],180,0);
        return $this->page('topwap/share/index.html',$pagedata);
    }

    public function extend(){
        $pagedata = array();
        return $this->page('topwap/share/extend.html',$pagedata);
    }

    //生成随机码
    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
          $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getAccessToken() {

            if ($_SESSION['access_token_expire_time']>time() && $_SESSION['access_token']) {
                $access_token = $_SESSION['access_token'];
            } else {
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
                $res = $this->httpGet($url);
                $access_token = $res['access_token'];
                $_SESSION['access_token'] = $access_token;
                $_SESSION['access_token_expire_time'] = time() + 7000;
            }            

            return $access_token;
    }

    //获取jsapi_ticket全局票据
    private function getJsApiTicket() {
        //如果session中保存有效的jsapi_ticket   
        if ($_SESSION['jsapi_ticket_expire_time']>time() && $_SESSION['jsapi_ticket']) {
            $jsapi_ticket = $_SESSION['jsapi_ticket'];
        } else {
            $accessToken = $this->getAccessToken();
            // https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=ACCESS_TOKEN&type=jsapi
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$accessToken&type=jsapi";
            $res = $this->httpGet($url);
            $jsapi_ticket = $res['ticket'];
            $_SESSION['jsapi_ticket'] = $jsapi_ticket;
            $_SESSION['jsapi_ticket_expire_time'] = time() + 7000;
        }
        
        return $jsapi_ticket;
    }


}