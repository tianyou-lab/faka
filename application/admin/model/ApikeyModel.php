<?php

namespace app\admin\model;
use think\Model;
use think\Db;

class ApikeyModel extends Model
{
    protected $name = 'apikey';
    protected $autoWriteTimestamp = true;

    /**
     * 根据搜索条件获取API秘钥列表
     */
    public function getApikeyByWhere($map, $Nowpage, $limits)
    {
        return $this->where($map)
            ->page($Nowpage, $limits)
            ->order('id desc')
            ->select();
    }

    /**
     * 根据搜索条件获取总数量
     */
    public function getAllCount($map)
    {
        return $this->where($map)->count();
    }

    /**
     * 插入API秘钥
     */
    public function insertApikey($param)
    {
        try {
            $result = $this->validate('ApikeyValidate')->allowField(true)->save($param);
            if(false === $result) {
                writelog(session('uid'), session('username'), 'API秘钥【'.$param['app_name'].'】添加失败', 2);
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            } else {
                writelog(session('uid'), session('username'), 'API秘钥【'.$param['app_name'].'】添加成功', 1);
                return ['code' => 1, 'data' => '', 'msg' => '添加API秘钥成功'];
            }
        } catch(\PDOException $e) {
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑API秘钥
     */
    public function editApikey($param)
    {
        try {
            $result = $this->validate('ApikeyValidate')->allowField(true)->save($param, ['id' => $param['id']]);
            if(false === $result) {
                writelog(session('uid'), session('username'), 'API秘钥【'.$param['app_name'].'】编辑失败', 2);
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            } else {
                writelog(session('uid'), session('username'), 'API秘钥【'.$param['app_name'].'】编辑成功', 1);
                return ['code' => 1, 'data' => '', 'msg' => '编辑API秘钥成功'];
            }
        } catch(\PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据ID获取单个API秘钥
     */
    public function getOneApikey($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 设置API秘钥状态
     */
    public function setStatus($id, $status)
    {
        try {
            $result = $this->save(['status' => $status, 'update_time' => time()], ['id' => $id]);
            if(false === $result) {
                return ['code' => 0, 'data' => '', 'msg' => '状态更新失败'];
            } else {
                $statusText = $status == 1 ? '启用' : '停用';
                writelog(session('uid'), session('username'), 'API秘钥状态更新为【'.$statusText.'】', 1);
                return ['code' => 1, 'data' => '', 'msg' => '状态更新成功'];
            }
        } catch(\PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 删除API秘钥
     */
    public function delApikey($id)
    {
        try {
            $apikey = $this->getOneApikey($id);
            $this->where('id', $id)->delete();
            writelog(session('uid'), session('username'), 'API秘钥【'.$apikey['app_name'].'】删除成功', 1);
            return ['code' => 1, 'data' => '', 'msg' => '删除成功'];
        } catch(\PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 重新生成秘钥
     */
    public function regenerateSecret($id, $new_secret)
    {
        try {
            $result = $this->save(['app_secret' => $new_secret, 'update_time' => time()], ['id' => $id]);
            if(false === $result) {
                return ['code' => 0, 'data' => '', 'msg' => '秘钥重新生成失败'];
            } else {
                writelog(session('uid'), session('username'), 'API秘钥重新生成成功', 1);
                return ['code' => 1, 'data' => $new_secret, 'msg' => '秘钥重新生成成功'];
            }
        } catch(\PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 验证API秘钥
     */
    public function verifyApikey($app_id, $app_secret)
    {
        $apikey = $this->where(['app_id' => $app_id, 'app_secret' => $app_secret, 'status' => 1])->find();
        
        if($apikey) {
            // 更新最后使用时间
            $this->save(['last_used_time' => time()], ['id' => $apikey['id']]);
            return $apikey;
        }
        
        return false;
    }

    /**
     * 获取API使用统计
     */
    public function getApikeyStats($apikey_id)
    {
        $stats = Db::query("
            SELECT 
                COUNT(*) as total_calls,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as success_calls,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error_calls,
                COUNT(CASE WHEN DATE(FROM_UNIXTIME(create_time)) = CURDATE() THEN 1 END) as today_calls,
                COUNT(CASE WHEN DATE(FROM_UNIXTIME(create_time)) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_calls,
                COUNT(CASE WHEN DATE(FROM_UNIXTIME(create_time)) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_calls
            FROM think_api_log 
            WHERE apikey_id = :apikey_id
        ", ['apikey_id' => $apikey_id]);
        
        return $stats[0] ?? [
            'total_calls' => 0,
            'success_calls' => 0,
            'error_calls' => 0,
            'today_calls' => 0,
            'week_calls' => 0,
            'month_calls' => 0
        ];
    }

    /**
     * 记录API调用日志
     */
    public function logApiCall($apikey_id, $api_method, $request_data, $response_data, $status, $error_msg = '')
    {
        try {
            Db::name('api_log')->insert([
                'apikey_id' => $apikey_id,
                'api_method' => $api_method,
                'request_data' => json_encode($request_data, JSON_UNESCAPED_UNICODE),
                'response_data' => json_encode($response_data, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'error_msg' => $error_msg,
                'ip' => getIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'create_time' => time()
            ]);
        } catch(\Exception $e) {
            // 日志记录失败不影响主流程
        }
    }
}


