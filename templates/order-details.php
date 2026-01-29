<?php
/**
 * Order details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.6.0
 */

defined( 'ABSPATH' ) || exit;

$order_details = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order_details ) {
	return;
}
$order_items           = $order_details->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note    = $order_details->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$show_customer_details = is_user_logged_in() || $order_details->get_user_id() === get_current_user_id();
$downloads             = $order_details->get_downloadable_items();
$show_downloads        = $order_details->has_downloadable_item() && $order_details->is_download_permitted();
if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}
?>
<section class="woocommerce-order-details">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order_details ); ?>
	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'mp-split-order' ); ?></h2>
	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'mp-split-order' ); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'mp-split-order' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order_details );
			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();
				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order_details,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}
			do_action( 'woocommerce_order_details_after_order_table_items', $order_details );
			?>
		</tbody>
		<tfoot>
			<?php
			foreach ( $order_details->get_order_item_totals() as $key => $total ) {
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
					<td><?php echo ( 'payment_method' === $key ) ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<?php
			} if ( $order_details->get_customer_note() ) :
				?>
				<tr>
					<th><?php esc_html_e( 'Note:', 'mp-split-order' ); ?></th>
					<td><?php echo wp_kses_post( nl2br( wptexturize( $order_details->get_customer_note() ) ) ); ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>
	<?php do_action( 'woocommerce_order_details_after_order_table', $order_details ); ?>
</section>
<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
do_action( 'woocommerce_after_order_details', $order_details );
