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
	 * Get the formatted cart items count HTML.
	 *
	 * @return string HTML span with count.
	 */
	public static function get_cart_items_count_html() {
		$count     = self::get_cart_products_count();
		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );

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
	 * Reorder coupon HTML so .woocommerce-remove-coupon is placed at the start of the price.
	 *
	 * @param string           $coupon_html          Full coupon HTML.
	 * @param WC_Coupon|string $coupon               Coupon object or code.
	 * @param string           $discount_amount_html Discount amount HTML.
	 * @return string Modified coupon HTML.
	 */
	public static function filter_coupon_html_order( $coupon_html, $coupon, $discount_amount_html ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $coupon_html;
		}

		if ( is_string( $coupon ) ) {
			$coupon = new WC_Coupon( $coupon );
		}
		if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
			return $coupon_html;
		}

		$remove_url  = esc_url( add_query_arg( 'remove_coupon', rawurlencode( $coupon->get_code() ), wc_get_checkout_url() ) );
		$is_arabic   = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$remove_text = $is_arabic ? '[إزالة]' : __( '[Remove]', 'woocommerce' );
		$remove_link = '<a href="' . $remove_url . '" class="woocommerce-remove-coupon" data-coupon="' . esc_attr( $coupon->get_code() ) . '">' . esc_html( $remove_text ) . '</a>';

		return $remove_link . ' ' . $discount_amount_html;
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

		wp_localize_script(
			'spada-fc-order-summary',
			'SpadaFCOrderSummary',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'spada_fc_order_summary_nonce' ),
				'shopUrl'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
				'isRtl'       => is_rtl(),
				'couponEmpty' => __( 'Please enter a coupon code.', 'spada-core' ),
				'userEmail'   => class_exists( 'Spada_FC_Checkout_Fields' ) ? Spada_FC_Checkout_Fields::get_logged_in_user_email() : '',
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

		if ( empty( $coupon_code ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'spada-core' ) ) );
		}

		if ( WC()->cart->has_discount( $coupon_code ) ) {
			wp_send_json_error( array( 'message' => __( 'Coupon code already applied.', 'spada-core' ) ) );
		}

		$result = WC()->cart->apply_coupon( $coupon_code );

		if ( $result ) {
			WC()->cart->calculate_totals();
			wp_send_json_success( array( 'message' => __( 'Coupon code applied successfully.', 'spada-core' ) ) );
		} else {
			$notices = wc_get_notices( 'error' );
			$msg = ! empty( $notices ) ? wp_strip_all_tags( end( $notices )['notice'] ) : __( 'Invalid coupon code.', 'spada-core' );
			wc_clear_notices();
			wp_send_json_error( array( 'message' => $msg ) );
		}
	}
}
