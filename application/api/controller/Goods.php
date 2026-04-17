<?php

namespace app\api\controller;
use app\admin\model\ApikeyModel;
use app\api\model\GoodsApiModel;
use app\api\model\OrderApiModel;
use app\api\model\PaymentApiModel;
use think\Controller;
use think\Db;

class Goods extends Controller
{
    protected $apikey;
    protected $apikeyModel;
    protected $start_time;
    protected $start_memory;
    protected $request_id;
    
    public function _initialize()
    {
        // 性能监控开始
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
        $this->request_id = uniqid();
        
        // 设置请求ID到响应头
        header('X-Request-ID: ' . $this->request_id);
        
        // 跨域支持
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Request-ID');
        header('Access-Control-Expose-Headers: X-Request-ID, X-Execution-Time, X-Memory-Usage');
        
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        
        // API认证
        $this->apikeyModel = new ApikeyModel();
        if (!$this->authenticate()) {
            $this->apiError('API认证失败', null, 401);
        }
        
        // 注册结束回调
        register_shutdown_function([$this, 'logPerformanceMetrics']);
    }
    
    /**
     * API认证（优化版本）
     */
    private function authenticate()
    {
        $app_id = input('app_id', '');
        $app_secret = input('app_secret', '');
        $timestamp = input('timestamp', '');
        $sign = input('sign', '');
        
        if (empty($app_id) || empty($app_secret)) {
            return false;
        }
        
        // 添加缓存机制避免重复数据库查询
        $cache_key = 'apikey_' . md5($app_id . $app_secret);
        $this->apikey = cache($cache_key);
        
        if (!$this->apikey) {
            // 缓存未命中，查询数据库
            $this->apikey = $this->apikeyModel->verifyApikey($app_id, $app_secret);
            if ($this->apikey) {
                // 缓存API密钥信息5分钟
                cache($cache_key, $this->apikey, 300);
            }
        }
        
        if (!$this->apikey) {
            return false;
        }
        
        // 检查IP白名单（优化版本）
        if (!empty($this->apikey['allowed_ips'])) {
            $allowed_ips = explode(',', $this->apikey['allowed_ips']);
            $client_ip = $this->getOptimizedClientIP();
            if (!in_array($client_ip, $allowed_ips)) {
                $this->apikeyModel->logApiCall($this->apikey['id'], 'auth', [], [], 'error', 'IP不在白名单中');
                return false;
            }
        }
        
        // 检查速率限制
        if (!$this->checkRateLimit()) {
            return false;
        }
        
        // 检查每日调用限制（优化版本）
        if ($this->apikey['daily_limit'] > 0) {
            $cache_key_daily = 'daily_calls_' . $this->apikey['id'] . '_' . date('Ymd');
            $today_calls = cache($cache_key_daily) ?: 0;
            
            if ($today_calls >= $this->apikey['daily_limit']) {
                $this->apikeyModel->logApiCall($this->apikey['id'], 'auth', [], [], 'error', '超过每日调用限制');
                return false;
            }
            
            // 增加今日调用计数
            cache($cache_key_daily, $today_calls + 1, 86400);
        }
        
        return true;
    }
    
    /**
     * 优化的客户端IP获取
     */
    private function getOptimizedClientIP()
    {
        static $ip = null;
        
        if ($ip !== null) {
            return $ip;
        }
        
        // 优先级顺序获取真实IP
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy_set_header X-Real-IP
            'HTTP_X_FORWARDED_FOR',      // 标准代理头
            'HTTP_CLIENT_IP',            // 客户端IP
            'REMOTE_ADDR'                // 直连IP
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $temp_ip = $_SERVER[$header];
                
                // 处理多个IP的情况（取第一个）
                if (strpos($temp_ip, ',') !== false) {
                    $temp_ip = trim(explode(',', $temp_ip)[0]);
                }
                
                // 验证IP格式并排除内网IP
                if (filter_var($temp_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $temp_ip;
                    break;
                }
            }
        }
        
        return $ip ?: '0.0.0.0';
    }
    
    /**
     * API速率限制检查
     */
    private function checkRateLimit()
    {
        // 每小时限制
        $hourly_cache_key = 'rate_limit_hourly_' . $this->apikey['id'] . '_' . date('YmdH');
        $hourly_calls = cache($hourly_cache_key) ?: 0;
        $hourly_limit = $this->apikey['hourly_limit'] ?? config('api.default_rate_limit.hourly', 1000);
        
        if ($hourly_calls >= $hourly_limit) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'rate_limit', [], [], 'error', '超过小时调用限制');
            return false;
        }
        
        // 每分钟限制
        $minute_cache_key = 'rate_limit_minute_' . $this->apikey['id'] . '_' . date('YmdHi');
        $minute_calls = cache($minute_cache_key) ?: 0;
        $minute_limit = $this->apikey['minute_limit'] ?? config('api.default_rate_limit.minute', 100);
        
        if ($minute_calls >= $minute_limit) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'rate_limit', [], [], 'error', '超过分钟调用限制');
            return false;
        }
        
        // 更新计数器
        cache($hourly_cache_key, $hourly_calls + 1, 3600);
        cache($minute_cache_key, $minute_calls + 1, 60);
        
        return true;
    }
    
    /**
     * 获取商品列表
     */
    public function getGoodsList()
    {
        try {
            $page = input('page', 1);
            $limit = input('limit', 20);
            $category_id = input('category_id', '');
            $keyword = input('keyword', '');
            
            $goodsModel = new GoodsApiModel();
            $result = $goodsModel->getGoodsList($page, $limit, $category_id, $keyword);
            
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getGoodsList', input(), $result, 'success');
            
            $this->apiSuccess('获取商品列表成功', $result);
            
        } catch (\Exception $e) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getGoodsList', input(), [], 'error', $e->getMessage());
            $this->apiError('获取商品列表失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取商品详情
     */
    public function getGoodsDetail()
    {
        try {
            $goods_id = input('goods_id', '');
            
            if (empty($goods_id)) {
                $this->apiError('商品ID不能为空');
            }
            
            $goodsModel = new GoodsApiModel();
            $result = $goodsModel->getGoodsDetail($goods_id);
            
            if (!$result) {
                $this->apiError('商品不存在或已下架');
            }
            
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getGoodsDetail', input(), $result, 'success');
            
            $this->apiSuccess('获取商品详情成功', $result);
            
        } catch (\Exception $e) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getGoodsDetail', input(), [], 'error', $e->getMessage());
            $this->apiError('获取商品详情失败：' . $e->getMessage());
        }
    }
    
    /**
     * 创建订单
     */
    public function createOrder()
    {
        try {
            $goods_id = input('goods_id', '');
            $quantity = input('quantity', 1);
            $contact_info = input('contact_info', '');
            $attach_info = input('attach_info', []);
            
            if (empty($goods_id)) {
                $this->apiError('商品ID不能为空');
            }
            
            if (empty($contact_info)) {
                $this->apiError('联系方式不能为空');
            }
            
            $orderModel = new OrderApiModel();
            $result = $orderModel->createOrder([
                'goods_id' => $goods_id,
                'quantity' => $quantity,
                'contact_info' => $contact_info,
                'attach_info' => $attach_info,
                'apikey_id' => $this->apikey['id']
            ]);
            
            if ($result['code'] != 1) {
                $this->apiError($result['msg']);
            }
            
            $this->apikeyModel->logApiCall($this->apikey['id'], 'createOrder', input(), $result['data'], 'success');
            
            $this->apiSuccess('订单创建成功', $result['data']);
            
        } catch (\Exception $e) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'createOrder', input(), [], 'error', $e->getMessage());
            $this->apiError('创建订单失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取订单状态
     */
    public function getOrderStatus()
    {
        try {
            $order_no = input('order_no', '');
            
            if (empty($order_no)) {
                $this->apiError('订单号不能为空');
            }
            
            $orderModel = new OrderApiModel();
            $result = $orderModel->getOrderStatus($order_no);
            
            if (!$result) {
                $this->apiError('订单不存在');
            }
            
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getOrderStatus', input(), $result, 'success');
            
            $this->apiSuccess('获取订单状态成功', $result);
            
        } catch (\Exception $e) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getOrderStatus', input(), [], 'error', $e->getMessage());
            $this->apiError('获取订单状态失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取支付二维码
     */
    public function getPaymentQRCode()
    {
        try {
            $order_no = input('order_no', '');
            $pay_type = input('pay_type', 'alipay'); // alipay, wechat
            
            if (empty($order_no)) {
                $this->apiError('订单号不能为空');
            }
            
            if (!in_array($pay_type, ['alipay', 'wechat'])) {
                $this->apiError('支付类型不正确');
            }
            
            $paymentModel = new PaymentApiModel();
            $result = $paymentModel->generateQRCode($order_no, $pay_type);
            
            if ($result['code'] != 1) {
                $this->apiError($result['msg']);
            }
            
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getPaymentQRCode', input(), $result['data'], 'success');
            
            $this->apiSuccess('获取支付二维码成功', $result['data']);
            
        } catch (\Exception $e) {
            $this->apikeyModel->logApiCall($this->apikey['id'], 'getPaymentQRCode', input(), [], 'error', $e->getMessage());
            $this->apiError('获取支付二维码失败：' . $e->getMessage());
        }
    }
    
    /**
     * 统一返回成功信息
     */
    private function apiSuccess($msg, $data = [])
    {
        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ];
        
        header('Content-Type: application/json;charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 统一返回错误信息
     */
    private function apiError($msg, $data = [], $code = 0)
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ];
        
        header('Content-Type: application/json;charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 记录性能指标
     */
    public function logPerformanceMetrics()
    {
        $execution_time = microtime(true) - $this->start_time;
        $memory_usage = memory_get_usage() - $this->start_memory;
        $peak_memory = memory_get_peak_usage();
        
        // 添加性能头信息
        header('X-Execution-Time: ' . round($execution_time * 1000, 2) . 'ms');
        header('X-Memory-Usage: ' . round($memory_usage / 1024 / 1024, 2) . 'MB');
        
        // 记录性能日志
        if (isset($this->apikey['id'])) {
            $this->apikeyModel->logApiCall(
                $this->apikey['id'], 
                'performance', 
                [
                    'request_id' => $this->request_id,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ], 
                [
                    'execution_time' => $execution_time,
                    'memory_usage' => $memory_usage,
                    'peak_memory' => $peak_memory,
                    'timestamp' => time()
                ], 
                'performance'
            );
        }
        
        // 如果执行时间过长，记录慢查询日志
        $slow_query_threshold = config('monitor.slow_query_time', 1.0);
        if ($execution_time > $slow_query_threshold) {
            error_log("慢API请求 - RequestID: {$this->request_id}, Time: {$execution_time}s, Memory: " . round($memory_usage / 1024 / 1024, 2) . "MB");
        }
    }
}
