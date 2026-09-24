<?php

/**
 * SPADA Authentication Orchestrator
 *
 * Coordinates UI views, asset loading, localized data, and checkout login modal.
 *
 * @package Spada
 */

if (! defined('ABSPATH')) {
	exit;
}

class Spada_Auth
{

	/**
	 * Init hooks.
	 */
	public static function init()
	{
		Spada_Auth_Ajax::init();
		Spada_Mobile_Login::init();

		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_shortcode('spada_auth_portal', array(__CLASS__, 'render_auth_portal_shortcode'));

		// Hook into My Account login page
		add_action('woocommerce_before_customer_login_form', array(__CLASS__, 'render_account_portal'), 5);
	}

	/**
	 * Enqueue styles and scripts conditionally.
	 */
	public static function enqueue_assets()
	{
		$is_account = function_exists('is_account_page') && is_account_page();

		if (! $is_account) {
			return;
		}

		wp_enqueue_style(
			'spada-google-font-oswald',
			'https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'spada-authentication',
			SPADA_CORE_URL . 'assets/css/authentication.css',
			array('spada-variables', 'spada-google-font-oswald'),
			SPADA_CORE_VERSION
		);

		wp_enqueue_script(
			'spada-authentication',
			SPADA_CORE_URL . 'assets/js/authentication.js',
			array('jquery'),
			SPADA_CORE_VERSION,
			true
		);

		$is_arabic = (
			get_locale() === 'ar' ||
			get_locale() === 'ar_SA' ||
			strpos(get_locale(), 'ar') === 0 ||
			(function_exists('is_rtl') && is_rtl()) ||
			(function_exists('trp_get_locale') && strpos(trp_get_locale(), 'ar') === 0) ||
			(! empty($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], '/ar/') !== false || substr($_SERVER['REQUEST_URI'], -3) === '/ar'))
		);

		wp_localize_script(
			'spada-authentication',
			'SpadaAuthData',
			array(
				'ajaxUrl'      => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce('spada_auth_nonce'),
				'isCheckout'   => 'no',
				'checkoutUrl'  => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
				'accountUrl'   => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '',
				'homeUrl'      => home_url('/'),
				'isRtl'        => $is_arabic,
				'authHero'     => array(
					'defaultHead' => $is_arabic ? 'حسابي' : 'Account',
					'defaultDesc' => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة للوصول إلى حسابك.' : 'Please provide necessary details to access to your account.',
					'loginHead'   => $is_arabic ? 'تسجيل الدخول' : 'Login',
					'loginDesc'   => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لتسجيل الدخول إلى حسابك.' : 'Please provide necessary details to login to your account.',
					'signupHead'  => $is_arabic ? 'إنشاء حساب' : 'Sign up',
					'signupDesc'  => $is_arabic ? 'يرجى تقديم التفاصيل اللازمة لإنشاء حسابك.' : 'Please provide necessary details to sign up to your account.',
				),
				'i18n'         => array(
					'signInEmail'          => $is_arabic ? 'المتابعة عبر البريد الإلكتروني' : __('Continue with Email', 'spada-core'),
					'signInWhatsapp'       => $is_arabic ? 'المتابعة عبر واتساب' : __('Continue with Whatsapp', 'spada-core'),
					'signInSms'            => $is_arabic ? 'المتابعة عبر الرسائل القصيرة' : __('Continue with SMS', 'spada-core'),
					'signupEmail'          => $is_arabic ? 'إنشاء حساب عبر البريد الإلكتروني' : __('SignUp with Email', 'spada-core'),
					'signupWhatsapp'       => $is_arabic ? 'إنشاء حساب عبر واتساب' : __('SignUp with Whatsapp', 'spada-core'),
					'signupSms'            => $is_arabic ? 'إنشاء حساب عبر الرسائل القصيرة' : __('SignUp with SMS', 'spada-core'),
					'enterEmail'           => $is_arabic ? 'أدخل عنوان بريدك الإلكتروني' : __('Enter your email address', 'spada-core'),
					'subEmail'             => $is_arabic ? 'سنرسل رمزاً مكوناً من ستة أرقام إلى بريدك الإلكتروني.' : __("We'll send a six digit code to your email adress.", 'spada-core'),
					'enterWhatsapp'        => $is_arabic ? 'أدخل رقم الواتساب الخاص بك' : __('Enter your whatsapp number', 'spada-core'),
					'subWhatsapp'          => $is_arabic ? 'سنرسل رمزاً مكوناً من ستة أرقام إلى رقم الواتساب الخاص بك' : __("We'll send a six digit code to your whatsapp", 'spada-core'),
					'enterMobile'          => $is_arabic ? 'أدخل رقم الجوال الخاص بك' : __('Enter your mobile number', 'spada-core'),
					'subMobile'            => $is_arabic ? 'سنرسل رمزاً مكوناً من ستة أرقام إلى رقم هاتفك المحمول' : __("We'll send a six digit code to your mobile number", 'spada-core'),
					'labelEmail'           => $is_arabic ? 'عنوان البريد الإلكتروني' : __('EMAIL ADDRESS', 'spada-core'),
					'labelWhatsapp'        => $is_arabic ? 'رقم الواتساب' : __('WHATSAPP NUMBER', 'spada-core'),
					'labelMobile'          => $is_arabic ? 'رقم الجوال' : __('MOBILE NUMBER', 'spada-core'),
					'checkEmail'           => $is_arabic ? 'تحقق من بريدك الإلكتروني' : __('Check your email address', 'spada-core'),
					'checkWhatsapp'        => $is_arabic ? 'تحقق من حسابك على واتساب' : __('Check your whatsapp account', 'spada-core'),
					'checkMobile'          => $is_arabic ? 'تحقق من رسائلك النصية' : __('Check your messages', 'spada-core'),
					'promptEmail'          => $is_arabic ? 'لقد أرسلنا رمزاً مكوناً من ستة أرقام إلى عنوان بريدك الإلكتروني' : __("We've sent a six digit code to your email address", 'spada-core'),
					'promptWhatsapp'       => $is_arabic ? 'لقد أرسلنا رمزاً مكوناً من ستة أرقام إلى حسابك في واتساب على' : __("We've sent a six digit code to your whatsapp account on", 'spada-core'),
					'promptMobile'         => $is_arabic ? 'لقد أرسلنا رمزاً مكوناً من ستة أرقام إلى رقم الجوال الخاص بك' : __("We've sent a six digit code to your mobile number", 'spada-core'),
					'changeEmail'          => $is_arabic ? 'تغيير البريد الإلكتروني' : __('Change Email', 'spada-core'),
					'changeNumber'         => $is_arabic ? 'تغيير الرقم' : __('Change Number', 'spada-core'),
					'didntReceive'         => $is_arabic ? 'لم تستلم الرمز؟' : __("Didn't receive the code?", 'spada-core'),
					'didntEmail'           => $is_arabic ? 'لم يصلك البريد الإلكتروني؟' : __("Didn't receive the email?", 'spada-core'),
					'didntWhatsapp'        => $is_arabic ? 'لم تصلك الرسالة على واتساب؟' : __("Didn't receive the message on whatsapp?", 'spada-core'),
					'didntMobile'          => $is_arabic ? 'لم تصلك الرسالة على رقمك؟' : __("Didn't receive the message on number?", 'spada-core'),
					'invalidOtp'           => $is_arabic ? 'يرجى إدخال جميع الأرقام الستة لرمز التحقق.' : __('Please fill in all 6 digits of the verification code.', 'spada-core'),
					'incorrectOtp'         => $is_arabic ? 'رمز التحقق غير صحيح.' : __('Incorrect verification code.', 'spada-core'),
					'invalidEmail'         => $is_arabic ? 'يرجى إدخال عنوان بريد إلكتروني صحيح.' : __('Please enter a valid email address.', 'spada-core'),
					'invalidPhone'         => $is_arabic ? 'يرجى إدخال رقم جوال صحيح.' : __('Please enter a valid phone number.', 'spada-core'),
					'phoneMustStartWith05' => $is_arabic ? 'يجب أن يبدأ رقم الجوال بالرقم 05.' : __('Phone number must start with 05.', 'spada-core'),
					'phoneMustStartWith5'  => $is_arabic ? 'يجب أن يبدأ رقم الجوال بالرقم 05.' : __('Phone number must start with 05.', 'spada-core'),
					'phoneMustBe10Digits'  => $is_arabic ? 'يجب أن يتكون رقم الجوال من 10 أرقام' : __('Phone number must be 10 digits', 'spada-core'),
					'phoneMustBe9Digits'   => $is_arabic ? 'يجب أن يتكون رقم الجوال من 10 أرقام' : __('Phone number must be 10 digits', 'spada-core'),
					'phoneRequired'        => $is_arabic ? 'يرجى إدخال رقم الجوال.' : __('Please enter your mobile number.', 'spada-core'),
					'noAccountEmail'       => $is_arabic ? 'لم يتم العثور على حساب بهذا البريد الإلكتروني. يرجى إنشاء حساب أولاً.' : __('No account found with this email address. Please sign up first.', 'spada-core'),
					'accountExistsEmail'   => $is_arabic ? 'يوجد حساب بالفعل بهذا البريد الإلكتروني. يرجى تسجيل الدخول بدلاً من ذلك.' : __('An account with this email address already exists. Please sign in instead.', 'spada-core'),
					'noAccountPhone'       => $is_arabic ? 'لم يتم العثور على حساب برقم الجوال هذا. يرجى إنشاء حساب أولاً.' : __('No account found with this mobile number. Please sign up first.', 'spada-core'),
					'accountExistsPhone'   => $is_arabic ? 'يوجد حساب بالفعل برقم الجوال هذا. يرجى تسجيل الدخول بدلاً من ذلك.' : __('An account with this mobile number already exists. Please sign in instead.', 'spada-core'),
					'resendIn'             => $is_arabic ? 'إعادة الإرسال بعد' : __('resend in', 'spada-core'),
					'sending'              => $is_arabic ? 'جاري الإرسال...' : __('Sending...', 'spada-core'),
					'verifying'            => $is_arabic ? 'جاري التحقق...' : __('Verifying...', 'spada-core'),
					'continue'             => $is_arabic ? 'متابعة' : __('Continue', 'spada-core'),
					'loginSuccess'         => $is_arabic ? 'تم تسجيل الدخول بنجاح!' : __('Login successful!', 'spada-core'),
					'signupSuccess'        => $is_arabic ? 'تم التسجيل بنجاح!' : __('SignUp Successful!', 'spada-core'),
				),
			)
		);
	}

	/**
	 * Render the account portal.
	 */
	public static function render_account_portal()
	{
		if (is_user_logged_in()) {
			return;
		}

		static $rendered = false;
		if ($rendered) {
			return;
		}
		$rendered = true;

		include SPADA_CORE_PATH . 'templates/authentication/account-portal.php';

		// Prevent default unstyled WooCommerce forms from appearing below
		echo '<div class="spada-native-login-hidden" style="display:none !important;">';
		add_action(
			'woocommerce_after_customer_login_form',
			function () {
				echo '</div>';
?>
			<script>
				(function() {
					var wrap = document.querySelector('.spada-native-login-hidden');
					if (wrap) {
						var controls = wrap.querySelectorAll('input, select, textarea, button');
						for (var i = 0; i < controls.length; i++) {
							controls[i].removeAttribute('required');
							controls[i].removeAttribute('aria-required');
							controls[i].disabled = true;
						}
						var forms = wrap.querySelectorAll('form');
						for (var j = 0; j < forms.length; j++) {
							forms[j].setAttribute('novalidate', 'novalidate');
						}
					}
				})();
			</script>
<?php
			},
			99
		);
	}

	/**
	 * Shortcode handler.
	 */
	public static function render_auth_portal_shortcode()
	{
		ob_start();
		self::render_account_portal();
		return ob_get_clean();
	}
}
