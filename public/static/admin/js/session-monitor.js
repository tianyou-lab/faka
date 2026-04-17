/**
 * 后台会话状态监控脚本
 * 用于实时监控管理员登录状态，防止界面显示异常
 */
(function() {
    'use strict';
    
    var SessionMonitor = {
        // 配置参数
        config: {
            checkInterval: 30000, // 30秒检查一次
            warningTime: 300000,  // 5分钟前警告
            logoutUrl: '/admin/login/index',
            heartbeatUrl: '/admin/index/heartbeat'
        },
        
        // 初始化
        init: function() {
            this.startMonitoring();
            this.bindEvents();
        },
        
        // 开始监控
        startMonitoring: function() {
            var self = this;
            
            // 定期检查会话状态
            setInterval(function() {
                self.checkSessionStatus();
            }, this.config.checkInterval);
            
            // 页面可见性变化时检查
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    self.checkSessionStatus();
                }
            });
        },
        
        // 检查会话状态
        checkSessionStatus: function() {
            var self = this;
            
            // 发送心跳请求检查会话状态
            $.ajax({
                url: this.config.heartbeatUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (response.code === 1) {
                        // 会话正常
                        self.updateSessionStatus('valid', response.data);
                    } else {
                        // 会话异常
                        self.handleSessionExpired(response.msg);
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 401 || xhr.status === 403) {
                        // 认证失败
                        self.handleSessionExpired('登录状态已失效');
                    } else if (status === 'timeout') {
                        // 请求超时，可能是网络问题
                        console.warn('会话检查请求超时');
                    }
                }
            });
        },
        
        // 更新会话状态显示
        updateSessionStatus: function(status, data) {
            var statusElement = document.getElementById('session-status');
            if (!statusElement) {
                // 创建状态显示元素
                statusElement = this.createStatusElement();
            }
            
            if (status === 'valid') {
                statusElement.className = 'session-status valid';
                statusElement.innerHTML = '<i class="fa fa-check-circle"></i> 会话正常';
                statusElement.style.display = 'none'; // 正常时隐藏
            } else {
                statusElement.className = 'session-status warning';
                statusElement.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 会话即将过期';
                statusElement.style.display = 'block';
            }
        },
        
        // 创建状态显示元素
        createStatusElement: function() {
            var element = document.createElement('div');
            element.id = 'session-status';
            element.className = 'session-status';
            element.style.cssText = 'position:fixed;top:10px;right:10px;z-index:9999;padding:8px 12px;border-radius:4px;font-size:12px;display:none;';
            
            document.body.appendChild(element);
            return element;
        },
        
        // 处理会话过期
        handleSessionExpired: function(message) {
            var self = this;
            
            // 显示会话过期提示
            layer.confirm(message + '，是否重新登录？', {
                icon: 2,
                title: '会话过期',
                btn: ['重新登录', '取消']
            }, function(index) {
                layer.close(index);
                self.redirectToLogin();
            }, function(index) {
                layer.close(index);
                // 用户取消，仍然重定向到登录页
                setTimeout(function() {
                    self.redirectToLogin();
                }, 1000);
            });
        },
        
        // 重定向到登录页
        redirectToLogin: function() {
            // 清除本地存储的会话信息
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('admin_session');
                sessionStorage.clear();
            }
            
            // 重定向到登录页
            window.location.href = this.config.logoutUrl;
        },
        
        // 绑定事件
        bindEvents: function() {
            var self = this;
            
            // 监听AJAX请求，检查401/403响应
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (xhr.status === 401 || xhr.status === 403) {
                    if (settings.url.indexOf('login') === -1) { // 排除登录相关请求
                        self.handleSessionExpired('登录状态已失效');
                    }
                }
            });
            
            // 监听页面卸载事件
            window.addEventListener('beforeunload', function() {
                // 可以在这里发送退出日志
            });
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        // 只在后台页面启用监控
        if (window.location.pathname.indexOf('/admin/') !== -1) {
            SessionMonitor.init();
        }
    });
    
    // 暴露到全局作用域
    window.SessionMonitor = SessionMonitor;
    
})();


