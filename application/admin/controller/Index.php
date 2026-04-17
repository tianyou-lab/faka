<?php

namespace app\admin\controller;

use think\Config;
use think\Loader;
use think\Db;

class Index extends Base
{
    public function index()
    {
        return $this->fetch('/index');
    }

    /**
     * 后台首页
     */
    public function indexPage()
    {
        $cacheKey    = 'admin_dashboard_' . date('YmdHi') . '_' . floor(date('i') / 10);
        $cachedStats = cache($cacheKey);

        if (!$cachedStats) {
            $today       = strtotime(date("Y-m-d"), time());
            $month_start = strtotime(date("Y-m-01"));
            $month_end   = strtotime("+1 month -1 seconds", $month_start);
            $shang_start = strtotime("-1 month", $month_start);
            $shang_end   = strtotime("+1 month -1 seconds", $shang_start);

            $stats_sql = "
                SELECT 
                    SUM(CASE WHEN mstatus <> 2 AND update_time >= {$month_start} AND update_time <= {$month_end} THEN mamount ELSE 0 END) as ben,
                    SUM(CASE WHEN mstatus <> 2 AND update_time >= {$shang_start} AND update_time <= {$shang_end} THEN mamount ELSE 0 END) as shang,
                    SUM(CASE WHEN mstatus <> 2 AND maddtype = 5 AND update_time >= {$today} AND update_time <= " . ($today + 86400) . " THEN mamount ELSE 0 END) as benyue,
                    SUM(CASE WHEN mstatus <> 2 AND maddtype = 5 AND update_time >= " . ($today - 86400) . " AND update_time <= " . ($today - 1) . " THEN mamount ELSE 0 END) as shangyue,
                    SUM(CASE WHEN mstatus <> 2 AND DATE(FROM_UNIXTIME(update_time)) = CURDATE() THEN mamount ELSE 0 END) as jinri,
                    SUM(CASE WHEN mstatus <> 2 AND DATE(FROM_UNIXTIME(update_time)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN mamount ELSE 0 END) as zuori,
                    COUNT(CASE WHEN mstatus <> 2 THEN 1 END) as infonum
                FROM think_info
            ";
            $stats_result = Db::query($stats_sql);
            $stats        = $stats_result[0];

            $goods_cache_key = 'goods_count_' . date('YmdH');
            $flnum = cache($goods_cache_key);
            if (!$flnum) {
                $flnum = Db::name('fl')->count();
                cache($goods_cache_key, $flnum, 3600);
            }

            $cachedStats = [
                'ben'        => $stats['ben'] ?: 0,
                'shang'      => $stats['shang'] ?: 0,
                'benyue'     => $stats['benyue'] ?: 0,
                'shangyue'   => $stats['shangyue'] ?: 0,
                'info_jinri' => [['jinri' => $stats['jinri'] ?: 0]],
                'info_zuori' => [['zuori' => $stats['zuori'] ?: 0]],
                'info_fl'    => [['flnum' => $flnum]],
                'info_info'  => [['infonum' => $stats['infonum'] ?: 0]],
            ];
            cache($cacheKey, $cachedStats, 600);
        }

        $ben        = $cachedStats['ben'];
        $shang      = $cachedStats['shang'];
        $benyue     = $cachedStats['benyue'];
        $shangyue   = $cachedStats['shangyue'];
        $info_jinri = $cachedStats['info_jinri'];
        $info_zuori = $cachedStats['info_zuori'];
        $info_fl    = $cachedStats['info_fl'];
        $info_info  = $cachedStats['info_info'];

        $noti = '';
        if (getadminpath() == "houtai") {
            $noti .= "请尽快修改默认后台目录！";
        }
        if (session('username') == "admin") {
            $noti .= "请尽快修改后台初始管理员账号！";
        }

        $info = [
            'web_server' => $_SERVER['SERVER_SOFTWARE'],
            'onload'     => ini_get('upload_max_filesize'),
            'think_v'    => THINK_VERSION,
            'phpversion' => phpversion(),
            'ben'        => $ben,
            'shang'      => $shang,
            'benyue'     => $benyue,
            'shangyue'   => $shangyue,
            'noti'       => $noti,
        ];

        $this->assign('info_jinri', $info_jinri);
        $this->assign('info_zuori', $info_zuori);
        $this->assign('info_fl', $info_fl);
        $this->assign('info_info', $info_info);
        $this->assign('info', $info);
        return $this->fetch('index');
    }

    /**
     * 修改用户名
     */
    public function editadminname()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $user  = Db::name('admin')->where('id=' . session('uid'))->find();
            if (md5(md5($param['password']) . config('auth_key')) != $user['password']) {
                return json(['code' => -1, 'url' => '', 'msg' => '密码错误']);
            } else {
                $pwd['username'] = $param['username'];
                Db::name('admin')->where('id=' . $user['id'])->update($pwd);
                session(null);
                cache('db_config_data', null);
                return json(['code' => 1, 'url' => 'index/index', 'msg' => '用户名修改成功']);
            }
        }
        return $this->fetch();
    }

    /**
     * 修改密码
     */
    public function editpwd()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $user  = Db::name('admin')->where('id=' . session('uid'))->find();
            if (md5(md5($param['old_password']) . config('auth_key')) != $user['password']) {
                return json(['code' => -1, 'url' => '', 'msg' => '旧密码错误']);
            } else {
                $pwd['password'] = md5(md5($param['password']) . config('auth_key'));
                Db::name('admin')->where('id=' . $user['id'])->update($pwd);
                session(null);
                cache('db_config_data', null);
                return json(['code' => 1, 'url' => 'index/index', 'msg' => '密码修改成功']);
            }
        }
        return $this->fetch();
    }

    /**
     * 设置超级密码
     */
    public function setsuperpwd()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $user  = Db::name('admin')->where('id=' . session('uid'))->find();
            if (!empty($user['superpassword'])) {
                return json(['code' => -1, 'url' => '', 'msg' => '小子你想干啥？超级密码已经有了']);
            }
            if (md5(md5($param['old_password']) . config('auth_key')) != $user['password']) {
                return json(['code' => -1, 'url' => '', 'msg' => '登录密码错误']);
            } else {
                $pwd['superpassword'] = md5(md5($param['password']) . config('auth_key'));
                if ($pwd['superpassword'] == md5(md5($param['old_password']) . config('auth_key'))) {
                    return json(['code' => -1, 'url' => '', 'msg' => '超级密码不能和登录密码一样']);
                }
                Db::name('admin')->where('id=' . $user['id'])->update($pwd);
                cache('db_config_data', null);
                return json(['code' => 1, 'url' => 'index/index', 'msg' => '超级密码设置成功']);
            }
        }
        return $this->fetch();
    }

    /**
     * 修改超级密码
     */
    public function editsuperpwd()
    {
        if (request()->isAjax()) {
            $param = input('post.');
            $user  = Db::name('admin')->where('id=' . session('uid'))->find();
            if (md5(md5($param['old_password']) . config('auth_key')) != $user['superpassword']) {
                return json(['code' => -1, 'url' => '', 'msg' => '旧超级密码错误']);
            } else {
                $pwd['superpassword'] = md5(md5($param['password']) . config('auth_key'));
                if ($pwd['superpassword'] == $user['password']) {
                    return json(['code' => -1, 'url' => '', 'msg' => '超级密码不能和登录密码一样']);
                }
                Db::name('admin')->where('id=' . $user['id'])->update($pwd);
                session(null);
                cache('db_config_data', null);
                return json(['code' => 1, 'url' => 'index/index', 'msg' => '超级密码修改成功']);
            }
        }
        return $this->fetch();
    }

    /**
     * 修改后台目录
     */
    public function changeAdminPath()
    {
        if (request()->isAjax()) {
            $newpath = input('param.newadminpath');
            $newpath = myTrim($newpath);
            if ($newpath == "admin") {
                return json(['code' => 0, 'msg' => '修改失败，admin不能作为后台目录']);
            }
            if (!preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u", $newpath)) {
                return json(['code' => 0, 'msg' => '修改失败，目录只支持数字，字母，汉字']);
            }
            $content  = "<?php use think\\Route;Route::rule('" . $newpath . "','admin/login/index');";
            $mcontent = "<?php use think\\Route;Route::rule('m" . $newpath . "','madmin/index/login');";
            if (file_put_contents(ROOT_PATH . 'application/admin.php', $content)) {
                file_put_contents(ROOT_PATH . 'application/madmin.php', $mcontent);
                return json(['code' => 1, 'msg' => '修改成功，pc目录' . $newpath . '  手机版目录m' . $newpath]);
            } else {
                return json(['code' => 0, 'msg' => '修改失败']);
            }
        }
        return $this->fetch('adminpath');
    }

    /**
     * 清除缓存
     */
    public function clear()
    {
        if (delete_dir_file(CACHE_PATH) && delete_dir_file(TEMP_PATH)) {
            return json(['code' => 1, 'msg' => '清除缓存成功']);
        } else {
            return json(['code' => 0, 'msg' => '清除缓存失败']);
        }
    }

    /**
     * 心跳检查接口
     */
    public function heartbeat()
    {
        try {
            $sessionCheck = \app\admin\common\SessionHelper::checkAdminSession();
            if ($sessionCheck['valid']) {
                $adminInfo = \app\admin\common\SessionHelper::getCurrentAdmin();
                return json([
                    'code' => 1,
                    'msg'  => '会话正常',
                    'data' => [
                        'username'          => $adminInfo['username'],
                        'login_time'        => date('Y-m-d H:i:s', $adminInfo['login_time']),
                        'login_ip'          => $adminInfo['login_ip'],
                        'session_remaining' => 86400 - (time() - $adminInfo['login_time']),
                    ],
                ]);
            } else {
                return json(['code' => 0, 'msg' => $sessionCheck['message']]);
            }
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '会话检查异常：' . $e->getMessage()]);
        }
    }
}
