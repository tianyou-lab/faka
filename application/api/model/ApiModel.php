<?php
namespace app\api\model;
use think\Model;
use think\Db;
use app\jingdian\model\CommonModel;

class ApiModel extends Model
{

   	protected $name = 'info'; 
    /**
     * [getAllArticle 根据订单号或联系方式分页查询]
     * @author [来利云商业源码&智能建站平台] [366802485@qq.com]
     */
     public function getOrderByWhere($map, $Nowpage, $limits)
    {
  		return $this->field("think_info.*,IFNULL(think_fl.mname,'未知') as name,IFNULL(think_fl.imgurl,'') as imgurl,IFNULL(think_fl.type,'')as type,IFNULL(a.account,'') as account,IFNULL(think_member_group.group_name,'注册会员') as groupname,IFNULL(b.fzhost,'主站') as fzhost")
  					->join('think_fl','think_fl.id = think_info.mflid','LEFT')
  					->join('think_member a','think_info.memberid = a.id','LEFT')
  					->join('think_member b','think_info.childid = b.id','LEFT')
  					->join('think_member_group','a.group_id = think_member_group.id','LEFT')
                   	->where($map)->page($Nowpage, $limits)
                   	->order('think_info.update_time desc')
                   	->select();
          
    }
    
    /**
     * [getAllArticle 根据订单号或联系方式查询全部]
     * @author [来利云商业源码&智能建站平台] [366802485@qq.com]
     */
     public function getCookieByWhere($map)
    {

        return $this->where($map)->where(['mstatus'=>0])->count();
           
    }
    
    public function getAllCount1($mstatus,$type,$mpid,$starttime,$endtime,$contact,$maddtype)
    {
        if($mpid==0 || $mpid==-1){
        	$selectShop=false;
        }else{
        	$selectShop=true;
        }
        
        if($starttime==0 && $endtime==0){
      	  $endtime=time();
        }
        
        
        
        if($type==0){
        	if($mstatus==99){
        		if($maddtype==99){
        			if($selectShop){
		        		if($contact==1){
		        			return $this->table("think_info")->where(['mflid'=>$mpid])->where('lianxi','neq','')->where('email','neq','')->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}else{
		        			return $this->table("think_info")->where(['mflid'=>$mpid])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}else{
		        		if($contact==1){
		        			return $this->table("think_info")->where('lianxi','neq','')->where('email','neq','')->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();	
		        		}else{
		        			return $this->table("think_info")->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}
        		}else{
        			if($selectShop){
		        		if($contact==1){
		        			return $this->table("think_info")->where(['mflid'=>$mpid])->where(['maddtype'=>$maddtype])->where('lianxi','neq','')->where('email','neq','')->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}else{
		        			return $this->table("think_info")->where(['mflid'=>$mpid])->where(['maddtype'=>$maddtype])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}else{
		        		if($contact==1){
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where('lianxi','neq','')->where('email','neq','')->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();	
		        		}else{
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}
        		}
	        	
	        		
	        }else{
	        	if($maddtype==99){
	        		if($selectShop){
		        		if($contact==1){
		        			return $this->table("think_info")->where('lianxi','neq','')->where('email','neq','')->where(['mstatus'=>$mstatus,'mflid'=>$mpid])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();	
		        		}else{
		        			return $this->table("think_info")->where(['mstatus'=>$mstatus,'mflid'=>$mpid])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}else{
		        		if($contact==1){
		        			return $this->table("think_info")->where('lianxi','neq','')->where('email','neq','')->where(['mstatus'=>$mstatus])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}else{
		        			return $this->table("think_info")->where(['mstatus'=>$mstatus])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}
	        	}else{
	        		if($selectShop){
		        		if($contact==1){
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where('lianxi','neq','')->where('email','neq','')->where(['mstatus'=>$mstatus,'mflid'=>$mpid])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();	
		        		}else{
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where(['mstatus'=>$mstatus,'mflid'=>$mpid])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}else{
		        		if($contact==1){
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where('lianxi','neq','')->where('email','neq','')->where(['mstatus'=>$mstatus])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}else{
		        			return $this->table("think_info")->where(['maddtype'=>$maddtype])->where(['mstatus'=>$mstatus])->where('create_time',['>=',$starttime],['<=',$endtime],'and')->count();
		        		}
		        		
		        	}
	        	}
	        	
	        	
	        }
        }else if($type==1){
        	if($selectShop){
	        		$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('mstatus','not in','2,4,5')	
        							  ->where('think_fl.type','1')
        							  
        								->count();
	        	}else{
	        		$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('mstatus','not in','2,4,5')	
        							  ->where('think_fl.type','1')
        								->count();
	        	}
        	
        	
        								
        	//echo $this->getLastSql();
        	return $result;
        	
        }else{
        	if($selectShop){
	        		$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('(think_info.mstatus=0 and think_fl.type=0) or (think_info.mstatus=1 and think_info.mflid=0) or (think_info.mstatus=0 and think_info.mflid=0)')
        							   
        								->count();
	        	}else{
	        		$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('(think_info.mstatus=0 and think_fl.type=0) or (think_info.mstatus=1 and think_info.mflid=0) or (think_info.mstatus=0 and think_info.mflid=0)')	
        							  
        								->count();
	        	}
        	
        	
        								
        	
        	return $result;
        }
        
        
    }
    
    public function getAllCount($map)
    {
        return $this->join('think_fl','think_fl.id = think_info.mflid','LEFT')->join('think_member','think_info.memberid = think_member.id','LEFT')->where($map)->count();
    }

    /**
     * 获取订单号详细信息
     */
    public function getOrderByOrder($order)
    {
    	if(SuperIsEmpty($order)){
        return TyReturn('订单号输入不正确001',-1); 
    	}
    	
    	$map = [];
    	$msg='';
    	$code=1;
    	
    	
        if($order&&$order!=="")
        {
        	$map['mcard|morder'] = $order;	
        }else{
        	return TyReturn('订单号输入不正确',-1); 
        }	
		$card=db('info')->where($map)->find();	
        if (empty($card)) {
        	$code=-1;
            $msg = '卡号不存在!（转账之后稍等30秒再提取）' . '<br>' . '紧急联系电话：' . config('WEB_MOBILE') . '<br>' . '联系QQ：' . config('WEB_QQ');
        }
        return TyReturn($msg,$code,$card);         
    }
    
    
   /**
     * 编辑订单信息
     */
    public function editOrder($param)
    {
        try{
            $CommonM=new CommonModel();
            $infoData=Db::name('info')->where('id',$param['id'])->find();
            
            if(!$infoData){
            	return ['code' => 0, 'data' => '', 'msg' => '获取订单失败'];
            }
            if($infoData['mstatus']==5){
            	return ['code' => 0, 'data' => '', 'msg' => '已发货商品无法再次编辑'];
            }
            if($param['mstatus']==5){
            	 //分销佣金begin
              if(config('fx_cengji')>0){
                $map = [];
                $map['mcard|morder'] = $infoData['mcard'];
                $CommonM->Fx_money($map,$infoData['buynum']);        
              }   
              //分销佣金end
        
              //分站佣金begin
              $childid=$infoData['childid'];
              if($childid>0){
                $childflData=Db::name('child_fl')->where('memberid',$infoData['childid'])->where('goodid',$infoData['mflid'])->find();
                if($childflData['mname']==-1){
                  $flData=Db::name('fl')->where('id',$infoData['mflid'])->find();
                  $mnamebie=$flData['mnamebie'];
                }else{
                  $mnamebie=$childflData['mname'];
                }
                $CommonM->childFxmoney($infoData['childid'],$infoData['mflid'],$infoData['buynum'],$infoData['mamount'],$infoData['mcard'],$mnamebie,$infoData['memberid']);
        	
              }	
              //分站佣金end
            }
			
            $result=db("info")->strict(false)->where('id',$param['id'])->update($param);
            if(false === $result){
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    
    
}