<?php

namespace app\admin\controller;

use app\admin\model\AttachModel;
use app\admin\model\AttachGroupModel;
use think\Db;

class Attach extends Base
{
    /**
     * 附加选项分类列表
     */
    public function index()
    {
        $key = input('key');
        $map = [];
        if ($key && $key !== "") {
            $map['name'] = ['like', "%" . $key . "%"];
        }
        $attach_group = new AttachGroupModel();
        $Nowpage = input('get.page') ? input('get.page') : 1;
        $limits  = config('list_rows');
        $count   = $attach_group->getAllCount($map);
        $allpage = intval(ceil($count / $limits));
        $lists   = $attach_group->getAll($map, $Nowpage, $limits);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        $this->assign('count', $count);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch("group");
    }

    /**
     * 删除附加选项组
     */
    public function del_group()
    {
        $id     = input('param.id');
        $group  = new AttachGroupModel();
        $flag   = $group->delGroup($id);
        $attach = new AttachModel();
        $attach->delAttachByAttachId($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 附加选项组状态切换
     */
    public function group_status()
    {
        $id     = input('param.id');
        $status = Db::name('attach_group')->where(['id' => $id])->value('status');
        if ($status == 1) {
            $flag = Db::name('attach_group')->where(['id' => $id])->setField(['status' => 0]);
            return json(['code' => 1, 'data' => $flag['data'], 'msg' => '已禁止']);
        } else {
            $flag = Db::name('attach_group')->where(['id' => $id])->setField(['status' => 1]);
            return json(['code' => 0, 'data' => $flag['data'], 'msg' => '已开启']);
        }
    }

    /**
     * 添加附加选项
     */
    public function add_group()
    {
        if (request()->isAjax()) {
            $param        = input('post.');
            $attach_group = new AttachGroupModel();
            $attach       = new AttachModel();
            $flag         = $attach_group->insertGroup($param);
            if (!empty($param['title'])) {
                $id    = $flag['id'];
                $title = $param['title'];
                $tip   = $param['tip'];
                foreach ($title as $k => $v) {
                    $data1 = [
                        'title'         => $v,
                        'tip'           => $tip[$k],
                        'attachgroupid' => $id,
                    ];
                    $flag = $attach->insertAttach($data1);
                }
            }
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch();
    }

    /**
     * 编辑附加选项
     */
    public function edit_group()
    {
        $attach       = new AttachModel();
        $attach_group = new AttachGroupModel();
        if (request()->isAjax()) {
            $param = input('post.');
            $flag  = $attach_group->editGroup($param);
            if (!empty($param['attach'])) {
                foreach ($param['attach'] as $k => $v) {
                    $data = [
                        'id'    => $v['id'],
                        'title' => $v['title'],
                        'tip'   => $v['tip'],
                    ];
                    $flag = $attach->editAttach($data);
                }
            }
            if (!empty($param['title1'])) {
                $id     = $param['id'];
                $title1 = $param['title1'];
                $tip1   = $param['tip1'];
                foreach ($title1 as $k => $v) {
                    $data1 = [
                        'title'         => $v,
                        'tip'           => $tip1[$k],
                        'attachgroupid' => $id,
                    ];
                    $flag = $attach->insertAttach($data1);
                }
            }
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $id = input('param.id');
        $this->assign([
            'group'  => $attach_group->getOne($id),
            'attach' => $attach->getOneAttach($id),
        ]);
        return $this->fetch();
    }
}