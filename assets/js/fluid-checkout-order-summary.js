/**
 * SPADA Fluid Checkout Order Summary Interactions
 *
 * Handles quantity stepper, item removal, and inline coupon codes with WooCommerce AJAX sync.
 */

(function ($) {
	'use strict';

	var SpadaOrderSummary = {
		qtyTimer: null,

		init: function () {
			this.bindEvents();
			this.initHeroTitleSync();
			this.initEmailSync();
		},

		bindEvents: function () {
			var self = this;

			// Quantity Stepper Click (supports custom buttons and any fallback spinner buttons)
			$(document).on('click', '.spada-qty-btn, .spada-qty-stepper .fc-number-spin-button, .spada-qty-stepper .number-spin-button', function (e) {
				e.preventDefault();
				self.handleQuantityChange($(this));
			});

			// Item Removal Click
			$(document).on('click', '.spada-remove-btn', function (e) {
				e.preventDefault();
				if (self.qtyTimer) {
					clearTimeout(self.qtyTimer);
				}
				self.handleRemoveItem($(this));
			});

			// Remove is-loading when WooCommerce finishes checkout fragment refresh
			$(document.body).on('updated_checkout checkout_error', function () {
				$('.spada-order-summary-table').removeClass('is-loading');
			});

			// Toggle Coupon Form
			$(document).on('click', '#spada-toggle-coupon-btn', function (e) {
				e.preventDefault();
				var $form = $('#spada-inline-coupon-form');
				$form.toggleClass('is-hidden');
				if (!$form.hasClass('is-hidden')) {
					$('#spada_coupon_code').focus();
				}
			});

			// Apply Coupon Code
			$(document).on('click', '#spada_apply_coupon_btn', function (e) {
				e.preventDefault();
				self.handleApplyCoupon();
			});

			$(document).on('keypress', '#spada_coupon_code', function (e) {
				if (e.which === 13) {
					e.preventDefault();
					self.handleApplyCoupon();
				}
			});
		},

		handleQuantityChange: function ($btn) {
			var self = this;
			var $stepper = $btn.closest('.spada-qty-stepper');
			var $input = $stepper.find('.spada-qty-input');
			if (!$input.length) {
				$input = $btn.siblings('.spada-qty-input');
			}

			var cartKey = $btn.data('cart_item_key') || $input.data('cart_item_key') || $btn.closest('.spada-cart-item').data('cart_item_key');
			if (!cartKey) {
				return;
			}

			var action = $btn.data('action');
			if (!action) {
				if ($btn.hasClass('is-minus') || $btn.hasClass('fc-minus') || $btn.hasClass('minus') || $btn.data('number-spinner-button') === 'minus') {
					action = 'decrease';
				} else if ($btn.hasClass('is-plus') || $btn.hasClass('fc-plus') || $btn.hasClass('plus') || $btn.data('number-spinner-button') === 'plus') {
					action = 'increase';
				}
			}

			var currentVal = parseInt($input.val(), 10) || 1;
			var minVal = parseInt($input.attr('min'), 10) || 1;
			var maxVal = parseInt($input.attr('max'), 10);
			var newVal = currentVal;

			if (action === 'decrease') {
				if (currentVal > minVal) {
					newVal = currentVal - 1;
				} else {
					return;
				}
			} else if (action === 'increase') {
				if (!maxVal || currentVal < maxVal) {
					newVal = currentVal + 1;
				} else {
					return;
				}
			}

			$input.val(newVal);

			// Immediately update the Qty: label in the item meta
			var $item = $btn.closest('.spada-cart-item');
			var $qtyLabel = $item.find('.spada-item-qty, .spada-pack-label');
			if ($qtyLabel.length) {
				var isRtl = (window.SpadaFCOrderSummary && SpadaFCOrderSummary.isRtl);
				$qtyLabel.text((isRtl ? 'الكمية: ' : 'Qty: ') + newVal);
			}

			// Debounce AJAX request slightly so quick clicks update instantly
			if (self.qtyTimer) {
				clearTimeout(self.qtyTimer);
			}

			self.qtyTimer = setTimeout(function () {
				self.updateQuantityAjax(cartKey, newVal);
			}, 300);
		},

		updateQuantityAjax: function (cartKey, qty) {
			var $table = $('.spada-order-summary-table');
			$table.addClass('is-loading');

			$.ajax({
				url: SpadaFCOrderSummary.ajaxUrl,
				type: 'POST',
				data: {
					action: 'spada_fc_update_cart_qty',
					security: SpadaFCOrderSummary.nonce,
					cart_item_key: cartKey,
					quantity: qty
				},
				success: function (response) {
					// Refresh WooCommerce checkout fragments
					$(document.body).trigger('update_checkout');
				},
				error: function () {
					$table.removeClass('is-loading');
				}
			});
		},

		handleRemoveItem: function ($btn) {
			var cartKey = $btn.data('cart_item_key');
			var $table = $('.spada-order-summary-table');
			$table.addClass('is-loading');

			$.ajax({
				url: SpadaFCOrderSummary.ajaxUrl,
				type: 'POST',
				data: {
					action: 'spada_fc_remove_cart_item',
					security: SpadaFCOrderSummary.nonce,
					cart_item_key: cartKey
				},
				success: function (response) {
					// Refresh WooCommerce checkout fragments
					$(document.body).trigger('update_checkout');
				},
				error: function () {
					$table.removeClass('is-loading');
				}
			});
		},

		handleApplyCoupon: function () {
			var $input = $('#spada_coupon_code');
			var code = $.trim($input.val());
			var $msg = $('#spada-coupon-msg');

			if (!code) {
				$msg.text(SpadaFCOrderSummary.couponEmpty).removeClass('is-hidden is-success').addClass('is-error');
				return;
			}

			var $table = $('.spada-order-summary-table');
			$table.addClass('is-loading');

			$.ajax({
				url: SpadaFCOrderSummary.ajaxUrl,
				type: 'POST',
				data: {
					action: 'spada_fc_apply_coupon',
					security: SpadaFCOrderSummary.nonce,
					coupon_code: code
				},
				success: function (response) {
					if (response.success) {
						$msg.text(response.data.message).removeClass('is-hidden is-error').addClass('is-success');
						$input.val('');
						$(document.body).trigger('update_checkout');
					} else {
						$table.removeClass('is-loading');
						$msg.text(response.data.message).removeClass('is-hidden is-success').addClass('is-error');
					}
				},
				error: function () {
					$table.removeClass('is-loading');
				}
			});
		},

		initEmailSync: function () {
			var self = this;
			var placeholderText = (window.SpadaFCOrderSummary && SpadaFCOrderSummary.isRtl) ? 'أدخل بريدك الإلكتروني' : 'Enter you email';
			var savedUserEmail = (window.SpadaFCOrderSummary && SpadaFCOrderSummary.userEmail) ? SpadaFCOrderSummary.userEmail : '';

			var syncEmails = function () {
				var $shippingEmail = $('input[name="shipping_email"]');
				var $billingEmail = $('input[name="billing_email"]');

				// 1. Ensure placeholder on shipping_email
				if ($shippingEmail.length) {
					var currentPlaceholder = $shippingEmail.attr('placeholder');
					if (!currentPlaceholder || currentPlaceholder !== placeholderText) {
						$shippingEmail.attr('placeholder', placeholderText);
					}
				}

				// 2. Auto-fill saved email for logged-in users if field is currently empty
				if (savedUserEmail) {
					if ($shippingEmail.length && !$shippingEmail.val()) {
						$shippingEmail.val(savedUserEmail).trigger('input').trigger('change');
					}
					if ($billingEmail.length && !$billingEmail.val()) {
						$billingEmail.val(savedUserEmail).trigger('input').trigger('change');
					}
				}

				// 3. Make shipping_email, billing_email and any email fields optional in DOM
				var $allEmailFields = $('input[name="shipping_email"], input[name="billing_email"], input[type="email"]');
				$allEmailFields.each(function () {
					var $input = $(this);
					$input.prop('required', false).removeAttr('required');
					var $row = $input.closest('.form-row, .fc-substep__field, .fc-field');
					$row.removeClass('validate-required is-required').addClass('validate-optional is-optional');
					$row.find('label .required, label .fc-field__required-mark').remove();
				});

				// 4. Ensure billing_email input exists in checkout form so POST always carries it
				var currentShippingVal = $shippingEmail.length ? $.trim($shippingEmail.val()) : '';
				if (currentShippingVal) {
					if ($billingEmail.length) {
						if ($billingEmail.val() !== currentShippingVal) {
							$billingEmail.val(currentShippingVal);
						}
					} else {
						// If billing_email input does not exist in DOM (e.g. single-step shipping only), create hidden field
						var $checkoutForm = $('form.checkout');
						if ($checkoutForm.length && !$('#spada_synced_billing_email').length) {
							$checkoutForm.append('<input type="hidden" id="spada_synced_billing_email" name="billing_email" value="' + currentShippingVal + '" />');
						} else if ($('#spada_synced_billing_email').length) {
							$('#spada_synced_billing_email').val(currentShippingVal);
						}
					}
				}
			};

			// Run sync on load and checkout lifecycle events
			syncEmails();
			$(window).on('load', syncEmails);
			$(document.body).on('updated_checkout init_checkout checkout_error fc_step_loaded fc_substep_loaded', syncEmails);

			// Real-time synchronization when user types or changes shipping email
			$(document).on('input change blur', 'input[name="shipping_email"]', function () {
				var val = $.trim($(this).val());
				var $billingEmail = $('input[name="billing_email"]');
				if ($billingEmail.length) {
					$billingEmail.val(val);
				}
				var $hiddenBilling = $('#spada_synced_billing_email');
				if ($hiddenBilling.length) {
					$hiddenBilling.val(val);
				} else if (!$billingEmail.length) {
					var $checkoutForm = $('form.checkout');
					if ($checkoutForm.length) {
						$checkoutForm.append('<input type="hidden" id="spada_synced_billing_email" name="billing_email" value="' + val + '" />');
					}
				}
			});

			// If billing email is changed directly, sync back to shipping email
			$(document).on('input change blur', 'input[name="billing_email"]:not(#spada_synced_billing_email)', function () {
				var val = $.trim($(this).val());
				var $shippingEmail = $('input[name="shipping_email"]');
				if ($shippingEmail.length && val && !$shippingEmail.val()) {
					$shippingEmail.val(val);
				}
			});
		}
	};

	$(document).ready(function () {
		SpadaOrderSummary.init();
	});

})(jQuery);
