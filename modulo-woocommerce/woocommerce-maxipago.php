<?php
/**
 * Plugin Name: WooCommerce maxiPago!
 * Plugin URI: https://github.com/maxipago/modulo-woocommerce
 * Author: maxiPago!
 * Author URI: https://developers.maxipago.com/
 * Version: 1.0.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: modulo-woocommerce
 * Domain Path: /languages/
 * Requires PHP: 7.3
 * Requires at least: 4.0.0
 * Tested up to: 5.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/lib/maxiPago.php';

if ( ! class_exists( 'WC_maxiPago' ) ) {

	class WC_maxiPago {

		const VERSION = '1.0.0';

		protected static $instance = null;
		protected $log = false;

		public static function load_maxipago_class() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		private function __construct() {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			if ( function_exists( 'curl_exec' ) && class_exists( 'WC_Payment_Gateway' ) ) {
				$this->includes();
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
					array( $this, 'plugin_action_links' ) );
				$cc_settings = get_option( 'woocommerce_maxipago-cc_settings' );
				if ( is_array( $cc_settings ) && isset( $cc_settings['enabled'] ) && $cc_settings['enabled'] ) {
					add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
					add_action( 'woocommerce_order_action_maxipago_cc_capture_action',
						array( $this, 'process_order_capture_action' ) );
					add_action( 'woocommerce_payment_token_deleted', array( $this, 'delete_payment_method' ), 10, 2 );
				}

				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) . '_success' ),
					array( $this, 'check_ipn_response' ) );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) . '_failure' ),
					array( $this, 'check_ipn_response' ) );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) . '_notifications' ),
					array( $this, 'check_ipn_response' ) );

				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) . '_cron' ),
					array( $this, 'execute_crons' ) );

				add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
				add_action( 'admin_notices', array( $this, 'show_ipn_notices' ) );

				add_action( 'init', array( $this, 'session_start' ) );
				add_action( 'woocommerce_after_checkout_form', array( $this, 'add_checkout_scripts' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'notify_dependencies_missing' ) );
			}
		}

		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'modulo-woocommerce' );
			load_textdomain( 'modulo-woocommerce',
				trailingslashit( WP_LANG_DIR ) . 'modulo-woocommerce/modulo-woocommerce-' . $locale . '.mo' );
			load_plugin_textdomain( 'modulo-woocommerce', false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		public function add_gateway( $methods ) {
			$methods[] = 'WC_maxiPago_CC_Gateway';
			$methods[] = 'WC_maxiPago_DC_Gateway';
			$methods[] = 'WC_maxiPago_Boleto_Gateway';
			$methods[] = 'WC_maxiPago_Online_Debit_Gateway';

			return $methods;
		}

		private function includes() {
			include_once 'includes/class-wc-maxipago-api.php';
			include_once 'includes/class-wc-maxipago-cc-api.php';
			include_once 'includes/class-wc-maxipago-cc-gateway.php';
			include_once 'includes/class-wc-maxipago-dc-api.php';
			include_once 'includes/class-wc-maxipago-dc-gateway.php';
			include_once 'includes/class-wc-maxipago-boleto-api.php';
			include_once 'includes/class-wc-maxipago-boleto-gateway.php';
			include_once 'includes/class-wc-maxipago-online_debit-api.php';
			include_once 'includes/class-wc-maxipago-online_debit-gateway.php';
			include_once 'includes/class-wc-maxipago-cron.php';
		}

		public function notify_dependencies_missing() {
			if ( ! function_exists( 'curl_exec' ) ) {
				include_once 'includes/admin/views/html-notice-missing-curl.php';
			}
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				include_once 'includes/admin/views/html-notice-missing-woocommerce.php';
			}
		}

		public function plugin_action_links( $links ) {
			$plugin_links   = array();
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_cc_gateway' ) ) . '">' . __( 'Credit Card Settings',
					'modulo-woocommerce' ) . '</a>';
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_dc_gateway' ) ) . '">' . __( 'Debit Card Settings',
					'modulo-woocommerce' ) . '</a>';
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_boleto_gateway' ) ) . '">' . __( 'Boleto Settings',
					'modulo-woocommerce' ) . '</a>';
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_online_debit_gateway' ) ) . '">' . __( 'Online Debit Settings',
					'modulo-woocommerce' ) . '</a>';

			return array_merge( $plugin_links, $links );
		}

		public function add_order_actions( $actions ) {
			$actions['maxipago_cc_capture_action'] = __( 'Capture Payment [maxiPago!]', 'modulo-woocommerce' );

			return $actions;
		}

		public function process_order_capture_action( $order ) {
			$api = new WC_maxiPago_CC_API();
			$api->capture_order( $order );
		}

		public function delete_payment_method( $token_id, $object_token ) {
			$api = new WC_maxiPago_CC_API();
			$api->delete_token( $object_token );
		}

		public function show_admin_notices() {
			include_once 'includes/admin/views/html-admin-notices.php';
		}

		public function show_ipn_notices() {
			$section = isset( $_GET['section'] ) ? $_GET['section'] : '';
			if ( strpos( $section, 'maxipago' ) !== false ) {
				include_once 'includes/admin/views/html-ipn-urls.php';
			}
		}

		private function get_order( $order_id ) {
			$order = new WC_Order( $order_id );

			if ( $order->get_id() == 0 ) {
				$post = $this->get_post_by_meta( $order_id );

				if ( $post ) {
					$order = new WC_Order( $post->ID );
				}
			}

			return $order;
		}

		private function get_post_by_meta( $order_id ) {
			$args = array(
				'post_status' => get_post_stati(),
				'post_type'   => 'shop_order',
				'meta_query'  => array(
					array(
						'key'   => 'referenceNum',
						'value' => $order_id
					)
				)
			);

			$posts = get_posts( $args );

			if ( count( $posts ) > 0 ) {
				return $posts[0];
			}

			return null;
		}

		/**
		 * Check for valid server callback
		 * @access public
		 * @return array|false
		 **/
		function check_ipn_response() {
			$redirect_url  = home_url( '/' );
			$body          = file_get_contents( 'php://input' );
			$post_is_valid = isset( $_POST ) && ! empty( $_POST );
			if ( $body || $post_is_valid ) {
				header( 'HTTP/1.1 200 OK' );
				try {
					$this->log_ipn_response( $body, $post_is_valid );

					$order_id = isset( $_POST['hp_orderid'] ) ? $_POST['hp_orderid'] : null;
					if ( ! $order_id ) {
						if ( $body ) {
							$xml = simplexml_load_string( $body );
							if ( property_exists( $xml, 'orderID' ) ) {
								$order_id = (string) $xml->orderID;
							}
						}
					}

					if ( $order_id ) {

						$this->log->add( 'maxipago_api', 'Order ID ' . $order_id );
						$posts = get_posts(
							array(
								'post_status' => get_post_stati(),
								'post_type'   => 'shop_order',
								'meta_query'  => array(
									array(
										'key'   => 'orderID',
										'value' => $order_id
									)
								)
							)
						);

						foreach ( $posts as $post ) {
							$order = $this->get_order( $post->ID );

							$redirect_url = $order->get_view_order_url();
							$result_data  = get_post_meta( $order->get_id(), '_maxipago_result_data', true );

							$this->log->add( 'maxipago_api', 'result data ' . $result_data );
							if ( $result_data ) {

								$method_id = $order->get_payment_method();

								$settings = get_option( 'woocommerce_' . $method_id . '_settings' );
								unset( $this->log );
								if ( $settings['save_log'] && class_exists( 'WC_Logger' ) == 'yes' ) {
									$this->log = new WC_Logger();
								}

								if ( $this->log ) {
									$this->log->add( 'maxipago_api',
										'[maxipago - IPN] Update Order Status: ' . $order->get_id() );
								}

								$client = new maxiPago();
								$client->setCredentials( $settings['merchant_id'], $settings['merchant_key'] );
								$client->setEnvironment( $settings['environment'] );

								$params = array(
									'orderID' => $result_data['orderID']
								);
								$client->pullReport( $params );
								$response = $client->getReportResult();
								if ( $this->log ) {
									$this->log->add( 'maxipago_api', '------------- pullReport -------------' );
									$this->log->add( 'maxipago_api', $client->xmlRequest );
									$this->log->add( 'maxipago_api', $client->xmlResponse );
								}
								$state = isset( $response[0]['transactionState'] ) ? $response[0]['transactionState'] : null;
								if ( $state ) {
									$cron = new WC_maxiPago_Cron();
									$cron->set_order_status( $order->get_id(), $state );

									$valid_statuses = array(
										WC_maxiPago_Cron::$transaction_states['Captured'],
										WC_maxiPago_Cron::$transaction_states['Paid'],
										WC_maxiPago_Cron::$transaction_states['Boleto Overpaid']
									);

									if ( in_array( $state, $valid_statuses ) ) {
										update_post_meta( $order->get_id(), '_maxipago_capture_result_data',
											$response );
										update_post_meta( $order->get_id(), 'responseMessage', 'CAPTURED' );
									}

									$firstResponse = $response[0];
									foreach ( $firstResponse as $key => $value ) {
										update_post_meta( $order->get_id(), $key, $value );
									}

									if ( $this->log ) {
										$this->log->add( 'maxipago_api',
											'[maxipago - IPN] Update Order Status to: ' . $state );
									}

								}
							}
						}
					}


				} catch ( Exception $e ) {
					if ( $this->log ) {
						$this->log->add( 'maxipago_api',
							'[maxipago - IPN]maxiPago! Request Failure! ' . $e->getMessage() );
					}

					wp_redirect( home_url( '/' ) );
				}


			}

			wp_redirect( $redirect_url );

			return [];
		}

		private function log_ipn_response( $body, $post_is_valid ) {
			if ( ! $this->log ) {
				$this->log = new WC_Logger();
			}

			if ( $body ) {
				$this->log->add( 'maxipago_api', 'Raw Body: ' . $body );
			}

			if ( $post_is_valid ) {
				$this->log->add( 'maxipago_api', '$_POST: ' . json_encode( $_POST ) );
			}
		}

		public function add_checkout_scripts() {
			$settings   = get_option( 'woocommerce_maxipago-cc_settings' );
			$fraudCheck = isset( $settings['fraud_check'] ) ? $settings['fraud_check'] : null;
			$script     = '';
			if ( $fraudCheck ) {
				$fraudProcessor = isset( $settings['fraud_processor'] ) ? $settings['fraud_processor'] : null;
				$clearSaleApp   = isset( $settings['clearsale_app'] ) ? $settings['clearsale_app'] : null;

				if ( $fraudProcessor == '98' && $clearSaleApp ) {
					$script .= '<script>';
					$script .= '
                    (function (a, b, c, d, e, f, g){
                        a[\'CsdpObject\'] = e; a[e] = a[e] || function () {
                            (a[e].q = a[e].q || []).push(arguments)
                        },
                        a[e].l = 1 * new Date(); f = b.createElement(c),
                        g = b.getElementsByTagName(c)[0]; f.async = 1; f.src = d;
                        g.parentNode.insertBefore(f, g)
                    })
                    (window, document, \'script\', \'//device.clearsale.com.br/p/fp.js\',\'csdp\');' . "\n";

					$script .= 'csdp(\'app\', \'' . $clearSaleApp . '\');' . "\n";
					$script .= 'csdp(\'sessionid\', \'' . session_id() . '\');';
					$script .= '</script>';
				} else {
					if ( $fraudProcessor == '99' ) {
						$sessionId      = session_id();
						$merchantId     = isset( $settings['merchant_id'] ) ? $settings['merchant_id'] : null;
						$merchantSecret = isset( $settings['merchant_secret'] ) ? $settings['merchant_secret'] : null;
						$hash           = hash_hmac( 'md5', $merchantId . '*' . $sessionId, $merchantSecret );
						$url            = "https://testauthentication.maxipago.net/redirection_service/logo?m={$merchantId}&s={$sessionId}&h={$hash}";
						$script         .= '<iframe width="1" height="1" frameborder="0" src="' . $url . '"></iframe>';
					}
				}
			}
			echo $script;
		}

		public function session_start() {
			if ( ! session_id() ) {
				session_start();
			}
		}

		public static function get_plugin_path() {
			return plugin_dir_path( __FILE__ );
		}

		public static function get_main_file() {
			return __FILE__;
		}

		public function execute_crons() {
			do_action( 'maxipago_update_cc_orders' );
			do_action( 'maxipago_update_dc_orders' );
			do_action( 'maxipago_update_boleto_orders' );
			do_action( 'maxipago_update_online_debit_orders' );

			$paragraph = '<p>' . __( 'maxiPago! cron\'s max', 'modulo-woocommerce' ) . '</p>';
			$link      = '<p><a href="' . home_url( '/' ) . '">' . __( 'Click here', 'modulo-woocommerce' ) . '</a>';
			$text      = __( ' to return to shop.', 'modulo-woocommerce' ) . '</p>';
			wp_die( $paragraph . $link . $text );
		}
	}

	add_action( 'plugins_loaded', array( 'WC_maxiPago', 'load_maxipago_class' ) );

}
