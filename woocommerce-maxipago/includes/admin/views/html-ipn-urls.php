<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
$success_url = add_query_arg('wc-api', 'wc_maxipago_success', home_url('/'));
$failure_url = add_query_arg('wc-api', 'wc_maxipago_failure', home_url('/'));
$notifications_url = add_query_arg('wc-api', 'wc_maxipago_notifications', home_url('/'));
?>
<div class="updated">
    <p><?php echo __("<strong>URLs de Notificação</strong>", 'woocommerce-maxipago'); ?></p>
    <p><?php echo __("<em>Configure essas URLs dentro do Painel do maxiPago!</em>", 'woocommerce-maxipago'); ?></p>
    <p>
        <small><?php echo __(sprintf("<strong>Sucesso:</strong> %s", $success_url), 'woocommerce-maxipago'); ?></small>
    </p>
    <p>
        <small><?php echo __(sprintf("<strong>Erro:</strong> %s", $failure_url), 'woocommerce-maxipago'); ?></small>
    </p>
    <p>
        <small><?php echo __(sprintf("<strong>Notificação:</strong> %s", $notifications_url), 'woocommerce-maxipago'); ?></small>
    </p>
</div>
