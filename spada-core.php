<?php
/**
 * Plugin Name: Spada Core
 * Plugin URI: https://www.veroke.com/
 * Description: Core functionality for SPADA: customer account portal with phone, WhatsApp, and email OTP authentication, and product Buy Now workflow.
 * Version: 1.6.9
 * Author: Veroke
 * Author URI: https://www.veroke.com/
 * Text Domain: spada-core
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPADA_CORE_VERSION', '1.8.0' );
define( 'SPADA_CORE_FILE', __FILE__ );
define( 'SPADA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'SPADA_CORE_PATH', plugin_dir_path( __FILE__ ) );

// Backwards compatibility aliases for Buy Now feature constants
define( 'SPADA_BUY_NOW_VERSION', SPADA_CORE_VERSION );
define( 'SPADA_BUY_NOW_FILE', SPADA_CORE_FILE );
define( 'SPADA_BUY_NOW_URL', SPADA_CORE_URL );
define( 'SPADA_BUY_NOW_PATH', SPADA_CORE_PATH );

// Load Buy Now feature
require_once SPADA_CORE_PATH . 'includes/class-spada-buy-now.php';

// Load Authentication & OTP modules
require_once SPADA_CORE_PATH . 'includes/authentication/class-spada-welcome-email.php';
require_once SPADA_CORE_PATH . 'includes/authentication/class-spada-otp-email.php';
require_once SPADA_CORE_PATH . 'includes/authentication/class-spada-auth-ajax.php';
require_once SPADA_CORE_PATH . 'includes/mobile-login/class-spada-mobile-login.php';
require_once SPADA_CORE_PATH . 'includes/authentication/class-spada-auth.php';

// Load My Account customization module
require_once SPADA_CORE_PATH . 'includes/my-account/class-spada-my-account.php';

// Load Fluid Checkout Order Summary customization module
require_once SPADA_CORE_PATH . 'includes/fluid-checkout/class-spada-fc-order-summary.php';

// Load Fluid Checkout Fields & Email Sync module
require_once SPADA_CORE_PATH . 'includes/fluid-checkout/class-spada-fc-checkout-fields.php';

// Initialize Buy Now instance
Spada_Buy_Now::instance();

// Initialize Spada Core modules on plugins_loaded
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		Spada_Auth::init();
		Spada_My_Account::init();
		Spada_FC_Order_Summary::init();
		Spada_FC_Checkout_Fields::init();
	}
}, 20 );

// Register global design tokens & CSS variables stylesheet
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'spada-variables',
		SPADA_CORE_URL . 'assets/css/spada-variables.css',
		array(),
		SPADA_CORE_VERSION
	);
}, 5 );

// Global RTL / Arabic detection helper for Spada templates & emails
if ( ! function_exists( 'spada_is_rtl' ) ) {
	/**
	 * Check if current context/request is Arabic or RTL.
	 *
	 * @return bool
	 */
	function spada_is_rtl() {
		if ( class_exists( 'Spada_OTP_Email' ) ) {
			return Spada_OTP_Email::is_arabic();
		}
		if ( class_exists( 'Spada_Welcome_Email' ) ) {
			return Spada_Welcome_Email::is_arabic();
		}
		return ( function_exists( 'is_rtl' ) && is_rtl() ) || ( get_locale() === 'ar' || strpos( get_locale(), 'ar' ) === 0 );
	}
}

