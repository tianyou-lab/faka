<?php

namespace app\admin\controller;
use app\admin\model\ApikeyModel;
use think\Db;

class Apikey extends Base
{
    /**
     * API秘钥列表
     * @author API秘钥管理系统
     */
    public function index()
    {
        $key = input('key');
        $status = input('status');
        $map = [];
        
        if($key && $key !== "") {
            $map['app_name|app_id'] = ['like', "%" . $key . "%"];          
        }
        
        if($status && $status !== "") {
            $map['status'] = $status;          
        }
        
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits = config('list_rows');
        
        $apikey = new ApikeyModel();
        $count = $apikey->getAllCount($map);
        $allpage = intval(ceil($count / $limits));
        $lists = $apikey->getApikeyByWhere($map, $Nowpage, $limits);
        
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        $this->assign('status', $status);
        
        if(input('get.page')) {
            return json($lists);
        }
        
        return $this->fetch();
    }

    /**
     * 添加API秘钥
     */
    public function add()
    {
        if(request()->isAjax()) {
            $param = input('post.');
            
            // 生成app_id和app_secret
            $param['app_id'] = 'app_' . date('YmdHis') . mt_rand(1000, 9999);
            $param['app_secret'] = md5($param['app_id'] . time() . mt_rand(10000, 99999));
            $param['status'] = 1; // 默认启用
            $param['create_time'] = time();
            $param['update_time'] = time();
            
            $apikey = new ApikeyModel();
            $flag = $apikey->insertApikey($param);
            
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        
        return $this->fetch();
    }

    /**
     * 编辑API秘钥
     */
    public function edit()
    {
        $apikey = new ApikeyModel();
        
        if(request()->isPost()) {
            $param = input('post.');
            $param['update_time'] = time();
            
            $flag = $apikey->editApikey($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        
        $id = input('param.id');
        $apikeyInfo = $apikey->getOneApikey($id);
        
        $this->assign('apikey', $apikeyInfo);
        return $this->fetch();
    }

    /**
     * 启用/停用API秘钥
     */
    public function status()
    {
        $id = input('param.id');
        $status = input('param.status');
        
        $apikey = new ApikeyModel();
        $flag = $apikey->setStatus($id, $status);
        
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 删除API秘钥
     */
    public function del()
    {
        $id = input('param.id');
        
        $apikey = new ApikeyModel();
        $flag = $apikey->delApikey($id);
        
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 重新生成秘钥
     */
    public function regenerate()
    {
        $id = input('param.id');
        
        // 生成新的app_secret
        $new_secret = md5('regenerate_' . time() . mt_rand(10000, 99999));
        
        $apikey = new ApikeyModel();
        $flag = $apikey->regenerateSecret($id, $new_secret);
        
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * API使用统计
     */
    public function stats()
    {
        $id = input('param.id');
        
        if($id) {
            // 获取单个API的统计信息
            $apikey = new ApikeyModel();
            $stats = $apikey->getApikeyStats($id);
            
            $this->assign('stats', $stats);
            $this->assign('apikey_id', $id);
        } else {
            // 获取所有API的统计信息
            $stats = Db::query("
                SELECT 
                    ak.app_name,
                    ak.app_id,
                    COUNT(al.id) as total_calls,
                    COUNT(CASE WHEN al.status = 'success' THEN 1 END) as success_calls,
                    COUNT(CASE WHEN al.status = 'error' THEN 1 END) as error_calls,
                    COUNT(CASE WHEN DATE(FROM_UNIXTIME(al.create_time)) = CURDATE() THEN 1 END) as today_calls
                FROM think_apikey ak
                LEFT JOIN think_api_log al ON ak.id = al.apikey_id
                WHERE ak.status = 1
                GROUP BY ak.id
                ORDER BY total_calls DESC
            ");
            
            $this->assign('stats', $stats);
        }
        
        return $this->fetch();
    }
}


