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
	 * Send an Email OTP to the specified email address.
	 *
	 * @param string $email User email.
	 * @return array Result with status and message.
	 */
	public static function send_otp($email)
	{
		$email = sanitize_email($email);

		if (! is_email($email)) {
			return array(
				'success' => false,
				'message' => __('Please enter a valid email address.', 'spada-core'),
			);
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

		if (is_array($existing_payload) && ! empty($existing_payload['expires_at']) && $now < (int) $existing_payload['expires_at']) {
			$remaining = (int) $existing_payload['expires_at'] - $now;
			$is_arabic = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
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
			'hash'       => wp_hash($otp, 'nonce'),
			'attempts'   => 0,
			'created_at' => $now,
			'expires_at' => $now + self::EXPIRATION_SECONDS,
			'email'      => $email,
		);

		set_transient($transient_key, $otp_payload, self::EXPIRATION_SECONDS);

		// Prepare branded HTML email
		$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		$subject   = sprintf(
			/* translators: %s: Site name */
			__('%s Verification Code: %s', 'spada-core'),
			$site_name,
			$otp
		);

		$message = self::get_email_template($otp, $site_name);
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf('From: %s <%s>', $site_name, get_option('admin_email')),
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
			'message' => __('Verification code sent successfully to your email address.', 'spada-core'),
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
		$is_arabic          = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
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
				$is_arabic = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
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

		$is_arabic       = (get_locale() === 'ar' || (function_exists('is_rtl') && is_rtl()));
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
	 * Branded HTML Email Template.
	 *
	 * @param string $otp 6-digit code.
	 * @param string $site_name Site name.
	 * @return string HTML content.
	 */
	private static function get_email_template($otp, $site_name)
	{
		ob_start();
?>
		<!DOCTYPE html>
		<html lang="en">

		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html($site_name); ?></title>
		</head>

		<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f9fafb; margin: 0; padding: 40px 16px; color: #111827;">
			<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 480px; background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
				<tr>
					<td style="background-color: #000000; padding: 24px; text-align: center;">
						<h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 2px;">SPADA</h1>
					</td>
				</tr>
				<tr>
					<td style="padding: 36px 32px; text-align: center;">
						<h2 style="font-size: 20px; font-weight: 700; margin: 0 0 12px 0; color: #111827;">
							<?php esc_html_e('Your Verification Code', 'spada-core'); ?>
						</h2>
						<p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 28px 0;">
							<?php esc_html_e('Use the 6-digit code below to securely sign in to your Spada account. This code will expire in 5 minutes.', 'spada-core'); ?>
						</p>

						<div style="background-color: #f3f4f6; border-radius: 12px; padding: 18px 24px; display: inline-block; margin-bottom: 28px; border: 1.5px dashed #00adb5;">
							<span style="font-family: monospace, Courier; font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #00adb5;">
								<?php echo esc_html($otp); ?>
							</span>
						</div>

						<p style="color: #9ca3af; font-size: 12px; margin: 0; line-height: 1.5;">
							<?php esc_html_e('If you did not request this verification code, please ignore this email.', 'spada-core'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<td style="background-color: #f9fafb; padding: 18px 32px; text-align: center; border-top: 1px solid #e5e7eb;">
						<p style="color: #9ca3af; font-size: 12px; margin: 0;">
							&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html($site_name); ?>. <?php esc_html_e('All rights reserved.', 'spada-core'); ?>
						</p>
					</td>
				</tr>
			</table>
		</body>

		</html>
<?php
		return ob_get_clean();
	}
}
