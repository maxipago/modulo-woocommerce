<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$success_url       = add_query_arg( 'wc-api', 'wc_maxipago_success', home_url( '/' ) );
$failure_url       = add_query_arg( 'wc-api', 'wc_maxipago_failure', home_url( '/' ) );
$notifications_url = add_query_arg( 'wc-api', 'wc_maxipago_notifications', home_url( '/' ) );
?>
<div class="updated">
    <p>URLs de Notificação</p>
    <p><em>Configure essas URLs dentro do Painel do maxiPago!</em></p>
    <p><small><strong>Sucesso:</strong> <?= $success_url ?></small></p>
    <p><small><strong>Erro:</strong> <?= $failure_url ?></small></p>
    <p><small><strong>Notificação:</strong> <?= $notifications_url ?></small></p>
</div>