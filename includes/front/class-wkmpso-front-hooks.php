<?php
/**
 * Front hooks
 *
 * @package Marketplace split order
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WKMPSO_Front_Hooks' ) ) {
	/**
	 *  Front hooks class.
	 *
	 * Class WKMPSO_Front_Hooks
	 *
	 * @package MPSplitOrder\Includes\Front
	 */
	class WKMPSO_Front_Hooks {
		/**
		 * The single instance of the class.
		 *
		 * @var $instance
		 * @since 1.0.0
		 */
		protected static $instance = null;

		/**
		 * Main Instance.
		 *
		 * Ensures only one instance of this class is loaded or can be loaded.
		 *
		 * @return Main instance.
		 * @since 1.0.0
		 * @static
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * WKMPSO_Front_Hooks constructor.
		 */
		public function __construct() {
			global $wk_mpso;
			$function_handler = WKMPSO_Front_Functions::get_instance();

			add_filter( 'woocommerce_cart_contents_changed', array( $function_handler, 'wkmpso_update_cart_items' ) );

			add_action( 'woocommerce_checkout_create_order', array( $function_handler, 'wkmpso_add_order_meta_for_split_order' ), 1 );
			add_action( 'woocommerce_new_order', array( $function_handler, 'wkmpso_add_order_meta_for_split_order' ) );
			add_filter( 'wc_get_template', array( $function_handler, 'wkmpso_get_template' ), 10, 2 );

			add_action( 'wkmpso_create_suborders', array( $function_handler, 'wkmpso_woocommerce_e' ) );

			add_filter( 'wkmp_unset_shipping_methods', '__return_false' );
			add_action( 'wkmp_get_seller_orders_query', array( $function_handler, 'wkmpso_split_order_show_for_seller' ), 10, 3 );
			add_filter( 'wkmp_seller_order_table_data', array( $function_handler, 'wkmpso_split_order_list' ) );
			add_action( 'wkmp_after_seller_update_order_status', array( $function_handler, 'mpso_after_seller_order_status' ), 10, 2 );
			add_filter( 'wkmp_get_seller_notification_data', array( $function_handler, 'mpso_hide_notification' ) );
			add_action( 'wkmp_before_seller_print_invoice_button', array( $function_handler, 'wkmpso_seller_order_access_validation' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $function_handler, 'wkmpso_after_order_create_check_product_stock' ) );
			add_action( 'wp', array( $function_handler, 'wkmpso_update_product_stock' ) );
			add_action( 'wkmp_after_seller_update_order_status', array( $function_handler, 'wkmpso_after_seller_update_order_status' ), 10, 2 );

			// Register REST API endpoints.
			add_action( 'rest_api_init', array( $function_handler, 'wkmpso_rest_api_init' ) );
		}
	}
}
