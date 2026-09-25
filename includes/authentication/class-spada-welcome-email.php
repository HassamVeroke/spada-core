<?php
/**
 * SPADA Welcome Email Manager
 *
 * Ensures newly registered customers receive exactly ONE official WooCommerce Welcome Email
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
	 * In-memory registry of user IDs that have had their welcome email dispatched in this request.
	 *
	 * @var array
	 */
	private static $dispatched_users = array();

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Hook into standard WooCommerce customer creation
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'on_woocommerce_created_customer' ), 20, 3 );

		// Listen to native notification hook to record sent state
		add_action( 'woocommerce_created_customer_notification', array( __CLASS__, 'on_native_notification' ), 5, 1 );

		// Listen to email sent hook to track dispatch
		add_action( 'woocommerce_email_sent', array( __CLASS__, 'on_email_sent' ), 10, 2 );

		// Ensure customer_new_account email is enabled for real customers, while strictly preventing duplicates
		add_filter( 'woocommerce_email_enabled_customer_new_account', array( __CLASS__, 'filter_email_enabled' ), 99, 3 );

		// Provide localized subject and heading if defaults are used
		add_filter( 'woocommerce_email_subject_customer_new_account', array( __CLASS__, 'filter_email_subject' ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_new_account', array( __CLASS__, 'filter_email_heading' ), 10, 2 );
	}

	/**
	 * Filter to ensure real customers have the welcome email enabled, while strictly blocking
	 * dummy mobile accounts and preventing any duplicate email dispatches.
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

			// If welcome email has already been sent to this user, block any secondary/duplicate email
			if ( isset( self::$dispatched_users[ $user->ID ] ) || get_user_meta( $user->ID, self::META_KEY_SENT, true ) ) {
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
		$customer_id = absint( $customer_id );
		if ( $customer_id ) {
			self::$dispatched_users[ $customer_id ] = true;
			update_user_meta( $customer_id, self::META_KEY_SENT, time() );
		}
	}

	/**
	 * Record sent status when email is dispatched via WooCommerce mailer.
	 *
	 * @param bool     $return Mail return.
	 * @param WC_Email $email  Email object.
	 */
	public static function on_email_sent( $return, $email ) {
		if ( is_a( $email, 'WC_Email_Customer_New_Account' ) && ! empty( $email->object->ID ) ) {
			$user_id = absint( $email->object->ID );
			self::$dispatched_users[ $user_id ] = true;
			update_user_meta( $user_id, self::META_KEY_SENT, time() );
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
		$customer_id = absint( $customer_id );
		if ( ! $customer_id ) {
			return;
		}

		if ( isset( self::$dispatched_users[ $customer_id ] ) || get_user_meta( $customer_id, self::META_KEY_SENT, true ) ) {
			return;
		}

		$password = '';
		if ( is_array( $new_customer_data ) && ! empty( $new_customer_data['user_pass'] ) ) {
			$password = $new_customer_data['user_pass'];
		}

		self::send_welcome_email( $customer_id, $password, false );
	}

	/**
	 * Dispatches the WooCommerce Customer New Account welcome email exactly once.
	 *
	 * @param int    $customer_id       User ID.
	 * @param string $password          Clear-text password if available.
	 * @param bool   $password_generated Whether password was auto-generated.
	 * @return bool True if triggered, false otherwise.
	 */
	public static function send_welcome_email( $customer_id, $password = '', $password_generated = false ) {
		$customer_id = absint( $customer_id );
		if ( ! $customer_id ) {
			return false;
		}

		// Prevent duplicate welcome emails in-memory and in DB
		if ( isset( self::$dispatched_users[ $customer_id ] ) || get_user_meta( $customer_id, self::META_KEY_SENT, true ) ) {
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
			// Register in memory and mark in DB immediately before dispatching to block any race condition
			self::$dispatched_users[ $customer_id ] = true;
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

			// Temporarily ensure email is enabled for this dispatch
			$original_enabled = $new_account_email->enabled;
			$new_account_email->enabled = 'yes';

			// Trigger the email exactly once
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
