<?php

namespace app\admin\validate;
use think\Validate;

class ApikeyValidate extends Validate
{
    protected $rule = [
        'app_name' => 'require|length:2,50|unique:apikey',
        'description' => 'length:0,200',
        'allowed_ips' => 'length:0,500',
        'daily_limit' => 'number|egt:0',
        'status' => 'in:0,1'
    ];
    
    protected $message = [
        'app_name.require' => '应用名称不能为空',
        'app_name.length' => '应用名称长度必须在2-50个字符之间',
        'app_name.unique' => '应用名称已存在',
        'description.length' => '描述长度不能超过200个字符',
        'allowed_ips.length' => 'IP白名单长度不能超过500个字符',
        'daily_limit.number' => '每日调用限制必须为数字',
        'daily_limit.egt' => '每日调用限制不能为负数',
        'status.in' => '状态值不正确'
    ];
    
    protected $scene = [
        'add' => ['app_name', 'description', 'allowed_ips', 'daily_limit'],
        'edit' => ['app_name', 'description', 'allowed_ips', 'daily_limit', 'status']
    ];
}


