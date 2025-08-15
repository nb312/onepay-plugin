/**
 * OnePay回调日志页面交互脚本
 */
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('OnePay回调日志JS已加载');
    
    // 详情模态框HTML模板
    const modalTemplate = `
        <div id="onepay-detail-modal" class="onepay-modal" style="display: none;">
            <div class="onepay-modal-backdrop"></div>
            <div class="onepay-modal-content">
                <div class="onepay-modal-header">
                    <h2>回调详情</h2>
                    <button type="button" class="onepay-modal-close">&times;</button>
                </div>
                <div class="onepay-modal-body">
                    <div class="onepay-loading">
                        <div class="onepay-spinner"></div>
                        <p>加载中...</p>
                    </div>
                    <div class="onepay-detail-content" style="display: none;"></div>
                </div>
            </div>
        </div>
    `;
    
    // 添加模态框HTML到页面
    if ($('#onepay-detail-modal').length === 0) {
        $('body').append(modalTemplate);
    }
    
    // 使用事件委托处理查看详情按钮点击
    $(document).on('click', '.view-detail-btn', function(e) {
        e.preventDefault();
        console.log('查看详情按钮被点击');
        
        const $btn = $(this);
        const logId = $btn.data('log-id');
        
        if (!logId) {
            console.error('缺少log-id参数');
            alert('无法获取日志ID');
            return;
        }
        
        console.log('准备加载日志详情，ID:', logId);
        
        // 显示模态框
        showModal();
        
        // 发送AJAX请求获取详情
        loadLogDetail(logId);
    });
    
    // 模态框关闭事件
    $(document).on('click', '.onepay-modal-close, .onepay-modal-backdrop', function() {
        hideModal();
    });
    
    // ESC键关闭模态框
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // ESC
            hideModal();
        }
    });
    
    /**
     * 显示模态框
     */
    function showModal() {
        const $modal = $('#onepay-detail-modal');
        $modal.show();
        $modal.find('.onepay-loading').show();
        $modal.find('.onepay-detail-content').hide();
        $('body').addClass('onepay-modal-open');
    }
    
    /**
     * 隐藏模态框
     */
    function hideModal() {
        $('#onepay-detail-modal').hide();
        $('body').removeClass('onepay-modal-open');
    }
    
    /**
     * 加载日志详情
     */
    function loadLogDetail(logId) {
        console.log('开始AJAX请求，日志ID:', logId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            timeout: 15000,
            data: {
                action: 'onepay_get_callback_detail',
                log_id: logId,
                nonce: onepayCallbackLogs.nonce
            },
            beforeSend: function() {
                console.log('AJAX请求发送中...');
            },
            success: function(response) {
                console.log('AJAX响应:', response);
                
                if (response.success && response.data) {
                    displayLogDetail(response.data);
                } else {
                    const errorMsg = response.data && response.data.message ? 
                        response.data.message : '获取详情失败';
                    displayError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX请求失败:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                let errorMsg = '请求失败: ';
                if (status === 'timeout') {
                    errorMsg += '请求超时';
                } else if (xhr.status === 0) {
                    errorMsg += '网络连接失败';
                } else {
                    errorMsg += error || '未知错误';
                }
                
                displayError(errorMsg);
            }
        });
    }
    
    /**
     * 显示日志详情
     */
    function displayLogDetail(data) {
        console.log('显示日志详情:', data);
        
        const $modal = $('#onepay-detail-modal');
        const $content = $modal.find('.onepay-detail-content');
        
        // 隐藏加载状态
        $modal.find('.onepay-loading').hide();
        
        // 生成详情HTML
        const detailHtml = generateDetailHtml(data);
        $content.html(detailHtml);
        $content.show();
        
        // 初始化标签页
        initTabs();
    }
    
    /**
     * 显示错误信息
     */
    function displayError(message) {
        console.error('显示错误:', message);
        
        const $modal = $('#onepay-detail-modal');
        const $content = $modal.find('.onepay-detail-content');
        
        $modal.find('.onepay-loading').hide();
        $content.html('<div class="onepay-error"><p>❌ ' + message + '</p></div>');
        $content.show();
    }
    
    /**
     * 生成详情HTML
     */
    function generateDetailHtml(log) {
        const extraData = log.extra_data || {};
        
        let html = '<div class="onepay-detail-tabs">';
        
        // 标签页导航
        html += '<div class="onepay-tab-nav">';
        html += '<button class="onepay-tab-btn active" data-tab="basic">基本信息</button>';
        html += '<button class="onepay-tab-btn" data-tab="request">请求数据</button>';
        html += '<button class="onepay-tab-btn" data-tab="response">响应数据</button>';
        if (extraData.processing_steps && extraData.processing_steps.length > 0) {
            html += '<button class="onepay-tab-btn" data-tab="steps">处理步骤</button>';
        }
        html += '<button class="onepay-tab-btn" data-tab="raw">原始数据</button>';
        html += '</div>';
        
        // 标签页内容
        html += '<div class="onepay-tab-content">';
        
        // 基本信息标签页
        html += generateBasicInfoTab(log, extraData);
        
        // 请求数据标签页
        html += generateRequestDataTab(log, extraData);
        
        // 响应数据标签页
        html += generateResponseDataTab(log, extraData);
        
        // 处理步骤标签页
        if (extraData.processing_steps && extraData.processing_steps.length > 0) {
            html += generateProcessingStepsTab(extraData);
        }
        
        // 原始数据标签页
        html += generateRawDataTab(log);
        
        html += '</div></div>';
        
        return html;
    }
    
    /**
     * 生成基本信息标签页
     */
    function generateBasicInfoTab(log, extraData) {
        let html = '<div class="onepay-tab-pane active" data-tab="basic">';
        html += '<table class="onepay-detail-table">';
        
        // 基本信息
        html += '<tr><th>日志ID</th><td>' + (log.id || '-') + '</td></tr>';
        html += '<tr><th>时间</th><td>' + (log.log_time || '-') + '</td></tr>';
        html += '<tr><th>类型</th><td>' + (log.log_type || '-') + '</td></tr>';
        html += '<tr><th>状态</th><td>' + getStatusBadge(log.status) + '</td></tr>';
        html += '<tr><th>客户端IP</th><td>' + (log.user_ip || '-') + '</td></tr>';
        
        // 订单信息
        if (log.order_id || log.order_number) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">订单信息</th></tr>';
            html += '<tr><th>订单ID</th><td>' + (log.order_id || '-') + '</td></tr>';
            html += '<tr><th>订单号</th><td>' + (log.order_number || '-') + '</td></tr>';
            
            if (extraData.merchant_order_no) {
                html += '<tr><th>商户订单号</th><td>' + extraData.merchant_order_no + '</td></tr>';
            }
            if (extraData.onepay_order_no) {
                html += '<tr><th>OnePay订单号</th><td>' + extraData.onepay_order_no + '</td></tr>';
            }
        }
        
        // 金额信息
        if (log.amount || log.currency) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">金额信息</th></tr>';
            html += '<tr><th>金额</th><td>' + (log.amount || '-') + '</td></tr>';
            html += '<tr><th>货币</th><td>' + getCurrencyDisplay(log.currency) + '</td></tr>';
            
            if (extraData.paid_amount) {
                html += '<tr><th>实付金额</th><td>' + extraData.paid_amount + '</td></tr>';
            }
            if (extraData.order_fee) {
                html += '<tr><th>手续费</th><td>' + extraData.order_fee + '</td></tr>';
            }
        }
        
        // 支付信息
        if (extraData.pay_model || extraData.order_status) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">支付信息</th></tr>';
            
            if (extraData.order_status) {
                html += '<tr><th>支付状态</th><td>' + getOrderStatusBadge(extraData.order_status) + '</td></tr>';
            }
            if (extraData.pay_model) {
                html += '<tr><th>支付方式</th><td>' + extraData.pay_model + '</td></tr>';
            }
            if (extraData.pay_type) {
                html += '<tr><th>支付类型</th><td>' + extraData.pay_type + '</td></tr>';
            }
        }
        
        // 签名信息
        if (extraData.signature_status || extraData.signature_valid !== undefined) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">签名验证</th></tr>';
            html += '<tr><th>验签状态</th><td>' + getSignatureStatusBadge(extraData.signature_status) + '</td></tr>';
            
            if (extraData.signature_checked_at) {
                html += '<tr><th>验签时间</th><td>' + extraData.signature_checked_at + '</td></tr>';
            }
        }
        
        // 处理信息
        if (extraData.processing_status || extraData.processing_message) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">处理信息</th></tr>';
            
            if (extraData.processing_status) {
                html += '<tr><th>处理状态</th><td>' + getStatusBadge(extraData.processing_status) + '</td></tr>';
            }
            if (extraData.processing_message) {
                html += '<tr><th>处理消息</th><td>' + extraData.processing_message + '</td></tr>';
            }
            if (extraData.processed_at) {
                html += '<tr><th>处理时间</th><td>' + extraData.processed_at + '</td></tr>';
            }
        }
        
        // 错误信息
        if (log.error_message) {
            html += '<tr><th colspan="2" style="background: #f8f9fa; font-weight: bold;">错误信息</th></tr>';
            html += '<tr><th>错误详情</th><td style="color: #dc3545; word-break: break-all;">' + log.error_message + '</td></tr>';
        }
        
        html += '</table></div>';
        return html;
    }
    
    /**
     * 生成请求数据标签页
     */
    function generateRequestDataTab(log, extraData) {
        let html = '<div class="onepay-tab-pane" data-tab="request">';
        
        if (log.request_data) {
            try {
                const requestData = typeof log.request_data === 'string' ? 
                    JSON.parse(log.request_data) : log.request_data;
                
                html += '<h4>请求结构</h4>';
                html += '<table class="onepay-detail-table">';
                html += '<tr><th>商户号</th><td>' + (requestData.merchantNo || '-') + '</td></tr>';
                html += '<tr><th>签名</th><td style="word-break: break-all; font-family: monospace; font-size: 12px;">' + 
                       (requestData.sign ? requestData.sign.substring(0, 50) + '...' : '-') + '</td></tr>';
                html += '</table>';
                
                if (requestData.result) {
                    html += '<h4>Result内容</h4>';
                    try {
                        const resultData = typeof requestData.result === 'string' ? 
                            JSON.parse(requestData.result) : requestData.result;
                        html += '<pre class="onepay-json">' + JSON.stringify(resultData, null, 2) + '</pre>';
                    } catch (e) {
                        html += '<pre class="onepay-json">' + requestData.result + '</pre>';
                    }
                }
                
                html += '<h4>完整请求数据</h4>';
                html += '<pre class="onepay-json">' + JSON.stringify(requestData, null, 2) + '</pre>';
                
            } catch (e) {
                html += '<pre class="onepay-json">' + log.request_data + '</pre>';
            }
        } else {
            html += '<div class="onepay-empty">无请求数据</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * 生成响应数据标签页
     */
    function generateResponseDataTab(log, extraData) {
        let html = '<div class="onepay-tab-pane" data-tab="response">';
        
        // 响应基本信息
        if (log.response_code || log.response_data) {
            html += '<table class="onepay-detail-table">';
            html += '<tr><th>响应码</th><td>' + (log.response_code || '-') + '</td></tr>';
            if (log.execution_time) {
                html += '<tr><th>执行时间</th><td>' + log.execution_time + ' 秒</td></tr>';
            }
            html += '</table>';
        }
        
        // 响应数据
        if (log.response_data) {
            html += '<h4>响应数据</h4>';
            try {
                const responseData = typeof log.response_data === 'string' ? 
                    JSON.parse(log.response_data) : log.response_data;
                html += '<pre class="onepay-json">' + JSON.stringify(responseData, null, 2) + '</pre>';
            } catch (e) {
                html += '<pre class="onepay-json">' + log.response_data + '</pre>';
            }
        } else {
            html += '<div class="onepay-empty">无响应数据</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * 生成处理步骤标签页
     */
    function generateProcessingStepsTab(extraData) {
        let html = '<div class="onepay-tab-pane" data-tab="steps">';
        
        if (extraData.processing_steps && extraData.processing_steps.length > 0) {
            html += '<div class="onepay-steps-timeline">';
            
            extraData.processing_steps.forEach(function(step, index) {
                const stepClass = getStepStatusClass(step.status);
                const stepIcon = getStepIcon(step.status);
                
                html += '<div class="onepay-step-item ' + stepClass + '">';
                html += '<div class="onepay-step-marker">' + stepIcon + '</div>';
                html += '<div class="onepay-step-content">';
                html += '<h5>' + getStepTitle(step.step) + ' <span class="onepay-step-status">' + step.status.toUpperCase() + '</span></h5>';
                html += '<p class="onepay-step-time">' + step.timestamp + '</p>';
                
                if (step.error) {
                    html += '<div class="onepay-step-error">❌ ' + step.error + '</div>';
                }
                
                if (step.data) {
                    html += '<div class="onepay-step-data">';
                    html += '<button type="button" class="onepay-toggle-data" data-target="step-data-' + index + '">查看数据 ▼</button>';
                    html += '<div id="step-data-' + index + '" class="onepay-step-data-content" style="display: none;">';
                    html += '<pre class="onepay-json">' + JSON.stringify(step.data, null, 2) + '</pre>';
                    html += '</div></div>';
                }
                
                html += '</div></div>';
            });
            
            html += '</div>';
        } else {
            html += '<div class="onepay-empty">无处理步骤数据</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    /**
     * 生成原始数据标签页
     */
    function generateRawDataTab(log) {
        let html = '<div class="onepay-tab-pane" data-tab="raw">';
        
        html += '<h4>完整日志数据</h4>';
        html += '<pre class="onepay-json">' + JSON.stringify(log, null, 2) + '</pre>';
        
        html += '</div>';
        return html;
    }
    
    /**
     * 初始化标签页
     */
    function initTabs() {
        // 标签页切换
        $(document).off('click', '.onepay-tab-btn').on('click', '.onepay-tab-btn', function() {
            const targetTab = $(this).data('tab');
            
            // 更新按钮状态
            $('.onepay-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // 更新内容显示
            $('.onepay-tab-pane').removeClass('active');
            $('.onepay-tab-pane[data-tab="' + targetTab + '"]').addClass('active');
        });
        
        // 步骤数据切换
        $(document).off('click', '.onepay-toggle-data').on('click', '.onepay-toggle-data', function() {
            const targetId = $(this).data('target');
            const $target = $('#' + targetId);
            const $button = $(this);
            
            if ($target.is(':visible')) {
                $target.hide();
                $button.text($button.text().replace('▲', '▼'));
            } else {
                $target.show();
                $button.text($button.text().replace('▼', '▲'));
            }
        });
    }
    
    /**
     * 获取状态徽章
     */
    function getStatusBadge(status) {
        const statusMap = {
            'success': '<span class="onepay-badge onepay-badge-success">成功</span>',
            'error': '<span class="onepay-badge onepay-badge-error">错误</span>',
            'signature_failed': '<span class="onepay-badge onepay-badge-error">验签失败</span>',
            'pending': '<span class="onepay-badge onepay-badge-warning">待处理</span>',
            'received': '<span class="onepay-badge onepay-badge-info">已接收</span>',
            'WARNING': '<span class="onepay-badge onepay-badge-warning">警告</span>',
            'ERROR': '<span class="onepay-badge onepay-badge-error">错误</span>',
            'SUCCESS': '<span class="onepay-badge onepay-badge-success">成功</span>'
        };
        
        return statusMap[status] || '<span class="onepay-badge onepay-badge-default">' + (status || '未知') + '</span>';
    }
    
    /**
     * 获取订单状态徽章
     */
    function getOrderStatusBadge(status) {
        const statusMap = {
            'SUCCESS': '<span class="onepay-badge onepay-badge-success">成功</span>',
            'PENDING': '<span class="onepay-badge onepay-badge-warning">待处理</span>',
            'FAIL': '<span class="onepay-badge onepay-badge-error">失败</span>',
            'FAILED': '<span class="onepay-badge onepay-badge-error">失败</span>',
            'CANCEL': '<span class="onepay-badge onepay-badge-warning">取消</span>',
            'WAIT3D': '<span class="onepay-badge onepay-badge-info">等待3D验证</span>'
        };
        
        return statusMap[status] || '<span class="onepay-badge onepay-badge-default">' + (status || '未知') + '</span>';
    }
    
    /**
     * 获取签名状态徽章
     */
    function getSignatureStatusBadge(status) {
        const statusMap = {
            'PASS': '<span class="onepay-badge onepay-badge-success">通过</span>',
            'FAIL': '<span class="onepay-badge onepay-badge-error">失败</span>',
            'PENDING': '<span class="onepay-badge onepay-badge-warning">待验证</span>'
        };
        
        return statusMap[status] || '<span class="onepay-badge onepay-badge-default">' + (status || '未知') + '</span>';
    }
    
    /**
     * 获取货币显示
     */
    function getCurrencyDisplay(currency) {
        const currencyMap = {
            '643': 'RUB (俄罗斯卢布)',
            '840': 'USD (美元)',
            '978': 'EUR (欧元)',
            '156': 'CNY (人民币)',
            'RUB': 'RUB (俄罗斯卢布)',
            'USD': 'USD (美元)',
            'EUR': 'EUR (欧元)',
            'CNY': 'CNY (人民币)'
        };
        
        return currencyMap[currency] || currency || '-';
    }
    
    /**
     * 获取步骤状态样式
     */
    function getStepStatusClass(status) {
        const classMap = {
            'success': 'onepay-step-success',
            'error': 'onepay-step-error',
            'warning': 'onepay-step-warning',
            'info': 'onepay-step-info'
        };
        
        return classMap[status] || 'onepay-step-default';
    }
    
    /**
     * 获取步骤图标
     */
    function getStepIcon(status) {
        const iconMap = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        
        return iconMap[status] || '◯';
    }
    
    /**
     * 获取步骤标题
     */
    function getStepTitle(stepName) {
        const titleMap = {
            '01_callback_received': '回调接收',
            '02_json_parsing': 'JSON解析',
            '03_data_validation': '数据验证',
            '04_signature_verification': '签名验证',
            '05_order_lookup': '订单查找',
            '06_order_processing': '订单处理',
            '07_order_processed': '处理完成',
            '08_response_sending': '发送响应',
            '99_signature_failed_exit': '验签失败退出',
            '99_invalid_payment_data': '支付数据无效',
            '99_exception_occurred': '异常发生'
        };
        
        return titleMap[stepName] || stepName;
    }
});