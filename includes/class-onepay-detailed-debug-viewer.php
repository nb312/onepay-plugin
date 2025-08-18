<?php
/**
 * OnePay超详细调试记录查看器
 * 
 * 后台页面，用于查看每个方法调用、if判断、变量变化等详细调试信息
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePay_Detailed_Debug_Viewer {
    
    private $debug_recorder;
    
    public function __construct() {
        require_once dirname(__FILE__) . '/class-onepay-detailed-debug-recorder.php';
        $this->debug_recorder = OnePay_Detailed_Debug_Recorder::get_instance();
    }
    
    /**
     * 显示调试查看页面
     */
    public function display() {
        // 处理操作
        if (isset($_POST['action'])) {
            $this->handle_actions();
        }
        
        // 获取筛选参数
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');
        $request_id = sanitize_text_field($_GET['request_id'] ?? '');
        $record_type = sanitize_text_field($_GET['record_type'] ?? '');
        $view_mode = sanitize_text_field($_GET['view_mode'] ?? 'sessions');
        
        ?>
        <div class="wrap">
            <h1>🔍 OnePay超详细调试记录</h1>
            <p>查看每个方法调用、条件判断、变量变化的详细记录，类似本地调试器功能。</p>
            
            <?php $this->display_navigation($view_mode); ?>
            
            <?php if ($view_mode === 'sessions'): ?>
                <?php $this->display_sessions_list(); ?>
            <?php elseif ($view_mode === 'records' && ($session_id || $request_id)): ?>
                <?php $this->display_debug_records($session_id, $request_id, $record_type); ?>
            <?php else: ?>
                <?php $this->display_overview(); ?>
            <?php endif; ?>
        </div>
        
        <?php $this->add_page_styles(); ?>
        <?php $this->add_page_scripts(); ?>
        <?php
    }
    
    /**
     * 显示导航菜单
     */
    private function display_navigation($current_view) {
        ?>
        <div class="debug-nav">
            <a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug'); ?>" 
               class="nav-tab <?php echo $current_view === 'overview' ? 'nav-tab-active' : ''; ?>">
                📊 概览
            </a>
            <a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug&view_mode=sessions'); ?>" 
               class="nav-tab <?php echo $current_view === 'sessions' ? 'nav-tab-active' : ''; ?>">
                📋 会话列表
            </a>
            <div class="nav-actions">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="cleanup">
                    <button type="submit" class="button" onclick="return confirm('确定要清理7天前的记录吗？')">
                        🗑️ 清理旧记录
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * 显示概览信息
     */
    private function display_overview() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onepay_detailed_debug_records';
        
        // 获取统计数据
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                MIN(created_at) as first_record,
                MAX(created_at) as last_record
            FROM {$table_name}
        ");
        
        $type_stats = $wpdb->get_results("
            SELECT record_type, COUNT(*) as count 
            FROM {$table_name} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY record_type 
            ORDER BY count DESC
        ");
        
        ?>
        <div class="debug-overview">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📊 总记录数</h3>
                    <div class="stat-number"><?php echo number_format($stats->total_records ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>🔗 总会话数</h3>
                    <div class="stat-number"><?php echo number_format($stats->total_sessions ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>📅 活跃天数</h3>
                    <div class="stat-number"><?php echo $stats->active_days ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>⏰ 最新记录</h3>
                    <div class="stat-time"><?php echo $stats->last_record ? date('Y-m-d H:i:s', strtotime($stats->last_record)) : '-'; ?></div>
                </div>
            </div>
            
            <div class="type-distribution">
                <h3>📈 最近7天记录类型分布</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>记录类型</th>
                            <th>数量</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($type_stats as $type_stat): ?>
                        <tr>
                            <td><code><?php echo esc_html($type_stat->record_type); ?></code></td>
                            <td><?php echo number_format($type_stat->count); ?></td>
                            <td><?php echo $this->get_record_type_description($type_stat->record_type); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * 显示会话列表
     */
    private function display_sessions_list() {
        $sessions = $this->debug_recorder->get_recent_sessions(50);
        
        ?>
        <div class="debug-sessions">
            <h2>最近的调试会话</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>会话ID</th>
                        <th>请求ID</th>
                        <th>客户端IP</th>
                        <th>开始时间</th>
                        <th>结束时间</th>
                        <th>记录数</th>
                        <th>错误数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html(substr($session->session_id, -12)); ?></code>
                        </td>
                        <td>
                            <code><?php echo esc_html(substr($session->request_id ?? '', -12)); ?></code>
                        </td>
                        <td>
                            <code><?php echo esc_html($session->client_ip ?? '-'); ?></code>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($session->start_time)); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($session->end_time)); ?></td>
                        <td><?php echo $session->record_count; ?></td>
                        <td>
                            <?php if ($session->error_count > 0): ?>
                                <span class="error-count"><?php echo $session->error_count; ?></span>
                            <?php else: ?>
                                <span class="no-errors">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=onepay-detailed-debug&view_mode=records&session_id=' . urlencode($session->session_id) . '&request_id=' . urlencode($session->request_id ?? '')); ?>" 
                               class="button button-small">
                                🔍 查看详情
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * 显示详细调试记录
     */
    private function display_debug_records($session_id, $request_id, $record_type) {
        $filters = array();
        if ($session_id) $filters['session_id'] = $session_id;
        if ($request_id) $filters['request_id'] = $request_id;
        if ($record_type) $filters['record_type'] = $record_type;
        $filters['limit'] = 2000;
        
        $records = $this->debug_recorder->get_records($filters);
        
        ?>
        <div class="debug-records">
            <div class="records-header">
                <h2>调试记录详情</h2>
                <div class="filters">
                    <form method="get">
                        <input type="hidden" name="page" value="onepay-detailed-debug">
                        <input type="hidden" name="view_mode" value="records">
                        <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request_id); ?>">
                        
                        <select name="record_type" onchange="this.form.submit()">
                            <option value="">所有类型</option>
                            <option value="method_enter" <?php selected($record_type, 'method_enter'); ?>>方法进入</option>
                            <option value="method_exit" <?php selected($record_type, 'method_exit'); ?>>方法退出</option>
                            <option value="condition" <?php selected($record_type, 'condition'); ?>>条件判断</option>
                            <option value="variable" <?php selected($record_type, 'variable'); ?>>变量赋值</option>
                            <option value="debug" <?php selected($record_type, 'debug'); ?>>调试信息</option>
                            <option value="error" <?php selected($record_type, 'error'); ?>>错误</option>
                        </select>
                    </form>
                </div>
            </div>
            
            <div class="records-timeline">
                <?php foreach ($records as $record): ?>
                <div class="record-item record-<?php echo esc_attr($record->record_type); ?>" data-depth="<?php echo esc_attr($record->execution_depth); ?>">
                    <div class="record-header">
                        <span class="record-time"><?php echo date('H:i:s.u', $record->timestamp); ?></span>
                        <span class="record-type"><?php echo esc_html($record->record_type); ?></span>
                        <span class="record-depth">深度: <?php echo $record->execution_depth; ?></span>
                        <?php if ($record->execution_time): ?>
                            <span class="record-duration"><?php echo round($record->execution_time * 1000, 2); ?>ms</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="record-content">
                        <div class="record-message"><?php echo esc_html($record->message); ?></div>
                        
                        <?php if ($record->class_name || $record->method_name): ?>
                        <div class="record-location">
                            <strong>位置:</strong> <?php echo esc_html($record->class_name); ?>::<?php echo esc_html($record->method_name); ?>()
                            <?php if ($record->file_path): ?>
                                <br><small><?php echo esc_html(basename($record->file_path)); ?>:<?php echo $record->line_number; ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record->variable_data): ?>
                        <div class="record-data">
                            <details>
                                <summary>📋 数据详情</summary>
                                <pre class="debug-json"><?php echo esc_html($this->format_json($record->variable_data)); ?></pre>
                            </details>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record->condition_data): ?>
                        <div class="record-condition">
                            <details>
                                <summary>🔍 条件详情</summary>
                                <pre class="debug-json"><?php echo esc_html($this->format_json($record->condition_data)); ?></pre>
                            </details>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record->memory_usage): ?>
                        <div class="record-memory">
                            <small>内存: <?php echo $this->format_bytes($record->memory_usage); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 处理操作
     */
    private function handle_actions() {
        if ($_POST['action'] === 'cleanup') {
            $this->debug_recorder->cleanup_old_records(7);
            echo '<div class="notice notice-success"><p>已清理7天前的旧记录。</p></div>';
        }
    }
    
    /**
     * 获取记录类型说明
     */
    private function get_record_type_description($type) {
        $descriptions = array(
            'request_start' => '请求开始',
            'request_end' => '请求结束',
            'method_enter' => '进入方法',
            'method_exit' => '退出方法',
            'condition' => '条件判断',
            'variable' => '变量赋值',
            'debug' => '调试信息',
            'error' => '错误记录',
            'http_request' => 'HTTP请求'
        );
        
        return $descriptions[$type] ?? $type;
    }
    
    /**
     * 格式化JSON
     */
    private function format_json($json_string) {
        $data = json_decode($json_string, true);
        if ($data) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $json_string;
    }
    
    /**
     * 格式化字节数
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * 添加页面样式
     */
    private function add_page_styles() {
        ?>
        <style>
        .debug-nav {
            margin: 20px 0;
            border-bottom: 1px solid #ccd0d4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-actions {
            display: flex;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccd0d4;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stat-time {
            font-size: 14px;
            color: #666;
        }
        
        .type-distribution {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccd0d4;
            margin: 20px 0;
        }
        
        .error-count {
            background: #dc3232;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .no-errors {
            color: #46b450;
        }
        
        .records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .records-timeline {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .record-item {
            border-bottom: 1px solid #eee;
            padding: 15px;
            position: relative;
        }
        
        .record-item:last-child {
            border-bottom: none;
        }
        
        .record-item[data-depth="1"] { margin-left: 20px; }
        .record-item[data-depth="2"] { margin-left: 40px; }
        .record-item[data-depth="3"] { margin-left: 60px; }
        .record-item[data-depth="4"] { margin-left: 80px; }
        
        .record-method_enter {
            border-left: 4px solid #0073aa;
        }
        
        .record-method_exit {
            border-left: 4px solid #46b450;
        }
        
        .record-condition {
            border-left: 4px solid #ffb900;
        }
        
        .record-variable {
            border-left: 4px solid #826eb4;
        }
        
        .record-error {
            border-left: 4px solid #dc3232;
            background: #fef7f7;
        }
        
        .record-debug {
            border-left: 4px solid #666;
        }
        
        .record-header {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
            color: #666;
        }
        
        .record-time {
            font-family: monospace;
            background: #f0f0f0;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .record-type {
            background: #0073aa;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        
        .record-depth {
            color: #999;
        }
        
        .record-duration {
            color: #46b450;
            font-weight: bold;
        }
        
        .record-message {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .record-location {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
        }
        
        .record-data, .record-condition {
            margin: 10px 0;
        }
        
        .debug-json {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
            line-height: 1.4;
            max-height: 200px;
            overflow: auto;
            margin: 5px 0;
        }
        
        .record-memory {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }
        
        details summary {
            cursor: pointer;
            font-weight: 500;
            padding: 5px 0;
        }
        
        details[open] summary {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * 添加页面脚本
     */
    private function add_page_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // 自动展开错误记录
            $('.record-error details').attr('open', true);
            
            // 添加搜索功能
            if ($('.records-timeline').length > 0) {
                $('.records-header').prepend(
                    '<div style="flex: 1; margin-right: 20px;">' +
                    '<input type="text" id="record-search" placeholder="搜索记录..." style="width: 100%; padding: 5px;">' +
                    '</div>'
                );
                
                $('#record-search').on('input', function() {
                    var searchTerm = $(this).val().toLowerCase();
                    $('.record-item').each(function() {
                        var text = $(this).text().toLowerCase();
                        if (text.indexOf(searchTerm) === -1) {
                            $(this).hide();
                        } else {
                            $(this).show();
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }
}