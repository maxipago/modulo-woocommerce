<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php _e( 'Payment', 'modulo-woocommerce' ); ?></h2>
<p class="order_details"><?php _e( 'Please use the link below to pay your Order:', 'modulo-woocommerce' ); ?><br/><a
            class="button" href="<?php echo $url; ?>"
            target="_blank"><?php _e( 'Pay the Order',
			'modulo-woocommerce' ); ?></a><br/><?php _e( 'After we receive the payment confirmation, your order will be processed.',
		'modulo-woocommerce' ); ?>
</p>