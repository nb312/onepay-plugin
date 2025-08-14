<?php
/**
 * OnePay 快速调试 - 添加到WordPress页面或文章内容中
 * 
 * 使用方法：在任何页面或文章中添加 [onepay_quick_debug] 短代码
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('onepay_quick_debug', 'onepay_quick_debug_shortcode');

function onepay_quick_debug_shortcode($atts) {
    if (!current_user_can('manage_woocommerce')) {
        return '<p style="color: red;">需要管理员权限才能查看调试信息</p>';
    }
    
    ob_start();
    ?>
    <div style="border: 2px solid #dc3232; padding: 20px; margin: 20px 0; background: #fff;">
        <h3 style="color: #dc3232; margin-top: 0;">OnePay 快速诊断</h3>
        
        <?php
        // 检查1：基础环境
        echo '<h4>📋 基础检查</h4>';
        
        $wc_active = class_exists('WooCommerce');
        echo '<p>• WooCommerce: ' . ($wc_active ? '✅ 激活' : '❌ 未激活 - 请先安装并激活WooCommerce') . '</p>';
        
        if (!$wc_active) {
            echo '</div>';
            return ob_get_clean();
        }
        
        $gateway_exists = class_exists('WC_Gateway_OnePay');
        echo '<p>• OnePay网关类: ' . ($gateway_exists ? '✅ 已加载' : '❌ 未加载 - 检查插件是否正确安装') . '</p>';
        
        if (!$gateway_exists) {
            echo '</div>';
            return ob_get_clean();
        }
        
        // 检查2：网关注册
        echo '<h4>⚙️ 网关注册</h4>';
        
        $gateways = WC()->payment_gateways()->payment_gateways();
        $onepay_registered = isset($gateways['onepay']);
        echo '<p>• 网关注册状态: ' . ($onepay_registered ? '✅ 已注册' : '❌ 未注册') . '</p>';
        
        if (!$onepay_registered) {
            echo '<p style="color: #dc3232;"><strong>问题：</strong>OnePay网关未注册到WooCommerce系统</p>';
            echo '<p><strong>解决方法：</strong></p>';
            echo '<ol>';
            echo '<li>确认OnePay插件已激活</li>';
            echo '<li>尝试停用并重新激活OnePay插件</li>';
            echo '<li>检查是否有其他插件冲突</li>';
            echo '</ol>';
            echo '</div>';
            return ob_get_clean();
        }
        
        // 检查3：网关配置
        echo '<h4>🔧 网关配置</h4>';
        
        $gateway = $gateways['onepay'];
        $enabled = ($gateway->enabled === 'yes');
        echo '<p>• 启用状态: ' . ($enabled ? '✅ 已启用' : '❌ 已禁用 - <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . '">点击启用</a>') . '</p>';
        
        if (!$enabled) {
            echo '<p style="color: #dc3232;"><strong>主要问题：网关未启用</strong></p>';
            echo '<p><strong>解决步骤：</strong></p>';
            echo '<ol>';
            echo '<li>进入 WooCommerce → 设置 → 支付 → OnePay</li>';
            echo '<li>勾选"Enable OnePay Payment"</li>';
            echo '<li>保存更改</li>';
            echo '</ol>';
        }
        
        // 检查4：货币支持
        echo '<h4>💰 货币支持</h4>';
        
        $current_currency = get_woocommerce_currency();
        $supported_currencies = array('RUB', 'USD', 'EUR');
        $currency_supported = in_array($current_currency, $supported_currencies);
        
        echo '<p>• 当前货币: ' . $current_currency . '</p>';
        echo '<p>• 货币支持: ' . ($currency_supported ? '✅ 支持' : '❌ 不支持 - <a href="' . admin_url('admin.php?page=wc-settings&tab=general') . '">更改货币</a>') . '</p>';
        echo '<p>• 支持的货币: ' . implode(', ', $supported_currencies) . '</p>';
        
        if (!$currency_supported) {
            echo '<p style="color: #dc3232;"><strong>主要问题：货币不支持</strong></p>';
            echo '<p><strong>解决步骤：</strong></p>';
            echo '<ol>';
            echo '<li>进入 WooCommerce → 设置 → 常规</li>';
            echo '<li>将"货币"更改为 RUB、USD 或 EUR</li>';
            echo '<li>保存更改</li>';
            echo '</ol>';
        }
        
        // 检查5：必要配置
        echo '<h4>📝 必要配置</h4>';
        
        $merchant_no = !empty($gateway->merchant_no);
        echo '<p>• 商户号: ' . ($merchant_no ? '✅ 已设置' : '❌ 未设置 - <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . '">设置商户号</a>') . '</p>';
        
        $api_url = !empty($gateway->api_url);
        echo '<p>• API地址: ' . ($api_url ? '✅ 已设置' : '❌ 未设置') . '</p>';
        
        // 检查6：最终可用性
        echo '<h4>✅ 最终状态</h4>';
        
        $is_available = $gateway->is_available();
        echo '<p>• OnePay可用性: ' . ($is_available ? '✅ 可用' : '❌ 不可用') . '</p>';
        
        if ($is_available) {
            echo '<div style="background: #d1edff; padding: 15px; border-left: 4px solid #0073aa;">';
            echo '<p style="margin: 0;"><strong>🎉 OnePay配置正确！</strong></p>';
            echo '<p style="margin: 5px 0 0 0;">OnePay应该在结账页面显示。如果仍然看不到，请检查：</p>';
            echo '<ul style="margin: 5px 0 0 20px;">';
            echo '<li>是否在结账页面？</li>';
            echo '<li>购物车是否有商品？</li>';
            echo '<li>是否有其他插件缓存？</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div style="background: #ffeaa7; padding: 15px; border-left: 4px solid #fdcb6e;">';
            echo '<p style="margin: 0;"><strong>⚠️ OnePay不可用</strong></p>';
            echo '<p style="margin: 5px 0 0 0;">请根据上面的检查项目修复问题。</p>';
            echo '</div>';
        }
        
        // 快速链接
        echo '<h4>🔗 快速链接</h4>';
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=onepay') . '" class="button button-primary">OnePay设置</a> ';
        echo '<a href="' . admin_url('admin.php?page=wc-settings&tab=general') . '" class="button">WooCommerce常规设置</a> ';
        echo '<a href="' . admin_url('plugins.php') . '" class="button">插件管理</a>';
        echo '</p>';
        ?>
        
        <hr>
        <p style="font-size: 12px; color: #666;">
            💡 <strong>提示:</strong> 修复问题后，清除任何缓存并刷新结账页面查看效果。
        </p>
    </div>
    <?php
    
    return ob_get_clean();
}