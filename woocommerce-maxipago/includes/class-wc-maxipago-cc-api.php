<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_maxiPago_CC_API extends WC_maxiPago_API {

    private function get_cc_expiration($value) {
        $value = explode('/', $value);
        $month = isset($value[0]) ? trim($value[0]) : '';
        $year = isset($value[1]) ? trim($value[1]) : '';
        return array(
            'month' => $month,
            'year' => $year,
        );
    }

    private function get_cc_brand($number) {
        $allowed_brands = array(
            'visa' => '/^4\d{12}(\d{3})?$/',
            'mastercard' => '/^(5[1-5]\d{4}|677189)\d{10}$/',
            'hipercard' => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
            'amex' => '/^3[47]\d{13}$/',
            'diners' => '/^3(0[0-5]|[68]\d)\d{11}$/',
            'elo' => '/^((((636368)|(438935)|(504175)|(451416)|(636297))\d{0,10})|((5067)|(4576)|(4011))\d{0,12})$/',
            'discover' => '/^6(?:011&#124;5[0-9]{2})[0-9]{12}$/'
        );
        foreach ($allowed_brands as $key => $value) {
            if (preg_match($value, $number)) {
                return $key;
            }
        }
        return null;
    }

    public function get_installments_html($order_total = 0) {
        $html = '';
        $installments = $this->gateway->installments;
        if ('1' == $installments) {
            return $html;
        }
        $html .= '<select id="maxipago-installments" name="maxipago_installments" style="font-size: 1.5em; padding: 4px; width: 100%;">';
        $intallment_values = $this->get_installments($order_total);
        for ($i = 1; $i <= $installments; $i++) {
            $total = $order_total / $i;
            $credit_interest = '';
            $min_per_installments = (WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT <= $this->gateway->min_per_installments)
                ? $this->gateway->min_per_installments : WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT;
            if ($i >= $this->gateway->max_without_interest && 0 != $this->gateway->max_without_interest) {
                if (!isset($intallment_values[$i - 1])) continue;
                $interest_total = $intallment_values[$i - 1]['installment_value'];
                $interest_order_total = $intallment_values[$i - 1]['total'];
                if ($total < $interest_total) {
                    $total = $interest_total;
                    $credit_interest = sprintf(__('(%s%% per month - Total: %s)', 'woocommerce-maxipago'),
                        $this->clean_values($this->gateway->interest_rate), sanitize_text_field(wc_price($interest_order_total)));
                }
            }
            if (1 != $i && $total < $min_per_installments) {
                continue;
            }
            $html .= '<option value="' . $i . '">' . esc_html(sprintf(__('%sx of %s %s', 'woocommerce-maxipago'), $i,
                    sanitize_text_field(wc_price($total)), $credit_interest)) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function validate_installments($posted, $order_total) {
        try {
            if (!isset($posted['maxipago_installments']) && 1 == $this->gateway->installments) {
                return true;
            }
            if (!isset($posted['maxipago_installments']) || !$posted['maxipago_installments']) {
                throw new Exception(__('Please select a number of installments.', 'woocommerce-maxipago'));
            }
            $installments = intval($posted['maxipago_installments']);
            $installment_total = $order_total / $installments;
            $installments_config = $this->gateway->installments;
            if ($installments >= $this->gateway->max_without_interest && 0 != $this->gateway->max_without_interest) {
                $interest_rate = $this->clean_values($this->gateway->interest_rate);
                $interest_total = $this->get_total_by_installments($order_total, $installments, $interest_rate);
                $installment_total = ($installment_total < $interest_total) ? $interest_total : $installment_total;
            }
            $min_per_installments = (WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT <= $this->gateway->min_per_installments)
                ? $this->gateway->min_per_installments : WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT;
            if ($installments > $installments_config || 1 != $installments && $installment_total < $min_per_installments) {
                throw new Exception(__('Invalid number of installments.', 'woocommerce-maxipago'));
            }
        } catch (Exception $e) {
            wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . esc_html($e->getMessage()), 'error');
            return false;
        }
        return true;
    }

    private function get_installment_price($price, $installments, $interest_rate, $type = 'price') {
        $price = (float)$price;
        $value = 0;
        if ($interest_rate) {
            $interest_rate = (float)(str_replace(',', '.', $interest_rate)) / 100;
            switch ($type) {
                case 'price':
                    $value = round($price * (($interest_rate * pow((1 + $interest_rate), $installments)) /
                            (pow((1 + $interest_rate), $installments) - 1)), 2);
                    break;
                case 'compound':
                    $value = ($price * pow(1 + $interest_rate, $installments)) / $installments;
                    break;
                case 'simple':
                    $value = ($price * (1 + ($installments * $interest_rate))) / $installments;
            }
        } else {
            if ($installments)
                $value = $price / $installments;
        }
        return $value;
    }

    private function get_total_by_installments($price, $installments, $interest_rate) {
        return $this->get_installment_price($price, $installments, $interest_rate,
            $this->gateway->interest_rate_caculate_method) * $installments;
    }

    private function get_installments($price = null) {
        $price = (float)$price;
        $max_installments = $this->gateway->installments;
        $installments_without_interest = $this->gateway->max_without_interest;
        $min_per_installment = $this->gateway->min_per_installments;
        $interest_rate = $this->clean_values($this->gateway->interest_rate);
        if ($min_per_installment > 0) {
            while ($max_installments > ($price / $min_per_installment)) $max_installments--;
        }
        $installments = array();
        if ($price > 0) {
            $max_installments = ($max_installments == 0) ? 1 : $max_installments;
            for ($i = 1; $i <= $max_installments; $i++) {
                $interest_rate_installment = ($i <= $installments_without_interest) ? '' : $interest_rate;
                $value = ($i <= $installments_without_interest) ? ($price / $i) :
                    $this->get_installment_price($price, $i, $interest_rate, $this->gateway->interest_rate_caculate_method);
                $total = $value * $i;
                $installments[] = array(
                    'total' => $total,
                    'installments' => $i,
                    'installment_value' => $value,
                    'interest_rate' => $interest_rate_installment
                );
            }
        }
        return $installments;
    }

    private function validate_fields($formData) {
        try {
            if ($this->gateway->use_token) {
                if ($formData['wc-maxipago-cc-payment-token'] != 'new' && (!isset($formData['maxipago_card_cvc_token']) || '' === $formData['maxipago_card_cvc_token'])) {
                    throw new Exception(__('Please type the cvc code for the card.', 'woocommerce-maxipago'));
                }
            }
            if (!isset($formData['maxipago_cc_document']) || '' === $formData['maxipago_cc_document'] || !$this->check_document($formData['maxipago_cc_document'])) {
                throw new Exception(__('Please type the valid document number.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_number']) || '' === $formData['maxipago_card_number']) {
                throw new Exception(__('Please type the card number.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_holder_name']) || '' === $formData['maxipago_holder_name']) {
                throw new Exception(__('Please type the name of the card holder.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_expiry']) || '' === $formData['maxipago_card_expiry']) {
                throw new Exception(__('Please type the card expiry date.', 'woocommerce-maxipago'));
            }
            if (!isset($formData['maxipago_card_cvc']) || '' === $formData['maxipago_card_cvc']) {
                throw new Exception(__('Please type the cvc code for the card', 'woocommerce-maxipago'));
            }
        } catch (Exception $e) {
            wc_add_notice('<strong>' . esc_html($this->gateway->title) . '</strong>: ' . esc_html($e->getMessage()), 'error');
            return false;
        }
        return true;
    }

    private function get_processor_id_by_cc_brand($cc_brand) {
        $name_config = 'acquirers_' . $cc_brand;
        return $this->gateway->$name_config;
    }

    public function get_processor_by_acquirer($type = 'all') {
        $processors = array(
            '1' => __('Simulador de Teste', 'woocommerce-maxipago'),
            '2' => __('Redecard', 'woocommerce-maxipago'),
            '3' => __('GetNet', 'woocommerce-maxipago'),
            '4' => __('Cielo', 'woocommerce-maxipago'),
            '6' => __('Elavon', 'woocommerce-maxipago'),
        );
        $types = array(
            'all' => array('1', '2', '3', '4', '6'),
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

    private function is_sale_with_token($formData) {
        return isset($formData['wc-maxipago-cc-payment-token']) && $formData['wc-maxipago-cc-payment-token'] != 'new';
    }

    private function is_save_token_checked($formData) {
        return isset($formData['wc-maxipago-cc-new-payment-method']) && $formData['wc-maxipago-cc-new-payment-method'] == 'true';
    }

    public function sale_order(WC_Order $order, $post) {
        $result = array(
            'result' => 'fail',
            'redirect' => ''
        );

        if ('yes' == $this->gateway->save_log && class_exists('WC_Logger')) {
            $this->log = new WC_Logger();
        }

        $is_valid = $this->validate_fields($post);

        if ($is_valid) {
            $is_valid = $this->validate_installments($post, (float)$order->get_total());
        }
        if ($is_valid) {

            $client = new maxiPago();
            $client->setCredentials($this->gateway->merchant_id, $this->gateway->merchant_key);
            $client->setEnvironment($this->gateway->environment);

            $token_number = null;
            if ($this->is_sale_with_token($post)) {
                $token_id = $post['wc-maxipago-cc-payment-token'];
                $token_number = $this->get_token($token_id);
            }

            $expiry = $this->get_cc_expiration(sanitize_text_field($post['maxipago_card_expiry']));
            $cc_number = sanitize_text_field($this->clean_number($post['maxipago_card_number']));
            $cc_installments = sanitize_text_field($this->clean_number($post['maxipago_installments']));

            $customer_document = $post['maxipago_cc_document'];

            if($this->gateway->installments == 1) $cc_installments = 1;

            $cc_brand = $this->get_cc_brand($cc_number);
            $processor_id = $this->get_processor_id_by_cc_brand($cc_brand);

            $fraud_check = $this->gateway->fraud_check == 'yes' ? 'Y' : 'N';
            $fraud_check = $this->gateway->processing_type != WC_maxiPago_CC_Gateway::PROCESSING_TYPE_SALE ? $fraud_check : 'N';

            $charge_total = (float)$order->get_total();
            $has_interest = 'N';
            if ($this->gateway->interest_rate && $cc_installments > $this->gateway->max_without_interest) {
                $has_interest = 'Y';
                $charge_total = $this->get_total_by_installments($charge_total, $cc_installments, $this->gateway->interest_rate);
            }

            $ipAddress = $this->clean_ip_address($order->customer_ip_address);

            $request_data = array(
                'referenceNum' => $this->gateway->invoice_prefix . $order->id,
                'processorID' => $processor_id,
                'ipAddress' => $ipAddress,
                'fraudCheck' => $fraud_check,
                'customerIdExt' => $customer_document,

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

                'currencyCode' => get_woocommerce_currency(),
                'chargeTotal' => wc_format_decimal($charge_total, wc_get_price_decimals()),
                'numberOfInstallments' => $cc_installments,
                'chargeInterest' => $has_interest
            );

            if (!$this->is_sale_with_token($post)) {
                $request_data['number'] = $cc_number;
                $request_data['expMonth'] = $expiry['month'];
                $request_data['expYear'] = $expiry['year'];
                $request_data['cvvNumber'] = sanitize_text_field($post['maxipago_card_cvc']);
            } else {
                $customer_id = get_current_user_id();
                $maxipago_customer_id = get_user_meta($customer_id, '_maxipago_customer_id', true);
                $request_data['token'] = $token_number;
                $request_data['customerId'] = $maxipago_customer_id;
                $request_data['cvvNumber'] = sanitize_text_field($post['maxipago_card_cvc_token']);
            }

            if ($this->gateway->soft_descriptor) {
                $request_data['softDescriptor'] = $this->gateway->soft_descriptor;
            }

            if ($this->is_save_token_checked($post)) {
                $cc_info = array(
                    'cc_number' => $cc_number,
                    'cc_expiry' => sanitize_text_field($post['maxipago_card_expiry']),
                    'cc_cvc' => sanitize_text_field($post['maxipago_card_cvc']),
                );
                $this->save_token($cc_info);
            }

            if ($this->gateway->processing_type == 'auth') {
                $client->creditCardAuth($request_data);
                if ($this->log) $this->log->add('maxipago_api', '------------- creditCardAuth -------------');
            } else {
                $client->creditCardSale($request_data);
                if ($this->log) $this->log->add('maxipago_api', '------------- creditCardSale -------------');
            }

            if ($this->log) {
                $xmlRequest = preg_replace('/<number>(.*)<\/number>/m', '<number>*****</number>', $client->xmlRequest);
                $xmlRequest = preg_replace('/<cvvNumber>(.*)<\/cvvNumber>/m', '<cvvNumber>***</cvvNumber>', $xmlRequest);
                $xmlRequest = preg_replace('/<token>(.*)<\/token>/m', '<token>***</token>', $xmlRequest);
                $this->log->add('maxipago_api', $xmlRequest);
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
                        update_post_meta($order->id, 'maxipago_processor_transaction_id', $client->getProcessorTransactionID());
                        update_post_meta($order->id, 'maxipago_processor_code', $client->getProcessorCode());
                        if ($fraud_check == 'Y') {
                            update_post_meta($order->id, 'maxipago_fraud_score', $client->getFraudScore());
                        }
                    }
                    $updated = $this->set_order_status($client->getReferenceNum(), $client->getResponseCode(),
                        $this->gateway->invoice_prefix, $this->gateway->processing_type);
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

    public function capture_order(WC_Order $order) {
        $message = __('The order does not allow capture.', 'woocommerce-maxipago');
        if ($order->payment_method == WC_maxiPago_CC_Gateway::ID && $order->get_status() == 'on-hold') {
            $cc_settings = get_option('woocommerce_maxipago-cc_settings');
            if (is_array($cc_settings)) {
                if ('yes' == $cc_settings['save_log'] && class_exists('WC_Logger')) {
                    $this->log = new WC_Logger();
                }
                $message = __('There was an error capture the payment', 'woocommerce-maxipago');
                $client = new maxiPago();
                $client->setCredentials($cc_settings['merchant_id'], $cc_settings['merchant_key']);
                $client->setEnvironment($cc_settings['environment']);
                $result_data = get_post_meta($order->id, '_maxipago_result_data', true);
                if ($result_data) {
                    $data = array(
                        'orderID' => $result_data['orderID'],
                        'referenceNum' => $result_data['referenceNum'],
                        'chargeTotal' => wc_format_decimal($order->get_total(), wc_get_price_decimals())
                    );
                    $client->creditCardCapture($data);
                    if ($this->log) {
                        $this->log->add('maxipago_api', '------------- creditCardCapture -------------');
                        $this->log->add('maxipago_api', $client->xmlRequest);
                        $this->log->add('maxipago_api', $client->xmlResponse);
                    }
                    $result = $client->getResult();
                    update_post_meta($order->id, '_maxipago_capture_result_data', $result);
                    if (!$client->isErrorResponse() && $client->getResponseCode() == 0) {
                        $order->payment_complete();
                        $message = sprintf(__('Payment captured successfully - User: %s', 'woocommerce-maxipago'), wp_get_current_user()->display_name);
                        set_transient('maxipago_admin_notice', array($message, 'notice'), 5);
                        return true;
                    } else {
                        $message = sprintf(__('There was an error capture the payment - Error: %s', 'woocommerce-maxipago'), $client->getMessage());
                    }
                }
            }
        }
        set_transient('maxipago_admin_notice', array($message, 'error'), 5);
        return false;
    }

    public function refund_order(WC_Order $order) {
        $message = __('The order does not allow refund.', 'woocommerce-maxipago');
        if ($order->payment_method == WC_maxiPago_CC_Gateway::ID && $order->get_status() == 'processing') {
            $cc_settings = get_option('woocommerce_maxipago-cc_settings');
            if (is_array($cc_settings)) {
                if ('yes' == $cc_settings['save_log'] && class_exists('WC_Logger')) {
                    $this->log = new WC_Logger();
                }
                $message = __('There was an error refund the payment', 'woocommerce-maxipago');
                $client = new maxiPago();
                $client->setCredentials($cc_settings['merchant_id'], $cc_settings['merchant_key']);
                $client->setEnvironment($cc_settings['environment']);
                $result_data = get_post_meta($order->id, '_maxipago_result_data', true);
                if ($result_data) {
                    $can_void = false;
                    $capture_data = get_post_meta($order->id, '_maxipago_capture_result_data', true);
                    if ($capture_data) {
                        $can_void = date('Ymd', $capture_data['transactionTimestamp']) == date('Ymd');
                    } elseif ($result_data) {
                        $can_void = date('Ymd', $result_data['transactionTimestamp']) == date('Ymd');
                    }
                    if ($can_void) {
                        if ($this->log) $this->log->add('maxipago_api', '------------- creditCardVoid -------------');
                        $data = array(
                            'transactionID' => $result_data['transactionID'],
                        );
                        $client->creditCardVoid($data);
                    } else {
                        if ($this->log) $this->log->add('maxipago_api', '------------- creditCardRefund -------------');
                        $data = array(
                            'orderID' => $result_data['orderID'],
                            'referenceNum' => $result_data['referenceNum'],
                            'chargeTotal' => wc_format_decimal($order->get_total(), wc_get_price_decimals()),
                        );
                        $client->creditCardRefund($data);
                    }
                    if ($this->log) {
                        $this->log->add('maxipago_api', $client->xmlRequest);
                        $this->log->add('maxipago_api', $client->xmlResponse);
                    }
                    $result = $client->getResult();
                    update_post_meta($order->id, '_maxipago_refund_result_data', $result);
                    if (!$client->isErrorResponse() && $client->getResponseCode() == 0) {
                        if ($can_void) {
                            $message = sprintf(__('Payment cancelled successfully - User: %s', 'woocommerce-maxipago'), wp_get_current_user()->display_name);
                            $order->update_status('cancelled', __('Payment cancelled.', 'woocommerce-maxipago'));
                        } else {
                            $message = sprintf(__('Payment refunded successfully - User: %s', 'woocommerce-maxipago'), wp_get_current_user()->display_name);
                            $order->update_status('refunded', __('Payment refunded.', 'woocommerce-maxipago'));
                        }
                        set_transient('maxipago_admin_notice', array($message, 'notice'), 5);
                        return true;
                    } else {
                        $message = sprintf(__('There was an error refund the payment - Error: %s', 'woocommerce-maxipago'), $client->getMessage());
                    }
                }
            }
        }
        set_transient('maxipago_admin_notice', array($message, 'error'), 5);
        return false;
    }

    public function save_token($cc_info) {
        $cc_expiry = $this->get_cc_expiration($cc_info['cc_expiry']);
        $cc_brand = $this->get_cc_brand($this->clean_number($cc_info['cc_number']));
        $customer_id = get_current_user_id();
        $user = new WP_User($customer_id);
        $cc_settings = get_option('woocommerce_maxipago-cc_settings');
        $maxipago_customer_id = get_user_meta($customer_id, '_maxipago_customer_id_'.$cc_settings['merchant_id'], true);
        if (!$cc_settings) return false;
        if ('yes' == $cc_settings['save_log'] && class_exists('WC_Logger')) {
            $this->log = new WC_Logger();
        }
        $client = new maxiPago();
        $client->setCredentials($cc_settings['merchant_id'], $cc_settings['merchant_key']);
        $client->setEnvironment($cc_settings['environment']);
        $is_default = false;
        if (!$maxipago_customer_id) {
            $is_default = true;
            $customerData = array(
                'customerIdExt' => $customer_id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name
            );
            $client->addProfile($customerData);
            if ($this->log) {
                $this->log->add('maxipago_api', '------------- addProfile -------------');
                $this->log->add('maxipago_api', $client->xmlRequest);
                $this->log->add('maxipago_api', $client->xmlResponse);
            }
            $maxipago_customer_id = $client->getCustomerId();
            if (!$maxipago_customer_id) {
                return false;
            }
            add_user_meta($customer_id, '_maxipago_customer_id_'.$cc_settings['merchant_id'], $maxipago_customer_id);
        }

        $date = new DateTime($cc_expiry['year'] . '-' . $cc_expiry['month'] . '-01');
        $date->modify('+1 month');
        $endDate = $date->format('m/d/Y');

        $token_data = array(
            'customerId' => $maxipago_customer_id,
            'creditCardNumber' => $this->clean_number($cc_info['cc_number']),
            'expirationMonth' => $cc_expiry['month'],
            'expirationYear' => $cc_expiry['year'],
            'billingName' => $user->billing_first_name . ' ' . $user->billing_last_name,
            'billingAddress1' => $user->billing_address_1 . ' ' . $user->billing_number,
            'billingAddress2' => $user->billing_address_2,
            'billingCity' => $user->billing_city,
            'billingState' => $user->billing_state,
            'billingZip' => $this->clean_number($user->billing_postcode),
            'billingPhone' => $this->clean_number($user->billing_phone),
            'billingEmail' => $user->user_email,
            'onFileEndDate' => $endDate,
            'onFilePermissions' => 'ongoing',
        );
        $client->addCreditCard($token_data);
        if ($this->log) {
            $this->log->add('maxipago_api', '------------- addCreditCard -------------');
            $xmlRequest = preg_replace('/<creditCardNumber>(.*)<\/creditCardNumber>/m', '<creditCardNumber>*****</creditCardNumber>', $client->xmlRequest);
            $this->log->add('maxipago_api', $xmlRequest);
            $this->log->add('maxipago_api', $client->xmlResponse);
        }

        if ($token_number = $client->getToken()) {
            $token = new WC_Payment_Token_CC();
            $token->set_token($token_number);
            $token->set_gateway_id($this->gateway->id);
            $token->set_card_type($cc_brand);
            $token->set_last4(substr($this->clean_number($cc_info['cc_number']), -4));
            $token->set_expiry_month($cc_expiry['month']);
            $token->set_expiry_year($cc_expiry['year']);
            $token->set_user_id($customer_id);
            $token->set_default($is_default);
            return $token->save();
        }
        return false;
    }

    public function delete_token(WC_Payment_Token_CC $object_token) {
        $token = $object_token->get_token();
        $customer_id = get_current_user_id();
        $cc_settings = get_option('woocommerce_maxipago-cc_settings');
        $maxipago_customer_id = get_user_meta($customer_id, '_maxipago_customer_id_'.$cc_settings['merchant_id'], true);
        if (!$maxipago_customer_id) return false;
        if (!$cc_settings) return false;
        if ('yes' == $cc_settings['save_log'] && class_exists('WC_Logger')) {
            $this->log = new WC_Logger();
        }
        $client = new maxiPago();
        $client->setCredentials($cc_settings['merchant_id'], $cc_settings['merchant_key']);
        $client->setEnvironment($cc_settings['environment']);
        $token_data = array(
            'customerId' => $maxipago_customer_id,
            'token' => $token,
        );
        $client->deleteCreditCard($token_data);
        if ($this->log) {
            $this->log->add('maxipago_api', '------------- deleteCreditCard -------------');
            $this->log->add('maxipago_api', $client->xmlRequest);
            $this->log->add('maxipago_api', $client->xmlResponse);
        }
        return true;
    }

    public function get_token($token_id) {
        $token = new WC_Payment_Token_CC($token_id);
        return $token->get_token();
    }

}