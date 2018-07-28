<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_maxiPago_DC_Gateway extends WC_Payment_Gateway_CC
{

    const ID = 'maxipago-dc';

    /** @var WC_maxiPago_DC_API */
    public $api;

    public $environment;
    public $merchant_id;
    public $merchant_key;
    public $invoice_prefix;
    public $save_log;
    public $soft_descriptor;
    public $interest_rate_caculate_method;
    public $mpi_processor;
    public $failure_action;
    public $acquirers_visa;
    public $acquirers_mastercard;

    public $supports = array('products', 'refunds');

    public function __construct()
    {

        $this->id = self::ID;
        $this->method_title = __('maxiPago! - Debit Card', 'woocommerce-maxipago');
        $this->method_description = __('Accept Payments by Debit Card using the maxiPago!', 'woocommerce-maxipago');
        $this->has_fields = true;

        // Global Settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->environment = $this->get_option('environment');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');
        $this->save_log = $this->get_option('save_log');

        // DC Settings
        $this->soft_descriptor = $this->get_option('soft_descriptor');
        $this->acquirers_visa = $this->get_option('acquirers_visa');
        $this->acquirers_mastercard = $this->get_option('acquirers_mastercard');

        $this->api = new WC_maxiPago_DC_API($this);

        $this->init_form_fields();
        $this->init_settings();

        // Front actions
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'set_thankyou_page'));
        add_action('woocommerce_email_after_order_table', array($this, 'set_email_instructions'), 10, 3);

        // Admin actions
        if (is_admin()) {
            add_action('admin_notices', array($this, 'do_ssl_check'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('woocommerce_order_action_cancel_order', array($this, 'process_cancel_order_meta_box_actions'));
        }
    }

    public function do_ssl_check()
    {
        if ($this->enabled == "yes") {
            $section = isset($_GET['section']) ? $_GET['section'] : '';
            if (strpos($section, 'maxipago') !== false) {
                if (get_option('woocommerce_force_ssl_checkout') == "no") {
                    echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>", 'woocommerce-maxipago'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
                }
            }
        }
    }

    public function get_supported_currencies()
    {
        return apply_filters(
            'woocommerce_maxipago_supported_currencies', array(
                'BRL',
                'USD',
            )
        );
    }

    public function using_supported_currency()
    {
        return in_array(get_woocommerce_currency(), $this->get_supported_currencies());
    }

    public function is_available()
    {
        return parent::is_available() && !empty($this->merchant_key) && !empty($this->merchant_id) && $this->using_supported_currency();
    }

    public function admin_options()
    {
        include 'admin/views/html-admin-page.php';
    }

    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-maxipago'),
                'type' => 'checkbox',
                'label' => __('Enable maxiPago! Debit Card', 'woocommerce-maxipago'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Debit Card', 'woocommerce-maxipago')
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-maxipago'),
                'type' => 'textarea',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Pay your order with a debit card.', 'woocommerce-maxipago')
            ),

            'integration' => array(
                'title' => __('Integration Settings', 'woocommerce-maxipago'),
                'type' => 'title',
                'description' => ''
            ),

            'environment' => array(
                'title' => __('Environment', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Select the environment type (test or production).', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => 'test',
                'options' => array(
                    'TEST' => __('Test', 'woocommerce-maxipago'),
                    'LIVE' => __('Production', 'woocommerce-maxipago')
                )
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Unique ID for each merchant.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '',
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'merchant_key' => array(
                'title' => __('Merchant Key', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Key associated with the Merchant ID.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '',
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'invoice_prefix' => array(
                'title' => __('Invoice Prefix', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Please enter a prefix for your invoice numbers, which is used to ensure that the order number is unique if you use this account in more than one store.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => 'WC-'
            ),
            'save_log' => array(
                'title' => __('Save Log', 'woocommerce-maxipago'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce-maxipago'),
                'default' => 'no',
                'description' => sprintf(__('Save log for API requests. You can check this log in %s.', 'woocommerce-maxipago'), '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.log')) . '">' . __('System Status &gt; Logs', 'woocommerce-maxipago') . '</a>')
            ),

            'payment' => array(
                'title' => __('Payment Options', 'woocommerce-maxipago'),
                'type' => 'title',
                'description' => ''
            ),

            'soft_descriptor' => array(
                'title' => __('Soft Descriptor', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('For retailers using Cielo is possible to enter descriptive field that will appear on the customers invoice. This feature is available for the Visa, JCB, Mastercard, Aura, Diners and Elo brands for the authorization or sale transactions. Use only letters and numbers without spaces.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '',
                'custom_attributes' => array(
                    'maxlength' => '13'
                ),
            ),

            'mpi_processor' => array(
                'title' => __('MPI Processor', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => '',
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_mpi_processors()
            ),

            'failure_action' => array(
                'title' => __('Failure Action', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => '',
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_mpi_action()
            ),

            'acquirers' => array(
                'title' => __('Acquirers', 'woocommerce-maxipago'),
                'type' => 'title',
                'description' => ''
            ),

            'acquirers_visa' => array(
                'title' => __('Visa', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Visa.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer()
            ),
            'acquirers_mastercard' => array(
                'title' => __('MasterCard', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for MasterCard.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer()
            )
        );
    }

    public function form()
    {
        wp_enqueue_script('wc-credit-card-form');
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
        include_once WC_maxiPago::get_plugin_path() . 'templates/dc/payment-form.php';
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        return $this->api->sale_order($order, $_POST);
    }

    public function set_thankyou_page($order_id)
    {
        $order = new WC_Order($order_id);
        $order_status = $order->get_status();
        $request_data = get_post_meta($order_id, '_maxipago_request_data', true);
        $result_data = get_post_meta($order_id, '_maxipago_result_data', true);
        if (
            isset($result_data['authenticationURL'])
            && 'on-hold' == $order_status
        ) {
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

    public function set_email_instructions(WC_Order $order, $sent_to_admin, $plain_text = false)
    {
        if ($sent_to_admin || !in_array($order->get_status(), array('processing', 'on-hold')) || $this->id !== $order->get_payment_method()) {
            return;
        }

        $request_data = get_post_meta($order->get_id(), '_maxipago_request_data', true);
        $result_data = get_post_meta($order->get_id(), '_maxipago_result_data', true);

        if (isset($result_data['creditCardScheme'])) {
            if ($plain_text) {
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

    public function admin_scripts($hook)
    {
        if ('woocommerce_page_wc-settings' === $hook && (isset($_GET['section']) && $this->id == strtolower($_GET['section']))) {
            wp_enqueue_script('woocommerce-maxipago-admin-scripts', plugins_url('assets/js/admin-scripts.js', plugin_dir_path(__FILE__)), array('jquery'), WC_maxiPago::VERSION, true);
        }
    }

}