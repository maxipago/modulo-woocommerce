<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_maxiPago_Ticket_Gateway extends WC_Payment_Gateway_CC
{

    const ID = 'maxipago-ticket';

    /** @var WC_maxiPago_Ticket_API */
    public $api;

    public $supports = array('products');

    public $environment;
    public $merchant_id;
    public $merchant_key;
    public $invoice_prefix;
    public $save_log;
    public $bank;
    public $days_to_expire;
    public $instructions;

    public function __construct()
    {

        $this->id = self::ID;
        $this->method_title = __('maxiPago! - Ticket', 'woocommerce-maxipago');
        $this->method_description = __('Accept Payments by Ticket using the maxiPago!', 'woocommerce-maxipago');
        $this->has_fields = true;

        // Global Settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->environment = $this->get_option('environment');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_key = $this->get_option('merchant_key');
        $this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');
        $this->save_log = $this->get_option('save_log');

        // Ticket Settings
        $this->bank = $this->get_option('bank');
        $this->days_to_expire = $this->get_option('days_to_expire');
        $this->instructions = $this->get_option('instructions');

        $this->api = new WC_maxiPago_Ticket_API($this);

        $this->init_form_fields();
        $this->init_settings();

        // Front actions
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'set_thankyou_page'));
        add_action('woocommerce_email_after_order_table', array($this, 'set_email_instructions'), 10, 3);

        // Admin actions
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Checkout Scripts
        if (is_checkout() || is_checkout_pay_page()) {
            wp_enqueue_script('jquery-maskedinput', plugins_url('assets/js/jquery-maskedinput/jquery.maskedinput.js', plugin_dir_path(__FILE__)), array('jquery'), WC_maxiPago::VERSION, true);
        }
    }

    public function get_supported_currencies()
    {
        return apply_filters(
            'woocommerce_maxipago_supported_currencies', array(
                'BRL',
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
                'label' => __('Enable maxiPago! Ticket', 'woocommerce-maxipago'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Ticket', 'woocommerce-maxipago')
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-maxipago'),
                'type' => 'textarea',
                'description' => __('Displayed at checkout.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => __('Pay your order with a ticket.', 'woocommerce-maxipago')
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

            'bank' => array(
                'title' => __('Bank of ticket', 'woocommerce-maxipago'),
                'type' => 'select',
                'description' => __('Choose your bank of ticket.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'class' => 'wc-enhanced-select',
                'default' => '',
                'options' => $this->api->get_banks('ticket')
            ),
            'days_to_expire' => array(
                'title' => __('Days to expire Ticket', 'woocommerce-maxipago'),
                'type' => 'text',
                'description' => __('Choose the number of days to expire ticket.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '5',
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-maxipago'),
                'type' => 'textarea',
                'description' => __('Enter the instructions that will appear on the ticket.', 'woocommerce-maxipago'),
                'desc_tip' => true,
                'default' => '',
            ),
        );
    }

    public function payment_fields()
    {
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
        wc_get_template(
            'ticket/payment-form.php',
            array(),
            'woocommerce/maxipago/',
            WC_maxiPago::get_templates_path()
        );
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
        $result_data = get_post_meta($order_id, '_maxipago_result_data', true);
        if (isset($result_data['boletoUrl']) && 'on-hold' == $order_status) {
            wc_get_template(
                'ticket/payment-instructions.php',
                array(
                    'url' => $result_data['boletoUrl'],
                ),
                'woocommerce/maxipago/',
                WC_maxiPago::get_templates_path()
            );

            add_post_meta($order_id, 'maxipago_ticket_url', $result_data['boletoUrl']);
        }
    }

    public function set_email_instructions(WC_Order $order, $sent_to_admin, $plain_text = false)
    {
        if ($sent_to_admin || !in_array($order->get_status(), array('on-hold')) || $this->id !== $order->get_payment_method()) {
            return;
        }
        $result_data = get_post_meta($order->get_id(), '_maxipago_result_data', true);
        if (isset($result_data['boletoUrl'])) {
            if ($plain_text) {
                wc_get_template(
                    'ticket/emails/plain-instructions.php',
                    array(
                        'url' => $result_data['boletoUrl'],
                    ),
                    'woocommerce/maxipago/',
                    WC_maxiPago::get_templates_path()
                );
            } else {
                wc_get_template(
                    'ticket/emails/html-instructions.php',
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