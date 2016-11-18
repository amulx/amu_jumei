<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2012 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class topwap_ctl_default extends topwap_controller
{

    public function __construct()
    {
        parent::__construct();
        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);
        $conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');
        $this->appid = $conf['appKey'];
        $this->appsecret = $conf['appSecret'];
    }

    public function index()
    {
        // logger::info('[topwap.default] : openid-----------'.$_SESSION['user_openid']);
        theme::setTitle('易拿货');
        $GLOBALS['runtime']['path'][] = array('title'=>app::get('topwap')->_('首页〉'),'link'=>kernel::base_url(1));
        $this->setLayoutFlag('index');

        //首页分享链接
        $pagedata['redirect_uri'] = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->appid.'&redirect_uri='.url::action('topwap_ctl_spread@spread',array('user_id'=>userAuth::id())).'&response_type=code&scope=snsapi_base&state=1';
    
        /**
            直接获取数据库方法  begin
        */
        // $qb = app::get('jumei')->database()->createQueryBuilder();
        // $limit = 20;
        // $qb->select('I.item_id,I.title,I.image_default_id,C.sold_quantity,I.price')
        //    ->from('sysitem_item','I')
        //    ->leftJoin('I','sysitem_item_count', 'C','I.item_id=C.item_id')
        //    ->leftJoin('I', 'sysitem_item_status', 'ST', 'I.item_id=ST.item_id')
        //    ->setMaxResults($limit)
        //    ->andWhere("ST.approve_status = 'onsale'")
        //    ->addOrderBy('I.modified_time','DESC')
        //    ->addOrderBy('C.sold_quantity','DESC');
        //    // return $qb->getSql();
        // $pagedata['items'] = $qb->execute()->fetchAll();
        /**
            直接获取数据库方法  end
        */

       /**
        通过curl获取第三方数据  begin
        */
        $url = 'http://www.aiyoupin.com/yinahuo_wap_index.php';
        $result = json_decode($this->postbbb($url),true);
        $pagedata['items'] = $result;
        $pagedata['today_ymd'] = time();

        /** 获取接口中的热搜  begin*/
        $hoturl = 'http://www.aiyoupin.com/yinahuo_hot_keyword.php';
        $hotdata = json_decode($this->postbbb($hoturl),true);
        $pagedata['hotsearch'] = json_encode($hotdata);
        /** 获取接口中的热搜  end*/
       /**
        通过curl获取第三方数据  end
        */
       
        //判断当前访问者的移动设备   规则是不登录不显示 add@by cg 2016/9/2
        if( userAuth::check() ){
            if ($this->is_weixin()) {
                $pagedata['download_url'] = "http://a.app.qq.com/o/simple.jsp?pkgname=com.yinahuo.mapp";//不用区分android和ios 统一下载链接
            }
        }

        return $this->page('topwap/index.html',$pagedata);
    }

    public function ajaxIndex(){
        $url = 'http://www.aiyoupin.com/test_index.php';
        $result = $this->postbbb($url);
        return $this->splash('success',null,$result,true);
        $page = input::get('p');
        if ($page && $page < 0) {
            $page = 0;
        } 
        //SELECT count(item_id) FROM sysitem_item
        $count = app::get('jumei')->database()->executeQuery("SELECT COUNT(I.item_id) from sysitem_item I LEFT JOIN sysitem_item_count C  on I.item_id=C.item_id LEFT JOIN sysitem_item_status ST on I.item_id=ST.item_id where ST.approve_status = 'onsale'")->fetchColumn();
        $limit = 20;
        $totalpage = ceil($count / $limit); 
        if ($page >= $totalpage) {
            return $this->splash('error',null,'没有更多商品了',true);
        }
        $qb = app::get('jumei')->database()->createQueryBuilder();
        $qb->select('I.item_id,I.title,I.image_default_id,C.sold_quantity,I.price')
           ->from('sysitem_item','I')
           ->leftJoin('I','sysitem_item_count', 'C','I.item_id=C.item_id')
           ->leftJoin('I', 'sysitem_item_status', 'ST', 'I.item_id=ST.item_id')
           ->setFirstResult($page * $limit)
           ->setMaxResults($limit)
           ->andWhere("ST.approve_status = 'onsale'")
           ->addOrderBy('I.modified_time','DESC')
           ->addOrderBy('C.sold_quantity','DESC');
          // return $qb->getSql();  
        $pagedata['items']= $qb->execute()->fetchAll();
        // return $this->splash('success',$url,$items,true);   
        // if( !$pagedata['pagers']['total'] )
        // {
        //     return view::make('topwap/empty/item.html',$pagedata);
        // }

        if(count($pagedata['items']) > 1)
        {
            return view::make('topwap/default/item_list.html',$pagedata);
        }else{
            return $this->splash('error',null,'没有更多商品了',true);
        }
    }

    public function switchToPc()
    {
        setcookie('browse', 'pc');
        return redirect::route('topc');
    }

    //更多
    public function more(){
        $kname = input::get('kname');
        theme::setTitle($kname);
        $pagedata['type'] = input::get('type');
        return $this->page('topwap/default/more.html',$pagedata);
    }

    //买家说列表
    public function buyersaidList(){
        $filter = array();
        $limit = 2;
        $offset = 0;
        $ojbBuyersaid = app::get('sysrate')->model('buyersaid');
        $pagedata['bsdata'] = $ojbBuyersaid->getList('*',$filter,$offset,$limit);
        $count = $ojbBuyersaid->count();
        $pagedata['pagers']['total']= ceil($count/$limit);

        return $this->page('topwap/default/bslist.html',$pagedata);
    }

    public function ajaxGetbsList(){
        $pages = input::get('pages');
        $filter = array();
        $limit = 2;
        if(!$pages){
            $pages = 1;
        }
        $offset = ($pages-1) * $limit;
        $ojbBuyersaid = app::get('sysrate')->model('buyersaid');
        $bsList = $ojbBuyersaid->getList('*',$filter,$offset,$limit);

        $count = $ojbBuyersaid->count();
        $pagedata['pagers']['total']= ceil($count/$limit);
        $pagedata['bsdata'] = $bsList;
        if($pagedata['bsdata'])
        {
            return view::make('topwap/default/bsdatas.html',$pagedata);
        }
    }
}
