<?php
if (!defined('ABSPATH')) {
    exit;
}
_e('Payment', 'woocommerce-maxipago');
echo "\n\n";
_e('Please use the link below to pay your Order:', 'woocommerce-maxipago');
echo "\n";
echo $url;
echo "\n";
_e('After we receive the payment confirmation, your order will be processed.', 'woocommerce-maxipago');
echo "\n\n";