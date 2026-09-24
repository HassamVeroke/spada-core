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
			this.sanitizeAddressFields();
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

				var savedStage = null;
				try {
					var raw = sessionStorage.getItem('spada_auth_stage');
					if (!raw) {
						raw = localStorage.getItem('spada_auth_stage');
					}
					if (!raw) {
						var match = document.cookie.match(/(?:^|;\s*)spada_auth_stage=([^;]+)/);
						if (match) {
							raw = decodeURIComponent(match[1]);
						}
					}
					if (raw) {
						savedStage = JSON.parse(raw);
					}
				} catch (e) {}

				var hasSavedSignup = savedStage && savedStage.action === 'signup' && savedStage.view !== 'choice';
				var hasSavedLogin  = savedStage && savedStage.action === 'login'  && savedStage.view !== 'choice';

				var $authPortal = $('#spada-auth-portal');
				if ($authPortal.length) {
					var currentView = (savedStage && savedStage.view) ? savedStage.view : ($authPortal.find('.spada-auth-view.is-active').attr('data-view') || 'choice');

					if (currentView === 'choice' && !isExplicitRegister && !isExplicitLogin && !hasSavedSignup && !hasSavedLogin) {
						setHeroTexts(defaultHead, defaultDesc);
						return;
					}

					if (isExplicitRegister || hasSavedSignup || (window.SpadaAuth && window.SpadaAuth.state && window.SpadaAuth.state.action === 'signup')) {
						setHeroTexts(signupHead, signupDesc);
						return;
					}

					if (isExplicitLogin || hasSavedLogin || (window.SpadaAuth && window.SpadaAuth.state && window.SpadaAuth.state.action === 'login' && currentView !== 'choice')) {
						setHeroTexts(loginHead, loginDesc);
						return;
					}

					if (currentView === 'choice') {
						setHeroTexts(defaultHead, defaultDesc);
						return;
					}
				}

				// Standard fallback (non-portal)
				if (isExplicitRegister || hasSavedSignup || $('.register-form:visible').length > 0) {
					setHeroTexts(signupHead, signupDesc);
				} else if (isExplicitLogin || hasSavedLogin) {
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
			$(document).on('click', '#spada-choice-login, [data-action="login"], #showLogin, .show-login, a[href*="action=login"]', function() {
				setHeroTexts(loginHead, loginDesc);
			});

			// User proceeds with SignUp
			$(document).on('click', '#spada-choice-signup, [data-action="signup"], #showRegister, .show-register, a[href*="action=register"], a[href*="action=signup"]', function() {
				setHeroTexts(signupHead, signupDesc);
			});

			// User navigates back to Choice landing
			$(document).on('click', '#spada-methods-back-btn', function() {
				try { sessionStorage.removeItem('spada_auth_stage'); } catch (e) {}
				try { localStorage.removeItem('spada_auth_stage'); } catch (e) {}
				try { document.cookie = 'spada_auth_stage=; path=/; max-age=0; SameSite=Lax'; } catch (e) {}
				setHeroTexts(defaultHead, defaultDesc);
			});
		},

		disarmUnfocusableInputs: function() {
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

			// 1. Profile Form Submission via AJAX
			$(document).on('submit', '#spada-profile-form', function(e) {
				e.preventDefault();
				self.disarmUnfocusableInputs();
				self.handleSaveProfile();
			});

			// Real-time cleanup of address inputs on change/blur
			$(document).on('change blur focusout', 'input[name="billing_address_1"], #spada_billing_address', function() {
				var $el = $(this);
				var val = $el.val();
				if (val && val.length > 20) {
					var cleaned = self.cleanAddress(val);
					if (cleaned && cleaned !== val) {
						$el.val(cleaned);
					}
				}
			});

			// Standard form submission cleanup
			$(document).on('submit', '.custom-account-form', function() {
				var $addr = $(this).find('input[name="billing_address_1"]');
				if ($addr.length && $addr.val()) {
					var cleaned = self.cleanAddress($addr.val());
					if (cleaned && cleaned !== $addr.val()) {
						$addr.val(cleaned);
					}
				}
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

		sanitizeAddressFields: function() {
			var self = this;
			$('input[name="billing_address_1"], #spada_billing_address').each(function() {
				var val = $(this).val();
				if (val && val.length > 20) {
					var cleaned = self.cleanAddress(val);
					if (cleaned && cleaned !== val) {
						$(this).val(cleaned);
					}
				}
			});
		},

		cleanAddress: function(address) {
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

				var words1 = seg1.toLowerCase().split(/[\s,]+/).filter(function(w) { return w.length >= 3; });
				var words2 = seg2.toLowerCase().split(/[\s,]+/).filter(function(w) { return w.length >= 3; });

				var shared = words1.filter(function(w) { return words2.indexOf(w) !== -1; });
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
			address = self.cleanAddress(address);
			$('#spada_billing_address').val(address);

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
					spada_billing_address: address
				},
				success: function(response) {
					$btn.removeClass('is-loading');
					$btn.find('.spada-btn-text').text(i18n.saveChanges || 'SAVE CHANGES');
					$btn.find('.spada-btn-spinner').addClass('is-hidden');

					if (response.success) {
						self.showNotice('success', response.data.message || i18n.updateSuccess || 'Account details updated successfully.');
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
