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
                        <th scope="col"><?php _e('WordPress订单', 'onepay'); ?></th>
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
                            <td colspan="12" class="no-items"><?php _e('暂无回调记录', 'onepay'); ?></td>
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
        
        // 提取WordPress订单号和创建链接
        $wp_order_info = $this->extract_wordpress_order_info($merchant_order_no, $log->order_id);
        
        // 获取验签状态 - 增强调试信息
        $signature_status = '';
        if ($log->log_type === 'async_callback') {
            $signature_valid = $extra_data['signature_valid'] ?? null;
            $signature_status_text = $extra_data['signature_status'] ?? '';
            
            // 调试：记录原始数据
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('OnePay Log Row Debug - Signature data: ' . json_encode([
                    'log_id' => $log->id,
                    'signature_valid' => $signature_valid,
                    'signature_status_text' => $signature_status_text,
                    'log_status' => $log->status
                ]));
            }
            
            // 优先使用 signature_status 文本，fallback 到 signature_valid 布尔值
            if ($signature_status_text === 'PASS' || $signature_valid === true || $signature_valid === 'true' || $signature_valid === 1) {
                $signature_status = '<span class="signature-pass">PASS</span>';
            } elseif ($signature_status_text === 'FAIL' || $signature_valid === false || $signature_valid === 'false' || $signature_valid === 0) {
                $signature_status = '<span class="signature-fail">FAIL</span>';
            } elseif ($signature_status_text === 'PENDING' || $signature_valid === 'pending' || $log->status === 'pending_verification') {
                $signature_status = '<span class="signature-pending">验签中</span>';
            } elseif ($log->status === 'signature_failed') {
                $signature_status = '<span class="signature-fail">FAIL</span>';
            } elseif ($log->status === 'success' || $log->status === 'received') {
                $signature_status = '<span class="signature-pass">PASS</span>';
            } else {
                $signature_status = '<span class="signature-unknown">未知</span>';
            }
        } else {
            $signature_status = '<span class="signature-unknown">-</span>';
        }
        
        // 格式化时间 - 转换为北京时间
        $display_time = '-';
        if ($log->log_time) {
            // 将UTC时间转换为北京时间 (UTC+8)
            $beijing_timestamp = strtotime($log->log_time) + (8 * 3600);
            $display_time = date('m-d H:i:s', $beijing_timestamp);
        }
        
        ?>
        <tr class="log-row log-<?php echo esc_attr($log->status); ?>">
            <td><?php echo esc_html($display_time); ?></td>
            <td>
                <span class="log-type log-type-<?php echo esc_attr($log->log_type); ?>">
                    <?php echo esc_html($this->get_log_type_label($log->log_type)); ?>
                </span>
            </td>
            <td><?php echo $wp_order_info['display']; ?></td>
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
            <td><?php 
                if ($currency) {
                    echo esc_html($this->get_currency_display_name($currency));
                } else {
                    echo '-';
                }
            ?></td>
            <td>
                <button type="button" class="button button-small view-detail-btn" data-log-id="<?php echo esc_attr($log->id); ?>">
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
     * 获取币种显示名称
     * 支持ISO 4217字母代码和数字代码
     */
    private function get_currency_display_name($currency_code) {
        // ISO 4217 数字代码到字母代码的映射表
        $numeric_codes = array(
            '008' => 'ALL',  // Albanian Lek
            '012' => 'DZD',  // Algerian Dinar
            '032' => 'ARS',  // Argentine Peso
            '036' => 'AUD',  // Australian Dollar
            '044' => 'BSD',  // Bahamian Dollar
            '048' => 'BHD',  // Bahraini Dinar
            '050' => 'BDT',  // Bangladeshi Taka
            '051' => 'AMD',  // Armenian Dram
            '052' => 'BBD',  // Barbadian Dollar
            '060' => 'BMD',  // Bermudian Dollar
            '064' => 'BTN',  // Bhutanese Ngultrum
            '068' => 'BOB',  // Bolivian Boliviano
            '072' => 'BWP',  // Botswana Pula
            '084' => 'BZD',  // Belize Dollar
            '090' => 'SBD',  // Solomon Islands Dollar
            '096' => 'BND',  // Brunei Dollar
            '104' => 'MMK',  // Myanmar Kyat
            '108' => 'BIF',  // Burundian Franc
            '116' => 'KHR',  // Cambodian Riel
            '124' => 'CAD',  // Canadian Dollar
            '132' => 'CVE',  // Cape Verdean Escudo
            '136' => 'KYD',  // Cayman Islands Dollar
            '144' => 'LKR',  // Sri Lankan Rupee
            '152' => 'CLP',  // Chilean Peso
            '156' => 'CNY',  // Chinese Yuan
            '170' => 'COP',  // Colombian Peso
            '174' => 'KMF',  // Comorian Franc
            '188' => 'CRC',  // Costa Rican Colon
            '191' => 'HRK',  // Croatian Kuna
            '192' => 'CUP',  // Cuban Peso
            '203' => 'CZK',  // Czech Koruna
            '208' => 'DKK',  // Danish Krone
            '214' => 'DOP',  // Dominican Peso
            '222' => 'SVC',  // Salvadoran Colon
            '230' => 'ETB',  // Ethiopian Birr
            '232' => 'ERN',  // Eritrean Nakfa
            '238' => 'FKP',  // Falkland Islands Pound
            '242' => 'FJD',  // Fijian Dollar
            '262' => 'DJF',  // Djiboutian Franc
            '270' => 'GMD',  // Gambian Dalasi
            '292' => 'GIP',  // Gibraltar Pound
            '320' => 'GTQ',  // Guatemalan Quetzal
            '324' => 'GNF',  // Guinean Franc
            '328' => 'GYD',  // Guyanese Dollar
            '332' => 'HTG',  // Haitian Gourde
            '340' => 'HNL',  // Honduran Lempira
            '344' => 'HKD',  // Hong Kong Dollar
            '348' => 'HUF',  // Hungarian Forint
            '352' => 'ISK',  // Icelandic Krona
            '356' => 'INR',  // Indian Rupee
            '360' => 'IDR',  // Indonesian Rupiah
            '364' => 'IRR',  // Iranian Rial
            '368' => 'IQD',  // Iraqi Dinar
            '376' => 'ILS',  // Israeli New Shekel
            '388' => 'JMD',  // Jamaican Dollar
            '392' => 'JPY',  // Japanese Yen
            '398' => 'KZT',  // Kazakhstani Tenge
            '400' => 'JOD',  // Jordanian Dinar
            '404' => 'KES',  // Kenyan Shilling
            '408' => 'KPW',  // North Korean Won
            '410' => 'KRW',  // South Korean Won
            '414' => 'KWD',  // Kuwaiti Dinar
            '417' => 'KGS',  // Kyrgyzstani Som
            '418' => 'LAK',  // Lao Kip
            '422' => 'LBP',  // Lebanese Pound
            '426' => 'LSL',  // Lesotho Loti
            '430' => 'LRD',  // Liberian Dollar
            '434' => 'LYD',  // Libyan Dinar
            '446' => 'MOP',  // Macanese Pataca
            '454' => 'MWK',  // Malawian Kwacha
            '458' => 'MYR',  // Malaysian Ringgit
            '462' => 'MVR',  // Maldivian Rufiyaa
            '478' => 'MRU',  // Mauritanian Ouguiya
            '480' => 'MUR',  // Mauritian Rupee
            '484' => 'MXN',  // Mexican Peso
            '496' => 'MNT',  // Mongolian Tugrik
            '498' => 'MDL',  // Moldovan Leu
            '504' => 'MAD',  // Moroccan Dirham
            '512' => 'OMR',  // Omani Rial
            '516' => 'NAD',  // Namibian Dollar
            '524' => 'NPR',  // Nepalese Rupee
            '532' => 'ANG',  // Netherlands Antillean Guilder
            '533' => 'AWG',  // Aruban Florin
            '548' => 'VUV',  // Vanuatu Vatu
            '554' => 'NZD',  // New Zealand Dollar
            '558' => 'NIO',  // Nicaraguan Cordoba
            '566' => 'NGN',  // Nigerian Naira
            '578' => 'NOK',  // Norwegian Krone
            '586' => 'PKR',  // Pakistani Rupee
            '590' => 'PAB',  // Panamanian Balboa
            '598' => 'PGK',  // Papua New Guinean Kina
            '600' => 'PYG',  // Paraguayan Guarani
            '604' => 'PEN',  // Peruvian Sol
            '608' => 'PHP',  // Philippine Peso
            '634' => 'QAR',  // Qatari Riyal
            '643' => 'RUB',  // Russian Ruble
            '646' => 'RWF',  // Rwandan Franc
            '654' => 'SHP',  // Saint Helena Pound
            '682' => 'SAR',  // Saudi Riyal
            '690' => 'SCR',  // Seychellois Rupee
            '694' => 'SLE',  // Sierra Leonean Leone
            '702' => 'SGD',  // Singapore Dollar
            '704' => 'VND',  // Vietnamese Dong
            '706' => 'SOS',  // Somali Shilling
            '710' => 'ZAR',  // South African Rand
            '728' => 'SSP',  // South Sudanese Pound
            '748' => 'SZL',  // Swazi Lilangeni
            '752' => 'SEK',  // Swedish Krona
            '756' => 'CHF',  // Swiss Franc
            '760' => 'SYP',  // Syrian Pound
            '764' => 'THB',  // Thai Baht
            '776' => 'TOP',  // Tongan Pa'anga
            '780' => 'TTD',  // Trinidad and Tobago Dollar
            '784' => 'AED',  // UAE Dirham
            '788' => 'TND',  // Tunisian Dinar
            '800' => 'UGX',  // Ugandan Shilling
            '807' => 'MKD',  // Macedonian Denar
            '818' => 'EGP',  // Egyptian Pound
            '826' => 'GBP',  // British Pound
            '834' => 'TZS',  // Tanzanian Shilling
            '840' => 'USD',  // US Dollar
            '858' => 'UYU',  // Uruguayan Peso
            '860' => 'UZS',  // Uzbekistani Som
            '882' => 'WST',  // Samoan Tala
            '886' => 'YER',  // Yemeni Rial
            '901' => 'TWD',  // New Taiwan Dollar
            '925' => 'ZWL',  // Zimbabwean Dollar
            '928' => 'VES',  // Venezuelan Bolívar
            '929' => 'MRU',  // Mauritanian Ouguiya
            '930' => 'STN',  // São Tomé and Príncipe Dobra
            '932' => 'ZWL',  // Zimbabwean Dollar
            '933' => 'BYN',  // Belarusian Ruble
            '934' => 'TMT',  // Turkmenistani Manat
            '936' => 'GHS',  // Ghanaian Cedi
            '937' => 'VES',  // Venezuelan Bolívar
            '938' => 'SDG',  // Sudanese Pound
            '940' => 'UYI',  // Uruguay Peso en Unidades Indexadas
            '941' => 'RSD',  // Serbian Dinar
            '943' => 'MZN',  // Mozambican Metical
            '944' => 'AZN',  // Azerbaijani Manat
            '946' => 'RON',  // Romanian Leu
            '947' => 'CHE',  // WIR Euro
            '948' => 'CHW',  // WIR Franc
            '949' => 'TRY',  // Turkish Lira
            '950' => 'XAF',  // Central African CFA Franc
            '951' => 'XCD',  // East Caribbean Dollar
            '952' => 'XOF',  // West African CFA Franc
            '953' => 'XPF',  // CFP Franc
            '955' => 'XBA',  // European Composite Unit
            '956' => 'XBB',  // European Monetary Unit
            '957' => 'XBC',  // European Unit of Account 9
            '958' => 'XBD',  // European Unit of Account 17
            '959' => 'XAU',  // Gold
            '960' => 'XDR',  // Special Drawing Rights
            '961' => 'XAG',  // Silver
            '962' => 'XPT',  // Platinum
            '963' => 'XTS',  // Testing Currency Code
            '964' => 'XPD',  // Palladium
            '965' => 'XUA',  // ADB Unit of Account
            '967' => 'ZMW',  // Zambian Kwacha
            '968' => 'SRD',  // Surinamese Dollar
            '969' => 'MGA',  // Malagasy Ariary
            '970' => 'COU',  // Unidad de Valor Real
            '971' => 'AFN',  // Afghan Afghani
            '972' => 'TJS',  // Tajikistani Somoni
            '973' => 'AOA',  // Angolan Kwanza
            '975' => 'BGN',  // Bulgarian Lev
            '976' => 'CDF',  // Congolese Franc
            '977' => 'BAM',  // Bosnia-Herzegovina Convertible Mark
            '978' => 'EUR',  // Euro
            '979' => 'MXV',  // Mexican Unidad de Inversion
            '980' => 'UAH',  // Ukrainian Hryvnia
            '981' => 'GEL',  // Georgian Lari
            '984' => 'BOV',  // Bolivian Mvdol
            '985' => 'PLN',  // Polish Zloty
            '986' => 'BRL',  // Brazilian Real
            '990' => 'CLF',  // Unidad de Fomento
            '994' => 'XSU',  // Sucre
            '997' => 'USN',  // US Dollar (Next day)
            '998' => 'USS'   // US Dollar (Same day)
        );
        
        // 如果输入的是数字代码，先转换为字母代码
        $original_currency_code = $currency_code;
        if (is_numeric($currency_code)) {
            // 补零到3位数字
            $padded_code = str_pad($currency_code, 3, '0', STR_PAD_LEFT);
            $currency_code = $numeric_codes[$padded_code] ?? $currency_code;
        }
        
        // 货币代码到显示名称的映射表
        $currencies = array(
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'CNY' => 'Chinese Yuan (¥)',
            'RUB' => 'Russian Ruble (₽)',
            'BRL' => 'Brazilian Real (R$)',
            'INR' => 'Indian Rupee (₹)',
            'JPY' => 'Japanese Yen (¥)',
            'GBP' => 'British Pound (£)',
            'AUD' => 'Australian Dollar (A$)',
            'CAD' => 'Canadian Dollar (C$)',
            'CHF' => 'Swiss Franc (CHF)',
            'SEK' => 'Swedish Krona (kr)',
            'NOK' => 'Norwegian Krone (kr)',
            'DKK' => 'Danish Krone (kr)',
            'PLN' => 'Polish Zloty (zł)',
            'CZK' => 'Czech Koruna (Kč)',
            'HUF' => 'Hungarian Forint (Ft)',
            'RON' => 'Romanian Leu (lei)',
            'BGN' => 'Bulgarian Lev (лв)',
            'HRK' => 'Croatian Kuna (kn)',
            'TRY' => 'Turkish Lira (₺)',
            'THB' => 'Thai Baht (฿)',
            'MYR' => 'Malaysian Ringgit (RM)',
            'SGD' => 'Singapore Dollar (S$)',
            'HKD' => 'Hong Kong Dollar (HK$)',
            'KRW' => 'South Korean Won (₩)',
            'ZAR' => 'South African Rand (R)',
            'MXN' => 'Mexican Peso ($)',
            'ARS' => 'Argentine Peso ($)',
            'CLP' => 'Chilean Peso ($)',
            'COP' => 'Colombian Peso ($)',
            'PEN' => 'Peruvian Sol (S/)',
            'VND' => 'Vietnamese Dong (₫)',
            'PHP' => 'Philippine Peso (₱)',
            'IDR' => 'Indonesian Rupiah (Rp)',
            'ILS' => 'Israeli New Shekel (₪)',
            'NZD' => 'New Zealand Dollar (NZ$)'
        );
        
        // 如果找到了对应的货币名称，返回名称；否则返回代码（可能是转换后的字母代码）
        $result = $currencies[$currency_code] ?? $currency_code;
        
        // 如果原始输入是数字代码但没有找到对应的字母代码，显示原始数字代码
        if ($result === $currency_code && is_numeric($original_currency_code)) {
            return "Currency Code: {$original_currency_code}";
        }
        
        return $result;
    }
    
    /**
     * 提取WordPress订单信息
     */
    private function extract_wordpress_order_info($merchant_order_no, $order_id) {
        $result = array(
            'order_id' => null,
            'order_number' => '',
            'display' => '-'
        );
        
        // 首先尝试使用数据库中的order_id
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $result['order_id'] = $order_id;
                $result['order_number'] = $order->get_order_number();
                $edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                $result['display'] = '<a href="' . esc_url($edit_url) . '" target="_blank">#' . esc_html($result['order_number']) . '</a>';
                return $result;
            }
        }
        
        // 如果没有order_id或找不到订单，尝试从商户订单号中解析
        if ($merchant_order_no && strpos($merchant_order_no, '_') !== false) {
            $parts = explode('_', $merchant_order_no);
            $potential_order_number = $parts[0];
            
            // 如果是纯数字，尝试作为订单ID查找
            if (is_numeric($potential_order_number)) {
                $order = wc_get_order($potential_order_number);
                if ($order && $order->get_payment_method() && strpos($order->get_payment_method(), 'onepay') !== false) {
                    $result['order_id'] = $potential_order_number;
                    $result['order_number'] = $order->get_order_number();
                    $edit_url = admin_url('post.php?post=' . $potential_order_number . '&action=edit');
                    $result['display'] = '<a href="' . esc_url($edit_url) . '" target="_blank">#' . esc_html($result['order_number']) . '</a>';
                    return $result;
                }
            }
            
            // 尝试通过订单号查找
            $orders = wc_get_orders(array(
                'orderby' => 'id',
                'order' => 'DESC',
                'limit' => 20,
                'meta_query' => array(
                    array(
                        'key' => '_payment_method',
                        'value' => array('onepay', 'onepay_fps', 'onepay_russian_card', 'onepay_cards'),
                        'compare' => 'IN'
                    )
                )
            ));
            
            foreach ($orders as $order) {
                if ($order->get_order_number() === $potential_order_number) {
                    $result['order_id'] = $order->get_id();
                    $result['order_number'] = $order->get_order_number();
                    $edit_url = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
                    $result['display'] = '<a href="' . esc_url($edit_url) . '" target="_blank">#' . esc_html($result['order_number']) . '</a>';
                    return $result;
                }
            }
            
            // 如果找不到，仍然显示解析出的订单号（无链接）
            $result['order_number'] = $potential_order_number;
            $result['display'] = '#' . esc_html($potential_order_number) . ' <span style="color: #666;">(未找到)</span>';
        }
        
        return $result;
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
        // 加载外部CSS文件
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_style(
            'onepay-callback-logs',
            $plugin_url . 'assets/css/onepay-callback-logs.css',
            array(),
            '1.0.0'
        );
        
        // 添加一些内联样式作为补充
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
        .signature-pending { color: #ffc107; font-weight: bold; }
        .signature-unknown { color: #6c757d; }
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
            border: 1px solid #ddd;
        }
        
        /* 加载动画样式 */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 状态徽章样式 */
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending_verification {
            background: #fff3cd;
            color: #856404;
        }
        </style>
        <?php
    }
    
    /**
     * 添加页面脚本
     */
    private function add_page_scripts() {
        // 加载外部JS文件
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'onepay-callback-logs',
            $plugin_url . 'assets/js/onepay-callback-logs.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // 传递数据给JS
        wp_localize_script('onepay-callback-logs', 'onepayCallbackLogs', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('onepay_callback_detail')
        ));
        
        // 添加AJAX处理钩子
        add_action('wp_ajax_onepay_get_callback_detail', array($this, 'ajax_get_callback_detail'));
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 刷新按钮
            $('#refresh-logs').on('click', function() {
                location.reload();
            });
            
            // 查看详情按钮 - 使用事件委托确保按钮点击能正确响应  
            // 注意：外部JS文件已处理此功能，这里保留作为后备
            $(document).on('click', '.view-detail-btn', function(e) {
                e.preventDefault();
                var logId = $(this).data('log-id');
                console.log('点击查看详情按钮，Log ID:', logId);
                
                if (!logId) {
                    alert('无法获取日志ID');
                    return;
                }
                
                showLogDetail(logId);
            });
            
            // 关闭弹窗 - 分别处理关闭按钮和遮罩层点击
            $(document).on('click', '.onepay-modal-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('点击关闭按钮');
                $('#onepay-detail-modal').remove();
            });
            
            $(document).on('click', '.onepay-modal-overlay', function(e) {
                if (e.target === this) {
                    console.log('点击遮罩层关闭弹窗');
                    $('#onepay-detail-modal').remove();
                }
            });
            
            // 阻止弹窗内容区域的点击事件冒泡
            $(document).on('click', '.onepay-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // 添加ESC键关闭弹窗功能
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('#onepay-detail-modal').length > 0) {
                    $('#onepay-detail-modal').remove();
                }
            });
        });
        
        function showLogDetail(logId) {
            console.log('查看详情 - Log ID:', logId);
            
            // 移除现有弹窗
            $('#onepay-detail-modal').remove();
            
            // 显示加载状态
            var loadingModal = '<div id="onepay-detail-modal" class="onepay-modal-overlay">' +
                '<div class="onepay-modal-content">' +
                '<div class="onepay-modal-header">' +
                '<h3>加载中...</h3>' +
                '<span class="onepay-modal-close" title="关闭">&times;</span>' +
                '</div>' +
                '<div class="onepay-modal-body">' +
                '<p>正在获取详细信息，请稍候...</p>' +
                '<div style="text-align: center; margin: 20px 0;">' +
                '<div style="border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto;"></div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(loadingModal);
            
            console.log('发送AJAX请求到:', '<?php echo admin_url('admin-ajax.php'); ?>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'onepay_get_log_detail',
                    log_id: logId,
                    _ajax_nonce: '<?php echo wp_create_nonce('onepay_callback_logs'); ?>'
                },
                timeout: 15000, // 15秒超时
                dataType: 'json',
                beforeSend: function() {
                    console.log('AJAX请求开始发送...');
                },
                success: function(response) {
                    console.log('AJAX响应成功:', response);
                    $('#onepay-detail-modal').remove();
                    
                    if (response && response.success) {
                        displayLogDetail(response.data);
                    } else {
                        var errorMsg = '获取详情失败';
                        if (response && response.data) {
                            errorMsg += ': ' + response.data;
                        }
                        showErrorModal(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX错误详情:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $('#onepay-detail-modal').remove();
                    
                    var errorMsg = '请求失败，请稍后重试';
                    if (status === 'timeout') {
                        errorMsg = '请求超时，请稍后重试';
                    } else if (xhr.status === 403) {
                        errorMsg = '权限不足，请刷新页面后重试';
                    } else if (xhr.status === 404) {
                        errorMsg = '请求的资源不存在';
                    } else if (error) {
                        errorMsg += '。错误信息: ' + error;
                    }
                    
                    showErrorModal(errorMsg);
                }
            });
        }
        
        // 显示错误弹窗
        function showErrorModal(message) {
            var errorModal = '<div id="onepay-detail-modal" class="onepay-modal-overlay">' +
                '<div class="onepay-modal-content">' +
                '<div class="onepay-modal-header">' +
                '<h3 style="color: #dc3545;">❌ 错误</h3>' +
                '<span class="onepay-modal-close" title="关闭">&times;</span>' +
                '</div>' +
                '<div class="onepay-modal-body">' +
                '<p>' + message + '</p>' +
                '<p><strong>故障排除建议：</strong></p>' +
                '<ul>' +
                '<li>刷新页面后重试</li>' +
                '<li>检查网络连接</li>' +
                '<li>联系系统管理员</li>' +
                '</ul>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(errorModal);
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
                '<button class="detail-tab-button" onclick="switchDetailTab(\'steps\')">处理步骤</button>' +
                '<button class="detail-tab-button" onclick="switchDetailTab(\'request\')">原始数据</button>' +
                '<button class="detail-tab-button" onclick="switchDetailTab(\'extra\')">解析数据</button>' +
                '</div>' +
                '<div id="summary-tab" class="detail-tab-content active">' + generateSummaryTab(data, extraData) + '</div>' +
                '<div id="steps-tab" class="detail-tab-content" style="display:none;">' + generateProcessingStepsTab(extraData) + '</div>' +
                '<div id="request-tab" class="detail-tab-content" style="display:none;">' + generateRequestTab(requestData) + '</div>' +
                '<div id="extra-tab" class="detail-tab-content" style="display:none;">' + generateExtraTab(extraData) + '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
        }
        
        function generateSummaryTab(data, extraData) {
            // 提取WordPress订单号
            var wpOrderDisplay = extractWordPressOrderInfo(extraData.merchant_order_no, data.order_id);
            
            // 格式化接收时间为北京时间
            var beijingTime = data.log_time ? formatToBejingTime(data.log_time) : '-';
            
            // 格式化币种显示
            var currencyDisplay = formatCurrencyDisplay(extraData.currency);
            
            return '<table class="detail-table">' +
                '<tr><td>接收时间</td><td>' + beijingTime + '</td></tr>' +
                '<tr><td>WordPress订单</td><td>' + wpOrderDisplay + '</td></tr>' +
                '<tr><td>商户订单号</td><td>' + (extraData.merchant_order_no || '-') + '</td></tr>' +
                '<tr><td>OnePay订单号</td><td>' + (extraData.onepay_order_no || '-') + '</td></tr>' +
                '<tr><td>订单状态</td><td>' + (extraData.order_status || '-') + '</td></tr>' +
                '<tr><td>订单金额</td><td>' + (extraData.order_amount ? '¥' + parseFloat(extraData.order_amount).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>实付金额</td><td>' + (extraData.paid_amount ? '¥' + parseFloat(extraData.paid_amount).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>手续费</td><td>' + (extraData.order_fee ? '¥' + parseFloat(extraData.order_fee).toFixed(2) : '-') + '</td></tr>' +
                '<tr><td>币种</td><td>' + currencyDisplay + '</td></tr>' +
                '<tr><td>支付方式</td><td>' + (extraData.pay_model || '-') + '</td></tr>' +
                '<tr><td>支付类型</td><td>' + (extraData.pay_type || '-') + '</td></tr>' +
                '<tr><td>下单时间</td><td>' + (extraData.order_time || '-') + '</td></tr>' +
                '<tr><td>完成时间</td><td>' + (extraData.finish_time || '-') + '</td></tr>' +
                '<tr><td>验签状态</td><td>' + getSignatureStatusHtml(extraData, data) + '</td></tr>' +
                '<tr><td>备注</td><td>' + (extraData.remark || '-') + '</td></tr>' +
                '<tr><td>失败原因</td><td>' + (extraData.msg || '-') + '</td></tr>' +
                '<tr><td>客户端IP</td><td>' + (data.user_ip || '-') + '</td></tr>' +
                '</table>';
        }
        
        function generateRequestTab(requestData) {
            try {
                var displayData = requestData;
                if (typeof requestData === 'string') {
                    displayData = requestData;
                } else {
                    displayData = JSON.stringify(requestData, null, 2);
                }
                return '<h4>原始请求数据</h4><pre class="json-code">' + escapeHtml(displayData) + '</pre>';
            } catch (e) {
                return '<h4>原始请求数据</h4><pre class="json-code">数据解析失败: ' + e.message + '</pre>';
            }
        }
        
        function generateExtraTab(extraData) {
            try {
                var displayData = typeof extraData === 'string' ? extraData : JSON.stringify(extraData, null, 2);
                return '<h4>解析后的扩展数据</h4><pre class="json-code">' + escapeHtml(displayData) + '</pre>';
            } catch (e) {
                return '<h4>解析后的扩展数据</h4><pre class="json-code">数据解析失败: ' + e.message + '</pre>';
            }
        }
        
        function generateProcessingStepsTab(extraData) {
            if (!extraData.processing_steps || !Array.isArray(extraData.processing_steps)) {
                return '<div style="padding: 20px; text-align: center; color: #666;">' +
                       '<p>📋 暂无处理步骤记录</p>' +
                       '<p><small>回调处理步骤追踪功能可能未启用或此记录较早</small></p>' +
                       '</div>';
            }
            
            var stepsHtml = '<div class="processing-steps">';
            stepsHtml += '<h4>🔄 回调处理步骤追踪</h4>';
            stepsHtml += '<div class="steps-summary">';
            stepsHtml += '<span>总步骤数: <strong>' + extraData.processing_steps.length + '</strong></span> | ';
            stepsHtml += '<span>最后步骤: <strong>' + (extraData.last_step || '未知') + '</strong></span> | ';
            stepsHtml += '<span>状态: <strong>' + getStepStatusBadge(extraData.last_step_status || 'unknown') + '</strong></span>';
            stepsHtml += '</div>';
            
            stepsHtml += '<div class="steps-timeline">';
            
            for (var i = 0; i < extraData.processing_steps.length; i++) {
                var step = extraData.processing_steps[i];
                var stepClass = 'step-' + (step.status || 'unknown');
                var stepIcon = getStepIcon(step.status);
                
                stepsHtml += '<div class="step-item ' + stepClass + '">';
                stepsHtml += '<div class="step-header">';
                stepsHtml += '<span class="step-icon">' + stepIcon + '</span>';
                stepsHtml += '<span class="step-name">' + getStepDisplayName(step.step) + '</span>';
                stepsHtml += '<span class="step-status">' + getStepStatusBadge(step.status) + '</span>';
                stepsHtml += '<span class="step-time">' + (step.timestamp || '') + '</span>';
                stepsHtml += '</div>';
                
                if (step.error) {
                    stepsHtml += '<div class="step-error">❌ ' + escapeHtml(step.error) + '</div>';
                }
                
                if (step.data) {
                    stepsHtml += '<div class="step-data">';
                    stepsHtml += '<button class="toggle-step-data" onclick="toggleStepData(' + i + ')">查看详细数据</button>';
                    stepsHtml += '<div class="step-data-content" id="step-data-' + i + '" style="display:none;">';
                    stepsHtml += '<pre class="json-code">' + escapeHtml(JSON.stringify(step.data, null, 2)) + '</pre>';
                    stepsHtml += '</div>';
                    stepsHtml += '</div>';
                }
                
                stepsHtml += '</div>';
            }
            
            stepsHtml += '</div>';
            stepsHtml += '</div>';
            
            return stepsHtml;
        }
        
        function getStepIcon(status) {
            switch(status) {
                case 'success': return '✅';
                case 'error': return '❌';
                case 'warning': return '⚠️';
                case 'info': return 'ℹ️';
                default: return '❓';
            }
        }
        
        function getStepStatusBadge(status) {
            var badges = {
                'success': '<span class="badge badge-success">成功</span>',
                'error': '<span class="badge badge-error">错误</span>',
                'warning': '<span class="badge badge-warning">警告</span>',
                'info': '<span class="badge badge-info">信息</span>'
            };
            return badges[status] || '<span class="badge badge-unknown">未知</span>';
        }
        
        function getStepDisplayName(stepName) {
            var stepNames = {
                '01_callback_received': '01. 接收回调',
                '02_json_parsing': '02. JSON解析',
                '03_data_validation': '03. 数据验证',
                '04_signature_verification': '04. 签名验证',
                '05_order_lookup': '05. 订单查找',
                '06_order_processing': '06. 订单处理',
                '07_response_sent': '07. 发送响应'
            };
            return stepNames[stepName] || stepName;
        }
        
        function toggleStepData(stepIndex) {
            var element = document.getElementById('step-data-' + stepIndex);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }
        
        function switchDetailTab(tabName) {
            $('.detail-tab-content').hide();
            $('.detail-tab-button').removeClass('active');
            $('#' + tabName + '-tab').show();
            $('[onclick="switchDetailTab(\'' + tabName + '\')"]').addClass('active');
        }
        
        // 提取WordPress订单信息
        function extractWordPressOrderInfo(merchantOrderNo, orderId) {
            // 优先使用orderId
            if (orderId) {
                var editUrl = '<?php echo admin_url('post.php'); ?>?post=' + orderId + '&action=edit';
                return '<a href="' + editUrl + '" target="_blank">#' + orderId + '</a>';
            }
            
            // 从商户订单号中解析
            if (merchantOrderNo && merchantOrderNo.indexOf('_') !== -1) {
                var parts = merchantOrderNo.split('_');
                var orderNumber = parts[0];
                return '#' + orderNumber + ' <span style="color: #666;">(从商户订单号解析)</span>';
            }
            
            return '-';
        }
        
        // 格式化为北京时间
        function formatToBejingTime(logTime) {
            if (!logTime) return '-';
            try {
                var date = new Date(logTime);
                // 添加8小时转换为北京时间
                date.setHours(date.getHours() + 8);
                return date.toLocaleString('zh-CN');
            } catch(e) {
                return logTime;
            }
        }
        
        // 格式化币种显示
        function formatCurrencyDisplay(currencyCode) {
            if (!currencyCode) return '-';
            
            var currencies = {
                'USD': 'US Dollar ($)',
                'EUR': 'Euro (€)',
                'CNY': 'Chinese Yuan (¥)',
                'RUB': 'Russian Ruble (₽)',
                'BRL': 'Brazilian Real (R$)',
                'INR': 'Indian Rupee (₹)',
                'JPY': 'Japanese Yen (¥)',
                'GBP': 'British Pound (£)',
                'AUD': 'Australian Dollar (A$)',
                'CAD': 'Canadian Dollar (C$)'
            };
            
            return currencies[currencyCode] || currencyCode;
        }
        
        // 获取验签状态HTML
        function getSignatureStatusHtml(extraData, logData) {
            var signatureValid = extraData.signature_valid;
            var signatureStatus = extraData.signature_status;
            var logStatus = logData.status;
            
            console.log('签名状态调试:', {
                signatureValid: signatureValid,
                signatureStatus: signatureStatus,
                logStatus: logStatus
            });
            
            // 优先使用 signature_status 文本
            if (signatureStatus === 'PASS' || signatureValid === true || signatureValid === 'true' || signatureValid === 1) {
                return '<span style="color: green; font-weight: bold;">PASS</span>';
            } else if (signatureStatus === 'FAIL' || signatureValid === false || signatureValid === 'false' || signatureValid === 0) {
                return '<span style="color: red; font-weight: bold;">FAIL</span>';
            } else if (logStatus === 'signature_failed') {
                return '<span style="color: red; font-weight: bold;">FAIL</span>';
            } else if (logStatus === 'success' || logStatus === 'received') {
                return '<span style="color: green; font-weight: bold;">PASS</span>';
            } else {
                return '<span style="color: #666;">待验证</span>';
            }
        }
        </script>
        <?php
    }
    
    /**
     * AJAX处理回调详情请求
     */
    public function ajax_get_callback_detail() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'onepay_callback_detail')) {
            wp_die('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '无权限访问'));
            return;
        }
        
        $log_id = intval($_POST['log_id']);
        if (!$log_id) {
            wp_send_json_error(array('message' => '无效的日志ID'));
            return;
        }
        
        try {
            // 获取日志详情
            global $wpdb;
            $table_name = $wpdb->prefix . 'onepay_debug_logs';
            
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $log_id
            ));
            
            if (!$log) {
                wp_send_json_error(array('message' => '日志不存在'));
                return;
            }
            
            // 解析extra_data
            $log->extra_data = $log->extra_data ? json_decode($log->extra_data, true) : array();
            
            // 返回成功响应
            wp_send_json_success($log);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '获取日志详情失败: ' . $e->getMessage()));
        }
    }
}
?>