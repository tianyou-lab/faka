<?php

namespace app\admin\controller;

use app\api\model\ApimailModel;
use app\admin\model\CateGoryGroupModel;
use think\Db;

class Kami extends Base
{
    /**
     * 卡密列表
     */
    public function index()
    {
        $key      = input('key');
        $goodsid  = input('id');
        $map      = [];
        $code     = -1;
        $msg      = "获取失败";
        $count    = 0;
        $Nowpage  = 1;
        $allpage  = 0;
        $page     = isset($param['page']) ? $param['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        if ($goodsid != 0 && !empty($goodsid)) {
            $map['think_mail.mpid'] = $goodsid;
        }
        if ($key && $key !== "") {
            $map['think_mail.musernm'] = ['like', "%" . $key . "%"];
        }
        $limitlast = config('list_rows');
        $limit     = ($page - 1) * $limitlast;
        $ApimailM  = new ApimailModel();
        $count     = $ApimailM->getAllCount($map);
        $allpage   = intval(ceil($count / $limitlast));
        $Nowpage   = input('get.page') ? input('get.page') : 1;
        $result    = $ApimailM->getmailBywhere($map, $Nowpage, $limitlast);
        $goodsdetail = Db::name('fl')->where('id', $goodsid)->find();
        if (input('get.page')) {
            return $result;
        }
        $this->assign('goodsname', $goodsdetail['mnamebie']);
        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        $this->assign('count', $count);
        $this->assign('goodsid', $goodsid);
        return $this->fetch();
    }

    /**
     * 删除卡密
     */
    public function del_kami()
    {
        $id       = input('param.id');
        $ApimailM = new ApimailModel();
        $flag     = $ApimailM->delKami($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 添加卡密
     */
    public function add_kami()
    {
        $goodid     = input('param.id');
        $shopdetail = Db::name('fl')->where('id', $goodid)->find();
        $this->assign('uid', session('uid'));
        $this->assign('shopdetail', $shopdetail);
        return $this->fetch();
    }

    /**
     * 删除所有卡密
     */
    public function del_all()
    {
        $param  = inputself();
        $mpid   = $param['mpid'];
        $result = Db::name('mail')->where(['mpid' => $mpid])->delete();
        if ($result === false) {
            return json(['code' => -1, 'count' => '', 'msg' => '删除失败']);
        } else {
            return json(['code' => 1, 'count' => $result, 'msg' => '删除成功' . $result . '条数据']);
        }
    }

    /**
     * 删除已使用卡密
     */
    public function del_guoqi()
    {
        $param  = inputself();
        $mpid   = $param['mpid'];
        $result = Db::name('mail')->where(['mis_use' => 1])->delete();
        if ($result === false) {
            return json(['code' => -1, 'count' => '', 'msg' => '清理失败']);
        } else {
            return json(['code' => 1, 'count' => $result, 'msg' => '删除成功' . $result . '条数据']);
        }
    }

    /**
     * 导出卡密
     */
    public function export_kami()
    {
        $param = inputself();
        $data  = [];
        $param['create_time'] = time();
        $param['userip']      = getIP();
        $param['mflid']       = isset($param['mflid']) ? $param['mflid'] : '';
        $param['buynum']      = isset($param['buynum']) ? $param['buynum'] : '';
        if ($param['mflid'] == '' || $param['buynum'] == '') {
            return $this->returnJson(-1, '不能为空', $data);
        }
        $dataFl = Db::name('fl')->where('id', $param['mflid'])->find();
        if (!$dataFl) {
            return $this->returnJson(-1, '商品异常', $data);
        }
        $decrypt = $dataFl['decrypt'];
        $code    = 1;
        $msg     = "获取成功";

        Db::startTrans();
        try {
            $orderid = createOrder();
            $sql  = "insert ignore into think_info set mcard=:mcard,morder=:morder,mstatus=1,create_time=:create_time,update_time=:update_time,userip=:userip,mflid=:mflid,buynum=:buynum,maddtype=88";
            $bool = Db::execute($sql, [
                'mcard'       => $orderid . '_' . $param['buynum'],
                'morder'      => $orderid . '_' . $param['buynum'],
                'create_time' => $param['create_time'],
                'update_time' => 0,
                'userip'      => $param['userip'],
                'mflid'       => $param['mflid'],
                'buynum'      => $param['buynum'],
            ]);
            if ($bool == false) {
                Db::rollback();
                return $this->returnJson(-1, '添加订单状态失败', $data);
            }

            $mailsql  = "SELECT * from think_mail where mpid=:mpid and mis_use=0 ORDER BY id asc LIMIT :beishu for update";
            $maildata = Db::query($mailsql, ['mpid' => $param['mflid'], 'beishu' => $param['buynum']]);
            if ($maildata == false) {
                Db::rollback();
                return $this->returnJson(-1, '提取卡密出错001', $data);
            }

            $file = "";
            $html = "";
            if ($decrypt == 0) {
                foreach ($maildata as $v) {
                    $mails[] = $v['musernm'];
                    $ids[]   = $v['id'];
                    $file   .= $v['musernm'] . "\r";
                    $html   .= $v['musernm'] . '<br />';
                    $data[]  = $v['musernm'];
                }
            } elseif ($decrypt == 1) {
                foreach ($maildata as $v) {
                    $decrypted = passport_decrypt($v['mpasswd'], DECRYPT_KEY);
                    $mails[]   = $decrypted;
                    $ids[]     = $v['id'];
                    $file     .= $decrypted . "\r";
                    $html     .= $decrypted . '<br/>';
                    $data[]    = $decrypted;
                }
            }

            $arr_string = join(',', $ids);
            $sql        = "update think_mail set mis_use=1,update_time=:update_time,syddhao=:orderid where id in(" . $arr_string . ")";
            $updatemail = Db::execute($sql, ['update_time' => time(), 'orderid' => $orderid]);
            if ($updatemail == false) {
                Db::rollback();
                return $this->returnJson(-1, '更新卡密状态出错001', $data);
            }
        } catch (\Exception $e) {
            Db::rollback();
            return $this->returnJson(-1, $e->getMessage(), $data);
        }
        Db::commit();

        $sor = fopen('upload/' . $orderid . '_' . $param['buynum'] . '.txt', "w");
        fwrite($sor, $file);
        fclose($sor);
        $this->redirect(url('kami/Downloadurl', ['orderid' => $orderid, 'buynum' => $param['buynum']]));
    }

    /**
     * 下载卡密文件
     */
    public function Downloadurl()
    {
        $param = inputself();
        $this->picDownload($param['orderid'] . '_' . $param['buynum'] . '.txt');
    }

    /**
     * 打包下载文件
     */
    private function picDownload($orderid)
    {
        $filename = "./upload/" . $orderid . ".zip";
        if (!file_exists($filename)) {
            $zip = new \ZipArchive();
            if ($zip->open($filename, \ZipArchive::OVERWRITE) !== true) {
                if ($zip->open($filename, \ZipArchive::CREATE) !== true) {
                    exit('无法打开文件，或者文件创建失败');
                }
            }
            $urlfile = $_SERVER['DOCUMENT_ROOT'] . "/upload/" . $orderid;
            if (file_exists($urlfile)) {
                $zip->addFile($urlfile, iconv('utf-8', 'gb2312', $orderid));
            } else {
                echo 'error';
                @unlink($filename);
                exit;
            }
            $zip->close();
        }
        header("Cache-Control: max-age=0");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . basename($filename));
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: binary");
        header('Content-Length: ' . filesize($filename));
        @readfile($filename);
        @unlink($filename);
        exit;
    }

    private function returnJson($code, $msg, $data)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }
}