(function ($) {
    'use strict';
    $(function () {
        $('#woocommerce_maxipago-cc_processing_type').on('change', function () {
            if ($('#woocommerce_maxipago-cc_processing_type').val() == 'auth') {
                $('#woocommerce_maxipago-cc_fraud_check').closest('tr').show();
                $('#woocommerce_maxipago-cc_fraud_check').change();
            } else {
                $('#woocommerce_maxipago-cc_fraud_check').attr('checked', false);
                $('#woocommerce_maxipago-cc_fraud_check').change();
                $('#woocommerce_maxipago-cc_fraud_check').closest('tr').hide();
            }
        }).change();

        $('#woocommerce_maxipago-cc_fraud_check').on('change', function () {
            if ($('#woocommerce_maxipago-cc_fraud_check').attr('checked')) {
                $('#woocommerce_maxipago-cc_auto_capture').closest('tr').show();
                $('#woocommerce_maxipago-cc_auto_void').closest('tr').show();
                $('#woocommerce_maxipago-cc_fraud_processor').closest('tr').show();
                $('#woocommerce_maxipago-cc_fraud_processor').change();
            } else {
                $('#woocommerce_maxipago-cc_auto_capture').closest('tr').hide();
                $('#woocommerce_maxipago-cc_auto_capture').attr('checked', false);

                $('#woocommerce_maxipago-cc_auto_void').closest('tr').hide();
                $('#woocommerce_maxipago-cc_auto_void').attr('checked', false);

                $('#woocommerce_maxipago-cc_fraud_processor').val('99');
                $('#woocommerce_maxipago-cc_fraud_processor').change();
                $('#woocommerce_maxipago-cc_fraud_processor').closest('tr').hide();
            }
        }).change();

        $('#woocommerce_maxipago-cc_fraud_processor').on('change', function () {
            if ($('#woocommerce_maxipago-cc_fraud_processor').val() == '98') {
                $('#woocommerce_maxipago-cc_clearsale_app').closest('tr').show();
            } else {
                $('#woocommerce_maxipago-cc_clearsale_app').val('');
                $('#woocommerce_maxipago-cc_clearsale_app').closest('tr').hide();
            }
        }).change();

        jQuery('.seller_installment_payment_checkbox').on('change', function () {
            var index = jQuery(this).data('index');
            var input = document.getElementById('mp_sellers_installments_amount_' + index);
            input.disabled = !this.checked;
            input.value = this.checked ? '1' : '';
        });
    });

}(jQuery));
