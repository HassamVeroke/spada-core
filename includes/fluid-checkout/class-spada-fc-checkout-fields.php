<?php
/**
 * SPADA Fluid Checkout Fields & Email Sync Handler
 *
 * Handles shipping_email field registration, custom placeholder,
 * auto-filling for registered users, making billing_email optional,
 * and syncing shipping_email into billing_email for all WooCommerce order flows.
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spada_FC_Checkout_Fields {

	/**
	 * Init hooks.
	 */
	public static function init() {
		// 1. Make billing email optional in WooCommerce checkout validation (shipping_email is required)
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'make_email_fields_optional_section' ), 999 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'make_all_checkout_email_fields_optional' ), 999 );

		// 2. Register & customize shipping_email in shipping address form (REQUIRED)
		add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'customize_shipping_fields' ), 1000 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'customize_shipping_checkout_fields' ), 1000 );

		// 3. Prevent Fluid Checkout from collapsing optional email fields behind expansible links
		add_filter( 'fc_hide_optional_fields_skip_list', array( __CLASS__, 'skip_hide_email_fields' ), 20 );

		// 4. Auto-fill registered user profile (Name, Phone, Email, Address) on checkout page load
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'autofill_registered_user_checkout_fields' ), 20, 2 );
		add_filter( 'default_checkout_shipping_first_name', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_billing_first_name', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_shipping_last_name', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_billing_last_name', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_shipping_phone', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_billing_phone', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_shipping_email', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );
		add_filter( 'default_checkout_billing_email', array( __CLASS__, 'default_checkout_field_value' ), 20, 2 );

		// 5. Sync shipping_email to billing_email before validation & processing
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'sync_shipping_email_to_post_superglobal' ), 5 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'sync_shipping_email_to_billing_email_posted_data' ), 999 );

		// 6. Suppress billing_email required error since it syncs from required shipping_email
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'prevent_billing_email_required_validation_error' ), 999, 2 );

		// 7. Fluid Checkout PRO "same as shipping" sync filter
		add_filter( 'fc_billing_same_as_shipping_field_value', array( __CLASS__, 'fc_mirror_shipping_email_to_billing' ), 20, 4 );
		add_filter( 'fc_billing_same_shipping_fields_keys', array( __CLASS__, 'fc_add_billing_email_to_sync_keys' ), 20 );

		// 8. Save email into order and ensure all order notifications use this email
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_shipping_email_to_order' ), 20, 2 );

		// 9. Disable Contact step in Fluid Checkout
		add_action( 'fc_register_steps', array( __CLASS__, 'disable_contact_step' ), 100 );
		add_action( 'wp', array( __CLASS__, 'disable_contact_step' ), 20 );

		// 10. Make Phone and Zip code fields full width in billing address
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'customize_billing_fields' ), 1000 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'customize_billing_checkout_fields' ), 1000 );
	}

	/**
	 * Make billing email fields optional in single-section field arrays (billing).
	 * Shipping email is REQUIRED.
	 *
	 * @param array $fields Section fields.
	 * @return array Modified fields.
	 */
	public static function make_email_fields_optional_section( $fields ) {
		if ( is_array( $fields ) ) {
			foreach ( $fields as $key => &$field ) {
				if ( 'shipping_email' === $key ) {
					continue;
				}
				if ( false !== strpos( $key, 'email' ) || ( isset( $field['type'] ) && 'email' === $field['type'] ) ) {
					$field['required'] = false;
					if ( ! isset( $field['class'] ) || ! is_array( $field['class'] ) ) {
						$field['class'] = array();
					}
					if ( ! in_array( 'fc-skip-hide-optional-field', $field['class'], true ) ) {
						$field['class'][] = 'fc-skip-hide-optional-field';
					}
				}
			}
		}
		return $fields;
	}

	/**
	 * Make billing email fields optional across all checkout sections.
	 * Shipping email is REQUIRED.
	 *
	 * @param array $fields All checkout fields.
	 * @return array Modified fields.
	 */
	public static function make_all_checkout_email_fields_optional( $fields ) {
		if ( is_array( $fields ) ) {
			foreach ( $fields as $section => &$section_fields ) {
				if ( is_array( $section_fields ) ) {
					foreach ( $section_fields as $key => &$field ) {
						if ( 'shipping_email' === $key ) {
							continue;
						}
						if ( false !== strpos( $key, 'email' ) || ( isset( $field['type'] ) && 'email' === $field['type'] ) ) {
							$field['required'] = false;
							if ( ! isset( $field['class'] ) || ! is_array( $field['class'] ) ) {
								$field['class'] = array();
							}
							if ( ! in_array( 'fc-skip-hide-optional-field', $field['class'], true ) ) {
								$field['class'][] = 'fc-skip-hide-optional-field';
							}
						}
					}
				}
			}
		}
		return $fields;
	}

	/**
	 * Ensure email fields are not hidden behind "Add [field] (optional)" links by Fluid Checkout.
	 *
	 * @param array $skip_list Field keys to skip hiding.
	 * @return array Modified skip list.
	 */
	public static function skip_hide_email_fields( $skip_list ) {
		if ( is_array( $skip_list ) ) {
			if ( ! in_array( 'shipping_email', $skip_list, true ) ) {
				$skip_list[] = 'shipping_email';
			}
			if ( ! in_array( 'billing_email', $skip_list, true ) ) {
				$skip_list[] = 'billing_email';
			}
		}
		return $skip_list;
	}

	/**
	 * Prevent billing_email required error from halting checkout since it syncs from required shipping_email.
	 *
	 * @param array    $data Posted data.
	 * @param WP_Error $errors Validation errors object.
	 */
	public static function prevent_billing_email_required_validation_error( $data, $errors ) {
		if ( is_wp_error( $errors ) ) {
			$errors->remove( 'billing_email_required' );
		}
	}

	/**
	 * Get the localized placeholder for the email field.
	 *
	 * @return string Placeholder text.
	 */
	public static function get_email_placeholder() {
		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		return $is_arabic ? 'أدخل بريدك الإلكتروني' : 'Enter you email';
	}

	/**
	 * Add or customize shipping_email in shipping fields.
	 *
	 * @param array $fields Shipping fields.
	 * @return array Modified fields.
	 */
	public static function customize_shipping_fields( $fields ) {
		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$label     = $is_arabic ? 'البريد الإلكتروني بالشحن' : __( 'Shipping email', 'spada-core' );

		if ( isset( $fields['shipping_email'] ) ) {
			$fields['shipping_email']['placeholder'] = self::get_email_placeholder();
			$fields['shipping_email']['label']       = $label;
			$fields['shipping_email']['type']        = 'email';
			$fields['shipping_email']['required']    = true;
			$fields['shipping_email']['class']       = array( 'form-row-wide' );
		} else {
			$fields['shipping_email'] = array(
				'type'        => 'email',
				'label'       => $label,
				'placeholder' => self::get_email_placeholder(),
				'required'    => true,
				'class'       => array( 'form-row-wide' ),
				'clear'       => true,
				'priority'    => 25,
				'validate'    => array( 'email' ),
			);
		}

		return $fields;
	}

	/**
	 * Ensure shipping_email is present and configured in checkout fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array Modified fields.
	 */
	public static function customize_shipping_checkout_fields( $fields ) {
		$is_arabic = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$label     = $is_arabic ? 'البريد الإلكتروني بالشحن' : __( 'Shipping email', 'spada-core' );

		if ( isset( $fields['shipping']['shipping_email'] ) ) {
			$fields['shipping']['shipping_email']['placeholder'] = self::get_email_placeholder();
			$fields['shipping']['shipping_email']['label']       = $label;
			$fields['shipping']['shipping_email']['type']        = 'email';
			$fields['shipping']['shipping_email']['required']    = true;
			$fields['shipping']['shipping_email']['class']       = array( 'form-row-wide' );
		} else {
			$fields['shipping']['shipping_email'] = array(
				'type'        => 'email',
				'label'       => $label,
				'placeholder' => self::get_email_placeholder(),
				'required'    => true,
				'class'       => array( 'form-row-wide' ),
				'clear'       => true,
				'priority'    => 25,
				'validate'    => array( 'email' ),
			);
		}

		return $fields;
	}

	/**
	 * Get registered user profile details (Name, Phone, Email, Address).
	 *
	 * @return array User details array.
	 */
	public static function get_logged_in_user_profile() {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();
		if ( ! $user_id || ! $current_user ) {
			return array();
		}

		// 1. Name
		$first_name = ! empty( $current_user->first_name ) ? $current_user->first_name : '';
		$last_name  = ! empty( $current_user->last_name ) ? $current_user->last_name : '';

		if ( empty( $first_name ) && ! empty( $current_user->display_name ) ) {
			$parts      = explode( ' ', trim( $current_user->display_name ), 2 );
			$first_name = $parts[0];
			if ( empty( $last_name ) && isset( $parts[1] ) ) {
				$last_name = $parts[1];
			}
		}

		// 2. Phone
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user_id, 'shipping_phone', true );
		}

		// 3. Email
		$email         = '';
		$saved_billing = get_user_meta( $user_id, 'billing_email', true );
		if ( ! empty( $saved_billing ) && is_email( $saved_billing ) ) {
			$email = $saved_billing;
		}
		if ( empty( $email ) ) {
			$saved_shipping = get_user_meta( $user_id, 'shipping_email', true );
			if ( ! empty( $saved_shipping ) && is_email( $saved_shipping ) ) {
				$email = $saved_shipping;
			}
		}
		if ( empty( $email ) && ! empty( $current_user->user_email ) && is_email( $current_user->user_email ) ) {
			if ( strpos( $current_user->user_email, '@spada.local' ) === false ) {
				$email = $current_user->user_email;
			}
		}

		// 4. Address
		$address = get_user_meta( $user_id, 'shipping_address_1', true );
		if ( empty( $address ) ) {
			$address = get_user_meta( $user_id, 'billing_address_1', true );
		}

		return array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'phone'      => $phone,
			'email'      => $email,
			'address'    => $address,
		);
	}

	/**
	 * Get saved user email for autofilling.
	 *
	 * @return string User email or empty string.
	 */
	public static function get_logged_in_user_email() {
		$profile = self::get_logged_in_user_profile();
		return ! empty( $profile['email'] ) ? $profile['email'] : '';
	}

	/**
	 * Auto-fill checkout fields for registered users on page load.
	 * Allows full editing by the user at checkout.
	 *
	 * @param mixed  $value Field value.
	 * @param string $input Field input name.
	 * @return mixed Auto-filled value or original.
	 */
	public static function autofill_registered_user_checkout_fields( $value, $input ) {
		if ( ! empty( $value ) || ! is_user_logged_in() ) {
			return $value;
		}

		$profile = self::get_logged_in_user_profile();
		if ( empty( $profile ) ) {
			return $value;
		}

		switch ( $input ) {
			case 'shipping_first_name':
			case 'billing_first_name':
				return ! empty( $profile['first_name'] ) ? $profile['first_name'] : $value;

			case 'shipping_last_name':
			case 'billing_last_name':
				return ! empty( $profile['last_name'] ) ? $profile['last_name'] : $value;

			case 'shipping_phone':
			case 'billing_phone':
				return ! empty( $profile['phone'] ) ? $profile['phone'] : $value;

			case 'shipping_email':
			case 'billing_email':
				return ! empty( $profile['email'] ) ? $profile['email'] : $value;

			case 'shipping_address_1':
			case 'billing_address_1':
				return ! empty( $profile['address'] ) ? $profile['address'] : $value;
		}

		return $value;
	}

	/**
	 * Default checkout value filter for registered user fields.
	 *
	 * @param mixed  $value Field value.
	 * @param string $input Input key.
	 * @return mixed Value.
	 */
	public static function default_checkout_field_value( $value, $input ) {
		return self::autofill_registered_user_checkout_fields( $value, $input );
	}

	/**
	 * Pre-populate $_POST['billing_email'] from $_POST['shipping_email'] right before checkout validation.
	 */
	public static function sync_shipping_email_to_post_superglobal() {
		$shipping_email = '';
		if ( ! empty( $_POST['shipping_email'] ) ) {
			$shipping_email = sanitize_email( wp_unslash( $_POST['shipping_email'] ) );
		}

		if ( empty( $shipping_email ) && is_user_logged_in() ) {
			$shipping_email = self::get_logged_in_user_email();
		}

		if ( ! empty( $shipping_email ) ) {
			$_POST['billing_email'] = $shipping_email;
		}
	}

	/**
	 * Sync shipping_email value to billing_email in parsed checkout posted data.
	 *
	 * @param array $data Posted checkout data.
	 * @return array Modified data with billing_email set.
	 */
	public static function sync_shipping_email_to_billing_email_posted_data( $data ) {
		$shipping_email = '';
		if ( ! empty( $data['shipping_email'] ) ) {
			$shipping_email = sanitize_email( $data['shipping_email'] );
		} elseif ( ! empty( $_POST['shipping_email'] ) ) {
			$shipping_email = sanitize_email( wp_unslash( $_POST['shipping_email'] ) );
		}

		if ( empty( $shipping_email ) && is_user_logged_in() ) {
			$shipping_email = self::get_logged_in_user_email();
		}

		if ( ! empty( $shipping_email ) ) {
			$data['billing_email']  = $shipping_email;
			$data['shipping_email'] = $shipping_email;
		}

		return $data;
	}

	/**
	 * Fluid Checkout PRO filter to copy shipping_email into billing_email during single address checkout.
	 *
	 * @param mixed  $new_field_value Current new value.
	 * @param string $field_key Target field key.
	 * @param string $shipping_field_key Source shipping field key.
	 * @param array  $post_data Serialized post data array.
	 * @return mixed Modified value.
	 */
	public static function fc_mirror_shipping_email_to_billing( $new_field_value, $field_key, $shipping_field_key, $post_data ) {
		if ( 'billing_email' === $field_key ) {
			if ( ! empty( $post_data['shipping_email'] ) ) {
				return sanitize_email( $post_data['shipping_email'] );
			}
			$logged_email = self::get_logged_in_user_email();
			if ( ! empty( $logged_email ) ) {
				return $logged_email;
			}
		}
		return $new_field_value;
	}

	/**
	 * Ensure Fluid Checkout includes billing_email in the keys to copy from shipping.
	 *
	 * @param array $keys List of field keys.
	 * @return array Modified keys.
	 */
	public static function fc_add_billing_email_to_sync_keys( $keys ) {
		if ( is_array( $keys ) && ! in_array( 'billing_email', $keys, true ) ) {
			$keys[] = 'billing_email';
		}
		return $keys;
	}

	/**
	 * Save shipping email to order's billing_email and order meta during checkout order creation.
	 * This guarantees all subsequent WooCommerce transactional emails (processing, completed, invoices)
	 * and payment gateway communications are sent to this email address.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data Posted data.
	 */
	public static function save_shipping_email_to_order( $order, $data ) {
		$shipping_email = '';
		if ( ! empty( $data['shipping_email'] ) ) {
			$shipping_email = sanitize_email( $data['shipping_email'] );
		} elseif ( ! empty( $_POST['shipping_email'] ) ) {
			$shipping_email = sanitize_email( wp_unslash( $_POST['shipping_email'] ) );
		}

		if ( empty( $shipping_email ) && is_user_logged_in() ) {
			$shipping_email = self::get_logged_in_user_email();
		}

		if ( ! empty( $shipping_email ) ) {
			$order->set_billing_email( $shipping_email );
			$order->update_meta_data( '_shipping_email', $shipping_email );

			// Also persist to customer user profile if logged in
			if ( is_user_logged_in() ) {
				$user_id = get_current_user_id();
				if ( $user_id ) {
					update_user_meta( $user_id, 'billing_email', $shipping_email );
					update_user_meta( $user_id, 'shipping_email', $shipping_email );
				}
			}
		}
	}

	/**
	 * Disable the Contact step in Fluid Checkout so checkout starts directly with Shipping.
	 */
	public static function disable_contact_step() {
		// Only run on checkout page (never on cart, shop, product, or account pages)
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! class_exists( 'FluidCheckout_Steps' ) ) {
			return;
		}

		$steps = FluidCheckout_Steps::instance();
		$steps->unregister_checkout_substep( 'contact' );
		$steps->unregister_checkout_step( 'contact' );

		// Remove login link section before customer details if hooked
		remove_action( 'woocommerce_checkout_before_customer_details', array( $steps, 'output_substep_contact_login_link_section' ), 1 );
	}

	/**
	 * Remove the (optional) text label from the shipping email field.
	 *
	 * @param array  $args Form field args.
	 * @param string $key  Field key.
	 * @return array Modified field args.
	 */
	public static function remove_optional_label_from_shipping_email( $args, $key ) {
		if ( 'shipping_email' === $key ) {
			$args['optional_label'] = '';
		}
		return $args;
	}

	/**
	 * Make Phone and Postcode/Zip fields full width in billing fields.
	 *
	 * @param array $fields Billing fields.
	 * @return array Modified fields.
	 */
	public static function customize_billing_fields( $fields ) {
		if ( isset( $fields['billing_phone'] ) ) {
			if ( ! isset( $fields['billing_phone']['class'] ) || ! is_array( $fields['billing_phone']['class'] ) ) {
				$fields['billing_phone']['class'] = array();
			}
			$fields['billing_phone']['class'] = array_values( array_diff( $fields['billing_phone']['class'], array( 'form-row-first', 'form-row-last' ) ) );
			$fields['billing_phone']['class'][] = 'form-row-wide';
		}
		if ( isset( $fields['billing_postcode'] ) ) {
			if ( ! isset( $fields['billing_postcode']['class'] ) || ! is_array( $fields['billing_postcode']['class'] ) ) {
				$fields['billing_postcode']['class'] = array();
			}
			$fields['billing_postcode']['class'] = array_values( array_diff( $fields['billing_postcode']['class'], array( 'form-row-first', 'form-row-last' ) ) );
			$fields['billing_postcode']['class'][] = 'form-row-wide';
		}
		return $fields;
	}

	/**
	 * Make Phone and Postcode/Zip fields full width in billing checkout fields.
	 *
	 * @param array $fields All checkout fields.
	 * @return array Modified fields.
	 */
	public static function customize_billing_checkout_fields( $fields ) {
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			if ( ! isset( $fields['billing']['billing_phone']['class'] ) || ! is_array( $fields['billing']['billing_phone']['class'] ) ) {
				$fields['billing']['billing_phone']['class'] = array();
			}
			$fields['billing']['billing_phone']['class'] = array_values( array_diff( $fields['billing']['billing_phone']['class'], array( 'form-row-first', 'form-row-last' ) ) );
			$fields['billing']['billing_phone']['class'][] = 'form-row-wide';
		}
		if ( isset( $fields['billing']['billing_postcode'] ) ) {
			if ( ! isset( $fields['billing']['billing_postcode']['class'] ) || ! is_array( $fields['billing']['billing_postcode']['class'] ) ) {
				$fields['billing']['billing_postcode']['class'] = array();
			}
			$fields['billing']['billing_postcode']['class'] = array_values( array_diff( $fields['billing']['billing_postcode']['class'], array( 'form-row-first', 'form-row-last' ) ) );
			$fields['billing']['billing_postcode']['class'][] = 'form-row-wide';
		}
		return $fields;
	}
}
