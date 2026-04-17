<?php
namespace app\api\controller;
use app\api\model\ApiaddmaillogModel;
use app\jingdian\model\OrderModel;
use app\jingdian\model\GoodsListModel;
use think\Config;
use think\Loader;
use think\Db;

class Apiaddmaillog extends Base
{
 	/**
 	 *获取库存日志
 	 */
 	public function getAddmaillog(){
 		$param=inputself();
 		$count=0;
        $Nowpage=1;
        $allpage=0;
        $code=-1;
        $msg="获取失败";
 		$page=isset($param['page'])?$param['page']:1;
 		if($page<1){
 			$page=1;
 		}
 		$limit=($page-1)*$param['limit'];//分页开始数量
 		$limitlast=$param['limit'];
 		
 		$ApiaddmaillogM = new ApiaddmaillogModel(); 
 		$count=$ApiaddmaillogM->getAllCount();
 		$allpage = intval(ceil($count/$limitlast));
 		$Nowpage = input('get.page') ? input('get.page'):1;	
 		$sql="SELECT think_addmaillog.*,IFNULL(think_fl.mname,'[未知]') as mname FROM `think_addmaillog` LEFT JOIN think_fl on think_addmaillog.goodid=think_fl.id ORDER BY think_addmaillog.id DESC limit :limit,:limitsize";
	 	$result=Db::query($sql,['limit'=>$limit,'limitsize'=>$limitlast]);
 		
 		if($result){
 			$code=1;
 			$msg="获取成功";
 		}
 		$returnData=[
			'count'=>$count,
			'code'=>$code,
			'msg'=>$msg,
			'allpage' =>$allpage,
			'Nowpage'=>$Nowpage,
			'data'=>$result		
 		];
 		return json($returnData);
 	}
 	
 	
 	/*
 	 * 获取指定addid 卡密数据
 	 */
 	public function getMailByAddid(){
 		$param=inputself();
 		$code=1;
        $msg="获取失败";
 		$sql="SELECT musernm from think_mail where addid=:addid";
 		$result=Db::query($sql,['addid'=>$param['addid']]);
 		if($result){
 			$code=1;
        	$msg="获取失败";
 		}
 		$returnData=[
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$result		
 		];
 		return json($returnData);
 	}
 	
 	/*
 	 * 删除指定addid 卡密
 	 */
 	public function delMailByAddid(){
 		$param=inputself();
 		$code=-1;
        $msg="失败";
 		//开启事务
    	Db::startTrans();
 		$sql="delete from think_mail where addid=:addid";
 		$result=Db::execute($sql,['addid'=>$param['addid']]);
 	
 		if($result===false){
 			// 回滚事务
    		Db::rollback();
    		$msg='删除卡密失败';
    		$code=-1;
    		$returnData=[
				'code'=>$code,
				'msg'=>$msg,	
	 		];
	 		return json($returnData);		
 		}
 		$sql="delete from think_addmaillog where id=:addid";
 		$result=Db::execute($sql,['addid'=>$param['addid']]);
 		if($result===false){
 			// 回滚事务
    		Db::rollback();
    		$msg='删除卡密日志失败';
    		$code=-1;
    		$returnData=[
				'code'=>$code,
				'msg'=>$msg,	
	 		];
	 		return json($returnData);			
 		}
 		Db::commit();
 		$msg='操作成功';
    	$code=1;
    		$returnData=[
				'code'=>$code,
				'msg'=>$msg,	
	 		];
	 	return json($returnData);	
 		
 		
 	}
}
