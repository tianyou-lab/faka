<?php
/**
 * 缓存助手类 - 用于优化系统性能
 */
namespace app\common;

class CacheHelper
{
    /**
     * 获取配置缓存
     * @param string $key 配置键
     * @param callable $callback 获取数据的回调函数
     * @param int $expire 过期时间（秒）
     * @return mixed
     */
    public static function getConfigCache($key, $callback, $expire = 300)
    {
        $cacheKey = 'config_' . $key;
        $cached = cache($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $data = $callback();
        cache($cacheKey, $data, $expire);
        
        return $data;
    }
    
    /**
     * 获取商品数据缓存
     * @param string $key 缓存键
     * @param callable $callback 获取数据的回调函数
     * @param int $expire 过期时间（秒）
     * @return mixed
     */
    public static function getGoodsCache($key, $callback, $expire = 60)
    {
        $cacheKey = 'goods_' . $key . '_' . date('Hi'); // 每分钟更新
        $cached = cache($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $data = $callback();
        cache($cacheKey, $data, $expire);
        
        return $data;
    }
    
    /**
     * 清除指定前缀的缓存
     * @param string $prefix 缓存前缀
     */
    public static function clearCacheByPrefix($prefix)
    {
        // 这里可以根据具体的缓存驱动实现批量清除
        // 目前使用简单的实现方式
        $keys = [
            $prefix . '_goods',
            $prefix . '_config',
            $prefix . '_navigation',
            $prefix . '_article'
        ];
        
        foreach ($keys as $key) {
            cache($key, null);
        }
    }
    
    /**
     * 获取IP地址缓存
     * @param string $ip IP地址
     * @param object $ipLocation IP查询对象
     * @return array
     */
    public static function getIpCache($ip, $ipLocation)
    {
        static $ipCache = [];
        
        if (!isset($ipCache[$ip])) {
            $ipCache[$ip] = $ipLocation->getlocation($ip);
        }
        
        return $ipCache[$ip];
    }
}


