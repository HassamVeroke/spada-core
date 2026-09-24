<?php

/**
 * SPADA Phone Authentication Handler
 *
 * Provides native Phone SMS and WhatsApp OTP operations
 * while keeping all security verification authoritative on the server.
 *
 * @package Spada
 */

if (! defined('ABSPATH')) {
	exit;
}

class Spada_Mobile_Login
{

	/**
	 * Init hooks.
	 */
	public static function init()
	{
		add_action('wp_ajax_nopriv_spada_request_phone_otp', array(__CLASS__, 'ajax_request_phone_otp'));
		add_action('wp_ajax_spada_request_phone_otp', array(__CLASS__, 'ajax_request_phone_otp'));

		add_action('wp_ajax_nopriv_spada_verify_phone_otp', array(__CLASS__, 'ajax_verify_phone_otp'));
		add_action('wp_ajax_spada_verify_phone_otp', array(__CLASS__, 'ajax_verify_phone_otp'));

		add_action('wp_ajax_nopriv_spada_resend_phone_otp', array(__CLASS__, 'ajax_resend_phone_otp'));
		add_action('wp_ajax_spada_resend_phone_otp', array(__CLASS__, 'ajax_resend_phone_otp'));

		add_action('template_redirect', array(__CLASS__, 'handle_account_details_sync'), 5);
	}

	/**
	 * Sync account details (first_name, display_name, billing_phone, shipping_phone)
	 * when save_account_details is submitted.
	 */
	public static function handle_account_details_sync()
	{
		if (! is_user_logged_in() || ! isset($_POST['save_account_details'])) {
			return;
		}

		$user_id = get_current_user_id();
		if (! $user_id) {
			return;
		}

		if (! empty($_POST['account_first_name'])) {
			$name = sanitize_text_field(wp_unslash($_POST['account_first_name']));
			wp_update_user(
				array(
					'ID'           => $user_id,
					'first_name'   => $name,
					'display_name' => $name,
				)
			);
		}

		if (! empty($_POST['billing_phone'])) {
			$phone = sanitize_text_field(wp_unslash($_POST['billing_phone']));
			update_user_meta($user_id, 'billing_phone', $phone);
			update_user_meta($user_id, 'shipping_phone', $phone);
		}
	}

	/**
	 * Normalize phone number (strip whitespace, ensure KSA +966 prefix without leading 0).
	 *
	 * @param string $phone Raw phone string.
	 * @return array Normalized phone code and number.
	 */
	public static function normalize_phone($phone)
	{
		$clean = preg_replace('/[^0-9+]/', '', (string) $phone);

		$code = '+966';
		if (strpos($clean, '+966') === 0) {
			$clean = substr($clean, 4);
		} elseif (strpos($clean, '00966') === 0) {
			$clean = substr($clean, 5);
		} elseif (strpos($clean, '966') === 0) {
			$clean = substr($clean, 3);
		}

		// Remove leading zero if present
		if (strpos($clean, '0') === 0) {
			$clean = substr($clean, 1);
		}

		return array(
			'code'   => $code,
			'number' => $clean,
		);
	}

	/**
	 * Mask phone number for display (+966 *******911).
	 *
	 * @param string $code Country code.
	 * @param string $number Phone number.
	 * @return string Masked string.
	 */
	public static function mask_phone($code, $number)
	{
		$len = strlen($number);
		if ($len <= 3) {
			return $code . ' ' . $number;
		}
		$last3 = substr($number, -3);
		return $code . ' ' . str_repeat('*', max(4, $len - 3)) . $last3;
	}

	/**
	 * Find a user by phone number across all standard WooCommerce meta keys and formats.
	 *
	 * @param string $phone_no Phone number (e.g. 5XXXXXXXX).
	 * @param string $phone_code Country code (e.g. +966).
	 * @return WP_User|null
	 */
	public static function get_user_by_phone($phone_no, $phone_code = '+966')
	{
		// Multi-format candidates for billing_phone & shipping_phone
		$clean_code = ltrim($phone_code, '+');
		$candidates = array_unique(
			array_filter(
				array(
					$phone_no,
					'0' . $phone_no,
					$phone_code . $phone_no,
					'+' . $clean_code . $phone_no,
					$clean_code . $phone_no,
				)
			)
		);

		$meta_queries = array('relation' => 'OR');
		foreach ($candidates as $cand) {
			$meta_queries[] = array(
				'key'     => 'billing_phone',
				'value'   => $cand,
				'compare' => '=',
			);
			$meta_queries[] = array(
				'key'     => 'shipping_phone',
				'value'   => $cand,
				'compare' => '=',
			);
		}

		$user_query = new WP_User_Query(
			array(
				'meta_query' => $meta_queries,
				'number'     => 1,
			)
		);

		$results = $user_query->get_results();
		if (! empty($results) && $results[0] instanceof WP_User) {
			return $results[0];
		}

		return null;
	}

	/**
	 * Request Phone OTP (SMS or WhatsApp).
	 */
	public static function ajax_request_phone_otp()
	{
		check_ajax_referer('spada_auth_nonce', 'nonce');

		$phone_raw   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
		$channel     = isset($_POST['channel']) && 'whatsapp' === $_POST['channel'] ? 'whatsapp' : 'sms';
		$auth_action = isset($_POST['auth_action']) && 'signup' === sanitize_key($_POST['auth_action']) ? 'signup' : 'login';

		if (empty($phone_raw)) {
			wp_send_json_error(
				array('message' => __('Please enter a valid mobile number.', 'spada-core')),
				400
			);
		}

		$normalized = self::normalize_phone($phone_raw);
		$phone_code = $normalized['code'];
		$phone_no   = $normalized['number'];

		$clean_input     = preg_replace('/[^0-9]/', '', (string) $phone_raw);
		$is_valid_format = preg_match('/^05[0-9]{8}$/', $clean_input) || preg_match('/^5[0-9]{8}$/', $phone_no);

		if (! $is_valid_format) {
			$is_arabic = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
			$msg       = (strlen($clean_input) > 0 && strpos($clean_input, '05') !== 0 && strpos($clean_input, '5') !== 0)
				? ($is_arabic ? 'يجب أن يبدأ رقم الجوال بـ 05.' : __('Phone number must start with 05.', 'spada-core'))
				: ($is_arabic ? 'يجب أن يتكون رقم الجوال من 10 أرقام.' : __('Phone number must be exactly 10 digits.', 'spada-core'));

			wp_send_json_error(
				array('message' => $msg),
				400
			);
		}

		$is_arabic     = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
		$existing_user = self::get_user_by_phone($phone_no, $phone_code);

		if ('login' === $auth_action && ! $existing_user) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'لم يتم العثور على حساب برقم الجوال هذا. يرجى إنشاء حساب أولاً.'
						: __('No account found with this mobile number. Please sign up first.', 'spada-core'),
				),
				400
			);
		}

		if ('signup' === $auth_action && $existing_user) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'يوجد حساب بالفعل برقم الجوال هذا. يرجى تسجيل الدخول بدلاً من ذلك.'
						: __('An account with this mobile number already exists. Please sign in instead.', 'spada-core'),
				),
				400
			);
		}

		// Generate native secure 6-digit OTP stored in transient
		$transient_key = 'spada_phone_otp_' . md5($phone_code . $phone_no);
		try {
			$otp = (string) random_int(100000, 999999);
		} catch (Exception $e) {
			$otp = (string) wp_rand(100000, 999999);
		}

		$now = time();
		set_transient(
			$transient_key,
			array(
				'hash'       => wp_hash($otp, 'nonce'),
				'attempts'   => 0,
				'code'       => $phone_code,
				'number'     => $phone_no,
				'created_at' => $now,
				'expires_at' => $now + 120, // 2 minutes strict
			),
			120
		);

		// Allow custom SMS / WhatsApp dispatchers to hook and deliver the OTP
		do_action('spada_phone_otp_sent', $phone_code, $phone_no, $otp, $channel);

		$masked = self::mask_phone($phone_code, $phone_no);

		wp_send_json_success(
			array(
				'message' => 'whatsapp' === $channel
					? __('Verification code sent to your WhatsApp account.', 'spada-core')
					: __('Verification code sent to your mobile number via SMS.', 'spada-core'),
				'masked'  => $masked,
				'phone'   => $phone_code . $phone_no,
			)
		);
	}

	/**
	 * Verify Phone OTP.
	 */
	public static function ajax_verify_phone_otp()
	{
		check_ajax_referer('spada_auth_nonce', 'nonce');

		$phone_raw   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
		$otp_code    = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
		$auth_action = isset($_POST['auth_action']) && 'signup' === sanitize_key($_POST['auth_action']) ? 'signup' : 'login';

		$normalized = self::normalize_phone($phone_raw);
		$phone_code = $normalized['code'];
		$phone_no   = $normalized['number'];

		$is_arabic          = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
		$incorrect_code_msg = $is_arabic ? 'رمز التحقق غير صحيح.' : __('Incorrect verification code.', 'spada-core');

		if (empty($phone_no) || strlen($otp_code) !== 6) {
			wp_send_json_error(
				array('message' => __('Please enter the full 6-digit verification code.', 'spada-core')),
				400
			);
		}

		// Verify against secure server-side transient
		$transient_key = 'spada_phone_otp_' . md5($phone_code . $phone_no);
		$payload       = get_transient($transient_key);
		$now           = time();

		if (! is_array($payload) || empty($payload['hash'])) {
			wp_send_json_error(array('message' => $incorrect_code_msg), 400);
		}

		// Explicit authoritative expiration check: STRICT 120 SECONDS
		if (empty($payload['expires_at']) || $now > (int) $payload['expires_at']) {
			delete_transient($transient_key);
			wp_send_json_error(array('message' => $incorrect_code_msg), 400);
		}

		// Check max attempts (5)
		if (isset($payload['attempts']) && (int) $payload['attempts'] >= 5) {
			delete_transient($transient_key);
			wp_send_json_error(array('message' => $incorrect_code_msg), 400);
		}

		if (! hash_equals($payload['hash'], wp_hash($otp_code, 'nonce'))) {
			$payload['attempts'] = isset($payload['attempts']) ? (int) $payload['attempts'] + 1 : 1;
			$remaining_ttl       = max(1, (int) $payload['expires_at'] - $now);
			set_transient($transient_key, $payload, $remaining_ttl);
			wp_send_json_error(array('message' => $incorrect_code_msg), 400);
		}

		// Valid & within 120-second expiration window: immediately burn transient to prevent reuse
		delete_transient($transient_key);

		// Authoritative Customer Login / Registration by phone
		$user = self::get_user_by_phone($phone_no, $phone_code);

		if (! $user) {
			if ('login' === $auth_action) {
				$is_arabic = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
				wp_send_json_error(
					array(
						'message' => $is_arabic
							? 'لم يتم العثور على حساب برقم الجوال هذا. يرجى إنشاء حساب أولاً.'
							: __('No account found with this mobile number. Please sign up first.', 'spada-core'),
					),
					400
				);
			}

			// Create user with phone (only for signup flow)
			$username = 'user_' . substr($phone_no, -6);
			if (username_exists($username)) {
				$username = $username . '_' . wp_rand(10, 99);
			}
			$dummy_email = 'customer_' . $phone_no . '@spada.local';
			$password    = wp_generate_password(24, true, true);

			if (function_exists('wc_create_new_customer')) {
				$customer_id = wc_create_new_customer($dummy_email, $username, $password);
			} else {
				$customer_id = wp_create_user($username, $password, $dummy_email);
			}

			if (is_wp_error($customer_id)) {
				wp_send_json_error(array('message' => $customer_id->get_error_message()), 400);
			}

			$user = get_user_by('id', $customer_id);
			$full_phone = $phone_code . $phone_no;
			update_user_meta($customer_id, 'billing_phone', $full_phone);
			update_user_meta($customer_id, 'shipping_phone', $full_phone);
		}

		// Log in
		wp_clear_auth_cookie();
		wp_set_current_user($user->ID);
		wp_set_auth_cookie($user->ID, true);
		do_action('wp_login', $user->user_login, $user);

		if (function_exists('WC') && WC()->session) {
			WC()->session->set_customer_session_cookie(true);
		}

		if (! empty($_POST['redirect_to'])) {
			$redirect = esc_url_raw(wp_unslash($_POST['redirect_to']));
		} elseif (function_exists('is_checkout') && isset($_POST['is_checkout']) && 'yes' === $_POST['is_checkout']) {
			$redirect = wc_get_checkout_url();
		} elseif ('signup' === $auth_action) {
			$redirect = wc_get_page_permalink('myaccount');
		} else {
			$redirect = home_url('/');
		}

		$is_arabic       = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
		$success_message = ('signup' === $auth_action)
			? ($is_arabic ? 'تم التسجيل بنجاح!' : __('SignUp Successful!', 'spada-core'))
			: ($is_arabic ? 'تم تسجيل الدخول بنجاح!' : __('Login successful!', 'spada-core'));

		wp_send_json_success(
			array(
				'message'  => $success_message,
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Resend Phone OTP.
	 */
	public static function ajax_resend_phone_otp()
	{
		self::ajax_request_phone_otp();
	}
}
