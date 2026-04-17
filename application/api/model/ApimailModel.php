<?php
namespace app\api\model;
use think\Model;
use think\Db;

class ApimailModel extends Model
{

   	protected $name = 'mail';  
    protected $autoWriteTimestamp = true;   // 开启自动写入时间戳
  
    /**
     * [getAllArticle 根据ID查询卡密]
     * @author [来利云商业源码&智能建站平台] [366802485@qq.com]
     */
    public function getmailBywhere($map, $Nowpage, $limits)
    {
  		return $this->field("think_mail.*,IFNULL(think_fl.mname,'未知') as name,think_addmaillog.userip,think_addmaillog.addqudao")
  					->join('think_fl','think_fl.id = think_mail.mpid','LEFT')
  					->join('think_addmaillog','think_addmaillog.id = think_mail.addid','LEFT')
                   	->where($map)->page($Nowpage, $limits)
                   	->order('think_mail.id desc')
                   	->select();          
    }
           
    
    
    public function getAllCount($map)
    {
       return $this->where($map)->count();    
    }
  
  
      public function delKami($id)
    {
        try{
            $this->where('id',$id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除成功'];
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    
    
}