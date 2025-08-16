<?php
/**
 * 测试和修复网关标题设置
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    // 如果是直接访问，加载WordPress
    require_once '../../../wp-config.php';
}

// 检查权限
if (!current_user_can('manage_woocommerce')) {
    wp_die('权限不足');
}

// 检查WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('需要安装WooCommerce');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OnePay 网关标题测试和修复</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .button {
            background: #0073aa;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .button:hover {
            background: #005a87;
        }
        .gateway-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>OnePay 网关标题测试和修复工具</h1>
    
    <div class="status info">
        此工具用于检查和修复Visa/Mastercard网关的标题显示问题
    </div>
    
    <?php
    // 获取当前网关设置
    $visa_settings = get_option('woocommerce_onepay_visa_settings', array());
    $mastercard_settings = get_option('woocommerce_onepay_mastercard_settings', array());
    
    // 获取可用的支付网关
    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    
    echo '<h2>当前网关状态</h2>';
    
    // 检查Visa网关
    echo '<div class="gateway-info">';
    echo '<h3>Visa网关</h3>';
    
    if (isset($gateways['onepay_visa'])) {
        $visa_gateway = $gateways['onepay_visa'];
        echo '<p><strong>状态:</strong> 已注册</p>';
        echo '<p><strong>当前标题:</strong> ' . esc_html($visa_gateway->title) . '</p>';
        echo '<p><strong>预期标题:</strong> VISA</p>';
        
        if ($visa_gateway->title === 'VISA') {
            echo '<div class="status success">✓ 标题正确</div>';
        } else {
            echo '<div class="status error">✗ 标题不正确，当前为: ' . esc_html($visa_gateway->title) . '</div>';
        }
    } else {
        echo '<div class="status error">✗ Visa网关未注册</div>';
    }
    
    echo '<p><strong>数据库设置:</strong> ' . json_encode($visa_settings) . '</p>';
    echo '</div>';
    
    // 检查Mastercard网关
    echo '<div class="gateway-info">';
    echo '<h3>Mastercard网关</h3>';
    
    if (isset($gateways['onepay_mastercard'])) {
        $mastercard_gateway = $gateways['onepay_mastercard'];
        echo '<p><strong>状态:</strong> 已注册</p>';
        echo '<p><strong>当前标题:</strong> ' . esc_html($mastercard_gateway->title) . '</p>';
        echo '<p><strong>预期标题:</strong> Mastercard</p>';
        
        if ($mastercard_gateway->title === 'Mastercard') {
            echo '<div class="status success">✓ 标题正确</div>';
        } else {
            echo '<div class="status error">✗ 标题不正确，当前为: ' . esc_html($mastercard_gateway->title) . '</div>';
        }
    } else {
        echo '<div class="status error">✗ Mastercard网关未注册</div>';
    }
    
    echo '<p><strong>数据库设置:</strong> ' . json_encode($mastercard_settings) . '</p>';
    echo '</div>';
    
    // 处理修复请求
    if (isset($_POST['fix_titles'])) {
        echo '<h2>修复结果</h2>';
        
        // 强制更新Visa设置
        $visa_fixed_settings = array_merge($visa_settings, array('title' => 'VISA'));
        update_option('woocommerce_onepay_visa_settings', $visa_fixed_settings);
        
        // 强制更新Mastercard设置
        $mastercard_fixed_settings = array_merge($mastercard_settings, array('title' => 'Mastercard'));
        update_option('woocommerce_onepay_mastercard_settings', $mastercard_fixed_settings);
        
        echo '<div class="status success">✓ 已强制更新网关标题设置</div>';
        echo '<div class="status info">请刷新页面查看更新后的结果</div>';
        
        // 清除WooCommerce缓存
        if (function_exists('wc_clear_transients')) {
            wc_clear_transients();
            echo '<div class="status success">✓ 已清除WooCommerce缓存</div>';
        }
    }
    
    // 处理重新加载网关请求
    if (isset($_POST['reload_gateways'])) {
        echo '<h2>重新加载网关</h2>';
        
        // 强制重新初始化支付网关
        WC()->payment_gateways()->init();
        
        echo '<div class="status success">✓ 已重新加载支付网关</div>';
        echo '<div class="status info">请刷新页面查看更新后的结果</div>';
    }
    ?>
    
    <h2>修复操作</h2>
    
    <form method="post" style="display: inline;">
        <button type="submit" name="fix_titles" class="button">强制修复标题设置</button>
    </form>
    
    <form method="post" style="display: inline;">
        <button type="submit" name="reload_gateways" class="button">重新加载网关</button>
    </form>
    
    <button onclick="location.reload()" class="button">刷新页面</button>
    
    <h2>说明</h2>
    <ul>
        <li><strong>强制修复标题设置:</strong> 直接在数据库中更新网关标题</li>
        <li><strong>重新加载网关:</strong> 强制WooCommerce重新初始化支付网关</li>
        <li><strong>刷新页面:</strong> 重新检查当前状态</li>
    </ul>
    
    <div class="status info">
        <strong>注意:</strong> 修复后请到WooCommerce结账页面验证标题是否正确显示
    </div>
</div>

</body>
</html>