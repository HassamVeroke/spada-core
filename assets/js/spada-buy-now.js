jQuery(function ($) {
	'use strict';

	/**
	 * Find Product ID from button or its product card.
	 */
	function findProductId($button) {
		var productId = $button.attr('data-product-id') || $button.data('product-id');
		if (productId) {
			return parseInt(productId, 10);
		}

		var $product = $button.closest('.product, [data-elementor-type="loop-item"]');
		if (!$product.length) {
			$product = $button.closest('[data-product_id], [data-product-id]');
		}

		if ($product.length) {
			productId = $product.attr('data-product_id') || $product.attr('data-product-id') || $product.data('product_id') || $product.data('product-id');
			if (productId) {
				return parseInt(productId, 10);
			}

			var classes = $product.attr('class') || '';
			var match = classes.match(/(?:^|\s)post-(\d+)(?:\s|$)/);
			if (match) {
				return parseInt(match[1], 10);
			}
		}

		var $ancestor = $button.parents('[data-elementor-post-id]').first();
		if ($ancestor.length) {
			productId = $ancestor.attr('data-elementor-post-id');
			if (productId) {
				return parseInt(productId, 10);
			}
		}

		return 0;
	}

	/**
	 * Get the text element inside the Elementor button.
	 */
	function getButtonTextElement($button) {
		var $text = $button.find('.elementor-button-text');
		return $text.length ? $text : $button;
	}

	function escapeHtml(value) {
		return $('<div>').text(value).html();
	}

	function setButtonText($button, text) {
		var $textEl = getButtonTextElement($button);
		$textEl.text(text);
		$textEl.removeData('spada-original-html');
	}

	function setButtonHtml($button, html) {
		if (html && typeof html === 'string') {
			html = html.replace(/margin\s*:\s*0\s*!important\s*;?/gi, '');
		}
		var $textEl = getButtonTextElement($button);
		$textEl.html(html);
		$textEl.removeData('spada-original-html');
		$textEl.find('img').each(function () {
			var style = $(this).attr('style');
			if (style && /margin\s*:\s*0\s*!important/i.test(style)) {
				$(this).attr('style', style.replace(/margin\s*:\s*0\s*!important\s*;?/gi, ''));
			}
		});
	}

	/**
	 * Replace button text with animated loader spinner (no text).
	 */
	function setButtonLoading($button, loading) {
		if (!$button || !$button.length) {
			return;
		}

		$button.toggleClass('spada-buy-now-loading', loading);
		$button.attr('aria-busy', loading ? 'true' : 'false');

		var $wrapper = $button.closest('.spada-buy-now');
		if ($wrapper.length) {
			$wrapper.toggleClass('spada-buy-now-loading', loading);
		}

		var $text = getButtonTextElement($button);
		if ($text.length) {
			if (loading) {
				if (!$text.data('spada-original-html')) {
					$text.data('spada-original-html', $text.html());
				}
				$text.html('<span class="spada-btn-loader" aria-hidden="true"></span>');
			} else if ($text.data('spada-original-html')) {
				$text.html($text.data('spada-original-html'));
				$text.removeData('spada-original-html');
			}
		}
	}

	/**
	 * Format price for variations.
	 */
	function formatVariationPrice(price) {
		price = parseFloat(String(price).replace(/[^0-9.-]/g, ''));
		if (isNaN(price)) {
			return '';
		}

		var settings = SpadaBuyNow.currency || {};
		var decimals = typeof settings.decimals !== 'undefined' ? parseInt(settings.decimals, 10) : 2;
		var decimalSeparator = settings.decimalSeparator || '.';
		var thousandSeparator = settings.thousandSeparator || ',';
		var symbol = settings.htmlSymbol || escapeHtml(settings.symbol || '');
		if (symbol && typeof symbol === 'string') {
			symbol = symbol.replace(/margin\s*:\s*0\s*!important\s*;?/gi, '');
		}

		var number = price.toFixed(decimals).split('.');
		number[0] = number[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
		var formattedNumber = number.join(decimalSeparator);

		return symbol ? symbol + ' ' + formattedNumber : formattedNumber;
	}

	/**
	 * Set Variable product button to initial state (shows single lowest variation price or Select Options).
	 */
	function setVariableSelectOptionsText($button) {
		var $text = getButtonTextElement($button);
		var singlePriceHtml = $text.data('spada-single-price-html');
		if (singlePriceHtml) {
			setButtonHtml($button, singlePriceHtml);
		} else {
			setButtonText($button, SpadaBuyNow.strings.selectOptions || 'Select Options');
		}
		$button.attr('data-spada-variable-state', 'select');
	}

	/**
	 * Set Variable product button to "Buy Now for [price]".
	 */
	function setVariableBuyNowText($button, variation) {
		if (!variation || typeof variation.display_price === 'undefined') {
			return;
		}

		var formattedPrice = formatVariationPrice(variation.display_price);
		if (!formattedPrice) {
			return;
		}

		var template = SpadaBuyNow.strings.buyNowFor || 'Buy Now for %s';
		var labelParts = template.split('%s');
		var label = escapeHtml(labelParts.shift()) + formattedPrice + escapeHtml(labelParts.join('%s'));

		// Append small arrow to allow changing variation
		label += ' <span class="spada-change-option-arrow" title="' + (SpadaBuyNow.strings.selectOptions || 'Change') + '" aria-label="Change option">▾</span>';

		setButtonHtml($button, label);
		$button.attr('data-spada-variable-state', 'ready');
	}

	/**
	 * Get or create the inline variation dropdown container INSIDE the .elementor-button-wrapper.
	 * Positioned absolute on top of the button.
	 */
	function getInlineContainer($wrapper) {
		var $btnWrapper = $wrapper.find('.elementor-button-wrapper');
		var $parent = $btnWrapper.length ? $btnWrapper : $wrapper;

		var $existing = $parent.find('.spada-buy-now-variation');
		if ($existing.length) {
			return $existing.first();
		}

		var $container = $('<div class="spada-buy-now-variation" aria-live="polite"></div>');
		$parent.append($container);
		return $container;
	}

	/**
	 * Prepare variation dropdown and open it cleanly without auto-closing.
	 */
	function prepareAndOpenDropdown($container, $wrapper, $button) {
		// Close any other open variation dropdowns on the page first
		$('.spada-buy-now-variation.is-open').not($container).each(function () {
			var $otherContainer = $(this);
			var $otherWrapper = $otherContainer.closest('.spada-buy-now');
			var $otherButton = $otherWrapper.find('.elementor-button').length ? $otherWrapper.find('.elementor-button') : $otherWrapper;
			var otherSelectedId = $otherButton.data('selected-variation-id');

			$otherContainer.removeClass('is-open');
			$otherWrapper.removeClass('is-dropdown-open');
			if (!otherSelectedId) {
				setVariableSelectOptionsText($otherButton);
				$otherButton.attr('data-spada-variable-state', 'select');
			}
		});

		var $form = $container.find('.variations_form');

		// Flag the form as NOT user-initiated during setup so automatic found_variation won't close dropdown
		$form.data('user-initiated', false);

		var $selects = $container.find('.variations select');
		var hasSelectedVariation = $button.data('selected-variation-id');
		if (!hasSelectedVariation) {
			$selects.each(function () {
				$(this).val('');
			});
		}

		initVariationForm($container, $wrapper, $button);

		// Now display the dropdown
		$container.addClass('is-open');
		$wrapper.addClass('is-dropdown-open');

		// Focus the first select field
		if ($selects.length) {
			$selects.first().trigger('focus');
		}
	}

	/**
	 * Open the variation dropdown positioned absolute on top of the button.
	 */
	function openVariationDropdown(productId, $wrapper, $button) {
		var $container = getInlineContainer($wrapper);

		// If form HTML is already cached on this wrapper
		var cachedHtml = $wrapper.data('variation-form-html');
		if (cachedHtml) {
			$container.html(cachedHtml);
			prepareAndOpenDropdown($container, $wrapper, $button);
			return;
		}

		setButtonLoading($button, true);

		$.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_variation_form',
				nonce: SpadaBuyNow.nonce,
				product_id: productId
			}
		}).done(function (response) {
			setButtonLoading($button, false);
			if (!response.success || !response.data.html) {
				alert(SpadaBuyNow.strings.error || 'Something went wrong. Please try again.');
				return;
			}

			$wrapper.data('variation-form-html', response.data.html);
			$container.html(response.data.html);
			prepareAndOpenDropdown($container, $wrapper, $button);
		}).fail(function () {
			setButtonLoading($button, false);
			alert(SpadaBuyNow.strings.error || 'Something went wrong. Please try again.');
		});
	}

	/**
	 * Initialize WooCommerce variation form inside the container.
	 */
	function initVariationForm($container, $wrapper, $button) {
		var $form = $container.find('.variations_form');
		if ($form.length) {
			$form.each(function () {
				var $this = $(this);
				$this.wc_variation_form();
				$this.trigger('check_variations');
			});
		}
	}

	/**
	 * Direct Buy Now for Simple Product.
	 */
	function addSimpleProduct(productId, $button) {
		setButtonLoading($button, true);

		$.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_add',
				nonce: SpadaBuyNow.nonce,
				product_id: productId,
				variation_id: 0,
				quantity: 1
			}
		}).done(function (response) {
			if (response.success && response.data.redirect) {
				window.location.href = response.data.redirect;
				return;
			}
			alert(response.data && response.data.message ? response.data.message : SpadaBuyNow.strings.error);
			setButtonLoading($button, false);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
				? xhr.responseJSON.data.message
				: SpadaBuyNow.strings.error;
			alert(message);
			setButtonLoading($button, false);
		});
	}

	/**
	 * Direct Buy Now for Variable Product (after variation selected).
	 */
	function submitVariableProduct(productId, variationId, variationData, $button) {
		if (!variationId) {
			setVariableSelectOptionsText($button);
			return;
		}

		var $wrapper = $button.closest('.spada-buy-now');
		var $container = $wrapper.find('.spada-buy-now-variation');
		var $form = $container.find('.variations_form');

		var variation = {};
		if ($form.length) {
			$form.find('select[name^="attribute_"], input[name^="attribute_"]').each(function () {
				var name = $(this).attr('name');
				var value = $(this).val();
				if (name && value) {
					variation[name] = value;
				}
			});
		} else if (variationData && variationData.attributes) {
			variation = variationData.attributes;
		}

		setButtonLoading($button, true);

		$.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_add',
				nonce: SpadaBuyNow.nonce,
				product_id: productId,
				variation_id: variationId,
				quantity: 1,
				variation: variation
			}
		}).done(function (response) {
			if (response.success && response.data.redirect) {
				window.location.href = response.data.redirect;
				return;
			}
			alert(response.data && response.data.message ? response.data.message : SpadaBuyNow.strings.error);
			setButtonLoading($button, false);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
				? xhr.responseJSON.data.message
				: SpadaBuyNow.strings.error;
			alert(message);
			setButtonLoading($button, false);
		});
	}

	/**
	 * Initialize buttons on page load without flashing or hiding.
	 */
	function initButtons() {
		$('.spada-buy-now').each(function () {
			var $wrapper = $(this);
			var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;
			var $product = $wrapper.closest('.product, [data-elementor-type="loop-item"]');

			var isOutOfStock = $product.hasClass('outofstock');
			var isVariable = $product.hasClass('product-type-variable');

			// Save original text if not already saved
			var $text = getButtonTextElement($button);
			if (!$text.data('spada-original-buy-text')) {
				$text.data('spada-original-buy-text', $text.text().trim());
			}

			// For all variable products (both in-stock and out-of-stock):
			// Ensure only first/lowest variation price is shown (no price range)
			if (isVariable) {
				var $firstPrice = $text.find('.woocommerce-Price-amount').first();
				if ($firstPrice.length) {
					if ($text.find('.woocommerce-Price-amount').length > 1 || $text.text().indexOf('–') !== -1 || $text.text().indexOf('-') !== -1 || $text.text().indexOf('through') !== -1) {
						var prefix = $text.clone().children().remove().end().text().trim();
						prefix = prefix.replace(/[-:–\s]+$/, '');
						if (!prefix) {
							prefix = SpadaBuyNow.strings.buyNowFor ? SpadaBuyNow.strings.buyNowFor.replace('%s', '').trim() : 'Buy Now for';
						}
						$text.empty().append(document.createTextNode(prefix + ' ')).append($firstPrice.clone());
					}
				}
				$text.data('spada-single-price-html', $text.html());
			}

			// Out of stock products: Keep Elementor Buy Now button, but disabled
			if (isOutOfStock) {
				$wrapper.addClass('spada-buy-now-disabled is-out-of-stock');
				$button
					.addClass('spada-buy-now-disabled')
					.prop('disabled', true)
					.attr('aria-disabled', 'true');
				return;
			}

			// In-stock Variable products: Enable button, initial state is 'select' so click opens dropdown
			if (isVariable) {
				setVariableSelectOptionsText($button);
				$button
					.removeClass('spada-buy-now-disabled')
					.prop('disabled', false)
					.attr('aria-disabled', 'false');
				return;
			}

			// In-stock Simple products: Keep Elementor Buy Now button as-is!
			$button
				.removeClass('spada-buy-now-disabled')
				.prop('disabled', false)
				.attr('aria-disabled', 'false');
		});
	}

	// Run initialization immediately on DOM ready
	initButtons();

	/**
	 * Handle Click on Buy Now button / Select Options button.
	 */
	$(document).on('click', '.spada-buy-now .elementor-button, .spada-buy-now', function (event) {
		var $target = $(this);
		var $wrapper = $target.closest('.spada-buy-now');
		var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;

		// If clicked inside the variation dropdown container, do not trigger button action
		if ($(event.target).closest('.spada-buy-now-variation').length) {
			return;
		}

		// If dropdown is currently open on this button, do nothing
		if ($wrapper.hasClass('is-dropdown-open')) {
			return;
		}

		// If clicked on the change option arrow specifically
		if ($(event.target).closest('.spada-change-option-arrow').length) {
			event.preventDefault();
			event.stopPropagation();
			var pId = findProductId($wrapper);
			if (pId) {
				openVariationDropdown(pId, $wrapper, $button);
			}
			return false;
		}

		// If button is disabled or product is out of stock, do nothing
		if ($button.hasClass('spada-buy-now-disabled') || $button.prop('disabled') || $wrapper.hasClass('is-out-of-stock') || $button.hasClass('spada-buy-now-loading')) {
			event.preventDefault();
			event.stopPropagation();
			return false;
		}

		var $product = $wrapper.closest('.product, [data-elementor-type="loop-item"]');
		var productId = findProductId($wrapper);
		if (!productId) {
			return;
		}

		var isVariable = $product.hasClass('product-type-variable');

		if (!isVariable) {
			// Simple product: direct checkout
			event.preventDefault();
			event.stopPropagation();
			addSimpleProduct(productId, $button);
			return false;
		}

		// Variable product:
		event.preventDefault();
		event.stopPropagation();

		var state = $button.attr('data-spada-variable-state') || 'select';

		if (state === 'ready') {
			var variationId = $button.data('selected-variation-id');
			var variationData = $button.data('selected-variation-data');
			if (variationId) {
				submitVariableProduct(productId, variationId, variationData, $button);
				return false;
			}
		}

		// State is 'select': open variation dropdown
		openVariationDropdown(productId, $wrapper, $button);
		return false;
	});

	/**
	 * Mark user interaction when user changes the dropdown select.
	 */
	$(document).on('change', '.spada-buy-now-variation .variations select', function () {
		var $select = $(this);
		var $form = $select.closest('.variations_form');
		if ($select.val()) {
			$form.data('user-initiated', true);
		} else {
			$form.data('user-initiated', false);
		}
	});

	/**
	 * When user selects a variation in the dropdown:
	 * Dropdown closes, Buy Now button shows up with variation price.
	 */
	$(document).on('found_variation', '.spada-buy-now-variation .variations_form', function (event, variation) {
		var $form = $(this);
		var $container = $form.closest('.spada-buy-now-variation');
		var $wrapper = $container.closest('.spada-buy-now');
		var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;

		if (variation && variation.is_in_stock && variation.variation_is_visible && variation.is_purchasable) {
			$button.data('selected-variation-id', variation.variation_id);
			$button.data('selected-variation-data', variation);

			// ONLY close dropdown and transition to Buy Now button if user actively made the selection!
			if ($form.data('user-initiated')) {
				$form.data('user-initiated', false);
				setVariableBuyNowText($button, variation);
				$button.attr('data-spada-variable-state', 'ready');

				// Close dropdown
				$container.removeClass('is-open');
				$wrapper.removeClass('is-dropdown-open');
			}
		}
	});

	/**
	 * When variation is reset or hidden:
	 * If user resets variation selection back to empty.
	 */
	$(document).on('hide_variation', '.spada-buy-now-variation .variations_form', function () {
		var $form = $(this);
		var $container = $form.closest('.spada-buy-now-variation');
		var $wrapper = $container.closest('.spada-buy-now');
		var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;

		// Clear selected variation if explicitly deselected
		var currentVal = $form.find('select').first().val();
		if (!currentVal) {
			$button.removeData('selected-variation-id');
			$button.removeData('selected-variation-data');
			setVariableSelectOptionsText($button);
			$button.attr('data-spada-variable-state', 'select');
		}
	});

	/**
	 * Click anywhere outside dropdown without choosing a variation:
	 * Dropdown should be hidden and choose option button should be shown.
	 */
	$(document).on('click', function (event) {
		if (!$(event.target).closest('.spada-buy-now, .spada-buy-now-variation').length) {
			$('.spada-buy-now-variation.is-open').each(function () {
				var $container = $(this);
				var $wrapper = $container.closest('.spada-buy-now');
				var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;
				var selectedVariationId = $button.data('selected-variation-id');

				// Hide the dropdown
				$container.removeClass('is-open');
				$wrapper.removeClass('is-dropdown-open');

				// If no variation chosen, show Choose Option button
				if (!selectedVariationId) {
					setVariableSelectOptionsText($button);
					$button.attr('data-spada-variable-state', 'select');
				}
			});
		}
	});
});
