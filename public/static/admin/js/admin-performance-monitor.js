/**
 * 后台性能监控脚本
 * 用于监控管理员后台的性能问题
 */
(function() {
    'use strict';
    
    var AdminPerformanceMonitor = {
        // 配置参数
        config: {
            checkInterval: 30000, // 30秒检查一次
            slowPageThreshold: 3000, // 页面加载超过3秒视为慢页面
            ajaxTimeoutThreshold: 5000, // AJAX请求超过5秒视为超时
            memoryThreshold: 50 * 1024 * 1024, // 内存使用超过50MB视为高
            performanceApiUrl: '/admin/index/performance-api'
        },
        
        // 性能数据
        performanceData: {
            pageLoadTimes: [],
            ajaxTimes: [],
            errors: [],
            slowQueries: []
        },
        
        // 初始化
        init: function() {
            this.monitorPageLoad();
            this.monitorAjaxRequests();
            this.monitorErrors();
            this.setupPerformanceReporting();
            this.startPeriodicChecks();
        },
        
        // 监控页面加载性能
        monitorPageLoad: function() {
            var self = this;
            
            window.addEventListener('load', function() {
                setTimeout(function() {
                    self.recordPagePerformance();
                }, 100);
            });
        },
        
        // 记录页面性能
        recordPagePerformance: function() {
            if ('performance' in window && 'timing' in window.performance) {
                var timing = window.performance.timing;
                var loadTime = timing.loadEventEnd - timing.navigationStart;
                var domReadyTime = timing.domContentLoadedEventEnd - timing.navigationStart;
                var firstPaintTime = this.getFirstPaintTime();
                
                var performanceInfo = {
                    url: window.location.pathname,
                    loadTime: loadTime,
                    domReadyTime: domReadyTime,
                    firstPaintTime: firstPaintTime,
                    dnsTime: timing.domainLookupEnd - timing.domainLookupStart,
                    connectTime: timing.connectEnd - timing.connectStart,
                    serverTime: timing.responseEnd - timing.requestStart,
                    timestamp: Date.now()
                };
                
                this.performanceData.pageLoadTimes.push(performanceInfo);
                
                // 如果页面加载时间过长，记录为慢页面
                if (loadTime > this.config.slowPageThreshold) {
                    this.reportSlowPage(performanceInfo);
                }
                
                // 显示性能信息（仅在调试模式下）
                if (this.isDebugMode()) {
                    this.displayPerformanceInfo(performanceInfo);
                }
            }
        },
        
        // 获取首次绘制时间
        getFirstPaintTime: function() {
            if ('getEntriesByType' in window.performance) {
                var paintEntries = window.performance.getEntriesByType('paint');
                var firstPaint = paintEntries.find(function(entry) {
                    return entry.name === 'first-paint';
                });
                return firstPaint ? firstPaint.startTime : 0;
            }
            return 0;
        },
        
        // 监控AJAX请求
        monitorAjaxRequests: function() {
            var self = this;
            var originalAjax = $.ajax;
            
            $.ajax = function(options) {
                var startTime = Date.now();
                var originalSuccess = options.success;
                var originalError = options.error;
                
                options.success = function(data) {
                    var endTime = Date.now();
                    var duration = endTime - startTime;
                    
                    self.recordAjaxPerformance(options.url, duration, true);
                    
                    if (originalSuccess) {
                        originalSuccess.apply(this, arguments);
                    }
                };
                
                options.error = function(xhr, status, error) {
                    var endTime = Date.now();
                    var duration = endTime - startTime;
                    
                    self.recordAjaxPerformance(options.url, duration, false, {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error
                    });
                    
                    if (originalError) {
                        originalError.apply(this, arguments);
                    }
                };
                
                return originalAjax.call(this, options);
            };
        },
        
        // 记录AJAX性能
        recordAjaxPerformance: function(url, duration, success, errorInfo) {
            var ajaxInfo = {
                url: url,
                duration: duration,
                success: success,
                errorInfo: errorInfo,
                timestamp: Date.now()
            };
            
            this.performanceData.ajaxTimes.push(ajaxInfo);
            
            // 如果请求时间过长，记录为慢查询
            if (duration > this.config.ajaxTimeoutThreshold) {
                this.reportSlowAjax(ajaxInfo);
            }
            
            // 保持数组大小在合理范围内
            if (this.performanceData.ajaxTimes.length > 100) {
                this.performanceData.ajaxTimes.shift();
            }
        },
        
        // 监控错误
        monitorErrors: function() {
            var self = this;
            
            // JavaScript错误
            window.addEventListener('error', function(e) {
                self.recordError({
                    type: 'javascript',
                    message: e.message,
                    filename: e.filename,
                    lineno: e.lineno,
                    colno: e.colno,
                    stack: e.error ? e.error.stack : null,
                    timestamp: Date.now()
                });
            });
            
            // Promise rejection错误
            window.addEventListener('unhandledrejection', function(e) {
                self.recordError({
                    type: 'promise',
                    message: e.reason,
                    timestamp: Date.now()
                });
            });
        },
        
        // 记录错误
        recordError: function(errorInfo) {
            this.performanceData.errors.push(errorInfo);
            
            // 保持数组大小在合理范围内
            if (this.performanceData.errors.length > 50) {
                this.performanceData.errors.shift();
            }
            
            // 立即报告严重错误
            if (this.isCriticalError(errorInfo)) {
                this.reportError(errorInfo);
            }
        },
        
        // 判断是否为严重错误
        isCriticalError: function(errorInfo) {
            var criticalPatterns = [
                /cannot read property/i,
                /undefined is not a function/i,
                /permission denied/i,
                /network error/i
            ];
            
            return criticalPatterns.some(function(pattern) {
                return pattern.test(errorInfo.message);
            });
        },
        
        // 设置性能报告
        setupPerformanceReporting: function() {
            var self = this;
            
            // 页面卸载时发送性能数据
            window.addEventListener('beforeunload', function() {
                self.sendPerformanceData();
            });
            
            // 定期发送性能数据
            setInterval(function() {
                self.sendPerformanceData();
            }, 5 * 60 * 1000); // 每5分钟发送一次
        },
        
        // 开始定期检查
        startPeriodicChecks: function() {
            var self = this;
            
            setInterval(function() {
                self.checkSystemPerformance();
            }, this.config.checkInterval);
        },
        
        // 检查系统性能
        checkSystemPerformance: function() {
            if ('memory' in window.performance) {
                var memInfo = window.performance.memory;
                if (memInfo.usedJSHeapSize > this.config.memoryThreshold) {
                    this.reportHighMemoryUsage(memInfo);
                }
            }
        },
        
        // 报告慢页面
        reportSlowPage: function(performanceInfo) {
            console.warn('慢页面检测:', performanceInfo);
            
            if (this.isDebugMode()) {
                this.showAlert('页面加载较慢: ' + performanceInfo.loadTime + 'ms', 'warning');
            }
        },
        
        // 报告慢AJAX
        reportSlowAjax: function(ajaxInfo) {
            console.warn('慢AJAX请求检测:', ajaxInfo);
            
            if (this.isDebugMode()) {
                this.showAlert('AJAX请求较慢: ' + ajaxInfo.url + ' (' + ajaxInfo.duration + 'ms)', 'warning');
            }
        },
        
        // 报告错误
        reportError: function(errorInfo) {
            console.error('严重错误检测:', errorInfo);
            
            // 可以发送到服务器进行记录
            // this.sendErrorToServer(errorInfo);
        },
        
        // 报告高内存使用
        reportHighMemoryUsage: function(memInfo) {
            console.warn('内存使用过高:', memInfo);
            
            if (this.isDebugMode()) {
                this.showAlert('内存使用过高: ' + (memInfo.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB', 'warning');
            }
        },
        
        // 发送性能数据到服务器
        sendPerformanceData: function() {
            if (this.performanceData.pageLoadTimes.length === 0 && 
                this.performanceData.ajaxTimes.length === 0 && 
                this.performanceData.errors.length === 0) {
                return;
            }
            
            var data = {
                pageLoadTimes: this.performanceData.pageLoadTimes,
                ajaxTimes: this.performanceData.ajaxTimes,
                errors: this.performanceData.errors,
                userAgent: navigator.userAgent,
                url: window.location.href,
                timestamp: Date.now()
            };
            
            // 使用navigator.sendBeacon或XMLHttpRequest发送数据
            if ('sendBeacon' in navigator) {
                navigator.sendBeacon(this.config.performanceApiUrl, JSON.stringify(data));
            } else {
                $.ajax({
                    url: this.config.performanceApiUrl,
                    type: 'POST',
                    data: data,
                    async: false
                });
            }
            
            // 清空已发送的数据
            this.performanceData.pageLoadTimes = [];
            this.performanceData.ajaxTimes = [];
            this.performanceData.errors = [];
        },
        
        // 显示性能信息
        displayPerformanceInfo: function(performanceInfo) {
            var infoDiv = document.getElementById('performance-info');
            if (!infoDiv) {
                infoDiv = document.createElement('div');
                infoDiv.id = 'performance-info';
                infoDiv.style.cssText = 'position:fixed;bottom:10px;right:10px;background:#000;color:#fff;padding:10px;font-size:12px;z-index:9999;max-width:300px;';
                document.body.appendChild(infoDiv);
            }
            
            var info = [
                '页面性能信息:',
                '总加载时间: ' + performanceInfo.loadTime + 'ms',
                'DOM就绪时间: ' + performanceInfo.domReadyTime + 'ms',
                '首次绘制: ' + performanceInfo.firstPaintTime + 'ms',
                'DNS查询: ' + performanceInfo.dnsTime + 'ms',
                '服务器响应: ' + performanceInfo.serverTime + 'ms'
            ];
            
            infoDiv.innerHTML = info.join('<br>');
            
            // 3秒后隐藏
            setTimeout(function() {
                if (infoDiv.parentNode) {
                    infoDiv.parentNode.removeChild(infoDiv);
                }
            }, 3000);
        },
        
        // 显示警告
        showAlert: function(message, type) {
            if (typeof layer !== 'undefined') {
                var icon = type === 'warning' ? 2 : 1;
                layer.msg(message, { icon: icon, time: 3000 });
            } else {
                console.warn(message);
            }
        },
        
        // 判断是否为调试模式
        isDebugMode: function() {
            return window.location.href.indexOf('debug=1') > -1 || 
                   localStorage.getItem('admin_debug') === '1';
        },
        
        // 获取性能统计
        getPerformanceStats: function() {
            var stats = {
                avgPageLoadTime: 0,
                avgAjaxTime: 0,
                errorCount: this.performanceData.errors.length,
                slowPageCount: 0,
                slowAjaxCount: 0
            };
            
            if (this.performanceData.pageLoadTimes.length > 0) {
                stats.avgPageLoadTime = this.performanceData.pageLoadTimes.reduce(function(sum, item) {
                    return sum + item.loadTime;
                }, 0) / this.performanceData.pageLoadTimes.length;
                
                stats.slowPageCount = this.performanceData.pageLoadTimes.filter(function(item) {
                    return item.loadTime > this.config.slowPageThreshold;
                }, this).length;
            }
            
            if (this.performanceData.ajaxTimes.length > 0) {
                stats.avgAjaxTime = this.performanceData.ajaxTimes.reduce(function(sum, item) {
                    return sum + item.duration;
                }, 0) / this.performanceData.ajaxTimes.length;
                
                stats.slowAjaxCount = this.performanceData.ajaxTimes.filter(function(item) {
                    return item.duration > this.config.ajaxTimeoutThreshold;
                }, this).length;
            }
            
            return stats;
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        // 延迟初始化，确保其他脚本已加载
        setTimeout(function() {
            AdminPerformanceMonitor.init();
        }, 1000);
    });
    
    // 暴露到全局作用域用于调试
    window.AdminPerformanceMonitor = AdminPerformanceMonitor;
    
})();


