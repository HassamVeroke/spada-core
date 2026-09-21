<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_Buy_Now {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_spada_buy_now_product', array( $this, 'ajax_product_info' ) );
		add_action( 'wp_ajax_nopriv_spada_buy_now_product', array( $this, 'ajax_product_info' ) );
		add_action( 'wp_ajax_spada_buy_now_variation_form', array( $this, 'ajax_variation_form' ) );
		add_action( 'wp_ajax_nopriv_spada_buy_now_variation_form', array( $this, 'ajax_variation_form' ) );
		add_action( 'wp_ajax_spada_buy_now_add', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_spada_buy_now_add', array( $this, 'ajax_add_to_cart' ) );
	}

	private function is_archive_context() {
		return is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy();
	}

	public function enqueue_assets() {

		if ( ! $this->is_archive_context() ) {
			return;
		}

		$currency_symbol = get_woocommerce_currency_symbol();

		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-add-to-cart-variation' );

		wp_enqueue_style(
			'spada-buy-now',
			SPADA_BUY_NOW_URL . 'assets/css/spada-buy-now.css',
			array( 'spada-variables' ),
			SPADA_BUY_NOW_VERSION
		);


		wp_enqueue_script(
			'spada-buy-now',
			SPADA_BUY_NOW_URL . 'assets/js/spada-buy-now.js',
			array( 'jquery', 'wc-add-to-cart', 'wc-add-to-cart-variation' ),
			SPADA_BUY_NOW_VERSION,
			true
		);

		wp_localize_script(
			'spada-buy-now',
			'SpadaBuyNow',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'spada_buy_now' ),
				'checkoutUrl'=> wc_get_checkout_url(),
				'currency'    => array(
					'symbol'           => wp_strip_all_tags( $currency_symbol ),
					'htmlSymbol'       => wp_kses(
						$currency_symbol,
						array(
							'img' => array(
								'src'     => true,
								'alt'     => true,
								'class'   => true,
								'height'  => true,
								'loading' => true,
								'style'   => true,
								'width'   => true,
							),
						)
					),
					'position'         => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals'         => wc_get_price_decimals(),
					'decimalSeparator'  => wc_get_price_decimal_separator(),
					'thousandSeparator' => wc_get_price_thousand_separator(),
				),
				'buttonClass'=> 'spada-buy-now',
				'strings'    => array(
					'loading'          => __( 'Loading...', 'spada-core' ),
					'loadingVariations' => __( 'Loading options...', 'spada-core' ),
					'buyNow'           => __( 'Buy Now', 'spada-core' ),
					'selectOptions'    => __( 'Select Options', 'spada-core' ),
					'buyNowFor'        => __( 'Buy Now for %s', 'spada-core' ),
					'outOfStock'       => __( 'Out of Stock', 'spada-core' ),
					'unavailable'      => __( 'Unavailable', 'spada-core' ),
					'selectVariation'  => __( 'Please select all available options.', 'spada-core' ),
					'notAvailable'     => __( 'This variation is not available.', 'spada-core' ),
					'error'            => __( 'Something went wrong. Please try again.', 'spada-core' ),
				),
			)
		);
	}

	/**
	 * Detect product ID from the current WooCommerce/Elementor loop.
	 * The JS also reads standard WooCommerce "post-{ID}" loop classes.
	 */
	public function ajax_product_info() {
		check_ajax_referer( 'spada_buy_now', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product ) {
			wp_send_json_error(
				array( 'message' => __( 'Product could not be found.', 'spada-core' ) ),
				404
			);
		}

		wp_send_json_success(
			array(
				'id'          => $product->get_id(),
				'type'        => $product->is_type( 'variable' ) ? 'variable' : 'simple',
				'in_stock'    => $product->is_in_stock(),
				'purchasable' => $product->is_purchasable(),
				'price_html'  => $product->is_type( 'simple' ) && '' !== $product->get_price()
					? wp_kses_post( wc_price( $product->get_price() ) )
					: '',
			)
		);
	}

	public function ajax_variation_form() {
		check_ajax_referer( 'spada_buy_now', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$wc_product = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $wc_product || ! $wc_product->is_type( 'variable' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Variable product could not be found.', 'spada-core' ) ),
				404
			);
		}

		global $post, $product;

		$old_post    = $post;
		$old_product = $product;

		$post    = get_post( $product_id );
		$product = $wc_product;

		if ( $post ) {
			setup_postdata( $post );
		}

		ob_start();

		wc_get_template(
			'single-product/add-to-cart/variable.php',
			array(
				'available_variations' => $wc_product->get_available_variations(),
				'attributes'           => $wc_product->get_variation_attributes(),
				'selected_attributes'  => $wc_product->get_default_attributes(),
			)
		);

		$html = ob_get_clean();

		wp_reset_postdata();
		$post    = $old_post;
		$product = $old_product;

		wp_send_json_success(
			array(
				'html' => $html,
			)
		);
	}

	public function ajax_add_to_cart() {
		check_ajax_referer( 'spada_buy_now', 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array( 'message' => __( 'Cart is unavailable.', 'spada-core' ) ),
				400
			);
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;

		$quantity = max( 1, $quantity );
		$product  = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product ) {
			wp_send_json_error(
				array( 'message' => __( 'Product could not be found.', 'spada-core' ) ),
				404
			);
		}

		$variation = array();

		if ( $product->is_type( 'variable' ) ) {
			if ( ! $variation_id ) {
				wp_send_json_error(
					array( 'message' => __( 'Please select a variation.', 'spada-core' ) ),
					400
				);
			}

			$variation_product = wc_get_product( $variation_id );

			if ( ! $variation_product || ! $variation_product->is_type( 'variation' ) || (int) $variation_product->get_parent_id() !== (int) $product_id ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid variation selected.', 'spada-core' ) ),
					400
				);
			}

			$variation = $variation_product->get_variation_attributes();

			$variation_check = $product->get_available_variation( $variation_id );
			if ( ! $variation_check ) {
				wp_send_json_error(
					array( 'message' => __( 'This variation is not available.', 'spada-core' ) ),
					400
				);
			}
		}

		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation
		);

		if ( ! $cart_item_key ) {
			$message = wc_get_notices( 'error' );
			wc_clear_notices();

			wp_send_json_error(
				array(
					'message' => ! empty( $message[0]['notice'] )
						? wp_strip_all_tags( $message[0]['notice'] )
						: __( 'The product could not be added to the cart.', 'spada-core' ),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'cart_item_key' => $cart_item_key,
				'redirect'      => wc_get_checkout_url(),
			)
		);
	}
}
