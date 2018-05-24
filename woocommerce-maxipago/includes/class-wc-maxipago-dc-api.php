<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_maxiPago_DC_API extends WC_maxiPago_API
{

    private function get_cc_expiration($value)
    {
        $value = explode('/', $value);
        $month = isset($value[0]) ? trim($value[0]) : '';
        $year = isset($value[1]) ? trim($value[1]) : '';
        return array(
            'month' => $month,
            'year' => $year,
        );
    }

    private function get_cc_brand($number)
    {
        $allowed_brands = array(
            'visa' => '/^4\d{12}(\d{3})?$/',
            'mastercard' => '/^(5[1-5]\d{4}|677189)\d{10}$/'
        );
        foreach ($allowed_brands as $key => $value) {
            if (preg_match($value, $number)) {
                return $key;
            }
        }
        return null;
    }

    private function validate_fields($formData)
    {
        try {
            if (!isset($formData['maxipago_dc_document']) || '' === $formData['maxipago_dc_document'] || !$this->check_document($formData['maxipago_dc_document'])) {
                throw new Exception(__('Please type the valid document number.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_dc_number']) || '' === $formData['maxipago_card_dc_number']) {
                throw new Exception(__('Please type the card number.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_holder_dc_name']) || '' === $formData['maxipago_holder_dc_name']) {
                throw new Exception(__('Please type the name of the card holder.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_dc_expiry']) || '' === $formData['maxipago_card_dc_expiry']) {
                throw new Exception(__('Please type the card expiry date.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_dc_cvc']) || '' === $formData['maxipago_card_dc_cvc']) {
                throw new Exception(__('Please type the cvc code for the card', 'woocommerce-maxipago'));
            }
        } catch (Exception $e) {
            wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . esc_html($e->getMessage()), 'error');
            return false;
        }
        return true;
    }

    public function get_mpi_processors()
    {
        $processors = array(
            '41' => __('Test', 'woocommerce-maxipago'),
            '40' => __('Production', 'woocommerce-maxipago'),
        );
        return $processors;
    }

    public function get_mpi_action()
    {
        $processors = array(
            'decline' => __('Stop Processing', 'woocommerce-maxipago'),
            'continue' => __('Continue Processing', 'woocommerce-maxipago'),
        );
        return $processors;
    }

    private function get_processor_id_by_cc_brand($cc_brand)
    {
        $name_config = 'acquirers_' . $cc_brand;
        return $this->gateway->$name_config;
    }

    public function get_processor_by_acquirer($type = 'all')
    {
        $processors = array(
            '1' => __('Simulador de Teste', 'woocommerce-maxipago'),
            '2' => __('Redecard', 'woocommerce-maxipago'),
            '3' => __('GetNet', 'woocommerce-maxipago'),
            '4' => __('Cielo', 'woocommerce-maxipago'),
            '5' => __('e.Rede', 'woocommerce-maxipago'),
            '6' => __('Elavon', 'woocommerce-maxipago'),
            '9' => __('Stone', 'woocommerce-maxipago')
        );
        $types = array(
            'all' => array('1', '2', '3', '4', '5', '6', '9'),
            'amex' => array('1', '4'),
            'diners' => array('1', '2', '4', '6'),
            'elo' => array('1', '3', '4'),
            'discover' => array('1', '2', '4', '6'),
            'hipercard' => array('1', '2'),
        );
        foreach ($processors as $typeId => $typeName) {
            if (!in_array($typeId, $types[$type])) {
                unset($processors[$typeId]);
            }
        }
        $processors = array('' => __('Disable', 'woocommerce-maxipago')) + $processors;
        return $processors;
    }

    public function sale_order(WC_Order $order, $post)
    {
        $result = array(
            'result' => 'fail',
            'redirect' => ''
        );

        if ('yes' == $this->gateway->save_log && class_exists('WC_Logger')) {
            $this->log = new WC_Logger();
        }

        $is_valid = $this->validate_fields($post);

        if ($is_valid) {

            $client = new maxiPago();
            $client->setCredentials($this->gateway->merchant_id, $this->gateway->merchant_key);
            $client->setEnvironment($this->gateway->environment);

            $expiry = $this->get_cc_expiration(sanitize_text_field($post['maxipago_card_dc_expiry']));
            $cc_number = sanitize_text_field($this->clean_number($post['maxipago_card_dc_number']));

            $customer_document = $post['maxipago_dc_document'];

            $cc_brand = $this->get_cc_brand($cc_number);
            $processor_id = $this->get_processor_id_by_cc_brand($cc_brand);

            $charge_total = (float)$order->get_total();
            $ipAddress = $this->clean_ip_address($order->get_customer_ip_address());

            $request_data = array(
                'referenceNum' => $this->gateway->invoice_prefix . $order->get_id(),
                'processorID' => $processor_id,
                'ipAddress' => $ipAddress,
                'customerIdExt' => $customer_document,
                'currencyCode' => get_woocommerce_currency(),
                'chargeTotal' => wc_format_decimal($charge_total, wc_get_price_decimals())
            );

            $request_data['number'] = $cc_number;
            $request_data['expMonth'] = $expiry['month'];
            $request_data['expYear'] = $expiry['year'];
            $request_data['cvvNumber'] = sanitize_text_field($post['maxipago_card_dc_cvc']);

            if ($this->gateway->soft_descriptor) {
                $request_data['softDescriptor'] = $this->gateway->soft_descriptor;
            }

            $request_data['mpiProcessorID'] = $this->gateway->mpi_processor ? $this->gateway->mpi_processor : '41';
            $request_data['onFailure'] = $this->gateway->failure_action ? $this->gateway->failure_action : 'decline';

            $addressData = $this->getAddressData($order, $customer_document);
            $orderData = $this->getOrderData($order);
            $request_data = array_merge($request_data, $addressData, $orderData);

            $client->saledebitCard3DS($request_data);

            if ($this->log)
                $this->log->add('maxipago_api', '------------- saledebitCard3DS -------------');

            if ($this->log) {
                $xmlRequest = preg_replace('/<number>(.*)<\/number>/m', '<number>*****</number>', $client->xmlRequest);
                $xmlRequest = preg_replace('/<cvvNumber>(.*)<\/cvvNumber>/m', '<cvvNumber>***</cvvNumber>', $xmlRequest);
                $xmlRequest = preg_replace('/<token>(.*)<\/token>/m', '<token>***</token>', $xmlRequest);
                $this->log->add('maxipago_api', $xmlRequest);
                $this->log->add('maxipago_api', $client->xmlResponse);
            }

            update_post_meta($order->get_id(), '_maxipago_request_data', $request_data);

            $result = $client->getResult();

            if (!empty($result)) {
                update_post_meta($order->get_id(), '_maxipago_result_data', $result);
                if ($client->isErrorResponse()) {
                    update_post_meta($order->get_id(), 'maxipago_transaction_id', $client->getTransactionID());
                    update_post_meta($order->get_id(), 'maxipago_error', $client->getMessage());
                    wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . wc_clean($client->getMessage()), 'error');
                } else {
                    if ($client->getTransactionID()) {
                        update_post_meta($order->get_id(), 'maxipago_transaction_id', $client->getTransactionID());
                        update_post_meta($order->get_id(), 'maxipago_response_message', $client->getMessage());
                        update_post_meta($order->get_id(), 'maxipago_processor_transaction_id', $client->getProcessorTransactionID());
                        update_post_meta($order->get_id(), 'maxipago_processor_code', $client->getProcessorCode());

                        $this->updatePostMeta($order->get_id(), $result);
                    }
                    $updated = $this->set_order_status(
                        $client->getReferenceNum(),
                        $client->getResponseCode(),
                        $this->gateway->invoice_prefix,
                        $this->gateway->processing_type
                    );

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