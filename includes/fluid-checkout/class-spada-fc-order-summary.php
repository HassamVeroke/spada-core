<?php
/**
 * SPADA Fluid Checkout Order Summary Customizer
 *
 * Customizes the Fluid Checkout Order Summary section, cart items,
 * + Add More button, VAT breakdown, and bottom TOTAL card matching the Figma design.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_FC_Order_Summary {

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Override template for review-order.php
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 200, 3 );
		add_filter( 'wc_get_template', array( __CLASS__, 'maybe_filter_wc_template' ), 200, 5 );

		// Header customizations: "ORDER SUMMARY" title & "X items" count (distinct products)
		add_filter( 'fc_order_review_title', array( __CLASS__, 'filter_order_review_title' ), 20 );
		add_filter( 'fc_pro_cart_display_items_count_html', array( __CLASS__, 'filter_cart_items_count_html' ), 20 );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'add_cart_items_count_fragment' ), 50 );
		add_action( 'fc_checkout_after_order_review_title_after', array( __CLASS__, 'output_cart_items_count_fallback' ), 15 );

		// Place .woocommerce-remove-coupon at start of the Price
		add_filter( 'woocommerce_cart_totals_coupon_html', array( __CLASS__, 'filter_coupon_html_order' ), 20, 3 );

		// Suppress cart item removed notices with Undo/Dismiss options
		add_filter( 'woocommerce_cart_item_removed_message', '__return_empty_string', 999 );
		add_filter( 'fc_pro_cart_removed_item_message', '__return_empty_string', 999 );
		add_filter( 'fc_pro_cart_removed_item_undo_button_label', '__return_empty_string', 999 );
		add_filter( 'fc_pro_cart_restore_item_message_dismiss_button', '__return_empty_string', 999 );

		// Enqueue Order Summary styles and scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );

		// Prevent Fluid Checkout Pro number spinner from injecting duplicate buttons into custom review order table
		add_filter( 'fc_pro_number_spinner_settings', array( __CLASS__, 'disable_fc_pro_number_spinner_on_checkout' ), 50 );

		// AJAX endpoints for interactive quantity stepper, item removal, and inline coupons
		add_action( 'wp_ajax_spada_fc_update_cart_qty', array( __CLASS__, 'ajax_update_cart_qty' ) );
		add_action( 'wp_ajax_nopriv_spada_fc_update_cart_qty', array( __CLASS__, 'ajax_update_cart_qty' ) );

		add_action( 'wp_ajax_spada_fc_remove_cart_item', array( __CLASS__, 'ajax_remove_cart_item' ) );
		add_action( 'wp_ajax_nopriv_spada_fc_remove_cart_item', array( __CLASS__, 'ajax_remove_cart_item' ) );

		add_action( 'wp_ajax_spada_fc_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_nopriv_spada_fc_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );

		// Localize coupon validation errors across WooCommerce & custom inline form
		add_filter( 'woocommerce_coupon_error', array( __CLASS__, 'filter_coupon_error' ), 999, 3 );

		// Suppress WooCommerce native coupon applied confirmation notices & toast banners
		add_filter( 'woocommerce_coupon_message', array( __CLASS__, 'filter_coupon_message' ), 999, 3 );
		add_filter( 'woocommerce_add_success', array( __CLASS__, 'suppress_coupon_success_notice' ), 999 );
	}

	/**
	 * Locate template override.
	 *
	 * @param string $template Template path.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @return string Modified template path.
	 */
	public static function locate_template( $template, $template_name, $template_path ) {
		if ( 'checkout/review-order.php' === $template_name ) {
			// Do not override if on the cart page
			if ( function_exists( 'is_cart' ) && is_cart() ) {
				return $template;
			}
			$custom_template = SPADA_CORE_PATH . 'templates/fluid-checkout/checkout/review-order.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Filter wc_get_template to ensure review-order.php override is always returned.
	 *
	 * @param string $located Located template file.
	 * @param string $template_name Template name.
	 * @param array  $args Template arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path Default template path.
	 * @return string Located template.
	 */
	public static function maybe_filter_wc_template( $located, $template_name, $args, $template_path, $default_path ) {
		if ( 'checkout/review-order.php' === $template_name ) {
			// Do not override if on the cart page
			if ( function_exists( 'is_cart' ) && is_cart() ) {
				return $located;
			}
			$custom_template = SPADA_CORE_PATH . 'templates/fluid-checkout/checkout/review-order.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		return $located;
	}

	/**
	 * Filter order summary title to "ORDER SUMMARY".
	 *
	 * @param string $title Existing title.
	 * @return string Uppercase title.
	 */
	public static function filter_order_review_title( $title ) {
		return __( 'ORDER SUMMARY', 'spada-core' );
	}

	/**
	 * Force Fluid Checkout to show "X items" count on the header.
	 *
	 * @return string Option value.
	 */
	public static function force_cart_items_count_link() {
		return 'cart_items_count';
	}

	/**
	 * Get the number of distinct products added to the cart.
	 *
	 * @return int Number of cart items.
	 */
	public static function get_cart_products_count() {
		return ( function_exists( 'WC' ) && WC()->cart ) ? count( WC()->cart->get_cart() ) : 0;
	}

	/**
	 * Check if current context is Arabic / RTL.
	 *
	 * @return bool True if Arabic or RTL.
	 */
	public static function is_arabic() {
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			return true;
		}

		$locale = get_locale();
		if ( ! empty( $locale ) && strpos( $locale, 'ar' ) === 0 ) {
			return true;
		}

		if ( class_exists( 'TRP_Translate_Press' ) ) {
			global $TRP_LANGUAGE;
			if ( ! empty( $TRP_LANGUAGE ) && strpos( $TRP_LANGUAGE, 'ar' ) === 0 ) {
				return true;
			}
		}

		if ( function_exists( 'trp_get_locale' ) ) {
			$trp_locale = trp_get_locale();
			if ( ! empty( $trp_locale ) && strpos( $trp_locale, 'ar' ) === 0 ) {
				return true;
			}
		}

		if ( ! empty( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/ar/' ) !== false ) {
			return true;
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), '/ar/' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the formatted cart items count HTML.
	 *
	 * @return string HTML span with count.
	 */
	public static function get_cart_items_count_html() {
		$count     = self::get_cart_products_count();
		$is_arabic = self::is_arabic();

		if ( $is_arabic ) {
			if ( 0 === $count ) {
				$text = 'لا توجد منتجات';
			} elseif ( 1 === $count ) {
				$text = 'منتج واحد';
			} elseif ( 2 === $count ) {
				$text = 'منتجان';
			} elseif ( $count >= 3 && $count <= 10 ) {
				$text = sprintf( '%d منتجات', $count );
			} else {
				$text = sprintf( '%d منتج', $count );
			}
		} else {
			$text = sprintf( _n( '%d item', '%d items', $count, 'spada-core' ), $count );
		}

		return sprintf( '<span class="fc-cart-items-count">%s</span>', esc_html( $text ) );
	}

	/**
	 * Filter Fluid Checkout PRO cart items count HTML to display distinct product count.
	 *
	 * @param string $html Existing HTML.
	 * @return string Modified HTML.
	 */
	public static function filter_cart_items_count_html( $html ) {
		return self::get_cart_items_count_html();
	}

	/**
	 * Ensure checkout review order fragments include distinct product count for .fc-cart-items-count.
	 *
	 * @param array $fragments WooCommerce checkout fragments.
	 * @return array Modified fragments.
	 */
	public static function add_cart_items_count_fragment( $fragments ) {
		$fragments['.fc-cart-items-count'] = self::get_cart_items_count_html();
		return $fragments;
	}

	/**
	 * Output fallback cart items count if Fluid Checkout PRO hasn't rendered it.
	 */
	public static function output_cart_items_count_fallback() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		if ( class_exists( 'FluidCheckout_PRO_CartPage' ) && has_action( 'fc_checkout_after_order_review_title_after', array( FluidCheckout_PRO_CartPage::instance(), 'output_cart_items_count' ) ) ) {
			return;
		}
		$rendered = true;
		echo self::get_cart_items_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Reorder coupon HTML so .woocommerce-remove-coupon is placed appropriately
	 * and alignment matches the other order summary amounts.
	 *
	 * In Arabic:
	 * - 428.16 [currency icon] [إزالة]
	 *
	 * In English:
	 * [Remove] -$428.16
	 *
	 * @param string           $coupon_html          Full coupon HTML.
	 * @param WC_Coupon|string $coupon               Coupon object or code.
	 * @param string           $discount_amount_html Discount amount HTML.
	 * @return string Modified coupon HTML.
	 */
	public static function filter_coupon_html_order( $coupon_html, $coupon, $discount_amount_html ) {
		$is_checkout = ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT )
			|| ( isset( $_GET['wc-ajax'] ) && in_array( $_GET['wc-ajax'], array( 'update_order_review', 'apply_coupon', 'remove_coupon' ), true ) )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_POST['action'] ) && strpos( $_POST['action'], 'spada_fc_' ) === 0 );

		if ( ! $is_checkout ) {
			return $coupon_html;
		}

		if ( is_string( $coupon ) ) {
			$coupon = new WC_Coupon( $coupon );
		}
		if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
			return $coupon_html;
		}

		$amount = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax ) : 0;
		if ( $amount > 0 ) {
			$price_html = wc_price( $amount );
		} else {
			$price_html = preg_replace( '/^[-\s\x{2212}\x{2013}]+/u', '', $discount_amount_html );
		}

		$remove_url  = esc_url( add_query_arg( 'remove_coupon', rawurlencode( $coupon->get_code() ), wc_get_checkout_url() ) );
		$is_arabic   = self::is_arabic();
		$remove_text = $is_arabic ? '[إزالة]' : __( '[Remove]', 'woocommerce' );
		$remove_link = '<a href="' . $remove_url . '" class="woocommerce-remove-coupon" data-coupon="' . esc_attr( $coupon->get_code() ) . '">' . esc_html( $remove_text ) . '</a>';

		if ( $is_arabic ) {
			// Arabic: - 428.16 [currency icon] [إزالة]
			$amount_html = '<span class="spada-discount-amount" dir="ltr">-&nbsp;' . $price_html . '</span>';
			return $amount_html . ' ' . $remove_link;
		} else {
			// English: [Remove] -$428.16
			$amount_html = '<span class="spada-discount-amount">-' . $price_html . '</span>';
			return $remove_link . ' ' . $amount_html;
		}
	}

	/**
	 * Prevent Fluid Checkout PRO number spinner from injecting duplicate buttons into the checkout review order table.
	 *
	 * @param array $settings Settings array.
	 * @return array Modified settings array.
	 */
	public static function disable_fc_pro_number_spinner_on_checkout( $settings ) {
		if ( isset( $settings['numberSpinnerOptions']['containerSelector'] ) ) {
			// Clear checkout review order table selector from number spinner so FC Pro does not add duplicate buttons
			$settings['numberSpinnerOptions']['containerSelector'] = '';
		}
		return $settings;
	}

	/**
	 * Enqueue checkout assets.
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// Enqueue Oswald font for headings & TOTAL box
		wp_enqueue_style(
			'spada-google-font-oswald',
			'https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap',
			array(),
			null
		);

		// Custom Order Summary styles
		wp_enqueue_style(
			'spada-fc-order-summary',
			SPADA_CORE_URL . 'assets/css/fluid-checkout-order-summary.css',
			array( 'spada-variables', 'spada-google-font-oswald' ),
			SPADA_CORE_VERSION
		);

		// Custom Order Summary interactive script
		wp_enqueue_script(
			'spada-fc-order-summary',
			SPADA_CORE_URL . 'assets/js/fluid-checkout-order-summary.js',
			array( 'jquery' ),
			SPADA_CORE_VERSION,
			true
		);

		$is_arabic = self::is_arabic();

		wp_localize_script(
			'spada-fc-order-summary',
			'SpadaFCOrderSummary',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'spada_fc_order_summary_nonce' ),
				'shopUrl'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
				'isRtl'       => $is_arabic,
				'couponEmpty' => $is_arabic ? 'يرجى إدخال رمز العرض.' : __( 'Please enter a coupon code.', 'spada-core' ),
				'userEmail'   => class_exists( 'Spada_FC_Checkout_Fields' ) ? Spada_FC_Checkout_Fields::get_logged_in_user_email() : '',
				'userProfile' => class_exists( 'Spada_FC_Checkout_Fields' ) ? Spada_FC_Checkout_Fields::get_logged_in_user_profile() : array(),
			)
		);
	}

	/**
	 * AJAX Update cart item quantity.
	 */
	public static function ajax_update_cart_qty() {
		check_ajax_referer( 'spada_fc_order_summary_nonce', 'security' );

		$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
		$quantity      = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;

		if ( empty( $cart_item_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item.', 'spada-core' ) ) );
		}

		if ( $quantity <= 0 ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		} else {
			WC()->cart->set_quantity( $cart_item_key, $quantity, false );
		}

		WC()->cart->calculate_totals();

		wp_send_json_success();
	}

	/**
	 * AJAX Remove cart item.
	 */
	public static function ajax_remove_cart_item() {
		check_ajax_referer( 'spada_fc_order_summary_nonce', 'security' );

		$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

		if ( empty( $cart_item_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item.', 'spada-core' ) ) );
		}

		WC()->cart->remove_cart_item( $cart_item_key );
		WC()->cart->calculate_totals();

		// Clear any cart item removed notices so no Undo/Dismiss messages are rendered
		wc_clear_notices();

		wp_send_json_success();
	}

	/**
	 * AJAX Apply coupon code.
	 */
	public static function ajax_apply_coupon() {
		check_ajax_referer( 'spada_fc_order_summary_nonce', 'security' );

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$is_arabic   = self::is_arabic();

		if ( empty( $coupon_code ) ) {
			$msg = $is_arabic ? 'يرجى إدخال رمز العرض.' : __( 'Please enter a coupon code.', 'spada-core' );
			wp_send_json_error( array( 'message' => $msg ) );
		}

		if ( WC()->cart->has_discount( $coupon_code ) ) {
			$msg = self::get_localized_coupon_error( '', 104, $coupon_code, $is_arabic );
			wp_send_json_error( array( 'message' => $msg ) );
		}

		$result = WC()->cart->apply_coupon( $coupon_code );

		if ( $result ) {
			WC()->cart->calculate_totals();
			// Clear all WooCommerce session notices so native confirmation toast/notice banner does not appear
			wc_clear_notices();
			$success_msg = $is_arabic ? 'تم تطبيق رمز العرض بنجاح.' : __( 'Coupon code applied successfully.', 'spada-core' );
			wp_send_json_success( array( 'message' => $success_msg ) );
		} else {
			$notices = wc_get_notices( 'error' );
			$msg     = ! empty( $notices ) ? wp_strip_all_tags( end( $notices )['notice'] ) : '';
			wc_clear_notices();

			$localized_msg = self::get_localized_coupon_error( $msg, 0, $coupon_code, $is_arabic );
			wp_send_json_error( array( 'message' => $localized_msg ) );
		}
	}

	/**
	 * Get properly localized coupon error message in Arabic or English.
	 *
	 * @param string    $err         Error text.
	 * @param int       $err_code    WooCommerce coupon error code.
	 * @param string    $coupon_code The coupon code.
	 * @param bool|null $is_arabic   Whether the context is Arabic.
	 * @return string Localized error string.
	 */
	public static function get_localized_coupon_error( $err = '', $err_code = 0, $coupon_code = '', $is_arabic = null ) {
		if ( null === $is_arabic ) {
			$is_arabic = self::is_arabic();
		}

		if ( empty( $coupon_code ) ) {
			if ( preg_match( '/["“\']([^"”\']+)["”\']/', $err, $matches ) ) {
				$coupon_code = $matches[1];
			} elseif ( ! empty( $_POST['coupon_code'] ) ) {
				$coupon_code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );
			}
		}

		$code_display = ! empty( $coupon_code ) ? $coupon_code : '';

		// 1. Check: Does not exist / Wrong coupon
		$is_not_exist = ( 105 === (int) $err_code )
			|| ( defined( 'WC_Coupon::E_WC_COUPON_NOT_EXIST' ) && WC_Coupon::E_WC_COUPON_NOT_EXIST === (int) $err_code )
			|| false !== stripos( $err, 'does not exist' )
			|| false !== stripos( $err, 'cannot be applied because it does not exist' )
			|| false !== stripos( $err, 'not exist' )
			|| ( empty( $err ) && 0 === (int) $err_code );

		if ( $is_not_exist && ( 105 === (int) $err_code || false !== stripos( $err, 'exist' ) || empty( $err ) ) ) {
			if ( $is_arabic ) {
				return $code_display
					? sprintf( 'لا يمكن تطبيق رمز العرض "%s" لأنه غير موجود.', $code_display )
					: 'رمز العرض المدخل غير موجود.';
			} else {
				return $code_display
					? sprintf( 'Promo "%s" cannot be applied because it does not exist.', $code_display )
					: 'Promo code does not exist.';
			}
		}

		// 2. Check: Expired coupon
		$is_expired = ( 107 === (int) $err_code )
			|| ( defined( 'WC_Coupon::E_WC_COUPON_EXPIRED' ) && WC_Coupon::E_WC_COUPON_EXPIRED === (int) $err_code )
			|| false !== stripos( $err, 'expired' )
			|| false !== strpos( $err, 'منتهي' );

		if ( $is_expired ) {
			if ( $is_arabic ) {
				return $code_display
					? sprintf( 'انتهت صلاحية رمز العرض "%s".', $code_display )
					: 'انتهت صلاحية رمز العرض.';
			} else {
				return $code_display
					? sprintf( 'Promo "%s" has expired.', $code_display )
					: 'Promo code has expired.';
			}
		}

		// 3. Check: Already applied
		$is_already_applied = ( 104 === (int) $err_code )
			|| ( defined( 'WC_Coupon::E_WC_COUPON_ALREADY_APPLIED' ) && WC_Coupon::E_WC_COUPON_ALREADY_APPLIED === (int) $err_code )
			|| false !== stripos( $err, 'already applied' );

		if ( $is_already_applied ) {
			if ( $is_arabic ) {
				return $code_display
					? sprintf( 'تم تطبيق رمز العرض "%s" مسبقاً.', $code_display )
					: 'تم تطبيق رمز العرض مسبقاً.';
			} else {
				return $code_display
					? sprintf( 'Promo code "%s" has already been applied.', $code_display )
					: 'Promo code already applied.';
			}
		}

		// 4. Check: Usage limit reached
		$is_usage_limit = ( 106 === (int) $err_code )
			|| ( defined( 'WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED' ) && WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED === (int) $err_code )
			|| false !== stripos( $err, 'usage limit' );

		if ( $is_usage_limit ) {
			if ( $is_arabic ) {
				return $code_display
					? sprintf( 'تم الوصول إلى الحد الأقصى لاستخدام رمز العرض "%s".', $code_display )
					: 'تم الوصول إلى الحد الأقصى لاستخدام رمز العرض.';
			} else {
				return 'Promo code usage limit has been reached.';
			}
		}

		// 5. Check: User's own coupon (child theme restriction)
		if ( false !== stripos( $err, 'own' ) || false !== stripos( $err, 'خاص بك' ) ) {
			return $is_arabic ? 'لا يمكنك استخدام رمز العرض الخاص بك.' : 'You cannot use your own promotional code.';
		}

		// 6. Check: Empty code
		if ( false !== stripos( $err, 'enter a coupon' ) || false !== stripos( $err, 'enter a promo' ) ) {
			return $is_arabic ? 'يرجى إدخال رمز العرض.' : 'Please enter a promotional code.';
		}

		// 7. Check: Minimum spend limit
		if ( 108 === (int) $err_code || false !== stripos( $err, 'minimum spend' ) ) {
			return $is_arabic ? 'لم يتم الوصول إلى الحد الأدنى للطلب لتطبيق رمز العرض.' : str_replace( 'Coupon', 'Promo', $err );
		}

		// 8. If message already contains Arabic text, preserve it
		if ( $is_arabic && preg_match( '/[\x{0600}-\x{06FF}]/u', $err ) ) {
			return $err;
		}

		// Fallback
		if ( $is_arabic ) {
			return 'رمز العرض غير صالح أو لا يمكن تطبيقه.';
		}

		return str_replace( 'Coupon', 'Promo', $err );
	}

	/**
	 * Filter WooCommerce coupon validation error to properly localize in Arabic and format in English.
	 *
	 * @param string           $err      Error text.
	 * @param int              $err_code Error code.
	 * @param WC_Coupon|string $coupon   Coupon object or code.
	 * @return string Localized error message.
	 */
	public static function filter_coupon_error( $err, $err_code = 0, $coupon = null ) {
		$coupon_code = '';
		if ( is_a( $coupon, 'WC_Coupon' ) ) {
			$coupon_code = $coupon->get_code();
		} elseif ( is_string( $coupon ) ) {
			$coupon_code = $coupon;
		}

		return self::get_localized_coupon_error( $err, $err_code, $coupon_code );
	}

	/**
	 * Suppress WooCommerce native coupon applied message.
	 *
	 * @param string $msg      Message.
	 * @param int    $msg_code Message code.
	 * @param mixed  $coupon   Coupon.
	 * @return string Empty string to suppress native notice.
	 */
	public static function filter_coupon_message( $msg, $msg_code = 0, $coupon = null ) {
		if ( defined( 'WC_Coupon::WC_COUPON_SUCCESS' ) && WC_Coupon::WC_COUPON_SUCCESS === $msg_code ) {
			return '';
		}
		return $msg;
	}

	/**
	 * Suppress WooCommerce native coupon applied success notice on checkout.
	 *
	 * @param string $message Notice message.
	 * @return string|false False if notice is a coupon applied confirmation.
	 */
	public static function suppress_coupon_success_notice( $message ) {
		$is_checkout = ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
			|| ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT );

		if ( $is_checkout ) {
			$lower = strtolower( wp_strip_all_tags( $message ) );
			if (
				false !== strpos( $lower, 'applied successfully' )
				|| false !== strpos( $lower, 'coupon code applied' )
				|| false !== strpos( $lower, 'promotional code applied' )
				|| false !== strpos( $lower, 'promo code applied' )
				|| false !== strpos( $message, 'تم تطبيق' )
			) {
				return false;
			}
		}

		return $message;
	}
}
