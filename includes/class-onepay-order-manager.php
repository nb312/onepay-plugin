<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Order Manager Class
 * 
 * Handles order-specific operations and status management
 */
class OnePay_Order_Manager {
    
    /**
     * Add OnePay meta box to order admin page
     */
    public static function add_meta_box() {
        // Support both traditional posts and new HPOS
        $screen = 'shop_order';
        
        // Check if HPOS is enabled
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $screen = wc_get_page_screen_id('shop-order');
        }
            
        add_meta_box(
            'onepay-order-details',
            __('OnePay Payment Details', 'onepay'),
            array(__CLASS__, 'display_meta_box'),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Display OnePay meta box content
     * 
     * @param WP_Post $post Order post object
     */
    public static function display_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        if (!$order || $order->get_payment_method() !== 'onepay') {
            echo '<p>' . __('This order was not paid through OnePay.', 'onepay') . '</p>';
            return;
        }
        
        $onepay_data = self::get_onepay_order_data($order);
        
        if (empty($onepay_data)) {
            echo '<p>' . __('No OnePay data available for this order.', 'onepay') . '</p>';
            return;
        }
        
        ?>
        <div class="onepay-order-details">
            <table class="widefat">
                <tbody>
                    <?php if (!empty($onepay_data['order_no'])): ?>
                    <tr>
                        <td><strong><?php _e('OnePay Order No:', 'onepay'); ?></strong></td>
                        <td><?php echo esc_html($onepay_data['order_no']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($onepay_data['payment_method'])): ?>
                    <tr>
                        <td><strong><?php _e('Payment Method:', 'onepay'); ?></strong></td>
                        <td><?php echo esc_html($onepay_data['payment_method']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($onepay_data['callback_status'])): ?>
                    <tr>
                        <td><strong><?php _e('Callback Status:', 'onepay'); ?></strong></td>
                        <td>
                            <span class="onepay-status-badge <?php echo esc_attr(strtolower($onepay_data['callback_status'])); ?>">
                                <?php echo esc_html($onepay_data['callback_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($onepay_data['paid_amount'])): ?>
                    <tr>
                        <td><strong><?php _e('Paid Amount:', 'onepay'); ?></strong></td>
                        <td><?php echo wc_price($onepay_data['paid_amount']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($onepay_data['fee'])): ?>
                    <tr>
                        <td><strong><?php _e('Transaction Fee:', 'onepay'); ?></strong></td>
                        <td><?php echo wc_price($onepay_data['fee']); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($onepay_data['callback_time'])): ?>
                    <tr>
                        <td><strong><?php _e('Last Callback:', 'onepay'); ?></strong></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($onepay_data['callback_time']))); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="onepay-order-actions">
                <button type="button" class="button onepay-refresh-status" data-order-id="<?php echo $order->get_id(); ?>">
                    <?php _e('Refresh Status', 'onepay'); ?>
                </button>
            </div>
        </div>
        
        <style>
        .onepay-status-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .onepay-status-badge.success { background: #46b450; color: white; }
        .onepay-status-badge.pending { background: #ffb900; color: white; }
        .onepay-status-badge.failed, .onepay-status-badge.fail { background: #dc3232; color: white; }
        .onepay-order-actions { margin-top: 10px; text-align: center; }
        </style>
        <?php
    }
    
    /**
     * Get OnePay order data
     * 
     * @param WC_Order $order The order object
     * @return array OnePay order data
     */
    public static function get_onepay_order_data($order) {
        return array(
            'order_no' => $order->get_meta('_onepay_order_no'),
            'payment_method' => $order->get_meta('_onepay_payment_method'),
            'callback_status' => $order->get_meta('_onepay_callback_processed'),
            'paid_amount' => $order->get_meta('_onepay_paid_amount'),
            'fee' => $order->get_meta('_onepay_fee'),
            'callback_time' => $order->get_meta('_onepay_callback_time'),
            'callback_data' => $order->get_meta('_onepay_callback_data')
        );
    }
    
    /**
     * Add OnePay column to orders list
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public static function add_order_list_column($columns) {
        $columns['onepay_status'] = __('OnePay Status', 'onepay');
        return $columns;
    }
    
    /**
     * Display OnePay column content
     * 
     * @param string $column Column name
     * @param int $post_id Order ID
     */
    public static function display_order_list_column($column, $post_id) {
        if ($column === 'onepay_status') {
            $order = wc_get_order($post_id);
            
            if ($order && $order->get_payment_method() === 'onepay') {
                $status = $order->get_meta('_onepay_callback_processed');
                if ($status) {
                    echo '<span class="onepay-status-badge ' . esc_attr(strtolower($status)) . '">' . esc_html($status) . '</span>';
                } else {
                    echo '<span class="onepay-status-badge pending">' . __('Pending', 'onepay') . '</span>';
                }
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * Handle bulk actions for OnePay orders
     * 
     * @param string $redirect_to Redirect URL
     * @param string $doaction Action name
     * @param array $post_ids Order IDs
     * @return string Modified redirect URL
     */
    public static function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction === 'onepay_refresh_status') {
            $processed = 0;
            
            foreach ($post_ids as $post_id) {
                $order = wc_get_order($post_id);
                
                if ($order && $order->get_payment_method() === 'onepay') {
                    // Here you could implement status refresh from OnePay API
                    $processed++;
                }
            }
            
            $redirect_to = add_query_arg('onepay_refreshed', $processed, $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * Display admin notices for bulk actions
     */
    public static function display_bulk_action_notices() {
        if (isset($_REQUEST['onepay_refreshed']) && (int) $_REQUEST['onepay_refreshed']) {
            $refreshed_count = intval($_REQUEST['onepay_refreshed']);
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                sprintf(__('Refreshed status for %d OnePay orders.', 'onepay'), $refreshed_count) . 
                '</p></div>';
        }
    }
    
    /**
     * Add custom order actions
     * 
     * @param array $actions Existing actions
     * @param WC_Order $order Order object
     * @return array Modified actions
     */
    public static function add_order_actions($actions, $order) {
        if ($order->get_payment_method() === 'onepay') {
            $actions['onepay_refresh_status'] = __('Refresh OnePay status', 'onepay');
        }
        
        return $actions;
    }
    
    /**
     * Process custom order actions
     * 
     * @param WC_Order $order Order object
     */
    public static function process_order_action($order) {
        if ($order->get_payment_method() !== 'onepay') {
            return;
        }
        
        // Here you could implement status refresh from OnePay API
        $order->add_order_note(__('OnePay status refresh requested.', 'onepay'));
    }
    
    /**
     * Add order status change handling
     * 
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public static function handle_status_change($order_id, $old_status, $new_status, $order) {
        if ($order->get_payment_method() !== 'onepay') {
            return;
        }
        
        // Log status changes for OnePay orders
        if ($old_status !== $new_status) {
            $logger = wc_get_logger();
            $logger->info(
                sprintf('OnePay order %d status changed from %s to %s', $order_id, $old_status, $new_status),
                array('source' => 'onepay-order-manager')
            );
        }
    }
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        add_filter('manage_edit-shop_order_columns', array(__CLASS__, 'add_order_list_column'));
        add_action('manage_shop_order_posts_custom_column', array(__CLASS__, 'display_order_list_column'), 10, 2);
        add_filter('handle_bulk_actions-edit-shop_order', array(__CLASS__, 'handle_bulk_actions'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'display_bulk_action_notices'));
        add_filter('woocommerce_order_actions', array(__CLASS__, 'add_order_actions'), 10, 2);
        add_action('woocommerce_order_action_onepay_refresh_status', array(__CLASS__, 'process_order_action'));
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'handle_status_change'), 10, 4);
    }
}