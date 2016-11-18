<?php
	/*
		2016-09-01  min.zhou@yorisun.com
		输出公共类
	*/
	class foroms_output_common
	{
		private $url;

		public function __construct($app)
    	{
	    	$this->url = config::get('aiyoupin_oms.url');
	    	logger::info('[foroms_output_common.__construct] url:' .$this->url);
    	}

	    /**
	     * curl 模拟post请求  edit@by cg   2016/9/9
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

    	public function postData($data, $method)
    	{
	    	try
	    	{
		    	$postUrl = $this->url .$method;	
		    	// logger::info('[foroms_output_common.postData] postUrl:' .$postUrl);
		    	$jsonData = json_encode($data);
		    	$postData = ['data'=>$jsonData];
		    	logger::info('[foroms_output_common.postData] method:' .$method .', postData:' .var_export($postData, true));
				$result_third = $this->postbbb($postUrl,$postData);
	    		logger::info('[foroms_output_common.postData] method:' .$method .',res:' .var_export($result_third, true));
	    	}	
	    	catch (Exception $ex)
	    	{
		    	logger::error('[foroms_output_common.postData] method:' .$method .', ex:' .$ex->getMessage());
		    	return false;
	    	}
	    	return $res;
    	}

    	public function respData($errCode, $errMsg, $data)
    	{
	    	$resp = array("errcode" => $errCode,
	    					"errmsg" => $errMsg,
	    					"data" => $data);
	    	return json_encode($resp);				
    	}
	}
?>