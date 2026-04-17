<?php

namespace app\api\model;
use think\Model;
use think\Db;

class GoodsApiModel extends Model
{
    /**
     * 获取商品列表
     */
    public function getGoodsList($page = 1, $limit = 20, $category_id = '', $keyword = '')
    {
        $map = ['status' => 1];
        
        if (!empty($category_id)) {
            $map['mlm'] = $category_id;
        }
        
        if (!empty($keyword)) {
            $map['mname|mnamebie'] = ['like', '%' . $keyword . '%'];
        }
        
        // 获取总数
        $total = Db::name('fl')->where($map)->count();
        
        // 获取商品列表
        $goods = Db::name('fl')
            ->field('id,mname,mnamebie,mprice,imgurl,yunimgurl,type,sort,create_time')
            ->where($map)
            ->order('sort asc, id desc')
            ->page($page, $limit)
            ->select();
        
        // 处理图片URL
        foreach ($goods as &$item) {
            $item = replaceImgurl($item);
            $item['stock'] = $this->getGoodsStock($item['id']);
        }
        
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
            'list' => $goods
        ];
    }
    
    /**
     * 获取商品详情
     */
    public function getGoodsDetail($goods_id)
    {
        $goods = Db::name('fl')
            ->field('id,mname,mnamebie,mprice,imgurl,yunimgurl,type,sort,info,create_time,mlm')
            ->where(['id' => $goods_id, 'status' => 1])
            ->find();
            
        if (!$goods) {
            return false;
        }
        
        // 处理图片URL
        $goods = replaceImgurl($goods);
        
        // 获取库存
        $goods['stock'] = $this->getGoodsStock($goods_id);
        
        // 获取分类信息
        if ($goods['mlm']) {
            $category = Db::name('fl_category')->where('id', $goods['mlm'])->find();
            $goods['category'] = $category;
        }
        
        // 获取附加选项
        $attach_options = Db::name('attach')->where('attachgroupid', $goods_id)->select();
        $goods['attach_options'] = $attach_options;
        
        return $goods;
    }
    
    /**
     * 获取商品库存
     */
    private function getGoodsStock($goods_id)
    {
        if ($this->isAutoDelivery($goods_id)) {
            // 自动发货商品，统计可用卡密数量
            return Db::name('mail')->where(['mpid' => $goods_id, 'mis_use' => 0])->count();
        } else {
            // 手动发货商品，返回虚拟库存
            return mt_rand(100, 999);
        }
    }
    
    /**
     * 判断是否为自动发货商品
     */
    private function isAutoDelivery($goods_id)
    {
        $goods = Db::name('fl')->where('id', $goods_id)->find();
        return $goods && $goods['type'] == 0;
    }
    
    /**
     * 获取商品分类列表
     */
    public function getCategoryList()
    {
        return Db::name('fl_category')
            ->field('id,name,sort')
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select();
    }
}


