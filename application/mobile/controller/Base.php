<?php
namespace app\mobile\controller;

use app\jingdian\model\BaseModel;
use think\Controller;
use think\Db;

class Base extends Controller
{
  public function _initialize()
  {

    $config = cache('db_config_data');
    if (!$config) {
      $config = load_config();
      cache('db_config_data', $config);
    }
    config($config);
    $MainMobile = config('WEB_MOBILE');
    config('MainMobile', $MainMobile);
    config('web_music', htmlspecialchars_decode(config('web_music')));
    config('web_site_cnzz', htmlspecialchars_decode(config('web_site_cnzz')));
    //--------分站功能已移除--------

    $pwd = self::generate_password();
    $cookieToken = cookie('tokenid');
    if (empty($cookieToken)) {
      cookie('tokenid', $pwd);
    }
    if (strstr($cookieToken, "|")) {
      cookie('tokenid', $pwd);
    }
    if (strlen($cookieToken) < 64) {
      cookie('tokenid', $pwd);
    }
    $cookieToken = cookie('tokenid');
    $model = new BaseModel();

    $wapbanner = $model->getWAPbanner();
    foreach ($wapbanner as &$v) {
      $v['images'] = str_replace('\\', '/', $v['images']);
    }
    $this->assign('wapbanner', $wapbanner);
    $kamibanner = $model->getKmbanner();
    foreach ($kamibanner as &$v) {
      $v['images'] = str_replace('\\', '/', $v['images']);
    }
    $this->assign('kamibanner', $kamibanner);


    $usertoken = cookie('usertoken');
    $module     = strtolower(request()->module());
    $controller = strtolower(request()->controller());
    $action     = strtolower(request()->action());
    $url        = $module . "/" . $controller . "/" . $action;
    if (config('web_reg_type') == 0) {
      //不能注册
      if (in_array($url, ['mobile/user/index', 'mobile/user/reg'])) {

        $this->error('管理员未开启会员系统模式', url('mobile/index/index'));
      }
    } elseif (config('web_reg_type') == 1 || config('web_reg_type') == 2) {
     if (session('useraccount.id') && session('useraccount.account')) {
        $hasUser = Db::name('member')->where('token', $usertoken)->find();
        $token = md5(md5($hasUser['account'] . $hasUser['password']) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
        if ($usertoken == $token) {
          if ($hasUser['status'] == 0) {
            session('useraccount', null);
            cookie('usertoken', null);
            $this->error('此账户已禁用', url('mobile/index/index'));
          }
          self::checkMemberGroup(session('useraccount.id'));
          self::checkMemberMarket(session('useraccount.id'));
          session('useraccount', $hasUser);
          cookie('usertoken', $token);
          $this->assign('useraccount', session('useraccount'));
        } else {
          session('useraccount', null);
          cookie('usertoken', null);
        }
      } else if (strlen($usertoken) == 32) {
        $hasUser = Db::name('member')->where('token', $usertoken)->find();
        $token = md5(md5($hasUser['account'] . $hasUser['password']) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
        if ($usertoken == $token) {
          if ($hasUser['status'] == 0) {
            session('useraccount', null);
            cookie('usertoken', null);
            $this->error('此账户已禁用', url('mobile/index/index'));
          }
          self::checkMemberGroup(session('useraccount.id'));
          self::checkMemberMarket(session('useraccount.id'));
          session('useraccount', $hasUser);
          cookie('usertoken', $token);
          $this->assign('useraccount', session('useraccount'));
        } else {
          session('useraccount', null);
          cookie('usertoken', null);
        }
      }

      if (!session('useraccount.id') && !session('useraccount.account')) {
        if (in_array($url, ['mobile/user/usorder', 'mobile/user/getpass', 'mobile/user/uscenter', 'mobile/user/usintegrallog', 'mobile/user/uspay', 'mobile/user/uspaylog', 'mobile/user/usupdatepass'])) {
          // $this->error('请先登录',url('mobile/user/index'));	                
        }
      }
    }





    //分销session
    $pidParam = inputself();
    if (isset($pidParam['pid'])) {
      session('userpid', $pidParam['pid']);
    }






    $cate = $model->getAllCate();
    $href = $model->getYqHref();
    $this->assign('cate', $cate);
    $this->assign('href', $href);
  }

  /*
     * 更新用户等级
     */
  public function checkMemberGroup($memberid)
  {
    $hasUser = Db::name('member')->where('id', $memberid)->find();
    $groupUser = Db::name('member_group')->where('id', $hasUser['group_id'])->find();
    $newGroup = Db::name('member_group')->where('point', 'elt', $hasUser['integral'])->order('point desc')->limit(1)->find();
    if ($groupUser['point'] < 0) {
      //代理不升级
    } else {
      if ($groupUser && $newGroup) {
        if ($groupUser['point'] < $newGroup['point']) {
          //更新用户等级
          Db::name('member')->where('id', $memberid)->update(['group_id' => $newGroup['id']]);
        }
      } else if ($newGroup) {
        //更新用户等级
        Db::name('member')->where('id', $memberid)->update(['group_id' => $newGroup['id']]);
      }
    }
  }

  /*
     * 更新分销商
     */
  public function checkMemberMarket($memberid)
  {
    $hasUser = Db::name('member')->where('id', $memberid)->find();
    if ($hasUser['integral'] >= config('fx_point') && $hasUser['is_distribut'] == 0) {
      //更新用户为分销商
      Db::name('member')->where('id', $memberid)->update(['is_distribut' => 1]);
    }
  }

  public function generate_password($length = 64)
  {

    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
      $password .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $password;
  }
}

