<?php
/**
 * OnePay回调测试工具
 * 
 * 用于模拟OnePay服务器发送的各种状态回调，测试回调处理逻辑
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

// 检查权限
if (!current_user_can('manage_woocommerce')) {
    wp_die('您没有权限访问此页面');
}

// 加载OnePay插件类
require_once __DIR__ . '/includes/class-onepay-callback.php';
require_once __DIR__ . '/includes/class-onepay-signature.php';
require_once __DIR__ . '/includes/class-wc-gateway-onepay.php';

$gateway = new WC_Gateway_OnePay();
$callback_handler = new OnePay_Callback();

// 处理测试请求
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'test_callback') {
    $test_order_id = intval($_POST['test_order_id']);
    $test_status = sanitize_text_field($_POST['test_status']);
    $test_amount = floatval($_POST['test_amount']);
    
    // 验证订单存在
    $order = wc_get_order($test_order_id);
    if (!$order) {
        $error_message = '订单不存在: ' . $test_order_id;
    } else {
        // 生成测试回调数据
        $test_result = generate_test_callback($order, $test_status, $test_amount, $gateway);
        
        // 模拟回调处理
        $_POST = array(); // 清空POST数据，模拟实际回调环境
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        // 将测试数据写入php://input的模拟
        file_put_contents('php://temp', json_encode($test_result));
        
        // 重定向php://input读取
        $GLOBALS['test_callback_data'] = json_encode($test_result);
        
        ob_start();
        try {
            // 模拟回调处理
            simulate_callback_processing($test_result, $callback_handler);
            $success_message = '回调测试成功完成！订单状态已更新。';
        } catch (Exception $e) {
            $error_message = '回调测试失败: ' . $e->getMessage();
        }
        ob_end_clean();
    }
}

/**
 * 生成测试回调数据
 */
function generate_test_callback($order, $status, $amount, $gateway) {
    $onepay_order_no = $order->get_meta('_onepay_order_no') ?: 'TEST_' . $order->get_id() . '_' . time();
    
    // 根据状态生成相应的回调数据
    $payment_data = array(
        'orderNo' => $onepay_order_no,
        'merchantOrderNo' => (string)$order->get_id(),
        'orderStatus' => $status,
        'currency' => $order->get_currency(),
        'orderAmount' => $amount * 100, // 转换为分
        'createTime' => date('Y-m-d H:i:s'),
        'payTime' => date('Y-m-d H:i:s'),
        'payModel' => 'CARDPAYMENT'
    );
    
    // 根据不同状态添加特定字段
    switch ($status) {
        case 'SUCCESS':
            $payment_data['paidAmount'] = $amount * 100;
            $payment_data['orderFee'] = round($amount * 0.03 * 100); // 3% 手续费
            break;
            
        case 'FAIL':
            $payment_data['msg'] = '支付失败，银行卡余额不足';
            $payment_data['errorCode'] = 'INSUFFICIENT_FUNDS';
            break;
            
        case 'CANCEL':
            $payment_data['msg'] = '用户取消支付';
            break;
            
        case 'WAIT3D':
            $payment_data['redirect3DUrl'] = 'https://3ds.example.com/challenge?token=abc123';
            $payment_data['threeDSecureFlow'] = 'CHALLENGE_REQUIRED';
            break;
    }
    
    $result_data = array(
        'code' => '0000',
        'message' => 'SUCCESS',
        'data' => $payment_data
    );
    
    $result_json = json_encode($result_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    // 生成签名（如果配置了私钥）
    $signature = '';
    if (!empty($gateway->private_key)) {
        $signature = OnePay_Signature::sign($result_json, $gateway->private_key);
    }
    
    return array(
        'merchantNo' => $gateway->merchant_no ?: 'TEST001',
        'result' => $result_json,
        'sign' => $signature
    );
}

/**
 * 模拟回调处理
 */
function simulate_callback_processing($callback_data, $callback_handler) {
    // 重写file_get_contents('php://input')
    $original_input = function_exists('stream_wrapper_restore') ? 
        stream_get_contents(fopen('php://input', 'r')) : '';
    
    // 创建临时输入流
    $temp_file = tmpfile();
    fwrite($temp_file, json_encode($callback_data));
    rewind($temp_file);
    
    // 替换全局变量模拟输入
    $GLOBALS['php_input_override'] = json_encode($callback_data);
    
    // 处理回调
    $callback_handler->process_callback();
}

/**
 * 获取最近订单用于测试
 */
function get_recent_orders($limit = 10) {
    return wc_get_orders(array(
        'limit' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array('pending', 'processing', 'on-hold'),
        'meta_query' => array(
            array(
                'key' => '_payment_method',
                'value' => array('onepay', 'onepay_fps', 'onepay_russian_card', 'onepay_cards'),
                'compare' => 'IN'
            )
        )
    ));
}

$recent_orders = get_recent_orders();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay回调测试工具</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f0f0f1;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .section h2 {
            margin-top: 0;
            color: #333;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            max-width: 300px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .button:hover {
            background: #005a87;
        }
        
        .button-secondary {
            background: #f0f0f1;
            color: #2c3338;
            border: 1px solid #8c8f94;
        }
        
        .button-secondary:hover {
            background: #e1e1e1;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .orders-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-fail { background: #f8d7da; color: #721c24; }
        .status-cancel { background: #e2e3e5; color: #495057; }
        .status-wait3d { background: #cce5ff; color: #004085; }
        
        .callback-preview {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .info-box {
            background: #e8f4fd;
            border: 1px solid #72aee6;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 OnePay回调测试工具</h1>
        
        <div class="info-box">
            <strong>使用说明：</strong> 此工具用于模拟OnePay服务器发送的各种状态回调，测试订单状态更新逻辑。请确保已启用调试模式以查看详细日志。
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo esc_html($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2>📋 最近的OnePay订单</h2>
            
            <?php if (empty($recent_orders)): ?>
                <p>没有找到使用OnePay支付方式的订单。请先创建一些测试订单。</p>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>订单ID</th>
                            <th>订单号</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>支付方式</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><?php echo $order->get_id(); ?></td>
                                <td><?php echo $order->get_order_number(); ?></td>
                                <td>¥<?php echo $order->get_total(); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($order->get_status()); ?>">
                                        <?php echo esc_html($order->get_status()); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
                                <td><?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></td>
                                <td>
                                    <button type="button" class="button button-secondary select-order" 
                                            data-id="<?php echo $order->get_id(); ?>"
                                            data-amount="<?php echo $order->get_total(); ?>">
                                        选择测试
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>⚡ 回调测试</h2>
            
            <form method="post" id="callback-test-form">
                <input type="hidden" name="action" value="test_callback">
                
                <div class="form-group">
                    <label for="test_order_id">测试订单ID:</label>
                    <input type="number" id="test_order_id" name="test_order_id" required 
                           placeholder="请先从上方选择一个订单">
                </div>
                
                <div class="form-group">
                    <label for="test_status">回调状态:</label>
                    <select id="test_status" name="test_status" required>
                        <option value="">请选择状态</option>
                        <option value="SUCCESS">SUCCESS - 支付成功</option>
                        <option value="PENDING">PENDING - 待付款</option>
                        <option value="FAIL">FAIL - 支付失败</option>
                        <option value="CANCEL">CANCEL - 支付取消</option>
                        <option value="WAIT3D">WAIT3D - 等待3D验证</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="test_amount">支付金额:</label>
                    <input type="number" id="test_amount" name="test_amount" step="0.01" required 
                           placeholder="自动填入订单金额">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="button">🚀 发送测试回调</button>
                    <button type="button" class="button button-secondary" id="preview-callback">👁️ 预览回调数据</button>
                </div>
            </form>
            
            <div id="callback-preview-container" style="display: none;">
                <h3>回调数据预览:</h3>
                <div class="callback-preview" id="callback-preview"></div>
            </div>
        </div>
        
        <div class="section">
            <h2>📊 快速测试场景</h2>
            <p>点击下方按钮快速测试常见场景：</p>
            
            <button type="button" class="button test-scenario" data-status="SUCCESS">✅ 测试支付成功</button>
            <button type="button" class="button test-scenario" data-status="FAIL">❌ 测试支付失败</button>
            <button type="button" class="button test-scenario" data-status="CANCEL">🚫 测试支付取消</button>
            <button type="button" class="button test-scenario" data-status="WAIT3D">🔒 测试3D验证</button>
        </div>
        
        <div class="section">
            <h2>🔍 调试信息</h2>
            <p><strong>回调URL:</strong> <code><?php echo add_query_arg('wc-api', 'onepay_callback', home_url('/')); ?></code></p>
            <p><strong>商户号:</strong> <code><?php echo esc_html($gateway->merchant_no ?: '未配置'); ?></code></p>
            <p><strong>调试模式:</strong> <code><?php echo $gateway->debug ? '已启用' : '未启用'; ?></code></p>
            <p><strong>测试模式:</strong> <code><?php echo $gateway->testmode ? '已启用' : '未启用'; ?></code></p>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" class="button button-secondary">
                    ⚙️ 前往OnePay设置
                </a>
                <a href="<?php echo admin_url('admin.php?page=onepay-debug-logs'); ?>" class="button button-secondary">
                    📋 查看调试日志
                </a>
            </p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 选择订单
            document.querySelectorAll('.select-order').forEach(function(button) {
                button.addEventListener('click', function() {
                    const orderId = this.dataset.id;
                    const amount = this.dataset.amount;
                    
                    document.getElementById('test_order_id').value = orderId;
                    document.getElementById('test_amount').value = amount;
                    
                    // 高亮选中的行
                    document.querySelectorAll('.orders-table tr').forEach(function(row) {
                        row.style.backgroundColor = '';
                    });
                    this.closest('tr').style.backgroundColor = '#e8f4fd';
                });
            });
            
            // 快速场景测试
            document.querySelectorAll('.test-scenario').forEach(function(button) {
                button.addEventListener('click', function() {
                    const status = this.dataset.status;
                    const orderId = document.getElementById('test_order_id').value;
                    
                    if (!orderId) {
                        alert('请先选择一个订单');
                        return;
                    }
                    
                    document.getElementById('test_status').value = status;
                    document.getElementById('callback-test-form').submit();
                });
            });
            
            // 预览回调数据
            document.getElementById('preview-callback').addEventListener('click', function() {
                const orderId = document.getElementById('test_order_id').value;
                const status = document.getElementById('test_status').value;
                const amount = document.getElementById('test_amount').value;
                
                if (!orderId || !status || !amount) {
                    alert('请填写完整的测试参数');
                    return;
                }
                
                // 这里可以通过AJAX获取预览数据，简化处理直接显示格式
                const previewData = {
                    merchantNo: '<?php echo esc_js($gateway->merchant_no ?: 'TEST001'); ?>',
                    result: JSON.stringify({
                        code: '0000',
                        message: 'SUCCESS',
                        data: {
                            orderNo: 'TEST_' + orderId + '_' + Date.now(),
                            merchantOrderNo: orderId,
                            orderStatus: status,
                            orderAmount: Math.round(parseFloat(amount) * 100),
                            currency: 'CNY',
                            createTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                            payTime: new Date().toISOString().slice(0, 19).replace('T', ' ')
                        }
                    }, null, 2),
                    sign: 'test_signature_here'
                };
                
                document.getElementById('callback-preview').textContent = JSON.stringify(previewData, null, 2);
                document.getElementById('callback-preview-container').style.display = 'block';
            });
        });
    </script>
</body>
</html>