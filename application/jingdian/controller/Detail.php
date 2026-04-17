<?php
namespace app\jingdian\controller;
use app\jingdian\model\DetailModel;
use think\Db;

class Detail extends Base
{
    public function index()
    {
    	$id = input('param.id');
    	
        $model = new DetailModel();
        $detail = $model->getDetail($id);
        
        $up = Db::name('article')->where('views !=0 AND id <' . $id)->where('cate_id','2')->order('id desc')->limit(1)->find();
        $down = Db::name('article')->where('views !=0 AND id >' . $id)->where('cate_id','2')->order('id')->limit(1)->find();
        $this->assign('detail',$detail);
        $this->assign('up',$up);
        $this->assign('down',$down);
        
        return $this->fetch();
    }
    
    public function getAll()
    {
    	
    	
        $model = new DetailModel();
        $detail = $model->getAllDetail();
        $ggxx1 = db('article')->where(array('cate_id' => 1,'title'=>'首页公告'))->select();
        $this->assign('ggxx1', $ggxx1);
        $this->assign('detail',$detail);      
        
        return $this->fetch();
    }
    
    
}
