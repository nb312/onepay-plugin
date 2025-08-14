<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Compatibility Class
 * 
 * Handles compatibility with various WooCommerce features and versions
 */
class OnePay_Compatibility {
    
    /**
     * Initialize compatibility checks and hooks
     */
    public static function init() {
        add_action('admin_notices', array(__CLASS__, 'compatibility_notices'));
        add_filter('woocommerce_get_sections_advanced', array(__CLASS__, 'add_compatibility_section'));
        add_action('wp_ajax_onepay_dismiss_notice', array(__CLASS__, 'dismiss_notice'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    
    /**
     * Check if WooCommerce version is compatible
     * 
     * @return bool
     */
    public static function is_wc_version_compatible() {
        if (!defined('WC_VERSION')) {
            return false;
        }
        
        return version_compare(WC_VERSION, '3.0', '>=');
    }
    
    /**
     * Check if PHP version is compatible
     * 
     * @return bool
     */
    public static function is_php_version_compatible() {
        return version_compare(PHP_VERSION, '7.4', '>=');
    }
    
    /**
     * Check if WordPress version is compatible
     * 
     * @return bool
     */
    public static function is_wp_version_compatible() {
        global $wp_version;
        return version_compare($wp_version, '5.0', '>=');
    }
    
    /**
     * Check if required PHP extensions are available
     * 
     * @return array
     */
    public static function check_php_extensions() {
        $required_extensions = array(
            'openssl' => extension_loaded('openssl'),
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring')
        );
        
        return $required_extensions;
    }
    
    /**
     * Check WooCommerce feature compatibility
     * 
     * @return array
     */
    public static function check_wc_features() {
        $features = array();
        
        // Check HPOS compatibility
        $features['hpos'] = array(
            'available' => class_exists('\Automattic\WooCommerce\Utilities\OrderUtil'),
            'enabled' => false,
            'compatible' => true
        );
        
        if ($features['hpos']['available']) {
            $features['hpos']['enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        
        // Check Blocks compatibility
        $features['blocks'] = array(
            'available' => class_exists('Automattic\WooCommerce\Blocks\Package'),
            'compatible' => true // OnePay now supports blocks
        );
        
        // Check Analytics compatibility
        $features['analytics'] = array(
            'available' => class_exists('Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore'),
            'compatible' => true
        );
        
        return $features;
    }
    
    /**
     * Display compatibility notices
     */
    public static function compatibility_notices() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Check PHP version
        if (!self::is_php_version_compatible()) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OnePay:</strong> ';
            printf(__('Requires PHP 7.4 or higher. You are running PHP %s.', 'onepay'), PHP_VERSION);
            echo '</p></div>';
        }
        
        // Check WooCommerce version
        if (!self::is_wc_version_compatible()) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OnePay:</strong> ';
            printf(__('Requires WooCommerce 3.0 or higher. You are running WooCommerce %s.', 'onepay'), defined('WC_VERSION') ? WC_VERSION : 'Unknown');
            echo '</p></div>';
        }
        
        // Check PHP extensions
        $extensions = self::check_php_extensions();
        $missing_extensions = array_keys(array_filter($extensions, function($loaded) {
            return !$loaded;
        }));
        
        if (!empty($missing_extensions)) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OnePay:</strong> ';
            printf(__('Missing required PHP extensions: %s', 'onepay'), implode(', ', $missing_extensions));
            echo '</p></div>';
        }
        
        // Show blocks support information on WooCommerce settings pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'woocommerce') !== false) {
            $features = self::check_wc_features();
            
            // Show blocks support confirmation if blocks checkout is active
            if ($features['blocks']['available'] && $features['blocks']['compatible'] && self::is_blocks_checkout_active()) {
                $dismiss_key = 'onepay_blocks_support_confirmed_v1';
                
                // Check if user has dismissed this notice
                if (!get_user_meta(get_current_user_id(), $dismiss_key, true)) {
                    echo '<div class="notice notice-success is-dismissible" data-dismiss-key="' . $dismiss_key . '">';
                    echo '<p><strong>' . __('OnePay提示', 'onepay') . ':</strong> ';
                    echo __('OnePay现已完全支持WooCommerce区块结账！您可以正常使用区块结账页面的所有OnePay支付功能。', 'onepay');
                    echo '</p></div>';
                }
            }
        }
    }
    
    /**
     * Check if WooCommerce Blocks checkout is active
     * 
     * @return bool
     */
    public static function is_blocks_checkout_active() {
        // Check if checkout page uses blocks
        $checkout_page_id = wc_get_page_id('checkout');
        
        if ($checkout_page_id > 0) {
            $checkout_page = get_post($checkout_page_id);
            if ($checkout_page && has_blocks($checkout_page->post_content)) {
                // Check if it has the checkout block
                if (has_block('woocommerce/checkout', $checkout_page_id)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add compatibility section to WooCommerce settings
     * 
     * @param array $sections
     * @return array
     */
    public static function add_compatibility_section($sections) {
        $sections['onepay_compatibility'] = __('OnePay Compatibility', 'onepay');
        return $sections;
    }
    
    /**
     * Get system information for support
     * 
     * @return array
     */
    public static function get_system_info() {
        global $wp_version;
        
        return array(
            'wordpress_version' => $wp_version,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'php_version' => PHP_VERSION,
            'onepay_version' => defined('ONEPAY_VERSION') ? ONEPAY_VERSION : '1.0.0',
            'php_extensions' => self::check_php_extensions(),
            'wc_features' => self::check_wc_features(),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
    }
    
    /**
     * Check if current environment is suitable for OnePay
     * 
     * @return array
     */
    public static function environment_check() {
        $issues = array();
        
        if (!self::is_php_version_compatible()) {
            $issues[] = sprintf(__('PHP version %s is too old. Minimum required: 7.4', 'onepay'), PHP_VERSION);
        }
        
        if (!self::is_wc_version_compatible()) {
            $issues[] = sprintf(__('WooCommerce version %s is too old. Minimum required: 3.0', 'onepay'), defined('WC_VERSION') ? WC_VERSION : 'Unknown');
        }
        
        $extensions = self::check_php_extensions();
        $missing = array_keys(array_filter($extensions, function($loaded) { return !$loaded; }));
        
        if (!empty($missing)) {
            $issues[] = sprintf(__('Missing PHP extensions: %s', 'onepay'), implode(', ', $missing));
        }
        
        // Check if SSL is available
        if (!function_exists('openssl_sign')) {
            $issues[] = __('OpenSSL functions not available. Required for RSA signature operations.', 'onepay');
        }
        
        return array(
            'compatible' => empty($issues),
            'issues' => $issues
        );
    }
    
    /**
     * Enqueue admin scripts for notice dismissal
     */
    public static function enqueue_admin_scripts() {
        wp_enqueue_script('onepay-admin-notices', plugins_url('assets/js/admin-notices.js', dirname(__FILE__)), array('jquery'), '1.0.0', true);
        wp_localize_script('onepay-admin-notices', 'onepay_admin_notices', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('onepay_dismiss_notice')
        ));
    }
    
    /**
     * Handle notice dismissal via AJAX
     */
    public static function dismiss_notice() {
        check_ajax_referer('onepay_dismiss_notice', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'onepay'));
        }
        
        $dismiss_key = sanitize_text_field($_POST['dismiss_key']);
        update_user_meta(get_current_user_id(), $dismiss_key, true);
        
        wp_send_json_success();
    }
}