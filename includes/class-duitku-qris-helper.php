<?php
namespace DuitkuQris;

class Helper {
    private static $logger = null;

    /**
     * Initialize the logger
     */
    private static function init_logger() {
        if (is_null(self::$logger)) {
            self::$logger = wc_get_logger();
        }
    }

    /**
     * Log message to WooCommerce logs
     */
    public static function log($message, $type = 'info') {
        self::init_logger();
        self::$logger->log($type, $message, ['source' => 'duitku_qris']);
    }

    /**
     * Format amount to Duitku format (no decimal points)
     */
    public static function format_amount($amount) {
        return number_format($amount, 0, '', '');
    }

    /**
     * Generate merchant order ID
     */
    public static function generate_merchant_order_id($order_id) {
        return 'TRX-' . $order_id;
    }

    /**
     * Calculate signature
     */
    public static function calculate_signature($merchant_code, $order_id, $amount, $api_key) {
        return md5($merchant_code . $order_id . $amount . $api_key);
    }

    /**
     * Get API endpoint URL based on environment
     */
    public static function get_api_url($environment, $endpoint) {
        $base_url = $environment === 'production' 
            ? 'https://passport.duitku.com/webapi/api/merchant'
            : 'https://sandbox.duitku.com/webapi/api/merchant';
        
        return $base_url . '/' . ltrim($endpoint, '/');
    }

    /**
     * Make API request to Duitku
     */
    public static function make_api_request($url, $body, $timeout = 30) {
        $response = wp_remote_post($url, [
            'body' => json_encode($body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            self::log('API Request Error: ' . $response->get_error_message(), 'error');
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        
        if (!$body) {
            self::log('API Response Error: Invalid JSON response', 'error');
            throw new \Exception('Invalid API response');
        }

        return $body;
    }

    /**
     * Get available QRIS providers
     */
    public static function get_qris_providers() {
        return [
            'SP' => 'QRIS ShopeePay',
            'NQ' => 'QRIS NobuBank',
            'DQ' => 'QRIS DANA',
            'GQ' => 'QRIS Gudang Voucher',
            'SQ' => 'QRIS Nusapay'
        ];
    }

    /**
     * Format expiry time for display
     */
    public static function format_expiry_time($timestamp) {
        return date_i18n('j F Y H:i:s', $timestamp);
    }

    /**
     * Check if order is expired
     */
    public static function is_order_expired($order) {
        $expiry_time = get_post_meta($order->get_id(), '_duitku_expiry_time', true);
        return $expiry_time && time() > $expiry_time;
    }

    /**
     * Validate callback parameters
     */
    public static function validate_callback_params($required_params, $data) {
        foreach ($required_params as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                throw new \Exception("Missing or empty parameter: {$param}");
            }
        }
        return true;
    }

    /**
     * Get callback URL
     */
    public static function get_callback_url() {
        return add_query_arg('duitku_callback', '1', home_url('/'));
    }

    /**
     * Get API endpoints
     */
    public static function get_endpoints() {
        return [
            'inquiry' => [
                'sandbox' => 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry',
                'production' => 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
            ],
            'transaction_status' => [
                'sandbox' => 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus',
                'production' => 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
            ]
        ];
    }

    /**
     * Handle API errors
     */
    public static function handle_api_error($response) {
        $message = isset($response->statusMessage) ? $response->statusMessage : 'Unknown error';
        $code = isset($response->statusCode) ? $response->statusCode : '99';
        
        self::log("API Error: [{$code}] {$message}", 'error');
        throw new \Exception($message);
    }

    /**
     * Update order status based on payment result
     */
    public static function update_order_status($order, $result_code, $reference = '') {
        switch ($result_code) {
            case '00':
                // Payment success
                if ($order->get_status() === 'pending') {
                    $order->payment_complete($reference);
                    $order->add_order_note(sprintf(
                        __('Payment completed via Duitku QRIS (Reference: %s)', 'woocommerce'),
                        $reference
                    ));
                }
                break;
                
            case '01':
                // Payment failed
                if ($order->get_status() === 'pending') {
                    $order->update_status('failed', __('Payment failed', 'woocommerce'));
                }
                break;
                
            default:
                // Unknown status
                self::log("Unknown payment result code: {$result_code}", 'warning');
                break;
        }
    }
}
