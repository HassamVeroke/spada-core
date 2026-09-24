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
			this.initEmailSync();
			this.initUserProfileAutofill();
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
				// Ensure no Undo/Dismiss elements or messages linger
				$('.restore-item, .restore-item-dismiss, .cart_item.removed.undo, [id$="_restore_button"]').remove();
				// Suppress native WooCommerce/Fluid Checkout coupon applied confirmation notice or toast
				$('.woocommerce-message, .fc-coupon-code-messages, .fc-toast').filter(function () {
					var text = $(this).text().toLowerCase();
					return text.indexOf('applied successfully') !== -1 ||
						text.indexOf('تم تطبيق') !== -1 ||
						text.indexOf('promotional code') !== -1 ||
						text.indexOf('coupon code') !== -1 ||
						text.indexOf('promo code') !== -1;
				}).remove();
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

			// Coupon removal click loading
			$(document).on('click', '.woocommerce-remove-coupon', function () {
				$('.spada-order-summary-table').addClass('is-loading');
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
			var $row = $btn.closest('.spada-cart-item');

			// Immediately animate out the row for clean, instant deletion
			$row.css('opacity', '0.4').slideUp(200, function () {
				$(this).remove();
			});
			$table.addClass('is-loading');

			// Clear notice wrappers
			$('.woocommerce-notices-wrapper, .woocommerce-NoticeGroup-checkout').empty();

			$.ajax({
				url: SpadaFCOrderSummary.ajaxUrl,
				type: 'POST',
				data: {
					action: 'spada_fc_remove_cart_item',
					security: SpadaFCOrderSummary.nonce,
					cart_item_key: cartKey
				},
				success: function (response) {
					// Clear any notices returned
					$('.woocommerce-notices-wrapper, .woocommerce-NoticeGroup-checkout').empty();
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
						// Immediately clean any native confirmation notice/toast
						$('.woocommerce-message, .fc-coupon-code-messages, .fc-toast').filter(function () {
							var text = $(this).text().toLowerCase();
							return text.indexOf('applied successfully') !== -1 ||
								text.indexOf('تم تطبيق') !== -1 ||
								text.indexOf('promotional code') !== -1 ||
								text.indexOf('coupon code') !== -1 ||
								text.indexOf('promo code') !== -1;
						}).remove();
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

				// 3. Make billing_email optional in DOM so browser validation doesn't block submission
				var $billingEmailFields = $('input[name="billing_email"]');
				$billingEmailFields.each(function () {
					var $input = $(this);
					$input.prop('required', false).removeAttr('required');
					var $row = $input.closest('.form-row, .fc-substep__field, .fc-field');
					$row.removeClass('validate-required is-required').addClass('validate-optional is-optional');
					$row.find('label .required, label .fc-field__required-mark').remove();
				});

				// 4. Ensure shipping_email remains strictly required with its required asterisk in label
				if ($shippingEmail.length) {
					$shippingEmail.prop('required', true).attr('required', 'required');
					var $shippingRow = $shippingEmail.closest('.form-row, .fc-substep__field, .fc-field');
					$shippingRow.removeClass('validate-optional is-optional').addClass('validate-required is-required');
					var $shippingLabel = $shippingRow.find('label');
					if ($shippingLabel.length) {
						$shippingLabel.find('.optional').remove();
						if (!$shippingLabel.find('.required, .fc-field__required-mark').length) {
							var isRtl = (window.SpadaFCOrderSummary && SpadaFCOrderSummary.isRtl);
							$shippingLabel.append('&nbsp;<abbr class="required" title="' + (isRtl ? 'مطلوب' : 'required') + '"><span class="fc-field__required-mark" aria-hidden="true">*</span></abbr>');
						}
					}
				}

				// 5. Ensure billing_email input exists in checkout form so POST always carries it
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
		},

		initUserProfileAutofill: function () {
			if (!window.SpadaFCOrderSummary || !SpadaFCOrderSummary.userProfile) {
				return;
			}
			var profile = SpadaFCOrderSummary.userProfile;

			var populateIfEmpty = function () {
				if (profile.first_name) {
					var $fn = $('input[name="shipping_first_name"], input[name="billing_first_name"]');
					$fn.each(function () {
						if (!$(this).val()) {
							$(this).val(profile.first_name);
						}
					});
				}
				if (profile.last_name) {
					var $ln = $('input[name="shipping_last_name"], input[name="billing_last_name"]');
					$ln.each(function () {
						if (!$(this).val()) {
							$(this).val(profile.last_name);
						}
					});
				}
				if (profile.phone) {
					var $ph = $('input[name="shipping_phone"], input[name="billing_phone"]');
					$ph.each(function () {
						if (!$(this).val()) {
							$(this).val(profile.phone);
						}
					});
				}
				if (profile.email) {
					var $em = $('input[name="shipping_email"], input[name="billing_email"]');
					$em.each(function () {
						if (!$(this).val()) {
							$(this).val(profile.email);
						}
					});
				}
				if (profile.address) {
					var cleanAddr = SpadaOrderSummary.cleanAddress(profile.address);
					var $addr = $('input[name="shipping_address_1"], input[name="billing_address_1"]');
					$addr.each(function () {
						if (!$(this).val()) {
							$(this).val(cleanAddr);
						}
					});
				}
			};

			populateIfEmpty();
			$(window).on('load', populateIfEmpty);
			$(document.body).on('updated_checkout init_checkout fc_step_loaded fc_substep_loaded', populateIfEmpty);

			// Real-time cleanup to prevent combined/duplicated addresses from Google Places or Map Location Picker
			$(document).on('change blur focusout updated_checkout', 'input[name="shipping_address_1"], input[name="billing_address_1"], #shipping_address_1, #billing_address_1', function () {
				var $el = $(this);
				var val = $el.val();
				if (val && val.length > 20) {
					var cleaned = SpadaOrderSummary.cleanAddress(val);
					if (cleaned && cleaned !== val) {
						$el.val(cleaned);
					}
				}
			});
		},

		cleanAddress: function (address) {
			if (!address || typeof address !== 'string') {
				return '';
			}

			address = address.replace(/\s+/g, ' ').trim();

			if (address.length < 20) {
				return address;
			}

			// 1. Direct exact duplicate check
			var half = Math.floor(address.length / 2);
			var left = address.substring(0, half).trim();
			var right = address.substring(half).trim();
			if (left === right) {
				return left;
			}

			// 2. Pattern A: 5-digit postal code followed by street/number
			var postalMatch = address.match(/^(.+?\b\d{5}\b)\s+((?:\d{1,5}\b\s+)?[A-Za-z\u0600-\u06FF].+)$/);
			if (postalMatch) {
				var seg1 = postalMatch[1].trim();
				var seg2 = postalMatch[2].trim();

				var words1 = seg1.toLowerCase().split(/[\s,]+/).filter(function (w) { return w.length >= 3; });
				var words2 = seg2.toLowerCase().split(/[\s,]+/).filter(function (w) { return w.length >= 3; });

				var shared = words1.filter(function (w) { return words2.indexOf(w) !== -1; });
				if (shared.length >= 2) {
					var hasCountry2 = /(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i.test(seg2);
					var hasCountry1 = /(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i.test(seg1);
					var commas1 = (seg1.match(/,/g) || []).length;
					var commas2 = (seg2.match(/,/g) || []).length;

					var best = (hasCountry2 || commas2 > commas1) ? seg2 : ((hasCountry1 || commas1 > commas2) ? seg1 : (seg2.length >= seg1.length ? seg2 : seg1));

					var m1 = seg1.match(/^(\d{3,5})\s+([A-Za-z\u0600-\u06FF].+)$/);
					var m2 = best.match(/^(\d{3,5})\s+([A-Za-z\u0600-\u06FF].+)$/);
					if (m1 && m2) {
						var bldg1 = m1[1];
						var bldg2 = m2[1];
						if (bldg1 !== bldg2 && new RegExp(',\\s*' + bldg2 + '\\s*,').test(best)) {
							best = best.replace(new RegExp('^' + bldg2 + '\\b'), bldg1);
						}
					}

					return best;
				}
			}

			// 3. Pattern B: Repeated street phrase of 2+ words
			var words = address.split(/\s+/);
			var numWords = words.length;
			if (numWords >= 6) {
				for (var windowLen = 4; windowLen >= 2; windowLen--) {
					for (var i = 0; i <= numWords - (windowLen * 2); i++) {
						var phrase = words.slice(i, i + windowLen).join(' ');
						var cleanPhrase = phrase.replace(/[^\w\s\u0600-\u06FF]/g, '');
						if (cleanPhrase.length < 6) continue;

						var cleanAddr = address.replace(/[^\w\s\u0600-\u06FF]/g, ' ');
						var firstPos = cleanAddr.toLowerCase().indexOf(cleanPhrase.toLowerCase());
						if (firstPos !== -1) {
							var secondPos = cleanAddr.toLowerCase().indexOf(cleanPhrase.toLowerCase(), firstPos + cleanPhrase.length);
							if (secondPos !== -1) {
								var prefix = address.substring(0, secondPos);
								var numM = prefix.match(/\s+(\d{1,5})\s+$/);
								var splitIdx = numM ? numM.index + numM[0].indexOf(numM[1]) : secondPos;

								var p1 = address.substring(0, splitIdx).trim();
								var p2 = address.substring(splitIdx).trim();

								if (p1 && p2) {
									var c1 = (p1.match(/,/g) || []).length;
									var c2 = (p2.match(/,/g) || []).length;
									var hasC2 = /(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i.test(p2);
									var hasC1 = /(?:Saudi Arabia|المملكة العربية السعودية|السعودية|KSA)$/i.test(p1);

									var chosen = (hasC2 || c2 > c1) ? p2 : ((hasC1 || c1 > c2) ? p1 : (p2.length >= p1.length ? p2 : p1));

									var m1_p = p1.match(/^(\d{3,5})\s+([A-Za-z\u0600-\u06FF].+)$/);
									var m2_c = chosen.match(/^(\d{3,5})\s+([A-Za-z\u0600-\u06FF].+)$/);
									if (m1_p && m2_c) {
										var b1 = m1_p[1];
										var b2 = m2_c[1];
										if (b1 !== b2 && new RegExp(',\\s*' + b2 + '\\s*,').test(chosen)) {
											chosen = chosen.replace(new RegExp('^' + b2 + '\\b'), b1);
										}
									}

									return chosen;
								}
							}
						}
					}
				}
			}

			return address;
		}
	};

	$(document).ready(function () {
		SpadaOrderSummary.init();
	});

})(jQuery);
