<?php

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\admin\model\MemberGroupModel;
use app\admin\model\MemberGroupPriceModel;
use app\admin\model\MemberPriceModel;
use app\admin\model\CateGoryModel;
use think\Db;
use com\IpLocationqq;

class Member extends Base
{
    /**
     * 会员组列表
     */
    public function group()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $map['group_name'] = ['like', "%" . $key . "%"];
        }
        $group   = new MemberGroupModel();
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = $group->getAllCount($map);
        $allpage = intval(ceil($count / $limits));
        $lists   = $group->getAll($map, $Nowpage, $limits);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 会员组自定义价格
     */
    public function groupprice()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $map['think_fl.mnamebie|think_member_group.group_name'] = ['like', "%" . $key . "%"];
        }
        $group   = new MemberGroupPriceModel();
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = $group->getAllCount($map);
        $allpage = intval(ceil($count / $limits));
        $lists   = $group->getAll($map, $Nowpage, $limits);
        $this->assign('count', $count);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch('groupprice');
    }

    /**
     * 添加会员组价格
     */
    public function add_groupprice()
    {
        if (request()->isAjax()) {
            $param  = input('post.');
            $dataFL = Db::name('fl')->where(['id' => $param['goodid']])->find();
            if ($dataFL == false) {
                return json(['code' => -1, 'data' => '', 'msg' => '商品ID出错']);
            }
            if ($dataFL['mprice'] < $param['price'] * 100) {
                return json(['code' => -1, 'data' => '', 'msg' => '不能高于商品默认价格']);
            }
            $param['price'] = $param['price'] * 100;
            $group = new MemberGroupPriceModel();
            $flag  = $group->insertGrouppirce($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $group    = new MemberGroupModel();
        $category = new CateGoryModel();
        $id       = input('param.id');
        $this->assign('group', $group->getGroup());
        $this->assign('category', $category->getOneFl($id));
        $this->assign('id', $id);
        return $this->fetch();
    }

    /**
     * 添加会员组
     */
    public function add_group()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $group = new MemberGroupModel();
            $flag  = $group->insertGroup($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch();
    }

    /**
     * 编辑会员组
     */
    public function edit_group()
    {
        $group = new MemberGroupModel();
        if (request()->isPost()) {
            $param = input('post.');
            $flag  = $group->editGroup($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id = input('param.id');
        $this->assign('group', $group->getOne($id));
        return $this->fetch();
    }

    /**
     * 编辑会员组价格
     */
    public function edit_groupprice()
    {
        if (request()->isPost()) {
            $param     = input('post.');
            $auth_rule = Db::name('member_group_price');
            foreach ($param['price'] as $id => $sort) {
                $result = $auth_rule->where(array('id' => $id))->find();
                if ($result['price'] <> $sort * 100) {
                    Db::execute("update think_child_fl set mprice=mprice+:tempmprice where goodid=:goodid and mprice<>-1 and memberid in(select id from think_member where group_id=:groupid)", ['tempmprice' => $sort * 100 - $result['price'], 'goodid' => $result['goodid'], 'groupid' => $result['membergroupid']]);
                    $auth_rule->where(array('id' => $id))->setField('price', $sort * 100);
                }
            }
            return json(['code' => 1, 'msg' => '更新成功']);
        }
    }

    /**
     * 变动金额
     */
    public function edit_money()
    {
        if (request()->isPost()) {
            $param    = input('post.');
            $superpwd = $param['superpwd'];
            $hasM     = Db::query("select * from think_admin where id=:id", ['id' => session('uid')]);
            if ($hasM[0]['superpassword'] != md5(md5($superpwd) . config('auth_key'))) {
                return json(['code' => '-1', 'data' => '', 'msg' => "超级密码不正确"]);
            }
            if ($param['status'] == 1) {
                $sql = "update think_member set money=money+:money where id=:id";
            } else {
                $sql = "update think_member set money=money-:money where id=:id";
            }
            $updatemoney = Db::execute($sql, ['money' => $param['newmoney'], 'id' => $param['id']]);
            if ($updatemoney === false) {
                return json(['code' => '-1', 'data' => '', 'msg' => "金额变动失败"]);
            } else {
                if ($param['status'] == 1) {
                    writemoneylog($param['id'], "管理员[admin]操作增加", 0, $param['newmoney']);
                } else {
                    writemoneylog($param['id'], "管理员[admin]操作扣除", 1, $param['newmoney']);
                }
                return json(['code' => '1', 'data' => '', 'msg' => "金额变动成功"]);
            }
        }
    }

    /**
     * 变动积分
     */
    public function edit_point()
    {
        if (request()->isPost()) {
            $param = input('post.');
            if ($param['status'] == 1) {
                $sql = "update think_member set integral=integral+:integral where id=:id";
            } else {
                $sql = "update think_member set integral=integral-:integral where id=:id";
            }
            $updatemoney = Db::execute($sql, ['integral' => $param['newpoint'], 'id' => $param['id']]);
            if ($updatemoney === false) {
                return json(['code' => '-1', 'data' => '', 'msg' => "积分变动失败"]);
            } else {
                if ($param['status'] == 1) {
                    writeintegrallog($param['id'], "管理员[admin]操作增加", 0, $param['newpoint']);
                } else {
                    writeintegrallog($param['id'], "管理员[admin]操作扣除", 1, $param['newpoint']);
                }
                return json(['code' => '1', 'data' => '', 'msg' => "积分变动成功"]);
            }
        }
    }

    /**
     * 删除会员组
     */
    public function del_group()
    {
        $id    = input('param.id');
        $group = new MemberGroupModel();
        $flag  = $group->delGroup($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 删除会员组价格
     */
    public function del_groupprice()
    {
        $id    = input('param.id');
        $group = new MemberGroupPriceModel();
        $flag  = $group->delGroup($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 会员组状态切换
     */
    public function group_status()
    {
        $id     = input('param.id');
        $status = Db::name('member_group')->where(array('id' => $id))->value('status');
        if ($status == 1) {
            $flag = Db::name('member_group')->where(array('id' => $id))->setField(['status' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已禁止']);
        } else {
            $flag = Db::name('member_group')->where(array('id' => $id))->setField(['status' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已开启']);
        }
    }

    /**
     * 会员列表
     */
    public function index()
    {
        $cacheKey   = 'member_list_' . md5(serialize(input())) . '_' . date('Hi');
        $cachedData = cache($cacheKey);

        if (!$cachedData) {
            $key      = input('key');
            $group_id = input('group_id');
            if ($group_id != '999' && $group_id !== null) {
                $map['think_member.group_id'] = $group_id;
            }
            $map['closed'] = 0;
            if ($key && $key !== "") {
                $map['account|nickname|mobile'] = ['like', "%" . $key . "%"];
            }

            static $memberGroups = null;
            static $memberStats  = null;
            if ($memberGroups === null) {
                $memberGroups = Db::name("member_group")->column("id,group_name");
            }
            if ($memberStats === null) {
                $memberStats = [
                    'Zmoney'   => Db::name("member")->sum("money"),
                    'Ztgmoney' => Db::name("member")->sum("tg_money"),
                ];
            }

            $arr      = $memberGroups;
            $Zmoney   = $memberStats['Zmoney'];
            $Ztgmoney = $memberStats['Ztgmoney'];
            $member   = new MemberModel();
            $Nowpage  = input('get.page') ? input('get.page') : 1;
            $limits   = config('list_rows');
            $count    = $member->getAllCount($map);
            $allpage  = intval(ceil($count / $limits));
            $lists    = $member->getMemberByWhere($map, $Nowpage, $limits);

            static $ipCache = [];
            $Ip = new IpLocationqq('qqwry.dat');
            $ipsToQuery = [];
            foreach ($lists as $k => $v) {
                $lists[$k]['last_login_time'] = date("Y-m-d H:i:s", $lists[$k]['last_login_time']);
                $userip = $lists[$k]['last_login_ip'];
                if (!empty($userip) && !isset($ipCache[$userip])) {
                    $ipsToQuery[] = $userip;
                }
            }
            foreach ($ipsToQuery as $ip) {
                $ipCache[$ip] = $Ip->getlocation($ip);
            }
            foreach ($lists as $k => $v) {
                $userip = $lists[$k]['last_login_ip'];
                if (!empty($userip)) {
                    $lists[$k]['ipaddr'] = $ipCache[$userip];
                } else {
                    $lists[$k]['ipaddr'] = ['country' => '未知', 'area' => '地区'];
                }
            }

            $cachedData = [
                'lists'    => $lists,
                'count'    => $count,
                'allpage'  => $allpage,
                'arr'      => $arr,
                'Zmoney'   => $Zmoney,
                'Ztgmoney' => $Ztgmoney,
            ];
            cache($cacheKey, $cachedData, 60);
        } else {
            $lists    = $cachedData['lists'];
            $count    = $cachedData['count'];
            $allpage  = $cachedData['allpage'];
            $arr      = $cachedData['arr'];
            $Zmoney   = $cachedData['Zmoney'];
            $Ztgmoney = $cachedData['Ztgmoney'];
            $Nowpage  = input('get.page') ? input('get.page') : 1;
        }

        if ($group_id === null) {
            $group_id = 999;
        }
        $this->assign('count', $count);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign("search_user", $arr);
        $this->assign("group_id", $group_id);
        $this->assign("Zmoney", $Zmoney);
        $this->assign("Ztgmoney", $Ztgmoney);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 会员私密价格
     */
    public function memberprice()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $map['think_member.account|think_fl.mnamebie'] = ['like', "%" . $key . "%"];
        }
        $group   = new MemberPriceModel();
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = $group->getAllCount($map);
        $allpage = intval(ceil($count / $limits));
        $lists   = $group->getAll($map, $Nowpage, $limits);
        $this->assign('count', $count);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch('memberprice');
    }

    /**
     * 添加会员私密价格
     */
    public function add_memberprice()
    {
        if (request()->isAjax()) {
            $param  = input('post.');
            $member = new MemberModel();
            $result = $member->getOneMemberByaccount($param['account']);
            if ($result['id'] <= 0) {
                return json(['code' => '-1', 'data' => '', 'msg' => '会员不存在']);
            }
            $dataFL = Db::name('fl')->where(['id' => $param['goodid']])->find();
            if ($dataFL == false) {
                return json(['code' => -1, 'data' => '', 'msg' => '商品ID出错']);
            }
            if ($dataFL['mprice'] < $param['price'] * 100) {
                return json(['code' => -1, 'data' => '', 'msg' => '不能高于商品默认价格']);
            }
            $param['memberid'] = $result['id'];
            $param['price']    = $param['price'] * 100;
            $group = new MemberPriceModel();
            $flag  = $group->insertGrouppirce($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $group    = new MemberGroupModel();
        $category = new CateGoryModel();
        $id       = input('param.id');
        $this->assign('group', $group->getGroup());
        $this->assign('category', $category->getOneFl($id));
        $this->assign('id', $id);
        return $this->fetch();
    }

    /**
     * 添加会员
     */
    public function add_member()
    {
        if (request()->isAjax()) {
            $param             = input('post.');
            $param['password'] = md5(md5($param['password']) . config('auth_key'));
            $member = new MemberModel();
            $flag   = $member->insertMember($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $group = new MemberGroupModel();
        $this->assign('group', $group->getGroup());
        return $this->fetch();
    }

    /**
     * 编辑会员
     */
    public function edit_member()
    {
        $member = new MemberModel();
        if (request()->isAjax()) {
            $param = input('post.');
            if (empty($param['password'])) {
                unset($param['password']);
            } else {
                $param['password'] = md5(md5($param['password']) . config('auth_key'));
            }
            $flag = $member->editMember($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id    = input('param.id');
        $group = new MemberGroupModel();
        $this->assign([
            'member' => $member->getOneMember($id),
            'group'  => $group->getGroup(),
        ]);
        return $this->fetch();
    }

    /**
     * 进入会员后台
     */
    public function login_member()
    {
        $param   = inputself();
        $id      = $param['id'];
        $hasUser = Db::name('member')->where('id', $id)->find();
        if ($hasUser) {
            $token = md5(md5($hasUser['account'] . $hasUser['password']) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
            session('useraccount', $hasUser);
            cookie('usertoken', $token);
            return $this->redirect(url('@jingdian/user/uscenter'), 302);
        }
        return $this->fetch();
    }

    /**
     * 编辑会员私密价格
     */
    public function edit_memberprice()
    {
        if (request()->isPost()) {
            $param     = input('post.');
            $auth_rule = Db::name('member_price');
            foreach ($param['price'] as $id => $sort) {
                $auth_rule->where(array('id' => $id))->setField('price', $sort * 100);
            }
            return json(['code' => 1, 'msg' => '更新成功']);
        }
    }

    /**
     * 删除会员
     */
    public function del_member()
    {
        $id       = input('param.id');
        $member   = new MemberModel();
        $oneMeber = $member->getOneMember($id);
        if ($oneMeber['money'] > 0 || $oneMeber['tg_money'] > 0) {
            return json(['code' => -1, 'data' => '', 'msg' => "该用户不能被删除。（有可用余额或者可用佣金）"]);
        }
        $map['pid1|pid2|pid3'] = $id;
        $result = Db::name('member')->where($map)->count();
        if ($result > 0) {
            return json(['code' => -1, 'data' => '', 'msg' => "该用户不能被删除。（有下级用户）"]);
        }
        $flag = $member->delMember($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 删除会员私密价格
     */
    public function del_memberprice()
    {
        $id    = input('param.id');
        $group = new MemberPriceModel();
        $flag  = $group->delGroup($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 会员状态切换
     */
    public function member_status()
    {
        $id     = input('param.id');
        $status = Db::name('member')->where('id', $id)->value('status');
        if ($status == 1) {
            $flag = Db::name('member')->where('id', $id)->setField(['status' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已禁止']);
        } else {
            $flag = Db::name('member')->where('id', $id)->setField(['status' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已开启']);
        }
    }

    /**
     * 分销商状态切换
     */
    public function member_distribut()
    {
        $id       = input('param.id');
        $distribut = Db::name('member')->where('id', $id)->value('is_distribut');
        if ($distribut == 1) {
            $map['pid1|pid2|pid3'] = $id;
            $result = Db::name('member')->where($map)->count();
            if ($result > 0) {
                return json(['code' => -1, 'data' => '', 'msg' => "不能取消该用户的分销商身份。（有下级用户）"]);
            }
            $flag = Db::name('member')->where('id', $id)->setField(['is_distribut' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已取消分销商']);
        } else {
            $flag = Db::name('member')->where('id', $id)->setField(['is_distribut' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已设置成分销商']);
        }
    }

    /**
     * 重置会员密码
     */
    public function edit_pass()
    {
        $id     = input('param.id');
        $parama = mt_rand(100000, 999999);
        $pass   = md5(md5($parama) . config('auth_key'));
        $flag   = Db::name('member')->where('id', $id)->setField(['password' => $pass]);
        if ($flag == false) {
            return json(['code' => -1, 'data' => '', 'msg' => '修改失败']);
        } else {
            return json(['code' => 1, 'data' => $parama, 'msg' => '密码重置成功请牢记']);
        }
    }

}