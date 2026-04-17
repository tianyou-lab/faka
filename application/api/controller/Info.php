<?php
namespace app\api\controller;
use think\Config;
use think\Db;

class Info extends Base
{
	public function addinfo(){
		$param=inputself();
		if($param['mamount']<=0){
			return json(['code' => -1, 'url' => '', 'msg' => '金额不能小于0']);
		}
		if(empty($param['mcard'])){
			return json(['code' => -1, 'url' => '', 'msg' => '订单号不能为空']);
		}
		$result=Db::name('info')->where('mcard|morder',$param['mcard'])->find();
		if($result){
      return json(['code' => -1, 'url' => '', 'msg' => '订单号已存在']);
		}
		$data=['mcard'=>$param['mcard'],
				'morder'=>$param['mcard'],
				'mamount'=>$param['mamount'],
				'maddtype'=>1,
				'create_time'=>time(),
				'userip'=>getIP(),
				'mstatus'=>'0',			
				];
		$info = Db::name('info')->insert($data);
        if(empty($info)){
            return json(['code' => -1, 'url' => '', 'msg' => '添加失败']);
        }else{
        	return json(['code' => 1, 'url' => '', 'msg' => '添加成功']);
        }            
	}
	
	public function setyiyong(){
		$param=inputself();
		$mcard=$param['mcard'];
		$map['mcard|morder']=$mcard;
		$data=['mflid'=>99999,
				'mstatus'=>1,
				'lianxi'=>'自用',
				'update_time'=>time(),
				'userip'=>getIP(),
				'userip'=>getIP(),
				'mstatus'=>'0',			
				];
		$result=Db::name('info')->where($map)->update($data);
		if(empty($result)){
            return json(['code' => -1, 'url' => '', 'msg' => '更新失败']);
        }else{
        	return json(['code' => 1, 'url' => '', 'msg' => '更新成功']);
        } 					
	}
	
	public function delinfo(){
		$param=inputself();
 		$weifukuan=isset($param['weifukuan'])?$param['weifukuan']:0;
 		$all=isset($param['all'])?$param['all']:0;
 		$usekami=isset($param['usekami'])?$param['usekami']:0;
 		$endtime=$param['endtime'];	
 		$map=[];
 		if($weifukuan==1){
 			//未付款
 			$map['mstatus']=2;
 			$result=Db::name('info')->where('create_time','elt',$endtime)->where($map)->limit(1000)->delete();	
 		}
 		if($all==1){
 			//所有订单
 			$map=[];
 			$result=Db::name('info')->where('create_time','elt',$endtime)->where($map)->limit(1000)->delete();	
 		}
 		if($usekami==1){
 			//已用卡密
 			$result=Db::name('mail')->where('create_time','elt',$endtime)->where('mis_use=1')->limit(1000)->delete();
 			
 		}

 		if($result===false){
            return json(['code' => -1, 'count' => '', 'msg' => '清理失败']);
        }else{
        	return json(['code' => 1, 'count' => $result, 'msg' => '清理成功'.$result.'条数据']);
        }
	}
	
	public function delkamibyid(){
		$param=inputself();
		$mpid=$param['mpid'];
		$result=Db::name('mail')->where(['mis_use'=>1,'mpid'=>$mpid])->limit(1000)->delete();
 		if($result===false){
            return json(['code' => -1, 'count' => '', 'msg' => '清理失败']);
        }else{
        	return json(['code' => 1, 'count' => $result, 'msg' => '清理成功'.$result.'条数据']);
        }
	}
	
}