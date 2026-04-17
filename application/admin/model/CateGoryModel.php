<?php

namespace app\admin\model;
use think\Model;
use think\Db;
use app\jingdian\model\GoodsListModel;

class CateGoryModel extends Model
{
   protected $name = 'fl';  
   protected $autoWriteTimestamp = true;   // 开启自动写入时间戳

    /**
     * 根据搜索条件获取用户列表信息
     */
    public function getMemberByWhere($map, $Nowpage, $limits,$status,$type)
    {
        //初始化商品类
        $GoodList=new GoodsListModel();
        if($status==999 && $type!=999){
        	$result=$this->field('think_fl.*,name')->join('think_category_group','think_category_group.id = think_fl.mlm','LEFT')
            ->where($map)->where('think_fl.type',$type)->page($Nowpage, $limits)->order('sort asc')->select();           
            foreach ($result as &$v) {          
	            $v=replaceImgurl($v);               
	        }
	        return $result;
         
           
        }else if($type==999 && $status!=999){
        	$result=$this->field('think_fl.*,name')->join('think_category_group','think_category_group.id = think_fl.mlm','LEFT')
            ->where($map)->where('think_fl.status',$status)->page($Nowpage, $limits)->order('sort asc')->select();
            
            foreach ($result as &$v) {          
	            $v=replaceImgurl($v);               
	        }
	        return $result;
        }else if($status==999 && $type==999){
        	$result=$this->field('think_fl.*,name')->join('think_category_group','think_category_group.id = think_fl.mlm','LEFT')
            ->where($map)->page($Nowpage, $limits)->order('sort asc')->select();
            
            foreach ($result as &$v) {          
	            $v=replaceImgurl($v);               
	        }
	        
	        return $result;
        }else{
        	$result=$this->field('think_fl.*,name')->join('think_category_group','think_category_group.id = think_fl.mlm','LEFT')
            ->where($map)->where('think_fl.status',$status)->where('think_fl.type',$type)->page($Nowpage, $limits)->order('sort asc')->select();
            foreach ($result as &$v) {          
	            $v=replaceImgurl($v);               
	        }
	        return $result;
        }
        
    }

    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllCount($map,$status,$type)
    {
        if($status==999 && $type!=999){
        	return $this->where($map)->where('type',$type)->count();

        }else if($type==999 && $status!=999){
        	return $this->where($map)->where('status',$status)->count();
        }else if($status==999 && $type==999){
        	return $this->where($map)->count();
        }else{
        	return $this->where($map)->where('status',$status)->where('type',$type)->count();
        }
        
    }


    /**
     * 一次查询获取商品管理页所需的所有分类统计数量
     * 替代原来 8 次独立 COUNT 查询
     */
    public function getCountStats($map = [])
    {
        $base = $this->where($map);
        // 用 CASE WHEN 在一条 SQL 里统计所有维度
        $row = Db::name('fl')->where($map)->field("
            COUNT(*) as all_count,
            SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) as allsale,
            SUM(CASE WHEN type=0 THEN 1 ELSE 0 END) as allzidong,
            SUM(CASE WHEN status=1 AND type=0 THEN 1 ELSE 0 END) as zidongsell,
            SUM(CASE WHEN status=0 AND type=0 THEN 1 ELSE 0 END) as zidongsale,
            SUM(CASE WHEN type=1 THEN 1 ELSE 0 END) as allshoudong,
            SUM(CASE WHEN status=1 AND type=1 THEN 1 ELSE 0 END) as shoudongsell,
            SUM(CASE WHEN status=0 AND type=1 THEN 1 ELSE 0 END) as shoudongsale
        ")->find();
        return [
            'all'          => (int)$row['all_count'],
            'allsale'      => (int)$row['allsale'],
            'allzidong'    => (int)$row['allzidong'],
            'zidongsell'   => (int)$row['zidongsell'],
            'zidongsale'   => (int)$row['zidongsale'],
            'allshoudong'  => (int)$row['allshoudong'],
            'shoudongsell' => (int)$row['shoudongsell'],
            'shoudongsale' => (int)$row['shoudongsale'],
        ];
    }

    /**
     * 插入信息
     */
    public function insertMember($param)
    {
        try{
            $result = $this->allowField(true)->save($param);
            if(false === $result){            
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
            	
                return ['code' => 1, 'data' => '', 'msg' => '添加成功','id'=>$this->id];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑信息
     * @param $param
     */
    public function editCategory($param)
    {
        try{
            $result = $this->allowField(true)->save($param, ['id' => $param['id']]);
            if(false === $result){            
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
            	//echo $this->getlastsql();
                return ['code' => 1, 'data' => '', 'msg' => '编辑成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
	
	
	/**
     * 根据商品id获取商品信息
     * @param $id
     */
    public function getOneFl($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 根据商品id获取商品信息
     * @param $id
     */
    public function getOneMember($id)
    {
        //初始化商品类
        //$GoodList=new GoodsListModel();
        $result=$this->where('id', $id)->find();
        $result=replaceImgurl($result);        
        return $result;
    }
    
	/**
     * 获取所有FL
     * @param $id
     */
    public function getAllfl()
    {       
        $result=$this->where(['status'=>1,'type'=>0])->select();    
        return $result;
    }
    

    /**
     * 删除商品
     * @param $id
     */
    public function delMember($id)
    {
        try{
            $mail=Db::name('mail')->where('mpid',$id)->count();
            if($mail){
            	return ['code' => 0, 'data' => '', 'msg' => '此商品存在'.$mail.'个卡密，请先删除卡密'];
            }
            Db::name('child_fl')->where('goodid',$id)->delete();//删除分站商品
            Db::name('member_price')->where('goodid',$id)->delete();//删除私密价格
            Db::name('member_group_price')->where('goodid',$id)->delete();//删除分组私密价格
            Db::name('yh')->where('mpid',$id)->delete();//删除商品优惠
            $this->where('id',$id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除成功'];
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    
    
   


}