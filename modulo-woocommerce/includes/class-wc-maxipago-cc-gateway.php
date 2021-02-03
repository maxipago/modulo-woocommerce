<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_maxiPago_CC_Gateway extends WC_Payment_Gateway_CC {

	const ID = 'maxipago-cc';

	const MIN_PER_INSTALLMENT = '5';

	const PROCESSING_TYPE_SALE = 'sale';
	const PROCESSING_TYPE_AUTH = 'auth';

	const INTEREST_RATE_TYPE_SIMPLE = 'simple';
	const INTEREST_RATE_TYPE_COMPOUND = 'compound';
	const INTEREST_RATE_TYPE_PRICE = 'price';

	/** @var WC_maxiPago_CC_API */
	public $api;

	public $environment;
	public $merchant_id;
	public $merchant_key;
	public $invoice_prefix;
	public $save_log;
	public $soft_descriptor;
	public $installments;
	public $interest_rate_caculate_method;
	public $interest_rate;
	public $max_without_interest;
	public $min_per_installments;
	public $fraud_check;
	public $auto_capture;
	public $auto_void;
	public $fraud_processor;
	public $processing_type;
	public $use_token;

	public $supports = array(
		'products',
		'refunds'
	);

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = 'Cartão de crédito - maxiPago!';
		$this->method_description = 'Aceite cartões de crédito com a maxiPago!';
		$this->has_fields         = true;

		// Global Settings
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->environment    = $this->get_option( 'environment' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_key   = $this->get_option( 'merchant_key' );
		$this->invoice_prefix = 'WC-';
		$this->save_log       = $this->get_option( 'save_log' );

		// CC Settings
		$this->soft_descriptor               = $this->get_option( 'soft_descriptor' );
		$this->installments                  = $this->get_option( 'installments' );
		$this->interest_rate_caculate_method = $this->get_option( 'interest_rate_caculate_method',
			self::INTEREST_RATE_TYPE_SIMPLE );
		$this->interest_rate                 = $this->get_option( 'interest_rate' );
		$this->max_without_interest          = $this->get_option( 'max_without_interest' );
		$this->min_per_installments          = $this->get_option( 'min_per_installments' );
		$this->fraud_check                   = $this->get_option( 'fraud_check' );
		$this->auto_capture                  = $this->get_option( 'auto_capture' );
		$this->auto_void                     = $this->get_option( 'auto_void' );
		$this->fraud_processor               = $this->get_option( 'fraud_processor' );
		$this->processing_type               = $this->get_option( 'processing_type' );
		$this->use_token                     = $this->get_option( 'use_token' );

		if ( $this->use_token == 'yes' ) {
			$this->supports[] = 'tokenization';
		}

		$this->api = new WC_maxiPago_CC_API( $this );

		$this->init_form_fields();
		$this->init_settings();

		// Front actions
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'set_thankyou_page' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'set_email_instructions' ), 10, 3 );

		// Admin actions
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'woocommerce_order_action_cancel_order',
				array( $this, 'process_cancel_order_meta_box_actions' ) );
			add_action( 'woocommerce_update_order', array( $this, 'update_order_check_cancel_or_refund' ) );
			add_action( 'woocommerce_update_order', array( $this, 'update_order_fire_capture_action' ) );
		}
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Habilitar',
				'type'    => 'checkbox',
				'label'   => 'Habilitar pagamentos com cartão de crédito',
				'default' => 'no'
			),
			'title'       => array(
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Cartão de crédito'
			),
			'description' => array(
				'title'       => 'Descrição',
				'type'        => 'textarea',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Pague seu pedido com cartão de crédito'
			),

			'integration' => array(
				'title'       => 'Configurações da integração',
				'type'        => 'title',
				'description' => ''
			),

			'environment'  => array(
				'title'       => 'Ambiente',
				'type'        => 'select',
				'description' => 'Selecione o ambiente de integração',
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'test',
				'options'     => array(
					'TEST' => 'Testes',
					'LIVE' => 'Produção'
				)
			),
			'merchant_id'  => array(
				'title'             => 'Merchant ID',
				'type'              => 'text',
				'description'       => 'ID do lojista recebido pela maxiPago',
				'desc_tip'          => true,
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'merchant_key' => array(
				'title'             => 'Merchant Key',
				'type'              => 'text',
				'description'       => 'Chave do lojista recebido pela maxiPago',
				'desc_tip'          => true,
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'save_log'     => array(
				'title'       => 'Logs de depuração',
				'type'        => 'checkbox',
				'label'       => 'Ativa os logs',
				'default'     => 'no',
				'description' => 'Salve os logs de deputação'
			),

			'payment' => array(
				'title'       => 'Configurações do pagamento',
				'type'        => 'title',
				'description' => ''
			),

			'soft_descriptor' => array(
				'title'             => 'Soft descriptor',
				'type'              => 'text',
				'description'       => 'Nome exibido na fatura do cliente',
				'desc_tip'          => true,
				'default'           => '',
				'custom_attributes' => array(
					'maxlength' => '13'
				),
			),

			'installments'                  => array(
				'title'    => 'Número máximo de parcelas',
				'type'     => 'select',
				'desc_tip' => true,
				'class'    => 'wc-enhanced-select',
				'default'  => '1',
				'options'  => array(
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12'
				)
			),
			'interest_rate'                 => array(
				'title'       => 'Taxa de juros das parcelas',
				'type'        => 'text',
				'description' => 'Taxa de juros que será cobrado no parcelamento',
				'desc_tip'    => true,
				'default'     => '0'
			),
			'interest_rate_caculate_method' => array(
				'title'       => 'Método de cálculo dos juros',
				'type'        => 'select',
				'description' => 'Escolha o médoto de cálculo dos juros',
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => self::INTEREST_RATE_TYPE_SIMPLE,
				'options'     => array(
					self::INTEREST_RATE_TYPE_SIMPLE   => 'Simples',
					self::INTEREST_RATE_TYPE_COMPOUND => 'Composto',
					self::INTEREST_RATE_TYPE_PRICE    => 'Valor',
				)
			),
			'max_without_interest'          => array(
				'title'    => 'Número de parcelas sem cobrar juros',
				'type'     => 'select',
				'desc_tip' => true,
				'class'    => 'wc-enhanced-select',
				'default'  => '0',
				'options'  => array(
					'0'  => '0',
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12'
				)
			),
			'min_per_installments'          => array(
				'title'    => 'Valor mínimo por parcela',
				'type'     => 'text',
				'desc_tip' => true,
				'default'  => self::MIN_PER_INSTALLMENT
			),
			'processing_type'               => array(
				'title'    => 'Tipo de autorização',
				'type'     => 'select',
				'desc_tip' => true,
				'class'    => 'wc-enhanced-select',
				'default'  => self::PROCESSING_TYPE_SALE,
				'options'  => array(
					self::PROCESSING_TYPE_SALE => 'Autorização e captura',
					self::PROCESSING_TYPE_AUTH => 'Pré-autorização',
				)
			),
			'use_token'                     => array(
				'title'   => 'Armazenar token do cartão',
				'type'    => 'checkbox',
				'label'   => 'Salve um token do cartão para compras futuras',
				'default' => 'no'
			),
			'fraud_check'                   => array(
				'title'   => 'Análise de fraude',
				'type'    => 'checkbox',
				'label'   => 'Ativa análise de fraude',
				'default' => 'no'
			),
			'fraud_processor'               => array(
				'title'   => 'Processador da análise de fraude',
				'type'    => 'select',
				'options' => $this->api->get_fraud_processor(),
				'default' => '99'
			),
			'clearsale_app'                 => array(
				'title' => 'ClearSale App',
				'type'  => 'text',
				'label' => 'Somente se o Fraud Processor for ClearSale',
			),
			'auto_capture'                  => array(
				'title'   => 'Captura automática',
				'type'    => 'checkbox',
				'label'   => 'Habilita captura automática quando for para análise de fraude',
				'default' => 'no'
			),
			'auto_void'                     => array(
				'title'   => 'Cancelamento automático',
				'type'    => 'checkbox',
				'label'   => 'Habilita cancelamento automático quanto for para análise de fraude',
				'default' => 'yes'
			)
		);
	}

	public function is_available() {
		return parent::is_available() && ! empty( $this->merchant_key ) && ! empty( $this->merchant_id ) && $this->using_supported_currency();
	}

	public function using_supported_currency() {
		return in_array( get_woocommerce_currency(), $this->get_supported_currencies() );
	}

	public function get_supported_currencies() {
		return apply_filters(
			'woocommerce_maxipago_supported_currencies', array(
				'BRL',
				'USD',
			)
		);
	}

	public function admin_options() {
		include 'admin/views/html-admin-page.php';
	}

	public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		include_once WC_maxiPago::get_plugin_path() . 'templates/cc/payment-form.php';
	}

	public function process_payment( $order_id ) {
		return $this->api->sale_order( wc_get_order( $order_id ), $_POST );
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		return $this->api->refund_order( $order );
	}

	public function set_thankyou_page( $order_id ) {
		$order        = new WC_Order( $order_id );
		$order_status = $order->get_status();
		$request_data = get_post_meta( $order_id, '_maxipago_request_data', true );
		$result_data  = get_post_meta( $order_id, '_maxipago_result_data', true );
		if ( isset( $request_data['numberOfInstallments'] ) && ( isset( $result_data['creditCardScheme'] ) )
		     && ( 'processing' == $order_status || 'on-hold' == $order_status )
		) {
			wc_get_template(
				'cc/payment-instructions.php',
				array(
					'brand'        => $result_data['creditCardScheme'],
					'installments' => $request_data['numberOfInstallments']
				),
				'woocommerce/maxipago/',
				WC_maxiPago::get_templates_path()
			);
		}
	}

	public function set_email_instructions( WC_Order $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! in_array( $order->get_status(),
				array( 'processing', 'on-hold' ) ) || $this->id !== $order->get_payment_method() ) {
			return;
		}
		$request_data = get_post_meta( $order->get_id(), '_maxipago_request_data', true );
		$result_data  = get_post_meta( $order->get_id(), '_maxipago_result_data', true );
		if ( isset( $request_data['numberOfInstallments'] ) && ( isset( $result_data['creditCardScheme'] ) ) ) {
			if ( $plain_text ) {
				wc_get_template(
					'cc/emails/plain-instructions.php',
					array(
						'brand'        => $result_data['creditCardScheme'],
						'installments' => $request_data['numberOfInstallments']
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			} else {
				wc_get_template(
					'cc/emails/html-instructions.php',
					array(
						'brand'        => $result_data['creditCardScheme'],
						'installments' => $request_data['numberOfInstallments']
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			}
		}
	}

	public function process_cancel_order_meta_box_actions( $order ) {
		$this->api->refund_order( $order );
	}

	public function add_payment_method() {
		if ( empty ( $_POST['maxipago_card_number'] ) ) {
			wc_add_notice( 'Não foi possível adicionar esse cartão de crédito' );

			return false;
		}
		$cc_info = array(
			'cc_number' => wc_clean( $_POST['maxipago_card_number'] ),
			'cc_expiry' => wc_clean( $_POST['maxipago_card_expiry'] ),
			'cc_cvc'    => wc_clean( $_POST['maxipago_card_cvc'] ),
		);
		$token   = $this->api->save_token( $cc_info );
		if ( ! $token ) {
			wc_add_notice( 'Não foi possível adicionar esse cartão de crédito' );

			return false;
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	public function admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' === $hook && ( isset( $_GET['section'] ) && $this->id == strtolower( $_GET['section'] ) ) ) {
			wp_enqueue_script( 'modulo-woocommerce-admin-scripts',
				plugins_url( 'assets/js/admin-scripts.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ),
				WC_maxiPago::VERSION, true );
		}
	}

	public function tokenization_script() {
		wp_enqueue_script( 'woocommerce-tokenization-form',
			plugins_url( 'assets/js/tokenization-form.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ),
			WC_maxiPago::VERSION, true );
	}

	public function update_order_check_cancel_or_refund( $order_id ) {
		// When updating and order directly by status,
		// first, we must check if this order was paid with maxipago! cc
		$order_payment_method = get_post_meta( $order_id, '_payment_method', true );

		if ( $order_payment_method && $order_payment_method == self::ID ) {
			// Then, we must check if the order status was changed to cancelled or refunded
			$order  = wc_get_order( $order_id );
			$status = $order->get_status();

			if ( $status == 'cancelled' || $status == 'refunded' ) {
				// But, before we call the api, we must check if this order isn't already cancelled/refunded on maxiPago
				// and see when was the last call to avoid trying to cancel twice
				$order_already_refunded_or_cancelled = get_post_meta( $order_id, '_maxipago_refund_result_data', true );
				$order_canceled_time                 = get_post_meta( $order_id, '_order_canceled_time', true );

				if ( ! empty( $order_canceled_time ) && time() - $order_canceled_time < 60 ) {
					return;
				}

				if ( ! $order_already_refunded_or_cancelled ) {
					// In the database, the order status was updated to the new status 'cancelled' or 'refunded'.
					// Here, we change it locally to processing ....
					$order->set_status( 'processing' );

					// last time we called the api
					update_post_meta( $order_id, '_order_canceled_time', time() );

					// ... so we can call api->refund_order()
					if ( $this->api->refund_order( $order ) ) {
						$order->update_status( $status, 'Pedido cancelado/reembolsado com sucesso' );
					}
				}
			}
		}
	}

	public function update_order_fire_capture_action( $order_id ) {
		// When updating and order directly by status,
		// first, we must check if this order was paid with maxipago! cc
		$order_payment_method = get_post_meta( $order_id, '_payment_method', true );

		if ( $order_payment_method && $order_payment_method == self::ID ) {
			// Then, we must check if the order status was changed to processing
			$order = wc_get_order( $order_id );
			if ( $order->get_status() == 'processing' ) {
				// Before we call the api, we must check if this order isn't already captured on maxiPago
				// and see when was the last call to avoid trying to capture twice
				$order_already_captured = get_post_meta( $order_id, '_maxipago_capture_result_data', true );
				$order_capture_time     = get_post_meta( $order_id, '_order_capture_time', true );

				if ( ! empty( $order_capture_time ) && time() - $order_capture_time < 60 ) {
					return;
				}

				if ( ! $order_already_captured || $order_already_captured['responseMessage'] != 'CAPTURED' ) {
					// In the database, the order status was updated to the 'pending' status.
					// Here, we change it locally to on-hold ....
					$order->set_status( 'on-hold' );

					// last time we called the api
					update_post_meta( $order_id, '_order_capture_time', time() );

					// ... so we can call api->capture_order()
					if ( ! $this->api->capture_order( $order ) ) {
						$order->update_status( 'on-hold', 'Um erro ocorreu ao capturar o pagamento' );
					} else {
						$order->update_status( 'completed', 'Pedido capturado com sucesso' );
					}
				}
			}
		}
	}
}