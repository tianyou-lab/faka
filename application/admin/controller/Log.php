<?php

namespace app\admin\controller;

use app\admin\model\LogModel;
use think\Db;
use com\IpLocationqq;

class Log extends Base
{
    /**
     * 操作日志
     */
    public function operate_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $map['admin_id'] = $key;
        }
        $arr     = Db::name("admin")->column("id,username");
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('log')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('log')->where($map)->page($Nowpage, $limits)->order('add_time desc')->select();

        static $ipCache = [];
        $Ip         = new IpLocationqq('qqwry.dat');
        $ipsToQuery = [];
        foreach ($lists as $k => $v) {
            $lists[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $ip = $lists[$k]['ip'];
            if (!empty($ip) && !isset($ipCache[$ip])) {
                $ipsToQuery[] = $ip;
            }
        }
        foreach ($ipsToQuery as $ip) {
            $ipCache[$ip] = $Ip->getlocation($ip);
        }
        foreach ($lists as $k => $v) {
            $ip = $lists[$k]['ip'];
            if (!empty($ip)) {
                $lists[$k]['ipaddr'] = $ipCache[$ip];
            } else {
                $lists[$k]['ipaddr'] = ['country' => '未知', 'area' => '地区'];
            }
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign("search_user", $arr);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 删除日志
     */
    public function del_log()
    {
        $id   = input('param.id');
        $log  = new LogModel();
        $flag = $log->delLog($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 充值日志
     */
    public function member_payorder_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            $map['memberid'] = $hasUser ? $hasUser['id'] : "0";
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('member_payorder')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('member_payorder')
            ->field("think_member_payorder.*,IFNULL(think_member.account,'未知') as account")
            ->join('think_member', 'think_member_payorder.memberid=think_member.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('think_member_payorder.create_time desc')->select();
        $Ip = new IpLocationqq('qqwry.dat');
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $lists[$k]['ipaddr']      = $Ip->getlocation($lists[$k]['ip']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 删除充值日志
     */
    public function del_member_payorder_log()
    {
        $id     = input('param.id');
        $result = Db::name('member_payorder')->where('id', $id)->delete();
        if ($result == false) {
            return json(['code' => -1, 'data' => '', 'msg' => '删除失败']);
        } else {
            return json(['code' => 1, 'data' => '', 'msg' => '删除成功']);
        }
    }

    /**
     * 财务日志
     */
    public function member_money_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            $map['memberid'] = $hasUser ? $hasUser['id'] : "0";
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('member_money_log')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('member_money_log')
            ->field("think_member_money_log.*,IFNULL(think_member.account,'未知') as account")
            ->join('think_member', 'think_member_money_log.memberid=think_member.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('think_member_money_log.create_time desc')->select();
        $Ip = new IpLocationqq('qqwry.dat');
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $lists[$k]['ipaddr']      = $Ip->getlocation($lists[$k]['ip']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 删除财务日志
     */
    public function del_member_money_log()
    {
        $id     = input('param.id');
        $result = Db::name('member_money_log')->where('id', $id)->delete();
        if ($result == false) {
            return json(['code' => -1, 'data' => '', 'msg' => '删除失败']);
        } else {
            return json(['code' => 1, 'data' => '', 'msg' => '删除成功']);
        }
    }

    /**
     * 积分日志
     */
    public function member_integral_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            $map['memberid'] = $hasUser ? $hasUser['id'] : "0";
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('member_integral_log')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('member_integral_log')
            ->field("think_member_integral_log.*,IFNULL(think_member.account,'未知') as account")
            ->join('think_member', 'think_member_integral_log.memberid=think_member.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('think_member_integral_log.create_time desc')->select();
        $Ip = new IpLocationqq('qqwry.dat');
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $lists[$k]['ipaddr']      = $Ip->getlocation($lists[$k]['ip']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 佣金日志
     */
    public function member_tgmoney_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            if ($hasUser == false) {
                $map['orderno'] = $key;
            } else {
                $map['memberid'] = $hasUser['id'];
            }
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('tgmoney_log')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('tgmoney_log')
            ->alias('a')
            ->field("a.*,IFNULL(b.account,'未知') as childaccount,IFNULL(c.account,'非会员') as buyaccount,IFNULL(d.account,'未知') as memberaccount")
            ->join('think_member b', 'a.childid = b.id', 'LEFT')
            ->join('think_member c', 'a.buyid = c.id', 'LEFT')
            ->join('think_member d', 'a.memberid = d.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('a.create_time desc')->select();
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 登录日志
     */
    public function member_login_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            $map['memberid'] = $hasUser ? $hasUser['id'] : "0";
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('member_login_log')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('member_login_log')
            ->field("think_member_login_log.*,IFNULL(think_member.account,'未知') as account")
            ->join('think_member', 'think_member_login_log.memberid=think_member.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('think_member_login_log.create_time desc')->select();
        $Ip = new IpLocationqq('qqwry.dat');
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $lists[$k]['ipaddr']      = $Ip->getlocation($lists[$k]['ip']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 删除积分日志
     */
    public function del_member_integral_log()
    {
        $id     = input('param.id');
        $result = Db::name('member_integral_log')->where('id', $id)->delete();
        if ($result == false) {
            return json(['code' => -1, 'data' => '', 'msg' => '删除失败']);
        } else {
            return json(['code' => 1, 'data' => '', 'msg' => '删除成功']);
        }
    }

    /**
     * 提现记录
     */
    public function member_tixian_log()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $hasUser = Db::name('member')->where('account', $key)->find();
            if ($hasUser) {
                $map['memberid'] = $hasUser['id'];
            }
        }
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = Db::name('member_tixian')->where($map)->count();
        $allpage = intval(ceil($count / $limits));
        $lists   = Db::name('member_tixian')
            ->field("think_member_tixian.*,IFNULL(think_member.account,'未知') as account,IFNULL(think_member.alipayname,'未知') as alipayname,IFNULL(think_member.alipayno,'未知') as alipayno,IFNULL(think_member.fzhost,'未知') as child_host")
            ->join('think_member', 'think_member_tixian.memberid=think_member.id', 'LEFT')
            ->where($map)->page($Nowpage, $limits)->order('think_member_tixian.create_time desc')->select();
        $Ip = new IpLocationqq('qqwry.dat');
        foreach ($lists as $k => $v) {
            $lists[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $lists[$k]['ipaddr']      = $Ip->getlocation($lists[$k]['userip']);
        }
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('count', $count);
        $this->assign('val', $key);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 提现处理
     */
    public function edit_tixian()
    {
        if (request()->isPost()) {
            $param  = input('post.');
            $tixian = Db::name('member_tixian')->where('id', $param['accountid'])->find();
            $sql    = "update think_member_tixian set status=:status,make=:make where id=:id and status=0";
            if ($param['status'] == 2) {
                Db::startTrans();
                try {
                    $result = Db::execute('update think_member set tg_money=tg_money+:chehuimoney where id=:id', ['chehuimoney' => $tixian['money'], 'id' => $param['memberid']]);
                    if ($result == false) {
                        Db::rollback();
                        return json(['code' => -1, 'url' => '', 'msg' => "撤回失败，更新用户余额出错"]);
                    }
                    $updatemoney = Db::execute($sql, ['status' => $param['status'], 'id' => $param['accountid'], 'make' => $param['make']]);
                    if ($updatemoney == false) {
                        Db::rollback();
                        return json(['code' => -1, 'url' => '', 'msg' => '更新撤回日志失败']);
                    }
                } catch (\Exception $e) {
                    Db::rollback();
                    return json(['code' => -1, 'url' => '', 'msg' => $e->getMessage()]);
                }
                Db::commit();
                writemoneylog($param['memberid'], "提现撤回：" . $tixian['orderno'], 0, $tixian['money'], getIP());
                $hasUser = Db::name('member')->where('id', $param['memberid'])->find();
                $msgStatus['Code'] = "短信未发送";
                if ($hasUser) {
                    $mobile = $hasUser['mobile'];
                    if (!empty($mobile)) {
                        $tplCode        = config('alimoban_tixianchehui');
                        $make           = str_replace("【", "", $param['make']);
                        $make           = str_replace("】", "", $make);
                        $param['make']  = mb_substr($make, 0, 20, 'utf-8');
                        $msgStatus      = sendMsg($mobile, $tplCode, $param);
                    }
                }
                return json(['code' => '1', 'data' => '', 'msg' => "撤销成功" . $msgStatus['Code']]);
            }

            $updatemoney = Db::execute($sql, ['status' => $param['status'], 'id' => $param['accountid'], 'make' => $param['make']]);
            if ($updatemoney == false) {
                return json(['code' => '-1', 'data' => '', 'msg' => "提现失败"]);
            } else {
                $hasUser = Db::name('member')->where('id', $param['memberid'])->find();
                if ($hasUser) {
                    $mobile                    = $hasUser['mobile'];
                    $tplCode                   = config('alimoban_tixiandaozhang');
                    $paramTixian['username']    = $hasUser['account'];
                    $paramTixian['money']       = $param['money'];
                    $msgStatus                 = sendMsg($mobile, $tplCode, $paramTixian);
                }
                writeamounttotal($tixian['memberid'], $tixian['paymoney'], 'txmoney');
                return json(['code' => '1', 'data' => '', 'msg' => "提现成功" . $msgStatus['Code']]);
            }
        }
    }
}