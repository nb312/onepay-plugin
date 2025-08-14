<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay International Cards Payment Gateway
 * 国际信用卡/借记卡支付网关
 */
class WC_Gateway_OnePay_Cards extends WC_Payment_Gateway {
    
    protected $parent_gateway;
    protected $supported_cards;
    
    public function __construct() {
        $this->id                 = 'onepay_cards';
        $this->icon               = '';  // 将动态生成多个卡标
        $this->has_fields         = true;
        $this->method_title       = __('OnePay 国际卡', 'onepay');
        $this->method_description = __('接受国际信用卡和借记卡支付', 'onepay');
        
        $this->supports = array(
            'products',
            'refunds',
        );
        
        // 从父网关获取设置
        $this->parent_gateway = new WC_Gateway_OnePay();
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title       = $this->get_option('title', __('信用卡/借记卡', 'onepay'));
        $this->description = $this->get_option('description', __('使用信用卡或借记卡安全支付', 'onepay'));
        $this->enabled     = $this->get_option('enabled', 'yes');
        
        // 获取启用的卡类型
        $this->supported_cards = array(
            'visa'       => $this->get_option('card_visa', 'yes') === 'yes',
            'mastercard' => $this->get_option('card_mastercard', 'yes') === 'yes',
            'amex'       => $this->get_option('card_amex', 'yes') === 'yes',
            'discover'   => $this->get_option('card_discover', 'yes') === 'yes',
            'jcb'        => $this->get_option('card_jcb', 'yes') === 'yes',
        );
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('启用/禁用', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('启用国际卡支付', 'onepay'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('标题', 'onepay'),
                'type'        => 'text',
                'description' => __('客户在结账时看到的标题', 'onepay'),
                'default'     => __('信用卡/借记卡', 'onepay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('描述', 'onepay'),
                'type'        => 'textarea',
                'description' => __('支付方式描述', 'onepay'),
                'default'     => __('使用信用卡或借记卡安全支付', 'onepay'),
                'desc_tip'    => true,
            ),
            'card_types_section' => array(
                'title'       => __('支持的卡类型', 'onepay'),
                'type'        => 'title',
                'description' => __('选择要接受的卡类型', 'onepay'),
            ),
            'card_visa' => array(
                'title'   => __('Visa', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('接受 Visa 卡', 'onepay'),
                'default' => 'yes'
            ),
            'card_mastercard' => array(
                'title'   => __('MasterCard', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('接受 MasterCard', 'onepay'),
                'default' => 'yes'
            ),
            'card_amex' => array(
                'title'   => __('American Express', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('接受 American Express', 'onepay'),
                'default' => 'yes'
            ),
            'card_discover' => array(
                'title'   => __('Discover', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('接受 Discover 卡', 'onepay'),
                'default' => 'yes'
            ),
            'card_jcb' => array(
                'title'   => __('JCB', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('接受 JCB 卡', 'onepay'),
                'default' => 'yes'
            ),
            'ui_section' => array(
                'title'       => __('界面设置', 'onepay'),
                'type'        => 'title',
                'description' => __('自定义支付表单外观', 'onepay'),
            ),
            'form_style' => array(
                'title'       => __('表单样式', 'onepay'),
                'type'        => 'select',
                'description' => __('选择表单显示样式', 'onepay'),
                'default'     => 'modern',
                'options'     => array(
                    'modern'   => __('现代卡片式', 'onepay'),
                    'classic'  => __('经典表单', 'onepay'),
                    'minimal'  => __('极简风格', 'onepay')
                )
            ),
            'show_card_icons' => array(
                'title'   => __('显示卡片图标', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('在支付选项旁显示支持的卡片图标', 'onepay'),
                'default' => 'yes'
            ),
            'security_badge' => array(
                'title'   => __('安全标识', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('显示安全加密标识', 'onepay'),
                'default' => 'yes'
            )
        );
    }
    
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        // 检查父网关是否配置
        if (!$this->parent_gateway->is_configured()) {
            return false;
        }
        
        // 至少要有一种卡类型启用
        $has_enabled_card = false;
        foreach ($this->supported_cards as $enabled) {
            if ($enabled) {
                $has_enabled_card = true;
                break;
            }
        }
        
        return $has_enabled_card;
    }
    
    public function get_icon() {
        if ($this->get_option('show_card_icons') !== 'yes') {
            return '';
        }
        
        $icons = array();
        
        if ($this->supported_cards['visa']) {
            $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/visa.svg" alt="Visa" class="onepay-card-icon">';
        }
        if ($this->supported_cards['mastercard']) {
            $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/mastercard.svg" alt="MasterCard" class="onepay-card-icon">';
        }
        if ($this->supported_cards['amex']) {
            $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/amex.svg" alt="Amex" class="onepay-card-icon">';
        }
        if ($this->supported_cards['discover']) {
            $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/discover.svg" alt="Discover" class="onepay-card-icon">';
        }
        if ($this->supported_cards['jcb']) {
            $icons[] = '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/jcb.svg" alt="JCB" class="onepay-card-icon">';
        }
        
        $icon_html = '<span class="onepay-card-icons">' . implode('', $icons) . '</span>';
        
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo '<p class="onepay-cards-description">' . wp_kses_post($this->description) . '</p>';
        }
        
        $form_style = $this->get_option('form_style', 'modern');
        $show_security = $this->get_option('security_badge', 'yes') === 'yes';
        
        ?>
        <div class="onepay-cards-form onepay-form-<?php echo esc_attr($form_style); ?>">
            <?php if ($show_security): ?>
            <div class="onepay-security-badge">
                <svg class="lock-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                </svg>
                <span><?php _e('安全加密支付', 'onepay'); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="onepay-form-group">
                <label for="onepay_card_number">
                    <?php _e('卡号', 'onepay'); ?>
                    <span class="required">*</span>
                </label>
                <div class="onepay-input-wrapper">
                    <input id="onepay_card_number" 
                           name="onepay_card_number" 
                           type="text" 
                           class="onepay-input" 
                           placeholder="1234 5678 9012 3456" 
                           maxlength="19"
                           autocomplete="cc-number">
                    <span id="onepay_card_type" class="onepay-card-type"></span>
                </div>
            </div>
            
            <div class="onepay-form-row">
                <div class="onepay-form-group onepay-form-group-half">
                    <label for="onepay_card_expiry">
                        <?php _e('有效期', 'onepay'); ?>
                        <span class="required">*</span>
                    </label>
                    <input id="onepay_card_expiry" 
                           name="onepay_card_expiry" 
                           type="text" 
                           class="onepay-input" 
                           placeholder="MM/YY" 
                           maxlength="5"
                           autocomplete="cc-exp">
                </div>
                
                <div class="onepay-form-group onepay-form-group-half">
                    <label for="onepay_card_cvv">
                        <?php _e('CVV', 'onepay'); ?>
                        <span class="required">*</span>
                        <span class="onepay-cvv-hint" title="<?php _e('卡片背面的3-4位安全码', 'onepay'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                            </svg>
                        </span>
                    </label>
                    <input id="onepay_card_cvv" 
                           name="onepay_card_cvv" 
                           type="text" 
                           class="onepay-input" 
                           placeholder="123" 
                           maxlength="4"
                           autocomplete="cc-csc">
                </div>
            </div>
            
            <div class="onepay-supported-cards">
                <span class="supported-text"><?php _e('支持的卡类型：', 'onepay'); ?></span>
                <?php
                $card_names = array(
                    'visa' => 'Visa',
                    'mastercard' => 'MasterCard', 
                    'amex' => 'Amex',
                    'discover' => 'Discover',
                    'jcb' => 'JCB'
                );
                
                foreach ($this->supported_cards as $card => $enabled) {
                    if ($enabled) {
                        echo '<img src="' . ONEPAY_PLUGIN_URL . 'assets/images/cards/' . $card . '-gray.svg" 
                              alt="' . $card_names[$card] . '" 
                              class="onepay-supported-card-icon" 
                              data-card-type="' . strtoupper($card) . '">';
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    public function validate_fields() {
        // 卡号验证
        if (empty($_POST['onepay_card_number'])) {
            wc_add_notice(__('请输入卡号', 'onepay'), 'error');
            return false;
        }
        
        $card_number = str_replace(array(' ', '-'), '', $_POST['onepay_card_number']);
        
        // 加载国际卡处理类
        if (!class_exists('OnePay_International_Card')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-international-card.php';
        }
        
        if (!OnePay_International_Card::validate_card_number($card_number)) {
            wc_add_notice(__('卡号无效', 'onepay'), 'error');
            return false;
        }
        
        // 检查卡类型是否被支持
        $card_type = OnePay_International_Card::detect_card_type($card_number);
        $card_type_map = array(
            'VISA' => 'visa',
            'MASTERCARD' => 'mastercard',
            'AMEX' => 'amex',
            'DISCOVER' => 'discover',
            'JCB' => 'jcb'
        );
        
        if (!$card_type || !isset($card_type_map[$card_type]) || !$this->supported_cards[$card_type_map[$card_type]]) {
            wc_add_notice(__('不支持此卡类型', 'onepay'), 'error');
            return false;
        }
        
        // 有效期验证
        if (empty($_POST['onepay_card_expiry'])) {
            wc_add_notice(__('请输入卡片有效期', 'onepay'), 'error');
            return false;
        }
        
        $expiry = $_POST['onepay_card_expiry'];
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $matches)) {
            wc_add_notice(__('有效期格式无效，请使用 MM/YY 格式', 'onepay'), 'error');
            return false;
        }
        
        $exp_month = $matches[1];
        $exp_year = '20' . $matches[2];
        $current_year = date('Y');
        $current_month = date('m');
        
        if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
            wc_add_notice(__('卡片已过期', 'onepay'), 'error');
            return false;
        }
        
        // CVV验证
        if (empty($_POST['onepay_card_cvv'])) {
            wc_add_notice(__('请输入CVV码', 'onepay'), 'error');
            return false;
        }
        
        $cvv = $_POST['onepay_card_cvv'];
        if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
            wc_add_notice(__('CVV码无效', 'onepay'), 'error');
            return false;
        }
        
        return true;
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'fail',
                'messages' => __('订单未找到', 'onepay')
            );
        }
        
        // 获取卡片数据
        $card_number = str_replace(array(' ', '-'), '', $_POST['onepay_card_number']);
        $expiry = $_POST['onepay_card_expiry'];
        $cvv = $_POST['onepay_card_cvv'];
        
        // 解析有效期
        preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $matches);
        $exp_month = $matches[1];
        $exp_year = '20' . $matches[2];
        
        // 加载国际卡处理类
        if (!class_exists('OnePay_International_Card')) {
            require_once ONEPAY_PLUGIN_PATH . 'includes/class-onepay-international-card.php';
        }
        
        // 检测卡类型
        $card_type = OnePay_International_Card::detect_card_type($card_number);
        
        // 准备卡片数据
        $card_data = array(
            'card_number' => $card_number,
            'card_type' => $card_type,
            'card_cvv' => $cvv,
            'card_exp_month' => $exp_month,
            'card_exp_year' => $exp_year
        );
        
        // 保存支付方式
        $order->update_meta_data('_onepay_payment_method', 'INTERNATIONAL_CARD');
        $order->update_meta_data('_onepay_payment_type', 'INTERNATIONAL_CARD');
        $order->update_meta_data('_payment_method_title', $this->title . ' (' . $card_type . ')');
        $order->save();
        
        // 创建国际卡支付请求
        $international_card_handler = new OnePay_International_Card();
        $response = $international_card_handler->create_international_card_payment($order, $card_data);
        
        if ($response['success']) {
            $order->update_status('pending', __('等待信用卡支付确认', 'onepay'));
            
            // 如果有3DS验证URL，重定向到3DS页面
            if (!empty($response['data']['webUrl'])) {
                return array(
                    'result'   => 'success',
                    'redirect' => $response['data']['webUrl']
                );
            }
            
            // 如果没有3DS，直接跳转到成功页面
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            wc_add_notice($response['message'], 'error');
            return array(
                'result' => 'fail'
            );
        }
    }
    
    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
        
        if ($this->enabled === 'no') {
            return;
        }
        
        wp_enqueue_script('onepay-cards', ONEPAY_PLUGIN_URL . 'assets/js/onepay-cards.js', array('jquery'), ONEPAY_VERSION, true);
        wp_enqueue_style('onepay-cards', ONEPAY_PLUGIN_URL . 'assets/css/onepay-cards.css', array(), ONEPAY_VERSION);
    }
}