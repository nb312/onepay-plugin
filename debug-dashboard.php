<?php
/**
 * OnePay调试仪表板
 * 
 * 汇总所有调试工具和状态信息的仪表板
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/debug-dashboard.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 加载必要的类
require_once __DIR__ . '/includes/class-wc-gateway-onepay.php';
require_once __DIR__ . '/includes/class-onepay-debug-logger.php';

$gateway = new WC_Gateway_OnePay();
$debug_logger = OnePay_Debug_Logger::get_instance();

// 获取最近的回调日志
$recent_logs = $debug_logger->get_logs(array(
    'limit' => 10,
    'log_type' => 'async_callback'
));

// 统计信息
$stats_today = $debug_logger->get_logs(array(
    'limit' => 1000,
    'log_type' => 'async_callback',
    'date_from' => date('Y-m-d 00:00:00'),
    'date_to' => date('Y-m-d 23:59:59')
));

$stats_signature_failed = $debug_logger->get_logs(array(
    'limit' => 100,
    'status' => 'signature_failed',
    'date_from' => date('Y-m-d 00:00:00', strtotime('-7 days'))
));

// 检查配置状态
$config_status = array(
    'merchant_no' => !empty($gateway->merchant_no),
    'private_key' => !empty($gateway->private_key),
    'platform_public_key' => !empty($gateway->platform_public_key),
    'api_url' => !empty($gateway->api_url),
    'callback_url' => !empty(home_url('/?wc-api=onepay_callback')),
    'debug_enabled' => $gateway->debug === 'yes'
);

$config_score = array_sum($config_status);
$config_percentage = round(($config_score / count($config_status)) * 100);

// 最近订单
$recent_orders = wc_get_orders(array(
    'limit' => 5,
    'status' => array('pending', 'processing', 'on-hold', 'completed'),
    'meta_query' => array(
        array(
            'key' => '_payment_method',
            'value' => 'onepay',
            'compare' => 'LIKE'
        )
    ),
    'orderby' => 'date',
    'order' => 'DESC'
));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay调试仪表板</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }
        .card h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.4em;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .card h2::before {
            content: '';
            width: 4px;
            height: 24px;
            background: #667eea;
            border-radius: 2px;
            margin-right: 12px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        .status-item {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .status-item.good {
            background: #d4edda;
            color: #155724;
        }
        .status-item.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-item.bad {
            background: #f8d7da;
            color: #721c24;
        }
        .status-number {
            display: block;
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .status-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        .config-progress {
            background: #e9ecef;
            border-radius: 20px;
            height: 20px;
            margin: 15px 0;
            overflow: hidden;
        }
        .config-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 20px;
            transition: width 0.3s ease;
        }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .tool-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-decoration: none;
            color: #333;
            border: 2px solid #e1e5e9;
            transition: all 0.3s ease;
        }
        .tool-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            text-decoration: none;
            color: #333;
        }
        .tool-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: block;
        }
        .tool-title {
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 8px;
        }
        .tool-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        .badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        .badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .empty-state-icon {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎛️ OnePay调试仪表板</h1>
            <p>一站式OnePay集成调试和监控中心</p>
        </div>
        
        <!-- 概览统计 -->
        <div class="dashboard-grid">
            <!-- 配置状态 -->
            <div class="card">
                <h2>配置完整度</h2>
                <div class="config-progress">
                    <div class="config-progress-bar" style="width: <?php echo $config_percentage; ?>%"></div>
                </div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <strong><?php echo $config_percentage; ?>%</strong> 配置完成
                </div>
                <div class="status-grid">
                    <div class="status-item <?php echo $config_status['merchant_no'] ? 'good' : 'bad'; ?>">
                        <span class="status-number"><?php echo $config_status['merchant_no'] ? '✓' : '✗'; ?></span>
                        <span class="status-label">商户号</span>
                    </div>
                    <div class="status-item <?php echo $config_status['private_key'] ? 'good' : 'warning'; ?>">
                        <span class="status-number"><?php echo $config_status['private_key'] ? '✓' : '!'; ?></span>
                        <span class="status-label">私钥</span>
                    </div>
                    <div class="status-item <?php echo $config_status['platform_public_key'] ? 'good' : 'warning'; ?>">
                        <span class="status-number"><?php echo $config_status['platform_public_key'] ? '✓' : '!'; ?></span>
                        <span class="status-label">公钥</span>
                    </div>
                </div>
            </div>
            
            <!-- 今日统计 -->
            <div class="card">
                <h2>今日回调统计</h2>
                <div class="status-grid">
                    <div class="status-item good">
                        <span class="status-number"><?php echo count($stats_today); ?></span>
                        <span class="status-label">总回调</span>
                    </div>
                    <div class="status-item <?php echo count($stats_signature_failed) > 0 ? 'bad' : 'good'; ?>">
                        <span class="status-number"><?php echo count($stats_signature_failed); ?></span>
                        <span class="status-label">验签失败</span>
                    </div>
                    <div class="status-item info">
                        <span class="status-number"><?php echo $gateway->testmode ? 'TEST' : 'LIVE'; ?></span>
                        <span class="status-label">模式</span>
                    </div>
                </div>
            </div>
            
            <!-- 系统状态 -->
            <div class="card">
                <h2>系统状态</h2>
                <div class="status-grid">
                    <div class="status-item <?php echo $config_status['debug_enabled'] ? 'good' : 'warning'; ?>">
                        <span class="status-number"><?php echo $config_status['debug_enabled'] ? 'ON' : 'OFF'; ?></span>
                        <span class="status-label">调试模式</span>
                    </div>
                    <div class="status-item good">
                        <span class="status-number"><?php echo extension_loaded('openssl') ? 'OK' : 'NO'; ?></span>
                        <span class="status-label">OpenSSL</span>
                    </div>
                    <div class="status-item good">
                        <span class="status-number"><?php echo class_exists('WooCommerce') ? 'OK' : 'NO'; ?></span>
                        <span class="status-label">WooCommerce</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 调试工具 -->
        <div class="card">
            <h2>🛠️ 调试工具</h2>
            <div class="tools-grid">
                <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>" class="tool-card">
                    <span class="tool-icon">📋</span>
                    <div class="tool-title">回调日志</div>
                    <div class="tool-description">查看详细的回调处理日志，包括步骤追踪和错误分析</div>
                </a>
                
                <a href="test-callback-signature.php" class="tool-card">
                    <span class="tool-icon">🔐</span>
                    <div class="tool-title">签名验证测试</div>
                    <div class="tool-description">深度调试签名验证机制，分析双密钥对配置</div>
                </a>
                
                <a href="test-mock-callback.php" class="tool-card">
                    <span class="tool-icon">🔄</span>
                    <div class="tool-title">模拟回调测试</div>
                    <div class="tool-description">发送模拟回调请求，测试完整的处理流程</div>
                </a>
                
                <a href="test-signature.php" class="tool-card">
                    <span class="tool-icon">🔑</span>
                    <div class="tool-title">密钥配置测试</div>
                    <div class="tool-description">验证RSA密钥配置和签名生成功能</div>
                </a>
                
                <a href="debug-payment.php" class="tool-card">
                    <span class="tool-icon">💳</span>
                    <div class="tool-title">支付调试</div>
                    <div class="tool-description">测试支付请求和API连接</div>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" class="tool-card">
                    <span class="tool-icon">⚙️</span>
                    <div class="tool-title">配置设置</div>
                    <div class="tool-description">OnePay插件配置和参数设置</div>
                </a>
            </div>
        </div>
        
        <!-- 最近回调日志 -->
        <div class="card">
            <h2>📊 最近回调日志</h2>
            <?php if (!empty($recent_logs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>订单</th>
                        <th>状态</th>
                        <th>金额</th>
                        <th>IP</th>
                        <th>验签</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <?php 
                    $extra_data = $log->extra_data ? json_decode($log->extra_data, true) : array();
                    $signature_status = $extra_data['signature_status'] ?? 'UNKNOWN';
                    ?>
                    <tr>
                        <td><?php echo date('m-d H:i:s', strtotime($log->log_time)); ?></td>
                        <td>
                            <?php if ($log->order_id): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>">
                                    #<?php echo $log->order_id; ?>
                                </a>
                            <?php else: ?>
                                <?php echo $log->order_number ?: '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php 
                                echo $log->status === 'success' ? 'success' : 
                                    ($log->status === 'error' || $log->status === 'signature_failed' ? 'error' : 
                                    ($log->status === 'pending' ? 'warning' : 'info')); 
                            ?>">
                                <?php echo strtoupper($log->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->amount && $log->currency): ?>
                                <?php echo number_format($log->amount, 2); ?> <?php echo $log->currency; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $log->user_ip ?: '-'; ?></td>
                        <td>
                            <span class="badge <?php echo $signature_status === 'PASS' ? 'success' : ($signature_status === 'FAIL' ? 'error' : 'warning'); ?>">
                                <?php echo $signature_status; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align: right; margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>">查看全部日志 →</a>
            </p>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>暂无回调日志</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 最近订单 -->
        <div class="card">
            <h2>🛒 最近OnePay订单</h2>
            <?php if (!empty($recent_orders)): ?>
            <table>
                <thead>
                    <tr>
                        <th>订单号</th>
                        <th>状态</th>
                        <th>金额</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>">
                                #<?php echo $order->get_order_number(); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge <?php 
                                echo $order->get_status() === 'completed' ? 'success' : 
                                    (in_array($order->get_status(), array('failed', 'cancelled')) ? 'error' : 'warning'); 
                            ?>">
                                <?php echo strtoupper($order->get_status()); ?>
                            </span>
                        </td>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                        <td><?php echo $order->get_date_created()->format('m-d H:i'); ?></td>
                        <td>
                            <a href="test-mock-callback.php?order_id=<?php echo $order->get_id(); ?>" style="text-decoration: none;">
                                🔄 测试回调
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🛒</div>
                <p>暂无OnePay订单</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 快速诊断 -->
        <div class="card">
            <h2>🔍 快速诊断</h2>
            <div style="display: grid; gap: 15px;">
                <?php if (!$config_status['merchant_no']): ?>
                <div style="padding: 15px; background: #f8d7da; border-radius: 8px; color: #721c24;">
                    <strong>❌ 商户号未配置</strong><br>
                    请在设置中配置商户号
                </div>
                <?php endif; ?>
                
                <?php if (!$config_status['private_key']): ?>
                <div style="padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404;">
                    <strong>⚠️ 商户私钥未配置</strong><br>
                    无法生成签名，请配置商户私钥
                </div>
                <?php endif; ?>
                
                <?php if (!$config_status['platform_public_key']): ?>
                <div style="padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404;">
                    <strong>⚠️ 平台公钥未配置</strong><br>
                    无法验证回调签名，这是验签失败的主要原因
                </div>
                <?php endif; ?>
                
                <?php if (count($stats_signature_failed) > 0): ?>
                <div style="padding: 15px; background: #f8d7da; border-radius: 8px; color: #721c24;">
                    <strong>❌ 最近7天有 <?php echo count($stats_signature_failed); ?> 次验签失败</strong><br>
                    建议检查平台公钥配置和签名机制
                </div>
                <?php endif; ?>
                
                <?php if (!$config_status['debug_enabled']): ?>
                <div style="padding: 15px; background: #d1ecf1; border-radius: 8px; color: #0c5460;">
                    <strong>💡 建议开启调试模式</strong><br>
                    调试模式可以记录更详细的日志信息
                </div>
                <?php endif; ?>
                
                <?php if ($config_score >= count($config_status)): ?>
                <div style="padding: 15px; background: #d4edda; border-radius: 8px; color: #155724;">
                    <strong>✅ 配置完整</strong><br>
                    所有配置项都已正确设置
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>