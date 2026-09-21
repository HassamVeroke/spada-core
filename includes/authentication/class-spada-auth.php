<?php
/**
 * SPADA Authentication Orchestrator
 *
 * Coordinates UI views, asset loading, localized data, and checkout login modal.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_Auth {

	/**
	 * Init hooks.
	 */
	public static function init() {
		Spada_Auth_Ajax::init();
		Spada_Mobile_Login::init();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_shortcode( 'spada_auth_portal', array( __CLASS__, 'render_auth_portal_shortcode' ) );

		// Hook into My Account login page
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'render_account_portal' ), 5 );
	}

	/**
	 * Enqueue styles and scripts conditionally.
	 */
	public static function enqueue_assets() {
		$is_account = function_exists( 'is_account_page' ) && is_account_page();

		if ( ! $is_account ) {
			return;
		}

		wp_enqueue_style(
			'spada-google-font-oswald',
			'https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'spada-authentication',
			SPADA_CORE_URL . 'assets/css/authentication.css',
			array( 'spada-variables', 'spada-google-font-oswald' ),
			SPADA_CORE_VERSION
		);

		wp_enqueue_script(
			'spada-authentication',
			SPADA_CORE_URL . 'assets/js/authentication.js',
			array( 'jquery' ),
			SPADA_CORE_VERSION,
			true
		);

		wp_localize_script(
			'spada-authentication',
			'SpadaAuthData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'spada_auth_nonce' ),
				'isCheckout'   => 'no',
				'checkoutUrl'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'accountUrl'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '',
				'isRtl'        => is_rtl(),
				'i18n'         => array(
					'signInEmail'      => __( 'Sign in with Email', 'spada-core' ),
					'signInWhatsapp'   => __( 'Sign in with Whatsapp', 'spada-core' ),
					'signInSms'        => __( 'Sign in with SMS', 'spada-core' ),
					'signupEmail'      => __( 'Signup using Email', 'spada-core' ),
					'signupWhatsapp'   => __( 'Signup using Whatsapp', 'spada-core' ),
					'signupSms'        => __( 'Signup using SMS', 'spada-core' ),
					'enterEmail'       => __( 'Enter your email address', 'spada-core' ),
					'subEmail'         => __( "We'll send a six digit code to your email adress.", 'spada-core' ),
					'enterWhatsapp'    => __( 'Enter your whatsapp number', 'spada-core' ),
					'subWhatsapp'      => __( "We'll send a six digit code to your whatsapp", 'spada-core' ),
					'enterMobile'      => __( 'Enter your mobile number', 'spada-core' ),
					'subMobile'        => __( "We'll send a six digit code to your mobile number", 'spada-core' ),
					'checkEmail'       => __( 'Check your email address', 'spada-core' ),
					'checkWhatsapp'    => __( 'Check your whatsapp account', 'spada-core' ),
					'checkMobile'      => __( 'Check your messages', 'spada-core' ),
					'promptEmail'      => __( "We've sent a six digit code to your email adress", 'spada-core' ),
					'promptWhatsapp'   => __( "We've sent a six digit code to your whatsapp account on", 'spada-core' ),
					'promptMobile'     => __( "We've sent a six digit code to your mobile number", 'spada-core' ),
					'changeEmail'      => __( 'Change Email', 'spada-core' ),
					'changeNumber'     => __( 'Change Number', 'spada-core' ),
					'didntReceive'     => __( "Didn't receive the code?", 'spada-core' ),
					'didntEmail'       => __( "Didn't receive the email?", 'spada-core' ),
					'didntWhatsapp'    => __( "Didn't receive the message on whatsapp?", 'spada-core' ),
					'didntMobile'      => __( "Didn't receive the message on number?", 'spada-core' ),
					'invalidOtp'       => __( 'Please fill in all 6 digits of the verification code.', 'spada-core' ),
					'invalidEmail'     => __( 'Please enter a valid email address.', 'spada-core' ),
					'invalidPhone'     => __( 'Please enter a valid phone number.', 'spada-core' ),
					'resendIn'         => __( 'resend in', 'spada-core' ),
					'sending'          => __( 'Sending...', 'spada-core' ),
					'verifying'        => __( 'Verifying...', 'spada-core' ),
					'continue'         => __( 'Continue', 'spada-core' ),
				),
			)
		);
	}

	/**
	 * Render the account portal.
	 */
	public static function render_account_portal() {
		if ( is_user_logged_in() ) {
			return;
		}

		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		include SPADA_CORE_PATH . 'templates/authentication/account-portal.php';

		// Prevent default unstyled WooCommerce forms from appearing below
		echo '<div class="spada-native-login-hidden" style="display:none !important;">';
		add_action(
			'woocommerce_after_customer_login_form',
			function() {
				echo '</div>';
			},
			99
		);
	}

	/**
	 * Shortcode handler.
	 */
	public static function render_auth_portal_shortcode() {
		ob_start();
		self::render_account_portal();
		return ob_get_clean();
	}
}
