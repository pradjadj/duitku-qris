(function($) {
    'use strict';

    // QR Code display and payment checking functionality
    var DuitkuQRIS = {
        init: function() {
            this.qrContainer = document.getElementById('duitku-qris-qrcode');
            this.statusContainer = document.getElementById('duitku-qris-status');
            this.timerContainer = document.getElementById('duitku-qris-timer');
            
            if (this.qrContainer && typeof qrString !== 'undefined') {
                this.generateQR();
                this.startPaymentCheck();
                this.startExpiryTimer();
            }
        },

        generateQR: function() {
            new QRCode(this.qrContainer, {
                text: qrString,
                width: 256,
                height: 256,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        },

        startPaymentCheck: function() {
            var self = this;
            this.checkInterval = setInterval(function() {
                self.checkPaymentStatus();
            }, 3000); // Check every 3 seconds
        },

        checkPaymentStatus: function() {
            var self = this;
            $.ajax({
                url: duitkuQrisParams.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'check_duitku_payment',
                    order_id: orderId,
                    nonce: duitkuQrisParams.nonce
                },
                success: function(response) {
                    if (response.success && response.data.status) {
                        clearInterval(self.checkInterval);
                        clearInterval(self.timerInterval);
                        
                        // Show success message
                        if (self.statusContainer) {
                            self.statusContainer.innerHTML = '<div class="woocommerce-notice woocommerce-notice--success">Pembayaran berhasil! Mengalihkan...</div>';
                        }
                        
                        // Redirect to thank you page
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    }
                }
            });
        },

        startExpiryTimer: function() {
            if (!this.timerContainer || typeof expiryTime === 'undefined') {
                return;
            }

            var self = this;
            this.timerInterval = setInterval(function() {
                var now = Math.floor(Date.now() / 1000);
                var timeLeft = expiryTime - now;

                if (timeLeft <= 0) {
                    clearInterval(self.timerInterval);
                    clearInterval(self.checkInterval);
                    self.timerContainer.innerHTML = 'Waktu pembayaran telah berakhir';
                    if (self.statusContainer) {
                        self.statusContainer.innerHTML = '<div class="woocommerce-notice woocommerce-notice--error">Waktu pembayaran telah berakhir. Silakan buat pesanan baru.</div>';
                    }
                    return;
                }

                var minutes = Math.floor(timeLeft / 60);
                var seconds = timeLeft % 60;
                self.timerContainer.innerHTML = 'Sisa waktu: ' + 
                    minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }, 1000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DuitkuQRIS.init();
    });

})(jQuery);
