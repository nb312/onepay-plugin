<?php
/**
 * OnePay Checkout 专项调试
 * 专门检查checkout页面的问题
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('onepay_checkout_debug', 'onepay_checkout_debug_shortcode');

function onepay_checkout_debug_shortcode() {
    if (!current_user_can('manage_woocommerce')) {
        return '<p style="color: red;">需要管理员权限</p>';
    }
    
    ob_start();
    ?>
    <div style="border: 2px solid #0073aa; padding: 20px; margin: 20px 0; background: #f0f8ff;">
        <h3 style="color: #0073aa; margin-top: 0;">OnePay Checkout 专项诊断</h3>
        
        <?php
        echo '<h4>🛒 购物车状态</h4>';
        
        // 检查购物车
        if (WC()->cart) {
            $cart_count = WC()->cart->get_cart_contents_count();
            $cart_total = WC()->cart->get_cart_contents_total();
            echo '<p>• 购物车商品数量: ' . $cart_count . '</p>';
            echo '<p>• 购物车总金额: ' . wc_price($cart_total) . '</p>';
            echo '<p>• 购物车是否为空: ' . (WC()->cart->is_empty() ? '❌ 是' : '✅ 否') . '</p>';
        } else {
            echo '<p>❌ 购物车对象未初始化</p>';
        }
        
        echo '<h4>📄 结账页面检查</h4>';
        
        // 检查当前页面
        echo '<p>• 当前页面是结账页: ' . (is_checkout() ? '✅ 是' : '❌ 否') . '</p>';
        echo '<p>• 当前页面ID: ' . get_the_ID() . '</p>';
        echo '<p>• WooCommerce结账页ID: ' . wc_get_page_id('checkout') . '</p>';
        
        // 检查结账页面内容
        $checkout_page = get_post(wc_get_page_id('checkout'));
        if ($checkout_page) {
            $has_shortcode = has_shortcode($checkout_page->post_content, 'woocommerce_checkout');
            $has_blocks = has_blocks($checkout_page->post_content);
            echo '<p>• 结账页有checkout短代码: ' . ($has_shortcode ? '✅ 是' : '❌ 否') . '</p>';
            echo '<p>• 结账页使用区块: ' . ($has_blocks ? '✅ 是' : '❌ 否') . '</p>';
        }
        
        echo '<h4>💳 支付网关实时检查</h4>';
        
        // 获取当前可用的支付网关
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        echo '<p>• 当前可用支付网关数量: ' . count($available_gateways) . '</p>';
        
        $onepay_in_available = isset($available_gateways['onepay']);
        echo '<p>• OnePay在可用网关中: ' . ($onepay_in_available ? '✅ 是' : '❌ 否') . '</p>';
        
        if ($onepay_in_available) {
            $onepay_gateway = $available_gateways['onepay'];
            echo '<p>• OnePay标题: ' . esc_html($onepay_gateway->get_title()) . '</p>';
            echo '<p>• OnePay描述: ' . esc_html($onepay_gateway->get_description()) . '</p>';
            echo '<p>• OnePay图标: ' . ($onepay_gateway->get_icon() ? '有' : '无') . '</p>';
        }
        
        echo '<h4>🔍 所有可用支付网关</h4>';
        echo '<div style="background: #f9f9f9; padding: 10px; max-height: 200px; overflow-y: auto;">';
        
        if (empty($available_gateways)) {
            echo '<p style="color: red;">❌ 没有任何可用的支付网关！这是问题所在。</p>';
            
            echo '<h4>🚨 无支付网关问题诊断</h4>';
            echo '<p>可能的原因：</p>';
            echo '<ul>';
            echo '<li>WooCommerce未正确初始化</li>';
            echo '<li>购物车为空或有问题</li>';
            echo '<li>所有支付网关都被禁用</li>';
            echo '<li>插件冲突或主题问题</li>';
            echo '</ul>';
            
            // 检查所有注册的网关（不管可用性）
            $all_gateways = WC()->payment_gateways()->payment_gateways();
            echo '<h5>所有注册的支付网关（包括不可用的）:</h5>';
            foreach ($all_gateways as $id => $gateway) {
                $available = $gateway->is_available() ? '可用' : '不可用';
                echo '<li>' . $id . ' - ' . $gateway->get_method_title() . ' (' . $available . ')</li>';
            }
            
        } else {
            foreach ($available_gateways as $id => $gateway) {
                $highlight = ($id === 'onepay') ? 'style="background: yellow;"' : '';
                echo '<div ' . $highlight . '>' . $id . ' - ' . $gateway->get_method_title() . '</div>';
            }
        }
        
        echo '</div>';
        
        echo '<h4>🧱 区块结账检查</h4>';
        
        if (class_exists('OnePay_Compatibility')) {
            $is_blocks = OnePay_Compatibility::is_blocks_checkout_active();
            echo '<p>• 使用区块结账: ' . ($is_blocks ? '✅ 是' : '❌ 否') . '</p>';
            
            if ($is_blocks) {
                $blocks_integration = class_exists('OnePay_Blocks_Integration');
                echo '<p>• OnePay区块集成: ' . ($blocks_integration ? '✅ 已加载' : '❌ 未加载') . '</p>';
                
                // 检查区块脚本是否注册
                global $wp_scripts;
                $script_registered = isset($wp_scripts->registered['onepay-blocks-integration']);
                echo '<p>• 区块脚本已注册: ' . ($script_registered ? '✅ 是' : '❌ 否') . '</p>';
            }
        }
        
        echo '<h4>⚡ 即时测试</h4>';
        
        // 尝试直接调用OnePay的is_available方法
        if (class_exists('WC_Gateway_OnePay')) {
            $test_gateway = new WC_Gateway_OnePay();
            $direct_available = $test_gateway->is_available();
            echo '<p>• OnePay直接可用性测试: ' . ($direct_available ? '✅ 可用' : '❌ 不可用') . '</p>';
        }
        
        echo '<h4>🔧 解决建议</h4>';
        
        if (empty($available_gateways)) {
            echo '<div style="background: #ffe6e6; padding: 15px; border-left: 4px solid #dc3232;">';
            echo '<p><strong>主要问题：没有可用的支付网关</strong></p>';
            echo '<p>立即尝试：</p>';
            echo '<ol>';
            echo '<li>确保购物车中有商品</li>';
            echo '<li>检查其他支付网关是否也不显示</li>';
            echo '<li>停用其他支付相关插件测试</li>';
            echo '<li>切换到默认主题测试</li>';
            echo '<li>清除所有缓存</li>';
            echo '</ol>';
            echo '</div>';
        } elseif (!$onepay_in_available) {
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">';
            echo '<p><strong>问题：OnePay不在可用网关列表中</strong></p>';
            echo '<p>尽管配置正确，但WooCommerce运行时检查时OnePay不可用。</p>';
            echo '<p>可能原因：</p>';
            echo '<ul>';
            echo '<li>某个运行时条件不满足</li>';
            echo '<li>插件冲突影响可用性判断</li>';
            echo '<li>主题或其他插件修改了支付网关逻辑</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div style="background: #d1edff; padding: 15px; border-left: 4px solid #0073aa;">';
            echo '<p><strong>✅ OnePay在可用网关中，但仍不显示？</strong></p>';
            echo '<p>这可能是前端渲染问题：</p>';
            echo '<ul>';
            echo '<li>检查浏览器控制台是否有JavaScript错误</li>';
            echo '<li>检查CSS是否隐藏了支付选项</li>';
            echo '<li>尝试不同浏览器或无痕模式</li>';
            echo '<li>检查是否有缓存插件影响</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '<h4>📞 技术支持信息</h4>';
        echo '<div style="background: #f1f1f1; padding: 10px; font-family: monospace; font-size: 11px;">';
        echo '<p>PHP版本: ' . PHP_VERSION . '</p>';
        echo '<p>WordPress版本: ' . get_bloginfo('version') . '</p>';
        echo '<p>WooCommerce版本: ' . (defined('WC_VERSION') ? WC_VERSION : 'N/A') . '</p>';
        echo '<p>当前主题: ' . wp_get_theme()->get('Name') . '</p>';
        echo '<p>当前时间: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<p>内存限制: ' . ini_get('memory_limit') . '</p>';
        echo '</div>';
        ?>
    </div>
    <?php
    
    return ob_get_clean();
}

// 也添加一个简单的检查钩子
add_action('wp_footer', 'onepay_checkout_page_debug');

function onepay_checkout_page_debug() {
    // 只在结账页面显示，且用户是管理员，且开启了debug
    if (!is_checkout() || !current_user_can('manage_woocommerce')) {
        return;
    }
    
    // 检查OnePay设置中是否开启了debug
    $gateways = WC()->payment_gateways()->payment_gateways();
    if (!isset($gateways['onepay']) || $gateways['onepay']->debug !== 'yes') {
        return;
    }
    
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $onepay_available = isset($available_gateways['onepay']);
    
    if (!$onepay_available) {
        echo '<div style="position: fixed; bottom: 20px; right: 20px; background: #dc3232; color: white; padding: 10px; border-radius: 5px; z-index: 9999; font-size: 12px;">';
        echo '<strong>OnePay Debug:</strong> 网关不在可用列表中<br>';
        echo '可用网关数: ' . count($available_gateways);
        echo '</div>';
    }
}