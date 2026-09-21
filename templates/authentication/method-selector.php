<?php
/**
 * SPADA Authentication Method Selector Template
 *
 * Matches My Account Login-1.png (Choose account: Email, WhatsApp, SMS).
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="spada-auth-view" id="spada-view-methods" data-view="methods">
	<!-- Top Navigation -->
	<div class="spada-view-nav">
		<a href="#" role="button" class="spada-back-btn" id="spada-methods-back-btn" aria-label="<?php esc_attr_e( 'Back to choice', 'spada-core' ); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
		</a>
	</div>

	<div class="spada-method-header">
		<h2 class="spada-method-title"><?php esc_html_e( 'Choose account', 'spada-core' ); ?></h2>
		<p class="spada-method-subtitle"><?php esc_html_e( 'Please select any of the below to continue.', 'spada-core' ); ?></p>
	</div>

	<div class="spada-method-list">
		<!-- Option 1: Email (Selected by default) -->
		<a href="#" role="button" class="spada-method-btn is-active" id="spada-method-email" data-method="email"
		   data-login-text="<?php esc_attr_e( 'Sign in with Email', 'spada-core' ); ?>"
		   data-signup-text="<?php esc_attr_e( 'Signup using Email', 'spada-core' ); ?>">
			<div class="spada-method-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
					<polyline points="22,6 12,13 2,6"></polyline>
				</svg>
			</div>
			<span class="spada-method-text"><?php esc_html_e( 'Sign in with Email', 'spada-core' ); ?></span>
		</a>

		<!-- Option 2: WhatsApp -->
		<a href="#" role="button" class="spada-method-btn" id="spada-method-whatsapp" data-method="whatsapp"
		   data-login-text="<?php esc_attr_e( 'Sign in with Whatsapp', 'spada-core' ); ?>"
		   data-signup-text="<?php esc_attr_e( 'Signup using Whatsapp', 'spada-core' ); ?>">
			<div class="spada-method-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
				</svg>
			</div>
			<span class="spada-method-text"><?php esc_html_e( 'Sign in with Whatsapp', 'spada-core' ); ?></span>
		</a>

		<!-- Option 3: SMS -->
		<a href="#" role="button" class="spada-method-btn" id="spada-method-sms" data-method="sms"
		   data-login-text="<?php esc_attr_e( 'Sign in with SMS', 'spada-core' ); ?>"
		   data-signup-text="<?php esc_attr_e( 'Signup using SMS', 'spada-core' ); ?>">
			<div class="spada-method-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
					<line x1="8" y1="10" x2="8.01" y2="10"></line>
					<line x1="12" y1="10" x2="12.01" y2="10"></line>
					<line x1="16" y1="10" x2="16.01" y2="10"></line>
				</svg>
			</div>
			<span class="spada-method-text"><?php esc_html_e( 'Sign in with SMS', 'spada-core' ); ?></span>
		</a>
	</div>
</div>
