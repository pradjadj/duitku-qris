<?php
namespace DuitkuQris;

class Callback_Handler {
    private $logger;

    public function __construct() {
        $this->logger = wc_get_logger();
    }

    public function process() {
        try {
            $this->validate_request();
            $this->handle_callback();
            echo "OK"; // Required response for Duitku
            exit;
        } catch (\Exception $e) {
            $this->log('Callback Error: ' . $e->getMessage());
            header('HTTP/1.1 400 Bad Request');
            echo $e->getMessage();
            exit;
        }
    }

    private function validate_request() {
        // Verify this is a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception('Invalid request method');
        }

        // Get and validate required parameters
        $required_params = [
            'merchantCode',
            'amount',
            'merchantOrderId',
            'signature'
        ];

        foreach ($required_params as $param) {
            if (!isset($_POST[$param])) {
                throw new \Exception("Missing parameter: {$param}");
            }
        }

        // Get gateway settings
        $gateway = new \WC_Gateway_Duitku_QRIS();
        $settings = $gateway->get_instance_settings();

        // Verify merchant code
        if ($_POST['merchantCode'] !== $settings['merchant_code']) {
            throw new \Exception('Invalid merchant code');
        }

        // Verify signature
        $calculated_signature = md5($settings['merchant_code'] . $_POST['amount'] . $_POST['merchantOrderId'] . $settings['api_key']);
        if ($_POST['signature'] !== $calculated_signature) {
            throw new \Exception('Invalid signature');
        }
    }

    private function handle_callback() {
        // Extract order ID from merchantOrderId (remove 'TRX-' prefix)
        $merchant_order_id = $_POST['merchantOrderId'];
        $order_id = str_replace('TRX-', '', $merchant_order_id);
        
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \Exception('Order not found: ' . $order_id);
        }

        // Get result code
        $result_code = isset($_POST['resultCode']) ? $_POST['resultCode'] : null;

        switch ($result_code) {
            case '00': // Success
                if ($order->get_status() === 'pending') {
                    // Store Duitku reference
                    $reference = isset($_POST['reference']) ? $_POST['reference'] : '';
                    update_post_meta($order->get_id(), '_duitku_reference', $reference);

                    // Complete the order
                    $order->payment_complete($reference);
                    $order->add_order_note(sprintf(
                        __('Payment completed via Duitku QRIS (Reference: %s)', 'woocommerce'),
                        $reference
                    ));

                    // Log the successful payment
                    $this->log(sprintf(
                        'Payment successful for order %s. Duitku Reference: %s',
                        $order_id,
                        $reference
                    ));
                }
                break;

            case '01': // Failed
                if ($order->get_status() === 'pending') {
                    $order->update_status('failed', __('Payment failed', 'woocommerce'));
                    $this->log(sprintf('Payment failed for order %s', $order_id));
                }
                break;

            default:
                throw new \Exception('Invalid result code: ' . $result_code);
        }

        // Store additional payment details
        if (isset($_POST['issuerCode'])) {
            update_post_meta($order->get_id(), '_duitku_issuer_code', $_POST['issuerCode']);
        }
        if (isset($_POST['settlementDate'])) {
            update_post_meta($order->get_id(), '_duitku_settlement_date', $_POST['settlementDate']);
        }
    }

    private function log($message) {
        $this->logger->debug($message, array('source' => 'duitku_qris'));
    }
}
