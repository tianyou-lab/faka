<?php

namespace app\common;

/**
 * 配置助手类
 * 
 * 提供统一的配置读取接口，支持环境变量和配置文件
 */
class ConfigHelper
{
    /**
     * 获取环境变量值
     */
    private static function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * 获取支付宝配置
     */
    public static function getAlipayConfig()
    {
        return [
            'app_id' => self::env('ALIPAY_APP_ID') ?: config('alipay.app_id'),
            'private_key' => self::env('ALIPAY_PRIVATE_KEY') ?: config('alipay.private_key'),
            'public_key' => self::env('ALIPAY_PUBLIC_KEY') ?: config('alipay.public_key'),
            'gateway' => self::env('ALIPAY_GATEWAY') ?: config('alipay.gateway', 'https://openapi.alipay.com/gateway.do'),
            'return_url' => self::env('ALIPAY_RETURN_URL') ?: config('alipay.return_url'),
            'notify_url' => self::env('ALIPAY_NOTIFY_URL') ?: config('alipay.notify_url'),
        ];
    }
    
    /**
     * 获取微信支付配置
     */
    public static function getWechatConfig()
    {
        return [
            'app_id' => self::env('WECHAT_APP_ID') ?: config('wechat.app_id'),
            'mch_id' => self::env('WECHAT_MCH_ID') ?: config('wechat.mch_id'),
            'key' => self::env('WECHAT_KEY') ?: config('wechat.key'),
            'cert_path' => self::env('WECHAT_CERT_PATH') ?: config('wechat.cert_path'),
            'key_path' => self::env('WECHAT_KEY_PATH') ?: config('wechat.key_path'),
            'notify_url' => self::env('WECHAT_NOTIFY_URL') ?: config('wechat.notify_url'),
        ];
    }
    
    /**
     * 获取API配置
     */
    public static function getApiConfig()
    {
        return [
            'default_rate_limit' => [
                'hourly' => self::env('API_HOURLY_LIMIT', 1000),
                'minute' => self::env('API_MINUTE_LIMIT', 100),
                'daily' => self::env('API_DAILY_LIMIT', 10000),
            ],
            'cache_ttl' => [
                'apikey' => self::env('API_CACHE_APIKEY_TTL', 300),
                'goods_list' => self::env('API_CACHE_GOODS_TTL', 600),
                'goods_detail' => self::env('API_CACHE_DETAIL_TTL', 1800),
            ],
            'order' => [
                'expire_time' => self::env('API_ORDER_EXPIRE', 900),
                'lock_timeout' => self::env('API_ORDER_LOCK_TIMEOUT', 10),
            ],
            'qr_code' => [
                'service_url' => self::env('QR_CODE_SERVICE', 'https://api.qrserver.com/v1/create-qr-code/'),
                'size' => self::env('QR_CODE_SIZE', '200x200'),
                'format' => self::env('QR_CODE_FORMAT', 'png'),
                'path' => self::env('QR_CODE_PATH', '/uploads/qrcode/'),
            ],
        ];
    }
    
    /**
     * 获取数据库配置
     */
    public static function getDatabaseConfig()
    {
        return [
            'type' => 'mysql',
            'hostname' => self::env('DB_HOST', 'localhost'),
            'database' => self::env('DB_NAME'),
            'username' => self::env('DB_USER'),
            'password' => self::env('DB_PASS'),
            'hostport' => self::env('DB_PORT', '3306'),
            'params' => [],
            'charset' => 'utf8mb4',
            'prefix' => self::env('DB_PREFIX', 'think_'),
            'debug' => self::env('APP_DEBUG', false),
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'slave_no' => '',
            'fields_strict' => true,
            'result_type' => \PDO::FETCH_ASSOC,
            'resultset_type' => 'array',
            'auto_timestamp' => false,
            'datetime_format' => 'Y-m-d H:i:s',
            'sql_explain' => false,
        ];
    }
    
    /**
     * 获取缓存配置
     */
    public static function getCacheConfig()
    {
        return [
            'type' => self::env('CACHE_TYPE', 'file'),
            'path' => self::env('CACHE_PATH', defined('CACHE_PATH') ? CACHE_PATH : 'runtime/cache/'),
            'prefix' => self::env('CACHE_PREFIX', 'api_'),
            'expire' => self::env('CACHE_EXPIRE', 0),
        ];
    }
    
    /**
     * 获取日志配置
     */
    public static function getLogConfig()
    {
        return [
            'type' => self::env('LOG_TYPE', 'file'),
            'path' => self::env('LOG_PATH', defined('LOG_PATH') ? LOG_PATH : 'runtime/log/'),
            'level' => self::env('LOG_LEVEL', 'error'),
            'file_size' => self::env('LOG_FILE_SIZE', 2097152),
            'time_format' => self::env('LOG_TIME_FORMAT', 'c'),
            'max_files' => self::env('LOG_MAX_FILES', 0),
        ];
    }
    
    /**
     * 获取监控配置
     */
    public static function getMonitorConfig()
    {
        return [
            'enabled' => self::env('MONITOR_ENABLED', true),
            'slow_query_time' => self::env('MONITOR_SLOW_QUERY', 1.0),
            'memory_limit' => self::env('MONITOR_MEMORY_LIMIT', 128),
            'alert_email' => self::env('MONITOR_ALERT_EMAIL'),
            'alert_webhook' => self::env('MONITOR_ALERT_WEBHOOK'),
        ];
    }
    
    /**
     * 获取安全配置
     */
    public static function getSecurityConfig()
    {
        return [
            'encryption_key' => self::env('ENCRYPTION_KEY'),
            'token_expire' => self::env('TOKEN_EXPIRE', 7200),
            'max_login_attempts' => self::env('MAX_LOGIN_ATTEMPTS', 5),
            'login_ban_time' => self::env('LOGIN_BAN_TIME', 300),
            'allowed_ips' => self::env('ALLOWED_IPS', ''),
        ];
    }
    
    /**
     * 获取上传配置
     */
    public static function getUploadConfig()
    {
        return [
            'path' => self::env('UPLOAD_PATH', 'uploads/'),
            'max_size' => self::env('UPLOAD_MAX_SIZE', 2097152),
            'allowed_ext' => self::env('UPLOAD_ALLOWED_EXT', 'jpg,jpeg,png,gif'),
            'qr_code_path' => self::env('QR_CODE_PATH', 'uploads/qrcode/'),
        ];
    }
    
    /**
     * 验证必需的配置项
     */
    public static function validateRequiredConfig()
    {
        $required_configs = [
            'DB_HOST' => '数据库主机',
            'DB_NAME' => '数据库名称',
            'DB_USER' => '数据库用户名',
            'DB_PASS' => '数据库密码',
        ];
        
        $missing = [];
        foreach ($required_configs as $key => $desc) {
            if (empty(self::env($key))) {
                $missing[] = "{$desc} ({$key})";
            }
        }
        
        return $missing;
    }
    
    /**
     * 检查配置完整性
     */
    public static function checkConfigIntegrity()
    {
        $checks = [
            'database' => self::checkDatabaseConfig(),
            'cache' => self::checkCacheConfig(),
            'upload' => self::checkUploadConfig(),
            'alipay' => self::checkAlipayConfig(),
            'wechat' => self::checkWechatConfig(),
        ];
        
        return $checks;
    }
    
    /**
     * 检查数据库配置
     */
    private static function checkDatabaseConfig()
    {
        $config = self::getDatabaseConfig();
        $required = ['hostname', 'database', 'username', 'password'];
        
        foreach ($required as $key) {
            if (empty($config[$key])) {
                return ['status' => false, 'message' => "数据库配置不完整：缺少 {$key}"];
            }
        }
        
        return ['status' => true, 'message' => '数据库配置完整'];
    }
    
    /**
     * 检查缓存配置
     */
    private static function checkCacheConfig()
    {
        $config = self::getCacheConfig();
        
        if ($config['type'] === 'file' && !is_writable($config['path'])) {
            return ['status' => false, 'message' => '缓存目录不可写：' . $config['path']];
        }
        
        return ['status' => true, 'message' => '缓存配置正常'];
    }
    
    /**
     * 检查上传配置
     */
    private static function checkUploadConfig()
    {
        $config = self::getUploadConfig();
        
        if (!is_writable($config['path'])) {
            return ['status' => false, 'message' => '上传目录不可写：' . $config['path']];
        }
        
        return ['status' => true, 'message' => '上传配置正常'];
    }
    
    /**
     * 检查支付宝配置
     */
    private static function checkAlipayConfig()
    {
        $config = self::getAlipayConfig();
        $required = ['app_id', 'private_key', 'public_key'];
        
        foreach ($required as $key) {
            if (empty($config[$key])) {
                return ['status' => false, 'message' => "支付宝配置不完整：缺少 {$key}"];
            }
        }
        
        return ['status' => true, 'message' => '支付宝配置完整'];
    }
    
    /**
     * 检查微信支付配置
     */
    private static function checkWechatConfig()
    {
        $config = self::getWechatConfig();
        $required = ['app_id', 'mch_id', 'key'];
        
        foreach ($required as $key) {
            if (empty($config[$key])) {
                return ['status' => false, 'message' => "微信支付配置不完整：缺少 {$key}"];
            }
        }
        
        return ['status' => true, 'message' => '微信支付配置完整'];
    }
    
    /**
     * 生成配置文件
     */
    public static function generateConfigFile()
    {
        $config = [
            'database' => self::getDatabaseConfig(),
            'cache' => self::getCacheConfig(),
            'log' => self::getLogConfig(),
            'alipay' => self::getAlipayConfig(),
            'wechat' => self::getWechatConfig(),
            'api' => self::getApiConfig(),
            'monitor' => self::getMonitorConfig(),
            'security' => self::getSecurityConfig(),
            'upload' => self::getUploadConfig(),
        ];
        
        return $config;
    }
}