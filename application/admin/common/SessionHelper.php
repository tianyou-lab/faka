<?php
/**
 * 后台会话管理助手类
 * 用于解决管理员登录后界面显示问题
 */
namespace app\admin\common;

class SessionHelper
{
    /**
     * 检查管理员会话是否有效
     * @return array 返回检查结果
     */
    public static function checkAdminSession()
    {
        $result = [
            'valid' => false,
            'message' => '',
            'redirect' => ''
        ];
        
        // 检查基本会话信息
        if(!session('uid') || !session('username')){
            $result['message'] = '会话信息不完整';
            $result['redirect'] = url('login/index');
            return $result;
        }
        
        // 检查会话过期时间
        if(session('login_time') && (time() - session('login_time')) > 86400){
            session(null);
            $result['message'] = '登录已过期';
            $result['redirect'] = url('login/index');
            return $result;
        }
        
        // 检查管理员信息是否存在
        $hasAdmin = \think\Db::name('admin')->where('id', session('uid'))->find();
        if(!$hasAdmin){
            session(null);
            $result['message'] = '管理员信息不存在';
            $result['redirect'] = url('login/index');
            return $result;
        }
        
        // 检查Token验证
        $token = md5($hasAdmin['username'] . $hasAdmin['password'].$_SERVER['HTTP_HOST'].date("Y-m-d").getIP());
        if($token != session('admintoken')){
            // 记录Token验证失败日志
            writelog(session('uid'), session('username'), 'Token验证失败，可能的原因：IP变化、日期变化或会话异常', 2);
            session(null);
            $result['message'] = '登录状态已失效';
            $result['redirect'] = url('login/index');
            return $result;
        }
        
        // 检查账号状态
        if($hasAdmin['status'] != 1){
            session(null);
            $result['message'] = '账号已被禁用';
            $result['redirect'] = url('login/index');
            return $result;
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * 刷新管理员会话
     * @param int $uid 管理员ID
     * @return bool
     */
    public static function refreshAdminSession($uid)
    {
        $hasAdmin = \think\Db::name('admin')->where('id', $uid)->find();
        if(!$hasAdmin){
            return false;
        }
        
        // 重新生成Token
        $token = md5($hasAdmin['username'] . $hasAdmin['password'].$_SERVER['HTTP_HOST'].date("Y-m-d").getIP());
        
        // 更新会话信息
        session('admintoken', $token);
        session('login_time', time());
        session('login_ip', getIP());
        
        return true;
    }
    
    /**
     * 安全退出登录
     */
    public static function safeLogout()
    {
        // 记录退出日志
        if(session('uid') && session('username')){
            writelog(session('uid'), session('username'), '管理员安全退出登录', 1);
        }
        
        // 清除所有会话
        session(null);
        
        // 清除配置缓存
        cache('db_config_data', null);
        cache('site_config_' . date('YmdH'), null);
    }
    
    /**
     * 获取当前管理员信息
     * @return array|null
     */
    public static function getCurrentAdmin()
    {
        if(!session('uid')){
            return null;
        }
        
        return [
            'id' => session('uid'),
            'username' => session('username'),
            'portrait' => session('portrait'),
            'rolename' => session('rolename'),
            'login_time' => session('login_time'),
            'login_ip' => session('login_ip')
        ];
    }
    
    /**
     * 检查是否需要设置超级密码
     * @return bool
     */
    public static function needSetSuperPassword()
    {
        if(!session('uid')){
            return false;
        }
        
        $hasAdmin = \think\Db::name('admin')->where('id', session('uid'))->find();
        return empty($hasAdmin['superpassword']);
    }
}


