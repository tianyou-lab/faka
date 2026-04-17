/**
 * 前端性能优化脚本
 * 用于优化页面加载速度和用户体验
 */
(function() {
    'use strict';
    
    var PerformanceOptimizer = {
        // 配置参数
        config: {
            lazyLoadImages: true,
            compressRequests: true,
            cacheTimeout: 300000, // 5分钟
            preloadNextPage: true
        },
        
        // 初始化
        init: function() {
            this.optimizePageLoad();
            this.setupLazyLoading();
            this.optimizeAjaxRequests();
            this.setupErrorHandling();
            this.monitorPerformance();
        },
        
        // 优化页面加载
        optimizePageLoad: function() {
            var self = this;
            
            // 延迟加载非关键脚本
            setTimeout(function() {
                self.loadNonCriticalScripts();
            }, 100);
            
            // 预加载关键资源
            this.preloadCriticalResources();
            
            // 优化字体加载
            this.optimizeFontLoading();
        },
        
        // 设置懒加载
        setupLazyLoading: function() {
            if (!this.config.lazyLoadImages) return;
            
            var images = document.querySelectorAll('img[data-src]');
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(function(img) {
                imageObserver.observe(img);
            });
        },
        
        // 优化AJAX请求
        optimizeAjaxRequests: function() {
            var self = this;
            var originalAjax = $.ajax;
            
            $.ajax = function(options) {
                // 添加请求缓存
                if (options.cache !== false && options.type === 'GET') {
                    var cacheKey = 'ajax_' + options.url + '_' + JSON.stringify(options.data || {});
                    var cached = self.getCache(cacheKey);
                    
                    if (cached) {
                        var deferred = $.Deferred();
                        setTimeout(function() {
                            if (options.success) {
                                options.success(cached);
                            }
                            deferred.resolve(cached);
                        }, 0);
                        return deferred.promise();
                    }
                }
                
                // 添加错误重试机制
                var originalError = options.error;
                var retryCount = 0;
                var maxRetries = 3;
                
                options.error = function(xhr, status, error) {
                    if (retryCount < maxRetries && status !== 'abort') {
                        retryCount++;
                        setTimeout(function() {
                            $.ajax(options);
                        }, 1000 * retryCount);
                    } else if (originalError) {
                        originalError.call(this, xhr, status, error);
                    }
                };
                
                // 缓存成功的GET请求
                var originalSuccess = options.success;
                options.success = function(data) {
                    if (options.cache !== false && options.type === 'GET') {
                        var cacheKey = 'ajax_' + options.url + '_' + JSON.stringify(options.data || {});
                        self.setCache(cacheKey, data);
                    }
                    
                    if (originalSuccess) {
                        originalSuccess.call(this, data);
                    }
                };
                
                return originalAjax.call(this, options);
            };
        },
        
        // 预加载关键资源
        preloadCriticalResources: function() {
            var resources = [
                { href: '/static/jingdian/css/style.css', as: 'style' },
                { href: '/static/jingdian/js/app.js', as: 'script' }
            ];
            
            resources.forEach(function(resource) {
                var link = document.createElement('link');
                link.rel = 'preload';
                link.href = resource.href;
                link.as = resource.as;
                document.head.appendChild(link);
            });
        },
        
        // 加载非关键脚本
        loadNonCriticalScripts: function() {
            var scripts = [
                // 这里可以添加非关键的脚本
            ];
            
            scripts.forEach(function(src) {
                var script = document.createElement('script');
                script.src = src;
                script.async = true;
                document.head.appendChild(script);
            });
        },
        
        // 优化字体加载
        optimizeFontLoading: function() {
            if ('fonts' in document) {
                // 预加载关键字体
                var fonts = [
                    'Arial',
                    'Microsoft YaHei'
                ];
                
                fonts.forEach(function(font) {
                    document.fonts.load('1em ' + font);
                });
            }
        },
        
        // 设置错误处理
        setupErrorHandling: function() {
            // 全局错误处理
            window.addEventListener('error', function(e) {
                console.warn('页面错误:', e.message, e.filename, e.lineno);
                
                // 可以发送错误报告到服务器
                // self.reportError(e);
            });
            
            // 资源加载错误处理
            window.addEventListener('error', function(e) {
                if (e.target !== window) {
                    console.warn('资源加载失败:', e.target.src || e.target.href);
                    
                    // 尝试从备用源加载
                    if (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK') {
                        // 这里可以实现备用资源加载逻辑
                    }
                }
            }, true);
        },
        
        // 性能监控
        monitorPerformance: function() {
            if ('performance' in window) {
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        var perfData = window.performance.timing;
                        var loadTime = perfData.loadEventEnd - perfData.navigationStart;
                        var domReadyTime = perfData.domContentLoadedEventEnd - perfData.navigationStart;
                        
                        console.info('页面性能指标:', {
                            '总加载时间': loadTime + 'ms',
                            'DOM就绪时间': domReadyTime + 'ms',
                            'DNS查询时间': (perfData.domainLookupEnd - perfData.domainLookupStart) + 'ms',
                            '建立连接时间': (perfData.connectEnd - perfData.connectStart) + 'ms'
                        });
                        
                        // 如果加载时间过长，显示提示
                        if (loadTime > 5000) {
                            console.warn('页面加载时间过长，建议优化');
                        }
                    }, 100);
                });
            }
        },
        
        // 缓存操作
        setCache: function(key, data) {
            if ('localStorage' in window) {
                try {
                    var item = {
                        data: data,
                        timestamp: Date.now()
                    };
                    localStorage.setItem(key, JSON.stringify(item));
                } catch (e) {
                    console.warn('缓存设置失败:', e);
                }
            }
        },
        
        getCache: function(key) {
            if ('localStorage' in window) {
                try {
                    var item = localStorage.getItem(key);
                    if (item) {
                        var parsed = JSON.parse(item);
                        if (Date.now() - parsed.timestamp < this.config.cacheTimeout) {
                            return parsed.data;
                        } else {
                            localStorage.removeItem(key);
                        }
                    }
                } catch (e) {
                    console.warn('缓存读取失败:', e);
                }
            }
            return null;
        },
        
        // 清除缓存
        clearCache: function() {
            if ('localStorage' in window) {
                var keys = Object.keys(localStorage);
                keys.forEach(function(key) {
                    if (key.startsWith('ajax_')) {
                        localStorage.removeItem(key);
                    }
                });
            }
        }
    };
    
    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            PerformanceOptimizer.init();
        });
    } else {
        PerformanceOptimizer.init();
    }
    
    // 暴露到全局作用域
    window.PerformanceOptimizer = PerformanceOptimizer;
    
})();


