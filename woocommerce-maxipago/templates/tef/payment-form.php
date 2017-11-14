<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<fieldset id="maxipago-tef-form" class='wc-tef-form wc-payment-form'>
    <div class="form-row form-row-wide">
        <label><?php _e( 'Select the Bank', 'woocommerce-maxipago' ); ?> <span class="required">*</span></label>
        <div class="maxipago-bank-selector">
        <?php foreach ($tef_banks as $bank_code => $bank):?>
            <input id="maxipago-bank-<?php echo $bank_code?>" type="radio" name="maxipago_tef_bank_code" value="<?php echo $bank_code?>" />
            <label class="maxipago-logo-bank maxipago-bank-<?php echo $bank_code?>" for="maxipago-bank-<?php echo $bank_code?>"></label>
        <?php endforeach; ?>
        </div>
	</div>
    <p class="form-row form-row-wide">
        <label for="maxipago-tef-document"><?php _e( 'CPF/CNPJ', 'woocommerce-maxipago' ); ?> <span class="required">*</span></label>
        <input id="maxipago-tef-document" name="maxipago_tef_document" class="input-text" type="tel" inputmode="numeric" maxlength="20" autocomplete="off" style="font-size: 1.5em; padding: 8px;" />
    </p>
	<div class="clear"></div>
</fieldset>
<div id="maxipago-tef-instructions">
    <p>&nbsp;</p><p><?php _e( 'After clicking "Place order" you will have access to link to pay order.', 'woocommerce-maxipago' ); ?><br /><?php _e( 'Note: The order will be confirmed only after the payment approval.', 'woocommerce-maxipago' ); ?></p>
</div>
<script type="text/javascript">
//<![CDATA[

//var documentTefMaskBehavior = function (val) {
//        if ((jQuery('#maxipago-tef-document').val().length > 14)) {
//            return '00.000.000/0000-00';
//        } else {
//            return '000.000.000-00';
//        }
//    },
//    documentTefOptions = {
//        'onFocus': function(val, e, field, options) {
//            field.mask(documentTefMaskBehavior.apply({}, arguments), options);
//        },
//        'onChange': function(val, e, field, options) {
//            field.mask(documentTefMaskBehavior.apply({}, arguments), options);
//        },
//        'onKeyUp': function(val, e, field, options) {
//            field.mask(documentTefMaskBehavior.apply({}, arguments), options);
//        },
//        'onKeyPress': function(val, e, field, options) {
//            field.mask(documentTefMaskBehavior.apply({}, arguments), options);
//        },
//        'placeholder': function(){
//            if (jQuery('#maxipago-tef-document').val().length > 14) {
//                return '__.___.___/____-__';
//            } else {
//                return '___.___.___-__';
//            }
//        }
//    };
//
//jQuery( function( $ ) {
//    $( '#maxipago-tef-document' ).mask( documentTefMaskBehavior, documentTefOptions );
//});
//]]>
</script>