<?php

// 手机邮箱发送验证码
class system_data_user_vcode{
    //public $ttl = 3600;//86400;
    public $ttl = 60;
    public $email_ttl = 60;
    public $count = 3;

    protected $prefix = 'VCODE_VERIFY:';

    public function __construct()
    {
        kernel::single('base_session')->start();
    }

    //随机取6位字符数
    public function randomkeys($length)
    {
        $key = '';
        $pattern = '1234567890';    //字符池
        for($i=0;$i<$length;$i++)
        {
            $key .= $pattern{mt_rand(0,9)};    //生成php随机数
        }
        return $key;
    }

    //验证码检查
    public function verify($vcode,$send,$type)
    {
        if(empty($vcode) ) return false;
        $vcodeData = $this->getVcode((string)$send,$type);
        if($vcodeData && $vcodeData['vcode'] == $vcode)
        {
            $data = $this->deleteVcode($vcodeData['account'],$type, $vcodeData);
            return $data;
        }
        else
        {
            return false;
        }
    }

    /*
     * 删除验证码（非物理删除，重新生成一个验证码）
     *
     */
    public function deleteVcode($account,$type, $vcodeData)
    {
        $vcode = $this->randomkeys(6);
        $vcodeData['vcode'] = $vcode;
        $key = $this->getVcodeKey($account,$type);
        // 获取当前模板的短信验证码配置
        $tmpl = $this->sendtypeToTmpl($type);
        $ttl = app::get('system')->getConf('messenger.ttl.'.$tmpl.'.sms');
        if($ttl)
        {
            $this->ttl = (int)$ttl;
        }
        cache::store('vcode')->put($key, $vcodeData, $this->ttl);
        return $vcodeData;
    }

    public function checkVcode($account,$type='signup')
    {
        $vcodeData = $this->getVcode($account,$type);
        // 获取短信验证码配置
        $smsConfData = $this->getSmsConfCount($account, $type);
        // 只在发送短信的情况下进行替换
        if($smsConfData)
        {
            $vcodeData['createtime'] = $smsConfData['createtime'];
            $vcodeData['count'] = $smsConfData['count'];
            $vcodeData['lastmodify'] = $smsConfData['lastmodify'];
        }
        
        if($vcodeData && !strpos($account,'@')){
            if( time() - $vcodeData['lastmodify'] <= 120 ){
                throw new \LogicException(app::get('system')->_('2分钟发送一次,还没到两分钟则不进行发送'));
                return false;
            }

            // 获取当前模板的短信验证码配置
            $tmpl = $this->sendtypeToTmpl($type);
            $count = app::get('system')->getConf('messenger.count.'.$tmpl.'.sms');
            if($count)
            {
                $this->count = (int)$count;
            }
             
            if( $vcodeData['createtime'] == date('Ymd') && $vcodeData['count'] >= $this->count ){
                throw new \LogicException(app::get('system')->_('每天只能进行'.$this->count.'次验证'));
                return false;
            }

            if( $vcodeData['createtime'] != date('Ymd') ){
                $vcodeData['count'] = 0;
            }
        }
        return $vcodeData;
    }

    public function getVcodeKey($account,$type='signup')
    {
        return $this->prefix.$account.$type;
    }

    //获取验证码
    public function getVcode($account,$type='signup')
    {
        $key = $this->getVcodeKey($account,$type);

        $vcode = cache::store('vcode')->get($key);

        return $vcode;
    }

    //短信发送
    public function send_sms($type,$mobile)
    {
        if( !$tmpl = $this->sendtypeToTmpl($type) ) return false;
        $vcodeData = $this->checkVcode($mobile,$type);
        $vcode = $this->randomkeys(6);
        $vcodeData['account'] = $mobile;
        $vcodeData['vcode'] = $vcode;
        $vcodeData['count']  += 1;
        $vcodeData['createtime'] = date('Ymd');
        $vcodeData['lastmodify'] = time();
        $data['vcode'] = $vcode;
        // 获取当前模板的短信验证码有效期配置
        $ttl = app::get('system')->getConf('messenger.ttl.'.$tmpl.'.sms');
        if($ttl)
        {
            $this->ttl = (int)$ttl;
        }
        $data['expires'] = $this->ttl;
        $key = $this->getVcodeKey($mobile,$type);
        $result = messenger::sendSms($mobile,$tmpl,$data);
        if($result['rsp'] == "fail")
        {
            logger::info('验证码发送失败1',$result);
            throw new \LogicException(app::get('system')->_('验证码发送失败!'));
        }
        
        cache::store('vcode')->put($key, $vcodeData, $this->ttl);
        
        // 设置短信验证码设置缓存,缓存为一天
        $smsConfData['count'] = $vcodeData['count'];
        $smsConfData['createtime'] = $vcodeData['createtime'];
        $smsConfData['lastmodify'] = $vcodeData['lastmodify'];
        $smsConfkey = $this->getSmsConfCountKey($mobile,$type);
        cache::store('vcode')->put($smsConfkey, $smsConfData, 1440);
        
        logger::info('验证码发送成功',$vcodeData);
        return true;
    }
    //邮件发送
    public function send_email($type,$email,$content, $is_url = true){
        if( !$tmpl = $this->sendtypeToTmpl($type) ) return false;
        $vcodeData = $this->checkVcode($email,$type);
        $vcode = $this->randomkeys(6);
        $vcodeData['account'] = $email;
        $vcodeData['vcode'] = $vcode;
        $vcodeData['count']  = 1;
        $vcodeData['createtime'] = date('Ymd');
        $vcodeData['lastmodify'] = time();

        $data['shopname'] = app::get('site')->getConf('site.name');
        $data['vcode'] = $content."&vcode=".$vcode;
        //当$is_url不为true时只发送单纯的验证码
        if(! $is_url)
        {
            $data['vcode'] = $vcode;
        }
        $key = $this->getVcodeKey($email,$type);

        $result = messenger::sendEmail($email,$tmpl,$data);

        if($result['rsp'] == "fail")
        {
            logger::info('邮件发送失败',$result);
            throw new \LogicException(app::get('system')->_('邮件发送失败,请检查邮箱格式是否正确!'));
        }

        cache::store('vcode')->put($key, $vcodeData, $this->email_ttl);
        logger::info('验证码发送成功',$vcodeData);
        return true;
    }

    //短信发送模板设置
    public function sendtypeToTmpl($sendtype){

        $tmpl = false;
        switch($sendtype){
        case 'activation': //激活
            $tmpl = 'account-member';
            break;
        case 'reset': //重置手机号或者邮箱
            $tmpl = 'account-member';
            break;
         case 'unreset': //重置手机号或者邮箱
            $tmpl = 'account-unmember';
            break;
        case 'forgot': //找回密码
            $tmpl = 'account-lostPw';
            break;
        case 'signup': //手机注册
            $tmpl = 'account-signup';
            break;
        case 'auth_shop': //商家安全验证
            $tmpl = 'account-shop';
            break;
        case 'findPw_shop': //商家找回密码
            $tmpl = 'findPw-shop';
            break;
        case 'depost_forgot':
            $tmpl = 'deposit-lostPw';
            break;
        case 'coupon':
            $tmpl = 'account-coupon';
            break;
        }
        return $tmpl;
    }

    // 获取短信验证码次数缓存key
    public function getSmsConfCountKey($account, $type='signup')
    {
        $key = 'VCODE_SMS_CONF:';
        $key = $key.$account.$type;
        
        return $key;
    }
    
    // 获取短信验证码次数
    public function getSmsConfCount($account, $type='signup')
    {
        $key = $this->getSmsConfCountKey($account, $type);
        $count = cache::store('vcode')->get($key);
        
        return $count;
    }


}
