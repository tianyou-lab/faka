<?php
namespace app\api\controller;
use think\Config;
use think\Loader;
use think\Db;

class Apiemail extends Base
{
	
 	/*
 	 * 发货通知邮件
 	 */
 	public function ApiSendFaHuo(){
 		$param=inputself();
		$email = $param['email'];     //手机号
        $title=$param['title'];     //标题
        $content=$param['content'];     //正文
        $msgStatus = SendMail($email,$title,$content,"");
        return json($msgStatus);         	
 	}
}
