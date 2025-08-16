<?php
/**
 * 验证结账页面Visa/Mastercard标题显示
 */

// 加载WordPress
require_once '../../../wp-config.php';

// 检查权限和WooCommerce
if (!current_user_can('manage_woocommerce')) {
    wp_die('权限不足');
}

if (!class_exists('WooCommerce')) {
    wp_die('需要安装WooCommerce');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OnePay 结账页面标题验证</title>
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
        .test-section {
            background: #f9f9f9;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .checkout-simulation {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 15px 0;
        }
    </style>
    
    <!-- 加载OnePay样式 -->
    <link rel="stylesheet" type="text/css" href="assets/css/onepay-checkout-payment-styles.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="container">
    <h1>OnePay 结账页面标题验证</h1>
    
    <div class="test-section">
        <h2>1. 网关注册和标题检查</h2>
        <?php
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        // 检查Visa网关
        if (isset($gateways['onepay_visa'])) {
            $visa_gateway = $gateways['onepay_visa'];
            echo '<div class="status info"><strong>Visa网关:</strong> 已注册</div>';
            echo '<div class="status info"><strong>当前标题:</strong> "' . esc_html($visa_gateway->title) . '"</div>';
            
            if ($visa_gateway->title === 'VISA') {
                echo '<div class="status success">✓ Visa标题正确显示为 "VISA"</div>';
            } else {
                echo '<div class="status error">✗ Visa标题不正确，应为 "VISA"，当前为: "' . esc_html($visa_gateway->title) . '"</div>';
            }
        } else {
            echo '<div class="status error">✗ Visa网关未注册</div>';
        }
        
        // 检查Mastercard网关
        if (isset($gateways['onepay_mastercard'])) {
            $mastercard_gateway = $gateways['onepay_mastercard'];
            echo '<div class="status info"><strong>Mastercard网关:</strong> 已注册</div>';
            echo '<div class="status info"><strong>当前标题:</strong> "' . esc_html($mastercard_gateway->title) . '"</div>';
            
            if ($mastercard_gateway->title === 'Mastercard') {
                echo '<div class="status success">✓ Mastercard标题正确显示为 "Mastercard"</div>';
            } else {
                echo '<div class="status error">✗ Mastercard标题不正确，应为 "Mastercard"，当前为: "' . esc_html($mastercard_gateway->title) . '"</div>';
            }
        } else {
            echo '<div class="status error">✗ Mastercard网关未注册</div>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>2. 数据库设置检查</h2>
        <?php
        $visa_settings = get_option('woocommerce_onepay_visa_settings', array());
        $mastercard_settings = get_option('woocommerce_onepay_mastercard_settings', array());
        
        echo '<div class="status info"><strong>Visa数据库设置:</strong></div>';
        echo '<pre>' . json_encode($visa_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
        
        echo '<div class="status info"><strong>Mastercard数据库设置:</strong></div>';
        echo '<pre>' . json_encode($mastercard_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
        
        // 检查数据库中的标题设置
        if (isset($visa_settings['title']) && $visa_settings['title'] === 'VISA') {
            echo '<div class="status success">✓ 数据库中Visa标题设置正确</div>';
        } else {
            echo '<div class="status error">✗ 数据库中Visa标题设置不正确</div>';
        }
        
        if (isset($mastercard_settings['title']) && $mastercard_settings['title'] === 'Mastercard') {
            echo '<div class="status success">✓ 数据库中Mastercard标题设置正确</div>';
        } else {
            echo '<div class="status error">✗ 数据库中Mastercard标题设置不正确</div>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. 模拟结账页面显示</h2>
        <div class="status info">以下是模拟的结账页面支付选项，应显示统一大卡片内部用横线分隔，Visa标题为"VISA"</div>
        
        <div class="checkout-simulation woocommerce-checkout">
            <h3>支付方式</h3>
            
            <ul class="wc_payment_methods payment_methods methods">
                <?php
                // 动态生成支付选项
                foreach ($gateways as $gateway_id => $gateway) {
                    if (strpos($gateway_id, 'onepay') === 0) {
                        $is_visa = ($gateway_id === 'onepay_visa');
                        $is_mastercard = ($gateway_id === 'onepay_mastercard');
                        $is_checked = ($gateway_id === 'onepay_fps') ? 'checked="checked"' : '';
                        
                        echo '<li class="wc_payment_method payment_method_' . esc_attr($gateway_id) . '">';
                        echo '<input id="payment_method_' . esc_attr($gateway_id) . '" type="radio" class="input-radio" name="payment_method" value="' . esc_attr($gateway_id) . '" ' . $is_checked . '>';
                        echo '<label for="payment_method_' . esc_attr($gateway_id) . '">';
                        
                        // 显示图标（如果有）
                        if (!empty($gateway->icon)) {
                            echo '<img src="' . esc_url($gateway->icon) . '" alt="' . esc_attr($gateway->title) . '" style="max-width: 32px; height: auto; margin-right: 8px;">';
                        }
                        
                        // 显示标题
                        echo esc_html($gateway->title);
                        echo '</label>';
                        
                        // 对于信用卡网关，显示简化的表单
                        if ($is_visa || $is_mastercard) {
                            echo '<div class="payment_box payment_method_' . esc_attr($gateway_id) . '_box" style="display:none;">';
                            echo '<fieldset class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
                            
                            echo '<div class="form-row form-row-wide">';
                            echo '<label for="' . esc_attr($gateway_id) . '-card-number">卡号 <span class="required">*</span></label>';
                            echo '<input id="' . esc_attr($gateway_id) . '-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" placeholder="•••• •••• •••• ••••" name="' . esc_attr($gateway_id) . '-card-number" />';
                            echo '</div>';
                            
                            echo '<div class="form-row form-row-first">';
                            echo '<label for="' . esc_attr($gateway_id) . '-card-expiry">有效期 <span class="required">*</span></label>';
                            echo '<input id="' . esc_attr($gateway_id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" placeholder="MM/YY" name="' . esc_attr($gateway_id) . '-card-expiry" />';
                            echo '</div>';
                            
                            echo '<div class="form-row form-row-last">';
                            echo '<label for="' . esc_attr($gateway_id) . '-card-cvc">CVV <span class="required">*</span></label>';
                            echo '<input id="' . esc_attr($gateway_id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" placeholder="CVV" name="' . esc_attr($gateway_id) . '-card-cvc" style="width:100px" />';
                            echo '</div>';
                            
                            echo '<div class="clear"></div>';
                            echo '</fieldset>';
                            echo '</div>';
                        }
                        
                        echo '</li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    
    <div class="test-section">
        <h2>4. 操作测试</h2>
        
        <?php if (isset($_GET['force_fix'])) {
            // 强制修复标题
            update_option('woocommerce_onepay_visa_settings', array(
                'enabled' => 'yes',
                'title' => 'VISA'
            ));
            
            update_option('woocommerce_onepay_mastercard_settings', array(
                'enabled' => 'yes',
                'title' => 'Mastercard'
            ));
            
            // 清除WooCommerce缓存
            if (function_exists('wc_clear_transients')) {
                wc_clear_transients();
            }
            
            echo '<div class="status success">✓ 已强制修复网关标题，请刷新页面查看结果</div>';
            echo '<meta http-equiv="refresh" content="2;url=' . strtok($_SERVER["REQUEST_URI"], '?') . '">';
        } ?>
        
        <p><a href="?force_fix=1" style="background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;">强制修复标题问题</a></p>
        <p><a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" style="background: #666; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;">刷新页面</a></p>
    </div>
    
    <div class="test-section">
        <h2>5. 访问说明</h2>
        <div class="status info">
            <p><strong>测试页面地址:</strong> http://localhost/nb_wordpress/wp-content/plugins/onepay/verify-checkout-titles.php</p>
            <p><strong>实际结账页面:</strong> http://localhost/nb_wordpress/checkout/</p>
            <p><strong>支付设置页面:</strong> http://localhost/nb_wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout</p>
        </div>
    </div>
</div>

<!-- 加载jQuery和OnePay脚本 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 模拟WooCommerce数据
var onePayCheckoutData = {
    pluginUrl: 'assets/',
    version: '1.0.0',
    isCheckout: true
};
</script>
<script src="assets/js/onepay-checkout-payment-enhancement.js?v=<?php echo time(); ?>"></script>

<script>
jQuery(document).ready(function($) {
    // 处理支付方式切换
    $('input[name="payment_method"]').on('change', function() {
        var selectedMethod = $(this).val();
        
        // 隐藏所有支付框
        $('.payment_box').hide();
        
        // 显示对应的支付框
        $('.payment_method_' + selectedMethod + '_box').show();
        
        console.log('选择的支付方式:', selectedMethod);
    });
    
    // 自动测试功能
    setTimeout(function() {
        console.log('测试Visa表单展开...');
        $('#payment_method_onepay_visa').prop('checked', true).trigger('change');
    }, 1000);
});
</script>

</body>
</html>