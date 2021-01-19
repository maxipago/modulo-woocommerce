<?php

class WC_maxiPago_Cron {

	public static $transaction_states = array(
		'In Progress'                      => 1,
		'Captured'                         => 3,
		'Pending Capture'                  => 4,
		'Pending Authorization'            => 5,
		'Authorized'                       => 6,
		'Declined'                         => 7,
		'Reversed'                         => 8,
		'Voided'                           => 9,
		'Paid'                             => 10,
		'Pending Confirmation'             => 11,
		'Pending Review'                   => 12,
		'Pending Reversion'                => 13,
		'Pending Capture (retrial)'        => 14,
		'Pending Reversal'                 => 16,
		'Pending Void'                     => 18,
		'Pending Void (retrial)'           => 19,
		'Boleto Issued'                    => 22,
		'Pending Authentication'           => 29,
		'Authenticated'                    => 30,
		'Pending Reversal (retrial)'       => 31,
		'Authentication in Progress'       => 32,
		'Submitted Authentication'         => 33,
		'Boleto Viewed'                    => 34,
		'Boleto Underpaid'                 => 35,
		'Boleto Overpaid'                  => 36,
		'File Submission Pending Reversal' => 38,
		'Fraud Approved'                   => 44,
		'Fraud Declined'                   => 45,
		'Fraud Review'                     => 46
	);
	/** @var WC_Logger */
	protected $log;

	public function __construct() {
		if ( ! wp_next_scheduled( 'maxipago_update_cc_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'maxipago_update_cc_orders' );
		}
		if ( ! wp_next_scheduled( 'maxipago_update_dc_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'maxipago_update_dc_orders' );
		}
		if ( ! wp_next_scheduled( 'maxipago_update_boleto_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'maxipago_update_boleto_orders' );
		}
		if ( ! wp_next_scheduled( 'maxipago_update_online_debit_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'maxipago_update_online_debit_orders' );
		}
		add_action( 'maxipago_update_cc_orders', array( $this, 'update_cc_order_status' ) );
		add_action( 'maxipago_update_dc_orders', array( $this, 'update_dc_order_status' ) );
		add_action( 'maxipago_update_boleto_orders', array( $this, 'update_boleto_order_status' ) );
		add_action( 'maxipago_update_online_debit_orders', array( $this, 'update_online_debit_order_status' ) );
	}

	public function update_cc_order_status() {
		$this->update_order_status( WC_maxiPago_CC_Gateway::ID );
	}

	private function update_order_status( $method_id ) {
		$settings = get_option( 'woocommerce_' . $method_id . '_settings' );
		if ( is_array( $settings ) && isset( $settings['enabled'] ) && $settings['enabled'] == 'yes' ) {
			if ( 'yes' == $settings['save_log'] && class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			}
			if ( $this->log ) {
				$this->log->add( 'maxipago_api',
					'----------- Run CRON Update Order Status: ' . $method_id . ' ----------- ' . PHP_EOL );
			}
			$client = new maxiPago;
			try {
				$client->setCredentials( $settings['merchant_id'], $settings['merchant_key'] );
			} catch ( Exception $e ) {
			}
			try {
				$client->setEnvironment( $settings['environment'] );
			} catch ( Exception $e ) {
			}
			$orders = new WP_Query(
				array(
					'post_type'      => 'shop_order',
					'post_status'    => 'wc-on-hold',
					'posts_per_page' => - 1,
				)
			);
			while ( $orders->have_posts() ) {
				$orders->the_post();
				$order_id = $orders->post->ID;
				$order    = new WC_Order( $order_id );
				if ( $order->get_payment_method() == $method_id ) {
					$result_data = get_post_meta( $order_id, '_maxipago_result_data', true );
					if ( $result_data && isset( $result_data['orderID'] ) ) {
						if ( strlen( $result_data['orderID'] ) == 0 ) {
							if ( $this->log ) {
								$this->log->add( 'maxipago_api',
									'CRON Update [' . $method_id . ']: "strlen(orderID) == 0" for order [' . $order->get_id() . ']' );
							}
							continue;
						}

						$params = array(
							'orderID' => $result_data['orderID']
						);
						try {
							$client->pullReport( $params );
						} catch ( Exception $e ) {
						}
						$response = $client->getReportResult();
						if ( $this->log ) {
							$this->log->add( 'maxipago_api', '------------- pullReport -------------' );
							$this->log->add( 'maxipago_api', $client->xmlRequest );
							$this->log->add( 'maxipago_api', $client->xmlResponse );
						}
						$state = isset( $response[0]['transactionState'] ) ? $response[0]['transactionState'] : null;
						if ( $state ) {
							if ( $this->set_order_status( $order_id, $state ) ) {
								if ( $this->orderWasCaptured( $state ) ) {
									update_post_meta( $order->get_id(), '_maxipago_capture_result_data', $response );
									update_post_meta( $order->get_id(), 'responseMessage', 'CAPTURED' );
								}

								if ( $this->log ) {
									$this->log->add( 'maxipago_api',
										'[' . $method_id . '] Update Order Status: ' . $settings['invoice_prefix'] . $order_id );
								}

								$firstResponse = $response[0];
								foreach ( $firstResponse as $key => $value ) {
									update_post_meta( $order->get_id(), $key, $value );
								}
							}
						}
					}
				}
			}
		}
	}

	public function set_order_status( $order_id, $status ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			if ( $order->get_id() === $order_id ) {

				$states       = self::$transaction_states;
				$order_status = sanitize_text_field( $status );

				switch ( $order_status ) {
					case $states['Fraud Approved']:
						$api = new WC_maxiPago_CC_API();

						return $api->capture_order( $order );
					case $states['Captured']:
					case $states['Paid']:
						$order->payment_complete();

						return true;
					case $states['Boleto Overpaid']:
						$order->payment_complete();
						$order->add_order_note( __( 'maxiPago!: Boleto pago com valor acima.', 'modulo-woocommerce' ) );

						return true;
					case $states['Boleto Underpaid']:
						$status = $order->get_status();
						$order->update_status( $status,
							__( 'maxiPago!: Boleto pago com valor abaixo.', 'modulo-woocommerce' ) );

						return true;
					case $states['Declined']:
						$order->update_status( 'failed', __( 'maxiPago!: Payment Denied.', 'modulo-woocommerce' ) );

						return true;
					case $states['Fraud Declined']:
						$order->update_status( 'failed',
							__( 'maxiPago!: Payment Denied for duplicity or fraud.', 'modulo-woocommerce' ) );

						return true;
					case $states['Voided']:
						$order->update_status( 'cancelled',
							__( 'maxiPago!: Payment Cancelled (Voided).', 'modulo-woocommerce' ) );

						return true;
					case $states['In Progress']:
					case $states['Pending Capture']:
					case $states['Pending Authorization']:
					case $states['Authorized']:
					case $states['Reversed']:
					case $states['Pending Confirmation']:
					case $states['Pending Review']:
					case $states['Pending Reversion']:
					case $states['Pending Capture (retrial)']:
					case $states['Pending Reversal']:
					case $states['Pending Void']:
					case $states['Pending Void (retrial)']:
					case $states['Boleto Issued']:
					case $states['Pending Authentication']:
					case $states['Authenticated']:
					case $states['Pending Reversal (retrial)']:
					case $states['Authentication in Progress']:
					case $states['Submitted Authentication']:
					case $states['Boleto Viewed']:
					case $states['File Submission Pending Reversal']:
					case $states['Fraud Review']:
						// Not Defined Yet
						break;
					default:
						return false;
				}
			}
		}

		return null;
	}

	public function orderWasCaptured( $orderState ) {
		$validStatus = array(
			self::$transaction_states['Captured'],
			self::$transaction_states['Paid'],
			self::$transaction_states['Boleto Overpaid']
		);

		return in_array( $orderState, $validStatus );
	}

	public function update_dc_order_status() {
		$this->update_order_status( WC_maxiPago_DC_Gateway::ID );
	}

	public function update_boleto_order_status() {
		$this->update_order_status( WC_maxiPago_Boleto_Gateway::ID );
	}

	public function update_online_debit_order_status() {
		$this->update_order_status( WC_maxiPago_Online_Debit_Gateway::ID );
	}
}

$cron = new WC_maxiPago_Cron();