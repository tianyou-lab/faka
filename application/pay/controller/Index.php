<?php
namespace app\pay\controller;

use app\jingdian\model\OrderModel;
use app\jingdian\model\GoodsListModel;
use think\Config;
use think\Loader;
use think\Db;
use org\Lanpay;
use org\WxpayService;

require ROOT_PATH.'/vendor/riverslei/payment/autoload.php';
use Payment\Common\PayException;
use Payment\Client\Charge;
use Payment\Configv;
//支付方法
class Index extends Base
{


    public function req()
    {
        $param = self::getParam();
        $this->assign('param', $param);
    }
    public function reqPay()
    {
        $param = inputself();
		if ($param['pd_FrpId'] == 'wxpay') {
			$zhifu="req".config('blfk_pay_wxpay');
		}
		if ($param['pd_FrpId'] == 'alipay') {
			$zhifu="req".config('blfk_pay_alipay');
		}
		if ($param['pd_FrpId'] == 'qqpay') {
			$zhifu="req".config('blfk_pay_qqpay');
		}
		if ($param['pd_FrpId'] == 'unionPay') {
			$zhifu="req".config('blfk_pay_unionpay');
		}
		if ($param['pd_FrpId'] == 'tenpay') {
			$zhifu="req".config('blfk_pay_tenpay');
		}

		$ordermoney =Db::name('info')->where('mcard', $param['p2_Order'])->find();		
		$userresult=Db::name('member_payorder')->where('orderno', $param['p2_Order'])->find();		
		if(!empty($userresult['memberid'])){
			$money = $userresult['money'];
		}else{
			$money = $ordermoney['mamount'];
		}//重新从数据库读取订单金额
		return $this->$zhifu($money);
    }
	
	/*
     * 免签支付&聚合支付&微信&支付宝
     */
    public function reqMq($money=99999)
    {		
       $param = inputself();
	  
	     if ($param['pd_FrpId'] == 'unionPay') {
			$type = '2'; 
        }
	     if ($param['pd_FrpId'] == 'wxpay') {
			$type = '3'; 
        }
	     if ($param['pd_FrpId'] == 'alipay') {
			$type = '1'; 
        }

		$host =config('is_https') . '://' . $this->url_host."/createOrder";		
		$_sign = md5($param['p2_Order'].$param['p5_Pid'].$type.$money.config('pay_mqkey'));
		$p = "payId=".$param['p2_Order'].'&param='.$param['p5_Pid'].'&type='.$type."&price=".str_replace(',', '', $money).'&sign='.$_sign.'&isHtml=1';
		$url =$host."?".$p;		
		header("Location:{$url}"); //跳转到支付页面
		exit;
	}
	
	
	/*
     * 微信官方扫码
     */
    public function reqWeixin($money=99999)
    {	 
		$param = inputself();
		$mchid = config('mch_id');          //微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送
		$appid = config('wx_appid');  //公众号APPID 通过微信支付商户资料审核后邮件发送
		$apiKey = config('wx_apiKey');   //https://pay.weixin.qq.com 帐户设置-安全设置-API安全-API密钥-设置API密钥
		//NATIVE H5开始
		$outTradeNo = $param['p2_Order'];     //订单号
		$payAmount = str_replace(',', '', $money);          //付款金额，单位:元
		$orderName = $param['p2_Order'];    //订单标题
		$notifyUrl = config('is_https') . '://' . $this->url_host ."/pay/callback/callbackweixin.html";     //付款成功后的回调地址(不要有问号)
		$payTime = time();      //付款时间
		$returnUrl = 'http://' . $this->url_host;
		
		if(is_weixin()&&config('wx_jsapi')==1){//jsapi
			$jsapipay = config('is_https') . '://' . $this->url_host."/pay/index/wxjsapi.html?p2_Order=".$param['p2_Order'];
			Header("Location: $jsapipay");
			exit();
		}
		
		if(isMobilePc()&&config('wx_h5')==1){//h5
			$wxPay = new WxpayService($mchid,$appid,$apiKey);
			$wapUrl = config('is_https') . '://' . $this->url_host;
			$wapName = config('shop_name');
			$mwebUrl= $wxPay->h5pay($payAmount,$outTradeNo,$orderName,$notifyUrl,$wapName,$wapUrl,$returnUrl); 
			$this->assign('mwebUrl', $mwebUrl);
			$this->assign('param', $param);
			$this->assign('money', $money);
			return $this->fetch('/index/wxh5');
		}
		//默认pc native
		$wxPay = new WxpayService($mchid,$appid,$apiKey);
		$arr = $wxPay->nativepay($payAmount,$outTradeNo,$orderName,$notifyUrl,$payTime);
		$ewm = $arr['code_url'];
		$this->assign('erweima', $ewm);
		$this->assign('param', $param);
		$this->assign('money', $money);
		return $this->fetch('/index/wx');
	
		
		//NATIVE H5结束		
	}
	
	
	
	/*
     * 微信官方扫码
     */
    public function wxjsapi()
    {	 
		$param = inputself();
		$money = 999999;
		$ordermoney =Db::name('info')->where('mcard', $param['p2_Order'])->find();		
		$userresult=Db::name('member_payorder')->where('orderno', $param['p2_Order'])->find();		
		if(!empty($userresult['memberid'])){
			$money = $userresult['money'];
		}else{
			$money = $ordermoney['mamount'];
		}//重新从数据库读取订单金额
		
		$mchid = config('mch_id');          //微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送
		$appid = config('wx_appid');  //公众号APPID 通过微信支付商户资料审核后邮件发送
		$apiKey = config('wx_apiKey');   //https://pay.weixin.qq.com 帐户设置-安全设置-API安全-API密钥-设置API密钥
        $appKey =  config('wx_appKey'); //微信支付申请对应的公众号的APP Key
		
		//①、获取用户openid
		$wxPay = new WxpayService($mchid,$appid,$apiKey,$appKey);
		$openId = $wxPay->GetOpenid();      //获取openid
		if(!$openId) exit('获取openid失败');
		//②、统一下单
		$outTradeNo = $param['p2_Order'];     //订单号
		$payAmount = str_replace(',', '', $money);          //付款金额，单位:元
		$orderName = $param['p2_Order'];    //订单标题
		$notifyUrl = config('is_https') . '://' . $this->url_host ."/pay/callback/callbackweixin.html";     //付款成功后的回调地址(不要有问号)
		$payTime = time();      //付款时间
		$jsApiParameters = $wxPay->jsapipay($openId,$payAmount,$outTradeNo,$orderName,$notifyUrl,$payTime);
		$jsApiParameters = json_encode($jsApiParameters);
		$this->assign('jsApiParameters', $jsApiParameters);
		$this->assign('param', $param);
		$this->assign('money', $money);
		return $this->fetch('/index/wxjsapi');
		
	}
	/*
     * 支付宝当面付
     */
    public function reqAlipay($money=99999)
    {
       $param = inputself();
        $config = [
            'use_sandbox' => false,
            'app_id' => config('app_id'),    //应用appid
            'sign_type' => 'RSA2',
            'ali_public_key' => config('ali_public_key'),
            'rsa_private_key' => config('rsa_private_key'),
            'notify_url' =>  config('is_https') . '://' . $this->url_host ."/pay/callback/callbackalipay.html",                      //异步通知地址
            'return_url	' =>  config('is_https') . '://' . $this->url_host . "/pay/callback/callbackalipay.html",
            'return_raw' => true
        ];

        $data = [
            'order_no' => $param['p2_Order'],     //商户订单号，需要保证唯一
            'amount' => str_replace(',', '', $money),           //订单金额，单位 元
            'subject' => '收银台支付',      //订单标题
            'body' => "消费".$money."元",      //订单描述
        ];
        try {
            $ewm = Charge::run(Configv::ALI_CHANNEL_QR, $config, $data);
        } catch (PayException $e) {
            echo $e->errorMessage();
            exit;
        }

        $this->assign('erweima', $ewm);
        $this->assign('param', $param);
		$this->assign('money', $money);
        return $this->fetch('/index/index');
    }
	
	/*
     * 蓝支付
     */
	 public function reqYzf($money=99999)
    {
    	
		$param = inputself(); 	
    	
		$lanpay = new Lanpay(config('pay_yzfid'), config('pay_yzfkey'));
		$notify_url = config('is_https') . '://' . $this->url_host ."/pay/callback/notifyyzf.html";
        if ($param['pd_FrpId'] == 'alipay') {
			$payid="1";
			$payname="支付宝";           
			$code_url = $lanpay->f2f_pay($param['p2_Order'], config('shop_name'), round($money,2), $notify_url); 
        }
		if ($param['pd_FrpId'] == 'wxpay') {
			$payid="2";
			$payname="微信";			
			$code_url = $lanpay->mp_pay($param['p2_Order'], config('shop_name'), round($money,2), $notify_url);  
        }

		$this->assign('payid', $payid);
		$this->assign('payname', $payname);
		$this->assign('param', $param);
		$this->assign('money', $money);
		$this->assign('erweima', $code_url);
		return $this->fetch('/index/lanzf');	
        
            
            
      
    }
    
    
    
	/*
     * 码支付
     */
	 public function reqMzf($money=99999)
    {
		$param = inputself();   
	     if ($param['pd_FrpId'] == 'unionPay') {
			$type = '4'; 
        }
	     if ($param['pd_FrpId'] == 'wxpay') {
			$type = '1'; 
        }
	     if ($param['pd_FrpId'] == 'alipay') {
			$type = '2'; 
        }
		$mid=config('pay_mzfid');
		$key=config('pay_mzfkey');
		$beizhu=config('shop_name');
		$type=$type;
		$price=str_replace(',', '', $money);
		$payId=$param['p2_Order'];
		$sign =md5($mid . $payId . $beizhu . $type . $price . $key);
		$notifyUrl =config('is_https') . '://' . $this->url_host ."/pay/callback/callbackmzf.html";
		$returnUrl =config('is_https') . '://' . $this->url_host ."/pay/callback/callbackmzf.html";
		$sign =md5($mid . $payId . $beizhu . $type . $price . $key);
		$fanhui =  $this->getCurl("http://jk.lailiyun.com/createOrder/?isHtml=0&mid=".$mid."&payId=".$payId."&price=".$price."&type=".$type."&param=".$beizhu."&notifyUrl=".$notifyUrl."&returnUrl=".$returnUrl."&sign=".$sign);	
		$this->assign('fanhui', $fanhui);		
		$this->assign('param', $param);
		return $this->fetch('/index/mazf');	
		
		 

      
    }
    
       	/**
         * 验签
         *
         * @author AnLin
         * @version V1.0.0
         * @since 2018/6/4
         */
	private function sign($dataArr,$signkey)
	{
        ksort($dataArr);
        $str = '';
        foreach ($dataArr as $key => $value) {
            if(!empty($value))
                $str.=$key.'='.$value.'&';
        }
        return strtoupper(md5($str . "key=" . $signkey));
	}
	

    //发送Http请求
    private function getCurl($url, $post = 0, $cookie = 0, $header = 0, $nobaody = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $klsf[] = 'Accept:*/*';
        $klsf[] = 'Accept-Language:zh-cn';
        //$klsf[] = 'Content-Type:application/json';
        $klsf[] = 'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_2_1 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C153 MicroMessenger/6.6.1 NetType/WIFI Language/zh_CN';
        $klsf[] = 'Referer:https://servicewechat.com/wx7c8d593b2c3a7703/5/page-frame.html';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $klsf);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

   
}
