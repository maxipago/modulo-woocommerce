<?php
/**
 * Plugin Name: WooCommerce maxiPago!
 * Plugin URI: https://github.com/maxipago/woocommerce-maxipago
 * Description: <strong>Oficial</strong> Plugin for maxiPago! Smart Payments.
 * Author: maxiPago! Smart Payments
 * Author URI: http://www.maxipago.com/
 * Version: 0.2.1
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-maxipago
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit;
}

include_once 'includes/lib/maxiPago.php';

if (!class_exists('WC_maxiPago')) :

    class WC_maxiPago {

        const VERSION = '0.2.3';

        protected static $instance = null;

        public static function load_maxipago_class() {
            if (null == self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('init', array($this, 'load_plugin_textdomain'));
            if (function_exists('curl_exec') && class_exists('WC_Payment_Gateway')) {
                $this->includes();
                add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                $cc_settings = get_option('woocommerce_maxipago-cc_settings');
                if (is_array($cc_settings) && isset($cc_settings['enabled']) && $cc_settings['enabled']) {
                    add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
                    add_action('woocommerce_order_action_maxipago_cc_capture_action', array($this, 'process_order_capture_action'));
                    add_action('woocommerce_payment_token_deleted', array($this, 'delete_payment_method'), 10, 2);
                }
                add_action('admin_notices', array($this, 'show_admin_notices'));
            } else {
                add_action('admin_notices', array($this, 'notify_dependencies_missing'));
            }
        }

        public static function get_templates_path() {
            return plugin_dir_path(__FILE__) . 'templates/';
        }

        public function load_plugin_textdomain() {
            $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-maxipago');
            load_textdomain('woocommerce-maxipago', trailingslashit(WP_LANG_DIR) . 'woocommerce-maxipago/woocommerce-maxipago-' . $locale . '.mo');
            load_plugin_textdomain('woocommerce-maxipago', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function add_gateway($methods) {
            $methods[] = 'WC_maxiPago_CC_Gateway';
            $methods[] = 'WC_maxiPago_Ticket_Gateway';
            $methods[] = 'WC_maxiPago_TEF_Gateway';
            return $methods;
        }

        private function includes() {
            include_once 'includes/class-wc-maxipago-api.php';
            include_once 'includes/class-wc-maxipago-cc-api.php';
            include_once 'includes/class-wc-maxipago-cc-gateway.php';
            include_once 'includes/class-wc-maxipago-ticket-api.php';
            include_once 'includes/class-wc-maxipago-ticket-gateway.php';
            include_once 'includes/class-wc-maxipago-tef-api.php';
            include_once 'includes/class-wc-maxipago-tef-gateway.php';
            include_once 'includes/class-wc-maxipago-cron.php';
        }

        public function notify_dependencies_missing() {
            if (!function_exists('curl_exec')) {
                include_once 'includes/admin/views/html-notice-missing-curl.php';
            }
            if (!class_exists('WC_Payment_Gateway')) {
                include_once 'includes/admin/views/html-notice-missing-woocommerce.php';
            }
        }

        public function plugin_action_links($links) {
            $plugin_links = array();
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_cc_gateway')) . '">' . __('Credit Card Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_ticket_gateway')) . '">' . __('Ticket Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_tef_gateway')) . '">' . __('TEF Settings', 'woocommerce-maxipago') . '</a>';
            return array_merge($plugin_links, $links);
        }

        public function add_order_actions($actions) {
            $actions['maxipago_cc_capture_action'] = __('Capture Payment [maxiPago!]', 'woocommerce-maxipago');
            return $actions;
        }

        public function process_order_capture_action($order) {
            $api = new WC_maxiPago_CC_API();
            $api->capture_order($order);
        }

        public function delete_payment_method($token_id,$object_token) {
            $api = new WC_maxiPago_CC_API();
            $api->delete_token($object_token);
        }

        public function show_admin_notices() {
            if ($data = get_transient('maxipago_admin_notice')) {
                ?>
                <div class="updated <?php echo $data[1] ?> is-dismissible">
                    <p><?php echo $data[0] ?></p>
                </div>
                <?php
                delete_transient('maxipago_admin_notice');
            }
        }
        
        public static function get_plugin_path() {
            return plugin_dir_path( __FILE__ );
        }

        public static function get_main_file() {
            return __FILE__;
        }

    }

    add_action('plugins_loaded', array('WC_maxiPago', 'load_maxipago_class'));

endif;