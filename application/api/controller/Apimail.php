<?php
namespace app\api\controller;
use app\api\model\ApimailModel;
use think\Config;
use think\Loader;
use think\Db;

class Apimail extends Base
{
 	/**
 	 *获取指定ID商品卡密信息
 	 */
 	public function getmailByid(){
 		$param=inputself();
        $map = [];
        $code=-1;
        $msg="获取失败";
        $count=0;
        $Nowpage=1;
        $allpage=0;
        $page=isset($param['page'])?$param['page']:1;
        $syddhao=isset($param['syddhao'])?$param['syddhao']:'';
        $addid=isset($param['addid'])?$param['addid']:0;
        $musernm=isset($param['musernm'])?$param['musernm']:'';
        $misuse=isset($param['misuse'])?$param['misuse']:99;
        $mpid=isset($param['mpid'])?$param['mpid']:0;
        $starttime=isset($param['starttime'])?$param['starttime']:0;
 		$endtime=isset($param['endtime'])?$param['endtime']:0;
 		if($starttime==0 && $endtime==0){
      	  $endtime=time();
        }
 		if($page<1){
 			$page=1;
 		}
 		if(!empty($syddhao)){
 			$map['think_mail.syddhao']=$syddhao;
 		}
 		if($addid!=0 && !empty($addid)){
 			$map['think_mail.addid']=$addid;
 		}
 		if($misuse!=99 && !empty($misuse)){
 			$map['think_mail.mis_use']=$misuse;
 		}
 		if($mpid!=0 && !empty($mpid)){
 			$map['think_mail.mpid']=$mpid;
 		}
 		if(!empty($musernm)){
 			$map['think_mail.musernm']=array('like','%'.$musernm.'%');;
 		}
 		$map['think_mail.create_time']=array(array('egt',$starttime),array('elt',$endtime));
 
 		$limit=($page-1)*$param['limit'];//分页开始数量
 		$limitlast=$param['limit'];
 		$ApimailM=new ApimailModel();
 		$count=$ApimailM->getAllCount($map);
 		
 		$allpage = intval(ceil($count/$limitlast));
 		$Nowpage = input('get.page') ? input('get.page'):1;	
 		$result=$ApimailM->getmailBywhere($map,$Nowpage, $limitlast);
 		if($result){
 			$code=1;
 			$msg="获取成功";
 			foreach ($result as $key=>$val) {
           		$result[$key]['name']=strip_tags(str_replace('&nbsp;',' ',htmlspecialchars_decode($val['name'])));
          	}
 			
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
 	 * 加密商品补货
 	 */
	public function AdddecryptPsw(){
		$param=inputself();
		$sql="insert ignore into think_mail (musernm,mpasswd,mpid,create_time) values(:musernm,:mpasswd,:mpid,:create_time)";
		$result=Db::execute($sql,[
						'musernm'=>md5($param['musernm']),
						'mpasswd'=>passport_encrypt($param['musernm'],DECRYPT_KEY),
						'mpid'=>$param['mpid'],
						'create_time'=>time()
						]);
		if($result){
			return json(['code'=>1,'msg'=>"添加成功"]);
		}else{
			return json(['code'=>0,'msg'=>"添加失败货已经存在"]);
		}				
	}
  
  
  
	/*
	 * 后台批量补货
	 */
	public function addadminkami(){
		$param=inputself();
		$goodid=$param['mpid'];			
		$kami=$param['values'];
      	$order=array("\r\n","\n","\r");
		$replace="\r\n";
		$kami=str_replace($order,$replace,$kami); 
		$addqudao=$param['addqudao'];
		$str = $kami;		
		$arr = array();
		for($i=0;$i<strlen($str);$i++){
		    $tempnum=ord($str[$i]);
		    if($tempnum!=0){
		    	$arr[] =$tempnum;	
		    }		    
		}
		$str = ''; 
        foreach($arr as $ch) { 
            $str .= chr($ch); 
        }
        $kami=$str; 
		$kami=addslashes($kami);	
		$FLData=Db::name('fl')->where('id',$goodid)->find();	
		if(empty($kami)){
			return json(['code'=>-1,"msg"=>"卡密不能为空","url"=>url('goods')]);
		}
		if(!$FLData){
			return json(['code'=>-1,"msg"=>"商品异常","url"=>url('goods')]);
		}
		if($FLData['status']==0){
			return json(['code'=>-1,"msg"=>"该商品已下架","url"=>url('goods')]);
		}
		if($FLData['type']!=0){
			return json(['code'=>-1,"msg"=>"商品不是虚拟商品","url"=>url('goods')]);
		}
		
		
			
			//开启事务
    		Db::startTrans();
    		try
    		{
    			
    			$textarray=explode("\r\n",$kami);			
   				$num=count($textarray);//总数量
    			$addbiaoshi = date('YmdHis').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
		        $timestamp=time();
		        $userip=getIP(); 
		        $sql="insert ignore into think_addmaillog (addbiaoshi,addqudao,userip,create_time,goodid,addnum,failnum) values(:addbiaoshi,:addqudao,:userip,:timestamp,:mpid,:addnum,:failnum)";
		        $result=Db::execute($sql,['addbiaoshi'=>$addbiaoshi
		        				  ,'addqudao'=>$addqudao
		        				  ,'userip'=>$userip
		        				  ,'timestamp'=>$timestamp
		        				  ,'mpid'=>$goodid
		        				  ,'addnum'=>$num
		        				  ,'failnum'=>0
		        				  ]);
		        if($result===false)
    			{
    				// 回滚事务
                    Db::rollback();                   
                    $errormsg='标识出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);

    			}   			
				$biaoshiId = Db::name('addmaillog')->getLastInsID();
				
   				$successnum=0;
   				$tempaccounttext='';
   				if($FLData['decrypt']==0){
   					foreach ($textarray as $key=>$val){
	   					$temptext= $val;
	   					if(empty($temptext)){
	   						continue;
	   					}
	   					if(empty($tempaccounttext)){	   						
	   						$tempaccounttext="('".$temptext."','',".$goodid.",".$biaoshiId.",".$timestamp."),";	   						
	   					}else{
	   						$tempaccounttext.="('".$temptext."','',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}
	   					
	   				}
	   				
	   				$re = substr ($tempaccounttext, -1);
			        if($re==','){
			         $len=strlen($tempaccounttext);
			         $tempaccounttext=substr ($tempaccounttext,0,$len-1);
			        }			        
			               
	   				$sql="insert ignore into think_mail (musernm,mpasswd,mpid,addid,create_time) values".$tempaccounttext;
	   				
   				}elseif($FLData['decrypt']==1){
   					foreach ($textarray as $key=>$val){
	   					$temptext= $val;
	   					if(empty($temptext)){
	   						continue;
	   					}
	   					if(empty($tempaccounttext)){
	   						$tempaccounttext="('".md5($temptext)."','".passport_encrypt($temptext,DECRYPT_KEY)."',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}else{
	   						$tempaccounttext.="('".md5($temptext)."','".passport_encrypt($temptext,DECRYPT_KEY)."',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}
	   					
	   				}
	   				$re = substr ($tempaccounttext, -1);
			        if($re==','){
			         $len=strlen($tempaccounttext);
			         $tempaccounttext=substr ($tempaccounttext,0,$len-1);
			        }		        
	   				$sql="insert ignore into think_mail (musernm,mpasswd,mpid,addid,create_time) values".$tempaccounttext;
   				}
   				
				$result=Db::execute($sql);				
				if($result===false){
					// 回滚事务
                    Db::rollback();                   
                    $errormsg='添加卡密出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);
				}
				$cunzainum=$num-$result;
				$sql = "update think_addmaillog set successnum=:successnum,failnum=:failnum,existnum=:existnum where id=:id";
				$resultLog=Db::execute($sql,['successnum'=>$result
		        				  ,'failnum'=>$cunzainum
		        				  ,'existnum'=>$cunzainum
		        				  ,'id'=>$biaoshiId	        				  
		        				  ]);
		        if($resultLog===false)
    			{
    				// 回滚事务
                    Db::rollback();                   
                    $errormsg='更新添加日志出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);

    			}   						  
    		}catch(\Exception $e){
					// 回滚事务
                    Db::rollback();
                    $errormsg=$e->getMessage();
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);
    		}
    		Db::commit();
			if($result>0){
				return json(['code'=>1,"msg"=>'恭喜你，操作成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,'success'=>$result,'addid'=>$biaoshiId,"url"=>url('index/goods')]);
			}else{
				return json(['code'=>1,"msg"=>'补货不成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,'success'=>$result,'addid'=>$biaoshiId,"url"=>url('index/goods')]);
			}	
		
	}
	/*
	 * 批量补货
	 */
	public function addkami(){
		$param=inputself();
		$goodid=$param['mpid'];			
		$kami=$param['values'];
		$kami=str_replace(" ","+",$kami);
		$kami=base64_decode($kami);		
		$addqudao=$param['addqudao'];
		$str = $kami;		
		$arr = array();
		for($i=0;$i<strlen($str);$i++){
		    $tempnum=ord($str[$i]);
		    if($tempnum!=0){
		    	$arr[] =$tempnum;	
		    }		    
		}
		$str = ''; 
        foreach($arr as $ch) { 
            $str .= chr($ch); 
        }
        $kami=$str; 
		$kami=addslashes($kami);	
		$FLData=Db::name('fl')->where('id',$goodid)->find();	
		if(empty($kami)){
			return json(['code'=>-1,"msg"=>"卡密不能为空","url"=>url('goods')]);
		}
		if(!$FLData){
			return json(['code'=>-1,"msg"=>"商品异常","url"=>url('goods')]);
		}
		if($FLData['status']==0){
			return json(['code'=>-1,"msg"=>"该商品已下架","url"=>url('goods')]);
		}
		if($FLData['type']!=0){
			return json(['code'=>-1,"msg"=>"商品不是虚拟商品","url"=>url('goods')]);
		}
		
		
			
			//开启事务
    		Db::startTrans();
    		try
    		{
    			
    			$textarray=explode("\r\n",$kami);			
   				$num=count($textarray);//总数量
    			$addbiaoshi = date('YmdHis').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
		        $timestamp=time();
		        $userip=getIP(); 
		        $sql="insert ignore into think_addmaillog (addbiaoshi,addqudao,userip,create_time,goodid,addnum,failnum) values(:addbiaoshi,:addqudao,:userip,:timestamp,:mpid,:addnum,:failnum)";
		        $result=Db::execute($sql,['addbiaoshi'=>$addbiaoshi
		        				  ,'addqudao'=>$addqudao
		        				  ,'userip'=>$userip
		        				  ,'timestamp'=>$timestamp
		        				  ,'mpid'=>$goodid
		        				  ,'addnum'=>$num
		        				  ,'failnum'=>0
		        				  ]);
		        if($result===false)
    			{
    				// 回滚事务
                    Db::rollback();                   
                    $errormsg='标识出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);

    			}   			
				$biaoshiId = Db::name('addmaillog')->getLastInsID();
				
   				$successnum=0;
   				$tempaccounttext='';
   				if($FLData['decrypt']==0){
   					foreach ($textarray as $key=>$val){
	   					$temptext= $val;
	   					if(empty($temptext)){
	   						continue;
	   					}
	   					if(empty($tempaccounttext)){	   						
	   						$tempaccounttext="('".$temptext."','',".$goodid.",".$biaoshiId.",".$timestamp."),";	   						
	   					}else{
	   						$tempaccounttext.="('".$temptext."','',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}
	   					
	   				}
	   				
	   				$re = substr ($tempaccounttext, -1);
			        if($re==','){
			         $len=strlen($tempaccounttext);
			         $tempaccounttext=substr ($tempaccounttext,0,$len-1);
			        }			        
			               
	   				$sql="insert ignore into think_mail (musernm,mpasswd,mpid,addid,create_time) values".$tempaccounttext;
	   				
   				}elseif($FLData['decrypt']==1){
   					foreach ($textarray as $key=>$val){
	   					$temptext= $val;
	   					if(empty($temptext)){
	   						continue;
	   					}
	   					if(empty($tempaccounttext)){
	   						$tempaccounttext="('".md5($temptext)."','".passport_encrypt($temptext,DECRYPT_KEY)."',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}else{
	   						$tempaccounttext.="('".md5($temptext)."','".passport_encrypt($temptext,DECRYPT_KEY)."',".$goodid.",".$biaoshiId.",".$timestamp."),";
	   					}
	   					
	   				}
	   				$re = substr ($tempaccounttext, -1);
			        if($re==','){
			         $len=strlen($tempaccounttext);
			         $tempaccounttext=substr ($tempaccounttext,0,$len-1);
			        }		        
	   				$sql="insert ignore into think_mail (musernm,mpasswd,mpid,addid,create_time) values".$tempaccounttext;
   				}
   				
				$result=Db::execute($sql);				
				if($result===false){
					// 回滚事务
                    Db::rollback();                   
                    $errormsg='添加卡密出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);
				}
				$cunzainum=$num-$result;
				$sql = "update think_addmaillog set successnum=:successnum,failnum=:failnum,existnum=:existnum where id=:id";
				$resultLog=Db::execute($sql,['successnum'=>$result
		        				  ,'failnum'=>$cunzainum
		        				  ,'existnum'=>$cunzainum
		        				  ,'id'=>$biaoshiId	        				  
		        				  ]);
		        if($resultLog===false)
    			{
    				// 回滚事务
                    Db::rollback();                   
                    $errormsg='更新添加日志出错';
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);

    			}   						  
    		}catch(\Exception $e){
					// 回滚事务
                    Db::rollback();
                    $errormsg=$e->getMessage();
                    $code=-1;
                    return json(['code'=>-1,"msg"=>$errormsg,"url"=>url('goods')]);
    		}
    		Db::commit();
			if($result>0){
				return json(['code'=>1,"msg"=>'恭喜你，操作成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,'success'=>$result,'addid'=>$biaoshiId,"url"=>url('index/goods')]);
			}else{
				return json(['code'=>1,"msg"=>'补货不成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,'success'=>$result,'addid'=>$biaoshiId,"url"=>url('index/goods')]);
			}	
		
	}
	/*
 	 * 判断商品性质
 	 */
	public function Isdecrypt(){
		$param=inputself();
		$result=Db::name('fl')->where('id',$param['mpid'])->find();
		if($result){        
        return json(['code'=>1,'decrypt'=>$result['decrypt'],'msg'=>"成功"]);     
		}else{
        return json(['code'=>-1,'decrypt'=>-1,'msg'=>"不存在商品"]);  
		}
					
	}
	
 	
}
