<?php
/**
 * 测试结账页面支付选项样式
 * 验证图标|文本|选中状态布局是否正常工作
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

// 加载WooCommerce
if (!class_exists('WooCommerce')) {
    wp_die('需要安装WooCommerce');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OnePay 结账支付选项样式测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .test-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 20px;
        }
        .test-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
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
    </style>
    
    <!-- 加载OnePay的CSS样式 -->
    <link rel="stylesheet" type="text/css" href="assets/css/onepay-checkout-payment-styles.css?v=<?php echo time(); ?>">
    
    <!-- 模拟WooCommerce的基础样式 -->
    <style>
        .woocommerce .wc_payment_methods {
            margin: 0;
            padding: 0;
        }
        .woocommerce .wc_payment_methods li {
            list-style: none;
        }
    </style>
</head>
<body>

<div class="test-container">
    <h1>OnePay 结账支付选项样式测试</h1>
    
    <div class="test-section">
        <div class="test-title">1. 样式文件加载测试</div>
        <?php
        $css_file = plugin_dir_path(__FILE__) . 'assets/css/onepay-checkout-payment-styles.css';
        $js_file = plugin_dir_path(__FILE__) . 'assets/js/onepay-checkout-payment-enhancement.js';
        
        if (file_exists($css_file)) {
            echo '<div class="status success">✓ CSS样式文件存在</div>';
        } else {
            echo '<div class="status error">✗ CSS样式文件不存在</div>';
        }
        
        if (file_exists($js_file)) {
            echo '<div class="status success">✓ JavaScript文件存在</div>';
        } else {
            echo '<div class="status error">✗ JavaScript文件不存在</div>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <div class="test-title">2. OnePay 网关注册测试</div>
        <?php
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $onepay_gateways = array(
            'onepay' => 'OnePay 主网关',
            'onepay_fps' => 'OnePay FPS',
            'onepay_russian_card' => 'OnePay 俄罗斯卡',
            'onepay_visa' => 'OnePay Visa',
            'onepay_mastercard' => 'OnePay Mastercard'
        );
        
        foreach ($onepay_gateways as $id => $name) {
            if (isset($gateways[$id])) {
                echo '<div class="status success">✓ ' . $name . ' 已注册</div>';
            } else {
                echo '<div class="status error">✗ ' . $name . ' 未注册</div>';
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <div class="test-title">3. 图标文件测试</div>
        <?php
        $icon_files = array(
            'fps-colored.svg' => 'FPS图标',
            'russian-card-colored.svg' => '俄罗斯卡图标'
        );
        
        foreach ($icon_files as $file => $name) {
            $icon_path = plugin_dir_path(__FILE__) . 'assets/images/' . $file;
            if (file_exists($icon_path)) {
                echo '<div class="status success">✓ ' . $name . ' 存在</div>';
            } else {
                echo '<div class="status error">✗ ' . $name . ' 不存在</div>';
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <div class="test-title">4. 模拟支付选项显示测试</div>
        <div class="status info">以下是模拟的支付选项，统一大卡片内部用横线分隔。Visa标题为"VISA"，表单字段简化为：卡号、有效期、CVV</div>
        
        <!-- 模拟WooCommerce支付选项HTML结构 - 统一卡片内分割线布局 -->
        <ul class="wc_payment_methods payment_methods methods">
            <li class="wc_payment_method payment_method_onepay_fps">
                <input id="payment_method_onepay_fps" type="radio" class="input-radio" name="payment_method" value="onepay_fps" checked="checked">
                <label for="payment_method_onepay_fps">
                    <img src="assets/images/fps-colored.svg" alt="FPS" style="max-width: 32px; height: auto;">
                    快速支付 FPS
                </label>
            </li>
            
            <li class="wc_payment_method payment_method_onepay_russian_card">
                <input id="payment_method_onepay_russian_card" type="radio" class="input-radio" name="payment_method" value="onepay_russian_card">
                <label for="payment_method_onepay_russian_card">
                    <img src="assets/images/russian-card-colored.svg" alt="Russian Card" style="max-width: 32px; height: auto;">
                    俄罗斯银行卡
                </label>
            </li>
            
            <li class="wc_payment_method payment_method_onepay_visa">
                <input id="payment_method_onepay_visa" type="radio" class="input-radio" name="payment_method" value="onepay_visa">
                <label for="payment_method_onepay_visa">
                    <img src="assets/images/cards/visa.svg" alt="Visa" style="max-width: 32px; height: auto;">
                    VISA
                </label>
                <div class="payment_box payment_method_onepay_visa_box" style="display:none;">
                    <fieldset class="wc-credit-card-form wc-payment-form">
                        <div class="form-row form-row-wide">
                            <label for="onepay_visa-card-number">卡号 <span class="required">*</span></label>
                            <input id="onepay_visa-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" placeholder="•••• •••• •••• ••••" name="onepay_visa-card-number" />
                        </div>
                        <div class="form-row form-row-first">
                            <label for="onepay_visa-card-expiry">有效期 <span class="required">*</span></label>
                            <input id="onepay_visa-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" placeholder="MM/YY" name="onepay_visa-card-expiry" />
                        </div>
                        <div class="form-row form-row-last">
                            <label for="onepay_visa-card-cvc">CVV <span class="required">*</span></label>
                            <input id="onepay_visa-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" placeholder="CVV" name="onepay_visa-card-cvc" style="width:100px" />
                        </div>
                        <div class="clear"></div>
                    </fieldset>
                </div>
            </li>
            
            <li class="wc_payment_method payment_method_onepay_mastercard">
                <input id="payment_method_onepay_mastercard" type="radio" class="input-radio" name="payment_method" value="onepay_mastercard">
                <label for="payment_method_onepay_mastercard">
                    <img src="assets/images/cards/mastercard.svg" alt="Mastercard" style="max-width: 32px; height: auto;">
                    Mastercard
                </label>
                <div class="payment_box payment_method_onepay_mastercard_box" style="display:none;">
                    <fieldset class="wc-credit-card-form wc-payment-form">
                        <div class="form-row form-row-wide">
                            <label for="onepay_mastercard-card-number">卡号 <span class="required">*</span></label>
                            <input id="onepay_mastercard-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" placeholder="•••• •••• •••• ••••" name="onepay_mastercard-card-number" />
                        </div>
                        <div class="form-row form-row-first">
                            <label for="onepay_mastercard-card-expiry">有效期 <span class="required">*</span></label>
                            <input id="onepay_mastercard-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" placeholder="MM/YY" name="onepay_mastercard-card-expiry" />
                        </div>
                        <div class="form-row form-row-last">
                            <label for="onepay_mastercard-card-cvc">CVV <span class="required">*</span></label>
                            <input id="onepay_mastercard-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" placeholder="CVV" name="onepay_mastercard-card-cvc" style="width:100px" />
                        </div>
                        <div class="clear"></div>
                    </fieldset>
                </div>
            </li>
            
            <!-- 模拟其他支付网关 -->
            <li class="wc_payment_method payment_method_paypal">
                <input id="payment_method_paypal" type="radio" class="input-radio" name="payment_method" value="paypal">
                <label for="payment_method_paypal">
                    PayPal
                </label>
            </li>
            
            <li class="wc_payment_method payment_method_bacs">
                <input id="payment_method_bacs" type="radio" class="input-radio" name="payment_method" value="bacs">
                <label for="payment_method_bacs">
                    银行转账
                </label>
            </li>
        </ul>
    </div>
    
    <div class="test-section">
        <div class="test-title">5. JavaScript增强功能测试</div>
        <div class="status info">点击任意支付选项，应该自动更新选中状态。选中Visa或Mastercard时，表单应自动展开</div>
        <button onclick="testJavaScript()">测试JavaScript功能</button>
        <div id="js-test-result"></div>
    </div>
</div>

<!-- 加载jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- 加载OnePay的JavaScript -->
<script>
// 模拟WordPress本地化数据
var onePayCheckoutData = {
    pluginUrl: 'assets/',
    version: '1.0.0',
    nonce: 'test-nonce',
    ajaxUrl: 'admin-ajax.php',
    isCheckout: true,
    isOrderPay: false
};
</script>
<script src="assets/js/onepay-checkout-payment-enhancement.js?v=<?php echo time(); ?>"></script>

<script>
function testJavaScript() {
    var result = document.getElementById('js-test-result');
    
    // 测试jQuery是否加载
    if (typeof jQuery !== 'undefined') {
        result.innerHTML += '<div class="status success">✓ jQuery已加载</div>';
    } else {
        result.innerHTML += '<div class="status error">✗ jQuery未加载</div>';
        return;
    }
    
    // 测试OnePay增强脚本是否加载
    if (typeof window.OnePayCheckoutEnhancement !== 'undefined') {
        result.innerHTML += '<div class="status success">✓ OnePay增强脚本已加载</div>';
    } else {
        result.innerHTML += '<div class="status error">✗ OnePay增强脚本未加载</div>';
    }
    
    // 测试选中状态切换
    var selectedItems = jQuery('.wc_payment_methods li.selected').length;
    result.innerHTML += '<div class="status info">当前选中项目数: ' + selectedItems + '</div>';
    
    // 模拟点击事件
    jQuery('.wc_payment_methods li').first().trigger('click');
    
    setTimeout(function() {
        var newSelectedItems = jQuery('.wc_payment_methods li.selected').length;
        if (newSelectedItems > 0) {
            result.innerHTML += '<div class="status success">✓ 选中状态功能正常</div>';
        } else {
            result.innerHTML += '<div class="status error">✗ 选中状态功能异常</div>';
        }
    }, 500);
}

// 自动测试样式应用和信用卡表单功能
jQuery(document).ready(function($) {
    // 检查CSS是否正确应用
    var paymentList = $('.wc_payment_methods');
    if (paymentList.length > 0) {
        console.log('支付方法列表找到');
        
        // 检查关键样式是否应用
        var firstItem = paymentList.find('li').first();
        var computedStyle = window.getComputedStyle(firstItem[0]);
        
        console.log('第一个支付项目的display属性:', computedStyle.display);
        console.log('第一个支付项目的border属性:', computedStyle.border);
    }
    
    // 处理信用卡支付表单显示/隐藏
    $('input[name="payment_method"]').on('change', function() {
        var selectedMethod = $(this).val();
        
        // 隐藏所有支付框
        $('.payment_box').hide();
        
        // 显示对应的支付框
        if (selectedMethod === 'onepay_visa') {
            $('.payment_method_onepay_visa_box').show();
        } else if (selectedMethod === 'onepay_mastercard') {
            $('.payment_method_onepay_mastercard_box').show();
        }
        
        console.log('选择的支付方式:', selectedMethod);
    });
    
    // 测试表单自动展开功能
    setTimeout(function() {
        console.log('测试Visa表单展开...');
        $('#payment_method_onepay_visa').prop('checked', true).trigger('change');
        
        setTimeout(function() {
            console.log('测试Mastercard表单展开...');
            $('#payment_method_onepay_mastercard').prop('checked', true).trigger('change');
        }, 2000);
    }, 1000);
});
</script>

</body>
</html>