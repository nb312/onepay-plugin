<?php
/**
 * 简单的API测试脚本
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-simple.php
 */

// API配置
$api_url = 'http://110.42.152.219:8083/nh-gateway/v2/card/payment';
$merchant_no = 'TEST001';

// 构建请求数据
$content_data = array(
    'timeStamp' => strval(time() * 1000),
    'orderAmount' => '10000', // 100.00
    'payType' => 'RUSSIA_PAY',
    'productDetail' => urlencode('Test Order'),
    'callbackUrl' => 'http://localhost/callback',
    'payModel' => 'FPS',
    'noticeUrl' => 'http://localhost/notice',
    'merchantOrderNo' => 'TEST_' . time(),
    'merchantNo' => $merchant_no,
    'userIp' => '127.0.0.1',
    'userId' => '1',
    'customParam' => urlencode('test_order')
);

$content_json = json_encode($content_data, JSON_UNESCAPED_SLASHES);

$request_data = array(
    'merchantNo' => $merchant_no,
    'version' => '2.0',
    'content' => $content_json,
    'sign' => 'test_signature_' . md5($content_json)
);

$request_json = json_encode($request_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// 发送请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $request_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($request_json)
));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>OnePay API简单测试</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            background: #2d2d30;
            border-radius: 5px;
        }
        h2 {
            color: #9cdcfe;
            margin-top: 0;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #1e1e1e;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        .error {
            color: #f48771;
        }
        .success {
            color: #6a9955;
        }
        .warning {
            color: #dcdcaa;
        }
    </style>
</head>
<body>
    <h1>OnePay API简单测试</h1>
    
    <div class="section">
        <h2>请求信息</h2>
        <p><strong>URL:</strong> <?php echo htmlspecialchars($api_url); ?></p>
        <p><strong>商户号:</strong> <?php echo htmlspecialchars($merchant_no); ?></p>
        <p><strong>请求方法:</strong> POST</p>
    </div>
    
    <div class="section">
        <h2>请求内容</h2>
        <h3>Content数据:</h3>
        <pre><?php echo htmlspecialchars(json_encode($content_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
        
        <h3>完整请求数据:</h3>
        <pre><?php echo htmlspecialchars(json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
    </div>
    
    <div class="section">
        <h2>发送请求...</h2>
        <?php
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        curl_close($ch);
        
        if ($curl_errno) {
            echo '<p class="error">CURL错误 (' . $curl_errno . '): ' . htmlspecialchars($curl_error) . '</p>';
        } else {
            echo '<p class="success">请求成功 (耗时: ' . $duration . 'ms)</p>';
            echo '<p><strong>HTTP状态码:</strong> ' . $http_code . '</p>';
            echo '<p><strong>响应长度:</strong> ' . strlen($response) . ' 字节</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>响应数据</h2>
        <?php if ($response): ?>
            <h3>原始响应:</h3>
            <pre><?php 
                // 显示原始响应，包括不可见字符
                $display_response = $response;
                // 替换不可见字符为可见表示
                $display_response = str_replace("\r\n", "\\r\\n\n", $display_response);
                $display_response = str_replace("\n", "\\n\n", $display_response);
                $display_response = str_replace("\r", "\\r", $display_response);
                $display_response = str_replace("\t", "\\t", $display_response);
                echo htmlspecialchars($display_response); 
            ?></pre>
            
            <?php
            // 尝试解析JSON
            $json_data = json_decode($response, true);
            $json_error = json_last_error();
            
            if ($json_error === JSON_ERROR_NONE && $json_data !== null):
            ?>
                <h3 class="success">JSON解析成功:</h3>
                <pre><?php echo htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                
                <?php if (isset($json_data['result'])): ?>
                    <h3>解析result字段:</h3>
                    <?php
                    $result_data = json_decode($json_data['result'], true);
                    if ($result_data !== null):
                    ?>
                        <pre class="success"><?php echo htmlspecialchars(json_encode($result_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        
                        <?php if (isset($result_data['code'])): ?>
                            <p><strong>响应代码:</strong> <?php echo htmlspecialchars($result_data['code']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($result_data['message'])): ?>
                            <p><strong>响应消息:</strong> <?php echo htmlspecialchars($result_data['message']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($result_data['data'])): ?>
                            <h4>Data内容:</h4>
                            <pre><?php echo htmlspecialchars(json_encode($result_data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="error">无法解析result字段: <?php echo json_last_error_msg(); ?></p>
                        <p>Result原始值:</p>
                        <pre><?php echo htmlspecialchars($json_data['result']); ?></pre>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="warning">响应中没有result字段</p>
                <?php endif; ?>
                
            <?php else: ?>
                <h3 class="error">JSON解析失败:</h3>
                <p>错误: <?php echo json_last_error_msg(); ?> (错误码: <?php echo $json_error; ?>)</p>
                
                <?php
                // 尝试清理并重新解析
                $cleaned = trim($response);
                // 移除BOM
                $cleaned = str_replace("\xEF\xBB\xBF", '', $cleaned);
                // 移除控制字符
                $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleaned);
                
                $json_data_cleaned = json_decode($cleaned, true);
                if ($json_data_cleaned !== null):
                ?>
                    <h3 class="warning">清理后解析成功:</h3>
                    <pre><?php echo htmlspecialchars(json_encode($json_data_cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                <?php else: ?>
                    <p class="error">清理后仍无法解析</p>
                    
                    <?php
                    // 显示响应的十六进制
                    echo '<h4>响应的十六进制表示（前100字节）:</h4>';
                    echo '<pre>';
                    $hex = bin2hex(substr($response, 0, 100));
                    for ($i = 0; $i < strlen($hex); $i += 2) {
                        echo substr($hex, $i, 2) . ' ';
                        if (($i + 2) % 32 == 0) echo "\n";
                    }
                    echo '</pre>';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">没有收到响应</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>诊断建议</h2>
        <?php if ($curl_errno): ?>
            <p class="error">• 连接失败，请检查网络和服务器状态</p>
        <?php elseif ($http_code == 0): ?>
            <p class="error">• 无法获取HTTP状态码，可能是服务器未响应</p>
        <?php elseif ($http_code >= 500): ?>
            <p class="error">• 服务器错误 (5xx)，请联系API提供方</p>
        <?php elseif ($http_code >= 400): ?>
            <p class="warning">• 客户端错误 (4xx)，请检查请求参数</p>
        <?php elseif ($http_code == 200): ?>
            <?php if ($json_error !== JSON_ERROR_NONE): ?>
                <p class="error">• HTTP 200但JSON解析失败，API返回格式可能有问题</p>
            <?php elseif (isset($result_data) && isset($result_data['code']) && $result_data['code'] === '0000'): ?>
                <p class="success">• 请求成功处理</p>
            <?php else: ?>
                <p class="warning">• 请求已送达但可能有业务错误</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>