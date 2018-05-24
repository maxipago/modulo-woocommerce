<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="woocommerce-message">
    <span><a class="button" href="<?php echo $url; ?>" target="_blank"
             style="display: block !important; visibility: visible !important;"><?php _e('Pay the Order', 'woocommerce-maxipago'); ?></a><?php _e('Please click in the following button to pay your Order.', 'woocommerce-maxipago'); ?>
        <br/><?php _e('After we receive the payment confirmation, your order will be processed.', 'woocommerce-maxipago'); ?></span>
</div>