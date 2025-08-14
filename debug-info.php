<?php
/**
 * OnePay Debug Information Page
 * 
 * Add this as a shortcode to troubleshoot gateway visibility issues
 * Usage: [onepay_debug]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add shortcode for debugging
add_shortcode('onepay_debug', 'onepay_debug_info');

function onepay_debug_info() {
    if (!current_user_can('manage_woocommerce')) {
        return '<p>Access denied. Administrator privileges required.</p>';
    }
    
    ob_start();
    ?>
    <div class="onepay-debug-info" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">
        <h3>OnePay Debug Information</h3>
        
        <?php
        // Check WooCommerce
        $wc_active = class_exists('WooCommerce');
        echo '<p><strong>WooCommerce Active:</strong> ' . ($wc_active ? 'Yes' : 'No') . '</p>';
        
        // Check currency
        if ($wc_active) {
            $currency = get_woocommerce_currency();
            $supported_currencies = array('RUB', 'USD', 'EUR');
            $currency_supported = in_array($currency, $supported_currencies);
            echo '<p><strong>Current Currency:</strong> ' . $currency . ' (' . ($currency_supported ? 'Supported' : 'Not Supported') . ')</p>';
        }
        
        // Check if gateway class exists
        $gateway_class_exists = class_exists('WC_Gateway_OnePay');
        echo '<p><strong>Gateway Class Loaded:</strong> ' . ($gateway_class_exists ? 'Yes' : 'No') . '</p>';
        
        // Check blocks support
        $blocks_available = class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType');
        $blocks_integration_loaded = class_exists('OnePay_Blocks_Integration');
        echo '<p><strong>WooCommerce Blocks Available:</strong> ' . ($blocks_available ? 'Yes' : 'No') . '</p>';
        echo '<p><strong>OnePay Blocks Integration:</strong> ' . ($blocks_integration_loaded ? 'Loaded' : 'Not loaded') . '</p>';
        
        // Check current checkout type
        if (class_exists('OnePay_Compatibility')) {
            $is_blocks_checkout = OnePay_Compatibility::is_blocks_checkout_active();
            echo '<p><strong>Current Checkout Type:</strong> ' . ($is_blocks_checkout ? 'Blocks Checkout' : 'Classic Checkout') . '</p>';
        }
        
        // Check available payment gateways
        if ($wc_active) {
            $available_gateways = WC()->payment_gateways()->payment_gateways();
            $onepay_available = isset($available_gateways['onepay']);
            echo '<p><strong>OnePay in Available Gateways:</strong> ' . ($onepay_available ? 'Yes' : 'No') . '</p>';
            
            if ($onepay_available) {
                $gateway = $available_gateways['onepay'];
                echo '<p><strong>Gateway Enabled:</strong> ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No') . '</p>';
                echo '<p><strong>Gateway Available:</strong> ' . ($gateway->is_available() ? 'Yes' : 'No') . '</p>';
                echo '<p><strong>Gateway Title:</strong> ' . esc_html($gateway->title) . '</p>';
                echo '<p><strong>Merchant No:</strong> ' . (empty($gateway->merchant_no) ? 'Not Set' : 'Set') . '</p>';
                echo '<p><strong>API URL:</strong> ' . (empty($gateway->api_url) ? 'Not Set' : 'Set') . '</p>';
                echo '<p><strong>Blocks Compatible:</strong> ' . (method_exists($gateway, 'supports') && $gateway->supports('blocks') ? 'Yes' : 'Traditional Only') . '</p>';
            }
        }
        
        // Check plugin constants
        echo '<p><strong>Plugin Path:</strong> ' . (defined('ONEPAY_PLUGIN_PATH') ? ONEPAY_PLUGIN_PATH : 'Not defined') . '</p>';
        echo '<p><strong>Plugin Version:</strong> ' . (defined('ONEPAY_VERSION') ? ONEPAY_VERSION : 'Not defined') . '</p>';
        
        // List all available gateways for comparison
        if ($wc_active && isset($available_gateways)) {
            echo '<h4>All Available Payment Gateways:</h4>';
            echo '<ul>';
            foreach ($available_gateways as $id => $gateway) {
                echo '<li>' . $id . ' - ' . esc_html($gateway->get_method_title()) . ' (' . ($gateway->enabled === 'yes' ? 'Enabled' : 'Disabled') . ')</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// Also add admin notice if there are issues
add_action('admin_notices', 'onepay_debug_admin_notice');

function onepay_debug_admin_notice() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen->id !== 'woocommerce_page_wc-settings') {
        return;
    }
    
    // Check if OnePay gateway is properly registered
    if (class_exists('WooCommerce')) {
        $available_gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($available_gateways['onepay'])) {
            echo '<div class="notice notice-warning"><p><strong>OnePay Debug:</strong> Gateway not found in available gateways. Check if the plugin is properly activated and WooCommerce is running.</p></div>';
        } elseif ($available_gateways['onepay']->enabled !== 'yes') {
            echo '<div class="notice notice-info"><p><strong>OnePay:</strong> Gateway is disabled. Enable it in the payment settings to show at checkout.</p></div>';
        } elseif (!$available_gateways['onepay']->is_available()) {
            echo '<div class="notice notice-warning"><p><strong>OnePay:</strong> Gateway is enabled but not available. Check currency support and configuration.</p></div>';
        }
    }
}