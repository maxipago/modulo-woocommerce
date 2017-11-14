<?php

class WC_maxiPago_Cron {

    /** @var WC_Logger */
    protected $log;

    public function __construct() {
        if (!wp_next_scheduled('maxipago_update_cc_orders')) {
            wp_schedule_event(time(), 'daily', 'maxipago_update_cc_orders');
        }
        if (!wp_next_scheduled('maxipago_update_ticket_orders')) {
            wp_schedule_event(time(), 'daily', 'maxipago_update_ticket_orders');
        }
        if (!wp_next_scheduled('maxipago_update_tef_orders')) {
            wp_schedule_event(time(), 'daily', 'maxipago_update_tef_orders');
        }
        add_action('maxipago_update_cc_orders', array($this, 'update_cc_order_status'));
        add_action('maxipago_update_ticket_orders', array($this, 'update_ticket_order_status'));
        add_action('maxipago_update_tef_orders', array($this, 'update_tef_order_status'));
    }

    public function update_cc_order_status() {
        $this->update_order_status(WC_maxiPago_CC_Gateway::ID);
    }

    public function update_ticket_order_status() {
        $this->update_order_status(WC_maxiPago_Ticket_Gateway::ID);
    }

    public function update_tef_order_status() {
        $this->update_order_status(WC_maxiPago_TEF_Gateway::ID);
    }

    private function update_order_status($method_id) {
        $settings = get_option('woocommerce_'.$method_id.'_settings');
        if (is_array($settings) && isset($settings['enabled']) && $settings['enabled'] == 'yes') {
            if ('yes' == $settings['save_log'] && class_exists('WC_Logger')) {
                $this->log = new WC_Logger();
            }
            $this->log->add('maxipago_api','----------- Run CRON Update Order Status: '.$method_id.' ----------- '.PHP_EOL);
            $client = new maxiPago;
            $client->setCredentials($settings['merchant_id'], $settings['merchant_key']);
            $client->setEnvironment($settings['environment']);
            $orders = new WP_Query(
                array(
                    'post_type' => 'shop_order',
                    'post_status' => 'wc-on-hold',
                    'posts_per_page' => -1,
                )
            );
            while ($orders->have_posts()) {
                $orders->the_post();
                $order_id = $orders->post->ID;
                $order = new WC_Order($order_id);
                if ($order->payment_method == $method_id) {
                    $result_data = get_post_meta($order_id, '_maxipago_result_data', true);
                    if ($result_data) {
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
                            if ($this->set_order_status($order_id, $state)) {
                                if ($this->log) $this->log->add('maxipago_api', '['.$method_id.'] Update Order Status: ' . $settings['invoice_prefix'] . $order_id);
                            }
                        }
                    }
                }
            }
        }
    }

    private function set_order_status($order_id, $status) {
        $valid = false;
        $order = wc_get_order($order_id);
        if ($order->id === $order_id) {
            $order_status = sanitize_text_field($status);
            switch ($order_status) {
                case 3  : // Capturada
                case 10 : // Paga
                case 44 : // Aprovada na Fraude
                    $order->payment_complete();
                    $valid = true;
                    break;
                case 7 : // Negada
                    $order->update_status('failed', __('maxiPago!: Payment Denied.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
                case 45 : // Negada por Fraude
                    $order->update_status('failed', __('maxiPago!: Payment Denied for duplicity or fraud.', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
                case 9 : // Cancelada (Voided)
                    $order->update_status('cancelled', __('maxiPago!: Payment Cancelled (Voided).', 'woocommerce-maxipago'));
                    $valid = true;
                    break;
            }
        }
        return $valid;
    }
}
$cron = new WC_maxiPago_Cron();