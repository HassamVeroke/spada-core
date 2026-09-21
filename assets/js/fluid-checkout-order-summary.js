/**
 * SPADA Fluid Checkout Order Summary Interactions
 *
 * Handles quantity stepper, item removal, and inline coupon codes with WooCommerce AJAX sync.
 */

(function($) {
	'use strict';

	var SpadaOrderSummary = {
		qtyTimer: null,

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Quantity Stepper Click (supports custom buttons and any fallback spinner buttons)
			$(document).on('click', '.spada-qty-btn, .spada-qty-stepper .fc-number-spin-button, .spada-qty-stepper .number-spin-button', function(e) {
				e.preventDefault();
				self.handleQuantityChange($(this));
			});

			// Item Removal Click
			$(document).on('click', '.spada-remove-btn', function(e) {
				e.preventDefault();
				if (self.qtyTimer) {
					clearTimeout(self.qtyTimer);
				}
				self.handleRemoveItem($(this));
			});

			// Remove is-loading when WooCommerce finishes checkout fragment refresh
			$(document.body).on('updated_checkout checkout_error', function() {
				$('.spada-order-summary-table').removeClass('is-loading');
			});

			// Toggle Coupon Form
			$(document).on('click', '#spada-toggle-coupon-btn', function(e) {
				e.preventDefault();
				var $form = $('#spada-inline-coupon-form');
				$form.toggleClass('is-hidden');
				if (!$form.hasClass('is-hidden')) {
					$('#spada_coupon_code').focus();
				}
			});

			// Apply Coupon Code
			$(document).on('click', '#spada_apply_coupon_btn', function(e) {
				e.preventDefault();
				self.handleApplyCoupon();
			});

			$(document).on('keypress', '#spada_coupon_code', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.handleApplyCoupon();
				}
			});
		},

		handleQuantityChange: function($btn) {
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

			// Debounce AJAX request slightly so quick clicks update instantly
			if (self.qtyTimer) {
				clearTimeout(self.qtyTimer);
			}

			self.qtyTimer = setTimeout(function() {
				self.updateQuantityAjax(cartKey, newVal);
			}, 300);
		},

		updateQuantityAjax: function(cartKey, qty) {
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
				success: function(response) {
					// Refresh WooCommerce checkout fragments
					$(document.body).trigger('update_checkout');
				},
				error: function() {
					$table.removeClass('is-loading');
				}
			});
		},

		handleRemoveItem: function($btn) {
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
				success: function(response) {
					// Refresh WooCommerce checkout fragments
					$(document.body).trigger('update_checkout');
				},
				error: function() {
					$table.removeClass('is-loading');
				}
			});
		},

		handleApplyCoupon: function() {
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
				success: function(response) {
					if (response.success) {
						$msg.text(response.data.message).removeClass('is-hidden is-error').addClass('is-success');
						$input.val('');
						$(document.body).trigger('update_checkout');
					} else {
						$table.removeClass('is-loading');
						$msg.text(response.data.message).removeClass('is-hidden is-success').addClass('is-error');
					}
				},
				error: function() {
					$table.removeClass('is-loading');
				}
			});
		}
	};

	$(document).ready(function() {
		SpadaOrderSummary.init();
	});

})(jQuery);
