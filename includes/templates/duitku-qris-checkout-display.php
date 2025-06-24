<?php
/**
 * QRIS payment display template
 * 
 * This template can be overridden by copying it to yourtheme/woocommerce/duitku-qris-checkout-display.php
 */

defined('ABSPATH') || exit;
?>

<div class="duitku-qris-container">
    <div class="duitku-qris-header">
        <h2>Silahkan Scan QRIS berikut ini</h2>
    </div>

    <div class="duitku-qris-amount">
        Nominal Pembayaran: <?php echo wc_price($amount); ?>
    </div>

    <div id="duitku-qris-qrcode"></div>

    <div id="duitku-qris-timer"></div>

    <div id="duitku-qris-status"></div>

    <div class="duitku-qris-instructions">
        <h3>Cara Pembayaran:</h3>
        <ol>
            <li>Buka aplikasi e-wallet atau mobile banking yang mendukung QRIS</li>
            <li>Pilih menu Scan QR atau QRIS</li>
            <li>Scan QR Code yang ditampilkan di atas</li>
            <li>Periksa detail transaksi pada aplikasi Anda</li>
            <li>Masukkan PIN atau password untuk konfirmasi pembayaran</li>
            <li>Pembayaran selesai, halaman akan otomatis dialihkan</li>
        </ol>
    </div>
</div>

<script type="text/javascript">
    var qrString = '<?php echo esc_js($qr_string); ?>';
    var orderId = '<?php echo esc_js($order_id); ?>';
    var expiryTime = <?php echo esc_js($expiry_time); ?>;
</script>
