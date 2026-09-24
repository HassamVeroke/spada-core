/**
 * SPADA Authentication Client Handler
 *
 * Implements view state management, 6-digit OTP interactions,
 * paste distribution, countdown timers, and AJAX session handling.
 */

(function($) {
	'use strict';

	var SpadaAuth = {
		state: {
			currentView: 'choice', // choice, methods, input, verify
			action: 'login',       // login or signup
			method: 'email',       // email, whatsapp, sms
			identifier: '',        // email or phone
			maskedTarget: '',      // formatted display for verify screen
			countdownTimer: null,
			countdownSec: 60
		},

		init: function() {
			this.cacheDom();
			this.disarmUnfocusableInputs();
			this.bindEvents();
			this.initOtpGrid();
			this.restoreOrInitState();
		},

		disarmUnfocusableInputs: function() {
			$('input[name="xoo-ml-reg-phone"], input[name="xoo-ml-reg-phone-cc"], input.xoo-ml-phone-input').prop('required', false).removeAttr('required').removeAttr('aria-required');
			$('.spada-native-login-hidden').find('input, select, textarea, button').prop('required', false).removeAttr('required').prop('disabled', true);
			$('form').attr('novalidate', 'novalidate');
		},

		cacheDom: function() {
			this.$wrap               = $('#spada-auth-portal');
			this.$mainTitle          = $('#spada-auth-main-title');
			this.$mainSubtitle       = $('#spada-auth-main-subtitle');
			this.$views              = $('.spada-auth-view');

			// Choice cards
			this.$choiceCards        = $('.spada-choice-card');

			// Method buttons
			this.$methodBtns         = $('.spada-method-btn');

			// Input View elements
			this.$inputHeading       = $('#spada-input-heading');
			this.$inputSubheading    = $('#spada-input-subheading');
			this.$emailGroup         = $('#spada-field-group-email');
			this.$phoneGroup         = $('#spada-field-group-phone');
			this.$phoneLabel         = $('#spada-phone-label');
			this.$emailInput         = $('#spada-auth-email');
			this.$phoneInput         = $('#spada-auth-phone');
			this.$phoneCustomError   = $('#spada-phone-custom-error');
			this.$phoneWrap          = this.$phoneInput.closest('.spada-phone-input-wrap');
			this.$inputNotice        = $('#spada-input-notice');
			this.$inputSubmitBtn     = $('#spada-input-submit-btn');
			this.$inputBackBtn       = $('#spada-input-back-btn');
			this.$inputChooseAnother = $('#spada-input-choose-another-btn');

			// Verify View elements
			this.$verifyHeading      = $('#spada-verify-heading');
			this.$verifyPrompt       = $('#spada-verify-prompt');
			this.$verifyTarget       = $('#spada-verify-target');
			this.$otpDigits          = $('.spada-otp-digit');
			this.$otpFull            = $('#spada-otp-full');
			this.$verifyNotice       = $('#spada-verify-notice');
			this.$verifySubmitBtn    = $('#spada-verify-submit-btn');
			this.$verifyBackBtn      = $('#spada-verify-back-btn');
			this.$resendBtn          = $('#spada-resend-btn');
			this.$countdownWrap      = $('#spada-countdown-wrap');
			this.$countdownSec       = $('#spada-countdown-sec');
			this.$changeTargetBtn    = $('#spada-change-target-btn');
			this.$changeTargetText   = $('#spada-change-target-text');
		},

		bindEvents: function() {
			var self = this;

			// 1. Choice cards click (Signup vs Login)
			this.$choiceCards.on('click', function(e) {
				e.preventDefault();
				var action = $(this).data('action');
				self.state.action = action;
				self.$choiceCards.removeClass('is-selected');
				$(this).addClass('is-selected');

				self.$mainTitle.text(action === 'signup' ? 'SIGNUP' : 'LOGIN');
				self.updateMethodButtons(action);
				self.showView('methods');
			});

			// 2. Method selection (Email, WhatsApp, SMS)
			this.$methodBtns.on('click', function(e) {
				e.preventDefault();
				var method = $(this).data('method');
				self.state.method = method;
				self.$methodBtns.removeClass('is-active');
				$(this).addClass('is-active');

				self.setupInputView(method);
				self.showView('input');
			});

			// 3. Navigation buttons
			$('#spada-methods-back-btn').on('click', function(e) {
				e.preventDefault();
				self.showView('choice');
			});

			this.$inputBackBtn.on('click', function(e) {
				e.preventDefault();
				self.showView('methods');
			});

			this.$inputChooseAnother.on('click', function(e) {
				e.preventDefault();
				self.showView('methods');
			});

			this.$verifyBackBtn.on('click', function(e) {
				e.preventDefault();
				self.showView('input');
			});

			this.$changeTargetBtn.on('click', function(e) {
				e.preventDefault();
				self.showView('input');
			});

			// 4. Phone & Email input validation and stage caching
			this.$emailInput.on('input propertychange', function() {
				self.state.identifier = $(this).val().trim();
				self.saveStage();
			});

			this.$phoneInput.on('input propertychange', function() {
				self.validatePhoneField(false);
				self.state.identifier = $(this).val().trim();
				self.saveStage();
			});

			this.$phoneInput.on('blur', function() {
				self.validatePhoneField(true);
			});

			// 5. Identifier Form Submit (Request OTP)
			this.$inputSubmitBtn.on('click', function(e) {
				e.preventDefault();
				if (!$(this).hasClass('disabled') && !$(this).prop('disabled')) {
					self.requestOtp();
				}
			});

			$('#spada-identifier-form').on('submit', function(e) {
				e.preventDefault();
				if (!self.$inputSubmitBtn.hasClass('disabled') && !self.$inputSubmitBtn.prop('disabled')) {
					self.requestOtp();
				}
			});

			// 5. Verify Form Submit (Verify OTP)
			this.$verifySubmitBtn.on('click', function(e) {
				e.preventDefault();
				if (!$(this).hasClass('disabled') && !$(this).prop('disabled')) {
					self.verifyOtp();
				}
			});

			$('#spada-otp-form').on('submit', function(e) {
				e.preventDefault();
				if (!self.$verifySubmitBtn.hasClass('disabled') && !self.$verifySubmitBtn.prop('disabled')) {
					self.verifyOtp();
				}
			});

			// 6. Resend Click
			this.$resendBtn.on('click', function(e) {
				e.preventDefault();
				if (!$(this).hasClass('disabled') && !$(this).prop('disabled')) {
					self.resendOtp();
				}
			});
		},

		showView: function(viewName) {
			this.state.currentView = viewName;
			this.$views.removeClass('is-active');
			$('#spada-view-' + viewName).addClass('is-active');
			if (viewName === 'methods') {
				this.updateMethodButtons(this.state.action);
			}
			this.clearNotices();
			this.disarmUnfocusableInputs();
			this.saveStage();
			$(document).trigger('spada_auth_view_change', [viewName, this.state.action]);
		},

		getSavedStage: function() {
			var raw = null;
			try {
				raw = sessionStorage.getItem('spada_auth_stage');
			} catch (e) {}
			if (!raw) {
				try {
					raw = localStorage.getItem('spada_auth_stage');
				} catch (e) {}
			}
			if (!raw) {
				try {
					var match = document.cookie.match(/(?:^|;\s*)spada_auth_stage=([^;]+)/);
					if (match) {
						raw = decodeURIComponent(match[1]);
					}
				} catch (e) {}
			}
			if (raw) {
				try {
					return JSON.parse(raw);
				} catch (e) {}
			}
			return null;
		},

		saveStage: function() {
			try {
				var searchParams = new URLSearchParams(window.location.search);
				if (this.state.currentView === 'choice') {
					this.clearStage();
					if (searchParams.has('action') || searchParams.has('step') || searchParams.has('auth_method')) {
						searchParams.delete('action');
						searchParams.delete('step');
						searchParams.delete('auth_method');
						var cleanSearch = searchParams.toString();
						var cleanUrl = window.location.pathname + (cleanSearch ? '?' + cleanSearch : '') + window.location.hash;
						try {
							window.history.replaceState(null, '', cleanUrl);
						} catch (e) {}
					}
				} else {
					var payload = {
						view: this.state.currentView,
						action: this.state.action || 'login',
						method: this.state.method || 'email',
						identifier: this.state.identifier || '',
						maskedTarget: this.state.maskedTarget || ''
					};
					var serialized = JSON.stringify(payload);
					try { sessionStorage.setItem('spada_auth_stage', serialized); } catch (e) {}
					try { localStorage.setItem('spada_auth_stage', serialized); } catch (e) {}
					try {
						document.cookie = 'spada_auth_stage=' + encodeURIComponent(serialized) + '; path=/; max-age=86400; SameSite=Lax';
					} catch (e) {}

					searchParams.set('action', this.state.action || 'login');
					if (this.state.currentView !== 'methods') {
						searchParams.set('step', this.state.currentView);
					} else {
						searchParams.delete('step');
					}
					if (this.state.method && this.state.currentView !== 'methods') {
						searchParams.set('auth_method', this.state.method);
					} else {
						searchParams.delete('auth_method');
					}
					var newSearch = searchParams.toString();
					var newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
					try {
						window.history.replaceState(null, '', newUrl);
					} catch (e) {}
				}
			} catch (e) {}
		},

		clearStage: function() {
			try { sessionStorage.removeItem('spada_auth_stage'); } catch (e) {}
			try { localStorage.removeItem('spada_auth_stage'); } catch (e) {}
			try {
				document.cookie = 'spada_auth_stage=; path=/; max-age=0; SameSite=Lax';
			} catch (e) {}
		},

		restoreOrInitState: function() {
			if ($('body').hasClass('logged-in')) {
				this.clearStage();
				return;
			}

			var saved = this.getSavedStage();

			var searchParams = new URLSearchParams(window.location.search);
			var actionParam  = searchParams.get('action');
			var stepParam    = searchParams.get('step');
			var methodParam  = searchParams.get('auth_method');
			var hash         = window.location.hash;

			var portalInitialView   = this.$wrap.attr('data-initial-view');
			var portalInitialAction = this.$wrap.attr('data-initial-action');
			var portalInitialMethod = this.$wrap.attr('data-initial-method');

			var hasExplicitSignup = actionParam === 'signup' || actionParam === 'register' || hash === '#signup' || searchParams.has('signup');
			var hasExplicitLogin  = actionParam === 'login' || hash === '#login';

			if (hasExplicitSignup) {
				this.state.action = 'signup';
			} else if (hasExplicitLogin) {
				this.state.action = 'login';
			} else if (saved && saved.action) {
				this.state.action = saved.action;
			} else if (portalInitialAction) {
				this.state.action = portalInitialAction;
			}

			// Sync choice cards selection
			this.$choiceCards.removeClass('is-selected');
			if (this.state.action === 'signup') {
				$('#spada-choice-signup').addClass('is-selected');
				this.$mainTitle.text('SIGNUP');
			} else {
				$('#spada-choice-login').addClass('is-selected');
				this.$mainTitle.text('LOGIN');
			}
			this.updateMethodButtons(this.state.action);

			var targetView = 'choice';
			if (stepParam && ['methods', 'input', 'verify'].indexOf(stepParam) !== -1) {
				targetView = stepParam;
			} else if (saved && saved.view) {
				targetView = saved.view;
			} else if (portalInitialView && portalInitialView !== 'choice') {
				targetView = portalInitialView;
			} else if (hasExplicitSignup || hasExplicitLogin) {
				targetView = 'methods';
			}

			if (targetView === 'methods') {
				this.showView('methods');
			} else if (targetView === 'input') {
				var method = methodParam || (saved && saved.method) || portalInitialMethod || this.state.method || 'email';
				this.state.method = method;

				this.$methodBtns.removeClass('is-active');
				$('#spada-method-' + method).addClass('is-active');

				this.setupInputView(method);

				if (saved && saved.identifier) {
					this.state.identifier = saved.identifier;
					if (method === 'email') {
						this.$emailInput.val(saved.identifier);
					} else {
						this.$phoneInput.val(saved.identifier);
					}
				}

				this.showView('input');
			} else if (targetView === 'verify') {
				var method = methodParam || (saved && saved.method) || portalInitialMethod || this.state.method || 'email';
				this.state.method = method;
				this.state.identifier = (saved && saved.identifier) || '';
				this.state.maskedTarget = (saved && saved.maskedTarget) || this.state.identifier;

				this.$methodBtns.removeClass('is-active');
				$('#spada-method-' + method).addClass('is-active');

				this.setupVerifyView(this.state.maskedTarget || this.state.identifier);
				this.showView('verify');
			} else {
				this.showView('choice');
			}
		},

		updateMethodButtons: function(action) {
			action = action || this.state.action || 'login';
			var isSignup = (action === 'signup');
			var i18n = (window.SpadaAuthData && window.SpadaAuthData.i18n) || {};

			this.$methodBtns.each(function() {
				var $btn = $(this);
				var method = $btn.data('method');
				var text = '';

				if (isSignup) {
					if (method === 'email') {
						text = i18n.signupEmail || $btn.data('signup-text') || 'Signup using Email';
					} else if (method === 'whatsapp') {
						text = i18n.signupWhatsapp || $btn.data('signup-text') || 'Signup using Whatsapp';
					} else if (method === 'sms') {
						text = i18n.signupSms || $btn.data('signup-text') || 'Signup using SMS';
					}
				} else {
					if (method === 'email') {
						text = i18n.signInEmail || $btn.data('login-text') || 'Sign in with Email';
					} else if (method === 'whatsapp') {
						text = i18n.signInWhatsapp || $btn.data('login-text') || 'Sign in with Whatsapp';
					} else if (method === 'sms') {
						text = i18n.signInSms || $btn.data('login-text') || 'Sign in with SMS';
					}
				}

				if (text) {
					$btn.find('.spada-method-text').text(text);
				}
			});
		},

		setupInputView: function(method) {
			var i18n = (window.SpadaAuthData && window.SpadaAuthData.i18n) || {};

			// Toggle icons in circle
			$('#spada-input-icon-wrap svg, #spada-verify-icon-wrap svg').addClass('is-hidden');
			$('#spada-input-icon-wrap .spada-icon-' + method + ', #spada-verify-icon-wrap .spada-icon-' + method).removeClass('is-hidden');

			if (method === 'email') {
				this.$inputHeading.text(i18n.enterEmail || 'Enter your email address');
				this.$inputSubheading.text(i18n.subEmail || "We'll send a six digit code to your email address.");
				this.$emailGroup.removeClass('is-hidden');
				this.$phoneGroup.addClass('is-hidden');
				this.clearPhoneError();
				this.$emailInput.focus();
			} else if (method === 'whatsapp') {
				this.$inputHeading.text(i18n.enterWhatsapp || 'Enter your whatsapp number');
				this.$inputSubheading.text(i18n.subWhatsapp || "We'll send a six digit code to your whatsapp");
				this.$emailGroup.addClass('is-hidden');
				this.$phoneGroup.removeClass('is-hidden');
				this.$phoneLabel.text('WHATSAPP NUMBER');
				this.clearPhoneError();
				this.$phoneInput.focus();
			} else {
				this.$inputHeading.text(i18n.enterMobile || 'Enter your mobile number');
				this.$inputSubheading.text(i18n.subMobile || "We'll send a six digit code to your mobile number");
				this.$emailGroup.addClass('is-hidden');
				this.$phoneGroup.removeClass('is-hidden');
				this.$phoneLabel.text('MOBILE NUMBER');
				this.clearPhoneError();
				this.$phoneInput.focus();
			}
		},

		setupVerifyView: function(targetDisplay) {
			var i18n = (window.SpadaAuthData && window.SpadaAuthData.i18n) || {};
			var method = this.state.method;

			$('#spada-input-icon-wrap svg, #spada-verify-icon-wrap svg').addClass('is-hidden');
			$('#spada-input-icon-wrap .spada-icon-' + method + ', #spada-verify-icon-wrap .spada-icon-' + method).removeClass('is-hidden');

			this.$verifyTarget.text(targetDisplay);

			if (method === 'email') {
				this.$verifyHeading.text(i18n.checkEmail || 'Check your email address');
				this.$verifyPrompt.text(i18n.promptEmail || "We've sent a six digit code to your email address");
				this.$changeTargetText.text(i18n.changeEmail || 'Change Email');
				$('#spada-resend-prompt').text(i18n.didntEmail || "Didn't receive the email?");
			} else if (method === 'whatsapp') {
				this.$verifyHeading.text(i18n.checkWhatsapp || 'Check your whatsapp account');
				this.$verifyPrompt.text(i18n.promptWhatsapp || "We've sent a six digit code to your whatsapp account on");
				this.$changeTargetText.text(i18n.changeNumber || 'Change Number');
				$('#spada-resend-prompt').text(i18n.didntWhatsapp || "Didn't receive the message on whatsapp?");
			} else {
				this.$verifyHeading.text(i18n.checkMobile || 'Check your messages');
				this.$verifyPrompt.text(i18n.promptMobile || "We've sent a six digit code to your mobile number");
				this.$changeTargetText.text(i18n.changeNumber || 'Change Number');
				$('#spada-resend-prompt').text(i18n.didntMobile || "Didn't receive the message on number?");
			}

			// Clear OTP fields & focus first
			this.$otpDigits.val('');
			this.$otpFull.val('');
			this.$otpDigits.first().focus();

			this.startCountdown();
		},

		initOtpGrid: function() {
			var self = this;

			this.$otpDigits.on('input', function(e) {
				var $input = $(this);
				var val = $input.val().replace(/\D/g, '');
				$input.val(val ? val.charAt(0) : '');

				// If value entered, auto-advance to next input
				if (val) {
					var idx = parseInt($input.data('index'), 10);
					if (idx < 5) {
						self.$otpDigits.eq(idx + 1).focus();
					}
				}

				self.updateFullOtp();
			});

			this.$otpDigits.on('keydown', function(e) {
				var $input = $(this);
				var idx = parseInt($input.data('index'), 10);

				// Backspace navigation
				if (e.key === 'Backspace' || e.keyCode === 8) {
					if (!$input.val() && idx > 0) {
						self.$otpDigits.eq(idx - 1).focus().val('');
						self.updateFullOtp();
					}
				}
			});

			// Paste handling (auto-distributes 6 digits across boxes)
			this.$otpDigits.on('paste', function(e) {
				var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
				if (!clipboardData) return;

				var pastedData = clipboardData.getData('text').replace(/\D/g, '');
				if (pastedData.length >= 6) {
					e.preventDefault();
					for (var i = 0; i < 6; i++) {
						self.$otpDigits.eq(i).val(pastedData.charAt(i));
					}
					self.$otpDigits.eq(5).focus();
					self.updateFullOtp();
				}
			});
		},

		updateFullOtp: function() {
			var full = '';
			this.$otpDigits.each(function() {
				full += $(this).val();
			});
			this.$otpFull.val(full);

			// If 6 digits filled, can automatically verify
			if (full.length === 6) {
				this.verifyOtp();
			}
		},

		requestOtp: function() {
			var self = this;
			var method = this.state.method;
			var authAction = this.state.action || 'login';
			var authData = window.SpadaAuthData || {};
			var data = {
				nonce: authData.nonce,
				auth_action: authAction
			};

			this.clearNotices();
			this.setLoading(this.$inputSubmitBtn, true);

			if (method === 'email') {
				var email = this.$emailInput.val().trim();
				if (!email) {
					this.showNotice(this.$inputNotice, 'Please enter your email address.', 'error');
					this.setLoading(this.$inputSubmitBtn, false);
					return;
				}
				data.action = 'spada_request_email_otp';
				data.email = email;
				this.state.identifier = email;

				$.post(authData.ajaxUrl, data, function(res) {
					self.setLoading(self.$inputSubmitBtn, false);
					if (res.success) {
						self.state.maskedTarget = email;
						self.setupVerifyView(email);
						self.showView('verify');
					} else {
						self.showNotice(self.$inputNotice, res.data && res.data.message ? res.data.message : 'Error sending OTP.', 'error');
					}
				}).fail(function(xhr) {
					self.setLoading(self.$inputSubmitBtn, false);
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Network error. Please try again.';
					self.showNotice(self.$inputNotice, msg, 'error');
				});
			} else {
				var isValidPhone = this.validatePhoneField(true);
				if (!isValidPhone) {
					this.setLoading(this.$inputSubmitBtn, false);
					this.$phoneInput.focus();
					return;
				}

				var phone = this.$phoneInput.val().trim();
				data.action = 'spada_request_phone_otp';
				data.phone = phone;
				data.channel = method; // whatsapp or sms
				this.state.identifier = phone;

				$.post(authData.ajaxUrl, data, function(res) {
					self.setLoading(self.$inputSubmitBtn, false);
					if (res.success) {
						var masked = res.data && res.data.masked ? res.data.masked : ('+966 ' + phone);
						self.state.maskedTarget = masked;
						self.setupVerifyView(masked);
						self.showView('verify');
					} else {
						self.showNotice(self.$inputNotice, res.data && res.data.message ? res.data.message : 'Error sending OTP.', 'error');
					}
				}).fail(function(xhr) {
					self.setLoading(self.$inputSubmitBtn, false);
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Network error. Please try again.';
					self.showNotice(self.$inputNotice, msg, 'error');
				});
			}
		},

		verifyOtp: function() {
			var self = this;
			var method = this.state.method;
			var authAction = this.state.action || 'login';
			var authData = window.SpadaAuthData || {};
			var otp = this.$otpFull.val().trim();

			if (otp.length !== 6) {
				this.showNotice(this.$verifyNotice, 'Please enter all 6 digits of the code.', 'error');
				return;
			}

			this.clearNotices();
			this.setLoading(this.$verifySubmitBtn, true);

			var data = {
				nonce: authData.nonce,
				otp_code: otp,
				is_checkout: authData.isCheckout,
				auth_action: authAction
			};

			if (method === 'email') {
				data.action = 'spada_verify_email_otp';
				data.email = this.state.identifier;
			} else {
				data.action = 'spada_verify_phone_otp';
				data.phone = this.state.identifier;
			}

			$.post(authData.ajaxUrl, data, function(res) {
				self.setLoading(self.$verifySubmitBtn, false);
				if (res.success) {
					self.clearStage();
					self.showNotice(self.$verifyNotice, res.data && res.data.message ? res.data.message : 'Login successful!', 'success');
					setTimeout(function() {
						if (authData.isCheckout === 'yes') {
							// Refresh checkout or redirect
							$(document.body).trigger('update_checkout');
							window.location.reload();
						} else {
							window.location.href = res.data && res.data.redirect ? res.data.redirect : (authData.accountUrl || window.location.href);
						}
					}, 500);
				} else {
					self.showNotice(self.$verifyNotice, res.data && res.data.message ? res.data.message : 'Invalid code.', 'error');
				}
			}).fail(function(xhr) {
				self.setLoading(self.$verifySubmitBtn, false);
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Verification error. Please try again.';
				self.showNotice(self.$verifyNotice, msg, 'error');
			});
		},

		resendOtp: function() {
			var self = this;
			var method = this.state.method;
			var authAction = this.state.action || 'login';
			var authData = window.SpadaAuthData || {};
			var data = {
				nonce: authData.nonce,
				auth_action: authAction
			};

			if (method === 'email') {
				data.action = 'spada_resend_email_otp';
				data.email = this.state.identifier;
			} else {
				data.action = 'spada_resend_phone_otp';
				data.phone = this.state.identifier;
				data.channel = method;
			}

			$.post(authData.ajaxUrl, data, function(res) {
				if (res.success) {
					self.showNotice(self.$verifyNotice, 'Verification code resent successfully.', 'success');
					self.startCountdown();
				} else {
					self.showNotice(self.$verifyNotice, res.data && res.data.message ? res.data.message : 'Error resending code.', 'error');
				}
			});
		},

		startCountdown: function() {
			var self = this;
			clearInterval(this.state.countdownTimer);
			this.state.countdownSec = 60;

			this.$resendBtn.addClass('disabled').attr('aria-disabled', 'true').prop('disabled', true).css('opacity', '0.5');
			this.$countdownWrap.removeClass('is-hidden');
			this.$countdownSec.text('60');

			this.state.countdownTimer = setInterval(function() {
				self.state.countdownSec--;
				self.$countdownSec.text(self.state.countdownSec);

				if (self.state.countdownSec <= 0) {
					clearInterval(self.state.countdownTimer);
					self.$countdownWrap.addClass('is-hidden');
					self.$resendBtn.removeClass('disabled').removeAttr('aria-disabled').prop('disabled', false).css('opacity', '1');
				}
			}, 1000);
		},

		setLoading: function($btn, isLoading) {
			var $text = $btn.find('.spada-btn-text');
			var $spinner = $btn.find('.spada-btn-spinner');

			if (isLoading) {
				$btn.addClass('disabled').attr('aria-disabled', 'true').prop('disabled', true);
				$text.css('opacity', '0.6');
				$spinner.removeClass('is-hidden');
			} else {
				$btn.removeClass('disabled').removeAttr('aria-disabled').prop('disabled', false);
				$text.css('opacity', '1');
				$spinner.addClass('is-hidden');
			}
		},

		showNotice: function($container, message, type) {
			$container.removeClass('is-hidden is-error is-success')
				.addClass(type === 'success' ? 'is-success' : 'is-error')
				.text(message);
		},

		clearNotices: function() {
			this.$inputNotice.addClass('is-hidden').text('');
			this.$verifyNotice.addClass('is-hidden').text('');
			this.clearPhoneError();
		},

		validatePhoneField: function(showEmptyError) {
			var raw = this.$phoneInput.val();
			if (typeof raw !== 'string') {
				raw = '';
			}

			// Strip all non-digits
			var digits = raw.replace(/\D/g, '');

			// If user pastes 05..., auto-strip the leading 0 since +966 is already prefixed
			if (digits.indexOf('05') === 0) {
				digits = digits.substring(1);
			}

			// Restrict to max 9 digits
			if (digits.length > 9) {
				digits = digits.substring(0, 9);
			}

			// Update field value if sanitized
			if (raw !== digits) {
				this.$phoneInput.val(digits);
			}

			var i18n = (window.SpadaAuthData && window.SpadaAuthData.i18n) || {};
			var isArabic = (window.SpadaAuthData && window.SpadaAuthData.isRtl) ||
				$('html').attr('lang') === 'ar' ||
				$('html').attr('dir') === 'rtl' ||
				$('body').hasClass('rtl') ||
				window.location.pathname.indexOf('/ar/') !== -1;

			var mustStart5Msg = i18n.phoneMustStartWith5 || (isArabic ? 'يجب أن يبدأ رقم الجوال بالرقم 5.' : 'Phone number must start with 5.');
			var mustBe9Msg    = i18n.phoneMustBe9Digits  || (isArabic ? 'يجب أن يتكون رقم الجوال من 9 أرقام' : 'Phone number must be 9 digits');
			var requiredMsg   = i18n.phoneRequired       || i18n.invalidPhone || (isArabic ? 'يرجى إدخال رقم الجوال.' : 'Please enter your mobile number.');

			var len = digits.length;

			if (len === 0) {
				if (showEmptyError) {
					this.showPhoneError(requiredMsg);
					return false;
				} else {
					this.clearPhoneError();
					return false;
				}
			}

			// Must start with 5
			if (digits.charAt(0) !== '5') {
				this.showPhoneError(mustStart5Msg);
				return false;
			}

			// Must be 9 digits
			if (len < 9) {
				this.showPhoneError(mustBe9Msg + ' (' + len + '/9).');
				return false;
			}

			// Exactly 9 digits starting with 5 -> valid
			this.clearPhoneError();
			return true;
		},

		showPhoneError: function(msg) {
			this.$phoneInput.addClass('invalid-phone-input').attr('aria-invalid', 'true');
			if (this.$phoneWrap && this.$phoneWrap.length) {
				this.$phoneWrap.addClass('has-error');
			}
			if (this.$phoneCustomError && this.$phoneCustomError.length) {
				this.$phoneCustomError.text(msg).css('display', 'block');
			}
		},

		clearPhoneError: function() {
			this.$phoneInput.removeClass('invalid-phone-input').attr('aria-invalid', 'false');
			if (this.$phoneWrap && this.$phoneWrap.length) {
				this.$phoneWrap.removeClass('has-error');
			}
			if (this.$phoneCustomError && this.$phoneCustomError.length) {
				this.$phoneCustomError.text('').hide();
			}
		}
	};

	$(document).ready(function() {
		SpadaAuth.init();
	});

})(jQuery);
