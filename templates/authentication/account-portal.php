<?php
/**
 * SPADA Account Portal Landing Template
 *
 * Matches My Account.png (Choice between Signup and Login).
 *
 * @package Spada
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$initial_view   = 'choice';
$initial_action = 'login';
$initial_method = 'email';

if ( isset( $_GET['action'] ) ) {
	$act = sanitize_key( $_GET['action'] );
	if ( in_array( $act, array( 'login', 'signup', 'register' ), true ) ) {
		$initial_action = ( 'register' === $act ) ? 'signup' : $act;
		$initial_view   = 'methods';
	}
}

if ( isset( $_GET['step'] ) ) {
	$step = sanitize_key( $_GET['step'] );
	if ( in_array( $step, array( 'choice', 'methods', 'input', 'verify' ), true ) ) {
		$initial_view = $step;
	}
}

if ( isset( $_GET['auth_method'] ) ) {
	$m = sanitize_key( $_GET['auth_method'] );
	if ( in_array( $m, array( 'email', 'whatsapp', 'sms' ), true ) ) {
		$initial_method = $m;
	}
} elseif ( isset( $_GET['method'] ) ) {
	$m = sanitize_key( $_GET['method'] );
	if ( in_array( $m, array( 'email', 'whatsapp', 'sms' ), true ) ) {
		$initial_method = $m;
	}
}

// Fallback to cookie if query param did not specify a non-choice step
if ( 'choice' === $initial_view && ! empty( $_COOKIE['spada_auth_stage'] ) ) {
	$cookie_stage = json_decode( stripslashes( (string) $_COOKIE['spada_auth_stage'] ), true );
	if ( is_array( $cookie_stage ) && ! empty( $cookie_stage['view'] ) ) {
		$v = sanitize_key( $cookie_stage['view'] );
		if ( in_array( $v, array( 'methods', 'input', 'verify' ), true ) ) {
			$initial_view   = $v;
			$initial_action = ! empty( $cookie_stage['action'] ) ? sanitize_key( $cookie_stage['action'] ) : 'login';
			$initial_method = ! empty( $cookie_stage['method'] ) ? sanitize_key( $cookie_stage['method'] ) : 'email';
		}
	}
}
?>
<div class="spada-auth-wrapper" id="spada-auth-portal" data-initial-view="<?php echo esc_attr( $initial_view ); ?>" data-initial-action="<?php echo esc_attr( $initial_action ); ?>" data-initial-method="<?php echo esc_attr( $initial_method ); ?>">
	<!-- Hidden header: Elementor hero section already provides the page title and subtitle -->
	<div class="spada-auth-header" style="display: none !important;">
		<h1 class="spada-auth-title" id="spada-auth-main-title"><?php echo esc_html( ( 'signup' === $initial_action ) ? __( 'SIGNUP', 'spada-core' ) : __( 'LOGIN', 'spada-core' ) ); ?></h1>
		<p class="spada-auth-subtitle" id="spada-auth-main-subtitle"><?php esc_html_e( 'Please provide necessary details to access to your account.', 'spada-core' ); ?></p>
	</div>

	<div class="spada-auth-card">
		<!-- Step 0: Choice Landing (Signup vs Login) -->
		<div class="spada-auth-view <?php echo ( 'choice' === $initial_view ) ? 'is-active' : ''; ?>" id="spada-view-choice" data-view="choice">
			<div class="spada-choice-grid">
				<!-- Signup Card -->
				<a href="#" role="button" class="spada-choice-card <?php echo ( 'signup' === $initial_action ) ? 'is-selected' : ''; ?>" id="spada-choice-signup" data-action="signup">
					<div class="spada-choice-icon-wrap">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
							<circle cx="8.5" cy="7" r="4"></circle>
							<line x1="20" y1="8" x2="20" y2="14"></line>
							<line x1="23" y1="11" x2="17" y2="11"></line>
						</svg>
					</div>
					<span class="spada-choice-label"><?php esc_html_e( 'Signup', 'spada-core' ); ?></span>
				</a>

				<div class="spada-choice-divider">
					<span><?php esc_html_e( 'OR', 'spada-core' ); ?></span>
				</div>

				<!-- Login Card (active default) -->
				<a href="#" role="button" class="spada-choice-card <?php echo ( 'login' === $initial_action ) ? 'is-selected' : ''; ?>" id="spada-choice-login" data-action="login">
					<div class="spada-choice-icon-wrap">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
							<polyline points="10 17 15 12 10 7"></polyline>
							<line x1="15" y1="12" x2="3" y2="12"></line>
						</svg>
					</div>
					<span class="spada-choice-label"><?php esc_html_e( 'Login', 'spada-core' ); ?></span>
				</a>
			</div>
		</div>

		<!-- Step 1: Method Selector (My Account Login-1.png) -->
		<?php require SPADA_CORE_PATH . 'templates/authentication/method-selector.php'; ?>

		<!-- Step 2: Input Identifier (My Account Login-2.png, Login-3.png, Login-4.png) -->
		<?php require SPADA_CORE_PATH . 'templates/authentication/input-step.php'; ?>

		<!-- Step 3: OTP Verification (My Account Login-5.png, Login-6.png, Login-7.png) -->
		<?php require SPADA_CORE_PATH . 'templates/authentication/verify-step.php'; ?>
	</div>
</div>
