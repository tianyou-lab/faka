<?php
/**
 * 会员管理性能优化助手类
 * 用于优化会员相关的查询和操作
 */
namespace app\admin\common;

use think\Db;
use com\IpLocationqq;

class MemberHelper
{
    /**
     * 批量获取IP地址信息（优化版本）
     * @param array $ips IP地址数组
     * @return array
     */
    public static function batchGetIpLocation($ips)
    {
        static $ipCache = [];
        $result = [];
        $needQuery = [];
        
        // 检查哪些IP需要查询
        foreach ($ips as $ip) {
            if (empty($ip)) {
                $result[$ip] = ['country' => '未知', 'area' => '地区'];
            } elseif (isset($ipCache[$ip])) {
                $result[$ip] = $ipCache[$ip];
            } else {
                $needQuery[] = $ip;
            }
        }
        
        // 批量查询未缓存的IP
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
     * 获取会员统计信息（缓存版本）
     * @return array
     */
    public static function getMemberStatistics()
    {
        $cacheKey = 'member_statistics_' . date('YmdH'); // 每小时更新
        $stats = cache($cacheKey);
        
        if (!$stats) {
            $stats = [
                'total_members' => Db::name('member')->where('closed', 0)->count(),
                'active_members' => Db::name('member')->where(['closed' => 0, 'status' => 1])->count(),
                'total_money' => Db::name('member')->where('closed', 0)->sum('money'),
                'total_tg_money' => Db::name('member')->where('closed', 0)->sum('tg_money'),
                'total_integral' => Db::name('member')->where('closed', 0)->sum('integral'),
                'distributor_count' => Db::name('member')->where(['closed' => 0, 'is_distribut' => 1])->count(),
                'today_new' => Db::name('member')->whereTime('create_time', 'today')->count(),
                'month_new' => Db::name('member')->whereTime('create_time', 'month')->count()
            ];
            
            // 缓存1小时
            cache($cacheKey, $stats, 3600);
        }
        
        return $stats;
    }
    
    /**
     * 获取会员组信息（缓存版本）
     * @return array
     */
    public static function getMemberGroups()
    {
        static $groups = null;
        
        if ($groups === null) {
            $cacheKey = 'member_groups_' . date('Ymd'); // 每天更新
            $groups = cache($cacheKey);
            
            if (!$groups) {
                $groups = Db::name('member_group')->column('id,group_name,point,discount');
                cache($cacheKey, $groups, 86400); // 缓存24小时
            }
        }
        
        return $groups;
    }
    
    /**
     * 验证会员数据
     * @param array $data 会员数据
     * @param string $scene 验证场景
     * @return array
     */
    public static function validateMemberData($data, $scene = 'add')
    {
        $validate = new \app\admin\validate\MemberValidate();
        
        if (!$validate->scene($scene)->check($data)) {
            return ['valid' => false, 'error' => $validate->getError()];
        }
        
        // 额外的业务验证
        if (isset($data['account'])) {
            $existUser = Db::name('member')->where('account', $data['account'])->find();
            if ($existUser && ($scene == 'add' || ($scene == 'edit' && $existUser['id'] != $data['id']))) {
                return ['valid' => false, 'error' => '用户名已存在'];
            }
        }
        
        if (isset($data['mobile']) && !empty($data['mobile'])) {
            $existMobile = Db::name('member')->where('mobile', $data['mobile'])->find();
            if ($existMobile && ($scene == 'add' || ($scene == 'edit' && $existMobile['id'] != $data['id']))) {
                return ['valid' => false, 'error' => '手机号已被注册'];
            }
        }
        
        if (isset($data['email']) && !empty($data['email'])) {
            $existEmail = Db::name('member')->where('email', $data['email'])->find();
            if ($existEmail && ($scene == 'add' || ($scene == 'edit' && $existEmail['id'] != $data['id']))) {
                return ['valid' => false, 'error' => '邮箱已被注册'];
            }
        }
        
        return ['valid' => true, 'error' => ''];
    }
    
    /**
     * 清除会员相关缓存
     * @param int $memberId 会员ID（可选）
     */
    public static function clearMemberCache($memberId = null)
    {
        $patterns = [
            'member_list_*',
            'member_query_*',
            'member_statistics_*',
            'member_groups_*'
        ];
        
        foreach ($patterns as $pattern) {
            // 这里可以根据具体的缓存驱动实现批量清除
            cache($pattern, null);
        }
        
        if ($memberId) {
            cache('member_info_' . $memberId, null);
        }
    }
    
    /**
     * 安全的密码加密
     * @param string $password 原始密码
     * @return string
     */
    public static function encryptPassword($password)
    {
        return md5(md5($password) . config('auth_key'));
    }
    
    /**
     * 生成用户Token
     * @param string $account 账号
     * @param string $password 加密后的密码
     * @return string
     */
    public static function generateUserToken($account, $password)
    {
        return md5(md5($account . $password) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
    }
    
    /**
     * 记录会员操作日志
     * @param int $memberId 会员ID
     * @param string $action 操作类型
     * @param string $detail 操作详情
     * @param array $extra 额外信息
     */
    public static function logMemberAction($memberId, $action, $detail, $extra = [])
    {
        $logData = [
            'member_id' => $memberId,
            'action' => $action,
            'detail' => $detail,
            'ip' => getIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'extra_data' => json_encode($extra),
            'create_time' => time()
        ];
        
        try {
            Db::name('member_action_log')->insert($logData);
        } catch (\Exception $e) {
            // 日志记录失败不影响主要业务
            error_log('Member action log failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 批量处理会员数据格式化
     * @param array $members 会员数据数组
     * @return array
     */
    public static function formatMemberListData($members)
    {
        if (empty($members)) {
            return [];
        }
        
        // 批量获取IP地址
        $ips = array_unique(array_column($members, 'last_login_ip'));
        $ipLocations = self::batchGetIpLocation($ips);
        
        // 格式化数据
        foreach ($members as &$member) {
            // 格式化时间
            $member['last_login_time'] = date("Y-m-d H:i:s", $member['last_login_time']);
            $member['create_time'] = date("Y-m-d H:i:s", $member['create_time']);
            
            // 设置IP地址信息
            $member['ipaddr'] = $ipLocations[$member['last_login_ip']] ?? ['country' => '未知', 'area' => '地区'];
            
            // 格式化金额
            $member['money'] = number_format($member['money'] / 100, 2);
            $member['tg_money'] = number_format($member['tg_money'] / 100, 2);
            
            // 设置状态文本
            $member['status_text'] = $member['status'] == 1 ? '正常' : '禁用';
            $member['distribut_text'] = $member['is_distribut'] == 1 ? '是' : '否';
        }
        
        return $members;
    }
}


