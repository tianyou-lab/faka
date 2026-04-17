<?php
namespace app\api\controller;
use app\api\model\ApiflModel;
use app\jingdian\model\GoodsListModel;
use think\Config;
use think\Loader;
use think\Db;

class Apifl extends Base
{
	/*
	 * 获取分类信息
	 */
	public function getAllfl(){
		$code=-1;
        $msg="获取失败";
		$fl=new ApiflModel();
		$result=$fl->getAllfl();
		if($result){
 			$code=1;
 			$msg="获取成功";
 			foreach ($result as $key=>$val) {
				$result[$key]['mname']=strip_tags(str_replace('&nbsp;','',htmlspecialchars_decode($val['mname'])));
	        }
	        $GoodListM=new GoodsListModel();
	        $result=$GoodListM->getAllGoods($result);
	        
 		}
		
        
 		return json($result);  
	}
}
