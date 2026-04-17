<?php
namespace app\jingdian\controller;

use app\jingdian\model\BaseModel;
use app\jingdian\model\GoodsListModel;
use think\Controller;
use think\Db;

class Base extends Controller
{
  public function _initialize()
  {


    // 优化配置加载缓存
    $cacheKey = 'site_config_' . date('YmdH'); // 每小时更新一次
    $config = cache($cacheKey);
    if (!$config) {
      $config = load_config();
      cache($cacheKey, $config, 3600); // 缓存1小时
    }
    config($config);
    $MainMobile = config('WEB_MOBILE');
    config('MainMobile', $MainMobile);
    $pcbanner = config('web_pcbanner');
    $pcbanner = str_replace('\\', '/', $pcbanner);
    config('web_pcbanner', $pcbanner);
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

    // 优化：使用缓存减少重复数据库查询
    $baseCacheKey = 'base_data_0_' . date('Hi'); // 每分钟更新
    $baseData = cache($baseCacheKey);
    
    if (!$baseData) {
      $model = new BaseModel();
      $baseData = [
        'cate' => $model->getAllCate(),
        'href' => $model->getYqHref(),
        'navigation' => $model->getAllNavigation()
      ];
      
      
      $GoodList = new GoodsListModel();
      $data_flName = $GoodList->getAllGoodsName();
      $baseData['GoodsLmShop'] = $GoodList->getAllGoods($data_flName);
      
      // 缓存数据
      cache($baseCacheKey, $baseData, 60); // 缓存1分钟
    }
    
    // 赋值到模板
    $this->assign('navigation', $baseData['navigation']);
    $this->assign('GoodsLmShop', $baseData['GoodsLmShop']);

    $usertoken = !empty(input('adminusertoken')) ? input('adminusertoken') : cookie('usertoken');

    $module     = strtolower(request()->module());
    $controller = strtolower(request()->controller());
    $action     = strtolower(request()->action());
    $url        = $module . "/" . $controller . "/" . $action;
	

    if (config('web_reg_type') == 0) {
      //不能注册
      if (in_array($url, ['jingdian/user/index', 'jingdian/user/reg'])) {

        $this->error('管理员未开启会员系统模式', url('jingdian/index/index'));
      }
    } elseif (config('web_reg_type') == 1 || config('web_reg_type') == 2) {
      if (session('useraccount.id') && session('useraccount.account')) {
        $hasUser = Db::name('member')->where('id', session('useraccount.id'))->find();
        $token = md5(md5($hasUser['account'] . $hasUser['password']) . md5(date("Y-m-d")) . config('auth_key') . config('token') . $_SERVER['HTTP_HOST']);
        if ($usertoken == $token) {
          if ($hasUser['status'] == 0) {
            session('useraccount', null);
            cookie('usertoken', null);
            $this->error('此账户已禁用', url('jingdian/index/index'));
          }
          self::checkMemberGroup(session('useraccount.id'));
          self::checkMemberMarket(session('useraccount.id'));
          $this->assign('useraccount', session('useraccount'));
          session('useraccount', $hasUser);
          cookie('usertoken', $token);
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
            $this->error('此账户已禁用', url('jingdian/index/index'));
          }
          self::checkMemberGroup($hasUser['id']);
          self::checkMemberMarket($hasUser['id']);
          session('useraccount', $hasUser);
          cookie('usertoken', $token);
        } else {
          session('useraccount', null);
          cookie('usertoken', null);
        }
      }

      if (!session('useraccount.id') && !session('useraccount.account')) {
        if (in_array($url, ['jingdian/user/usorder', 'jingdian/user/getpass', 'jingdian/user/uscenter', 'jingdian/user/usintegrallog', 'jingdian/user/uspay', 'jingdian/user/uspaylog', 'jingdian/user/usupdatepass'])) {
          $this->error('请先登录', url('jingdian/user/index'));
        }
      }
    }

    //分销session
    $pidParam = inputself();
    if (isset($pidParam['pid'])) {
      session('userpid', $pidParam['pid']);
    }

    $this->assign('cate', $baseData['cate']);
    $this->assign('href', $baseData['href']);
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
    // 密码字符集，可任意添加你需要的字符 
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
      // 这里提供两种字符获取方式 
      // 第一种是使用 substr 截取$chars中的任意一位字符； 
      // 第二种是取字符数组 $chars 的任意元素 
      // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1); 
      $password .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $password;
  }
}

