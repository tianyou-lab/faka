<?php

namespace app\admin\controller;

use app\admin\model\UserModel;
use app\admin\model\UserType;
use think\Db;

class User extends Base
{
    /**
     * 用户列表
     */
    public function index()
    {
        $key      = input('key');
        $page     = input('get.page') ? input('get.page') : 1;
        $cacheKey = 'admin_user_list_' . md5($key) . '_' . $page . '_' . date('Hi');
        $cachedData = cache($cacheKey);

        if (!$cachedData) {
            $map = [];
            if ($key && $key !== "") {
                $map['username'] = ['like', "%" . $key . "%"];
            }
            $Nowpage = $page;
            $limits  = config('list_rows');
            $count   = Db::name('admin')->where($map)->count();
            $allpage = intval(ceil($count / $limits));
            $user    = new UserModel();
            $lists   = $user->getUsersByWhere($map, $Nowpage, $limits);
            foreach ($lists as $k => $v) {
                $lists[$k]['last_login_time'] = date('Y-m-d H:i:s', $v['last_login_time']);
            }
            $cachedData = [
                'lists'   => $lists,
                'count'   => $count,
                'allpage' => $allpage,
                'Nowpage' => $Nowpage,
                'key'     => $key,
            ];
            cache($cacheKey, $cachedData, 60);
        } else {
            $lists   = $cachedData['lists'];
            $count   = $cachedData['count'];
            $allpage = $cachedData['allpage'];
            $Nowpage = $cachedData['Nowpage'];
            $key     = $cachedData['key'];
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 添加用户
     */
    public function userAdd()
    {
        if (request()->isAjax()) {
            $param             = input('post.');
            $param['password'] = md5(md5($param['password']) . config('auth_key'));
            $user = new UserModel();
            $flag = $user->insertUser($param);
            $accdata = [
                'uid'      => $user['id'],
                'group_id' => $param['groupid'],
            ];
            Db::name('auth_group_access')->insert($accdata);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $role = new UserType();
        $this->assign('role', $role->getRole());
        return $this->fetch();
    }

    /**
     * 编辑用户
     */
    public function userEdit()
    {
        $user = new UserModel();
        if (request()->isAjax()) {
            $param = input('post.');
            if (empty($param['password'])) {
                unset($param['password']);
            } else {
                $param['password'] = md5(md5($param['password']) . config('auth_key'));
            }
            $flag = $user->editUser($param);
            Db::name('auth_group_access')->where('uid', $user['id'])->update(['group_id' => $param['groupid']]);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id   = input('param.id');
        $role = new UserType();
        $this->assign([
            'user' => $user->getOneUser($id),
            'role' => $role->getRole(),
        ]);
        return $this->fetch();
    }

    /**
     * 删除用户
     */
    public function UserDel()
    {
        $id   = input('param.id');
        $role = new UserModel();
        $flag = $role->delUser($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 用户状态切换
     */
    public function user_state()
    {
        $id     = input('param.id');
        $status = Db::name('admin')->where('id', $id)->value('status');
        if ($status == 1) {
            $flag = Db::name('admin')->where('id', $id)->setField(['status' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已禁止']);
        } else {
            $flag = Db::name('admin')->where('id', $id)->setField(['status' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已开启']);
        }
    }
}