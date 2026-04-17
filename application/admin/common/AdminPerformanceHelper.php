<?php
/**
 * 后台性能优化助手类
 * 用于优化管理员后台各功能的性能
 */
namespace app\admin\common;

use think\Db;
use com\IpLocationqq;

class AdminPerformanceHelper
{
    /**
     * 获取后台仪表板统计数据（缓存版本）
     * @return array
     */
    public static function getDashboardStats()
    {
        $cacheKey = 'admin_dashboard_stats_' . date('YmdHi') . '_' . floor(date('i') / 10);
        $stats = cache($cacheKey);
        
        if (!$stats) {
            $today = strtotime(date("Y-m-d"), time());
            $month_start = strtotime(date("Y-m-01"));
            $month_end = strtotime("+1 month -1 seconds", $month_start);
            $shang_start = strtotime("-1 month", $month_start);
            $shang_end = strtotime("+1 month -1 seconds", $shang_start);
            
            // 使用单个查询获取多个统计数据
            $stats_sql = "
                SELECT 
                    SUM(CASE WHEN mstatus <> 2 AND update_time >= {$month_start} AND update_time <= {$month_end} THEN mamount ELSE 0 END) as current_month,
                    SUM(CASE WHEN mstatus <> 2 AND update_time >= {$shang_start} AND update_time <= {$shang_end} THEN mamount ELSE 0 END) as last_month,
                    SUM(CASE WHEN mstatus <> 2 AND DATE(FROM_UNIXTIME(update_time)) = CURDATE() THEN mamount ELSE 0 END) as today,
                    SUM(CASE WHEN mstatus <> 2 AND DATE(FROM_UNIXTIME(update_time)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN mamount ELSE 0 END) as yesterday,
                    COUNT(CASE WHEN mstatus <> 2 THEN 1 END) as total_orders
                FROM think_info
            ";
            $result = Db::query($stats_sql);
            $stats = $result[0];
            
            // 商品数目（缓存更长时间）
            $goods_count = cache('goods_count_cache');
            if (!$goods_count) {
                $goods_count = Db::name('fl')->count();
                cache('goods_count_cache', $goods_count, 3600);
            }
            $stats['goods_count'] = $goods_count;
            
            // 缓存10分钟
            cache($cacheKey, $stats, 600);
        }
        
        return $stats;
    }
    
    /**
     * 批量IP地址查询优化
     * @param array $ips IP地址数组
     * @return array
     */
    public static function batchIpLookup($ips)
    {
        static $ipCache = [];
        $result = [];
        $needQuery = [];
        
        foreach ($ips as $ip) {
            if (empty($ip)) {
                $result[$ip] = ['country' => '未知', 'area' => '地区'];
            } elseif (isset($ipCache[$ip])) {
                $result[$ip] = $ipCache[$ip];
            } else {
                $needQuery[] = $ip;
            }
        }
        
        if (!empty($needQuery)) {
            $ipLocation = new IpLocationqq('qqwry.dat');
            foreach ($needQuery as $ip) {
                $location = $ipLocation->getlocation($ip);
                $ipCache[$ip] = $location;
                $result[$ip] = $location;
            }
        }
        
        return $result;
    }
    
    /**
     * 优化的分页缓存处理
     * @param string $baseKey 基础缓存键
     * @param callable $dataCallback 数据获取回调函数
     * @param int $cacheTime 缓存时间（秒）
     * @param array $params 参数
     * @return array
     */
    public static function getPaginatedData($baseKey, $dataCallback, $cacheTime = 60, $params = [])
    {
        $cacheKey = $baseKey . '_' . md5(serialize($params)) . '_' . date('Hi');
        $data = cache($cacheKey);
        
        if (!$data) {
            $data = call_user_func($dataCallback, $params);
            cache($cacheKey, $data, $cacheTime);
        }
        
        return $data;
    }
    
    /**
     * 数据库查询优化 - 只选择必要字段
     * @param string $table 表名
     * @param array $fields 字段数组
     * @param array $conditions 查询条件
     * @param string $orderBy 排序
     * @param int $limit 限制数量
     * @return array
     */
    public static function optimizedQuery($table, $fields, $conditions = [], $orderBy = null, $limit = null)
    {
        $cacheKey = 'opt_query_' . md5($table . serialize($fields) . serialize($conditions) . $orderBy . $limit);
        $result = cache($cacheKey);
        
        if (!$result) {
            $query = Db::name($table)->field(implode(',', $fields));
            
            if (!empty($conditions)) {
                $query = $query->where($conditions);
            }
            
            if ($orderBy) {
                $query = $query->order($orderBy);
            }
            
            if ($limit) {
                $query = $query->limit($limit);
            }
            
            $result = $query->select();
            
            // 缓存2分钟
            cache($cacheKey, $result, 120);
        }
        
        return $result;
    }
    
    /**
     * 清除后台性能缓存
     * @param string $pattern 缓存模式
     */
    public static function clearAdminCache($pattern = null)
    {
        $patterns = $pattern ? [$pattern] : [
            'admin_dashboard_*',
            'admin_user_list_*',
            'order_list_*',
            'order_query_*',
            'member_list_*',
            'member_query_*',
            'goods_count_*',
            'opt_query_*'
        ];
        
        foreach ($patterns as $p) {
            // 这里可以根据具体的缓存驱动实现批量清除
            cache($p, null);
        }
    }
    
    /**
     * 性能监控 - 记录慢查询
     * @param string $operation 操作名称
     * @param float $startTime 开始时间
     * @param array $extra 额外信息
     */
    public static function logSlowOperation($operation, $startTime, $extra = [])
    {
        $executionTime = microtime(true) - $startTime;
        
        // 如果执行时间超过2秒，记录日志
        if ($executionTime > 2.0) {
            $logData = [
                'operation' => $operation,
                'execution_time' => round($executionTime, 3),
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
                'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB',
                'extra' => $extra,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => session('uid'),
                'user_name' => session('username')
            ];
            
            $logFile = RUNTIME_PATH . 'log' . DIRECTORY_SEPARATOR . 'admin_slow_' . date('Ymd') . '.log';
            file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
        }
        
        return $executionTime;
    }
    
    /**
     * 生成优化的缓存键
     * @param string $prefix 前缀
     * @param array $params 参数
     * @param string $timeUnit 时间单位（Y-年，m-月，d-日，H-小时，i-分钟）
     * @return string
     */
    public static function generateCacheKey($prefix, $params = [], $timeUnit = 'i')
    {
        $timeFormats = [
            'Y' => 'Y',      // 年
            'm' => 'Ym',     // 月
            'd' => 'Ymd',    // 日
            'H' => 'YmdH',   // 小时
            'i' => 'YmdHi'   // 分钟
        ];
        
        $timeStr = date($timeFormats[$timeUnit] ?? 'YmdHi');
        $paramStr = empty($params) ? '' : '_' . md5(serialize($params));
        
        return $prefix . '_' . $timeStr . $paramStr;
    }
    
    /**
     * 获取系统性能信息
     * @return array
     */
    public static function getSystemPerformance()
    {
        $cacheKey = 'system_performance_' . date('YmdHi');
        $performance = cache($cacheKey);
        
        if (!$performance) {
            $performance = [
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
                'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2),
                'memory_limit' => ini_get('memory_limit'),
                'php_version' => phpversion(),
                'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
                'cache_size' => self::getCacheSize(),
                'db_connections' => self::getDatabaseConnections(),
                'timestamp' => time()
            ];
            
            // 缓存1分钟
            cache($cacheKey, $performance, 60);
        }
        
        return $performance;
    }
    
    /**
     * 获取缓存大小估算
     * @return string
     */
    private static function getCacheSize()
    {
        // 这是一个简单的估算，实际实现可能因缓存驱动而异
        $cacheDir = RUNTIME_PATH . 'cache';
        if (is_dir($cacheDir)) {
            $size = self::getDirSize($cacheDir);
            return self::formatBytes($size);
        }
        return 'Unknown';
    }
    
    /**
     * 获取目录大小
     * @param string $dir 目录路径
     * @return int
     */
    private static function getDirSize($dir)
    {
        $size = 0;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($filePath)) {
                        $size += self::getDirSize($filePath);
                    } else {
                        $size += filesize($filePath);
                    }
                }
            }
        }
        return $size;
    }
    
    /**
     * 格式化字节数
     * @param int $bytes 字节数
     * @return string
     */
    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * 获取数据库连接数（简单实现）
     * @return int
     */
    private static function getDatabaseConnections()
    {
        try {
            $result = Db::query("SHOW STATUS LIKE 'Threads_connected'");
            return isset($result[0]['Value']) ? (int)$result[0]['Value'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}


