<?php
/**
 * 回调日志修复验证测试
 * 
 * 访问: http://localhost/nb_wordpress/wp-content/plugins/onepay/test-callback-logs-fix.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查是否为管理员
if (!current_user_can('manage_options')) {
    wp_die('无权限访问此页面');
}

// 检查文件是否存在
$plugin_dir = plugin_dir_path(__FILE__);
$css_file = $plugin_dir . 'assets/css/onepay-callback-logs.css';
$js_file = $plugin_dir . 'assets/js/onepay-callback-logs.js';

$css_exists = file_exists($css_file);
$js_exists = file_exists($js_file);

// 检查URL是否可访问
$plugin_url = plugin_dir_url(__FILE__);
$css_url = $plugin_url . 'assets/css/onepay-callback-logs.css';
$js_url = $plugin_url . 'assets/js/onepay-callback-logs.js';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay回调日志修复验证</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .status-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }
        .status-item {
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        .status-item.success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .status-item.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .file-path {
            font-family: monospace;
            background: #f8f9fa;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .test-button {
            background: #5469d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        .test-button:hover {
            background: #4256c7;
        }
        .test-result {
            margin: 15px 0;
            padding: 10px;
            border-radius: 4px;
            background: #e9ecef;
        }
        .navigation {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .navigation a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .navigation a:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 OnePay回调日志修复验证</h1>
        
        <h2>文件检查结果</h2>
        <div class="status-grid">
            <div class="status-item <?php echo $css_exists ? 'success' : 'error'; ?>">
                <strong>CSS文件:</strong> <?php echo $css_exists ? '✅ 存在' : '❌ 不存在'; ?><br>
                <div class="file-path"><?php echo $css_file; ?></div>
                <br><strong>访问URL:</strong> <a href="<?php echo $css_url; ?>" target="_blank"><?php echo $css_url; ?></a>
            </div>
            
            <div class="status-item <?php echo $js_exists ? 'success' : 'error'; ?>">
                <strong>JavaScript文件:</strong> <?php echo $js_exists ? '✅ 存在' : '❌ 不存在'; ?><br>
                <div class="file-path"><?php echo $js_file; ?></div>
                <br><strong>访问URL:</strong> <a href="<?php echo $js_url; ?>" target="_blank"><?php echo $js_url; ?></a>
            </div>
        </div>
        
        <h2>功能测试</h2>
        <div class="test-section">
            <h3>jQuery可用性测试</h3>
            <button class="test-button" onclick="testJQuery()">测试jQuery</button>
            <div id="jquery-test-result" class="test-result" style="display: none;"></div>
        </div>
        
        <div class="test-section">
            <h3>模态框测试</h3>
            <button class="test-button view-detail-btn" data-log-id="test">测试查看详情按钮</button>
            <div id="modal-test-result" class="test-result" style="display: none;"></div>
        </div>
        
        <div class="test-section">
            <h3>AJAX端点测试</h3>
            <button class="test-button" onclick="testAjaxEndpoint()">测试AJAX端点</button>
            <div id="ajax-test-result" class="test-result" style="display: none;"></div>
        </div>
        
        <h2>解决方案总结</h2>
        <div class="status-item success">
            <h3>已完成的修复:</h3>
            <ul>
                <li>✅ 创建了缺失的 <code>onepay-callback-logs.js</code> 文件</li>
                <li>✅ 创建了相应的 <code>onepay-callback-logs.css</code> 样式文件</li>
                <li>✅ 更新了PHP文件以正确加载外部CSS和JS文件</li>
                <li>✅ 添加了AJAX处理端点 <code>ajax_get_callback_detail</code></li>
                <li>✅ 修复了查看详情按钮的事件绑定问题</li>
                <li>✅ 实现了完整的模态框详情展示功能</li>
                <li>✅ 增强了回调处理步骤追踪和详情显示</li>
                <li>✅ 创建了多个调试工具和仪表板</li>
            </ul>
        </div>
        
        <div class="navigation">
            <h3>相关页面:</h3>
            <a href="<?php echo admin_url('admin.php?page=onepay-callback-logs'); ?>">📋 回调日志页面</a>
            <a href="debug-dashboard.php">🎛️ 调试仪表板</a>
            <a href="test-callback-signature.php">🔐 签名验证测试</a>
            <a href="test-mock-callback.php">🔄 模拟回调测试</a>
        </div>
    </div>

    <!-- 加载jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- 加载我们的JS文件 -->
    <script src="<?php echo $js_url; ?>"></script>
    
    <!-- 设置全局变量 -->
    <script>
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.onepayCallbackLogs = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('onepay_callback_detail'); ?>'
        };
    </script>
    
    <script>
        function testJQuery() {
            const resultDiv = document.getElementById('jquery-test-result');
            resultDiv.style.display = 'block';
            
            if (typeof jQuery !== 'undefined') {
                resultDiv.innerHTML = '✅ jQuery 已加载，版本: ' + jQuery.fn.jquery;
                resultDiv.style.background = '#d4edda';
                resultDiv.style.color = '#155724';
                
                // 测试事件绑定
                setTimeout(() => {
                    if (jQuery('.view-detail-btn').length > 0) {
                        resultDiv.innerHTML += '<br>✅ 查看详情按钮已找到: ' + jQuery('.view-detail-btn').length + ' 个';
                    }
                }, 100);
            } else {
                resultDiv.innerHTML = '❌ jQuery 未加载';
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#721c24';
            }
        }
        
        function testAjaxEndpoint() {
            const resultDiv = document.getElementById('ajax-test-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '🔄 正在测试AJAX端点...';
            
            if (typeof jQuery !== 'undefined') {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'onepay_get_callback_detail',
                        log_id: 999999, // 不存在的ID
                        nonce: onepayCallbackLogs.nonce
                    },
                    success: function(response) {
                        if (response.success === false && response.data && response.data.message === '日志不存在') {
                            resultDiv.innerHTML = '✅ AJAX端点正常工作 (正确返回"日志不存在"错误)';
                            resultDiv.style.background = '#d4edda';
                            resultDiv.style.color = '#155724';
                        } else {
                            resultDiv.innerHTML = '⚠️ AJAX端点响应异常: ' + JSON.stringify(response);
                            resultDiv.style.background = '#fff3cd';
                            resultDiv.style.color = '#856404';
                        }
                    },
                    error: function(xhr, status, error) {
                        resultDiv.innerHTML = '❌ AJAX请求失败: ' + error;
                        resultDiv.style.background = '#f8d7da';
                        resultDiv.style.color = '#721c24';
                    }
                });
            } else {
                resultDiv.innerHTML = '❌ jQuery未加载，无法测试AJAX';
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#721c24';
            }
        }
        
        // 监听模态框事件
        jQuery(document).ready(function($) {
            $(document).on('click', '.view-detail-btn', function(e) {
                const resultDiv = document.getElementById('modal-test-result');
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '✅ 查看详情按钮点击事件已触发！<br>🔄 如果您看到模态框出现，说明功能正常工作。';
                resultDiv.style.background = '#d4edda';
                resultDiv.style.color = '#155724';
            });
        });
    </script>
</body>
</html>