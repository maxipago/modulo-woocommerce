<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_maxiPago_Online_Debit_Gateway extends WC_Payment_Gateway_CC {

	const ID = 'maxipago-online_debit';

	/** @var WC_maxiPago_Online_Debit_API */
	public $api;

	public $supports = array( 'products' );

	public $environment;
	public $merchant_id;
	public $merchant_key;
	public $invoice_prefix;
	public $save_log;
	public $banks;

	public function __construct() {

		$this->id                 = self::ID;
		$this->method_title       = 'Transferência online - maxiPago!';
		$this->method_description = 'Aceite pagamentos com transferência com a maxiPago!';
		$this->has_fields         = true;

		// Global Settings
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->environment    = $this->get_option( 'environment' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_key   = $this->get_option( 'merchant_key' );
		$this->invoice_prefix = 'WC-';
		$this->save_log       = $this->get_option( 'save_log' );

		// Online Debit Settings
		$this->banks = $this->get_option( 'banks' );

		$this->api = new WC_maxiPago_Online_Debit_API( $this );

		$this->init_form_fields();
		$this->init_settings();

		// Front actions
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'set_thankyou_page' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'set_email_instructions' ), 10, 3 );

		// Admin actions
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}

		// Checkout Scripts
		if ( is_checkout() || is_checkout_pay_page() ) {
			wp_enqueue_script( 'jquery-maskedinput',
				plugins_url( 'assets/js/jquery-maskedinput/jquery.maskedinput.js', plugin_dir_path( __FILE__ ) ),
				array( 'jquery' ), WC_maxiPago::VERSION, true );
			wp_enqueue_style( 'online_debit-checkout',
				plugins_url( 'assets/css/online_debit-checkout.css', plugin_dir_path( __FILE__ ) ),
				array(), WC_maxiPago::VERSION );
		}
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Habilita',
				'type'    => 'checkbox',
				'label'   => 'Habilita pagamentos por transferência',
				'default' => 'no'
			),
			'title'       => array(
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Transferência online'
			),
			'description' => array(
				'title'       => 'Descrição',
				'type'        => 'textarea',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Pague seu pedido com transferência'
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

			'banks' => array(
				'title'       => 'Bancos',
				'type'        => 'multiselect',
				'description' => 'Informe seu banco',
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-multiselect',
				'default'     => '',
				'options'     => $this->api->get_banks( 'online_debit' )
			),
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
			)
		);
	}

	public function admin_options() {
		include 'admin/views/html-admin-page.php';
	}

	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		$all_banks          = $this->api->get_banks( 'online_debit' );
		$online_debit_banks = $this->banks;
		$this->api->get_banks( 'online_debit' );
		$banks = array();
		foreach ( $online_debit_banks as $bank ) {
			if ( in_array( $bank, array_keys( $all_banks ) ) ) {
				$banks[ $bank ] = $all_banks[ $bank ];
			}
		}

		wc_get_template(
			'online_debit/payment-form.php',
			array(
				'online_debit_banks' => $banks,
			),
			'woocommerce/maxipago/',
			WC_maxiPago::get_templates_path()
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return $this->api->sale_order( $order, $_POST );
	}

	public function set_thankyou_page( $order_id ) {
		$order        = new WC_Order( $order_id );
		$order_status = $order->get_status();
		$result_data  = get_post_meta( $order_id, '_maxipago_result_data', true );
		if ( isset( $result_data['onlineDebitUrl'] ) && 'on-hold' == $order_status ) {
			wc_get_template(
				'online_debit/payment-instructions.php',
				array(
					'url' => $result_data['onlineDebitUrl'],
				),
				'woocommerce/maxipago/',
				WC_maxiPago::get_templates_path()
			);
		}
	}

	public function set_email_instructions( WC_Order $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! in_array( $order->get_status(),
				array( 'on-hold' ) ) || $this->id !== $order->get_payment_method() ) {
			return;
		}
		$result_data = get_post_meta( $order->get_id(), '_maxipago_result_data', true );
		if ( isset( $result_data['boletoUrl'] ) ) {
			if ( $plain_text ) {
				wc_get_template(
					'online_debit/emails/plain-instructions.php',
					array(
						'url' => $result_data['onlineDebitUrl'],
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			} else {
				wc_get_template(
					'online_debit/emails/html-instructions.php',
					array(
						'url' => $result_data['onlineDebitUrl'],
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			}
		}
	}
}