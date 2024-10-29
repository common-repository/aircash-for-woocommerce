jQuery(function ($) {
    'use strict';
    let aircashCheck = function () {
        $.ajax({
            url: aircash.url,
            method: 'POST',
            data: {
                'action': 'aircash_order_check',
                '_ajax_nonce': aircash.nonce,
                'order': aircash.order
            },
            success: function (response) {
                if (typeof response.aircash_status !== 'undefined' && response.aircash_status === 'success') {
                    jQuery('.aircash-wrapper').addClass('success');
                    setTimeout(() => {
                        window.location.href = response.url;
                    }, 2000);
                }
            },
            error: function (response) {
            }
        })
    }
    aircashCheck();

    const interval = setInterval(() => {
        aircashCheck();
    }, 1500);
    setTimeout(function () {
        clearInterval(interval);
        // @TODO redirect to order timed out
    }, aircash.timeout * 60 * 1000);
});