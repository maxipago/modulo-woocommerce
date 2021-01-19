<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php _e( 'Payment', 'modulo-woocommerce' ); ?></h2>
<p class="order_details">Por favor, use o link a seguir para ver seu boleto. Você poderá pagá-lo em um internet bancking
    ou imprimi-lo para pagar no seu banco.
    <br/><a class="button" href="<?php echo $url; ?>"
            target="_blank">Pagar</a><br/>Seu pedido será processado tão logo identifiquemos o pagamento.
</p>