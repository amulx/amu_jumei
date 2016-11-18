<?php
	/*
		2016-09-01  min.zhou@yorisun.com
		输出公共类
	*/
	class foroms_controller extends topc_controller
	{
    	public function respData($errCode, $errMsg, $data)
    	{
	    	$resp = array("errcode" => $errCode,
	    					"errmsg" => $errMsg,
	    					"data" => $data);
	    	return json_encode($resp);				
    	}
	}
?>