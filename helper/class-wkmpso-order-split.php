<?php
/**
 * WKMPSO_Order_Split
 *
 * @package Marketplace split order
 *
 * @version 1.1.4
 */

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'WKMPSO_Order_Split' ) ) {
	/**
	 * Class WKMPSO_Order_Split
	 *
	 * @package MPSplitOrder\Includes\Front
	 */
	class WKMPSO_Order_Split {
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
		 * Run the plugin.
		 *
		 * @param int $master_order_id Master order id.
		 */
		public function wkmpso_split_order( $master_order_id ) {
			$seller_array     = array();
			$split_cart_items = array();
			$vendor_id_array  = array();
			$master_order     = new \WC_Order( $master_order_id );

			foreach ( $master_order->get_items() as $values ) {
				$product_id   = empty( $values['product_id'] ) ? 0 : $values['product_id'];
				$variation_id = empty( $values['variation_id'] ) ? 0 : $values['variation_id'];
				$vendor_id    = get_post_field( 'post_author', $product_id );
				$product_id   = empty( $variation_id ) ? $product_id : $variation_id;

				if ( in_array( $vendor_id, $vendor_id_array, true ) ) {
					$build_array = array(
						'cart_data' => $values->get_data(),
					);

					array_push( $seller_array[ $vendor_id ], $build_array );
				} else {
					if ( ! in_array( $vendor_id, $vendor_id_array, true ) ) {
						$split_cart_items = array();
					}

					array_push( $split_cart_items, array( 'cart_data' => $values->get_data() ) );

					$seller_array[ $vendor_id ] = $split_cart_items;
				}
				array_push( $vendor_id_array, $vendor_id );
			}

			if ( count( $seller_array ) > 1 ) {
				$this->wkmpso_create_order( $master_order, $seller_array );
			}
		}

		/**
		 * Finding GCD.
		 *
		 * @param mixed $a a.
		 * @param mixed $b b.
		 *
		 * @return float|int
		 */
		public function wkmpso_gcd( $a, $b ) {
			// Ensure both inputs are positive floats.
			$a = abs( floatval( $a ) );
			$b = abs( floatval( $b ) );

			// If either number is 0, return the other number.
			if ( 0.0 === $a || 0.0 === $b ) {
				return max( $a, $b );
			}

			// Calculate GCD using floats and avoid the '%' operator.
			while ( 0.0 !== $b ) {
				$temp = $b;
				$b    = fmod( $a, $b ); // Use fmod() for floats.
				$a    = $temp;
			}

			return $a;
		}

		/**
		 * GCD Array.
		 *
		 * @param array $gcd array.
		 * @param int   $a a.
		 *
		 * @return int
		 */
		public function wkmpso_gcd_array( $gcd, $a = 0 ) {
			$b = array_pop( $gcd );
			return ( null === $b ) ? (int) $a : $this->wkmpso_gcd_array( $gcd, $this->wkmpso_gcd( $a, $b ) );
		}

		/**
		 * Create order.
		 *
		 * @param mixed $master_order master order.
		 * @param array $seller_array seller array.
		 *
		 * @return mixed|WP_Error
		 * @throws \WC_Data_Exception Wp error.
		 */
		public function wkmpso_create_order( $master_order, $seller_array = array() ) {
			global $wpdb;

			$customer_id        = $master_order->get_customer_id();
			$payment_method     = $master_order->get_payment_method();
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$vendor_array       = array();
			$vendor_ship_array  = array();
			$order_fee_array    = array();

			$master_total_tax = $master_order->get_total_tax();
			$master_fee       = $master_order->get_fees();

			$master_order->update_meta_data( 'master_order', 'true' );

			$shipping_session1 = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value as shipping_cost, seller_id FROM {$wpdb->prefix}mporders_meta WHERE order_id=%d AND meta_key=%s", $master_order->get_id(), 'shipping_cost' ), ARRAY_A );
			$shipping_session2 = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value as shipping_id, seller_id FROM {$wpdb->prefix}mporders_meta WHERE order_id=%d AND meta_key=%s", $master_order->get_id(), 'shipping_method_id' ), ARRAY_A );
			$shipping_session  = array();

			foreach ( $shipping_session1 as $key => $value ) {
				if ( $value['seller_id'] === $shipping_session2[ $key ]['seller_id'] ) {
					$shipping_session[ $value['seller_id'] ] = array(
						'seller_id'     => $value['seller_id'],
						'shipping_id'   => $shipping_session2[ $key ]['shipping_id'],
						'shipping_cost' => $value['shipping_cost'],
					);
				}
			}

			foreach ( $master_fee as $key => $value ) {
				$value_data = $value->get_data();

				$order_fee_array[ $key ] = array(
					'name'  => $value_data['name'],
					'total' => $value_data['total'],
					'tax'   => $value_data['taxes']['total'],
				);
			}

			$extra             = 0;
			$master_total_tax += $extra;

			foreach ( $seller_array as $vkey => $value ) {
				$vendor_total = 0;

				foreach ( $value as $pvalue ) {
					$pvalues               = $pvalue['cart_data'];
					$vendor_total         += $pvalues['subtotal'];
					$vendor_array[ $vkey ] = $vendor_total;
				}
			}

			$ship_sel_totl = (float) $master_order->get_total_shipping();
			$ship_sel_totl = ! empty( $ship_sel_totl ) ? $ship_sel_totl : 1;
			$ship_details  = array();

			foreach ( $master_order->get_items( 'shipping' ) as $key => $shipping_item ) {
				$shipping_data  = $shipping_item->get_data();
				$seller_id      = wc_get_order_item_meta( $key, '_wkmp_seller_id' ) ? wc_get_order_item_meta( $key, '_wkmp_seller_id' ) : 1;
				$shipping_taxes = $shipping_data['taxes']['total'];

				$ship_details[ $seller_id ] = array(
					'key'                   => $key,
					'shipping_method_title' => $shipping_data['method_title'],
					'shipping_instance_id'  => $shipping_data['instance_id'],
					'total_shipping'        => $shipping_data['total'],
				);
			}

			if ( ! empty( $shipping_session ) ) {
				foreach ( $shipping_session as $vkey => $vvalue ) {
					$cur_cost = (float) $vvalue['shipping_cost'];
					$s_tax    = array();
					if ( ! empty( $shipping_taxes ) ) {
						foreach ( $shipping_taxes as $key => $value ) {
							$s_tax[ $key ] = $cur_cost * $value / $ship_sel_totl;
						}
					}
					$vendor_ship_array[ $vvalue['seller_id'] ] = $s_tax;
				}
			} else {
				$seller_count = count( $seller_array );
				$shipping_tl  = $master_order->get_total_shipping();

				foreach ( $seller_array as $slkey => $slvalue ) {
					$s_tax = array();
					if ( $shipping_tl > 0 ) {
						$cur_cost = (float) ( $shipping_tl / $seller_count );
						if ( ! empty( $shipping_taxes ) ) {
							foreach ( $shipping_taxes as $key => $tvalue ) {
								$s_tax[ $key ] = $cur_cost * $tvalue / $shipping_tl;
							}
						}
					}
					$vendor_ship_array[ $slkey ] = $s_tax;
				}
			}

			$ratio = $this->wkmpso_gcd_array( $vendor_array, 0 );
			$ratio = $ratio > 0 ? $ratio : 1; // Check if $ratio is zero before performing division. Prevent division by zero by setting ratio to 1.

			foreach ( $vendor_array as $vkey => $vvalue ) {
				$vendor_array[ $vkey ] = $vvalue / $ratio;
			}

			$vendor_fee_array = array();

			$order_default_args = array(
				'_order_version'              => '',
				'is_vat_exempt'               => '',
				'_edit_lock'                  => '',
				'_wkmpsplit_order'            => '',
				'_wkmpsplit_create_suborders' => '',
			);

			$master_order_meta = $order_default_args;
			$seller_count      = count( $seller_array );
			$shipping_tl       = $master_order->get_shipping_total();

			foreach ( $seller_array as $key => $value ) {
				$shipping_details    = ! empty( $ship_details[ $key ] ) ? $ship_details[ $key ] : $ship_details;
				$shipping_cost       = ! empty( $shipping_details['total_shipping'] ) ? $shipping_details['total_shipping'] : 0;
				$vendor_discount     = 0;
				$tax_shipping_vendor = array();

				if ( isset( $vendor_ship_array[ $key ] ) ) {
					$tax_shipping_vendor = $vendor_ship_array[ $key ];
				}

				try {
					// Start transaction if available.
					wc_transaction_query( 'start' );
					$order_data = array(
						'customer_id'   => $customer_id,
						'customer_note' => $master_order->get_customer_note(),
						'created_via'   => $master_order->get_created_via(),
					);

					// Insert or update the post data.
					$order_id = absint( WC()->session->order_awaiting_payment );
					$order    = wc_get_order( $order_id );

					if ( $order_id && ( $order ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
						$order_data['order_id'] = $order_id;
						$order                  = wc_update_order( $order_data );

						if ( is_wp_error( $order ) ) {
							throw new Exception( sprintf( /* translators: %s̤ WooCommerce Plugin link */ esc_html__( 'Error %d: Unable to create order. Please try again.', 'mp-split-order' ), 522 ) );
						} else {
							$order->remove_order_items();
							do_action( 'woocommerce_resume_order', $order_id );
						}
					} else {
						$order = wc_create_order( $order_data );

						if ( is_wp_error( $order ) ) {
							throw new Exception( sprintf( /* translators: %s̤ WooCommerce Plugin link */ esc_html__( 'Error %d: Unable to create order. Please try again.', 'mp-split-order' ), 520 ) );
						} elseif ( false === $order ) {
							throw new Exception( sprintf( /* translators: %s̤ WooCommerce Plugin link */ esc_html__( 'Error %d: Unable to create order. Please try again.', 'mp-split-order' ), 521 ) );
						} else {
							$order_id = $order->get_id();
						}
					}

					$order_total           = 0;
					$total_sel_tax         = array();
					$product_stock_details = array();

					// Store the line items to the new/resumed order.
					foreach ( $value as $skey => $svalue ) {
						$values        = $svalue['cart_data'];
						$product       = wc_get_product( $values['product_id'] );
						$product_stock = $product->get_stock_quantity();
						$product_stock_details[ $values['product_id'] ]['product_id'] = $values['product_id'];
						$product_stock_details[ $values['product_id'] ]['stock']      = $product_stock;

						$item_id = $order->add_product(
							wc_get_product( $values['product_id'] ),
							$values['quantity'],
							array(
								'variation_id' => $values['variation_id'],
								'totals'       => array(
									'subtotal'     => $values['subtotal'],
									'subtotal_tax' => $values['subtotal_tax'],
									'total'        => $values['total'],
									'tax'          => $values['total_tax'],
									'tax_data'     => $values['taxes'],
								),
							)
						);

						wc_add_order_item_meta( $item_id, 'Sold By', 'wkmp_seller_id=' . get_post_field( 'post_author', $values['product_id'] ) );

						if ( empty( $total_sel_tax ) ) {
							$total_sel_tax = $values['taxes']['total'];
						} elseif ( ! empty( $values['taxes']['total'] ) ) {
							foreach ( $values['taxes']['total'] as $skey => $sval ) {
								if ( ! empty( $total_sel_tax[ $skey ] ) ) {
									$total_sel_tax[ $skey ] += $sval;
								} else {
									$total_sel_tax[ $skey ] = $sval;
								}
							}
						}

						$pro_id = empty( $values['variation_id'] ) ? $values['product_id'] : $values['variation_id'];

						$product_price    = wc_get_product( $pro_id ) ? wc_get_price_excluding_tax( wc_get_product( $pro_id ) ) : 0;
						$product_price    = apply_filters( 'wkmp_modify_product_price', $product_price, $pro_id );
						$vendor_discount += number_format( (float) ( ( $values['quantity'] * $product_price ) - $values['total'] ), 2, '.', '' );
						$order_total     += $values['subtotal'];

						if ( ! $item_id ) {
							throw new Exception( /* translators: %s̤ WooCommerce Plugin link */ sprintf( esc_html__( 'Error %d: Unable to create order. Please try again.', 'mp-split-order' ), 525 ) );
						}
					}

					// Shipping tax.
					if ( ! empty( $tax_shipping_vendor ) ) {
						foreach ( $tax_shipping_vendor as $vkey => $vvalue ) {
							if ( ! empty( $total_sel_tax[ $vkey ] ) ) {
								$total_sel_tax[ $vkey ] += $vvalue;
							}
						}
					}

					// Manage fee according to seller.
					$fee_total = 0;
					foreach ( $vendor_fee_array as $feevalue ) {
						$set_flag = false;
						$itmw     = new WC_Order_Item_Fee();
						$itmw->set_order_id( $order->get_id() );

						if ( isset( $feevalue[ $key ]['name'] ) ) {
							$set_flag = true;
							if ( ! empty( $feevalue[ $key ]['tax'] ) ) {
								$itmw->set_taxes(
									array(
										'total' => $feevalue[ $key ]['tax'],
									)
								);
								foreach ( $total_sel_tax as $fky => $fval ) {
									$total_sel_tax[ $fky ] += $feevalue[ $key ]['tax'][ $fky ];
								}
							}

							$itmw->set_name( $feevalue[ $key ]['name'] );

							$itmw->set_total( floatval( $feevalue[ $key ]['total'] ) );
							$fee_total = $fee_total + floatval( $feevalue[ $key ]['total'] );
						}

						if ( $set_flag ) {
							$order->add_item( $itmw );
						}
					}

					$order_total += (float) $shipping_cost + array_sum( $total_sel_tax ) - $vendor_discount + $fee_total;

					$billing_address  = $master_order->get_address( 'billing' );
					$shipping_address = $master_order->get_address( 'shipping' );
					$order->set_parent_id( $master_order->get_id() );

					// setting final data.
					$order->set_order_key( $master_order->get_order_key() );
					$order->set_payment_method( $master_order->get_payment_method() );
					$order->set_payment_method_title( $master_order->get_payment_method_title() );
					$order->set_customer_ip_address( $master_order->get_customer_ip_address() );
					$order->set_customer_user_agent( $master_order->get_customer_user_agent() );
					$order->set_created_via( $master_order->get_created_via() );
					$order->set_currency( $master_order->get_currency() );
					$order->set_address( $billing_address, 'billing' );
					$order->set_address( $shipping_address, 'shipping' );
					$order->set_payment_method( isset( $available_gateways[ $payment_method ] ) ? $available_gateways[ $payment_method ] : $payment_method );
					$order->set_transaction_id( $master_order->get_transaction_id() );
					$order->set_discount_total( $vendor_discount );
					$order->set_total( $order_total );
					$order->set_shipping_total( $shipping_cost );

					$this->wkmpso_create_split_order_tax_lines( $master_order, $order, $total_sel_tax );

					if ( ! empty( $shipping_details ) ) {
						$this->wkmpso_create_split_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), $shipping_details, $shipping_cost, $tax_shipping_vendor, $pro_id );
					}

					$this->wkmpso_create_split_order_coupon_lines( $master_order, $order, $vendor_discount );
					$order->save();

					$arg = array(
						'ID'          => $order->get_id(),
						'post_author' => $key,
					);

					wp_update_post( $arg );

					if ( ! empty( $master_order_meta ) ) {
						foreach ( $master_order_meta as $meta_key => $meta_value ) {
							$order->update_meta_data( $meta_key, $master_order->get_meta( $meta_key, true ) );
						}
					}

					if ( is_wp_error( $order->get_id() ) ) {
						throw new Exception( $order->get_error_message() );
					}

					$order_meta = $order->get_meta( '_sub_order', true );

					if ( ! empty( $order_meta ) ) {
						array_push( $order_meta, $order->get_id() );
						$order->update_meta_data( '_sub_order', $order_meta );
					} else {
						$order->update_meta_data( '_sub_order', array( $order->get_id() ) );
					}

					$push_arr = array(
						'shipping_method_id' => ! empty( $shipping_details['shipping_method_title'] ) ? $shipping_details['shipping_method_title'] : '',
						'shipping_cost'      => $shipping_cost,
					);

					foreach ( $push_arr as $key1 => $value1 ) {
						$wpdb->insert(
							$wpdb->prefix . 'mporders_meta',
							array(
								'seller_id'  => $key,
								'order_id'   => $order->get_id(),
								'meta_key'   => $key1,
								'meta_value' => $value1,
							)
						);
					}

					do_action( 'woocommerce_checkout_create_order', $order, array() );
					do_action( 'woocommerce_checkout_order_processed', $order->get_id(), array(), $order );

					$order->set_status( $master_order->get_status() );
					$order->calculate_totals( true );
					$order->save();

					// If we got here, the order was created without problems!.
					wc_transaction_query( 'commit' );
				} catch ( Exception $e ) {
					// There was an error adding order data!.
					wc_transaction_query( 'rollback' );

					return new WP_Error( 'checkout-error', $e->getMessage() );
				}
			}

			$master_order->delete_meta_data( '_wkmpsplit_create_suborders' );
			$master_order->save();

			if ( apply_filters( 'mpso_unset_shipping_methods', true ) ) {
				WC()->session->__unset( 'chosen_shipping_methods' );
			}
		}

		/**
		 * Add shipping item meta.
		 *
		 * @param array  $product_id Product ids.
		 * @param object $item Item Object.
		 *
		 * @return void
		 */
		public function wkmpso_add_shipping_item_meta( $product_id, $item ) {
			global $wkmarketplace;

			if ( $product_id > 0 ) {
				$post_author = get_post_field( 'post_author', $product_id );

				if ( $wkmarketplace->wkmp_user_is_seller( $post_author ) ) {
					$shop_name = get_user_meta( $post_author, 'shop_name', true );
					if ( empty( $shop_name ) ) {
						$first_name = get_user_meta( $post_author, 'first_name', true );
						$first_name = empty( $first_name ) ? get_user_meta( $post_author, 'billing_first_name', true ) : $first_name;
						$first_name = empty( $first_name ) ? get_user_meta( $post_author, 'shipping_first_name', true ) : $first_name;

						if ( ! empty( $first_name ) ) {
							$last_name = get_user_meta( $post_author, 'last_name', true );
							$last_name = empty( $last_name ) ? get_user_meta( $post_author, 'billing_last_name', true ) : $last_name;
							$last_name = empty( $last_name ) ? get_user_meta( $post_author, 'shipping_last_name', true ) : $last_name;

							$shop_name = $first_name . ' ' . $last_name;
						}
					}
					$item->update_meta_data( 'Store name', $shop_name );
					$item->update_meta_data( '_wkmp_seller_id', $post_author );
				}
			}
		}

		/**
		 * Create split order tax.
		 *
		 * @param mixed $master_order master order.
		 * @param mixed $order order.
		 * @param mixed $tax_total tax total.
		 */
		public function wkmpso_create_split_order_tax_lines( $master_order, $order, $tax_total ) {
			if ( ! isset( $tax_total ) || empty( $tax_total ) ) {
				return;
			}
			$item = new \WC_Order_Item_Tax();
			foreach ( array_keys( $tax_total ) as $tax_rate_id ) {
				if ( $tax_rate_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
					$item->set_props(
						array(
							'rate_id'            => $tax_rate_id,
							'tax_total'          => $tax_total[ $tax_rate_id ],
							'shipping_tax_total' => 0,
							'rate_code'          => \WC_Tax::get_rate_code( $tax_rate_id ),
							'label'              => \WC_Tax::get_rate_label( $tax_rate_id ),
							'compound'           => \WC_Tax::is_compound( $tax_rate_id ),
						)
					);
					// Add item to order and save.
				}
			}
			$order->add_item( $item );
		}

		/**
		 * Create split order shipping class.
		 *
		 * @param mixed $order order.
		 * @param mixed $chosen_shipping_methods choose shipping method.
		 * @param mixed $shipping_details shipping details.
		 * @param mixed $shipping_total shipping total.
		 * @param mixed $tax_shipping_vendor tax shipping vendor.
		 * @param mixed $product_id product id.
		 */
		public function wkmpso_create_split_order_shipping_lines( $order, $chosen_shipping_methods, $shipping_details, $shipping_total, $tax_shipping_vendor, $product_id ) {
			$instance_id = 0;
			// Fallback if $chosen_shipping_methods is empty.
			if ( empty( $chosen_shipping_methods ) && ! empty( $shipping_details['shipping_instance_id'] ) ) {
				// need the actual method_id — replace 'marketplace_shipping' with the correct one for your case.
				$instance_id = $shipping_details['shipping_instance_id'];

				global $wpdb;
				$method_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE instance_id = %d",
						$instance_id
					)
				);

				if ( $method_id ) {
					$chosen_shipping_methods = array( $method_id . ':' . $instance_id );
				}
			}

			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product && ! $product->is_virtual() ) {
				$item = new \WC_Order_Item_Shipping();

				$item->legacy_package_key = $shipping_details['key']; // For legacy actions.

				$item->set_props(
					array(
						'method_title' => $shipping_details['shipping_method_title'],
						'method_id'    => $chosen_shipping_methods[0],
						'instance_id'  => $shipping_details['shipping_instance_id'],
						'total'        => ! empty( $shipping_total ) ? wc_format_decimal( $shipping_total ) : 0,
						'taxes'        => array( 'total' => $tax_shipping_vendor ),
					)
				);

				$this->wkmpso_add_shipping_item_meta( $product_id, $item );

				$order->add_item( $item );
			}
		}


		/**
		 * Add coupon lines to the order.
		 *
		 * @param object $master_order master order.
		 * @param object $order order.
		 * @param float  $discount_total discount total.
		 */
		public function wkmpso_create_split_order_coupon_lines( $master_order, $order, $discount_total ) {
			foreach ( $master_order->get_coupon_codes() as $coupon_code ) {
				$item = new \WC_Order_Item_Coupon();
				$item->set_props(
					array(
						'code'         => $coupon_code,
						'discount'     => $discount_total,
						'discount_tax' => 0,
					)
				);
				// Add item to order and save.
				$order->add_item( $item );
			}
		}

		/**
		 * Return $price in proper format as per WooCommerce configuration.
		 *
		 * @param string $price Price.
		 * @param array  $args Arguments.
		 *
		 * @return string $price
		 */
		public function wkmpso_get_formatted_price( $price = 0, $args = array() ) {
			return html_entity_decode( strip_tags( wc_price( $price, $args ) ) );
		}
	}
}
