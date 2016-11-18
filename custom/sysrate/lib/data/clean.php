<?php 
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 *
 */
class sysrate_data_clean
{
    
    /*
     * 清除初始化数据
     */
    public function clean()
    {
        app::get('sysrate')->model('appeal')->delete( array('*') );
        app::get('sysrate')->model('consultation')->delete( array('*') );
        app::get('sysrate')->model('dsr')->delete( array('*') );
        app::get('sysrate')->model('feedback')->delete( array('*') );
        app::get('sysrate')->model('score')->delete( array('*') );
        app::get('sysrate')->model('traderate')->delete( array('*') );
        app::get('sysrate')->model('buyersaid')->delete( array('*') );
    }
    #End Func
}