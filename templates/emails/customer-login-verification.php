<?php

/**
 * Customer Login Verification Email
 *
 * Managed exclusively inside Spada Core plugin: templates/emails/customer-login-verification.php
 *
 * @package Spada
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

$is_rtl = ! empty($is_rtl);

$email_heading = $is_rtl
	? 'تسجيل الدخول إلى حسابك'
	: 'Login to Your Account';

$preheader = $is_rtl
	? 'استخدم رمز التحقق المكون من 6 أرقام لتسجيل الدخول بأمان إلى حسابك على سبادا.'
	: 'Use your 6-digit verification code to securely login to your SPADA account.';

$instructions = $is_rtl
	? 'استخدم رمز التحقق المكون من 6 أرقام أدناه لتسجيل الدخول بأمان إلى حسابك على سبادا:'
	: 'Use the 6-digit verification code below to securely login to your SPADA account:';

$expire_notice = $is_rtl
	? 'سينتهي صلاحية رمز التحقق هذا خلال دقيقتين. يرجى عدم مشاركة هذا الرمز مع أي شخص.'
	: 'This verification code will expire in 2 minutes. Please do not share this code with anyone.';

$ignore_notice = $is_rtl
	? 'إذا لم تكن قد طلبت رمز التحقق من تسجيل الدخول، فيرجى تجاهل هذه الرسالة الإلكترونية. حسابك لا يزال آمنًا.'
	: 'If you did not request a login verification code, please ignore this email. Your account remains secure.';

$regards = $is_rtl
	? 'مع أطيب التحيات،'
	: 'Best regards,';

$team = $is_rtl
	? 'فريق مشروبات سبادا'
	: 'SPADA Drinks Team';

/**
 * Output the email header.
 *
 * @hooked WC_Emails::email_header()
 */
if (! has_action('woocommerce_email_header') && function_exists('wc_get_template')) {
	wc_get_template('emails/email-header.php', array('email_heading' => $email_heading));
} else {
	do_action('woocommerce_email_header', $email_heading, ! empty($email) ? $email : null);
}
?>

<!-- Hidden Preheader for email clients -->
<span style="display:none !important;visibility:hidden;mso-hide:all;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
	<?php echo esc_html($preheader); ?>
</span>

<div id="spada-verification-body" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" style="font-family: 'Roboto', Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #111827; text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>; direction: <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>; padding: 10px 0;">

	<p style="margin: 0 0 24px 0; font-size: 16px; color: #374151; line-height: 1.6;">
		<?php echo esc_html($instructions); ?>
	</p>

	<!-- Prominent OTP Code Block -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0; text-align: center;">
		<tr>
			<td align="center" style="text-align: center;">
				<table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto; background-color: #f4fbfb; border: 2px dashed #00a9bb; border-radius: 12px;">
					<tr>
						<td align="center" style="padding: 16px 36px; text-align: center;">
							<span style="font-family: 'Courier New', Courier, monospace; font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #00a9bb; direction: ltr; display: inline-block; unicode-bidi: embed; line-height: 1.2;">
								<?php echo esc_html($verification_code); ?>
							</span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<p style="margin: 0 0 16px 0; font-size: 15px; color: #374151; line-height: 1.6;">
		<?php echo esc_html($expire_notice); ?>
	</p>

	<p style="margin: 0 0 24px 0; font-size: 14px; color: #6b7280; line-height: 1.6;">
		<?php echo esc_html($ignore_notice); ?>
	</p>

	<p style="margin: 0; font-size: 15px; color: #111827; line-height: 1.6;">
		<?php echo esc_html($regards); ?><br>
		<strong><?php echo esc_html($team); ?></strong>
	</p>

</div>

<?php
/**
 * Output the email footer.
 *
 * @hooked WC_Emails::email_footer()
 */
if (! has_action('woocommerce_email_footer') && function_exists('wc_get_template')) {
	wc_get_template('emails/email-footer.php');
} else {
	do_action('woocommerce_email_footer', ! empty($email) ? $email : null);
}
