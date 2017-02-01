<?php
if (!defined('ABSPATH')) {
    exit;
}
$fields = array();
$order_total = $this->get_order_total();
$installments = $this->api->get_installments_html($order_total);
$request_uri = $_SERVER['REQUEST_URI'];
$default_fields = array(
    'card-number-field' => '<p class="form-row form-row-wide">
    <label for="maxipago-card-number" style="font-size: 1.20em;">' . __('Card Number', 'woocommerce-maxipago') . ' <span class="required">*</span></label>
    <input id="maxipago-card-number" name="maxipago_card_number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" style="font-size: 1.5em; padding: 8px;" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" />
</p>',
    'card-holder-name-field' => '<p class="form-row form-row-wide">
    <label for="maxipago-holder-name" style="font-size: 1.20em;">' . __('Name Printed on the Card', 'woocommerce-maxipago') . ' <span class="required">*</span></label>
    <input id="maxipago-holder-name" name="maxipago_holder_name" class="input-text wc-credit-card-form-card-holder-name" autocomplete="cc-holder" autocorrect="no" autocapitalize="no" spellcheck="no" style="font-size: 1.5em; padding: 8px;" />
</p>',
    'card-expiry-field' => '<p class="form-row form-row-first">
    <label for="maxipago-card-expiry" style="font-size: 1.20em;">' . __('Expiry (MM/YYYY)', 'woocommerce-maxipago') . ' <span class="required">*</span></label>
    <input id="maxipago-card-expiry" name="maxipago_card_expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" style="font-size: 1.5em; padding: 8px;" placeholder="' . esc_attr__('MM / YYYY', 'woocommerce-maxipago') . '" />
</p>',
    'card-cvc-field' => '<p class="form-row form-row-last">
    <label for="maxipago-card-cvc" style="font-size: 1.20em;">' . __('Security Code', 'woocommerce-maxipago') . ' <span class="required">*</span></label>
    <input id="maxipago-card-cvc" name="maxipago_card_cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVV', 'woocommerce-maxipago') . '" style="font-size: 1.5em; padding: 8px;" />
</p>',
    'card-installments-field' => '<p class="form-row form-row-wide">
    <label for="maxipago-installments" style="font-size: 1.20em;">' . __('Installments', 'woocommerce-maxipago') . ' <span class="required">*</span></label>'.
    $installments . '
</p>'
);
$fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
?>
<fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form '>
    <?php
        do_action('woocommerce_credit_card_form_start', $this->id);
        foreach ($fields as $key => $field) {
            if(strpos($request_uri,'add-payment-method') !== false &&
                ($key == 'card-installments-field' || $key == 'card-holder-name-field' || $key == 'card-cvc-field')) {
                continue;
            } elseif($key == 'card-installments-field' && $this->installments == 1) {
                echo '<input type="hidden" name="maxipago_installments" value="1"/>';
            } else {
                echo $field;
            }
        }
        do_action('woocommerce_credit_card_form_end', $this->id);
    ?>
    <div class="clear"></div>
</fieldset>
<?php if(strpos($request_uri,'add-payment-method')===false):?>
    <fieldset class="fieldset-cvc-token" style="display: none">
        <p class="form-row form-row-first">
            <label for="maxipago-card-cvc-token" style="font-size: 1.20em;"><?php echo __('Security Code', 'woocommerce-maxipago')?><span class="required">*</span></label>
            <input id="maxipago-card-cvc-token" name="maxipago_card_cvc_token" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="<?php echo esc_attr__('CVV', 'woocommerce-maxipago') ?>" style="font-size: 1.5em; padding: 8px;" />
        </p>
    </fieldset>
<?php endif; ?>