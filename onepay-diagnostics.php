<?php
/**
 * OnePay 诊断工具
 * 在任何页面添加 ?onepay_debug=1 来查看诊断信息
 */

if (!defined('ABSPATH')) {
    exit;
}

// 只在添加debug参数时显示
if (isset($_GET['onepay_debug']) && $_GET['onepay_debug'] == '1' && current_user_can('manage_woocommerce')) {
    add_action('wp_footer', 'onepay_show_debug_info');
    add_action('admin_footer', 'onepay_show_debug_info');
}

function onepay_show_debug_info() {
    echo '<div id="onepay-debug" style="
        position: fixed; 
        top: 20px; 
        right: 20px; 
        width: 400px; 
        max-height: 80vh; 
        overflow-y: auto; 
        background: #fff; 
        border: 2px solid #dc3232; 
        padding: 15px; 
        font-family: monospace; 
        font-size: 12px; 
        z-index: 999999;
        box-shadow: 0 0 10px rgba(0,0,0,0.5);
    ">';
    
    echo '<h3 style="margin: 0 0 10px 0; color: #dc3232;">OnePay 诊断信息</h3>';
    echo '<button onclick="document.getElementById(\'onepay-debug\').style.display=\'none\'" style="float: right; margin-top: -30px;">关闭</button>';
    
    // 基础检查
    echo '<h4>🔍 基础检查</h4>';
    echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0;">';
    
    // WooCommerce 检查
    $wc_active = class_exists('WooCommerce');
    echo '• WooCommerce: ' . ($wc_active ? '✅ 激活' : '❌ 未激活') . '<br>';
    
    if ($wc_active) {
        echo '• WC版本: ' . (defined('WC_VERSION') ? WC_VERSION : '未知') . '<br>';
    }
    
    // 插件检查
    $plugin_active = class_exists('OnePay_Plugin');
    echo '• OnePay插件: ' . ($plugin_active ? '✅ 加载' : '❌ 未加载') . '<br>';
    
    // 网关类检查
    $gateway_class = class_exists('WC_Gateway_OnePay');
    echo '• 网关类: ' . ($gateway_class ? '✅ 存在' : '❌ 不存在') . '<br>';
    
    echo '</div>';
    
    // 货币检查
    echo '<h4>💰 货币检查</h4>';
    echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0;">';
    
    if ($wc_active) {
        $currency = get_woocommerce_currency();
        $supported = array('RUB', 'USD', 'EUR');
        $currency_ok = in_array($currency, $supported);
        
        echo '• 当前货币: ' . $currency . '<br>';
        echo '• 支持状态: ' . ($currency_ok ? '✅ 支持' : '❌ 不支持') . '<br>';
        echo '• 支持货币: ' . implode(', ', $supported) . '<br>';
    }
    
    echo '</div>';
    
    // 网关状态检查
    if ($wc_active && $gateway_class) {
        echo '<h4>⚙️ 网关状态</h4>';
        echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0;">';
        
        $gateways = WC()->payment_gateways()->payment_gateways();
        $onepay_exists = isset($gateways['onepay']);
        
        echo '• 网关注册: ' . ($onepay_exists ? '✅ 已注册' : '❌ 未注册') . '<br>';
        
        if ($onepay_exists) {
            $gateway = $gateways['onepay'];
            
            echo '• 启用状态: ' . ($gateway->enabled === 'yes' ? '✅ 已启用' : '❌ 已禁用') . '<br>';
            echo '• 可用状态: ' . ($gateway->is_available() ? '✅ 可用' : '❌ 不可用') . '<br>';
            echo '• 标题: ' . esc_html($gateway->title) . '<br>';
            echo '• 商户号: ' . (empty($gateway->merchant_no) ? '❌ 未设置' : '✅ 已设置') . '<br>';
            echo '• API地址: ' . (empty($gateway->api_url) ? '❌ 未设置' : '✅ 已设置') . '<br>';
            echo '• 测试模式: ' . ($gateway->testmode ? '✅ 开启' : '❌ 关闭') . '<br>';
            
            // 详细可用性检查
            if (!$gateway->is_available()) {
                echo '<strong style="color: #dc3232;">不可用原因分析:</strong><br>';
                
                if ($gateway->enabled !== 'yes') {
                    echo '• ❌ 网关未启用<br>';
                }
                
                if (!$gateway->is_valid_for_use()) {
                    echo '• ❌ 货币不支持<br>';
                }
                
                if (empty($gateway->merchant_no)) {
                    echo '• ❌ 商户号未设置<br>';
                }
                
                if (!is_admin() && empty($gateway->api_url)) {
                    echo '• ❌ API地址未设置<br>';
                }
            }
        }
        
        echo '</div>';
        
        // 所有支付网关列表
        echo '<h4>📋 所有支付网关</h4>';
        echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0; max-height: 200px; overflow-y: auto;">';
        
        foreach ($gateways as $id => $gateway_obj) {
            $status = $gateway_obj->enabled === 'yes' ? '✅' : '❌';
            $available = $gateway_obj->is_available() ? '(可用)' : '(不可用)';
            echo $status . ' ' . $id . ' - ' . $gateway_obj->get_method_title() . ' ' . $available . '<br>';
        }
        
        echo '</div>';
    }
    
    // 区块结账检查
    echo '<h4>🧱 区块结账检查</h4>';
    echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0;">';
    
    $blocks_available = class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType');
    $blocks_integration = class_exists('OnePay_Blocks_Integration');
    
    echo '• WC Blocks可用: ' . ($blocks_available ? '✅ 是' : '❌ 否') . '<br>';
    echo '• OnePay Blocks集成: ' . ($blocks_integration ? '✅ 已加载' : '❌ 未加载') . '<br>';
    
    if (class_exists('OnePay_Compatibility')) {
        $is_blocks_checkout = OnePay_Compatibility::is_blocks_checkout_active();
        echo '• 当前结账类型: ' . ($is_blocks_checkout ? '🧱 区块结账' : '📄 经典结账') . '<br>';
    }
    
    echo '</div>';
    
    // 环境信息
    echo '<h4>🌐 环境信息</h4>';
    echo '<div style="background: #f1f1f1; padding: 10px; margin: 5px 0;">';
    echo '• PHP版本: ' . PHP_VERSION . '<br>';
    echo '• WordPress版本: ' . get_bloginfo('version') . '<br>';
    echo '• SSL: ' . (is_ssl() ? '✅ 启用' : '❌ 禁用') . '<br>';
    echo '• 当前页面: ' . $_SERVER['REQUEST_URI'] . '<br>';
    echo '• 是否结账页: ' . (is_checkout() ? '✅ 是' : '❌ 否') . '<br>';
    echo '</div>';
    
    // 解决建议
    echo '<h4>💡 解决建议</h4>';
    echo '<div style="background: #e7f3ff; padding: 10px; margin: 5px 0;">';
    echo '<strong>常见问题解决方案：</strong><br>';
    echo '1. 如果货币不支持，请在WooCommerce设置中更改为RUB、USD或EUR<br>';
    echo '2. 如果网关未启用，请在 WooCommerce → 设置 → 支付 → OnePay 中启用<br>';
    echo '3. 如果商户号未设置，请在OnePay设置中填写商户号<br>';
    echo '4. 如果API地址未设置，请在OnePay设置中填写API地址<br>';
    echo '5. 确认OnePay插件已激活且WooCommerce正常运行<br>';
    echo '</div>';
    
    echo '</div>';
}

// 在插件加载时注册这个诊断工具
add_action('plugins_loaded', function() {
    if (defined('ONEPAY_PLUGIN_PATH')) {
        // 诊断工具已经包含在内
    }
}, 999);