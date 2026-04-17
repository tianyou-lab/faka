<?php
namespace app\madmin\controller;
use think\Controller;
use think\Request;
use think\Db;

class Base extends Controller
{
    public function _initialize()
    {
    	
		$request = Request::instance();
    	$config = cache('db_config_data');
        if(!$config){            
            $config = load_config();                          
            cache('db_config_data',$config);
        }
        config($config); 
    	$module     = strtolower(request()->module());
        $controller = strtolower(request()->controller());
        $action     = strtolower(request()->action());
        $url        = $module."/".$controller."/".$action;
        //跳过检测以及主页权限 
		$loginurl = array("madmin/index/login", "madmin/index/dologin"); 
    	if(in_array($url, $loginurl)){                      
            return;             
        }

    	if(!session('uid')||!session('username')){
            if(!in_array($url, $loginurl)&&$request->path()!='m'.getadminpath()){               
				return $this->redirect(url('/'),302);
                //$this->error('抱歉，请先登录',url('login'));              
            }          
        }

        
        $hasAdmin=Db::name('admin')->where('id', session('uid'))->find();
        $token=md5($hasAdmin['username'] . $hasAdmin['password'].$_SERVER['HTTP_HOST'].date("Y-m-d").getIP());
        
        if($token!=session('admintoken')){
        	$this->redirect(url("login"));
        }
        
    	

        if(config('web_site_close') == 0 && session('uid') !=1 ){
            $this->error('站点已经关闭，请稍后访问~');
        }
        if(config('admin_allow_ip') && session('uid') !=1 ){          
            if(in_array(getIP(),explode('#',config('admin_allow_ip')))){
                $this->error('403:禁止访问');
            }
        }
    }
}