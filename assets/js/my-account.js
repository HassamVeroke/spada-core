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
			this.disarmUnfocusableInputs();
			this.hideHeroSection();
			this.initHeroAuthFlowSync();
			this.bindEvents();
			this.checkQueryParams();
		},

		initHeroAuthFlowSync: function() {
			if ($('body').hasClass('logged-in')) {
				return;
			}

			var authData = (window.SpadaAccountData && window.SpadaAccountData.authHero) || {};
			var isArabic = (window.SpadaAccountData && window.SpadaAccountData.isRtl) ||
				$('html').attr('lang') === 'ar' ||
				$('html').attr('dir') === 'rtl' ||
				$('body').hasClass('rtl') ||
				window.location.pathname.indexOf('/ar/') !== -1;

			var defaultHead = authData.defaultHead || (isArabic ? 'حسابي' : 'Account');
			var defaultDesc = authData.defaultDesc || (isArabic ? 'يرجى تقديم التفاصيل اللازمة للوصول إلى حسابك.' : 'Please provide necessary details to access to your account.');
			var loginHead   = authData.loginHead || (isArabic ? 'تسجيل الدخول' : 'Login');
			var loginDesc   = authData.loginDesc || (isArabic ? 'يرجى تقديم التفاصيل اللازمة لتسجيل الدخول إلى حسابك.' : 'Please provide necessary details to login to your account.');
			var signupHead  = authData.signupHead || (isArabic ? 'إنشاء حساب' : 'Sign up');
			var signupDesc  = authData.signupDesc || (isArabic ? 'يرجى تقديم التفاصيل اللازمة لإنشاء حسابك.' : 'Please provide necessary details to sign up to your account.');

			var initialCaptured = false;
			var captureInitialTexts = function() {
				if (initialCaptured) return;
				var $head = $('.acc_hero_head h1');
				if (!$head.length) {
					$head = $('.acc_hero_head .elementor-heading-title, .acc_hero_head');
				}
				if ($head.length) {
					var rawHead = $.trim($head.text());
					if (rawHead && rawHead.toLowerCase() !== 'login' && rawHead !== 'تسجيل الدخول' && rawHead.toLowerCase() !== 'sign up' && rawHead !== 'إنشاء حساب') {
						defaultHead = rawHead;
					}
				}

				var $descContainer = $('.acc_hero_desc .elementor-widget-container');
				if (!$descContainer.length) {
					$descContainer = $('.acc_hero_desc p, .acc_hero_desc');
				}
				if ($descContainer.length) {
					var $p = $descContainer.find('p');
					var rawDesc = $.trim($p.length ? $p.text() : $descContainer.text());
					if (rawDesc && rawDesc !== loginDesc && rawDesc !== signupDesc) {
						defaultDesc = rawDesc;
					}
				}
				initialCaptured = true;
			};
			captureInitialTexts();

			var setHeroTexts = function(heading, desc) {
				if (heading) {
					var $head = $('.acc_hero_head h1');
					if (!$head.length) {
						$head = $('.acc_hero_head .elementor-heading-title, .acc_hero_head');
					}
					if ($head.length && $.trim($head.text()) !== heading) {
						$head.text(heading);
					}
				}

				if (desc) {
					var $descContainer = $('.acc_hero_desc .elementor-widget-container');
					if (!$descContainer.length) {
						$descContainer = $('.acc_hero_desc p, .acc_hero_desc');
					}
					if ($descContainer.length) {
						var $p = $descContainer.find('p');
						if ($p.length) {
							if ($.trim($p.text()) !== desc) {
								$p.text(desc);
							}
						} else if ($.trim($descContainer.text()) !== desc) {
							$descContainer.text(desc);
						}
					}
				}
			};

			var syncHeroState = function() {
				captureInitialTexts();

				var searchParams = new URLSearchParams(window.location.search);
				var actionParam = searchParams.get('action');
				var keyParam = searchParams.get('key');
				var hash = window.location.hash;

				var isExplicitRegister = actionParam === 'register' ||
					actionParam === 'signup' ||
					keyParam === 'register' ||
					hash === '#register' ||
					hash === '#signup' ||
					searchParams.has('signup');

				var isExplicitLogin = actionParam === 'login' || hash === '#login';

				var $authPortal = $('#spada-auth-portal');
				if ($authPortal.length) {
					var currentView = $authPortal.find('.spada-auth-view.is-active').attr('data-view') || 'choice';

					if (currentView === 'choice' && !isExplicitRegister && !isExplicitLogin) {
						setHeroTexts(defaultHead, defaultDesc);
						return;
					}

					if (isExplicitRegister || (window.SpadaAuth && window.SpadaAuth.state && window.SpadaAuth.state.action === 'signup')) {
						setHeroTexts(signupHead, signupDesc);
						return;
					}

					if (isExplicitLogin || (window.SpadaAuth && window.SpadaAuth.state && window.SpadaAuth.state.action === 'login' && currentView !== 'choice')) {
						setHeroTexts(loginHead, loginDesc);
						return;
					}

					if (currentView === 'choice') {
						setHeroTexts(defaultHead, defaultDesc);
						return;
					}
				}

				// Standard fallback (non-portal)
				if (isExplicitRegister || $('.register-form:visible').length > 0 || $('.xoo-el-section-register:visible').length > 0) {
					setHeroTexts(signupHead, signupDesc);
				} else if (isExplicitLogin) {
					setHeroTexts(loginHead, loginDesc);
				} else {
					setHeroTexts(defaultHead, defaultDesc);
				}
			};

			// Initial evaluation
			syncHeroState();
			setTimeout(syncHeroState, 50);
			setTimeout(syncHeroState, 300);
			$(window).on('load', syncHeroState);

			// Listen to custom Spada auth view changes
			$(document).on('spada_auth_view_change', function(e, viewName, action) {
				if (viewName === 'choice') {
					setHeroTexts(defaultHead, defaultDesc);
				} else if (action === 'signup') {
					setHeroTexts(signupHead, signupDesc);
				} else if (action === 'login') {
					setHeroTexts(loginHead, loginDesc);
				}
			});

			// User proceeds with Login
			$(document).on('click', '#spada-choice-login, [data-action="login"], #showLogin, .show-login, .xoo-el-login-tgr, a[href*="action=login"]', function() {
				setHeroTexts(loginHead, loginDesc);
			});

			// User proceeds with SignUp
			$(document).on('click', '#spada-choice-signup, [data-action="signup"], #showRegister, .show-register, .xoo-el-reg-tgr, a[href*="action=register"], a[href*="action=signup"]', function() {
				setHeroTexts(signupHead, signupDesc);
			});

			// User navigates back to Choice landing
			$(document).on('click', '#spada-methods-back-btn', function() {
				setHeroTexts(defaultHead, defaultDesc);
			});
		},

		disarmUnfocusableInputs: function() {
			$('input[name="xoo-ml-reg-phone"], input[name="xoo-ml-reg-phone-cc"], input.xoo-ml-phone-input').prop('required', false).removeAttr('required').removeAttr('aria-required');
			$('.custom-account-form, #spada-profile-form, form.woocommerce-EditAccountForm').attr('novalidate', 'novalidate');
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
				self.disarmUnfocusableInputs();
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
