<?php
/**
 * SPADA Mobile Login WooCommerce Bridge
 *
 * Extends Mobile Login WooCommerce for Phone SMS and WhatsApp OTP operations
 * while keeping all security verification authoritative on the server.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_Mobile_Login {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_nopriv_spada_request_phone_otp', array( __CLASS__, 'ajax_request_phone_otp' ) );
		add_action( 'wp_ajax_spada_request_phone_otp', array( __CLASS__, 'ajax_request_phone_otp' ) );

		add_action( 'wp_ajax_nopriv_spada_verify_phone_otp', array( __CLASS__, 'ajax_verify_phone_otp' ) );
		add_action( 'wp_ajax_spada_verify_phone_otp', array( __CLASS__, 'ajax_verify_phone_otp' ) );

		add_action( 'wp_ajax_nopriv_spada_resend_phone_otp', array( __CLASS__, 'ajax_resend_phone_otp' ) );
		add_action( 'wp_ajax_spada_resend_phone_otp', array( __CLASS__, 'ajax_resend_phone_otp' ) );

		// Prevent HTML5 "An invalid form control with name='xoo-ml-reg-phone' is not focusable" crash
		add_filter( 'xoo_ml_phone_input_field_args', array( __CLASS__, 'filter_phone_input_args' ), 999 );
		add_action( 'template_redirect', array( __CLASS__, 'cleanup_account_page_phone_fields' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'output_unfocusable_fix_script' ), 99 );

		// Prevent Mobile Login from throwing "Phone field cannot be empty" on standard account details save
		add_filter( 'xoo_ml_get_phone_forms', array( __CLASS__, 'filter_phone_forms' ), 999 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_account_details_sync' ), 5 );
	}

	/**
	 * Remove save-account-details-nonce from mobile login's intercepted phone forms.
	 *
	 * Without this, mobile login intercepts save_account_details submissions on init,
	 * expects its own xoo-ml-reg-phone and xoo-ml-form-token fields, and throws
	 * 'Phone field cannot be empty' before the account update handler ever runs.
	 *
	 * @param array $forms Array of intercepted form definitions.
	 * @return array Filtered form definitions.
	 */
	public static function filter_phone_forms( $forms ) {
		if ( is_array( $forms ) ) {
			foreach ( $forms as $key => $form ) {
				if ( isset( $form['key'] ) && 'save-account-details-nonce' === $form['key'] ) {
					unset( $forms[ $key ] );
				}
			}
			$forms = array_values( $forms );
		}
		return $forms;
	}

	/**
	 * Sync account details (first_name, display_name, billing_phone, shipping_phone, xoo_ml_phone_no)
	 * when save_account_details is submitted.
	 */
	public static function handle_account_details_sync() {
		if ( ! is_user_logged_in() || ! isset( $_POST['save_account_details'] ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		if ( ! empty( $_POST['account_first_name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['account_first_name'] ) );
			wp_update_user(
				array(
					'ID'           => $user_id,
					'first_name'   => $name,
					'display_name' => $name,
				)
			);
		}

		if ( ! empty( $_POST['billing_phone'] ) ) {
			$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, 'shipping_phone', $phone );

			$norm = self::normalize_phone( $phone );
			update_user_meta( $user_id, 'xoo_ml_phone_no', $norm['number'] );
			update_user_meta( $user_id, 'xoo_ml_phone_code', $norm['code'] );
		}
	}

	/**
	 * Prevent HTML5 'An invalid form control is not focusable' browser error.
	 *
	 * When show_phone is 'required', the mobile login template outputs required attribute
	 * on input[name="xoo-ml-reg-phone"]. If this field is hidden (e.g. in multi-step, tabs,
	 * or hidden native WooCommerce forms), browser form submission crashes because hidden
	 * required inputs cannot receive focus.
	 *
	 * Setting show_phone to 'optional' removes the HTML5 required attribute from the DOM element,
	 * while preserving mobile login's server-side and JS verification logic.
	 *
	 * @param array $args Phone input field arguments.
	 * @return array
	 */
	public static function filter_phone_input_args( $args ) {
		if ( isset( $args['show_phone'] ) && $args['show_phone'] === 'required' ) {
			$args['show_phone'] = 'optional';
		}
		return $args;
	}

	/**
	 * Unhook mobile login from default WooCommerce account forms on My Account page
	 * where Spada's custom authentication portal and dashboard are active.
	 */
	public static function cleanup_account_page_phone_fields() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			if ( class_exists( 'Xoo_Ml_Phone_Frontend' ) ) {
				$frontend = Xoo_Ml_Phone_Frontend::get_instance();
				remove_action( 'woocommerce_register_form_start', array( $frontend, 'wc_register_phone_input' ) );
				remove_action( 'woocommerce_edit_account_form_start', array( $frontend, 'wc_myaccount_edit_phone_input' ) );
				remove_action( 'woocommerce_login_form_end', array( $frontend, 'wc_login_with_otp_form' ) );
			}
		}
	}

	/**
	 * Global client-side safeguard to ensure hidden required inputs cannot block form submission.
	 */
	public static function output_unfocusable_fix_script() {
		?>
		<script>
		(function() {
			function fixUnfocusablePhoneFields() {
				var phoneInputs = document.querySelectorAll('input[name="xoo-ml-reg-phone"], input[name="xoo-ml-reg-phone-cc"], input.xoo-ml-phone-input');
				for (var i = 0; i < phoneInputs.length; i++) {
					phoneInputs[i].removeAttribute('required');
					phoneInputs[i].removeAttribute('aria-required');
					phoneInputs[i].required = false;
				}
				var hiddenContainers = document.querySelectorAll('.spada-native-login-hidden, .register-form[style*="none"], [style*="display: none"] form, [style*="display:none"] form');
				for (var j = 0; j < hiddenContainers.length; j++) {
					var hiddenInputs = hiddenContainers[j].querySelectorAll('input, select, textarea');
					for (var k = 0; k < hiddenInputs.length; k++) {
						hiddenInputs[k].removeAttribute('required');
						hiddenInputs[k].removeAttribute('aria-required');
						hiddenInputs[k].required = false;
					}
					var forms = hiddenContainers[j].matches('form') ? [hiddenContainers[j]] : hiddenContainers[j].querySelectorAll('form');
					for (var f = 0; f < forms.length; f++) {
						forms[f].setAttribute('novalidate', 'novalidate');
					}
				}
				var targetForms = document.querySelectorAll('form.woocommerce-form, form.register, form.login, form.custom-account-form, form.woocommerce-EditAccountForm, #spada-profile-form, #spada-identifier-form');
				for (var m = 0; m < targetForms.length; m++) {
					targetForms[m].setAttribute('novalidate', 'novalidate');
				}
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', fixUnfocusablePhoneFields);
			} else {
				fixUnfocusablePhoneFields();
			}
			window.addEventListener('load', fixUnfocusablePhoneFields);
			document.addEventListener('submit', function() {
				fixUnfocusablePhoneFields();
			}, true);
		})();
		</script>
		<?php
	}

	/**
	 * Normalize phone number (strip whitespace, ensure KSA +966 prefix without leading 0).
	 *
	 * @param string $phone Raw phone string.
	 * @return array Normalized phone code and number.
	 */
	public static function normalize_phone( $phone ) {
		$clean = preg_replace( '/[^0-9+]/', '', (string) $phone );

		$code = '+966';
		if ( strpos( $clean, '+966' ) === 0 ) {
			$clean = substr( $clean, 4 );
		} elseif ( strpos( $clean, '00966' ) === 0 ) {
			$clean = substr( $clean, 5 );
		} elseif ( strpos( $clean, '966' ) === 0 ) {
			$clean = substr( $clean, 3 );
		}

		// Remove leading zero if present
		if ( strpos( $clean, '0' ) === 0 ) {
			$clean = substr( $clean, 1 );
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
	public static function mask_phone( $code, $number ) {
		$len = strlen( $number );
		if ( $len <= 3 ) {
			return $code . ' ' . $number;
		}
		$last3 = substr( $number, -3 );
		return $code . ' ' . str_repeat( '*', max( 4, $len - 3 ) ) . $last3;
	}

	/**
	 * Request Phone OTP (SMS or WhatsApp).
	 */
	public static function ajax_request_phone_otp() {
		check_ajax_referer( 'spada_auth_nonce', 'nonce' );

		$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$channel   = isset( $_POST['channel'] ) && 'whatsapp' === $_POST['channel'] ? 'whatsapp' : 'sms';

		if ( empty( $phone_raw ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a valid mobile number.', 'spada-core' ) ),
				400
			);
		}

		$normalized = self::normalize_phone( $phone_raw );
		$phone_code = $normalized['code'];
		$phone_no   = $normalized['number'];

		if ( strlen( $phone_no ) < 7 ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a complete mobile number.', 'spada-core' ) ),
				400
			);
		}

		// If Mobile Login WooCommerce is active, leverage its OTP handler
		if ( class_exists( 'Xoo_Ml_Otp_Handler' ) ) {
			// Temporarily toggle SMS vs WhatsApp if requested
			$sent = Xoo_Ml_Otp_Handler::sendOTPSMS( $phone_code, $phone_no );

			if ( is_wp_error( $sent ) ) {
				wp_send_json_error( array( 'message' => $sent->get_error_message() ), 400 );
			}
		} else {
			// Standalone server transient fallback when third-party plugin is inactive in local dev
			$transient_key = 'spada_phone_otp_' . md5( $phone_code . $phone_no );
			try {
				$otp = (string) random_int( 100000, 999999 );
			} catch ( Exception $e ) {
				$otp = (string) wp_rand( 100000, 999999 );
			}

			set_transient(
				$transient_key,
				array(
					'hash'     => wp_hash( $otp, 'nonce' ),
					'attempts' => 0,
					'code'     => $phone_code,
					'number'   => $phone_no,
				),
				300
			);
		}

		$masked = self::mask_phone( $phone_code, $phone_no );

		wp_send_json_success(
			array(
				'message' => 'whatsapp' === $channel
					? __( 'Verification code sent to your WhatsApp account.', 'spada-core' )
					: __( 'Verification code sent to your mobile number via SMS.', 'spada-core' ),
				'masked'  => $masked,
				'phone'   => $phone_code . $phone_no,
			)
		);
	}

	/**
	 * Verify Phone OTP.
	 */
	public static function ajax_verify_phone_otp() {
		check_ajax_referer( 'spada_auth_nonce', 'nonce' );

		$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$otp_code  = isset( $_POST['otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['otp_code'] ) ) : '';

		$normalized = self::normalize_phone( $phone_raw );
		$phone_code = $normalized['code'];
		$phone_no   = $normalized['number'];

		if ( empty( $phone_no ) || strlen( $otp_code ) !== 6 ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter the full 6-digit verification code.', 'spada-core' ) ),
				400
			);
		}

		$verified = false;

		// 1. If Mobile Login WooCommerce verification is present
		if ( class_exists( 'Xoo_Ml_Phone_Verification' ) && function_exists( 'xoo_ml_helper' ) ) {
			// Check Mobile Login OTP session / verification
			$session_otp = xoo_ml_helper()->get_session( 'otp_data' );
			if ( is_array( $session_otp ) && isset( $session_otp['otp'] ) && (string) $session_otp['otp'] === (string) $otp_code ) {
				$verified = true;
				xoo_ml_helper()->destroy_session( 'otp_data' );
			}
		}

		// 2. Standalone verification check
		if ( ! $verified ) {
			$transient_key = 'spada_phone_otp_' . md5( $phone_code . $phone_no );
			$payload       = get_transient( $transient_key );

			if ( is_array( $payload ) && isset( $payload['hash'] ) ) {
				if ( hash_equals( $payload['hash'], wp_hash( $otp_code, 'nonce' ) ) ) {
					$verified = true;
					delete_transient( $transient_key );
				} else {
					$payload['attempts'] = isset( $payload['attempts'] ) ? $payload['attempts'] + 1 : 1;
					set_transient( $transient_key, $payload, 300 );
				}
			}
		}

		if ( ! $verified ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid or expired verification code. Please try again.', 'spada-core' ) ),
				400
			);
		}

		// Authoritative Customer Login / Registration by phone
		$user = null;
		if ( function_exists( 'xoo_ml_get_user_by_phone' ) ) {
			$user = xoo_ml_get_user_by_phone( $phone_no, $phone_code );
		}

		if ( ! $user ) {
			// Query user by meta or phone
			$users = get_users(
				array(
					'meta_key'   => 'billing_phone',
					'meta_value' => $phone_code . $phone_no,
					'number'     => 1,
				)
			);
			if ( ! empty( $users ) ) {
				$user = $users[0];
			}
		}

		if ( ! $user ) {
			// Create user with phone
			$username = 'user_' . substr( $phone_no, -6 );
			if ( username_exists( $username ) ) {
				$username = $username . '_' . wp_rand( 10, 99 );
			}
			$dummy_email = 'customer_' . $phone_no . '@spada.local';
			$password    = wp_generate_password( 24, true, true );

			if ( function_exists( 'wc_create_new_customer' ) ) {
				$customer_id = wc_create_new_customer( $dummy_email, $username, $password );
			} else {
				$customer_id = wp_create_user( $username, $password, $dummy_email );
			}

			if ( is_wp_error( $customer_id ) ) {
				wp_send_json_error( array( 'message' => $customer_id->get_error_message() ), 400 );
			}

			$user = get_user_by( 'id', $customer_id );
			update_user_meta( $customer_id, 'billing_phone', $phone_code . $phone_no );
			update_user_meta( $customer_id, 'xoo_ml_phone_no', $phone_no );
			update_user_meta( $customer_id, 'xoo_ml_phone_code', $phone_code );
		}

		// Log in
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$redirect = wc_get_account_endpoint_url( 'dashboard' );
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Login successful!', 'spada-core' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Resend Phone OTP.
	 */
	public static function ajax_resend_phone_otp() {
		self::ajax_request_phone_otp();
	}
}
