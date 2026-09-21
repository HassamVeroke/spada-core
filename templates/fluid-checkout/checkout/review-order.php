<?php

/**
 * SPADA Fluid Checkout Review Order Template
 *
 * Pixel-perfect implementation matching the Figma Order Summary screenshot.
 *
 * @package Spada
 */

defined('ABSPATH') || exit;
?>

<table class="shop_table woocommerce-checkout-review-order-table spada-order-summary-table <?php echo esc_attr(apply_filters('fc_pro_checkout_review_order_table_classes', '')); ?>">
	<thead class="screen-reader-text">
		<tr>
			<th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
			<th class="product-total"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action('woocommerce_review_order_before_cart_contents');

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :
			$_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
			$product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

			if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) :
				// Determine pack/quantity meta
				$pack_text = '';
				if (! empty($cart_item['variation'])) {
					$pack_text = implode(' ', array_values($cart_item['variation']));
				}
				if (empty($pack_text) && method_exists($_product, 'get_attribute')) {
					$pack_text = $_product->get_attribute('pack-size');
				}
				if (empty($pack_text) && $_product->is_type('variation')) {
					$var_attrs = $_product->get_variation_attributes();
					if (! empty($var_attrs)) {
						$pack_text = implode(' ', array_values($var_attrs));
					}
				}
		?>
				<tr class="<?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item cart-item spada-cart-item', $cart_item, $cart_item_key)); ?>" data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>" data-product_id="<?php echo esc_attr($product_id); ?>">
					<td colspan="2" class="spada-cart-item-cell" role="none">
						<div class="spada-cart-item-inner">
							<!-- Product Thumbnail -->
							<div class="spada-item-thumb">
								<?php
								$thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image('woocommerce_thumbnail'), $cart_item, $cart_item_key);
								echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>

							<!-- Product Details -->
							<div class="spada-item-details">
								<!-- Top Line: Title & Subtotal -->
								<div class="spada-item-header">
									<h4 class="spada-item-title">
										<?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)); ?>
									</h4>
									<span class="spada-item-subtotal">
										<?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
										?>
									</span>
								</div>

								<!-- Middle Line: Pack Size / Variation Meta -->
								<div class="spada-item-meta">
									<?php if (! empty($pack_text)) : ?>
										<span class="spada-pack-label"><?php echo esc_html(sprintf(__('Qty: %s', 'spada-core'), $pack_text)); ?></span>
									<?php else : ?>
										<?php echo wc_get_formatted_cart_item_data($cart_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
										?>
									<?php endif; ?>
								</div>

								<!-- Bottom Line: Unit Price & Stepper + Remove Button -->
								<div class="spada-item-footer">
									<div class="spada-item-unit-price">
										<?php echo WC()->cart->get_product_price($_product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
										?>
									</div>

									<div class="spada-item-actions">
										<!-- Stepper Pill -->
										<div class="spada-qty-stepper">
											<a href="#" role="button" class="spada-qty-btn is-minus" data-action="decrease" aria-label="<?php esc_attr_e('Decrease quantity', 'spada-core'); ?>" data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>">−</a>
											<input type="number"
												id="quantity_<?php echo esc_attr($cart_item_key); ?>"
												class="spada-qty-input fc-buttons-added buttons-added"
												name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]"
												value="<?php echo esc_attr($cart_item['quantity']); ?>"
												min="1"
												max="<?php echo esc_attr($_product->get_max_purchase_quantity() > 0 ? $_product->get_max_purchase_quantity() : ''); ?>"
												step="1"
												data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>"
												readonly />
											<a href="#" role="button" class="spada-qty-btn is-plus" data-action="increase" aria-label="<?php esc_attr_e('Increase quantity', 'spada-core'); ?>" data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>">+</a>
										</div>

										<!-- Red Remove Button -->
										<a href="<?php echo esc_url(wc_get_cart_remove_url($cart_item_key)); ?>"
											class="spada-remove-btn remove"
											data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>"
											data-product_id="<?php echo esc_attr($product_id); ?>"
											data-product_sku="<?php echo esc_attr($_product->get_sku()); ?>"
											aria-label="<?php esc_attr_e('Remove item', 'spada-core'); ?>"
											title="<?php esc_attr_e('Remove item', 'spada-core'); ?>">
											<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
												<polyline points="3 6 5 6 21 6"></polyline>
												<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												<line x1="10" y1="11" x2="10" y2="17"></line>
												<line x1="14" y1="11" x2="14" y2="17"></line>
											</svg>
										</a>
									</div>
								</div>
							</div>
						</div>
					</td>
				</tr>
		<?php
			endif;
		endforeach;

		do_action('woocommerce_review_order_after_cart_contents');
		?>

		<!-- Add More Products Row -->
		<tr class="spada-add-more-row">
			<td colspan="2" class="spada-add-more-cell">
				<?php
				$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
				?>
				<a href="<?php echo esc_url($shop_url); ?>" class="spada-add-more-btn" id="spada-add-more-link">
					<span class="spada-add-more-box" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<line x1="12" y1="5" x2="12" y2="19"></line>
							<line x1="5" y1="12" x2="19" y2="12"></line>
						</svg>
					</span>
					<span class="spada-add-more-label"><?php esc_html_e('Add More', 'spada-core'); ?></span>
				</a>
			</td>
		</tr>

		<!-- Thin Divider -->
		<tr class="spada-divider-row">
			<td colspan="2" class="spada-divider-cell">
				<hr class="spada-order-divider" />
			</td>
		</tr>
	</tbody>

	<tfoot>
		<?php
		// Calculate subtotal inclusive of tax
		$subtotal_incl_tax = 0.0;
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
					$item_price = floatval( $cart_item['data']->get_price() );
					$quantity   = intval( $cart_item['quantity'] );
					$subtotal_incl_tax += ( $item_price * $quantity );
				}
			}
		}

		if ( $subtotal_incl_tax <= 0 && WC()->cart ) {
			$subtotal_incl_tax = (float) WC()->cart->subtotal;
		}
		if ( $subtotal_incl_tax <= 0 && WC()->cart ) {
			$subtotal_incl_tax = (float) WC()->cart->get_cart_contents_total();
		}

		// Calculate VAT breakdown matching Saudi Arabia 15% standard
		$wc_tax = (float) WC()->cart->get_cart_contents_tax();
		if ( $wc_tax <= 0 ) {
			$wc_tax = (float) WC()->cart->get_total_tax();
		}

		if ( $wc_tax > 0 ) {
			$total_vat         = $wc_tax;
			$cart_total_excl   = (float) WC()->cart->get_cart_contents_total();
			$subtotal_incl_tax = $cart_total_excl + $total_vat;
		} else {
			// In Saudi Arabia, catalog prices are inclusive of 15% VAT
			// Total Price Excl VAT = Subtotal / 1.15
			// Total VAT = Subtotal - Total Price Excl VAT
			$cart_total_excl = round( $subtotal_incl_tax / 1.15, 2 );
			$total_vat       = round( $subtotal_incl_tax - $cart_total_excl, 2 );
		}

		$is_arabic       = ( get_locale() === 'ar' || ( function_exists( 'is_rtl' ) && is_rtl() ) );
		$excl_vat_label  = $is_arabic ? 'الإجمالي بدون الضريبة' : __( 'Total Price Excluding VAT', 'spada-core' );
		$vat_label       = $is_arabic ? 'إجمالي الضريبة (15٪)' : __( 'Total VAT (15%)', 'spada-core' );

		if ( wc_tax_enabled() && WC()->cart && WC()->cart->get_tax_totals() ) {
			$taxes = WC()->cart->get_tax_totals();
			$tax_obj = reset( $taxes );
			if ( $tax_obj && ! empty( $tax_obj->label ) ) {
				$vat_label = $tax_obj->label;
			}
		}
		?>

		<!-- 1. Total Price Excluding VAT -->
		<tr class="spada-summary-row spada-price-excl-vat">
			<th><?php echo esc_html( $excl_vat_label ); ?></th>
			<td><?php echo wc_price( $cart_total_excl ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>

		<!-- 2. Total VAT (15%) -->
		<tr class="spada-summary-row spada-vat-row">
			<th><?php echo esc_html( $vat_label ); ?></th>
			<td><?php echo wc_price( $total_vat ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>

		<!-- 3. Subtotal -->
		<tr class="spada-summary-row spada-subtotal-row">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php echo wc_price( $subtotal_incl_tax ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>

		<!-- 4. Shipping -->
		<tr class="spada-summary-row spada-shipping-row">
			<th><?php esc_html_e('Shipping', 'woocommerce'); ?></th>
			<td>
				<?php
				if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {
					$packages = WC()->shipping()->get_packages();
					$chosen_method = isset(WC()->session->chosen_shipping_methods[0]) ? WC()->session->chosen_shipping_methods[0] : '';
					$shipping_total = WC()->cart->get_shipping_total();

					if ($shipping_total > 0) {
						echo wc_price($shipping_total + WC()->cart->get_shipping_tax()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} elseif (! empty($chosen_method)) {
						echo esc_html__('Free', 'spada-core');
					} else {
						echo '&mdash;';
					}
				} else {
					echo '&mdash;';
				}
				?>
			</td>
		</tr>

		<!-- Applied Coupons -->
		<?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
			<tr class="spada-summary-row spada-discount-row coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
				<th>
					<?php wc_cart_totals_coupon_label($coupon); ?>
					<a href="<?php echo esc_url(add_query_arg('remove_coupon', rawurlencode($code), wc_get_checkout_url())); ?>" class="spada-remove-coupon-link" data-coupon="<?php echo esc_attr($code); ?>" title="<?php esc_attr_e('Remove coupon', 'spada-core'); ?>">&times;</a>
				</th>
				<td><?php wc_cart_totals_coupon_html($coupon); ?></td>
			</tr>
		<?php endforeach; ?>

		<!-- Fees (if any) -->
		<?php foreach (WC()->cart->get_fees() as $fee) : ?>
			<tr class="spada-summary-row spada-fee-row">
				<th><?php echo esc_html($fee->name); ?></th>
				<td><?php wc_cart_totals_fee_html($fee); ?></td>
			</tr>
		<?php endforeach; ?>

		<!-- 5. + Add coupon code -->
		<tr class="spada-coupon-toggle-row">
			<td colspan="2" class="spada-coupon-toggle-cell">
				<a href="#" role="button" class="spada-coupon-toggle-btn" id="spada-toggle-coupon-btn">
					<span class="spada-coupon-toggle-icon" aria-hidden="true">+</span>
					<span><?php esc_html_e('Add coupon code', 'spada-core'); ?></span>
				</a>
				<!-- Collapsible Coupon Form -->
				<div class="spada-coupon-form-wrap is-hidden" id="spada-inline-coupon-form">
					<div class="spada-coupon-input-group">
						<input type="text" name="spada_coupon_code" id="spada_coupon_code" class="spada-coupon-input" placeholder="<?php esc_attr_e('Coupon code', 'spada-core'); ?>" />
						<a href="#" role="button" class="spada-coupon-apply-btn" id="spada_apply_coupon_btn"><?php esc_html_e('Apply', 'spada-core'); ?></a>
					</div>
					<div class="spada-coupon-message is-hidden" id="spada-coupon-msg"></div>
				</div>
			</td>
		</tr>

		<!-- 6. Highlighted Bottom TOTAL Card -->
		<tr class="spada-order-total-card-row">
			<td colspan="2" class="spada-order-total-card-cell">
				<div class="spada-total-card">
					<span class="spada-total-card-label"><?php esc_html_e('TOTAL', 'spada-core'); ?></span>
					<span class="spada-total-card-amount"><?php wc_cart_totals_order_total_html(); ?></span>
				</div>
			</td>
		</tr>
	</tfoot>
</table>