<?php
/**
 * OnePay调试记录实时监控工具
 * 
 * 实时监控调试记录的写入情况，帮助诊断为什么记录在"签名验证成功"后停止
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
    <title>OnePay调试记录实时监控</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; }
        .button { background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
        .log-entry { margin: 5px 0; padding: 5px; background: #f9f9f9; border-left: 3px solid #0073aa; font-family: monospace; font-size: 12px; }
        .log-error { border-left-color: #dc3232; background: #fef7f7; }
        .log-success { border-left-color: #46b450; background: #f0f9ff; }
        .monitor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .realtime-log { max-height: 400px; overflow-y: auto; }
    </style>
    <script>
        // 自动刷新功能
        let autoRefresh = false;
        let refreshInterval;
        
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            const button = document.getElementById('autoRefreshBtn');
            
            if (autoRefresh) {
                button.textContent = '⏸️ 停止自动刷新';
                button.style.background = '#dc3232';
                refreshInterval = setInterval(function() {
                    location.reload();
                }, 3000);
            } else {
                button.textContent = '🔄 启动自动刷新';
                button.style.background = '#0073aa';
                clearInterval(refreshInterval);
            }
        }
        
        // 监控数据库记录数量变化
        let lastRecordCount = 0;
        
        function checkRecordChanges() {
            fetch('?action=get_record_count')
                .then(response => response.json())
                .then(data => {
                    const currentCount = data.count;
                    const indicator = document.getElementById('recordChangeIndicator');
                    
                    if (currentCount > lastRecordCount) {
                        indicator.innerHTML = '🟢 记录增加中 (' + currentCount + ')';
                        indicator.className = 'log-success';
                    } else if (currentCount === lastRecordCount) {
                        indicator.innerHTML = '🟡 记录暂停 (' + currentCount + ')';
                        indicator.className = 'log-entry';
                    }
                    
                    lastRecordCount = currentCount;
                });
        }
        
        // 每秒检查一次记录变化
        setInterval(checkRecordChanges, 1000);
    </script>
</head>
<body>
    <h1>🔍 OnePay调试记录实时监控</h1>
    
    <div class="section info">
        <h3>📊 监控状态</h3>
        <p>这个工具会实时监控调试记录的写入情况，帮助识别记录中断的确切位置。</p>
        <div id="recordChangeIndicator" class="log-entry">🔵 正在初始化...</div>
        <button id="autoRefreshBtn" class="button" onclick="toggleAutoRefresh()">🔄 启动自动刷新</button>
        <a href="?" class="button">🔄 手动刷新</a>
    </div>
    
    <?php
    
    // 处理AJAX请求
    if (isset($_GET['action']) && $_GET['action'] === 'get_record_count') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        header('Content-Type: application/json');
        echo json_encode(array('count' => intval($count)));
        exit;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
    
    // 监控区域
    echo '<div class="monitor-grid">';
    
    // 左侧：最新记录
    echo '<div class="section">';
    echo '<h3>📋 最新调试记录 (最近10条)</h3>';
    
    $latest_records = $wpdb->get_results("
        SELECT * FROM $table_name 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if (!empty($latest_records)) {
        echo '<div class="realtime-log">';
        foreach ($latest_records as $record) {
            $time = date('H:i:s', strtotime($record->created_at));
            $session_short = substr($record->session_id, -6);
            $class_name = $record->class_name ? $record->class_name . '::' : '';
            $method_name = $record->method_name ? $record->method_name . '()' : '';
            
            $css_class = 'log-entry';
            if ($record->record_type === 'error') {
                $css_class = 'log-error';
            } elseif (strpos($record->message, '【诊断】') !== false) {
                $css_class = 'log-success';
            }
            
            echo '<div class="' . $css_class . '">';
            echo '[' . $time . '] [' . $session_short . '] ';
            echo '[' . strtoupper($record->record_type) . '] ';
            echo $class_name . $method_name . ' ';
            echo esc_html($record->message);
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="warning">⚠️ 没有找到最近的调试记录</div>';
    }
    
    echo '</div>';
    
    // 右侧：问题诊断
    echo '<div class="section">';
    echo '<h3>🔍 签名验证后记录诊断</h3>';
    
    // 查找包含"签名验证成功"的最近记录
    $signature_success_records = $wpdb->get_results("
        SELECT * FROM $table_name 
        WHERE message LIKE '%签名验证成功%' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    
    if (!empty($signature_success_records)) {
        foreach ($signature_success_records as $sig_record) {
            $session_short = substr($sig_record->session_id, -8);
            echo '<div class="log-success">';
            echo '<strong>会话 ' . $session_short . '</strong> - ' . date('H:i:s', strtotime($sig_record->created_at));
            echo '<br>📍 签名验证成功记录位置';
            
            // 查找该会话在这个时间点之后的记录
            $after_records = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM $table_name 
                WHERE session_id = %s 
                AND timestamp > %f
                ORDER BY timestamp ASC
                LIMIT 10
            ", $sig_record->session_id, $sig_record->timestamp));
            
            if (!empty($after_records)) {
                echo '<br>✅ 后续记录: ' . count($after_records) . ' 条';
                echo '<br>最后记录: ' . esc_html($after_records[count($after_records)-1]->message);
                
                // 显示详细的后续记录
                echo '<br><small>后续步骤:</small>';
                foreach ($after_records as $after) {
                    echo '<br>&nbsp;&nbsp;➤ ' . esc_html($after->message);
                }
            } else {
                echo '<br>❌ <strong>没有后续记录 - 这就是问题所在！</strong>';
                
                // 检查是否有诊断记录
                $diagnostic_records = $wpdb->get_results($wpdb->prepare("
                    SELECT * FROM $table_name 
                    WHERE session_id = %s 
                    AND message LIKE '%【诊断】%'
                    ORDER BY timestamp ASC
                ", $sig_record->session_id));
                
                if (!empty($diagnostic_records)) {
                    echo '<br>🔍 发现诊断记录:';
                    foreach ($diagnostic_records as $diag) {
                        echo '<br>&nbsp;&nbsp;🔍 ' . esc_html($diag->message);
                    }
                } else {
                    echo '<br>❌ 连诊断记录都没有，说明问题很严重';
                }
            }
            
            echo '</div><br>';
        }
    } else {
        echo '<div class="warning">⚠️ 最近1小时内没有发现"签名验证成功"记录</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // 文件系统诊断日志
    echo '<div class="section">';
    echo '<h3>📄 文件系统诊断日志</h3>';
    
    $debug_files = array(
        'debug-record-success.log' => '成功记录日志',
        'debug-record-failures.log' => '失败记录日志', 
        'debug-record-exceptions.log' => '异常记录日志'
    );
    
    $found_files = false;
    foreach ($debug_files as $filename => $description) {
        $filepath = dirname(__FILE__) . '/' . $filename;
        if (file_exists($filepath)) {
            $found_files = true;
            echo '<div class="info">';
            echo '<h4>📄 ' . $description . '</h4>';
            $content = file_get_contents($filepath);
            $lines = explode("\n", $content);
            $recent_lines = array_slice(array_filter($lines), -10); // 最近10行
            
            echo '<pre>';
            foreach ($recent_lines as $line) {
                echo esc_html($line) . "\n";
            }
            echo '</pre>';
            echo '</div>';
        }
    }
    
    if (!$found_files) {
        echo '<div class="warning">⚠️ 没有发现文件系统诊断日志，这表明调试记录器可能根本没有遇到问题</div>';
    }
    
    echo '</div>';
    
    // 数据库状态检查
    echo '<div class="section">';
    echo '<h3>🗄️ 数据库状态检查</h3>';
    
    // 检查最近的记录统计
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT session_id) as total_sessions,
            MAX(created_at) as last_record_time,
            MIN(created_at) as first_record_time
        FROM $table_name 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    if ($stats) {
        echo '<div class="info">';
        echo '<strong>最近1小时统计:</strong><br>';
        echo '总记录数: ' . $stats->total_records . '<br>';
        echo '会话数: ' . $stats->total_sessions . '<br>';
        echo '最新记录: ' . ($stats->last_record_time ? date('H:i:s', strtotime($stats->last_record_time)) : '无') . '<br>';
        echo '最早记录: ' . ($stats->first_record_time ? date('H:i:s', strtotime($stats->first_record_time)) : '无') . '<br>';
        echo '</div>';
    }
    
    // 检查是否有写入问题
    $wpdb_status = is_object($wpdb) ? '正常' : '异常';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    echo '<div class="' . ($table_exists ? 'success' : 'error') . '">';
    echo 'WPDB状态: ' . $wpdb_status . '<br>';
    echo '数据表存在: ' . ($table_exists ? '是' : '否') . '<br>';
    echo '最后错误: ' . ($wpdb->last_error ? $wpdb->last_error : '无') . '<br>';
    echo '</div>';
    
    echo '</div>';
    
    ?>
    
    <div class="section">
        <h3>💡 使用建议</h3>
        <p><strong>现在请发送一个回调测试，然后观察：</strong></p>
        <ol>
            <li>启动自动刷新功能</li>
            <li>发送回调后立即观察"记录变化指示器"</li>
            <li>查看是否出现"签名验证成功"记录</li>
            <li>观察是否有后续的"【诊断】"记录</li>
            <li>检查文件系统日志是否有异常信息</li>
        </ol>
        
        <p><strong>如果记录确实在"签名验证成功"后停止：</strong></p>
        <ul>
            <li>检查是否有"【诊断】"标记的记录</li>
            <li>查看文件系统日志中的错误信息</li>
            <li>检查WordPress错误日志</li>
            <li>确认PHP内存和执行时间限制</li>
        </ul>
    </div>
    
    <div class="section">
        <h3>🔗 相关工具</h3>
        <a href="diagnose-debug-stop.php" class="button">深度诊断工具</a>
        <a href="debug-callback-flow.php" class="button">回调流程分析</a>
        <a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug'); ?>" class="button">详细调试界面</a>
    </div>
    
    <p><small>监控时间: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>