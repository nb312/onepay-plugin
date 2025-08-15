<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Payment Gateway Class
 * 
 * Extends WooCommerce Payment Gateway to integrate with OnePay API
 */
class WC_Gateway_OnePay extends WC_Payment_Gateway {
    
    const ID = 'onepay';
    
    /**
     * 测试模式标志
     * @var bool
     */
    public $testmode;
    
    /**
     * 调试模式标志
     * @var bool
     */
    public $debug;
    
    /**
     * 商户号
     * @var string
     */
    public $merchant_no;
    
    /**
     * 商户私钥
     * @var string
     */
    public $private_key;
    
    /**
     * 平台公钥
     * @var string
     */
    public $platform_public_key;
    
    /**
     * API接口地址
     * @var string
     */
    public $api_url;
    
    /**
     * 隐藏区块警告标志
     * @var bool
     */
    public $hide_blocks_warning;
    
    /**
     * 日志记录器
     * @var WC_Logger
     */
    public $logger;
    
    public function __construct() {
        $this->id                 = self::ID;
        $this->icon               = '';
        $this->has_fields         = false; // 改为false，因为这只是配置网关
        $this->method_title       = __('OnePay 配置', 'onepay');
        $this->method_description = __('OnePay支付网关的主配置。请在此处配置API密钥，然后分别启用各个支付方式。', 'onepay');
        
        $this->supports = array(
            'products',
            'refunds',
        );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title                = $this->get_option('title', 'OnePay');
        $this->description          = $this->get_option('description', 'Pay securely using OnePay payment system.');
        $this->enabled              = 'no'; // 主网关始终禁用，只用于配置
        $this->testmode             = 'yes' === $this->get_option('testmode', 'yes');
        $this->debug                = 'yes' === $this->get_option('debug', 'no');
        $this->merchant_no          = $this->get_option('merchant_no', '');
        $this->private_key          = $this->get_option('private_key', '');
        $this->platform_public_key  = $this->get_option('platform_public_key', '');
        $this->api_url              = $this->testmode ? $this->get_option('test_api_url', 'http://110.42.152.219:8083/nh-gateway/v2/card/payment') : $this->get_option('live_api_url', 'https://api.onepay.com/v2/card/payment');
        $this->hide_blocks_warning  = 'yes' === $this->get_option('hide_blocks_warning', 'no');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_api_onepay_callback', array($this, 'process_callback'));
        add_action('woocommerce_api_onepay_return', array($this, 'process_return'));
        
        // Don't automatically disable based on currency check alone
        // Let the admin control when to enable/disable
        // The is_available() method will handle runtime availability
    }
    
    public function is_valid_for_use() {
        $current_currency = get_woocommerce_currency();
        // Support all currencies
        $is_valid = true; // Remove currency restriction
        
        // Log currency check for debugging
        if ($this->debug) {
            $logger = wc_get_logger();
            $logger->info(
                sprintf('OnePay currency check: %s is %s', $current_currency, $is_valid ? 'supported' : 'not supported'),
                array('source' => 'onepay')
            );
        }
        
        return $is_valid;
    }
    
    /**
     * Check if the gateway is available for use
     * This method determines if the gateway should be shown at checkout
     * 
     * @return bool
     */
    public function is_available() {
        // 临时调试模式 - 如果URL包含 onepay_force=1，强制显示网关进行测试
        if (isset($_GET['onepay_force']) && $_GET['onepay_force'] == '1' && current_user_can('manage_woocommerce')) {
            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->info('OnePay forced available for testing', array('source' => 'onepay'));
            }
            return true;
        }
        
        // Start with basic enabled check
        if ('yes' !== $this->enabled) {
            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->info('OnePay not available: not enabled. Current enabled value: ' . $this->enabled, array('source' => 'onepay'));
            }
            return false;
        }
        
        // Debug logging if enabled
        if ($this->debug) {
            $logger = wc_get_logger();
            $logger->info('OnePay availability check - enabled: yes', array('source' => 'onepay'));
        }
        
        // Simplified currency check
        $current_currency = get_woocommerce_currency();
        // Currency check removed - now supports all currencies
        // Uncomment below to restrict currencies if needed
        /*
        $supported_currencies = array('RUB', 'USD', 'EUR', 'CNY', 'JPY', 'GBP');
        
        if (!in_array($current_currency, $supported_currencies)) {
            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->info("OnePay not available: currency '$current_currency' not supported. Supported: " . implode(', ', $supported_currencies), array('source' => 'onepay'));
            }
            return false;
        }
        */
        
        if ($this->debug) {
            $logger = wc_get_logger();
            $logger->info("OnePay currency check passed: $current_currency", array('source' => 'onepay'));
        }
        
        // 在管理员界面更宽松的检查
        if (is_admin()) {
            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->info('OnePay available in admin context', array('source' => 'onepay'));
            }
            return true; // 简化管理员检查
        }
        
        // 前端结账页面的检查
        if (empty($this->merchant_no)) {
            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->info('OnePay not available: merchant number not set', array('source' => 'onepay'));
            }
            return false;
        }
        
        if ($this->debug) {
            $logger = wc_get_logger();
            $logger->info('OnePay basic checks passed, calling parent::is_available()', array('source' => 'onepay'));
        }
        
        return true; // 简化最终检查，不调用 parent::is_available()
    }
    
    public function admin_options() {
        // Simplified check - just check if currency is supported
        $current_currency = get_woocommerce_currency();
        // Support all currencies in admin
        $currency_supported = true;
        
        if ($currency_supported) {
            echo '<h2>' . esc_html($this->get_method_title());
            wc_back_link(__('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            echo '</h2>';
            echo wpautop($this->get_method_description());
            
            $this->display_admin_status_section();
            
            parent::admin_options();
            
            // 回调日志已移至独立页面查看，避免配置界面刷新
            // $this->display_callback_logs_section();
            $this->display_callback_logs_notice();
            $this->display_admin_tools_section();
            
        } else {
            echo '<div class="inline error"><p><strong>' . __('Gateway disabled', 'onepay') . '</strong>: ' . __('OnePay does not support your store currency.', 'onepay') . '</p></div>';
        }
    }
    
    /**
     * Display admin status section
     */
    public function display_admin_status_section() {
        $callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
        $return_url = add_query_arg('wc-api', 'onepay_return', home_url('/'));
        ?>
        <div class="onepay-status-section">
            <h4><?php _e('OnePay Integration Status', 'onepay'); ?></h4>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Callback URL', 'onepay'); ?></th>
                    <td>
                        <div class="onepay-webhook-url"><?php echo esc_html($callback_url); ?></div>
                        <p class="description"><?php _e('Use this URL as the callback URL in your OnePay merchant configuration.', 'onepay'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Return URL', 'onepay'); ?></th>
                    <td>
                        <div class="onepay-webhook-url"><?php echo esc_html($return_url); ?></div>
                        <p class="description"><?php _e('Use this URL as the return URL for synchronous notifications.', 'onepay'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Configuration Status', 'onepay'); ?></th>
                    <td>
                        <span class="onepay-status-indicator <?php echo $this->is_configured() ? 'connected' : 'disconnected'; ?>"></span>
                        <?php echo $this->is_configured() ? __('Configured', 'onepay') : __('Not Configured', 'onepay'); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * 显示回调日志页面提示
     */
    public function display_callback_logs_notice() {
        if (!$this->debug) {
            return; // 只在调试模式下显示
        }
        
        $callback_logs_url = admin_url('admin.php?page=onepay-callback-logs');
        ?>
        <div class="onepay-callback-logs-notice">
            <h3><?php _e('异步回调记录', 'onepay'); ?></h3>
            <p class="description">
                <?php _e('回调记录已移至独立页面查看，避免配置界面频繁刷新。', 'onepay'); ?>
                <a href="<?php echo esc_url($callback_logs_url); ?>" class="button button-primary" target="_blank">
                    <?php _e('查看OnePay回调日志', 'onepay'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * 显示回调日志记录区域
     */
    public function display_callback_logs_section() {
        if (!$this->debug) {
            return; // 只在调试模式下显示
        }
        
        // 加载调试日志器
        require_once dirname(__FILE__) . '/class-onepay-debug-logger.php';
        $debug_logger = OnePay_Debug_Logger::get_instance();
        
        ?>
        <div class="onepay-callback-logs-section">
            <h3><?php _e('异步回调记录', 'onepay'); ?> 
                <button type="button" id="onepay_refresh_callbacks" class="button button-small">
                    <?php _e('刷新', 'onepay'); ?>
                </button>
            </h3>
            
            <div class="onepay-callback-tabs">
                <button class="tab-button active" onclick="switchCallbackTab('async')"><?php _e('异步回调', 'onepay'); ?></button>
                <button class="tab-button" onclick="switchCallbackTab('legacy')"><?php _e('历史记录', 'onepay'); ?></button>
            </div>
            
            <div id="onepay_callback_logs_container">
                <div id="async-callbacks" class="callback-tab-content active">
                    <?php $this->render_async_callback_logs($debug_logger); ?>
                </div>
                <div id="legacy-callbacks" class="callback-tab-content" style="display:none;">
                    <?php $this->render_legacy_callback_logs($debug_logger); ?>
                </div>
            </div>
            
            <p class="description">
                <?php _e('异步回调记录直接从OnePay接口获取，包含验签状态和完整的回调数据。', 'onepay'); ?>
            </p>
        </div>
        
        <style>
        .onepay-callback-tabs {
            margin: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .tab-button {
            background: none;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .tab-button.active {
            border-bottom-color: #0073aa;
            color: #0073aa;
            font-weight: bold;
        }
        .callback-tab-content {
            margin-top: 15px;
        }
        .signature-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .signature-status.pass {
            background: #28a745;
            color: white;
        }
        .signature-status.fail {
            background: #dc3545;
            color: white;
        }
        .processing-status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
        }
        .processing-status.success {
            background: #d4edda;
            color: #155724;
        }
        .processing-status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .processing-status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .processing-status.pending {
            background: #d1ecf1;
            color: #0c5460;
        }
        </style>
        
        <script>
        function switchCallbackTab(tabName) {
            // 隐藏所有内容
            document.querySelectorAll('.callback-tab-content').forEach(function(content) {
                content.style.display = 'none';
            });
            
            // 移除所有按钮的active类
            document.querySelectorAll('.tab-button').forEach(function(button) {
                button.classList.remove('active');
            });
            
            // 显示选中的内容
            document.getElementById(tabName + '-callbacks').style.display = 'block';
            
            // 激活选中的按钮
            event.target.classList.add('active');
        }
        </script>
        <?php
    }
    
    /**
     * 渲染异步回调日志
     */
    public function render_async_callback_logs($debug_logger) {
        // 获取异步回调记录
        $async_callbacks = $debug_logger->get_logs(array(
            'log_type' => 'async_callback',
            'limit' => 15,
            'order_by' => 'log_time',
            'order' => 'DESC'
        ));
        
        if (empty($async_callbacks)) {
            echo '<div class="onepay-no-callbacks">';
            echo '<p>' . __('暂无异步回调记录。启用调试模式后，所有异步回调将在此显示。', 'onepay') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<table class="widefat onepay-async-callback-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('时间', 'onepay') . '</th>';
        echo '<th>' . __('商户订单号', 'onepay') . '</th>';
        echo '<th>' . __('OnePay订单号', 'onepay') . '</th>';
        echo '<th>' . __('订单状态', 'onepay') . '</th>';
        echo '<th>' . __('支付金额', 'onepay') . '</th>';
        echo '<th>' . __('验签状态', 'onepay') . '</th>';
        echo '<th>' . __('处理状态', 'onepay') . '</th>';
        echo '<th>' . __('操作', 'onepay') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($async_callbacks as $callback) {
            // 解析extra_data获取详细信息
            $extra_data = !empty($callback->extra_data) ? json_decode($callback->extra_data, true) : array();
            
            $merchant_order_no = $extra_data['merchant_order_no'] ?? '';
            $onepay_order_no = $extra_data['onepay_order_no'] ?? ($callback->order_number ?: '');
            $order_status = $extra_data['order_status'] ?? '';
            $paid_amount = $extra_data['paid_amount'] ?? ($callback->amount ?: 0);
            $signature_valid = $extra_data['signature_valid'] ?? false;
            $signature_status = $extra_data['signature_status'] ?? 'UNKNOWN';
            $processing_status = $extra_data['processing_status'] ?? 'PENDING';
            
            // 时间格式化显示
            $display_time = $callback->log_time ? date('m-d H:i:s', strtotime($callback->log_time)) : '-';
            
            echo '<tr class="async-callback-row callback-' . esc_attr($callback->status) . '">';
            echo '<td>' . esc_html($display_time) . '</td>';
            echo '<td>' . esc_html($merchant_order_no ?: '-') . '</td>';
            echo '<td>' . esc_html($onepay_order_no ?: '-') . '</td>';
            echo '<td>';
            if ($order_status) {
                echo '<span class="order-status order-status-' . esc_attr(strtolower($order_status)) . '">' . esc_html($order_status) . '</span>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . ($paid_amount ? '¥' . number_format($paid_amount, 2) : '-') . '</td>';
            echo '<td><span class="signature-status ' . ($signature_valid ? 'pass' : 'fail') . '">' . esc_html($signature_status) . '</span></td>';
            echo '<td><span class="processing-status ' . esc_attr(strtolower($processing_status)) . '">' . esc_html($processing_status) . '</span></td>';
            echo '<td><button type="button" class="button button-small view-async-callback-detail" data-id="' . esc_attr($callback->id) . '">' . __('详情', 'onepay') . '</button></td>';
            echo '</tr>';
            
            // 详情行（默认隐藏）
            echo '<tr id="async-callback-detail-' . esc_attr($callback->id) . '" class="async-callback-detail-row" style="display:none;">';
            echo '<td colspan="8">';
            echo '<div class="async-callback-detail-content">';
            $this->render_async_callback_detail($callback, $extra_data);
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // 添加JavaScript处理详情显示
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.view-async-callback-detail').on('click', function() {
                var callbackId = $(this).data('id');
                var detailRow = $('#async-callback-detail-' + callbackId);
                
                if (detailRow.is(':visible')) {
                    detailRow.hide();
                    $(this).text('<?php echo __('详情', 'onepay'); ?>');
                } else {
                    detailRow.show();
                    $(this).text('<?php echo __('隐藏', 'onepay'); ?>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 渲染异步回调详情
     */
    public function render_async_callback_detail($callback, $extra_data) {
        ?>
        <div class="async-callback-detail">
            <h4><?php _e('异步回调详细信息', 'onepay'); ?></h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div>
                    <h5><?php _e('订单信息', 'onepay'); ?></h5>
                    <table class="widefat">
                        <tr><td><strong><?php _e('商户编号', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['merchant_no'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('商户订单号', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['merchant_order_no'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('OnePay订单号', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['onepay_order_no'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('订单状态', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['order_status'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('订单金额', 'onepay'); ?>:</strong></td><td><?php echo ($extra_data['order_amount'] ?? 0) ? '¥' . number_format($extra_data['order_amount'], 2) : '-'; ?></td></tr>
                        <tr><td><strong><?php _e('实际支付金额', 'onepay'); ?>:</strong></td><td><?php echo ($extra_data['paid_amount'] ?? 0) ? '¥' . number_format($extra_data['paid_amount'], 2) : '-'; ?></td></tr>
                        <tr><td><strong><?php _e('手续费', 'onepay'); ?>:</strong></td><td><?php echo ($extra_data['order_fee'] ?? 0) ? '¥' . number_format($extra_data['order_fee'], 2) : '-'; ?></td></tr>
                    </table>
                </div>
                
                <div>
                    <h5><?php _e('支付信息', 'onepay'); ?></h5>
                    <table class="widefat">
                        <tr><td><strong><?php _e('支付类型', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['pay_type'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('支付方式', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['pay_model'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('下单时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['order_time'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('完成时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['finish_time'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('备注', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['remark'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('客户端IP', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback->user_ip ?: '-'); ?></td></tr>
                    </table>
                </div>
                
                <div>
                    <h5><?php _e('处理信息', 'onepay'); ?></h5>
                    <table class="widefat">
                        <tr><td><strong><?php _e('验签状态', 'onepay'); ?>:</strong></td><td><span class="signature-status <?php echo ($extra_data['signature_valid'] ?? false) ? 'pass' : 'fail'; ?>"><?php echo esc_html($extra_data['signature_status'] ?? 'UNKNOWN'); ?></span></td></tr>
                        <tr><td><strong><?php _e('处理状态', 'onepay'); ?>:</strong></td><td><span class="processing-status <?php echo esc_attr(strtolower($extra_data['processing_status'] ?? 'pending')); ?>"><?php echo esc_html($extra_data['processing_status'] ?? 'PENDING'); ?></span></td></tr>
                        <tr><td><strong><?php _e('处理消息', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['processing_message'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('接收时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['received_at'] ?? '-'); ?></td></tr>
                        <tr><td><strong><?php _e('处理时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($extra_data['processed_at'] ?? '-'); ?></td></tr>
                        <?php if ($callback->order_id): ?>
                        <tr><td><strong><?php _e('关联订单', 'onepay'); ?>:</strong></td><td><a href="<?php echo admin_url('post.php?post=' . $callback->order_id . '&action=edit'); ?>" target="_blank">#<?php echo $callback->order_id; ?></a></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($callback->request_data)): ?>
            <h5><?php _e('原始回调数据', 'onepay'); ?></h5>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
<?php echo esc_html(json_encode(json_decode($callback->request_data, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * 渲染历史回调日志（向后兼容）
     */
    public function render_legacy_callback_logs($debug_logger) {
        // 获取历史回调记录
        $legacy_callbacks = $debug_logger->get_logs(array(
            'log_type' => 'callback',
            'limit' => 10,
            'order_by' => 'log_time',
            'order' => 'DESC'
        ));
        
        if (empty($legacy_callbacks)) {
            echo '<div class="onepay-no-callbacks">';
            echo '<p>' . __('暂无历史回调记录。', 'onepay') . '</p>';
            echo '</div>';
            return;
        }
        
        // 使用原有的回调日志渲染逻辑
        $this->render_callback_logs_table($legacy_callbacks);
    }
    
    /**
     * 渲染回调日志表格（重构后的通用方法）
     */
    public function render_callback_logs_table($callbacks) {
        echo '<table class="widefat onepay-callback-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('时间', 'onepay') . '</th>';
        echo '<th>' . __('商户订单号', 'onepay') . '</th>';
        echo '<th>' . __('OnePay订单号', 'onepay') . '</th>';
        echo '<th>' . __('订单状态', 'onepay') . '</th>';
        echo '<th>' . __('支付金额', 'onepay') . '</th>';
        echo '<th>' . __('手续费', 'onepay') . '</th>';
        echo '<th>' . __('支付方式', 'onepay') . '</th>';
        echo '<th>' . __('处理结果', 'onepay') . '</th>';
        echo '<th>' . __('操作', 'onepay') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($callbacks as $callback) {
            // 解析回调数据获取完整信息
            $callback_data = $this->parse_callback_data($callback);
            
            // 时间格式化显示
            $display_time = $callback->log_time ? date('m-d H:i:s', strtotime($callback->log_time)) : '-';
            
            echo '<tr class="callback-row callback-' . esc_attr($callback->status) . '">';
            echo '<td>' . esc_html($display_time) . '</td>';
            echo '<td>' . esc_html($callback_data['merchant_order_no'] ?: '-') . '</td>';
            echo '<td>' . esc_html($callback_data['onepay_order_no'] ?: '-') . '</td>';
            echo '<td>';
            if ($callback_data['order_status']) {
                echo '<span class="order-status order-status-' . esc_attr(strtolower($callback_data['order_status'])) . '">' . esc_html($callback_data['order_status']) . '</span>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>';
            if ($callback_data['paid_amount']) {
                echo '¥' . number_format($callback_data['paid_amount'], 2);
                if ($callback_data['order_amount'] && $callback_data['paid_amount'] != $callback_data['order_amount']) {
                    echo '<br><small>订单: ¥' . number_format($callback_data['order_amount'], 2) . '</small>';
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . ($callback_data['order_fee'] ? '¥' . number_format($callback_data['order_fee'], 2) : '-') . '</td>';
            echo '<td>' . esc_html($callback_data['pay_model'] ?: '-') . '</td>';
            echo '<td><span class="callback-result callback-result-' . esc_attr(strtolower($callback->status)) . '">' . esc_html($callback_data['callback_result']) . '</span></td>';
            echo '<td><button type="button" class="button button-small view-callback-detail" data-id="' . esc_attr($callback->id) . '">' . __('详情', 'onepay') . '</button></td>';
            echo '</tr>';
            
            // 详情行（默认隐藏）
            echo '<tr id="callback-detail-' . esc_attr($callback->id) . '" class="callback-detail-row" style="display:none;">';
            echo '<td colspan="9">';
            echo '<div class="callback-detail-content">';
            $this->render_callback_detail($callback, $callback_data);
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // 添加JavaScript处理详情显示
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.view-callback-detail').on('click', function() {
                var callbackId = $(this).data('id');
                var detailRow = $('#callback-detail-' + callbackId);
                
                if (detailRow.is(':visible')) {
                    detailRow.hide();
                    $(this).text('<?php echo __('详情', 'onepay'); ?>');
                } else {
                    detailRow.show();
                    $(this).text('<?php echo __('隐藏', 'onepay'); ?>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 渲染回调日志（向后兼容）
     */
    public function render_callback_logs($debug_logger) {
        // 直接调用历史记录渲染
        $this->render_legacy_callback_logs($debug_logger);
    }
    
    /**
     * 解析回调数据 - 兼容多种数据格式
     */
    private function parse_callback_data($callback) {
        $result = array(
            'merchant_order_no' => '',
            'onepay_order_no' => '',
            'order_status' => '',
            'order_amount' => 0,
            'paid_amount' => 0,
            'order_fee' => 0,
            'currency' => '',
            'pay_type' => '',
            'pay_model' => '',
            'order_time' => '',
            'finish_time' => '',
            'msg' => '',
            'callback_result' => 'unknown'
        );
        
        // 优先从request_data解析（最原始的数据）
        if (!empty($callback->request_data)) {
            $request_data = json_decode($callback->request_data, true);
            
            // 检查多种可能的数据格式
            $payment_data = null;
            
            // 格式1: 标准OnePay回调格式 {merchantNo, result, sign}
            if ($request_data && isset($request_data['result'])) {
                $callback_result = json_decode($request_data['result'], true);
                if ($callback_result && isset($callback_result['data'])) {
                    $payment_data = $callback_result['data'];
                }
            }
            // 格式2: 直接的data格式（可能的变体）
            elseif ($request_data && isset($request_data['data'])) {
                $payment_data = $request_data['data'];
            }
            // 格式3: 直接包含字段的格式
            elseif ($request_data && isset($request_data['orderNo'])) {
                $payment_data = $request_data;
            }
            
            // 解析支付数据
            if ($payment_data) {
                $result['merchant_order_no'] = $payment_data['merchantOrderNo'] ?? '';
                $result['onepay_order_no'] = $payment_data['orderNo'] ?? '';
                $result['order_status'] = $payment_data['orderStatus'] ?? '';
                $result['currency'] = $payment_data['currency'] ?? '';
                $result['pay_type'] = $payment_data['payType'] ?? '';
                $result['pay_model'] = $payment_data['payModel'] ?? '';
                $result['msg'] = $payment_data['msg'] ?? '';
                
                // 金额处理 - 检查是否需要从分转换为元
                if (isset($payment_data['orderAmount'])) {
                    $order_amount = floatval($payment_data['orderAmount']);
                    // 如果金额大于10000，认为是以分为单位，需要转换
                    $result['order_amount'] = ($order_amount > 10000) ? $order_amount / 100 : $order_amount;
                }
                
                if (isset($payment_data['paidAmount'])) {
                    $paid_amount = floatval($payment_data['paidAmount']);
                    $result['paid_amount'] = ($paid_amount > 10000) ? $paid_amount / 100 : $paid_amount;
                }
                
                if (isset($payment_data['orderFee'])) {
                    $order_fee = floatval($payment_data['orderFee']);
                    $result['order_fee'] = ($order_fee > 1000) ? $order_fee / 100 : $order_fee;
                }
                
                // 时间格式化 - 处理毫秒时间戳
                if (isset($payment_data['orderTime']) && $payment_data['orderTime'] > 0) {
                    $order_time = $payment_data['orderTime'];
                    // 如果是毫秒时间戳（13位数字）
                    if ($order_time > 1000000000000) {
                        $result['order_time'] = date('Y-m-d H:i:s', $order_time / 1000);
                    } else {
                        $result['order_time'] = date('Y-m-d H:i:s', $order_time);
                    }
                }
                
                if (isset($payment_data['finishTime']) && $payment_data['finishTime'] > 0) {
                    $finish_time = $payment_data['finishTime'];
                    if ($finish_time > 1000000000000) {
                        $result['finish_time'] = date('Y-m-d H:i:s', $finish_time / 1000);
                    } else {
                        $result['finish_time'] = date('Y-m-d H:i:s', $finish_time);
                    }
                }
            }
        }
        
        // 从extra_data补充（优先级较低，只在主数据缺失时使用）
        if (!empty($callback->extra_data)) {
            $extra_data = json_decode($callback->extra_data, true);
            if ($extra_data) {
                $result['order_status'] = $result['order_status'] ?: ($extra_data['order_status'] ?? '');
                $result['merchant_order_no'] = $result['merchant_order_no'] ?: ($extra_data['merchant_order_no'] ?? '');
                $result['pay_model'] = $result['pay_model'] ?: ($extra_data['pay_model'] ?? '');
                
                // 只在主数据没有金额时才使用extra_data的金额
                if (!$result['paid_amount'] && isset($extra_data['paid_amount'])) {
                    $result['paid_amount'] = floatval($extra_data['paid_amount']);
                }
                if (!$result['order_fee'] && isset($extra_data['order_fee'])) {
                    $result['order_fee'] = floatval($extra_data['order_fee']);
                }
                if (!$result['order_amount'] && isset($extra_data['original_order_amount'])) {
                    $result['order_amount'] = floatval($extra_data['original_order_amount']);
                }
                
                // 时间信息补充
                $result['order_time'] = $result['order_time'] ?: ($extra_data['order_time'] ?? '');
                $result['finish_time'] = $result['finish_time'] ?: ($extra_data['finish_time'] ?? '');
            }
        }
        
        // 从数据库基础字段补充
        $result['onepay_order_no'] = $result['onepay_order_no'] ?: ($callback->order_number ?: '');
        $result['currency'] = $result['currency'] ?: ($callback->currency ?: '');
        
        // 金额补充：优先使用解析的金额，其次使用数据库amount字段
        if (!$result['paid_amount'] && !empty($callback->amount)) {
            $result['paid_amount'] = floatval($callback->amount);
        }
        
        // 确定处理结果
        if ($callback->status === 'success') {
            $result['callback_result'] = 'SUCCESS';
        } elseif ($callback->status === 'error') {
            $result['callback_result'] = 'ERROR';
        } elseif ($callback->status === 'received') {
            $result['callback_result'] = '已接收';
        } elseif (!empty($callback->response_code)) {
            $result['callback_result'] = $callback->response_code;
        } elseif (!empty($result['order_status'])) {
            $result['callback_result'] = $result['order_status'];
        }
        
        return $result;
    }
    
    /**
     * 渲染回调详情
     */
    private function render_callback_detail($callback, $callback_data) {
        ?>
        <div class="callback-detail">
            <h4><?php _e('回调详细信息', 'onepay'); ?></h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h5><?php _e('订单信息', 'onepay'); ?></h5>
                    <table class="widefat">
                        <tr><td><strong><?php _e('商户订单号', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['merchant_order_no'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('OnePay订单号', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['onepay_order_no'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('订单状态', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['order_status'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('订单金额', 'onepay'); ?>:</strong></td><td><?php echo $callback_data['order_amount'] ? '¥' . number_format($callback_data['order_amount'], 2) : '-'; ?></td></tr>
                        <tr><td><strong><?php _e('实际支付金额', 'onepay'); ?>:</strong></td><td><?php echo $callback_data['paid_amount'] ? '¥' . number_format($callback_data['paid_amount'], 2) : '-'; ?></td></tr>
                        <tr><td><strong><?php _e('手续费', 'onepay'); ?>:</strong></td><td><?php echo $callback_data['order_fee'] ? '¥' . number_format($callback_data['order_fee'], 2) : '-'; ?></td></tr>
                        <tr><td><strong><?php _e('币种', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['currency'] ?: '-'); ?></td></tr>
                    </table>
                </div>
                
                <div>
                    <h5><?php _e('支付信息', 'onepay'); ?></h5>
                    <table class="widefat">
                        <tr><td><strong><?php _e('支付类型', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['pay_type'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('支付方式', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['pay_model'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('下单时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['order_time'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('完成时间', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['finish_time'] ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('客户端IP', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback->user_ip ?: '-'); ?></td></tr>
                        <tr><td><strong><?php _e('执行时间', 'onepay'); ?>:</strong></td><td><?php echo $callback->execution_time ? number_format($callback->execution_time * 1000, 1) . 'ms' : '-'; ?></td></tr>
                        <?php if ($callback_data['msg']): ?>
                        <tr><td><strong><?php _e('失败原因', 'onepay'); ?>:</strong></td><td><?php echo esc_html($callback_data['msg']); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($callback->request_data)): ?>
            <h5><?php _e('原始回调数据', 'onepay'); ?></h5>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
<?php echo esc_html(json_encode(json_decode($callback->request_data, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display admin tools section
     */
    public function display_admin_tools_section() {
        ?>
        <div class="onepay-admin-section">
            <h3><?php _e('OnePay Tools', 'onepay'); ?></h3>
            
            <div class="onepay-admin-buttons">
                <button type="button" id="onepay_test_connection" class="button button-secondary">
                    <?php _e('Test Connection', 'onepay'); ?>
                </button>
                <button type="button" id="onepay_validate_keys" class="button button-secondary">
                    <?php _e('Validate Keys', 'onepay'); ?>
                </button>
                <button type="button" id="onepay_run_tests" class="button button-primary">
                    <?php _e('Run Full Tests', 'onepay'); ?>
                </button>
            </div>
            
            <div id="onepay_tools_result" style="display: none;"></div>
            
            <?php if ($this->debug): ?>
            <div class="onepay-logs">
                <h4><?php _e('Recent Log Entries', 'onepay'); ?></h4>
                <?php $this->display_recent_logs(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Check if gateway is properly configured
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->merchant_no) && !empty($this->private_key) && !empty($this->api_url);
    }
    
    /**
     * Display recent log entries
     */
    public function display_recent_logs() {
        if (!$this->debug) {
            return;
        }
        
        // Get log files directly from WooCommerce log directory
        $log_files = $this->get_log_files();
        
        if (empty($log_files)) {
            echo '<p>' . __('No log entries found.', 'onepay') . '</p>';
            return;
        }
        
        echo '<div class="onepay-logs">';
        echo '<h5>' . __('Recent Log Entries (Last 10)', 'onepay') . '</h5>';
        
        // Get log directory path again for file reading
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        $entries_shown = 0;
        foreach ($log_files as $log_file) {
            if ($entries_shown >= 10) break;
            
            $file_path = $log_dir . $log_file;
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                $lines = array_filter(explode("\n", $content));
                $recent_lines = array_slice($lines, -5); // Last 5 lines from this file
                
                foreach (array_reverse($recent_lines) as $line) {
                    if ($entries_shown >= 10) break;
                    if (empty($line)) continue;
                    
                    $log_entry = $this->parse_log_line($line);
                    echo '<div class="onepay-log-entry ' . esc_attr(strtolower($log_entry['level'])) . '">';
                    echo '<strong>' . esc_html($log_entry['timestamp']) . '</strong> ';
                    echo '<span class="log-level">[' . esc_html($log_entry['level']) . ']</span> ';
                    echo esc_html($log_entry['message']);
                    echo '</div>';
                    
                    $entries_shown++;
                }
            }
        }
        
        if ($entries_shown === 0) {
            echo '<p>' . __('No OnePay log entries found. Enable debug mode and perform some actions to see logs here.', 'onepay') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get OnePay log files
     * 
     * @return array Log file names
     */
    private function get_log_files() {
        $log_files = array();
        
        // Get WooCommerce log directory path
        $log_dir = '';
        if (defined('WC_LOG_DIR')) {
            $log_dir = WC_LOG_DIR;
        } else {
            // Fallback to default WooCommerce logs path
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-logs/';
        }
        
        if (is_dir($log_dir)) {
            $files = scandir($log_dir);
            foreach ($files as $file) {
                if (strpos($file, 'onepay') !== false && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $log_files[] = $file;
                }
            }
        }
        
        return array_reverse($log_files); // Most recent first
    }
    
    /**
     * Parse log line
     * 
     * @param string $line Log line
     * @return array Parsed log entry
     */
    private function parse_log_line($line) {
        // WooCommerce log format: YYYY-MM-DDTHH:MM:SS+00:00 LEVEL message
        $pattern = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2})\s+(\w+)\s+(.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            return array(
                'timestamp' => date('Y-m-d H:i:s', strtotime($matches[1])),
                'level' => strtoupper($matches[2]),
                'message' => $matches[3]
            );
        }
        
        return array(
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'INFO',
            'message' => $line
        );
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'setup_guide' => array(
                'title'       => __('设置指南', 'onepay'),
                'type'        => 'title',
                'description' => __('<div style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0;">
                    <h3 style="margin-top: 0;">🚀 OnePay配置步骤：</h3>
                    <ol>
                        <li>在下方填写商户号和API密钥</li>
                        <li>保存设置后，前往<a href="admin.php?page=wc-settings&tab=checkout">支付设置</a></li>
                        <li>分别启用需要的支付方式：
                            <ul>
                                <li>OnePay FPS - 俄罗斯快速支付系统</li>
                                <li>OnePay 俄罗斯卡 - 俄罗斯银行卡</li>
                                <li>OnePay 国际卡 - 国际信用卡/借记卡</li>
                            </ul>
                        </li>
                    </ol>
                </div>', 'onepay'),
            ),
            'title' => array(
                'title'       => __('Title', 'onepay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'onepay'),
                'default'     => __('OnePay', 'onepay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'onepay'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'onepay'),
                'default'     => __('Pay securely using OnePay payment system.', 'onepay'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'   => __('Test mode', 'onepay'),
                'label'   => __('Enable Test Mode', 'onepay'),
                'type'    => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API credentials.', 'onepay'),
                'default' => 'yes',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'   => __('Debug Log', 'onepay'),
                'label'   => __('Enable logging', 'onepay'),
                'type'    => 'checkbox',
                'description' => __('Log OnePay events, such as API requests.', 'onepay'),
                'default' => 'no',
                'desc_tip'    => true,
            ),
            'merchant_no' => array(
                'title'       => __('Merchant Number', 'onepay'),
                'type'        => 'text',
                'description' => __('Your OnePay merchant number.', 'onepay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'private_key' => array(
                'title'       => __('Private RSA Key', 'onepay'),
                'type'        => 'textarea',
                'description' => __('Your RSA private key for signing requests. Keep this secure!', 'onepay'),
                'default'     => '',
                'desc_tip'    => true,
                'css'         => 'height: 150px; font-family: monospace;'
            ),
            'platform_public_key' => array(
                'title'       => __('Platform Public Key', 'onepay'),
                'type'        => 'textarea',
                'description' => __('OnePay platform public key for verifying responses.', 'onepay'),
                'default'     => '',
                'desc_tip'    => true,
                'css'         => 'height: 150px; font-family: monospace;'
            ),
            'live_api_url' => array(
                'title'       => __('Live API URL', 'onepay'),
                'type'        => 'text',
                'description' => __('OnePay live API endpoint URL.', 'onepay'),
                'default'     => 'https://api.onepay.com/v2/card/payment',
                'desc_tip'    => true,
            ),
            'test_api_url' => array(
                'title'       => __('测试API URL', 'onepay'),
                'type'        => 'text',
                'description' => __('测试环境的OnePay API端点。当前配置的测试服务器地址。', 'onepay'),
                'default'     => 'http://110.42.152.219:8083/nh-gateway/v2/card/payment',
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'style' => 'width: 400px;'
                )
            ),
            'hide_blocks_warning' => array(
                'title'   => __('隐藏区块结账警告', 'onepay'),
                'label'   => __('隐藏WooCommerce区块结账兼容性警告', 'onepay'),
                'type'    => 'checkbox',
                'description' => __('如果您不使用WooCommerce区块结账，可以隐藏相关的兼容性提示。', 'onepay'),
                'default' => 'no',
                'desc_tip'    => true,
            ),
            'ssl_note' => array(
                'title'       => __('SSL Information', 'onepay'),
                'type'        => 'title',
                'description' => __('<strong>Important:</strong> Always use HTTPS URLs for production. HTTP URLs are only acceptable in test mode for local development. OnePay requires secure connections for all live transactions.', 'onepay'),
            ),
        );
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        $payment_methods = array(
            'FPS' => array(
                'name' => __('FPS (Fast Payment System)', 'onepay'),
                'description' => __('俄罗斯快速支付系统 - 最小: 1 RUB, 最大: 账户限额', 'onepay')
            ),
            'CARDPAYMENT' => array(
                'name' => __('Card Payment', 'onepay'),
                'description' => __('使用您的卡支付 - 最小: 1 RUB, 最大: 卡限额', 'onepay')
            ),
            'INTERNATIONAL_CARD' => array(
                'name' => __('International Card Payment', 'onepay'),
                'description' => __('国际信用卡/借记卡支付 - 支持 VISA, MasterCard, AMEX, JCB, Discover', 'onepay')
            )
        );
        
        echo '<div class="onepay-payment-methods">';
        foreach ($payment_methods as $method_key => $method) {
            echo '<label class="onepay-payment-method" for="onepay_method_' . esc_attr($method_key) . '">';
            echo '<input type="radio" id="onepay_method_' . esc_attr($method_key) . '" name="onepay_payment_method" value="' . esc_attr($method_key) . '" ' . checked($method_key, 'FPS', false) . ' class="onepay-payment-radio" />';
            echo '<div class="method-info">';
            echo '<strong>' . esc_html($method['name']) . '</strong>';
            echo '<div class="method-description">' . esc_html($method['description']) . '</div>';
            echo '</div>';
            echo '</label>';
        }
        echo '</div>';
        
        // 国际卡支付表单
        echo '<div id="onepay_international_card_fields" style="display:none; margin-top: 20px;">';
        echo '<h4>' . __('信用卡信息', 'onepay') . '</h4>';
        
        // 卡号
        echo '<p class="form-row form-row-wide">';
        echo '<label for="onepay_card_number">' . __('卡号', 'onepay') . ' <span class="required">*</span></label>';
        echo '<input id="onepay_card_number" name="onepay_card_number" type="text" class="input-text" placeholder="1234 5678 9012 3456" maxlength="19" />';
        echo '<span id="onepay_card_type" class="card-type-indicator"></span>';
        echo '</p>';
        
        // 有效期
        echo '<p class="form-row form-row-first">';
        echo '<label for="onepay_card_expiry">' . __('有效期 (MM/YY)', 'onepay') . ' <span class="required">*</span></label>';
        echo '<input id="onepay_card_expiry" name="onepay_card_expiry" type="text" class="input-text" placeholder="MM/YY" maxlength="5" />';
        echo '</p>';
        
        // CVV
        echo '<p class="form-row form-row-last">';
        echo '<label for="onepay_card_cvv">' . __('CVV/CVC', 'onepay') . ' <span class="required">*</span></label>';
        echo '<input id="onepay_card_cvv" name="onepay_card_cvv" type="text" class="input-text" placeholder="123" maxlength="4" />';
        echo '</p>';
        
        echo '<div class="clear"></div>';
        echo '</div>';
    }
    
    public function validate_fields() {
        // 从传统表单或blocks数据获取支付方式
        $payment_method = $this->get_payment_method_from_request();
        
        if (empty($payment_method)) {
            wc_add_notice(__('请选择支付方式', 'onepay'), 'error');
            return false;
        }
        
        $allowed_methods = array('FPS', 'CARDPAYMENT', 'INTERNATIONAL_CARD');
        if (!in_array($payment_method, $allowed_methods)) {
            wc_add_notice(__('选择的支付方式无效', 'onepay'), 'error');
            return false;
        }
        
        // 如果是国际卡支付，验证卡片信息
        if ($payment_method === 'INTERNATIONAL_CARD') {
            return $this->validate_international_card_fields();
        }
        
        return true;
    }
    
    /**
     * 验证国际卡字段
     * 
     * @return bool
     */
    private function validate_international_card_fields() {
        // 卡号验证
        if (empty($_POST['onepay_card_number'])) {
            wc_add_notice(__('请输入卡号', 'onepay'), 'error');
            return false;
        }
        
        $card_number = str_replace(array(' ', '-'), '', $_POST['onepay_card_number']);
        
        // 加载国际卡处理类
        if (!class_exists('OnePay_International_Card')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-international-card.php';
        }
        
        if (!OnePay_International_Card::validate_card_number($card_number)) {
            wc_add_notice(__('卡号无效', 'onepay'), 'error');
            return false;
        }
        
        // 有效期验证
        if (empty($_POST['onepay_card_expiry'])) {
            wc_add_notice(__('请输入卡片有效期', 'onepay'), 'error');
            return false;
        }
        
        $expiry = $_POST['onepay_card_expiry'];
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $matches)) {
            wc_add_notice(__('有效期格式无效，请使用 MM/YY 格式', 'onepay'), 'error');
            return false;
        }
        
        $exp_month = $matches[1];
        $exp_year = '20' . $matches[2];
        $current_year = date('Y');
        $current_month = date('m');
        
        if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
            wc_add_notice(__('卡片已过期', 'onepay'), 'error');
            return false;
        }
        
        // CVV验证
        if (empty($_POST['onepay_card_cvv'])) {
            wc_add_notice(__('请输入CVV码', 'onepay'), 'error');
            return false;
        }
        
        $cvv = $_POST['onepay_card_cvv'];
        if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
            wc_add_notice(__('CVV码无效', 'onepay'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get payment method from request (works for both traditional and blocks checkout)
     * 
     * @return string
     */
    private function get_payment_method_from_request() {
        // Try traditional POST data first
        if (isset($_POST['onepay_payment_method']) && !empty($_POST['onepay_payment_method'])) {
            return sanitize_text_field($_POST['onepay_payment_method']);
        }
        
        // Try blocks checkout payment data
        if (isset($_POST['payment_data']) && is_array($_POST['payment_data'])) {
            foreach ($_POST['payment_data'] as $data) {
                if (isset($data['key']) && $data['key'] === 'onepay_payment_method' && !empty($data['value'])) {
                    return sanitize_text_field($data['value']);
                }
            }
        }
        
        // Check if it's in the main payment data
        if (isset($_POST['paymentMethodData']) && is_array($_POST['paymentMethodData'])) {
            $payment_data = $_POST['paymentMethodData'];
            if (isset($payment_data['onepay_payment_method']) && !empty($payment_data['onepay_payment_method'])) {
                return sanitize_text_field($payment_data['onepay_payment_method']);
            }
        }
        
        // Default to FPS if nothing is found
        return 'FPS';
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'fail',
                'messages' => __('订单未找到', 'onepay')
            );
        }
        
        $payment_method = $this->get_payment_method_from_request();
        
        $order->update_meta_data('_onepay_payment_method', $payment_method);
        $order->save();
        
        // 处理国际卡支付
        if ($payment_method === 'INTERNATIONAL_CARD') {
            return $this->process_international_card_payment($order);
        }
        
        // 处理其他支付方式
        $api_handler = new OnePay_API();
        $response = $api_handler->create_payment_request($order, $payment_method);
        
        if ($response['success']) {
            $order->update_status('pending', __('等待OnePay支付确认', 'onepay'));
            $order->update_meta_data('_onepay_order_no', $response['data']['orderNo']);
            $order->save();
            
            return array(
                'result'   => 'success',
                'redirect' => $response['data']['webUrl']
            );
        } else {
            wc_add_notice($response['message'], 'error');
            return array(
                'result' => 'fail'
            );
        }
    }
    
    /**
     * 处理国际卡支付
     * 
     * @param WC_Order $order 订单
     * @return array 支付结果
     */
    private function process_international_card_payment($order) {
        // 获取卡片数据
        $card_number = str_replace(array(' ', '-'), '', $_POST['onepay_card_number']);
        $expiry = $_POST['onepay_card_expiry'];
        $cvv = $_POST['onepay_card_cvv'];
        
        // 解析有效期
        preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $matches);
        $exp_month = $matches[1];
        $exp_year = '20' . $matches[2];
        
        // 加载国际卡处理类
        if (!class_exists('OnePay_International_Card')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-international-card.php';
        }
        
        // 检测卡类型
        $card_type = OnePay_International_Card::detect_card_type($card_number);
        if (!$card_type) {
            wc_add_notice(__('无法识别的卡类型', 'onepay'), 'error');
            return array('result' => 'fail');
        }
        
        // 准备卡片数据
        $card_data = array(
            'card_number' => $card_number,
            'card_type' => $card_type,
            'card_cvv' => $cvv,
            'card_exp_month' => $exp_month,
            'card_exp_year' => $exp_year
        );
        
        // 创建国际卡支付请求
        $international_card_handler = new OnePay_International_Card();
        $response = $international_card_handler->create_international_card_payment($order, $card_data);
        
        if ($response['success']) {
            $order->update_status('pending', __('等待OnePay国际卡支付确认', 'onepay'));
            
            // 如果有3DS验证URL，重定向到3DS页面
            if (!empty($response['data']['webUrl'])) {
                return array(
                    'result'   => 'success',
                    'redirect' => $response['data']['webUrl']
                );
            }
            
            // 如果没有3DS，直接跳转到成功页面
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            wc_add_notice($response['message'], 'error');
            return array(
                'result' => 'fail'
            );
        }
    }
    
    public function process_callback() {
        $callback_handler = new OnePay_Callback();
        $callback_handler->process_callback();
    }
    
    public function process_return() {
        if (isset($_GET['orderNo']) && isset($_GET['orderStatus'])) {
            $order_no = sanitize_text_field($_GET['orderNo']);
            $status = sanitize_text_field($_GET['orderStatus']);
            
            $orders = wc_get_orders(array(
                'meta_key' => '_onepay_order_no',
                'meta_value' => $order_no,
                'limit' => 1
            ));
            
            if (!empty($orders)) {
                $order = $orders[0];
                if ($status === 'SUCCESS') {
                    wp_redirect($this->get_return_url($order));
                } else {
                    wc_add_notice(__('Payment failed or was cancelled.', 'onepay'), 'error');
                    wp_redirect(wc_get_checkout_url());
                }
            } else {
                wp_redirect(wc_get_checkout_url());
            }
            exit;
        }
        
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
        
        if ($this->enabled === 'no') {
            return;
        }
        
        wp_enqueue_script('onepay-checkout', ONEPAY_PLUGIN_URL . 'assets/js/onepay-frontend.js', array('jquery'), ONEPAY_VERSION, true);
    }
    
    public function process_refund($order_id, $amount = null, $reason = '') {
        return new WP_Error('onepay_refund_error', __('Refund functionality not yet implemented.', 'onepay'));
    }
    
    public function log($message, $level = 'info') {
        if ($this->debug) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->log($level, $message, array('source' => 'onepay'));
        }
    }
}