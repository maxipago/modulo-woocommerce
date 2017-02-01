<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="error inline">
    <p>
        <strong><?php _e('maxiPago Disabled', 'woocommerce-maxipago'); ?></strong>: <?php printf(__('Currency <code>%s</code> is not supported. Works with %s.', 'woocommerce-maxipago'), get_woocommerce_currency(), '<code>' . implode(', ', $this->get_supported_currencies()) . '</code>'); ?>
    </p>
</div>
