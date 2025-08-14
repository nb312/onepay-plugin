const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'onepay-blocks': './assets/js/onepay-blocks.js',
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/'),
        filename: '[name].js',
    },
    externals: {
        '@wordpress/element': 'wp.element',
        '@wordpress/html-entities': 'wp.htmlEntities',
        '@wordpress/i18n': 'wp.i18n',
        '@woocommerce/blocks-registry': 'wc.wcBlocksRegistry',
        '@woocommerce/settings': 'wc.wcSettings',
        'react': 'React',
        'react-dom': 'ReactDOM'
    }
};