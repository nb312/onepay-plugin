<?php
/**
 * OnePay 插件状态检查脚本
 * 用于诊断插件和支付网关的配置状态
 */

// 加载WordPress环境
require_once($_SERVER['DOCUMENT_ROOT'] . '/nb_wordpress/wp-load.php');

echo "========================================\n";
echo "OnePay 插件状态检查\n";
echo "========================================\n\n";

// 检查插件激活状态
$active_plugins = get_option('active_plugins');
$is_active = in_array('onepay/onepay.php', $active_plugins);
echo "1. 插件激活状态: " . ($is_active ? "✓ 已激活" : "✗ 未激活") . "\n";

if (!$is_active) {
    echo "   提示: 请在WordPress后台激活OnePay插件\n";
}

// 检查WooCommerce是否安装
if (class_exists('WooCommerce')) {
    echo "2. WooCommerce状态: ✓ 已安装\n";
    echo "   版本: " . WC()->version . "\n";
} else {
    echo "2. WooCommerce状态: ✗ 未安装\n";
    echo "   提示: OnePay需要WooCommerce才能工作\n";
    exit;
}

// 检查支付网关配置
$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
$onepay_available = isset($payment_gateways['onepay']);

echo "3. OnePay网关在结账页面: " . ($onepay_available ? "✓ 可用" : "✗ 不可用") . "\n";

// 获取OnePay网关设置
$onepay_settings = get_option('woocommerce_onepay_settings', array());

echo "\n4. OnePay网关配置:\n";
echo "   - 启用状态: " . (isset($onepay_settings['enabled']) && $onepay_settings['enabled'] === 'yes' ? "✓ 已启用" : "✗ 未启用") . "\n";
echo "   - 测试模式: " . (isset($onepay_settings['testmode']) && $onepay_settings['testmode'] === 'yes' ? "是" : "否") . "\n";
echo "   - 商户号: " . (!empty($onepay_settings['merchant_no']) ? "✓ 已配置 (" . $onepay_settings['merchant_no'] . ")" : "✗ 未配置") . "\n";
echo "   - 私钥: " . (!empty($onepay_settings['private_key']) ? "✓ 已配置" : "✗ 未配置") . "\n";
echo "   - 平台公钥: " . (!empty($onepay_settings['platform_public_key']) ? "✓ 已配置" : "✗ 未配置") . "\n";
echo "   - 调试模式: " . (isset($onepay_settings['debug']) && $onepay_settings['debug'] === 'yes' ? "开启" : "关闭") . "\n";

// 检查货币设置
$currency = get_woocommerce_currency();
echo "\n5. 商店货币: " . $currency . "\n";

// 检查是否所有支付网关都已加载
$all_gateways = WC()->payment_gateways->payment_gateways();
echo "\n6. 所有已注册的支付网关:\n";
foreach ($all_gateways as $gateway_id => $gateway) {
    $is_enabled = $gateway->is_available() ? '已启用' : '未启用';
    echo "   - {$gateway_id}: {$gateway->title} ({$is_enabled})\n";
}

// 检查回调URL
$callback_url = add_query_arg('wc-api', 'onepay_callback', home_url('/'));
echo "\n7. 回调URL配置:\n";
echo "   - 异步回调: " . $callback_url . "\n";
echo "   - 同步返回: " . add_query_arg('wc-api', 'onepay_return', home_url('/')) . "\n";

// 检查日志文件
$log_file = WC_LOG_DIR . 'onepay-' . date('Y-m-d') . '-' . md5('onepay' . date('Y-m-d')) . '.log';
echo "\n8. 日志文件:\n";
if (file_exists($log_file)) {
    echo "   - 位置: " . $log_file . "\n";
    echo "   - 大小: " . filesize($log_file) . " bytes\n";
} else {
    echo "   - 今日无日志记录\n";
}

echo "\n========================================\n";
echo "诊断建议:\n";
echo "========================================\n";

$issues = array();

if (!$is_active) {
    $issues[] = "激活OnePay插件";
}

if (!$onepay_available) {
    if (isset($onepay_settings['enabled']) && $onepay_settings['enabled'] !== 'yes') {
        $issues[] = "在WooCommerce设置中启用OnePay支付网关";
    }
    if (empty($onepay_settings['merchant_no'])) {
        $issues[] = "配置商户号（测试可使用TEST001）";
    }
    if (empty($onepay_settings['private_key'])) {
        $issues[] = "配置RSA私钥";
    }
}

if (empty($issues)) {
    echo "✓ 所有基本配置已完成，可以进行支付测试\n";
    echo "\n测试步骤:\n";
    echo "1. 添加商品到购物车\n";
    echo "2. 进入结账页面\n";
    echo "3. 选择OnePay支付方式\n";
    echo "4. 完成订单\n";
} else {
    echo "请完成以下配置:\n";
    foreach ($issues as $index => $issue) {
        echo ($index + 1) . ". " . $issue . "\n";
    }
}

echo "\n配置页面: " . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . "\n";