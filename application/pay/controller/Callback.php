<?php
namespace app\pay\controller;

use app\jingdian\model\OrderModel;
use app\jingdian\model\UserModel;
use think\Config;
use think\Loader;
use org\Lanpay;
use org\WxpayService;
use think\Db;
require ROOT_PATH.'/vendor/riverslei/payment/autoload.php';
use Payment\Common\PayException;
use Payment\Notify\PayNotifyInterface;
use Payment\Client\Notify;
use Payment\Configv;	

/**
 * 支付宝
 */
class AlipayNotify implements PayNotifyInterface
{
    public function notifyProcess(array $data)
    {
        $channel = $data['channel'];
        if ($channel === Configv::ALI_CHARGE) {// 支付宝支付
        } elseif ($channel === Configv::WX_CHARGE) {// 微信支付
        } elseif ($channel === Configv::CMB_CHARGE) {// 招商支付
        } elseif ($channel === Configv::CMB_BIND) {// 招商签约
        } else {
            // 其它类型的通知
        }
        // 执行业务逻辑，成功后返回true
        return true;
    }
}

/*
* 支付返回方法
*/
class Callback extends Base
{
	//支付宝返回通知
      public function callbackalipay()
    {
        $param = inputself();
		$config = [
            'use_sandbox' => false,
            'app_id'                    => config('app_id'),
            'sign_type'                 => 'RSA2',
            'ali_public_key'            => config('ali_public_key'),
            'rsa_private_key'           => config('rsa_private_key'),
            'return_raw' => true
		];
		$callback = new AlipayNotify();
		try {
			$ret = Notify::run('ali_charge', $config, $callback);// 处理回调，内部进行了签名检查
		} catch (PayException $e) {
			echo $e->errorMessage();
			exit;
		}
      if($param['trade_status']=="TRADE_SUCCESS"&&$param['app_id']==config('app_id')){
        	//业务处理										
			$OrderM = new OrderModel();	
			$UserM = new UserModel();	
			$Orderresult=$OrderM->getOrder($param['out_trade_no']);
            $mapx['orderno|outorderno'] = $param['out_trade_no'];
            $userresult=Db::name('member_payorder')->where($mapx)->find();          
          if(!empty($userresult['memberid'])){
			  $data = [
                	 'memberid' => $userresult['memberid'],
                      'money' => $param['total_amount'],
                      'r2' => $param['out_trade_no'],
                      'r6' => $param['out_trade_no'],
                      'userip' => $userresult['ip'],
                  ];
				$order = $UserM->payMember($param['out_trade_no'], $data);			  
          }else{
                  $data = [
                      'mstatus' => 0,
                      'update_time' => time(),
                      'mcard' => $param['out_trade_no'],
                      'morder' => $param['trade_no'],
                      'maddtype' => "2",
                      'mamount' => (float)$param['total_amount'],
                  ];
                  $order = $OrderM->updateOrderStatus($param['out_trade_no'], $data);
		  }
     	 //业务处理	
      }
			exit($ret); //状态
		

    }
	
	//微信返回通知
    public function callbackweixin()
    {	
		$mchid = config('mch_id');    	
		$appid = config('wx_appid');
		$apiKey = config('wx_apiKey');   
		$wxPay = new WxpayService($mchid,$appid,$apiKey);
		$result = $wxPay->notify();
		if($result){
			//业务处理										
			$OrderM = new OrderModel();	
			$UserM = new UserModel();	
			$Orderresult=$OrderM->getOrder($result['out_trade_no']);
            $mapx['orderno|outorderno'] = $result['out_trade_no'];
            $userresult=Db::name('member_payorder')->where($mapx)->find();	
          if(!empty($userresult['memberid'])){
			  $data = [
                	 'memberid' => $userresult['memberid'],
                      'money' =>  $result['cash_fee'] / 100,
                      'r2' => $result['out_trade_no'],
                      'r6' => $result['out_trade_no'],
                      'userip' => $userresult['ip'],
                  ];
			  		$order = $UserM->payMember($result['out_trade_no'], $data);			  
          }else{
                  $data = [
                      'mstatus' => 0,
                      'update_time' => time(),
                      'mcard' => $result['out_trade_no'],
                      'morder' => $result['transaction_id'],
                      'mamount' =>  $result['cash_fee'] / 100,
                  ];
                  $order = $OrderM->updateOrderStatus($result['out_trade_no'], $data);
		  }
		  //业务处理
		}else{
			echo 'fail';
		}

    }
	
	//蓝支付官方异步返回通知
    public function notifyyzf()
    {
		$param = inputself();
		$pay = new Lanpay(config('pay_yzfid'), config('pay_yzfkey'));
		if ($pay->verify($param)) {
			if ($param['trade_status'] == 'TRADE_SUCCESS') {
						//业务处理										
						$OrderM = new OrderModel();
						$UserM = new UserModel();						
						$Orderresult=$OrderM->getOrder($param['out_trade_no']);
						$mapx['orderno|outorderno'] = $param['out_trade_no'];
						$userresult=Db::name('member_payorder')->where($mapx)->find();							
						if(!empty($userresult['memberid'])){
						  $data = [
						    	 'memberid' => $userresult['memberid'],
						          'money' => $param['money'],
						          'r2' => $param['out_trade_no'],
						          'r6' => $param['out_trade_no'],
						          'userip' => $userresult['ip'],
						      ];
						      $order = $UserM->payMember($param['out_trade_no'], $data);
						}else{
						  $data = [
						        'mstatus' => 0,
						        'update_time' => time(),
						        'mcard' => $param['out_trade_no'],
						        'morder' => $param['trade_no'],
						        'mamount' => (float)$param['money'],
						    ];
						        $order = $OrderM->updateOrderStatus($param['out_trade_no'], $data);
						}	
				exit('success');
			} else {
				exit('fail');
			}
		} else {
			exit('fail');
		}

    }
    
    
	//码支付返回通知
	public function callbackmzf()
    {
        $param = inputself();
		$sign = md5(config('pay_mzfid') . $param['payId'] . urldecode($param['param']) . $param['type'] . $param['price'] . $param['reallyPrice'] . config('pay_mzfkey'));
		if (!$param['payId'] || $sign != $param['sign']) { //不合法的数据
			exit('fail');  //返回失败 继续补单
		} else { //合法的数据
			//业务处理										
			$OrderM = new OrderModel();
			$UserM = new UserModel();
			$Orderresult=$OrderM->getOrder($param['payId']);
			$mapx['orderno|outorderno'] = $param['payId'];
            $userresult=Db::name('member_payorder')->where($mapx)->find();	
          if(!empty($userresult['memberid'])){
			  $data = [
                	 'memberid' => $userresult['memberid'],
                      'money' => $param['reallyPrice'],
                      'r2' => $param['payId'],
                      'r6' => $param['payId'],
                      'userip' => $userresult['ip'],
                  ];
                  $order = $UserM->payMember($param['payId'], $data);
							//if (isMobilePc()) {
                            //      return $this->redirect(url('@mobile/user/uscenter'));
                            //  } else {
                             //     return $this->redirect(url('@jingdian/user/uscenter'));
                             // }
          }else{
              $data = [
                    'mstatus' => 0,
                    'update_time' => time(),
                    'mcard' => $param['payId'],
                    'morder' => $param['order_id'],
                    'mamount' => (float)$param['price'],
                ];
                    $order = $OrderM->updateOrderStatus($param['payId'], $data);
                    //if (isMobilePc()) {
                    //  return $this->redirect(url('@mobile/Getmail/index', ['mpid' => $Orderresult['data']['mflid'], 'number' => $param['payId']]));
                    //} else {
                    //  return $this->redirect(url('@jingdian/Getmail/index', ['mpid' => $Orderresult['data']['mflid'], 'number' => $param['payId']]));
                    //}
          }		
			exit('success'); //返回成功 不要删除哦
		}

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
        reset($dataArr);
         $str = "";
        foreach ($dataArr as $key => $val) {
            $str = $str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($str . "key=" . $signkey));
	}

    
}
