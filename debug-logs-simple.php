<?php
/**
 * 简化的OnePay调试日志查看器
 * 直接访问，无需复杂权限检查
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

// 简单的登录检查
if (!is_user_logged_in()) {
    wp_die('请先登录WordPress后台');
}

// 检查是否是管理员
$current_user = wp_get_current_user();
if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
    wp_die('您没有权限访问此页面');
}

// 加载调试日志器
require_once __DIR__ . '/includes/class-onepay-debug-logger.php';
$debug_logger = OnePay_Debug_Logger::get_instance();

// 获取最近的日志
global $wpdb;
$table_name = $wpdb->prefix . 'onepay_debug_logs';

// 检查表是否存在
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay调试日志</title>
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
        
        .info-box {
            background: #e8f4fd;
            border: 1px solid #72aee6;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f9f9f9;
            font-weight: 600;
        }
        
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-received { background: #d1ecf1; color: #0c5460; }
        
        .log-type-callback { background: #fff3e0; color: #e65100; }
        .log-type-api_request { background: #e3f2fd; color: #1565c0; }
        .log-type-error { background: #ffebee; color: #c62828; }
        
        .button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }
        
        .button:hover {
            background: #005a87;
        }
        
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 OnePay调试日志</h1>
        
        <div class="info-box">
            <strong>当前用户:</strong> <?php echo esc_html($current_user->display_name); ?> 
            (<?php echo esc_html(implode(', ', $current_user->roles)); ?>)
        </div>
        
        <?php if (!$table_exists): ?>
            <div class="error-box">
                <h3>❌ 数据库表不存在</h3>
                <p>调试日志表 <code><?php echo esc_html($table_name); ?></code> 不存在。</p>
                <p>这可能是因为OnePay调试日志器还没有初始化。请访问OnePay设置页面启用调试模式。</p>
                <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" class="button">前往OnePay设置</a></p>
            </div>
        <?php else: ?>
            <div class="success-box">
                ✅ 数据库表已存在
            </div>
            
            <?php
            // 获取统计信息
            $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $callback_logs = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE log_type = %s", 'callback'
            ));
            $today_logs = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE DATE(log_time) = %s", date('Y-m-d')
            ));
            ?>
            
            <h2>📊 统计信息</h2>
            <table>
                <tr><th>总日志数</th><td><?php echo $total_logs; ?></td></tr>
                <tr><th>回调日志数</th><td><?php echo $callback_logs; ?></td></tr>
                <tr><th>今日日志数</th><td><?php echo $today_logs; ?></td></tr>
            </table>
            
            <?php if ($total_logs > 0): ?>
                <h2>📝 最近20条日志</h2>
                <?php
                $recent_logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name} ORDER BY log_time DESC LIMIT %d", 20
                ));
                ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>类型</th>
                            <th>订单</th>
                            <th>状态</th>
                            <th>金额</th>
                            <th>IP</th>
                            <th>执行时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('m-d H:i:s', strtotime($log->log_time)); ?></td>
                                <td>
                                    <span class="log-type-<?php echo esc_attr($log->log_type); ?>">
                                        <?php echo esc_html($log->log_type); ?>
                                    </span>
                                </td>
                                <td><?php echo $log->order_number ?: ($log->order_id ?: '-'); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log->status ?: 'unknown'); ?>">
                                        <?php echo esc_html($log->status ?: '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo $log->amount ? '¥' . number_format($log->amount, 2) : '-'; ?></td>
                                <td><?php echo esc_html($log->user_ip ?: '-'); ?></td>
                                <td><?php echo $log->execution_time ? number_format($log->execution_time * 1000, 1) . 'ms' : '-'; ?></td>
                                <td>
                                    <button onclick="showDetail(<?php echo $log->id; ?>)" class="button">详情</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- 详情弹窗 -->
                <div id="detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; max-width: 90%; max-height: 90%; overflow-y: auto;">
                        <h3>日志详情 <button onclick="closeDetail()" style="float: right; background: #ccc; border: none; padding: 5px 10px;">关闭</button></h3>
                        <div id="detail-content"></div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="info-box">
                    <p>暂无日志记录。请执行一些OnePay操作或测试回调来生成日志。</p>
                    <p><a href="../test-callback.php" class="button">测试回调</a></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <hr>
        <p>
            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" class="button">OnePay设置</a>
            <a href="../test-callback.php" class="button">回调测试</a>
            <a href="../check-database.php" class="button">数据库检查</a>
            <a href="<?php echo admin_url(); ?>" class="button">返回后台</a>
        </p>
    </div>
    
    <script>
        function showDetail(logId) {
            // 发送AJAX请求获取详情
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_log_detail&log_id=' + logId
            })
            .then(response => response.json())
            .then(data => {
                const content = document.getElementById('detail-content');
                if (data.success) {
                    const log = data.data;
                    content.innerHTML = `
                        <h4>基本信息</h4>
                        <p><strong>ID:</strong> ${log.id}</p>
                        <p><strong>时间:</strong> ${log.log_time}</p>
                        <p><strong>类型:</strong> ${log.log_type}</p>
                        <p><strong>状态:</strong> ${log.status || '-'}</p>
                        <p><strong>订单ID:</strong> ${log.order_id || '-'}</p>
                        <p><strong>订单号:</strong> ${log.order_number || '-'}</p>
                        <p><strong>金额:</strong> ${log.amount ? '¥' + log.amount : '-'}</p>
                        <p><strong>IP地址:</strong> ${log.user_ip || '-'}</p>
                        <p><strong>执行时间:</strong> ${log.execution_time ? (log.execution_time * 1000).toFixed(1) + 'ms' : '-'}</p>
                        
                        ${log.request_data ? `<h4>请求数据</h4><pre>${log.request_data}</pre>` : ''}
                        ${log.response_data ? `<h4>响应数据</h4><pre>${log.response_data}</pre>` : ''}
                        ${log.error_message ? `<h4>错误信息</h4><p style="color: red;">${log.error_message}</p>` : ''}
                        ${log.extra_data ? `<h4>额外数据</h4><pre>${log.extra_data}</pre>` : ''}
                    `;
                } else {
                    content.innerHTML = '<p style="color: red;">获取详情失败: ' + data.data + '</p>';
                }
                document.getElementById('detail-modal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('detail-content').innerHTML = '<p style="color: red;">网络错误</p>';
                document.getElementById('detail-modal').style.display = 'block';
            });
        }
        
        function closeDetail() {
            document.getElementById('detail-modal').style.display = 'none';
        }
        
        // 点击外部关闭弹窗
        document.getElementById('detail-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetail();
            }
        });
    </script>
</body>
</html>

<?php
// 添加AJAX处理
add_action('wp_ajax_get_log_detail', function() {
    global $wpdb;
    
    $log_id = intval($_POST['log_id'] ?? 0);
    if (!$log_id) {
        wp_send_json_error('无效的日志ID');
    }
    
    $table_name = $wpdb->prefix . 'onepay_debug_logs';
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d", $log_id
    ), ARRAY_A);
    
    if (!$log) {
        wp_send_json_error('日志不存在');
    }
    
    wp_send_json_success($log);
});
?>