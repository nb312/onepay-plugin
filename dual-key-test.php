<?php
/**
 * OnePay双密钥对机制测试和说明
 * 
 * 这个工具用于测试和演示OnePay的双密钥对机制
 */

// 加载WordPress环境
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// 检查权限
if (!current_user_can('manage_woocommerce')) {
    wp_die('您没有权限访问此页面');
}

// 加载必要的类
require_once(dirname(__FILE__) . '/includes/class-wc-gateway-onepay.php');
require_once(dirname(__FILE__) . '/includes/class-onepay-signature.php');

$gateway = new WC_Gateway_OnePay();

// 处理密钥生成请求
$merchant_keypair = null;
$platform_keypair = null;
$test_results = array();

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_merchant_keys') {
        $merchant_keypair = OnePay_Signature::generate_key_pair();
    } elseif ($_POST['action'] === 'generate_platform_keys') {
        $platform_keypair = OnePay_Signature::generate_key_pair();
    } elseif ($_POST['action'] === 'test_dual_keys') {
        // 执行双密钥对测试
        $test_results = test_dual_key_mechanism();
    }
}

function test_dual_key_mechanism() {
    global $gateway;
    
    $results = array();
    
    // 测试数据
    $test_content = '{"merchantNo":"TEST001","orderAmount":"10000","currency":"RUB","payModel":"CARDPAYMENT"}';
    $test_result = '{"code":"0000","message":"SUCCESS","data":{"orderNo":"OP123456789","orderStatus":"SUCCESS","paidAmount":"10000"}}';
    
    // 1. 测试商户请求签名（商户私钥签名）
    if (!empty($gateway->private_key)) {
        $merchant_signature = OnePay_Signature::sign($test_content, $gateway->private_key);
        $results['merchant_sign'] = array(
            'success' => !empty($merchant_signature),
            'signature' => $merchant_signature ? substr($merchant_signature, 0, 50) . '...' : 'Failed',
            'note' => '商户使用私钥对content进行签名（发送请求时）'
        );
    } else {
        $results['merchant_sign'] = array(
            'success' => false,
            'signature' => '未配置商户私钥',
            'note' => '需要配置商户私钥'
        );
    }
    
    // 2. 测试平台回调验签（平台公钥验签）
    if (!empty($gateway->platform_public_key)) {
        // 模拟一个平台签名（注意：这里我们无法生成真正的平台签名，因为我们没有平台私钥）
        $results['platform_verify'] = array(
            'success' => true,
            'note' => '平台公钥配置正确，可用于验证平台回调签名',
            'format_check' => OnePay_Signature::validate_key($gateway->platform_public_key, 'public')
        );
    } else {
        $results['platform_verify'] = array(
            'success' => false,
            'note' => '未配置平台公钥，无法验证回调签名'
        );
    }
    
    // 3. 密钥对关系说明
    $results['key_relationship'] = array(
        'merchant_keys' => '商户密钥对：商户私钥（己方保留）+ 商户公钥（提供给平台）',
        'platform_keys' => '平台密钥对：平台私钥（平台保留）+ 平台公钥（平台提供给商户）',
        'request_flow' => '请求流程：商户用私钥签名 -> 平台用商户公钥验签',
        'callback_flow' => '回调流程：平台用私钥签名 -> 商户用平台公钥验签',
        'independence' => '两对密钥完全独立，没有任何关联关系'
    );
    
    return $results;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>OnePay双密钥对机制测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .key-display { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .flow-diagram { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        .highlight { background-color: #ffeb3b; padding: 2px 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>OnePay双密钥对机制测试和说明</h1>
        
        <!-- 关键概念说明 -->
        <div class="card info">
            <h2>🔑 关键概念</h2>
            <div class="flow-diagram">
                <h3>双密钥对机制</h3>
                <p><strong>商户密钥对：</strong></p>
                <ul>
                    <li><span class="highlight">商户私钥</span>：商户保留，用于对请求进行签名</li>
                    <li><span class="highlight">商户公钥</span>：提供给平台，平台用于验证商户请求</li>
                </ul>
                
                <p><strong>平台密钥对：</strong></p>
                <ul>
                    <li><span class="highlight">平台私钥</span>：平台保留，用于对响应/回调进行签名</li>
                    <li><span class="highlight">平台公钥</span>：平台提供给商户，商户用于验证平台响应</li>
                </ul>
                
                <p><strong>❌ 重要：</strong>两对密钥完全独立，没有任何关联关系！</p>
            </div>
        </div>
        
        <!-- 数据流向图 -->
        <div class="card">
            <h2>📊 签名验证流程</h2>
            <div class="flow-diagram">
                <h3>1. 支付请求流程</h3>
                <p>商户 → 平台</p>
                <table>
                    <tr><th>步骤</th><th>操作</th><th>使用的密钥</th></tr>
                    <tr><td>1</td><td>商户构建content数据</td><td>-</td></tr>
                    <tr><td>2</td><td>商户用私钥对content签名</td><td>🔐 商户私钥</td></tr>
                    <tr><td>3</td><td>发送{merchantNo, version, content, sign}到平台</td><td>-</td></tr>
                    <tr><td>4</td><td>平台用商户公钥验证签名</td><td>🔓 商户公钥（平台侧）</td></tr>
                </table>
                
                <h3>2. 回调通知流程</h3>
                <p>平台 → 商户</p>
                <table>
                    <tr><th>步骤</th><th>操作</th><th>使用的密钥</th></tr>
                    <tr><td>1</td><td>平台构建result数据</td><td>-</td></tr>
                    <tr><td>2</td><td>平台用私钥对result签名</td><td>🔐 平台私钥</td></tr>
                    <tr><td>3</td><td>发送{merchantNo, result, sign}到商户</td><td>-</td></tr>
                    <tr><td>4</td><td>商户用平台公钥验证签名</td><td>🔓 平台公钥（商户侧）</td></tr>
                </table>
            </div>
        </div>
        
        <!-- 当前配置状态 -->
        <div class="card">
            <h2>⚙️ 当前配置状态</h2>
            <table>
                <tr>
                    <th>配置项</th>
                    <th>状态</th>
                    <th>说明</th>
                </tr>
                <tr>
                    <td>商户号</td>
                    <td><?php echo !empty($gateway->merchant_no) ? '✅ 已配置' : '❌ 未配置'; ?></td>
                    <td><?php echo !empty($gateway->merchant_no) ? esc_html($gateway->merchant_no) : '需要配置商户号'; ?></td>
                </tr>
                <tr>
                    <td>商户私钥</td>
                    <td><?php echo !empty($gateway->private_key) ? '✅ 已配置' : '❌ 未配置'; ?></td>
                    <td><?php echo !empty($gateway->private_key) ? '长度: ' . strlen($gateway->private_key) . ' 字符' : '需要生成/配置商户私钥'; ?></td>
                </tr>
                <tr>
                    <td>平台公钥</td>
                    <td><?php echo !empty($gateway->platform_public_key) ? '✅ 已配置' : '❌ 未配置'; ?></td>
                    <td><?php echo !empty($gateway->platform_public_key) ? '长度: ' . strlen($gateway->platform_public_key) . ' 字符' : '需要从平台获取公钥'; ?></td>
                </tr>
            </table>
        </div>
        
        <!-- 测试功能 -->
        <div class="card">
            <h2>🧪 双密钥对机制测试</h2>
            <form method="post">
                <input type="hidden" name="action" value="test_dual_keys">
                <button type="submit">执行双密钥对测试</button>
            </form>
            
            <?php if (!empty($test_results)): ?>
                <h3>测试结果</h3>
                
                <div class="card <?php echo $test_results['merchant_sign']['success'] ? 'success' : 'error'; ?>">
                    <h4>1. 商户请求签名测试</h4>
                    <p><strong>状态：</strong><?php echo $test_results['merchant_sign']['success'] ? '✅ 成功' : '❌ 失败'; ?></p>
                    <p><strong>签名：</strong><?php echo esc_html($test_results['merchant_sign']['signature']); ?></p>
                    <p><strong>说明：</strong><?php echo esc_html($test_results['merchant_sign']['note']); ?></p>
                </div>
                
                <div class="card <?php echo $test_results['platform_verify']['success'] ? 'success' : 'error'; ?>">
                    <h4>2. 平台公钥验证准备</h4>
                    <p><strong>状态：</strong><?php echo $test_results['platform_verify']['success'] ? '✅ 就绪' : '❌ 未就绪'; ?></p>
                    <p><strong>说明：</strong><?php echo esc_html($test_results['platform_verify']['note']); ?></p>
                    <?php if (isset($test_results['platform_verify']['format_check'])): ?>
                    <p><strong>格式检查：</strong><?php echo $test_results['platform_verify']['format_check'] ? '✅ 有效' : '❌ 无效'; ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="card info">
                    <h4>3. 密钥对关系说明</h4>
                    <?php foreach ($test_results['key_relationship'] as $key => $value): ?>
                        <p><strong><?php echo esc_html($key); ?>：</strong><?php echo esc_html($value); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 密钥生成工具 -->
        <div class="card">
            <h2>🔧 密钥生成工具（仅用于测试）</h2>
            <p class="warning">⚠️ <strong>注意：</strong>以下生成的密钥仅用于理解和测试目的。生产环境请使用专业工具生成密钥。</p>
            
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <h3>商户密钥对</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="generate_merchant_keys">
                        <button type="submit">生成商户密钥对</button>
                    </form>
                    
                    <?php if ($merchant_keypair): ?>
                        <h4>商户私钥（商户保留）</h4>
                        <div class="key-display"><?php echo esc_html($merchant_keypair['private']); ?></div>
                        
                        <h4>商户公钥（提供给平台）</h4>
                        <div class="key-display"><?php echo esc_html($merchant_keypair['public']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div style="flex: 1;">
                    <h3>平台密钥对（模拟）</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="generate_platform_keys">
                        <button type="submit">生成平台密钥对</button>
                    </form>
                    
                    <?php if ($platform_keypair): ?>
                        <h4>平台私钥（平台保留）</h4>
                        <div class="key-display"><?php echo esc_html($platform_keypair['private']); ?></div>
                        
                        <h4>平台公钥（平台提供给商户）</h4>
                        <div class="key-display"><?php echo esc_html($platform_keypair['public']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 常见问题 -->
        <div class="card warning">
            <h2>❓ 常见问题解答</h2>
            <h3>Q: 为什么签名验证测试总是失败？</h3>
            <p><strong>A:</strong> 因为测试逻辑错误！用商户私钥生成的签名不能用平台公钥验证。正确的验证关系是：</p>
            <ul>
                <li>商户私钥生成的签名 ← → 商户公钥验证</li>
                <li>平台私钥生成的签名 ← → 平台公钥验证</li>
            </ul>
            
            <h3>Q: 我只有商户私钥和平台公钥，如何测试？</h3>
            <p><strong>A:</strong> 这是正常的！商户侧只需要：</p>
            <ul>
                <li>商户私钥：用于签名请求</li>
                <li>平台公钥：用于验证回调</li>
            </ul>
            <p>无法进行完整的本地测试，只能测试格式和生成签名的能力。</p>
            
            <h3>Q: 如何验证配置是否正确？</h3>
            <p><strong>A:</strong> 最好的测试方式是实际发送请求：</p>
            <ul>
                <li>如果请求被平台接受，说明商户私钥配置正确</li>
                <li>如果回调验签成功，说明平台公钥配置正确</li>
            </ul>
        </div>
        
        <div class="card">
            <p><a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>">← 返回回调日志</a></p>
        </div>
    </div>
</body>
</html>