/**
 * OnePay WooCommerce Blocks Integration
 */
import { __ } from '@wordpress/i18n';
import { createElement, useState, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';

const { React } = window;

// Get payment method data from server
const settings = getSetting('onepay_data', {});

/**
 * OnePay Payment Method Label Component
 */
const OnePayLabel = ({ components }) => {
    const { PaymentMethodLabel } = components;
    const label = decodeEntities(settings.title || __('OnePay', 'onepay'));
    
    return createElement(PaymentMethodLabel, { text: label });
};

/**
 * OnePay Payment Method Content Component
 */
const OnePayContent = ({ eventRegistration, emitResponse }) => {
    const { onPaymentSetup } = eventRegistration;
    const [selectedMethod, setSelectedMethod] = useState(settings.defaultMethod || 'FPS');
    const { responseTypes, noticeContexts } = emitResponse;

    // Register payment setup handler
    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            // Validate payment method selection
            if (!selectedMethod) {
                return {
                    type: responseTypes.ERROR,
                    message: __('Please select a payment method.', 'onepay'),
                    messageContext: noticeContexts.PAYMENTS
                };
            }

            // Return payment data
            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        onepay_payment_method: selectedMethod
                    }
                }
            };
        });

        return () => {
            unsubscribe();
        };
    }, [
        selectedMethod,
        onPaymentSetup,
        responseTypes.ERROR,
        responseTypes.SUCCESS,
        noticeContexts.PAYMENTS
    ]);

    // Handle payment method selection
    const handleMethodChange = (method) => {
        setSelectedMethod(method);
    };

    return createElement('div', { className: 'onepay-payment-methods' }, [
        settings.description && createElement('p', { 
            key: 'description',
            dangerouslySetInnerHTML: { __html: decodeEntities(settings.description) }
        }),
        
        // Payment methods selection
        createElement('div', { key: 'methods', className: 'onepay-method-selection' },
            Object.keys(settings.paymentMethods || {}).map(methodKey => {
                const method = settings.paymentMethods[methodKey];
                const isSelected = selectedMethod === methodKey;
                
                return createElement('label', {
                    key: methodKey,
                    className: `onepay-payment-method ${isSelected ? 'selected' : ''}`
                }, [
                    createElement('input', {
                        key: 'input',
                        type: 'radio',
                        name: 'onepay_payment_method',
                        value: methodKey,
                        checked: isSelected,
                        onChange: () => handleMethodChange(methodKey)
                    }),
                    createElement('div', { key: 'info', className: 'method-info' }, [
                        createElement('strong', { key: 'name' }, method.name),
                        createElement('div', { 
                            key: 'desc', 
                            className: 'method-description' 
                        }, method.description)
                    ])
                ]);
            })
        )
    ]);
};

/**
 * OnePay Edit Component (for block editor)
 */
const OnePayEdit = () => {
    return createElement('div', { className: 'onepay-payment-methods preview' }, [
        createElement('p', { key: 'title' }, 
            decodeEntities(settings.title || __('OnePay', 'onepay'))
        ),
        settings.description && createElement('p', { 
            key: 'description',
            dangerouslySetInnerHTML: { __html: decodeEntities(settings.description) }
        }),
        createElement('div', { key: 'preview', className: 'onepay-method-selection' },
            __('Payment method selection will appear here during checkout.', 'onepay')
        )
    ]);
};

/**
 * OnePay Payment Method Configuration
 */
const OnePayPaymentMethod = {
    name: 'onepay',
    label: OnePayLabel,
    content: OnePayContent,
    edit: OnePayEdit,
    canMakePayment: () => true,
    ariaLabel: decodeEntities(settings.title || __('OnePay payment method', 'onepay')),
    supports: {
        features: settings.supports || ['products']
    }
};

// Register the payment method
registerPaymentMethod(OnePayPaymentMethod);