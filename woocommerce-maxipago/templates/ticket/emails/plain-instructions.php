<?php
if (!defined('ABSPATH')) {
    exit;
}
_e('Payment', 'woocommerce-maxipago');
echo "\n\n";
_e('Please use the link below to view your Ticket, you can print and pay in your internet banking or in a lottery retailer:', 'woocommerce-maxipago');
echo "\n";
echo $url;
echo "\n";
_e('After we receive the payment confirmation, your order will be processed.', 'woocommerce-maxipago');
echo "\n\n";