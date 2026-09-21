/**
 * SPADA My Account Client Script
 *
 * Handles hero section suppression, password visibility toggle,
 * profile update AJAX submissions, and orders/subscriptions tab toggling.
 */

(function($) {
	'use strict';

	var SpadaAccount = {
		init: function() {
			this.hideHeroSection();
			this.bindEvents();
			this.checkQueryParams();
		},

		hideHeroSection: function() {
			if (!$('body').hasClass('logged-in') || !$('body').hasClass('woocommerce-account')) {
				return;
			}

			// Hide known hero selectors
			var heroSelectors = '.hero_title, .hero-lg, .hero-sm, .w1200-hero-cc, .page-header, .entry-header, .spada-auth-header';
			$(heroSelectors).each(function() {
				$(this).closest('.elementor-section, .elementor-top-section, header, section').addClass('spada-hero-hidden').hide();
			});

			// Hide any Elementor section whose primary title says "ACCOUNT"
			$('.elementor-section, .elementor-top-section').each(function() {
				var $heading = $(this).find('h1, h2, .elementor-heading-title');
				if ($heading.length && $.trim($heading.text()).toLowerCase() === 'account') {
					$(this).addClass('spada-hero-hidden').hide();
				}
			});
		},

		bindEvents: function() {
			var self = this;

			// 1. Password Visibility Toggle
			$(document).on('click', '#spada-pwd-toggle', function(e) {
				e.preventDefault();
				var $input = $('#spada_account_password');
				var $btn = $(this);
				var isPassword = $input.attr('type') === 'password';

				if (isPassword) {
					$input.attr('type', 'text');
					$btn.find('.spada-eye-slash').addClass('is-hidden');
					$btn.find('.spada-eye').removeClass('is-hidden');
				} else {
					$input.attr('type', 'password');
					$btn.find('.spada-eye-slash').removeClass('is-hidden');
					$btn.find('.spada-eye').addClass('is-hidden');
				}
			});

			// 2. Profile Form Submission via AJAX
			$(document).on('submit', '#spada-profile-form', function(e) {
				e.preventDefault();
				self.handleSaveProfile();
			});

			// 3. Navigation Tabs (ORDERS & SUBSCRIPTIONS)
			$(document).on('click', '.spada-tab-btn', function(e) {
				var tab = $(this).data('tab');
				var $panel = $('#spada-panel-' + tab);

				// If panel exists on page, toggle smoothly
				if ($panel.length) {
					e.preventDefault();
					if ($panel.hasClass('is-hidden')) {
						$('.spada-tab-panel').addClass('is-hidden');
						$('.spada-tab-btn').removeClass('is-active');
						$panel.removeClass('is-hidden');
						$(this).addClass('is-active');
						$('html, body').animate({
							scrollTop: $panel.offset().top - 100
						}, 300);
					} else {
						$panel.addClass('is-hidden');
						$(this).removeClass('is-active');
					}
				}
			});
		},

		handleSaveProfile: function() {
			var self = this;
			var $btn = $('#spada-save-profile-btn');
			var $notice = $('#spada-profile-notice');
			var i18n = (window.SpadaAccountData && window.SpadaAccountData.i18n) || {};

			var displayName = $.trim($('#spada_account_display_name').val());
			var email = $.trim($('#spada_account_email').val());
			var phone = $.trim($('#spada_billing_phone').val());
			var address = $.trim($('#spada_billing_address').val());
			var password = $('#spada_account_password').val();

			if (!displayName) {
				self.showNotice('error', i18n.genericError || 'Please enter your name.');
				return;
			}

			if (!email) {
				self.showNotice('error', i18n.genericError || 'Please enter a valid email address.');
				return;
			}

			// Loading state
			$btn.addClass('is-loading');
			$btn.find('.spada-btn-text').text(i18n.saving || 'Saving...');
			$btn.find('.spada-btn-spinner').removeClass('is-hidden');
			$notice.addClass('is-hidden').text('');

			$.ajax({
				url: (window.SpadaAccountData && window.SpadaAccountData.ajaxUrl) || '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'spada_update_account_details',
					security: (window.SpadaAccountData && window.SpadaAccountData.nonce) || '',
					spada_account_display_name: displayName,
					spada_account_email: email,
					spada_billing_phone: phone,
					spada_billing_address: address,
					spada_account_password: password
				},
				success: function(response) {
					$btn.removeClass('is-loading');
					$btn.find('.spada-btn-text').text(i18n.saveChanges || 'SAVE CHANGES');
					$btn.find('.spada-btn-spinner').addClass('is-hidden');

					if (response.success) {
						self.showNotice('success', response.data.message || i18n.updateSuccess || 'Account details updated successfully.');
						$('#spada_account_password').val('');
					} else {
						self.showNotice('error', response.data.message || i18n.genericError || 'Something went wrong.');
					}
				},
				error: function() {
					$btn.removeClass('is-loading');
					$btn.find('.spada-btn-text').text(i18n.saveChanges || 'SAVE CHANGES');
					$btn.find('.spada-btn-spinner').addClass('is-hidden');
					self.showNotice('error', i18n.genericError || 'Something went wrong. Please try again.');
				}
			});
		},

		showNotice: function(type, message) {
			var $notice = $('#spada-profile-notice');
			$notice.removeClass('is-hidden is-success is-error')
				.addClass(type === 'success' ? 'is-success' : 'is-error')
				.text(message);

			$('html, body').animate({
				scrollTop: $notice.offset().top - 120
			}, 300);
		},

		checkQueryParams: function() {
			try {
				var searchParams = new URLSearchParams(window.location.search);
				if (searchParams.get('spada_updated') === '1') {
					var i18n = (window.SpadaAccountData && window.SpadaAccountData.i18n) || {};
					this.showNotice('success', i18n.updateSuccess || 'Account details updated successfully.');
				}
			} catch (e) {}
		}
	};

	$(document).ready(function() {
		SpadaAccount.init();
	});

})(jQuery);
