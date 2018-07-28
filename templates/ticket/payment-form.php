<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<fieldset id="maxipago-tef-form" class='wc-tef-form wc-payment-form'>
    </p>
    <p class="form-row form-row-wide">
        <label for="maxipago-ticket-document"><?php _e('CPF/CNPJ', 'woocommerce-maxipago'); ?> <span
                    class="required">*</span></label>
        <input id="maxipago-ticket-document" name="maxipago_ticket_document" class="input-text" type="tel"
               inputmode="numeric" maxlength="20" autocomplete="off" style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
<div id="maxipago-ticket-instructions">
    <p>&nbsp;</p>
    <p><?php _e('After clicking "Place order" you will have access to ticket which you can print and pay in your internet banking or in a lottery retailer.', 'woocommerce-maxipago'); ?>
        <br/><?php _e('Note: The order will be confirmed only after the payment approval.', 'woocommerce-maxipago'); ?>
    </p>
</div>

<script type="text/javascript">
    //<![CDATA[

    //    var documentTicketMaskBehavior = function (val) {
    //            if ((jQuery('#maxipago-ticket-document').val().length > 14)) {
    //                return '00.000.000/0000-00';
    //            } else {
    //                return '000.000.000-00';
    //            }
    //        },
    //        documentTicketOptions = {
    //            'onFocus': function(val, e, field, options) {
    //                field.mask(documentTicketMaskBehavior.apply({}, arguments), options);
    //            },
    //            'onChange': function(val, e, field, options) {
    //                field.mask(documentTicketMaskBehavior.apply({}, arguments), options);
    //            },
    //            'onKeyUp': function(val, e, field, options) {
    //                field.mask(documentTicketMaskBehavior.apply({}, arguments), options);
    //            },
    //            'onKeyPress': function(val, e, field, options) {
    //                field.mask(documentTicketMaskBehavior.apply({}, arguments), options);
    //            },
    //            'placeholder': function(){
    //                if (jQuery('#maxipago-ticket-document').val().length > 14) {
    //                    return '__.___.___/____-__';
    //                } else {
    //                    return '___.___.___-__';
    //                }
    //            }
    //        };
    //
    //    jQuery( function( $ ) {
    //        $( '#maxipago-ticket-document' ).mask( documentTicketMaskBehavior, documentTicketOptions );
    //    });
    //]]>
</script>