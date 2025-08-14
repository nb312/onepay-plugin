/**
 * OnePay Frontend JavaScript
 */
(function($) {
    'use strict';
    
    var OnePay = {
        init: function() {
            this.bindEvents();
            this.initCardFields();
        },
        
        bindEvents: function() {
            var self = this;
            
            // 监听支付方式选择
            $(document).on('change', 'input[name="onepay_payment_method"]', function() {
                self.toggleCardFields($(this).val());
            });
            
            // 监听卡号输入，自动检测卡类型
            $(document).on('input', '#onepay_card_number', function() {
                self.detectCardType($(this).val());
                self.formatCardNumber($(this));
            });
            
            // 格式化有效期输入
            $(document).on('input', '#onepay_card_expiry', function() {
                self.formatExpiry($(this));
            });
            
            // 限制CVV输入
            $(document).on('input', '#onepay_card_cvv', function() {
                self.formatCVV($(this));
            });
            
            // 表单提交验证
            $('body').on('checkout_place_order_onepay', this.submitPayment);
        },
        
        initCardFields: function() {
            // 初始化时隐藏国际卡字段
            $('#onepay_international_card_fields').hide();
            
            // 如果已选择国际卡，显示字段
            if ($('input[name="onepay_payment_method"]:checked').val() === 'INTERNATIONAL_CARD') {
                $('#onepay_international_card_fields').show();
            }
        },
        
        toggleCardFields: function(paymentMethod) {
            if (paymentMethod === 'INTERNATIONAL_CARD') {
                $('#onepay_international_card_fields').slideDown();
            } else {
                $('#onepay_international_card_fields').slideUp();
            }
        },
        
        detectCardType: function(cardNumber) {
            // 移除空格和破折号
            cardNumber = cardNumber.replace(/[\s-]/g, '');
            
            var cardType = '';
            var cardTypeDisplay = '';
            
            // VISA
            if (/^4/.test(cardNumber)) {
                cardType = 'VISA';
                cardTypeDisplay = '<span class="card-visa">VISA</span>';
            }
            // MasterCard
            else if (/^5[1-5]/.test(cardNumber) || /^2[2-7]/.test(cardNumber)) {
                cardType = 'MASTERCARD';
                cardTypeDisplay = '<span class="card-mastercard">MasterCard</span>';
            }
            // American Express
            else if (/^3[47]/.test(cardNumber)) {
                cardType = 'AMEX';
                cardTypeDisplay = '<span class="card-amex">AMEX</span>';
            }
            // Discover
            else if (/^6(?:011|5)/.test(cardNumber)) {
                cardType = 'DISCOVER';
                cardTypeDisplay = '<span class="card-discover">Discover</span>';
            }
            // JCB
            else if (/^35/.test(cardNumber)) {
                cardType = 'JCB';
                cardTypeDisplay = '<span class="card-jcb">JCB</span>';
            }
            
            $('#onepay_card_type').html(cardTypeDisplay);
            $('#onepay_card_type').data('card-type', cardType);
        },
        
        formatCardNumber: function($input) {
            var value = $input.val().replace(/\s/g, '');
            var formattedValue = value.match(/.{1,4}/g);
            if (formattedValue) {
                $input.val(formattedValue.join(' '));
            }
        },
        
        formatExpiry: function($input) {
            var value = $input.val().replace(/[^\d]/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            $input.val(value);
        },
        
        formatCVV: function($input) {
            var value = $input.val().replace(/[^\d]/g, '');
            var cardType = $('#onepay_card_type').data('card-type');
            
            // AMEX使用4位CVV，其他使用3位
            var maxLength = (cardType === 'AMEX') ? 4 : 3;
            $input.val(value.substring(0, maxLength));
        },
        
        validateCardNumber: function(cardNumber) {
            // 移除空格和破折号
            cardNumber = cardNumber.replace(/[\s-]/g, '');
            
            // 检查是否为数字
            if (!/^\d+$/.test(cardNumber)) {
                return false;
            }
            
            // Luhn算法验证
            var sum = 0;
            var isEven = false;
            
            for (var i = cardNumber.length - 1; i >= 0; i--) {
                var digit = parseInt(cardNumber.charAt(i), 10);
                
                if (isEven) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }
                
                sum += digit;
                isEven = !isEven;
            }
            
            return (sum % 10) === 0;
        },
        
        submitPayment: function() {
            if ($('#payment_method_onepay').is(':checked')) {
                var paymentMethod = $('input[name="onepay_payment_method"]:checked').val();
                
                if (!paymentMethod) {
                    alert('请选择支付方式');
                    return false;
                }
                
                // 如果是国际卡支付，验证卡片信息
                if (paymentMethod === 'INTERNATIONAL_CARD') {
                    var cardNumber = $('#onepay_card_number').val();
                    var expiry = $('#onepay_card_expiry').val();
                    var cvv = $('#onepay_card_cvv').val();
                    
                    if (!cardNumber) {
                        alert('请输入卡号');
                        return false;
                    }
                    
                    if (!OnePay.validateCardNumber(cardNumber)) {
                        alert('卡号无效');
                        return false;
                    }
                    
                    if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
                        alert('请输入有效期（MM/YY格式）');
                        return false;
                    }
                    
                    if (!cvv || !/^\d{3,4}$/.test(cvv)) {
                        alert('请输入CVV码');
                        return false;
                    }
                }
            }
            return true;
        }
    };
    
    $(document).ready(function() {
        OnePay.init();
    });
    
})(jQuery);