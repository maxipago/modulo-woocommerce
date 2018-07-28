<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2><?php _e('Payment', 'woocommerce-maxipago'); ?></h2>
<p class="order_details"><?php printf(__('Payment successfully: %s in %s.', 'woocommerce-maxipago'), '<strong>' . $brand . '</strong>', '<strong>' . $installments . 'x</strong>'); ?></p>
