<?php
namespace app\api\controller;
use think\Config;
use think\Db;

class Pub
{
	public function checkdingdan(){
		$param=inputself();
		$mcard=$param['mcard'];
		$map['mcard|morder']=$mcard;
		$result=Db::name('info')->where($map)->where('mstatus','neq','2')->find();
          if(empty($result)){
                $mapx['orderno|outorderno'] = $mcard;
                $memberpay=Db::name('member_payorder')->where($mapx)->where('status','neq','0')->find();
          }
		  if(!empty($memberpay)){
                	return 3;
                 	exit;
                }
		if(empty($result)){
			return 0;
		}else{
			return 1;
		}				
	}
}