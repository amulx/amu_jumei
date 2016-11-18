<?php


class sysuser_mdl_user_history extends dbeav_model{

    /**
     * 添加历史足迹
     * @param $user_id $goods_id
     * @return true or false
     */
    function addH($userId=null,$goods_id=null)
    {
        if(!$userId || !$goods_id) return false;

        $filter['user_id'] = $userId;
        $filter['item_id'] = $goods_id;
        
        if($row = $this->getRow('history_id',$filter)){
          $historyId = $row['history_id'];
        }
        $goodsData = app::get('sysitem')->model('item')->getRow('cat_id,title,price,item_id,image_default_id,cat_id,shop_id',array('item_id'=>$goods_id));
        $sdf = array(
           'history_id' => $historyId,
           'item_id' =>$goods_id,
           'user_id' =>$userId,
           'shop_id' =>$goodsData['shop_id'],
           'cat_id'=>$goodsData['cat_id'],
           'goods_name'=>$goodsData['title'],
           'goods_price'=>$goodsData['price'],
           'image_default_id'=>$goodsData['image_default_id'],
           'create_time' => time(),
          );
          if($this->save($sdf))
          {
              return true;
          }
          else
          {
              return false;
          }
    }

    /**
     * 删除历史足迹
     * @param $user_id $page $num
     * @return data
    */
    function delH($userId,$gid)
    {
        $is_delete = false;
        $is_delete = $this->delete(array('item_id' => $gid,'user_id' => $userId));
        return $is_delete;
    }
    /**
     * 删除历史足迹
     * @param $user_id $page $num
     * @return data
     */
    function delAllH($userId){
        return $this->delete(array('user_id' => $userId));
    }
}
