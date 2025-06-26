# Duitku QRIS Gateway for WooCommerce

A WooCommerce payment gateway integration for Duitku QRIS that allows customers to pay using QRIS directly on the checkout page without redirection.

## Features

- Direct QRIS payment on checkout/order received page
- Supports multiple QRIS providers:
  - QRIS ShopeePay (SP)
  - QRIS NobuBank (NQ)
  - QRIS DANA (DQ)
  - QRIS Gudang Voucher (GQ)
  - QRIS Nusapay (SQ)
- Real-time payment status updates (3-second intervals)
- Automatic order status management
- Configurable payment expiry time
- Error logging in WooCommerce System Status
- Modern black & white UI design
- Compatible with HPOS (High-Performance Order Storage)
- Supports PHP 8.0+
- Compatible with WooCommerce 6.8+ and WordPress 6.8+

## Requirements

- PHP version 8.0 or higher
- WordPress 6.8 or higher
- WooCommerce 6.8 or higher
- SSL Certificate (for production use)
- Duitku merchant account and API credentials

## Installation

1. Upload the plugin files to `/wp-content/plugins/duitku-qris-gateway` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments
4. Click on "Duitku QRIS" to configure the payment gateway

## Configuration

### Required Settings

1. **Merchant Code**: Your Duitku merchant code
2. **API Key**: Your Duitku API key
3. **Environment**: Choose between Sandbox (testing) and Production
4. **QRIS Provider**: Select your preferred QRIS provider
5. **Expiry Period**: Set the payment expiry time in minutes

### Optional Settings

- **Enable Logging**: Toggle error logging in WooCommerce System Status
- **Title**: Change the payment method title shown to customers
- **Description**: Modify the payment method description

## Callback URL

Configure your Duitku merchant account with this callback URL:
`[your-site-url]/?duitku_callback=1`

## Testing

### Sandbox Testing
1. Set Environment to "Sandbox"
2. Use the sandbox credentials from your Duitku account
3. Test transactions using the QRIS test QR codes provided by Duitku

### Production
1. Set Environment to "Production"
2. Update the credentials with your live Duitku merchant account details
3. Perform a test transaction to ensure everything works correctly

## Error Logging

Errors are logged in WooCommerce > Status > Logs with the source "duitku_qris"

## Order Statuses

- **Pending Payment**: Initial status when QRIS code is generated
- **Processing/Completed**: After successful payment
- **Cancelled**: When payment expires
- **Failed**: If payment fails

## Support

For support:
- Email: support@sgnet.co.id
- Documentation: https://docs.duitku.com/api/id/?php#permintaan-transaksi

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
