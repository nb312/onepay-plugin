<?php
/**
 * OnePay API连接测试工具
 * 
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-api-connection.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 加载必要的类
if (!class_exists('OnePay_API')) {
    require_once __DIR__ . '/includes/class-onepay-api.php';
}

// 执行测试
$test_result = null;
$manual_test_result = null;

if (isset($_POST['test_connection'])) {
    $api_handler = new OnePay_API();
    $test_result = $api_handler->test_connection();
}

if (isset($_POST['manual_test'])) {
    $test_url = sanitize_text_field($_POST['test_url']);
    $test_data = array(
        'merchantNo' => 'TEST',
        'version' => '2.0',
        'content' => json_encode(array('test' => true)),
        'sign' => 'test'
    );
    
    $args = array(
        'method' => 'POST',
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ),
        'body' => json_encode($test_data),
        'sslverify' => false
    );
    
    $response = wp_remote_post($test_url, $args);
    
    if (is_wp_error($response)) {
        $manual_test_result = array(
            'success' => false,
            'message' => $response->get_error_message(),
            'code' => $response->get_error_code()
        );
    } else {
        $manual_test_result = array(
            'success' => true,
            'http_code' => wp_remote_retrieve_response_code($response),
            'body' => wp_remote_retrieve_body($response),
            'headers' => wp_remote_retrieve_headers($response)
        );
    }
}

// 获取当前配置
$gateway = new WC_Gateway_OnePay();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay API连接测试</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .card h2 {
            margin-bottom: 20px;
            color: #1a1f36;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #6c757d;
        }
        
        .status-item.success {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .status-item.warning {
            border-color: #ffc107;
            background: #fff3cd;
        }
        
        .status-item.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .status-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .status-value {
            font-size: 1.1em;
            font-weight: 600;
            color: #1a1f36;
        }
        
        .config-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .config-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .config-table td:first-child {
            font-weight: 600;
            color: #495057;
            width: 200px;
        }
        
        .test-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .test-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .test-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .result-box {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        
        .result-box.success {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .result-box.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .details-list {
            list-style: none;
            margin-top: 15px;
        }
        
        .details-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .details-list li:last-child {
            border-bottom: none;
        }
        
        .code-block {
            background: #1a1f36;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔌 OnePay API连接测试</h1>
            <p>测试和诊断API连接问题</p>
        </div>
        
        <!-- 当前配置 -->
        <div class="card">
            <h2>⚙️ 当前配置</h2>
            <table class="config-table">
                <tr>
                    <td>API URL</td>
                    <td>
                        <code><?php echo esc_html($gateway->api_url); ?></code>
                        <?php if (strpos($gateway->api_url, 'https://') === 0): ?>
                            <span style="color: green;">✅ HTTPS</span>
                        <?php else: ?>
                            <span style="color: orange;">⚠️ HTTP (不安全)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>测试模式</td>
                    <td><?php echo $gateway->testmode ? '✅ 开启' : '❌ 关闭'; ?></td>
                </tr>
                <tr>
                    <td>商户号</td>
                    <td><?php echo !empty($gateway->merchant_no) ? esc_html($gateway->merchant_no) : '<span style="color: red;">❌ 未配置</span>'; ?></td>
                </tr>
                <tr>
                    <td>私钥</td>
                    <td><?php echo !empty($gateway->private_key) ? '✅ 已配置 (' . strlen($gateway->private_key) . ' 字符)' : '<span style="color: red;">❌ 未配置</span>'; ?></td>
                </tr>
                <tr>
                    <td>平台公钥</td>
                    <td><?php echo !empty($gateway->platform_public_key) ? '✅ 已配置 (' . strlen($gateway->platform_public_key) . ' 字符)' : '<span style="color: orange;">⚠️ 未配置（可选）</span>'; ?></td>
                </tr>
            </table>
        </div>
        
        <!-- 连接测试 -->
        <div class="card">
            <h2>🧪 API连接测试</h2>
            
            <?php if (!empty($gateway->api_url)): ?>
                <form method="post">
                    <button type="submit" name="test_connection" class="test-button" id="testBtn">
                        开始测试
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ 请先在OnePay设置中配置API URL
                </div>
            <?php endif; ?>
            
            <?php if ($test_result): ?>
                <div class="result-box <?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                    <h3><?php echo $test_result['message']; ?></h3>
                    
                    <?php if (isset($test_result['results'])): ?>
                        <div class="status-grid" style="margin-top: 20px;">
                            <div class="status-item <?php echo $test_result['results']['api_reachable'] ? 'success' : 'error'; ?>">
                                <div class="status-label">API可达性</div>
                                <div class="status-value"><?php echo $test_result['results']['api_reachable'] ? '✅ 可访问' : '❌ 无法访问'; ?></div>
                            </div>
                            <div class="status-item <?php echo $test_result['results']['url_valid'] ? 'success' : 'error'; ?>">
                                <div class="status-label">URL有效性</div>
                                <div class="status-value"><?php echo $test_result['results']['url_valid'] ? '✅ 有效' : '❌ 无效'; ?></div>
                            </div>
                            <div class="status-item <?php echo $test_result['results']['ssl_enabled'] ? 'success' : 'warning'; ?>">
                                <div class="status-label">SSL加密</div>
                                <div class="status-value"><?php echo $test_result['results']['ssl_enabled'] ? '✅ HTTPS' : '⚠️ HTTP'; ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($test_result['results']['details'])): ?>
                            <h4 style="margin-top: 20px;">详细信息：</h4>
                            <ul class="details-list">
                                <?php foreach ($test_result['results']['details'] as $detail): ?>
                                    <li><?php echo $detail; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (isset($test_result['http_code'])): ?>
                            <div style="margin-top: 20px;">
                                <strong>HTTP响应码：</strong> <?php echo $test_result['http_code']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($test_result['response_preview'])): ?>
                            <div style="margin-top: 20px;">
                                <strong>响应预览：</strong>
                                <div class="code-block">
                                    <?php echo esc_html($test_result['response_preview']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 手动测试 -->
        <div class="card">
            <h2>🔧 手动URL测试</h2>
            <form method="post">
                <div class="input-group">
                    <label for="test_url">测试URL</label>
                    <input type="url" 
                           id="test_url" 
                           name="test_url" 
                           value="<?php echo isset($_POST['test_url']) ? esc_attr($_POST['test_url']) : 'http://110.42.152.219:8083/nh-gateway/v2/card/payment'; ?>"
                           placeholder="输入要测试的API URL">
                </div>
                <button type="submit" name="manual_test" class="test-button">
                    测试此URL
                </button>
            </form>
            
            <?php if ($manual_test_result): ?>
                <div class="result-box <?php echo $manual_test_result['success'] ? 'success' : 'error'; ?>">
                    <?php if ($manual_test_result['success']): ?>
                        <h3>✅ 连接成功</h3>
                        <p><strong>HTTP状态码：</strong> <?php echo $manual_test_result['http_code']; ?></p>
                        <p><strong>响应内容：</strong></p>
                        <div class="code-block">
                            <?php 
                            $body = $manual_test_result['body'];
                            $json = json_decode($body);
                            if ($json) {
                                echo esc_html(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            } else {
                                echo esc_html(substr($body, 0, 500));
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <h3>❌ 连接失败</h3>
                        <p><strong>错误代码：</strong> <?php echo esc_html($manual_test_result['code']); ?></p>
                        <p><strong>错误信息：</strong> <?php echo esc_html($manual_test_result['message']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 故障排除指南 -->
        <div class="card">
            <h2>💡 故障排除指南</h2>
            <div class="alert alert-info">
                <h4>常见问题和解决方案：</h4>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li><strong>连接超时</strong>
                        <ul>
                            <li>检查服务器地址是否正确</li>
                            <li>确认服务器端口（8083）是否开放</li>
                            <li>检查防火墙设置</li>
                        </ul>
                    </li>
                    <li style="margin-top: 10px;"><strong>SSL证书错误</strong>
                        <ul>
                            <li>测试环境可以使用HTTP</li>
                            <li>生产环境必须使用有效的HTTPS证书</li>
                        </ul>
                    </li>
                    <li style="margin-top: 10px;"><strong>404错误</strong>
                        <ul>
                            <li>确认API端点路径正确</li>
                            <li>检查是否为 /nh-gateway/v2/card/payment</li>
                        </ul>
                    </li>
                    <li style="margin-top: 10px;"><strong>认证失败</strong>
                        <ul>
                            <li>检查商户号是否正确</li>
                            <li>确认私钥格式是否正确</li>
                            <li>验证签名算法是否为MD5withRSA</li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <div style="margin-top: 20px;">
                <h4>快速链接：</h4>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" 
                       style="color: #667eea; text-decoration: none; margin-right: 20px;">
                       ⚙️ OnePay设置
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>" 
                       style="color: #667eea; text-decoration: none;">
                       📝 查看日志
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('testBtn')?.addEventListener('click', function() {
            this.innerHTML = '测试中... <span class="spinner"></span>';
            this.disabled = true;
        });
    </script>
</body>
</html>