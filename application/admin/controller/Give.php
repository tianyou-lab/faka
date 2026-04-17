<?php

namespace app\admin\controller;

use app\admin\model\GiveModel;
use think\Db;
use com\IpLocationqq;

class Give extends Base
{
    /**
     * 充值赠送列表
     */
    public function index()
    {
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('pay_give')->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('pay_give')->page($Nowpage, $limits)->order('paymoney asc')->select();
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 添加充值配置
     */
    public function add_give()
    {
        $give = new GiveModel();
        if (request()->isAjax()) {
            $param = input('post.');
            $flag  = $give->insertGive($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch();
    }

    /**
     * 编辑充值
     */
    public function edit_give()
    {
        $give = new GiveModel();
        if (request()->isAjax()) {
            $param = input('post.');
            $flag  = $give->editGive($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id = input('param.id');
        $this->assign(['give' => $give->getOneGive($id)]);
        return $this->fetch();
    }

    /**
     * 删除充值
     */
    public function del_give()
    {
        $id   = input('param.id');
        $give = new GiveModel();
        $flag = $give->delGive($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }
}