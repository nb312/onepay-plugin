/**
 * OnePay Admin Notices JavaScript
 * Handle dismissible notices
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle dismissible notices
        $(document).on('click', '.notice.is-dismissible[data-dismiss-key] .notice-dismiss', function(e) {
            var $notice = $(this).closest('.notice');
            var dismissKey = $notice.data('dismiss-key');
            
            if (dismissKey && onepay_admin_notices) {
                $.post(onepay_admin_notices.ajax_url, {
                    action: 'onepay_dismiss_notice',
                    dismiss_key: dismissKey,
                    nonce: onepay_admin_notices.nonce
                });
            }
        });
    });
    
})(jQuery);