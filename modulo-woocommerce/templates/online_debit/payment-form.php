<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $online_debit_banks ) ): ?>
    Nenhum banco configurado; por favor, entre em contato com nosso suporte.
<?php endif; ?>
<fieldset id="maxipago-online_debit-form" class='wc-online_debit-form wc-payment-form'>
    <div class="form-row form-row-wide">
        <label>Selecione o banco <span class="required">*</span></label>
        <div class="maxipago-bank-selector">
			<?php foreach ( $online_debit_banks as $bank_code => $bank ): ?>
                <input id="maxipago-bank-<?= $bank_code ?>" type="radio" name="maxipago_online_debit_bank_code"
                       value="<?= $bank_code ?>"/>
                <label class="maxipago-logo-bank maxipago-bank-<?= $bank_code ?>"
                       for="maxipago-bank-<?= $bank_code ?>"></label>
			<?php endforeach; ?>
        </div>
    </div>
    <p class="form-row form-row-wide">
        <label for="maxipago-online_debit-document">CPF/CNPJ <span
                    class="required">*</span></label>
        <input id="maxipago-online_debit-document" name="maxipago_online_debit_document" class="input-text" type="tel"
               inputmode="numeric"
               maxlength="20" autocomplete="off" style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
<div id="maxipago-online_debit-instructions">
    <p>Assim que clicar em <em>Finalizar compra</em>, você receberá um link para efetuar a transferência.
        <br/>
        Seu pedido será processado tão logo recebamos a confirmação de pagamento.
    </p>
</div>