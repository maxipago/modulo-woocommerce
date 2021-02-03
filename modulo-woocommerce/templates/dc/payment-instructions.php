<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woocommerce-message">
	<span>
        <a class="button" href="<?php echo $url; ?>" target="_blank"
           style="display: block !important; visibility: visible !important;">Pagar
        </a>
        Por favor, clique no link a seguir para pagar seu pedido.
        <br/>
        Seu pedido será processado tão logo recebermos a confirmação do pagamento.
    </span>
    <script>
        window.location.href = "<?= $url ?>";
    </script>
</div>