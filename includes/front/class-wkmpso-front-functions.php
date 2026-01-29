<?php
/**
 * Front functions
 *
 * @package MarketPlace Split Order
 */

defined( 'ABSPATH' ) || exit();

use Automattic\WooCommerce\Utilities\OrderUtil;
use WkMobikulMultivendorApi\Helper;


if ( ! class_exists( 'WKMPSO_Front_Functions' ) ) {

	/**
	 * Front functions class.
	 *
	 * Class WKMPSO_Front_Functions
	 *
	 * @package MPSplitOrder\Includes\Front
	 */
	class WKMPSO_Front_Functions {
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
		 * Update cart item of order.
		 *
		 * @param mixed $cart cart.
		 */
		public function wkmpso_update_cart_items( $cart ) {
			if ( ! empty( $cart ) ) {
				foreach ( $cart as $value ) {
					$prod_id = isset( $value['product_id'] ) ? $value['product_id'] : 0;
					if ( isset( $value['variation_id'] ) && ! empty( $value['variation_id'] ) ) {
						$prod_id = $value['variation_id'];
					}
					$value['marketplace']['vendor']  = get_post_field( 'post_author', $prod_id );
					$value['marketplace']['product'] = $prod_id;
				}
			}

			return $cart;
		}

		/**
		 * Add order meta for split order.
		 *
		 * @param object $order Order object.
		 *
		 * @return void
		 */
		public function wkmpso_add_order_meta_for_split_order( $order ) {
			$sellers_in_orders = array();
			$order             = ( $order instanceof \WC_Order ) ? $order : wc_get_order( $order );

			foreach ( $order->get_items() as $item ) {
				$product_id = isset( $item['product_id'] ) ? $item['product_id'] : 0;
				if ( isset( $item['variation_id'] ) && ! empty( $item['variation_id'] ) ) {
					$product_id = $item['variation_id'];
				}
				$seller_id = get_post_field( 'post_author', $product_id );
				if ( ! in_array( $seller_id, $sellers_in_orders, true ) ) {
					$sellers_in_orders[] = $seller_id;
				}
			}

			$seller_count = ! empty( $sellers_in_orders ) ? count( $sellers_in_orders ) : 0;

			if ( $seller_count > 1 ) {
				$order->update_meta_data( '_wkmpsplit_create_suborders', 'yes' );
				$order->update_meta_data( '_wkmpsplit_order', 'yes' );
				$order->save();

				$events = array(
					'pending_to_processing',
					'pending_to_completed',
					'pending_to_on-hold',
					'failed_to_processing',
					'failed_to_completed',
					'failed_to_on-hold',
					'cancelled_to_processing',
					'cancelled_to_completed',
					'cancelled_to_on-hold',
				);

				foreach ( $events as $event ) {
					$action = 'woocommerce_order_status_' . $event . '_notification';
					remove_action( $action, array( WC()->mailer()->emails['WC_Email_New_Order'], 'trigger' ) );
				}
			}
		}

		/**
		 * Woocommerce executions.
		 *
		 * @param mixed $order order object..
		 */
		public function wkmpso_woocommerce_e( $order ) {
			global $wk_mpso;

			if ( ! empty( $order ) && 'yes' === $order->get_meta( '_wkmpsplit_create_suborders', true ) ) {
				$checkout = WKMPSO_Order_Split::get_instance();
				$checkout->wkmpso_split_order( $order->get_id() );
			}
		}

		/**
		 * WC Get templates.
		 *
		 * @param string $located Located.
		 * @param string $template_name Template name.
		 *
		 * @hooked 'wc_get_template' filter hook.
		 *
		 * @return string
		 */
		public function wkmpso_get_template( $located, $template_name ) {
			if ( 'checkout/thankyou.php' === $template_name ) {
				$located = WKMPSO_PLUGIN_FILE . 'templates/thankyou.php';
			}

			if ( 'order/order-details.php' === $template_name ) {
				$located = WKMPSO_PLUGIN_FILE . 'templates/order-details.php';
			}

			if ( 'myaccount/orders.php' === $template_name ) {
				$located = WKMPSO_PLUGIN_FILE . 'templates/orders.php';
			}

			return $located;
		}

		/**
		 * Split order show on seller dashboard.
		 *
		 * @param string $order_detail_sql order details query.
		 * @param array  $query_args argument.
		 * @param int    $user_id user id.
		 * @return string $order_detail_sql sql query.
		 */
		public function wkmpso_split_order_show_for_seller( $order_detail_sql, $query_args, $user_id ) {
			global $wpdb, $wk_mpso;

			$user_id                = empty( $query_args['user_id'] ) ? get_current_user_id() : intval( $query_args['user_id'] );
			$search                 = empty( $query_args['search'] ) ? 0 : intval( $query_args['search'] );
			$orderby                = empty( $query_args['order_by'] ) ? 'order_id' : $query_args['order_by'];
			$sort_order             = empty( $query_args['sort_order'] ) ? 'desc' : $query_args['sort_order'];
			$order_approval_enabled = get_user_meta( $user_id, '_wkmp_enable_seller_order_approval', true );

			$order_detail_sql = "SELECT * FROM {$wpdb->prefix}mporders mo";

			if ( $order_approval_enabled ) {
				$order_detail_sql .= " LEFT JOIN {$wpdb->prefix}mporders_meta mpom ON ( mo.order_id = mpom.order_id )";
			}

			if ( $wk_mpso->wkmpso_is_hpos_enabled() ) {
				$order_detail_sql .= " LEFT JOIN {$wpdb->prefix}wc_orders opm ON ( mo.order_id = opm.id )";
			} else {
				$order_detail_sql .= " LEFT JOIN {$wpdb->prefix}posts opm ON ( mo.order_id = opm.ID )";
			}

			$order_detail_sql .= $wpdb->prepare( ' WHERE mo.seller_id = %d', $user_id );

			if ( $order_approval_enabled ) {
				$order_detail_sql .= " AND mpom.meta_key='paid_status' AND mpom.meta_value IN ('paid','approved')";
			}

			if ( $wk_mpso->wkmpso_is_hpos_enabled() ) {
				$order_detail_sql .= " AND opm.parent_order_id ='0'";
			} else {
				$order_detail_sql .= " AND opm.post_parent ='0'";
			}

			if ( ! empty( $search ) ) {
				$order_detail_sql .= $wpdb->prepare( ' AND mo.order_id = %d', $search );
			}
			$wkmpdb            = $wpdb;
			$order_detail_sql .= $wkmpdb->prepare( ' ORDER BY mo.%1s %2s', $orderby, $sort_order );

			return $order_detail_sql;
		}

		/**
		 * For display seller order list.
		 *
		 * @param array $order_data Order data.
		 *
		 * @return array
		 */
		public function wkmpso_split_order_list( $order_data ) {
			global $wpdb, $wk_mpso;

			$result = $order_data;
			$orders = empty( $order_data['data'] ) ? array() : $order_data['data'];

			foreach ( $orders as $key => $seller_order ) {
				$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $seller_order['order_id'] );

				if ( ! empty( $child_orders_ids ) ) {
					$ids = wp_list_pluck( $child_orders_ids, 'ID' );

					foreach ( $ids as $value ) {
						$child_order_id = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mporders WHERE seller_id = %d AND order_id = %d ", get_current_user_id(), $value ) );

						if ( ! empty( $child_order_id ) ) {
							$sub_order                      = wc_get_order( $value );
							$orders[ $key ]['order_id']     = $value;
							$orders[ $key ]['order_status'] = $sub_order->get_status();
							$orders[ $key ]['view']         = esc_url( dirname( $seller_order['view'] ) . '/' . $value );
						}
					}
				}
			}

			$result['data'] = $orders;

			return $result;
		}

		/**
		 * Add order note on main order.
		 *
		 * @param mixed $order Order Object.
		 * @param array $data Seller Data.
		 *
		 * @return void
		 */
		public function mpso_after_seller_order_status( $order, $data ) {
			$p_id = $order->get_parent_id();
			if ( ! empty( $p_id ) ) {
				$order_id       = empty( $data['mp-order-id'] ) ? 0 : intval( $data['mp-order-id'] );
				$old_status     = str_replace( 'wc-', '', $data['mp-old-order-status'] );
				$changed_status = str_replace( 'wc-', '', $data['mp-order-status'] );
				$note           = sprintf( /* Translators: %s: order_id. */ esc_html__( 'Order Id: %1$d, changed Order Status from %2$s to %3$s', 'mp-split-order' ), $order_id, $old_status, $changed_status );
				$porder         = wc_get_order( $p_id );
				$porder->add_order_note( $note, 1 );
				$porder->save();
			}
		}

		/**
		 * Hide notification about main order.
		 *
		 * @param mixed $data Data.
		 * @return mixed
		 */
		public function mpso_hide_notification( $data ) {
			global $wk_mpso;

			foreach ( $data as $key => $value ) {
				$context_id       = isset( $value['context'] ) ? $value['context'] : 0;
				$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $context_id );

				if ( ! empty( $child_orders_ids ) ) {
					$data[ $key ]['context'] = 0;
				}
			}

			return $data;
		}

		/**
		 * Validate Seller view order.
		 *
		 * @param mixed $seller_order Seller Order.
		 *
		 * @return void
		 */
		public function wkmpso_seller_order_access_validation( $seller_order ) {
			global $wk_mpso;

			$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $seller_order->get_id() );

			if ( ! empty( $child_orders_ids && count( $child_orders_ids ) !== 1 ) ) {
				?>
				<h1><?php esc_html_e( 'Access Denied', 'mp-split-order' ); ?></h1>
				<p><?php esc_html_e( 'Sorry, You can\'t access it.', 'mp-split-order' ); ?></p>
				<?php
				exit;
			}
		}

		/**
		 * Check product stock.
		 *
		 * @return void
		 */
		public function wkmpso_after_order_create_check_product_stock() {
			$product_stock = array();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id   = $cart_item['product_id'];
				$variation_id = $cart_item['variation_id'];
				$product_id   = ! empty( $variation_id ) ? $variation_id : $product_id;
				$manage_stock = get_post_meta( $product_id, '_manage_stock', true );
				$quantity     = (int) $cart_item['quantity'];
				if ( 'yes' === $manage_stock ) {
					$real_qty                     = (int) get_post_meta( $product_id, '_stock', true );
					$product_stock[ $product_id ] = $real_qty - $quantity;
				}
			}
			if ( ! empty( $product_stock ) ) {
				WC()->session->set( 'mpso_order_product_stock', $product_stock );
			}
		}

		/**
		 * Mange product stock.
		 *
		 * @return void
		 */
		public function wkmpso_update_product_stock() {
			$get = WC()->session->get( 'mpso_order_product_stock' );
			if ( ! empty( $get ) ) {
				foreach ( $get as $key => $stock ) {
					if ( $stock > 0 ) {
						update_post_meta( $key, '_manage_stock', 'yes' );
						update_post_meta( $key, '_stock', $stock );
					}
				}
				WC()->session->__unset( 'mpso_order_product_stock' );
			}
		}

		/**
		 * Update split order status.
		 *
		 * @param object $order order details.
		 * @param array  $data parent order details.
		 * @return object $order order details.
		 */
		public function wkmpso_after_seller_update_order_status( $order, $data ) {
			global $wpdb, $wk_mpso;

			$mp_seller_id     = (int) $data['mp-seller-id'];
			$child_orders_ids = $wk_mpso->wkmpso_get_child_order_ids( $data['mp-order-id'] );

			$ids = wp_list_pluck( $child_orders_ids, 'ID' );

			foreach ( $ids as $id ) {
				$query_result = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}mporders WHERE seller_id = %d && order_id = %d", $mp_seller_id, $id ) );

				if ( ! empty( $query_result ) ) {
					$update_order_id = $id;
					break;
				}
			}

			$suborder = $order->get_meta( '_wkmpsplit_order', true );

			if ( ! empty( $suborder ) && ! empty( $update_order_id ) ) {
				$orders = new \WC_Order( $update_order_id );
				$orders->update_status( 'completed' );
			}

			return $order;
		}


		/**
		 * Register REST API route for getting sub-orders data.
		 *
		 * @return void
		 */
		public function wkmpso_rest_api_init() {
			register_rest_route(
				'mobikul/v1',
				'/mp-split-order/get/sub-orders/(?P<order_id>\d+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sub_orders_data' ),
					'permission_callback' => function () {
						return true;
					},
				)
			);
		}

		/**
		 * Get sub-orders data for a main order.
		 *
		 * @param array $data Request data containing order_id.
		 * @return array|WP_Error Response array containing main order and sub-orders data or WP_Error on failure.
		 */
		public function get_sub_orders_data( $data ) {
			$general_data_handler = WKMPSO_Order_Split::get_instance();

			$main_order_id = $data['order_id'];
			$main_order    = wc_get_order( $main_order_id );

			if ( ! $main_order ) {
				return new WP_Error( 'no_order', 'Order not found', array( 'status' => 404 ) );
			}

			$sub_orders = $this->wkmpso_get_child_order_ids( $main_order_id );

			$billing_address  = $main_order->get_address( 'billing' );
			$shipping_address = $main_order->get_address( 'shipping' );

			$total          = $main_order->get_total();
			$subtotal       = $main_order->get_subtotal();
			$total_tax      = $main_order->get_total_tax();
			$total_shipping = $main_order->get_shipping_total();
			$total_discount = $main_order->get_discount_total();
			$cart_tax       = $main_order->get_cart_tax();
			$shipping_tax   = $main_order->get_shipping_tax();

			$response = array(
				'main_order' => array(
					'id'               => $main_order->get_id(),
					'status'           => $main_order->get_status(),
					'currency'         => $main_order->get_currency(),
					'order_date'       => $main_order->get_date_created()->date( 'F j, Y' ),

					'total'            => $general_data_handler->wkmpso_get_formatted_price( $total, array( 'currency' => $main_order->get_currency() ) ),
					'subtotal'         => $general_data_handler->wkmpso_get_formatted_price( $subtotal, array( 'currency' => $main_order->get_currency() ) ),

					'total_tax'        => $general_data_handler->wkmpso_get_formatted_price( $total_tax, array( 'currency' => $main_order->get_currency() ) ),
					'total_shipping'   => $general_data_handler->wkmpso_get_formatted_price( $total_shipping, array( 'currency' => $main_order->get_currency() ) ),
					'total_discount'   => $general_data_handler->wkmpso_get_formatted_price( $total_discount, array( 'currency' => $main_order->get_currency() ) ),
					'cart_tax'         => $general_data_handler->wkmpso_get_formatted_price( $cart_tax, array( 'currency' => $main_order->get_currency() ) ),
					'shipping_tax'     => $general_data_handler->wkmpso_get_formatted_price( $shipping_tax, array( 'currency' => $main_order->get_currency() ) ),

					'billing_address'  => $billing_address,
					'shipping_address' => $shipping_address,

					'created_at'       => $main_order->post_date_gmt,
					'updated_at'       => $main_order->post_modified_gmt,
				),
				'sub_orders' => array(),
			);

			// Get sub-order details and reformat the response.
			foreach ( $sub_orders as $sub_order_id ) {
				$sub_order = wc_get_order( $sub_order_id->ID );

				$products = array();
				foreach ( $sub_order->get_items() as $item_id => $item ) {
					$products[] = array(
						'name'     => $item->get_name(),
						'quantity' => $item->get_quantity(),
						'sold_by'  => get_the_author_meta( 'display_name', $item->get_product()->get_post_data()->post_author ),
						'total'    => $item->get_total(),
					);
				}

				$response['sub_orders'][] = array(
					'order_number'   => $sub_order->get_order_number(),
					'date'           => $sub_order->get_date_created()->date( 'F j, Y' ),
					'email'          => $sub_order->get_billing_email(),

					'total'          => $general_data_handler->wkmpso_get_formatted_price( $sub_order->get_total(), array( 'currency' => $main_order->get_currency() ) ),

					'payment_method' => $sub_order->get_payment_method_title(),
					'order_details'  => array(
						'product'        => $products,
						'subtotal'       => $general_data_handler->wkmpso_get_formatted_price( $sub_order->get_subtotal(), array( 'currency' => $main_order->get_currency() ) ),
						'shipping'       => array(
							'cost'   => $general_data_handler->wkmpso_get_formatted_price( $this->get_shipping_details( $sub_order )['shipping_total'], array( 'currency' => $main_order->get_currency() ) ),
							'method' => $this->get_shipping_details( $sub_order )['shipping_methods'][0]['method_title'],
						),
						'payment_method' => $sub_order->get_payment_method_title(),
						'total'          => $general_data_handler->wkmpso_get_formatted_price( $sub_order->get_total(), array( 'currency' => $main_order->get_currency() ) ),
					),
				);
			}

			return $response;
		}

		/**
		 * Get shipping details for an order.
		 *
		 * @param WC_Order $order Order object.
		 * @return array Array containing shipping methods and total.
		 */
		public function get_shipping_details( $order ) {
			$shipping_details = array();
			$shipping_methods = $order->get_shipping_methods();

			foreach ( $shipping_methods as $shipping_method ) {
				$shipping_details[] = array(
					'method_title' => $shipping_method->get_method_title(),
					'method_id'    => $shipping_method->get_method_id(),
					'instance_id'  => $shipping_method->get_instance_id(),
					'total'        => $shipping_method->get_total(),
				);
			}

			$shipping_total = $order->get_shipping_total();

			return array(
				'shipping_methods' => $shipping_details,
				'shipping_total'   => $shipping_total,
			);
		}

		/**
		 * Check if HPOS is enabled.
		 *
		 * @return bool True if HPOS is enabled, false otherwise.
		 */
		public function wkmpso_is_hpos_enabled() {
			return OrderUtil::custom_orders_table_usage_is_enabled();
		}

		/**
		 * Get child order IDs for a parent order.
		 *
		 * @param int $parent_order_id Parent order ID.
		 * @return array Array of child order IDs.
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
	}
}
