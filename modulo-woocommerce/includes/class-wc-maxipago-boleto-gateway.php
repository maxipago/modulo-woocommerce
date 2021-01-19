<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_maxiPago_Boleto_Gateway extends WC_Payment_Gateway_CC {

	const ID = 'maxipago-boleto';

	/** @var WC_maxiPago_Boleto_API */
	public $api;

	public $supports = array( 'products' );

	public $environment;
	public $merchant_id;
	public $merchant_key;
	public $invoice_prefix;
	public $save_log;
	public $bank;
	public $days_to_expire;
	public $instructions;

	public function __construct() {

		$this->id                 = self::ID;
		$this->method_title       = 'Boleto bancário - maxiPago!';
		$this->method_description = 'Aceite pagamentos por boleto com a maxiPago!';
		$this->has_fields         = true;

		// Global Settings
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->environment    = $this->get_option( 'environment' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_key   = $this->get_option( 'merchant_key' );
		$this->invoice_prefix = 'WC-';
		$this->save_log       = $this->get_option( 'save_log' );

		// Boleto Settings
		$this->bank           = $this->get_option( 'bank' );
		$this->days_to_expire = $this->get_option( 'days_to_expire' );
		$this->instructions   = $this->get_option( 'instructions' );

		$this->api = new WC_maxiPago_Boleto_API( $this );

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
		}
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Habilita',
				'type'    => 'checkbox',
				'label'   => 'Habilita pagamentos por boleto',
				'default' => 'no'
			),
			'title'       => array(
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Boleto'
			),
			'description' => array(
				'title'       => 'Descrição',
				'type'        => 'textarea',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Pague seu pedido com boleto bancário'
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

			'bank'           => array(
				'title'       => 'Banco emissor do boleto',
				'type'        => 'select',
				'description' => 'Informe o banco emissor do boleto',
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => '',
				'options'     => $this->api->get_banks()
			),
			'days_to_expire' => array(
				'title'       => 'Dias para expirar o boleto',
				'type'        => 'text',
				'description' => 'Informe em quantos dias o boleto irá expirar',
				'desc_tip'    => true,
				'default'     => '5',
			),
			'instructions'   => array(
				'title'       => 'Instruções',
				'type'        => 'textarea',
				'description' => 'Informe as instruções de pagamento do boleto',
				'desc_tip'    => true,
				'default'     => '',
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
		wc_get_template(
			'boleto/payment-form.php',
			array(),
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
		if ( isset( $result_data['boletoUrl'] ) && 'on-hold' == $order_status ) {
			wc_get_template(
				'boleto/payment-instructions.php',
				array(
					'url' => $result_data['boletoUrl'],
				),
				'woocommerce/maxipago/',
				WC_maxiPago::get_templates_path()
			);

			add_post_meta( $order_id, 'maxipago_boleto_url', $result_data['boletoUrl'] );
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
					'boleto/emails/plain-instructions.php',
					array(
						'url' => $result_data['boletoUrl'],
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			} else {
				wc_get_template(
					'boleto/emails/html-instructions.php',
					array(
						'url' => $result_data['boletoUrl'],
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			}
		}
	}

}