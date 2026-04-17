<?php
/**
 * 会员注册修复补丁
 * 用于修复注册功能的验证问题
 */
namespace app\jingdian\controller;

use app\admin\model\MemberModel;
use app\admin\model\MemberGroupModel;
use org\Verify;
use think\Db;

trait UserRegisterFix 
{
    /**
     * 优化的注册方法
     */
    public function regFixed(){
        if(config('web_reg_xingshi')==1){
            //仅手机号
            return $this->redirect(url('@jingdian/user/regmobile'),302);
        }
        
        if(!request()->isPost()){     		   		
            if(session('useraccount.account')){
                return $this->redirect(url('@jingdian/index/index'),302);
            }
            return $this->fetch('/user/reg');     
        }
        
        $param = inputself();
        
        // 验证码检查
        if(config('CODE_TYPE')==0){
            $code = input("param.code");
            $verify = new Verify();
            if (!$code) {
                return json(['code' => -4, 'url' => '', 'msg' => '请输入验证码']);
            }
            if (!$verify->check($code)) {
                return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
            }
        }elseif(config('CODE_TYPE')==1){
            $gtresult=action("jingdian/Geetest/gtcheck");
            if($gtresult==false){
                return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
            }
        }
        
        // 数据预处理
        $param['password'] = md5(md5($param['password']) . config('auth_key'));
        
        // 获取用户组
        $group = new MemberGroupModel();
        $res = $group->getdefault();
        if($res){
            $group_id = $res['id'];
        }else{
            $respoint0 = $group->getpoint0();
            if($respoint0){
                $group_id = $respoint0['id'];
            }else{
                $group_id = 0;
            }
        }
        
        $param['group_id'] = $group_id;
        $token = md5(md5($param['account'] . $param['password']).md5(date("Y-m-d")). config('auth_key'). config('token').$_SERVER['HTTP_HOST']);
        $integral = config('web_reg_point');
        $money = config('web_reg_money');
        
        if(config('web_reg_status')==0){
            $regstatus = 1;
        }else{
            $regstatus = 0;
        }
        
        // 分销逻辑
        $pid1 = 0;
        if(session('userpid')){
            $pid1 = session('userpid');
        }       
        $pid2 = 0;
        $pid3 = 0;
        
        if($pid1 != 0){
            $pid2Result = Db::name('member')->where('id',$pid1)->find();
            if($pid2Result){
                if($pid2Result['is_distribut']==1){
                    $pid2 = $pid2Result['pid1'];
                    $pid3Result = Db::name('member')->where('id',$pid2)->find();
                    if($pid3Result){
                        if($pid3Result['is_distribut']==1){
                            $pid3 = $pid3Result['pid1'];
                            $pid4Result = Db::name('member')->where('id',$pid3)->find();
                            if($pid4Result==false){
                                $pid3 = 0;
                            }elseif($pid4Result['is_distribut']==0){
                                $pid3 = 0;
                            }
                        }else{
                            $pid2 = 0;
                        }
                    }else{
                        $pid2 = 0;
                    }
                }else{
                    $pid1 = 0;
                }
            }else{
                $pid1 = 0;
            }
        }
        
        // 准备数据
        $data = [
            'account' => $param['account'],
            'password' => $param['password'],
            'group_id' => $param['group_id'],
            'mobile' => $param['mobile'] ?? '',
            'email' => $param['email'] ?? '',
            'qq' => $param['qq'] ?? '',
            'token' => $token,
            'integral' => $integral,
            'money' => $money,
            'status' => $regstatus,
            'pid1' => $pid1,
            'pid2' => $pid2,
            'pid3' => $pid3,
        ];
        
        // 使用验证器验证注册数据
        $validate = new \app\admin\validate\MemberValidate();
        if (!$validate->scene('register')->check($data)) {
            return json(['code' => -2, 'url' => '', 'msg' => $validate->getError()]);
        }
        
        // 检查用户名是否已存在（双重验证）
        $existUser = Db::name('member')->where('account', $param['account'])->find();
        if($existUser){
            return json(['code' => -2, 'url' => '', 'msg' => '用户名已存在']);
        }
        
        // 检查手机号是否已存在
        if(!empty($param['mobile'])){
            $existMobile = Db::name('member')->where('mobile', $param['mobile'])->find();
            if($existMobile){
                return json(['code' => -2, 'url' => '', 'msg' => '手机号已被注册']);
            }
        }
        
        // 检查邮箱是否已存在
        if(!empty($param['email'])){
            $existEmail = Db::name('member')->where('email', $param['email'])->find();
            if($existEmail){
                return json(['code' => -2, 'url' => '', 'msg' => '邮箱已被注册']);
            }
        }
        
        $member = new MemberModel();
        $flag = $member->insertMember($data);
        
        if($flag['code']==1){
            $memberData = Db::name('member')->where('account',$param['account'])->find();      	
            
            if($integral > 0){
                //记录积分log
                writeintegrallog($memberData['id'],"注册赠送积分",0,$integral);
            }
            if($money > 0){
                //记录金额log
                writemoneylog($memberData['id'],"注册赠送金额",0,$money);
                writeamounttotal($memberData['id'],$money,'zsmoney');
            }
            
            if($regstatus == 1){
                return json(['code' => 1, 'url' => url('jingdian/index/index'), 'msg' => '注册成功']);
            }else{
                return json(['code' => 1, 'url' => url('jingdian/index/index'), 'msg' => '注册成功,请联系客服开通']);
            }
        }else{
            return json(['code' => -2, 'url' => '', 'msg' => $flag['msg']]);
        }
    }
    
    /**
     * 手机号注册优化
     */
    public function regmobileFixed(){
        if(config('web_reg_xingshi')==0){
            //普通注册
            return $this->redirect(url('@jingdian/user/reg'),302);
        }
        
        if(!request()->isPost()){     		   		
            if(session('useraccount.account')){
                return $this->redirect(url('@jingdian/index/index'),302);
            }
            return $this->fetch('/user/regmobile');     
        }
        
        $param = inputself();
        
        // 手机号格式验证
        if(!preg_match('/^1[3-9]\d{9}$/', $param['mobile'])){
            return json(['code' => -2, 'url' => '', 'msg' => '手机号格式不正确']);
        }
        
        // 检查手机号是否已注册
        $existMobile = Db::name('member')->where('mobile', $param['mobile'])->find();
        if($existMobile){
            return json(['code' => -2, 'url' => '', 'msg' => '该手机号已被注册']);
        }
        
        // 验证短信验证码（如果启用）
        if(config('SMS_VERIFY_ENABLED')){
            $smsCode = input('param.sms_code');
            if(empty($smsCode)){
                return json(['code' => -4, 'url' => '', 'msg' => '请输入短信验证码']);
            }
            
            $savedCode = session('sms_code_' . $param['mobile']);
            if(empty($savedCode) || $savedCode != $smsCode){
                return json(['code' => -4, 'url' => '', 'msg' => '短信验证码错误']);
            }
            
            // 清除验证码
            session('sms_code_' . $param['mobile'], null);
        }
        
        // 其他注册逻辑与普通注册相同
        $param['account'] = $param['mobile']; // 使用手机号作为用户名
        
        return $this->regFixed();
    }
}


