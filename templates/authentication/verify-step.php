<?php
/**
 * SPADA 6-Digit OTP Verification Template
 *
 * Matches My Account Login-5.png (Email OTP), Login-6.png (WhatsApp OTP), Login-7.png (SMS OTP).
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="spada-auth-view" id="spada-view-verify" data-view="verify">
	<!-- Top Navigation -->
	<div class="spada-view-nav">
		<a href="#" role="button" class="spada-back-btn" id="spada-verify-back-btn" aria-label="<?php esc_attr_e( 'Back to identifier input', 'spada-core' ); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
		</a>
	</div>

	<!-- Centered Circle Icon -->
	<div class="spada-circle-icon-wrap" id="spada-verify-icon-wrap">
		<!-- Email Icon -->
		<svg class="spada-icon-email" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#00adb5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
			<polyline points="22,6 12,13 2,6"></polyline>
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
	<h2 class="spada-verify-heading" id="spada-verify-heading"><?php esc_html_e( 'Check your email address', 'spada-core' ); ?></h2>
	<p class="spada-verify-subheading" id="spada-verify-subheading">
		<span id="spada-verify-prompt"><?php esc_html_e( "We've sent a six digit code to your email address", 'spada-core' ); ?></span>
		<br />
		<strong id="spada-verify-target">someone@example.com</strong>
	</p>

	<!-- OTP Form -->
	<form class="spada-verify-form" id="spada-otp-form" onsubmit="return false;">
		<!-- Notice/Alert Box -->
		<div class="spada-form-notice is-hidden" id="spada-verify-notice" role="alert"></div>

		<!-- 6 OTP Input Boxes Matrix -->
		<div class="spada-otp-grid" id="spada-otp-grid" aria-label="<?php esc_attr_e( 'Enter 6-digit verification code', 'spada-core' ); ?>">
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-index="0" aria-label="<?php esc_attr_e( 'Digit 1', 'spada-core' ); ?>" required />
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="1" aria-label="<?php esc_attr_e( 'Digit 2', 'spada-core' ); ?>" required />
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="2" aria-label="<?php esc_attr_e( 'Digit 3', 'spada-core' ); ?>" required />
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="3" aria-label="<?php esc_attr_e( 'Digit 4', 'spada-core' ); ?>" required />
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="4" aria-label="<?php esc_attr_e( 'Digit 5', 'spada-core' ); ?>" required />
			<input type="text" class="spada-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="5" aria-label="<?php esc_attr_e( 'Digit 6', 'spada-core' ); ?>" required />
		</div>

		<!-- Hidden full OTP collector -->
		<input type="hidden" id="spada-otp-full" name="otp_code" value="" />

		<!-- Primary Continue Button -->
		<a href="#" role="button" class="spada-btn-primary" id="spada-verify-submit-btn">
			<span class="spada-btn-text"><?php esc_html_e( 'Continue', 'spada-core' ); ?></span>
			<span class="spada-btn-spinner is-hidden" aria-hidden="true"></span>
		</a>

		<!-- Resend Section -->
		<div class="spada-resend-wrap">
			<span id="spada-resend-prompt"><?php esc_html_e( "Didn't receive the email?", 'spada-core' ); ?></span>
			<a href="#" role="button" class="spada-resend-link" id="spada-resend-btn">
				<?php esc_html_e( 'Click to resend', 'spada-core' ); ?>
			</a>
			<span class="spada-countdown-text is-hidden" id="spada-countdown-wrap">
				(<?php esc_html_e( 'resend in', 'spada-core' ); ?> <span id="spada-countdown-sec">60</span>s)
			</span>
		</div>

		<!-- Change Email / Number Secondary Button -->
		<a href="#" role="button" class="spada-btn-outline" id="spada-change-target-btn">
			<span id="spada-change-target-text"><?php esc_html_e( 'Change Email', 'spada-core' ); ?></span>
		</a>
	</form>
</div>
