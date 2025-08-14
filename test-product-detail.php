<?php
/**
 * 测试productDetail字段长度
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-product-detail.php
 */

// 测试不同长度的产品描述
$test_cases = array(
    array(
        'name' => '简单订单',
        'order_number' => '12345',
        'items' => array('Product A', 'Product B')
    ),
    array(
        'name' => '长产品名',
        'order_number' => '12345',
        'items' => array(
            'This is a very long product name that might exceed the limit when encoded',
            'Another extremely long product name with special characters: ™®©',
            'Product with unicode: 测试产品 テスト製品 Тестовый продукт'
        )
    ),
    array(
        'name' => '特殊字符',
        'order_number' => '12345',
        'items' => array(
            'Product & Service',
            'Item #123 @ $99.99',
            '50% OFF! Special "Deal"'
        )
    ),
    array(
        'name' => '大量商品',
        'order_number' => '12345',
        'items' => array_fill(0, 20, 'Product Item')
    )
);

function test_product_detail($order_number, $items) {
    // 模拟 get_order_description 函数的逻辑
    $processed_items = array();
    foreach ($items as $product_name) {
        // 清理产品名称，移除特殊字符
        $product_name = preg_replace('/[^\p{L}\p{N}\s\-.,]/u', '', $product_name);
        // 限制单个产品名称长度
        if (mb_strlen($product_name) > 30) {
            $product_name = mb_substr($product_name, 0, 30) . '...';
        }
        $processed_items[] = $product_name;
    }
    
    // 构建基本描述
    $base_description = 'Order #' . $order_number;
    
    // 计算可用于商品描述的字符数
    $max_total_length = 200;
    $current_length = strlen($base_description);
    $available_length = $max_total_length - $current_length - 10;
    
    // 添加商品描述
    $items_description = '';
    if (!empty($processed_items)) {
        $temp_items = array();
        $temp_length = 0;
        
        foreach ($processed_items as $item) {
            $item_with_separator = ($temp_length > 0 ? ', ' : ': ') . $item;
            $item_length = mb_strlen($item_with_separator);
            
            if ($temp_length + $item_length < $available_length) {
                $temp_items[] = $item;
                $temp_length += $item_length;
            } else {
                if (count($temp_items) == 0 && $available_length > 20) {
                    $truncated_item = mb_substr($item, 0, $available_length - 10) . '...';
                    $temp_items[] = $truncated_item;
                }
                break;
            }
        }
        
        if (!empty($temp_items)) {
            $items_description = ': ' . implode(', ', $temp_items);
            if (count($processed_items) > count($temp_items)) {
                $items_description .= '...';
            }
        }
    }
    
    $final_description = $base_description . $items_description;
    
    // 最终检查
    $encoded = urlencode($final_description);
    if (strlen($encoded) > 256) {
        $final_description = 'Order #' . $order_number;
        $encoded = urlencode($final_description);
        
        if (strlen($encoded) > 256) {
            $final_description = 'Order';
            $encoded = urlencode($final_description);
        }
    }
    
    return array(
        'original' => $final_description,
        'encoded' => $encoded,
        'encoded_length' => strlen($encoded)
    );
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>ProductDetail长度测试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
        }
        .test-case {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-case h2 {
            color: #666;
            margin-top: 0;
        }
        .items-list {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
        }
        .result.pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .result.fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .result.warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
        }
        .detail {
            margin: 5px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .encoded {
            word-break: break-all;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .length {
            font-weight: bold;
            font-size: 16px;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.pass {
            background: #28a745;
            color: white;
        }
        .status.fail {
            background: #dc3545;
            color: white;
        }
        .status.warning {
            background: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📏 ProductDetail 长度测试</h1>
        <p>API要求: productDetail字段URL编码后最大长度为256字符</p>
        
        <?php foreach ($test_cases as $test): ?>
            <?php $result = test_product_detail($test['order_number'], $test['items']); ?>
            <div class="test-case">
                <h2><?php echo htmlspecialchars($test['name']); ?></h2>
                
                <div class="items-list">
                    <strong>原始商品列表 (<?php echo count($test['items']); ?> 个商品):</strong>
                    <ul>
                        <?php foreach (array_slice($test['items'], 0, 5) as $item): ?>
                            <li><?php echo htmlspecialchars($item); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($test['items']) > 5): ?>
                            <li>... 还有 <?php echo count($test['items']) - 5; ?> 个商品</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="result <?php echo $result['encoded_length'] <= 256 ? 'pass' : 'fail'; ?>">
                    <div class="detail">
                        <strong>最终描述:</strong> <?php echo htmlspecialchars($result['original']); ?>
                    </div>
                    <div class="detail">
                        <strong>原始长度:</strong> <?php echo strlen($result['original']); ?> 字符
                    </div>
                    <div class="detail">
                        <span class="length">URL编码后长度: <?php echo $result['encoded_length']; ?> / 256</span>
                        <?php if ($result['encoded_length'] <= 256): ?>
                            <span class="status pass">✅ 通过</span>
                        <?php elseif ($result['encoded_length'] <= 300): ?>
                            <span class="status warning">⚠️ 接近限制</span>
                        <?php else: ?>
                            <span class="status fail">❌ 超出限制</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="encoded">
                        <strong>URL编码后:</strong><br>
                        <?php echo htmlspecialchars($result['encoded']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="test-case">
            <h2>自定义测试</h2>
            <form method="post">
                <div style="margin-bottom: 10px;">
                    <label for="custom_order">订单号:</label>
                    <input type="text" id="custom_order" name="custom_order" value="<?php echo isset($_POST['custom_order']) ? htmlspecialchars($_POST['custom_order']) : 'TEST123'; ?>" style="width: 200px; padding: 5px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label for="custom_items">商品列表 (每行一个):</label><br>
                    <textarea id="custom_items" name="custom_items" rows="5" style="width: 100%; padding: 5px;"><?php 
                        echo isset($_POST['custom_items']) ? htmlspecialchars($_POST['custom_items']) : "Test Product 1\nTest Product 2\nTest Product 3"; 
                    ?></textarea>
                </div>
                <button type="submit" style="padding: 10px 20px; background: #5469d4; color: white; border: none; border-radius: 4px; cursor: pointer;">测试</button>
            </form>
            
            <?php if (isset($_POST['custom_order']) && isset($_POST['custom_items'])): ?>
                <?php 
                $custom_items = array_filter(array_map('trim', explode("\n", $_POST['custom_items'])));
                $custom_result = test_product_detail($_POST['custom_order'], $custom_items);
                ?>
                <div class="result <?php echo $custom_result['encoded_length'] <= 256 ? 'pass' : 'fail'; ?>" style="margin-top: 20px;">
                    <div class="detail">
                        <strong>最终描述:</strong> <?php echo htmlspecialchars($custom_result['original']); ?>
                    </div>
                    <div class="detail">
                        <span class="length">URL编码后长度: <?php echo $custom_result['encoded_length']; ?> / 256</span>
                        <?php if ($custom_result['encoded_length'] <= 256): ?>
                            <span class="status pass">✅ 通过</span>
                        <?php else: ?>
                            <span class="status fail">❌ 超出限制</span>
                        <?php endif; ?>
                    </div>
                    <div class="encoded">
                        <?php echo htmlspecialchars($custom_result['encoded']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>