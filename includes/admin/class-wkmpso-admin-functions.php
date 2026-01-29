<?php
/**
 * Front functions
 *
 * @package MarketPlace Split Order
 *
 * @version 1.1.4
 */

defined( 'ABSPATH' ) || exit();

use WkMarketplace\Helper as MPHelper;

if ( ! class_exists( 'WKMPSO_Admin_Functions' ) ) {
	/**
	 * Admin functions class
	 */
	class WKMPSO_Admin_Functions {
		/**
		 * The single instance of the class.
		 *
		 * @var $instance
		 * @since 1.0.0
		 */
		protected static $instance = null;

		/**
		 * Sec data.
		 *
		 * @var $sec
		 */
		protected static $sec = null;

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
		 * Function handler constructor.
		 *
		 * @return void
		 */
		public function __construct() {
			self::$sec = 'pcc_';
		}

		/**
		 * Register Options
		 *
		 * @return void
		 */
		public function wkmpso_register_settings_options() {
			register_setting( 'wkmpso-settings-group', '_wkmpso_plugin_status' );
		}

		/**
		 * Dashboard Menus for WooCommerce Quick Search and Edit
		 *
		 * @return void
		 */
		public function wkmpso_create_dashboard_menu() {
			$menus_list = apply_filters( 'wk_modules_admin_page_slugs', array() );
			if ( function_exists( 'wkwc_create_menu' ) && ! empty( $menus_list['wkmp_split_order'] ) ) {
				wkwc_create_menu( $menus_list['wkmp_split_order'], $this );
			}
		}

		/**
		 * Add all the pro menu pages.
		 *
		 * @param array $menu_array Menu array list.
		 *
		 * @return array
		 */
		public function wkmpso_add_pro_menu_pages( $menu_array ) {
			$capability                     = apply_filters( 'wkmp_dashboard_menu_capability', 'manage_options' );
			$menu_array['wkmp_split_order'] = array(
				array(
					'parent_slug'   => 'wk-marketplace',
					'page_title'    => esc_html__( 'Split Order', 'mp-split-order' ),
					'menu_title'    => esc_html__( 'Split Order', 'mp-split-order' ),
					'menu_slug'     => 'wkmp-split-order',
					'capability'    => $capability,
					'callback'      => 'wkmpso_tabs_output',
					'screen_option' => false,
					'position'      => 99,
				),
			);

			return $menu_array;
		}

		/**
		 * Add subscription page in WooCommerce Screens.
		 *
		 * @param array $screens screen.
		 *
		 * @return array || Update Screen Lists
		 */
		public function wkmpso_set_wc_screen_ids( $screens ) {
			$screens[] = 'marketplace_page_wkmp-split-order';

			return $screens;
		}

		/**
		 * Display settings html
		 *
		 * @return void
		 */
		public function wkmpso_tabs_output() {
			wc_get_template( 'templates/wkmpso-settings.php', array(), '', WKMPSO_PLUGIN_FILE );
		}

		/**
		 * Shipping option validation.
		 *
		 * @param mixed $value value.
		 * @param mixed $old_value old value.
		 * @param mixed $option option.
		 *
		 * @return mixed|string
		 */
		public function wkmpso_shipping_option_validate( $value, $old_value, $option ) {
			if ( 'marketplace' !== $value && 'wkmp_shipping_option' === $option ) {
				add_settings_error( 'wkmp_shipping_option', 'wkmp_shipping_option_error', esc_html__( 'You can choose only Seller Shipping if split order addon is activated.', 'mp-split-order' ) );
				$value = 'marketplace';
			}

			return $value;
		}

		/**
		 * Change shipping message.
		 *
		 * @return string
		 */
		public function wkmpso_change_shipping_message() {
			return esc_html__( 'Only Seller Shipping is available for split order', 'mp-split-order' );
		}

		/**
		 * Remove shipping methods.
		 *
		 * @param mixed $shipping_methods shipping methods.
		 *
		 * @return mixed
		 */
		public function wkmpso_remove_shipping_methods( $shipping_methods ) {
			unset( $shipping_methods['woocommerce'] );
			return $shipping_methods;
		}

		/**
		 * Email classes.
		 *
		 * @param mixed $email email.
		 *
		 * @return mixed
		 */
		public function wkmpso_email_classes( $email ) {
			foreach ( $email as $key => $actions ) {
				if ( 'WC_Email_Seller_order_processing' === $key ) {
					unset( $email[ $key ] );
				}
			}

			return $email;
		}

		/**
		 * Email action.
		 *
		 * @param mixed $action action.
		 *
		 * @return mixed
		 */
		public function wkmpso_email_action( $action ) {
			foreach ( $action as $key => $actions ) {
				if ( 'woocommerce_seller_order_processing' === $actions ) {
					unset( $action[ $key ] );
				}
			}

			return $action;
		}

		/**
		 * When order delete.
		 *
		 * @param int $order_id Order id.
		 *
		 * @return void
		 */
		public function wkmpso_update_parent_order( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			global $wk_mpso;

			$parent_id = $order->get_parent_id();

			if ( empty( $parent_id ) ) {
				$child_orders = $wk_mpso->wkmpso_get_child_order_ids( $order_id );
				foreach ( $child_orders as $child_order_id ) {
					$child_order = wc_get_order( $child_order_id->ID );

					$child_order->set_parent_id( 0 );
					$child_order->set_status( 'trash' );
					$child_order->save();
				}
			}

			if ( $parent_id ) {
				$child_orders = $wk_mpso->wkmpso_get_child_order_ids( $parent_id );

				$remaining_child_orders = array_values(
					array_filter(
						$child_orders,
						function ( $item ) use ( $order_id ) {
							return (int) $item->ID !== (int) $order_id;
						}
					)
				);

				if ( empty( $remaining_child_orders ) ) {
					$parent_order = wc_get_order( $parent_id );
					if ( $parent_order ) {
						$parent_order->set_status( 'trash' );
						$parent_order->save();
					}
				}
				$order->set_parent_id( 0 );
				$order->save();
			}
		}

		/**
		 * Order language filter.
		 *
		 * @param mixed $query query.
		 */
		public function wkmpso_process_admin_shop_order_language_filter( $query ) {
			global $pagenow;

			$p_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( $query->is_admin && 'edit.php' === $pagenow && 'shop_order' === $p_type ) {
				$query->set( 'post_parent', 0 ); // Set the new "meta query".
			}
		}

		/**
		 * Order language filter.
		 *
		 * @param array $args Clauses.
		 *
		 * @return array $args.
		 */
		public function wkmpso_process_admin_shop_order_filter( $args ) {
			global $pagenow;

			$page            = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$parent_order_id = filter_input( INPUT_GET, 'parent_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( 'admin.php' === $pagenow && 'wc-orders' === $page ) {
				if ( $parent_order_id ) {
					$args['parent'] = $parent_order_id;
				} else {
					$args['parent'] = 0;
				}
			}

			return $args;
		}

		/**
		 * Exclude child orders from count.
		 *
		 * @param int    $count Count.
		 *
		 * @param string $status Status.
		 *
		 * @return int $count
		 */
		public function wkmpso_exclude_child_orders_from_count( $count, $status ) {
			global $wpdb;
			$wpdbs        = $wpdb;
			$orders_table = $wpdb->prefix . 'wc_orders';

			$parent_order_id = filter_input( INPUT_GET, 'parent_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$id              = $parent_order_id ? $parent_order_id : 0;
			$order_type      = 'shop_order';

			$res = $wpdbs->get_results( $wpdbs->prepare( "SELECT status, COUNT(*) AS cnt FROM {$orders_table} WHERE type = %s AND parent_order_id = %d GROUP BY status", $order_type, $id ), ARRAY_A );

			$count_cache = $res ? array_combine( array_column( $res, 'status' ), array_map( 'absint', array_column( $res, 'cnt' ) ) ) : array();
			$status      = (array) $status;
			$count       = array_sum( array_intersect_key( $count_cache, array_flip( $status ) ) );

			return $count;
		}

		/**
		 * Shop order column.
		 *
		 * @param mixed $columns columns.
		 *
		 * @return array
		 */
		public function wkmpso_custom_shop_order_column( $columns ) {
			$parent_order_id = (int) filter_input( INPUT_GET, 'parent_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! $parent_order_id ) {
				$reordered_columns = array();
				foreach ( $columns as $key => $column ) {
					$reordered_columns[ $key ] = $column;
					if ( 'order_status' === $key ) {
						$reordered_columns['sub_orders'] = __( 'Sub Orders', 'mp-split-order' );
					}
				}
				$reordered_columns['action'] = __( 'Action', 'mp-split-order' );
				return $reordered_columns;
			}
			return $columns;
		}

		/**
		 * Columns consent.
		 *
		 * @param mixed  $column column.
		 * @param object $order  Order object.
		 */
		public function wkmpso_custom_orders_list_column_content( $column, $order ) {
			global $wk_mpso;

			switch ( $column ) {
				case 'sub_orders':
					$order            = ( $order instanceof \WC_Order ) ? $order : wc_get_order( $order );
					$suborder         = $order->get_meta( '_wkmpsplit_order', true );
					$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $order->get_id() );

					if ( ! empty( $suborder ) ) {
						if ( ! empty( $child_orders_ids ) ) {
							foreach ( $child_orders_ids as $suborder ) {
								echo '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $suborder->ID ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $suborder->ID ) . '</strong></a>';
								echo '<br>';
							}
						} else {
							echo '-';
						}
					} else {
						echo '-';
					}
					break;
				case 'action':
					$order            = ( $order instanceof \WC_Order ) ? $order : wc_get_order( $order );
					$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $order->get_id() );

					if ( ! empty( $child_orders_ids ) ) {
						echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-orders&parent_order_id=' . absint( $order->get_id() ) ) ) . '" class="order-view"><strong>' . esc_html__( 'View Suborder', 'mp-split-order' ) . '</strong></a>';
					} else {
						echo '-';
					}
					break;
			}
		}

		/**
		 * Add shipping notification.
		 *
		 * @return void
		 */
		public function wkmpso_add_shipping_notification_settings_tag() {
			$style = ' color: indianred;font-style: italic;'; ?>
			<tr><th></th><td><p style="<?php echo esc_attr( $style ); ?>" class="shipping-notification-description"><b><?php esc_html_e( 'Note:', 'mp-split-order' ); ?></b> <?php esc_html_e( 'You can choose only seller shipping if split order is activated and enabled', 'mp-split-order' ); ?></p></td></tr>
			<?php
		}

		/**
		 * Update order details.
		 *
		 * @param int $order_id order id.
		 *
		 * @return void
		 */
		public function wkmpso_get_order_details( $order_id ) {
			global $woocommerce;

			$order_details   = array();
			$product_details = array();

			foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
				$product_id                                     = $cart_item['product_id'];
				$order_details [ $product_id ]['variation_id']  = $cart_item['variation_id'];
				$order_details [ $product_id ]['quantity']      = $cart_item['quantity'];
				$product                                        = wc_get_product( $product_id );
				$product_stock_qty                              = $product->get_stock_quantity();
				$product_stock_status                           = $product->get_stock_status();
				$product_details[ $product_id ]['stock_qty']    = $product_stock_qty;
				$product_details[ $product_id ]['stock_status'] = $product_stock_status;
			}

			$order = wc_get_order( $order_id );

			$order->update_meta_data( 'wkmpso_before_order_product_status', $product_details );
			$order->update_meta_data( 'wkmpso_custom_order_date', $order_details );
			$order->save();
		}

		/**
		 * Get table data.
		 *
		 * @param mixed $data .
		 * @param mixed $seller_id .
		 * @return mixed
		 */
		public function wkmpso_before_seller_order_table( $data, $seller_id ) {
			global $wpdb, $wk_mpso;

			foreach ( $data as $key => $seller_order ) {
				if ( $wk_mpso->wkmpso_is_hpos_enabled() ) {
					$child_orders_ids = $wpdb->get_results( $wpdb->prepare( "SELECT id as ID FROM {$wpdb->prefix}wc_orders WHERE parent_order_id=%d", $seller_order ) );
				} else {
					$child_orders_ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_parent=%d", $seller_order ) );
				}

				if ( ! empty( $child_orders_ids ) ) {
					$ids = wp_list_pluck( $child_orders_ids, 'ID' );
					foreach ( $ids as $id ) {
						$child_order_id = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mporders WHERE seller_id=%d AND order_id=%d ", $seller_id, $id ) );
						if ( ! empty( $child_order_id ) ) {
							unset( $data[ $key ] );
						}
					}
				}
			}

			return $data;
		}

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @param mixed $links_array Plugin Row Meta.
		 * @param mixed $plugin_file_name  Plugin Base file.
		 *
		 * @return array
		 */
		public function wkmpso_plugin_row_meta( $links_array, $plugin_file_name ) {
			if ( false !== strpos( $plugin_file_name, WKMPSO_PLUGIN_BASENAME ) ) {
				$links_array[] = '<a target="_blank" href="' . esc_url( 'https://webkul.com/blog/wordpress-woocommerce-marketplace-split-order-plugin/' ) . '" aria-label="' . esc_attr__( 'View Woocommerce Marketplace Split Order documentation', 'mp-split-order' ) . '">' . esc_html__( 'Docs', 'mp-split-order' ) . '</a>';
				$links_array[] = '<a target="_blank" href="' . esc_url( 'https://webkul.uvdesk.com/' ) . '" aria-label="' . esc_attr__( 'Visit customer support', 'mp-split-order' ) . '">' . esc_html__( 'Support', 'mp-split-order' ) . '</a>';
				$links_array[] = '<a target="_blank" href="' . esc_url( 'https://codecanyon.net/item/wordpress-woocommerce-marketplace-split-order-plugin/reviews/19466195' ) . '" aria-label="' . esc_attr__( 'Rate on Codecanyon', 'mp-split-order' ) . '" title="' . esc_attr__( 'Rate on Codecanyon', 'mp-split-order' ) . '" style="color: #ffb900">' . str_repeat( '<span class="dashicons dashicons-star-filled" style="font-size: 16px; width:16px; height: 16px"></span>', 5 ) . '</a>';
			}

			return $links_array;
		}

		/**
		 * Show setting links.
		 *
		 * @param array $links Setting links.
		 *
		 * @return array
		 */
		public function wkmpso_add_plugin_setting_links( $links ) {
			$links = is_array( $links ) ? $links : array();

			$links[] = '<a href="' . esc_url( admin_url( '/admin.php?page=wkmp-split-order' ) ) . '">' . esc_html__( 'Settings', 'mp-split-order' ) . '</a>';

			return $links;
		}

		/**
		 * Add nonce security.
		 *
		 * @param array $nonce_data Nonce data.
		 *
		 * @return array
		 */
		public function wkmpso_add_nonce_security( $nonce_data ) {
			$nonce_data = empty( $nonce_data ) ? array() : $nonce_data;

			$wk_page = \WK_Caching::wk_get_request_data( 'page' );

			if ( 'wkmp-split-order' === $wk_page ) {
				$wk_tab   = \WK_Caching::wk_get_request_data( 'tab' );
				$wk_page .= empty( $wk_tab ) ? '' : $wk_tab;

				$nonce_data[ $wk_page ] = MPHelper\WKMP_General_Queries::wkmp_get_pro_sec_data( $this->wkmpso_get_sec_data() );
			}

			return $nonce_data;
		}

		/**
		 * Get pvt data.
		 *
		 * @return string
		 */
		public function wkmpso_get_sec_data() {
			$pnd = get_file_data( WP_PLUGIN_DIR . '/' . WKMPSO_PLUGIN_BASENAME, array( 'sc' => 'Plugin Name' ) );
			return get_option( 'wp_' . self::$sec . wc_strtolower( str_replace( ' ', '_', $pnd['sc'] ?? '' ) ) );
		}
	}
}
