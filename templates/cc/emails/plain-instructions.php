<?php
if (!defined('ABSPATH')) {
    exit;
}
_e('Payment', 'woocommerce-maxipago');
echo "\n\n";
printf(__('Payment successfully: %s in %s.', 'woocommerce-maxipago'), $brand, $installments . 'x');
echo "\n\n";