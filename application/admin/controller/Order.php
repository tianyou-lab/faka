<?php

namespace app\admin\controller;

use app\admin\model\OrderModel;
use think\Db;
use think\Session;

class Order extends Base
{
    /**
     * 订单列表
     */
    public function index()
    {
        $inputParams = [
            'key'     => input('key'),
            'mstatus' => input('mstatus'),
            'type'    => input('get.type') ? input('get.type') : 999,
            'page'    => input('get.page') ? input('get.page') : 1,
        ];

        $cacheKey   = 'order_list_' . md5(serialize($inputParams)) . '_' . date('Hi');
        $cachedData = cache($cacheKey);

        if (!$cachedData || input('get.page')) {
            $key     = $inputParams['key'];
            $mstatus = $inputParams['mstatus'];
            $type    = $inputParams['type'];

            $map = [];
            if ($key && $key !== "") {
                $map['mcard|morder|lianxi|think_info.email|a.account'] = ['like', "%" . $key . "%"];
            }
            if ($mstatus !== null && $mstatus !== "") {
                $map['mstatus'] = $mstatus;
            }
            if ($type == "888") {
                $reltype = "0";
            }
            if ($type == "1") {
                $reltype = "1";
            }
            if ($type !== "999" && $type !== "") {
                $map['think_fl.type'] = $reltype;
            }

            $OrderM  = new OrderModel();
            $Nowpage = $inputParams['page'];
            $limits  = config('list_rows');
            $count   = $OrderM->getAllCount($map);
            $allpage = intval(ceil($count / $limits));
            $lists   = $OrderM->getOrderByWhere($map, $Nowpage, $limits);

            $cachedData = [
                'lists'   => $lists,
                'count'   => $count,
                'allpage' => $allpage,
                'key'     => $key,
                'mstatus' => $mstatus,
                'type'    => $type,
            ];

            if (!input('get.page')) {
                cache($cacheKey, $cachedData, 60);
            }
        } else {
            $lists   = $cachedData['lists'];
            $count   = $cachedData['count'];
            $allpage = $cachedData['allpage'];
            $key     = $cachedData['key'];
            $mstatus = $cachedData['mstatus'];
            $type    = $cachedData['type'];
            $Nowpage = $inputParams['page'];
        }

        $this->assign('Nowpage', $Nowpage);
        $this->assign('allpage', $allpage);
        $this->assign('val', $key);
        $this->assign('mstatus', $mstatus);
        Session::set('count', $count);
        $this->assign('count', $count);
        $this->assign('type', $type);
        if (input('get.page')) {
            return json($lists);
        }
        return $this->fetch();
    }

    /**
     * 订单附加信息详情
     */
    public function getOrderAttach()
    {
        $number      = trim(input('param.number'));
        $OrderM      = new OrderModel();
        $orderattach = $OrderM->getOrderAttach($number);
        return json($orderattach);
    }

    /**
     * 修改订单信息
     */
    public function UpdateOrder()
    {
        $param  = inputself();
        $OrderM = new OrderModel();
        $result = $OrderM->editOrder($param);
        return json($result);
    }

    /**
     * 删除订单
     */
    public function del_order()
    {
        $id     = input('param.id');
        $OrderM = new OrderModel();
        $flag   = $OrderM->del_order($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * CSV批量导出
     */
    public function export()
    {
        $head     = ['订单号', '商品名称', '状态', '类型', '电话', '邮箱', '附加信息', '添加时间'];
        $data     = Session::get('alllists');
        $count    = Session::get('count');
        $mark     = date('YmdHis');
        set_time_limit(0);
        $sqlCount = $count;
        $sqlLimit = 100000;
        $limit    = 100000;
        $cnt      = 0;
        $fileNameArr = array();

        for ($i = 0; $i < ceil($sqlCount / $sqlLimit); $i++) {
            $fp = fopen($mark . '_' . $i . '.csv', 'w');
            $fileNameArr[] = $mark . '_' . $i . '.csv';
            fputcsv($fp, $head);
            $dataArr = $data;
            foreach ($dataArr as $a) {
                $cnt++;
                if ($limit == $cnt) {
                    ob_flush();
                    flush();
                    $cnt = 0;
                }
                fputcsv($fp, $a);
            }
            fclose($fp);
        }

        $zip      = new \ZipArchive();
        $filename = $mark . ".zip";
        $zip->open($filename, \ZipArchive::CREATE);
        foreach ($fileNameArr as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
        foreach ($fileNameArr as $file) {
            unlink($file);
        }

        header("Cache-Control: max-age=0");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . basename($filename));
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: binary");
        header('Content-Length: ' . filesize($filename));
        @readfile($filename);
        @unlink($filename);
    }
}