/**
 * OnePay Cards Payment JavaScript
 * 现代化信用卡支付交互
 */
(function($) {
    'use strict';
    
    var OnePay_Cards = {
        cardPatterns: {
            visa: /^4/,
            mastercard: /^(5[1-5]|2[2-7])/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/,
            jcb: /^35/
        },
        
        init: function() {
            this.bindEvents();
            this.initializeForm();
        },
        
        bindEvents: function() {
            var self = this;
            
            // 卡号输入处理
            $(document).on('input', '#onepay_card_number', function() {
                self.handleCardNumberInput($(this));
            });
            
            // 有效期格式化
            $(document).on('input', '#onepay_card_expiry', function() {
                self.formatExpiry($(this));
            });
            
            // CVV输入限制
            $(document).on('input', '#onepay_card_cvv', function() {
                self.formatCVV($(this));
            });
            
            // 实时验证
            $(document).on('blur', '#onepay_card_number', function() {
                self.validateCardNumber($(this));
            });
            
            $(document).on('blur', '#onepay_card_expiry', function() {
                self.validateExpiry($(this));
            });
            
            // 表单提交验证
            $('form.checkout').on('checkout_place_order_onepay_cards', function() {
                return self.validateForm();
            });
            
            // 支付方式切换动画
            $(document).on('change', 'input[name="payment_method"]', function() {
                self.handlePaymentMethodChange($(this));
            });
        },
        
        initializeForm: function() {
            // 添加平滑过渡效果
            $('.payment_box').hide();
            $('input[name="payment_method"]:checked').closest('li').find('.payment_box').slideDown(300);
        },
        
        handleCardNumberInput: function($input) {
            var value = $input.val().replace(/\s/g, '');
            var formattedValue = this.formatCardNumber(value);
            $input.val(formattedValue);
            
            // 检测卡类型
            var cardType = this.detectCardType(value);
            this.updateCardTypeIndicator(cardType);
            this.highlightSupportedCard(cardType);
        },
        
        formatCardNumber: function(value) {
            // 移除非数字字符
            value = value.replace(/\D/g, '');
            
            // 限制长度
            if (value.length > 16) {
                value = value.substr(0, 16);
            }
            
            // 格式化为 4 位一组
            var formatted = value.match(/.{1,4}/g);
            return formatted ? formatted.join(' ') : value;
        },
        
        detectCardType: function(cardNumber) {
            for (var type in this.cardPatterns) {
                if (this.cardPatterns[type].test(cardNumber)) {
                    return type;
                }
            }
            return null;
        },
        
        updateCardTypeIndicator: function(cardType) {
            var $indicator = $('#onepay_card_type');
            
            if (cardType) {
                var displayName = cardType.charAt(0).toUpperCase() + cardType.slice(1);
                if (cardType === 'mastercard') displayName = 'MasterCard';
                if (cardType === 'amex') displayName = 'Amex';
                if (cardType === 'jcb') displayName = 'JCB';
                
                $indicator
                    .text(displayName)
                    .removeClass('visa mastercard amex discover jcb')
                    .addClass(cardType)
                    .fadeIn(200);
            } else {
                $indicator.fadeOut(200);
            }
        },
        
        highlightSupportedCard: function(cardType) {
            $('.onepay-supported-card-icon').removeClass('active');
            
            if (cardType) {
                $('.onepay-supported-card-icon[data-card-type="' + cardType.toUpperCase() + '"]').addClass('active');
            }
        },
        
        formatExpiry: function($input) {
            var value = $input.val().replace(/\D/g, '');
            
            if (value.length >= 2) {
                var month = value.substring(0, 2);
                var year = value.substring(2, 4);
                
                // 验证月份
                if (parseInt(month) > 12) {
                    month = '12';
                }
                
                value = month + (year ? '/' + year : '');
            }
            
            $input.val(value);
        },
        
        formatCVV: function($input) {
            var value = $input.val().replace(/\D/g, '');
            var cardType = this.detectCardType($('#onepay_card_number').val().replace(/\s/g, ''));
            
            // AMEX 使用 4 位 CVV，其他使用 3 位
            var maxLength = (cardType === 'amex') ? 4 : 3;
            
            if (value.length > maxLength) {
                value = value.substr(0, maxLength);
            }
            
            $input.val(value);
            
            // 更新 placeholder
            $input.attr('placeholder', cardType === 'amex' ? '1234' : '123');
        },
        
        validateCardNumber: function($input) {
            var cardNumber = $input.val().replace(/\s/g, '');
            
            if (cardNumber.length < 13) {
                this.showFieldError($input, '卡号长度不足');
                return false;
            }
            
            if (!this.luhnCheck(cardNumber)) {
                this.showFieldError($input, '卡号无效');
                return false;
            }
            
            this.clearFieldError($input);
            return true;
        },
        
        luhnCheck: function(cardNumber) {
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
        
        validateExpiry: function($input) {
            var value = $input.val();
            var match = value.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
            
            if (!match) {
                this.showFieldError($input, '请输入有效的日期格式 (MM/YY)');
                return false;
            }
            
            var month = parseInt(match[1], 10);
            var year = parseInt('20' + match[2], 10);
            var now = new Date();
            var currentYear = now.getFullYear();
            var currentMonth = now.getMonth() + 1;
            
            if (year < currentYear || (year === currentYear && month < currentMonth)) {
                this.showFieldError($input, '卡片已过期');
                return false;
            }
            
            this.clearFieldError($input);
            return true;
        },
        
        validateForm: function() {
            // 只在选择了信用卡支付时验证
            if ($('#payment_method_onepay_cards').is(':checked')) {
                var isValid = true;
                
                // 验证卡号
                if (!this.validateCardNumber($('#onepay_card_number'))) {
                    isValid = false;
                }
                
                // 验证有效期
                if (!this.validateExpiry($('#onepay_card_expiry'))) {
                    isValid = false;
                }
                
                // 验证 CVV
                var cvv = $('#onepay_card_cvv').val();
                if (!cvv || cvv.length < 3) {
                    this.showFieldError($('#onepay_card_cvv'), '请输入有效的CVV码');
                    isValid = false;
                } else {
                    this.clearFieldError($('#onepay_card_cvv'));
                }
                
                if (!isValid) {
                    this.scrollToError();
                    return false;
                }
            }
            
            return true;
        },
        
        showFieldError: function($field, message) {
            var $group = $field.closest('.onepay-form-group');
            $group.find('.field-error').remove();
            $group.append('<span class="field-error" style="color: #ef4444; font-size: 12px; margin-top: 4px; display: block;">' + message + '</span>');
            $field.css('border-color', '#ef4444');
        },
        
        clearFieldError: function($field) {
            var $group = $field.closest('.onepay-form-group');
            $group.find('.field-error').remove();
            $field.css('border-color', '');
        },
        
        scrollToError: function() {
            var $firstError = $('.field-error:first');
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        },
        
        handlePaymentMethodChange: function($input) {
            if ($input.val() === 'onepay_cards' && $input.is(':checked')) {
                // 添加焦点到第一个输入框
                setTimeout(function() {
                    $('#onepay_card_number').focus();
                }, 400);
            }
        }
    };
    
    $(document).ready(function() {
        OnePay_Cards.init();
    });
    
})(jQuery);