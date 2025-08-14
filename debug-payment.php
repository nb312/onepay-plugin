<?php
/**
 * OnePay 支付调试工具
 * 
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/debug-payment.php
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

$test_result = null;

if (isset($_POST['test_payment'])) {
    // 创建测试订单数据
    $test_order = new stdClass();
    $test_order->id = 999999;
    $test_order->total = floatval($_POST['amount']);
    
    // 创建模拟订单对象
    class Test_Order {
        private $id;
        private $total;
        private $meta_data = array();
        
        public function __construct($id, $total) {
            $this->id = $id;
            $this->total = $total;
        }
        
        public function get_id() {
            return $this->id;
        }
        
        public function get_order_number() {
            return 'TEST_' . $this->id;
        }
        
        public function get_total() {
            return $this->total;
        }
        
        public function get_customer_id() {
            return 1;
        }
        
        public function get_items() {
            return array();
        }
        
        public function update_meta_data($key, $value) {
            $this->meta_data[$key] = $value;
        }
        
        public function save() {
            // 模拟保存
        }
    }
    
    $order = new Test_Order(999999, floatval($_POST['amount']));
    $payment_method = $_POST['payment_method'];
    
    // 执行API请求
    $api_handler = new OnePay_API();
    $test_result = $api_handler->create_payment_request($order, $payment_method);
}

// 获取最近的日志
$log_content = '';
$log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
if (is_dir($log_dir)) {
    $files = glob($log_dir . 'onepay-*.log');
    if (!empty($files)) {
        $latest_log = end($files);
        $log_content = file_get_contents($latest_log);
        // 只获取最后50行
        $lines = explode("\n", $log_content);
        $lines = array_slice($lines, -50);
        $log_content = implode("\n", $lines);
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay 支付调试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
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
            margin-top: 0;
        }
        
        h2 {
            color: #666;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button {
            background: #5469d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }
        
        button:hover {
            background: #4256c7;
        }
        
        .result-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .result-box.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .result-box.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .result-box h3 {
            margin-top: 0;
            color: #333;
        }
        
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        
        .json-key {
            color: #9cdcfe;
        }
        
        .json-value {
            color: #ce9178;
        }
        
        .json-null {
            color: #569cd6;
        }
        
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .log-error {
            color: #f48771;
        }
        
        .log-warning {
            color: #dcdcaa;
        }
        
        .log-info {
            color: #9cdcfe;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 10px 20px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            position: relative;
            font-size: 14px;
        }
        
        .tab.active {
            color: #5469d4;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #5469d4;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 OnePay 支付调试工具</h1>
        
        <div class="card">
            <h2>配置信息</h2>
            <?php
            $gateway = new WC_Gateway_OnePay();
            ?>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">API URL</div>
                    <div class="info-value"><?php echo esc_html($gateway->api_url); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">商户号</div>
                    <div class="info-value"><?php echo esc_html($gateway->merchant_no ?: '未设置'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">测试模式</div>
                    <div class="info-value"><?php echo $gateway->testmode ? '✅ 开启' : '❌ 关闭'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">调试模式</div>
                    <div class="info-value"><?php echo $gateway->debug ? '✅ 开启' : '❌ 关闭'; ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>测试支付请求</h2>
            <form method="post">
                <div class="form-group">
                    <label for="amount">订单金额</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           value="100" 
                           step="0.01" 
                           min="0.01" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">支付方式</label>
                    <select id="payment_method" name="payment_method">
                        <option value="FPS">FPS (快速支付系统)</option>
                        <option value="CARDPAYMENT">CARDPAYMENT (银行卡)</option>
                    </select>
                </div>
                
                <button type="submit" name="test_payment">发送测试请求</button>
            </form>
            
            <?php if ($test_result): ?>
                <div class="result-box <?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                    <h3><?php echo $test_result['success'] ? '✅ 请求成功' : '❌ 请求失败'; ?></h3>
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('response')">响应数据</button>
                        <button class="tab" onclick="showTab('raw')">原始数据</button>
                        <button class="tab" onclick="showTab('debug')">调试信息</button>
                    </div>
                    
                    <div id="response-tab" class="tab-content active">
                        <h4>响应消息：</h4>
                        <p><?php echo esc_html($test_result['message']); ?></p>
                        
                        <?php if ($test_result['success'] && isset($test_result['data'])): ?>
                            <h4>响应数据：</h4>
                            <pre><?php 
                                echo htmlspecialchars(json_encode($test_result['data'], 
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            ?></pre>
                            
                            <?php if (isset($test_result['data']['webUrl'])): ?>
                                <h4>支付URL：</h4>
                                <p><a href="<?php echo esc_url($test_result['data']['webUrl']); ?>" 
                                      target="_blank"><?php echo esc_html($test_result['data']['webUrl']); ?></a></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (isset($test_result['code'])): ?>
                            <h4>错误代码：</h4>
                            <p><?php echo esc_html($test_result['code']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="raw-tab" class="tab-content">
                        <h4>完整响应：</h4>
                        <pre><?php 
                            echo htmlspecialchars(json_encode($test_result, 
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        ?></pre>
                    </div>
                    
                    <div id="debug-tab" class="tab-content">
                        <?php if (isset($test_result['debug_info'])): ?>
                            <h4>调试信息：</h4>
                            <p><?php echo esc_html($test_result['debug_info']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($test_result['raw_response'])): ?>
                            <h4>原始响应：</h4>
                            <pre><?php echo esc_html($test_result['raw_response']); ?></pre>
                        <?php endif; ?>
                        
                        <?php if (isset($test_result['parsed_result'])): ?>
                            <h4>解析结果：</h4>
                            <pre><?php 
                                echo htmlspecialchars(json_encode($test_result['parsed_result'], 
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>最近日志</h2>
            <div class="log-viewer">
                <?php if ($log_content): ?>
                    <?php 
                    $lines = explode("\n", $log_content);
                    foreach ($lines as $line) {
                        $class = '';
                        if (strpos($line, 'ERROR') !== false) {
                            $class = 'log-error';
                        } elseif (strpos($line, 'WARNING') !== false) {
                            $class = 'log-warning';
                        } elseif (strpos($line, 'INFO') !== false) {
                            $class = 'log-info';
                        }
                        echo '<div class="' . $class . '">' . esc_html($line) . '</div>';
                    }
                    ?>
                <?php else: ?>
                    <p>暂无日志记录</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // 隐藏所有tab内容
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            
            // 移除所有tab的active类
            document.querySelectorAll('.tab').forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            // 显示选中的tab内容
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // 设置选中的tab为active
            event.target.classList.add('active');
        }
    </script>
</body>
</html>