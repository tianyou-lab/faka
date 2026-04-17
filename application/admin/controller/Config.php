<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use think\Db;

class Config extends Base
{
    /**
     * 获取配置参数
     */
    public function index()
    {
        $configModel = new ConfigModel();
        $list   = $configModel->getAllConfig();
        $config = [];
        foreach ($list as $k => $v) {
            $config[trim($v['name'])] = $v['value'];
        }
        $this->normalizeUnifiedSiteName($config);
        $this->assign('config', $config);
        return $this->fetch();
    }

    private function loadConfig()
    {
        $configModel = new ConfigModel();
        $list   = $configModel->getAllConfig();
        $config = [];
        foreach ($list as $k => $v) {
            $config[trim($v['name'])] = $v['value'];
        }
        $this->normalizeUnifiedSiteName($config);
        $this->assign('config', $config);
    }

    private function normalizeUnifiedSiteName(&$config)
    {
        if (!isset($config['unified_site_name']) || trim((string)$config['unified_site_name']) === '') {
            if (!empty($config['shop_name'])) {
                $config['unified_site_name'] = $config['shop_name'];
            } elseif (!empty($config['m_title'])) {
                $config['unified_site_name'] = $config['m_title'];
            } elseif (!empty($config['web_site_title'])) {
                $config['unified_site_name'] = $config['web_site_title'];
            } elseif (!empty($config['web_title'])) {
                $config['unified_site_name'] = $config['web_title'];
            } else {
                $config['unified_site_name'] = '';
            }
        }
    }

    public function basic()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function content()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function system()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function captcha()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function payment()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function token()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function frontend()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function email()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function member()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function color()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function template()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function waptemplate()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    public function upload()
    {
        $this->loadConfig();
        return $this->fetch();
    }

    /**
     * 批量保存配置
     */
    public function save($config)
    {
        if (isset($config['unified_site_name'])) {
            $unifiedSiteName          = trim((string)$config['unified_site_name']);
            $config['web_title']      = $unifiedSiteName;
            $config['m_title']        = $unifiedSiteName;
            $config['shop_name']      = $unifiedSiteName;
            $config['web_site_title'] = $unifiedSiteName;
        }
        if (isset($config['list_rows'])) {
            $config = [
                'list_rows'     => $config['list_rows'],
                'select_mcard'  => isset($config['select_mcard']) ? ($config['select_mcard'] == 'on' || $config['select_mcard'] == '1' ? 1 : 'off') : 'off',
                'select_mobile' => isset($config['select_mobile']) ? ($config['select_mobile'] == 'on' || $config['select_mobile'] == '1' ? 1 : 'off') : 'off',
                'select_cookie' => isset($config['select_cookie']) ? ($config['select_cookie'] == 'on' || $config['select_cookie'] == '1' ? 1 : 'off') : 'off',
                'select_openid' => isset($config['select_openid']) ? ($config['select_openid'] == 'on' || $config['select_openid'] == '1' ? 1 : 'off') : 'off',
            ];
        }
        if (isset($config['shorturl'])) {
            if ($config['shorturl'] == 1) {
                $mobile   = "<?php use think\\Route;Route::rule('mg/:mpid','mobile/Index/goodsdetail');Route::rule('mg','mobile/Index/goodsdetail');Route::rule('mc/:lmid','mobile/index/categorybyid');";
                $jingdian = "<?php use think\\Route;Route::rule('pg/:mpid','jingdian/Index/goodsdetail');Route::rule('pg','jingdian/Index/goodsdetail');Route::rule('pc/:lmid','jingdian/index/goodscategory');Route::rule('createOrder','jingdian/Mqapi/createOrder');Route::rule('getOrder','jingdian/Mqapi/getOrder');Route::rule('checkOrder','jingdian/Mqapi/checkOrder');Route::rule('closeOrder','jingdian/Mqapi/closeOrder');Route::rule('getState','jingdian/Mqapi/getState');Route::rule('appHeart','jingdian/Mqapi/appHeart');Route::rule('appPush','jingdian/Mqapi/appPush');";
                file_put_contents(ROOT_PATH . 'application/jingdian.php', $jingdian);
                file_put_contents(ROOT_PATH . 'application/mobile.php', $mobile);
            } else {
                $jingdian = "<?php use think\\Route;Route::rule('createOrder','jingdian/Mqapi/createOrder');Route::rule('getOrder','jingdian/Mqapi/getOrder');Route::rule('checkOrder','jingdian/Mqapi/checkOrder');Route::rule('closeOrder','jingdian/Mqapi/closeOrder');Route::rule('getState','jingdian/Mqapi/getState');Route::rule('appHeart','jingdian/Mqapi/appHeart');Route::rule('appPush','jingdian/Mqapi/appPush');";
                file_put_contents(ROOT_PATH . 'application/jingdian.php', $jingdian);
                file_put_contents(ROOT_PATH . 'application/mobile.php', '');
            }
        }

        $configModel = new ConfigModel();
        if ($config && is_array($config)) {
            foreach ($config as $name => $value) {
                if ($value != '***密文***') {
                    $map = ['name' => $name];
                    $configModel->SaveConfig($map, $value);
                }
            }
        }
        cache('db_config_data', null);
        cache('site_config_' . date('YmdH'), null);
        $this->success('保存成功！');
    }
}