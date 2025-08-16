<?php
/**
 * 快速检查网关标题状态
 */

// 加载WordPress
require_once '../../../wp-config.php';

// 检查WooCommerce
if (!class_exists('WooCommerce')) {
    die('需要安装WooCommerce');
}

echo "<h2>OnePay 网关标题检查结果</h2>\n";

// 获取支付网关
$gateways = WC()->payment_gateways()->get_available_payment_gateways();

// 检查Visa网关
if (isset($gateways['onepay_visa'])) {
    $visa_gateway = $gateways['onepay_visa'];
    echo "<p><strong>Visa网关状态:</strong> 已注册</p>\n";
    echo "<p><strong>当前标题:</strong> " . esc_html($visa_gateway->title) . "</p>\n";
    echo "<p><strong>预期标题:</strong> VISA</p>\n";
    
    if ($visa_gateway->title === 'VISA') {
        echo "<p style='color: green;'>✓ Visa标题正确</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Visa标题不正确</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Visa网关未注册</p>\n";
}

// 检查Mastercard网关
if (isset($gateways['onepay_mastercard'])) {
    $mastercard_gateway = $gateways['onepay_mastercard'];
    echo "<p><strong>Mastercard网关状态:</strong> 已注册</p>\n";
    echo "<p><strong>当前标题:</strong> " . esc_html($mastercard_gateway->title) . "</p>\n";
    echo "<p><strong>预期标题:</strong> Mastercard</p>\n";
    
    if ($mastercard_gateway->title === 'Mastercard') {
        echo "<p style='color: green;'>✓ Mastercard标题正确</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Mastercard标题不正确</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Mastercard网关未注册</p>\n";
}

// 检查数据库设置
$visa_settings = get_option('woocommerce_onepay_visa_settings', array());
$mastercard_settings = get_option('woocommerce_onepay_mastercard_settings', array());

echo "<h3>数据库设置</h3>\n";
echo "<p><strong>Visa设置:</strong> " . json_encode($visa_settings) . "</p>\n";
echo "<p><strong>Mastercard设置:</strong> " . json_encode($mastercard_settings) . "</p>\n";

// 强制更新设置
if (isset($_GET['force_update'])) {
    update_option('woocommerce_onepay_visa_settings', array(
        'enabled' => 'yes',
        'title' => 'VISA'
    ));
    
    update_option('woocommerce_onepay_mastercard_settings', array(
        'enabled' => 'yes', 
        'title' => 'Mastercard'
    ));
    
    echo "<p style='color: blue;'>已强制更新数据库设置，请刷新页面查看结果</p>\n";
}

echo "<p><a href='?force_update=1'>强制更新数据库设置</a></p>\n";
echo "<p><strong>访问地址:</strong> http://localhost/nb_wordpress/wp-content/plugins/onepay/quick-gateway-check.php</p>\n";
?>