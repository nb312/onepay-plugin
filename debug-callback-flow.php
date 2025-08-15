<?php
/**
 * OnePay回调流程调试工具
 * 
 * 用于检查详细调试记录是否记录了完整的回调处理流程
 * 访问方式：http://yoursite.com/wp-content/plugins/onepay/debug-callback-flow.php
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    // WordPress环境检测
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

// 加载必要的类
require_once dirname(__FILE__) . '/includes/class-onepay-detailed-debug-recorder.php';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay回调流程调试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
        .record { margin: 5px 0; padding: 8px; border-left: 4px solid #0073aa; background: #f9f9f9; }
        .record-method_enter { border-left-color: #0073aa; }
        .record-method_exit { border-left-color: #46b450; }
        .record-condition { border-left-color: #ffb900; }
        .record-variable { border-left-color: #826eb4; }
        .record-debug { border-left-color: #666; }
        .record-error { border-left-color: #dc3232; background: #fef7f7; }
        .timestamp { color: #666; font-size: 11px; }
        .method-name { font-weight: bold; color: #0073aa; }
        .button { background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
        .flow-step { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 5px; }
        .step-title { font-weight: bold; color: #0073aa; }
        .step-missing { background: #fef7f7; border-left: 4px solid #dc3232; }
        .step-present { background: #f0f9ff; border-left: 4px solid #46b450; }
    </style>
</head>
<body>
    <h1>🔍 OnePay回调流程调试检查</h1>
    
    <?php
    $debug_recorder = OnePay_Detailed_Debug_Recorder::get_instance();
    
    // 获取最近的会话
    $sessions = $debug_recorder->get_recent_sessions(5);
    
    if (empty($sessions)) {
        echo '<div class="section warning"><h3>⚠️ 没有找到调试记录</h3><p>请先发送一个测试回调，或者确保已启用调试模式。</p></div>';
    } else {
        echo '<div class="section info"><h3>📋 最近的调试会话</h3>';
        echo '<p>找到 ' . count($sessions) . ' 个调试会话，点击查看详细流程：</p>';
        
        foreach ($sessions as $session) {
            $session_short_id = substr($session->session_id, -8);
            $error_indicator = $session->error_count > 0 ? ' ❌' : ' ✅';
            
            echo '<div style="margin: 10px 0; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px;">';
            echo '<strong>会话 ' . $session_short_id . '</strong>' . $error_indicator;
            echo ' | 记录数: ' . $session->record_count;
            echo ' | 错误数: ' . $session->error_count;
            echo ' | 时间: ' . date('m-d H:i:s', strtotime($session->start_time));
            echo ' <a href="?session=' . urlencode($session->session_id) . '" class="button">查看流程</a>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // 如果选择了特定会话，显示详细流程
    if (isset($_GET['session']) && !empty($_GET['session'])) {
        $session_id = sanitize_text_field($_GET['session']);
        $records = $debug_recorder->get_records(array(
            'session_id' => $session_id,
            'limit' => 500
        ));
        
        echo '<div class="section">';
        echo '<h3>📊 会话流程分析: ' . substr($session_id, -8) . '</h3>';
        echo '<p>总记录数: ' . count($records) . '</p>';
        
        // 分析流程完整性
        $flow_analysis = analyze_callback_flow($records);
        display_flow_analysis($flow_analysis);
        
        // 显示详细记录
        echo '<h4>📋 详细记录</h4>';
        echo '<div style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
        
        foreach ($records as $record) {
            $timestamp = date('H:i:s.', $record->timestamp) . sprintf('%03d', ($record->timestamp - floor($record->timestamp)) * 1000);
            $depth_indent = str_repeat('&nbsp;&nbsp;', $record->execution_depth);
            
            echo '<div class="record record-' . $record->record_type . '">';
            echo '<span class="timestamp">[' . $timestamp . ']</span> ';
            echo $depth_indent;
            
            switch ($record->record_type) {
                case 'method_enter':
                    echo '<span class="method-name">🎯 进入: ' . $record->class_name . '::' . $record->method_name . '()</span>';
                    break;
                case 'method_exit':
                    echo '<span class="method-name">🏁 退出: ' . $record->class_name . '::' . $record->method_name . '()</span>';
                    if ($record->execution_time) {
                        echo ' <small>(' . round($record->execution_time * 1000, 2) . 'ms)</small>';
                    }
                    break;
                case 'condition':
                    echo '🔍 条件: ' . $record->message;
                    break;
                case 'variable':
                    echo '📝 变量: ' . $record->message;
                    break;
                case 'debug':
                    echo '💭 调试: ' . $record->message;
                    break;
                case 'error':
                    echo '❌ 错误: ' . $record->message;
                    break;
                default:
                    echo '📋 ' . $record->record_type . ': ' . $record->message;
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * 分析回调流程完整性
     */
    function analyze_callback_flow($records) {
        $analysis = array(
            'total_records' => count($records),
            'expected_steps' => array(),
            'found_steps' => array(),
            'missing_steps' => array(),
            'method_calls' => array(),
            'has_signature_success' => false,
            'has_order_processing' => false,
            'has_response_sent' => false,
            'last_significant_step' => ''
        );
        
        // 期望的关键步骤
        $expected_steps = array(
            'request_start' => '请求开始',
            'json_parsing' => 'JSON解析',
            'data_validation' => '数据验证',
            'signature_verification' => '签名验证',
            'order_lookup' => '订单查找',
            'order_processing' => '订单处理',
            'response_sending' => '响应发送',
            'request_end' => '请求结束'
        );
        
        $analysis['expected_steps'] = $expected_steps;
        
        // 分析记录
        foreach ($records as $record) {
            // 检查方法调用
            if ($record->record_type === 'method_enter') {
                $method_key = $record->class_name . '::' . $record->method_name;
                if (!isset($analysis['method_calls'][$method_key])) {
                    $analysis['method_calls'][$method_key] = 0;
                }
                $analysis['method_calls'][$method_key]++;
            }
            
            // 检查关键步骤
            if (strpos($record->message, '请求开始') !== false) {
                $analysis['found_steps']['request_start'] = true;
            }
            if (strpos($record->message, 'JSON解析') !== false) {
                $analysis['found_steps']['json_parsing'] = true;
            }
            if (strpos($record->message, '数据验证') !== false || strpos($record->message, 'validate') !== false) {
                $analysis['found_steps']['data_validation'] = true;
            }
            if (strpos($record->message, '签名验证成功') !== false) {
                $analysis['has_signature_success'] = true;
                $analysis['found_steps']['signature_verification'] = true;
            }
            if (strpos($record->message, '查找订单') !== false || strpos($record->message, 'order_lookup') !== false) {
                $analysis['found_steps']['order_lookup'] = true;
            }
            if (strpos($record->message, '订单处理') !== false || strpos($record->message, 'process_payment') !== false) {
                $analysis['has_order_processing'] = true;
                $analysis['found_steps']['order_processing'] = true;
            }
            if (strpos($record->message, '发送响应') !== false || strpos($record->message, 'response') !== false) {
                $analysis['has_response_sent'] = true;
                $analysis['found_steps']['response_sending'] = true;
            }
            if (strpos($record->message, '请求结束') !== false || strpos($record->message, 'end_request') !== false) {
                $analysis['found_steps']['request_end'] = true;
            }
            
            // 记录最后的重要步骤
            if (in_array($record->record_type, array('method_enter', 'method_exit', 'debug'))) {
                $analysis['last_significant_step'] = $record->message;
            }
        }
        
        // 找出缺失的步骤
        foreach ($expected_steps as $step_key => $step_name) {
            if (!isset($analysis['found_steps'][$step_key])) {
                $analysis['missing_steps'][] = $step_name;
            }
        }
        
        return $analysis;
    }
    
    /**
     * 显示流程分析结果
     */
    function display_flow_analysis($analysis) {
        echo '<div class="section">';
        echo '<h4>🔬 流程完整性分析</h4>';
        
        // 总体状态
        $completion_rate = (count($analysis['found_steps']) / count($analysis['expected_steps'])) * 100;
        $status_class = $completion_rate >= 80 ? 'success' : ($completion_rate >= 50 ? 'warning' : 'error');
        
        echo '<div class="flow-step ' . $status_class . '">';
        echo '<div class="step-title">总体完成度: ' . round($completion_rate, 1) . '%</div>';
        echo '<p>记录总数: ' . $analysis['total_records'] . ' | ';
        echo '完成步骤: ' . count($analysis['found_steps']) . '/' . count($analysis['expected_steps']) . '</p>';
        echo '</div>';
        
        // 具体步骤状态
        foreach ($analysis['expected_steps'] as $step_key => $step_name) {
            $is_present = isset($analysis['found_steps'][$step_key]);
            $step_class = $is_present ? 'step-present' : 'step-missing';
            $step_icon = $is_present ? '✅' : '❌';
            
            echo '<div class="flow-step ' . $step_class . '">';
            echo '<div class="step-title">' . $step_icon . ' ' . $step_name . '</div>';
            echo '</div>';
        }
        
        // 特别检查
        echo '<div class="flow-step info">';
        echo '<div class="step-title">🔍 特别检查</div>';
        echo '<p>';
        echo '签名验证成功: ' . ($analysis['has_signature_success'] ? '✅ 是' : '❌ 否') . '<br>';
        echo '订单处理执行: ' . ($analysis['has_order_processing'] ? '✅ 是' : '❌ 否') . '<br>';
        echo '响应发送记录: ' . ($analysis['has_response_sent'] ? '✅ 是' : '❌ 否') . '<br>';
        echo '</p>';
        echo '<p><strong>最后执行步骤:</strong> ' . esc_html($analysis['last_significant_step']) . '</p>';
        echo '</div>';
        
        // 方法调用统计
        if (!empty($analysis['method_calls'])) {
            echo '<div class="flow-step info">';
            echo '<div class="step-title">📊 方法调用统计</div>';
            echo '<pre>';
            foreach ($analysis['method_calls'] as $method => $count) {
                echo $method . ': ' . $count . ' 次调用' . "\n";
            }
            echo '</pre>';
            echo '</div>';
        }
        
        // 缺失步骤警告
        if (!empty($analysis['missing_steps'])) {
            echo '<div class="flow-step step-missing">';
            echo '<div class="step-title">⚠️ 缺失的步骤</div>';
            echo '<ul>';
            foreach ($analysis['missing_steps'] as $missing_step) {
                echo '<li>' . $missing_step . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    ?>
    
    <div class="section">
        <h3>🎯 调试建议</h3>
        <p>如果发现记录在"签名验证成功"后中断：</p>
        <ul>
            <li>检查数据库是否有写入权限问题</li>
            <li>查看WordPress错误日志中是否有PHP错误</li>
            <li>确认OnePay插件的所有文件都已正确上传</li>
            <li>验证详细调试记录器是否正确初始化</li>
        </ul>
        
        <p><strong>如果问题仍然存在，请提供：</strong></p>
        <ol>
            <li>完整的会话记录（从上面复制）</li>
            <li>WordPress错误日志的相关部分</li>
            <li>服务器PHP错误日志</li>
            <li>数据库表结构是否完整</li>
        </ol>
    </div>
    
    <div class="section">
        <h3>🔗 相关链接</h3>
        <a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug'); ?>" class="button">查看详细调试界面</a>
        <a href="test-detailed-debug.php" class="button">运行功能测试</a>
        <a href="?" class="button">刷新页面</a>
    </div>
    
    <p><small>生成时间: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>