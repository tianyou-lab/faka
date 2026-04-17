<?php
namespace app\api\controller;
use think\Request;
use app\api\model\ApiModel;
use app\jingdian\model\OrderModel;
use app\jingdian\model\GoodsListModel;
use app\jingdian\model\CommonModel;
use think\Config;
use think\Loader;
use think\Db;

class Api extends Base
{
	
 	/**
 	 *获取订单 
 	 */
 	public function getOrder(){
 		$param=inputself();
 		$count=0;
        $Nowpage=1;
        $allpage=0;
        $code=-1;
        $map = [];
        $msg="获取失败";
 		$page=isset($param['page'])?$param['page']:1;
 		$mstatus=isset($param['mstatus'])?$param['mstatus']:99;
 		$maddtype=isset($param['maddtype'])?$param['maddtype']:99;
 		$type=isset($param['type'])?$param['type']:0;
 		$mpid=isset($param['mpid'])?$param['mpid']:0;
 		$starttime=isset($param['starttime'])?$param['starttime']:0;
 		$endtime=isset($param['endtime'])?$param['endtime']:0;
 		$contact=isset($param['contact'])?$param['contact']:0;
 		$account=isset($param['account'])?$param['account']:'';
 		$childhost=isset($param['childhost'])?$param['childhost']:'';
 		$orderKey=isset($param['orderkey'])?$param['orderkey']:'';
 		if($starttime==0 && $endtime==0){
      	  $endtime=time();
        }
        $map['think_info.create_time']=array('between', array($starttime,$endtime));
 		if($page<1){
 			$page=1;
 		}
 		if(!empty($orderKey)){
 			$map['think_info.mcard|think_info.morder|think_info.lianxi|think_info.email']=$orderKey;
 		}

 		if(!empty($account)){
 			$hasUser=Db::name('member')->where('account',$account)->find();
 			if($hasUser){
 				$map['think_info.memberid']=$hasUser['id'];
 			}
 		}
 		if(!empty($childhost)){
 			$hasUserfzhost=Db::name('member')->where('fzhost',$childhost)->find();
 			if($hasUserfzhost){
 				$map['think_info.childid']=$hasUserfzhost['id'];
 			}
 		}
 		$limit=$param['limit'];
 		//$limit=($page-1)*$param['limit'];//分页开始数量
 		$limitlast=$param['limit'];
 		if($mstatus!=99){
 			$map['think_info.mstatus']=$mstatus;
 		}
 		
 		if($type==1){
 			$map['think_fl.type']="1";
 			$map['think_info.mstatus']=array('not in','2,4,5');
 		}elseif($type==2){
 			$map="(think_info.mstatus=0 and think_fl.type=0) or (think_info.mstatus=1 and think_info.mflid=0) or (think_info.mstatus=0 and think_info.mflid=0)";
 			//$map['think_info.mstatus|think_info.mflid']="0";
 			
 		}
 		
 		if($maddtype!=99){
 			$map['think_info.maddtype']=$maddtype;
 		}
 		if($mpid!=0 && $mpid!=-1){
 			$map['think_info.mflid']=$mpid;
 		}
 		if($contact==1){
 			$map['think_info.lianxi|think_info.email']=['neq',''];
 		}
 		
 		$ApiM = new ApiModel(); 
 		$count=$ApiM->getAllCount($map);
 		
 		$allpage = intval(ceil($count/$limitlast));
 		$Nowpage = input('get.page') ? input('get.page'):1;	
 		$result=$ApiM->getOrderByWhere($map, $Nowpage, $limit);
 	
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
 	
 	
 	
 	/**
 	 *获取订单总数 
 	 */
 	public function getAllOrderCount(){
 		 return $this->where($map)->count();
 	}
 	
 	/*
 	 * 获取指定订单详情
 	 */
 	public function getOrderDetail(){
 		$code=-1;
        $msg="获取失败";
 		$key=input('param.key');
 		$sql="SELECT think_info.*, IFNULL(think_fl.mname,'[未知]') as mname,IFNULL(think_fl.type,0) as type from think_info  LEFT JOIN think_fl on  think_info.mflid=think_fl.id where think_info.mcard=:key1 or think_info.morder=:key2 or think_info.lianxi=:key3 or think_info.email=:key4";
	 	$result=Db::query($sql,['key1'=>$key,'key2'=>$key,'key3'=>$key,'key4'=>$key]);
	 	if($result){
 			$code=1;
 			$msg="获取成功";
 		}
 		$returnData=[
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$result		
 		];
 		return json($returnData);
 	}
 	
 	
 	/*
 	 * 清理订单
 	 */
 	public function deleteOrder(){
 		$param=inputself();
 		$number=isset($param['number'])?$param['number']:200;
 		
 		$code=-1;
        $msg="获取失败";
 		$sql = "DELETE tb FROM think_info AS tb,(SELECT id FROM think_info ORDER BY id desc LIMIT :number,1) AS tmp WHERE tb.id<tmp.id";
 		$result=Db::execute($sql,['number'=>$number]);
 		if($result){
 			$code=1;
 			$msg="删除成功";
 		}
 		$returnData=[
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$result		
 		];
 		return json($returnData);
 	}
 	
 	/*
 	 * 获取订单状态数量
 	 */
 	public function getOrderGroupBymstatus(){
 		$code=-1;
        $msg="获取失败";
 		$sql="SELECT COUNT(id) as count,mstatus FROM think_info GROUP BY mstatus";
 		$result=Db::query($sql);
 		if($result){
 			$code=1;
 			$msg="获取成功";
 		}
 		$returnData=[			
			'code'=>$code,
			'msg'=>$msg,	
			'data'=>$result		
 		];
 		return json($returnData);
 	}
 	
 	
 	/*
 	 * 获取待处理数量
 	 */
 	public function getOrderMstatus0(){
 		$param=inputself();
 		$map=[];
 		$type=isset($param['type'])?$param['type']:0;
 		
 		if($type==1){
 			$map['think_fl.type']='1';
 			$map['think_info.mstatus']=array('not in','2,4,5');
 		}elseif($type==2){
 			
 			$map="(think_info.mstatus=0 and think_fl.type=0) or (think_info.mstatus=1 and think_info.mflid=0) or (think_info.mstatus=0 and think_info.mflid=0)";
 		}
 		
 		$ApiM = new ApiModel(); 
 		$count=$ApiM->getAllCount($map);
 		return $count;
 	}
 	
 	
 	/**
     * 获取订单详情
     */
    public function getOrderDetailByOrder()
 	{
 		$code=-1;
        $msg="获取失败";
 		$number=trim(input('param.number'));
 		if(SuperIsEmpty($number)){
 		$msg='订单号异常';
 		$returnData=[		
			'code'=>$code,
			'msg'=>$msg,
			'data'=>''		
 		];
 		return json($returnData);
 		}
 		//订单详情
 		$ApiM=new ApiModel();
 		$order=$ApiM->getOrderByOrder($number);

 		if($order['code']==-1){
	 		$msg=$order['msg'];
			$returnData=[		
				'code'=>$code,
				'msg'=>$msg,
				'data'=>''		
	 		];
	 		return json($returnData);
 		}
 		$code=1;
 		$msg="获取成功";
 		//商品详情
 		$GoodList=new GoodsListModel();
 		$data['mpid']=$order['data']['mflid'];
 		$param=$GoodList->shopyh($data);

		//订单附加信息详情
		$OrderM=new OrderModel();
		$orderattach=$OrderM->getOrderAttach($number);
 		$data['param']=$param;
 		$data['orderattach']=$orderattach;
 		$data['orderdetail']=$order;
 		$returnData=[		
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$data		
 		];
 		return json($returnData);
	
 	}
 	
 	/**
     * 获取指定商品ID是否下单
     */
    public function getOrderDetailByid()
 	{
 		$code=-1;
        $msg="获取失败";
 		$goodsid=trim(input('param.goodsid'));
 		if(SuperIsEmpty($goodsid)){
 		$msg='商品ID异常';
 		$returnData=[		
			'code'=>$code,
			'msg'=>$msg,
			'data'=>''		
 		];
 		return json($returnData);
 		}
 		$inforesult=Db::name('info')->where(["mflid"=>$goodsid,"mstatus"=>1])->find();
 		if($inforesult==false){
 			$msg='无订单';
	 		$returnData=[		
				'code'=>$code,
				'msg'=>$msg,
				'data'=>''		
	 		];
	 		return json($returnData);
 		}

 		
 		$code=1;
 		$msg="获取成功";
 		
		//订单附加信息详情
		$OrderM=new OrderModel();
		$orderattach=$OrderM->getOrderAttach($inforesult['mcard']);
 		$data['orderattach']=$orderattach;
 		$data['orderdetail']=$inforesult;
 		$returnData=[		
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$data		
 		];
 		return json($returnData);
	
 	}
 	
 	/*
 	 * 修改订单信息
 	 */
 	public function UpdateOrder(){
 		$param=inputself();
 		$ApiM=new ApiModel();
 		$result=$ApiM->editOrder($param);
 		return json($result);		
 	}
 	
 	/*
 	 * 导出邮箱
 	 */
 	public function ExportEmail(){
 			$param=inputself();
 			$data=[];
	 		$param['create_time']=time();
	 		$param['userip']=getIP();
	 		$param['mflid']=isset($param['mflid'])?$param['mflid']:'';
 			$param['buynum']=isset($param['buynum'])?$param['buynum']:'';
 			if($param['mflid']=='' || $param['buynum']==''){
 				$msg='不能为空';
    				$code=-1;
    				$returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
 			}
 			$dataFl=Db::name('fl')->where('id',$param['mflid'])->find();
 			if(!$dataFl){
 					$msg='商品异常';
    				$code=-1;
    				$returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
 			}
 			$decrypt=$dataFl['decrypt'];
 			
 			$code=1;
        	$msg="获取成功";
        	
 			//开启事务
    		Db::startTrans();
    		try
    		{
    			//添加订单信息		
    			$orderid=self::createOrder();
    			$sql="insert ignore into think_info set mcard=:mcard,morder=:morder,mstatus=1,create_time=:create_time,update_time=:update_time,userip=:userip,mflid=:mflid,buynum=:buynum,maddtype=88";
           		$bool = Db::execute($sql,[
 								'mcard'=>$orderid.'_'.$param['buynum'],
 								'morder'=>$orderid.'_'.$param['buynum'],
 								'create_time'=>$param['create_time'],
 								'update_time'=>0,
 								'userip'=>$param['userip'],
 								'mflid'=>$param['mflid'],
 								'buynum'=>$param['buynum']				
 								]);
           
    			if($bool==false)
    			{
    				// 回滚事务
    				Db::rollback();
    				$msg='添加订单状态失败';
    				$code=-1;
    				$returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
		    				     
    			}
				$maildata =Db::query("SELECT * from think_mail where mpid=:mpid and mis_use=0 ORDER BY id asc LIMIT :beishu for update",['mpid'=>$param['mflid'],'beishu'=>$param['buynum']]);
    			if($maildata==false)
    			{
    				// 回滚事务
                    Db::rollback();
                    $msg='提取邮箱出错001';
                    $code=-1;
                    $returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
                    
    			}
    			$file="";
                $html="";
                if($decrypt==0){
                	foreach ($maildata as $v) 
	                {
	                	$mails[] = $v['musernm'];
	                    $ids[] = $v['id'];
	                    $file .= $v['musernm'];
	                    $file .= "\r";
	                    $html .= $v['musernm'];
	                    $html .= '<br />';
	                    $data[]=$v['musernm'];
	                }
                }elseif($decrypt==1){
                	foreach ($maildata as $v) 
	                {
	                	$mails[] =passport_decrypt($v['mpasswd'],DECRYPT_KEY);
	                    $ids[] = $v['id'];
	                    $file .= passport_decrypt($v['mpasswd'],DECRYPT_KEY);
	                    $file .= "\r";
	                    $html .= passport_decrypt($v['mpasswd'],DECRYPT_KEY);
	                    $html .= '<br/>';
	                    $data[]=passport_decrypt($v['mpasswd'],DECRYPT_KEY);
	                }
                }
                
                
                
               
    			$arr_string = join(',', $ids);
                $sql = "update think_mail set mis_use=1,update_time=:update_time,syddhao=:orderid where id in(".$arr_string.")";
         
                $updatemail = Db::execute($sql,['update_time'=>time(),'orderid'=>$orderid]);
    			if($updatemail==false)
    			{
    				// 回滚事务
                    Db::rollback();
                    
                    $msg='更新邮箱状态出错001';
                    $code=-1;
                    $returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
		                  

    			}
    			
    			
    			
    			
    		}catch(\Exception $e){
					// 回滚事务
                    Db::rollback();
                    $msg=$e->getMessage();
                    $code=-1;
                    $returnData=[		
					'code'=>$code,
					'msg'=>$msg,
					'data'=>$data		
		 			];
		 			return json($returnData);
                    
    		}
    		Db::commit();
    		$returnData=[		
			'code'=>$code,
			'msg'=>$msg,
			'data'=>$data		
 			];
 			
            $sor = fopen('upload/'.$orderid.'_'.$param['buynum'].'.txt',"w");
            $fwbool=fwrite($sor,$file);
            
            fclose($sor);
 			return json($returnData);
    		
 	}
 	
 	/*
 	 * 销售统计
 	 */
 	function Salesstatistics(){
 		$param=inputself();
 		$map=[];
 		$type=isset($param['type'])?$param['type']:0;
 		$starttime=isset($param['starttime'])?$param['starttime']:0;
 		$endtime=isset($param['endtime'])?$param['endtime']:time();
 		$map['think_info.mstatus']=['neq','2'];
 		$map['think_info.create_time']=array('between', array($starttime,$endtime));
 		if($type==0){
 			//商品分类
 			$field="IFNULL(think_fl.mnamebie,'自用') as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['商品名称','订单数量','总金额'];	
 			$groupby='think_info.mflid';	
 		}
 		if($type==1){
 			//联系手机
 			$field="IF(think_info.lianxi='','无',think_info.lianxi) as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['联系手机','订单数量','总金额'];
 			$groupby='think_info.lianxi';			
 		}
 		if($type==2){
 			//联系邮箱
 			$field="IF(think_info.email='','无',think_info.email) as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['联系邮箱','订单数量','总金额'];
 			$groupby='think_info.email';			
 		}
 		if($type==3){
 			//本地缓存
 			$field="IF(think_info.cookie='','无',think_info.cookie) as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['本地缓存','订单数量','总金额'];	
 			$groupby='think_info.cookie';		
 		}
 		if($type==4){
 			//支付方式
 			$field="think_info.maddtype as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['支付途径','订单数量','总金额'];	
 			$groupby='think_info.maddtype';		
 		}
 		if($type==5){
 			//微信公众号
 			$field="IF(think_info.openid='','无',think_info.openid) as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['微信公众号ID','订单数量','总金额'];
 			$groupby='think_info.openid';			
 		}
 		if($type==6){
 			//会员帐号
 			$field="IFNULL(think_member.account,'非会员') as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['会员名称','订单数量','总金额'];	
 			$groupby='think_info.memberid';		
 		}
 		if($type==7){
 			//订单号
 			$field="IFNULL(think_info.mcard,'无') as name,count(distinct think_info.id)as count,sum(think_info.mamount) as jine";
 			$lines=['订单号','订单数量','总金额'];	
 			$groupby='think_info.id';		
 		}
 		
 		$result=Db::name("info")->field($field)
 						->join('think_fl','think_fl.id = think_info.mflid','LEFT')
  						->join('think_member','think_info.memberid = think_member.id','LEFT')
  						->where($map)
  						->group($groupby)
	                   	->order('jine desc')
	                   	->select();
	    if($result){
	    	$code=1;
	    }else{
	    	$code=1;
	    }
	    $returnData=[
	    			'code'=>$code,
	    			'data'=>$result,
	    			'lines'=>$lines	    			
	    			];
	    return json($returnData);               	
 		
 	}
 	
 	/*
 	 * 卡密销售统计
 	 */
 	function KeySalesstatistics(){
 		$param=inputself();
 		$map=[];
 		$type=isset($param['type'])?$param['type']:0;
 		$starttime=isset($param['starttime'])?$param['starttime']:0;
 		$endtime=isset($param['endtime'])?$param['endtime']:time();
 		$map['think_mail.update_time']=array('between', array($starttime,$endtime));
 		if($type==0){
 			//商品分类
 			$field="IFNULL(think_fl.mnamebie,'未知') as name,count(distinct think_mail.id)as count";
 			$lines=['商品名称','卡密数量'];	
 			$groupby='think_mail.mpid';	
 		}
 		if($type==1){
 			//联系手机
 			$field="IF(think_info.lianxi='','无',think_info.lianxi) as name,count(distinct think_mail.id)as count";
 			$lines=['联系手机','卡密数量'];
 			$groupby='think_info.lianxi';			
 		}
 		if($type==2){
 			//联系邮箱
 			$field="IF(think_info.email='','无',think_info.email) as name,count(distinct think_mail.id)as count";
 			$lines=['联系邮箱','卡密数量'];
 			$groupby='think_info.email';			
 		}
 		if($type==3){
 			//本地缓存
 			$field="IF(think_info.cookie='','无',think_info.cookie) as name,count(distinct think_mail.id)as count";
 			$lines=['本地缓存','卡密数量'];	
 			$groupby='think_info.cookie';		
 		}
 		if($type==4){
 			//支付方式
 			$field="think_info.maddtype as name,count(distinct think_mail.id)as count";
 			$lines=['支付途径','卡密数量'];	
 			$groupby='think_info.maddtype';		
 		}
 		if($type==5){
 			//微信公众号
 			$field="IF(think_info.openid='','无',think_info.openid) as name,count(distinct think_mail.id)as count";
 			$lines=['微信公众号ID','订单数量'];
 			$groupby='think_info.openid';			
 		}
 		if($type==6){
 			//会员帐号
 			$field="IFNULL(think_member.account,'非会员') as name,count(distinct think_mail.id)as count";
 			$lines=['会员名称','卡密数量'];	
 			$groupby='think_member.account';		
 		}
 		if($type==7){
 			//订单分类
 			$field="IFNULL(think_info.mcard,'未知') as name,count(distinct think_mail.id)as count";
 			$lines=['订单编号','卡密数量'];	
 			$groupby='think_info.mcard';		
 		}
 		$result=Db::name("mail")->field($field)
 						->join('think_fl','think_fl.id = think_mail.mpid','LEFT')
  						->join('think_info','think_info.mcard = think_mail.syddhao','LEFT')
  						
  						->where($map)
  						->group($groupby)
	                   	->order('count desc')
	                   	->select();
	                  	
	    if($result){
	    	$code=1;
	    }else{
	    	$code=1;
	    }
	    $returnData=[
	    			'code'=>$code,
	    			'data'=>$result,
	    			'lines'=>$lines	    			
	    			];
	    return json($returnData);               	
 		
 	}
 	
 	
 	/*
   	 * 根据商品ID和购买数量计算单价和总价
   	 */
   	public function getBuyMoneyBybuynum(){
           $param=inputself();
           $data_fl = Db::query("select * from think_fl where id=:id",['id'=>$param['mpid']]);
           $data_info=Db::query("select * from think_info where (mcard=:mcard or morder=:morder)",['mcard'=>$param['order'],'morder'=>$param['order']]);
           $sfyh=0;
    	   $data_yh=[];
    		$childid=$data_info[0]['childid'];
    		$mpid=$data_info[0]['mflid'];
    		$buynum=$data_info[0]['buynum'];
    		$jine=$data_info[0]['mamount'];
    		if($childid>0){
    			$CommonM=new CommonModel();
	 			$childminmoney=$CommonM->GetChildMinMoneyBygoodid($childid,$buynum,$mpid);
	 			$childFxmoney=bcsub($jine, $childminmoney, 4);
		      	if($childFxmoney<0){
		      		$code=-1;
          			$msg='价格设置错误，请联系管理员修改价格';
          			return json(['code'=>$code,'msg'=>$msg,'money'=>'9999','mdj'=>'9999','typeprice'=>'9999']);          			
		      	}
			 	$data_child_fl=db('child_fl')->where(['goodid'=>trim($mpid),'memberid'=>$childid])->find();
			 	if($data_child_fl){
			 		if($data_child_fl['mprice']>0){
			 			$data_fl[0]=replaceChild($data_child_fl[0],$data_fl[0]);
			 			$child_fl_mpirce=$data_child_fl['mprice'];
			 			$mdj =$child_fl_mpirce;
			 			$typeprice=6;//子站价格
			 		}
			 	}
			}
           
         
           		$typeprice=4;//批发价格
		           if($param['memberid']>0){//判断是否会员登录
		           		//获取会员私密价格
		           		$memberprice=Db::query("select * from think_member_price where memberid=:memberid and goodid=:goodid",['memberid'=>$param['memberid'],'goodid'=>$param['mpid']]);
		           		if(empty($memberprice)){
		           			//获取会员分组
		           			$member=Db::query("select * from think_member where id=:memberid",['memberid'=>$param['memberid']]);	           		
			           		//获取会员分组价格
			           		if(!empty($member)){
			           			$membergroupprice=Db::query("select * from think_member_group_price where membergroupid=:membergroupid and goodid=:goodid",['membergroupid'=>$member[0]['group_id'],'goodid'=>$param['mpid']]);
			           			if(!empty($membergroupprice)){
			           				$mdj=$membergroupprice[0]['price'];
			           				$typeprice=2;//分组价格
			           			}else{
			           				//获取分组折扣
			           				$membergroupdiscount=Db::query("select * from think_member_group where id=:membergroupid",['membergroupid'=>$member[0]['group_id']]);
			           				if(!empty($membergroupdiscount)){
			           					$memberdiscount=$membergroupdiscount[0]['discount'];	           					         					           					
			           				}
			           			}	
			           		}
			           			           		
		           		}else{
		           			$mdj=$memberprice[0]['price'];
		           			$typeprice=1;//会员私密价格
		           		}
		           		
		           		
		           }
		          	
		          	
		                   
		            if($typeprice!=1 && $typeprice!=2){
			          	$data_yh = Db::query("SELECT * from think_yh where mpid=:mpid ORDER BY mdy desc",['mpid'=>$param['mpid']]);
			            //计算单价
			            if(!empty($data_yh)){
			                        foreach ($data_yh as $v) {
			                        	
			                            if($v['mdy'] <= $buynum){
			                                $mdj = $v['mdj'];
			                                break;
			                            }
			                        }
			            }
		                
		                
		                if(!isset($mdj)){
		                	$mdj = $data_fl[0]['mprice'];
		                	$typeprice=3;//原始价格
		                	$sfyh=0;
		                }else{
		                	$sfyh=1; 
		                }
		                
		            }
		            
		            /*
		             * 折扣开始
		             */
		            if(isset($memberdiscount)){
		            	if($memberdiscount>0 && $memberdiscount<100){
			            	$somemberdisount=bcdiv($memberdiscount,100,2);
			            	
			            	$mdj=bcmul($mdj,$somemberdisount,4);
			            	
			            	$typeprice=5;//分组折扣
			            }
		            }
		            
		            /*
		             * 折扣结束
		             */
           
           
            
           	$buynum=$param['buynum'];
             //计算购买金额
            if($buynum*$mdj<1){
            	 $canMoney = number_format($buynum*$mdj/100,4);
            }else{
            	 $canMoney = number_format($buynum*$mdj/100,2);
            }
           
            //库存数量
            if ($canMoney == 0) {
            	$code=-1;
                $msg = "金额不足！";
            } elseif ($buynum < $data_fl[0]['mmin'] || $buynum > $data_fl[0]['mmax']) {
            	$code=-1;
                $msg = '最小提取数量：' . $data_fl[0]['mmin'] . '&nbsp;&nbsp;最大提取数量：' . $data_fl[0]['mmax'];
            }else{
            	  $code=1;
                $msg = 'OK'; 
            }
           
          return json(['code'=>$code,'msg'=>$msg,'money'=>$canMoney,'mdj'=>$mdj,'typeprice'=>$typeprice]);    
    }
    
    /*
     * 根据用户ID获取用户信息
     */
    public function ApigetMemberById(){
    	$result=Db::name('member')->where('id',input('memberid'))->find();
    	return json($result); 	
    }
    
    public function dencode(){
      $param=inputself();
      $param['text']=str_replace(" ","+",$param['text']);
      $text=passport_decrypt($param['text'],DECRYPT_KEY);
      return json(['text'=>$text]);
    }
    public function weitixian(){
    	//待提现
        $daitixian=Db::name('member_tixian')->where('status',0)->count();
        return json($daitixian); 
    }
    
 	private function createOrder(){
 		
		 
		  $order_date = date('Y-m-d');
		 
		  //订单号码主体（YYYYMMDDHHIISSNNNNNNNN）
		 
		  $order_id_main = date('YmdHis') . rand(10000000,99999999);
		 
		  //订单号码主体长度
		 
		  $order_id_len = strlen($order_id_main);
		 
		  $order_id_sum = 0;
		 
		  for($i=0; $i<$order_id_len; $i++){
		 
		  $order_id_sum += (int)(substr($order_id_main,$i,1));
		 
		  }
		 
		  //唯一订单号码（YYYYMMDDHHIISSNNNNNNNNCC）
		 
		  $order_id = $order_id_main . str_pad((100 - $order_id_sum % 100) % 100,2,'0',STR_PAD_LEFT);
		  return $order_id;
 	}
 	
}
