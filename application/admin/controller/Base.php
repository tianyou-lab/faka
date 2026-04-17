<?php

namespace app\admin\controller;

use app\admin\model\Node;
use think\Controller;
use think\Db;

class Base extends Controller
{
    public function _initialize()
    {
        $sessionCheck = \app\admin\common\SessionHelper::checkAdminSession();
        if (!$sessionCheck['valid']) {
            $this->error($sessionCheck['message'], $sessionCheck['redirect']);
        }

        if (session('login_ip') && session('login_ip') != getIP()) {
            writelog(session('uid'), session('username'), '检测到IP地址变化：' . session('login_ip') . ' -> ' . getIP(), 1);
        }

        $auth       = new \com\Auth();
        $module     = strtolower(request()->module());
        $controller = strtolower(request()->controller());
        $action     = strtolower(request()->action());
        $url        = $module . "/" . $controller . "/" . $action;

        if (session('uid') != 1) {
            if (!in_array($url, ['admin/index/index', 'admin/index/indexpage'])) {
                if (!$auth->check($url, session('uid'))) {
                    $this->error('抱歉，您没有操作权限');
                }
            }
        }

        $node = new Node();
        $this->assign([
            'username' => session('username'),
            'portrait' => session('portrait'),
            'rolename' => session('rolename'),
            'menu'     => $node->getMenu(session('rule')),
        ]);

        $cacheKey = 'site_config_' . date('YmdH');
        $config   = cache($cacheKey);
        if (!$config) {
            $config = load_config();
            cache($cacheKey, $config, 3600);
        }
        config($config);

        if (config('web_site_close') == 0 && session('uid') != 1) {
            $this->error('站点已经关闭，请稍后访问~');
        }

        if (config('admin_allow_ip') && session('uid') != 1) {
            if (in_array(getIP(), explode('#', config('admin_allow_ip')))) {
                $this->error('403:禁止访问');
            }
        }

        $hasAdmin = Db::name('admin')->where('id', session('uid'))->find();
        if (empty($hasAdmin['superpassword']) && $url != "admin/index/setsuperpwd") {
            $this->redirect('index/setsuperpwd');
        }
    }
}