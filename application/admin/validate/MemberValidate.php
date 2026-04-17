<?php
namespace app\admin\validate;
use think\Validate;

class MemberValidate extends Validate
{
    protected $rule = [
        'account' => 'require|length:3,20|unique:member|alphaNum',
        'password' => 'require',
        'mobile' => 'mobile',
        'email' => 'email',
        'qq' => 'number',
        'group_id' => 'number'
    ];
    
    protected $message = [
        'account.require' => '用户名不能为空',
        'account.length' => '用户名长度必须在3-20个字符之间',
        'account.unique' => '该用户名已经存在',
        'account.alphaNum' => '用户名只能包含字母和数字',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度必须在6-20个字符之间',
        'mobile.mobile' => '手机号格式不正确',
        'email.email' => '邮箱格式不正确',
        'qq.number' => 'QQ号码必须为数字',
        'group_id.number' => '用户组ID必须为数字'
    ];
    
    protected $scene = [
        'add' => ['account', 'password', 'mobile', 'email', 'qq', 'group_id'],
        'edit' => ['account', 'mobile', 'email', 'qq', 'group_id'],
        'register' => ['account', 'password', 'email']
    ];

}