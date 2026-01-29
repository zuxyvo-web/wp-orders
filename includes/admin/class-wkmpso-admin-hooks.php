<?php
/**
 * Front hooks
 *
 * @package Marketplace split order
 *
 * @version 1.1.4
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WKMPSO_Admin_Hooks' ) ) {
	/**
	 * Front hooks class.
	 *
	 * Class WKMPSO_Admin_Hooks
	 *
	 * @package MPSplitOrder\Includes\Admin
	 */
	class WKMPSO_Admin_Hooks {
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
		 * WKMPSO_Admin_Hooks constructor.
		 */
		public function __construct() {
			$function_handler = WKMPSO_Admin_Functions::get_instance();

			add_action( 'admin_init', array( $function_handler, 'wkmpso_register_settings_options' ) );
			add_action( 'admin_menu', array( $function_handler, 'wkmpso_create_dashboard_menu' ), 20 );
			add_filter( 'woocommerce_screen_ids', array( $function_handler, 'wkmpso_set_wc_screen_ids' ) );

			add_filter( 'plugin_row_meta', array( $function_handler, 'wkmpso_plugin_row_meta' ), 10, 4 );
			add_filter( 'plugin_action_links_' . WKMPSO_PLUGIN_BASENAME, array( $function_handler, 'wkmpso_add_plugin_setting_links' ) );

			if ( 'enable' === get_option( '_wkmpso_plugin_status', 'enable' ) ) {
				add_filter( 'change_shipping_message', array( $function_handler, 'wkmpso_change_shipping_message' ) );
				add_filter( 'wkmp_general_settings_shipping_methods', array( $function_handler, 'wkmpso_remove_shipping_methods' ) );
				add_filter( 'woocommerce_email_classes', array( $function_handler, 'wkmpso_email_classes' ) );
				add_filter( 'woocommerce_email_actions', array( $function_handler, 'wkmpso_email_action' ) );

				add_action( 'pre_get_posts', array( $function_handler, 'wkmpso_process_admin_shop_order_language_filter' ) );
				add_filter( 'manage_edit-shop_order_columns', array( $function_handler, 'wkmpso_custom_shop_order_column' ), 20 );
				add_action( 'manage_shop_order_posts_custom_column', array( $function_handler, 'wkmpso_custom_orders_list_column_content' ), 20, 2 );

				add_action( 'woocommerce_before_trash_order', array( $function_handler, 'wkmpso_update_parent_order' ) );

				// HPOS compatibility hook.
				add_filter( 'woocommerce_order_query_args', array( $function_handler, 'wkmpso_process_admin_shop_order_filter' ) );
				add_filter( 'woocommerce_shop_order_list_table_order_count', array( $function_handler, 'wkmpso_exclude_child_orders_from_count' ), 10, 2 );
				add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $function_handler, 'wkmpso_custom_shop_order_column' ), 20 );
				add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $function_handler, 'wkmpso_custom_orders_list_column_content' ), 20, 2 );
				// HPOS compatibility end here.

				add_filter( 'pre_update_option_wkmp_shipping_option', array( $function_handler, 'wkmpso_shipping_option_validate' ), 10, 3 );
				add_action( 'wkmp_add_settings_field', array( $function_handler, 'wkmpso_add_shipping_notification_settings_tag' ) );
				add_action( 'woocommerce_checkout_update_order_meta', array( $function_handler, 'wkmpso_get_order_details' ), 10, 2 );
				add_filter( 'wkmp_get_seller_orders', array( $function_handler, 'wkmpso_before_seller_order_table' ), 10, 2 );
			}

			add_filter( 'wkmp_admin_scripts_nonce_security', array( $function_handler, 'wkmpso_add_nonce_security' ) );
			add_filter( 'wk_modules_admin_page_slugs', array( $function_handler, 'wkmpso_add_pro_menu_pages' ) );
		}
	}
}
