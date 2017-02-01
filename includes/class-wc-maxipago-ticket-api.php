<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_maxiPago_Ticket_API extends WC_maxiPago_API {

    private function validate_fields($formData) {
        try {
            if (!isset($formData['maxipago_ticket_document']) || '' === $formData['maxipago_ticket_document'] || !$this->check_document($formData['maxipago_ticket_document'])) {
                throw new Exception(__('Please type the valid document number.', 'woocommerce-maxipago'));
            }
        } catch (Exception $e) {
            wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . esc_html($e->getMessage()), 'error');
            return false;
        }
        return true;
    }

    public function sale_order(WC_Order $order, $post) {
        $result = array(
            'result' => 'fail',
            'redirect' => ''
        );
        if ('yes' == $this->gateway->save_log && class_exists('WC_Logger')) {
            $this->log = new WC_Logger();
        }
        if ($this->validate_fields($post)) {
            $client = new maxiPago();
            $client->setCredentials($this->gateway->merchant_id, $this->gateway->merchant_key);
            $client->setEnvironment($this->gateway->environment);

            $date = new DateTime();
            $date->modify("{$this->gateway->days_to_expire} days");
            $expiration_date = $date->format('Y-m-d');

            $request_data = array(
                'referenceNum' => $this->gateway->invoice_prefix . $order->id,
                'processorID' => $this->gateway->bank,
                'ipAddress' => $order->customer_ip_address,

                'bmail' => $order->billing_email,
                'bname' => $order->billing_first_name . ' ' . $order->billing_last_name,
                'baddress' => $order->billing_address_1,
                'baddress2' => $order->billing_address_2,
                'bcity' => $order->billing_city,
                'bstate' => $order->billing_state,
                'bpostalcode' => $this->clean_number($order->billing_postcode),
                'bcountry' => $order->billing_country,
                'bphone' => $this->clean_number($order->billing_phone),

                'smail' => $order->billing_email,
                'sname' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                'saddress' => $order->shipping_address_1,
                'saddress2' => $order->shipping_address_2,
                'scity' => $order->shipping_city,
                'sstate' => $order->shipping_state,
                'spostalcode' => $this->clean_number($order->shipping_postcode),
                'scountry' => $order->shipping_country,
                'sphone' => $this->clean_number($order->billing_phone),

                'chargeTotal' => wc_format_decimal($order->get_total(), wc_get_price_decimals()),
                'number' => $order->id,
                'expirationDate' => $expiration_date,
                'instructions' => $this->gateway->instructions,
            );

            if ($this->log) $this->log->add('maxipago_api', '------------- boletoSale -------------');
            $client->boletoSale($request_data);

            if ($this->log) {
                $this->log->add('maxipago_api', $client->xmlRequest);
                $this->log->add('maxipago_api', $client->xmlResponse);
            }

            update_post_meta($order->id, '_maxipago_request_data', $request_data);

            $result = $client->getResult();

            if (!empty($result)) {
                update_post_meta($order->id, '_maxipago_result_data', $result);
                if ($client->isErrorResponse()) {
                    update_post_meta($order->id, 'maxipago_transaction_id', $client->getTransactionID());
                    update_post_meta($order->id, 'maxipago_error', $client->getMessage());
                    wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . wc_clean($client->getMessage()), 'error');
                } else {
                    if ($client->getTransactionID()) {
                        update_post_meta($order->id, 'maxipago_transaction_id', $client->getTransactionID());
                        update_post_meta($order->id, 'maxipago_response_message', $client->getMessage());
                        update_post_meta($order->id, 'maxipago_processor_code', $client->getProcessorCode());
                    }
                    $updated = $this->set_order_status($client->getReferenceNum(), $client->getResponseCode(),
                        $this->gateway->invoice_prefix);
                    if ($updated) {
                        WC()->cart->empty_cart();
                        $result = array(
                            'result' => 'success',
                            'redirect' => $order->get_checkout_order_received_url()
                        );
                    }
                }
            } else {
                wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' .
                    esc_html(__('An error has occurred while processing your payment, please try again.', 'woocommerce-maxipago')), 'error');
            }
        }
        return $result;
    }

}