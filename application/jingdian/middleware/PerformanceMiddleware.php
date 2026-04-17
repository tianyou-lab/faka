<?php
/**
 * 前端性能监控中间件
 * 用于监控和优化前端性能
 */
namespace app\jingdian\middleware;

class PerformanceMiddleware
{
    private $startTime;
    private $startMemory;
    
    public function handle($request, \Closure $next)
    {
        // 记录开始时间和内存使用
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // 设置性能相关的HTTP头
        $response = $next($request);
        
        // 计算执行时间和内存使用
        $executionTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $memoryUsage = round((memory_get_usage(true) - $this->startMemory) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        // 添加性能头信息（仅在调试模式下）
        if (config('app_debug')) {
            $response->header('X-Execution-Time', $executionTime . 'ms');
            $response->header('X-Memory-Usage', $memoryUsage . 'MB');
            $response->header('X-Peak-Memory', $peakMemory . 'MB');
        }
        
        // 添加缓存控制头
        $this->setCacheHeaders($response, $request);
        
        // 添加安全头
        $this->setSecurityHeaders($response);
        
        // 记录性能日志
        if ($executionTime > 2000) { // 超过2秒记录日志
            $this->logSlowRequest($request, $executionTime, $memoryUsage);
        }
        
        return $response;
    }
    
    /**
     * 设置缓存控制头
     */
    private function setCacheHeaders($response, $request)
    {
        $path = $request->pathinfo();
        
        // 静态资源缓存
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|ttf|eot)$/i', $path)) {
            $response->header('Cache-Control', 'public, max-age=31536000'); // 1年
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        }
        // API接口缓存
        elseif (strpos($path, '/api/') !== false) {
            $response->header('Cache-Control', 'public, max-age=300'); // 5分钟
        }
        // HTML页面缓存
        else {
            $response->header('Cache-Control', 'public, max-age=60'); // 1分钟
        }
    }
    
    /**
     * 设置安全头
     */
    private function setSecurityHeaders($response)
    {
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
    
    /**
     * 记录慢请求日志
     */
    private function logSlowRequest($request, $executionTime, $memoryUsage)
    {
        $logData = [
            'url' => $request->url(true),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 写入日志文件
        $logFile = RUNTIME_PATH . 'log' . DIRECTORY_SEPARATOR . 'slow_requests_' . date('Ymd') . '.log';
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
    }
}
