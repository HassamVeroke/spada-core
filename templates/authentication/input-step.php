<?php

/**
 * SPADA Authentication Identifier Input Template
 *
 * Matches My Account Login-2.png (Email), Login-3.png (WhatsApp), Login-4.png (SMS).
 *
 * @package Spada
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="spada-auth-view" id="spada-view-input" data-view="input">
	<!-- Top Navigation -->
	<div class="spada-view-nav">
		<a href="#" role="button" class="spada-back-btn" id="spada-input-back-btn" aria-label="<?php esc_attr_e('Back to methods', 'spada-core'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
		</a>
	</div>

	<!-- Centered Circle Icon -->
	<div class="spada-circle-icon-wrap" id="spada-input-icon-wrap">
		<!-- Email Icon -->
		<!-- <svg class="spada-icon-email" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#00adb5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
			<polyline points="22,6 12,13 2,6"></polyline>
		</svg> -->
		<svg class="spada-icon-email" width="28" height="28" viewBox="0 0 52 35" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M35.4291 17.1428L48.6494 5.65696V28.4976L35.4291 17.1428ZM18.3937 19.0879L23.0362 23.1179C23.7648 23.7369 24.7148 24.1109 25.7529 24.1109H25.7895H25.8174C26.8577 24.1109 27.8077 23.7347 28.5449 23.1114L28.5384 23.1157L33.1809 19.0858L47.2932 31.2057H4.28786L18.3937 19.0879ZM4.26851 2.9338H47.319L26.6406 20.8934C26.4091 21.0791 26.1206 21.1793 25.8239 21.1771H25.7938H25.7658C25.468 21.1792 25.1787 21.0782 24.9469 20.8912L24.9491 20.8934L4.26851 2.9338ZM2.9338 5.65481L16.152 17.1407L2.9338 28.489V5.65481ZM49.3587 0.408367C48.8429 0.150451 48.2368 0 47.5941 0H3.99555C3.37287 0.00246486 2.759 0.147294 2.20088 0.423412L2.22453 0.412666C1.55796 0.742545 0.996623 1.25183 0.603641 1.88326C0.210659 2.51469 0.0016106 3.24322 0 3.98695L0 30.1482C0.00113785 31.207 0.422235 32.2221 1.1709 32.9707C1.91956 33.7194 2.93463 34.1405 3.9934 34.1416H47.5877C48.6464 34.1405 49.6615 33.7194 50.4102 32.9707C51.1588 32.2221 51.5799 31.207 51.5811 30.1482V3.98695C51.5811 2.42441 50.6805 1.07035 49.3694 0.419114L49.3458 0.408367H49.3587Z" fill="#00A9BB" />
		</svg>
		<!-- WhatsApp Icon -->
		<svg class="spada-icon-whatsapp is-hidden" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#00adb5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
		</svg>
		<!-- SMS Icon -->
		<svg class="spada-icon-sms is-hidden" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#00adb5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			<line x1="8" y1="10" x2="8.01" y2="10"></line>
			<line x1="12" y1="10" x2="12.01" y2="10"></line>
			<line x1="16" y1="10" x2="16.01" y2="10"></line>
		</svg>
	</div>

	<!-- Titles (Dynamic via JS) -->
	<h2 class="spada-input-heading" id="spada-input-heading"><?php esc_html_e('Enter your email address', 'spada-core'); ?></h2>
	<p class="spada-input-subheading" id="spada-input-subheading"><?php esc_html_e("We'll send a six digit code to your email adress.", 'spada-core'); ?></p>

	<!-- Input Form -->
	<form class="spada-input-form" id="spada-identifier-form" onsubmit="return false;">
		<!-- Notice/Alert Box -->
		<div class="spada-form-notice is-hidden" id="spada-input-notice" role="alert"></div>

		<!-- Email Input Row -->
		<div class="spada-field-group" id="spada-field-group-email">
			<label for="spada-auth-email" class="spada-field-label"><?php esc_html_e('EMAIL ADDRESS', 'spada-core'); ?></label>
			<input type="email" id="spada-auth-email" class="spada-input-control" placeholder="name@example.com" autocomplete="email" required />
		</div>

		<!-- Phone Input Row (Used for WhatsApp and SMS) -->
		<div class="spada-field-group is-hidden" id="spada-field-group-phone">
			<label for="spada-auth-phone" class="spada-field-label" id="spada-phone-label"><?php esc_html_e('MOBILE NUMBER', 'spada-core'); ?></label>
			<div class="spada-phone-input-wrap">
				<span class="spada-phone-code">+966</span>
				<input type="tel" id="spada-auth-phone" class="spada-input-control" placeholder="5XXXXXXXX" autocomplete="tel-national" />
			</div>
		</div>

		<!-- Primary Continue Button -->
		<a href="#" role="button" class="spada-btn-primary" id="spada-input-submit-btn">
			<span class="spada-btn-text"><?php esc_html_e('Continue', 'spada-core'); ?></span>
			<span class="spada-btn-spinner is-hidden" aria-hidden="true"></span>
		</a>

		<!-- Divider OR -->
		<div class="spada-choice-divider">
			<span><?php esc_html_e('OR', 'spada-core'); ?></span>
		</div>

		<!-- Secondary Button -->
		<a href="#" role="button" class="spada-btn-outline" id="spada-input-choose-another-btn">
			<?php esc_html_e('Choose another option', 'spada-core'); ?>
		</a>
	</form>
</div>