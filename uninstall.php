<?php
/**
 * Duitku QRIS Gateway Uninstaller
 * Cleans up all plugin data when uninstalled
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Include the main plugin file to access the gateway class
if (!class_exists('WC_Duitku_QRIS_Gateway')) {
    require_once plugin_dir_path(__FILE__) . 'duitku-qris-gateway.php';
}

// Initialize the gateway class to access its properties
if (class_exists('WC_Duitku_QRIS_Gateway')) {
    $gateway = new WC_Duitku_QRIS_Gateway();
    
    // Remove all plugin options
    delete_option('woocommerce_' . $gateway->id . '_settings');
    delete_option('duitku_qris_version');
    
    // Remove transients
    delete_transient('duitku_qris_activation_redirect');
    
    // Remove scheduled events
    wp_clear_scheduled_hook('duitku_qris_check_payments');
    
    // Remove custom database tables (if any)
    global $wpdb;
    
    $tables = [
        $wpdb->prefix . 'duitku_transactions',
        $wpdb->prefix . 'duitku_logs'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Remove all meta data from orders
    $meta_keys = [
        '_duitku_reference',
        '_duitku_qr_string',
        '_duitku_expiry',
        '_duitku_callback_data',
        '_duitku_payment_method'
    ];
    
    foreach ($meta_keys as $meta_key) {
        $wpdb->delete(
            $wpdb->postmeta,
            ['meta_key' => $meta_key],
            ['%s']
        );
    }
    
    // Alternative broad cleanup for any remaining duitku meta
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} 
        WHERE meta_key LIKE '_duitku_%'"
    );
    
    // Remove log files
    $upload_dir = wp_upload_dir();
    $log_dir = trailingslashit($upload_dir['basedir']) . 'duitku-logs';
    
    if (file_exists($log_dir)) {
        array_map('unlink', glob("{$log_dir}/*.log"));
        @rmdir($log_dir);
    }
    
    // Remove WooCommerce system logs
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}wc_admin_notes
        WHERE source = 'duitku-qris'"
    );
    
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}wc_admin_note_actions
        WHERE note_id IN (
            SELECT note_id FROM {$wpdb->prefix}wc_admin_notes
            WHERE source = 'duitku-qris'
        )"
    );
    
    // Remove any cron jobs
    wp_clear_scheduled_hook('duitku_qris_daily_maintenance');
    
    // Remove plugin files (optional - use with caution)
    if (defined('DUITKU_REMOVE_ALL_FILES_ON_UNINSTALL') && DUITKU_REMOVE_ALL_FILES_ON_UNINSTALL) {
        $plugin_dir = plugin_dir_path(__FILE__);
        
        $files_to_remove = [
            'duitku-qris-gateway.php',
            'uninstall.php',
            'README.md',
            'duitku-qris.js',
            'includes/',
            'assets/',
            'languages/',
            'vendor/'
        ];
        
        foreach ($files_to_remove as $file) {
            $path = $plugin_dir . $file;
            
            if (is_dir($path)) {
                $iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
                
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        @rmdir($file->getRealPath());
                    } else {
                        @unlink($file->getRealPath());
                    }
                }
                
                @rmdir($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }
        
        // Finally remove the plugin directory itself
        @rmdir($plugin_dir);
    }
} else {
    // Fallback cleanup if gateway class can't be loaded
    global $wpdb;
    
    // Remove options
    delete_option('woocommerce_duitku_qris_settings');
    delete_option('duitku_qris_version');
    
    // Remove transients
    delete_transient('duitku_qris_activation_redirect');
    
    // Remove meta
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} 
        WHERE meta_key LIKE '_duitku_%'"
    );
    
    // Remove scheduled events
    wp_clear_scheduled_hook('duitku_qris_check_payments');
    wp_clear_scheduled_hook('duitku_qris_daily_maintenance');
}