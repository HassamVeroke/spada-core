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
	 * Tracks user IDs currently being sent in-flight to prevent filter recursion/self-blocking.
	 *
	 * @var array
	 */
	private static $sending_in_progress = array();

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

		// Listen to native notification hook to record sent state (priority 20 to run after WC default trigger)
		add_action( 'woocommerce_created_customer_notification', array( __CLASS__, 'on_native_notification' ), 20, 1 );

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

			$user_id = (int) $user->ID;

			// If this user is actively in the middle of being dispatched by send_welcome_email(), ALLOW IT
			if ( ! empty( self::$sending_in_progress[ $user_id ] ) ) {
				return true;
			}

			// If welcome email has already been sent to this user, block any secondary/duplicate email
			if ( ! empty( self::$dispatched_users[ $user_id ] ) || get_user_meta( $user_id, self::META_KEY_SENT, true ) ) {
				return false;
			}

			return true;
		}

		return $enabled;
	}

	/**
	 * Detect if current request or customer context is Arabic.
	 *
	 * @return bool
	 */
	public static function is_arabic() {
		// 1. TranslatePress active language
		if ( function_exists( 'trp_get_current_language' ) ) {
			$trp_lang = trp_get_current_language();
			if ( ! empty( $trp_lang ) && strpos( $trp_lang, 'ar' ) === 0 ) {
				return true;
			}
		}

		// 2. TranslatePress cookie
		if ( ! empty( $_COOKIE['trp_language'] ) && strpos( sanitize_text_field( wp_unslash( $_COOKIE['trp_language'] ) ), 'ar' ) === 0 ) {
			return true;
		}

		// 3. TranslatePress form language param
		if ( ! empty( $_REQUEST['trp-form-language'] ) && strpos( sanitize_text_field( wp_unslash( $_REQUEST['trp-form-language'] ) ), 'ar' ) === 0 ) {
			return true;
		}

		// 4. HTTP Referer (critical for AJAX registration from /ar/ pages)
		$referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( ! empty( $referer ) && ( strpos( $referer, '/ar/' ) !== false || substr( $referer, -3 ) === '/ar' ) ) {
			return true;
		}

		// 5. WordPress / WooCommerce locale
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( ! empty( $locale ) && strpos( $locale, 'ar' ) === 0 ) {
			return true;
		}

		// 6. Right-to-left
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolves strings that may contain TranslatePress [language-include] shortcodes or need localization.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	public static function resolve_language_string( $text ) {
		if ( empty( $text ) || ! is_string( $text ) ) {
			return $text;
		}

		$is_ar = self::is_arabic();

		// Check for [language-include] shortcode
		if ( false !== strpos( $text, '[language-include' ) ) {
			if ( $is_ar ) {
				// Match [language-include lang="ar..."]...[/language-include]
				if ( preg_match( '#\[language-include\s+[^\]]*lang=["\']?ar[^"\'\]]*["\']?[^\]]*\](.*?)\[/language-include\]#is', $text, $matches ) ) {
					return trim( $matches[1] );
				}
			} else {
				// Match [language-include lang="en..."]...[/language-include]
				if ( preg_match( '#\[language-include\s+[^\]]*lang=["\']?en[^"\'\]]*["\']?[^\]]*\](.*?)\[/language-include\]#is', $text, $matches ) ) {
					return trim( $matches[1] );
				}
			}

			// Clean remaining [language-include] tags if any
			$cleaned = preg_replace( '#\[language-include\b[^\]]*\].*?\[/language-include\]#is', '', $text );
			return trim( do_shortcode( $cleaned ) );
		}

		return do_shortcode( $text );
	}

	/**
	 * Localized email subject for Customer New Account email.
	 *
	 * @param string   $subject Current subject.
	 * @param WC_Email $email   Email object.
	 * @return string
	 */
	public static function filter_email_subject( $subject, $email = null ) {
		$is_ar = self::is_arabic();

		// If user configured [language-include] shortcode, resolve it directly
		if ( false !== strpos( $subject, '[language-include' ) ) {
			return self::resolve_language_string( $subject );
		}

		// Provide localized default if subject is empty or default WC placeholder
		if ( empty( $subject ) || 'Your account on {site_title}' === trim( $subject ) ) {
			return $is_ar
				? 'حسابك على سبادا - تم التسجيل بنجاح'
				: 'Your account on Spada - Welcome!';
		}

		return do_shortcode( $subject );
	}

	/**
	 * Localized email heading for Customer New Account email.
	 *
	 * @param string   $heading Current heading.
	 * @param WC_Email $email   Email object.
	 * @return string
	 */
	public static function filter_email_heading( $heading, $email = null ) {
		$is_ar = self::is_arabic();

		// If user configured [language-include] shortcode, resolve it directly
		if ( false !== strpos( $heading, '[language-include' ) ) {
			return self::resolve_language_string( $heading );
		}

		// If empty or default placeholder, provide clean default
		if ( empty( $heading ) || '{site_title}' === trim( $heading ) || 'Welcome to {site_title}' === trim( $heading ) ) {
			return $is_ar ? 'حياك الله في سبادا' : 'Welcome to Spada';
		}

		return do_shortcode( $heading );
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
		if ( ! empty( self::$dispatched_users[ $customer_id ] ) || get_user_meta( $customer_id, self::META_KEY_SENT, true ) ) {
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
			// Mark as sending in progress so filter_email_enabled allows THIS dispatch
			self::$sending_in_progress[ $customer_id ] = true;

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

			// Permanently record sent state in memory and database
			self::$dispatched_users[ $customer_id ] = true;
			update_user_meta( $customer_id, self::META_KEY_SENT, time() );

			return true;
		} catch ( Throwable $e ) {
			error_log( 'Spada Welcome Email Error: ' . $e->getMessage() );
			return false;
		} finally {
			unset( self::$sending_in_progress[ $customer_id ] );
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
