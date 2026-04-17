/**
 * 会员注册表单验证脚本
 * 用于前端实时验证注册表单
 */
(function() {
    'use strict';
    
    var RegisterValidator = {
        // 配置参数
        config: {
            checkUserUrl: '/api/check-user-exists',
            checkMobileUrl: '/api/check-mobile-exists',
            checkEmailUrl: '/api/check-email-exists'
        },
        
        // 验证规则
        rules: {
            account: {
                required: true,
                minLength: 3,
                maxLength: 20,
                pattern: /^[a-zA-Z0-9]+$/,
                message: {
                    required: '用户名不能为空',
                    minLength: '用户名至少3个字符',
                    maxLength: '用户名最多20个字符',
                    pattern: '用户名只能包含字母和数字',
                    exists: '用户名已存在'
                }
            },
            password: {
                required: true,
                minLength: 6,
                maxLength: 20,
                message: {
                    required: '密码不能为空',
                    minLength: '密码至少6个字符',
                    maxLength: '密码最多20个字符'
                }
            },
            password2: {
                required: true,
                confirm: 'password',
                message: {
                    required: '请确认密码',
                    confirm: '两次密码输入不一致'
                }
            },
            mobile: {
                required: false,
                pattern: /^1[3-9]\d{9}$/,
                message: {
                    pattern: '手机号格式不正确',
                    exists: '手机号已被注册'
                }
            },
            email: {
                required: false,
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: {
                    pattern: '邮箱格式不正确',
                    exists: '邮箱已被注册'
                }
            },
            qq: {
                required: false,
                pattern: /^\d{5,11}$/,
                message: {
                    pattern: 'QQ号格式不正确'
                }
            },
            code: {
                required: true,
                minLength: 4,
                maxLength: 6,
                message: {
                    required: '请输入验证码',
                    minLength: '验证码长度不正确',
                    maxLength: '验证码长度不正确'
                }
            }
        },
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.setupRealTimeValidation();
        },
        
        // 绑定事件
        bindEvents: function() {
            var self = this;
            
            // 表单提交验证
            $('#doreg').on('submit', function(e) {
                e.preventDefault();
                self.validateForm();
            });
            
            // 验证码刷新
            $('.verify-code-img').on('click', function() {
                self.refreshVerifyCode();
            });
        },
        
        // 设置实时验证
        setupRealTimeValidation: function() {
            var self = this;
            
            // 用户名验证
            $('#account').on('blur', function() {
                self.validateField('account', $(this).val());
            });
            
            // 密码验证
            $('#password').on('blur', function() {
                self.validateField('password', $(this).val());
            });
            
            // 确认密码验证
            $('#password2').on('blur', function() {
                self.validateField('password2', $(this).val());
            });
            
            // 手机号验证
            $('#mobile').on('blur', function() {
                if ($(this).val()) {
                    self.validateField('mobile', $(this).val());
                }
            });
            
            // 邮箱验证
            $('#email').on('blur', function() {
                if ($(this).val()) {
                    self.validateField('email', $(this).val());
                }
            });
            
            // QQ号验证
            $('#qq').on('blur', function() {
                if ($(this).val()) {
                    self.validateField('qq', $(this).val());
                }
            });
        },
        
        // 验证单个字段
        validateField: function(field, value) {
            var rule = this.rules[field];
            var isValid = true;
            var message = '';
            
            // 必填验证
            if (rule.required && (!value || value.trim() === '')) {
                isValid = false;
                message = rule.message.required;
            }
            
            if (isValid && value) {
                // 长度验证
                if (rule.minLength && value.length < rule.minLength) {
                    isValid = false;
                    message = rule.message.minLength;
                }
                
                if (rule.maxLength && value.length > rule.maxLength) {
                    isValid = false;
                    message = rule.message.maxLength;
                }
                
                // 格式验证
                if (rule.pattern && !rule.pattern.test(value)) {
                    isValid = false;
                    message = rule.message.pattern;
                }
                
                // 确认密码验证
                if (rule.confirm) {
                    var confirmValue = $('#' + rule.confirm).val();
                    if (value !== confirmValue) {
                        isValid = false;
                        message = rule.message.confirm;
                    }
                }
            }
            
            // 显示验证结果
            this.showFieldResult(field, isValid, message);
            
            // 异步验证（用户名、手机号、邮箱唯一性）
            if (isValid && value && (field === 'account' || field === 'mobile' || field === 'email')) {
                this.checkFieldExists(field, value);
            }
            
            return isValid;
        },
        
        // 检查字段是否已存在
        checkFieldExists: function(field, value) {
            var self = this;
            var url = '';
            
            switch (field) {
                case 'account':
                    url = this.config.checkUserUrl;
                    break;
                case 'mobile':
                    url = this.config.checkMobileUrl;
                    break;
                case 'email':
                    url = this.config.checkEmailUrl;
                    break;
            }
            
            if (!url) return;
            
            $.ajax({
                url: url,
                type: 'POST',
                data: { [field]: value },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        self.showFieldResult(field, false, self.rules[field].message.exists);
                    } else {
                        self.showFieldResult(field, true, '');
                    }
                },
                error: function() {
                    // 网络错误时不阻止用户继续
                    console.warn('验证请求失败，请检查网络连接');
                }
            });
        },
        
        // 显示字段验证结果
        showFieldResult: function(field, isValid, message) {
            var $field = $('#' + field);
            var $parent = $field.closest('.p-input');
            var $warn = $parent.find('.tel-warn');
            
            if (isValid) {
                $parent.removeClass('error');
                $warn.removeClass('show').addClass('hide');
                $warn.find('em').text('');
                $warn.find('i').removeClass('icon-err').addClass('icon-succ1');
            } else {
                $parent.addClass('error');
                $warn.removeClass('hide').addClass('show');
                $warn.find('em').text(message);
                $warn.find('i').removeClass('icon-succ1').addClass('icon-err');
            }
        },
        
        // 验证整个表单
        validateForm: function() {
            var self = this;
            var isValid = true;
            var firstErrorField = null;
            
            // 验证所有字段
            $('input[name]').each(function() {
                var field = $(this).attr('name');
                var value = $(this).val();
                
                if (self.rules[field]) {
                    var fieldValid = self.validateField(field, value);
                    if (!fieldValid && isValid) {
                        isValid = false;
                        firstErrorField = $(this);
                    }
                }
            });
            
            if (isValid) {
                this.submitForm();
            } else {
                // 聚焦到第一个错误字段
                if (firstErrorField) {
                    firstErrorField.focus();
                }
                this.showMessage('请检查表单中的错误信息', 'error');
            }
        },
        
        // 提交表单
        submitForm: function() {
            var self = this;
            var formData = $('#doreg').serialize();
            
            // 显示提交状态
            self.showLoading('正在注册...');
            
            $.ajax({
                url: $('#doreg').attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.code === 1) {
                        self.showMessage(response.msg, 'success');
                        setTimeout(function() {
                            if (response.url) {
                                window.location.href = response.url;
                            } else {
                                window.location.reload();
                            }
                        }, 1500);
                    } else {
                        self.showMessage(response.msg, 'error');
                        
                        // 如果是验证码错误，刷新验证码
                        if (response.code === -4) {
                            self.refreshVerifyCode();
                        }
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showMessage('网络错误，请稍后重试', 'error');
                }
            });
        },
        
        // 刷新验证码
        refreshVerifyCode: function() {
            var $img = $('.verify-code-img');
            if ($img.length > 0) {
                var src = $img.attr('src');
                var newSrc = src.replace(/\?.*$/, '') + '?t=' + Date.now();
                $img.attr('src', newSrc);
            }
        },
        
        // 显示消息
        showMessage: function(message, type) {
            if (typeof layer !== 'undefined') {
                var icon = type === 'success' ? 1 : 2;
                layer.msg(message, { icon: icon, time: 3000 });
            } else {
                alert(message);
            }
        },
        
        // 显示加载状态
        showLoading: function(message) {
            if (typeof layer !== 'undefined') {
                layer.load(2, { content: message || '处理中...' });
            }
        },
        
        // 隐藏加载状态
        hideLoading: function() {
            if (typeof layer !== 'undefined') {
                layer.closeAll('loading');
            }
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        RegisterValidator.init();
    });
    
    // 暴露到全局作用域
    window.RegisterValidator = RegisterValidator;
    
})();


