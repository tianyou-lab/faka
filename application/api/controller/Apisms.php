<?php
namespace app\api\controller;
use think\Config;
use think\Loader;
use think\Db;

class Apisms extends Base
{
	
 	/*
 	 * 发货通知短信
 	 */
 	public function ApiSendFaHuo(){
 		$param=inputself();
		$mobile = $param['mobile'];     //手机号
        $tplCode = config('alimoban_fahuo');   //模板ID
        $shopname=$param['shopname'];
        $status=$param['status'];
        $data['shopname']=substr($shopname,0,20);
        $data['status']=$status;
        $msgStatus = sendMsg($mobile,$tplCode,$data);
        return json($msgStatus);         	
 	}
}
