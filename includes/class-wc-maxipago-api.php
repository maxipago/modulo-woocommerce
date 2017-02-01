<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_maxiPago_API {

    /** @var WC_Logger */
    protected $log = false;
    protected $gateway = null;

    public function __construct($gateway = null) {
        $this->gateway = $gateway;
    }

    public function clean_values($value) {
        return str_replace(array('%', ','), array('', '.'), $value);
    }

    public function clean_number($number) {
        return preg_replace('/\D/', '', $number);
    }

    protected function check_document($document) {
        $invalids = array(
            '00000000000',
            '11111111111',
            '22222222222',
            '33333333333',
            '44444444444',
            '55555555555',
            '66666666666',
            '88888888888',
            '99999999999',
        );
        if (empty($document)) {
            return false;
        }
        $document = $this->clean_number($document);
        $document = str_pad($document, 11, '0', STR_PAD_LEFT);
        if (strlen($document) != 11 || in_array($document, $invalids)) {
            return false;
        } else {
            for ($t = 9; $t < 11; $t++) {
                for ($d = 0, $c = 0; $c < $t; $c++) {
                    $d += $document{$c} * (($t + 1) - $c);
                }
                $d = ((10 * $d) % 11) % 10;
                if ($document{$c} != $d) {
                    return false;
                }
            }
            return true;
        }
    }

    public function set_order_status($reference, $status, $invoice_prefix, $processing_type = null) {
        $valid = false;
        $order_id = intval(str_replace($invoice_prefix, '', $reference));
        $order = wc_get_order($order_id);
        if ($order->id === $order_id) {
            $order_status = sanitize_text_field($status);
            switch ($order_status) {
                case 0 : // Aprovada
                    if ($processing_type == WC_maxiPago_CC_Gateway::PROCESSING_TYPE_SALE) {
                        $order->payment_complete();
                    } else {
                        if ($order->payment_method == WC_maxiPago_Ticket_Gateway::ID) {
                            $order->update_status('on-hold', __('maxiPago!: After we receive the ticket payment confirmation, your order will be processed.', 'woocommerce-maxipago'));
                        } elseif($order->payment_method != WC_maxiPago_TEF_Gateway::ID) {
                            $order->update_status('on-hold', __('maxiPago!: After we receive the TEF payment confirmation, your order will be processed.', 'woocommerce-maxipago'));
                        } else {
                            $order->update_status('on-hold', __('maxiPago!: This order requires manual capture.', 'woocommerce-maxipago'));
                        }
                    }
                    $valid = true;
                    break;
                case 5 : // Revisão de Fraude
                    $order->update_status('on-hold', __('maxiPago!: This order is under fraud review, please wait for return.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
                case 1 : // Negada
                    $order->update_status('failed', __('maxiPago!: Payment Denied.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
                case 2 : // Negada por Duplicidade ou Fraude
                    $order->update_status('failed', __('maxiPago!: Payment Denied for duplicity or fraud.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
                case 1022 : // Erro na operadora de cartão
                case 1024 : // Erro interno na maxiPago!
                case 1025 : // Erro interno na maxiPago!
                case 2048 : // Erro interno na maxiPago!
                case 4097 : // Erro interno na maxiPago!
                    $order->update_status('failed', __('maxiPago!: An error has occurred while processing your payment.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
            }
        }
        return $valid;
    }

    public function get_banks($type = 'ticket') {
        $banks = array(
            'ticket' => array(
                '' => __('Select the Bank', 'woocommerce-maxipago'),
                '13' => __('Banco do Brasil', 'woocommerce-maxipago'),
                '12' => __('Bradesco', 'woocommerce-maxipago'),
                '16' => __('Caixa Econômica Federal', 'woocommerce-maxipago'),
                '14' => __('HSBC', 'woocommerce-maxipago'),
                '11' => __('Itaú', 'woocommerce-maxipago'),
                '15' => __('Santander', 'woocommerce-maxipago')
            ),
            'tef' => array(
                '17' => __('Bradesco', 'woocommerce-maxipago'),
                '18' => __('Itaú', 'woocommerce-maxipago')
            )
        );
        return isset($banks[$type]) ? $banks[$type] : array();
    }

}