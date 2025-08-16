/**
 * OnePay 结账页面支付选项增强脚本
 * 实现图标|文本|选中状态的自定义布局
 */
(function($) {
    'use strict';
    
    var OnePayCheckoutEnhancement = {
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.enhancePaymentMethods();
            
            // 监听页面变化（AJAX更新）
            $(document.body).on('updated_checkout', this.enhancePaymentMethods.bind(this));
        },
        
        // 绑定事件
        bindEvents: function() {
            var self = this;
            
            // 处理支付方法选择
            $(document).on('click', '.wc_payment_methods li', function(e) {
                if (!$(e.target).is('input')) {
                    var radio = $(this).find('input[type="radio"]');
                    if (radio.length && !radio.is(':disabled')) {
                        radio.prop('checked', true).trigger('change');
                        self.updateSelectedState();
                        self.togglePaymentBoxes();
                    }
                }
            });
            
            // 处理radio按钮变化
            $(document).on('change', '.wc_payment_methods input[type="radio"]', function() {
                self.updateSelectedState();
                self.togglePaymentBoxes();
            });
        },
        
        // 增强支付方法显示
        enhancePaymentMethods: function() {
            var self = this;
            
            $('.wc_payment_methods.payment_methods.methods li').each(function() {
                self.enhancePaymentMethodItem($(this));
            });
            
            this.updateSelectedState();
            this.togglePaymentBoxes();
        },
        
        // 增强单个支付方法项
        enhancePaymentMethodItem: function($item) {
            if ($item.hasClass('onepay-enhanced')) {
                return; // 已经增强过了
            }
            
            var $label = $item.find('label');
            var $radio = $item.find('input[type="radio"]');
            var $img = $label.find('img');
            
            if (!$label.length) return;
            
            // 获取支付方法文本（移除图标）
            var labelText = this.getCleanLabelText($label);
            
            // 创建新的标签结构
            var $newLabel = $('<label></label>')
                .attr('for', $label.attr('for'))
                .addClass('onepay-payment-method-label');
            
            // 创建左侧内容容器
            var $leftContent = $('<div class="payment-method-left"></div>');
            
            // 处理图标
            if ($img.length) {
                // 克隆现有图标
                var $newImg = $img.clone();
                $newImg.addClass('payment-method-icon');
                $leftContent.append($newImg);
            } else {
                // 创建默认图标
                var $defaultIcon = $('<span class="payment-method-default-icon"></span>');
                $leftContent.append($defaultIcon);
            }
            
            // 创建文本容器
            var $textContainer = $('<span class="payment-method-text"></span>').text(labelText);
            $leftContent.append($textContainer);
            
            // 创建右侧选中状态指示器
            var $rightContent = $('<div class="payment-method-right"></div>');
            var $indicator = $('<span class="payment-method-indicator"></span>');
            $rightContent.append($indicator);
            
            // 组装新标签
            $newLabel.append($leftContent).append($rightContent);
            
            // 替换原标签
            $label.replaceWith($newLabel);
            
            // 标记为已增强
            $item.addClass('onepay-enhanced');
            
            // 确保radio按钮与标签正确关联
            if ($radio.length) {
                $newLabel.attr('for', $radio.attr('id'));
            }
        },
        
        // 获取清洁的标签文本（移除图标和多余空白）
        getCleanLabelText: function($label) {
            var $tempLabel = $label.clone();
            $tempLabel.find('img').remove();
            var text = $tempLabel.text().trim();
            
            // 移除常见的多余字符和空白
            text = text.replace(/\s+/g, ' ').trim();
            
            return text;
        },
        
        // 更新选中状态
        updateSelectedState: function() {
            $('.wc_payment_methods.payment_methods.methods li').removeClass('selected');
            $('.wc_payment_methods.payment_methods.methods li').each(function() {
                var $radio = $(this).find('input[type="radio"]');
                if ($radio.length && $radio.is(':checked')) {
                    $(this).addClass('selected');
                }
            });
        },
        
        // 切换支付框显示状态
        togglePaymentBoxes: function() {
            // 隐藏所有支付框
            $('.wc_payment_methods .payment_box').hide();
            
            // 显示选中支付方式的表单
            var $selectedRadio = $('.wc_payment_methods input[type="radio"]:checked');
            if ($selectedRadio.length) {
                var $selectedLi = $selectedRadio.closest('li');
                var $paymentBox = $selectedLi.find('.payment_box');
                if ($paymentBox.length) {
                    $paymentBox.show();
                }
            }
        },
        
        // 为特定支付网关添加自定义图标
        addCustomIcons: function() {
            var customIcons = {
                'payment_method_onepay_fps': {
                    emoji: '⚡',
                    color: '#4CAF50'
                },
                'payment_method_onepay_russian_card': {
                    emoji: '🇷🇺',
                    color: '#FF6B6B'
                },
                'payment_method_onepay_cards': {
                    emoji: '💳',
                    color: '#2196F3'
                },
                'payment_method_bacs': {
                    emoji: '🏦',
                    color: '#666'
                },
                'payment_method_cheque': {
                    emoji: '💰',
                    color: '#888'
                },
                'payment_method_cod': {
                    emoji: '📦',
                    color: '#FF9800'
                },
                'payment_method_paypal': {
                    emoji: '💙',
                    color: '#0070BA'
                }
            };
            
            $.each(customIcons, function(className, config) {
                var $item = $('.' + className);
                if ($item.length) {
                    var $defaultIcon = $item.find('.payment-method-default-icon');
                    if ($defaultIcon.length) {
                        $defaultIcon
                            .text(config.emoji)
                            .css({
                                'background': config.color,
                                'color': 'white'
                            });
                    }
                }
            });
        },
        
        // 确保样式加载
        ensureStyles: function() {
            if ($('#onepay-checkout-styles').length === 0) {
                var stylesUrl = onePayCheckoutData.pluginUrl + 'assets/css/onepay-checkout-payment-styles.css';
                $('head').append('<link id="onepay-checkout-styles" rel="stylesheet" type="text/css" href="' + stylesUrl + '?v=' + onePayCheckoutData.version + '">');
            }
        }
    };
    
    // 文档就绪时初始化
    $(document).ready(function() {
        OnePayCheckoutEnhancement.init();
        OnePayCheckoutEnhancement.addCustomIcons();
        
        // 延迟执行以确保WooCommerce完全加载
        setTimeout(function() {
            OnePayCheckoutEnhancement.enhancePaymentMethods();
        }, 500);
    });
    
    // 暴露到全局作用域以便调试
    window.OnePayCheckoutEnhancement = OnePayCheckoutEnhancement;
    
})(jQuery);