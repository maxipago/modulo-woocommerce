(function ($) {
    'use strict';
    $(function () {
        $('#woocommerce_maxipago-cc_processing_type').on('change', function () {
            var fields = $('#woocommerce_maxipago-cc_fraud_check').closest('tr');
            if ('auth' === $(this).val()) {
                fields.show();
            } else {
                fields.hide();
            }
        }).change();
    });
}(jQuery));
