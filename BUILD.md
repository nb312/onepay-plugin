# OnePay WooCommerce Blocks Development

## Building React Components

To build the React components for WooCommerce Blocks checkout support:

### Prerequisites

1. Install Node.js (version 14 or higher)
2. Navigate to the plugin directory

### Build Steps

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Or build for development with watch mode
npm run start
```

### Files Generated

After building, the following files will be created:
- `assets/js/onepay-blocks.js` - Compiled React components
- `assets/js/onepay-blocks.asset.php` - WordPress script dependencies

### Development Notes

- The source file is `assets/js/onepay-blocks.js` (written in modern JavaScript/React)
- The build process compiles it for browser compatibility
- CSS is in `assets/css/onepay-blocks.css` (no build needed)

### Alternative for Testing

If you don't have Node.js installed, the current JavaScript file should work as-is in modern browsers, but building is recommended for production use to ensure maximum compatibility.

## Blocks Checkout Features

The OnePay blocks integration provides:

- ✅ React-based payment method selection
- ✅ Support for FPS and Card Payment methods  
- ✅ Integration with WooCommerce Store API
- ✅ Responsive design for mobile/desktop
- ✅ Editor preview in block editor
- ✅ Full compatibility with WooCommerce Blocks

## Usage

Once built and activated, OnePay will automatically work with:
- Traditional WooCommerce checkout (shortcode-based)
- Modern WooCommerce Blocks checkout
- WooCommerce block editor

No additional configuration needed - the plugin detects the checkout type automatically.