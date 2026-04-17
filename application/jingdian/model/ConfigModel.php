<?php

namespace app\jingdian\model;
use think\Model;
use think\Db;
class ConfigModel extends Model
{
    protected $name = 'child_config';

    //获取配置所有信息
    public function getAllConfig()
    {
        return $this->where('memberid',session('useraccount.id'))->select();
    }


    //保存信息
    public function SaveConfig($map,$value)
    {
        try{
        	$exist=$this->where($map)->find();
        	if(!$exist){
        		$resultInsert=$this->insert($map);       		   		
        	}
        	
        	
        	$result = $this->allowField(true)->where($map)->setField('value', $value);
        	
            if(false === $result){            
                return ['code' => -1, 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'msg' => '保存成功'];
            }
        }catch( PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

}