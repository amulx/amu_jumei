<?php

class system_messenger_smsemp
{
    /**
     * 用户名
     */
    var $userName;

    /**
     * 密码
     */
    var $userPass;


    /**
     * 方法名
     */
    var $method;


    /**
     * 版本号
     */
    var $version;

    var $url;


    /**
     * @param string $url 			接口地址
     * @param string $serialNumber 	用户名
     * @param string $password		密码
     * @param string $timeout		连接超时时间，默认0，为不超时
     * @param string $response_timeout		信息返回超时时间，默认30
     *
     *
     */
    function __construct()
    {
        $this->userName = config::get('smsemp.account');
        $this->userPass = config::get('smsemp.password');
        $this->method = config::get('smsemp.method');
        $this->version = config::get('smsemp.version');
        $this->url = config::get('smsemp.send_url');
    }



    /**
     * 发送短信
     * @return int 操作结果状态码
     */
    function sendSMS($content,$to)
    {   
        $post_data = "account=".$this->userName."&password=".$this->userPass."&mobile=".$to."&content=".$content;
        $target = $this->url;
        logger::info('[system_messenger_smg.send] : content-----------'.$content);
        $result = $this->xml_to_array($this->Post($post_data, $target));
        logger::info('[system_messenger_smg.send] : result-----------'.json_decode($result));
        if (2 == $result['SubmitResult']['code']) {
            return $this->xml_to_array($this->Post($post_data, $target));
        } else {
	        logger::error('[system_messenger_smsemp.sendSMS] code:' .$result['SubmitResult']['code'] .',msg:' .$result['SubmitResult']['msg'] .',content:' .$content);
	        
            throw new \LogicException('短信发送失败');
        }
        
        
    }


    function Post($curlPost,$url){
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
            $return_str = curl_exec($curl);
            curl_close($curl);
            return $return_str;
    }


    function xml_to_array($xml){
        $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
        if(preg_match_all($reg, $xml, $matches)){
            $count = count($matches[0]);
            for($i = 0; $i < $count; $i++){
            $subxml= $matches[2][$i];
            $key = $matches[1][$i];
                if(preg_match( $reg, $subxml )){
                    $arr[$key] = $this->xml_to_array( $subxml );
                }else{
                    $arr[$key] = $subxml;
                }
            }
        }
        return $arr;
    }


}