<?php
namespace app\madmin\controller;
use app\admin\model\CateGoryModel;
use app\admin\model\OrderModel;
use app\admin\model\MemberModel;
use app\admin\model\MemberGroupModel;
use com\IpLocationqq;
use org\Verify;
use think\Config;
use think\Loader;
use think\Db;
use think\Request;

class Index extends Base
{
    
	  
    /*
     * 登录
     */
    public function doLogin()
    {    
	    
        $referer = isset($_SERVER["HTTP_REFERER"]) ? (string)$_SERVER["HTTP_REFERER"] : "";
        if ($referer !== "") {
            $refererHost = (string)parse_url($referer, PHP_URL_HOST);
            $refererPath = trim((string)parse_url($referer, PHP_URL_PATH), "/");
            $currentHost = (string)parse_url("http://" . (isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : ""), PHP_URL_HOST);
            $madminPath = "m" . trim(getadminpath(), "/");

            $hostValid = ($refererHost === "" || $currentHost === "" || strcasecmp($refererHost, $currentHost) === 0);
            $pathValid = ($refererPath === "" || preg_match("#(?:^|/)" . preg_quote($madminPath, "#") . "(?:\\.html)?$#", $refererPath));

            if (!$hostValid || !$pathValid) {
                return json(["code" => 404, "url" => "404.html", "msg" => "404页面丢失"]);
            }
        }

        $username = input("param.username");
        $password = input("param.password");
        $code = input("param.code");
            
        $result = $this->validate(compact('username', 'password'), 'MemberloginValidate');
        if(true !== $result){
            return json(['code' => -5, 'url' => '', 'msg' => $result]);
        }
        
        if(config('CODE_TYPE')==0){
        	$code = input("param.code");
        	$verify = new Verify();
	       	if (!$code) {
	                return json(['code' => -4, 'url' => '', 'msg' => '请输入验证码']);
	        }
	        if (!$verify->check($code)) {
	                return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
	        }
        }elseif(config('CODE_TYPE')==1){
        	$gtresult=action("jingdian/Geetest/gtcheck");
        	if($gtresult==false){
        		return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
        	}
        	
        }

        $hasUser = Db::name('admin')->where('username', $username)->find();
        if(empty($hasUser)){
            return json(['code' => -1, 'url' => '', 'msg' => '管理员不存在']);
        }

        if(md5(md5($password) . config('auth_key')) != $hasUser['password']){
            writelog($hasUser['id'],$username,'用户【'.$username.'】登录失败：密码错误',2);
            return json(['code' => -2, 'url' => '', 'msg' => '账号或密码错误']);
        }

        if(1 != $hasUser['status']){
            writelog($hasUser['id'],$username,'用户【'.$username.'】登录失败：该账号被禁用',2);
            return json(['code' => -6, 'url' => '', 'msg' => '该账号被禁用']);
        }
        
        $token=md5($hasUser['username'] . $hasUser['password'].$_SERVER['HTTP_HOST'].date("Y-m-d").getIP());
        session('uid', $hasUser['id']);         //用户ID
        session('username', $hasUser['username']);  //用户名
        session('password', $hasUser['password']);  //用户名
        session('portrait', $hasUser['portrait']); //用户头像
        cookie("admintoken",$token);
  
        //更新管理员状态
        $param = [
            'loginnum' => $hasUser['loginnum'] + 1,
            'last_login_ip' => getIP(),
            'last_login_time' => time(),
            'token' => $token
        ];

        Db::name('admin')->where('id', $hasUser['id'])->update($param);
        writelog($hasUser['id'],session('username'),'用户【'.session('username').'】登录成功',1);
        return json(['code' => 1, 'url' => url('index/index'), 'msg' => '登录成功！']);
	}  
    /*
     * 登录
     */
    public function login()
    {
		$request = Request::instance();
		if($request->path()!='m'.getadminpath()){
         	 abort(404,'页面不存在');
        }
	    if(!request()->isPost()){
	       return $this->fetch(); 	
	    }
    }
    public function index()
    {        
        //今日收益
        $sql="SELECT sum(mamount) as jinri FROM `think_info` where mstatus<>2 and to_days(date_format(from_UNIXTIME(`update_time`),'%Y-%m-%d')) = to_days(now())";
        $info_jinri = Db::query($sql);
        $this->assign('info_jinri', $info_jinri);
         //昨日收益
        $sql="SELECT sum(mamount) as zuori FROM `think_info` where mstatus<>2 and to_days(now())-to_days(date_format(from_UNIXTIME(`update_time`),'%Y-%m-%d')) =1 ";
        $info_zuori = Db::query($sql);
        $this->assign('info_zuori', $info_zuori);
         //订单数目
        $sql="SELECT count(1) as infonum from think_info";
        $info_info = Db::query($sql);
        $this->assign('info_info', $info_info);
        //待发货数量
        $sql="SELECT count(id) as daifahuo from think_info where mstatus=1 and mstatus<>4 and mflid in(SELECT id from think_fl where type=1)";
        $info_daifahuo = Db::query($sql);
        $this->assign('info_daifahuo', $info_daifahuo);
        //待提现
        $daitixian=Db::name('member_tixian')->where('status',0)->count();
        $this->assign('daitixian', $daitixian);
        //未提取订单
        $sql="SELECT count(id) as weitiqu from think_info where mstatus=0 and mflid in(SELECT id from think_fl where type=0)";
        $info_weitiqu = Db::query($sql);
        $this->assign('info_weitiqu', $info_weitiqu);
        //商品数量
        $shopnum=Db::name('fl')->count();
        $this->assign('shopnum', $shopnum);
        //会员数量
        $membernum=Db::name('member')->count();
        $this->assign('membernum', $membernum);		
        return $this->fetch();  
    }


    /*
     * 商品管理
     */
	public function goods(){
		$key = input('key');
        $status=input('status');
        if($status==''){
        	$status=999;
        }
        $status=1;       
        $type=0;       
        $map = [];
        if($key&&$key!=="")
        {
            $map['mname|mnotice'] = ['like',"%" . $key . "%"];          
        }
        
        $mlm=input('mlm');
        if($mlm!='999' && $mlm!==null){
          $map['think_fl.mlm']=$mlm;
        }
        $arr=Db::name("category_group")->column("id,name"); //获取分组列表
        $member = new CateGoryModel();    
        $Nowpage = input('get.page') ? input('get.page'):1;
        $limits = config('list_rows');// 获取总条数
        $count = $member->getAllCount($map,$status,$type);//计算总页面
        $allpage = intval(ceil($count / $limits));      
        $lists = $member->getMemberByWhere($map, $Nowpage, $limits,$status,$type);
        if(!empty($lists)){
        	$data_mail = Db::query("SELECT count(mis_use) as count,mpid from think_mail where mis_use=0 GROUP BY mpid");
	    	$data_yiyong = Db::query("SELECT count(mis_use) as yiyong,mpid from think_mail where mis_use=1 GROUP BY mpid");
	    	$result = array();
	        foreach ($data_mail as $val) {
	            $result[$val['mpid']] = $val['count'];
	        }
	        $used = array();
	        foreach ($data_yiyong as $val) {
	            $used[$val['mpid']] = $val['yiyong'];
	        }
	        foreach ($lists as &$v) {
	            if(!empty($result[$v['id']])){
	            	$v['count'] = $result[$v['id']];
	            }else{
	            	if($v['type']==1){
		            	$v['count'] = mt_rand(100,999);   	
		            }else{
		            	$v['count'] = 0; 
		            }
	            }
	            
	            
	            if(!empty($used[$v['id']])){
	            	$v['yiyong'] = $used[$v['id']];            	
	            }else{
	            	$v['yiyong'] = 0;
	            }
	            $v['zongshu']=$v['count']+$v['yiyong'];
	            
	                               
	        }
        }
        
        
        if($mlm===null){
          $mlm=999;
        }
       
        $this->assign('count', $count); 
        $this->assign('Nowpage', $Nowpage); //当前页
        $this->assign('allpage', $allpage); //总页数 
        $this->assign('val', $key);
        $this->assign('status', $status);
        $this->assign('type', $type);
        $this->assign("search_user",$arr);
        $this->assign("mlm",$mlm);
        if(input('get.page'))
        {
            return json($lists);
        }
		return $this->fetch();
	}
	
	/*
	 * 补货
	 */
	public function addkami(){
		$param=inputself();
		$goodid=$param['goodid'];
		$FLData=Db::name('fl')->where('id',$goodid)->find();
		if(!request()->isPost()){
			if(!$FLData){
				return $this->error('商品不存在');
			}
			if($FLData['status']==0){
				return $this->error('该商品已下架');
			}			
			$this->assign('shopdetail', $FLData);
			return $this->fetch();
		}
		
		$kami=$param['kami'];
		$order=array("\r\n","\n","\r");
		$replace="\r\n";
		$kami=str_replace($order,$replace,$kami); 
		$str = $kami;
		$arr = array();
		for($i=0;$i<strlen($str);$i++){
		    $arr[] = ord($str[$i]);
		}
		
			
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
		        				  ,'addqudao'=>''
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
				return json(['code'=>1,"msg"=>'恭喜你，操作成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,"url"=>url('index/goods')]);
			}else{
				return json(['code'=>-1,"msg"=>'补货不成功<br>总共数量：'.$num.'<br>成功数量：'.$result.'<br>存在数量：'.$cunzainum,"url"=>url('index/goods')]);
			}

			
		
		
		
		
	}
	public function order(){
		$key = input('key');         
        $map = [];
        if($key&&$key!=="")
        {
            $map['mcard|morder|lianxi|think_info.email'] = ['like',"%" . $key . "%"];          
        }              
        $order = new OrderModel($map);    
        $Nowpage = input('get.page') ? input('get.page'):1;
        $limits = config('list_rows');// 获取总条数
        $count = $order->getAllCount();//计算总页面
        $allpage = intval(ceil($count / $limits));      
        $lists = $order->getOrderByWhere($map, $Nowpage, $limits);
    
        $this->assign('count', $count); 
        $this->assign('Nowpage', $Nowpage); //当前页
        $this->assign('allpage', $allpage); //总页数 
        $this->assign('val', $key);
        if(input('get.page'))
        {
            return json($lists);
        }
      
		return $this->fetch();
	}
  
	/**
     * 订单编辑
     * 
     */
	public function orderedit(){
      if(!request()->isPost()){        
          $orderid = input('id');		
          $this->assign('orderid', $orderid);
          return $this->fetch();
        }else{
          	$param=inputself();
            $OrderM = new OrderModel();       
            $result=$OrderM->editOrder($param);
            return json($result);	
        }
          
      
		
	}
	/**
     * 会员列表
     * 
     */
    public function member(){

        $key = input('key');
        $group_id=input('group_id');
        if($group_id!='999' && $group_id!==null){
          $map['think_member.group_id']=$group_id;
        }
        $map['closed'] = 0;//0未删除，1已删除
   
        if($key&&$key!=="")
        {           
            $map['account|nickname|mobile'] = ['like',"%" . $key . "%"];          
        }
        $arr=Db::name("member_group")->column("id,group_name"); //获取分组列表
        $Zmoney=Db::name("member")->column("sum(money)"); //获取剩余总额
        $Ztgmoney=Db::name("member")->column("sum(tg_money)"); //获取总推广佣金          
        $member = new MemberModel();       
        $Nowpage = input('get.page') ? input('get.page'):1;
        $limits = config('list_rows');// 获取总条数
        $count = $member->getAllCount($map);//计算总页面
        $allpage = intval(ceil($count / $limits));       
        $lists = $member->getMemberByWhere($map, $Nowpage, $limits);
       
         $Ip = new IpLocationqq('qqwry.dat'); // 实例化类 参数表示IP地址库文件
        foreach($lists as $k=>$v){
              $lists[$k]['last_login_time']=date("Y-m-d H:i:s",$lists[$k]['last_login_time']);
              $userip=$lists[$k]['last_login_ip'];
                if(!empty($userip)){
                    $lists[$k]['ipaddr'] = $Ip->getlocation($userip);
                }else{
                    $lists[$k]['ipaddr']=['country'=>'未知','area'=>'地区'];    	
                }            
          }
        if($group_id===null){
          $group_id=999;
        }
        $this->assign('count', $count);   
        $this->assign('Nowpage', $Nowpage); //当前页
        $this->assign('allpage', $allpage); //总页数 
        $this->assign("search_user",$arr);
        $this->assign("group_id",$group_id);
        $this->assign("Zmoney",$Zmoney);
        $this->assign("Ztgmoney",$Ztgmoney);
        $this->assign('val', $key);
        if(input('get.page'))
        {
            return json($lists);
        }
        return $this->fetch();
    }
	
}
