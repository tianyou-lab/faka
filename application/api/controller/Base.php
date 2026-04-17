<?php
namespace app\api\controller;
use app\api\model\BaseModel;
use think\Controller;

class Base extends Controller
{
    public function _initialize()
    {
       	$config = cache('db_config_data');
        if(!$config){            
            $config = load_config();                          
            cache('db_config_data',$config);
        }
        config($config);        
        $param=inputself();
    	$token=config('token');
    	if($token!=$param['token']){
    		//$this->error('403:禁止访问');
    		exit("{'code':-1, 'url':'', 'msg':'TOKEN配置错误'}");
    	}
    }
    

}