<?php

namespace app\admin\controller;

use app\admin\model\UserType;
use think\Controller;
use think\Db;
use think\Request;
use org\Verify;
use com\Geetestlib;

class Login extends Controller
{
    public function _initialize()
    {
        $config = cache('db_config_data');
        if (!$config) {
            $config = load_config();
            cache('db_config_data', $config);
        }
        config($config);
    }

    /**
     * 登录页面
     */
    public function index()
    {
        $request = Request::instance();
        if ($request->pathinfo() != getadminpath() . '.html' && $request->pathinfo() != getadminpath()) {
            abort(404, '页面不存在');
        }
        $this->assign('verify_type', config('verify_type'));
        return $this->fetch('/login');
    }

    /**
     * 登录操作
     */
    public function doLogin()
    {
        $referer = isset($_SERVER["HTTP_REFERER"]) ? (string)$_SERVER["HTTP_REFERER"] : "";
        if ($referer !== "") {
            $refererHost = (string)parse_url($referer, PHP_URL_HOST);
            $refererPath = trim((string)parse_url($referer, PHP_URL_PATH), "/");
            $currentHost = (string)parse_url("http://" . (isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : ""), PHP_URL_HOST);
            $adminPath = trim(getadminpath(), "/");

            $hostValid = ($refererHost === "" || $currentHost === "" || strcasecmp($refererHost, $currentHost) === 0);
            $pathValid = ($refererPath === "" || preg_match("#(?:^|/)" . preg_quote($adminPath, "#") . "(?:\\.html)?$#", $refererPath));

            if (!$hostValid || !$pathValid) {
                return json(["code" => 404, "url" => "404.html", "msg" => "404页面丢失"]);
            }
        }

        $username = input("param.username");
        $password = input("param.password");

        if (config('verify_type') == 1) {
            $code = input("param.code");
        }

        $result = $this->validate(compact('username', 'password'), 'AdminValidate');
        if (true !== $result) {
            return json(['code' => -5, 'url' => '', 'msg' => $result]);
        }

        $verify = new Verify();
        if (config('verify_type') == 1) {
            if (!$code) {
                return json(['code' => -4, 'url' => '', 'msg' => '请输入验证码']);
            }
            if (!$verify->check($code)) {
                return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
            }
        }

        $hasUser = Db::name('admin')->where('username', $username)->find();
        if (empty($hasUser)) {
            return json(['code' => -1, 'url' => '', 'msg' => '管理员不存在']);
        }

        if (md5(md5($password) . config('auth_key')) != $hasUser['password']) {
            writelog($hasUser['id'], $username, '用户【' . $username . '】登录失败：密码错误', 2);
            return json(['code' => -2, 'url' => '', 'msg' => '账号或密码错误']);
        }

        if (1 != $hasUser['status']) {
            writelog($hasUser['id'], $username, '用户【' . $username . '】登录失败：该账号被禁用', 2);
            return json(['code' => -6, 'url' => '', 'msg' => '该账号被禁用']);
        }

        $user  = new UserType();
        $info  = $user->getRoleInfo($hasUser['groupid']);
        $token = md5($hasUser['username'] . $hasUser['password'] . $_SERVER['HTTP_HOST'] . date("Y-m-d") . getIP());

        session('uid', $hasUser['id']);
        session('username', $hasUser['username']);
        session('password', $hasUser['password']);
        session('portrait', $hasUser['portrait']);
        session('rolename', $info['title']);
        session('rule', $info['rules']);
        session('name', $info['name']);
        session("admintoken", $token);
        session('login_time', time());
        session('login_ip', getIP());

        $param = [
            'loginnum'        => $hasUser['loginnum'] + 1,
            'last_login_ip'   => getIP(),
            'last_login_time' => time(),
            'token'           => $token,
        ];
        Db::name('admin')->where('id', $hasUser['id'])->update($param);

        $expireTime = time() - 7776000;
        Db::name("pay_order")->where("create_date <" . $expireTime)->delete();
        Db::name("log")->where("add_time <" . $expireTime)->delete();
        Db::name("addmaillog")->where("create_time <" . $expireTime)->delete();
        Db::name("member_integral_log")->where("create_time <" . $expireTime)->delete();
        Db::name("member_login_log")->where("create_time <" . $expireTime)->delete();
        Db::name("member_payorder")->where("create_time <" . $expireTime)->delete();
        Db::name("member_money_log")->where("create_time <" . $expireTime)->delete();
        Db::name('info')->where('create_time', 'elt', $expireTime)->limit(1000)->delete();
        Db::name('mail')->where('create_time', 'elt', $expireTime)->where('mis_use=1')->limit(1000)->delete();

        $dir = ROOT_PATH . "upload/";
        $this->z_del_file_by_ctime($dir, 7776000);

        writelog($hasUser['id'], session('username'), '用户【' . session('username') . '】登录成功', 1);
        return json(['code' => 1, 'url' => url('index/index'), 'msg' => '登录成功！']);
    }

    /**
     * 验证码
     */
    public function checkVerify()
    {
        $verify           = new Verify();
        $verify->imageH   = 32;
        $verify->imageW   = 100;
        $verify->length   = 4;
        $verify->useCurve = false;
        $verify->useNoise = false;
        $verify->fontSize = 14;
        return $verify->entry();
    }

    /**
     * 退出登录
     */
    public function loginOut()
    {
        \app\admin\common\SessionHelper::safeLogout();
        $this->redirect(url('login/index'));
    }

    /**
     * 删除文件夹下指定时间前创建的文件
     * @param string $dir 目录路径
     * @param int    $n   过期时间（秒）
     */
    private function z_del_file_by_ctime($dir, $n)
    {
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (false !== ($file = readdir($dh))) {
                    if ($file != "." && $file != "..") {
                        $fullpath = $dir . "/" . $file;
                        if (!is_dir($fullpath)) {
                            $filedate = filemtime($fullpath);
                            $minutes  = round((time() - $filedate) / 60);
                            if ($minutes > $n) {
                                @unlink($fullpath);
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }
    }
}
