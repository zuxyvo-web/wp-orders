<?php
/**
 * Thankyou page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version     3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'wkmpso_create_suborders', $order );
$main_order            = $order->get_id();
$show_customer_details = is_user_logged_in() || $order->get_user_id() === get_current_user_id();

/**
 * Check is main order.
 *
 * @param mixed $main_order main order.
 *
 * @return bool/array
 */
function is_main_order( $main_order ) {
	global $wk_mpso;

	$child_orders = $wk_mpso->wkmpso_get_child_order_ids( $main_order );

	if ( ! empty( $child_orders ) ) {
		$ids = wp_list_pluck( $child_orders, 'ID' );
		return $ids;
	}

	return false;
}

$is_having_child = is_main_order( $main_order );

if ( ! empty( $is_having_child ) ) {
	foreach ( $is_having_child as $key => $value ) {
		$order_details = new WC_Order( $value ); ?>
		<div class="woocommerce-order">
			<?php if ( $order_details ) : ?>
				<?php if ( $order_details->has_status( 'failed' ) ) : ?>
					<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'mp-split-order' ); ?></p>
					<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
						<a href="<?php echo esc_url( $order_details->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'mp-split-order' ); ?></a>
						<?php if ( is_user_logged_in() ) : ?>
							<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'mp-split-order' ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mp-split-order' ), $order_details ) ); ?></p>
					<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
						<li class="woocommerce-order-overview__order order">
							<?php esc_html_e( 'Order number:', 'mp-split-order' ); ?>
							<strong><?php echo esc_html( $order_details->get_order_number() ); ?></strong>
						</li>
						<li class="woocommerce-order-overview__date date">
							<?php esc_html_e( 'Date:', 'mp-split-order' ); ?>
							<strong><?php echo esc_html( wc_format_datetime( $order_details->get_date_created() ) ); ?></strong>
						</li>
						<?php if ( is_user_logged_in() || $order_details->get_user_id() === get_current_user_id() && $order_details->get_billing_email() ) : ?>
							<li class="woocommerce-order-overview__email email">
								<?php esc_html_e( 'Email:', 'mp-split-order' ); ?>
								<strong><?php echo esc_html( $order_details->get_billing_email() ); ?></strong>
							</li>
						<?php endif; ?>
						<li class="woocommerce-order-overview__total total">
							<?php esc_html_e( 'Total:', 'mp-split-order' ); ?>
							<strong><?php echo wp_kses_post( $order_details->get_formatted_order_total() ); ?></strong>
						</li>
						<?php if ( $order_details->get_payment_method_title() ) : ?>
							<li class="woocommerce-order-overview__payment-method method">
								<?php esc_html_e( 'Payment method:', 'mp-split-order' ); ?>
								<strong><?php echo wp_kses_post( $order_details->get_payment_method_title() ); ?></strong>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<?php do_action( 'woocommerce_thankyou_' . $order_details->get_payment_method(), $order_details->get_id() ); ?>
				<?php do_action( 'woocommerce_thankyou', $order_details->get_id() ); ?>
			<?php else : ?>
				<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mp-split-order' ), null ) ); ?></p>
			<?php endif; ?>
			</div>
		<?php
	}
} else {
	$order_details = $order;
	?>
	<div class="woocommerce-order">
		<?php if ( $order_details ) : ?>
			<?php if ( $order_details->has_status( 'failed' ) ) : ?>
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'mp-split-order' ); ?></p>
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
					<a href="<?php echo esc_url( $order_details->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'mp-split-order' ); ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'mp-split-order' ); ?></a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mp-split-order' ), $order_details ) ); ?></p>
				<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<li class="woocommerce-order-overview__order order">
						<?php esc_html_e( 'Order number:', 'mp-split-order' ); ?>
						<strong><?php echo esc_html( $order_details->get_order_number() ); ?></strong>
					</li>
					<li class="woocommerce-order-overview__date date">
						<?php esc_html_e( 'Date:', 'mp-split-order' ); ?>
						<strong><?php echo esc_html( wc_format_datetime( $order_details->get_date_created() ) ); ?></strong>
					</li>
					<?php if ( is_user_logged_in() || $order_details->get_user_id() === get_current_user_id() && $order_details->get_billing_email() ) : ?>
						<li class="woocommerce-order-overview__email email">
							<?php esc_html_e( 'Email:', 'mp-split-order' ); ?>
							<strong><?php echo esc_html( $order_details->get_billing_email() ); ?></strong>
						</li>
					<?php endif; ?>
					<li class="woocommerce-order-overview__total total">
						<?php esc_html_e( 'Total:', 'mp-split-order' ); ?>
						<strong><?php echo wp_kses_post( $order_details->get_formatted_order_total() ); ?></strong>
					</li>
					<?php if ( $order_details->get_payment_method_title() ) : ?>
						<li class="woocommerce-order-overview__payment-method method">
							<?php esc_html_e( 'Payment method:', 'mp-split-order' ); ?>
							<strong><?php echo wp_kses_post( $order_details->get_payment_method_title() ); ?></strong>
						</li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
			<?php do_action( 'woocommerce_thankyou_' . $order_details->get_payment_method(), $order_details->get_id() ); ?>
			<?php do_action( 'woocommerce_thankyou', $order_details->get_id() ); ?>
		<?php else : ?>
			<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mp-split-order' ), null ) ); ?></p>
		<?php endif; ?>
		</div>
	<?php
}

if ( $show_customer_details ) {
	wc_get_template( 'order/order-details-customer.php', array( 'order' => $order_details ) );
}
