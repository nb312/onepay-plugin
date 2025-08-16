<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Mastercard Payment Gateway
 * Mastercard信用卡支付网关
 */
class WC_Gateway_OnePay_Mastercard extends WC_Payment_Gateway {
    
    protected $parent_gateway;
    
    public function __construct() {
        $this->id                 = 'onepay_mastercard';
        $this->icon               = ONEPAY_PLUGIN_URL . 'assets/images/cards/mastercard.svg';
        $this->has_fields         = true;
        $this->method_title       = __('Mastercard', 'onepay');
        $this->method_description = __('Mastercard信用卡支付', 'onepay');
        
        $this->supports = array(
            'products',
            'refunds'
        );
        
        // 从父网关获取设置
        $this->parent_gateway = new WC_Gateway_OnePay();
        
        $this->init_form_fields();
        $this->init_settings();
        
        // 强制设置标题为Mastercard，不从数据库读取
        $this->title       = __('Mastercard', 'onepay');
        $this->description = '';
        $this->enabled     = $this->get_option('enabled', 'yes');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('启用/禁用', 'onepay'),
                'type'    => 'checkbox',
                'label'   => __('启用Mastercard支付', 'onepay'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('标题', 'onepay'),
                'type'        => 'text',
                'description' => __('客户在结账时看到的标题（固定为Mastercard）', 'onepay'),
                'default'     => __('Mastercard', 'onepay'),
                'custom_attributes' => array('readonly' => 'readonly'),
                'desc_tip'    => true,
            ),
            'min_amount' => array(
                'title'       => __('最小金额', 'onepay'),
                'type'        => 'text',
                'description' => __('最小支付金额（留空表示无限制）', 'onepay'),
                'default'     => '1',
                'desc_tip'    => true,
            ),
            'max_amount' => array(
                'title'       => __('最大金额', 'onepay'),
                'type'        => 'text',
                'description' => __('最大支付金额（留空表示无限制）', 'onepay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }
    
    /**
     * 加载支付脚本
     */
    public function payment_scripts() {
        if (!is_admin() && (is_cart() || is_checkout() || isset($_GET['pay_for_order']))) {
            wp_enqueue_script('onepay-mastercard', ONEPAY_PLUGIN_URL . 'assets/js/onepay-mastercard.js', array('jquery'), ONEPAY_VERSION, true);
            wp_enqueue_style('onepay-mastercard', ONEPAY_PLUGIN_URL . 'assets/css/onepay-mastercard.css', array(), ONEPAY_VERSION);
        }
    }
    
    /**
     * 显示支付字段
     */
    public function payment_fields() {
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
        echo '<div class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-number">' . __('卡号', 'onepay') . ' <span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" name="' . esc_attr($this->id) . '-card-number" />
              </div>';
        
        echo '<div class="form-row form-row-first">
                <label for="' . esc_attr($this->id) . '-card-expiry">' . __('有效期', 'onepay') . ' <span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="cc-exp" placeholder="MM/YY" name="' . esc_attr($this->id) . '-card-expiry" />
              </div>';
        
        echo '<div class="form-row form-row-last">
                <label for="' . esc_attr($this->id) . '-card-cvc">' . __('CVV', 'onepay') . ' <span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="cc-csc" placeholder="CVV" name="' . esc_attr($this->id) . '-card-cvc" style="width:100px" />
              </div>';
        
        echo '<div class="clear"></div></fieldset>';
    }
    
    /**
     * 验证支付字段
     */
    public function validate_fields() {
        if (empty($_POST[$this->id . '-card-number'])) {
            wc_add_notice(__('卡号是必填项', 'onepay'), 'error');
            return false;
        }
        
        if (empty($_POST[$this->id . '-card-expiry'])) {
            wc_add_notice(__('有效期是必填项', 'onepay'), 'error');
            return false;
        }
        
        if (empty($_POST[$this->id . '-card-cvc'])) {
            wc_add_notice(__('CVV是必填项', 'onepay'), 'error');
            return false;
        }
        
        // 验证卡号格式（基础验证）
        $card_number = str_replace(' ', '', $_POST[$this->id . '-card-number']);
        if (!preg_match('/^5[1-5][0-9]{14}$/', $card_number)) {
            wc_add_notice(__('请输入有效的Mastercard卡号', 'onepay'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * 处理支付
     */
    public function process_payment($order_id) {
        return $this->parent_gateway->process_payment_with_card_data($order_id, array(
            'card_type' => 'mastercard',
            'card_number' => sanitize_text_field($_POST[$this->id . '-card-number']),
            'card_expiry' => sanitize_text_field($_POST[$this->id . '-card-expiry']),
            'card_cvc' => sanitize_text_field($_POST[$this->id . '-card-cvc']),
        ));
    }
    
    /**
     * 检查是否可用
     */
    public function is_available() {
        if (!$this->enabled || 'yes' !== $this->enabled) {
            return false;
        }
        
        if (!$this->parent_gateway->is_properly_configured()) {
            return false;
        }
        
        // 检查金额限制
        $total = WC()->cart ? WC()->cart->total : 0;
        
        if (!empty($this->get_option('min_amount')) && $total < (float) $this->get_option('min_amount')) {
            return false;
        }
        
        if (!empty($this->get_option('max_amount')) && $total > (float) $this->get_option('max_amount')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 处理管理员选项保存，确保标题始终为Mastercard
     */
    public function process_admin_options() {
        $saved = parent::process_admin_options();
        
        // 强制设置标题为Mastercard，防止被管理员修改
        $this->update_option('title', 'Mastercard');
        $this->title = 'Mastercard';
        
        return $saved;
    }
}