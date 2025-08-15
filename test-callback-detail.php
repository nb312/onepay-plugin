<?php
/**
 * 测试回调详情功能
 * 检查AJAX是否正常工作，查看最近的回调记录
 */

require_once __DIR__ . '/../../../../../../wp-load.php';

// 检查登录状态
if (!is_user_logged_in()) {
    wp_die('请先登录WordPress后台');
}

// 检查权限
if (!current_user_can('manage_woocommerce')) {
    wp_die('您没有权限访问此页面');
}

// 加载调试日志器
require_once __DIR__ . '/includes/class-onepay-debug-logger.php';
$debug_logger = OnePay_Debug_Logger::get_instance();

// 获取最近的回调记录
$recent_callbacks = $debug_logger->get_logs(array(
    'log_type' => 'callback',
    'limit' => 5,
    'order_by' => 'log_time',
    'order' => 'DESC'
));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePay回调详情测试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1000px;
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
        
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        
        .button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .button:hover {
            background: #005a87;
        }
        
        #test-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 OnePay回调详情功能测试</h1>
        
        <div class="info-box">
            <strong>测试目标:</strong> 验证回调详情弹窗功能是否正常工作
        </div>
        
        <?php if (empty($recent_callbacks)): ?>
            <div class="test-section">
                <h3>❌ 无测试数据</h3>
                <p>当前没有回调记录。请先执行一些OnePay操作或测试回调。</p>
                <p><a href="test-callback.php" class="button">生成测试回调</a></p>
            </div>
        <?php else: ?>
            <div class="test-section">
                <h3>📋 最近回调记录</h3>
                <p>以下是最近的5条回调记录，点击"详情"按钮测试弹窗功能：</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>时间</th>
                            <th>订单号</th>
                            <th>状态</th>
                            <th>金额</th>
                            <th>IP</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_callbacks as $callback): ?>
                            <?php
                            $order_status = '';
                            if (!empty($callback->extra_data)) {
                                $extra_data = json_decode($callback->extra_data, true);
                                $order_status = $extra_data['order_status'] ?? '';
                            }
                            
                            $beijing_time = date('m-d H:i:s', strtotime($callback->log_time) + 8 * 3600);
                            ?>
                            <tr>
                                <td><?php echo $callback->id; ?></td>
                                <td><?php echo esc_html($beijing_time); ?></td>
                                <td><?php echo esc_html($callback->order_number ?: '-'); ?></td>
                                <td><?php echo esc_html($order_status ?: $callback->status); ?></td>
                                <td><?php echo $callback->amount ? '¥' . number_format($callback->amount, 2) : '-'; ?></td>
                                <td><?php echo esc_html($callback->user_ip ?: '-'); ?></td>
                                <td>
                                    <button class="button test-detail-btn" data-id="<?php echo $callback->id; ?>">
                                        详情
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="test-section">
                <h3>🧪 AJAX测试</h3>
                <p>测试AJAX请求是否能正常获取回调详情：</p>
                <button class="button" id="test-ajax">测试AJAX请求</button>
                <div id="test-result"></div>
            </div>
        <?php endif; ?>
        
        <hr>
        <p>
            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay'); ?>" class="button">返回OnePay设置</a>
            <a href="debug-logs-simple.php" class="button">查看调试日志</a>
        </p>
    </div>
    
    <!-- 详情弹窗 -->
    <div id="detail-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>回调详情</h2>
            <div id="detail-content"></div>
        </div>
    </div>
    
    <script src="<?php echo site_url('/wp-includes/js/jquery/jquery.min.js'); ?>"></script>
    <script>
        jQuery(document).ready(function($) {
            // 详情按钮点击测试
            $('.test-detail-btn').click(function() {
                var callbackId = $(this).data('id');
                var $button = $(this);
                
                $button.prop('disabled', true).text('加载中...');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'onepay_get_callback_detail',
                    callback_id: callbackId
                }, function(response) {
                    console.log('Response:', response);
                    
                    if (response.success) {
                        showDetail(response.data);
                        $('#test-result').removeClass('error').addClass('success')
                            .html('<strong>✅ 成功:</strong> AJAX请求成功，获取到回调详情').show();
                    } else {
                        $('#test-result').removeClass('success').addClass('error')
                            .html('<strong>❌ 失败:</strong> ' + response.data).show();
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    $('#test-result').removeClass('success').addClass('error')
                        .html('<strong>❌ 网络错误:</strong> ' + error + '<br><small>' + xhr.responseText + '</small>').show();
                }).always(function() {
                    $button.prop('disabled', false).text('详情');
                });
            });
            
            // AJAX测试按钮
            $('#test-ajax').click(function() {
                var firstCallbackId = $('.test-detail-btn').first().data('id');
                if (!firstCallbackId) {
                    $('#test-result').removeClass('success').addClass('error')
                        .html('<strong>❌ 错误:</strong> 没有可测试的回调记录').show();
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('测试中...');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'onepay_get_callback_detail',
                    callback_id: firstCallbackId
                }, function(response) {
                    console.log('Test Response:', response);
                    
                    if (response.success) {
                        $('#test-result').removeClass('error').addClass('success')
                            .html('<strong>✅ AJAX测试成功!</strong><br>' +
                                 '回调ID: ' + response.data.id + '<br>' +
                                 '状态: ' + response.data.status + '<br>' +
                                 '响应完整，数据结构正确').show();
                    } else {
                        $('#test-result').removeClass('success').addClass('error')
                            .html('<strong>❌ AJAX测试失败:</strong> ' + response.data).show();
                    }
                }).fail(function(xhr, status, error) {
                    $('#test-result').removeClass('success').addClass('error')
                        .html('<strong>❌ AJAX测试失败:</strong> ' + error).show();
                }).always(function() {
                    $button.prop('disabled', false).text('测试AJAX请求');
                });
            });
            
            // 显示详情弹窗
            function showDetail(callback) {
                var content = '<h3>基本信息</h3>' +
                    '<p><strong>ID:</strong> ' + callback.id + '</p>' +
                    '<p><strong>时间:</strong> ' + callback.log_time + '</p>' +
                    '<p><strong>类型:</strong> ' + callback.log_type + '</p>' +
                    '<p><strong>状态:</strong> ' + (callback.status || '-') + '</p>' +
                    '<p><strong>订单号:</strong> ' + (callback.order_number || '-') + '</p>' +
                    '<p><strong>金额:</strong> ' + (callback.amount ? '¥' + callback.amount : '-') + '</p>' +
                    '<p><strong>IP地址:</strong> ' + (callback.user_ip || '-') + '</p>';
                
                if (callback.execution_time) {
                    content += '<p><strong>执行时间:</strong> ' + (callback.execution_time * 1000).toFixed(1) + 'ms</p>';
                }
                
                if (callback.request_data) {
                    content += '<h3>请求数据</h3><pre>' + callback.request_data + '</pre>';
                }
                
                if (callback.response_data) {
                    content += '<h3>响应数据</h3><pre>' + callback.response_data + '</pre>';
                }
                
                if (callback.error_message) {
                    content += '<h3>错误信息</h3><p style="color: red;">' + callback.error_message + '</p>';
                }
                
                if (callback.extra_data) {
                    content += '<h3>额外数据</h3><pre>' + callback.extra_data + '</pre>';
                }
                
                $('#detail-content').html(content);
                $('#detail-modal').show();
            }
            
            // 关闭弹窗
            $('.close, .modal').click(function(e) {
                if (e.target === this) {
                    $('#detail-modal').hide();
                }
            });
        });
    </script>
</body>
</html>