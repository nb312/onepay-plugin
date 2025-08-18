<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay IP白名单管理类
 * 
 * 处理回调请求的IP地址验证和白名单管理
 */
class OnePay_IP_Whitelist {
    
    /**
     * 默认的OnePay服务器IP白名单
     */
    const DEFAULT_WHITELIST_IPS = array(
        '132.145.68.50',
        '152.67.142.250', 
        '144.21.50.178',
        '150.230.124.245',
        '140.238.90.149',
        '140.238.97.36'
    );
    
    /**
     * 单例模式实例
     */
    private static $instance = null;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        // 私有构造函数，防止直接实例化
    }
    
    /**
     * 检查IP地址是否在白名单中
     * 
     * @param string $ip 要检查的IP地址
     * @param array|null $custom_whitelist 自定义白名单，如果为null则使用配置的白名单
     * @return bool 是否在白名单中
     */
    public function is_ip_whitelisted($ip, $custom_whitelist = null) {
        // 获取白名单IP列表
        $whitelist = $custom_whitelist !== null ? $custom_whitelist : $this->get_whitelist_ips();
        
        // 如果白名单为空，允许所有IP（向后兼容）
        if (empty($whitelist)) {
            return true;
        }
        
        // 验证IP地址格式
        if (!$this->is_valid_ip($ip)) {
            return false;
        }
        
        foreach ($whitelist as $whitelist_ip) {
            $whitelist_ip = trim($whitelist_ip);
            
            // 跳过空行
            if (empty($whitelist_ip)) {
                continue;
            }
            
            // 检查是否为CIDR格式
            if (strpos($whitelist_ip, '/') !== false) {
                if ($this->ip_in_cidr($ip, $whitelist_ip)) {
                    return true;
                }
            } else {
                // 直接IP地址匹配
                if ($ip === $whitelist_ip) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 验证IP地址是否有效
     * 
     * @param string $ip IP地址
     * @return bool 是否有效
     */
    public function is_valid_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ||
               filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
    
    /**
     * 检查IP是否在CIDR网段中
     * 
     * @param string $ip IP地址
     * @param string $cidr CIDR格式的网段
     * @return bool 是否在网段中
     */
    public function ip_in_cidr($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        
        // 验证CIDR格式
        if (!$this->is_valid_ip($subnet) || !is_numeric($mask)) {
            return false;
        }
        
        // 支持IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = ~((1 << (32 - $mask)) - 1);
            
            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        }
        
        // TODO: 添加IPv6支持（如需要）
        return false;
    }
    
    /**
     * 获取配置的白名单IP列表
     * 
     * @return array IP地址数组
     */
    public function get_whitelist_ips() {
        // 获取OnePay网关设置
        $gateway = new WC_Gateway_OnePay();
        $whitelist_enabled = $gateway->get_option('ip_whitelist_enabled', 'yes');
        
        // 如果白名单功能被禁用，返回空数组（允许所有IP）
        if ($whitelist_enabled !== 'yes') {
            return array();
        }
        
        $custom_ips = $gateway->get_option('ip_whitelist', '');
        
        // 如果没有自定义IP，使用默认列表
        if (empty($custom_ips)) {
            return self::DEFAULT_WHITELIST_IPS;
        }
        
        // 解析自定义IP列表
        $ips = array_filter(array_map('trim', explode("\n", $custom_ips)));
        
        // 合并默认IP和自定义IP
        $use_defaults = $gateway->get_option('ip_whitelist_include_defaults', 'yes');
        if ($use_defaults === 'yes') {
            $ips = array_merge(self::DEFAULT_WHITELIST_IPS, $ips);
        }
        
        // 去重
        return array_unique($ips);
    }
    
    /**
     * 验证IP白名单格式
     * 
     * @param string $whitelist_text 白名单文本（每行一个IP）
     * @return array 验证结果数组
     */
    public function validate_whitelist($whitelist_text) {
        $result = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'valid_ips' => array(),
            'invalid_ips' => array()
        );
        
        $lines = array_filter(array_map('trim', explode("\n", $whitelist_text)));
        
        foreach ($lines as $line_number => $line) {
            $line_number++; // 从1开始计数
            
            // 跳过注释行
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // 检查CIDR格式
            if (strpos($line, '/') !== false) {
                list($ip, $mask) = explode('/', $line, 2);
                
                if (!$this->is_valid_ip(trim($ip))) {
                    $result['errors'][] = "第{$line_number}行: 无效的IP地址 '{$ip}'";
                    $result['invalid_ips'][] = $line;
                } elseif (!is_numeric($mask) || $mask < 0 || $mask > 32) {
                    $result['errors'][] = "第{$line_number}行: 无效的子网掩码 '/{$mask}'";
                    $result['invalid_ips'][] = $line;
                } else {
                    $result['valid_ips'][] = $line;
                }
            } else {
                // 普通IP地址
                if (!$this->is_valid_ip($line)) {
                    $result['errors'][] = "第{$line_number}行: 无效的IP地址 '{$line}'";
                    $result['invalid_ips'][] = $line;
                } else {
                    $result['valid_ips'][] = $line;
                }
            }
        }
        
        if (!empty($result['errors'])) {
            $result['valid'] = false;
        }
        
        return $result;
    }
    
    /**
     * 获取客户端真实IP地址
     * 考虑代理服务器和负载均衡器
     * 
     * @return string 客户端IP地址
     */
    public function get_client_ip() {
        $ip_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    // 验证IP并排除私有IP和保留IP
                    if ($this->is_valid_ip($ip) && 
                        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        // 如果没有找到公网IP，返回REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 记录IP验证日志
     * 
     * @param string $ip 请求IP
     * @param bool $allowed 是否被允许
     * @param string $reason 原因
     */
    public function log_ip_check($ip, $allowed, $reason = '') {
        $logger = wc_get_logger();
        $context = array('source' => 'onepay-ip-whitelist');
        
        $message = sprintf(
            'IP白名单检查: %s %s %s',
            $ip,
            $allowed ? '通过' : '拒绝',
            $reason ? "($reason)" : ''
        );
        
        if ($allowed) {
            $logger->info($message, $context);
        } else {
            $logger->warning($message, $context);
        }
    }
    
    /**
     * 获取IP白名单统计信息
     * 
     * @return array 统计信息
     */
    public function get_whitelist_stats() {
        $whitelist = $this->get_whitelist_ips();
        
        return array(
            'total_ips' => count($whitelist),
            'default_ips' => count(self::DEFAULT_WHITELIST_IPS),
            'custom_ips' => count($whitelist) - count(self::DEFAULT_WHITELIST_IPS),
            'enabled' => !empty($whitelist)
        );
    }
    
    /**
     * 导出IP白名单配置
     * 
     * @return string JSON格式的配置
     */
    public function export_whitelist() {
        $gateway = new WC_Gateway_OnePay();
        
        $config = array(
            'enabled' => $gateway->get_option('ip_whitelist_enabled', 'yes'),
            'include_defaults' => $gateway->get_option('ip_whitelist_include_defaults', 'yes'),
            'custom_ips' => $gateway->get_option('ip_whitelist', ''),
            'export_time' => current_time('mysql'),
            'version' => '1.0'
        );
        
        return json_encode($config, JSON_PRETTY_PRINT);
    }
    
    /**
     * 导入IP白名单配置
     * 
     * @param string $json_config JSON格式的配置
     * @return array 导入结果
     */
    public function import_whitelist($json_config) {
        $result = array(
            'success' => false,
            'message' => '',
            'imported_count' => 0
        );
        
        $config = json_decode($json_config, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['message'] = '无效的JSON格式';
            return $result;
        }
        
        // 验证配置结构
        $required_fields = array('enabled', 'include_defaults', 'custom_ips');
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                $result['message'] = "缺少必需字段: {$field}";
                return $result;
            }
        }
        
        // 验证IP列表
        if (!empty($config['custom_ips'])) {
            $validation = $this->validate_whitelist($config['custom_ips']);
            if (!$validation['valid']) {
                $result['message'] = '配置中包含无效的IP地址: ' . implode(', ', $validation['errors']);
                return $result;
            }
            $result['imported_count'] = count($validation['valid_ips']);
        }
        
        // 更新网关设置
        $gateway = new WC_Gateway_OnePay();
        $gateway->update_option('ip_whitelist_enabled', $config['enabled']);
        $gateway->update_option('ip_whitelist_include_defaults', $config['include_defaults']);
        $gateway->update_option('ip_whitelist', $config['custom_ips']);
        
        $result['success'] = true;
        $result['message'] = '白名单配置导入成功';
        
        return $result;
    }
}