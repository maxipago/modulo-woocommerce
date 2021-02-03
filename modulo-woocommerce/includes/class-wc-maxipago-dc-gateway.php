<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_maxiPago_DC_Gateway extends WC_Payment_Gateway_CC {

	const ID = 'maxipago-dc';

	/** @var WC_maxiPago_DC_API */
	public $api;

	public $environment;
	public $merchant_id;
	public $merchant_key;
	public $invoice_prefix;
	public $save_log;
	public $soft_descriptor;
	public $mpi_processor;
	public $failure_action;

	public $supports = array( 'products', 'refunds' );

	public function __construct() {

		$this->id                 = self::ID;
		$this->method_title       = 'Cartão de débito - maxiPago!';
		$this->method_description = 'Aceite cartões de débito com a maxiPago!';
		$this->has_fields         = true;

		// Global Settings
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->environment    = $this->get_option( 'environment' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->merchant_key   = $this->get_option( 'merchant_key' );
		$this->invoice_prefix = 'WC-';
		$this->save_log       = $this->get_option( 'save_log' );

		// DC Settings
		$this->soft_descriptor = $this->get_option( 'soft_descriptor' );
		$this->api             = new WC_maxiPago_DC_API( $this );

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
		}
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Habilita',
				'type'    => 'checkbox',
				'label'   => 'Habilita pagamentos por cartão de débito',
				'default' => 'no'
			),
			'title'       => array(
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Cartão de débito'
			),
			'description' => array(
				'title'       => 'Descrição',
				'type'        => 'textarea',
				'description' => 'Exibido na hora do pagamento',
				'desc_tip'    => true,
				'default'     => 'Pague seu pedido com cartão de débito'
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

			'mpi_processor' => array(
				'title'       => 'MPI',
				'type'        => 'select',
				'description' => '',
				'class'       => 'wc-enhanced-select',
				'default'     => '',
				'options'     => $this->api->get_mpi_processors()
			),

			'failure_action' => array(
				'title'       => 'Ação em caso de falha',
				'type'        => 'select',
				'description' => '',
				'class'       => 'wc-enhanced-select',
				'default'     => '',
				'options'     => $this->api->get_mpi_action()
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
		include_once WC_maxiPago::get_plugin_path() . 'templates/dc/payment-form.php';
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return $this->api->sale_order( $order, $_POST );
	}

	public function set_thankyou_page( $order_id ) {
		$order        = new WC_Order( $order_id );
		$order_status = $order->get_status();
		$result_data  = get_post_meta( $order_id, '_maxipago_result_data', true );

		if ( isset( $result_data['authenticationURL'] ) && 'on-hold' == $order_status ) {
			wc_get_template(
				'dc/payment-instructions.php',
				array(
					'url' => $result_data['authenticationURL']
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

		$result_data = get_post_meta( $order->get_id(), '_maxipago_result_data', true );

		if ( isset( $result_data['creditCardScheme'] ) ) {
			if ( $plain_text ) {
				wc_get_template(
					'dc/emails/plain-instructions.php',
					array(
						'url' => $result_data['authenticationURL']
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			} else {
				wc_get_template(
					'dc/emails/html-instructions.php',
					array(
						'url' => $result_data['authenticationURL']
					),
					'woocommerce/maxipago/',
					WC_maxiPago::get_templates_path()
				);
			}
		}
	}

	public function admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' === $hook && ( isset( $_GET['section'] ) && $this->id == strtolower( $_GET['section'] ) ) ) {
			wp_enqueue_script( 'modulo-woocommerce-admin-scripts',
				plugins_url( 'assets/js/admin-scripts.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ),
				WC_maxiPago::VERSION, true );
		}
	}

}