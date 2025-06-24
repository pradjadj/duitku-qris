<?php
class WC_Gateway_Duitku_QRIS extends WC_Payment_Gateway {
    private $merchant_code;
    private $api_key;
    private $environment;
    private $expiry_period;
    private $qris_provider;
    private $enable_logging;

    public function __construct() {
        $this->id = 'duitku_qris';
        $this->icon = ''; // Add QRIS icon URL here
        $this->has_fields = false;
        $this->method_title = 'Duitku QRIS';
        $this->method_description = 'Accept payments using Duitku QRIS - Display QR code directly on checkout page';

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_code = $this->get_option('merchant_code');
        $this->api_key = $this->get_option('api_key');
        $this->environment = $this->get_option('environment');
        $this->expiry_period = $this->get_option('expiry_period');
        $this->qris_provider = $this->get_option('qris_provider');
        $this->enable_logging = 'yes' === $this->get_option('enable_logging');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_wc_gateway_duitku_qris', array($this, 'check_callback'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Duitku QRIS Payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title'),
                'type' => 'text',
                'description' => __('Payment method title that the customer will see on your checkout.'),
                'default' => __('QRIS Payment'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.'),
                'default' => __('Pay with QRIS - Scan QR Code to complete payment'),
            ),
            'merchant_code' => array(
                'title' => __('Merchant Code'),
                'type' => 'text',
                'description' => __('Enter your Duitku Merchant Code'),
                'default' => '',
                'desc_tip' => true,
            ),
            'api_key' => array(
                'title' => __('API Key'),
                'type' => 'password',
                'description' => __('Enter your Duitku API Key'),
                'default' => '',
                'desc_tip' => true,
            ),
            'environment' => array(
                'title' => __('Environment'),
                'type' => 'select',
                'description' => __('Choose environment'),
                'default' => 'sandbox',
                'options' => array(
                    'sandbox' => __('Sandbox'),
                    'production' => __('Production')
                ),
            ),
            'qris_provider' => array(
                'title' => __('QRIS Provider'),
                'type' => 'select',
                'description' => __('Choose QRIS provider'),
                'default' => 'SP',
                'options' => array(
                    'SP' => __('QRIS ShopeePay'),
                    'NQ' => __('QRIS NobuBank'),
                    'DQ' => __('QRIS DANA'),
                    'GQ' => __('QRIS Gudang Voucher'),
                    'SQ' => __('QRIS Nusapay')
                ),
            ),
            'expiry_period' => array(
                'title' => __('Expiry Period'),
                'type' => 'number',
                'description' => __('Transaction expiry period in minutes'),
                'default' => '10',
                'desc_tip' => true,
            ),
            'enable_logging' => array(
                'title' => __('Enable Logging'),
                'type' => 'checkbox',
                'label' => __('Log debug messages'),
                'default' => 'no',
            )
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            // Generate merchant order ID
            $merchant_order_id = 'TRX-' . $order_id;
            
            // Prepare API request
            $amount = $order->get_total();
            $customer_email = $order->get_billing_email();
            $customer_phone = $order->get_billing_phone();
            
            // Calculate signature
            $signature = md5($this->merchant_code . $merchant_order_id . $amount . $this->api_key);
            
            // API endpoints
            $endpoints = [
                'sandbox' => 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry',
                'production' => 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
            ];
            
            $api_url = $endpoints[$this->environment];
            
            // Prepare request body
            $body = [
                'merchantCode' => $this->merchant_code,
                'paymentAmount' => $amount,
                'paymentMethod' => $this->qris_provider,
                'merchantOrderId' => $merchant_order_id,
                'productDetails' => 'Order #' . $order_id,
                'customerVaName' => get_bloginfo('name'),
                'email' => $customer_email,
                'phoneNumber' => $customer_phone,
                'callbackUrl' => home_url('/?duitku_callback=1'),
                'returnUrl' => $order->get_checkout_order_received_url(),
                'signature' => $signature,
                'expiryPeriod' => $this->expiry_period
            ];

            // Make API request
            $response = wp_remote_post($api_url, array(
                'body' => json_encode($body),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                $this->log('API Request Error: ' . $response->get_error_message());
                throw new Exception($response->get_error_message());
            }

            $result = json_decode(wp_remote_retrieve_body($response));

            if (!$result || isset($result->statusCode) && $result->statusCode !== '00') {
                $this->log('API Response Error: ' . wp_json_encode($result));
                throw new Exception($result->statusMessage ?? 'Unknown error');
            }

            // Save QR string and other data to order
            update_post_meta($order_id, '_duitku_qris_string', $result->qrString);
            update_post_meta($order_id, '_duitku_reference', $result->reference);
            update_post_meta($order_id, '_duitku_expiry_time', time() + ($this->expiry_period * 60));

            // Update order status to pending
            $order->update_status('pending', __('Awaiting QRIS payment', 'woocommerce'));

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url()
            );

        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'woocommerce') . $e->getMessage(), 'error');
            return;
        }
    }

    public function check_payment_status($order) {
        if (!$order) {
            return false;
        }

        $merchant_order_id = 'TRX-' . $order->get_id();
        $signature = md5($this->merchant_code . $merchant_order_id . $this->api_key);

        $endpoints = [
            'sandbox' => 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus',
            'production' => 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
        ];

        $api_url = $endpoints[$this->environment];

        $response = wp_remote_post($api_url, array(
            'body' => json_encode([
                'merchantCode' => $this->merchant_code,
                'merchantOrderId' => $merchant_order_id,
                'signature' => $signature
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('Check Status Error: ' . $response->get_error_message());
            return false;
        }

        $result = json_decode(wp_remote_retrieve_body($response));

        if (!$result) {
            return false;
        }

        // Check if payment is successful
        if ($result->statusCode === '00') {
            if ($order->get_status() === 'pending') {
                $order->payment_complete($result->reference);
                $order->add_order_note(__('Payment completed via Duitku QRIS', 'woocommerce'));
            }
            return true;
        }

        // Check if payment has expired
        $expiry_time = get_post_meta($order->get_id(), '_duitku_expiry_time', true);
        if ($expiry_time && time() > $expiry_time && $order->get_status() === 'pending') {
            $order->update_status('cancelled', __('Payment expired', 'woocommerce'));
        }

        return false;
    }

    protected function log($message) {
        if ($this->enable_logging) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->debug($message, array('source' => 'duitku_qris'));
        }
    }
}
