<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class sysuser_data_user_history
{
    /**
     * 添加收藏
     * @param string user id
     * @param string object type
     * @return boolean true or false
     */
    public function addH($userId,$nGid=null)
    {
        if(!$nGid || !$userId) return false;
        $objMdlHistory = app::get('sysuser')->model('user_history');
       
        return $objMdlHistory->addH($userId,$nGid);
    }

    
    /**
     * 删除当前页的收藏内容
     * @param string user_id
     * @param string nGid
     */
    public function delH($userId,$nGid=null)
    {
        if (!$userId) return false;

        $objMdlHistory = app::get('sysuser')->model('user_history');

        if (is_null($nGid))
        {
            return $objMdlHistory->delAllH($userId);
        }
        else
        {
            return $objMdlHistory->delH($userId,$nGid);
        }
    }
}
