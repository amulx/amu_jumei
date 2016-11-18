<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class topwap_site_view_helper {

    public function function_header($params, $template, $pagedata)
    {
        $appleDesktop = app::get('sysconf')->getConf('sysconf_setting.wapmac_logo');
        $wapTitle = theme::getTitle() ? theme::getTitle() : app::get('sysconf')->getConf('sysconf_setting.wap_name');
        $pagedata['appleDesktop'] = $appleDesktop;
        $pagedata['wapTitle'] = $wapTitle;
        //echo '<pre>';print_r($pagedata);exit();
        return view::make('topwap/common/header.html',$pagedata)->render();
    }

    public function function_wapfooter($params, $template)
    {
        $html = '<div class="system-type"> <a href="#" class="shopex-btn shopex-btn-warning shopex-btn-outlined btn-action-outlined">电脑版</a> </div> <div class="system-info">&copy; 2013 All rights reserved. Powered By ShopEx</div>';
        return $html;
    }

}//结束

