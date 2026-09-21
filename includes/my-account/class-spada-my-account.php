<?php
/**
 * SPADA My Account Customization Class
 *
 * Enqueues dedicated styling, filters navigation items,
 * and styles customer dashboard cards using real WooCommerce APIs.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_My_Account {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'customize_account_menu_items' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_customer_dashboard_overview' ), 5 );
	}

	/**
	 * Enqueue assets on My Account page.
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/css/my-account.css',
			array( 'spada-variables' ),
			SPADA_CORE_VERSION
		);

		wp_enqueue_script(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/js/my-account.js',
			array( 'jquery' ),
			SPADA_CORE_VERSION,
			true
		);
	}

	/**
	 * Customize account navigation items.
	 *
	 * @param array $items Existing items.
	 * @return array Modified items.
	 */
	public static function customize_account_menu_items( $items ) {
		return $items;
	}

	/**
	 * Render customer dashboard overview.
	 */
	public static function render_customer_dashboard_overview() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$current_user = wp_get_current_user();
		$customer_orders = function_exists( 'wc_get_orders' ) ? wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'return'      => 'ids',
			)
		) : array();

		$order_count = count( $customer_orders );
		?>
		<div class="spada-account-welcome-banner">
			<div class="spada-welcome-avatar">
				<?php echo get_avatar( $user_id, 56 ); ?>
			</div>
			<div class="spada-welcome-content">
				<h2 class="spada-welcome-title">
					<?php
					printf(
						/* translators: %s: Customer first name */
						esc_html__( 'Hello, %s!', 'spada-core' ),
						esc_html( ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name )
					);
					?>
				</h2>
				<p class="spada-welcome-subtitle">
					<?php
					printf(
						/* translators: %d: Order count */
						esc_html__( 'You have %d orders placed with Spada.', 'spada-core' ),
						absint( $order_count )
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
