<?php
/**
 * SPADA Authentication AJAX Handlers
 *
 * Secure AJAX endpoints for Email OTP dispatch, verification, and session creation.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_Auth_Ajax {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_nopriv_spada_request_email_otp', array( __CLASS__, 'ajax_request_email_otp' ) );
		add_action( 'wp_ajax_spada_request_email_otp', array( __CLASS__, 'ajax_request_email_otp' ) );

		add_action( 'wp_ajax_nopriv_spada_verify_email_otp', array( __CLASS__, 'ajax_verify_email_otp' ) );
		add_action( 'wp_ajax_spada_verify_email_otp', array( __CLASS__, 'ajax_verify_email_otp' ) );

		add_action( 'wp_ajax_nopriv_spada_resend_email_otp', array( __CLASS__, 'ajax_resend_email_otp' ) );
		add_action( 'wp_ajax_spada_resend_email_otp', array( __CLASS__, 'ajax_resend_email_otp' ) );
	}

	/**
	 * Request Email OTP handler.
	 */
	public static function ajax_request_email_otp() {
		check_ajax_referer( 'spada_auth_nonce', 'nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$auth_action = isset( $_POST['auth_action'] ) && 'signup' === sanitize_key( $_POST['auth_action'] ) ? 'signup' : 'login';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please provide a valid email address.', 'spada-core' ) ),
				400
			);
		}

		$is_arabic   = Spada_OTP_Email::is_arabic();
		$user_exists = (bool) email_exists( $email );

		if ( 'login' === $auth_action && ! $user_exists ) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'لم يتم العثور على حساب بهذا البريد الإلكتروني. يرجى إنشاء حساب أولاً.'
						: __( 'No account found with this email address. Please sign up first.', 'spada-core' ),
				),
				400
			);
		}

		if ( 'signup' === $auth_action && $user_exists ) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'يوجد حساب بالفعل بهذا البريد الإلكتروني. يرجى تسجيل الدخول بدلاً من ذلك.'
						: __( 'An account with this email address already exists. Please sign in instead.', 'spada-core' ),
				),
				400
			);
		}

		$result = Spada_OTP_Email::send_otp( $email, $auth_action );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
				'email'   => $email,
			)
		);
	}

	/**
	 * Verify Email OTP handler.
	 */
	public static function ajax_verify_email_otp() {
		check_ajax_referer( 'spada_auth_nonce', 'nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$otp_code    = isset( $_POST['otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['otp_code'] ) ) : '';
		$auth_action = isset( $_POST['auth_action'] ) && 'signup' === sanitize_key( $_POST['auth_action'] ) ? 'signup' : 'login';

		if ( empty( $email ) || empty( $otp_code ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Email and 6-digit verification code are required.', 'spada-core' ) ),
				400
			);
		}

		$result = Spada_OTP_Email::verify_otp( $email, $otp_code, $auth_action );

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array(
					'message'   => $result['message'],
					'remaining' => isset( $result['remaining'] ) ? $result['remaining'] : 0,
				),
				400
			);
		}

		// Optional redirect override (e.g. from checkout or redirect_to parameter)
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
		} elseif ( function_exists( 'is_checkout' ) && isset( $_POST['is_checkout'] ) && 'yes' === $_POST['is_checkout'] ) {
			$redirect = wc_get_checkout_url();
		} elseif ( 'signup' === $auth_action ) {
			$redirect = wc_get_page_permalink( 'myaccount' );
		} else {
			$redirect = home_url( '/' );
		}

		wp_send_json_success(
			array(
				'message'  => $result['message'],
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Resend Email OTP handler.
	 */
	public static function ajax_resend_email_otp() {
		check_ajax_referer( 'spada_auth_nonce', 'nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$auth_action = isset( $_POST['auth_action'] ) && 'signup' === sanitize_key( $_POST['auth_action'] ) ? 'signup' : 'login';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please provide a valid email address.', 'spada-core' ) ),
				400
			);
		}

		$is_arabic   = Spada_OTP_Email::is_arabic();
		$user_exists = (bool) email_exists( $email );

		if ( 'login' === $auth_action && ! $user_exists ) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'لم يتم العثور على حساب بهذا البريد الإلكتروني. يرجى إنشاء حساب أولاً.'
						: __( 'No account found with this email address. Please sign up first.', 'spada-core' ),
				),
				400
			);
		}

		if ( 'signup' === $auth_action && $user_exists ) {
			wp_send_json_error(
				array(
					'message' => $is_arabic
						? 'يوجد حساب بالفعل بهذا البريد الإلكتروني. يرجى تسجيل الدخول بدلاً من ذلك.'
						: __( 'An account with this email address already exists. Please sign in instead.', 'spada-core' ),
				),
				400
			);
		}

		$result = Spada_OTP_Email::send_otp( $email, $auth_action );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		wp_send_json_success( array( 'message' => $is_arabic ? 'تم إرسال رمز تحقق جديد.' : __( 'New verification code has been sent.', 'spada-core' ) ) );
	}
}
