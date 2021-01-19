<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
_e( 'Payment', 'modulo-woocommerce' );
echo "\n\n";
printf( __( 'Payment successfully: %s in %s.', 'modulo-woocommerce' ), $brand, $installments . 'x' );
echo "\n\n";