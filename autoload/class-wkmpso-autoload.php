<?php
/**
 * Dynamically loads classes
 *
 * @package MarketPlace Split Order
 */

namespace MPSplitOrder\Inc;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WKMPSO_Autoload' ) ) {

	/**
	 * WKMPSO_Autoload class
	 */
	class WKMPSO_Autoload {
		/**
		 * The single instance of the class.
		 *
		 * @var $instance
		 * @since 1.0
		 */
		protected static $instance = null;

		/**
		 * This is a singleton page, access the single instance just using this method.
		 *
		 * @return object
		 */
		public static function get_instance() {
			if ( ! static::$instance ) {
				static::$instance = new self();
			}

			return static::$instance;
		}

		/**
		 * WKMPSO_Autoload constructor.
		 */
		public function __construct() {

			if ( function_exists( '__autoload' ) ) {
				spl_autoload_register( '__autoload' );
			}

			spl_autoload_register( array( $this, 'wkmpso_class_autoload' ) );
		}

		/**
		 * Autoload callback
		 *
		 * @param string $class_name The name of the class to load.
		 *
		 * @return void
		 */
		public function wkmpso_class_autoload( $class_name ) {

			if ( false === strpos( $class_name, 'WKMPSO_' ) ) {
				return;
			}

			$current_file = strtolower( $class_name );
			$current_file = str_ireplace( '_', '-', $current_file );
			$file_name    = "class-{$current_file}.php";
			$filepath     = trailingslashit( dirname( __DIR__ ) );
			$file_exists  = false;

			$all_paths = array(
				'autoload',
				'helper',
				'includes',
				'templates',
				'includes/admin',
				'includes/front',
			);

			foreach ( $all_paths as $path ) {
				$file_path = $filepath . $path . '/' . $file_name;

				if ( file_exists( $file_path ) ) {
					require_once $file_path;
					$file_exists = true;
					break;
				}
			}
			// If the file exists in the specified path, then include it.
			if ( ! $file_exists ) {
				wp_die(
					sprintf( /* Translators: %d: product filepath. */ esc_html__( 'The file attempting to be loaded at %s does not exist.', 'mp-split-order' ), esc_html( $file_path ) )
				);
			}
		}
	}

	WKMPSO_Autoload::get_instance();
}
