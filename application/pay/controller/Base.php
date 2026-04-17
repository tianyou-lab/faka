<?php
namespace app\pay\controller;
use app\jingdian\model\BaseModel;
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
        $model = new BaseModel();
    	$cate = $model->getAllCate();
    	$href = $model->getYqHref();
        $this->assign('cate', $cate);
        $this->assign('href', $href);
        $this->url_host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT']=='80'? '' : ($_SERVER['SERVER_PORT']=='443'? '' : ':'.$_SERVER['SERVER_PORT'])));
    }
}