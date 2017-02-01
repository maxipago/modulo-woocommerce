<?php
if (!defined('ABSPATH')) {
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
    private $api;

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
    public $processing_type;
    public $acquirers_visa;
    public $acquirers_mastercard;
    public $acquirers_amex;
    public $acquirers_diners;
    public $acquirers_elo;
    public $acquirers_discover;
    public $acquirers_hipercard;
    public $use_token;

    public $supports = array('products','refunds');

    public function __construct() {

        $this->id = self::ID;
        $this->method_title = __('maxiPago! - Credit Card', 'woocommerce-maxipago');
        $this->method_description = __('Accept Payments by Credit Card using the maxiPago!', 'woocommerce-maxipago');
        $this->has_fields = true;

        // Global Settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->environment = $this->get_option('environment');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');
        $this->save_log = $this->get_option('save_log');

        // CC Settings
        $this->soft_descriptor = $this->get_option('soft_descriptor');
        $this->installments = $this->get_option('installments');
        $this->interest_rate_caculate_method = $this->get_option('interest_rate_caculate_method', self::INTEREST_RATE_TYPE_SIMPLE);
        $this->interest_rate = $this->get_option('interest_rate');
        $this->max_without_interest = $this->get_option('max_without_interest');
        $this->min_per_installments = $this->get_option('min_per_installments');
        $this->fraud_check = $this->get_option('fraud_check');
        $this->processing_type = $this->get_option('processing_type');
        $this->acquirers_visa = $this->get_option('acquirers_visa');
        $this->acquirers_mastercard = $this->get_option('acquirers_mastercard');
        $this->acquirers_amex = $this->get_option('acquirers_amex');
        $this->acquirers_diners = $this->get_option('acquirers_diners');
        $this->acquirers_elo = $this->get_option('acquirers_elo');
        $this->acquirers_discover = $this->get_option('acquirers_discover');
        $this->acquirers_hipercard = $this->get_option('acquirers_hipercard');
        $this->use_token = $this->get_option('use_token');

        if($this->use_token) {
            $this->supports[] = 'tokenization';
        }

        $this->api = new WC_maxiPago_CC_API($this);

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

    public function get_supported_currencies() {
        return apply_filters(
            'woocommerce_maxipago_supported_currencies', array(
                'BRL',
                'USD',
            )
        );
    }

    public function using_supported_currency() {
        return in_array(get_woocommerce_currency(), $this->get_supported_currencies());
    }

    public function is_available() {
        return parent::is_available() && !empty($this->merchant_key) && !empty($this->merchant_id) && $this->using_supported_currency();
    }

    public function admin_options() {
        include 'admin/views/html-admin-page.php';
    }

    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-maxipago'),
                'type' => 'checkbox',
                'label' => __('Enable maxiPago! Credit Card', 'woocommerce-maxipago'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Credit Card', 'woocommerce-maxipago')
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-maxipago'),
                'type' => 'textarea',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Pay your order with a credit card.', 'woocommerce-maxipago')
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

            'installments' => array(
                'title' => __('Maximum number of installments', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Maximum number of installments for orders in your store.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '1',
                'options' => array(
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                    '11' => '11',
                    '12' => '12'
                )
            ),
            'interest_rate' => array(
                'title' => __('Interest Rate (%)', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Percentage of interest that will be charged to the customer in the installment where there is interest rate to be charged.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '0'
            ),
            'interest_rate_caculate_method' => array(
                'title' => __('Interest Rate Calculate Method', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your interest rate calculate method.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => self::INTEREST_RATE_TYPE_SIMPLE,
                'options' => array(
                    self::INTEREST_RATE_TYPE_SIMPLE => __('Simple', 'woocommerce-maxipago'),
                    self::INTEREST_RATE_TYPE_COMPOUND => __('Compound', 'woocommerce-maxipago'),
                    self::INTEREST_RATE_TYPE_PRICE => __('Price', 'woocommerce-maxipago'),
                )
            ),
            'max_without_interest' => array(
                'title' => __('Number of installments without Interest Rate', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Indicate the number of public without Interest Rate.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '0',
                'options' => array(
                    '0' => __('None', 'woocommerce-maxipago'),
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                    '7' => '7',
                    '8' => '8',
                    '9' => '9',
                    '10' => '10',
                    '11' => '11',
                    '12' => '12'
                )
            ),
            'min_per_installments' => array(
                'title' => __('Minimum value per installments', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Minimum value per installments, cannot be less than 1.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => self::MIN_PER_INSTALLMENT
            ),
            'processing_type' => array(
                'title' => __('Processing Type', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your processing type.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => self::PROCESSING_TYPE_SALE,
                'options' => array(
                    self::PROCESSING_TYPE_SALE => __('Sale (Authorize and Capture)', 'woocommerce-maxipago'),
                    self::PROCESSING_TYPE_AUTH => __('Authorization (Authorize only)', 'woocommerce-maxipago'),
                )
            ),
            'use_token' => array(
                'title' => __('Use Credit Card Tokenization', 'woocommerce-maxipago'),
                'type' => 'checkbox',
                'label' => __('Save the Credit Card token to use for future purchases.', 'woocommerce-maxipago'),
                'default' => 'no'
            ),
            'fraud_check' => array(
                'title' => __('Fraud Check', 'woocommerce-maxipago'),
                'type' => 'checkbox',
                'label' => __('Enable fraudControl!', 'woocommerce-maxipago'),
                'default' => 'no'
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
            ),
            'acquirers_amex' => array(
                'title' => __('Amex (American Express)', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Amex (American Express).', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer('amex')
            ),
            'acquirers_diners' => array(
                'title' => __('Diners Club', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Diners Club.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer('diners')
            ),
            'acquirers_elo' => array(
                'title' => __('Elo', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Elo.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer('elo')
            ),
            'acquirers_discover' => array(
                'title' => __('Discover', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Discover.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer('discover')
            ),
            'acquirers_hipercard' => array(
                'title' => __('Hipercard', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your acquirer for Hipercard.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_processor_by_acquirer('hipercard')
            ),
        );
    }

    public function form() {
        wp_enqueue_script('wc-credit-card-form');
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
        include_once WC_maxiPago::get_plugin_path() . 'templates/cc/payment-form.php';
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        return $this->api->sale_order($order, $_POST);
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        return $this->api->refund_order($order);
    }

    public function set_thankyou_page($order_id) {
        $order = new WC_Order($order_id);
        $order_status = $order->get_status();
        $request_data = get_post_meta($order_id, '_maxipago_request_data', true);
        $result_data = get_post_meta($order_id, '_maxipago_result_data', true);
        if (isset($request_data['numberOfInstallments']) && (isset($result_data['creditCardScheme']))
            && ('processing' == $order_status || 'on-hold' == $order_status)
        ) {
            wc_get_template(
                'cc/payment-instructions.php',
                array(
                    'brand' => $result_data['creditCardScheme'],
                    'installments' => $request_data['numberOfInstallments']
                ),
                'woocommerce/maxipago/',
                WC_maxiPago::get_templates_path()
            );
        }
    }

    public function set_email_instructions(WC_Order $order, $sent_to_admin, $plain_text = false) {
        if ($sent_to_admin || !in_array($order->get_status(), array('processing', 'on-hold')) || $this->id !== $order->payment_method) {
            return;
        }
        $request_data = get_post_meta($order->id, '_maxipago_request_data', true);
        $result_data = get_post_meta($order->id, '_maxipago_result_data', true);
        if (isset($request_data['numberOfInstallments']) && (isset($result_data['creditCardScheme']))) {
            if ($plain_text) {
                wc_get_template(
                    'cc/emails/plain-instructions.php',
                    array(
                        'brand' => $result_data['creditCardScheme'],
                        'installments' => $request_data['numberOfInstallments']
                    ),
                    'woocommerce/maxipago/',
                    WC_maxiPago::get_templates_path()
                );
            } else {
                wc_get_template(
                    'cc/emails/html-instructions.php',
                    array(
                        'brand' => $result_data['creditCardScheme'],
                        'installments' => $request_data['numberOfInstallments']
                    ),
                    'woocommerce/maxipago/',
                    WC_maxiPago::get_templates_path()
                );
            }
        }
    }

    public function process_cancel_order_meta_box_actions($order) {
        $this->api->refund_order($order);
    }

    public function add_payment_method() {
        if (empty ($_POST['maxipago_card_number'])) {
            wc_add_notice(__('There was a problem adding this card.', 'woocommerce'), 'error');
            return false;
        }
        $cc_info = array(
            'cc_number' => wc_clean($_POST['maxipago_card_number']),
            'cc_expiry' => wc_clean($_POST['maxipago_card_expiry']),
            'cc_cvc' => wc_clean($_POST['maxipago_card_cvc']),
        );
        $token = $this->api->save_token($cc_info);
        if (!$token) {
            wc_add_notice(__('There was a problem adding this card.', 'woocommerce'), 'error');
            return false;
        }
        return array(
            'result' => 'success',
            'redirect' => wc_get_endpoint_url('payment-methods'),
        );
    }

    public function do_ssl_check() {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>", 'woocommerce-maxipago'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public function admin_scripts($hook) {
        if ('woocommerce_page_wc-settings' === $hook && (isset($_GET['section']) && $this->id == strtolower($_GET['section']))) {
            wp_enqueue_script('woocommerce-maxipago-admin-scripts', plugins_url('assets/js/admin-scripts.js', plugin_dir_path(__FILE__)), array('jquery'), WC_maxiPago::VERSION, true);
        }
    }

    public function tokenization_script() {
        wp_enqueue_script('woocommerce-tokenization-form', plugins_url('assets/js/tokenization-form.js', plugin_dir_path(__FILE__)), array('jquery'), WC_maxiPago::VERSION, true);
    }

}