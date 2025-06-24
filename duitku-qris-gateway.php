<?php
/**
 * Plugin Name: Duitku QRIS Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: Duitku Payment Gateway with QRIS for WooCommerce - Display QRIS directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 * Requires PHP: 8.0
 * WC requires at least: 6.8
 * WC tested up to: 9.8
 */

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('DUITKU_QRIS_VERSION', '1.0');
define('DUITKU_QRIS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DUITKU_QRIS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'DuitkuQris\\';
    $base_dir = DUITKU_QRIS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function duitku_qris_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include main gateway class
    require_once DUITKU_QRIS_PLUGIN_DIR . 'includes/class-wc-gateway-duitku-qris.php';
    
    // Add gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'duitku_qris_add_gateway');
}
add_action('plugins_loaded', 'duitku_qris_init');

// Register the gateway
function duitku_qris_add_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Duitku_QRIS';
    return $gateways;
}

// Add settings link on plugin page
function duitku_qris_plugin_links($links) {
    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=duitku_qris');
    $plugin_links = array(
        '<a href="' . $settings_url . '">' . __('Settings') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'duitku_qris_plugin_links');

// Handle callback
function duitku_qris_handle_callback() {
    if (isset($_GET['duitku_callback']) && $_GET['duitku_callback'] === '1') {
        require_once DUITKU_QRIS_PLUGIN_DIR . 'includes/class-duitku-qris-callback-handler.php';
        $handler = new DuitkuQris\Callback_Handler();
        $handler->process();
        exit;
    }
}
add_action('init', 'duitku_qris_handle_callback');

// Register AJAX handlers
function duitku_qris_ajax_handlers() {
    add_action('wp_ajax_check_duitku_payment', 'duitku_qris_check_payment');
    add_action('wp_ajax_nopriv_check_duitku_payment', 'duitku_qris_check_payment');
}
add_action('init', 'duitku_qris_ajax_handlers');

// AJAX callback for checking payment status
function duitku_qris_check_payment() {
    check_ajax_referer('duitku_qris_check_payment', 'nonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $gateway = new WC_Gateway_Duitku_QRIS();
    $status = $gateway->check_payment_status($order);

    wp_send_json_success([
        'status' => $status,
        'redirect' => $order->get_checkout_order_received_url()
    ]);
}

// Enqueue scripts and styles
function duitku_qris_enqueue_scripts() {
    if (is_checkout() && is_wc_endpoint_url('order-received')) {
        wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], '1.0.0', true);
        wp_enqueue_script('duitku-qris-checkout', DUITKU_QRIS_PLUGIN_URL . 'assets/js/duitku-qris-checkout.js', ['jquery', 'qrcode-js'], DUITKU_QRIS_VERSION, true);
        wp_enqueue_style('duitku-qris-style', DUITKU_QRIS_PLUGIN_URL . 'assets/css/duitku-qris.css', [], DUITKU_QRIS_VERSION);
        
        // Localize script
        wp_localize_script('duitku-qris-checkout', 'duitkuQrisParams', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('duitku_qris_check_payment')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'duitku_qris_enqueue_scripts');
