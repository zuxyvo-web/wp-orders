<?php
/**
 * Plugin Name: MarketPlace Split Order
 * Plugin URI: https://codecanyon.net/item/wordpress-woocommerce-marketplace-split-order-plugin/19466195
 * Description: MarketPlace Split Order helps to filter the vendor order in a simple way.
 * Version: 1.2.0
 * Author: Webkul
 * Author URI:  https://webkul.com/
 * Text Domain: mp-split-order
 * Domain Path: /languages
 *
 * Requires at least: 6.7
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Tested up to PHP: 8.3
 * WC requires at least: 9.7
 * WC tested up to: 9.9
 * MP Requires at least : 6.0
 * MP tested up to: 6.2
 * WKLWDT: 202506301310
 *
 * Store URI: https://store.webkul.com/woocommerce-multi-vendor-split-order.html
 * Blog URI: http://webkul.com/blog/wordpress-woocommerce-marketplace-split-order-plugin/
 *
 * @package MarketPlace Split Order
 */

// WKPCCS: Webkul MarketPlace Split Order.
defined( 'ABSPATH' ) || exit();

// Define Constants.
defined( 'WKMPSO_PLUGIN_FILE' ) || define( 'WKMPSO_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
defined( 'WKMPSO_PLUGIN_BASENAME' ) || define( 'WKMPSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load core auto-loader.
require_once __DIR__ . '/autoload/class-wkmpso-autoload.php';

// Load Final webar class.
require_once __DIR__ . '/includes/class-wkmarketplace-split-order.php';

/**
 * Returns the main instance of WC.
 *
 * @return WKMarketplace_Split_Order
 */
function wk_mpso() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WKMarketplace_Split_Order::instance();
}

// Global for backwards compatibility.
$GLOBALS['wk_mpso'] = wk_mpso();
