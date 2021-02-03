<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woocommerce-message">
    <span>O pagamento com seu <strong><?= $brand ?></strong>&nbsp;

        <?php if ( ! empty( $installments ) ): ?>
            em <strong><?= $installments ?> vezes</strong>
        <?php endif; ?>

        foi concluído com sucesso.
    </span>
</div>