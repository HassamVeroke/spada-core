jQuery(function ($) {
	'use strict';

	var currentProductId = 0;

	$('.spada-buy-now').each(function () {
		var $button = $(this);
		var $text = getButtonTextElement($button);
		if (!$text.data('spada-original-buy-text')) {
			$text.data('spada-original-buy-text', $text.text().trim());
		}

		$button
			.addClass('spada-buy-now-disabled')
			.prop('disabled', true)
			.attr('aria-disabled', 'true')
			.attr('data-spada-ready', 'false')
			.attr('data-stock-checked', 'false');

		setButtonLoading($button, true, SpadaBuyNow.strings.loading);
	});

	function setButtonReady($button) {
		$button.attr('data-spada-ready', 'true');
	}

	function findProductId($button) {
		var $product = $button.closest('.product');
		var productId = $button.attr('data-product-id') || $button.data('product-id');

		if (productId) {
			return parseInt(productId, 10);
		}

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

	function getButtonTextElement($button) {
		var $text = $button.find('.elementor-button-text');
		return $text.length ? $text : $button;
	}

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

	function escapeHtml(value) {
		return $('<div>').text(value).html();
	}

	function setButtonText($button, text) {
		getButtonTextElement($button).text(text);
	}

	function setButtonHtml($button, html) {
		if (html && typeof html === 'string') {
			html = html.replace(/margin\s*:\s*0\s*!important\s*;?/gi, '');
		}
		var $textEl = getButtonTextElement($button);
		$textEl.html(html);
		$textEl.find('img').each(function() {
			var style = $(this).attr('style');
			if (style && /margin\s*:\s*0\s*!important/i.test(style)) {
				$(this).attr('style', style.replace(/margin\s*:\s*0\s*!important\s*;?/gi, ''));
			}
		});
	}

	function setVariableSelectOptionsText($button) {
		setButtonText($button, SpadaBuyNow.strings.selectOptions);
		$button.attr('data-spada-variable-state', 'select');
	}

	function setSimpleBuyNowText($button, productInfo) {
		if (!productInfo || !productInfo.price_html) {
			return;
		}

		var label = escapeHtml(SpadaBuyNow.strings.buyNowFor.replace('%s', '')) + productInfo.price_html;
		setButtonHtml($button, label);
	}

	function setVariableBuyNowText($button, variation) {
		if (!variation || typeof variation.display_price === 'undefined') {
			return;
		}

		var formattedPrice = formatVariationPrice(variation.display_price);
		if (!formattedPrice) {
			return;
		}

		var labelParts = (SpadaBuyNow.strings.buyNowFor || 'Buy Now for %s').split('%s');
		var label = escapeHtml(labelParts.shift()) + formattedPrice + escapeHtml(labelParts.join('%s'));
		setButtonHtml($button, label);
		$button.attr('data-spada-variable-state', 'ready');
	}

	function setButtonLoading($button, loading, loadingText) {
		if (!$button || !$button.length) {
			return;
		}

		$button.toggleClass('spada-buy-now-loading', loading);
		$button.attr('aria-busy', loading ? 'true' : 'false');
		$button.attr('aria-disabled', loading ? 'true' : ($button.hasClass('spada-buy-now-disabled') ? 'true' : 'false'));

		var $text = getButtonTextElement($button);
		if ($text.length) {
			if (loading) {
				if (!$text.data('spada-loading-html')) {
					$text.data('spada-loading-html', $text.html());
				}
				$text.html(escapeHtml(loadingText || SpadaBuyNow.strings.loading) + '<span class="spada-buy-now-inline-spinner" aria-hidden="true"></span>');
			} else if ($text.data('spada-loading-html')) {
				$text.html($text.data('spada-loading-html'));
				$text.removeData('spada-loading-html');
			}
		}
	}

	function setSelectorLoading($container, loading) {
		$container.toggleClass('spada-buy-now-selector-loading', loading);
		$container.attr('aria-busy', loading ? 'true' : 'false');
	}

	function focusVariationSelect($container, openPicker) {
		var $select = $container.find('.variations select').first();
		if (!$select.length) {
			return;
		}

		$select.trigger('focus');
		if (openPicker && $select[0] && typeof $select[0].showPicker === 'function') {
			try {
				$select[0].showPicker();
			} catch (error) {
			}
		}
	}

	function getInlineContainer($button) {
		var $existing = $button.siblings('.spada-buy-now-variation');
		if ($existing.length) {
			return $existing.first();
		}

		var $container = $('<div class="spada-buy-now-variation" aria-live="polite"></div>');
		$container.insertBefore($button);
		return $container;
	}

	function closeVariationSelector($button) {
		$button.siblings('.spada-buy-now-variation').remove();
	}

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
		}).fail(function () {
			alert(SpadaBuyNow.strings.error);
		}).always(function () {
			setButtonLoading($button, false);
		});
	}

	function loadVariationForm(productId, $button) {
		var $container = getInlineContainer($button);

		if ($container.data('loaded') === true) {
			$container.addClass('is-open');
			setButtonLoading($button, false);
			focusVariationSelect($container, true);
			return;
		}

		// Show the selector immediately so there is no empty/waiting gap.
		$container
			.addClass('is-open spada-buy-now-selector-loading')
			.html('<div class="spada-buy-now-selector-placeholder"><span class="spada-buy-now-spinner"></span><span>' + SpadaBuyNow.strings.loadingVariations + '</span></div>');
		setSelectorLoading($container, true);

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
			if (!response.success || !response.data.html) {
				$container.html('<div class="spada-buy-now-selector-error">' + SpadaBuyNow.strings.error + '</div>');
				setButtonLoading($button, false);
				return;
			}

			$container.html(response.data.html).data('loaded', true);

			var $form = $container.find('.variations_form');
			if ($form.length) {
				$form.each(function () {
					var $this = $(this);
					$this.wc_variation_form();
					$this.trigger('check_variations');
				});
			}

			// Let the browser paint the returned select before restoring the button text.
			window.requestAnimationFrame(function () {
				setButtonLoading($button, false);
				focusVariationSelect($container, true);
			});
		}).fail(function () {
			$container.html('<div class="spada-buy-now-selector-error">' + SpadaBuyNow.strings.error + '</div>');
			setButtonLoading($button, false);
		}).always(function () {
			setSelectorLoading($container, false);
		});
	}

	function checkButtonStock($button) {
		if ($button.attr('data-stock-checking') === 'true' || $button.attr('data-stock-checked') === 'true') {
			return;
		}

		var productId = findProductId($button);
		if (!productId) {
			setButtonLoading($button, false);
			setButtonText($button, SpadaBuyNow.strings.unavailable);
			setButtonReady($button);
			return;
		}

		$button.attr('data-stock-checking', 'true');

		$.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_product',
				nonce: SpadaBuyNow.nonce,
				product_id: productId
			}
		}).done(function (response) {
			$button.data('spada-product-info', response);
			$button.attr('data-stock-checking', 'false');
			$button.attr('data-stock-checked', 'true');

			if (!response.success) {
				setButtonLoading($button, false);
				setButtonText($button, SpadaBuyNow.strings.unavailable);
				setButtonReady($button);
				return;
			}

			if (!response.data.in_stock) {
				setButtonLoading($button, false);
				setButtonText($button, SpadaBuyNow.strings.outOfStock);
				$button.addClass('spada-buy-now-disabled').prop('disabled', true).attr('aria-disabled', 'true');
				setButtonReady($button);
				return;
			}

			setButtonLoading($button, false);

			if (response.data.type === 'variable') {
				// Variable products start with Select Options, not the parent price.
				setVariableSelectOptionsText($button);
			} else {
				setSimpleBuyNowText($button, response.data);
			}

			$button.removeClass('spada-buy-now-disabled').prop('disabled', false).attr('aria-disabled', 'false');
			setButtonReady($button);
		}).fail(function () {
			$button.attr('data-stock-checking', 'false');
			setButtonLoading($button, false);
			setButtonText($button, SpadaBuyNow.strings.unavailable);
			$button.addClass('spada-buy-now-disabled').prop('disabled', true).attr('aria-disabled', 'true');
			setButtonReady($button);
		});
	}

	if ('IntersectionObserver' in window) {
		var stockObserver = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					checkButtonStock($(entry.target));
					stockObserver.unobserve(entry.target);
				}
			});
		}, { rootMargin: '300px 0px' });

		$('.spada-buy-now').each(function () {
			stockObserver.observe(this);
		});
	} else {
		$('.spada-buy-now').each(function () {
			checkButtonStock($(this));
		});
	}

	$(document).on('click', '.spada-buy-now', function (event) {
		event.preventDefault();
		event.stopPropagation();

		var $button = $(this);
		if ($button.hasClass('spada-buy-now-disabled') || $button.prop('disabled') || $button.attr('data-stock-checking') === 'true') {
			return;
		}

		var productId = findProductId($button);
		if (!productId) {
			return;
		}

		if ($button.attr('data-stock-checked') !== 'true') {
			checkButtonStock($button);
			return;
		}

		currentProductId = productId;
		$button.trigger('blur');
		// If this is already an opened/loaded variation selector, the button is now the Buy Now action.
		var $container = $button.siblings('.spada-buy-now-variation');
		if ($container.length && $container.data('loaded') === true) {
			var $form = $container.find('.variations_form');
			var variationId = parseInt($form.find('input[name="variation_id"]').val(), 10) || 0;
			if (variationId) {
				submitVariableProduct($form, $button);
			}
			return;
		}

		var productInfo = $button.data('spada-product-info');
		var productInfoRequest = productInfo ? $.Deferred().resolve(productInfo).promise() : $.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_product',
				nonce: SpadaBuyNow.nonce,
				product_id: productId
			}
		});

		setButtonLoading($button, true, SpadaBuyNow.strings.loadingVariations);

		productInfoRequest.done(function (response) {
			if (!response.success) {
				setButtonLoading($button, false);
				alert(response.data && response.data.message ? response.data.message : SpadaBuyNow.strings.error);
				return;
			}

			if (!response.data.in_stock) {
				$button.addClass('spada-buy-now-disabled').prop('disabled', true).attr('aria-disabled', 'true');
				setButtonLoading($button, false);
				return;
			}

			if (response.data.type === 'simple') {
				setButtonLoading($button, false);
				setSimpleBuyNowText($button, response.data);
				addSimpleProduct(productId, $button);
				return;
			}

			if (response.data.type === 'variable') {
				setVariableSelectOptionsText($button);
				loadVariationForm(productId, $button);
			}
		}).fail(function () {
			setButtonLoading($button, false);
			alert(SpadaBuyNow.strings.error);
		});
	});

	function submitVariableProduct($form, $button) {
		var variationId = parseInt($form.find('input[name="variation_id"]').val(), 10) || 0;
		var quantity = parseFloat($form.find('input.qty').val()) || 1;
		var variation = {};

		if (!variationId) {
			setVariableSelectOptionsText($button);
			$form.find('.variations').addClass('spada-buy-now-validation-error');
			return;
		}

		$form.find('select[name^="attribute_"], input[name^="attribute_"]').each(function () {
			var name = $(this).attr('name');
			var value = $(this).val();
			if (name && value) {
				variation[name] = value;
			}
		});

		setButtonLoading($button, true, SpadaBuyNow.strings.loading);

		$.ajax({
			url: SpadaBuyNow.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'spada_buy_now_add',
				nonce: SpadaBuyNow.nonce,
				product_id: currentProductId,
				variation_id: variationId,
				quantity: quantity,
				variation: variation
			}
		}).done(function (response) {
			if (response.success && response.data.redirect) {
				window.location.href = response.data.redirect;
				return;
			}
			alert(response.data && response.data.message ? response.data.message : SpadaBuyNow.strings.error);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
				? xhr.responseJSON.data.message
				: SpadaBuyNow.strings.error;
			alert(message);
		}).always(function () {
			setButtonLoading($button, false);
		});
	}

	$(document).on('found_variation', '.spada-buy-now-variation .variations_form', function (event, variation) {
		var $form = $(this);
		var $container = $form.closest('.spada-buy-now-variation');
		var $button = $container.nextAll('.spada-buy-now').first();

		$form.removeClass('spada-buy-now-invalid');
		$button.removeClass('spada-buy-now-selection-required');

		if (variation && variation.is_in_stock && variation.variation_is_visible && variation.is_purchasable) {
			setVariableBuyNowText($button, variation);
			$button.prop('disabled', false).removeClass('spada-buy-now-disabled').attr('aria-disabled', 'false');
			$container.removeClass('spada-buy-now-unavailable');
		} else {
			setVariableSelectOptionsText($button);
			$button.prop('disabled', true).addClass('spada-buy-now-disabled').attr('aria-disabled', 'true');
			$container.addClass('spada-buy-now-unavailable');
		}
	});

	$(document).on('hide_variation', '.spada-buy-now-variation .variations_form', function () {
		var $form = $(this);
		var $container = $form.closest('.spada-buy-now-variation');
		var $button = $container.nextAll('.spada-buy-now').first();
		setVariableSelectOptionsText($button);
		$button.prop('disabled', true).addClass('spada-buy-now-disabled').attr('aria-disabled', 'true');
		$container.removeClass('spada-buy-now-unavailable');
	});

	$(document).on('change', '.spada-buy-now-variation select', function () {
		var $container = $(this).closest('.spada-buy-now-variation');
		$container.find('.spada-buy-now-validation-error').removeClass('spada-buy-now-validation-error');
	});
});
