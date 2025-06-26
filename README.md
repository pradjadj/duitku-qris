# Duitku QRIS Gateway for WooCommerce

Plugin WordPress untuk integrasi payment gateway Duitku dengan WooCommerce, mendukung QRIS dari 5 Provider yang didukung Duitku.

## Fitur

- Memunculkan QRIS dihalaman checkout langsung tanpa redirect ke halaman Duitku
- Pilihan QRIS providers yang didukung Duitku:
  - QRIS ShopeePay (SP)
  - QRIS NobuBank (NQ)
  - QRIS DANA (DQ)
  - QRIS Gudang Voucher (GQ)
  - QRIS Nusapay (SQ)
- Real-time payment status updates per 3 detik
- Otomatis mengganti status order ke Processing atau langsung ke Completed setelah pembayaran
- Setting waktu Payment Expired
- Error logging di WooCommerce System Status
- Plugin Kompatibel dengan HPOS (High-Performance Order Storage)
- Supports PHP 8.0+
- Kompatibel dengan WooCommerce 6.8+ and WordPress 6.8+

## Persyaratan

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Akun merchant Duitku aktif

## Instalasi

1. Download dan Upload plugin ke `/wp-content/plugins/duitku-qris`
2. Aktifkan plugin melalui WordPress admin
3. Konfigurasi settingan di WooCommerce > Settings > Payments > "Duitku QRIS"

## Konfigurasi

### Settingan yang dibutuhkan

1. **Merchant Code**: Your Duitku merchant code
2. **API Key**: Your Duitku API key
3. **Environment**: Choose between Sandbox (testing) and Production
4. **QRIS Provider**: Select your preferred QRIS provider
5. **Merchant Order ID Prefix**: Prefix for tracking your Transaction at Duitku Dashboard, like TRX-12345
6. **Expiry Period**: Set the payment expiry time in minutes
7. **Order Status After Payment**: Choose the order status after successful payment. Processing or directly to Completed after Payment

### Settingan tambahan

- **Title**: Change the payment method title shown to customers
- **Description**: Modify the payment method description
- **Enable Logging**: Toggle error logging in WooCommerce System Status

## Testing

### Sandbox Testing
1. Ganti Environment ke "Sandbox"
2. Pakai credential Sandbox Duitku
3. Test transakti pakai Provider QRIS ShopeePay dan jangan lupa aktifkan QRIS ShopeePay di Dashboard Sandbox Duitku

### Production Live
1. Ganti Environment ke "Production"
2. Pakai credential Prodiction Duitku
3. Jalankan transaksi QRIS dan pilih Provider QRIS yang aktif di Dashboard Production Duitku

## Error Logging

Cek Error Log di WooCommerce > Status > Logs dengan nama source "duitku_qris"

## Status Order

- **Pending Payment**: QRIS Belum terbayar
- **Processing/Completed**: Pembayaran diterima
- **Cancelled**: Pembayaran Dibatalkan karena Expired
- **Failed**: Pesanan Gagal

## Support

Untuk dukungan plugin bisa hubungi:
- Email: support@sgnet.co.id
- Dokumentasi link Duitku di: https://docs.duitku.com/api/id/?php#permintaan-transaksi

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## Changelog

### 1.0
- Initial release
- Direct QRIS display on checkout
- Support for 5 QRIS providers
- Real-time payment status updates
- Modern black & white UI
- HPOS compatibility
