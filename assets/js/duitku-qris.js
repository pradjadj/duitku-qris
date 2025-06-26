/**
 * Duitku QRIS Payment Gateway JavaScript
 * Handles the QRIS payment flow and status checking
 */

jQuery(document).ready(function($) {
    // Initialize QR Code and payment status checking if on order received page
    if ($('#duitku-qris-payment').length) {
        const qrString = $('#qrcode').data('qrstring');
        const orderId = $('#duitku-payment-status').data('order-id') || 0;
        const checkInterval = duitku_qris_params.check_interval || 3000;

        // Generate QR Code without tooltip
        if (qrString && typeof QRCode !== 'undefined') {
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: qrString,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });

            // Remove title attribute to prevent tooltip on hover
            $('#qrcode').removeAttr('title');
            
            // Also remove any title elements that might be added by the library
            $('#qrcode').find('title').remove();
        }

        // Function to check payment status
        const checkPaymentStatus = function() {
            if (!orderId) return;

            $.ajax({
                url: duitku_qris_params.ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_duitku_payment_status',
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.paid) {
                            // Payment completed
                            $('#duitku-payment-status').html(
                                '<div class="woocommerce-message">' + 
                                'Payment received. Thank you! Page will refresh shortly...' +
                                '</div>'
                            );
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else if (response.data.expired) {
                            // Payment expired
                            $('#duitku-payment-status').html(
                                '<div class="woocommerce-error">' + 
                                'Payment expired. Please place a new order.' +
                                '</div>'
                            );
                            clearInterval(statusInterval);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking payment status:', error);
                }
            });
        };

        // Start checking payment status at intervals
        const statusInterval = setInterval(checkPaymentStatus, checkInterval);
        
        // Check immediately on page load
        checkPaymentStatus();

        // Add click handler for manual refresh button if exists
        $(document).on('click', '#duitku-refresh-status', function(e) {
            e.preventDefault();
            checkPaymentStatus();
        });
    }

    // Additional payment method selection handling if needed
    $('form.checkout').on('change', 'input[name="payment_method"]', function() {
        if ($(this).val() === 'duitku_qris') {
            // Custom handling when Duitku QRIS is selected
            // For example, you could show additional information
        }
    });
});