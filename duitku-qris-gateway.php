<?php
/**
 * Plugin Name: Duitku QRIS Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: Duitku Payment Gateway with QRIS for WooCommerce - Display QRIS directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 * Requires at least: 6.0
 * Tested up to: 6.8
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>Duitku QRIS Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// HPOS Compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'init_duitku_qris_gateway', 11);

function init_duitku_qris_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Duitku_QRIS_Gateway extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'duitku_qris';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = 'Duitku QRIS';
            $this->method_description = 'Accept QRIS payments directly on your checkout page using Duitku';
            
            $this->supports = array(
                'products',
                'refunds'
            );
            
            // Load the settings
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchant_code = $this->get_option('merchant_code');
            $this->api_key = $this->get_option('api_key');
            $this->environment = $this->get_option('environment');
            $this->qris_provider = $this->get_option('qris_provider');
            $this->order_prefix = $this->get_option('order_prefix');
            $this->expiry_period = $this->get_option('expiry_period', 10);
            $this->enable_logging = $this->get_option('enable_logging');
            $this->payment_complete_status = $this->get_option('payment_complete_status', 'processing');
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_duitku_pg', array($this, 'handle_callback'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            
            // Add settings link to plugin action links
            function duitku_qris_plugin_action_links($links) {
                $plugin_links = array(
                    '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=duitku_qris') . '">' . __('Settings', 'woocommerce') . '</a>'
                );
                return array_merge($plugin_links, $links);
            }
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'duitku_qris_plugin_action_links');
        }
        
        public function enqueue_scripts() {
            if (is_checkout() || is_order_received_page()) {
                wp_enqueue_script('qrcodejs', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', array(), '1.0.0', true);
                wp_enqueue_script('duitku-qris', plugin_dir_url(__FILE__) . 'assets/js/duitku-qris.js', array('jquery', 'qrcodejs'), '1.2', true);
                
                wp_localize_script('duitku-qris', 'duitku_qris_params', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'check_interval' => 3000 // 3 seconds
                ));
            }
        }
        
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Duitku QRIS Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'QRIS via Duitku',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay securely via QRIS. Scan the QR code to complete your payment.',
                ),
                'merchant_code' => array(
                    'title' => 'Merchant Code',
                    'type' => 'text',
                    'description' => 'Your Duitku Merchant Code',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_key' => array(
                    'title' => 'API Key',
                    'type' => 'password',
                    'description' => 'Your Duitku API Key',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'environment' => array(
                    'title' => 'Environment',
                    'type' => 'select',
                    'description' => 'Select Sandbox for testing or Production for live transactions',
                    'default' => 'sandbox',
                    'options' => array(
                        'sandbox' => 'Sandbox',
                        'production' => 'Production'
                    ),
                    'desc_tip' => true,
                ),
                'qris_provider' => array(
                    'title' => 'QRIS Provider',
                    'type' => 'select',
                    'description' => 'Select your QRIS provider',
                    'default' => 'SP',
                    'options' => array(
                        'SP' => 'QRIS ShopeePay',
                        'NQ' => 'QRIS NobuBank',
                        'DQ' => 'QRIS DANA',
                        'GQ' => 'QRIS Gudang Voucher',
                        'SQ' => 'QRIS Nusapay'
                    ),
                    'desc_tip' => true,
                ),
                'order_prefix' => array(
                    'title' => 'Merchant Order ID Prefix',
                    'type' => 'text',
                    'description' => 'Prefix for Merchant Order ID (max 5 characters)',
                    'default' => 'TRX-',
                    'desc_tip' => true,
                ),
                'expiry_period' => array(
                    'title' => 'Expiry Period (minutes)',
                    'type' => 'number',
                    'description' => 'Time before payment expires (in minutes)',
                    'default' => '10',
                    'desc_tip' => true,
                ),
                'payment_complete_status' => array(
                    'title' => 'Order Status After Payment',
                    'type' => 'select',
                    'description' => 'Order status when payment is completed',
                    'default' => 'processing',
                    'options' => array(
                        'processing' => 'Processing',
                        'completed' => 'Completed'
                    ),
                    'desc_tip' => true,
                ),
                'enable_logging' => array(
                    'title' => 'Enable Logging',
                    'type' => 'checkbox',
                    'label' => 'Enable transaction logging',
                    'description' => 'Log Duitku QRIS events in WooCommerce System Status logs',
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'callback_info' => array(
                    'title' => 'Callback URL',
                    'type' => 'title',
                    'description' => 'Set this URL as your callback URL in Duitku Merchant Portal: <strong>' . home_url('/wc-api/wc_duitku_pg') . '</strong>',
                ),
            );
        }
        
        public function plugin_action_links($links) {
            $settings_url = add_query_arg(
                array(
                    'page' => 'wc-settings',
                    'tab' => 'checkout',
                    'section' => $this->id
                ),
                admin_url('admin.php')
            );
            
            $plugin_links = array(
                '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'woocommerce') . '</a>'
            );
            
            return array_merge($plugin_links, $links);
        }
        
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            // Generate merchant order ID
            $merchant_order_id = substr($this->order_prefix, 0, 5) . $order_id;
            
            // Prepare parameters
            $params = array(
                'merchantCode' => $this->merchant_code,
                'paymentAmount' => $order->get_total(),
                'merchantOrderId' => $merchant_order_id,
                'productDetails' => $this->get_product_details($order),
                'email' => $order->get_billing_email(),
                'paymentMethod' => $this->qris_provider,
                'customerVaName' => get_bloginfo('name'),
                'phoneNumber' => $order->get_billing_phone(),
                'returnUrl' => $this->get_return_url($order),
                'callbackUrl' => home_url('/wc-api/wc_duitku_pg'),
                'signature' => md5($this->merchant_code . $merchant_order_id . $order->get_total() . $this->api_key),
                'expiryPeriod' => $this->expiry_period,
                'itemDetails' => $this->get_item_details($order),
                'customerDetail' => $this->get_customer_detail($order),
                'additionalParam' => '',
                'merchantUserId' => $order->get_customer_id() ?: 'guest_' . $order_id
            );
            
            // Determine API URL based on environment
            $api_url = ($this->environment == 'production') ? 
                'https://passport.duitku.com/webapi/api/merchant/v2/inquiry' : 
                'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry';
            
            // Send request to Duitku
            $response = $this->send_request($api_url, $params);
            
            if ($response && isset($response['statusCode'])) {
                if ($response['statusCode'] == '00') {
                    // Log the response if logging is enabled
                    if ($this->enable_logging == 'yes') {
                        $this->log('Duitku QRIS Response: ' . print_r($response, true));
                    }
                    
                    // Store Duitku reference in order meta
                    $order->update_meta_data('_duitku_reference', $response['reference']);
                    $order->update_meta_data('_duitku_qr_string', $response['qrString']);
                    $order->update_meta_data('_duitku_expiry', time() + ($this->expiry_period * 60));
                    $order->save();
                    
                    // Mark as pending (we're awaiting the payment)
                    $order->update_status('pending', __('Awaiting QRIS payment', 'duitku-qris'));
                    
                    // Return thankyou redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    $error_message = isset($response['statusMessage']) ? $response['statusMessage'] : 'Unknown error occurred';
                    wc_add_notice(__('Payment error:', 'duitku-qris') . ' ' . $error_message, 'error');
                    return;
                }
            } else {
                wc_add_notice(__('Connection error.', 'duitku-qris'), 'error');
                return;
            }
        }
        
        public function handle_callback() {
            // Get the raw callback data
            $raw_post = file_get_contents('php://input');
            
            // First try to decode as JSON
            $params = json_decode($raw_post, true);
            
            // If JSON decode failed or empty, try to parse as POST data
            if (json_last_error() !== JSON_ERROR_NONE || empty($params)) {
                parse_str($raw_post, $params);
            }
            
            // If still empty, fall back to regular POST
            if (empty($params)) {
                $params = $_POST;
            }
            
            // Log the raw callback data for debugging
            $this->log('Raw callback data received: ' . print_r($params, true));
            
            // Required parameters from Duitku documentation
            $required_params = array(
                'merchantCode',
                'amount',
                'merchantOrderId',
                'productDetail',
                'additionalParam',
                'paymentCode',
                'resultCode',
                'merchantUserId',
                'reference',
                'signature',
                'publisherOrderId',
                'settlementDate',
                'issuerCode'
            );
            
            // Check for missing parameters
            $missing_params = array();
            foreach ($required_params as $param) {
                if (!array_key_exists($param, $params)) {
                    $missing_params[] = $param;
                }
            }
            
            if (!empty($missing_params)) {
                $error_msg = 'Missing required parameters: ' . implode(', ', $missing_params);
                $this->log($error_msg);
                status_header(400);
                die($error_msg);
            }
            
            // Verify merchant code matches our settings
            if ($params['merchantCode'] !== $this->merchant_code) {
                $error_msg = 'Invalid merchantCode. Received: ' . $params['merchantCode'] . ' | Expected: ' . $this->merchant_code;
                $this->log($error_msg);
                status_header(401);
                die('Invalid merchantCode');
            }
            
            // Extract order ID from merchantOrderId
            $order_id = str_replace(substr($this->order_prefix, 0, 5), '', $params['merchantOrderId']);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                $error_msg = 'Order not found: ' . $order_id;
                $this->log($error_msg);
                status_header(404);
                die($error_msg);
            }
            
            // Verify signature
            $signature_string = $params['merchantCode'] . $params['amount'] . $params['merchantOrderId'] . $this->api_key;
            $generated_signature = md5($signature_string);
            
            if ($params['signature'] !== $generated_signature) {
                $error_msg = 'Invalid signature. Received: ' . $params['signature'] . ' | Generated: ' . $generated_signature;
                $this->log($error_msg);
                status_header(401);
                die('Invalid signature');
            }
            
            // Process payment status
            if ($params['resultCode'] == '00') {
                // Payment success
                $order->payment_complete($params['reference']);
                $order->add_order_note(sprintf(
                    __('Duitku QRIS payment completed. Reference: %s | Amount: %s', 'duitku-qris'),
                    $params['reference'],
                    wc_price($params['amount'])
                ));
                
                if ($this->payment_complete_status == 'completed') {
                    $order->update_status('completed');
                }
                
                $this->log('Payment completed for order ' . $order_id . '. Reference: ' . $params['reference']);
            } else {
                // Payment failed
                $order->update_status('failed', sprintf(
                    __('Duitku QRIS payment failed. Result code: %s', 'duitku-qris'),
                    $params['resultCode']
                ));
                $this->log('Payment failed for order ' . $order_id . '. Result code: ' . $params['resultCode']);
            }
            
            // Store complete callback data
            $callback_data = array(
                'merchantCode' => $params['merchantCode'],
                'amount' => $params['amount'],
                'merchantOrderId' => $params['merchantOrderId'],
                'productDetail' => $params['productDetail'],
                'additionalParam' => $params['additionalParam'],
                'paymentCode' => $params['paymentCode'],
                'resultCode' => $params['resultCode'],
                'merchantUserId' => $params['merchantUserId'],
                'reference' => $params['reference'],
                'signature' => $params['signature'],
                'publisherOrderId' => $params['publisherOrderId'],
                'settlementDate' => $params['settlementDate'],
                'issuerCode' => $params['issuerCode'],
                'callback_received' => current_time('mysql')
            );
            
            $order->update_meta_data('_duitku_callback_data', $callback_data);
            $order->save();
            
            status_header(200);
            die('OK');
        }
        
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if ($order->get_payment_method() == $this->id) {
                if ($order->is_paid()) {
                    echo '<div class="duitku-qris-container" style="text-align: center; margin: 20px 0;">';
                    echo '<div class="woocommerce-message" style="font-size: 1.2em; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #46b450;">';
                    echo 'Payment Received';
                    echo '</div>';
                    echo '</div>';
                } elseif ($order->has_status('pending')) {
                    $qr_string = $order->get_meta('_duitku_qr_string');
                    $expiry_time = $order->get_meta('_duitku_expiry');
                    
                    if ($qr_string) {
                        $current_time = time();
                        $time_left = max(0, $expiry_time - $current_time);
                        
                        echo '<div class="duitku-qris-container" style="text-align: center; margin: 20px 0;">';
                        echo '<div id="duitku-qris-content">';
                        echo '<h3>Scan QRIS untuk Pembayaran</h3>';
                        
                        echo '<div id="duitku-qris-qrcode" style="display: inline-block; margin: 0 auto;"></div>';
                        
                        echo '<div id="duitku-countdown" style="margin: 15px 0; font-weight: bold; color: #d63638;">';
                        echo 'Selesaikan pembayaran dalam: <span id="duitku-countdown-timer">' . gmdate("i:s", $time_left) . '</span>';
                        echo '</div>';
                        
                        echo '<p>Silakan scan QR code di atas menggunakan aplikasi mobile banking atau e-wallet yang mendukung QRIS.</p>';
                        
                        echo '<button id="duitku-refresh-page" class="button alt" style="margin: 10px 0; padding: 10px 20px; font-size: 1.2em;">';
                        echo 'Refresh Status Pembayaran';
                        echo '</button>';
                        echo '</div>';
                        
                        echo '<div id="duitku-payment-status" style="margin-top: 20px;"></div>';
                        
                        $ajax_nonce = wp_create_nonce('duitku_qris_check_payment_nonce');
                        
                        wc_enqueue_js('
                            jQuery(document).ready(function($) {
                                // Initialize QR Code
                                new QRCode(document.getElementById("duitku-qris-qrcode"), {
                                    text: "' . esc_js($qr_string) . '",
                                    width: 300,
                                    height: 300,
                                    colorDark : "#000000",
                                    colorLight : "#ffffff",
                                    correctLevel : QRCode.CorrectLevel.H
                                });
                                
                                // Remove title attribute to prevent tooltip
                                $("#duitku-qris-qrcode").removeAttr("title");
                                
                                // Countdown timer
                                var countdown = ' . $time_left . ';
                                var countdownElement = $("#duitku-countdown-timer");
                                var countdownInterval = setInterval(function() {
                                    countdown--;
                                    if (countdown <= 0) {
                                        clearInterval(countdownInterval);
                                        $("#duitku-qris-content").hide();
                                        $("#duitku-payment-status").html("<div class=\"woocommerce-error\" style=\"text-align: center;\">Payment time has expired. Please create a new order.</div>");
                                        return;
                                    }
                                    
                                    var minutes = Math.floor(countdown / 60);
                                    var seconds = countdown % 60;
                                    countdownElement.text((minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds));
                                }, 1000);
                                
                                // Manual refresh button
                                $("#duitku-refresh-page").on("click", function() {
                                    window.location.reload();
                                });
                                
                                // Payment status check
                                var checkInterval = 3000; // 3 seconds
                                var paymentCheck = function() {
                                    $.ajax({
                                        url: "' . admin_url('admin-ajax.php') . '",
                                        type: "POST",
                                        data: {
                                            action: "duitku_qris_check_payment",
                                            order_id: "' . $order->get_id() . '",
                                            security: "' . $ajax_nonce . '"
                                        },
                                        dataType: "json",
                                        success: function(response) {
                                            if (response.success && response.data.paid) {
                                                clearInterval(countdownInterval);
                                                $("#duitku-qris-content").hide();
                                                $("#duitku-payment-status").html("<div class=\"woocommerce-message\" style=\"text-align: center;\">Payment successfully received! Page will refresh...</div>");
                                                setTimeout(function() {
                                                    window.location.reload();
                                                }, 2000);
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error("Payment check error:", error);
                                        },
                                        complete: function() {
                                            // Schedule next check only if not paid
                                            if (!$("#duitku-payment-status").text().includes("received")) {
                                                setTimeout(paymentCheck, checkInterval);
                                            }
                                        }
                                    });
                                };
                                
                                // Initial check and schedule next
                                setTimeout(paymentCheck, checkInterval);
                            });
                        ');
                        
                        echo '</div>';
                    }
                }
            }
        }
        
        protected function get_product_details($order) {
            $items = $order->get_items();
            $product_names = array();
            
            foreach ($items as $item) {
                $product_names[] = $item->get_name();
            }
            
            return implode(', ', $product_names);
        }
        
        protected function get_item_details($order) {
            $items = $order->get_items();
            $item_details = array();
            
            foreach ($items as $item) {
                $item_details[] = array(
                    'name' => $item->get_name(),
                    'price' => $item->get_total(),
                    'quantity' => $item->get_quantity()
                );
            }
            
            // Add shipping as an item if it exists
            if ($order->get_shipping_total() > 0) {
                $item_details[] = array(
                    'name' => 'Shipping',
                    'price' => $order->get_shipping_total(),
                    'quantity' => 1
                );
            }
            
            return $item_details;
        }
        
        protected function get_customer_detail($order) {
            return array(
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phoneNumber' => $order->get_billing_phone(),
                'billingAddress' => array(
                    'firstName' => $order->get_billing_first_name(),
                    'lastName' => $order->get_billing_last_name(),
                    'address' => $order->get_billing_address_1(),
                    'city' => $order->get_billing_city(),
                    'postalCode' => $order->get_billing_postcode(),
                    'phone' => $order->get_billing_phone(),
                    'countryCode' => $order->get_billing_country()
                ),
                'shippingAddress' => array(
                    'firstName' => $order->get_shipping_first_name(),
                    'lastName' => $order->get_shipping_last_name(),
                    'address' => $order->get_shipping_address_1(),
                    'city' => $order->get_shipping_city(),
                    'postalCode' => $order->get_shipping_postcode(),
                    'phone' => $order->get_billing_phone(),
                    'countryCode' => $order->get_shipping_country()
                )
            );
        }
        
        protected function log($message) {
            if ($this->enable_logging == 'yes') {
                $logger = wc_get_logger();
                $logger->info($message, array('source' => 'duitku-qris'));
            }
        }
        
        public function send_request($url, $params) {
            $args = array(
                'body' => json_encode($params),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 60,
            );
            
            $response = wp_remote_post($url, $args);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            } else {
                if ($this->enable_logging == 'yes') {
                    $this->log('Duitku QRIS Request Error: ' . $response->get_error_message());
                }
                return false;
            }
        }
    }

    function add_duitku_qris_gateway($methods) {
        $methods[] = 'WC_Duitku_QRIS_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_duitku_qris_gateway');

    function duitku_qris_check_payment_status_ajax() {
        check_ajax_referer('duitku_qris_check_payment_nonce', 'security');
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        $paid = $order->is_paid();
        $expired = false;
        
        if (!$paid) {
            $expiry = $order->get_meta('_duitku_expiry');
            if ($expiry && time() > $expiry && $order->has_status('pending')) {
                sleep(rand(1, 5));
                wc_increase_stock_levels($order->get_id());
                $order->update_status(
                    'cancelled', 
                    sprintf(
                        'QRIS payment expired (expiry time: %s)',
                        date('Y-m-d H:i:s', $expiry)
                    )
                );
                $expired = true;
            }
        }
        
        wp_send_json_success(array(
            'paid' => $paid,
            'expired' => $expired,
            'message' => $paid ? 'Payment received' : 
                      ($expired ? 'Payment time expired' : 'Waiting for payment')
        ));
    }

    add_action('wp_ajax_duitku_qris_check_payment', 'duitku_qris_check_payment_status_ajax');
    add_action('wp_ajax_nopriv_duitku_qris_check_payment', 'duitku_qris_check_payment_status_ajax');
}