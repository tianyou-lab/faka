<?php
namespace app\admin\model;
use think\Model;
use think\Db;
use app\jingdian\model\CommonModel;

class OrderModel extends Model
{
    protected $name = 'info';  
    protected $autoWriteTimestamp = true;   // 开启自动写入时间戳

    /**
     * 根据搜索条件获取用户列表信息 - 优化版本
     */
    public function getOrderByWhere($map, $Nowpage, $limits)
    {
        // 使用查询缓存键
        $cacheKey = 'order_query_' . md5(serialize($map)) . '_' . $Nowpage . '_' . $limits;
        $result = cache($cacheKey);
        
        if (!$result) {
            // 优化：只选择必要的字段，减少数据传输
            $result=$this->field('
                think_info.id, think_info.morder, think_info.mflid, think_info.mcard, 
                think_info.lianxi, think_info.email, think_info.mamount, think_info.mstatus, 
                think_info.create_time, think_info.update_time, think_info.memberid, 
                think_info.childid, think_info.maddtype,
                think_fl.mnamebie, think_fl.mprice, think_fl.imgurl, think_fl.yunimgurl, think_fl.type,
                a.account, b.fzhost
            ')
            ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
            ->join('think_member a','a.id = think_info.memberid','LEFT')
            ->join('think_member b','b.id = think_info.childid','LEFT')
            ->where($map)->where('maddtype<>88')
            ->page($Nowpage, $limits)
            ->order('think_info.id desc')
            ->select();
            
            foreach ($result as &$v) {
                $v=replaceImgurl($v);
            }
            
            // 缓存查询结果2分钟
            cache($cacheKey, $result, 120);
        }
        
        return $result; 
    }

    /**
     * 根据搜索条件获取用户列表信息
     */
    public function getallOrderByWhere($map)
    {
        
        $result=$this->field('think_info.*,think_fl.mnamebie as mnamebie,think_fl.mprice as mprice,think_fl.imgurl as imgurl,think_fl.yunimgurl as yunimgurl,think_fl.type as type,a.account as account,b.fzhost as fzhost')
        			 ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        			 ->join('think_member a','a.id = think_info.memberid','LEFT')
        			 ->join('think_member b','b.id = think_info.childid','LEFT')
            		 ->where($map)->where('maddtype<>88')->order('think_info.id desc')->select();
		 $data=collection($result)->toArray();
		 $dingdandata=array();
		 $type['0']="自动发货";
		 $type['1']="手动发货";
		 $mstatus['0']="已付款";
		 $mstatus['1']="已下单或提取";
		 $mstatus['2']="未付款";
		 $mstatus['3']="进行中";
		 $mstatus['4']="已撤回";
		 $mstatus['5']="已完成";
        foreach ($data as $key=>$val) {
			 foreach ($val as $k=>$v) {
					if($k=="mnamebie"){
						$data_arr['amnamebie']=$v;
					}
					if($k=="mstatus"){
						$data_arr['bmstatus']=$mstatus[$v];
					}
					if($k=="type"){
						$data_arr['ctype']=$type[$v];
					}
					if($k=="lianxi"){
						$data_arr['dlianxi']="\t".$v;
					}
					if($k=="email"){
						$data_arr['email']=$v;
					}
					if($k=="mcard"){
						$orderattach=self::getAttach($v);						 
						$data_arr['forderattach']=$orderattach;			 
						$data_arr['a1']="\t".$v;
					}
					if($k=="create_time"){
						$data_arr['gcreate_time']="\t".$v;
					}
			}
					
					ksort($data_arr, SORT_FLAG_CASE);
					$dingdandata[] = $data_arr;
        }
        return $dingdandata; 
            
    }
	
	/**
     * 导出用的附加选项信息
     */
    public function getAttach($order)
    { 
      	
    	$map['mcard|morder'] = $order;	
		$card=db('info')->where($map)->find();
		
    	$sql="SELECT think_orderattach.text,think_attach.title as t1 FROM think_orderattach LEFT JOIN think_attach on think_orderattach.attachid=think_attach.id where think_orderattach.orderno=:orderno or think_orderattach.orderno=:orderno2";
    	$orderattach=Db::query($sql,['orderno'=>$card['mcard'],'orderno2'=>$card['morder']]);
		
		 $attach = ''; // 初始化变量
		 foreach ($orderattach as $ak=>$av) {
								ksort($av, SORT_FLAG_CASE);
							 foreach ($av as $key=>$vv){
								 if($key=="t1"){
									 $attach.=$av['t1'].':';
								 }else{									
									 $attach.=$av['text'].'  '; 
								 }
									
								
							}
						 }
      	return $attach;
		
	}
	/**
     * 获取指定订单附件选项信息
     */
    public function getOrderAttach($order)
    { 
      	
    	$map['mcard|morder'] = $order;	
		$card=db('info')->where($map)->find();
		
    	$sql="SELECT think_orderattach.text,think_attach.title FROM think_orderattach LEFT JOIN think_attach on think_orderattach.attachid=think_attach.id where think_orderattach.orderno=:orderno or think_orderattach.orderno=:orderno2";
    	$orderattach=Db::query($sql,['orderno'=>$card['mcard'],'orderno2'=>$card['morder']]);
    	
    	
    	//$orderattach=db('orderattach')->where('orderno',trim($order))->order('attachid desc')->select();
      	return $orderattach;
	}
  
  
    /**
     * 根据搜索条件获取所有的数量
     * @param $where
     */
    public function getAllCount($map)
    {
        if(!isset($map['think_fl.type'])){
            unset($map['think_fl.type']);
        }
        return $this->field('think_info.*,think_fl.mnamebie as mnamebie,think_fl.mprice as mprice,think_fl.imgurl as imgurl,think_fl.yunimgurl as yunimgurl,think_fl.type as type,a.account as account,b.fzhost as fzhost')
        			 ->join('think_fl','think_fl.id = think_info.mflid','LEFT')
        			 ->join('think_member a','a.id = think_info.memberid','LEFT')
        			 ->join('think_member b','b.id = think_info.childid','LEFT')
            		 ->where($map)->where('maddtype<>88')->count();
    }
  
  
 /**
     * 根据条件获取全部数据
     */
    public function getAll($map, $Nowpage, $limits)
    {
        return $this->where($map)->page($Nowpage,$limits)->order('id asc')->select();     
    }

    
   /**
     * 编辑订单信息
     */
    public function editOrder($param)
    {
        try{
            $CommonM=new CommonModel();
            $infoData=Db::name('info')->where('id',$param['id'])->find();
            $flData=Db::name('fl')->where('id',$infoData['mflid'])->find();
          
          	if($infoData['memberid']!=0){//判断是否会员登录
        		$memberData=Db::name('member')->where('id',$infoData['memberid'])->find();
        		$email=$memberData['email'];
        	}else{
        		$email=$infoData['email'];
        	}
          
            if(!$infoData){
            	return ['code' => 0, 'data' => '', 'msg' => '获取订单失败'];
            }
            if($infoData['mstatus']==5){
            	return ['code' => 0, 'data' => '', 'msg' => '已发货商品无法再次编辑'];
            }
            if($infoData['mstatus']==1 && $flData['type']==0){
            	return ['code' => 0, 'data' => '', 'msg' => '已提取商品无法再次编辑'];
            }
          
            if($param['mstatus']!='4' && $flData['type']=='0'){
            	return ['code' => 0, 'data' => '', 'msg' => '自动商品无法更改此状态'];
            }
            if(!empty($email))
            {
                $mail_host=config('mail_host');
                $mail_port=config('mail_port');
                $mail_username=config('mail_username');
                $mail_password=config('mail_password');
                if(!empty($mail_host) and !empty($mail_port) and !empty($mail_username) and !empty($mail_password))
                { 
                  if($param['mstatus']==5){ 
                    SendMail($email,'订单编号:'.$infoData['mcard'],'尊敬的用户，您的订单号是'.$infoData['mcard']."\r\n已发货\r\n回执信息：".$param['statustext'],""); 
                  }
                  if($param['mstatus']==4){ 
                    SendMail($email,'订单编号:'.$infoData['mcard'],'尊敬的用户，您的订单号'.$infoData['mcard']."\r\n已撤销\r\n回执信息：".$param['statustext'],""); 
                  }
                  if($param['mstatus']==3){ 
                    SendMail($email,'订单编号:'.$infoData['mcard'],'尊敬的用户，您的订单号是'.$infoData['mcard']."\r\n处理中\r\n回执信息：".$param['statustext'],""); 
                  }
                
                }
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
        }catch( \PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
  
    /**
     * 获取指定订单附件选项信息
     */ 
      public function del_order($id)
    {
        try{
            $this->where('id',$id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除成功'];
        }catch( \PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }


}