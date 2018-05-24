<?php
/**
 * Plugin Name: WooCommerce maxiPago!
 * Plugin URI: https://github.com/maxipago/woocommerce-maxipago
 * Description: <strong>Oficial</strong> Plugin for maxiPago! Smart Payments.
 * Author: maxiPago! Smart Payments
 * Author URI: http://www.maxipago.com/
 * Version: 0.3.9
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-maxipago
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit;
}

include_once 'includes/lib/maxiPago.php';

if (!class_exists('WC_maxiPago')) {

    class WC_maxiPago
    {

        const VERSION = '0.3.9';

        protected static $instance = null;
        protected $log = false;

        public static function load_maxipago_class()
        {
            if (null == self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        private function __construct()
        {
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

                add_action('add_meta_boxes',array($this, 'add_product_split_payment_metabox'));
                add_action('save_post', array($this, 'save_product_split_payment_meta'), 10, 3);

                add_action('woocommerce_api_' . strtolower(get_class($this) . '_success'), array($this, 'check_ipn_response'));
                add_action('woocommerce_api_' . strtolower(get_class($this) . '_failure'), array($this, 'check_ipn_response'));
                add_action('woocommerce_api_' . strtolower(get_class($this) . '_notifications'), array($this, 'check_ipn_response'));

                add_action('admin_notices', array($this, 'show_admin_notices'));
                add_action('admin_notices', array($this, 'show_ipn_notices'));

                add_action('init', array($this, 'session_start'));
                add_action('woocommerce_after_checkout_form', array($this, 'add_checkout_scripts'));
                add_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 10, 3);

                add_action('restrict_manage_posts', array($this, 'seller_filter_selector'));

                add_action('posts_where', array($this, 'seller_filter_order'));
                add_filter('manage_edit-shop_order_columns', array($this, 'show_shop_order_seller_column'));
                add_filter('manage_shop_order_posts_custom_column', array($this, 'show_shop_order_seller_data'), 10, 2);

                add_action('posts_where', array($this, 'seller_filter_product'));
                add_filter('manage_edit-product_columns', array($this, 'show_product_seller_column'));
                add_filter('manage_product_posts_custom_column', array($this, 'show_product_seller_data'), 10, 2);
            } else {
                add_action('admin_notices', array($this, 'notify_dependencies_missing'));
            }
        }

        public static function get_templates_path()
        {
            return plugin_dir_path(__FILE__) . 'templates/';
        }

        public function load_plugin_textdomain()
        {
            $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-maxipago');
            load_textdomain('woocommerce-maxipago', trailingslashit(WP_LANG_DIR) . 'woocommerce-maxipago/woocommerce-maxipago-' . $locale . '.mo');
            load_plugin_textdomain('woocommerce-maxipago', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function add_gateway($methods)
        {
            $methods[] = 'WC_maxiPago_CC_Gateway';
            $methods[] = 'WC_maxiPago_DC_Gateway';
            $methods[] = 'WC_maxiPago_Ticket_Gateway';
            $methods[] = 'WC_maxiPago_TEF_Gateway';
            $methods[] = 'WC_maxiPago_Redepay_Gateway';
            return $methods;
        }

        private function includes()
        {
            include_once 'includes/class-wc-maxipago-api.php';
            include_once 'includes/class-wc-maxipago-cc-api.php';
            include_once 'includes/class-wc-maxipago-cc-gateway.php';
            include_once 'includes/class-wc-maxipago-dc-api.php';
            include_once 'includes/class-wc-maxipago-dc-gateway.php';
            include_once 'includes/class-wc-maxipago-ticket-api.php';
            include_once 'includes/class-wc-maxipago-ticket-gateway.php';
            include_once 'includes/class-wc-maxipago-tef-api.php';
            include_once 'includes/class-wc-maxipago-tef-gateway.php';
            include_once 'includes/class-wc-maxipago-redepay-api.php';
            include_once 'includes/class-wc-maxipago-redepay-gateway.php';
            include_once 'includes/class-wc-maxipago-cron.php';
        }

        public function notify_dependencies_missing()
        {
            if (!function_exists('curl_exec')) {
                include_once 'includes/admin/views/html-notice-missing-curl.php';
            }
            if (!class_exists('WC_Payment_Gateway')) {
                include_once 'includes/admin/views/html-notice-missing-woocommerce.php';
            }
        }

        public function plugin_action_links($links)
        {
            $plugin_links = array();
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_cc_gateway')) . '">' . __('Credit Card Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_dc_gateway')) . '">' . __('Debit Card Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_ticket_gateway')) . '">' . __('Ticket Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_tef_gateway')) . '">' . __('TEF Settings', 'woocommerce-maxipago') . '</a>';
            $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_maxipago_redepay_gateway')) . '">' . __('RedePay Settings', 'woocommerce-maxipago') . '</a>';
            return array_merge($plugin_links, $links);
        }

        public function add_order_actions($actions)
        {
            $actions['maxipago_cc_capture_action'] = __('Capture Payment [maxiPago!]', 'woocommerce-maxipago');
            return $actions;
        }

        public function process_order_capture_action($order)
        {
            $api = new WC_maxiPago_CC_API();
            $api->capture_order($order);
        }

        public function delete_payment_method($token_id, $object_token)
        {
            $api = new WC_maxiPago_CC_API();
            $api->delete_token($object_token);
        }

        public function show_admin_notices()
        {
            include_once 'includes/admin/views/html-admin-notices.php';
        }

        public function show_ipn_notices()
        {
            $section = isset($_GET['section']) ? $_GET['section'] : '';
            if (strpos($section, 'maxipago') !== false) {
                include_once 'includes/admin/views/html-ipn-urls.php';
            }
        }

        private function get_order($order_id)
        {
            $order = new WC_Order($order_id);

            if($order->get_id() == 0) {
                $post = $this->get_post_by_meta($order_id);

                if($post)
                    $order = new WC_Order($post->ID);
            }

            return $order;
        }

        private function get_post_by_meta($order_id)
        {
            $args = array(
                'post_status' => get_post_stati(),
                'post_type' => 'shop_order',
                'meta_query' => array (
                    array(
                        'key' => 'referenceNum',
                        'value' => $order_id
                    )
                )
            );

            $posts = get_posts($args);

            if(count($posts) > 0)
                return $posts[0];

            return null;
        }

        /**
         * Check for valid server callback
         *
         * @access public
         * @return array|false
         **/
        function check_ipn_response()
        {
            $redirect_url = home_url('/');
            $body = file_get_contents('php://input');
            $post_is_valid = isset($_POST) && !empty($_POST);
            if ($body || $post_is_valid) {
                header('HTTP/1.1 200 OK');
                try {
                    $this->log_ipn_response($body, $post_is_valid);

                    $order_id = isset($_POST['hp_orderid']) ? $_POST['hp_orderid'] : null;
                    if (!$order_id) {
                        if ($body) {
                            $xml = simplexml_load_string($body);
                            if (property_exists($xml, 'orderID')) {
                                $order_id = (string) $xml->orderID;
                            }
                        }
                    }

                    if ($order_id) {

                        $this->log->add('maxipago_api', 'Order ID ' . $order_id);
                        $posts = get_posts(
                            array(
                                'post_status' => get_post_stati(),
                                'post_type' => 'shop_order',
                                'meta_query' => array (
                                    array (
                                        'key' => 'orderID',
                                        'value' => $order_id
                                    )
                                )
                            )
                        );

                        foreach($posts as $post) {
                            $order = $this->get_order($post->ID);

                            $redirect_url = $order->get_view_order_url();
                            $result_data = get_post_meta($order->get_id(), '_maxipago_result_data', true);

                            $this->log->add('maxipago_api', 'result data ' . $result_data);
                            if ($result_data) {

                                $method_id = $order->get_payment_method();

                                $settings = get_option('woocommerce_' . $method_id . '_settings');
                                unset($this->log);
                                if ($settings['save_log'] && class_exists('WC_Logger') == 'yes') {
                                    $this->log = new WC_Logger();
                                }

                                if ($this->log) {
                                    $this->log->add('maxipago_api', '[maxipago - IPN] Update Order Status: ' . $order->get_id());
                                }

                                $client = new maxiPago();
                                $client->setCredentials($settings['merchant_id'], $settings['merchant_key']);
                                $client->setEnvironment($settings['environment']);

                                $params = array(
                                    'orderID' => $result_data['orderID']
                                );
                                $client->pullReport($params);
                                $response = $client->getReportResult();
                                if ($this->log) {
                                    $this->log->add('maxipago_api', '------------- pullReport -------------');
                                    $this->log->add('maxipago_api', $client->xmlRequest);
                                    $this->log->add('maxipago_api', $client->xmlResponse);
                                }
                                $state = isset($response[0]['transactionState']) ? $response[0]['transactionState'] : null;
                                if ($state) {
                                    $cron = new WC_maxiPago_Cron();
                                    $cron->set_order_status($order->get_id(), $state);

                                    if ($this->log)
                                        $this->log->add('maxipago_api', '[maxipago - IPN] Update Order Status to: ' . $state);

                                }
                            }
                        }
                    }


                } catch (Exception $e) {
                    if ($this->log) {
                        $this->log->add('maxipago_api', '[maxipago - IPN]maxiPago! Request Failure! ' . $e->getMessage());
                    }

                    wp_redirect(home_url('/'));
                }


            }

            wp_redirect($redirect_url);
        }

        private function log_ipn_response($body, $post_is_valid)
        {
            if(!$this->log)
                $this->log = new WC_Logger();

            if($body)
                $this->log->add('maxipago_api', 'Raw Body: ' . $body);

            if($post_is_valid)
                $this->log->add('maxipago_api', '$_POST: ' . json_encode($_POST));
        }

        public function add_checkout_scripts()
        {
            $settings = get_option('woocommerce_maxipago-cc_settings');
            $fraudCheck = isset($settings['fraud_check']) ? $settings['fraud_check'] : null;
            $script = '';
            if ($fraudCheck) {
                $fraudProcessor = isset($settings['fraud_processor']) ? $settings['fraud_processor'] : null;
                $clearSaleApp = isset($settings['clearsale_app']) ? $settings['clearsale_app'] : null;

                if ($fraudProcessor == '98' && $clearSaleApp) {
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
                } else if ($fraudProcessor == '99') {
                    $sessionId = session_id();
                    $merchantId = isset($settings['merchant_id']) ? $settings['merchant_id'] : null;
                    $merchantSecret = isset($settings['merchant_secret']) ? $settings['merchant_secret'] : null;
                    $hash = hash_hmac('md5', $merchantId . '*' . $sessionId, $merchantSecret);
                    $url = "https://testauthentication.maxipago.net/redirection_service/logo?m={$merchantId}&s={$sessionId}&h={$hash}";
                    $script .= '<iframe width="1" height="1" frameborder="0" src="' . $url . '"></iframe>';
                }
           }
           echo $script;
        }

        public function session_start()
        {
            if(!session_id()) {
                session_start();
            }
        }

        public static function get_plugin_path()
        {
            return plugin_dir_path(__FILE__);
        }

        public static function get_main_file()
        {
            return __FILE__;
        }

        private function is_split_payment_active()
        {
            $splitPayment = get_option('woocommerce_maxipago-cc_settings')['split_payment'];

            return (isset($splitPayment) && $splitPayment == 'yes');
        }

        private function get_seller_name($seller_merchant_id)
        {
            $sellers = get_option('sellers');

            foreach($sellers as $seller)
                if($seller['seller_merchant_id'] == $seller_merchant_id)
                    return $seller['seller_name'];

            return '';
        }

        public function add_product_split_payment_metabox($post_type)
        {
            if($post_type == 'product')
            {
                if($this->is_split_payment_active())
                {
                    $mb_id = __('maxiPago! Split Payment', 'woocommerce-maxipago');
                    $mb_callback = array($this, 'seller_selector');
                    add_meta_box('seller', $mb_id, $mb_callback, null, 'side');
                }
            }
        }

        public function seller_selector()
        {
            include_once 'includes/admin/views/html-product-seller.php';
        }

        public function save_product_split_payment_meta($post_id, $post, $update)
        {
            if(get_post_type($post) != 'product')
                return;

            if(isset($_POST['merchant_id']))
                update_post_meta($post_id, 'seller_id',$_POST['merchant_id']);
        }
        
        private function product_is_subscription($product)
        {
            if(class_exists('WC_Product_Subscription') && class_exists('WC_Product_Variable_Subscription') && class_exists('WC_Product_Subscription_Variation'))
                return ($product instanceof WC_Product_Subscription) || ($product instanceof WC_Product_Variable_Subscription) || ($product instanceof WC_Product_Subscription_Variation);

            return false;
        }

        public function add_to_cart_validation($valid, $product_id, $quantity)
        {
            $productToAdd = wc_get_product($product_id);

            if(function_exists('wcs_is_subscription'))
                wcs_is_subscription($productToAdd);

            if($this->product_is_subscription($productToAdd))
            {
                global $woocommerce;
                $cartItems = $woocommerce->cart->get_cart();

                foreach($cartItems as $item => $values)
                {
                    $cartProduct =  wc_get_product( $values['data']->get_id() );

                    if($this->product_is_subscription($cartProduct))
                    {
                        if($productToAdd->get_id() == $cartProduct->get_id())
                            return true;

                        wc_add_notice(__('Only one subscription allowed per cart!', 'woocommerce-maxipago'), 'error');
                        return false;
                    }
                }
            }

            return true;
        }

        public function seller_filter_selector()
        {
            global $typenow;

            if ('product' == $typenow || 'shop_order' == $typenow) {
                $splitPayment = get_option('woocommerce_maxipago-cc_settings')['split_payment'];

                if($this->is_split_payment_active())
                    include_once 'includes/admin/views/html-filter-seller.php';
            }
        }

        public function seller_filter_order($where)
        {
            global $pagenow;
            global $typenow;

            if($pagenow == 'edit.php' && $typenow == 'shop_order')
            {
                if($this->is_split_payment_active() && isset($_GET['merchant_id']) && $_GET['merchant_id'] != '')
                {
                    global $wpdb;

                    $seller_id = $_GET['merchant_id'];

                    $posts_table = $wpdb->posts;
                    $order_items_table = $wpdb->prefix . "woocommerce_order_items";
                    $order_itemmeta_table = $wpdb->prefix . "woocommerce_order_itemmeta";
                    $postmeta_table = $wpdb->postmeta;

                    $productQuery = "SELECT post_id FROM $postmeta_table WHERE meta_key = 'seller_id' and meta_value = '$seller_id'";
                    $orderItemQuery = "SELECT order_item_id FROM $order_itemmeta_table WHERE meta_key = '_product_id' and meta_value in ($productQuery)";
                    $orderQuery = "SELECT order_id FROM $order_items_table WHERE order_item_type = 'line_item' and order_item_id in ($orderItemQuery)";

                    $where .= " AND $posts_table.id in ($orderQuery)";
                }
            }

            return $where;
        }

        public function show_shop_order_seller_column($columns)
        {
            if($this->is_split_payment_active())
                $columns['seller'] = __('Seller(s)','woocommerce-maxipago');

            return $columns;
        }

        public function show_shop_order_seller_data($column, $post_id)
        {
            if($this->is_split_payment_active() && $column == 'seller')
            {
                $sellers = array();

                $order = wc_get_order($post_id);

                if($order)
                {
                    $items = $order->get_items();

                    foreach($items as $item)
                    {
                        $product_id = $item->get_product_id();
                        $seller_id = get_post_meta($product_id,'seller_id',true);

                        if($seller_id)
                        {
                            $seller = array(
                                'id' => $seller_id,
                                'name' => $this->get_seller_name($seller_id)
                            );

                            if(!in_array($seller, $sellers)) {
                                array_push($sellers, $seller);
                            }
                        }
                    }
                }

                $sellers_amount = count($sellers);

                if($sellers_amount == 0)
                    echo "";
                else if ($sellers_amount == 1)
                    echo $sellers[0]['name'];
                else if ($sellers_amount == 2)
                    echo $sellers[0]['name'] . __(' and ', 'woocommerce-maxipago') . $sellers[1]['name'];
                else if ($sellers_amount > 2)
                    echo __('Three or more sellers', 'woocommerce-maxipago');
            }
        }

        public function seller_filter_product($where)
        {
            global $pagenow;
            global $typenow;

            if($pagenow == 'edit.php' && $typenow == 'product')
            {
                if($this->is_split_payment_active() && isset($_GET['merchant_id']) && $_GET['merchant_id'] != '')
                {
                    global $wpdb;

                    $seller_id = $_GET['merchant_id'];

                    $posts_table = $wpdb->posts;
                    $postmeta_table = $wpdb->postmeta;

                    $productQuery = "SELECT post_id FROM $postmeta_table WHERE meta_key = 'seller_id' and meta_value = '$seller_id'";

                    $where .= " AND $posts_table.id in ($productQuery)";
                }
            }

            return $where;
        }

        public function show_product_seller_column($columns)
        {
            if($this->is_split_payment_active())
                $columns['seller'] = __('Seller','woocommerce-maxipago');

            return $columns;
        }

        public function show_product_seller_data($column, $post_id)
        {
            if($this->is_split_payment_active() && $column == 'seller')
            {
                $seller_id = get_post_meta($post_id,'seller_id',true);
                echo $this->get_seller_name($seller_id);
            }
        }
    }

    add_action('plugins_loaded', array('WC_maxiPago', 'load_maxipago_class'));

}
