/**
 * 注册按钮修复脚本
 * 解决注册按钮无反应的问题
 */
(function() {
    'use strict';
    
    var RegisterButtonFix = {
        init: function() {
            this.fixRegisterButton();
            this.bindFormEvents();
            this.addDebugInfo();
        },
        
        // 修复注册按钮
        fixRegisterButton: function() {
            var $regBtn = $('#regbtn');
            
            if ($regBtn.length > 0) {
                // 移除禁用状态
                $regBtn.prop('disabled', false);
                $regBtn.removeClass('off');
                
                console.log('注册按钮修复完成');
                
                // 确保按钮可以响应点击
                $regBtn.off('click.fix').on('click.fix', function(e) {
                    console.log('注册按钮被点击');
                    
                    // 如果按钮被禁用，阻止默认行为
                    if ($(this).prop('disabled') || $(this).hasClass('off')) {
                        e.preventDefault();
                        console.log('按钮处于禁用状态，阻止提交');
                        return false;
                    }
                });
            }
        },
        
        // 绑定表单事件
        bindFormEvents: function() {
            var self = this;
            
            // 监听表单提交
            $('#doreg').off('submit.fix').on('submit.fix', function(e) {
                console.log('表单提交事件触发');
                
                var $regBtn = $('#regbtn');
                if ($regBtn.prop('disabled') || $regBtn.hasClass('off')) {
                    e.preventDefault();
                    console.log('表单提交被阻止，按钮处于禁用状态');
                    return false;
                }
                
                // 显示提交状态
                $regBtn.text('注册中...');
                $regBtn.prop('disabled', true);
                $regBtn.addClass('off');
            });
            
            // 智能启用按钮
            this.smartEnableButton();
        },
        
        // 智能启用按钮
        smartEnableButton: function() {
            var self = this;
            
            function checkFormValid() {
                var account = $('#account').val() || '';
                var password = $('#password').val() || '';
                var password2 = $('#password2').val() || '';
                var mobile = $('#mobile').val() || '';
                
                var isValid = false;
                
                // 普通注册验证
                if (account.length >= 3 && password.length >= 6 && password === password2) {
                    isValid = true;
                }
                
                // 手机注册验证
                if (mobile.length >= 11 && password.length >= 6 && password === password2) {
                    isValid = true;
                }
                
                var $regBtn = $('#regbtn');
                if (isValid) {
                    $regBtn.removeClass('off');
                    $regBtn.prop('disabled', false);
                    console.log('注册按钮已启用');
                } else {
                    $regBtn.addClass('off');
                    $regBtn.prop('disabled', true);
                    console.log('注册按钮已禁用');
                }
                
                return isValid;
            }
            
            // 绑定字段变化事件
            $('#account, #password, #password2, #mobile').on('input blur keyup', function() {
                setTimeout(checkFormValid, 100); // 延迟检查，确保值已更新
            });
            
            // 初始检查
            setTimeout(checkFormValid, 500);
        },
        
        // 添加调试信息
        addDebugInfo: function() {
            if (window.location.href.indexOf('debug=1') > -1) {
                var debugInfo = $('<div id="register-debug" style="position:fixed;top:10px;right:10px;background:#000;color:#fff;padding:10px;font-size:12px;z-index:9999;"></div>');
                
                function updateDebugInfo() {
                    var $regBtn = $('#regbtn');
                    var info = [
                        '注册按钮状态调试:',
                        '按钮存在: ' + ($regBtn.length > 0 ? '是' : '否'),
                        '按钮禁用: ' + ($regBtn.prop('disabled') ? '是' : '否'),
                        '按钮class: ' + $regBtn.attr('class'),
                        '账号长度: ' + ($('#account').val() || '').length,
                        '密码长度: ' + ($('#password').val() || '').length,
                        '确认密码: ' + ($('#password2').val() === $('#password').val() ? '匹配' : '不匹配')
                    ];
                    debugInfo.html(info.join('<br>'));
                }
                
                $('body').append(debugInfo);
                setInterval(updateDebugInfo, 1000);
            }
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        // 延迟执行，确保其他脚本已加载
        setTimeout(function() {
            RegisterButtonFix.init();
        }, 1000);
    });
    
    // 暴露到全局作用域用于调试
    window.RegisterButtonFix = RegisterButtonFix;
    
})();


