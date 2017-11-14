<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="woocommerce-message">
    <span><?php printf(__('Payment successfully: %s in %s.', 'woocommerce-maxipago'), '<strong>' . $brand . '</strong>', '<strong>' . $installments . 'x</strong>'); ?></span>
</div>