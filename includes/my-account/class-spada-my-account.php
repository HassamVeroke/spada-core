<?php
/**
 * SPADA My Account Customization Class
 *
 * Enqueues dedicated styling, removes sidebar navigation & hero section,
 * and renders customer profile card matching screenshot exactly.
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

		// Remove default WooCommerce account navigation (sidebar)
		add_action( 'template_redirect', array( __CLASS__, 'remove_default_account_navigation' ) );

		// Render custom dashboard matching screenshot
		remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard' );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_customer_dashboard_overview' ), 5 );

		// AJAX and POST handler for saving profile changes
		add_action( 'wp_ajax_spada_update_account_details', array( __CLASS__, 'ajax_update_account_details' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post_update_account_details' ) );
	}

	/**
	 * Remove default WooCommerce account navigation.
	 */
	public static function remove_default_account_navigation() {
		if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) {
			remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
		}
	}

	/**
	 * Enqueue assets on My Account page.
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		// Enqueue Oswald font for headings & buttons
		wp_enqueue_style(
			'spada-google-font-oswald',
			'https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/css/my-account.css',
			array( 'spada-variables', 'spada-google-font-oswald' ),
			SPADA_CORE_VERSION
		);

		wp_enqueue_script(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/js/my-account.js',
			array( 'jquery' ),
			SPADA_CORE_VERSION,
			true
		);

		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );

		wp_localize_script(
			'spada-my-account',
			'SpadaAccountData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'spada_update_account_action' ),
				'isRtl'   => $is_arabic,
				'authHero' => array(
					'loginHead'  => $is_arabic ? 'تسجيل الدخول' : 'Login',
					'loginDesc'  => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لتسجيل الدخول إلى حسابك.' : 'Please provide necessary details to login to your account.',
					'signupHead' => $is_arabic ? 'إنشاء حساب' : 'Sign up',
					'signupDesc' => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لإنشاء حسابك.' : 'Please provide necessary details to sign up to your account.',
				),
				'i18n'    => array(
					'saving'        => $is_arabic ? 'جاري الحفظ...' : __( 'Saving...', 'spada-core' ),
					'saveChanges'   => $is_arabic ? 'حفظ التغييرات' : __( 'SAVE CHANGES', 'spada-core' ),
					'updateSuccess' => $is_arabic ? 'تم حفظ التغييرات بنجاح.' : __( 'Account details updated successfully.', 'spada-core' ),
					'genericError'  => $is_arabic ? 'حدث خطأ، يرجى المحاولة مرة أخرى.' : __( 'Something went wrong. Please try again.', 'spada-core' ),
				),
			)
		);
	}

	/**
	 * Render customer dashboard overview.
	 */
	public static function render_customer_dashboard_overview() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$template_path = SPADA_CORE_PATH . 'templates/my-account/account-dashboard.php';
		if ( file_exists( $template_path ) ) {
			require $template_path;
		}
	}

	/**
	 * AJAX Update account details.
	 */
	public static function ajax_update_account_details() {
		check_ajax_referer( 'spada_update_account_action', 'security' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to update your account.', 'spada-core' ) ) );
		}

		$display_name = isset( $_POST['spada_account_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_display_name'] ) ) : '';
		$phone        = isset( $_POST['spada_billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_phone'] ) ) : '';
		$email        = isset( $_POST['spada_account_email'] ) ? sanitize_email( wp_unslash( $_POST['spada_account_email'] ) ) : '';
		$address      = isset( $_POST['spada_billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_address'] ) ) : '';
		$password     = isset( $_POST['spada_account_password'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_password'] ) ) : '';

		if ( empty( $display_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'spada-core' ) ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'spada-core' ) ) );
		}

		// Check email uniqueness if changed
		$current_user = wp_get_current_user();
		if ( strtolower( $current_user->user_email ) !== strtolower( $email ) ) {
			$existing_user_id = email_exists( $email );
			if ( $existing_user_id && $existing_user_id !== $user_id ) {
				wp_send_json_error( array( 'message' => __( 'This email is already in use by another account.', 'spada-core' ) ) );
			}
		}

		// Update user standard fields
		$userdata = array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'first_name'   => $display_name,
			'user_email'   => $email,
		);

		$result = wp_update_user( $userdata );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Update user meta
		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, 'shipping_phone', $phone );
		}
		if ( ! empty( $address ) ) {
			update_user_meta( $user_id, 'billing_address_1', $address );
			update_user_meta( $user_id, 'shipping_address_1', $address );
		}
		update_user_meta( $user_id, 'billing_email', $email );

		// Maybe update password
		if ( ! empty( $password ) ) {
			wp_set_password( $password, $user_id );
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}

		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$msg = $is_arabic ? 'تم حفظ التغييرات بنجاح.' : __( 'Account details updated successfully.', 'spada-core' );

		wp_send_json_success( array( 'message' => $msg ) );
	}

	/**
	 * Fallback standard POST handler for account updates.
	 */
	public static function handle_post_update_account_details() {
		if ( ! isset( $_POST['spada_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spada_account_nonce'] ) ), 'spada_update_account_action' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$display_name = isset( $_POST['spada_account_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_display_name'] ) ) : '';
		$phone        = isset( $_POST['spada_billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_phone'] ) ) : '';
		$email        = isset( $_POST['spada_account_email'] ) ? sanitize_email( wp_unslash( $_POST['spada_account_email'] ) ) : '';
		$address      = isset( $_POST['spada_billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_address'] ) ) : '';
		$password     = isset( $_POST['spada_account_password'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_password'] ) ) : '';

		if ( ! empty( $display_name ) && ! empty( $email ) && is_email( $email ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
					'first_name'   => $display_name,
					'user_email'   => $email,
				)
			);

			if ( ! empty( $phone ) ) {
				update_user_meta( $user_id, 'billing_phone', $phone );
				update_user_meta( $user_id, 'shipping_phone', $phone );
			}
			if ( ! empty( $address ) ) {
				update_user_meta( $user_id, 'billing_address_1', $address );
				update_user_meta( $user_id, 'shipping_address_1', $address );
			}
			update_user_meta( $user_id, 'billing_email', $email );

			if ( ! empty( $password ) ) {
				wp_set_password( $password, $user_id );
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );
			}
		}

		wp_safe_redirect( add_query_arg( 'spada_updated', '1', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}
}
