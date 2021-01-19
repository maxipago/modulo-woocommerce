<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_maxiPago_CC_API extends WC_maxiPago_API {

	public function get_installments_html( $order_total = 0 ) {
		$html         = '';
		$installments = $this->gateway->installments;
		if ( '1' == $installments ) {
			return $html;
		}
		$html              .= '<select id="maxipago-installments" name="maxipago_installments" style="font-size: 1.5em; padding: 4px; width: 100%;">';
		$intallment_values = $this->get_installments( $order_total );
		for ( $i = 1; $i <= $installments; $i ++ ) {
			$total                = $order_total / $i;
			$credit_interest      = '';
			$min_per_installments = ( WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT <= $this->gateway->min_per_installments )
				? $this->gateway->min_per_installments : WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT;
			if ( $i >= $this->gateway->max_without_interest && 0 != $this->gateway->max_without_interest ) {
				if ( ! isset( $intallment_values[ $i - 1 ] ) ) {
					continue;
				}
				$interest_total       = $intallment_values[ $i - 1 ]['installment_value'];
				$interest_order_total = $intallment_values[ $i - 1 ]['total'];
				if ( $total < $interest_total ) {
					$total           = $interest_total;
					$credit_interest = sprintf( '(%s%% por mês - Total: %s)',
						$this->clean_values( $this->gateway->interest_rate ),
						sanitize_text_field( wc_price( $interest_order_total ) ) );
				}
			}
			if ( 1 != $i && $total < $min_per_installments ) {
				continue;
			}
			$html .= '<option value="' . $i . '">' . esc_html( sprintf( '%sx de %s %s', $i,
					sanitize_text_field( wc_price( $total ) ), $credit_interest ) ) . '</option>';
		}
		$html .= '</select>';

		return $html;
	}

	private function get_installments( $price = null ) {
		$price                         = (float) $price;
		$max_installments              = $this->gateway->installments;
		$installments_without_interest = $this->gateway->max_without_interest;
		$min_per_installment           = $this->gateway->min_per_installments;
		$interest_rate                 = $this->clean_values( $this->gateway->interest_rate );
		if ( $min_per_installment > 0 ) {
			while ( $max_installments > ( $price / $min_per_installment ) ) {
				$max_installments --;
			}
		}
		$installments = array();
		if ( $price > 0 ) {
			$max_installments = ( $max_installments == 0 ) ? 1 : $max_installments;
			for ( $i = 1; $i <= $max_installments; $i ++ ) {
				$interest_rate_installment = ( $i <= $installments_without_interest ) ? '' : $interest_rate;
				$value                     = ( $i <= $installments_without_interest ) ? ( $price / $i ) :
					$this->get_installment_price( $price, $i, $interest_rate,
						$this->gateway->interest_rate_caculate_method );
				$total                     = $value * $i;
				$installments[]            = array(
					'total'             => $total,
					'installments'      => $i,
					'installment_value' => $value,
					'interest_rate'     => $interest_rate_installment
				);
			}
		}

		return $installments;
	}

	private function get_installment_price( $price, $installments, $interest_rate, $type = 'price' ) {
		$price = (float) $price;
		$value = 0;
		if ( $interest_rate ) {
			$interest_rate = (float) ( str_replace( ',', '.', $interest_rate ) ) / 100;
			switch ( $type ) {
				case 'price':
					$value = round( $price * ( ( $interest_rate * pow( ( 1 + $interest_rate ), $installments ) ) /
					                           ( pow( ( 1 + $interest_rate ), $installments ) - 1 ) ), 2 );
					break;
				case 'compound':
					$value = ( $price * pow( 1 + $interest_rate, $installments ) ) / $installments;
					break;
				case 'simple':
					$value = ( $price * ( 1 + ( $installments * $interest_rate ) ) ) / $installments;
			}
		} else {
			if ( $installments ) {
				$value = $price / $installments;
			}
		}

		return $value;
	}

	public function get_processor_by_acquirer( $type = 'all' ) {
		$processors = array(
			'1'  => 'Simulador',
			'2'  => 'Redecard',
			'3'  => 'GetNet',
			'4'  => 'Cielo',
			'5'  => 'e.Rede',
			'6'  => 'Elavon',
			'9'  => 'Stone',
			'10' => 'BIN'
		);
		$types      = array(
			'all'       => array( '1', '2', '3', '4', '5', '6', '9', '10' ),
			'amex'      => array( '1', '4', '5' ),
			'diners'    => array( '1', '2', '4', '5', '6' ),
			'elo'       => array( '1', '3', '4', '5' ),
			'discover'  => array( '1', '2', '4', '5', '6' ),
			'hipercard' => array( '1', '2', '5' ),
			'hiper'     => array( '1', '2', '5' ),
			'jcb'       => array( '1', '2', '4', '5' ),
			'aura'      => array( '1', '4' ),
			'credz'     => array( '1', '2', '5' ),
		);
		foreach ( $processors as $typeId => $typeName ) {
			if ( ! in_array( $typeId, $types[ $type ] ) ) {
				unset( $processors[ $typeId ] );
			}
		}
		$processors = array( '' => 'Desabilitar' ) + $processors;

		return $processors;
	}

	public function get_fraud_processor() {
		return array(
			'99' => 'Kount',
			'98' => 'Clear Sale'
		);
	}

	public function order_is_new_payment( WC_Order $order ) {
		return $this->get_maxipago_order_id( $order ) == null;
	}

	public function get_maxipago_order_id( WC_Order $order ) {
		return get_post_meta( $order->get_id(), 'orderID', 'single' );
	}

	public function get_order( $order_id ) {
		return wc_get_order( $order_id );
	}

	public function sale_order( WC_Order $order, $post ) {
		$result = array(
			'result'   => 'fail',
			'redirect' => ''
		);

		$this->instantiate_logger();

		$is_valid = $this->validate_fields( $post );

		if ( $is_valid ) {
			$is_valid = $this->validate_installments( $post, (float) $order->get_total() );
		}
		if ( $is_valid ) {
			$client       = $this->get_maxipago_client();
			$token_number = null;

			if ( $this->is_sale_with_token( $post ) ) {
				$token_id     = $post['wc-maxipago-cc-payment-token'];
				$token_number = $this->get_token( $token_id );
			}

			$expiry          = $this->get_cc_expiration( sanitize_text_field( $post['maxipago_card_expiry'] ) );
			$cc_number       = sanitize_text_field( $this->clean_number( $post['maxipago_card_number'] ) );
			$cc_installments = sanitize_text_field( $this->clean_number( $post['maxipago_installments'] ) );

			$customer_document = $post['maxipago_cc_document'];

			if ( $this->gateway->installments == 1 ) {
				$cc_installments = 1;
			}

			$cc_brand     = $this->get_cc_brand( $cc_number );
			$processor_id = $this->get_processor_id_by_cc_brand( $cc_brand );

			$shipping_total = (float) $order->get_shipping_total();
			$charge_total   = (float) $order->get_total();

			$has_interest = 'N';
			if ( $this->gateway->interest_rate && $cc_installments > $this->gateway->max_without_interest ) {
				$has_interest = 'Y';
				$charge_total = $this->get_total_by_installments( $charge_total, $cc_installments,
					$this->gateway->interest_rate );
			}

			$ipAddress = $this->clean_ip_address( $order->get_customer_ip_address() );
			$orderId   = $this->gateway->invoice_prefix . $order->get_id();

			$request_data = array(
				'referenceNum'         => $orderId,
				'processorID'          => $processor_id,
				'ipAddress'            => $ipAddress,
				'customerIdExt'        => $customer_document,
				'currencyCode'         => get_woocommerce_currency(),
				'chargeTotal'          => wc_format_decimal( $charge_total, wc_get_price_decimals() ),
				'shippingTotal'        => wc_format_decimal( $shipping_total, wc_get_price_decimals() ),
				'numberOfInstallments' => $cc_installments,
				'chargeInterest'       => $has_interest
			);

			$addressData  = $this->getAddressData( $order, $customer_document );
			$orderData    = $this->getOrderData( $order );
			$request_data = array_merge( $request_data, $addressData, $orderData );

			if ( ! $this->is_sale_with_token( $post ) ) {
				$request_data['number']    = $cc_number;
				$request_data['expMonth']  = $expiry['month'];
				$request_data['expYear']   = $expiry['year'];
				$request_data['cvvNumber'] = sanitize_text_field( $post['maxipago_card_cvc'] );
			} else {
				$customer_id                = get_current_user_id();
				$maxipago_customer_id       = get_user_meta( $customer_id,
					'_maxipago_customer_id_' . $this->gateway->merchant_id, true );
				$request_data['token']      = $token_number;
				$request_data['customerId'] = $maxipago_customer_id;
				$request_data['cvvNumber']  = sanitize_text_field( $post['maxipago_card_cvc_token'] );
			}

			$fraudcheck_data = $this->getFraudCheckData( $order );
			$request_data    = array_merge( $request_data, $fraudcheck_data );

			if ( $this->gateway->soft_descriptor ) {
				$request_data['softDescriptor'] = $this->gateway->soft_descriptor;
			}

			if ( $this->is_save_token_checked( $post ) ) {
				$cc_info = array(
					'cc_number' => $cc_number,
					'cc_expiry' => sanitize_text_field( $post['maxipago_card_expiry'] ),
					'cc_cvc'    => sanitize_text_field( $post['maxipago_card_cvc'] ),
				);
				$this->save_token( $cc_info );
			}

			$use3DS = $this->gateway->use3DS == 'yes';
			if ( $use3DS ) {
				$request_data['mpiProcessorID'] = $this->gateway->mpi_processor;
				$request_data['onFailure']      = $this->gateway->failure_action;
			}

			$addressData  = $this->getAddressData( $order, $customer_document );
			$orderData    = $this->getOrderData( $order );
			$request_data = array_merge( $request_data, $addressData, $orderData );

			if ( $this->gateway->processing_type == 'auth' ) {
				if ( $use3DS ) {
					try {
						$client->authCreditCard3DS( $request_data );
					} catch ( Exception $e ) {
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', '------------- authCreditCard3DS -------------' );
					}
				} else {
					try {
						$client->creditCardAuth( $request_data );
					} catch ( Exception $e ) {
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', '------------- creditCardAuth -------------' );
					}
				}
			} else {
				if ( $use3DS ) {
					try {
						$client->saleCreditCard3DS( $request_data );
					} catch ( Exception $e ) {
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', '------------- saleCreditCard3DS -------------' );
					}
				} else {
					try {
						$client->creditCardSale( $request_data );
					} catch ( Exception $e ) {
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', '------------- creditCardSale -------------' );
					}
				}
			}

			if ( $this->log ) {
				$xmlRequest = preg_replace( '/<number>(.*)<\/number>/m', '<number>*****</number>',
					$client->xmlRequest );
				$xmlRequest = preg_replace( '/<cvvNumber>(.*)<\/cvvNumber>/m', '<cvvNumber>***</cvvNumber>',
					$xmlRequest );
				$xmlRequest = preg_replace( '/<token>(.*)<\/token>/m', '<token>***</token>', $xmlRequest );
				$this->log->add( 'maxipago_api', $xmlRequest );
				$this->log->add( 'maxipago_api', $client->xmlResponse );
			}

			$request_data['number']    = 'XXXXXXXXXXXXXXXX';
			$request_data['token']     = 'XXX';
			$request_data['cvvNumber'] = 'XXX';
			update_post_meta( $order->get_id(), '_maxipago_request_data', $request_data );

			$result = $client->getResult();

			if ( ! empty( $result ) ) {
				update_post_meta( $order->get_id(), '_maxipago_result_data', $result );
				if ( $client->isErrorResponse() ) {
					update_post_meta( $order->get_id(), 'maxipago_transaction_id', $client->getTransactionID() );
					update_post_meta( $order->get_id(), 'maxipago_error', $client->getMessage() );
					wc_add_notice( '<strong>' . esc_html( $this->gateway->title ) . '</strong>: ' . wc_clean( $client->getMessage() ),
						'error' );
				} else {
					if ( $client->getTransactionID() ) {
						update_post_meta( $order->get_id(), 'maxipago_transaction_id', $client->getTransactionID() );
						update_post_meta( $order->get_id(), 'maxipago_response_message', $client->getMessage() );
						update_post_meta( $order->get_id(), 'maxipago_processor_transaction_id',
							$client->getProcessorTransactionID() );
						update_post_meta( $order->get_id(), 'maxipago_processor_code', $client->getProcessorCode() );
						if ( $fraudcheck_data['fraudCheck'] == 'Y' ) {
							update_post_meta( $order->get_id(), 'maxipago_fraud_score', $client->getFraudScore() );
						}

						if ( $this->gateway->processing_type == 'sale' ) {
							update_post_meta( $order->get_id(), '_maxipago_capture_result_data', $result );
							update_post_meta( $order->get_id(), 'responseMessage', 'CAPTURED' );
						}

						$this->updatePostMeta( $order->get_id(), $result );
					}
					$updated = $this->set_order_status(
						$client->getReferenceNum(),
						$client->getResponseCode(),
						$this->gateway->invoice_prefix,
						$this->gateway->processing_type
					);
					if ( $updated ) {
						WC()->cart->empty_cart();
						$result = array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url()
						);
					}
				}
			} else {
				wc_add_notice( '<strong>' . esc_html( $this->gateway->title ) . '</strong>: ' .
				               esc_html( 'Ocorreu um erro ao processar seu pagamento, por favor tente novamente.' ),
					'error' );
			}
		}

		return $result;
	}

	private function instantiate_logger() {
		if ( 'yes' == $this->gateway->save_log && class_exists( 'WC_Logger' ) ) {
			$this->log = new WC_Logger();
		}
	}

	private function validate_fields( $formData ) {
		try {
			if ( isset( $formData['wc-maxipago-cc-payment-token'] ) && $formData['wc-maxipago-cc-payment-token'] != 'new' ) {
				if ( ( ! isset( $formData['maxipago_card_cvc_token'] ) || '' === $formData['maxipago_card_cvc_token'] ) ) {
					throw new Exception( __( 'Please type the cvc code for the card.', 'modulo-woocommerce' ) );
				}
			} else {


				if ( ! isset( $formData['maxipago_card_number'] ) || '' === $formData['maxipago_card_number'] ) {
					throw new Exception( __( 'Please type the card number.', 'modulo-woocommerce' ) );
				}
				if ( ! isset( $formData['maxipago_holder_name'] ) || '' === $formData['maxipago_holder_name'] ) {
					throw new Exception( __( 'Please type the name of the card holder.', 'modulo-woocommerce' ) );
				}
				if ( ! isset( $formData['maxipago_card_expiry'] ) || '' === $formData['maxipago_card_expiry'] ) {
					throw new Exception( __( 'Please type the card expiry date.', 'modulo-woocommerce' ) );
				}
				if ( ! isset( $formData['maxipago_card_cvc'] ) || '' === $formData['maxipago_card_cvc'] ) {
					throw new Exception( __( 'Please type the cvc code for the card', 'modulo-woocommerce' ) );
				}
			}

			if ( ! isset( $formData['maxipago_cc_document'] ) || '' === $formData['maxipago_cc_document'] || ! $this->check_document( $formData['maxipago_cc_document'] ) ) {
				throw new Exception( __( 'Please type the valid document number.', 'modulo-woocommerce' ) );
			}


		} catch ( Exception $e ) {
			wc_add_notice( '<strong>' . esc_html( $this->gateway->title ) . '</strong>: ' . esc_html( $e->getMessage() ),
				'error' );

			return false;
		}

		return true;
	}

	private function validate_installments( $posted, $order_total ) {
		try {
			if ( ! isset( $posted['maxipago_installments'] ) && 1 == $this->gateway->installments ) {
				return true;
			}
			if ( ! isset( $posted['maxipago_installments'] ) || ! $posted['maxipago_installments'] ) {
				throw new Exception( __( 'Please select a number of installments.', 'modulo-woocommerce' ) );
			}
			$installments        = intval( $posted['maxipago_installments'] );
			$installment_total   = $order_total / $installments;
			$installments_config = $this->gateway->installments;
			if ( $installments >= $this->gateway->max_without_interest && 0 != $this->gateway->max_without_interest ) {
				$interest_rate     = $this->clean_values( $this->gateway->interest_rate );
				$interest_total    = $this->get_total_by_installments( $order_total, $installments, $interest_rate );
				$installment_total = ( $installment_total < $interest_total ) ? $interest_total : $installment_total;
			}
			$min_per_installments = ( WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT <= $this->gateway->min_per_installments )
				? $this->gateway->min_per_installments : WC_maxiPago_CC_Gateway::MIN_PER_INSTALLMENT;
			if ( $installments > $installments_config || 1 != $installments && $installment_total < $min_per_installments ) {
				throw new Exception( __( 'Invalid number of installments.', 'modulo-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			wc_add_notice( '<strong>' . esc_html( $this->gateway->title ) . '</strong>: ' . esc_html( $e->getMessage() ),
				'error' );

			return false;
		}

		return true;
	}

	private function get_total_by_installments( $price, $installments, $interest_rate ) {
		return $this->get_installment_price( $price, $installments, $interest_rate,
				$this->gateway->interest_rate_caculate_method ) * $installments;
	}

	private function get_maxipago_client() {
		$client = new maxiPago();
		try {
			$client->setCredentials( $this->gateway->merchant_id, $this->gateway->merchant_key );
		} catch ( Exception $e ) {
		}
		try {
			$client->setEnvironment( $this->gateway->environment );
		} catch ( Exception $e ) {
		}

		return $client;
	}

	private function is_sale_with_token( $formData ) {
		return isset( $formData['wc-maxipago-cc-payment-token'] ) && $formData['wc-maxipago-cc-payment-token'] != 'new';
	}

	public function get_token( $token_id ) {
		$token = new WC_Payment_Token_CC( $token_id );

		return $token->get_token();
	}

	private function get_cc_expiration( $value ) {
		$value = explode( '/', $value );
		$month = isset( $value[0] ) ? trim( $value[0] ) : '';
		$year  = isset( $value[1] ) ? trim( $value[1] ) : '';

		return array(
			'month' => $month,
			'year'  => $year,
		);
	}

	private function get_cc_brand( $number ) {
		$allowed_brands = array(
			'visa'       => '/^4\d{12}(\d{3})?$/',
			'mastercard' => '/^(5[1-5]\d{4}|677189)\d{10}$/',
			'hipercard'  => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
			'amex'       => '/^3[47]\d{13}$/',
			'diners'     => '/^3(0[0-5]|[68]\d)\d{11}$/',
			'elo'        => '/^((((636368)|(438935)|(504175)|(451416)|(636297))\d{0,10})|((5067)|(4576)|(4011))\d{0,12})$/',
			'discover'   => '/^6(?:011&#124;5[0-9]{2})[0-9]{12}$/',
			'jcb'        => '/^(?:2131|1800|35\d{3})\d{11}$/',
			'aura'       => '/^(5078\d{2})(\d{2})(\d{11})$/',
			'credz'      => '/\d{12,18}$/'
		);
		foreach ( $allowed_brands as $key => $value ) {
			if ( preg_match( $value, $number ) ) {
				return $key;
			}
		}

		return null;
	}

	private function get_processor_id_by_cc_brand( $cc_brand ) {
		$name_config = 'acquirers_' . $cc_brand;

		return $this->gateway->$name_config;
	}

	private function getFraudCheckData( WC_Order $order ) {
		$fraud_data = array(
			'fraudCheck' => $this->gateway->fraud_check == 'yes' ? 'Y' : 'N'
		);

		if ( $fraud_data['fraudCheck'] == 'N' || $this->gateway->processing_type == 'sale' ) {
			return $fraud_data;
		}

		return array_merge( $fraud_data, $this->getFraudProcessorData( $order ) );
	}

	private function getFraudProcessorData( WC_Order $order ) {
		if ( $this->gateway->fraud_processor ) {
			$processor_data = array(
				'fraudProcessorID' => $this->gateway->fraud_processor,
				'voidOnHighRisk'   => $this->gateway->auto_void == 'yes' ? 'Y' : 'N',
				'captureOnLowRisk' => $this->gateway->auto_capture == 'yes' ? 'Y' : 'N',
				'websiteId'        => 'DEFAULT'
			);

			if ( $processor_data['fraudProcessorID'] == '98' ) {
				$sessionId                    = session_id();
				$processor_data['fraudToken'] = $sessionId;
			} else {
				if ( $processor_data['fraudProcessorID'] == '99' ) {
					$sessionId                    = session_id();
					$merchantId                   = $this->gateway->merchant_id;
					$hash                         = hash_hmac( 'md5', $merchantId . '*' . $sessionId, $merchantId );
					$processor_data['fraudToken'] = $hash;
				}
			}

			return $processor_data;
		}

		return null;
	}

	private function is_save_token_checked( $formData ) {
		return isset( $formData['wc-maxipago-cc-new-payment-method'] ) && $formData['wc-maxipago-cc-new-payment-method'] == 'true';
	}

	public function save_token( $cc_info ) {
		$cc_expiry            = $this->get_cc_expiration( $cc_info['cc_expiry'] );
		$cc_brand             = $this->get_cc_brand( $this->clean_number( $cc_info['cc_number'] ) );
		$customer_id          = get_current_user_id();
		$user                 = new WP_User( $customer_id );
		$cc_settings          = get_option( 'woocommerce_maxipago-cc_settings' );
		$maxipago_customer_id = get_user_meta( $customer_id, '_maxipago_customer_id_' . $cc_settings['merchant_id'],
			true );
		if ( ! $cc_settings ) {
			return false;
		}
		if ( 'yes' == $cc_settings['save_log'] && class_exists( 'WC_Logger' ) ) {
			$this->log = new WC_Logger();
		}
		$client = new maxiPago();
		try {
			$client->setCredentials( $cc_settings['merchant_id'], $cc_settings['merchant_key'] );
		} catch ( Exception $e ) {
		}
		try {
			$client->setEnvironment( $cc_settings['environment'] );
		} catch ( Exception $e ) {
		}
		$is_default = false;
		if ( ! $maxipago_customer_id ) {
			$is_default   = true;
			$customerData = array(
				'customerIdExt' => $customer_id,
				'firstName'     => $user->first_name,
				'lastName'      => $user->last_name
			);
			try {
				$client->addProfile( $customerData );
			} catch ( Exception $e ) {
			}
			if ( $this->log ) {
				$this->log->add( 'maxipago_api', '------------- addProfile -------------' );
				$this->log->add( 'maxipago_api', $client->xmlRequest );
				$this->log->add( 'maxipago_api', $client->xmlResponse );
			}
			$maxipago_customer_id = $client->getCustomerId();
			if ( ! $maxipago_customer_id ) {
				return false;
			}
			add_user_meta( $customer_id, '_maxipago_customer_id_' . $cc_settings['merchant_id'],
				$maxipago_customer_id );
		}

		try {
			$date = new DateTime( $cc_expiry['year'] . '-' . $cc_expiry['month'] . '-01' );
			$date->modify( '+1 month' );

			$endDate    = $date->format( 'm/d/Y' );
			$token_data = array(
				'customerId'        => $maxipago_customer_id,
				'creditCardNumber'  => $this->clean_number( $cc_info['cc_number'] ),
				'expirationMonth'   => $cc_expiry['month'],
				'expirationYear'    => $cc_expiry['year'],
				'billingName'       => $user->billing_first_name . ' ' . $user->billing_last_name,
				'billingAddress1'   => $user->billing_address_1 . ' ' . $user->billing_number,
				'billingAddress2'   => $user->billing_address_2,
				'billingCity'       => $user->billing_city,
				'billingState'      => $user->billing_state,
				'billingZip'        => $this->clean_number( $user->billing_postcode ),
				'billingPhone'      => $this->clean_number( $user->billing_phone ),
				'billingEmail'      => $user->user_email,
				'onFileEndDate'     => $endDate,
				'onFilePermissions' => 'ongoing',
			);

			$client->addCreditCard( $token_data );

			if ( $this->log ) {
				$this->log->add( 'maxipago_api', '------------- addCreditCard -------------' );
				$xmlRequest = preg_replace( '/<creditCardNumber>(.*)<\/creditCardNumber>/m',
					'<creditCardNumber>*****</creditCardNumber>', $client->xmlRequest );
				$this->log->add( 'maxipago_api', $xmlRequest );
				$this->log->add( 'maxipago_api', $client->xmlResponse );
			}

			if ( $token_number = $client->getToken() ) {
				$token = new WC_Payment_Token_CC();
				$token->set_token( $token_number );
				$token->set_gateway_id( $this->gateway->id );
				$token->set_card_type( $cc_brand );
				$token->set_last4( substr( $this->clean_number( $cc_info['cc_number'] ), - 4 ) );
				$token->set_expiry_month( $cc_expiry['month'] );
				$token->set_expiry_year( $cc_expiry['year'] );
				$token->set_user_id( $customer_id );
				$token->set_default( $is_default );

				return $token->save();
			}
		} catch ( Exception $e ) {
		}

		return false;
	}

	public function capture_order( WC_Order $order ) {
		$message = __( 'The order does not allow capture.', 'modulo-woocommerce' );
		if ( $order->get_payment_method() == WC_maxiPago_CC_Gateway::ID && $order->get_status() == 'on-hold' ) {
			$cc_settings = get_option( 'woocommerce_maxipago-cc_settings' );
			if ( is_array( $cc_settings ) ) {
				if ( 'yes' == $cc_settings['save_log'] && class_exists( 'WC_Logger' ) ) {
					$this->log = new WC_Logger();
				}
				$message = __( 'There was an error capture the payment', 'modulo-woocommerce' );
				$client  = new maxiPago();
				try {
					$client->setCredentials( $cc_settings['merchant_id'], $cc_settings['merchant_key'] );
				} catch ( Exception $e ) {
				}
				try {
					$client->setEnvironment( $cc_settings['environment'] );
				} catch ( Exception $e ) {
				}
				$result_data = get_post_meta( $order->get_id(), '_maxipago_result_data', true );
				if ( $result_data ) {
					$data = array(
						'orderID'      => $result_data['orderID'],
						'referenceNum' => $result_data['referenceNum'],
						'chargeTotal'  => wc_format_decimal( (float) $order->get_total(), wc_get_price_decimals() )
					);
					try {
						$client->creditCardCapture( $data );
					} catch ( Exception $e ) {
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', '------------- creditCardCapture -------------' );
						$this->log->add( 'maxipago_api', $client->xmlRequest );
						$this->log->add( 'maxipago_api', $client->xmlResponse );
					}
					$result = $client->getResult();
					if ( ! $client->isErrorResponse() && $client->getResponseCode() == 0 ) {
						$order->payment_complete();
						update_post_meta( $order->get_id(), '_maxipago_capture_result_data', $result );
						update_post_meta( $order->get_id(), 'responseMessage', 'CAPTURED' );
						$message = sprintf( __( 'Payment captured successfully - User: %s', 'modulo-woocommerce' ),
							wp_get_current_user()->display_name );
						set_transient( 'maxipago_admin_notice', array( $message, 'notice' ), 5 );

						return true;
					} else {
						$message = sprintf( __( 'There was an error capture the payment - Error: %s',
							'modulo-woocommerce' ), $client->getMessage() );
					}
				}
			}
		}
		set_transient( 'maxipago_admin_notice', array( $message, 'error' ), 5 );

		return false;
	}

	public function refund_order( WC_Order $order ) {
		$message = __( 'The order does not allow refund.', 'modulo-woocommerce' );
		if ( $order->get_payment_method() == WC_maxiPago_CC_Gateway::ID && $order->get_status() == 'processing' ) {
			$cc_settings = get_option( 'woocommerce_maxipago-cc_settings' );
			if ( is_array( $cc_settings ) ) {
				if ( 'yes' == $cc_settings['save_log'] && class_exists( 'WC_Logger' ) ) {
					$this->log = new WC_Logger();
				}
				$message = __( 'There was an error refund the payment', 'modulo-woocommerce' );
				$client  = new maxiPago();
				try {
					$client->setCredentials( $cc_settings['merchant_id'], $cc_settings['merchant_key'] );
				} catch ( Exception $e ) {
				}
				try {
					$client->setEnvironment( $cc_settings['environment'] );
				} catch ( Exception $e ) {
				}
				$result_data = get_post_meta( $order->get_id(), '_maxipago_result_data', true );
				if ( $result_data ) {
					$can_void     = date( 'Ymd', $result_data['transactionTimestamp'] ) == date( 'Ymd' );
					$capture_data = get_post_meta( $order->get_id(), '_maxipago_capture_result_data', true );
					if ( $capture_data ) {
						$can_void = date( 'Ymd', $capture_data['transactionTimestamp'] ) == date( 'Ymd' );
					}
					if ( $can_void ) {
						if ( $this->log ) {
							$this->log->add( 'maxipago_api', '------------- creditCardVoid -------------' );
						}
						$data = array(
							'transactionID' => $result_data['transactionID'],
						);
						try {
							$client->creditCardVoid( $data );
						} catch ( Exception $e ) {
						}
					} else {
						if ( $this->log ) {
							$this->log->add( 'maxipago_api', '------------- creditCardRefund -------------' );
						}
						$charge_total   = (float) $order->get_total();
						$shipping_total = (float) $order->get_shipping_total();

						$data = array(
							'orderID'       => $result_data['orderID'],
							'referenceNum'  => $result_data['referenceNum'],
							'chargeTotal'   => wc_format_decimal( $charge_total, wc_get_price_decimals() ),
							'shippingTotal' => wc_format_decimal( $shipping_total, wc_get_price_decimals() )
						);
						try {
							$client->creditCardRefund( $data );
						} catch ( Exception $e ) {
						}
					}
					if ( $this->log ) {
						$this->log->add( 'maxipago_api', $client->xmlRequest );
						$this->log->add( 'maxipago_api', $client->xmlResponse );
					}
					$result = $client->getResult();
					update_post_meta( $order->get_id(), '_maxipago_refund_result_data', $result );
					if ( ! $client->isErrorResponse() && $client->getResponseCode() == 0 ) {
						if ( $can_void ) {
							$message = sprintf( __( 'Payment cancelled successfully - User: %s', 'modulo-woocommerce' ),
								wp_get_current_user()->display_name );
							$order->update_status( 'cancelled', __( 'Payment cancelled.', 'modulo-woocommerce' ) );
						} else {
							$message = sprintf( __( 'Payment refunded successfully - User: %s', 'modulo-woocommerce' ),
								wp_get_current_user()->display_name );
							$order->update_status( 'refunded', __( 'Payment refunded.', 'modulo-woocommerce' ) );
						}
						set_transient( 'maxipago_admin_notice', array( $message, 'notice' ), 5 );

						return true;
					} else {
						$message = sprintf( __( 'There was an error refund the payment - Error: %s',
							'modulo-woocommerce' ), $client->getMessage() );
					}
				}
			}
		}
		set_transient( 'maxipago_admin_notice', array( $message, 'error' ), 5 );

		return false;
	}

	public function delete_token( WC_Payment_Token_CC $object_token ) {
		$token                = $object_token->get_token();
		$customer_id          = get_current_user_id();
		$cc_settings          = get_option( 'woocommerce_maxipago-cc_settings' );
		$maxipago_customer_id = get_user_meta( $customer_id, '_maxipago_customer_id_' . $cc_settings['merchant_id'],
			true );
		if ( ! $maxipago_customer_id ) {
			return false;
		}
		if ( ! $cc_settings ) {
			return false;
		}
		if ( 'yes' == $cc_settings['save_log'] && class_exists( 'WC_Logger' ) ) {
			$this->log = new WC_Logger();
		}
		$client = new maxiPago();
		try {
			$client->setCredentials( $cc_settings['merchant_id'], $cc_settings['merchant_key'] );
		} catch ( Exception $e ) {
		}
		try {
			$client->setEnvironment( $cc_settings['environment'] );
		} catch ( Exception $e ) {
		}
		$token_data = array(
			'customerId' => $maxipago_customer_id,
			'token'      => $token,
		);
		try {
			$client->deleteCreditCard( $token_data );
		} catch ( Exception $e ) {
		}
		if ( $this->log ) {
			$this->log->add( 'maxipago_api', '------------- deleteCreditCard -------------' );
			$this->log->add( 'maxipago_api', $client->xmlRequest );
			$this->log->add( 'maxipago_api', $client->xmlResponse );
		}

		return true;
	}

	public function get_mpi_processors() {
		return array(
			'41' => __( 'Test', 'modulo-woocommerce' ),
			'40' => __( 'Production', 'modulo-woocommerce' ),
		);
	}

	public function get_mpi_action() {
		return array(
			'decline'  => __( 'Stop Processing', 'modulo-woocommerce' ),
			'continue' => __( 'Continue Processing', 'modulo-woocommerce' ),
		);
	}

	public function get_card_type( $token_id ) {
		$token = new WC_Payment_Token_CC( $token_id );

		return $token->get_card_type();
	}

	public function get_cc_expiration_by_token( $token_id ) {
		$token = new WC_Payment_Token_CC( $token_id );

		return array(
			'month' => $token->get_expiry_month(),
			'year'  => $token->get_expiry_year()
		);
	}
}