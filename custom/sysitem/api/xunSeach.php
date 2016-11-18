<?php

class sysitem_api_xunSeach {

	var $xs;

	var $search;

	/**
	 * 接口作用说明
	 *
	 * @var        string
	 */
	public $apiDescription = '获取易拿货数据接口';

	public function getParams(){	
		$return['params'] = array(
			// 'user_id' => ['type'=>'int','valid'=>'required', 'default'=>'', 'example'=>'', 'description'=>'用户ID必填','default'=>'','example'=>''],
		);
		return $return;
	}
	public function __construct(){
	    	require_once ("/usr/local/xunsearch/sdk/php/lib/XS.php");
	    	$this->xs = new XS('yinahuo');//创建xs对象   项目名称为：yinahuo
	    	$this->search = $this->xs->search; //获取搜索对象    	                              
	}

	/**
	 * 热门搜索词
	 *
	 * @param      <type>  $apiData  The api data
	 *
	 * @return     <type>  The hot query.
	 */
	public function getHotQuery($apiData){
		// $this->search->getHotQuery(6,'lastNum');//获取前6个上周热门词
		$hotWords = $this->search->getHotQuery();//获取前6个总热门搜索词
		return $hotWords;
	}

	/**
	 * Scws分词
	 *
	 * @return     XSTokenizerScws  ( description_of_the_return_value )
	 */
	public function useScws(){
		$tokenizer = new XSTokenizerScws();//创建分词对象实例
		$tokenizer->setIgnore(true);//让返回的分词结果忽略标点符号
		$tokenizer->setDuality(true);//对分词结果中的连续单字做二元组合
		$tokenizer->setMulti(3);//设置复合分词方案
		$text = "迅搜(xunsearch)是优秀的开源全文检索解决方案";
		$words = $tokenizer->getResult($text);//返回分词结果
		return $words;

		$tops = $tokenizer->getTops($text,5,'n,v,vn');//提取前5个重要词  要求词性必须是n或v或者vn
		print_r($tops);
	}

	/**
	 * 搜索案例
	 *
	 * @return     <type>  ( description_of_the_return_value )
	 */
	public function sampleSearch(){
		// $this->search->search('杭州 西湖');//搜索同时包含这2个词的结果
		// $this->search->search('杭州 OR 西湖');//搜索包含其中一个词的结果   AND/OR/NOT/XOR		
		// $this->search->search('subject:杭州 西湖');//包括西湖并且标题包含杭州的结果  字段检索使用 ﬁeld:XXX 的格式。 
		
		// $this->search->setLimit(5,15); // 设置最多返回 5条，并跳过前 15条，即返回第 16-20 条结果 
		// $docs = $this->search->setQuery()->search();// 搜索 ‘测试’ 
		// foreach ($docs as $doc) {
		// 	$subject = $this->search->highlight($doc->subject); // 高亮处理标题 
		// 	echo $doc->rank().'.'.$subject.date('Y-m-d');
		// 	echo $doc->message;
		// }
		// $count = $this->search->getLastCount();// 获取最后一次 $search->search() 的匹配数量 
		// $count = $this->search->count(); // 直接检索包含 ‘测试’ 的数量
		
		$docs = $this->search->setQuery()->search('两');

		foreach ($docs as $key =>$doc) {
			$items[$key]['title'] = $doc->title;
			$items[$key]['price'] = $doc->price;
			$items[$key]['item_id'] = $doc->item_id;
		}
		
		$result['items'] = $items;
		$result['count'] = $this->search->getLastCount();
		return $result;
	}


	public function jumeiIndex(){
		$count = $this->search->count();//总记录数

		$docs = $search->search(); // 执行搜索，将搜索结果文档保存在 $docs 数组中		
	}


}