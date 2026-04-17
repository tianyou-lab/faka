# 发卡系统

## 环境要求

- PHP 7.0+
- MySQL 5.5+
- Nginx / Apache

## 目录结构

```
├── application/       # 应用目录
│   ├── admin/         # 后台管理模块
│   ├── api/           # API接口模块
│   ├── jingdian/      # 前台经典模板
│   ├── mobile/        # 手机端模块
│   ├── madmin/        # 分站管理模块
│   ├── pay/           # 支付模块
│   └── common/        # 公共类
├── data/              # 数据文件（SQL、安装锁）
├── extend/            # 扩展类库
├── public/            # Web入口目录
│   ├── static/        # 静态资源
│   ├── upload/        # 上传文件
│   └── index.php      # 入口文件
├── runtime/           # 运行时缓存
├── thinkphp/          # ThinkPHP框架核心
└── vendor/            # Composer依赖
```

## 安装说明

### 全新安装

1. 上传源码并解压
2. Nginx 设置运行目录为 `public`
3. 配置 Nginx 伪静态（参考 `nginx.conf.example`）
4. 访问域名自动跳转安装页面
5. 前端显示"非法host"请到后台分站管理设置主站域名

### Nginx 伪静态配置

参考项目根目录 `nginx.conf.example` 文件。

## 后台地址

```
http://你的域名/houtai
```

## 权限设置

- 所有目录给 755 权限
- `runtime/` 目录需要可写权限
- `public/upload/` 目录需要可写权限

## 安全建议

- 安装完成后修改后台目录路径
- 建议安装 Web 防火墙
- 生产环境关闭 `app_debug`
