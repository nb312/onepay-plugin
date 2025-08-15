<?php
/**
 * OnePay模拟回调测试工具
 * 
 * 模拟发送回调请求到本地回调处理器，测试完整的处理流程
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-mock-callback.php
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

$test_results = array();
$callback_response = '';
$test_executed = false;

// 处理测试请求
if (isset($_POST['run_test']) && isset($_POST['test_data'])) {
    $test_executed = true;
    
    try {
        // 解析测试数据
        $test_data = json_decode($_POST['test_data'], true);
        if (!$test_data) {
            throw new Exception('测试数据JSON格式错误');
        }
        
        // 生成签名（如果配置了私钥）
        if (!empty($gateway->private_key) && isset($test_data['result'])) {
            $signature = OnePay_Signature::sign($test_data['result'], $gateway->private_key);
            if ($signature) {
                $test_data['sign'] = $signature;
                $test_results['signature_generated'] = true;
            } else {
                $test_results['signature_generated'] = false;
                $test_results['signature_error'] = '签名生成失败';
            }
        }
        
        // 准备回调URL
        $callback_url = home_url('/?wc-api=onepay_callback');
        
        // 准备POST数据
        $post_data = json_encode($test_data, JSON_UNESCAPED_UNICODE);
        
        // 发送回调请求
        $test_results['request_url'] = $callback_url;
        $test_results['request_data'] = $post_data;
        $test_results['request_time'] = current_time('mysql');
        
        $response = wp_remote_post($callback_url, array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'OnePay-MockTest/1.0'
            ),
            'body' => $post_data
        ));
        
        if (is_wp_error($response)) {
            $test_results['success'] = false;
            $test_results['error'] = $response->get_error_message();
        } else {
            $test_results['success'] = true;
            $test_results['response_code'] = wp_remote_retrieve_response_code($response);
            $test_results['response_message'] = wp_remote_retrieve_response_message($response);
            $test_results['response_body'] = wp_remote_retrieve_body($response);
            $test_results['response_headers'] = wp_remote_retrieve_headers($response);
        }
        
    } catch (Exception $e) {
        $test_results['success'] = false;
        $test_results['error'] = $e->getMessage();
    }
}

// 获取最近的订单用于测试
$recent_orders = wc_get_orders(array(
    'limit' => 5,
    'status' => array('pending', 'processing', 'on-hold'),
    'orderby' => 'date',
    'order' => 'DESC'
));

// 默认测试数据
$default_test_data = array(
    'merchantNo' => $gateway->merchant_no ?: 'TEST001',
    'result' => json_encode(array(
        'code' => '0000',
        'message' => 'SUCCESS',
        'data' => array(
            'orderNo' => 'OP' . time(),
            'merchantOrderNo' => !empty($recent_orders) ? $recent_orders[0]->get_id() : '123456',
            'orderStatus' => 'SUCCESS',
            'orderAmount' => 10000, // 100元，单位：分
            'paidAmount' => 10000,
            'currency' => '643', // RUB
            'payModel' => 'CARDPAYMENT',
            'payType' => 'CARD',
            'orderTime' => time() * 1000,
            'finishTime' => time() * 1000,
            'remark' => '测试支付'
        )
    ), JSON_UNESCAPED_UNICODE),
    'sign' => '签名将自动生成'
);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay模拟回调测试</title>
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        textarea {
            width: 100%;
            min-height: 300px;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
        }
        textarea:focus {
            border-color: #5469d4;
            outline: none;
        }
        button {
            background: #5469d4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        button:hover {
            background: #4256c7;
        }
        button.secondary {
            background: #6c757d;
        }
        button.secondary:hover {
            background: #5a6268;
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
        .preset-buttons {
            margin: 15px 0;
        }
        .preset-buttons button {
            margin-right: 10px;
            margin-bottom: 10px;
            padding: 8px 16px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 OnePay模拟回调测试工具</h1>
        <p>此工具模拟OnePay平台向您的回调URL发送支付通知，用于测试回调处理逻辑。</p>
        
        <!-- 配置检查 -->
        <div class="card">
            <h2>环境检查</h2>
            <table>
                <tr>
                    <th>检查项目</th>
                    <th>状态</th>
                    <th>说明</th>
                </tr>
                <tr>
                    <td>回调URL</td>
                    <td><span class="status info"><?php echo home_url('/?wc-api=onepay_callback'); ?></span></td>
                    <td>WordPress回调接收地址</td>
                </tr>
                <tr>
                    <td>商户号</td>
                    <td>
                        <?php if (!empty($gateway->merchant_no)): ?>
                            <span class="status success"><?php echo esc_html($gateway->merchant_no); ?></span>
                        <?php else: ?>
                            <span class="status warning">使用默认</span>
                        <?php endif; ?>
                    </td>
                    <td>测试将使用的商户号</td>
                </tr>
                <tr>
                    <td>商户私钥</td>
                    <td>
                        <?php if (!empty($gateway->private_key)): ?>
                            <span class="status success">已配置</span>
                        <?php else: ?>
                            <span class="status warning">未配置</span>
                        <?php endif; ?>
                    </td>
                    <td>用于生成测试签名</td>
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
                    <td>用于验证回调签名</td>
                </tr>
            </table>
            
            <?php if (empty($gateway->private_key)): ?>
            <div class="alert warning">
                <strong>注意：</strong>未配置商户私钥，将无法生成有效签名。测试可能会因为签名验证失败而无法通过。
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 可用订单 -->
        <?php if (!empty($recent_orders)): ?>
        <div class="card">
            <h2>可用于测试的订单</h2>
            <table>
                <tr>
                    <th>订单ID</th>
                    <th>订单号</th>
                    <th>状态</th>
                    <th>金额</th>
                    <th>创建时间</th>
                </tr>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><?php echo $order->get_id(); ?></td>
                    <td><?php echo $order->get_order_number(); ?></td>
                    <td><?php echo $order->get_status(); ?></td>
                    <td><?php echo wc_price($order->get_total()); ?></td>
                    <td><?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- 测试表单 -->
        <div class="card">
            <h2>模拟回调测试</h2>
            
            <form method="post">
                <div class="form-group">
                    <label for="test_data">回调数据 (JSON格式)</label>
                    <div class="preset-buttons">
                        <button type="button" onclick="loadPreset('success')">成功回调</button>
                        <button type="button" onclick="loadPreset('failed')">失败回调</button>
                        <button type="button" onclick="loadPreset('pending')">待处理回调</button>
                        <button type="button" onclick="loadPreset('cancelled')">取消回调</button>
                        <?php if (!empty($recent_orders)): ?>
                        <button type="button" onclick="loadPreset('real_order')">真实订单测试</button>
                        <?php endif; ?>
                    </div>
                    <textarea id="test_data" name="test_data"><?php 
                        echo isset($_POST['test_data']) ? esc_textarea($_POST['test_data']) : 
                             htmlspecialchars(json_encode($default_test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); 
                    ?></textarea>
                    <small style="color: #666;">签名字段(sign)将根据result字段自动生成</small>
                </div>
                
                <button type="submit" name="run_test">🚀 发送测试回调</button>
            </form>
        </div>
        
        <!-- 测试结果 -->
        <?php if ($test_executed): ?>
        <div class="card">
            <h2>测试结果</h2>
            
            <?php if ($test_results['success']): ?>
                <div class="alert success">✅ 回调测试请求发送成功</div>
                
                <table>
                    <tr>
                        <th>项目</th>
                        <th>结果</th>
                    </tr>
                    <tr>
                        <td>请求URL</td>
                        <td><?php echo esc_html($test_results['request_url']); ?></td>
                    </tr>
                    <tr>
                        <td>响应状态码</td>
                        <td>
                            <span class="status <?php echo $test_results['response_code'] == 200 ? 'success' : 'error'; ?>">
                                <?php echo $test_results['response_code']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>响应消息</td>
                        <td><?php echo esc_html($test_results['response_message']); ?></td>
                    </tr>
                    <tr>
                        <td>响应内容</td>
                        <td>
                            <span class="status <?php echo $test_results['response_body'] === 'SUCCESS' ? 'success' : 'error'; ?>">
                                <?php echo esc_html($test_results['response_body']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (isset($test_results['signature_generated'])): ?>
                    <tr>
                        <td>签名生成</td>
                        <td>
                            <span class="status <?php echo $test_results['signature_generated'] ? 'success' : 'error'; ?>">
                                <?php echo $test_results['signature_generated'] ? '成功' : '失败'; ?>
                            </span>
                            <?php if (isset($test_results['signature_error'])): ?>
                                - <?php echo esc_html($test_results['signature_error']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>测试时间</td>
                        <td><?php echo $test_results['request_time']; ?></td>
                    </tr>
                </table>
                
                <h3>发送的数据:</h3>
                <pre><?php echo htmlspecialchars($test_results['request_data']); ?></pre>
                
            <?php else: ?>
                <div class="alert error">❌ 回调测试失败: <?php echo esc_html($test_results['error']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- 使用说明 -->
        <div class="card">
            <h2>使用说明</h2>
            <ol>
                <li>选择预设的回调类型或手动编辑JSON数据</li>
                <li>确保merchantOrderNo字段对应实际存在的订单ID</li>
                <li>点击"发送测试回调"按钮模拟回调</li>
                <li>查看响应结果，SUCCESS表示处理成功</li>
                <li>检查<a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>">回调日志</a>查看详细处理过程</li>
            </ol>
            
            <h3>回调状态说明:</h3>
            <ul>
                <li><strong>SUCCESS:</strong> 支付成功</li>
                <li><strong>PENDING:</strong> 支付处理中</li>
                <li><strong>FAIL/FAILED:</strong> 支付失败</li>
                <li><strong>CANCEL:</strong> 支付取消</li>
                <li><strong>WAIT3D:</strong> 等待3D验证（国际卡）</li>
            </ul>
        </div>
        
        <!-- 相关链接 -->
        <div class="card">
            <h2>相关工具</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>" 
                   style="margin-right: 20px;">📋 查看回调日志</a>
                <a href="test-callback-signature.php" style="margin-right: 20px;">🔐 签名验证测试</a>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>">⚙️ OnePay设置</a>
            </p>
        </div>
    </div>

    <script>
        // 预设数据模板
        const presets = {
            success: {
                merchantNo: '<?php echo $gateway->merchant_no ?: 'TEST001'; ?>',
                result: JSON.stringify({
                    code: '0000',
                    message: 'SUCCESS',
                    data: {
                        orderNo: 'OP' + Date.now(),
                        merchantOrderNo: '<?php echo !empty($recent_orders) ? $recent_orders[0]->get_id() : '123456'; ?>',
                        orderStatus: 'SUCCESS',
                        orderAmount: 10000,
                        paidAmount: 10000,
                        currency: '643',
                        payModel: 'CARDPAYMENT',
                        payType: 'CARD',
                        orderTime: Date.now(),
                        finishTime: Date.now(),
                        remark: '测试成功支付'
                    }
                }, null, 2),
                sign: '签名将自动生成'
            },
            failed: {
                merchantNo: '<?php echo $gateway->merchant_no ?: 'TEST001'; ?>',
                result: JSON.stringify({
                    code: '0000',
                    message: 'SUCCESS',
                    data: {
                        orderNo: 'OP' + Date.now(),
                        merchantOrderNo: '<?php echo !empty($recent_orders) ? $recent_orders[0]->get_id() : '123456'; ?>',
                        orderStatus: 'FAIL',
                        orderAmount: 10000,
                        paidAmount: 0,
                        currency: '643',
                        payModel: 'CARDPAYMENT',
                        payType: 'CARD',
                        orderTime: Date.now(),
                        finishTime: 0,
                        msg: '卡片余额不足',
                        remark: '测试失败支付'
                    }
                }, null, 2),
                sign: '签名将自动生成'
            },
            pending: {
                merchantNo: '<?php echo $gateway->merchant_no ?: 'TEST001'; ?>',
                result: JSON.stringify({
                    code: '0000',
                    message: 'SUCCESS',
                    data: {
                        orderNo: 'OP' + Date.now(),
                        merchantOrderNo: '<?php echo !empty($recent_orders) ? $recent_orders[0]->get_id() : '123456'; ?>',
                        orderStatus: 'PENDING',
                        orderAmount: 10000,
                        paidAmount: 0,
                        currency: '643',
                        payModel: 'FPS',
                        payType: 'SBP',
                        orderTime: Date.now(),
                        finishTime: 0,
                        remark: '测试待处理支付'
                    }
                }, null, 2),
                sign: '签名将自动生成'
            },
            cancelled: {
                merchantNo: '<?php echo $gateway->merchant_no ?: 'TEST001'; ?>',
                result: JSON.stringify({
                    code: '0000',
                    message: 'SUCCESS',
                    data: {
                        orderNo: 'OP' + Date.now(),
                        merchantOrderNo: '<?php echo !empty($recent_orders) ? $recent_orders[0]->get_id() : '123456'; ?>',
                        orderStatus: 'CANCEL',
                        orderAmount: 10000,
                        paidAmount: 0,
                        currency: '643',
                        payModel: 'CARDPAYMENT',
                        payType: 'CARD',
                        orderTime: Date.now(),
                        finishTime: 0,
                        msg: '用户取消支付',
                        remark: '测试取消支付'
                    }
                }, null, 2),
                sign: '签名将自动生成'
            }
            <?php if (!empty($recent_orders)): ?>
            ,
            real_order: {
                merchantNo: '<?php echo $gateway->merchant_no ?: 'TEST001'; ?>',
                result: JSON.stringify({
                    code: '0000',
                    message: 'SUCCESS',
                    data: {
                        orderNo: 'OP' + Date.now(),
                        merchantOrderNo: '<?php echo $recent_orders[0]->get_id(); ?>',
                        orderStatus: 'SUCCESS',
                        orderAmount: <?php echo intval($recent_orders[0]->get_total() * 100); ?>,
                        paidAmount: <?php echo intval($recent_orders[0]->get_total() * 100); ?>,
                        currency: '643',
                        payModel: 'CARDPAYMENT',
                        payType: 'CARD',
                        orderTime: Date.now(),
                        finishTime: Date.now(),
                        remark: '真实订单测试 - 订单<?php echo $recent_orders[0]->get_id(); ?>'
                    }
                }, null, 2),
                sign: '签名将自动生成'
            }
            <?php endif; ?>
        };

        function loadPreset(type) {
            if (presets[type]) {
                document.getElementById('test_data').value = JSON.stringify(presets[type], null, 2);
            }
        }
    </script>
</body>
</html>