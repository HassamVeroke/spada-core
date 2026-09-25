<?php

/**
 * SPADA Authoritative Server-Side Email OTP Service
 *
 * Implements secure 6-digit OTP generation, dispatch via wp_mail,
 * expiration, rate limiting, and verification for customer login & registration.
 *
 * @package Spada
 */

if (! defined('ABSPATH')) {
	exit;
}

class Spada_OTP_Email
{

	/**
	 * Transient prefix.
	 */
	const TRANSIENT_PREFIX = 'spada_email_otp_';

	/**
	 * Rate limit prefix.
	 */
	const RATE_LIMIT_PREFIX = 'spada_email_otp_rl_';

	/**
	 * Expiration time in seconds (2 minutes).
	 */
	const EXPIRATION_SECONDS = 120;

	/**
	 * Max verify attempts before invalidation.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Detect if current request or context is Arabic.
	 *
	 * @return bool
	 */
	public static function is_arabic()
	{
		// 1. Explicit request parameter (from frontend auth AJAX)
		if ( ! empty( $_REQUEST['lang'] ) ) {
			$lang = strtolower( trim( sanitize_text_field( wp_unslash( $_REQUEST['lang'] ) ) ) );
			if ( strpos( $lang, 'ar' ) === 0 ) {
				return true;
			}
			if ( strpos( $lang, 'en' ) === 0 ) {
				return false;
			}
		}

		// 2. HTTP Referer (critical: distinguishes /ar/ from English pages like /account/)
		$referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( ! empty( $referer ) ) {
			if ( strpos( $referer, '/ar/' ) !== false || substr( $referer, -3 ) === '/ar' ) {
				return true;
			}
			// If referer is explicitly an English URL (e.g. /account/ without /ar/), it is English
			$parsed_path = wp_parse_url( $referer, PHP_URL_PATH );
			if ( ! empty( $parsed_path ) && strpos( $parsed_path, '/ar' ) === false ) {
				return false;
			}
		}

		// 3. TranslatePress form language parameter
		if ( ! empty( $_REQUEST['trp-form-language'] ) ) {
			$trp_form_lang = strtolower( trim( sanitize_text_field( wp_unslash( $_REQUEST['trp-form-language'] ) ) ) );
			if ( strpos( $trp_form_lang, 'ar' ) === 0 ) {
				return true;
			}
			if ( strpos( $trp_form_lang, 'en' ) === 0 ) {
				return false;
			}
		}

		// 4. TranslatePress cookie
		if ( ! empty( $_COOKIE['trp_language'] ) ) {
			$cookie_lang = strtolower( trim( sanitize_text_field( wp_unslash( $_COOKIE['trp_language'] ) ) ) );
			if ( strpos( $cookie_lang, 'ar' ) === 0 ) {
				return true;
			}
			if ( strpos( $cookie_lang, 'en' ) === 0 ) {
				return false;
			}
		}

		// 5. TranslatePress current language function
		if ( function_exists( 'trp_get_current_language' ) ) {
			$trp_lang = strtolower( trim( (string) trp_get_current_language() ) );
			if ( ! empty( $trp_lang ) ) {
				if ( strpos( $trp_lang, 'ar' ) === 0 ) {
					return true;
				}
				if ( strpos( $trp_lang, 'en' ) === 0 ) {
					return false;
				}
			}
		}

		// 6. WordPress locale
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( ! empty( $locale ) ) {
			if ( strpos( $locale, 'ar' ) === 0 ) {
				return true;
			}
			if ( strpos( $locale, 'en' ) === 0 ) {
				return false;
			}
		}

		// 7. Right-to-left
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			return true;
		}

		return false;
	}

	/**
	 * Send an Email OTP to the specified email address.
	 *
	 * @param string $email User email.
	 * @param string $auth_action Optional. 'signup' or 'login'. Auto-detected if omitted.
	 * @return array Result with status and message.
	 */
	public static function send_otp($email, $auth_action = '')
	{
		$email = sanitize_email($email);

		if (! is_email($email)) {
			return array(
				'success' => false,
				'message' => __('Please enter a valid email address.', 'spada-core'),
			);
		}

		// Determine auth action if omitted
		if (empty($auth_action)) {
			$auth_action = email_exists($email) ? 'login' : 'signup';
		} else {
			$auth_action = ('signup' === sanitize_key($auth_action)) ? 'signup' : 'login';
		}

		// Rate limiting: allow max 4 requests per 10 minutes per email
		$rl_key   = self::RATE_LIMIT_PREFIX . md5($email);
		$requests = (int) get_transient($rl_key);

		if ($requests >= 4) {
			return array(
				'success' => false,
				'message' => __('Too many OTP requests. Please wait a few minutes before trying again.', 'spada-core'),
			);
		}

		set_transient($rl_key, $requests + 1, 10 * MINUTE_IN_SECONDS);

		// Ensure user cannot resend a new OTP before the earlier one expires (120 seconds)
		$transient_key    = self::TRANSIENT_PREFIX . md5($email);
		$existing_payload = get_transient($transient_key);
		$now              = time();

		$is_arabic = self::is_arabic();

		if (is_array($existing_payload) && ! empty($existing_payload['expires_at']) && $now < (int) $existing_payload['expires_at']) {
			$remaining = (int) $existing_payload['expires_at'] - $now;
			return array(
				'success'   => false,
				'message'   => $is_arabic
					? sprintf('يرجى الانتظار %d ثانية قبل طلب رمز جديد.', $remaining)
					: sprintf(__('Please wait %d seconds before requesting a new code.', 'spada-core'), $remaining),
				'remaining' => $remaining,
			);
		}

		// Generate cryptographically secure 6-digit integer
		try {
			$otp = (string) random_int(100000, 999999);
		} catch (Exception $e) {
			$otp = (string) wp_rand(100000, 999999);
		}

		// Store hashed OTP in transient
		$otp_payload = array(
			'hash'        => wp_hash($otp, 'nonce'),
			'attempts'    => 0,
			'created_at'  => $now,
			'expires_at'  => $now + self::EXPIRATION_SECONDS,
			'email'       => $email,
			'auth_action' => $auth_action,
		);

		set_transient($transient_key, $otp_payload, self::EXPIRATION_SECONDS);

		// Subjects matching exact specifications
		if ('signup' === $auth_action) {
			$subject = $is_arabic ? 'تحقق من حسابك' : 'Verify Your Account';
		} else {
			$subject = $is_arabic ? 'تحقق من حسابك' : 'Verify Your Account';
		}

		// Render WooCommerce email HTML using existing Header & Footer
		$message = self::render_verification_email($otp, $auth_action, $is_arabic);

		$site_name  = wp_specialchars_decode(get_option('woocommerce_email_from_name', get_bloginfo('name')), ENT_QUOTES);
		$from_email = sanitize_email(get_option('woocommerce_email_from_address', get_option('admin_email')));

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf('From: %s <%s>', $site_name, $from_email),
		);

		$sent = wp_mail($email, $subject, $message, $headers);

		if (! $sent) {
			return array(
				'success' => false,
				'message' => __('Failed to dispatch verification email. Please try again or choose another method.', 'spada-core'),
			);
		}

		return array(
			'success' => true,
			'message' => $is_arabic
				? 'تم إرسال رمز التحقق بنجاح إلى بريدك الإلكتروني.'
				: __('Verification code sent successfully to your email address.', 'spada-core'),
			'email'   => $email,
		);
	}

	/**
	 * Verify an Email OTP and log in / register customer.
	 *
	 * @param string $email User email.
	 * @param string $entered_otp User entered 6-digit code.
	 * @return array Verification result with user data or error.
	 */
	public static function verify_otp($email, $entered_otp, $auth_action = 'login')
	{
		$email       = sanitize_email($email);
		$entered_otp = preg_replace('/\D/', '', (string) $entered_otp);

		if (! is_email($email) || strlen($entered_otp) !== 6) {
			return array(
				'success' => false,
				'message' => __('Invalid email or code format. Please enter all 6 digits.', 'spada-core'),
			);
		}

		$transient_key      = self::TRANSIENT_PREFIX . md5($email);
		$otp_payload        = get_transient($transient_key);
		$now                = time();
		$is_arabic          = self::is_arabic();
		$incorrect_code_msg = $is_arabic ? 'رمز التحقق غير صحيح.' : __('Incorrect verification code.', 'spada-core');

		if (false === $otp_payload || ! is_array($otp_payload) || empty($otp_payload['hash'])) {
			return array(
				'success' => false,
				'message' => $incorrect_code_msg,
			);
		}

		// Explicit authoritative expiration check: STRICT 120 SECONDS
		if (empty($otp_payload['expires_at']) || $now > (int) $otp_payload['expires_at']) {
			delete_transient($transient_key);
			return array(
				'success' => false,
				'message' => $incorrect_code_msg,
			);
		}

		// Check attempt throttling
		if (isset($otp_payload['attempts']) && (int) $otp_payload['attempts'] >= self::MAX_ATTEMPTS) {
			delete_transient($transient_key);
			return array(
				'success' => false,
				'message' => $incorrect_code_msg,
			);
		}

		// Verify hash
		$expected_hash = $otp_payload['hash'];
		$provided_hash = wp_hash($entered_otp, 'nonce');

		if (! hash_equals($expected_hash, $provided_hash)) {
			// Increment attempts but preserve remaining lifetime (NEVER reset to full 120s)
			$otp_payload['attempts'] = isset($otp_payload['attempts']) ? (int) $otp_payload['attempts'] + 1 : 1;
			$remaining_ttl           = max(1, (int) $otp_payload['expires_at'] - $now);
			set_transient($transient_key, $otp_payload, $remaining_ttl);

			$remaining = self::MAX_ATTEMPTS - $otp_payload['attempts'];
			return array(
				'success'   => false,
				'message'   => $incorrect_code_msg,
				'remaining' => $remaining,
			);
		}

		// Burn the transient immediately upon success
		delete_transient($transient_key);

		// Customer lookup or creation
		$user = get_user_by('email', $email);

		if (! $user) {
			if ('login' === $auth_action) {
				$is_arabic = self::is_arabic();
				return array(
					'success' => false,
					'message' => $is_arabic
						? 'لم يتم العثور على حساب بهذا البريد الإلكتروني. يرجى إنشاء حساب أولاً.'
						: __('No account found with this email address. Please sign up first.', 'spada-core'),
				);
			}

			// Auto-register WooCommerce customer (only for signup)
			$username = sanitize_user(current(explode('@', $email)), true);
			// Ensure unique username
			if (username_exists($username)) {
				$username = $username . '_' . wp_rand(100, 999);
			}

			$password = wp_generate_password(24, true, true);

			if (function_exists('wc_create_new_customer')) {
				$customer_id = wc_create_new_customer($email, $username, $password);
			} else {
				$customer_id = wp_create_user($username, $password, $email);
			}

			if (is_wp_error($customer_id)) {
				return array(
					'success' => false,
					'message' => $customer_id->get_error_message(),
				);
			}

			$user = get_user_by('id', $customer_id);

			// Dispatch WooCommerce customer welcome email
			if (class_exists('Spada_Welcome_Email')) {
				Spada_Welcome_Email::send_welcome_email($customer_id, $password, true);
			}
		}

		// Authoritative login
		wp_clear_auth_cookie();
		wp_set_current_user($user->ID);
		wp_set_auth_cookie($user->ID, true);
		do_action('wp_login', $user->user_login, $user);

		// Set WooCommerce customer session cookie if available
		if (function_exists('WC') && WC()->session) {
			WC()->session->set_customer_session_cookie(true);
		}

		$is_arabic       = self::is_arabic();
		$success_message = ('signup' === $auth_action)
			? ($is_arabic ? 'تم التسجيل بنجاح!' : __('SignUp Successful!', 'spada-core'))
			: ($is_arabic ? 'تم تسجيل الدخول بنجاح!' : __('Login successful!', 'spada-core'));

		return array(
			'success'  => true,
			'message'  => $success_message,
			'user_id'  => $user->ID,
			'redirect' => ('signup' === $auth_action) ? wc_get_page_permalink('myaccount') : home_url('/'),
		);
	}

	/**
	 * Render WooCommerce-compatible verification email HTML.
	 * Reuses the existing SPADA WooCommerce email header and footer.
	 *
	 * @param string $otp 6-digit code.
	 * @param string $auth_action 'signup' or 'login'.
	 * @param bool   $is_arabic Whether language is Arabic.
	 * @return string Rendered HTML.
	 */
	public static function render_verification_email($otp, $auth_action, $is_arabic)
	{
		$target_locale = $is_arabic ? 'ar' : 'en_US';
		$switched      = function_exists('switch_to_locale') ? switch_to_locale($target_locale) : false;

		$locale_filter = function () use ($target_locale) {
			return $target_locale;
		};
		add_filter('locale', $locale_filter, 999);
		add_filter('determine_locale', $locale_filter, 999);

		if ($is_arabic) {
			add_filter('is_rtl', '__return_true', 999);
		} else {
			add_filter('is_rtl', '__return_false', 999);
		}

		// Ensure WooCommerce mailer is initialized
		$mailer = null;
		if (function_exists('WC') && WC()->mailer()) {
			$mailer = WC()->mailer();
		}

		$template_name = ('signup' === $auth_action)
			? 'customer-signup-verification.php'
			: 'customer-login-verification.php';

		$template_args = array(
			'verification_code' => $otp,
			'auth_action'       => $auth_action,
			'is_rtl'            => (bool) $is_arabic,
			'email'             => null,
		);

		// Exclusively load template from Spada Core plugin
		$plugin_template = SPADA_CORE_PATH . 'templates/emails/' . $template_name;
		$content         = '';

		if ( file_exists( $plugin_template ) ) {
			ob_start();
			extract( $template_args );
			include $plugin_template;
			$content = ob_get_clean();
		}

		// Inline WooCommerce email CSS styles
		if (! empty($mailer) && method_exists($mailer, 'style_inline')) {
			$content = $mailer->style_inline($content);
		}

		// Cleanup filters & restore locale
		remove_filter('locale', $locale_filter, 999);
		remove_filter('determine_locale', $locale_filter, 999);
		if ($is_arabic) {
			remove_filter('is_rtl', '__return_true', 999);
		} else {
			remove_filter('is_rtl', '__return_false', 999);
		}
		if ($switched && function_exists('restore_previous_locale')) {
			restore_previous_locale();
		}

		return $content;
	}
}
