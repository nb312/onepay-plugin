# OnePay Payment Gateway for WooCommerce

A secure payment gateway plugin for WooCommerce that integrates with OnePay's Russian payment system, supporting FPS (SBP) and card payments with RSA signature verification.

## Features

- **Multiple Payment Methods**: Supports FPS (Fast Payment System) and Card Payment methods
- **RSA Signature Security**: MD5withRSA signature generation and verification for all transactions
- **Comprehensive Logging**: Detailed logging system for debugging and monitoring
- **Callback Processing**: Handles asynchronous payment notifications with idempotency protection
- **Admin Interface**: Full admin configuration panel with testing tools
- **Order Management**: Enhanced order management with OnePay-specific details
- **Multi-Currency Support**: Supports RUB, USD, and EUR currencies

## Installation

1. **Upload the Plugin**
   - Upload the `onepay` folder to `/wp-content/plugins/`
   - Or install via WordPress admin panel

2. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "OnePay Payment Gateway" and click "Activate"

3. **Configure WooCommerce**
   - Go to WooCommerce → Settings → Payments
   - Find "OnePay" and click "Set up"

## Configuration

### Basic Settings

1. **Enable/Disable**: Enable the OnePay payment gateway
2. **Title**: Display title for customers (e.g., "OnePay")
3. **Description**: Payment method description shown at checkout
4. **Test Mode**: Enable for testing with sandbox environment

### API Configuration

1. **Merchant Number**: Your OnePay merchant number
2. **Private RSA Key**: Your RSA private key for signing requests
3. **Platform Public Key**: OnePay's public key for verifying responses
4. **API URLs**: Live and test endpoint URLs

### RSA Keys

Generate RSA key pairs using OpenSSL:

```bash
# Generate private key
openssl genrsa -out private_key.pem 2048

# Extract public key
openssl rsa -in private_key.pem -pubout -out public_key.pem
```

Provide your public key to OnePay and obtain their public key for configuration.

## OnePay Integration Setup

### Callback URLs

Configure these URLs in your OnePay merchant account:

- **Callback URL**: `https://yoursite.com/?wc-api=onepay_callback`
- **Return URL**: `https://yoursite.com/?wc-api=onepay_return`

### Payment Flow

1. Customer selects OnePay payment method
2. Customer chooses payment type (FPS or Card)
3. Order is created and customer is redirected to OnePay
4. Customer completes payment on OnePay platform
5. OnePay sends callback notification to your site
6. Order status is updated based on payment result

## Testing

The plugin includes comprehensive testing tools:

1. **Connection Test**: Verify API endpoint connectivity
2. **Key Validation**: Test RSA key format and signature operations
3. **Full Test Suite**: Run all integration tests

Access testing tools via WooCommerce → Settings → Payments → OnePay → "Run Full Tests"

## Supported Payment Methods

### FPS (SBP - Fast Payment System)
- Minimum: 1 RUB
- Maximum: User account limit
- Real-time payments through Russian banking system

### Card Payment
- Minimum: 1 RUB  
- Maximum: Card limit
- Supports major card networks

## Error Handling

The plugin includes comprehensive error handling:

- **Signature Verification**: All callbacks are signature-verified
- **Idempotency**: Prevents duplicate payment processing
- **Retry Logic**: OnePay retries failed callbacks up to 7 times
- **Logging**: Detailed logs for troubleshooting

## Callback Processing

OnePay sends callbacks for payment status updates:

- **Success**: Order marked as completed
- **Pending**: Order kept in pending status
- **Failed**: Order marked as failed

The plugin responds with "SUCCESS" or "ERROR" to indicate processing status.

## Security Features

- **RSA Signatures**: MD5withRSA signature verification
- **Data Sanitization**: All input data is sanitized
- **Nonce Verification**: WordPress nonce verification for admin actions
- **Key Protection**: Private keys are stored securely in WordPress options

## Logging and Debugging

Enable debug logging in plugin settings to capture:

- API requests and responses
- Callback processing
- Signature operations
- Payment status changes
- Error conditions

Logs are stored in WooCommerce logs and can be viewed via WooCommerce → Status → Logs.

## Troubleshooting

### Common Issues

1. **Signature Verification Failed**
   - Check RSA key format and validity
   - Ensure correct public/private key pair
   - Verify key exchange with OnePay

2. **Callbacks Not Received**
   - Verify callback URL is publicly accessible
   - Check server firewall settings
   - Review WordPress permalink settings

3. **Orders Not Updating**
   - Check callback processing logs
   - Verify merchant number configuration
   - Ensure WooCommerce permissions

### Support

For technical support:
1. Check plugin logs in WooCommerce → Status → Logs
2. Run the built-in test suite to identify issues
3. Review OnePay merchant dashboard for transaction details

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+
- PHP OpenSSL extension
- PHP cURL extension

## Version History

- **1.0.0**: Initial release with full OnePay integration

## License

GPL v2 or later