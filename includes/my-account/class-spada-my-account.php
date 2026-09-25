<?php
/**
 * SPADA My Account Customization Class
 *
 * Enqueues dedicated styling, removes sidebar navigation & hero section,
 * and renders customer profile card matching screenshot exactly.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_My_Account {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );

		// Remove default WooCommerce account navigation (sidebar)
		add_action( 'template_redirect', array( __CLASS__, 'remove_default_account_navigation' ) );

		// Render custom dashboard matching screenshot
		remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard' );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_customer_dashboard_overview' ), 5 );

		// AJAX and POST handler for saving profile changes
		add_action( 'wp_ajax_spada_update_account_details', array( __CLASS__, 'ajax_update_account_details' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post_update_account_details' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_save_account_details' ), 5 );

		// Sync customer shipping address when an order is placed
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'sync_address_from_order' ), 20, 3 );
		add_action( 'woocommerce_checkout_update_user_meta', array( __CLASS__, 'sync_address_from_checkout_meta' ), 20, 2 );
	}

	/**
	 * Sync customer shipping address when order is processed.
	 *
	 * @param int $order_id Order ID.
	 * @param array $posted_data Posted checkout data.
	 * @param WC_Order $order Order instance.
	 */
	public static function sync_address_from_order( $order_id, $posted_data = array(), $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return;
		}

		$shipping_addr = $order->get_shipping_address_1();
		if ( empty( $shipping_addr ) ) {
			$shipping_addr = $order->get_billing_address_1();
		}

		$shipping_addr = self::clean_address_string( $shipping_addr );

		if ( ! empty( $shipping_addr ) ) {
			update_user_meta( $customer_id, 'shipping_address_1', $shipping_addr );
			update_user_meta( $customer_id, 'billing_address_1', $shipping_addr );
		}
	}

	/**
	 * Sync customer shipping address during checkout user meta update.
	 *
	 * @param int $customer_id Customer ID.
	 * @param array $data Posted checkout data.
	 */
	public static function sync_address_from_checkout_meta( $customer_id, $data ) {
		if ( ! $customer_id || empty( $data ) ) {
			return;
		}

		$shipping_addr = ! empty( $data['shipping_address_1'] ) ? sanitize_text_field( $data['shipping_address_1'] ) : '';
		if ( empty( $shipping_addr ) && ! empty( $data['billing_address_1'] ) ) {
			$shipping_addr = sanitize_text_field( $data['billing_address_1'] );
		}

		$shipping_addr = self::clean_address_string( $shipping_addr );

		if ( ! empty( $shipping_addr ) ) {
			update_user_meta( $customer_id, 'shipping_address_1', $shipping_addr );
			update_user_meta( $customer_id, 'billing_address_1', $shipping_addr );
		}
	}

	/**
	 * Remove default WooCommerce account navigation.
	 */
	public static function remove_default_account_navigation() {
		if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) {
			remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
		}
	}

	/**
	 * Enqueue assets on My Account page.
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		// Enqueue Oswald font for headings & buttons
		wp_enqueue_style(
			'spada-google-font-oswald',
			'https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/css/my-account.css',
			array( 'spada-variables', 'spada-google-font-oswald' ),
			SPADA_CORE_VERSION
		);

		wp_enqueue_script(
			'spada-my-account',
			SPADA_CORE_URL . 'assets/js/my-account.js',
			array( 'jquery' ),
			SPADA_CORE_VERSION,
			true
		);

		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );

		wp_localize_script(
			'spada-my-account',
			'SpadaAccountData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'spada_update_account_action' ),
				'isRtl'   => $is_arabic,
				'authHero' => array(
					'defaultHead' => $is_arabic ? 'حسابي' : 'Account',
					'defaultDesc' => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة للوصول إلى حسابك.' : 'Please provide necessary details to access to your account.',
					'loginHead'   => $is_arabic ? 'تسجيل الدخول' : 'Login',
					'loginDesc'   => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لتسجيل الدخول إلى حسابك.' : 'Please provide necessary details to login to your account.',
					'signupHead'  => $is_arabic ? 'إنشاء حساب' : 'Sign up',
					'signupDesc'  => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لإنشاء حسابك.' : 'Please provide necessary details to sign up to your account.',
				),
				'i18n'    => array(
					'saving'        => $is_arabic ? 'جاري الحفظ...' : __( 'Saving...', 'spada-core' ),
					'saveChanges'   => $is_arabic ? 'حفظ التغييرات' : __( 'SAVE CHANGES', 'spada-core' ),
					'updateSuccess' => $is_arabic ? 'تم حفظ التغييرات بنجاح.' : __( 'Account details updated successfully.', 'spada-core' ),
					'genericError'  => $is_arabic ? 'حدث خطأ، يرجى المحاولة مرة أخرى.' : __( 'Something went wrong. Please try again.', 'spada-core' ),
				),
			)
		);
	}

	/**
	 * Render customer dashboard overview.
	 */
	public static function render_customer_dashboard_overview() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$template_path = SPADA_CORE_PATH . 'templates/my-account/account-dashboard.php';
		if ( file_exists( $template_path ) ) {
			require $template_path;
		}
	}

	/**
	 * AJAX Update account details.
	 */
	public static function ajax_update_account_details() {
		check_ajax_referer( 'spada_update_account_action', 'security' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to update your account.', 'spada-core' ) ) );
		}

		$display_name = isset( $_POST['spada_account_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_display_name'] ) ) : '';
		$phone        = isset( $_POST['spada_billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_phone'] ) ) : '';
		$email        = isset( $_POST['spada_account_email'] ) ? sanitize_email( wp_unslash( $_POST['spada_account_email'] ) ) : '';
		$address      = isset( $_POST['spada_billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_address'] ) ) : '';
		$address      = self::clean_address_string( $address );
		$password     = isset( $_POST['spada_account_password'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_password'] ) ) : '';

		if ( empty( $display_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'spada-core' ) ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'spada-core' ) ) );
		}

		// Check email uniqueness if changed
		$current_user = wp_get_current_user();
		if ( strtolower( $current_user->user_email ) !== strtolower( $email ) ) {
			$existing_user_id = email_exists( $email );
			if ( $existing_user_id && $existing_user_id !== $user_id ) {
				wp_send_json_error( array( 'message' => __( 'This email is already in use by another account.', 'spada-core' ) ) );
			}
		}

		// Update user standard fields
		$userdata = array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'first_name'   => $display_name,
			'user_email'   => $email,
		);

		$result = wp_update_user( $userdata );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Update user meta
		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, 'shipping_phone', $phone );
		}
		if ( ! empty( $address ) ) {
			update_user_meta( $user_id, 'billing_address_1', $address );
			update_user_meta( $user_id, 'shipping_address_1', $address );
		}
		update_user_meta( $user_id, 'billing_email', $email );

		// Maybe update password
		if ( ! empty( $password ) ) {
			wp_set_password( $password, $user_id );
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}

		// Dispatch welcome email if user previously had no real email (e.g. phone signup)
		if ( class_exists( 'Spada_Welcome_Email' ) && ! empty( $email ) && ! Spada_Welcome_Email::is_dummy_email( $email ) ) {
			Spada_Welcome_Email::send_welcome_email( $user_id, $password, empty( $password ) );
		}

		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$msg = $is_arabic ? 'تم حفظ التغييرات بنجاح.' : __( 'Account details updated successfully.', 'spada-core' );

		wp_send_json_success( array( 'message' => $msg ) );
	}

	/**
	 * Fallback standard POST handler for account updates.
	 */
	public static function handle_post_update_account_details() {
		if ( ! isset( $_POST['spada_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spada_account_nonce'] ) ), 'spada_update_account_action' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$display_name = isset( $_POST['spada_account_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_display_name'] ) ) : '';
		$phone        = isset( $_POST['spada_billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_phone'] ) ) : '';
		$email        = isset( $_POST['spada_account_email'] ) ? sanitize_email( wp_unslash( $_POST['spada_account_email'] ) ) : '';
		$address      = isset( $_POST['spada_billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_billing_address'] ) ) : '';
		$address      = self::clean_address_string( $address );
		$password     = isset( $_POST['spada_account_password'] ) ? sanitize_text_field( wp_unslash( $_POST['spada_account_password'] ) ) : '';

		if ( ! empty( $display_name ) && ! empty( $email ) && is_email( $email ) ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
					'first_name'   => $display_name,
					'user_email'   => $email,
				)
			);

			if ( ! empty( $phone ) ) {
				update_user_meta( $user_id, 'billing_phone', $phone );
				update_user_meta( $user_id, 'shipping_phone', $phone );
			}
			if ( ! empty( $address ) ) {
				update_user_meta( $user_id, 'billing_address_1', $address );
				update_user_meta( $user_id, 'shipping_address_1', $address );
			}
			update_user_meta( $user_id, 'billing_email', $email );

			if ( ! empty( $password ) ) {
				wp_set_password( $password, $user_id );
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );
			}

			// Dispatch welcome email if user previously had no real email (e.g. phone signup)
			if ( class_exists( 'Spada_Welcome_Email' ) && ! empty( $email ) && ! Spada_Welcome_Email::is_dummy_email( $email ) ) {
				Spada_Welcome_Email::send_welcome_email( $user_id, $password, empty( $password ) );
			}
		}

		wp_safe_redirect( add_query_arg( 'spada_updated', '1', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Intercept standard WooCommerce save_account_details submission to clean and sync address & phone.
	 */
	public static function handle_save_account_details() {
		if ( ! is_user_logged_in() || ! isset( $_POST['save_account_details'] ) ) {
			return;
		}
		if ( ! isset( $_POST['save-account-details-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ), 'save_account_details' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		if ( ! empty( $_POST['billing_address_1'] ) ) {
			$clean_addr = self::clean_address_string( sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) ) );
			$_POST['billing_address_1'] = $clean_addr;
			update_user_meta( $user_id, 'billing_address_1', $clean_addr );
			update_user_meta( $user_id, 'shipping_address_1', $clean_addr );
		}

		if ( ! empty( $_POST['billing_phone'] ) ) {
			$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, 'shipping_phone', $phone );
		}
	}

	/**
	 * Clean and deduplicate combined address strings (e.g. from Google Places + Geocoder).
	 *
	 * Detects when a place name and formatted address were concatenated together
	 * (e.g. "8996 Al Awsat Valley St Al Olaya Riyadh 12214 2510 Al Awsat Valley St, 2510, Al Olaya, Riyadh 12214, Saudi Arabia")
	 * and returns the single, properly formatted address.
	 *
	 * @param string $address Raw address string.
	 * @return string Cleaned single address string.
	 */
	public static function clean_address_string( $address ) {
		if ( empty( $address ) || ! is_string( $address ) ) {
			return '';
		}

		$address = trim( preg_replace( '/\s+/', ' ', $address ) );

		$strlen = function( $s ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
		};
		$substr = function( $s, $start, $len = null ) {
			return function_exists( 'mb_substr' ) ? mb_substr( $s, $start, $len, 'UTF-8' ) : ( null !== $len ? substr( $s, $start, $len ) : substr( $s, $start ) );
		};
		$strtolower = function( $s ) {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		};
		$stripos = function( $haystack, $needle, $offset = 0 ) {
			return function_exists( 'mb_stripos' ) ? mb_stripos( $haystack, $needle, $offset, 'UTF-8' ) : stripos( $haystack, $needle, $offset );
		};

		if ( $strlen( $address ) < 20 ) {
			return $address;
		}

		// 1. Direct exact duplicate check: "Address Address" -> "Address"
		$len   = $strlen( $address );
		$half  = (int) floor( $len / 2 );
		$left  = trim( $substr( $address, 0, $half ) );
		$right = trim( $substr( $address, $half ) );
		if ( $left === $right ) {
			return $left;
		}

		// 2. Pattern A: Check if a 5-digit postal code is followed by a building number / street
		if ( preg_match( '/^(.+?\b\d{5}\b)\s+((?:\d{1,5}\b\s+)?[A-Za-z\p{Arabic}].+)$/u', $address, $matches ) ) {
			$seg1 = trim( $matches[1] );
			$seg2 = trim( $matches[2] );

			$words1 = preg_split( '/[\s,]+/', $strtolower( $seg1 ) );
			$words2 = preg_split( '/[\s,]+/', $strtolower( $seg2 ) );
			$words1 = array_filter( $words1, function( $w ) use ( $strlen ) { return $strlen( $w ) >= 3; } );
			$words2 = array_filter( $words2, function( $w ) use ( $strlen ) { return $strlen( $w ) >= 3; } );

			$shared = array_intersect( $words1, $words2 );
			if ( count( $shared ) >= 2 ) {
				$has_country2 = preg_match( '/(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i', $seg2 );
				$has_country1 = preg_match( '/(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i', $seg1 );
				$commas1      = substr_count( $seg1, ',' );
				$commas2      = substr_count( $seg2, ',' );

				if ( $has_country2 || $commas2 > $commas1 ) {
					$best = $seg2;
				} elseif ( $has_country1 || $commas1 > $commas2 ) {
					$best = $seg1;
				} else {
					$best = ( $strlen( $seg2 ) >= $strlen( $seg1 ) ) ? $seg2 : $seg1;
				}

				// If seg1 had primary building number (e.g. 8996) and seg2 started with secondary number (e.g. 2510)
				if ( preg_match( '/^(\d{3,5})\s+([A-Za-z\p{Arabic}].+)$/u', $seg1, $m1 ) &&
				     preg_match( '/^(\d{3,5})\s+([A-Za-z\p{Arabic}].+)$/u', $best, $m2 ) ) {
					$bldg1 = $m1[1];
					$bldg2 = $m2[1];
					if ( $bldg1 !== $bldg2 && preg_match( '/,\s*' . preg_quote( $bldg2, '/' ) . '\s*,/', $best ) ) {
						$best = preg_replace( '/^' . preg_quote( $bldg2, '/' ) . '\b/', $bldg1, $best, 1 );
					}
				}

				return $best;
			}
		}

		// 3. Pattern B: Look for repeated street / word sequence of 2+ words
		$words     = preg_split( '/\s+/', $address );
		$num_words = count( $words );
		if ( $num_words >= 6 ) {
			for ( $window = 4; $window >= 2; $window-- ) {
				for ( $i = 0; $i <= $num_words - ( $window * 2 ); $i++ ) {
					$phrase       = implode( ' ', array_slice( $words, $i, $window ) );
					$clean_phrase = preg_replace( '/[^\w\s\p{Arabic}]/u', '', $phrase );
					if ( $strlen( $clean_phrase ) < 6 ) {
						continue;
					}

					$clean_addr = preg_replace( '/[^\w\s\p{Arabic}]/u', ' ', $address );
					$first_pos  = $stripos( $clean_addr, $clean_phrase );
					if ( false !== $first_pos ) {
						$second_pos = $stripos( $clean_addr, $clean_phrase, $first_pos + $strlen( $clean_phrase ) );
						if ( false !== $second_pos ) {
							$prefix = $substr( $address, 0, $second_pos );
							if ( preg_match( '/\s+(\d{1,5})\s+$/u', $prefix, $num_m, PREG_OFFSET_CAPTURE ) ) {
								$split_idx = $num_m[0][1];
							} else {
								$split_idx = $second_pos;
							}

							$p1 = trim( $substr( $address, 0, $split_idx ) );
							$p2 = trim( $substr( $address, $split_idx ) );

							if ( ! empty( $p1 ) && ! empty( $p2 ) ) {
								$c1      = substr_count( $p1, ',' );
								$c2      = substr_count( $p2, ',' );
								$has_c2  = preg_match( '/(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i', $p2 );
								$has_c1  = preg_match( '/(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i', $p1 );

								if ( $has_c2 || $c2 > $c1 ) {
									$chosen = $p2;
								} elseif ( $has_c1 || $c1 > $c2 ) {
									$chosen = $p1;
								} else {
									$chosen = ( $strlen( $p2 ) >= $strlen( $p1 ) ) ? $p2 : $p1;
								}

								if ( preg_match( '/^(\d{3,5})\s+([A-Za-z\p{Arabic}].+)$/u', $p1, $m1 ) &&
								     preg_match( '/^(\d{3,5})\s+([A-Za-z\p{Arabic}].+)$/u', $chosen, $m2 ) ) {
									$bldg1 = $m1[1];
									$bldg2 = $m2[1];
									if ( $bldg1 !== $bldg2 && preg_match( '/,\s*' . preg_quote( $bldg2, '/' ) . '\s*,/', $chosen ) ) {
										$chosen = preg_replace( '/^' . preg_quote( $bldg2, '/' ) . '\b/', $bldg1, $chosen, 1 );
									}
								}

								return $chosen;
							}
						}
					}
				}
			}
		}

		return $address;
	}
}
