<?php
namespace app\api\controller;
use think\Config;
use think\Db;

class Login extends Base
{
	public function index(){
		$param=inputself();
		$username=$param['username'];
		$password=$param['password'];
		$hasUser = Db::name('admin')->where('username', $username)->find();
        if(empty($hasUser)){
            return json(['code' => -1, 'url' => '', 'msg' => '管理员不存在']);
        }
        if(md5(md5($password) . config('auth_key')) != $hasUser['password']){          
            return json(['code' => -2, 'url' => '', 'msg' => '账号或密码错误']);
        }
            return json(['code' => -1, 'url' => '', 'msg' => '管理员不存在']);
        //return json(['code' => 1, 'url' => '', 'msg' => '登录成功']);
	}
}