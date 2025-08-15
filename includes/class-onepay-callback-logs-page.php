<?php
/**
 * OnePay回调日志页面类
 * 独立的WordPress后台页面，用于查看和管理回调日志
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePay_Callback_Logs_Page {
    
    private $debug_logger;
    
    public function __construct() {
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $this->debug_logger = OnePay_Debug_Logger::get_instance();
    }
    
    /**
     * 显示页面
     */
    public function display() {
        // 处理表单提交
        if (isset($_POST['action'])) {
            $this->handle_form_actions();
        }
        
        // 获取分页参数
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // 获取筛选参数
        $log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // 构建查询参数
        $query_args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'order_by' => 'log_time',
            'order' => 'DESC'
        );
        
        if ($log_type) {
            $query_args['log_type'] = $log_type;
        }
        if ($status) {
            $query_args['status'] = $status;
        }
        if ($date_from) {
            $query_args['date_from'] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $query_args['date_to'] = $date_to . ' 23:59:59';
        }
        
        // 获取日志数据
        $logs = $this->debug_logger->get_logs($query_args);
        
        // 获取总数用于分页
        $total_count = $this->get_logs_count($query_args);
        $total_pages = ceil($total_count / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('OnePay回调日志', 'onepay'); ?></h1>
            
            <?php $this->display_filters($log_type, $status, $date_from, $date_to); ?>
            
            <?php $this->display_statistics(); ?>
            
            <?php $this->display_logs_table($logs); ?>
            
            <?php $this->display_pagination($page, $total_pages, $total_count); ?>
        </div>
        
        <?php $this->add_page_styles(); ?>
        <?php $this->add_page_scripts(); ?>
        <?php
    }
    
    /**
     * 显示筛选器
     */
    private function display_filters($log_type, $status, $date_from, $date_to) {
        ?>
        <div class="onepay-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="onepay-callback-logs">
                
                <select name="log_type">
                    <option value=""><?php _e('所有类型', 'onepay'); ?></option>
                    <option value="async_callback" <?php selected($log_type, 'async_callback'); ?>><?php _e('异步回调', 'onepay'); ?></option>
                    <option value="callback" <?php selected($log_type, 'callback'); ?>><?php _e('历史回调', 'onepay'); ?></option>
                    <option value="api_request" <?php selected($log_type, 'api_request'); ?>><?php _e('API请求', 'onepay'); ?></option>
                </select>
                
                <select name="status">
                    <option value=""><?php _e('所有状态', 'onepay'); ?></option>
                    <option value="received" <?php selected($status, 'received'); ?>><?php _e('已接收', 'onepay'); ?></option>
                    <option value="success" <?php selected($status, 'success'); ?>><?php _e('成功', 'onepay'); ?></option>
                    <option value="error" <?php selected($status, 'error'); ?>><?php _e('错误', 'onepay'); ?></option>
                    <option value="signature_failed" <?php selected($status, 'signature_failed'); ?>><?php _e('验签失败', 'onepay'); ?></option>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('开始日期', 'onepay'); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('结束日期', 'onepay'); ?>">
                
                <button type="submit" class="button"><?php _e('筛选', 'onepay'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>" class="button"><?php _e('重置', 'onepay'); ?></a>
                <button type="button" id="refresh-logs" class="button button-primary"><?php _e('刷新', 'onepay'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * 显示统计信息
     */
    private function display_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        
        $stats = $wpdb->get_results("
            SELECT 
                log_type,
                status,
                COUNT(*) as count
            FROM {$table_name} 
            WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY log_type, status
        ", ARRAY_A);
        
        $total_24h = 0;
        $success_24h = 0;
        $failed_24h = 0;
        $signature_failed_24h = 0;
        
        foreach ($stats as $stat) {
            $total_24h += $stat['count'];
            
            if ($stat['status'] === 'success') {
                $success_24h += $stat['count'];
            } elseif ($stat['status'] === 'error') {
                $failed_24h += $stat['count'];
            } elseif ($stat['status'] === 'signature_failed') {
                $signature_failed_24h += $stat['count'];
            }
        }
        
        ?>
        <div class="onepay-stats">
            <h3><?php _e('24小时统计', 'onepay'); ?></h3>
            <div class="stats-grid">
                <div class="stat-item total">
                    <span class="stat-number"><?php echo $total_24h; ?></span>
                    <span class="stat-label"><?php _e('总回调', 'onepay'); ?></span>
                </div>
                <div class="stat-item success">
                    <span class="stat-number"><?php echo $success_24h; ?></span>
                    <span class="stat-label"><?php _e('成功', 'onepay'); ?></span>
                </div>
                <div class="stat-item failed">
                    <span class="stat-number"><?php echo $failed_24h; ?></span>
                    <span class="stat-label"><?php _e('失败', 'onepay'); ?></span>
                </div>
                <div class="stat-item signature-failed">
                    <span class="stat-number"><?php echo $signature_failed_24h; ?></span>
                    <span class="stat-label"><?php _e('验签失败', 'onepay'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 显示日志表格
     */
    private function display_logs_table($logs) {
        ?>
        <div class="onepay-logs-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('时间', 'onepay'); ?></th>
                        <th scope="col"><?php _e('类型', 'onepay'); ?></th>
                        <th scope="col"><?php _e('商户订单号', 'onepay'); ?></th>
                        <th scope="col"><?php _e('OnePay订单号', 'onepay'); ?></th>
                        <th scope="col"><?php _e('订单状态', 'onepay'); ?></th>
                        <th scope="col"><?php _e('验签', 'onepay'); ?></th>
                        <th scope="col"><?php _e('订单金额', 'onepay'); ?></th>
                        <th scope="col"><?php _e('实付金额', 'onepay'); ?></th>
                        <th scope="col"><?php _e('支付方式', 'onepay'); ?></th>
                        <th scope="col"><?php _e('币种', 'onepay'); ?></th>
                        <th scope="col"><?php _e('操作', 'onepay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="11" class="no-items"><?php _e('暂无回调记录', 'onepay'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php $this->render_log_row($log); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * 渲染单行日志
     */
    private function render_log_row($log) {
        $extra_data = !empty($log->extra_data) ? json_decode($log->extra_data, true) : array();
        
        // 获取所有字段信息
        $merchant_order_no = $extra_data['merchant_order_no'] ?? '';
        $onepay_order_no = $extra_data['onepay_order_no'] ?? ($log->order_number ?: '');
        $order_status = $extra_data['order_status'] ?? $log->response_code ?? '';
        $order_amount = $extra_data['order_amount'] ?? 0;
        $paid_amount = $extra_data['paid_amount'] ?? $log->amount ?? 0;
        $pay_model = $extra_data['pay_model'] ?? $log->payment_method ?? '';
        $currency = $extra_data['currency'] ?? $log->currency ?? '';
        
        // 获取验签状态
        $signature_status = '';
        if ($log->log_type === 'async_callback') {
            $signature_valid = $extra_data['signature_valid'] ?? null;
            if ($signature_valid === true) {
                $signature_status = '<span class="signature-pass">PASS</span>';
            } elseif ($signature_valid === false) {
                $signature_status = '<span class="signature-fail">FAIL</span>';
            } else {
                $signature_status = '<span class="signature-unknown">-</span>';
            }
        } else {
            $signature_status = '<span class="signature-unknown">-</span>';
        }
        
        // 格式化时间
        $display_time = $log->log_time ? date('m-d H:i:s', strtotime($log->log_time)) : '-';
        
        ?>
        <tr class="log-row log-<?php echo esc_attr($log->status); ?>">
            <td><?php echo esc_html($display_time); ?></td>
            <td>
                <span class="log-type log-type-<?php echo esc_attr($log->log_type); ?>">
                    <?php echo esc_html($this->get_log_type_label($log->log_type)); ?>
                </span>
            </td>
            <td><?php echo esc_html($merchant_order_no ?: '-'); ?></td>
            <td><?php echo esc_html($onepay_order_no ?: '-'); ?></td>
            <td>
                <?php if ($order_status): ?>
                    <span class="order-status order-status-<?php echo esc_attr(strtolower($order_status)); ?>">
                        <?php echo esc_html($this->get_order_status_label($order_status)); ?>
                    </span>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><?php echo $signature_status; ?></td>
            <td><?php echo $order_amount ? '¥' . number_format($order_amount, 2) : '-'; ?></td>
            <td><?php echo $paid_amount ? '¥' . number_format($paid_amount, 2) : '-'; ?></td>
            <td><?php echo esc_html($pay_model ?: '-'); ?></td>
            <td><?php echo esc_html($currency ?: '-'); ?></td>
            <td>
                <button type="button" class="button button-small view-detail" data-id="<?php echo esc_attr($log->id); ?>">
                    <?php _e('查看详情', 'onepay'); ?>
                </button>
            </td>
        </tr>
        <?php
    }
    
    /**
     * 显示分页
     */
    private function display_pagination($current_page, $total_pages, $total_count) {
        if ($total_pages <= 1) {
            return;
        }
        
        $base_url = admin_url('admin.php?page=onepay-callback-logs');
        
        // 保持筛选参数
        $query_params = array();
        if (!empty($_GET['log_type'])) {
            $query_params['log_type'] = $_GET['log_type'];
        }
        if (!empty($_GET['status'])) {
            $query_params['status'] = $_GET['status'];
        }
        if (!empty($_GET['date_from'])) {
            $query_params['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $query_params['date_to'] = $_GET['date_to'];
        }
        
        ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(__('共 %s 条记录', 'onepay'), number_format_i18n($total_count)); ?></span>
                
                <?php if ($current_page > 1): ?>
                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg(array_merge($query_params, array('paged' => $current_page - 1)), $base_url)); ?>">
                        <span class="screen-reader-text"><?php _e('上一页', 'onepay'); ?></span>
                        <span aria-hidden="true">‹</span>
                    </a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <label for="current-page-selector" class="screen-reader-text"><?php _e('当前页', 'onepay'); ?></label>
                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo $current_page; ?>" size="<?php echo strlen($total_pages); ?>" aria-describedby="table-paging">
                    <span class="tablenav-paging-text"> / <span class="total-pages"><?php echo $total_pages; ?></span></span>
                </span>
                
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg(array_merge($query_params, array('paged' => $current_page + 1)), $base_url)); ?>">
                        <span class="screen-reader-text"><?php _e('下一页', 'onepay'); ?></span>
                        <span aria-hidden="true">›</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 获取日志类型标签
     */
    private function get_log_type_label($log_type) {
        $labels = array(
            'async_callback' => __('异步回调', 'onepay'),
            'callback' => __('历史回调', 'onepay'),
            'api_request' => __('API请求', 'onepay'),
            'payment_request' => __('支付请求', 'onepay'),
            'error' => __('错误', 'onepay')
        );
        
        return $labels[$log_type] ?? $log_type;
    }
    
    /**
     * 获取状态标签
     */
    private function get_status_label($status) {
        $labels = array(
            'received' => __('已接收', 'onepay'),
            'success' => __('成功', 'onepay'),
            'error' => __('错误', 'onepay'),
            'signature_failed' => __('验签失败', 'onepay'),
            'pending' => __('待处理', 'onepay')
        );
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * 获取订单状态标签
     */
    private function get_order_status_label($order_status) {
        $labels = array(
            'SUCCESS' => __('成功', 'onepay'),
            'PENDING' => __('待支付', 'onepay'),
            'FAIL' => __('失败', 'onepay'),
            'FAILED' => __('失败', 'onepay'),
            'CANCEL' => __('已取消', 'onepay'),
            'WAIT3D' => __('等待3D验证', 'onepay')
        );
        
        return $labels[$order_status] ?? $order_status;
    }
    
    /**
     * 获取日志总数
     */
    private function get_logs_count($query_args) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_debug_logs';
        
        $where = array('1=1');
        
        if (!empty($query_args['log_type'])) {
            $where[] = $wpdb->prepare("log_type = %s", $query_args['log_type']);
        }
        if (!empty($query_args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $query_args['status']);
        }
        if (!empty($query_args['date_from'])) {
            $where[] = $wpdb->prepare("log_time >= %s", $query_args['date_from']);
        }
        if (!empty($query_args['date_to'])) {
            $where[] = $wpdb->prepare("log_time <= %s", $query_args['date_to']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}");
    }
    
    /**
     * 处理表单操作
     */
    private function handle_form_actions() {
        // 可以在这里添加批量操作等功能
    }
    
    /**
     * 添加页面样式
     */
    private function add_page_styles() {
        ?>
        <style>
        .onepay-filters {
            background: #fff;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .onepay-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .onepay-stats {
            background: #fff;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .stat-item.total { background: #f0f8ff; }
        .stat-item.success { background: #f0fff4; }
        .stat-item.failed { background: #fff5f5; }
        .stat-item.signature-failed { background: #fffbf0; }
        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .log-type {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
        }
        .log-type-async_callback { background: #d1ecf1; color: #0c5460; }
        .log-type-callback { background: #fff3cd; color: #856404; }
        .log-type-api_request { background: #e2e3e5; color: #383d41; }
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-received { background: #d1ecf1; color: #0c5460; }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-signature_failed { background: #fff3cd; color: #856404; }
        .signature-pass { color: #28a745; font-weight: bold; }
        .signature-fail { color: #dc3545; font-weight: bold; }
        .order-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .order-status-success { background: #d4edda; color: #155724; }
        .order-status-pending { background: #fff3cd; color: #856404; }
        .order-status-fail, .order-status-failed { background: #f8d7da; color: #721c24; }
        .order-status-cancel { background: #e2e3e5; color: #383d41; }
        .order-status-wait3d { background: #d1ecf1; color: #0c5460; }
        .signature-unknown { color: #6c757d; }
        .onepay-logs-table-wrapper {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        /* 弹窗样式 */
        .onepay-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .onepay-modal-content {
            background: white;
            border-radius: 4px;
            width: 90%;
            max-width: 800px;
            max-height: 80%;
            overflow: hidden;
            position: relative;
        }
        .onepay-modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .onepay-modal-header h3 {
            margin: 0;
        }
        .onepay-modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .onepay-modal-close:hover {
            color: #000;
        }
        .onepay-modal-body {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        .detail-tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .detail-tab-button {
            background: none;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-right: 10px;
        }
        .detail-tab-button.active {
            border-bottom-color: #0073aa;
            color: #0073aa;
            font-weight: bold;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .detail-table td:first-child {
            font-weight: bold;
            width: 150px;
            background: #f9f9f9;
        }
        .json-code {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        </style>
        <?php
    }
    
    /**
     * 添加页面脚本
     */
    private function add_page_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 刷新按钮
            $('#refresh-logs').on('click', function() {
                location.reload();
            });
            
            // 查看详情按钮
            $('.view-detail').on('click', function() {
                var logId = $(this).data('id');
                showLogDetail(logId);
            });
            
            // 关闭弹窗
            $(document).on('click', '.onepay-modal-close, .onepay-modal-overlay', function() {
                $('#onepay-detail-modal').remove();
            });
        });
        
        function showLogDetail(logId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'onepay_get_log_detail',
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        displayLogDetail(response.data);
                    } else {
                        alert('获取详情失败: ' + response.data);
                    }
                },
                error: function() {
                    alert('请求失败，请稍后重试');
                }
            });
        }
        
        function displayLogDetail(data) {
            var extraData = {};
            try {
                extraData = JSON.parse(data.extra_data || '{}');
            } catch(e) {
                extraData = {};
            }
            
            var requestData = {};
            try {
                requestData = JSON.parse(data.request_data || '{}');
            } catch(e) {
                requestData = {};
            }
            
            var modalHtml = '<div id="onepay-detail-modal" class="onepay-modal-overlay">' +
                '<div class="onepay-modal-content">' +
                '<div class="onepay-modal-header">' +
                '<h3>回调详情 #' + data.id + '</h3>' +
                '<span class="onepay-modal-close">&times;</span>' +
                '</div>' +
                '<div class="onepay-modal-body">' +
                '<div class="detail-tabs">' +
                '<button class="detail-tab-button active" onclick="switchDetailTab(\'summary\')">基本信息</button>' +
                '<button class="detail-tab-button" onclick="switchDetailTab(\'request\')">原始数据</button>' +
                '<button class="detail-tab-button" onclick="switchDetailTab(\'extra\')">解析数据</button>' +
                '</div>' +
                '<div id="summary-tab" class="detail-tab-content active">' + generateSummaryTab(data, extraData) + '</div>' +
                '<div id="request-tab" class="detail-tab-content" style="display:none;">' + generateRequestTab(requestData) + '</div>' +
                '<div id="extra-tab" class="detail-tab-content" style="display:none;">' + generateExtraTab(extraData) + '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
        }
        
        function generateSummaryTab(data, extraData) {
            return '<table class="detail-table">' +
                '<tr><td>接收时间</td><td>' + (data.log_time || '-') + '</td></tr>' +
                '<tr><td>商户订单号</td><td>' + (extraData.merchant_order_no || '-') + '</td></tr>' +
                '<tr><td>OnePay订单号</td><td>' + (extraData.onepay_order_no || '-') + '</td></tr>' +
                '<tr><td>订单状态</td><td>' + (extraData.order_status || '-') + '</td></tr>' +
                '<tr><td>订单金额</td><td>' + (extraData.order_amount ? '¥' + parseFloat(extraData.order_amount).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>实付金额</td><td>' + (extraData.paid_amount ? '¥' + parseFloat(extraData.paid_amount).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>手续费</td><td>' + (extraData.order_fee ? '¥' + parseFloat(extraData.order_fee).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>币种</td><td>' + (extraData.currency || '-') + '</td></tr>' +
                '<tr><td>支付方式</td><td>' + (extraData.pay_model || '-') + '</td></tr>' +
                '<tr><td>支付类型</td><td>' + (extraData.pay_type || '-') + '</td></tr>' +
                '<tr><td>下单时间</td><td>' + (extraData.order_time || '-') + '</td></tr>' +
                '<tr><td>完成时间</td><td>' + (extraData.finish_time || '-') + '</td></tr>' +
                '<tr><td>验签状态</td><td>' + (extraData.signature_valid ? '<span style="color: green;">PASS</span>' : '<span style="color: red;">FAIL</span>') + '</td></tr>' +
                '<tr><td>备注</td><td>' + (extraData.remark || '-') + '</td></tr>' +
                '<tr><td>失败原因</td><td>' + (extraData.msg || '-') + '</td></tr>' +
                '<tr><td>客户端IP</td><td>' + (data.user_ip || '-') + '</td></tr>' +
                '</table>';
        }
        
        function generateRequestTab(requestData) {
            return '<pre class="json-code">' + JSON.stringify(requestData, null, 2) + '</pre>';
        }
        
        function generateExtraTab(extraData) {
            return '<pre class="json-code">' + JSON.stringify(extraData, null, 2) + '</pre>';
        }
        
        function switchDetailTab(tabName) {
            $('.detail-tab-content').hide();
            $('.detail-tab-button').removeClass('active');
            $('#' + tabName + '-tab').show();
            $('[onclick="switchDetailTab(\'' + tabName + '\')"]').addClass('active');
        }
        </script>
        <?php
    }
}
?>