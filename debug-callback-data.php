<?php
/**
 * 深度分析回调数据存储和显示问题
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
    wp_die('权限不足');
}

// 获取数据库中的原始回调数据
global $wpdb;
$table_name = $wpdb->prefix . 'onepay_debug_logs';

// 检查表是否存在
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>回调数据深度分析</title>";
echo "<style>body{font-family:monospace;margin:20px;} .section{margin:20px 0;padding:15px;border:1px solid #ddd;} .error{color:red;} .success{color:green;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;overflow-x:auto;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f9f9f9;}</style>";
echo "</head><body>";

echo "<h1>🔍 OnePay回调数据深度分析</h1>";

if (!$table_exists) {
    echo "<div class='section error'>❌ 数据库表不存在: {$table_name}</div>";
    exit;
}

echo "<div class='section success'>✅ 数据库表存在: {$table_name}</div>";

// 1. 检查表结构
echo "<div class='section'>";
echo "<h2>📊 表结构分析</h2>";
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
echo "<table>";
echo "<tr><th>字段名</th><th>类型</th><th>可空</th><th>键</th><th>默认值</th><th>额外</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col->Field}</td>";
    echo "<td>{$col->Type}</td>";
    echo "<td>{$col->Null}</td>";
    echo "<td>{$col->Key}</td>";
    echo "<td>{$col->Default}</td>";
    echo "<td>{$col->Extra}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 2. 获取最近的回调记录进行详细分析
echo "<div class='section'>";
echo "<h2>📝 最近回调记录分析</h2>";

$recent_callbacks = $wpdb->get_results(
    "SELECT * FROM {$table_name} WHERE log_type = 'callback' ORDER BY log_time DESC LIMIT 5"
);

if (empty($recent_callbacks)) {
    echo "<div class='error'>❌ 没有找到回调记录</div>";
} else {
    echo "<div class='info'>✅ 找到 " . count($recent_callbacks) . " 条回调记录</div>";
    
    foreach ($recent_callbacks as $i => $callback) {
        echo "<h3>回调记录 #" . ($i + 1) . " (ID: {$callback->id})</h3>";
        
        echo "<h4>基本信息:</h4>";
        echo "<table>";
        echo "<tr><th>字段</th><th>原始值</th><th>分析</th></tr>";
        
        // 分析每个字段
        $fields = [
            'id' => '记录ID',
            'log_time' => '日志时间',
            'log_type' => '日志类型', 
            'order_id' => '订单ID',
            'order_number' => '订单号',
            'user_id' => '用户ID',
            'user_name' => '用户名',
            'user_email' => '用户邮箱',
            'user_ip' => '用户IP',
            'amount' => '金额',
            'currency' => '货币',
            'payment_method' => '支付方式',
            'request_url' => '请求URL',
            'response_code' => '响应码',
            'error_message' => '错误信息',
            'execution_time' => '执行时间',
            'status' => '状态'
        ];
        
        foreach ($fields as $field => $desc) {
            $value = $callback->$field ?? '';
            $analysis = '';
            
            if ($field === 'log_time') {
                if ($value) {
                    $beijing_time = date('Y-m-d H:i:s', strtotime($value) + 8 * 3600);
                    $analysis = "北京时间: {$beijing_time}";
                } else {
                    $analysis = "❌ 时间为空";
                }
            } elseif ($field === 'amount') {
                if ($value) {
                    $analysis = "显示: ¥" . number_format($value, 2);
                } else {
                    $analysis = "❌ 金额为空";
                }
            } elseif ($field === 'execution_time') {
                if ($value) {
                    $analysis = "显示: " . number_format($value * 1000, 1) . "ms";
                } else {
                    $analysis = "❌ 执行时间为空";
                }
            } elseif (empty($value)) {
                $analysis = "❌ 字段为空";
            } else {
                $analysis = "✅ 有值";
            }
            
            echo "<tr>";
            echo "<td><strong>{$desc}</strong><br><small>{$field}</small></td>";
            echo "<td>" . (strlen($value) > 50 ? substr(esc_html($value), 0, 50) . '...' : esc_html($value)) . "</td>";
            echo "<td>{$analysis}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 详细分析 request_data
        if (!empty($callback->request_data)) {
            echo "<h4>请求数据分析:</h4>";
            echo "<pre>" . esc_html($callback->request_data) . "</pre>";
            
            // 尝试解析JSON
            $request_json = json_decode($callback->request_data, true);
            if ($request_json) {
                echo "<h5>解析后的请求数据结构:</h5>";
                if (isset($request_json['result'])) {
                    echo "<strong>包含result字段:</strong><br>";
                    $result_data = json_decode($request_json['result'], true);
                    if ($result_data && isset($result_data['data'])) {
                        echo "<strong>result.data内容:</strong><br>";
                        $payment_data = $result_data['data'];
                        
                        echo "<table>";
                        echo "<tr><th>API字段</th><th>值</th><th>应显示</th></tr>";
                        
                        $api_fields = [
                            'orderNo' => '订单号',
                            'merchantOrderNo' => '商户订单号', 
                            'orderStatus' => '订单状态',
                            'orderAmount' => '订单金额(分)',
                            'paidAmount' => '实付金额(分)',
                            'orderFee' => '手续费(分)',
                            'currency' => '货币',
                            'payModel' => '支付方式',
                            'orderTime' => '订单时间',
                            'finishTime' => '完成时间'
                        ];
                        
                        foreach ($api_fields as $field => $desc) {
                            $value = $payment_data[$field] ?? '';
                            $display = '';
                            
                            if ($field === 'orderAmount' || $field === 'paidAmount' || $field === 'orderFee') {
                                $display = $value ? '¥' . number_format($value / 100, 2) : '';
                            } elseif ($field === 'orderTime' || $field === 'finishTime') {
                                $display = $value ? date('Y-m-d H:i:s', $value / 1000) : '';
                            } else {
                                $display = $value;
                            }
                            
                            echo "<tr>";
                            echo "<td><strong>{$desc}</strong><br><small>{$field}</small></td>";
                            echo "<td>" . esc_html($value) . "</td>";
                            echo "<td>" . esc_html($display) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<div class='error'>❌ result.data字段解析失败</div>";
                    }
                } else {
                    echo "<div class='error'>❌ 没有找到result字段</div>";
                }
            } else {
                echo "<div class='error'>❌ JSON解析失败: " . json_last_error_msg() . "</div>";
            }
        } else {
            echo "<div class='error'>❌ 没有请求数据</div>";
        }
        
        // 分析 extra_data
        if (!empty($callback->extra_data)) {
            echo "<h4>额外数据分析:</h4>";
            echo "<pre>" . esc_html($callback->extra_data) . "</pre>";
            
            $extra_json = json_decode($callback->extra_data, true);
            if ($extra_json) {
                echo "<h5>额外数据字段:</h5>";
                echo "<table>";
                echo "<tr><th>字段</th><th>值</th></tr>";
                foreach ($extra_json as $key => $value) {
                    echo "<tr><td>{$key}</td><td>" . esc_html(is_array($value) ? json_encode($value) : $value) . "</td></tr>";
                }
                echo "</table>";
            }
        }
        
        // 分析response_data
        if (!empty($callback->response_data)) {
            echo "<h4>响应数据分析:</h4>";
            echo "<pre>" . esc_html($callback->response_data) . "</pre>";
        }
        
        echo "<hr>";
    }
}
echo "</div>";

// 3. 检查签名验证配置
echo "<div class='section'>";
echo "<h2>🔐 签名验证配置检查</h2>";

$onepay_settings = get_option('woocommerce_onepay_settings', array());
echo "<table>";
echo "<tr><th>配置项</th><th>状态</th></tr>";
echo "<tr><td>商户号</td><td>" . (empty($onepay_settings['merchant_no']) ? '❌ 未配置' : '✅ 已配置') . "</td></tr>";
echo "<tr><td>私钥</td><td>" . (empty($onepay_settings['private_key']) ? '❌ 未配置' : '✅ 已配置') . "</td></tr>";
echo "<tr><td>平台公钥</td><td>" . (empty($onepay_settings['platform_public_key']) ? '❌ 未配置' : '✅ 已配置') . "</td></tr>";
echo "<tr><td>调试模式</td><td>" . ($onepay_settings['debug'] === 'yes' ? '✅ 已启用' : '❌ 未启用') . "</td></tr>";
echo "</table>";

if (empty($onepay_settings['platform_public_key'])) {
    echo "<div class='error'>⚠️ 平台公钥未配置，可能导致签名验证跳过，数据可能不完整</div>";
}
echo "</div>";

// 4. 显示当前的显示逻辑问题
echo "<div class='section'>";
echo "<h2>🖥️ 显示逻辑分析</h2>";
echo "<p>当前显示页面可能存在的问题：</p>";
echo "<ul>";
echo "<li>1. 数据库字段order_number, amount, execution_time等可能没有正确填充</li>";
echo "<li>2. 时间转换可能不一致</li>";
echo "<li>3. 订单状态可能需要从extra_data中提取</li>";
echo "<li>4. 签名验证失败可能导致数据处理中断</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>