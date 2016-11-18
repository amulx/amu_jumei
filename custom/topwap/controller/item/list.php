<?php
/**
 * 商品列表页控制器
 */
class topwap_ctl_item_list extends topwap_controller {


    public function __construct()
    {
        $this->objLibSearch = kernel::single('topwap_item_search');
    }

    public function index()
    {

        $filter = input::get();
        $cat_id = $filter['cat_id'];
        $keyword = $filter['keyword'];
        $kname = $filter['kname'];
        $search_keywords = input::get('search_keywords');
        if (!empty($search_keywords)) {
                    $keyword = $search_keywords;
        }
        $pagedata['activeFilter'] = ['keyword'=>$keyword,'cat_id'=>$cat_id,'search_keywords'=>$search_keywords,'kname'=>$kname,'type'=>$filter['type']];
        $pagedata['search_keywords'] = $search_keywords;

        if (empty($filter['type'])) {       //没有more
                $url = 'http://www.aiyoupin.com/yinahuo_wap_list.php?k='.$keyword.'&cat_id='.$cat_id.'&sort=hot';
                $result = json_decode($this->postbbb($url),true);
                 
        } else {
                $pagedata['type'] = $filter['type'];
                //http://www.aiyoupin.com/yinahuo_wap_index_style.php?style=
                $url = 'http://www.aiyoupin.com/yinahuo_wap_index_style_pages.php?style='.$kname;
                // var_dump($url);exit();
                $result = json_decode($this->postbbb($url),true);
                $pagedata['style_img'] = $result['style_img'];  
        }

        $pagedata['items'] = $result['show_list'];
        $pagedata['pagers']['total'] = $result['max_page'];
         
        //判断当前访问者的移动设备   规则是不登录不显示 add@by cg 2016/9/2
        if( userAuth::check() ){
            if ($this->is_weixin()) {
                $pagedata['download_url'] = "http://a.app.qq.com/o/simple.jsp?pkgname=com.yinahuo.mapp";//不用区分android和ios 统一下载链接
            }
        }       

        if($kname){
            theme::setTitle($kname);
        }else{
            theme::setTitle($keyword);
        }
        return $this->page('topwap/item/list/index.html', $pagedata);
    }

    public function ajaxGetItemList()
    {
        /**原有数据
        $filter = input::get();
        $itemsList = $this->objLibSearch->search($filter)
                          ->setItemsActivetyTag()
                          ->setItemsPromotionTag()
                          ->getData();

        $pagedata['items'] = $itemsList['list'];
         */

        $filter = input::get();
        // var_dump($filter);exit;
        $keyword = $filter['keyword'];
        $type = $filter['type'];
        $pages = $filter['pages'];
        $kname = $filter['kname'];

        $search_keywords = input::get('search_keywords');
        if (!empty($search_keywords)) {
                $keyword= $search_keywords;
        } 
        $pagedata['activeFilter'] = ['keyword'=>$keyword,'cat_id'=>$cat_id,'search_keywords'=>$search_keywordssearch_keywords,'kname'=>$kname,'type'=>$type];
        if($type != 'more'){
            if($filter['orderBy'] == 'sold_quantity'){
                $where = "&sort=sold_quantity";
            }
            if ($filter['orderBy'] == 'modified_time') {
                $where = "&sort=new";
            }
            if ($filter['orderBy'] == 'price asc') {
                $where = "&sort=price_a";
            }
            if ($filter['orderBy'] == 'price desc') {
                $where = "&sort=price_d";
            }
            if($filter['orderBy'] == ''){
                $where = '&sort=hot';
            }
            $url = 'http://www.aiyoupin.com/yinahuo_wap_list.php?k='.$keyword.'&cat_id='.$filter['cat_id'].'&p='.$pages.$where;
            $result = json_decode($this->postbbb($url),true);
        }else{
            $url = 'http://www.aiyoupin.com/yinahuo_wap_index_style_pages.php?style='.$kname.'&p='.$pages;
            // var_dump($url);exit;
            $result = json_decode($this->postbbb($url),true);
            $pagedata['style_img'] = $result['style_img'];
        }
        
        
        

        $pagedata['pagers']['total'] = $result['max_page'];
        // if( !$pagedata['pagers']['total'] )
        // {
        //     return view::make('topwap/empty/item.html',$pagedata);
        // }

        $pagedata['items'] = $result['show_list'];
        
        if($pagedata['items'])
        {
            return view::make('topwap/item/list/item_list.html',$pagedata);
        }
    }
}

