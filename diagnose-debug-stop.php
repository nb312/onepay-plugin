<?php
/**
 * OnePay调试记录中断诊断工具
 * 
 * 专门用于诊断为什么调试记录在"签名验证成功"后停止
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    $wp_load_paths = array(
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    );
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('无法加载WordPress环境');
    }
}

// 检查用户权限
if (!current_user_can('manage_woocommerce')) {
    die('权限不足');
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay调试记录中断诊断</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
        .button { background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
        .diagnostic-item { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; }
        .issue { border-left-color: #dc3232; background: #fef7f7; }
        .good { border-left-color: #46b450; background: #f0f9ff; }
    </style>
</head>
<body>
    <h1>🔍 OnePay调试记录中断深度诊断</h1>
    
    <?php
    
    // 加载必要的类
    require_once dirname(__FILE__) . '/includes/class-onepay-detailed-debug-recorder.php';
    require_once dirname(__FILE__) . '/includes/class-onepay-callback.php';
    
    echo '<div class="section info">';
    echo '<h3>🎯 诊断目标</h3>';
    echo '<p>分析为什么调试记录在 <code>$this->detailed_debug->log_debug(\'签名验证成功\');</code> 后停止记录</p>';
    echo '</div>';
    
    // 1. 检查调试记录器状态
    echo '<div class="section">';
    echo '<h3>1️⃣ 调试记录器状态检查</h3>';
    
    try {
        $debug_recorder = OnePay_Detailed_Debug_Recorder::get_instance();
        echo '<div class="diagnostic-item good">✅ 调试记录器实例创建成功</div>';
        
        // 检查调试是否启用
        $gateway_settings = get_option('woocommerce_onepay_settings', array());
        $debug_enabled = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        
        if ($debug_enabled) {
            echo '<div class="diagnostic-item good">✅ 调试模式已启用</div>';
        } else {
            echo '<div class="diagnostic-item issue">❌ 调试模式未启用 - 这可能是问题所在</div>';
        }
        
        // 测试记录器写入
        $test_session = $debug_recorder->start_request('diagnosis_test', array('test' => true));
        if ($test_session) {
            echo '<div class="diagnostic-item good">✅ 调试记录器可以开始会话</div>';
            
            // 测试写入
            $debug_recorder->log_debug('诊断测试记录');
            $debug_recorder->end_request('test_success', null);
            
            echo '<div class="diagnostic-item good">✅ 调试记录器可以写入记录</div>';
        } else {
            echo '<div class="diagnostic-item issue">❌ 调试记录器无法开始会话</div>';
        }
        
    } catch (Exception $e) {
        echo '<div class="diagnostic-item issue">❌ 调试记录器异常: ' . esc_html($e->getMessage()) . '</div>';
    }
    
    echo '</div>';
    
    // 2. 检查数据库状态
    echo '<div class="section">';
    echo '<h3>2️⃣ 数据库状态检查</h3>';
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
    
    // 检查表是否存在
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    if ($table_exists) {
        echo '<div class="diagnostic-item good">✅ 调试记录表存在</div>';
        
        // 检查表结构
        $table_structure = $wpdb->get_results("DESCRIBE $table_name");
        echo '<div class="diagnostic-item good">✅ 表结构正常 (' . count($table_structure) . ' 个字段)</div>';
        
        // 检查最近的记录
        $recent_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        echo '<div class="diagnostic-item ' . ($recent_count > 0 ? 'good' : 'warning') . '">';
        echo ($recent_count > 0 ? '✅' : '⚠️') . ' 最近1小时内有 ' . $recent_count . ' 条记录</div>';
        
        // 检查写入权限
        try {
            $test_result = $wpdb->insert($table_name, array(
                'session_id' => 'test_' . time(),
                'timestamp' => microtime(true),
                'record_type' => 'test',
                'message' => '诊断测试记录',
                'created_at' => current_time('mysql')
            ));
            
            if ($test_result) {
                echo '<div class="diagnostic-item good">✅ 数据库写入权限正常</div>';
                // 清理测试记录
                $wpdb->delete($table_name, array('record_type' => 'test'));
            } else {
                echo '<div class="diagnostic-item issue">❌ 数据库写入失败: ' . $wpdb->last_error . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="diagnostic-item issue">❌ 数据库写入异常: ' . esc_html($e->getMessage()) . '</div>';
        }
        
    } else {
        echo '<div class="diagnostic-item issue">❌ 调试记录表不存在</div>';
    }
    
    echo '</div>';
    
    // 3. 检查PHP环境
    echo '<div class="section">';
    echo '<h3>3️⃣ PHP环境检查</h3>';
    
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    $current_memory = memory_get_usage(true);
    $peak_memory = memory_get_peak_usage(true);
    
    echo '<div class="diagnostic-item good">✅ PHP内存限制: ' . $memory_limit . '</div>';
    echo '<div class="diagnostic-item good">✅ 当前内存使用: ' . round($current_memory / 1024 / 1024, 2) . ' MB</div>';
    echo '<div class="diagnostic-item good">✅ 峰值内存使用: ' . round($peak_memory / 1024 / 1024, 2) . ' MB</div>';
    echo '<div class="diagnostic-item good">✅ 最大执行时间: ' . ($max_execution_time == 0 ? '无限制' : $max_execution_time . '秒') . '</div>';
    
    // 检查错误日志
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div class="diagnostic-item good">✅ WordPress调试模式已启用</div>';
    } else {
        echo '<div class="diagnostic-item warning">⚠️ WordPress调试模式未启用，可能看不到错误信息</div>';
    }
    
    echo '</div>';
    
    // 4. 分析最近的调试记录
    echo '<div class="section">';
    echo '<h3>4️⃣ 最近调试记录分析</h3>';
    
    if ($table_exists) {
        // 找到最近包含"签名验证成功"的记录
        $signature_success_records = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE message LIKE %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC 
            LIMIT 5
        ", '%签名验证成功%'));
        
        if (!empty($signature_success_records)) {
            echo '<div class="diagnostic-item good">✅ 找到 ' . count($signature_success_records) . ' 条"签名验证成功"记录</div>';
            
            foreach ($signature_success_records as $record) {
                echo '<div class="diagnostic-item info">';
                echo '<strong>会话 ' . substr($record->session_id, -8) . '</strong> - ' . $record->created_at;
                
                // 查找该会话在这条记录之后的记录
                $after_records = $wpdb->get_results($wpdb->prepare("
                    SELECT * FROM $table_name 
                    WHERE session_id = %s 
                    AND timestamp > %f
                    ORDER BY timestamp ASC
                    LIMIT 20
                ", $record->session_id, $record->timestamp));
                
                if (!empty($after_records)) {
                    echo '<br>✅ 该会话在签名验证成功后还有 ' . count($after_records) . ' 条记录';
                    echo '<br>最后记录: ' . esc_html($after_records[count($after_records)-1]->message);
                } else {
                    echo '<br>❌ 该会话在签名验证成功后没有更多记录 - 这是问题！';
                    
                    // 检查是否有错误记录
                    $error_records = $wpdb->get_results($wpdb->prepare("
                        SELECT * FROM $table_name 
                        WHERE session_id = %s 
                        AND record_type = 'error'
                        ORDER BY timestamp DESC
                        LIMIT 3
                    ", $record->session_id));
                    
                    if (!empty($error_records)) {
                        echo '<br>❌ 发现错误记录:';
                        foreach ($error_records as $error) {
                            echo '<br>&nbsp;&nbsp;- ' . esc_html($error->message);
                        }
                    }
                }
                echo '</div>';
            }
        } else {
            echo '<div class="diagnostic-item warning">⚠️ 最近24小时内没有找到"签名验证成功"记录</div>';
        }
        
        // 检查是否有异常中断的会话
        $incomplete_sessions = $wpdb->get_results("
            SELECT session_id, COUNT(*) as record_count, MAX(created_at) as last_record
            FROM $table_name 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY session_id
            HAVING record_count < 10
            ORDER BY last_record DESC
            LIMIT 5
        ");
        
        if (!empty($incomplete_sessions)) {
            echo '<div class="diagnostic-item warning">';
            echo '⚠️ 发现 ' . count($incomplete_sessions) . ' 个可能不完整的会话:';
            foreach ($incomplete_sessions as $session) {
                echo '<br>&nbsp;&nbsp;- ' . substr($session->session_id, -8) . ' (仅 ' . $session->record_count . ' 条记录)';
            }
            echo '</div>';
        }
    }
    
    echo '</div>';
    
    // 5. 代码流程分析
    echo '<div class="section">';
    echo '<h3>5️⃣ 代码流程分析</h3>';
    
    // 检查关键文件是否存在
    $callback_file = dirname(__FILE__) . '/includes/class-onepay-callback.php';
    if (file_exists($callback_file)) {
        echo '<div class="diagnostic-item good">✅ 回调处理文件存在</div>';
        
        // 分析代码中"签名验证成功"之后的逻辑
        $code_content = file_get_contents($callback_file);
        
        if (strpos($code_content, "log_debug('签名验证成功')") !== false) {
            echo '<div class="diagnostic-item good">✅ 找到"签名验证成功"调试记录点</div>';
            
            // 检查是否有后续的调试记录
            $subsequent_debug_calls = array(
                "log_debug('签名验证成功，返回true')",
                "log_debug('解析回调result数据')",
                "log_debug('开始查找对应订单')",
                "log_debug('开始处理订单状态更新')"
            );
            
            foreach ($subsequent_debug_calls as $debug_call) {
                if (strpos($code_content, $debug_call) !== false) {
                    echo '<div class="diagnostic-item good">✅ 找到后续调试点: ' . esc_html($debug_call) . '</div>';
                } else {
                    echo '<div class="diagnostic-item issue">❌ 缺少后续调试点: ' . esc_html($debug_call) . '</div>';
                }
            }
        } else {
            echo '<div class="diagnostic-item issue">❌ 未找到"签名验证成功"调试记录点</div>';
        }
        
    } else {
        echo '<div class="diagnostic-item issue">❌ 回调处理文件不存在</div>';
    }
    
    echo '</div>';
    
    // 6. 实时测试
    echo '<div class="section">';
    echo '<h3>6️⃣ 实时诊断测试</h3>';
    
    if (isset($_POST['run_test'])) {
        echo '<div class="diagnostic-item info">🔄 运行实时测试...</div>';
        
        try {
            // 模拟调试记录器的使用
            $test_recorder = OnePay_Detailed_Debug_Recorder::get_instance();
            $test_session = $test_recorder->start_request('diagnosis_live_test', array('test' => 'live'));
            
            $test_recorder->enter_method('DiagnosisTest', 'testMethod', array('param' => 'value'));
            $test_recorder->log_debug('测试：签名验证成功');
            $test_recorder->log_debug('测试：后续步骤1');
            $test_recorder->log_debug('测试：后续步骤2');
            $test_recorder->log_debug('测试：后续步骤3');
            $test_recorder->exit_method('DiagnosisTest', 'testMethod', 'success');
            $test_recorder->end_request('test_complete', null);
            
            echo '<div class="diagnostic-item good">✅ 实时测试成功完成</div>';
            
            // 检查测试记录
            $test_records = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM $table_name 
                WHERE session_id = %s 
                ORDER BY timestamp ASC
            ", $test_session));
            
            echo '<div class="diagnostic-item good">✅ 测试生成了 ' . count($test_records) . ' 条记录</div>';
            
            if (count($test_records) >= 6) {
                echo '<div class="diagnostic-item good">✅ 调试记录器功能正常，问题可能在特定的回调处理逻辑中</div>';
            } else {
                echo '<div class="diagnostic-item issue">❌ 调试记录器功能异常，生成的记录数量不足</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="diagnostic-item issue">❌ 实时测试失败: ' . esc_html($e->getMessage()) . '</div>';
        }
    } else {
        echo '<form method="post">';
        echo '<input type="hidden" name="run_test" value="1">';
        echo '<button type="submit" class="button">🧪 运行实时诊断测试</button>';
        echo '</form>';
        echo '<p>这将模拟调试记录器的使用，测试是否能正常记录"签名验证成功"后的步骤。</p>';
    }
    
    echo '</div>';
    
    // 7. 诊断结论和建议
    echo '<div class="section">';
    echo '<h3>7️⃣ 诊断结论和建议</h3>';
    
    echo '<div class="diagnostic-item info">';
    echo '<strong>基于以上诊断，可能的原因包括：</strong>';
    echo '<ol>';
    echo '<li><strong>调试模式未启用</strong> - 检查 WooCommerce → 支付 → OnePay 设置</li>';
    echo '<li><strong>数据库写入问题</strong> - 权限不足或表结构问题</li>';
    echo '<li><strong>PHP内存或时间限制</strong> - 进程被强制终止</li>';
    echo '<li><strong>未捕获的PHP错误</strong> - 代码执行中断但没有错误日志</li>';
    echo '<li><strong>调试记录器状态问题</strong> - 在执行过程中被禁用</li>';
    echo '<li><strong>代码逻辑问题</strong> - 某处调用了 exit() 或 die()</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<div class="diagnostic-item warning">';
    echo '<strong>建议的解决步骤：</strong>';
    echo '<ol>';
    echo '<li>启用 WordPress 调试模式 (WP_DEBUG = true)</li>';
    echo '<li>检查 PHP 错误日志</li>';
    echo '<li>临时增加 PHP 内存限制</li>';
    echo '<li>在关键位置添加错误日志记录</li>';
    echo '<li>使用上面的实时测试验证调试记录器功能</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '</div>';
    
    ?>
    
    <div class="section">
        <h3>🔗 相关工具</h3>
        <a href="debug-callback-flow.php" class="button">查看回调流程分析</a>
        <a href="test-detailed-debug.php" class="button">基础功能测试</a>
        <a href="?" class="button">刷新诊断</a>
    </div>
    
    <p><small>诊断时间: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>