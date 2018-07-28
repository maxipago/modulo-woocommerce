<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2><?php _e('Payment', 'woocommerce-maxipago'); ?></h2>
<p class="order_details"><?php _e('Please use the link below to view your Ticket, you can print and pay in your internet banking or in a lottery retailer:', 'woocommerce-maxipago'); ?>
    <br/><a class="button" href="<?php echo $url; ?>"
            target="_blank"><?php _e('Pay the Order', 'woocommerce-maxipago'); ?></a><br/><?php _e('After we receive the payment confirmation, your order will be processed.', 'woocommerce-maxipago'); ?>
</p>