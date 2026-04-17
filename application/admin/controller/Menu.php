<?php

namespace app\admin\controller;

use app\admin\model\MenuModel;
use think\Db;

class Menu extends Base
{
    /**
     * 菜单列表
     */
    public function index()
    {
        $nav        = new \org\Leftnav;
        $menu       = new MenuModel();
        $admin_rule = $menu->getAllMenu();
        $arr        = $nav::rule($admin_rule);
        $this->assign('admin_rule', $arr);
        return $this->fetch();
    }

    /**
     * 添加菜单
     */
    public function add_rule()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $menu  = new MenuModel();
            $flag  = $menu->insertMenu($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch();
    }

    /**
     * 编辑菜单
     */
    public function edit_rule()
    {
        $menu = new MenuModel();
        if (request()->isPost()) {
            $param = input('post.');
            $flag  = $menu->editMenu($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id = input('param.id');
        $this->assign('menu', $menu->getOneMenu($id));
        return $this->fetch();
    }

    /**
     * 删除菜单
     */
    public function del_rule()
    {
        $id   = input('param.id');
        $menu = new MenuModel();
        $flag = $menu->delMenu($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 菜单排序
     */
    public function ruleorder()
    {
        if (request()->isAjax()) {
            $param     = input('post.');
            $auth_rule = Db::name('auth_rule');
            foreach ($param as $id => $sort) {
                $auth_rule->where(array('id' => $id))->setField('sort', $sort);
            }
            return json(['code' => 1, 'msg' => '排序更新成功']);
        }
    }

    /**
     * 菜单状态切换
     */
    public function rule_state()
    {
        $id     = input('param.id');
        $status = Db::name('auth_rule')->where('id', $id)->value('status');
        if ($status == 1) {
            $flag = Db::name('auth_rule')->where('id', $id)->setField(['status' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已禁止']);
        } else {
            $flag = Db::name('auth_rule')->where('id', $id)->setField(['status' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已开启']);
        }
    }
}