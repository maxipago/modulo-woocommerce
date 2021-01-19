<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
_e( 'Payment', 'modulo-woocommerce' );
echo "\n\n";
_e( 'Please use the link below to pay your Order:', 'modulo-woocommerce' );
echo "\n";
echo $url;
echo "\n";
_e( 'After we receive the payment confirmation, your order will be processed.', 'modulo-woocommerce' );
echo "\n\n";