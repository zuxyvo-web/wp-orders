==== Woocommerce Marketplace Split Order ====
Contributors: Webkul
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 1.2.0
Requires PHP: 7.4
Tested up to PHP: 8.3
WC Requires at least : 9.7
WC tested up to: 9.9
MP Requires at least : 6.0
MP tested up to: 6.2
Tags: woocommerce, marketplace, split, order, seller order, vendor order, order splitting
License: See license.txt included with plugin.
License URI: https://store.webkul.com/license.html

Woocommerce Marketplace Split Order

== Description ==

WordPress WooCommerce Marketplace Split Order**  With the help of this Plugin, separate order ids will be generated while placing the order if there are multiple seller products in the shopping cart. The customer and admin receive separate order ids for every seller. Returns, Refunds & Replacements can be managed very easily when all the sellers have their unique order ids. It gives more clarity in the communication between a seller and a buyer.

== Installation ==

1. Upload the wp-marketplace-split-order folder to the /wp-content/plugins/ directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. You need to have WooCommerce and Marketplace installed in order to get this plugin working.

== Frequently Asked Questions ==

*No questions asked yet*

For any Query please generate a ticket at https://webkul.uvdesk.com/en/

== 1.2.0 (2025-06-30) ==
Fixed: Order was not splitting in case of variable products of vendors in the card.
Fixed: A fatal error on order thank you page due to old marketplace version dependency.
Fixed: Code formatting and optimized it to the latest WordPress and WooCommerce standards.
Removed: Unused commission and reward handling from this module, they will be handled by the marketplace and respective modules.

== 1.1.6 (2025-02-04) ==
Added: Compatibility with WooCommerce Cart and Checkout blocks.
Added: All types of payment gateway support.
Updated: Code according to WordPress and WooCommerce coding standards.

== 1.1.5 (2024-04-25) ==
Added: HPOS compatibility.
Resolved: PHPCS issues.
Resolved: Directories structure issues.

= 1.1.4=
Added: Auto added a note in the main order when the seller changes the suborder status.
Fixed: Functionality is not working for the Guest user.
Fixed: Hide notification about the main order to the seller.
Fixed: shows the Main order instead of suborders on the seller's end
Fixed: Shipping details are not showing on the Seller end in the suborder
Fixed: Seller access the main order

= 1.1.3=
* Improve coding standard.
* Manage seller split order.

= 1.1.2 =
* Admin can show suborders in order list.
* Work on seller shipping.
* Manage each seller wise products.

= 1.1.1 =

* Updated the flow of plugin.
* Fixed security issues.

= 1.1.0 =
Fixed compatibility issue with WooCommerce 3.4

= 1.0.2 =
Update the splitting of shipping cost

= 1.0.1 =
Woocommerce 3.0 compatible

= 1.0.0 =
Initial release
