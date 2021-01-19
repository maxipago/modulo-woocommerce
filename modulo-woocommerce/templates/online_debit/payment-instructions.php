<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woocommerce-message">
    <span><a class="button" href="<?php echo $url; ?>" target="_blank"
             style="display: block !important; visibility: visible !important;"><?php _e( 'Pay the Order',
			    'modulo-woocommerce' ); ?></a><?php _e( 'Please click in the following button to pay your Order.',
		    'modulo-woocommerce' ); ?>
        <br/><?php _e( 'After we receive the payment confirmation, your order will be processed.',
		    'modulo-woocommerce' ); ?></span>
</div>