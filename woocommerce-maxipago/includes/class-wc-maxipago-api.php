<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_maxiPago_API
{

    /** @var WC_Logger */
    public $log = false;
    public $gateway = null;
    protected $_countryCodes = array(
        'AD' => '376',
        'AE' => '971',
        'AF' => '93',
        'AG' => '1268',
        'AI' => '1264',
        'AL' => '355',
        'AM' => '374',
        'AN' => '599',
        'AO' => '244',
        'AQ' => '672',
        'AR' => '54',
        'AS' => '1684',
        'AT' => '43',
        'AU' => '61',
        'AW' => '297',
        'AZ' => '994',
        'BA' => '387',
        'BB' => '1246',
        'BD' => '880',
        'BE' => '32',
        'BF' => '226',
        'BG' => '359',
        'BH' => '973',
        'BI' => '257',
        'BJ' => '229',
        'BL' => '590',
        'BM' => '1441',
        'BN' => '673',
        'BO' => '591',
        'BR' => '55',
        'BS' => '1242',
        'BT' => '975',
        'BW' => '267',
        'BY' => '375',
        'BZ' => '501',
        'CA' => '1',
        'CC' => '61',
        'CD' => '243',
        'CF' => '236',
        'CG' => '242',
        'CH' => '41',
        'CI' => '225',
        'CK' => '682',
        'CL' => '56',
        'CM' => '237',
        'CN' => '86',
        'CO' => '57',
        'CR' => '506',
        'CU' => '53',
        'CV' => '238',
        'CX' => '61',
        'CY' => '357',
        'CZ' => '420',
        'DE' => '49',
        'DJ' => '253',
        'DK' => '45',
        'DM' => '1767',
        'DO' => '1809',
        'DZ' => '213',
        'EC' => '593',
        'EE' => '372',
        'EG' => '20',
        'ER' => '291',
        'ES' => '34',
        'ET' => '251',
        'FI' => '358',
        'FJ' => '679',
        'FK' => '500',
        'FM' => '691',
        'FO' => '298',
        'FR' => '33',
        'GA' => '241',
        'GB' => '44',
        'GD' => '1473',
        'GE' => '995',
        'GH' => '233',
        'GI' => '350',
        'GL' => '299',
        'GM' => '220',
        'GN' => '224',
        'GQ' => '240',
        'GR' => '30',
        'GT' => '502',
        'GU' => '1671',
        'GW' => '245',
        'GY' => '592',
        'HK' => '852',
        'HN' => '504',
        'HR' => '385',
        'HT' => '509',
        'HU' => '36',
        'ID' => '62',
        'IE' => '353',
        'IL' => '972',
        'IM' => '44',
        'IN' => '91',
        'IQ' => '964',
        'IR' => '98',
        'IS' => '354',
        'IT' => '39',
        'JM' => '1876',
        'JO' => '962',
        'JP' => '81',
        'KE' => '254',
        'KG' => '996',
        'KH' => '855',
        'KI' => '686',
        'KM' => '269',
        'KN' => '1869',
        'KP' => '850',
        'KR' => '82',
        'KW' => '965',
        'KY' => '1345',
        'KZ' => '7',
        'LA' => '856',
        'LB' => '961',
        'LC' => '1758',
        'LI' => '423',
        'LK' => '94',
        'LR' => '231',
        'LS' => '266',
        'LT' => '370',
        'LU' => '352',
        'LV' => '371',
        'LY' => '218',
        'MA' => '212',
        'MC' => '377',
        'MD' => '373',
        'ME' => '382',
        'MF' => '1599',
        'MG' => '261',
        'MH' => '692',
        'MK' => '389',
        'ML' => '223',
        'MM' => '95',
        'MN' => '976',
        'MO' => '853',
        'MP' => '1670',
        'MR' => '222',
        'MS' => '1664',
        'MT' => '356',
        'MU' => '230',
        'MV' => '960',
        'MW' => '265',
        'MX' => '52',
        'MY' => '60',
        'MZ' => '258',
        'NA' => '264',
        'NC' => '687',
        'NE' => '227',
        'NG' => '234',
        'NI' => '505',
        'NL' => '31',
        'NO' => '47',
        'NP' => '977',
        'NR' => '674',
        'NU' => '683',
        'NZ' => '64',
        'OM' => '968',
        'PA' => '507',
        'PE' => '51',
        'PF' => '689',
        'PG' => '675',
        'PH' => '63',
        'PK' => '92',
        'PL' => '48',
        'PM' => '508',
        'PN' => '870',
        'PR' => '1',
        'PT' => '351',
        'PW' => '680',
        'PY' => '595',
        'QA' => '974',
        'RO' => '40',
        'RS' => '381',
        'RU' => '7',
        'RW' => '250',
        'SA' => '966',
        'SB' => '677',
        'SC' => '248',
        'SD' => '249',
        'SE' => '46',
        'SG' => '65',
        'SH' => '290',
        'SI' => '386',
        'SK' => '421',
        'SL' => '232',
        'SM' => '378',
        'SN' => '221',
        'SO' => '252',
        'SR' => '597',
        'ST' => '239',
        'SV' => '503',
        'SY' => '963',
        'SZ' => '268',
        'TC' => '1649',
        'TD' => '235',
        'TG' => '228',
        'TH' => '66',
        'TJ' => '992',
        'TK' => '690',
        'TL' => '670',
        'TM' => '993',
        'TN' => '216',
        'TO' => '676',
        'TR' => '90',
        'TT' => '1868',
        'TV' => '688',
        'TW' => '886',
        'TZ' => '255',
        'UA' => '380',
        'UG' => '256',
        'US' => '1',
        'UY' => '598',
        'UZ' => '998',
        'VA' => '39',
        'VC' => '1784',
        'VE' => '58',
        'VG' => '1284',
        'VI' => '1340',
        'VN' => '84',
        'VU' => '678',
        'WF' => '681',
        'WS' => '685',
        'XK' => '381',
        'YE' => '967',
        'YT' => '262',
        'ZA' => '27',
        'ZM' => '260',
        'ZW' => '263'
    );

    public function __construct($gateway = null)
    {
        $this->gateway = $gateway;
    }

    public function clean_values($value)
    {
        return str_replace(array('%', ','), array('', '.'), $value);
    }

    public function clean_number($number)
    {
        return preg_replace('/\D/', '', $number);
    }

    protected function check_document($document)
    {
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
        if (strlen($document) <= 11) {
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
            }
            return true;
        } else if (strlen($document) > 14) {
            return false;
        }
        return true;
    }

    public function set_order_status($reference, $status, $invoice_prefix, $processing_type = null)
    {
        $valid = false;
        $order_id = intval(str_replace($invoice_prefix, '', $reference));
        $order = wc_get_order($order_id);
        if ($order) {
            if ($order->get_id() === $order_id) {
                $order_status = sanitize_text_field($status);
                switch ($order_status) {
                    case 0 : // Aprovada
                        if ($processing_type == WC_maxiPago_CC_Gateway::PROCESSING_TYPE_SALE) {
                            $order->payment_complete();
                        } elseif ($processing_type == WC_maxiPago_CC_Gateway::PROCESSING_TYPE_AUTH) {
                            $authResponse = get_post_meta($order->get_id(), 'responseMessage', true);

                            if($authResponse == 'CAPTURED')
                                $order->payment_complete();
                            else
                                $order->update_status('on-hold', __('maxiPago!: This order requires manual capture.', 'woocommerce-maxipago'));
                        } else {
                            if ($order->get_payment_method() == WC_maxiPago_Ticket_Gateway::ID) {
                                $order->update_status('on-hold', __('maxiPago!: After we receive the ticket payment confirmation, your order will be processed.', 'woocommerce-maxipago'));
                            } elseif ($order->get_payment_method() == WC_maxiPago_TEF_Gateway::ID) {
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
        }
        return $valid;
    }

    public function get_banks($type = 'ticket')
    {
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

    public function clean_ip_address($ipAddress)
    {
        if (strpos($ipAddress, ':') !== false) {
            $ipAddress = str_replace(array('http://', 'https://'), '', $ipAddress);
            $ipAddress = explode(':', $ipAddress);
            $ipAddress = $ipAddress[0];
        }

        return $ipAddress;
    }

    public function getAddressData(WC_Order $order, $documentNumber = '')
    {
        $phone = $this->clean_number($order->get_billing_phone());

        $billingAddress = $order->get_billing_address_1();
        $billingComplement = $order->get_billing_address_2();
        $billingNeighborhood = isset($order_data['billing_address']['neighborhood']) ? $order_data['billing_address']['neighborhood'] : null;

        if ($order->get_meta('_billing_number')) {
            $billingAddress = $billingAddress . ', ' . $order->get_meta('_billing_number');
        }

        if ($order->get_meta('_billing_neighborhood')) {
            $billingNeighborhood = $order->get_meta('_billing_neighborhood');
        }

        $shippingAddress = $order->get_shipping_address_1();
        $shippingComplement = $order->get_shipping_address_2();
        $shippingNeighborhood = isset($order_data['shipping_address']['neighborhood']) ? $order_data['shipping_address']['neighborhood'] : null;

        if ($order->get_meta('_shipping_number')) {
            $shippingAddress = $shippingAddress . ', ' . $order->get_meta('_shipping_number');
        }

        if ($order->get_meta('_shipping_neighborhood')) {
            $shippingNeighborhood = $order->get_meta('_shipping_neighborhood');
        }

        $order_data = $order->get_data();
        $data = array(
            'billingId' => $order->get_customer_id(),
            'billingEmail' => $order->get_billing_email(),
            'billingName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'billingAddress' => $billingAddress,
            'billingAddress2' => $billingComplement,
            'billingDistrict' => $billingNeighborhood ? $billingNeighborhood : 'N/A',
            'billingCity' => $order->get_billing_city(),
            'billingState' => $order->get_billing_state(),
            'billingPostalCode' => $this->clean_number($order->get_billing_postcode()),
            'billingCountry' => $order->get_billing_country(),
            'billingPhone' => $phone,
            'billingBirthDate' => isset($order_data['billing_address']['birthdate']) ? $order_data['billing_address']['birthdate'] : '1990-01-01',
            'billingGender' => isset($order_data['billing_address']['sex']) ? $order_data['billing_address']['sex'] : 'M',

            'shippingId' => $order->get_billing_email(),
            'shippingEmail' => $order->get_billing_email(),
            'shippingName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'shippingAddress' => $shippingAddress,
            'shippingAddress2' => $shippingComplement,
            'shippingDistrict' => $shippingNeighborhood ? $shippingNeighborhood : 'N/A',
            'shippingCity' => $order->get_shipping_city(),
            'shippingState' => $order->get_shipping_state(),
            'shippingPostalCode' => $this->clean_number($order->get_shipping_postcode()),
            'shippingCountry' => $order->get_shipping_country(),
            'shippingPhone' => $phone,
            'shippingBirthDate' => isset($order_data['shipping_address']['birthdate']) ? $order_data['shipping_address']['birthdate'] : '1990-01-01',
            'shippingGender' => isset($order_data['shipping_address']['sex']) ? $order_data['shipping_address']['sex'] : 'M',
        );

        $customerType = 'Individual';
        $documentType = 'CPF';

        $documentNumber = $this->clean_number($documentNumber);
        if (strlen($documentNumber) == '14') {
            $customerType = 'Legal entity';
            $documentType = 'CNPJ';
        }

        $data['billingType'] = $customerType;//'Legal entity'
        $data['billingDocumentType'] = $documentType;
        $data['billingDocumentValue'] = $documentNumber;

        $data['shippingType'] = $customerType;//'Legal entity'
        $data['shippingDocumentType'] = $documentType;
        $data['shippingDocumentValue'] = $documentNumber;

        if ($phone) {
            $data['billingPhoneType'] = 'Mobile';
            $data['billingCountryCode'] = $this->getCountryCode($order->get_billing_country());
            $data['billingPhoneAreaCode'] = $this->getAreaNumber($phone);
            $data['billingPhoneNumber'] = $this->getPhoneNumber($phone);

            $data['shippingPhoneType'] = 'Mobile';
            $data['shippingCountryCode'] = $this->getCountryCode($order->get_shipping_country());
            $data['shippingPhoneAreaCode'] = $this->getAreaNumber($phone);
            $data['shippingPhoneNumber'] = $this->getPhoneNumber($phone);
        }

        return $data;
    }

    public function getPhoneNumber($telefone)
    {
        if (strlen($telefone) >= 10) {
            $telefone = preg_replace('/^D/', '', $telefone);
            $telefone = substr($telefone, 2, strlen($telefone) - 2);
        }
        return $telefone;
    }

    public function getAreaNumber($telefone)
    {
        $telefone = preg_replace('/^D/', '', $telefone);
        $telefone = substr($telefone, 0, 2);
        return $telefone;
    }

    public function getCountryCode($country = 'BR')
    {
        return isset($this->_countryCodes[$country]) ? $this->_countryCodes[$country] : 'BR';
    }
    
    public function getSellerData(WC_Order $order, $number_of_installments = 1)
    {
        $sellerData = array();
        $items = $order->get_items();

        $orderTotal = (float) $order->get_total();

        $i = 0;
        /** @var WC_Order_Item $item */
        foreach ($items as $item) {
            $i++;
            $product = $item->get_product();
            $seller = $this->get_split_payment_seller($product->get_id());

            if($seller)
            {
                $sellerPercentual = $seller['mdr'];
                $orderSellerTotal = $sellerPercentual * $orderTotal;

                $sellerData['sellerId' . $i] = $seller['merchant_id'];
                $sellerData['sellerMDR' . $i] = wc_format_decimal($orderSellerTotal, wc_get_price_decimals());
                $sellerData['sellerDaysToPay' . $i] = $seller['days_to_pay'];
                $sellerData['sellerInstallments' . $i] = $number_of_installments;

                if($seller['use_installment'])
                    $sellerData['sellerInstallments' . $i] = $seller['installments_amount'];
            }
        }

        return $sellerData;
    }

    public function getOrderData(WC_Order $order)
    {
        $orderData = array();
        $items = $order->get_items();

        $i = 0;
        /** @var WC_Order_Item $item */
        foreach ($items as $item) {
            $i++;
            $product = $item->get_product();
            $sku = $product->get_sku() ? $product->get_sku() : $product->get_id();

            $orderData['itemIndex' . $i] = $i;
            $orderData['itemProductCode' . $i] = $sku;
            $orderData['itemDescription' . $i] = $item->get_name();
            $orderData['itemQuantity' . $i] = $item->get_quantity();
            $orderData['itemUnitCost' . $i] = number_format($order->get_item_total($item), 2, '.', '');
            $orderData['itemTotalAmount' . $i] = number_format($order->get_line_subtotal($item), 2, '.', '');
        }

        $orderData['itemCount'] = $i;

        $orderData['userAgent'] = $_SERVER['HTTP_USER_AGENT'];

        return $orderData;

    }

    public function updatePostMeta($orderId, $result)
    {
        foreach ($result as $key => $value) {
            update_post_meta($orderId, $key, $value);
        }
    }
}