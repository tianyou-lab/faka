<?php

namespace app\api\model;
use think\Model;
use think\Db;

class OrderApiModel extends Model
{
    /**
     * 创建订单（优化版本）
     */
    public function createOrder($data)
    {
        // 生成分布式锁键名
        $lock_key = 'order_create_lock_' . $data['goods_id'];
        $lock_timeout = config('api.order.lock_timeout', 10);
        
        try {
            // 尝试获取分布式锁
            if (!$this->acquireLock($lock_key, $lock_timeout)) {
                return ['code' => 0, 'msg' => '系统繁忙，请稍后重试'];
            }
            
            Db::startTrans();
            
            // 使用行锁获取商品信息
            $goods = Db::name('fl')
                ->where(['id' => $data['goods_id'], 'status' => 1])
                ->lock(true)
                ->find();
                
            if (!$goods) {
                $this->releaseLock($lock_key);
                Db::rollback();
                return ['code' => 0, 'msg' => '商品不存在或已下架'];
            }
            
            // 检查库存（原子操作）
            if ($goods['type'] == 0) { // 自动发货
                $available_cards = Db::name('mail')
                    ->where(['mpid' => $data['goods_id'], 'mis_use' => 0])
                    ->lock(true)
                    ->select();
                    
                if (count($available_cards) < $data['quantity']) {
                    $this->releaseLock($lock_key);
                    Db::rollback();
                    return ['code' => 0, 'msg' => '库存不足，当前可用：' . count($available_cards)];
                }
            }
            
            // 计算订单金额
            $total_amount = $goods['mprice'] * $data['quantity'];
            
            // 生成唯一订单号（防重复）
            $order_no = $this->generateUniqueOrderNo();
            
            // 处理附加信息
            $attach_data = '';
            if (!empty($data['attach_info'])) {
                foreach ($data['attach_info'] as $attach) {
                    $attach_data .= $attach['title'] . ':' . $attach['value'] . '  ';
                }
            }
            
            // 插入订单
            $order_data = [
                'morder' => $order_no,
                'mcard' => '',
                'mflid' => $data['goods_id'],
                'lianxi' => $data['contact_info'],
                'email' => '',
                'mamount' => $total_amount,
                'mstatus' => 2, // 未付款
                'maddtype' => 99, // API订单标识
                'create_time' => time(),
                'update_time' => time(),
                'memberid' => 0,
                'childid' => 0,
                'quantity' => $data['quantity'],
                'apikey_id' => $data['apikey_id'],
                'attach_data' => $attach_data
            ];
            
            $order_id = Db::name('info')->insertGetId($order_data);
            
            if (!$order_id) {
                $this->releaseLock($lock_key);
                Db::rollback();
                return ['code' => 0, 'msg' => '订单创建失败'];
            }
            
            // 插入附加信息
            if (!empty($data['attach_info'])) {
                foreach ($data['attach_info'] as $attach) {
                    if (!empty($attach['id']) && !empty($attach['value'])) {
                        Db::name('orderattach')->insert([
                            'orderno' => $order_no,
                            'attachid' => $attach['id'],
                            'text' => $attach['value'],
                            'create_time' => time()
                        ]);
                    }
                }
            }
            
            // 如果是自动发货商品，预占库存
            if ($goods['type'] == 0) {
                $card_ids = array_slice(array_column($available_cards, 'id'), 0, $data['quantity']);
                Db::name('mail')
                    ->whereIn('id', $card_ids)
                    ->update([
                        'reserved_order' => $order_no,
                        'reserved_time' => time()
                    ]);
            }
            
            Db::commit();
            $this->releaseLock($lock_key);
            
            return [
                'code' => 1,
                'msg' => '订单创建成功',
                'data' => [
                    'order_id' => $order_id,
                    'order_no' => $order_no,
                    'goods_name' => $goods['mnamebie'],
                    'quantity' => $data['quantity'],
                    'total_amount' => $total_amount,
                    'status' => 2,
                    'status_text' => '未付款',
                    'create_time' => date('Y-m-d H:i:s'),
                    'expire_time' => date('Y-m-d H:i:s', time() + config('api.order.expire_time', 900))
                ]
            ];
            
        } catch (\Exception $e) {
            $this->releaseLock($lock_key);
            Db::rollback();
            return ['code' => 0, 'msg' => '订单创建失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 获取订单状态
     */
    public function getOrderStatus($order_no)
    {
        $order = Db::name('info')
            ->alias('i')
            ->field('i.*,f.mnamebie,f.type')
            ->join('think_fl f', 'i.mflid = f.id', 'LEFT')
            ->where('i.morder', $order_no)
            ->find();
            
        if (!$order) {
            return false;
        }
        
        // 状态映射
        $status_map = [
            0 => '已付款',
            1 => '已下单或提取',
            2 => '未付款',
            3 => '进行中',
            4 => '已撤回',
            5 => '已完成'
        ];
        
        $result = [
            'order_id' => $order['id'],
            'order_no' => $order['morder'],
            'goods_name' => $order['mnamebie'],
            'quantity' => $order['quantity'] ?? 1,
            'total_amount' => $order['mamount'],
            'status' => $order['mstatus'],
            'status_text' => $status_map[$order['mstatus']] ?? '未知状态',
            'contact_info' => $order['lianxi'],
            'create_time' => date('Y-m-d H:i:s', $order['create_time']),
            'update_time' => date('Y-m-d H:i:s', $order['update_time'])
        ];
        
        // 如果已付款，获取卡密信息
        if ($order['mstatus'] == 0 || $order['mstatus'] == 1) {
            $cards = $this->getOrderCards($order_no);
            $result['cards'] = $cards;
        }
        
        return $result;
    }
    
    /**
     * 获取订单卡密
     */
    private function getOrderCards($order_no)
    {
        return Db::name('mail')
            ->field('musernm,mpassword,motherinfo')
            ->where('morder', $order_no)
            ->select();
    }
    
    /**
     * 支付回调处理
     */
    public function paymentCallback($order_no, $trade_no = '')
    {
        try {
            Db::startTrans();
            
            // 获取订单信息
            $order = Db::name('info')->where('morder', $order_no)->find();
            if (!$order) {
                Db::rollback();
                return ['code' => 0, 'msg' => '订单不存在'];
            }
            
            if ($order['mstatus'] != 2) {
                Db::rollback();
                return ['code' => 0, 'msg' => '订单状态异常'];
            }
            
            // 获取商品信息
            $goods = Db::name('fl')->where('id', $order['mflid'])->find();
            if (!$goods) {
                Db::rollback();
                return ['code' => 0, 'msg' => '商品信息不存在'];
            }
            
            $quantity = $order['quantity'] ?? 1;
            
            if ($goods['type'] == 0) { // 自动发货
                // 获取可用卡密
                $cards = Db::name('mail')
                    ->where(['mpid' => $order['mflid'], 'mis_use' => 0])
                    ->limit($quantity)
                    ->select();
                    
                if (count($cards) < $quantity) {
                    Db::rollback();
                    return ['code' => 0, 'msg' => '库存不足'];
                }
                
                // 标记卡密为已使用
                $card_ids = [];
                foreach ($cards as $card) {
                    $card_ids[] = $card['id'];
                }
                Db::name('mail')->whereIn('id', $card_ids)->update([
                    'mis_use' => 1,
                    'morder' => $order_no,
                    'update_time' => time()
                ]);
                
                // 更新订单状态为已完成
                $new_status = 5;
            } else { // 手动发货
                // 更新订单状态为已付款
                $new_status = 0;
            }
            
            // 更新订单状态
            Db::name('info')->where('id', $order['id'])->update([
                'mstatus' => $new_status,
                'mcard' => $trade_no,
                'update_time' => time()
            ]);
            
            Db::commit();
            
            return [
                'code' => 1,
                'msg' => '支付成功',
                'data' => [
                    'order_no' => $order_no,
                    'status' => $new_status,
                    'cards' => isset($cards) ? $cards : []
                ]
            ];
            
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 0, 'msg' => '支付处理失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 获取分布式锁
     */
    private function acquireLock($key, $timeout)
    {
        $end_time = time() + $timeout;
        $lock_value = uniqid();
        
        while (time() < $end_time) {
            // 尝试设置锁（使用缓存的原子操作）
            if (cache($key) === false) {
                cache($key, $lock_value, $timeout);
                return true;
            }
            
            // 短暂等待后重试
            usleep(10000); // 10ms
        }
        
        return false;
    }
    
    /**
     * 释放分布式锁
     */
    private function releaseLock($key)
    {
        cache($key, null);
    }
    
    /**
     * 生成唯一订单号
     */
    private function generateUniqueOrderNo()
    {
        do {
            $order_no = 'API' . date('YmdHis') . mt_rand(1000, 9999);
            $exists = Db::name('info')->where('morder', $order_no)->find();
        } while ($exists);
        
        return $order_no;
    }
}
