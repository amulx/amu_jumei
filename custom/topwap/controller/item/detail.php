<?php

/**
 * detail.php 商品详情
 *
 * @author
 * @copyright  Copyright (c) 2005-2015 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
class topwap_ctl_item_detail extends topwap_controller {
    var $appid; //微信param
    var $appsecret;//微信param
    public function __construct()
    {
        parent::__construct();
        $countData = kernel::single('topwap_cart')->getCartCount();
        userAuth::syncCookieWithCartNumber($countData['number']);
        userAuth::syncCookieWithCartVariety($countData['variety']);
        $conf = app::get('sysuser')->getConf('sysuser_plugin_wapweixin');//微信param
        $this->appid = $conf['appKey'];//微信param
        $this->appsecret = $conf['appSecret'];//微信param
    }

    public function index()
    {

        $itemId = intval(input::get('item_id'));
        if( empty($itemId) )
        {
            return redirect::action('topwap_ctl_default@index');
        }

        
        if( userAuth::check() )
        {
            // -----------------增加商品访问统计情况  begin---------------add@by cg 2016/9/2
            $userInfo = userAuth::getUserInfo();//获取当前用户信息
            $openid = $_SESSION['user_openid'];
            // var_dump($userInfo);exit();   
            if (empty($openid)) {//session中不存在openid时去数据库取
                $filter = array('user_id' => $userInfo['userId']);
                $result = app::get('sysuser')->model('user_distri')->getRow('openid', $filter);
                $openid = $result['openid'];
                // var_dump($ymd_time);exit;
            }
            $static_data['openid'] = $openid;
            $static_data['view_time'] = time();//当前时间
            $static_data['ymd_time'] = date("Ymd",$static_data['view_time']);
            //访问者ip
            $static_data['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_HOST'];
            $static_data['user_id'] = $userInfo['userId'];//当前用户ip
            $static_data['mobile'] = $userInfo['mobile'];//当前用户手机
            $static_data['item_id'] = $itemId;
            $item_static = app::get('sysitem')->model('item_static');
            $item_static->save($static_data);   
            // -----------------增加商品访问统计情况  begin-------------------------------                  
            $pagedata['nologin'] = 1;
            //判断当前访问者的移动设备   规则是不登录不显示 add@by cg 2016/9/2

            if ($this->is_weixin()) {
                $pagedata['download_url'] = "http://a.app.qq.com/o/simple.jsp?pkgname=com.yinahuo.mapp";//不用区分android和ios 统一下载链接
            }
 
        }
        $pagedata['image_default_id'] = $this->__setting();
        $params['item_id'] = $itemId;
        $params['fields'] = "*,item_desc.wap_desc,item_count,item_store,item_status,sku,item_nature,spec_index";
        $detailData = app::get('topwap')->rpcCall('item.get',$params);
        
        if(!$detailData)
        {
            $pagedata['error'] = "商品过期不存在";
            return $this->page('topwap/item/detail/error.html', $pagedata);
        }

        if(count($detailData['sku']) == 1)
        {
            $detailData['default_sku_id'] = array_keys($detailData['sku'])[0];
        }

        $detailData['valid'] = $this->__checkItemValid($detailData);
        if($detailData['use_platform'] != 2 && $detailData['use_platform'] != 0)
        {
            $pagedata['error'] = "该商品仅适用于电脑端";
            return $this->page('topwap/item/detail/error.html', $pagedata);
        }

        //相册图片
        if( $detailData['list_image'] )
        {
            $detailData['list_image'] = explode(',',$detailData['list_image']);
            $detailData['list_image_first'] = reset($detailData['list_image']);
            $detailData['list_image_last'] = end($detailData['list_image']);
        }

        $dlytmplParams['template_id'] = $detailData['dlytmpl_id'];
        $dlytmplParams['fields'] = 'is_free';
        //获取是否免邮的信息
        $dlytmplInfo = app::get('topwap')->rpcCall('logistics.dlytmpl.get',$dlytmplParams);
        if($dlytmplInfo)
        {
            $pagedata['freeConf'] = $dlytmplInfo['is_free'];
        }
        //获取商品的促销信息
        $promotionInfo = app::get('topwap')->rpcCall('item.promotion.get', array('item_id'=>$itemId));
        if($promotionInfo)
        {

            foreach($promotionInfo as $vp)
            {
                $basicPromotionInfo = app::get('topwap')->rpcCall('promotion.promotion.get', array('promotion_id'=>$vp['promotion_id'], 'platform'=>'wap'));
                if($basicPromotionInfo['valid']===true)
                {
                    $pagedata['promotionDetail'][$vp['promotion_id']] = $basicPromotionInfo;
                    $pagedata['promotionTag'][$basicPromotionInfo['promotion_type']] = $basicPromotionInfo;
                    //2016.7.20 hui增加X件Y折促销查询出x和y
                    if($basicPromotionInfo['promotion_type'] == 'xydiscount' ){
                        $xydiscount = app::get('topwap')->rpcCall('promotion.xydiscount.get', array('xydiscount_id'=>$basicPromotionInfo['rel_promotion_id'], 'shop_id'=>$basicPromotionInfo['shop_id']));
                        $xy = explode('|',$xydiscount['condition_value']);
                        $xy[1] = '0.'.$xy[1];
                        $pagedata['promotionDetail'][$vp['promotion_id']]['xydiscount'] = $xy;
                    }
                    //----------------------------
                }
            }
        }
        $pagedata['promotion_count'] = count($pagedata['promotionDetail']);

        // 活动促销(如名字叫团购)
        $activityDetail = app::get('topwap')->rpcCall('promotion.activity.item.info',array('item_id'=>$itemId,'valid'=>1),'buyer');
        if($activityDetail)
        {
            $pagedata['activityDetail'] = $activityDetail;
        }

        //如果商品没有规格，就修改默认规格 2016.9.2 czh
        if($detailData['spec_desc']){
            $detailData['spec'] = $this->__getSpec($detailData['spec_desc'], $detailData['sku']);
        }else{
            $param = array(
                array(
                    "item"=>array(
                        "item_id"=>$itemId,//修改就放id
                        "shop_id" => $detailData['shop_id'],//店铺id
                        'title' => $detailData['title'], //商品标题
                        'brand_id' => $detailData['brand_id'], //品牌id
                        'price' => $detailData['price'], //商品价格
                        'mkt_price' => $detailData['mkt_price'], //商品市场价格
                        'cost_price' => $detailData['cost_price'], //商品成本价格
                        "dlytmpl_id"=>$detailData['dlytmpl_id'],//运费模板
                    ),
                    "cat_id"=>$detailData['cat_id'],//分类id
                )
            );
            $resultItem = app::get('topwap')->rpcCall('item.get.commodity',$param);
            $resItem = explode(':',$resultItem);
            if($resItem[0] != '-1'){
                return redirect::action('topwap_ctl_item_detail@index', ['item_id'=>$resItem[1]])->send();
            }
        }
        //2016.9.2
        
        $bsdatas = app::get('sysrate')->model('buyersaid')->getList('*',['item_id'=>$itemId]);
        foreach ($bsdatas as $value) {
            if($value['result'] == 'good'){
                $detailData['rate_good_count'] += 1;
            }
            if($value['result'] == 'neutral'){
                $detailData['rate_neutral_count'] += 1;
            }
            if($value['result'] == 'bad'){
                $detailData['rate_bad_count'] += 1;
            }
            $detailData['rate_count'] += 1;
        }

        $pagedata['item'] = $detailData;
        //倒计时3天
        $end_item = $detailData['modified_time']+(60*60*24*3);
        $pagedata['end_item'] = $end_item;
        // $pagedata['end_item'] = strtotime(date('Y-m-d',$end_item));//改成零时结束
        //倒计时3天

        // if (!$this->is_weixin()) {
        //     $pagedata['app_img'] = $this->suolue($detailData['image_default_id'],100,100,$itemId);
        // }
        theme::setTitle("易拿货-爆款批发-工厂拿货:".$detailData['title']);

        $pagedata['shop'] = app::get('topwap')->rpcCall('shop.get',array('shop_id'=>$pagedata['item']['shop_id']));
        $pagedata['next_page'] = url::action("topwap_ctl_item_detail@index",array('item_id'=>$itemId));
        //商品收藏和店铺收藏情况
        $pagedata['collect'] = $this->__CollectInfo(input::get('item_id'),$pagedata['shop']['shop_id']);
        // 获取评价
        $pagedata['countRate'] = $this->__getRateResultCount($detailData);
        // 获取当前平台设置的货币符号和精度
        $cur_symbol = app::get('topwap')->rpcCall('currency.get.symbol',array());
        $pagedata['cur_symbol'] = $cur_symbol;
        $this->setLayoutFlag('product');


        //添加微信相关数据
        $pagedata['timestamp'] = $timestamp = time();     //当前时间戳
        $pagedata['nonceStr'] = $nonceStr = $this->createNonceStr(); //16位随机字符串
        // $url = url::action('topwap_ctl_item_detail@index',array('item_id'=>$itemId));
        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $jsapiTicket = $this->getJsApiTicket();
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";  //url待解决
        // echo "<pre>";
        // echo $string;
        // echo "<h1>";
        $pagedata['signature'] = sha1($string);
        // echo $pagedata['signature'];
        // exit();
        $pagedata['appid'] = $this->appid;
        // $pagedata['redirect_uri'] = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->appid.'&redirect_uri='.url::action('topwap_ctl_item_detail@index',array('item_id'=>$itemId)).'&response_type=code&scope=snsapi_base&state=1';
        $pagedata['redirect_uri'] = url::action('topwap_ctl_item_detail@detailTransit',array('item_id'=>$itemId));
        if (!$this->is_weixin()) {
            $pagedata['app_img'] = $this->suolue($detailData['image_default_id'],100,100,$itemId);
            // $qrcode = getQrcodeUri($pagedata['redirect_uri'],180,0);
            // //保存base64字符串为图片 
            // $pagedata['qrcode_img'] = $this->genQrcodePic($qrcode,$itemId);
        }

        return $this->page('topwap/item/detail/index.html', $pagedata);
    }
    //商品详情页中转路由
    public function detailTransit(){
        $itemId = input::get('item_id');
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->appid.'&redirect_uri='.url::action('topwap_ctl_item_detail@index',array('item_id'=>$itemId)).'&response_type=code&scope=snsapi_base&state=1';
        return redirect::away($url);
    }

    //商品描述
    public function itemPic()
    {
        theme::setTitle('商品描述');
        $itemId = intval(input::get('item_id'));
        if( empty($itemId) )
        {
            return redirect::action('topwap_ctl_default@index');
        }

        $pagedata['image_default_id'] = $this->__setting();
        $params['item_id'] = $itemId;
        $params['fields'] = "*,item_desc.wap_desc,item_count,item_store,item_status,sku,item_nature,spec_index";
        $detailData = app::get('topwap')->rpcCall('item.get',$params);
        $pagedata['title'] = "商品描述";
        $pagedata['itemPic'] = $detailData;
        // 商品自然属性
        $pagedata['itemParamshtml'] = view::make('topwap/item/detail/itemparams.html', $detailData)->render();
        // 商品备注
        $pagedata['itemremarkhtml'] = view::make('topwap/item/detail/itemremark.html',$detailData)->render();

        return $this->page('topwap/item/detail/itempic.html', $pagedata);
    }

    // 商品评价
    public function getItemRate()
    {
        theme::setTitle('商品评价');
        $itemId = intval(input::get('item_id'));
        if( empty($itemId) ) return '';

        $pagedata =  $this->__searchRate($itemId);
        $pagedata['item_id'] = $itemId;
        $pagedata['title'] = app::get('topwap')->_('商品评价');

        return $this->page('topwap/item/detail/itemrate.html', $pagedata);
    }

    // 获取评价列表
    public function getItemRateList()
    {
        try {
            $itemId = intval(input::get('item_id'));
            if( empty($itemId) ) return '';
            $pagedata=$this->__searchRate($itemId);
            $data['pages'] = $pagedata['pages'];
            $data['total'] = $pagedata['total']; // 总页数
            $data['rate_type'] = $pagedata['rate']['result'];
            $data['success'] = true;

            $data['html'] = view::make('topwap/item/detail/itemrate_list.html',$pagedata)->render();
            if(intval($pagedata['total']) <=0)
            {
               $data['html'] = view::make('topwap/empty/rate.html')->render();
            }

        } catch (Exception $e) {
            return $this->splash('error', null, $e->getMessage(), true);
        }
        return response::json($data);
    }

    public function viewNotifyItem()
    {
        theme::setTitle('到货通知');
        $pagedata = input::get();
        $pagedata['title'] = app::get('topwap')->_('到货通知');

        return $this->page('topwap/item/detail/shipment.html', $pagedata);
    }
    // 到货通知
    public function userNotifyItem()
    {
        try
        {
            $postdata = $this->__checkdata(input::get());
            $params['shop_id'] = $postdata['shop_id'];
            $params['item_id'] = $postdata['item_id'];
            $params['sku_id'] = $postdata['sku_id'];
            $params['email'] = $postdata['email'];
            $result = app::get('topwap')->rpcCall('user.notifyitem',$params);
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            return $this->splash('error',$url,$msg);
        }
        $url = url::action('topwap_ctl_item_detail@index', ['item_id'=>$postdata['item_id']]);

        if( $result['sendstatus'] == 'ready' )
        {
            $msg = app::get('topwap')->_('您已经填过该商品的到货通知');
        }
        else
        {
            $msg = app::get('topwap')->_('预订成功');
        }
        return $this->splash('success', $url, $msg);
    }

    private function __checkdata($data)
    {
        $validator = validator::make(
                ['shop_id' => $data['shop_id'] , 'item_id' => $data['item_id'],'sku_id' => $data['sku_id'],'email' => $data['email']],
                ['shop_id' => 'required'       , 'item_id' => 'required',     'sku_id' => 'required', 'email' => 'required|email'],
                ['shop_id' => '店铺id不能为空！' , 'item_id' => '商品id不能为空！','sku_id' => '货品id不能为空！','email' => '邮件不能为空！|邮件格式不正确!']
        );
        if ($validator->fails())
        {
            $messages = $validator->messagesInfo();
            foreach( $messages as $error )
            {
                throw new Exception( $error[0] );
            }
        }
        return $data;
    }

    // 获取评论百分比
    private function __getRateResultCount($detailData)
    {
        if( !$detailData['rate_count'] )
        {
            $countRate['good']['num'] = 0;
            $countRate['good']['percentage'] = '0%';
            $countRate['neutral']['num'] = 0;
            $countRate['neutral']['percentage'] = '0%';
            $countRate['bad']['num'] = 0;
            $countRate['bad']['percentage'] = '0%';
            return $countRate;
        }
        $countRate['good']['num'] = $detailData['rate_good_count'];
        $countRate['good']['percentage'] = sprintf('%.2f',$detailData['rate_good_count']/$detailData['rate_count'])*100 .'%';
        $countRate['neutral']['num'] = $detailData['rate_neutral_count'];
        $countRate['neutral']['percentage'] = sprintf('%.2f',$detailData['rate_neutral_count']/$detailData['rate_count'])*100 .'%';
        $countRate['bad']['num'] = $detailData['rate_bad_count'];
        $countRate['bad']['percentage'] = sprintf('%.2f',$detailData['rate_bad_count']/$detailData['rate_count'])*100 .'%';
        $countRate['total'] = $detailData['rate_count'];
        return $countRate;
    }

    private function __searchRate($itemId)
    {
        $rate_type_arr = ['1'=>'good','2'=>'neutral','3'=>'bad'];
        $current = input::get('pages',1);
        $rate_type = input::get('rate_type');
        $pagedata['rate_type_group'] = $rate_type;
        $limit = 10;
        $params = ['item_id'=>$itemId,'page_no'=>intval($current),'page_size'=>intval($limit),'fields'=>'*,append'];
        if( $rate_type == '4'  )
        {
            $params['is_pic'] = true;
            $pagedata['query_type'] = 'pic';
        }
        else
        {
            $pagedata['query_type'] = 'content';
        }

        if($rate_type)
        {
            $params['result'] = $rate_type_arr[$rate_type];
            $pagedata['rate_type'] = $rate_type_arr[$rate_type];
        }
        $data = app::get('topwap')->rpcCall('rate.list.get', $params);

        foreach($data['trade_rates'] as $k=>$row )
        {
            if($row['rate_pic'])
            {
                $data['trade_rates'][$k]['rate_pic'] = explode(",",$row['rate_pic']);
            }

            if( $row['append']['append_rate_pic'] )
            {
                $data['trade_rates'][$k]['append']['append_rate_pic'] = explode(',', $row['append']['append_rate_pic']);
            }

            $userId[] = $row['user_id'];
        }

        $pagedata['rate']= $data['trade_rates'];
        if( $userId )
        {
            $pagedata['userName'] = app::get('topwap')->rpcCall('user.get.account.name',array('user_id'=>$userId),'buyer');
        }

        //处理翻页数据
        if($data['total_results']>0) $total = ceil($data['total_results']/$limit);
        $current = $total < $current ? $total : $current;

        $pagedata['pages'] = $current;
        $pagedata['total'] = $total;

        return $pagedata;
    }


    private function __setting()
    {
        $setting = kernel::single('image_data_image')->getImageSetting('item');
        return $setting;
    }

    //当前商品收藏和店铺收藏的状态
    private function __CollectInfo($itemId,$shopId)
    {
        $userId = userAuth::id();
        $collect = unserialize($_COOKIE['collect']);
        if(in_array($itemId, $collect['item']))
        {
            $pagedata['itemCollect'] = 1;
        }
        else
        {
            $pagedata['itemCollect'] = 0;
        }
        if(in_array($shopId, $collect['shop']))
        {
            $pagedata['shopCollect'] = 1;
        }
        else
        {
            $pagedata['shopCollect'] = 0;
        }

        return $pagedata;
    }

    private function __getSpec($spec, $sku)
    {
        if( empty($spec) ) return array();

        foreach( $sku as $row )
        {
            $key = implode('_',$row['spec_desc']['spec_value_id']);

            if( $key )
            {
                $result['specSku'][$key]['sku_id'] = $row['sku_id'];
                $result['specSku'][$key]['item_id'] = $row['item_id'];
                $result['specSku'][$key]['price'] = $row['price'];
                $result['specSku'][$key]['store'] = $row['realStore'];
                if( $row['status'] == 'delete')
                {
                    $result['specSku'][$key]['valid'] = false;
                }
                else
                {
                    $result['specSku'][$key]['valid'] = true;
                }

                $specIds = array_flip($row['spec_desc']['spec_value_id']);
                $specInfo = explode('、',$row['spec_info']);
                foreach( $specInfo  as $info)
                {
                    $id = each($specIds)['value'];
                    $result['specName'][$id] = explode('：',$info)[0];
                }
            }
        }
        return $result;
    }

    private function __checkItemValid($itemsInfo)
    {
        if( empty($itemsInfo) ) return false;

        //违规商品
        if( $itemsInfo['violation'] == 1 ) return false;

        //未启商品
        if( $itemsInfo['disabled'] == 1 ) return false;

        //未上架商品
        if($itemsInfo['approve_status'] == 'instock' ) return false;

        //库存小于或者等于0的时候，为无效商品
        //if($itemsInfo['realStore'] <= 0 ) return false;

        return true;
    }


    //生成随机码
    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
          $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getAccessToken() {

            if ($_SESSION['access_token_expire_time']>time() && $_SESSION['access_token']) {
                $access_token = $_SESSION['access_token'];
            } else {
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
                $res = $this->httpGet($url);
                $access_token = $res['access_token'];
                $_SESSION['access_token'] = $access_token;
                $_SESSION['access_token_expire_time'] = time() + 7000;
            }            

            return $access_token;
    }

    //获取jsapi_ticket全局票据
    private function getJsApiTicket() {
        //如果session中保存有效的jsapi_ticket   
        if ($_SESSION['jsapi_ticket_expire_time']>time() && $_SESSION['jsapi_ticket']) {
            $jsapi_ticket = $_SESSION['jsapi_ticket'];
        } else {
            $accessToken = $this->getAccessToken();
            // https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=ACCESS_TOKEN&type=jsapi
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$accessToken&type=jsapi";
            $res = $this->httpGet($url);
            $jsapi_ticket = $res['ticket'];
            $_SESSION['jsapi_ticket'] = $jsapi_ticket;
            $_SESSION['jsapi_ticket_expire_time'] = time() + 7000;
        }
        
        return $jsapi_ticket;
    }

    /**
     *$filesrc是要缩略图片的具体路径
     *$dst_w  缩略图的宽
     *$dst_h  缩略图的高
     */
    function suolue($filesrc,$dst_w,$dst_h,$item_id){

    // ============================1、打开原图片=============================================
            // 获取图片路径
        $imfile_src = $filesrc;
            // 获取图片基本信息
        $im_info = getimagesize($imfile_src);
            //获取图片类型
        $im_type = image_type_to_extension($im_info[2],false);
            //创建与原图类型一样的图片
        $fun = "imagecreatefrom{$im_type}";
        $image = $fun($imfile_src);


    // ============================2、操作图片===============================================
            // 缩略图的宽高
        $dst_w = $dst_w;
        $dst_h = $dst_h;

            // 图片比例的处理
        if (($dst_w/$im_info[0])>($dst_h/$im_info[1])) {
            $bili = $dst_h/$im_info[1];
        }else{
            $bili = $dst_w/$im_info[0];
        }
            // 等比缩放的宽、高
        $dst_w = floor($im_info[0]*$bili);
        $dst_h = floor($im_info[1]*$bili);
            //创建缩放图片
        $image_thumb = imagecreatetruecolor($dst_w, $dst_h);

        imagecopyresampled($image_thumb, $image, 0, 0, 0, 0, $dst_w, $dst_h, $im_info[0], $im_info[1]);
            //销毁大图
        imagedestroy($image);
    // ===========================3、输出图片==============================================
        // header("Content-Type:".$im_info['mime']);
        
        $func = "image{$im_type}";
        // $func($image_thumb);
        $func($image_thumb,"images/".$item_id."thumbimage.".$im_type);
        // $url = kernel::base_url().'/images/app/thumbimage'.userAuth::id();echo "<pre>";
        // var_dump($url);exit();
        // $func($image_thumb,kernel::base_url().'/images/app/thumbimage'.userAuth::id().$im_type);//保存图片
        $app_img_name =  $item_id."thumbimage.".$im_type;
    // ===========================4、销毁图片 释放内存====================================

        imagedestroy($image_thumb);
        return  $app_img_name;
    }

    //生成商品详情页二维码图片
    public function genQrcodePic($picInfo,$item_id){
        //匹配出图片的格式 
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $picInfo, $result)){ 
            $type = $result[2];
            // $new_file = app::get('topwap')->res_url."/images/{$item_id}qrcode.{$type}";  
            // var_dump($new_file);exit();
            $new_file = "images/{$item_id}"."qrcode".".{$type}"; 
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $picInfo)))){ 
                return "{$item_id}"."qrcode".".{$type}";
            }
        }        
    }
    

}
