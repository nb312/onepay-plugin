/**
 * OnePay Admin JavaScript
 */
(function($) {
    'use strict';
    
    var OnePayAdmin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#onepay_test_connection').on('click', this.testConnection);
            $('#onepay_generate_keys').on('click', this.generateKeys);
            $('#onepay_validate_keys').on('click', this.validateKeys);
            $('#onepay_run_tests').on('click', this.runFullTests);
            $('#onepay_refresh_callbacks').on('click', this.refreshCallbacks);
            $(document).on('click', '.view-callback-detail', this.viewCallbackDetail);
            $(document).on('click', '.onepay-callback-modal-close, .onepay-callback-modal', this.closeCallbackModal);
            $('input[name="woocommerce_onepay_private_key"], input[name="woocommerce_onepay_platform_public_key"]').on('blur', this.validateKeysOnBlur);
        },
        
        testConnection: function(e) {
            e.preventDefault();
            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');
            
            $.post(ajaxurl, {
                action: 'onepay_test_connection',
                nonce: onepay_admin.nonce
            }, function(response) {
                if (response.success) {
                    alert('Connection successful!');
                } else {
                    alert('Connection failed: ' + response.data);
                }
            }).always(function() {
                $button.prop('disabled', false).text('Test Connection');
            });
        },
        
        generateKeys: function(e) {
            e.preventDefault();
            alert('Please generate RSA keys externally and paste them in the configuration fields.');
        },
        
        validateKeys: function(e) {
            e.preventDefault();
            var $button = $(this);
            $button.prop('disabled', true).text('Validating...');
            
            var privateKey = $('textarea[name="woocommerce_onepay_private_key"]').val();
            var publicKey = $('textarea[name="woocommerce_onepay_platform_public_key"]').val();
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_validate_keys',
                nonce: onepay_admin.nonce,
                private_key: privateKey,
                public_key: publicKey
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Key Validation Results:\n';
                    message += 'Private Key: ' + (data.private_valid ? 'Valid' : 'Invalid') + '\n';
                    message += 'Public Key: ' + (data.public_valid ? 'Valid' : 'Invalid') + '\n';
                    message += 'Signature Test: ' + (data.signature_test ? 'Passed' : 'Failed');
                    alert(message);
                } else {
                    alert('Key validation failed: ' + response.data);
                }
            }).always(function() {
                $button.prop('disabled', false).text('Validate Keys');
            });
        },
        
        validateKeysOnBlur: function() {
            var $field = $(this);
            var keyType = $field.attr('name').includes('private') ? 'private' : 'public';
            var keyValue = $field.val().trim();
            
            if (keyValue) {
                // Simple validation - check if key has proper format
                var hasBegin = keyValue.includes('-----BEGIN');
                var hasEnd = keyValue.includes('-----END');
                
                if (hasBegin && hasEnd) {
                    $field.removeClass('onepay-invalid-key').addClass('onepay-valid-key');
                } else {
                    $field.removeClass('onepay-valid-key').addClass('onepay-invalid-key');
                }
            } else {
                $field.removeClass('onepay-valid-key onepay-invalid-key');
            }
        },
        
        runFullTests: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $resultDiv = $('#onepay_tools_result');
            
            $button.prop('disabled', true).text('Running Tests...');
            $resultDiv.html('<p>Running comprehensive tests...</p>').show();
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_run_tests',
                nonce: onepay_admin.nonce
            }, function(response) {
                if (response.success) {
                    $resultDiv.html(response.data.report_html);
                    
                    // Show summary
                    var results = response.data.results.overall;
                    var message = 'Tests completed: ' + results.total_passed + '/' + results.total_tests + 
                                ' passed (' + results.success_rate + '%). Status: ' + results.status;
                    
                    if (results.overall_success) {
                        $resultDiv.prepend('<div class="notice notice-success"><p>' + message + '</p></div>');
                    } else {
                        $resultDiv.prepend('<div class="notice notice-error"><p>' + message + '</p></div>');
                    }
                } else {
                    $resultDiv.html('<div class="notice notice-error"><p>Test failed: ' + response.data + '</p></div>');
                }
            }).fail(function() {
                $resultDiv.html('<div class="notice notice-error"><p>Failed to run tests. Please try again.</p></div>');
            }).always(function() {
                $button.prop('disabled', false).text('Run Full Tests');
            });
        },
        
        refreshCallbacks: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $container = $('#onepay_callback_logs_container');
            
            $button.prop('disabled', true).text('刷新中...');
            $container.html('<p>加载中...</p>');
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_refresh_callbacks',
                nonce: onepay_admin.nonce
            }, function(response) {
                if (response.success) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<div class="notice notice-error"><p>刷新失败: ' + response.data + '</p></div>');
                }
            }).fail(function() {
                $container.html('<div class="notice notice-error"><p>网络错误，请重试</p></div>');
            }).always(function() {
                $button.prop('disabled', false).text('刷新');
            });
        },
        
        viewCallbackDetail: function(e) {
            e.preventDefault();
            var $button = $(this);
            var callbackId = $button.data('id');
            
            if (!callbackId) {
                alert('无效的回调ID');
                return;
            }
            
            $button.prop('disabled', true).text('加载中...');
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_get_callback_detail',
                nonce: onepay_admin.nonce,
                callback_id: callbackId
            }, function(response) {
                if (response.success) {
                    OnePayAdmin.showCallbackModal(response.data);
                } else {
                    alert('获取回调详情失败: ' + response.data);
                }
            }).fail(function() {
                alert('网络错误，请重试');
            }).always(function() {
                $button.prop('disabled', false).text('详情');
            });
        },
        
        showCallbackModal: function(callback) {
            // 创建弹窗HTML
            var modalHtml = '<div class="onepay-callback-modal">' +
                '<div class="onepay-callback-modal-content">' +
                    '<div class="onepay-callback-modal-header">' +
                        '<h2>回调详情 #' + callback.id + '</h2>' +
                        '<span class="onepay-callback-modal-close">&times;</span>' +
                    '</div>' +
                    '<div class="onepay-callback-modal-body">' +
                        this.formatCallbackDetail(callback) +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // 移除已存在的弹窗
            $('.onepay-callback-modal').remove();
            
            // 添加新弹窗
            $('body').append(modalHtml);
            $('.onepay-callback-modal').show();
        },
        
        formatCallbackDetail: function(callback) {
            var html = '';
            
            // 基本信息
            html += '<div class="callback-detail-section">' +
                '<h4>基本信息</h4>' +
                '<div class="callback-detail-grid">' +
                    '<div class="callback-detail-label">时间:</div>' +
                    '<div class="callback-detail-value">' + callback.log_time + '</div>' +
                    '<div class="callback-detail-label">订单ID:</div>' +
                    '<div class="callback-detail-value">' + (callback.order_id || '-') + '</div>' +
                    '<div class="callback-detail-label">订单号:</div>' +
                    '<div class="callback-detail-value">' + (callback.order_number || '-') + '</div>' +
                    '<div class="callback-detail-label">金额:</div>' +
                    '<div class="callback-detail-value">' + (callback.amount ? '¥' + callback.amount + ' ' + (callback.currency || '') : '-') + '</div>' +
                    '<div class="callback-detail-label">IP地址:</div>' +
                    '<div class="callback-detail-value">' + (callback.user_ip || '-') + '</div>' +
                    '<div class="callback-detail-label">执行时间:</div>' +
                    '<div class="callback-detail-value">' + (callback.execution_time ? (callback.execution_time * 1000).toFixed(1) + 'ms' : '-') + '</div>' +
                    '<div class="callback-detail-label">状态:</div>' +
                    '<div class="callback-detail-value">' + (callback.status || '-') + '</div>' +
                '</div>' +
            '</div>';
            
            // 请求数据
            if (callback.request_data) {
                html += '<div class="callback-detail-section">' +
                    '<h4>请求数据</h4>' +
                    '<div class="callback-json-viewer">' + this.formatJson(callback.request_data) + '</div>' +
                '</div>';
            }
            
            // 响应数据
            if (callback.response_data) {
                html += '<div class="callback-detail-section">' +
                    '<h4>响应数据</h4>' +
                    '<div class="callback-json-viewer">' + this.formatJson(callback.response_data) + '</div>' +
                '</div>';
            }
            
            // 错误信息
            if (callback.error_message) {
                html += '<div class="callback-detail-section">' +
                    '<h4>错误信息</h4>' +
                    '<div style="color: red; padding: 10px; background: #fee; border: 1px solid #fcc; border-radius: 3px;">' +
                        callback.error_message +
                    '</div>' +
                '</div>';
            }
            
            // 额外数据
            if (callback.extra_data) {
                html += '<div class="callback-detail-section">' +
                    '<h4>额外数据</h4>' +
                    '<div class="callback-json-viewer">' + this.formatJson(callback.extra_data) + '</div>' +
                '</div>';
            }
            
            return html;
        },
        
        formatJson: function(data) {
            try {
                var obj = typeof data === 'string' ? JSON.parse(data) : data;
                return JSON.stringify(obj, null, 2);
            } catch (e) {
                return data;
            }
        },
        
        closeCallbackModal: function(e) {
            if (e.target === this || $(e.target).hasClass('onepay-callback-modal-close')) {
                $('.onepay-callback-modal').hide().remove();
            }
        }
    };
    
    $(document).ready(function() {
        OnePayAdmin.init();
    });
    
})(jQuery);