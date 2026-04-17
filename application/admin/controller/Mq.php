<?php

namespace app\admin\controller;

use app\jingdian\model\OrderModel;
use app\jingdian\model\UserModel;
use think\Db;
use app\service\QrcodeServer;
use Zxing\QrReader;

class Mq extends Base
{
    public function Setting()
    {
        return $this->fetch();
    }

    public function qrcodelist()
    {
        return $this->fetch();
    }

    public function addqrcode()
    {
        return $this->fetch();
    }

    public function jk()
    {
        return $this->fetch();
    }

    public function orderlist()
    {
        return $this->fetch();
    }

    private function getReturn($code = 1, $msg = "成功", $data = null)
    {
        return ['code' => $code, 'msg' => $msg, 'data' => $data];
    }

    /**
     * 支付统计概览
     */
    public function getMain()
    {
        $today       = strtotime(date("Y-m-d"), time());
        $month_start = strtotime(date("Y-m-01"));
        $month_end   = strtotime("+1 month -1 seconds", $month_start);
        $shang_start = strtotime("-1 month", $month_start);
        $shang_end   = strtotime("+1 month -1 seconds", $shang_start);

        $todayOrder = Db::name("pay_order")
            ->where("create_date >=" . $today)
            ->where("create_date <=" . ($today + 86400))
            ->count();
        $todaySuccessOrder = Db::name("pay_order")
            ->where("state >=1")
            ->where("create_date >=" . $today)
            ->where("create_date <=" . ($today + 86400))
            ->count();
        $todayCloseOrder = Db::name("pay_order")
            ->where("state", -1)
            ->where("create_date >=" . $today)
            ->where("create_date <=" . ($today + 86400))
            ->count();
        $todayMoney = Db::name("pay_order")
            ->where("state >=1")
            ->where("create_date >=" . $today)
            ->where("create_date <=" . ($today + 86400))
            ->sum("price");
        $countOrder = Db::name("pay_order")->count();
        $countMoney = Db::name("pay_order")->where("state >=1")->sum("price");

        $benalipay = Db::name("pay_order")
            ->where("state >=1")->where("type =1")
            ->where("create_date >=" . $month_start)->where("create_date <=" . $month_end)
            ->sum("price");
        $shangalipay = Db::name("pay_order")
            ->where("state >=1")->where("type =1")
            ->where("create_date >=" . $shang_start)->where("create_date <=" . $shang_end)
            ->sum("price");
        $benweixin = Db::name("pay_order")
            ->where("state >=1")->where("type =3")
            ->where("create_date >=" . $month_start)->where("create_date <=" . $month_end)
            ->sum("price");
        $shangweixin = Db::name("pay_order")
            ->where("state >=1")->where("type =3")
            ->where("create_date >=" . $shang_start)->where("create_date <=" . $shang_end)
            ->sum("price");
        $benjuhe = Db::name("pay_order")
            ->where("state >=1")->where("type =2")
            ->where("create_date >=" . $month_start)->where("create_date <=" . $month_end)
            ->sum("price");
        $shangjuhe = Db::name("pay_order")
            ->where("state >=1")->where("type =2")
            ->where("create_date >=" . $shang_start)->where("create_date <=" . $shang_end)
            ->sum("price");

        return json($this->getReturn(1, "成功", [
            "todayOrder"        => $todayOrder,
            "todaySuccessOrder" => $todaySuccessOrder,
            "todayCloseOrder"   => $todayCloseOrder,
            "benalipay"         => $benalipay,
            "shangalipay"       => $shangalipay,
            "benweixin"         => $benweixin,
            "shangweixin"       => $shangweixin,
            "benjuhe"           => $benjuhe,
            "shangjuhe"         => $shangjuhe,
            "todayMoney"        => round($todayMoney, 2),
            "countOrder"        => $countOrder,
            "countMoney"        => round($countMoney),
        ]));
    }

    /**
     * 获取支付设置
     */
    public function getSettings()
    {
        $keys = ['notifyUrl', 'returnUrl', 'key', 'lastheart', 'lastpay', 'jkstate', 'close', 'payQf', 'wxpay', 'jhpay', 'alipay'];
        $data = [];
        foreach ($keys as $k) {
            $row    = Db::name("setting")->where("key", $k)->find();
            $data[$k] = $row['value'];
        }
        return json($this->getReturn(1, "成功", $data));
    }

    /**
     * 保存支付设置
     */
    public function saveSetting()
    {
        Db::name("setting")->where("key", "notifyUrl")->update(["value" => input("notifyUrl")]);
        Db::name("setting")->where("key", "returnUrl")->update(["value" => input("returnUrl")]);
        Db::name("setting")->where("key", "key")->update(["value" => input("key")]);
        Db::name("setting")->where("key", "close")->update(["value" => input("close")]);
        Db::name("setting")->where("key", "payQf")->update(["value" => input("payQf")]);
        Db::name("setting")->where("key", "wxpay")->update(["value" => urldecode(input("wxpay"))]);
        Db::name("setting")->where("key", "jhpay")->update(["value" => urldecode(input("jhpay"))]);
        Db::name("setting")->where("key", "alipay")->update(["value" => urldecode(input("alipay"))]);
        return json($this->getReturn());
    }

    /**
     * 添加收款二维码
     */
    public function addPayQrcode()
    {
        Db::name("pay_qrcode")->insert([
            "type"    => input("type"),
            "pay_url" => urldecode(input("pay_url")),
            "price"   => input("price"),
        ]);
        return json($this->getReturn());
    }

    /**
     * 获取收款二维码列表
     */
    public function getPayQrcodes()
    {
        $page = input("page");
        $size = input("limit");
        $obj  = Db::name('pay_qrcode')->page($page, $size);
        if (input("type")) {
            $obj = $obj->where("type", input("type"));
        }
        $array = $obj->order("id", "desc")->select();
        return json([
            "code"  => 0,
            "msg"   => "获取成功",
            "data"  => $array,
            "count" => $obj->count(),
        ]);
    }

    /**
     * 删除收款二维码
     */
    public function delPayQrcode()
    {
        Db::name("pay_qrcode")->where("id", input("id"))->delete();
        return json($this->getReturn());
    }

    /**
     * 获取订单列表
     */
    public function getOrders()
    {
        $page = input("page");
        $size = input("limit");
        $obj  = Db::name('pay_order')->page($page, $size);
        if (input("type")) {
            $obj = $obj->where("type", input("type"));
        }
        if (input("state")) {
            $obj = $obj->where("state", input("state"));
        }
        $array = $obj->order("id", "desc")->select();
        return json([
            "code"  => 0,
            "msg"   => "获取成功",
            "data"  => $array,
            "count" => $obj->count(),
        ]);
    }

    /**
     * 删除订单
     */
    public function delOrder()
    {
        $res = Db::name("pay_order")->where("id", input("id"))->find();
        Db::name("pay_order")->where("id", input("id"))->delete();
        if ($res['state'] == 0) {
            Db::name("tmp_price")->where("oid", $res['order_id'])->delete();
        }
        return json($this->getReturn());
    }

    /**
     * 补单处理
     */
    public function setBd()
    {
        $res = Db::name("pay_order")->where("id", input("id"))->find();
        if ($res) {
            $OrderM      = new OrderModel();
            $UserM       = new UserModel();
            $Orderresult = $OrderM->getOrder($res['pay_id']);
            $mapx['orderno|outorderno'] = $res['pay_id'];
            $userresult = Db::name('member_payorder')->where($mapx)->find();
            if (!empty($userresult['memberid'])) {
                $data = [
                    'memberid' => $userresult['memberid'],
                    'money'    => $res['price'],
                    'r2'       => $res['pay_id'],
                    'r6'       => $res['pay_id'],
                    'userip'   => $userresult['ip'],
                ];
                $order = $UserM->payMember($res['pay_id'], $data);
            } else {
                $data = [
                    'mstatus'     => 0,
                    'update_time' => time(),
                    'mcard'       => $res['pay_id'],
                    'morder'      => $res['order_id'],
                    'mamount'     => (float)$res['price'],
                ];
                $order = $OrderM->updateOrderStatus($res['pay_id'], $data);
            }
            if ($res['state'] == 0) {
                Db::name("tmp_price")->where("oid", $res['order_id'])->delete();
            }
            Db::name("pay_order")->where("id", $res['id'])->update(["state" => 1]);
            return json($this->getReturn(1, "补单成功"));
        } else {
            return json($this->getReturn(-1, "订单不存在"));
        }
    }

    /**
     * 删除过期订单
     */
    public function delGqOrder()
    {
        Db::name("pay_order")->where("state", "-1")->delete();
        return json($this->getReturn());
    }

    /**
     * 删除7天前订单
     */
    public function delLastOrder()
    {
        Db::name("pay_order")->where("create_date <" . (time() - 604800))->delete();
        return json($this->getReturn());
    }

    /**
     * 生成二维码
     */
    public function enQrcode($url)
    {
        $qr_code = new QrcodeServer(['generate' => "display", "size", 200]);
        $content = $qr_code->createServer($url);
        return response($content, 200, ['Content-Length' => strlen($content)])->contentType('image/png');
    }

    /**
     * 获取客户IP
     */
    public function ip()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * 发送HTTP请求
     */
    public function getCurl($url, $post = 0, $cookie = 0, $header = 0, $nobaody = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $klsf   = [];
        $klsf[] = 'Accept:*/*';
        $klsf[] = 'Accept-Language:zh-cn';
        $klsf[] = 'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_2_1 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C153 MicroMessenger/6.6.1 NetType/WIFI Language/zh_CN';
        $klsf[] = 'Referer:https://servicewechat.com/wx7c8d593b2c3a7703/5/page-frame.html';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $klsf);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }
}
