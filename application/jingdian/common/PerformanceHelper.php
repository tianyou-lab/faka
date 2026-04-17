<?php
/**
 * 前端性能优化助手类
 * 用于优化前端加载速度和性能
 */
namespace app\jingdian\common;

use think\Db;

class PerformanceHelper
{
    /**
     * 获取缓存的基础数据
     * @param int $childId 分站ID
     * @return array
     */
    public static function getCachedBaseData($childId = 0)
    {
        $cacheKey = 'base_data_' . $childId . '_' . date('Hi');
        $data = cache($cacheKey);
        
        if (!$data) {
            $model = new \app\jingdian\model\BaseModel();
            $data = [
                'cate' => $model->getAllCate(),
                'href' => $model->getYqHref(),
                'navigation' => $model->getAllNavigation()
            ];
            
            // 缓存1分钟
            cache($cacheKey, $data, 60);
        }
        
        return $data;
    }
    
    /**
     * 获取缓存的商品数据
     * @return array
     */
    public static function getCachedGoodsData()
    {
        $cacheKey = 'goods_list_' . date('Hi');
        $data = cache($cacheKey);
        
        if (!$data) {
            $goodsList = new \app\jingdian\model\GoodsListModel();
            $data_flName = $goodsList->getAllGoodsName();
            $data = $goodsList->getAllGoods($data_flName);
            
            // 缓存1分钟
            cache($cacheKey, $data, 60);
        }
        
        return $data;
    }
    
    /**
     * 检查会话状态（优化版本）
     * @param string $usertoken 用户Token
     * @return array|null
     */
    public static function checkUserSession($usertoken)
    {
        static $checkedSessions = [];
        
        if (isset($checkedSessions[$usertoken])) {
            return $checkedSessions[$usertoken];
        }
        
        if (strlen($usertoken) == 32) {
            $hasUser = Db::name('member')->where('token', $usertoken)->find();
            if ($hasUser) {
                $token = md5(md5($hasUser['account'] . $hasUser['password']) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
                if ($usertoken == $token && $hasUser['status'] == 1) {
                    $checkedSessions[$usertoken] = $hasUser;
                    return $hasUser;
                }
            }
        }
        
        $checkedSessions[$usertoken] = null;
        return null;
    }
    
    /**
     * 优化的分站检查
     * @param string $host 主机名
     * @return array|null
     */
    public static function checkSubSite($host)
    {
        static $checkedHosts = [];
        
        if (isset($checkedHosts[$host])) {
            return $checkedHosts[$host];
        }
        
        $cacheKey = 'subsite_' . md5($host);
        $data = cache($cacheKey);
        
        if (!$data) {
            $hasUser = Db::name('member')->where('fzhost', $host)->find();
            if ($hasUser && $hasUser['fzstatus'] == 1) {
                $hasUserAuth = Db::name('fz_auth')->where('memberid', $hasUser['id'])->find();
                if ($hasUserAuth) {
                    $store = $hasUserAuth['starttime'] + $hasUserAuth['endtime'] * 24 * 60 * 60 - time();
                    if ($store > 0 || $hasUserAuth['endtime'] == 0) {
                        $data = [
                            'user' => $hasUser,
                            'auth' => $hasUserAuth
                        ];
                        // 缓存5分钟
                        cache($cacheKey, $data, 300);
                    }
                }
            }
        }
        
        $checkedHosts[$host] = $data;
        return $data;
    }
    
    /**
     * 压缩HTML输出
     * @param string $html HTML内容
     * @return string
     */
    public static function compressHtml($html)
    {
        // 移除HTML注释（保留IE条件注释）
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // 移除多余的空白字符
        $html = preg_replace('/\s+/', ' ', $html);
        
        // 移除标签间的空白
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * 生成性能监控标记
     * @param string $label 标记名称
     * @return string
     */
    public static function performanceMark($label)
    {
        if (config('app_debug')) {
            return '<script>console.time("' . $label . '");</script>';
        }
        return '';
    }
    
    /**
     * 结束性能监控标记
     * @param string $label 标记名称
     * @return string
     */
    public static function performanceMarkEnd($label)
    {
        if (config('app_debug')) {
            return '<script>console.timeEnd("' . $label . '");</script>';
        }
        return '';
    }
    
    /**
     * 预加载关键资源
     * @param array $resources 资源列表
     * @return string
     */
    public static function preloadResources($resources)
    {
        $html = '';
        foreach ($resources as $resource) {
            $type = isset($resource['type']) ? $resource['type'] : 'script';
            $as = isset($resource['as']) ? $resource['as'] : ($type == 'style' ? 'style' : 'script');
            $html .= '<link rel="preload" href="' . $resource['href'] . '" as="' . $as . '">' . "\n";
        }
        return $html;
    }
    
    /**
     * 获取CDN资源URL
     * @param string $resource 资源路径
     * @param string $version 版本号
     * @return string
     */
    public static function getCdnUrl($resource, $version = null)
    {
        $cdnBase = config('cdnpublic', '/static/cdn/');
        $url = $cdnBase . $resource;
        
        if ($version) {
            $url .= '@' . $version;
        }
        
        return $url;
    }
    
    /**
     * 清除性能缓存
     */
    public static function clearPerformanceCache()
    {
        $patterns = [
            'base_data_*',
            'goods_list_*',
            'site_config_*',
            'subsite_*'
        ];
        
        foreach ($patterns as $pattern) {
            // 这里可以根据具体的缓存驱动实现批量清除
            // 目前使用简单的实现
            cache($pattern, null);
        }
    }
}


