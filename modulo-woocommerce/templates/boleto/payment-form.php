<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<fieldset id="maxipago-online_debit-form" class='wc-online_debit-form wc-payment-form'>
    <p class="form-row form-row-wide">
        <label for="maxipago-boleto-document">CPF/CNPJ
            <span class="required">*</span>
        </label>
        <input id="maxipago-boleto-document"
               name="maxipago_boleto_document"
               class="input-text"
               type="tel"
               inputmode="numeric"
               maxlength="20"
               autocomplete="off"
               style="font-size: 1.5em; padding: 8px;"/>
    </p>
    <div class="clear"></div>
</fieldset>
<div id="maxipago-boleto-instructions">
    <p>&nbsp;</p>
    <p>Assim que clicar no botão Finalizar, você terá acesso ao seu boleto e você poderá pagá-lo via internet bancking
        ou imprimi-lo para pagar em uma casa lotérica ou banco.
        <br/>Seu pedido será processado tão logo identifiquemos o pagamento.
    </p>
</div>