<?php
/**
 * OnePay数据库检查工具
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

// 检查权限
if (!current_user_can('manage_woocommerce')) {
    wp_die('您没有权限访问此页面');
}

global $wpdb;

echo "<h1>OnePay数据库检查</h1>";

// 检查OnePay调试日志表
$table_name = $wpdb->prefix . 'onepay_debug_logs';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

echo "<h2>数据库表检查</h2>";
echo "<p><strong>表名:</strong> {$table_name}</p>";

if ($table_exists) {
    echo "<p style='color: green;'>✅ 表已存在</p>";
    
    // 检查表结构
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    echo "<h3>表结构:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>空值</th><th>键</th><th>默认值</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 检查记录数
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "<p><strong>记录总数:</strong> {$count}</p>";
    
    // 检查回调记录数
    $callback_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE log_type = %s",
        'callback'
    ));
    echo "<p><strong>回调记录数:</strong> {$callback_count}</p>";
    
    // 显示最近几条记录
    if ($count > 0) {
        echo "<h3>最近5条记录:</h3>";
        $recent_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, log_time, log_type, order_id, status, error_message 
             FROM {$table_name} 
             ORDER BY log_time DESC 
             LIMIT %d",
            5
        ));
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>时间</th><th>类型</th><th>订单ID</th><th>状态</th><th>错误信息</th></tr>";
        foreach ($recent_logs as $log) {
            echo "<tr>";
            echo "<td>{$log->id}</td>";
            echo "<td>{$log->log_time}</td>";
            echo "<td>{$log->log_type}</td>";
            echo "<td>{$log->order_id}</td>";
            echo "<td>{$log->status}</td>";
            echo "<td>" . (substr($log->error_message ?: '', 0, 50)) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    echo "<p style='color: red;'>❌ 表不存在</p>";
    echo "<p>尝试创建表...</p>";
    
    // 尝试创建表
    require_once ABSPATH . 'wp-content/plugins/onepay/includes/class-onepay-debug-logger.php';
    $debug_logger = OnePay_Debug_Logger::get_instance();
    
    // 检查表是否创建成功
    $table_exists_after = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if ($table_exists_after) {
        echo "<p style='color: green;'>✅ 表创建成功</p>";
    } else {
        echo "<p style='color: red;'>❌ 表创建失败</p>";
        
        // 显示最后的数据库错误
        if ($wpdb->last_error) {
            echo "<p><strong>数据库错误:</strong> " . $wpdb->last_error . "</p>";
        }
    }
}

// 检查WordPress用户权限
echo "<h2>用户权限检查</h2>";
$current_user = wp_get_current_user();
echo "<p><strong>当前用户:</strong> {$current_user->display_name} (ID: {$current_user->ID})</p>";
echo "<p><strong>用户角色:</strong> " . implode(', ', $current_user->roles) . "</p>";

$capabilities = array('manage_woocommerce', 'manage_options', 'edit_shop_orders');
foreach ($capabilities as $cap) {
    $has_cap = current_user_can($cap);
    $status = $has_cap ? '✅' : '❌';
    echo "<p>{$status} {$cap}: " . ($has_cap ? '有权限' : '无权限') . "</p>";
}

// 检查WooCommerce
echo "<h2>WooCommerce检查</h2>";
if (class_exists('WooCommerce')) {
    echo "<p style='color: green;'>✅ WooCommerce已激活</p>";
    echo "<p><strong>版本:</strong> " . WC_VERSION . "</p>";
} else {
    echo "<p style='color: red;'>❌ WooCommerce未激活</p>";
}

// 检查OnePay插件
echo "<h2>OnePay插件检查</h2>";
$plugin_file = 'onepay/onepay.php';
if (is_plugin_active($plugin_file)) {
    echo "<p style='color: green;'>✅ OnePay插件已激活</p>";
} else {
    echo "<p style='color: red;'>❌ OnePay插件未激活</p>";
}

echo "<hr>";
echo "<p><a href='" . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . "'>前往OnePay设置</a></p>";
echo "<p><a href='" . admin_url('admin.php?page=onepay-debug-logs') . "'>前往OnePay调试日志</a></p>";
?>