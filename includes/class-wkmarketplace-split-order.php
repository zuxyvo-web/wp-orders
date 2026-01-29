<?php
/**
 * Final Class
 *
 * @package MarketPlace Split Order
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Main WKMarketplace_Split_Order Class.
 *
 * @class WKMarketplace_Split_Order
 */
final class WKMarketplace_Split_Order {
	/**
	 * The single instance of the class.
	 *
	 * @var $instance
	 * @since 1.0
	 */
	protected static $instance = null;

	/**
	 * Main WKMarketplace_Split_Order Instance.
	 *
	 * Ensures only one instance of WKMarketplace_Split_Order is loaded or can be loaded.
	 *
	 * @static
	 *
	 * @return WKMarketplace_Split_Order - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * MP_Split_Order Constructor.
	 */
	public function __construct() {
		$this->wkmpso_define_constants();
		$this->wkmpso_init_hooks();
	}

	/**
	 * Define MP_Split_Order Constants.
	 */
	private function wkmpso_define_constants() {
		defined( 'WKMPSO_SCRIPT_VERSION' ) || define( 'WKMPSO_SCRIPT_VERSION', '1.0.3' );
		defined( 'WKMPSO_ABSPATH' ) || define( 'WKMPSO_ABSPATH', dirname( WKMPSO_PLUGIN_FILE ) . '/' );
		defined( 'WKMPSO_PLUGIN_URL' ) || define( 'WKMPSO_PLUGIN_URL', plugin_dir_url( WKMPSO_PLUGIN_FILE ) );
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @return void
	 */
	private function wkmpso_init_hooks() {
		add_action( 'init', array( $this, 'wkmpso_load_plugin_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'wkmpso_load_plugin' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'wkmpso_order_status_change_action' ), 10, 4 );

		self::wkmpso_declare_hpos_compatibility_status( WKMPSO_PLUGIN_FILE, true );
		self::wkmpso_declare_cart_checkout_block_compatibility_status( WKMPSO_PLUGIN_FILE, true );
	}

	/**
	 * Load Localization files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 */
	public function wkmpso_load_plugin_textdomain() {
		load_plugin_textdomain( 'mp-split-order', false, plugin_basename( dirname( WKMPSO_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Includes function.
	 */
	public function wkmpso_load_plugin() {
		if ( ! defined( 'MARKETPLACE_VERSION' ) ) {
			// Add WooCommerce dependency Message.
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="error">
					<p>
						<?php
						printf(
							/* translators: %s: is file path */
							esc_html__( 'Marketplace Split Order is deactivated. It requires %s plugin in order to work!', 'mp-split-order' ),
							'<a href="https://codecanyon.net/item/wordpress-woocommerce-marketplace-plugin/19214408" target="_blank">' . esc_html__( 'Maketplace Plugin', 'mp-split-order' ) . '</a>'
						);
						?>
					</p>
				</div>
					<?php
				}
			);
		} else {
			$shipping_method = get_option( 'wkmp_shipping_option' );

			if ( 'woocommerce' === $shipping_method ) {
				update_option( 'wkmp_shipping_option', 'marketplace' );
			}

			WKMPSO_File_Handler::get_instance();

			$current_user = wp_get_current_user();

			if ( 'disable' === get_option( '_wkmpso_plugin_status', 'enable' ) && empty( $current_user->allcaps['wk_marketplace_seller'] ) ) {
				// Add admin notices.
				add_action(
					'admin_notices',
					function () {
						?>
					<div class="error">
						<p>
							<?php
							printf(
								/* translators: %s: is file path */
								esc_html__( 'Marketplace Split Order won\'t work. Kindly enable the use of  %s', 'mp-split-order' ),
								'<a href="' . esc_url( admin_url() . 'admin.php?page=wkmp-split-order' ) . '" target="_blank">' . esc_html__( 'Marketplace Split Order', 'mp-split-order' ) . '</a>'
							);
							?>
						</p>
					</div>
						<?php
					}
				);
			}
		}
	}

	/**
	 * Order status change actions.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param object $order      Order object.
	 */
	public function wkmpso_order_status_change_action( $order_id, $old_status, $new_status, $order ) {
		// Avoid recursive updates for specific transitions.
		if ( 'checkout-draft' === $old_status && 'pending' === $new_status ) {
			return;
		}

		if ( 'pending' === $old_status && 'processing' === $new_status && did_action( 'woocommerce_checkout_create_order' ) ) {
			return;
		}

		if ( 0 === $order->get_parent_id() ) {
			$child_order_ids = $this->wkmpso_get_child_order_ids( $order_id );

			if ( ! empty( $child_order_ids ) ) {
				foreach ( $child_order_ids as $child_order_id ) {
					$child_order = wc_get_order( $child_order_id->ID );

					if ( $child_order && $child_order->get_status() !== $new_status ) {
						$child_order->update_status( $new_status );
						$child_order->save();
					}
				}
			}
		} else {
			$parent_id       = $order->get_parent_id();
			$parent_order    = wc_get_order( $parent_id );
			$child_order_ids = $this->wkmpso_get_child_order_ids( $parent_id );

			if ( $parent_order && ! empty( $child_order_ids ) ) {
				$all_same_status = true;

				foreach ( $child_order_ids as $child_order_id ) {
					$child_order = wc_get_order( $child_order_id->ID );

					if ( $child_order_id->ID !== $order_id && $child_order && $child_order->get_status() !== $new_status ) {
						$all_same_status = false;
						break;
					}
				}

				if ( $all_same_status && $parent_order->get_status() !== $new_status ) {
					$parent_order->update_status( $new_status );
					$parent_order->save();
				}
			}
		}
	}

	/**
	 * Check whether HPOS is enabled
	 *
	 * @return boolean
	 */
	public function wkmpso_is_hpos_enabled() {
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Get child order id by parent order id
	 *
	 * @param int $parent_order_id Parent Order id.
	 *
	 * @return boolean
	 */
	public function wkmpso_get_child_order_ids( $parent_order_id ) {
		global $wpdb;

		if ( $this->wkmpso_is_hpos_enabled() ) {
			$child_orders_ids = $wpdb->get_results( $wpdb->prepare( "SELECT id as ID FROM {$wpdb->prefix}wc_orders WHERE parent_order_id = %d", $parent_order_id ) );
		} else {
			$child_orders_ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_parent = %d", $parent_order_id ) );
		}

		return $child_orders_ids;
	}

	/**
	 * Declare plugin is compatible with HPOS.
	 *
	 * @param string $file Plugin main file path.
	 * @param bool   $status Compatibility status.
	 *
	 * @return void
	 */
	public static function wkmpso_declare_hpos_compatibility_status( $file = '', $status = true ) {
		add_action(
			'before_woocommerce_init',
			function () use ( $file, $status ) {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $file, $status );
				}
			}
		);
	}

	/**
	 * Declare plugin is incompatible with WC Cart and Checkout blocks.
	 *
	 * @param string $file Plugin main file path.
	 * @param bool   $status Compatibility status.
	 *
	 * @return void
	 */
	public static function wkmpso_declare_cart_checkout_block_compatibility_status( $file = '', $status = true ) {
		add_action(
			'before_woocommerce_init',
			function () use ( $file, $status ) {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $file, $status );
				}
			}
		);
	}
}
