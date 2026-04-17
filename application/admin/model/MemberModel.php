<?php

namespace app\admin\model;
use think\Model;
use think\Db;

class MemberModel extends Model
{
    protected $name = 'member';  
    protected $autoWriteTimestamp = true;   // 开启自动写入时间戳

    /**
     * 根据搜索条件获取用户列表信息 - 优化版本
     */
    public function getMemberByWhere($map, $Nowpage, $limits)
    {
        // 使用查询缓存键
        $cacheKey = 'member_query_' . md5(serialize($map)) . '_' . $Nowpage . '_' . $limits;
        $result = cache($cacheKey);
        
        if (!$result) {
            // 优化：只选择必要的字段，减少数据传输
            $result = $this->field("
                think_member.id, think_member.account, think_member.mobile, think_member.email, 
                think_member.qq, think_member.money, think_member.tg_money, think_member.integral, 
                think_member.status, think_member.is_distribut, think_member.last_login_time, 
                think_member.last_login_ip, think_member.create_time, think_member.group_id,
                IFNULL(think_member_group.group_name,'注册会员') as group_name,
                think_fz_auth.id as authid, think_fz_auth.starttime, think_fz_auth.endtime
            ")
            ->join('think_member_group', 'think_member.group_id = think_member_group.id','LEFT')
            ->join('think_fz_auth', 'think_fz_auth.memberid = think_member.id','LEFT')
            ->where($map)
            ->page($Nowpage, $limits)
            ->order('think_member.id desc')
            ->select();
            
            // 缓存查询结果2分钟
            cache($cacheKey, $result, 120);
        }
        
        return $result;
    }

    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllCount($map)
    {
        return $this->where($map)->count();
    }


    /**
     * 插入信息
     */
    public function insertMember($param)
    {
        try{
            
            $result = $this->validate('MemberValidate')->allowField(true)->save($param);
            if(false === $result){            
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '添加成功'];
            }
        }catch( \PDOException $e){
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑信息
     * @param $param
     */
    public function editMember($param)
    {
        try{
            $result =  $this->validate('MemberValidate')->allowField(true)->save($param, ['id' => $param['id']]);
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
     * 根据用户id获取角色信息
     * @param $id
     */
    public function getOneMember($id)
    {
        return $this->where('id', $id)->find();
    }
    
    /**
     * 根据用户名获取id
     * @param $id
     */
    public function getOneMemberByaccount($account)
    {
        return $this->where('account', $account)->find();
    }



    /**
     * 删除用户
     * @param $id
     */
    public function delMember($id)
    {
        try{
             $this->where('id', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除成功'];
        }catch( \PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }


}