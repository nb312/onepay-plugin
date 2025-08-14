<?php
/**
 * OnePay 国际卡支付测试页面
 * 
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-international-card.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 加载必要的类
if (!class_exists('OnePay_International_Card')) {
    require_once __DIR__ . '/includes/class-onepay-international-card.php';
}

// 处理测试表单提交
$test_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_action'])) {
    $action = $_POST['test_action'];
    
    switch ($action) {
        case 'validate_card':
            $card_number = $_POST['card_number'];
            $is_valid = OnePay_International_Card::validate_card_number($card_number);
            $card_type = OnePay_International_Card::detect_card_type($card_number);
            $test_result = sprintf(
                '<div class="notice notice-%s"><p>卡号: %s<br>有效性: %s<br>卡类型: %s</p></div>',
                $is_valid ? 'success' : 'error',
                esc_html($card_number),
                $is_valid ? '✅ 有效' : '❌ 无效',
                $card_type ?: '未知'
            );
            break;
            
        case 'test_signature':
            $test_data = array(
                'timeStamp' => strval(time() * 1000),
                'merchantOrderNo' => 'TEST_' . time(),
                'payType' => 'INTERNATIONAL_CARD_PAY',
                'payModel' => 'CREDIT_CARD',
                'currency' => 'USD',
                'orderAmount' => '10000',
                'productDetail' => '测试商品',
                'cardNo' => '4111111111111111',
                'cardType' => 'VISA',
                'cardCcv' => '123',
                'cardExpMonth' => '12',
                'cardExpYear' => '2025',
                'firstName' => 'Test',
                'lastName' => 'User',
                'country' => 'USA',
                'city' => 'New York',
                'address' => '123 Test Street',
                'phone' => '+1234567890',
                'postcode' => '10001',
                'callbackUrl' => home_url('/'),
                'noticeUrl' => home_url('/')
            );
            
            $json_data = json_encode($test_data, JSON_UNESCAPED_SLASHES);
            $gateway = new WC_Gateway_OnePay();
            
            if (!empty($gateway->private_key)) {
                $signature = OnePay_Signature::sign($json_data, $gateway->private_key);
                $test_result = sprintf(
                    '<div class="notice notice-info"><p><strong>测试数据:</strong><br><pre>%s</pre><br><strong>生成的签名:</strong><br>%s</p></div>',
                    esc_html(json_encode($test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                    esc_html($signature ?: '签名生成失败')
                );
            } else {
                $test_result = '<div class="notice notice-error"><p>请先在OnePay设置中配置私钥</p></div>';
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay 国际卡支付测试</title>
    <?php wp_head(); ?>
    <style>
        body {
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .test-section h2 {
            margin-top: 0;
            color: #23282d;
        }
        .form-row {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .button {
            background: #0073aa;
            border: 1px solid #0073aa;
            color: white;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .button:hover {
            background: #005a87;
        }
        .notice {
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid;
        }
        .notice-success {
            border-color: #46b450;
            background: #f0f8f0;
        }
        .notice-error {
            border-color: #dc3232;
            background: #fef7f7;
        }
        .notice-info {
            border-color: #00a0d2;
            background: #f7fcfe;
        }
        pre {
            background: #f1f1f1;
            padding: 10px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .test-cards {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .test-cards h4 {
            margin-top: 0;
        }
        .test-cards ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 OnePay 国际卡支付测试工具</h1>
        
        <?php echo $test_result; ?>
        
        <!-- 卡号验证测试 -->
        <div class="test-section">
            <h2>1. 卡号验证测试</h2>
            <form method="post">
                <input type="hidden" name="test_action" value="validate_card">
                <div class="form-row">
                    <label for="card_number">卡号</label>
                    <input type="text" id="card_number" name="card_number" 
                           placeholder="输入卡号进行验证" 
                           value="<?php echo isset($_POST['card_number']) ? esc_attr($_POST['card_number']) : ''; ?>">
                </div>
                <button type="submit" class="button">验证卡号</button>
            </form>
            
            <div class="test-cards">
                <h4>测试卡号（仅用于测试）:</h4>
                <ul>
                    <li>VISA: 4111111111111111</li>
                    <li>MasterCard: 5555555555554444</li>
                    <li>AMEX: 378282246310005</li>
                    <li>Discover: 6011111111111117</li>
                    <li>JCB: 3530111333300000</li>
                </ul>
            </div>
        </div>
        
        <!-- 签名生成测试 -->
        <div class="test-section">
            <h2>2. 签名生成测试</h2>
            <form method="post">
                <input type="hidden" name="test_action" value="test_signature">
                <p>点击按钮生成测试国际卡支付请求的签名</p>
                <button type="submit" class="button">生成测试签名</button>
            </form>
        </div>
        
        <!-- API配置状态 -->
        <div class="test-section">
            <h2>3. API配置状态</h2>
            <?php
            $gateway = new WC_Gateway_OnePay();
            $config_status = array(
                '启用状态' => $gateway->enabled === 'yes' ? '✅ 已启用' : '❌ 未启用',
                '测试模式' => $gateway->testmode ? '✅ 测试模式' : '❌ 生产模式',
                '商户号' => !empty($gateway->merchant_no) ? '✅ 已配置' : '❌ 未配置',
                '私钥' => !empty($gateway->private_key) ? '✅ 已配置' : '❌ 未配置',
                '平台公钥' => !empty($gateway->platform_public_key) ? '✅ 已配置' : '❌ 未配置',
                'API URL' => $gateway->api_url ?: '未配置'
            );
            ?>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($config_status as $key => $value): ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong><?php echo $key; ?>:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo $value; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- 快速链接 -->
        <div class="test-section">
            <h2>4. 快速链接</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" 
                   class="button" target="_blank">OnePay设置</a>
                <a href="<?php echo wc_get_checkout_url(); ?>?onepay_force=1" 
                   class="button" target="_blank">测试结账页面</a>
                <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" 
                   class="button" target="_blank">订单管理</a>
            </p>
        </div>
        
        <!-- 测试说明 -->
        <div class="test-section">
            <h2>5. 测试流程说明</h2>
            <ol>
                <li><strong>配置插件:</strong> 在WooCommerce设置中配置OnePay商户号和密钥</li>
                <li><strong>创建测试订单:</strong> 在前台添加商品到购物车</li>
                <li><strong>选择支付方式:</strong> 在结账页面选择"OnePay"并选择"国际卡支付"</li>
                <li><strong>输入卡片信息:</strong> 使用上面提供的测试卡号</li>
                <li><strong>完成支付:</strong> 提交订单，系统将调用OnePay API</li>
                <li><strong>3DS验证:</strong> 如果返回3DS URL，将跳转到验证页面</li>
                <li><strong>回调处理:</strong> OnePay将通过回调URL通知支付结果</li>
            </ol>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>