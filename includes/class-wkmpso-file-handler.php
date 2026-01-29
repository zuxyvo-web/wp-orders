<?php
/**
 * File handler
 *
 * @package MarketPlace Split Order
 *
 * @version 1.1.4
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WKMPSO_File_Handler' ) ) {
	/**
	 * File handler class.
	 *
	 * Class WKMPSO_File_Handler
	 */
	class WKMPSO_File_Handler {
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
		 * WKMPSO_File_Handler constructor.
		 */
		public function __construct() {
			if ( is_admin() ) {
				WKMPSO_Admin_Hooks::get_instance();
			} elseif ( 'enable' === get_option( '_wkmpso_plugin_status', 'enable' ) ) {
				WKMPSO_Front_Hooks::get_instance();
			}
		}
	}
}
