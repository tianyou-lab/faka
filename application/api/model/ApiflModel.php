<?php
namespace app\api\model;
use think\Model;
use think\Db;

class ApiflModel extends Model
{

   	protected $name = 'fl';  
    protected $autoWriteTimestamp = true;   // 开启自动写入时间戳
    /**
     * 获取商品所有信息
     * @author [来利云商业源码&智能建站平台] [366802485@qq.com]
     */
     public function getAllFl()
    {
  		return $this->select();        
    }
    
    /**
     * [getAllArticle 根据订单号或联系方式查询全部]
     * @author [来利云商业源码&智能建站平台] [366802485@qq.com]
     */
     public function getCookieByWhere($map)
    {

        return $this->where($map)->where(['mstatus'=>0])->count();
           
    }
    
    public function getAllCount($mstatus,$type)
    {
        if($type==0){
        	if($mstatus==99){
	        	return $this->table("think_info")->count();	
	        }else{
	        	return $this->table("think_info")->where(['mstatus'=>$mstatus])->count();
	        }
        }else if($type==1){
        	$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('mstatus','not in','2,4,5')	
        							  ->where('think_fl.type','1')
        								->count();
        								
        	//echo $this->getLastSql();
        	return $result;
        	//return $this->table("think_info")->where('mstatus','not in','2,4,5')->count();
        }else{
        	$result=$this->table("think_info")
        							  ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        							  ->where('(think_info.mstatus=0 and think_fl.type=0) or (think_info.mstatus=1 and think_info.mflid=0) or (think_info.mstatus=0 and think_info.mflid=0)')	
        							  
        								->count();
        								
        	//echo $this->getLastSql();
        	return $result;
        }
        
        
    }

    
    
}