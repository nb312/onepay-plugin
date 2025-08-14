<?php
/**
 * OnePay 新UI测试页面
 * 
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-new-ui.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 获取所有OnePay网关
$payment_gateways = WC()->payment_gateways->payment_gateways();
$onepay_gateways = array();

foreach ($payment_gateways as $gateway) {
    if (strpos($gateway->id, 'onepay') === 0) {
        $onepay_gateways[] = $gateway;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay 新UI测试</title>
    <?php wp_head(); ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .header p {
            opacity: 0.9;
            margin-top: 10px;
        }
        
        .test-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .gateway-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .gateway-info {
            flex: 1;
        }
        
        .gateway-name {
            font-weight: 600;
            color: #1a1f36;
            margin-bottom: 5px;
        }
        
        .gateway-id {
            font-size: 0.85em;
            color: #6b7280;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-enabled {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-disabled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .payment-methods {
            margin-top: 30px;
        }
        
        .payment-method-item {
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method-item:hover {
            border-color: #5469d4;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(84, 105, 212, 0.15);
        }
        
        .payment-method-item.selected {
            border-color: #5469d4;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .payment-method-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .payment-method-title {
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .payment-icons {
            display: flex;
            gap: 8px;
        }
        
        .payment-icons img {
            height: 24px;
        }
        
        .payment-method-description {
            margin-top: 8px;
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .info-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #92400e;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #78350f;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✨ OnePay 新UI测试</h1>
            <p>现代化的支付界面体验</p>
        </div>
        
        <!-- 网关状态 -->
        <div class="test-section">
            <h2>📊 网关状态</h2>
            <?php foreach ($onepay_gateways as $gateway): ?>
            <div class="gateway-status">
                <div class="gateway-info">
                    <div class="gateway-name"><?php echo esc_html($gateway->method_title); ?></div>
                    <div class="gateway-id">ID: <?php echo esc_html($gateway->id); ?></div>
                </div>
                <span class="status-badge <?php echo $gateway->enabled === 'yes' ? 'status-enabled' : 'status-disabled'; ?>">
                    <?php echo $gateway->enabled === 'yes' ? '已启用' : '未启用'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 支付方式展示 -->
        <div class="test-section">
            <h2>💳 支付方式选择（模拟）</h2>
            <div class="payment-methods">
                <?php 
                $active_gateways = array_filter($onepay_gateways, function($g) {
                    return $g->enabled === 'yes' && $g->id !== 'onepay';
                });
                
                if (empty($active_gateways)): ?>
                    <p style="color: #6b7280; text-align: center;">暂无启用的支付方式</p>
                <?php else: ?>
                    <?php foreach ($active_gateways as $gateway): ?>
                    <div class="payment-method-item" onclick="this.classList.toggle('selected')">
                        <div class="payment-method-header">
                            <div class="payment-method-title">
                                <?php echo esc_html($gateway->get_title()); ?>
                            </div>
                            <div class="payment-icons">
                                <?php echo $gateway->get_icon(); ?>
                            </div>
                        </div>
                        <?php if ($gateway->get_description()): ?>
                        <div class="payment-method-description">
                            <?php echo esc_html($gateway->get_description()); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 操作按钮 -->
        <div class="test-section">
            <h2>🔧 快速操作</h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" 
                   class="btn btn-primary" target="_blank">
                    配置OnePay
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout'); ?>" 
                   class="btn btn-secondary" target="_blank">
                    支付设置
                </a>
                <a href="<?php echo wc_get_checkout_url(); ?>" 
                   class="btn btn-secondary" target="_blank">
                    前往结账
                </a>
            </div>
            
            <div class="info-box">
                <h4>💡 测试提示</h4>
                <p>1. 首先在"配置OnePay"中设置商户号和密钥</p>
                <p>2. 然后在"支付设置"中分别启用需要的支付方式</p>
                <p>3. 添加商品到购物车后，前往结账页面测试</p>
                <p>4. 每种支付方式都是独立显示的，用户可以直接选择</p>
            </div>
        </div>
        
        <!-- 配置信息 -->
        <div class="test-section">
            <h2>⚙️ 当前配置</h2>
            <?php
            $main_gateway = new WC_Gateway_OnePay();
            $config = array(
                '测试模式' => $main_gateway->testmode ? '✅ 开启' : '❌ 关闭',
                '调试模式' => $main_gateway->debug ? '✅ 开启' : '❌ 关闭',
                '商户号' => !empty($main_gateway->merchant_no) ? '✅ 已配置' : '❌ 未配置',
                '私钥' => !empty($main_gateway->private_key) ? '✅ 已配置' : '❌ 未配置',
                '平台公钥' => !empty($main_gateway->platform_public_key) ? '✅ 已配置' : '❌ 未配置',
            );
            ?>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($config as $key => $value): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px 0; font-weight: 500;"><?php echo $key; ?></td>
                    <td style="padding: 12px 0; text-align: right;"><?php echo $value; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <!-- JavaScript交互 -->
        <script>
            // 模拟支付方式选择交互
            document.querySelectorAll('.payment-method-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    // 移除其他选中状态
                    document.querySelectorAll('.payment-method-item').forEach(function(other) {
                        if (other !== item) {
                            other.classList.remove('selected');
                        }
                    });
                });
            });
        </script>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>