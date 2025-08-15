<?php
/**
 * OnePay超详细调试功能测试
 * 
 * 这个文件用于测试详细调试记录功能是否正常工作
 * 访问方式：http://yoursite.com/wp-content/plugins/onepay/test-detailed-debug.php
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
    <title>OnePay超详细调试功能测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .button { 
            background: #0073aa; 
            color: white; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 3px; 
            display: inline-block; 
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 OnePay超详细调试功能测试</h1>
    
    <?php
    // 执行测试
    $tests = array();
    
    // 测试1: 检查详细调试记录器类是否可用
    $tests['class_exists'] = class_exists('OnePay_Detailed_Debug_Recorder');
    
    // 测试2: 创建调试记录器实例
    $debug_recorder = null;
    try {
        if ($tests['class_exists']) {
            $debug_recorder = OnePay_Detailed_Debug_Recorder::get_instance();
            $tests['instance_creation'] = !empty($debug_recorder);
        } else {
            $tests['instance_creation'] = false;
        }
    } catch (Exception $e) {
        $tests['instance_creation'] = false;
        $tests['instance_error'] = $e->getMessage();
    }
    
    // 测试3: 检查数据库表是否存在
    if ($debug_recorder) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        $tests['table_exists'] = $table_exists;
    }
    
    // 测试4: 测试基本记录功能
    if ($debug_recorder) {
        try {
            $request_id = $debug_recorder->start_request('test', array('test_data' => 'hello'));
            $debug_recorder->enter_method('TestClass', 'testMethod', array('param1' => 'value1'));
            $debug_recorder->log_condition('$test_var > 0', true, array('test_var' => 5));
            $debug_recorder->log_variable('test_variable', 'test_value', '测试变量');
            $debug_recorder->log_debug('这是一条测试调试信息');
            $debug_recorder->exit_method('TestClass', 'testMethod', 'test_result');
            $debug_recorder->end_request('success', null);
            
            $tests['basic_recording'] = true;
            $tests['test_request_id'] = $request_id;
        } catch (Exception $e) {
            $tests['basic_recording'] = false;
            $tests['recording_error'] = $e->getMessage();
        }
    }
    
    // 测试5: 检查是否能读取记录
    if ($debug_recorder && isset($tests['test_request_id'])) {
        try {
            $records = $debug_recorder->get_records(array(
                'request_id' => $tests['test_request_id'],
                'limit' => 50
            ));
            $tests['read_records'] = !empty($records);
            $tests['record_count'] = count($records);
        } catch (Exception $e) {
            $tests['read_records'] = false;
            $tests['read_error'] = $e->getMessage();
        }
    }
    
    // 显示测试结果
    ?>
    
    <div class="test-section <?php echo $tests['class_exists'] ? 'success' : 'error'; ?>">
        <h3>✓ 测试1: 调试记录器类检查</h3>
        <p><strong>结果:</strong> <?php echo $tests['class_exists'] ? '✅ 通过' : '❌ 失败'; ?></p>
        <p><strong>说明:</strong> 检查 OnePay_Detailed_Debug_Recorder 类是否正确加载</p>
    </div>
    
    <div class="test-section <?php echo $tests['instance_creation'] ? 'success' : 'error'; ?>">
        <h3>✓ 测试2: 实例创建</h3>
        <p><strong>结果:</strong> <?php echo $tests['instance_creation'] ? '✅ 通过' : '❌ 失败'; ?></p>
        <p><strong>说明:</strong> 检查是否能成功创建调试记录器实例</p>
        <?php if (isset($tests['instance_error'])): ?>
        <p><strong>错误:</strong> <?php echo esc_html($tests['instance_error']); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="test-section <?php echo isset($tests['table_exists']) && $tests['table_exists'] ? 'success' : 'error'; ?>">
        <h3>✓ 测试3: 数据库表检查</h3>
        <p><strong>结果:</strong> <?php echo isset($tests['table_exists']) && $tests['table_exists'] ? '✅ 通过' : '❌ 失败'; ?></p>
        <p><strong>说明:</strong> 检查调试记录数据库表是否存在</p>
        <?php if (isset($tests['table_exists'])): ?>
        <p><strong>表名:</strong> <?php echo $wpdb->prefix; ?>onepay_detailed_debug_records</p>
        <?php endif; ?>
    </div>
    
    <div class="test-section <?php echo isset($tests['basic_recording']) && $tests['basic_recording'] ? 'success' : 'error'; ?>">
        <h3>✓ 测试4: 基本记录功能</h3>
        <p><strong>结果:</strong> <?php echo isset($tests['basic_recording']) && $tests['basic_recording'] ? '✅ 通过' : '❌ 失败'; ?></p>
        <p><strong>说明:</strong> 测试方法进入/退出、条件判断、变量赋值等记录功能</p>
        <?php if (isset($tests['test_request_id'])): ?>
        <p><strong>测试请求ID:</strong> <code><?php echo esc_html($tests['test_request_id']); ?></code></p>
        <?php endif; ?>
        <?php if (isset($tests['recording_error'])): ?>
        <p><strong>错误:</strong> <?php echo esc_html($tests['recording_error']); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="test-section <?php echo isset($tests['read_records']) && $tests['read_records'] ? 'success' : 'error'; ?>">
        <h3>✓ 测试5: 记录读取功能</h3>
        <p><strong>结果:</strong> <?php echo isset($tests['read_records']) && $tests['read_records'] ? '✅ 通过' : '❌ 失败'; ?></p>
        <p><strong>说明:</strong> 测试是否能正确读取调试记录</p>
        <?php if (isset($tests['record_count'])): ?>
        <p><strong>读取到的记录数:</strong> <?php echo $tests['record_count']; ?></p>
        <?php endif; ?>
        <?php if (isset($tests['read_error'])): ?>
        <p><strong>错误:</strong> <?php echo esc_html($tests['read_error']); ?></p>
        <?php endif; ?>
    </div>
    
    <?php if (isset($records) && !empty($records)): ?>
    <div class="test-section info">
        <h3>📋 测试记录详情</h3>
        <p>以下是刚才测试生成的调试记录：</p>
        <pre><?php 
        foreach ($records as $record) {
            echo sprintf("[%s] %s: %s\n", 
                date('H:i:s.u', $record->timestamp),
                strtoupper($record->record_type),
                $record->message
            );
            if ($record->variable_data) {
                $data = json_decode($record->variable_data, true);
                if ($data) {
                    echo "    数据: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
            }
            echo "\n";
        }
        ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="test-section info">
        <h3>🎯 下一步操作</h3>
        <p>如果所有测试都通过，您可以：</p>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug'); ?>" class="button">查看详细调试界面</a></li>
            <li>发送一个测试回调到您的网站来生成真实的调试记录</li>
            <li>在WordPress后台查看完整的回调处理流程</li>
        </ul>
        
        <?php if (!isset($tests['basic_recording']) || !$tests['basic_recording']): ?>
        <p><strong>⚠️ 注意:</strong> 如果测试失败，请检查：</p>
        <ul>
            <li>WordPress数据库连接是否正常</li>
            <li>用户是否有足够的权限</li>
            <li>OnePay插件是否正确安装和激活</li>
            <li>在WooCommerce设置中是否启用了调试模式</li>
        </ul>
        <?php endif; ?>
    </div>
    
    <div class="test-section">
        <h3>⚙️ 调试设置检查</h3>
        <?php
        $gateway_settings = get_option('woocommerce_onepay_settings', array());
        $debug_enabled = isset($gateway_settings['debug']) && $gateway_settings['debug'] === 'yes';
        ?>
        <p><strong>调试模式状态:</strong> 
            <span style="color: <?php echo $debug_enabled ? 'green' : 'red'; ?>">
                <?php echo $debug_enabled ? '✅ 已启用' : '❌ 未启用'; ?>
            </span>
        </p>
        
        <?php if (!$debug_enabled): ?>
        <p><strong>提示:</strong> 要启用超详细调试，请前往 
            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>">
                WooCommerce设置 → 支付 → OnePay
            </a> 
            并开启"调试模式"选项。
        </p>
        <?php endif; ?>
    </div>
    
    <p><small>测试时间: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>