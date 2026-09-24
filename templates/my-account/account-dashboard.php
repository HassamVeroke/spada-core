<?php
/**
 * SPADA My Account Logged-In Dashboard Template
 *
 * Replaces default WooCommerce dashboard with custom profile card,
 * floating notched labels, orders & subscriptions tabs, and logout button.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id      = get_current_user_id();
$current_user = wp_get_current_user();

if ( ! $user_id || ! $current_user ) {
	return;
}

// User data
$display_name = ! empty( $current_user->display_name ) ? $current_user->display_name : ( ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->user_login );
$phone        = get_user_meta( $user_id, 'billing_phone', true );
if ( empty( $phone ) ) {
	$phone = get_user_meta( $user_id, 'shipping_phone', true );
}
$email        = ! empty( $current_user->user_email ) ? $current_user->user_email : get_user_meta( $user_id, 'billing_email', true );
$address      = get_user_meta( $user_id, 'shipping_address_1', true );
if ( empty( $address ) ) {
	$address = get_user_meta( $user_id, 'billing_address_1', true );
}
if ( empty( $address ) && function_exists( 'wc_get_orders' ) ) {
	$recent_orders = wc_get_orders(
		array(
			'customer' => $user_id,
			'limit'    => 1,
			'orderby'  => 'date',
			'order'    => 'DESC',
		)
	);
	if ( ! empty( $recent_orders ) ) {
		$last_order = $recent_orders[0];
		$address    = $last_order->get_shipping_address_1();
		if ( empty( $address ) ) {
			$address = $last_order->get_billing_address_1();
		}
		if ( ! empty( $address ) ) {
			update_user_meta( $user_id, 'shipping_address_1', $address );
			update_user_meta( $user_id, 'billing_address_1', $address );
		}
	}
}

// Clean and heal combined/corrupted address in user meta
if ( ! empty( $address ) && class_exists( 'Spada_My_Account' ) && method_exists( 'Spada_My_Account', 'clean_address_string' ) ) {
	$cleaned_address = Spada_My_Account::clean_address_string( $address );
	if ( ! empty( $cleaned_address ) && $cleaned_address !== $address ) {
		$address = $cleaned_address;
		update_user_meta( $user_id, 'shipping_address_1', $cleaned_address );
		update_user_meta( $user_id, 'billing_address_1', $cleaned_address );
	}
}

// Endpoints
$orders_url        = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) : '#';
$subscriptions_url = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'subscriptions', '', wc_get_page_permalink( 'myaccount' ) ) : '#';
$logout_url        = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url();

$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
?>

<div class="spada-account-wrapper" id="spada-account-portal">
	<!-- Main Profile Card -->
	<div class="spada-account-card">
		<form id="spada-profile-form" class="spada-profile-form" method="post" action="">
			<?php wp_nonce_field( 'spada_update_account_action', 'spada_account_nonce' ); ?>

			<!-- Notice Alert Container -->
			<div id="spada-profile-notice" class="spada-profile-notice is-hidden" role="alert"></div>

			<!-- Fields Grid -->
			<div class="spada-profile-grid">
				<!-- Row 1: Your Name (left) & Phone Number (right) -->
				<div class="spada-profile-col">
					<div class="spada-floating-field">
						<label for="spada_account_display_name">
							<?php echo $is_arabic ? esc_html__( 'الاسم *', 'spada-core' ) : esc_html__( 'Your Name *', 'spada-core' ); ?>
						</label>
						<input
							type="text"
							id="spada_account_display_name"
							name="spada_account_display_name"
							value="<?php echo esc_attr( $display_name ); ?>"
							required
							autocomplete="name"
						/>
					</div>
				</div>

				<div class="spada-profile-col">
					<div class="spada-floating-field">
						<label for="spada_billing_phone">
							<?php echo $is_arabic ? esc_html__( 'رقم الجوال *', 'spada-core' ) : esc_html__( 'Phone Number *', 'spada-core' ); ?>
						</label>
						<input
							type="tel"
							id="spada_billing_phone"
							name="spada_billing_phone"
							value="<?php echo esc_attr( $phone ); ?>"
							required
							autocomplete="tel"
						/>
					</div>
				</div>

				<!-- Row 2: Email (left, in place of removed password) & Address (right, in place of email) -->
				<div class="spada-profile-col">
					<div class="spada-floating-field">
						<label for="spada_account_email">
							<?php echo $is_arabic ? esc_html__( 'البريد الإلكتروني *', 'spada-core' ) : esc_html__( 'Email *', 'spada-core' ); ?>
						</label>
						<input
							type="email"
							id="spada_account_email"
							name="spada_account_email"
							value="<?php echo esc_attr( $email ); ?>"
							required
							autocomplete="email"
						/>
					</div>
				</div>

				<div class="spada-profile-col">
					<div class="spada-floating-field">
						<label for="spada_billing_address">
							<?php echo $is_arabic ? esc_html__( 'العنوان', 'spada-core' ) : esc_html__( 'Address', 'spada-core' ); ?>
						</label>
						<input
							type="text"
							id="spada_billing_address"
							name="spada_billing_address"
							value="<?php echo esc_attr( $address ); ?>"
							autocomplete="street-address"
						/>
					</div>
				</div>
			</div>

			<!-- Save Changes Button -->
			<button type="submit" class="spada-account-save-btn" id="spada-save-profile-btn">
				<span class="spada-btn-text"><?php echo $is_arabic ? esc_html__( 'حفظ التغييرات', 'spada-core' ) : esc_html__( 'SAVE CHANGES', 'spada-core' ); ?></span>
				<span class="spada-btn-spinner is-hidden" aria-hidden="true"></span>
			</button>
		</form>
	</div>

	<!-- Navigation Tabs Below Card: ORDERS & SUBSCRIPTIONS -->
	<div class="spada-account-nav-tabs">
		<a href="<?php echo esc_url( $orders_url ); ?>" role="button" class="spada-tab-btn" id="spada-tab-orders" data-tab="orders">
			<?php echo $is_arabic ? esc_html__( 'الطلبات', 'spada-core' ) : esc_html__( 'ORDERS', 'spada-core' ); ?>
		</a>
		<a href="<?php echo esc_url( $subscriptions_url ); ?>" role="button" class="spada-tab-btn" id="spada-tab-subscriptions" data-tab="subscriptions">
			<?php echo $is_arabic ? esc_html__( 'الاشتراكات', 'spada-core' ) : esc_html__( 'SUBSCRIPTIONS', 'spada-core' ); ?>
		</a>
	</div>

	<!-- Tab Panels Section (Displays orders or subscriptions) -->
	<div class="spada-account-tab-panels">
		<!-- Orders Panel -->
		<div class="spada-tab-panel is-hidden" id="spada-panel-orders">
			<?php
			$customer_orders = function_exists( 'wc_get_orders' ) ? wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => 10,
				)
			) : array();

			if ( ! empty( $customer_orders ) ) :
				?>
				<table class="spada-account-table my_account_orders">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Date', 'woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $customer_orders as $order ) : ?>
							<tr>
								<td>#<?php echo esc_html( $order->get_order_number() ); ?></td>
								<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
								<td><span class="spada-order-status spada-status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td>
									<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button view spada-table-view-btn">
										<?php esc_html_e( 'View', 'woocommerce' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="spada-empty-state">
					<p><?php echo $is_arabic ? esc_html__( 'لا توجد طلبات سابقة.', 'spada-core' ) : esc_html__( 'No orders placed yet.', 'spada-core' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<!-- Subscriptions Panel -->
		<div class="spada-tab-panel is-hidden" id="spada-panel-subscriptions">
			<?php
			$subscriptions = function_exists( 'wcs_get_users_subscriptions' ) ? wcs_get_users_subscriptions( $user_id ) : array();

			if ( ! empty( $subscriptions ) ) :
				?>
				<table class="spada-account-table my_account_subscriptions">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Subscription', 'woocommerce-subscriptions' ); ?></th>
							<th><?php esc_html_e( 'Status', 'woocommerce-subscriptions' ); ?></th>
							<th><?php esc_html_e( 'Next Payment', 'woocommerce-subscriptions' ); ?></th>
							<th><?php esc_html_e( 'Total', 'woocommerce-subscriptions' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'woocommerce-subscriptions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subscriptions as $subscription ) : ?>
							<tr>
								<td>#<?php echo esc_html( $subscription->get_order_number() ); ?></td>
								<td><span class="spada-order-status spada-status-<?php echo esc_attr( $subscription->get_status() ); ?>"><?php echo esc_html( wcs_get_subscription_status_name( $subscription->get_status() ) ); ?></span></td>
								<td><?php echo esc_html( $subscription->get_date_to_display( 'next_payment' ) ); ?></td>
								<td><?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?></td>
								<td>
									<a href="<?php echo esc_url( $subscription->get_view_order_url() ); ?>" class="button view spada-table-view-btn">
										<?php esc_html_e( 'View', 'woocommerce' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="spada-empty-state">
					<p><?php echo $is_arabic ? esc_html__( 'لا توجد اشتراكات نشطة.', 'spada-core' ) : esc_html__( 'No active subscriptions found.', 'spada-core' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Logout Button -->
	<a href="<?php echo esc_url( $logout_url ); ?>" class="spada-account-logout-btn" id="spada-account-logout-btn">
		<?php echo $is_arabic ? esc_html__( 'تسجيل الخروج', 'spada-core' ) : esc_html__( 'LOG OUT', 'spada-core' ); ?>
	</a>
</div>
