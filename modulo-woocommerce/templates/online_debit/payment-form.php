<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $online_debit_banks ) ): ?>
	<?php _e( 'Nenhum banco configurado, entre em contato com nosso suporte técnico.', 'modulo-woocommerce' ); ?>
<?php endif; ?>
<fieldset id="maxipago-online_debit-form" class='wc-online_debit-form wc-payment-form'>
    <div class="form-row form-row-wide">
        <label><?php _e( 'Select the Bank', 'modulo-woocommerce' ); ?> <span class="required">*</span></label>
        <div class="maxipago-bank-selector">
			<?php foreach ( $online_debit_banks as $bank_code => $bank ): ?>
                <input id="maxipago-bank-<?php echo $bank_code ?>" type="radio" name="maxipago_online_debit_bank_code"
                       value="<?php echo $bank_code ?>"/>
                <label class="maxipago-logo-bank maxipago-bank-<?php echo $bank_code ?>"
                       for="maxipago-bank-<?php echo $bank_code ?>"></label>
			<?php endforeach; ?>
        </div>
    </div>
    <p class="form-row form-row-wide">
        <label for="maxipago-online_debit-document"><?php _e( 'CPF/CNPJ', 'modulo-woocommerce' ); ?> <span
                    class="required">*</span></label>
        <input id="maxipago-online_debit-document" name="maxipago_online_debit_document" class="input-text" type="tel"
               inputmode="numeric"
               maxlength="20" autocomplete="off" style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
<div id="maxipago-online_debit-instructions">
    <p>&nbsp;</p>
    <p><?php _e( 'After clicking "Place order" you will have access to link to pay order.', 'modulo-woocommerce' ); ?>
        <br/><?php _e( 'Note: The order will be confirmed only after the payment approval.', 'modulo-woocommerce' ); ?>
    </p>
</div>