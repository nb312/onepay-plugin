<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OnePay Callback Handler Class
 * 
 * Handles payment notification callbacks from OnePay
 * Implements idempotency and proper order status management
 */
class OnePay_Callback {
    
    private $gateway;
    private $logger;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_OnePay();
        $this->logger = OnePay_Logger::get_instance();
    }
    
    /**
     * Process payment callback from OnePay
     */
    public function process_callback() {
        try {
            $this->logger->info('Callback received from OnePay');
            
            // Get raw POST data
            $raw_data = file_get_contents('php://input');
            
            if (empty($raw_data)) {
                $this->logger->error('Empty callback data received');
                $this->send_callback_response('ERROR');
                return;
            }
            
            // Parse JSON data
            $callback_data = json_decode($raw_data, true);
            
            if (!$callback_data) {
                $this->logger->error('Invalid JSON in callback data', array('raw_data' => $raw_data));
                $this->send_callback_response('ERROR');
                return;
            }
            
            // Validate callback data structure
            if (!$this->validate_callback_data($callback_data)) {
                $this->send_callback_response('ERROR');
                return;
            }
            
            // Verify signature
            if (!$this->verify_callback_signature($callback_data)) {
                $this->send_callback_response('ERROR');
                return;
            }
            
            // Process the callback
            $result_data = json_decode($callback_data['result'], true);
            
            if (!$result_data || !isset($result_data['data'])) {
                $this->logger->error('Invalid result data in callback', array('result' => $callback_data['result']));
                $this->send_callback_response('ERROR');
                return;
            }
            
            $payment_data = $result_data['data'];
            $this->logger->info('Processing callback for OnePay order: ' . $payment_data['orderNo']);
            
            // Find the order
            $order = $this->find_order_by_onepay_order_no($payment_data['orderNo']);
            
            if (!$order) {
                $this->log('Order not found for OnePay order number: ' . $payment_data['orderNo'], 'warning');
                $this->send_callback_response('SUCCESS'); // Send SUCCESS to prevent retries for unknown orders
                return;
            }
            
            // Process payment status
            $this->process_payment_status($order, $payment_data, $result_data);
            
            // Send success response
            $this->send_callback_response('SUCCESS');
            
        } catch (Exception $e) {
            $this->log('Callback processing exception: ' . $e->getMessage(), 'error');
            $this->send_callback_response('ERROR');
        }
    }
    
    /**
     * Validate callback data structure
     * 
     * @param array $callback_data The callback data
     * @return bool True if valid, false otherwise
     */
    private function validate_callback_data($callback_data) {
        $required_fields = array('merchantNo', 'result', 'sign');
        
        foreach ($required_fields as $field) {
            if (!isset($callback_data[$field]) || empty($callback_data[$field])) {
                $this->logger->error('Missing required callback field: ' . $field, array(
                    'received_fields' => array_keys($callback_data)
                ));
                return false;
            }
        }
        
        // Validate merchantNo
        if ($callback_data['merchantNo'] !== $this->gateway->merchant_no) {
            $this->logger->error('Merchant number mismatch', array(
                'received' => $callback_data['merchantNo'],
                'expected' => $this->gateway->merchant_no
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify callback signature
     * 
     * @param array $callback_data The callback data
     * @return bool True if signature is valid, false otherwise
     */
    private function verify_callback_signature($callback_data) {
        if (empty($this->gateway->platform_public_key)) {
            $this->log('Platform public key not configured, skipping signature verification', 'warning');
            return true; // Allow processing without signature verification if key not configured
        }
        
        $result = $callback_data['result'];
        $signature = $callback_data['sign'];
        
        $signature_valid = OnePay_Signature::verify(
            $result,
            $signature,
            $this->gateway->platform_public_key
        );
        
        if (!$signature_valid) {
            $this->log('Signature verification failed', 'error');
            return false;
        }
        
        $this->log('Signature verification successful');
        return true;
    }
    
    /**
     * Find order by OnePay order number
     * 
     * @param string $onepay_order_no OnePay order number
     * @return WC_Order|false The order object or false if not found
     */
    private function find_order_by_onepay_order_no($onepay_order_no) {
        $orders = wc_get_orders(array(
            'meta_key' => '_onepay_order_no',
            'meta_value' => $onepay_order_no,
            'limit' => 1
        ));
        
        return !empty($orders) ? $orders[0] : false;
    }
    
    /**
     * Process payment status update
     * 
     * @param WC_Order $order The WooCommerce order
     * @param array $payment_data Payment data from callback
     * @param array $result_data Full result data
     */
    private function process_payment_status($order, $payment_data, $result_data) {
        $order_status = $payment_data['orderStatus'];
        $order_id = $order->get_id();
        
        // 检查幂等性 - 如果订单已处理，直接返回成功
        $processed_status = $order->get_meta('_onepay_callback_processed');
        if ($processed_status === $order_status) {
            $this->log('订单 ' . $order_id . ' 的回调已处理，状态为 ' . $order_status);
            return;
        }
        
        $this->log('处理订单 ' . $order_id . ' 的状态更新: ' . $order_status);
        
        // 更新订单元数据与回调数据
        $order->update_meta_data('_onepay_callback_data', json_encode($payment_data));
        $order->update_meta_data('_onepay_callback_processed', $order_status);
        $order->update_meta_data('_onepay_callback_time', current_time('mysql'));
        
        // 检查是否为国际卡支付
        $payment_type = $order->get_meta('_onepay_payment_type');
        if ($payment_type === 'INTERNATIONAL_CARD') {
            $this->process_international_card_callback($order, $payment_data);
        }
        
        if (isset($payment_data['paidAmount'])) {
            $paid_amount = floatval($payment_data['paidAmount']) / 100; // 转换为最小单位
            $order->update_meta_data('_onepay_paid_amount', $paid_amount);
        } elseif (isset($payment_data['orderAmount'])) {
            // 国际卡使用orderAmount字段
            $paid_amount = floatval($payment_data['orderAmount']) / 100;
            $order->update_meta_data('_onepay_paid_amount', $paid_amount);
        }
        
        if (isset($payment_data['orderFee'])) {
            $order_fee = floatval($payment_data['orderFee']) / 100; // 转换为最小单位
            $order->update_meta_data('_onepay_fee', $order_fee);
        }
        
        // 根据状态处理
        switch ($order_status) {
            case 'SUCCESS':
                $this->process_successful_payment($order, $payment_data);
                break;
                
            case 'PENDING':
                $this->process_pending_payment($order, $payment_data);
                break;
                
            case 'FAIL':
            case 'FAILED':
                $this->process_failed_payment($order, $payment_data);
                break;
                
            default:
                $this->log('未知的支付状态: ' . $order_status, 'warning');
                $order->add_order_note(
                    sprintf(__('OnePay回调收到未知状态: %s', 'onepay'), $order_status)
                );
        }
        
        $order->save();
    }
    
    /**
     * 处理国际卡支付回调
     * 
     * @param WC_Order $order 订单
     * @param array $payment_data 支付数据
     */
    private function process_international_card_callback($order, $payment_data) {
        // 记录国际卡特有的信息
        if (isset($payment_data['descriptor'])) {
            $order->update_meta_data('_onepay_descriptor', $payment_data['descriptor']);
        }
        
        if (isset($payment_data['errorMessage'])) {
            $order->update_meta_data('_onepay_error_message', $payment_data['errorMessage']);
        }
        
        // 更新支付模式
        if (isset($payment_data['payModel'])) {
            $order->update_meta_data('_onepay_pay_model', $payment_data['payModel']);
        }
        
        // 记录货币信息
        if (isset($payment_data['currency'])) {
            $order->update_meta_data('_onepay_currency', $payment_data['currency']);
        }
        
        $this->log('处理国际卡支付回调，订单号: ' . $payment_data['orderNo']);
    }
    
    /**
     * Process successful payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_successful_payment($order, $payment_data) {
        if ($order->has_status(array('processing', 'completed'))) {
            $this->log('Order ' . $order->get_id() . ' already processed');
            return;
        }
        
        $paid_amount = isset($payment_data['paidAmount']) ? floatval($payment_data['paidAmount']) / 100 : null;
        $order_amount = floatval($order->get_total());
        
        // Verify payment amount
        if ($paid_amount && abs($paid_amount - $order_amount) > 0.01) {
            $this->log('Payment amount mismatch. Expected: ' . $order_amount . ', Received: ' . $paid_amount, 'warning');
            $order->add_order_note(
                sprintf(
                    __('OnePay payment amount mismatch. Expected: %s, Received: %s', 'onepay'),
                    wc_price($order_amount),
                    wc_price($paid_amount)
                )
            );
        }
        
        // Complete payment
        $order->payment_complete($payment_data['orderNo']);
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment completed. Transaction ID: %s, Amount: %s', 'onepay'),
                $payment_data['orderNo'],
                isset($paid_amount) ? wc_price($paid_amount) : wc_price($order_amount)
            )
        );
        
        $this->log('Payment completed for order ' . $order->get_id());
        
        // Trigger action for other plugins
        do_action('onepay_payment_complete', $order, $payment_data);
    }
    
    /**
     * Process pending payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_pending_payment($order, $payment_data) {
        if (!$order->has_status('pending')) {
            $order->update_status('pending', __('OnePay payment is pending confirmation.', 'onepay'));
        }
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment pending. Transaction ID: %s', 'onepay'),
                $payment_data['orderNo']
            )
        );
        
        $this->log('Payment pending for order ' . $order->get_id());
    }
    
    /**
     * Process failed payment
     * 
     * @param WC_Order $order The order
     * @param array $payment_data Payment data
     */
    private function process_failed_payment($order, $payment_data) {
        $order->update_status('failed', __('OnePay payment failed.', 'onepay'));
        
        $failure_reason = isset($payment_data['msg']) ? $payment_data['msg'] : __('Payment failed', 'onepay');
        
        $order->add_order_note(
            sprintf(
                __('OnePay payment failed. Transaction ID: %s, Reason: %s', 'onepay'),
                $payment_data['orderNo'],
                $failure_reason
            )
        );
        
        $this->log('Payment failed for order ' . $order->get_id() . ': ' . $failure_reason);
        
        // Trigger action for other plugins
        do_action('onepay_payment_failed', $order, $payment_data);
    }
    
    /**
     * Send callback response to OnePay
     * 
     * @param string $status Response status (SUCCESS or ERROR)
     */
    private function send_callback_response($status) {
        $this->log('Sending callback response: ' . $status);
        
        // Clear any output that might have been generated
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set appropriate headers
        status_header(200);
        header('Content-Type: text/plain');
        
        // Send the response
        echo $status;
        
        // End execution
        exit;
    }
    
    
    /**
     * Handle synchronous return from OnePay
     * 
     * @param array $params URL parameters
     */
    public function process_return($params) {
        $this->log('Processing return from OnePay');
        $this->log('Return parameters: ' . json_encode($params));
        
        $required_params = array('orderNo', 'orderAmount', 'orderStatus', 'currency', 'merchantOrderNo');
        
        foreach ($required_params as $param) {
            if (!isset($params[$param])) {
                $this->log('Missing return parameter: ' . $param, 'error');
                return false;
            }
        }
        
        // Find order by OnePay order number
        $order = $this->find_order_by_onepay_order_no($params['orderNo']);
        
        if (!$order) {
            $this->log('Order not found for return: ' . $params['orderNo'], 'warning');
            return false;
        }
        
        // Log the return
        $order->add_order_note(
            sprintf(
                __('Customer returned from OnePay. Status: %s', 'onepay'),
                $params['orderStatus']
            )
        );
        
        return $order;
    }
}