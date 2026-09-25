<?php
/**
 * SPADA Welcome Email Manager
 *
 * Ensures newly registered customers receive the official WooCommerce Welcome Email
 * (WC_Email_Customer_New_Account) after successful signup via any method (Email OTP,
 * checkout, or registration forms).
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_Welcome_Email {

	/**
	 * Meta key to track if welcome email was dispatched.
	 */
	const META_KEY_SENT = '_spada_welcome_email_sent';

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Hook into standard WooCommerce customer creation
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'on_woocommerce_created_customer' ), 20, 3 );

		// Hook into WordPress user_register as fallback
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 20, 1 );

		// Listen to native notification hook to record sent state
		add_action( 'woocommerce_created_customer_notification', array( __CLASS__, 'on_native_notification' ), 5, 1 );

		// Ensure customer_new_account email is enabled for real customers
		add_filter( 'woocommerce_email_enabled_customer_new_account', array( __CLASS__, 'filter_email_enabled' ), 20, 3 );

		// Provide localized subject and heading if defaults are used
		add_filter( 'woocommerce_email_subject_customer_new_account', array( __CLASS__, 'filter_email_subject' ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_new_account', array( __CLASS__, 'filter_email_heading' ), 10, 2 );
	}

	/**
	 * Filter to ensure real customers have the welcome email enabled, while excluding dummy mobile accounts.
	 *
	 * @param bool     $enabled Current enabled status.
	 * @param WP_User  $user    User object.
	 * @param WC_Email $email   Email object.
	 * @return bool
	 */
	public static function filter_email_enabled( $enabled, $user = null, $email = null ) {
		if ( $user instanceof WP_User && ! empty( $user->user_email ) ) {
			if ( self::is_dummy_email( $user->user_email ) ) {
				return false;
			}
			return true;
		}
		return $enabled;
	}

	/**
	 * Localized email subject for Customer New Account email.
	 *
	 * @param string   $subject Current subject.
	 * @param WC_Email $email   Email object.
	 * @return string
	 */
	public static function filter_email_subject( $subject, $email = null ) {
		$is_ar = ( function_exists( 'spada_is_rtl' ) && spada_is_rtl() ) || ( get_locale() === 'ar' || strpos( get_locale(), 'ar' ) === 0 );
		$site_title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( empty( $subject ) || false !== strpos( $subject, 'Your account on' ) || false !== strpos( $subject, 'حسابك على' ) ) {
			return $is_ar
				? sprintf( 'حسابك على %s - تم التسجيل بنجاح', $site_title )
				: sprintf( 'Your account on %s - Welcome!', $site_title );
		}

		return $subject;
	}

	/**
	 * Localized email heading for Customer New Account email.
	 *
	 * @param string   $heading Current heading.
	 * @param WC_Email $email   Email object.
	 * @return string
	 */
	public static function filter_email_heading( $heading, $email = null ) {
		$is_ar = ( function_exists( 'spada_is_rtl' ) && spada_is_rtl() ) || ( get_locale() === 'ar' || strpos( get_locale(), 'ar' ) === 0 );
		$site_title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( empty( $heading ) || false !== strpos( $heading, 'Welcome to' ) || false !== strpos( $heading, 'مرحباً بك في' ) ) {
			return $is_ar
				? sprintf( 'مرحباً بك في %s', $site_title )
				: sprintf( 'Welcome to %s', $site_title );
		}

		return $heading;
	}

	/**
	 * Record sent status when WooCommerce native notification fires.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public static function on_native_notification( $customer_id ) {
		if ( $customer_id ) {
			update_user_meta( $customer_id, self::META_KEY_SENT, time() );
		}
	}

	/**
	 * Triggered on woocommerce_created_customer.
	 *
	 * @param int   $customer_id       Customer ID.
	 * @param array $new_customer_data Customer data array.
	 * @param bool  $password_generated Whether password was generated.
	 */
	public static function on_woocommerce_created_customer( $customer_id, $new_customer_data = array(), $password_generated = false ) {
		$password = '';
		if ( is_array( $new_customer_data ) && ! empty( $new_customer_data['user_pass'] ) ) {
			$password = $new_customer_data['user_pass'];
		}

		self::send_welcome_email( $customer_id, $password, (bool) $password_generated );
	}

	/**
	 * Triggered on WordPress user_register as a fallback.
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_user_register( $user_id ) {
		self::send_welcome_email( $user_id, '', true );
	}

	/**
	 * Dispatches the WooCommerce Customer New Account welcome email.
	 *
	 * @param int    $customer_id       User ID.
	 * @param string $password          Clear-text password if available.
	 * @param bool   $password_generated Whether password was auto-generated.
	 * @return bool True if triggered, false otherwise.
	 */
	public static function send_welcome_email( $customer_id, $password = '', $password_generated = true ) {
		$customer_id = absint( $customer_id );
		if ( ! $customer_id ) {
			return false;
		}

		// Prevent duplicate welcome emails
		if ( get_user_meta( $customer_id, self::META_KEY_SENT, true ) ) {
			return false;
		}

		$user = get_user_by( 'id', $customer_id );
		if ( ! $user || empty( $user->user_email ) || ! is_email( $user->user_email ) ) {
			return false;
		}

		// Skip dummy/local phone placeholder emails
		if ( self::is_dummy_email( $user->user_email ) ) {
			return false;
		}

		// Avoid sending to administrators/shop managers created via wp-admin
		if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
			if ( in_array( 'administrator', $user->roles, true ) || in_array( 'shop_manager', $user->roles, true ) ) {
				return false;
			}
		}

		// Ensure WooCommerce is active
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return false;
		}

		try {
			// Mark as sent before triggering to avoid recursion
			update_user_meta( $customer_id, self::META_KEY_SENT, time() );

			// Access WooCommerce Mailer
			$mailer = WC()->mailer();
			if ( ! $mailer ) {
				return false;
			}

			$email_notifications = $mailer->get_emails();
			if ( empty( $email_notifications['WC_Email_Customer_New_Account'] ) ) {
				return false;
			}

			/** @var WC_Email_Customer_New_Account $new_account_email */
			$new_account_email = $email_notifications['WC_Email_Customer_New_Account'];

			// Ensure email is enabled for this dispatch
			$original_enabled = $new_account_email->enabled;
			$new_account_email->enabled = 'yes';

			// Trigger the email
			$new_account_email->trigger( $customer_id, $password, (bool) $password_generated );

			$new_account_email->enabled = $original_enabled;

			return true;
		} catch ( Throwable $e ) {
			error_log( 'Spada Welcome Email Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if email is a temporary/dummy local email generated for phone signups.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_dummy_email( $email ) {
		if ( empty( $email ) ) {
			return true;
		}
		$email_lower = strtolower( trim( $email ) );
		return (
			strpos( $email_lower, '@spada.local' ) !== false ||
			substr( $email_lower, -6 ) === '.local'
		);
	}
}
