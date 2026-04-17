<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\Route;

// API路由配置
Route::group('api', function () {
    // 商品相关API
    Route::group('goods', function () {
        Route::get('getGoodsList', 'api/Goods/getGoodsList');           // 获取商品列表
        Route::get('getGoodsDetail', 'api/Goods/getGoodsDetail');       // 获取商品详情
        Route::post('createOrder', 'api/Goods/createOrder');            // 创建订单
        Route::get('getOrderStatus', 'api/Goods/getOrderStatus');       // 获取订单状态
        Route::post('getPaymentQRCode', 'api/Goods/getPaymentQRCode');  // 获取支付二维码
    });
    
    // 支付回调API
    Route::group('payment', function () {
        Route::post('alipay_notify', 'api/Payment/alipay_notify');      // 支付宝回调
        Route::post('wechat_notify', 'api/Payment/wechat_notify');      // 微信回调
    });
});

// 管理员API秘钥管理路由
Route::group('admin/apikey', function () {
    Route::get('index', 'admin/Apikey/index');                         // 秘钥列表
    Route::get('add', 'admin/Apikey/add');                             // 添加页面
    Route::post('add', 'admin/Apikey/add');                            // 添加处理
    Route::get('edit/[:id]', 'admin/Apikey/edit');                     // 编辑页面
    Route::post('edit', 'admin/Apikey/edit');                          // 编辑处理
    Route::post('status', 'admin/Apikey/status');                      // 状态切换
    Route::post('del', 'admin/Apikey/del');                            // 删除秘钥
    Route::post('regenerate', 'admin/Apikey/regenerate');              // 重新生成秘钥
    Route::get('stats/[:id]', 'admin/Apikey/stats');                   // 使用统计
});

return [
    
];


