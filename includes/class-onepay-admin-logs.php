<?php
/**
 * OnePay后台日志查看器
 * 
 * 在WordPress后台提供日志查看界面
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePay_Admin_Logs {
    
    private static $instance = null;
    private $debug_logger;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $this->debug_logger = OnePay_Debug_Logger::get_instance();
        
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 注册AJAX处理
        add_action('wp_ajax_onepay_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_onepay_get_log_detail', array($this, 'ajax_get_log_detail'));
        add_action('wp_ajax_onepay_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_onepay_export_logs', array($this, 'ajax_export_logs'));
        
        // 添加管理页面样式和脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        // 在WooCommerce菜单下添加
        add_submenu_page(
            'woocommerce',
            'OnePay调试日志',
            'OnePay日志',
            'manage_woocommerce',
            'onepay-debug-logs',
            array($this, 'render_admin_page')
        );
        
        // 也在工具菜单下添加
        add_management_page(
            'OnePay调试日志',
            'OnePay日志',
            'manage_options',
            'onepay-logs-tool',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        ?>
        <div class="wrap onepay-logs-wrap">
            <h1>OnePay调试日志查看器</h1>
            
            <!-- 统计信息 -->
            <div class="onepay-stats-cards">
                <div class="stats-card">
                    <h3>今日支付</h3>
                    <div class="stats-value" id="stats-today-payments">0</div>
                </div>
                <div class="stats-card">
                    <h3>成功率</h3>
                    <div class="stats-value" id="stats-success-rate">0%</div>
                </div>
                <div class="stats-card">
                    <h3>总金额</h3>
                    <div class="stats-value" id="stats-total-amount">¥0.00</div>
                </div>
                <div class="stats-card">
                    <h3>错误数</h3>
                    <div class="stats-value error" id="stats-errors">0</div>
                </div>
            </div>
            
            <!-- 过滤器 -->
            <div class="onepay-filters">
                <div class="filter-row">
                    <div class="filter-item">
                        <label>日志类型:</label>
                        <select id="filter-log-type">
                            <option value="">全部</option>
                            <option value="payment_request">支付请求</option>
                            <option value="api_request">API请求</option>
                            <option value="api_response">API响应</option>
                            <option value="callback">回调通知</option>
                            <option value="error">错误</option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label>状态:</label>
                        <select id="filter-status">
                            <option value="">全部</option>
                            <option value="pending">待处理</option>
                            <option value="success">成功</option>
                            <option value="failed">失败</option>
                            <option value="error">错误</option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label>订单号:</label>
                        <input type="text" id="filter-order-id" placeholder="输入订单号">
                    </div>
                    
                    <div class="filter-item">
                        <label>日期范围:</label>
                        <input type="date" id="filter-date-from">
                        <span>至</span>
                        <input type="date" id="filter-date-to">
                    </div>
                    
                    <div class="filter-actions">
                        <button class="button button-primary" id="btn-search">搜索</button>
                        <button class="button" id="btn-reset">重置</button>
                        <button class="button" id="btn-refresh">刷新</button>
                        <button class="button" id="btn-export">导出CSV</button>
                        <button class="button button-link-delete" id="btn-clear">清理旧日志</button>
                    </div>
                </div>
            </div>
            
            <!-- 日志表格 -->
            <table class="wp-list-table widefat fixed striped" id="logs-table">
                <thead>
                    <tr>
                        <th width="150">时间</th>
                        <th width="100">类型</th>
                        <th width="80">订单号</th>
                        <th width="100">用户</th>
                        <th width="120">IP地址</th>
                        <th width="80">金额</th>
                        <th width="80">支付方式</th>
                        <th width="80">状态</th>
                        <th>信息摘要</th>
                        <th width="100">操作</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <tr><td colspan="10" class="loading">加载中...</td></tr>
                </tbody>
            </table>
            
            <!-- 分页 -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">共 <span id="total-items">0</span> 条记录</span>
                    <span class="pagination-links">
                        <button class="button" id="btn-first-page" disabled>«</button>
                        <button class="button" id="btn-prev-page" disabled>‹</button>
                        <span class="paging-input">
                            第 <input type="number" id="current-page" value="1" min="1" class="current-page"> 页，
                            共 <span id="total-pages">1</span> 页
                        </span>
                        <button class="button" id="btn-next-page">›</button>
                        <button class="button" id="btn-last-page">»</button>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- 详情弹窗 -->
        <div id="log-detail-modal" class="onepay-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>日志详情</h2>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body" id="log-detail-content">
                    <!-- 动态内容 -->
                </div>
            </div>
        </div>
        
        <style>
            .onepay-logs-wrap { padding: 20px; }
            
            /* 统计卡片 */
            .onepay-stats-cards {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
            }
            .stats-card {
                flex: 1;
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            .stats-card h3 {
                margin: 0 0 10px 0;
                color: #666;
                font-size: 14px;
            }
            .stats-value {
                font-size: 28px;
                font-weight: bold;
                color: #2271b1;
            }
            .stats-value.error { color: #d63638; }
            
            /* 过滤器 */
            .onepay-filters {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 15px;
                margin-bottom: 20px;
            }
            .filter-row {
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            .filter-item {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .filter-item label {
                font-weight: 600;
                white-space: nowrap;
            }
            .filter-actions {
                margin-left: auto;
                display: flex;
                gap: 10px;
            }
            
            /* 表格 */
            #logs-table { margin-top: 0; }
            #logs-table td.loading { text-align: center; padding: 20px; }
            .log-type-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .log-type-payment_request { background: #e8f5e9; color: #2e7d32; }
            .log-type-api_request { background: #e3f2fd; color: #1565c0; }
            .log-type-api_response { background: #f3e5f5; color: #6a1b9a; }
            .log-type-callback { background: #fff3e0; color: #e65100; }
            .log-type-error { background: #ffebee; color: #c62828; }
            
            .status-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .status-success { background: #d4edda; color: #155724; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-failed { background: #f8d7da; color: #721c24; }
            .status-error { background: #f8d7da; color: #721c24; }
            
            /* 弹窗 */
            .onepay-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .modal-content {
                background: white;
                width: 80%;
                max-width: 900px;
                max-height: 80vh;
                border-radius: 4px;
                display: flex;
                flex-direction: column;
            }
            .modal-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .modal-header h2 { margin: 0; }
            .modal-header .close {
                font-size: 28px;
                cursor: pointer;
                color: #999;
            }
            .modal-header .close:hover { color: #333; }
            .modal-body {
                padding: 20px;
                overflow-y: auto;
                flex: 1;
            }
            
            /* 详情内容 */
            .detail-section {
                margin-bottom: 20px;
            }
            .detail-section h3 {
                margin: 0 0 10px 0;
                padding: 5px 10px;
                background: #f0f0f1;
                border-left: 3px solid #2271b1;
            }
            .detail-grid {
                display: grid;
                grid-template-columns: 150px 1fr;
                gap: 10px;
            }
            .detail-label {
                font-weight: 600;
                color: #666;
            }
            .detail-value {
                word-break: break-all;
            }
            .json-viewer {
                background: #f5f5f5;
                border: 1px solid #ddd;
                padding: 10px;
                border-radius: 3px;
                font-family: monospace;
                font-size: 12px;
                white-space: pre-wrap;
                max-height: 300px;
                overflow-y: auto;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let totalPages = 1;
            let logsData = [];
            
            // 加载日志
            function loadLogs() {
                const filters = {
                    log_type: $('#filter-log-type').val(),
                    status: $('#filter-status').val(),
                    order_id: $('#filter-order-id').val(),
                    date_from: $('#filter-date-from').val(),
                    date_to: $('#filter-date-to').val(),
                    page: currentPage
                };
                
                $.post(ajaxurl, {
                    action: 'onepay_get_logs',
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        displayLogs(response.data.logs);
                        updatePagination(response.data.total, response.data.pages);
                        updateStats(response.data.stats);
                    }
                });
            }
            
            // 显示日志
            function displayLogs(logs) {
                logsData = logs;
                const tbody = $('#logs-tbody');
                tbody.empty();
                
                if (logs.length === 0) {
                    tbody.append('<tr><td colspan="10" style="text-align:center;">没有找到日志记录</td></tr>');
                    return;
                }
                
                logs.forEach(function(log) {
                    const row = `
                        <tr>
                            <td>${log.log_time}</td>
                            <td><span class="log-type-badge log-type-${log.log_type}">${getLogTypeLabel(log.log_type)}</span></td>
                            <td>${log.order_number || '-'}</td>
                            <td>${log.user_name || '-'}</td>
                            <td>${log.user_ip || '-'}</td>
                            <td>${log.amount ? '¥' + log.amount : '-'}</td>
                            <td>${log.payment_method || '-'}</td>
                            <td><span class="status-badge status-${log.status}">${getStatusLabel(log.status)}</span></td>
                            <td>${getSummary(log)}</td>
                            <td>
                                <button class="button button-small view-detail" data-id="${log.id}">查看详情</button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
            }
            
            // 获取日志类型标签
            function getLogTypeLabel(type) {
                const labels = {
                    'payment_request': '支付请求',
                    'api_request': 'API请求',
                    'api_response': 'API响应',
                    'callback': '回调通知',
                    'error': '错误'
                };
                return labels[type] || type;
            }
            
            // 获取状态标签
            function getStatusLabel(status) {
                const labels = {
                    'pending': '待处理',
                    'success': '成功',
                    'failed': '失败',
                    'error': '错误',
                    'sent': '已发送',
                    'received': '已接收',
                    'completed': '已完成'
                };
                return labels[status] || status;
            }
            
            // 获取摘要信息
            function getSummary(log) {
                if (log.error_message) {
                    return log.error_message.substring(0, 50) + (log.error_message.length > 50 ? '...' : '');
                }
                if (log.response_code) {
                    return '响应码: ' + log.response_code;
                }
                if (log.request_url) {
                    return log.request_url.substring(0, 50) + (log.request_url.length > 50 ? '...' : '');
                }
                return '-';
            }
            
            // 更新分页
            function updatePagination(total, pages) {
                totalPages = pages;
                $('#total-items').text(total);
                $('#total-pages').text(pages);
                $('#current-page').val(currentPage).attr('max', pages);
                
                $('#btn-first-page, #btn-prev-page').prop('disabled', currentPage <= 1);
                $('#btn-next-page, #btn-last-page').prop('disabled', currentPage >= pages);
            }
            
            // 更新统计
            function updateStats(stats) {
                if (stats) {
                    $('#stats-today-payments').text(stats.today_payments || 0);
                    $('#stats-success-rate').text((stats.success_rate || 0) + '%');
                    $('#stats-total-amount').text('¥' + (stats.total_amount || 0));
                    $('#stats-errors').text(stats.errors || 0);
                }
            }
            
            // 查看详情
            $(document).on('click', '.view-detail', function() {
                const logId = $(this).data('id');
                $.post(ajaxurl, {
                    action: 'onepay_get_log_detail',
                    log_id: logId
                }, function(response) {
                    if (response.success) {
                        showLogDetail(response.data);
                    }
                });
            });
            
            // 显示日志详情
            function showLogDetail(log) {
                let content = `
                    <div class="detail-section">
                        <h3>基本信息</h3>
                        <div class="detail-grid">
                            <div class="detail-label">日志时间:</div>
                            <div class="detail-value">${log.log_time}</div>
                            <div class="detail-label">日志类型:</div>
                            <div class="detail-value">${getLogTypeLabel(log.log_type)}</div>
                            <div class="detail-label">状态:</div>
                            <div class="detail-value">${getStatusLabel(log.status)}</div>
                            ${log.execution_time ? `
                            <div class="detail-label">执行时间:</div>
                            <div class="detail-value">${log.execution_time}秒</div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                if (log.order_id) {
                    content += `
                        <div class="detail-section">
                            <h3>订单信息</h3>
                            <div class="detail-grid">
                                <div class="detail-label">订单ID:</div>
                                <div class="detail-value">${log.order_id}</div>
                                <div class="detail-label">订单号:</div>
                                <div class="detail-value">${log.order_number || '-'}</div>
                                <div class="detail-label">金额:</div>
                                <div class="detail-value">¥${log.amount} ${log.currency}</div>
                                <div class="detail-label">支付方式:</div>
                                <div class="detail-value">${log.payment_method || '-'}</div>
                            </div>
                        </div>
                    `;
                }
                
                if (log.user_id) {
                    content += `
                        <div class="detail-section">
                            <h3>用户信息</h3>
                            <div class="detail-grid">
                                <div class="detail-label">用户ID:</div>
                                <div class="detail-value">${log.user_id}</div>
                                <div class="detail-label">用户名:</div>
                                <div class="detail-value">${log.user_name || '-'}</div>
                                <div class="detail-label">邮箱:</div>
                                <div class="detail-value">${log.user_email || '-'}</div>
                                <div class="detail-label">IP地址:</div>
                                <div class="detail-value">${log.user_ip || '-'}</div>
                            </div>
                        </div>
                    `;
                }
                
                if (log.request_url) {
                    content += `
                        <div class="detail-section">
                            <h3>请求信息</h3>
                            <div class="detail-grid">
                                <div class="detail-label">请求URL:</div>
                                <div class="detail-value">${log.request_url}</div>
                            </div>
                        </div>
                    `;
                }
                
                if (log.request_data) {
                    content += `
                        <div class="detail-section">
                            <h3>请求数据</h3>
                            <div class="json-viewer">${formatJson(log.request_data)}</div>
                        </div>
                    `;
                }
                
                if (log.response_data) {
                    content += `
                        <div class="detail-section">
                            <h3>响应数据</h3>
                            <div class="json-viewer">${formatJson(log.response_data)}</div>
                        </div>
                    `;
                }
                
                if (log.error_message) {
                    content += `
                        <div class="detail-section">
                            <h3>错误信息</h3>
                            <div style="color: red; padding: 10px; background: #fee; border: 1px solid #fcc;">
                                ${log.error_message}
                            </div>
                        </div>
                    `;
                }
                
                if (log.extra_data) {
                    content += `
                        <div class="detail-section">
                            <h3>额外数据</h3>
                            <div class="json-viewer">${formatJson(log.extra_data)}</div>
                        </div>
                    `;
                }
                
                $('#log-detail-content').html(content);
                $('#log-detail-modal').show();
            }
            
            // 格式化JSON
            function formatJson(data) {
                try {
                    const obj = typeof data === 'string' ? JSON.parse(data) : data;
                    return JSON.stringify(obj, null, 2);
                } catch (e) {
                    return data;
                }
            }
            
            // 事件绑定
            $('#btn-search').click(loadLogs);
            $('#btn-reset').click(function() {
                $('#filter-log-type, #filter-status, #filter-order-id').val('');
                $('#filter-date-from, #filter-date-to').val('');
                currentPage = 1;
                loadLogs();
            });
            $('#btn-refresh').click(loadLogs);
            
            // 分页
            $('#btn-first-page').click(function() {
                currentPage = 1;
                loadLogs();
            });
            $('#btn-prev-page').click(function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadLogs();
                }
            });
            $('#btn-next-page').click(function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadLogs();
                }
            });
            $('#btn-last-page').click(function() {
                currentPage = totalPages;
                loadLogs();
            });
            $('#current-page').change(function() {
                const page = parseInt($(this).val());
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    loadLogs();
                }
            });
            
            // 关闭弹窗
            $('.close, .onepay-modal').click(function(e) {
                if (e.target === this) {
                    $('#log-detail-modal').hide();
                }
            });
            
            // 导出CSV
            $('#btn-export').click(function() {
                const filters = {
                    log_type: $('#filter-log-type').val(),
                    status: $('#filter-status').val(),
                    order_id: $('#filter-order-id').val(),
                    date_from: $('#filter-date-from').val(),
                    date_to: $('#filter-date-to').val()
                };
                
                const params = $.param({action: 'onepay_export_logs', filters: filters});
                window.open(ajaxurl + '?' + params, '_blank');
            });
            
            // 清理日志
            $('#btn-clear').click(function() {
                if (confirm('确定要清理30天前的日志吗？')) {
                    $.post(ajaxurl, {
                        action: 'onepay_clear_logs',
                        days: 30
                    }, function(response) {
                        if (response.success) {
                            alert('清理成功');
                            loadLogs();
                        }
                    });
                }
            });
            
            // 初始加载
            loadLogs();
            
            // 自动刷新（每30秒）
            setInterval(loadLogs, 30000);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX获取日志列表
     */
    public function ajax_get_logs() {
        check_ajax_referer('wp_rest', '_wpnonce', false);
        
        $filters = $_POST['filters'] ?? array();
        $page = intval($filters['page'] ?? 1);
        $per_page = 20;
        
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'log_type' => $filters['log_type'] ?? '',
            'status' => $filters['status'] ?? '',
            'order_id' => $filters['order_id'] ?? 0,
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? ''
        );
        
        $logs = $this->debug_logger->get_logs($args);
        
        // 获取总数
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // 获取统计信息
        $today = date('Y-m-d');
        $stats = array(
            'today_payments' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE log_type = 'payment_request' AND DATE(log_time) = %s",
                $today
            )),
            'success_rate' => $this->calculate_success_rate(),
            'total_amount' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$table_name} WHERE DATE(log_time) = %s AND status = 'success'",
                $today
            )) ?: 0,
            'errors' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE log_type = 'error' AND DATE(log_time) = %s",
                $today
            ))
        );
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'stats' => $stats
        ));
    }
    
    /**
     * AJAX获取日志详情
     */
    public function ajax_get_log_detail() {
        check_ajax_referer('wp_rest', '_wpnonce', false);
        
        $log_id = intval($_POST['log_id'] ?? 0);
        
        if (!$log_id) {
            wp_send_json_error('无效的日志ID');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $log_id
        ), ARRAY_A);
        
        if (!$log) {
            wp_send_json_error('日志不存在');
        }
        
        wp_send_json_success($log);
    }
    
    /**
     * AJAX清理日志
     */
    public function ajax_clear_logs() {
        check_ajax_referer('wp_rest', '_wpnonce', false);
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('权限不足');
        }
        
        $days = intval($_POST['days'] ?? 30);
        $this->debug_logger->cleanup_old_logs($days);
        
        wp_send_json_success('清理成功');
    }
    
    /**
     * 导出日志为CSV
     */
    public function ajax_export_logs() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('权限不足');
        }
        
        $filters = $_GET['filters'] ?? array();
        
        $args = array(
            'limit' => 10000, // 最多导出10000条
            'log_type' => $filters['log_type'] ?? '',
            'status' => $filters['status'] ?? '',
            'order_id' => $filters['order_id'] ?? 0,
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? ''
        );
        
        $logs = $this->debug_logger->get_logs($args);
        
        // 设置CSV头
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="onepay-logs-' . date('Y-m-d-His') . '.csv"');
        
        // 输出BOM以支持Excel正确识别UTF-8
        echo "\xEF\xBB\xBF";
        
        // 创建输出流
        $output = fopen('php://output', 'w');
        
        // 写入表头
        fputcsv($output, array(
            '时间', '类型', '订单号', '用户', 'IP地址', '金额', '货币', 
            '支付方式', '状态', '响应码', '错误信息', '执行时间'
        ));
        
        // 写入数据
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->log_time,
                $log->log_type,
                $log->order_number,
                $log->user_name,
                $log->user_ip,
                $log->amount,
                $log->currency,
                $log->payment_method,
                $log->status,
                $log->response_code,
                $log->error_message,
                $log->execution_time
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 计算成功率
     */
    private function calculate_success_rate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        $today = date('Y-m-d');
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE log_type = 'payment_request' AND DATE(log_time) = %s",
            $today
        ));
        
        if ($total == 0) {
            return 0;
        }
        
        $success = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE log_type = 'payment_request' AND status = 'success' AND DATE(log_time) = %s",
            $today
        ));
        
        return round(($success / $total) * 100, 2);
    }
    
    /**
     * 加载管理资源
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'onepay-debug-logs') === false && strpos($hook, 'onepay-logs-tool') === false) {
            return;
        }
        
        // 如果需要额外的CSS/JS文件可以在这里加载
    }
}

// 初始化
OnePay_Admin_Logs::get_instance();