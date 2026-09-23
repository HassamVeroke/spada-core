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
		$button.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
		$textEl.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
		$textEl.text(text);
		$textEl.removeData('spada-original-html');
	}

	function setButtonHtml($button, html) {
		if (html && typeof html === 'string') {
			html = html.replace(/margin\s*:\s*0\s*!important\s*;?/gi, '');
		}
		var $textEl = getButtonTextElement($button);
		$button.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
		$textEl.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
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
	 * Format price for variations matching WooCommerce standard price HTML.
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

		var symbolHtml = symbol ? '<span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' : '';
		var pos = settings.position || 'right_space';

		var priceInner;
		if (!symbolHtml) {
			priceInner = formattedNumber;
		} else if (pos === 'left') {
			priceInner = symbolHtml + formattedNumber;
		} else if (pos === 'left_space') {
			priceInner = symbolHtml + '&nbsp;' + formattedNumber;
		} else if (pos === 'right') {
			priceInner = formattedNumber + symbolHtml;
		} else {
			// 'right_space' or default
			priceInner = formattedNumber + '&nbsp;' + symbolHtml;
		}

		return '<span class="woocommerce-Price-amount amount">' + priceInner + '</span>';
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
		var prefix = labelParts[0] || '';
		var suffix = labelParts[1] || '';

		var arrowSvg = '<span class="spada-change-option-arrow" role="button" tabindex="0" title="' + (SpadaBuyNow.strings.selectOptions || 'Change option') + '" aria-label="' + (SpadaBuyNow.strings.selectOptions || 'Change option') + '">' +
			'<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
			'<path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
			'</svg>' +
			'</span>';

		var label = prefix + formattedPrice + suffix + ' ' + arrowSvg;

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

		// Flag the form as initializing during setup so automatic found_variation won't close dropdown
		$form.data('spada-initializing', true);

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

		// Clear initializing flag after WooCommerce initialization completes
		setTimeout(function () {
			$form.data('spada-initializing', false);
		}, 250);
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
			var $text = getButtonTextElement($button);

			// Mark button, wrapper and text element with no-translation attributes so TranslatePress won't intercept or delay
			$wrapper.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
			$button.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');
			$text.attr('data-no-translation', 'true').attr('data-no-dynamic-translation', 'true').addClass('notranslate trp-no-translation');

			var isOutOfStock = $product.hasClass('outofstock');
			var isVariable = $product.hasClass('product-type-variable');

			// Save original text if not already saved
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

		// If clicked specifically on the change option arrow badge, open variation dropdown
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
	 * Keyboard accessibility for change option arrow.
	 */
	$(document).on('keydown', '.spada-change-option-arrow', function (event) {
		if (event.key === 'Enter' || event.key === ' ' || event.which === 13 || event.which === 32) {
			event.preventDefault();
			event.stopPropagation();
			$(this).trigger('click');
		}
	});

	/**
	 * When user selects an option in the variations select:
	 * Instantly update button to Buy Now for {price} and close dropdown.
	 */
	$(document).on('change', '.spada-buy-now-variation .variations select', function () {
		var $select = $(this);
		var $form = $select.closest('.variations_form');
		var $container = $form.closest('.spada-buy-now-variation');
		var $wrapper = $container.closest('.spada-buy-now');
		var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;

		var selectedVal = $select.val();
		if (!selectedVal) {
			$button.removeData('selected-variation-id');
			$button.removeData('selected-variation-data');
			setVariableSelectOptionsText($button);
			$button.attr('data-spada-variable-state', 'select');
			return;
		}

		// Match variation directly from product_variations data
		var variations = $form.data('product_variations');
		if (variations && variations.length) {
			var chosenAttr = $select.attr('name');
			for (var i = 0; i < variations.length; i++) {
				var v = variations[i];
				if (v.attributes && (v.attributes[chosenAttr] === selectedVal || v.attributes[chosenAttr] === '')) {
					if (v.is_in_stock && v.is_purchasable) {
						$button.data('selected-variation-id', v.variation_id);
						$button.data('selected-variation-data', v);
						setVariableBuyNowText($button, v);
						$button.attr('data-spada-variable-state', 'ready');

						// Close dropdown IMMEDIATELY without waiting for blur/click-away
						$container.removeClass('is-open');
						$wrapper.removeClass('is-dropdown-open');
						$select.trigger('blur');
						return;
					}
				}
			}
		}
	});

	/**
	 * When user selects a variation in the dropdown:
	 * Dropdown closes, Buy Now button shows up with variation price.
	 */
	$(document).on('found_variation', '.spada-buy-now-variation .variations_form', function (event, variation) {
		var $form = $(this);
		if ($form.data('spada-initializing')) {
			return;
		}

		var $container = $form.closest('.spada-buy-now-variation');
		var $wrapper = $container.closest('.spada-buy-now');
		var $button = $wrapper.find('.elementor-button').length ? $wrapper.find('.elementor-button') : $wrapper;

		if (variation && variation.is_in_stock && variation.variation_is_visible && variation.is_purchasable) {
			$button.data('selected-variation-id', variation.variation_id);
			$button.data('selected-variation-data', variation);

			setVariableBuyNowText($button, variation);
			$button.attr('data-spada-variable-state', 'ready');

			// Close dropdown immediately without waiting for blur/click-away
			$container.removeClass('is-open');
			$wrapper.removeClass('is-dropdown-open');
			$form.find('select').trigger('blur');
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
