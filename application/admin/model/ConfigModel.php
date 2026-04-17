<?php

namespace app\admin\model;
use think\Model;
use think\Db;

class ConfigModel extends Model
{
    protected $name = 'config';

    //获取配置所有信息
    public function getAllConfig()
    {
        return $this->select();
    }


    //保存信息
    public function SaveConfig($map,$value)
    {
        try{
            $hasConfig = $this->where($map)->find();
            if($hasConfig){
                $result = $this->allowField(true)->where($map)->setField('value', $value);
            }else{
                $result = $this->allowField(true)->save([
                    'name' => $map['name'],
                    'value' => $value
                ]);
            }
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