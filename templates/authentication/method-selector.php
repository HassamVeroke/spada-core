<?php

/**
 * SPADA Authentication Method Selector Template
 *
 * Matches My Account Login-1.png (Choose account: Email, WhatsApp, SMS).
 *
 * @package Spada
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="spada-auth-view <?php echo ( isset( $initial_view ) && 'methods' === $initial_view ) ? 'is-active' : ''; ?>" id="spada-view-methods" data-view="methods">
	<!-- Top Navigation -->
	<div class="spada-view-nav">
		<a href="#" role="button" class="spada-back-btn" id="spada-methods-back-btn" aria-label="<?php esc_attr_e('Back to choice', 'spada-core'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
		</a>
	</div>

	<div class="spada-method-header">
		<h2 class="spada-method-title"><?php esc_html_e('Choose account', 'spada-core'); ?></h2>
		<p class="spada-method-subtitle"><?php esc_html_e('Please select any of the below to continue.', 'spada-core'); ?></p>
	</div>

	<div class="spada-method-list">
		<!-- Option 1: Email (Selected by default) -->
		<a href="#" role="button" class="spada-method-btn is-active" id="spada-method-email" data-method="email"
			data-login-text="<?php esc_attr_e('Continue with Email', 'spada-core'); ?>"
			data-signup-text="<?php esc_attr_e('SignUp with Email', 'spada-core'); ?>">
			<div class="spada-method-icon">
				<svg width="62" height="41" viewBox="0 0 62 41" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M41.9341 20.2904L57.5818 6.69562V33.7299L41.9341 20.2904ZM21.7709 22.5926L27.2658 27.3625C28.1282 28.0951 29.2526 28.5378 30.4813 28.5378H30.5246H30.5577C31.7889 28.5378 32.9133 28.0926 33.7859 27.3549L33.7783 27.36L39.2732 22.5901L55.9766 36.9353H5.07514L21.7709 22.5926ZM5.05224 3.47246H56.0071L31.532 24.7295C31.258 24.9494 30.9166 25.068 30.5653 25.0653H30.5297H30.4966C30.1442 25.0678 29.8017 24.9483 29.5274 24.727L29.5299 24.7295L5.05224 3.47246ZM3.47246 6.69308L19.1176 20.2878L3.47246 33.7198V6.69308ZM58.4213 0.483347C57.8108 0.178075 57.0934 0 56.3327 0H4.72916C3.99215 0.00291743 3.26557 0.174338 2.60498 0.501154L2.63297 0.488434C1.84401 0.878882 1.17961 1.48168 0.714474 2.22904C0.249337 2.9764 0.00190632 3.8387 0 4.71899L0 35.6837C0.00134677 36.9368 0.499761 38.1383 1.38588 39.0244C2.272 39.9105 3.47345 40.409 4.72662 40.4103H56.3251C57.5783 40.409 58.7797 39.9105 59.6659 39.0244C60.552 38.1383 61.0504 36.9368 61.0517 35.6837V4.71899C61.0517 2.86955 59.9858 1.26688 58.434 0.496066L58.406 0.483347H58.4213Z" fill="#00A9BB" />
				</svg>
			</div>
			<span class="spada-method-text"><?php echo ( isset( $initial_action ) && 'signup' === $initial_action ) ? esc_html__( 'SignUp with Email', 'spada-core' ) : esc_html__( 'Continue with Email', 'spada-core' ); ?></span>
		</a>

		<!-- Option 2: WhatsApp -->
		<a href="#" role="button" class="spada-method-btn" id="spada-method-whatsapp" data-method="whatsapp"
			data-login-text="<?php esc_attr_e('Continue with Whatsapp', 'spada-core'); ?>"
			data-signup-text="<?php esc_attr_e('SignUp with Whatsapp', 'spada-core'); ?>">
			<div class="spada-method-icon">
				<svg width="51" height="51" viewBox="0 0 51 51" fill="#000" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M37.0049 30.5602C36.6947 30.4051 35.7912 29.9606 34.8826 29.5218L37.0049 30.5602ZM34.8826 29.5218C34.8802 29.5207 34.8851 29.523 34.8826 29.5218V29.5218ZM21.075 20.6845C21.0737 20.6871 21.0762 20.6819 21.075 20.6845C21.047 20.742 21.0165 20.8046 20.9899 20.8576L21.075 20.6845ZM17.8368 13.6229C17.8197 13.6228 17.8029 13.6226 17.7863 13.6222C17.8198 13.6231 17.8534 13.6229 17.8886 13.6228L17.9075 13.6228C17.9064 13.6228 17.9087 13.6228 17.9075 13.6228C17.9093 13.6228 17.9207 13.6228 17.9225 13.6228C17.9216 13.6228 17.9234 13.6228 17.9225 13.6228C17.9457 13.6228 17.972 13.6229 17.9959 13.6234M17.8886 13.6228C17.8705 13.6229 17.8545 13.6229 17.8368 13.6229L17.8886 13.6228ZM14.7807 43.3627C18.0177 45.2801 21.7194 46.2914 25.4817 46.2908C37.058 46.2908 46.4798 36.8669 46.4841 25.2862C46.4932 22.5264 45.9547 19.7923 44.8999 17.242C43.845 14.6917 42.2948 12.376 40.3389 10.429C38.3955 8.46953 36.082 6.91587 33.5329 5.85836C30.9838 4.80085 28.2499 4.26059 25.4902 4.26897C13.9032 4.26897 4.48138 13.6907 4.47714 25.2714C4.47128 29.2253 5.5844 33.1002 7.68785 36.4483L8.18719 37.243L6.06444 44.9946L14.0158 42.9079L14.7807 43.3627ZM35.1613 1.9154C38.2295 3.18761 41.0149 5.05575 43.3562 7.41168C45.7093 9.75466 47.5766 12.541 48.8459 15.6095C50.1152 18.678 50.7634 21.9677 50.753 25.2884C50.7487 39.2234 39.4103 50.5597 25.4838 50.5597H25.4732C21.2535 50.5579 17.1015 49.5001 13.3953 47.4828L0 50.9974L3.58469 37.9017C1.36885 34.0609 0.205627 29.7034 0.212494 25.2692C0.216736 11.3363 11.5551 7.05719e-05 25.4817 7.05719e-05C28.8031 -0.00780869 32.0932 0.643196 35.1613 1.9154ZM15.0742 14.5919C15.5855 14.0317 16.184 13.8995 16.5361 13.8995C16.9545 13.8995 17.3685 13.9034 17.7305 13.92C17.7893 13.9232 17.8499 13.9231 17.8994 13.9229C17.9315 13.9228 17.9612 13.923 17.9897 13.9237C18.0078 13.9241 18.0255 13.9248 18.043 13.9258C18.1302 13.931 18.2062 13.9452 18.2812 13.9805C18.4274 14.0495 18.6468 14.2361 18.8896 14.8194C19.071 15.2546 19.3396 15.9087 19.6205 16.5927C19.7471 16.901 19.8762 17.2154 20.001 17.5186C20.3981 18.4839 20.7597 19.3581 20.8447 19.5294C20.9853 19.8088 21.0473 20.0684 20.8975 20.3653C20.8631 20.4343 20.8321 20.498 20.803 20.5577C20.6478 20.8761 20.5485 21.0798 20.3076 21.3594C20.187 21.4995 20.0688 21.6435 19.954 21.7832C19.7478 22.0344 19.5525 22.2721 19.376 22.4473C19.2256 22.5967 19.0119 22.8089 18.9111 23.0811C18.7987 23.385 18.8323 23.722 19.0518 24.0987C19.425 24.7416 20.7127 26.8413 22.624 28.5469C24.6748 30.3754 26.4765 31.1577 27.3611 31.5418C27.5333 31.6166 27.6708 31.6763 27.7686 31.7257C28.1034 31.8925 28.4173 31.988 28.7207 31.9512C29.0364 31.9128 29.2827 31.7392 29.4961 31.4952C29.8603 31.0784 31.0838 29.6419 31.5176 28.9913C31.6971 28.7225 31.8385 28.6594 31.9551 28.6456C32.1039 28.6281 32.2888 28.678 32.5859 28.7872C32.8576 28.8871 33.7608 29.3124 34.6963 29.7637C35.6198 30.2094 36.5501 30.6692 36.8682 30.8282C37.0019 30.8951 37.1208 30.9524 37.228 31.0042C37.3884 31.0815 37.5235 31.1467 37.6426 31.212C37.8404 31.3204 37.9215 31.3935 37.9561 31.4512C37.957 31.4535 37.9589 31.4585 37.9614 31.4661C37.9645 31.4755 37.9684 31.4891 37.9727 31.5079C37.9828 31.553 37.9918 31.6152 37.998 31.6944C38.0105 31.853 38.0101 32.0652 37.9883 32.3194C37.9447 32.8274 37.8181 33.4868 37.5635 34.1993C37.3347 34.8393 36.6441 35.4994 35.8096 36.0343C34.9823 36.5644 34.0893 36.9239 33.54 37.0059C32.4773 37.1658 31.1493 37.229 29.6963 36.7657C28.8993 36.5142 27.9082 36.1874 26.6709 35.6778L26.125 35.4483C20.2969 32.932 16.349 27.2793 15.5772 26.1742C15.5221 26.0953 15.4832 26.0396 15.4609 26.0098C15.2986 25.7931 14.6685 24.9517 14.0811 23.794C13.486 22.6214 12.9434 21.1431 12.9434 19.6573C12.9434 16.8614 14.307 15.4095 14.9542 14.7204C14.9977 14.6742 15.0379 14.6314 15.0742 14.5919Z" fill="black" />
				</svg>
			</div>
			<span class="spada-method-text"><?php echo ( isset( $initial_action ) && 'signup' === $initial_action ) ? esc_html__( 'SignUp with Whatsapp', 'spada-core' ) : esc_html__( 'Continue with Whatsapp', 'spada-core' ); ?></span>
		</a>

		<!-- Option 3: SMS -->
		<a href="#" role="button" class="spada-method-btn" id="spada-method-sms" data-method="sms"
			data-login-text="<?php esc_attr_e('Continue with SMS', 'spada-core'); ?>"
			data-signup-text="<?php esc_attr_e('SignUp with SMS', 'spada-core'); ?>">
			<div class="spada-method-icon">
				<svg width="52" height="52" viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M4.7291 0C4.63928 0 4.54999 0.00229454 4.46126 0.00682831L4.7291 0ZM46.5619 41.8328C46.6491 41.8328 46.7358 41.8306 46.822 41.8264L46.5619 41.8328ZM4.22949 40.8701L7.99512 37.1035H47.0615V4.72949H4.22949V40.8701ZM33.9893 23.0312H38.2188V18.8018H33.9893V23.0312ZM27.7598 23.0312H23.5312V18.8018H27.7598V23.0312ZM13.0732 23.0312H17.3018V18.8018H13.0732V23.0312ZM51.291 5.22949V36.6035C51.291 39.2034 49.1614 41.333 46.5615 41.333H9.75098L0 51.084V5.22949C0 2.62963 2.12962 0.5 4.72949 0.5H46.5615C49.1614 0.5 51.291 2.62963 51.291 5.22949Z" fill="black" />
				</svg>
			</div>
			<span class="spada-method-text"><?php echo ( isset( $initial_action ) && 'signup' === $initial_action ) ? esc_html__( 'SignUp with SMS', 'spada-core' ) : esc_html__( 'Continue with SMS', 'spada-core' ); ?></span>
		</a>
	</div>

	<!-- Hidden translatable template for method texts across actions (TranslatePress translates this on server) -->
	<div class="spada-action-methods-template" style="display:none !important;" aria-hidden="true">
		<div data-action-template="login">
			<span data-method-btn-text="email"><?php esc_html_e('Continue with Email', 'spada-core'); ?></span>
			<span data-method-btn-text="whatsapp"><?php esc_html_e('Continue with Whatsapp', 'spada-core'); ?></span>
			<span data-method-btn-text="sms"><?php esc_html_e('Continue with SMS', 'spada-core'); ?></span>
		</div>
		<div data-action-template="signup">
			<span data-method-btn-text="email"><?php esc_html_e('SignUp with Email', 'spada-core'); ?></span>
			<span data-method-btn-text="whatsapp"><?php esc_html_e('SignUp with Whatsapp', 'spada-core'); ?></span>
			<span data-method-btn-text="sms"><?php esc_html_e('SignUp with SMS', 'spada-core'); ?></span>
		</div>
	</div>
</div>