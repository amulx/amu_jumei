<?php

class sysitem_api_getCommodity {

	public $apiDescription = '获取易拿货商品数据接口';

	public function getParams(){	
		$return['params'] = array();
		return $return;
	}

	//获取数据接口
	public function getCommodity($params)
	{
		if (empty($params))
		{
			return '-1:请提交接口所需参数';
		}
		logger::info('[topc_ctl_commodity.getCommodity] params=:' .json_encode($params));
		//
		foreach( $params as $itemData )
		{
			// 获取属性值与分类映射
			$db = app::get('syscategory')->database();
			$qb = $db->createQueryBuilder();
			$qb->select('a.prop_value_id,a.prop_image,a.prop_value,a.prop_id,c.prop_type,c.show_type,c.prop_name')
				->from('syscategory_prop_values', 'a')
				->leftJoin('a', 'syscategory_cat_rel_prop', 'b', 'a.prop_id = b.prop_id')
				->leftJoin('a', 'syscategory_props', 'c', 'a.prop_id=c.prop_id')
				->where($qb->expr()->eq('b.cat_id', $itemData['cat_id']));
				// var_dump($qb->getSql());exit;
			logger::info('[topc_ctl_commodity.getCommodity] sql=:' .$qb->getSql());	
			$lstTmp = $qb->execute()->fetchAll();
			foreach( $lstTmp as $key => $value )
			{
				$lstCatRelProp[$value['prop_value']][] = array(

						$value['prop_value_id'], 
						$value['prop_image'], 
						$value['prop_id'], 
						$itemData['cat_id'], 
						$value['prop_type'], 
						$value['show_type'],
						$value['prop_name'],
				);
			}
			//调用_createItem方法
			$res = $this->_createItem($itemData, $lstCatRelProp);

			return $res;
		}

	}

	//处理post过来的数据
	private function _createItem($itemData, $catRelProp ,&$resItemId)
	{
		$resItemId = 0;
		$data = $itemData;
        $data['item']['title'] = htmlspecialchars($data['item']['title']);//商品标题
        $data['item']['sub_title'] = htmlspecialchars($data['item']['sub_title']);//商品子标题
        $data['item']['keyword'] = implode(',',$data['item']['keyword']);//商品关键词
        $data['item']['approve_status'] = 'onsale';//默认自动上架
        $data['item']['sub_stock'] = 1;//库存计数：付款减库存(0),下单减库存(1)
        $data['item']['shop_cat_id'] = ','.implode(',', $data['item']['shop_cids']).',';//商家自定义分类id
	    $data['item']['cat_id'] = $data['cat_id'];//商品类目ID
	    //$data['item']['store'] = $data['item']['sku'][1];

		// 处理sku
		$arrSkus = $data['item']['sku'][0];
		if(empty($arrSkus)){ //如果sku为空的时候，就执行默认的sku数据
			$price = $data['item']['price'];
			$mkt_price = $data['item']['mkt_price'];
			$cost_price = $data['item']['cost_price'];
			$arrSkus = array(
				array("常规","均码",$price,$mkt_price,$cost_price,"3000","",""),
			);
			//颜色,尺码,商品价格,商品市场价格,商品成本价格,商品库存,bn,商品级别的条形码
		}
		// echo '<pre>';var_dump($arrSkus);exit;
		foreach ($arrSkus as $value) {
			$skuStoreCount += intval($value[5]);
			logger::info('[topc.controller] : store-----------'.$value[5]);
			$catRelProps = $this->_catRelProp($value, $data['cat_id'], $catRelProp);
		}
		$data['item']['store'] = $skuStoreCount;

		//echo '<pre>';var_dump($catRelProp);exit;
		$resSku = $this->_procItemSku($arrSkus, $catRelProps);
		$resSpec = $this->_procItemSpec($arrSkus, $catRelProps);
		// echo '<pre>';var_dump($skuStoreCount);

		$data['item']['sku'] = $resSku;
		$data['item']['spec'] = $resSpec;
		
		try
        {
        	// echo '<pre>---------------------';var_dump($data);
        	$data = $this->_checkkPost($data);
        	// echo '<pre>++++++++++++++++';var_dump($data);exit;
        	// 插入数据库
	        $resItemId = app::get('topc')->rpcCall('item.create',$data);
	        // exit();
	        //处理销售量
	        $this->_ItemSoldQuantity($resItemId , $data['sold_quantity']);
	        // 处理评论
	        if(is_array($data['comment'])){
	        	$this->_ItemComment($resItemId , $data['comment']);
	        }
        }
        catch(Exception $e)
        {
	        $msg = '-1:'.$e->getMessage();
	        return $msg;
        }
        //返回商品id
		return "1:" . $resItemId;
	}

	private function _procItemSku($sku, $catRelProp)
	{
		
		foreach ($sku as $value) {
			// 检查颜色值
			$colorVal = $value[0];
			$chkColor = $catRelProp[$colorVal][0];

			// echo '<pre> _procItemSku---color';var_dump($chkColor);
			// 检查尺寸
			$sizeVal = $value[1];
			$chkSize = $catRelProp[$sizeVal][0];
			// echo '<pre> _procItemSku----size';var_dump($chkSize);

			$spec_private_value_id = '';
			$spec_value = array_filter(array($chkColor[2] => $colorVal , $chkSize[2] => $sizeVal));

			// echo '<pre> _procItemSku';var_dump($spec_value);exit;


		    $spec_value_id = array_filter(array($chkColor[2] => $chkColor[0] , $chkSize[2] => $chkSize[0]));

		    if(!empty($chkColor) && !empty($chkSize)){
		    	//sku格式数据
		    	$skudata[] = [
					"sku_id"=>"new",
					"spec_desc"=>array(
						"spec_private_value_id"=>$spec_private_value_id,
						"spec_value"=>$spec_value,
						"spec_value_id"=>$spec_value_id,
					),
		    		"price"=>$value[2],
		    		"mkt_price"=>$value[3],
		    		"cost_price"=>$value[4],
		    		"store"=>$value[5],
		    		"bn"=>$value[6],
		    		"barcode"=>$value[7],
				];
		    }
		}

		//echo '<pre> _procItemSku';var_dump($skudata);
		// echo '<pre>';var_dump($skudata);exit;
		return json_encode($skudata);
	}

	//检查sku是否有
	private function _catRelProp($sku , $catId , &$catRelProp){
		// echo '<pre> _catRelProp------------';var_dump($catRelProp);exit;
		// 检查颜色值
		$colorVal = $sku[0];
		$chkColor = $catRelProp[$colorVal];

		if (empty($chkColor))
		{
			// 加入数据库
			$newProp['prop_id'] = 1;
			$newProp['prop_value'] = $colorVal;
			$newPropId = app::get('syscategory')->model('prop_values')->insert($newProp);
			if ($newPropId > 0)
			{
				$catRelProp[$colorVal][] = array($newPropId,'',$newProp['prop_id'], $catId,'spec','image','颜色');
			}
		}


		// 检查尺寸
		$sizeVal = $sku[1];
		$chkSize = $catRelProp[$sizeVal];
		if (empty($chkSize))
		{
			//echo '<pre> _catRelProp------------';var_dump($sizeVal);
			// 加入数据库
			$newSize['prop_id'] = 2;
			$newSize['prop_value'] = $sizeVal;
			//echo '<pre> _catRelProp=====================';var_dump($newSize);
			$newSizeId = app::get('syscategory')->model('prop_values')->insert($newSize);
			// echo '<pre> _catRelProp+++++++++++';var_dump($newSizeId);
			if ($newSizeId > 0)
			{
				$catRelProp[$sizeVal][] = array($newSizeId,'',$newSize['prop_id'],$catId,'spec','text','尺寸');;
			}			
		}

		return $catRelProp;
	}

	//Spec值
	private function _procItemSpec($sku,$catRelProp){
		foreach ($sku as $value) {

			// 检查颜色值
			$colorVal = $value[0];
			$chkColor = $catRelProp[$colorVal][0];

			if (!empty($chkColor))
			{

				$colorData[$chkColor[0]]=[
					"private_spec_value_id"=>"",
					"spec_value"=>$colorVal,
					"spec_value_id"=>$chkColor[0],
					"spec_image"=>$chkColor[1],
				];
			}

			if($chkColor[4] == 'spec' && $chkColor[5] == 'image'){
				$specData[$chkColor[2]]=[
			        		"spec_name" => $chkColor[6],
			        		"spec_id" => $chkColor[2],
			        		"show_type" => $chkColor[5],
			        		"option" => $colorData,
			        	];
			}

			// 检查尺寸
			$sizeVal = $value[1];
			$chkSize = $catRelProp[$sizeVal][0];

			if (!empty($chkSize))
			{
				$sizeData[$chkSize[0]]=[
					"private_spec_value_id"=>"",
					"spec_value"=>$sizeVal,
					"spec_value_id"=>$chkSize[0],
				];
			}

		
			if($chkSize[4] == 'spec' && $chkSize[5] == 'text'){
				$specData[$chkSize[2]]=[
			        		"spec_name" => $chkSize[6],
			        		"spec_id" => $chkSize[2],
			        		"show_type" => $chkSize[5],
			        		"option" => $sizeData,
			        	];
			}
		}
		// echo '<pre>';var_dump($specData);exit;
		return json_encode($specData);
	}

	//处理销售量
	private function _ItemSoldQuantity($resItemId , $sold_quantity){
		$db = app::get('sysitem')->database();
        $params = ['num'=>$sold_quantity, 'item_id'=> $resItemId];
        $db->executeUpdate('UPDATE sysitem_item_count SET sold_quantity = ? WHERE item_id = ?', [$params['num'], $params['item_id']]);
	}

	//处理评论数组
	private function _ItemComment($resItemId , $comment){
		foreach ($comment as $comData) {
    		$comData['anony']=0;
        	$comData['item_id'] = $resItemId;
			app::get('sysrate')->model('buyersaid')->save($comData);
		}
	}

	//处理没有的数据
	private function _checkkPost($params)
    {
        if(!$params['item']['mkt_price'])
        {
            $params['item']['mkt_price'] = 0;
        }
        if(!$params['item']['cost_price'])
        {
            $params['item']['cost_price'] = 0;
        }
        if(!$params['item']['weight'])
        {
            $params['item']['weight'] = 0;
        }
        if(!$params['item']['order_sort'])
        {
            $params['item']['order_sort'] = 1;
        }
        return $params;
    }


}