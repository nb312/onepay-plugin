/**
 * OnePay Admin JavaScript
 */
(function($) {
    'use strict';
    
    var OnePayAdmin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#onepay_test_connection').on('click', this.testConnection);
            $('#onepay_generate_keys').on('click', this.generateKeys);
            $('#onepay_validate_keys').on('click', this.validateKeys);
            $('#onepay_run_tests').on('click', this.runFullTests);
            $('input[name="woocommerce_onepay_private_key"], input[name="woocommerce_onepay_platform_public_key"]').on('blur', this.validateKeysOnBlur);
        },
        
        testConnection: function(e) {
            e.preventDefault();
            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');
            
            $.post(ajaxurl, {
                action: 'onepay_test_connection',
                nonce: onepay_admin.nonce
            }, function(response) {
                if (response.success) {
                    alert('Connection successful!');
                } else {
                    alert('Connection failed: ' + response.data);
                }
            }).always(function() {
                $button.prop('disabled', false).text('Test Connection');
            });
        },
        
        generateKeys: function(e) {
            e.preventDefault();
            alert('Please generate RSA keys externally and paste them in the configuration fields.');
        },
        
        validateKeys: function(e) {
            e.preventDefault();
            var $button = $(this);
            $button.prop('disabled', true).text('Validating...');
            
            var privateKey = $('textarea[name="woocommerce_onepay_private_key"]').val();
            var publicKey = $('textarea[name="woocommerce_onepay_platform_public_key"]').val();
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_validate_keys',
                nonce: onepay_admin.nonce,
                private_key: privateKey,
                public_key: publicKey
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Key Validation Results:\n';
                    message += 'Private Key: ' + (data.private_valid ? 'Valid' : 'Invalid') + '\n';
                    message += 'Public Key: ' + (data.public_valid ? 'Valid' : 'Invalid') + '\n';
                    message += 'Signature Test: ' + (data.signature_test ? 'Passed' : 'Failed');
                    alert(message);
                } else {
                    alert('Key validation failed: ' + response.data);
                }
            }).always(function() {
                $button.prop('disabled', false).text('Validate Keys');
            });
        },
        
        validateKeysOnBlur: function() {
            var $field = $(this);
            var keyType = $field.attr('name').includes('private') ? 'private' : 'public';
            var keyValue = $field.val().trim();
            
            if (keyValue) {
                // Simple validation - check if key has proper format
                var hasBegin = keyValue.includes('-----BEGIN');
                var hasEnd = keyValue.includes('-----END');
                
                if (hasBegin && hasEnd) {
                    $field.removeClass('onepay-invalid-key').addClass('onepay-valid-key');
                } else {
                    $field.removeClass('onepay-valid-key').addClass('onepay-invalid-key');
                }
            } else {
                $field.removeClass('onepay-valid-key onepay-invalid-key');
            }
        },
        
        runFullTests: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $resultDiv = $('#onepay_tools_result');
            
            $button.prop('disabled', true).text('Running Tests...');
            $resultDiv.html('<p>Running comprehensive tests...</p>').show();
            
            $.post(onepay_admin.ajax_url, {
                action: 'onepay_run_tests',
                nonce: onepay_admin.nonce
            }, function(response) {
                if (response.success) {
                    $resultDiv.html(response.data.report_html);
                    
                    // Show summary
                    var results = response.data.results.overall;
                    var message = 'Tests completed: ' + results.total_passed + '/' + results.total_tests + 
                                ' passed (' + results.success_rate + '%). Status: ' + results.status;
                    
                    if (results.overall_success) {
                        $resultDiv.prepend('<div class="notice notice-success"><p>' + message + '</p></div>');
                    } else {
                        $resultDiv.prepend('<div class="notice notice-error"><p>' + message + '</p></div>');
                    }
                } else {
                    $resultDiv.html('<div class="notice notice-error"><p>Test failed: ' + response.data + '</p></div>');
                }
            }).fail(function() {
                $resultDiv.html('<div class="notice notice-error"><p>Failed to run tests. Please try again.</p></div>');
            }).always(function() {
                $button.prop('disabled', false).text('Run Full Tests');
            });
        }
    };
    
    $(document).ready(function() {
        OnePayAdmin.init();
    });
    
})(jQuery);