<?php
/**
 * 全面的回调功能测试和问题诊断工具
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
    wp_die('权限不足');
}

// 加载必要的类
require_once __DIR__ . '/includes/class-onepay-debug-logger.php';
require_once __DIR__ . '/includes/class-wc-gateway-onepay.php';

$debug_logger = OnePay_Debug_Logger::get_instance();
$gateway = new WC_Gateway_OnePay();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>OnePay回调功能全面测试</title>";
echo "<style>";
echo "body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:20px;background:#f0f0f1;}";
echo ".container{background:white;padding:30px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:20px;}";
echo "h1{color:#1d2327;border-bottom:2px solid #0073aa;padding-bottom:10px;}";
echo "h2{color:#135e96;margin-top:30px;}";
echo ".info-box{background:#e8f4fd;border:1px solid #72aee6;padding:15px;border-radius:4px;margin:15px 0;}";
echo ".success-box{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:4px;margin:15px 0;}";
echo ".warning-box{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:4px;margin:15px 0;}";
echo ".error-box{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:4px;margin:15px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f9f9f9;}";
echo ".button{background:#0073aa;color:white;border:none;padding:8px 15px;border-radius:4px;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px;}";
echo "pre{background:#f5f5f5;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;max-height:300px;overflow-y:auto;}";
echo ".status-success{color:#155724;font-weight:bold;} .status-error{color:#721c24;font-weight:bold;} .status-warning{color:#856404;font-weight:bold;}";
echo "</style>";
echo "</head><body>";

echo "<div class='container'>";
echo "<h1>🔍 OnePay回调功能全面测试和诊断</h1>";

// 1. 配置检查
echo "<h2>1. 配置检查</h2>";
$settings = get_option('woocommerce_onepay_settings', array());

echo "<table>";
echo "<tr><th>配置项</th><th>状态</th><th>值/说明</th></tr>";

$debug_enabled = $settings['debug'] === 'yes';
echo "<tr><td>调试模式</td><td class='" . ($debug_enabled ? 'status-success' : 'status-error') . "'>" . ($debug_enabled ? '✅ 已启用' : '❌ 未启用') . "</td><td>" . ($debug_enabled ? '日志将被记录' : '⚠️ 没有调试日志') . "</td></tr>";

$merchant_no = !empty($settings['merchant_no']);
echo "<tr><td>商户号</td><td class='" . ($merchant_no ? 'status-success' : 'status-error') . "'>" . ($merchant_no ? '✅ 已配置' : '❌ 未配置') . "</td><td>" . ($merchant_no ? esc_html($settings['merchant_no']) : '需要配置') . "</td></tr>";

$private_key = !empty($settings['private_key']);
echo "<tr><td>私钥</td><td class='" . ($private_key ? 'status-success' : 'status-error') . "'>" . ($private_key ? '✅ 已配置' : '❌ 未配置') . "</td><td>" . ($private_key ? '长度: ' . strlen($settings['private_key']) . ' 字符' : '需要配置') . "</td></tr>";

$public_key = !empty($settings['platform_public_key']);
echo "<tr><td>平台公钥</td><td class='" . ($public_key ? 'status-success' : 'status-warning') . "'>" . ($public_key ? '✅ 已配置' : '⚠️ 未配置') . "</td><td>" . ($public_key ? '长度: ' . strlen($settings['platform_public_key']) . ' 字符' : '签名验证将被跳过') . "</td></tr>";

echo "</table>";

if (!$debug_enabled) {
    echo "<div class='error-box'>";
    echo "<strong>⚠️ 调试模式未启用!</strong><br>";
    echo "这是导致没有回调日志的主要原因。请到OnePay设置中启用调试模式。";
    echo "</div>";
}

// 2. 数据库检查
echo "<h2>2. 数据库状态检查</h2>";
global $wpdb;
$table_name = $wpdb->prefix . 'onepay_debug_logs';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

if ($table_exists) {
    echo "<div class='success-box'>✅ 日志表存在</div>";
    
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $callback_logs = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE log_type = %s", 'callback'));
    $recent_callback_logs = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE log_type = %s AND log_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)", 'callback'));
    
    echo "<table>";
    echo "<tr><th>统计项</th><th>数量</th></tr>";
    echo "<tr><td>总日志数</td><td>{$total_logs}</td></tr>";
    echo "<tr><td>回调日志数</td><td>{$callback_logs}</td></tr>";
    echo "<tr><td>最近24小时回调</td><td>{$recent_callback_logs}</td></tr>";
    echo "</table>";
    
    if ($callback_logs == 0) {
        echo "<div class='warning-box'>⚠️ 没有回调日志记录，可能原因：<br>1. 调试模式未启用<br>2. 还没有收到过回调<br>3. 回调处理出现问题</div>";
    }
} else {
    echo "<div class='error-box'>❌ 日志表不存在，调试日志器可能未正确初始化</div>";
}

// 3. 最近回调分析（如果存在）
if ($table_exists && $callback_logs > 0) {
    echo "<h2>3. 最近回调数据分析</h2>";
    
    $recent_callbacks = $wpdb->get_results(
        "SELECT * FROM {$table_name} WHERE log_type = 'callback' ORDER BY log_time DESC LIMIT 3"
    );
    
    foreach ($recent_callbacks as $i => $callback) {
        echo "<h3>回调记录 #" . ($i + 1) . " (ID: {$callback->id})</h3>";
        
        echo "<table>";
        echo "<tr><th>字段</th><th>当前值</th><th>状态</th></tr>";
        
        // 检查关键字段
        $checks = [
            'log_time' => ['值' => $callback->log_time, '说明' => '时间记录'],
            'order_number' => ['值' => $callback->order_number, '说明' => 'OnePay订单号'],
            'amount' => ['值' => $callback->amount, '说明' => '金额(元)'],
            'currency' => ['值' => $callback->currency, '说明' => '货币'],
            'execution_time' => ['值' => $callback->execution_time, '说明' => '执行时间(秒)'],
            'status' => ['值' => $callback->status, '说明' => '处理状态'],
            'response_code' => ['值' => $callback->response_code, '说明' => '响应码/订单状态']
        ];
        
        foreach ($checks as $field => $info) {
            $value = $info['值'];
            $has_value = !empty($value);
            $status_class = $has_value ? 'status-success' : 'status-error';
            $status_text = $has_value ? '✅ 有值' : '❌ 空值';
            
            echo "<tr>";
            echo "<td><strong>{$info['说明']}</strong><br><small>{$field}</small></td>";
            echo "<td>" . esc_html($value ?: '(空)') . "</td>";
            echo "<td class='{$status_class}'>{$status_text}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 分析extra_data
        if (!empty($callback->extra_data)) {
            echo "<h4>额外数据内容:</h4>";
            $extra_data = json_decode($callback->extra_data, true);
            if ($extra_data) {
                echo "<table>";
                echo "<tr><th>字段</th><th>值</th></tr>";
                foreach ($extra_data as $key => $val) {
                    echo "<tr><td>{$key}</td><td>" . esc_html(is_array($val) ? json_encode($val) : $val) . "</td></tr>";
                }
                echo "</table>";
            }
        }
        
        // 分析request_data
        if (!empty($callback->request_data)) {
            echo "<h4>原始回调数据:</h4>";
            $request_json = json_decode($callback->request_data, true);
            if ($request_json && isset($request_json['result'])) {
                $result_data = json_decode($request_json['result'], true);
                if ($result_data && isset($result_data['data'])) {
                    echo "<strong>API返回的订单数据:</strong>";
                    echo "<pre>" . json_encode($result_data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "<div class='error-box'>❌ 无法解析result.data</div>";
                }
            } else {
                echo "<div class='error-box'>❌ 无法解析回调数据结构</div>";
            }
        } else {
            echo "<div class='error-box'>❌ 没有原始回调数据</div>";
        }
        
        echo "<hr>";
    }
}

// 4. 创建测试回调数据
echo "<h2>4. 测试回调数据处理</h2>";
echo "<div class='info-box'>";
echo "<strong>测试说明:</strong> 我们将模拟一个回调数据，测试数据解析和存储是否正常工作。";
echo "</div>";

// 模拟回调数据
$test_callback_data = array(
    'merchantNo' => $settings['merchant_no'] ?: 'TEST001',
    'result' => json_encode(array(
        'code' => '0000',
        'message' => 'success',
        'data' => array(
            'orderNo' => 'OP' . time(),
            'merchantOrderNo' => '测试订单' . time(),
            'orderStatus' => 'SUCCESS',
            'orderAmount' => 10000, // 100元，单位分
            'paidAmount' => 10000,
            'orderFee' => 30, // 0.30元手续费
            'currency' => 'CNY',
            'payModel' => 'FPS',
            'orderTime' => time() * 1000,
            'finishTime' => time() * 1000
        )
    )),
    'sign' => 'test_signature_' . time()
);

echo "<h3>模拟回调数据:</h3>";
echo "<pre>" . json_encode($test_callback_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// 测试数据解析
echo "<h3>测试数据解析结果:</h3>";
try {
    $test_execution_time = 0.123; // 123毫秒
    $debug_logger->log_callback_processed($test_callback_data, 'SUCCESS', '测试回调处理成功', $test_execution_time, null);
    
    echo "<div class='success-box'>✅ 测试数据已成功写入日志</div>";
    
    // 获取刚写入的数据
    $latest_test = $wpdb->get_row(
        "SELECT * FROM {$table_name} WHERE log_type = 'callback' ORDER BY id DESC LIMIT 1"
    );
    
    if ($latest_test) {
        echo "<h4>写入结果验证:</h4>";
        echo "<table>";
        echo "<tr><th>字段</th><th>写入值</th><th>状态</th></tr>";
        
        $test_checks = [
            'order_number' => $latest_test->order_number,
            'amount' => $latest_test->amount,
            'currency' => $latest_test->currency,
            'execution_time' => $latest_test->execution_time,
            'status' => $latest_test->status,
            'response_code' => $latest_test->response_code
        ];
        
        foreach ($test_checks as $field => $value) {
            $has_value = !empty($value);
            $status_class = $has_value ? 'status-success' : 'status-error';
            $status_text = $has_value ? '✅ 正确写入' : '❌ 写入失败';
            
            echo "<tr>";
            echo "<td>{$field}</td>";
            echo "<td>" . esc_html($value ?: '(空)') . "</td>";
            echo "<td class='{$status_class}'>{$status_text}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='error-box'>❌ 测试失败: " . esc_html($e->getMessage()) . "</div>";
}

// 5. 显示逻辑测试
echo "<h2>5. 显示逻辑测试</h2>";
echo "<div class='info-box'>测试OnePay设置页面的回调显示功能</div>";

echo "<h3>当前显示的回调记录:</h3>";
ob_start();
$gateway->render_callback_logs($debug_logger);
$display_output = ob_get_clean();

if (!empty($display_output)) {
    echo "<div style='border:1px solid #ddd;padding:15px;background:#fafafa;'>";
    echo $display_output;
    echo "</div>";
} else {
    echo "<div class='error-box'>❌ 显示输出为空</div>";
}

// 6. 问题诊断和建议
echo "<h2>6. 问题诊断和修复建议</h2>";

$issues = array();
$suggestions = array();

if (!$debug_enabled) {
    $issues[] = "调试模式未启用";
    $suggestions[] = "到 WooCommerce > 设置 > 支付 > OnePay 中启用调试模式";
}

if ($callback_logs == 0) {
    $issues[] = "没有回调日志记录";
    $suggestions[] = "确保调试模式已启用，并触发一次支付回调测试";
}

if (!$public_key) {
    $issues[] = "平台公钥未配置";
    $suggestions[] = "配置平台公钥以确保签名验证正常工作";
}

if (!empty($issues)) {
    echo "<div class='warning-box'>";
    echo "<strong>发现的问题:</strong><ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul></div>";
    
    echo "<div class='info-box'>";
    echo "<strong>修复建议:</strong><ul>";
    foreach ($suggestions as $suggestion) {
        echo "<li>{$suggestion}</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div class='success-box'>✅ 配置和功能检查通过，回调功能应该正常工作</div>";
}

echo "</div>"; // container

echo "<div class='container'>";
echo "<h2>🔧 快速操作</h2>";
echo "<p>";
echo "<a href='" . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . "' class='button'>OnePay设置</a> ";
echo "<a href='test-callback.php' class='button'>回调测试工具</a> ";
echo "<a href='debug-logs-simple.php' class='button'>查看调试日志</a> ";
echo "<a href='debug-callback-data.php' class='button'>数据库分析</a>";
echo "</p>";
echo "</div>";

echo "</body></html>";
?>