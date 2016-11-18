<?php 

/**
* 调用第三方接口获取商品数据
*/
class topwap_ctl_item_thirdPartyList extends topwap_controller{
	
	// function __construct(){}

	public function index(){
		$itemData = app::get('topwap')->rpcCall('item.get.testItem');
		var_dump($itemData);
		// $filter = input::get();//页面搜索条件
		// $pagedata['pagers']['total'];//总页数
		// $pagedata['items'];//搜索结果
		// $pagedata['activeFilter'];//过滤条件
		// $pagedata['search_keywords'] = $activeFilter['search_keywords'];//搜索关键字
		// return $this->page('topwap/item/thirdPartyList/index.html', $pagedata);
	}


	function ajaxGetItemList(){
		$filter = input::get();
		if (!$pagedata['pagers']['total']) {
			return view::make('topwap/empty/item.html',$pagedata);
		} 

		if ($pagedata['items']) {
			return view::make('topwap/item/thirdPartyList/item_list.html',$pagers);
		}
		
	}
}

?>