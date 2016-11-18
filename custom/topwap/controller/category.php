<?php
class topwap_ctl_category extends topwap_controller{
	
		public function __construct()
    {
    	parent::__construct();
      $countData = kernel::single('topwap_cart')->getCartCount();
      userAuth::syncCookieWithCartNumber($countData['number']);
      userAuth::syncCookieWithCartVariety($countData['variety']);
    }
    
    public function index()
    {
        theme::setTitle('分类');
        $this->setLayoutFlag('category');
        /** 原有分类类目  begin */
        // $catList = app::get('topwap')->rpcCall('category.cat.get.list',array('fields'=>'cat_id,cat_name'));
        // $pagedata['data'] = $catList;
        // echo "<pre>";var_dump($pagedata);exit();
        /**原有分类类目  end */
        /** 获取接口中的类目  begin*/
        $url = 'http://www.aiyoupin.com/yinahuo_wap_menu.php';
        $result = json_decode($this->postbbb($url),true);
        $pagedata['data'] = $result;
        // echo "<pre>";var_dump($pagedata);exit();
        /** 获取接口中的类目  end*/

        /** 获取接口中的热搜  begin*/
        $hoturl = 'http://www.aiyoupin.com/yinahuo_hot_keyword.php';
        $hotdata = json_decode($this->postbbb($hoturl),true);
        $pagedata['hotsearch'] = $hotdata;
        /** 获取接口中的热搜  end*/

        return $this->page('topwap/category/index.html',$pagedata);
    }
}