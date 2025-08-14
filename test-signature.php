<?php
/**
 * OnePay签名测试工具
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-signature.php
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

// 测试数据
$test_content = '{"test":"hello","timestamp":1234567890}';

// 生成签名
$signature = '';
$signature_error = '';
if (!empty($gateway->private_key)) {
    try {
        $signature = OnePay_Signature::sign($test_content, $gateway->private_key);
        if (!$signature) {
            $signature_error = '签名生成失败';
        }
    } catch (Exception $e) {
        $signature_error = $e->getMessage();
    }
} else {
    $signature_error = '私钥未配置';
}

// 验证私钥格式
$private_key_info = array();
if (!empty($gateway->private_key)) {
    $private_key_info['length'] = strlen($gateway->private_key);
    $private_key_info['starts_with'] = substr($gateway->private_key, 0, 30);
    $private_key_info['ends_with'] = substr($gateway->private_key, -30);
    $private_key_info['has_begin_marker'] = strpos($gateway->private_key, '-----BEGIN') !== false;
    $private_key_info['has_end_marker'] = strpos($gateway->private_key, '-----END') !== false;
    
    // 尝试加载私钥
    $key_resource = @openssl_pkey_get_private($gateway->private_key);
    $private_key_info['can_load'] = $key_resource !== false;
    if ($key_resource) {
        $key_details = openssl_pkey_get_details($key_resource);
        $private_key_info['key_type'] = $key_details['type'] ?? 'unknown';
        $private_key_info['key_bits'] = $key_details['bits'] ?? 0;
    }
}

// 验证公钥格式
$public_key_info = array();
if (!empty($gateway->platform_public_key)) {
    $public_key_info['length'] = strlen($gateway->platform_public_key);
    $public_key_info['starts_with'] = substr($gateway->platform_public_key, 0, 30);
    $public_key_info['ends_with'] = substr($gateway->platform_public_key, -30);
    $public_key_info['has_begin_marker'] = strpos($gateway->platform_public_key, '-----BEGIN') !== false;
    $public_key_info['has_end_marker'] = strpos($gateway->platform_public_key, '-----END') !== false;
    
    // 尝试加载公钥
    $key_resource = @openssl_pkey_get_public($gateway->platform_public_key);
    $public_key_info['can_load'] = $key_resource !== false;
    if ($key_resource) {
        $key_details = openssl_pkey_get_details($key_resource);
        $public_key_info['key_type'] = $key_details['type'] ?? 'unknown';
        $public_key_info['key_bits'] = $key_details['bits'] ?? 0;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay签名测试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
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
        }
        h2 {
            color: #666;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
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
        }
        .key-preview {
            font-family: monospace;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            font-size: 12px;
            word-break: break-all;
        }
        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        textarea, input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        button {
            background: #5469d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #4256c7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 OnePay签名测试工具</h1>
        
        <div class="card">
            <h2>配置状态</h2>
            <table>
                <tr>
                    <th>配置项</th>
                    <th>状态</th>
                    <th>值</th>
                </tr>
                <tr>
                    <td>商户号</td>
                    <td>
                        <?php if (!empty($gateway->merchant_no)): ?>
                            <span class="status success">已配置</span>
                        <?php else: ?>
                            <span class="status error">未配置</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($gateway->merchant_no ?: '未设置'); ?></td>
                </tr>
                <tr>
                    <td>API URL</td>
                    <td><span class="status success">已配置</span></td>
                    <td><?php echo esc_html($gateway->api_url); ?></td>
                </tr>
                <tr>
                    <td>测试模式</td>
                    <td>
                        <?php if ($gateway->testmode): ?>
                            <span class="status warning">开启</span>
                        <?php else: ?>
                            <span class="status success">关闭</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $gateway->testmode ? '是' : '否'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>私钥状态</h2>
            <?php if (!empty($private_key_info)): ?>
                <table>
                    <tr>
                        <th>属性</th>
                        <th>值</th>
                    </tr>
                    <tr>
                        <td>密钥长度</td>
                        <td><?php echo $private_key_info['length']; ?> 字符</td>
                    </tr>
                    <tr>
                        <td>包含BEGIN标记</td>
                        <td>
                            <?php if ($private_key_info['has_begin_marker']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>包含END标记</td>
                        <td>
                            <?php if ($private_key_info['has_end_marker']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>可以加载</td>
                        <td>
                            <?php if ($private_key_info['can_load']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (isset($private_key_info['key_type'])): ?>
                    <tr>
                        <td>密钥类型</td>
                        <td><?php echo $private_key_info['key_type'] == OPENSSL_KEYTYPE_RSA ? 'RSA' : '其他'; ?></td>
                    </tr>
                    <tr>
                        <td>密钥位数</td>
                        <td><?php echo $private_key_info['key_bits']; ?> bits</td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>开头预览</td>
                        <td class="key-preview"><?php echo esc_html($private_key_info['starts_with']); ?>...</td>
                    </tr>
                </table>
            <?php else: ?>
                <p class="status error">私钥未配置</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>公钥状态</h2>
            <?php if (!empty($public_key_info)): ?>
                <table>
                    <tr>
                        <th>属性</th>
                        <th>值</th>
                    </tr>
                    <tr>
                        <td>密钥长度</td>
                        <td><?php echo $public_key_info['length']; ?> 字符</td>
                    </tr>
                    <tr>
                        <td>包含BEGIN标记</td>
                        <td>
                            <?php if ($public_key_info['has_begin_marker']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>包含END标记</td>
                        <td>
                            <?php if ($public_key_info['has_end_marker']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>可以加载</td>
                        <td>
                            <?php if ($public_key_info['can_load']): ?>
                                <span class="status success">是</span>
                            <?php else: ?>
                                <span class="status error">否</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (isset($public_key_info['key_type'])): ?>
                    <tr>
                        <td>密钥类型</td>
                        <td><?php echo $public_key_info['key_type'] == OPENSSL_KEYTYPE_RSA ? 'RSA' : '其他'; ?></td>
                    </tr>
                    <tr>
                        <td>密钥位数</td>
                        <td><?php echo $public_key_info['key_bits']; ?> bits</td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>开头预览</td>
                        <td class="key-preview"><?php echo esc_html($public_key_info['starts_with']); ?>...</td>
                    </tr>
                </table>
            <?php else: ?>
                <p class="status warning">公钥未配置（可选）</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>签名测试</h2>
            <h3>测试数据</h3>
            <pre><?php echo esc_html($test_content); ?></pre>
            
            <h3>生成的签名</h3>
            <?php if ($signature): ?>
                <pre style="color: #6a9955;"><?php echo esc_html($signature); ?></pre>
                <p class="status success">签名生成成功</p>
            <?php else: ?>
                <p class="status error">签名生成失败: <?php echo esc_html($signature_error); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>手动签名测试</h2>
            <div class="test-form">
                <form method="post">
                    <div class="form-group">
                        <label for="test_data">测试数据（JSON格式）</label>
                        <textarea id="test_data" name="test_data" rows="5"><?php 
                            echo isset($_POST['test_data']) ? esc_textarea($_POST['test_data']) : '{"orderNo":"TEST123","amount":"100.00","timestamp":' . time() . '}'; 
                        ?></textarea>
                    </div>
                    <button type="submit" name="generate_signature">生成签名</button>
                </form>
                
                <?php if (isset($_POST['generate_signature']) && isset($_POST['test_data'])): ?>
                    <?php
                    $test_data = $_POST['test_data'];
                    $test_signature = '';
                    $test_error = '';
                    
                    // 验证JSON格式
                    $json_test = json_decode($test_data);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $test_error = 'JSON格式错误: ' . json_last_error_msg();
                    } elseif (!empty($gateway->private_key)) {
                        try {
                            $test_signature = OnePay_Signature::sign($test_data, $gateway->private_key);
                            if (!$test_signature) {
                                $test_error = '签名生成失败';
                            }
                        } catch (Exception $e) {
                            $test_error = $e->getMessage();
                        }
                    } else {
                        $test_error = '私钥未配置';
                    }
                    ?>
                    
                    <div style="margin-top: 20px;">
                        <h3>测试结果</h3>
                        <?php if ($test_signature): ?>
                            <p class="status success">签名生成成功</p>
                            <h4>生成的签名:</h4>
                            <pre style="color: #6a9955;"><?php echo esc_html($test_signature); ?></pre>
                            
                            <h4>完整请求体示例:</h4>
                            <pre><?php 
                                $request_example = array(
                                    'merchantNo' => $gateway->merchant_no ?: 'TEST001',
                                    'version' => '2.0',
                                    'content' => $test_data,
                                    'sign' => $test_signature
                                );
                                echo htmlspecialchars(json_encode($request_example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            ?></pre>
                        <?php else: ?>
                            <p class="status error">错误: <?php echo esc_html($test_error); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <h2>诊断建议</h2>
            <ul>
                <?php if (empty($gateway->merchant_no)): ?>
                    <li>❌ 请配置商户号</li>
                <?php endif; ?>
                
                <?php if (empty($gateway->private_key)): ?>
                    <li>❌ 请配置私钥用于签名生成</li>
                <?php elseif (!$private_key_info['can_load']): ?>
                    <li>❌ 私钥格式错误，请检查：
                        <ul>
                            <li>确保包含完整的 -----BEGIN PRIVATE KEY----- 和 -----END PRIVATE KEY----- 标记</li>
                            <li>确保密钥内容没有被截断</li>
                            <li>确保是有效的RSA私钥</li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li>✅ 私钥配置正确</li>
                <?php endif; ?>
                
                <?php if (!empty($public_key_info) && !$public_key_info['can_load']): ?>
                    <li>⚠️ 公钥格式错误（用于验证响应签名）</li>
                <?php elseif (!empty($public_key_info)): ?>
                    <li>✅ 公钥配置正确</li>
                <?php endif; ?>
                
                <?php if ($signature): ?>
                    <li>✅ 签名生成功能正常</li>
                <?php endif; ?>
            </ul>
            
            <h3>快速链接</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" 
                   style="margin-right: 20px;">⚙️ OnePay设置</a>
                <a href="test-api-connection.php" style="margin-right: 20px;">🔌 API连接测试</a>
                <a href="debug-payment.php">🔍 支付调试</a>
            </p>
        </div>
    </div>
</body>
</html>