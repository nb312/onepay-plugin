<?php
/**
 * OnePay回调签名验证调试工具
 * 
 * 用于深度调试回调签名验证失败的问题
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-callback-signature.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 加载必要的类
require_once __DIR__ . '/includes/class-onepay-signature.php';
require_once __DIR__ . '/includes/class-wc-gateway-onepay.php';

$gateway = new WC_Gateway_OnePay();

// 模拟回调数据结构
$sample_callback_data = array(
    'merchantNo' => $gateway->merchant_no ?: 'TEST001',
    'result' => json_encode(array(
        'code' => '0000',
        'message' => 'SUCCESS',
        'data' => array(
            'orderNo' => 'OP' . time(),
            'merchantOrderNo' => '123456',
            'orderStatus' => 'SUCCESS',
            'orderAmount' => 10000, // 分
            'paidAmount' => 10000,
            'currency' => '643', // RUB
            'payModel' => 'CARDPAYMENT',
            'orderTime' => time() * 1000,
            'finishTime' => time() * 1000
        )
    ), JSON_UNESCAPED_UNICODE),
    'sign' => ''
);

// 生成签名
$signature_error = '';
$signature_generated = false;
if (!empty($gateway->private_key)) {
    try {
        $content_to_sign = $sample_callback_data['result'];
        $signature = OnePay_Signature::sign($content_to_sign, $gateway->private_key);
        if ($signature) {
            $sample_callback_data['sign'] = $signature;
            $signature_generated = true;
        } else {
            $signature_error = '签名生成失败';
        }
    } catch (Exception $e) {
        $signature_error = $e->getMessage();
    }
} else {
    $signature_error = '私钥未配置';
}

// 验证签名
$verification_result = array();
if ($signature_generated && !empty($gateway->platform_public_key)) {
    try {
        $content_to_verify = $sample_callback_data['result'];
        $signature_to_verify = $sample_callback_data['sign'];
        
        // 详细验证过程
        $verification_result['content_length'] = strlen($content_to_verify);
        $verification_result['signature_length'] = strlen($signature_to_verify);
        $verification_result['content_preview'] = substr($content_to_verify, 0, 100);
        $verification_result['signature_preview'] = substr($signature_to_verify, 0, 50);
        
        // 验证公钥格式
        $public_key_resource = @openssl_pkey_get_public($gateway->platform_public_key);
        $verification_result['public_key_valid'] = $public_key_resource !== false;
        if ($public_key_resource) {
            $key_details = openssl_pkey_get_details($public_key_resource);
            $verification_result['key_type'] = $key_details['type'] ?? 'unknown';
            $verification_result['key_bits'] = $key_details['bits'] ?? 0;
            openssl_pkey_free($public_key_resource);
        }
        
        // 手动验证签名
        $signature_decoded = base64_decode($signature_to_verify);
        $verification_result['signature_decode_success'] = $signature_decoded !== false;
        $verification_result['decoded_signature_length'] = $signature_decoded ? strlen($signature_decoded) : 0;
        
        if ($verification_result['signature_decode_success'] && $verification_result['public_key_valid']) {
            $public_key_resource = openssl_pkey_get_public($gateway->platform_public_key);
            $verify_result = openssl_verify($content_to_verify, $signature_decoded, $public_key_resource, OPENSSL_ALGO_MD5);
            $verification_result['openssl_verify_result'] = $verify_result;
            $verification_result['openssl_verify_meaning'] = $verify_result === 1 ? '验证成功' : ($verify_result === 0 ? '验证失败' : '验证错误');
            
            // 获取OpenSSL错误
            $openssl_errors = array();
            while ($error = openssl_error_string()) {
                $openssl_errors[] = $error;
            }
            $verification_result['openssl_errors'] = $openssl_errors;
            
            openssl_pkey_free($public_key_resource);
        }
        
        // 使用我们的类进行验证
        $class_verify_result = OnePay_Signature::verify($content_to_verify, $signature_to_verify, $gateway->platform_public_key);
        $verification_result['class_verify_result'] = $class_verify_result;
        
    } catch (Exception $e) {
        $verification_result['exception'] = $e->getMessage();
    }
}

// 测试双密钥机制
$dual_key_test_results = array();

// 测试1: 商户私钥签名，商户公钥验证（这应该成功）
if (!empty($gateway->private_key)) {
    try {
        // 提取商户公钥
        $private_key_resource = openssl_pkey_get_private($gateway->private_key);
        if ($private_key_resource) {
            $key_details = openssl_pkey_get_details($private_key_resource);
            $merchant_public_key = $key_details['key'];
            openssl_pkey_free($private_key_resource);
            
            // 用商户私钥签名，商户公钥验证
            $test_content = '{"test":"merchant_key_pair"}';
            $merchant_signature = OnePay_Signature::sign($test_content, $gateway->private_key);
            if ($merchant_signature) {
                $merchant_verify = OnePay_Signature::verify($test_content, $merchant_signature, $merchant_public_key);
                $dual_key_test_results['merchant_key_pair'] = array(
                    'signature_generated' => true,
                    'verification_result' => $merchant_verify,
                    'status' => $merchant_verify ? 'SUCCESS' : 'FAILED'
                );
            } else {
                $dual_key_test_results['merchant_key_pair'] = array(
                    'signature_generated' => false,
                    'status' => 'SIGNATURE_GENERATION_FAILED'
                );
            }
        }
    } catch (Exception $e) {
        $dual_key_test_results['merchant_key_pair'] = array(
            'error' => $e->getMessage(),
            'status' => 'ERROR'
        );
    }
}

// 测试2: 尝试用商户私钥签名，平台公钥验证（这应该失败）
if (!empty($gateway->private_key) && !empty($gateway->platform_public_key)) {
    try {
        $test_content = '{"test":"cross_key_verification"}';
        $merchant_signature = OnePay_Signature::sign($test_content, $gateway->private_key);
        if ($merchant_signature) {
            $cross_verify = OnePay_Signature::verify($test_content, $merchant_signature, $gateway->platform_public_key);
            $dual_key_test_results['cross_key_verification'] = array(
                'signature_generated' => true,
                'verification_result' => $cross_verify,
                'status' => $cross_verify ? 'UNEXPECTED_SUCCESS' : 'EXPECTED_FAILURE',
                'note' => '商户私钥签名不应该能用平台公钥验证'
            );
        }
    } catch (Exception $e) {
        $dual_key_test_results['cross_key_verification'] = array(
            'error' => $e->getMessage(),
            'status' => 'ERROR'
        );
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay回调签名验证调试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        h2 {
            color: #666;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        h3 {
            color: #888;
            margin-top: 25px;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .json-preview {
            font-family: monospace;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            font-size: 12px;
            word-break: break-all;
            max-height: 200px;
            overflow-y: auto;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 OnePay回调签名验证调试工具</h1>
        <p>此工具用于深度调试回调签名验证失败的问题，分析双密钥机制的工作原理。</p>
        
        <!-- 配置检查 -->
        <div class="card">
            <h2>配置检查</h2>
            <table>
                <tr>
                    <th>配置项</th>
                    <th>状态</th>
                    <th>说明</th>
                </tr>
                <tr>
                    <td>商户号</td>
                    <td>
                        <?php if (!empty($gateway->merchant_no)): ?>
                            <span class="status success"><?php echo esc_html($gateway->merchant_no); ?></span>
                        <?php else: ?>
                            <span class="status error">未配置</span>
                        <?php endif; ?>
                    </td>
                    <td>用于标识商户身份</td>
                </tr>
                <tr>
                    <td>商户私钥</td>
                    <td>
                        <?php if (!empty($gateway->private_key)): ?>
                            <span class="status success">已配置</span>
                        <?php else: ?>
                            <span class="status error">未配置</span>
                        <?php endif; ?>
                    </td>
                    <td>用于商户签名请求</td>
                </tr>
                <tr>
                    <td>平台公钥</td>
                    <td>
                        <?php if (!empty($gateway->platform_public_key)): ?>
                            <span class="status success">已配置</span>
                        <?php else: ?>
                            <span class="status warning">未配置</span>
                        <?php endif; ?>
                    </td>
                    <td>用于验证平台回调签名</td>
                </tr>
            </table>
        </div>
        
        <!-- 双密钥机制说明 -->
        <div class="card">
            <h2>双密钥机制说明</h2>
            <div class="alert info">
                <strong>重要：</strong>OnePay使用双密钥机制，商户和平台各有自己的密钥对：
                <ul style="margin-top: 10px;">
                    <li><strong>商户密钥对：</strong>商户私钥用于签名请求，商户公钥提供给平台验证</li>
                    <li><strong>平台密钥对：</strong>平台私钥用于签名回调，平台公钥提供给商户验证</li>
                    <li><strong>回调验证：</strong>应该使用平台公钥验证平台发送的回调签名</li>
                </ul>
            </div>
        </div>
        
        <!-- 密钥对测试 -->
        <div class="card">
            <h2>密钥对测试结果</h2>
            <?php if (!empty($dual_key_test_results)): ?>
                <table>
                    <tr>
                        <th>测试项目</th>
                        <th>结果</th>
                        <th>说明</th>
                    </tr>
                    <?php foreach ($dual_key_test_results as $test_name => $test_result): ?>
                    <tr>
                        <td><?php echo esc_html($test_name); ?></td>
                        <td>
                            <?php 
                            $status_class = 'info';
                            if (isset($test_result['status'])) {
                                switch ($test_result['status']) {
                                    case 'SUCCESS':
                                    case 'EXPECTED_FAILURE':
                                        $status_class = 'success';
                                        break;
                                    case 'FAILED':
                                    case 'ERROR':
                                        $status_class = 'error';
                                        break;
                                    case 'UNEXPECTED_SUCCESS':
                                        $status_class = 'warning';
                                        break;
                                }
                            }
                            ?>
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo esc_html($test_result['status'] ?? 'UNKNOWN'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (isset($test_result['note'])): ?>
                                <?php echo esc_html($test_result['note']); ?>
                            <?php elseif (isset($test_result['error'])): ?>
                                错误: <?php echo esc_html($test_result['error']); ?>
                            <?php else: ?>
                                验证结果: <?php echo isset($test_result['verification_result']) ? ($test_result['verification_result'] ? '通过' : '失败') : 'N/A'; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="alert warning">无法进行密钥对测试，请检查密钥配置。</div>
            <?php endif; ?>
        </div>
        
        <!-- 模拟回调数据 -->
        <div class="card">
            <h2>模拟回调数据生成</h2>
            <?php if ($signature_generated): ?>
                <div class="alert success">✅ 模拟回调数据生成成功</div>
                
                <h3>生成的回调数据:</h3>
                <pre><?php echo htmlspecialchars(json_encode($sample_callback_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                
                <h3>签名内容 (result字段):</h3>
                <div class="json-preview"><?php echo esc_html($sample_callback_data['result']); ?></div>
                
                <h3>生成的签名:</h3>
                <div class="json-preview"><?php echo esc_html($sample_callback_data['sign']); ?></div>
                
            <?php else: ?>
                <div class="alert error">❌ 模拟回调数据生成失败: <?php echo esc_html($signature_error); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- 签名验证测试 -->
        <?php if ($signature_generated && !empty($verification_result)): ?>
        <div class="card">
            <h2>签名验证测试结果</h2>
            
            <table>
                <tr>
                    <th>验证项目</th>
                    <th>结果</th>
                    <th>说明</th>
                </tr>
                <tr>
                    <td>内容长度</td>
                    <td><?php echo $verification_result['content_length']; ?> 字符</td>
                    <td>待验证的内容长度</td>
                </tr>
                <tr>
                    <td>签名长度</td>
                    <td><?php echo $verification_result['signature_length']; ?> 字符</td>
                    <td>Base64编码后的签名长度</td>
                </tr>
                <tr>
                    <td>解码后签名长度</td>
                    <td><?php echo $verification_result['decoded_signature_length']; ?> 字节</td>
                    <td>Base64解码后的二进制签名长度</td>
                </tr>
                <tr>
                    <td>公钥有效性</td>
                    <td>
                        <span class="status <?php echo $verification_result['public_key_valid'] ? 'success' : 'error'; ?>">
                            <?php echo $verification_result['public_key_valid'] ? '有效' : '无效'; ?>
                        </span>
                    </td>
                    <td>平台公钥是否可以正确加载</td>
                </tr>
                <?php if (isset($verification_result['key_type'])): ?>
                <tr>
                    <td>密钥类型</td>
                    <td><?php echo $verification_result['key_type'] == OPENSSL_KEYTYPE_RSA ? 'RSA' : '其他'; ?></td>
                    <td>公钥的加密算法类型</td>
                </tr>
                <tr>
                    <td>密钥位数</td>
                    <td><?php echo $verification_result['key_bits']; ?> bits</td>
                    <td>RSA密钥的位数</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>签名解码</td>
                    <td>
                        <span class="status <?php echo $verification_result['signature_decode_success'] ? 'success' : 'error'; ?>">
                            <?php echo $verification_result['signature_decode_success'] ? '成功' : '失败'; ?>
                        </span>
                    </td>
                    <td>Base64签名解码是否成功</td>
                </tr>
                <?php if (isset($verification_result['openssl_verify_result'])): ?>
                <tr>
                    <td>OpenSSL验证结果</td>
                    <td>
                        <span class="status <?php echo $verification_result['openssl_verify_result'] === 1 ? 'success' : 'error'; ?>">
                            <?php echo $verification_result['openssl_verify_meaning']; ?>
                        </span>
                    </td>
                    <td>原生OpenSSL函数验证结果</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>类方法验证结果</td>
                    <td>
                        <span class="status <?php echo $verification_result['class_verify_result'] ? 'success' : 'error'; ?>">
                            <?php echo $verification_result['class_verify_result'] ? '成功' : '失败'; ?>
                        </span>
                    </td>
                    <td>OnePay_Signature类验证结果</td>
                </tr>
            </table>
            
            <?php if (!empty($verification_result['openssl_errors'])): ?>
            <h3>OpenSSL错误信息:</h3>
            <pre><?php echo htmlspecialchars(implode("\n", $verification_result['openssl_errors'])); ?></pre>
            <?php endif; ?>
            
            <?php if (isset($verification_result['exception'])): ?>
            <div class="alert error">
                <strong>验证异常:</strong> <?php echo esc_html($verification_result['exception']); ?>
            </div>
            <?php endif; ?>
            
            <h3>内容预览 (前100字符):</h3>
            <div class="json-preview"><?php echo esc_html($verification_result['content_preview']); ?></div>
            
            <h3>签名预览 (前50字符):</h3>
            <div class="json-preview"><?php echo esc_html($verification_result['signature_preview']); ?>...</div>
        </div>
        <?php endif; ?>
        
        <!-- 问题诊断 -->
        <div class="card">
            <h2>问题诊断建议</h2>
            <ul>
                <?php if (empty($gateway->merchant_no)): ?>
                    <li>❌ 请配置商户号</li>
                <?php endif; ?>
                
                <?php if (empty($gateway->private_key)): ?>
                    <li>❌ 请配置商户私钥用于生成请求签名</li>
                <?php endif; ?>
                
                <?php if (empty($gateway->platform_public_key)): ?>
                    <li>⚠️ 请配置平台公钥用于验证回调签名（这是验签失败的主要原因）</li>
                <?php endif; ?>
                
                <?php if (!empty($verification_result) && !$verification_result['public_key_valid']): ?>
                    <li>❌ 平台公钥格式错误，请检查公钥内容是否完整</li>
                <?php endif; ?>
                
                <?php if (!empty($verification_result) && $verification_result['openssl_verify_result'] !== 1): ?>
                    <li>❌ 签名验证失败，可能原因：
                        <ul>
                            <li>平台公钥与平台私钥不匹配</li>
                            <li>签名内容在传输过程中被修改</li>
                            <li>签名算法不匹配（应为MD5withRSA）</li>
                        </ul>
                    </li>
                <?php endif; ?>
                
                <?php if (isset($dual_key_test_results['cross_key_verification']) && $dual_key_test_results['cross_key_verification']['verification_result']): ?>
                    <li>⚠️ 检测到商户私钥可以用平台公钥验证，这表明密钥配置可能有误</li>
                <?php endif; ?>
            </ul>
            
            <h3>解决方案：</h3>
            <ol>
                <li>确认从OnePay平台获取的是正确的<strong>平台公钥</strong>（不是商户公钥）</li>
                <li>检查平台公钥的格式是否完整，包含完整的BEGIN和END标记</li>
                <li>确认平台使用的是MD5withRSA签名算法</li>
                <li>联系OnePay技术支持确认密钥配置</li>
            </ol>
        </div>
        
        <!-- 快速链接 -->
        <div class="card">
            <h2>相关工具</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" 
                   style="margin-right: 20px;">⚙️ OnePay设置</a>
                <a href="test-signature.php" style="margin-right: 20px;">🔐 签名测试</a>
                <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>">📋 回调日志</a>
            </p>
        </div>
    </div>
</body>
</html>