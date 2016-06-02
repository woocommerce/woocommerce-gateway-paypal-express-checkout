<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

?><h3><?php _e( 'PayPal Configuration', 'woocommerce-gateway-paypal-express-checkout' ); ?></h3>
<?php
if ( $live_cert ) {
	?>
	<input type="hidden" name="woo_pp_live_api_cert_string" value="<?php echo esc_attr( base64_encode( $live_cert ) ); ?>">
	<?php
}

if ( $sb_cert ) {
	?>
	<input type="hidden" name="woo_pp_sandbox_api_cert_string" value="<?php echo esc_attr( base64_encode( $sb_cert ) ); ?>">
	<?php
}

?>

<table class="form-table ppec-settings<?php echo $enable_ips ? ' ips-enabled' : ''; ?>">
	<tr>
		<th>
			<label for="woo_pp_enabled"><?php _e( 'Enable/Disable', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_enabled_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_enabled_help" style="display: none;">
				<p>
					<h2><?php _e( 'Enable PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If this setting is enabled, buyers will be allowed to pay for their purchases using PayPal Express Checkout.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Enable PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_enabled" id="woo_pp_enabled" value="true"<?php checked( $enabled ); ?>>
				<label for="woo_pp_enabled"><?php _e( 'Enable PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>

	<?php /* defer ppc for next release
	<?php if ( 'US' === WC()->countries->get_base_country() ) : ?>
	<tr>
		<th>
			<label for="woo_pp_ppc_enabled"><?php _e( 'PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_ppc_enabled_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_ppc_enabled_help" style="display: none;">
				<p>
					<h2><?php _e( 'Enable PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>PayPal Credit allows you a convenient way to offer financing to your customers, without exposing your business to the additional risk typically involved with seller financing.  Offer your buyers a convenient way to finance their purchases with a single click!</p><p>If this setting is enabled, the PayPal Credit button will be shown to buyers on the shopping cart page:</p><div style="text-align: center;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-small.png" width="148" height="26" alt="PayPal Credit button"></div><p>When buyers click on this button, they are taken to PayPal and invited to sign up for PayPal Credit (if they are not already signed up) and to pay for their purchase using PayPal Credit.  The transaction appears no differently to you than a normal PayPal transaction &mdash; you still receive the proceeds from the transaction immediately, as you normally would.</p><p><strong>Note:</strong> PayPal recommends that you enable this option.  However, PayPal Credit is available primarily to users in the United States; if most of your buyers come from outside the United States, you may want to disable this option.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Enable PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_ppc_enabled" id="woo_pp_ppc_enabled" value="true"<?php checked( $ppc_enabled ); ?>>
				<label for="woo_pp_ppc_enabled"><?php _e( 'Enable PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>
	<?php endif; ?>
	*/ ?>

	<tr>
		<th>
			<label for="woo_pp_environment"><?php _e( 'Environment', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_environment_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_environment_help" style="display: none;">
				<p>
					<h2><?php _e( 'Environment', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>This setting specifies whether you will process live transactions, or whether you will process simulated transactions using the PayPal Sandbox.</p><ul style="list-style: disc inside;"><li>If you set this to <strong>Live</strong>, your site will process live transactions using the live PayPal site.</li><li>If you set this to <strong>Sandbox</strong>, transactions will be simulated using the PayPal Sandbox.</li></ul><p>To get started with the PayPal Sandbox, go to <a href="https://developer.paypal.com" target="_blank">https://developer.paypal.com</a>.</p><p><strong>Note:</strong> The PayPal Sandbox is completely isolated from the live site.  If you have a PayPal account onthe live PayPal site, it does not necessarily mean that you have an account on the PayPal Sandbox, and vice-versa.  For this reason, we maintain two separate sets of API credentials for you &mdash; one set for the live site, and one set for the Sandbox.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Environment', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_environment" id="woo_pp_environment">
					<option value="live"<?php selected( $environment, 'live' ) ?>><?php _e( 'Live', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="sandbox"<?php selected( $environment, 'sandbox' ) ?>><?php _e( 'Sandbox', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
<?php if ( $enable_ips ) { ?>
	<tr class="woo_pp_live">
		<th>
			<?php _e( 'Easy Setup', 'woocommerce-gateway-paypal-express-checkout' ); ?>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_easy_setup_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_easy_setup_help" style="display: none;">
				<p>
					<h2><?php _e( 'Easy Setup', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Easy Setup allows you to set up your PayPal account and get API credentials all in one easy process.  Just click on the link and follow the steps provided.  You\'ll have your PayPal account up and running in seconds!</p><p><strong>Note:</strong> If you get an error message on PayPal saying that credentials already exist for your account, just come back to the settings page and click the "(Click here if you need certificate credentials)" link.</p><p>If you know that your account already has API certificate credentials, just click the "(Click here if you need certificate credentials)" link instead.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>

			<a href="<?php echo esc_url( wc_gateway_ppec()->ips->get_signup_url( 'live' ) ); ?>" class="button button-primary"><?php _e( 'Click Here to Set Up Your PayPal Account', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
			<?php
			/* Disable certificate-style until middleware support it. Maybe in v1.1.
			<a href="<?php echo esc_url( $ips_url ); ?>&amp;mode=certificate&amp;env=live" class="button"><?php _e( 'Click here if you need certificate credentials', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
			*/ ?>

			<br>
			<a href="#" class="toggle-api-credential-fields api-credential-fields-hidden" style="display: inline-block; margin-top: 10px;" data-hide-text="<?php _e( 'Hide credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?>" data-show-text="<?php _e( 'Show credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?>"><?php _e( 'Show credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
		</td>
	</tr>
<?php } ?>
	<tr class="woo_pp_live api-credential-row">
		<th>
			<label for="woo_pp_live_api_style"><?php _e( 'Live API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_api_style_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_api_style_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>To process PayPal transactions using Express Checkout, you must have a PayPal Business account and a set of API credentials from PayPal.  If you\'ve processed PayPal transactions in the past on another site (not including eBay), you may already have API credentials from PayPal.  Otherwise, you will need to request a set from PayPal.</p><p>When you request your API credentials, PayPal gives you have the choice of selecting between an API signature or an API certificate.  This setting allows you to specify which type of credentials you have.</p><p>If you already have API credentials from PayPal, simply select which type you have.</p><p>If you aren\'t sure whether or not you have API credentials, follow these steps:<ol style="list-style: decimal outside;"><li>Log in to your PayPal account at <a href="https://www.paypal.com" target="_blank">https://www.paypal.com</a>.</li><li>The next few steps will differ depending on your account settings.<ul style="list-style: disc outside; margin-left: 15px;"><li>Do you see a set of tabs at the top of the page that read <strong>Money</strong>, <strong>Transactions</strong>, <strong>Customers</strong>, <strong>Tools</strong>, and <strong>More</strong>?  If so:<ol style="list-style: lower-roman outside;"><li>Click the Business Profile button.  (It\'s in the upper-right corner of the page, immediately to the left of the <strong>Log Out</strong> button.)</li><li>Click <strong>Profile and settings</strong>.</li><li>On the left-hand side of the page, click <strong>My selling tools</strong>.</li><li>Locate <strong>API access</strong> in the list of settings.  Click the <strong>Update</strong> link immediately to the right of it.</li></ol></li><li>Look in your browser\'s address bar.  Does the URL start with <strong>https://paypalmanager.paypal.com/</strong>?  If so:<ol style="list-style: lower-roman outside;"><li>Click <strong>Profile</strong>.  (It will be in the row of links underneath the <strong>My Account</strong> tab.)</li><li>Click <strong>Request API credentials</strong>.  (It will be in the <strong>Account information</strong> section.)</li><li>Click <strong>Set up PayPal API credentials and permissions</strong>.  (It will be in the <strong>Option 1 - PayPal API</strong> box.)</li></ol></li><li>Otherwise, follow these steps:<ol style="list-style: lower-roman outside;"><li>Under <strong>Profile</strong>, click <strong>My Selling Tools</strong>.</li><li>Locate <strong>API access</strong> in the list of settings.  Click the <strong>Update</strong> link immediately to the right of it.</li></ol></li></ul></li><li>Look in the <strong>Option 2</strong> box.  This box will have a link in it that says <strong>View API Signature</strong>, <strong>View API Certificate</strong>, or <strong>Request API credentials</strong>.<ul style="list-style: disc outside; margin-left: 15px;"><li>If the link says <strong>View API Signature</strong> or <strong>View API Certificate</strong>, you already have PayPal API credentials.  Click on the link to view them.</li><li>If the link says <strong>Request API credentials</strong>, you do not yet have API credentials.  Click the link to request your API credentials.  (<strong>Note:</strong> If you are requesting a new set of API credentials, we recommend that you request an API certificate.)</li></ul></li><li>Once you have your API credentials, simply copy them into the spaces provided.</li></ol></p><p><strong>Note:</strong> PayPal only allows you to have one set of credentials at a time.  If you request an API signature, then later discover you need an API certificate (or vice-versa), you will have to delete your existing credentials before requesting a new set.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_live_api_style" id="woo_pp_live_api_style">
					<option value="signature"<?php selected( $live_style, 'signature' ); ?>><?php _e( 'API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="certificate"<?php selected( $live_style, 'certificate' ); ?>><?php _e( 'API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_live api-credential-row">
		<th>
			<label for="woo_pp_live_api_username"><?php _e( 'Live API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_api_username_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_api_username_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API username provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the username that they give you.  This is <strong>not</strong> the same as the email address you use to log in to <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Live API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="text" name="woo_pp_live_api_username" id="woo_pp_live_api_username" size="40" value="<?php echo esc_attr( $live_api_username ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_live api-credential-row">
		<th>
			<label for="woo_pp_live_api_password"><?php _e( 'Live API password', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_api_password_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_api_password_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live API password', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API password provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the password that they give you.  This is <strong>not</strong> the same as the password you use to log in to <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Live API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live API password' ); ?></span></legend>
				<input type="password" name="woo_pp_live_api_password" id="woo_pp_live_api_password" size="40" value="<?php echo esc_attr( $live_api_pass ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_live_signature api-credential-row">
		<th>
			<label for="woo_pp_live_api_signature"><?php _e( 'Live API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_api_signature_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_api_signature_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API signature provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the signature that they give you.  This is <strong>not</strong> the same as the email address or password that you use to log in to <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Live API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="password" name="woo_pp_live_api_signature" id="woo_pp_live_api_signature" size="40" value="<?php echo esc_attr( $live_api_sig ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_live_certificate api-credential-row">
		<th>
			<label for="woo_pp_live_api_certificate"><?php _e( 'Live API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_api_certificate_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_api_certificate_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>When you request API credentials from PayPal, and you choose the API certificate option, PayPal provides you with the certificate as a file.  This file is typically called <strong>cert_key_pem.txt</strong>.  Upload the file using this setting.</p><p><strong>Note:</strong> Upload the file exactly as it was provided to you by PayPal.  The name of the file doesn\'t matter, but you must not modify the contents of the file.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Live API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<p><strong><?php _e( 'Certificate status:', 'woocommerce-gateway-paypal-express-checkout' ); ?></strong> <?php echo $live_cert_info; ?></p>
				<p><span style="font-style: italic;"><?php _e( 'Upload a new certificate:', 'woocommerce-gateway-paypal-express-checkout' ); ?></span> <input type="file" name="woo_pp_live_api_certificate" id="woo_pp_live_api_certificate">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_live api-credential-row">
		<th>
			<label for="woo_pp_live_subject">Live subject</label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_live_subject_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_live_subject_help" style="display: none;">
				<p>
					<h2><?php _e( 'Live subject', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If you\'re processing transactions on behalf of someone else\'s PayPal account, enter their email address or Secure Merchant Account ID (also known as a Payer ID) here.  Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.</p><p>Most people won\'t need to use this setting.  If you\'re not sure what to put here, leave it blank.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Live subject', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="text" name="woo_pp_live_subject" size="40" value="<?php echo esc_attr( $live_subject ); ?>">
			</fieldset>
		</td>
	</tr>
<?php if ( $enable_ips ) { ?>
	<tr class="woo_pp_sandbox">
		<th>
			<?php _e( 'Easy Setup', 'woocommerce-gateway-paypal-express-checkout' ); ?>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_easy_setup_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<a href="<?php echo esc_url( wc_gateway_ppec()->ips->get_signup_url( 'sandbox' ) ); ?>" class="button button-primary"><?php _e( 'Click Here to Set Up Your PayPal Account', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
			<?php
			/* Disable certificate-style until middleware support it. Maybe in v1.1.
			<a href="<?php echo esc_url( $ips_url ); ?>&amp;mode=certificate&amp;env=sandbox" class="button"><?php _e( 'Click here if you need certificate credentials', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
			*/ ?>

			<br>
			<a href="#" class="toggle-api-credential-fields api-credential-fields-hidden" style="display: inline-block; margin-top: 10px;" data-hide-text="<?php _e( 'Hide credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?>" data-show-text="<?php _e( 'Show credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?>"><?php _e( 'Show credential fields', 'woocommerce-gateway-paypal-express-checkout' ); ?></a>
		</td>
	</tr>
<?php } ?>
	<tr class="woo_pp_sandbox api-credential-row">
		<th>
			<label for="woo_pp_sandbox_api_style"><?php _e( 'Sandbox API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_api_style_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_api_style_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>This setting allows you to specify whether the API credentials for your Sandbox account contain an API signature or an API certificate.</p><p>To process PayPal transactions on the PayPal Sandbox using Express Checkout, you must:</p><ul style="list-style: disc outside; margin-left: 20px;"><li>Have a live PayPal account (it doesn\'t matter if you have a Personal or Business account)</li><li>Sign in to <a href="https://developer.paypal.com" target="_blank">http://developer.paypal.com</a> using the email address and password from your live PayPal account.</li><li>Create at least one PayPal Business account and one PayPal Personal account on the Sandbox.  The Business account will represent you, as the merchant, and the Personal account will represent your buyer.  (<strong>Note:</strong> PayPal will usually create a Personal and a Business account on the Sandbox for you the first time you log in.)</li></ul><p>Typically, when you create a PayPal Business account on the Sandbox, PayPal will generate a set of credentials for you.  These credentials will usually have an API signature, so most people can select <strong>API signature</strong> here.</p><p>To retrieve the API credentials for your Sandbox account, follow these steps:</p><ol style="list-style: decimal outside;"><li>Go to <a href="https://developer.paypal.com" target="_blank">https://developer.paypal.com</a> and sign in using the email address and password from your live PayPal account.</li><li>Click <strong>Dashboard</strong>.</li><li>Under <strong>Sandbox</strong>, click <strong>Accounts</strong>.</li><li>In the list of accounts, click on the email address of your Business account.  (If you do not have a business account, click <strong>Create Account</strong> to create a new account.)</li><li>Click the <strong>Profile</strong> link that appears underneath the account\'s email address.</li><li>Click the <strong>API credentials</strong> tab.</li><li>Copy and paste the API credentials into the spaces provided.</li></ol><p><strong>Note:</strong> If you see a username and password, but not a signature, the account has an API certificate attached to it.  To retrieve the API certificate, follow these steps:<ol style="list-style: decimal outside;"><li>Log in to the account at <a href="https://www.sandbox.paypal.com" target="_blank">https://www.sandbox.paypal.com</a>.</li><li>The next few steps will differ depending on your account settings.<ul style="list-style: disc outside; margin-left: 15px;"><li>Do you see a set of tabs at the top of the page that read <strong>Money</strong>, <strong>Transactions</strong>, <strong>Customers</strong>, <strong>Tools</strong>, and <strong>More</strong>?  If so:<ol style="list-style: lower-roman outside;"><li>Click the Business Profile button.  (It\'s in the upper-right corner of the page, immediately to the left of the <strong>Log Out</strong> button.)</li><li>Click <strong>Profile and settings</strong>.</li><li>On the left-hand side of the page, click <strong>My selling tools</strong>.</li><li>Locate <strong>API access</strong> in the list of settings.  Click the <strong>Update</strong> link immediately to the right of it.</li></ol></li><li>Look in your browser\'s address bar.  Does the URL start with <strong>https://paypalmanager.sandbox.paypal.com/</strong>?  If so:<ol style="list-style: lower-roman outside;"><li>Click <strong>Profile</strong>.  (It will be in the row of links underneath the <strong>My Account</strong> tab.)</li><li>Click <strong>Request API credentials</strong>.  (It will be in the <strong>Account information</strong> section.)</li><li>Click <strong>Set up PayPal API credentials and permissions</strong>.  (It will be in the <strong>Option 1 - PayPal API</strong> box.)</li></ol></li><li>Otherwise, follow these steps:<ol style="list-style: lower-roman outside;"><li>Under <strong>Profile</strong>, click <strong>My Selling Tools</strong>.</li><li>Locate <strong>API access</strong> in the list of settings.  Click the <strong>Update</strong> link immediately to the right of it.</li></ol></li></ul></li><li>Click <strong>View API certificate</strong>.  (It will be in the <strong>Option 2</strong> box, on the right-hand side of the page.)</li><li>Click <strong>Download Certificate</strong>.  Your API certificate will be downloaded to your computer.</li></ol></p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox API credential type', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_sandbox_api_style" id="woo_pp_sandbox_api_style">
					<option value="signature"<?php selected( $sb_style, 'signature' ); ?>><?php _e( 'API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="certificate"<?php selected( $sb_style, 'certificate' ); ?>><?php _e( 'API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_sandbox api-credential-row">
		<th>
			<label for="woo_pp_sandbox_api_username"><?php _e( 'Sandbox API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_api_username_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_api_username_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API username provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the username that they give you.  This is <strong>not</strong> the same as the email address you use to log in to <a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a>, <a href="https://www.sandbox.paypal.com" target="_blank">www.sandbox.paypal.com</a> or <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Sandbox API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox API username', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="text" name="woo_pp_sandbox_api_username" id="woo_pp_sandbox_api_username" size="40" value="<?php echo esc_attr( $sb_api_username ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_sandbox api-credential-row">
		<th>
			<label for="woo_pp_sandbox_api_password"><?php _e( 'Sandbox API password', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_api_password_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_api_password_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox API password', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API password provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the password that they give you.  This is <strong>not</strong> the same as the password you use to log in to <a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a>, <a href="https://www.sandbox.paypal.com" target="_blank">www.sandbox.paypal.com</a> or <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Sandbox API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox API password', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="password" name="woo_pp_sandbox_api_password" id="woo_pp_sandbox_api_password" size="40" value="<?php echo esc_attr( $sb_api_pass ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_sandbox_signature api-credential-row">
		<th>
			<label for="woo_pp_sandbox_api_signature"><?php _e( 'Sandbox API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_api_signature_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_api_signature_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Enter the API signature provided to you by PayPal.</p><p><strong>Note:</strong> This value is generated for you by PayPal.  You must use the signature that they give you.  This is <strong>not</strong> the same as the email address or password that you use to log in to <a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a>, <a href="https://www.sandbox.paypal.com" target="_blank">www.sandbox.paypal.com</a> or <a href="https://www.paypal.com" target="_blank">www.paypal.com</a>.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Sandbox API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox API signature', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="password" name="woo_pp_sandbox_api_signature" id="woo_pp_sandbox_api_signature" size="40" value="<?php echo esc_attr( $sb_api_sig ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_sandbox_certificate api-credential-row">
		<th>
			<label for="woo_pp_sandbox_api_certificate"><?php _e( 'Sandbox API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_api_certificate_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_api_certificate_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>When you request API credentials from PayPal, and you choose the API certificate option, PayPal provides you with the certificate as a file.  This file is typically called <strong>cert_key_pem.txt</strong>.  Upload the file using this setting.</p><p><strong>Note:</strong> Upload the file exactly as it was provided to you by PayPal.  The name of the file doesn\'t matter, but you must not modify the contents of the file.</p><p>For help on retrieving your API credentials, click on the help for the <strong>Sandbox API credential type</strong> setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox API certificate', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<p><strong><?php _e( 'Certificate status:', 'woocommerce-gateway-paypal-express-checkout' ); ?></strong> <?php echo $sb_cert_info; ?></p>
				<p><span style="font-style: italic;"><?php _e( 'Upload a new certificate:', 'woocommerce-gateway-paypal-express-checkout' ); ?></span> <input type="file" name="woo_pp_sandbox_api_certificate" id="woo_pp_sandbox_api_certificate"></p>
			</fieldset>
		</td>
	</tr>
	<tr class="woo_pp_sandbox api-credential-row">
		<th>
			<label for="woo_pp_sandbox_subject"><?php _e( 'Sandbox subject', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_sandbox_subject_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_sandbox_subject_help" style="display: none;">
				<p>
					<h2><?php _e( 'Sandbox subject', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If you\'re processing transactions on behalf of another PayPal account, enter the email address or Secure Merchant Account ID (also known as a Payer ID) of the other account here.  Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.</p><p>Most people won\'t need to use this setting.  If you\'re not sure what to put here, leave it blank.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Sandbox subject', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="text" name="woo_pp_sandbox_subject" id="woo_pp_sandbox_subject" size="40" value="<?php echo esc_attr( $sb_subject ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_enable_in_context_checkout"><?php _e( 'In-Context Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_enable_in_context_checkout_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_enable_in_context_checkout_help" style="display: none;">
				<p>
					<h2><?php _e( 'Enable In-Context Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>PayPal offers a new in-context checkout experience, which allows you to show the PayPal checkout in a minibrowser on top of your checkout.  This experience can help to improve conversion on your store by reassuring buyers that they have not left your store.</p><p>More information on in-context checkout is available from <a href="https://developer.paypal.com/docs/classic/express-checkout/in-context/">the PayPal Developer Portal</a>.</p><p>If you want to use the new in-context checkout, enable this setting.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>

					<img src="<?php echo esc_url( wc_gateway_ppec()->plugin_url . 'assets/img/in-context-composite.png' ); ?>" width="378" height"299">
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Enable In-Context Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_icc_enabled" id="woo_pp_enable_in_context_checkout"<?php checked( $icc_enabled ); ?> value="true">
				<label for="woo_pp_enable_in_context_checkout"><?php _e( 'Enable In-Context Checkout', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_enable_logging"><?php _e( 'Enable logging', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_enable_logging_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_enable_logging_help" style="display: none;">
				<p>
					<h2><?php _e( 'Enable Logging', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If this setting is enabled, some informations will be logged to a log file. The log is accessible via WooCommerce &gt; System Status &gt; Logs. From the dropdown, select filename with prefix <code>wc_gateway_ppec</code></p> then click View.', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Enable logging', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_logging_enabled" id="woo_pp_enable_logging"<?php checked( $logging_enabled ); ?> value="true">
				<label for="woo_pp_enable_logging"><?php _e( 'Enable logging', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_button_size"><?php _e( 'Button size', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_button_size_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_button_size_help" style="display: none;">
				<p>
					<h2><?php _e( 'Button size', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>PayPal offers different sizes of the "PayPal Checkout" and "PayPal Credit" buttons, allowing you to select a size that best fits your site\'s theme.  This setting will allow you to choose which size button(s) appear on your cart page:</p><table><tr><th style="text-align: right;">Small:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-small.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-small.png"></td></tr><tr><th style="text-align: right;">Medium:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-medium.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-medium.png"></td></tr><tr><th style="text-align: right;">Large:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-large.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-large.png"></td></tr></table>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Button size', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_button_size">
					<option value="<?php echo WC_Gateway_PPEC_Settings::buttonSizeSmall; ?>"<?php selected( $button_size, WC_Gateway_PPEC_Settings::buttonSizeSmall ); ?>><?php _e( 'Small', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::buttonSizeMedium; ?>"<?php selected( $button_size, WC_Gateway_PPEC_Settings::buttonSizeMedium ); ?>><?php _e( 'Medium', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_mark_size"><?php _e( 'Mark size', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_mark_size_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_mark_size_help" style="display: none;">
				<p>
					<h2><?php _e( 'Mark size', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>PayPal offers different sizes of the PayPal and PayPal Credit logos, allowing you to select a size that best fits your site\'s theme.  This setting will allow you to choose which size logo(s) appear in the list of payment methods in the checkout:</p><table><tr><th style="text-align: right;">Small:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-small.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-small.png"></td></tr><tr><th style="text-align: right;">Medium:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-medium.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-medium.png"></td></tr><tr><th style="text-align: right;">Large:</th><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-large.png"></td><td style="padding: 3px;"><img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-large.png"></td></tr></table>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Mark size', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_mark_size">
					<option value="<?php echo WC_Gateway_PPEC_Settings::markSizeSmall; ?>"<?php selected( $mark_size, WC_Gateway_PPEC_Settings::markSizeSmall ); ?>><?php _e( 'Small', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::markSizeMedium; ?>"<?php selected( $mark_size, WC_Gateway_PPEC_Settings::markSizeMedium ); ?>><?php _e( 'Medium', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::markSizeLarge; ?>"<?php selected( $mark_size, WC_Gateway_PPEC_Settings::markSizeLarge ); ?>><?php _e( 'Large', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_logo_image_url"><?php _e( 'Logo image URL', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_logo_image_url_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_logo_image_url_help" style="display: none;">
				<p>
					<h2><?php _e( 'Logo image URL', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If you want PayPal to co-brand the checkout page with your logo, enter the URL of your logo image here.  The logo image must be no larger than 190x60, and should be in a format understood by most browsers (such as GIF, PNG, or JPG).</p><p><strong>Note:</strong> The URL you enter here should be on an HTTPS site (e.g., the URL should start with https://).  If it is not, some browsers may display a warning or refuse to show the image.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Logo image URL', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="url" name="woo_pp_logo_image_url" id="woo_pp_logo_image_url" size="80" value="<?php echo esc_url( $logo_image_url ); ?>">
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_payment_action"><?php _e( 'Payment type', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_payment_action_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_payment_action_help" style="display: none;">
				<p>
					<h2><?php _e( 'Payment type', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>PayPal supports three payment types: Sale, Authorization, and Order.</p><ul style="list-style: disc outside; margin-left: 20px;"><li><strong>Sale:</strong> Sale transactions complete as soon as the buyer finishes checking out on your site.  The proceeds from the transaction are deposited into your PayPal account immediately (barring other factors, such as eChecks or other payment holds).  Sale transactions cannot be captured or voided, but they can be refunded.</li><li><strong>Authorization:</strong> Authorization transactions place a hold on the buyer\'s funds at the time of checkout, but does not move the funds from the buyer\'s account.  Funds are held for three days.  To move money to your account, you must perform a capture within 29 days of the time the buyer checks out.</li><li><strong>Order:</strong> Orders represent an open-to-buy on the buyer\'s account &mdash; they do not move money or hold funds, but you can authorize and capture against them later.  Orders are generally valid for 29 days from the time the buyer checks out.  Orders are handy when a product is going to be backordered or you have to split an order into multiple shipments.</li></ul>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Payment type', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_payment_action" id="woo_pp_payment_action">
					<option value="<?php echo WC_Gateway_PPEC_Settings::PaymentActionSale; ?>"<?php selected( $payment_action, WC_Gateway_PPEC_Settings::PaymentActionSale ); ?>><?php _e( 'Sale', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::PaymentActionAuthorization; ?>"<?php selected( $payment_action, WC_Gateway_PPEC_Settings::PaymentActionAuthorization ); ?>><?php _e( 'Authorization', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_allow_guest_checkout"><?php _e( 'Guest payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_allow_guest_checkout_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_allow_guest_checkout_help" style="display: none;">
				<p>
					<h2><?php _e( 'Allow buyers to pay without a PayPal account', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>This setting controls whether buyers can pay through PayPal without creating a PayPal account.</p><p>Unless you have a compelling reason to disable this setting, we recommend that you leave it enabled.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Guest payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_allow_guest_checkout" id="woo_pp_allow_guest_checkout"<?php checked( $allow_guest_checkout ); ?> value="true">
				<label for="woo_pp_allow_guest_checkout"><?php _e( 'Allow buyers to pay without a PayPal account', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_block_echecks"><?php _e( 'Instant payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_block_echecks_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_block_echecks_help" style="display: none;">
				<p>
					<h2><?php _e( 'Require instant payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks).  Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.</p><p>If you sell virtual and/or downloadable goods, it may make more sense to enable this option (as buyers who are expecting instant fulfillment may be frustrated when they realize that they have to wait 3-5 days to receive their purchase).  However, if you sell physical goods, we recommend that you leave this option disabled, as it will allow buyers to purchase from you who may have otherwise been unable to.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Instant payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_block_echecks" id="woo_pp_block_echecks"<?php checked( $block_echecks ); ?> value="true">
				<label for="woo_pp_block_echecks"><?php _e( 'Require instant payments', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>

	<tr id="woo_pp_req_ba_row">
		<th>
			<label for="woo_pp_req_billing_address"><?php _e( 'Billing address', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_req_billing_address_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_req_billing_address_help" style="display: none;">
				<p>
					<h2><?php _e( 'Require buyers to provide their billing address', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Normally, PayPal does not share the buyer\'s billing details with you.  However, there are times when you must collect the buyer\'s billing address to fulfill an essential business function (such as determining whether you must charge the buyer tax).</p><p>If you need the buyer\'s billing address to fulfill an essential business function, enable this setting.  Buyers will be notified during the PayPal checkout that you require their billing address to process the transaction.</p><p>Remember, PayPal will always provide you with the buyer\'s country, even if you do not enable this setting.</p><p><strong>Note: Do not enable this setting unless you have been approved by PayPal to do so.</strong></p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Billing address', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<input type="checkbox" name="woo_pp_req_billing_address" id="woo_pp_req_billing_address"<?php checked( $require_billing_address ); ?> value="true">
				<label for="woo_pp_req_billing_address"><?php _e( 'Require buyers to provide their billing address', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			</fieldset>
		</td>
	</tr>

	<tr>
		<th>
			<label for="woo_pp_zero_subtotal_behavior"><?php _e( 'Zero subtotal behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_zero_subtotal_behavior_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_zero_subtotal_behavior_help" style="display: none;">
				<p>
					<h2><?php _e( 'Zero subtotal behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>When a buyer opts to check out with PayPal, the contents of the buyer\'s shopping cart are sent over to PayPal and displayed on the PayPal checkout pages.  PayPal does not have an intrinsic way of handling coupons, so any coupons that the buyer has redeemed are presented to PayPal as line items with a negative price.</p><p>PayPal requires that the subtotal of all line items be greater than zero.  (The subtotal of all line items is the total of all the items in the cart, minus any coupons, but before shipping, handling, and tax are calculated.)  However, in some situations, the subtotal is zero or less than zero &mdash; for example, if you offer an item that is free with shipping and handling, or when the buyer has redeemed enough coupons to bring their subtotal down to zero.  PayPal cannot handle this situation, so this setting controls what will happen in this scenario:</p><ul style="list-style: disc outside; margin-left: 20px;"><li><strong>Modify line items prices and add a shipping discount:</strong> When this option is selected, an item called "Discount Offset" will be added to the list of line items sent to PayPal.  This will raise the item subtotal above zero.  A corresponding discount will be passed to PayPal to offset this amount; it will show up on PayPal as "Shipping Discount".</li><li><strong>Don\'t send line items to PayPal:</strong> When this option is selected, line items will not be passed to PayPal.  The buyer will not see the amount they are paying or any of the items they are paying for in the PayPal checkout.</li><li><strong>Send the coupons to PayPal as a shipping discount:</strong> When this option is selected, any coupons in the buyer\'s cart will be aggregated together and passed as a single discount to PayPal.  The discount will appear on the PayPal Checkout as "Shipping Discount".</li></ul><p><strong>Note:</strong> Regardless of which option you select, the total of the buyer\'s purchase will not change.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Zero subtotal behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_zero_subtotal_behavior" id="woo_pp_zero_subtotal_behavior">
					<option value="<?php echo WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorModifyItems; ?>"<?php selected( $zero_subtotal_behavior, WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorModifyItems ); ?>><?php _e( 'Modify line item prices and add a shipping discount', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorOmitLineItems; ?>"<?php selected( $zero_subtotal_behavior, WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorOmitLineItems ); ?>><?php _e( 'Don\'t send line items to PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorPassCouponsAsShippingDiscount; ?>"<?php selected( $zero_subtotal_behavior, WC_Gateway_PPEC_Settings::zeroSubtotalBehaviorPassCouponsAsShippingDiscount ); ?>><?php _e( 'Send the coupons to PayPal as a shipping discount', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
				</select>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th>
			<label for="woo_pp_subtotal_mismatch_behavior"><?php _e( 'Subtotal mismatch behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></label>
			<a href="#TB_inline?width=800&amp;height=600&amp;inlineId=woo_pp_subtotal_mismatch_behavior_help" class="thickbox"><img src="<?php echo esc_url( $help_image_url ); ?>" class="help_tip" style="cursor: pointer;" height="16" width="16" data-tip="<?php _e( 'Click here for help with this option.', 'woocommerce-gateway-paypal-express-checkout' ); ?>"></a>
		</th>
		<td>
			<div id="woo_pp_subtotal_mismatch_behavior_help" style="display: none;">
				<p>
					<h2><?php _e( 'Subtotal mismatch behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></h2>
					<?php _e( '<p>Internally, WooCommerce calculates line item prices and taxes out to four decimal places; however, PayPal can only handle amounts out to two decimal places (or, depending on the currency, no decimal places at all).  Occasionally, this can cause discrepancies between the way WooCommerce calculates prices versus the way PayPal calculates them.  Consider the following example:</p><p>You have an item that you sell for $0.0130 each, and a buyer buys 200 of them.  When this line item is sent over to PayPal, it must be shortened to $0.01 each.</p><table style="border: 1px solid gray; border-collapse: collapse;"><tr><th style="border: 1px solid gray;">&nbsp;</th><th style="border: 1px solid gray; text-align: center; padding: 2px;">WooCommerce</th><th style="border: 1px solid gray; text-align: center; padding: 2px;">PayPal</th></tr><tr><th style="border: 1px solid gray; text-align: right; padding: 2px;">Unit Price</th><td style="border: 1px solid gray; text-align: center; padding: 2px;">$0.0130</td><td style="border: 1px solid gray; text-align: center; padding: 2px;">$0.01</td></tr><tr><th style="border: 1px solid gray; text-align: right; padding: 2px;">Quantity</th><td style="border: 1px solid gray; text-align: center; padding: 2px;">200</td><td style="border: 1px solid gray; text-align: center; padding: 2px;">200</td></tr><tr><th style="border: 1px solid gray; text-align: right; padding: 2px;">Total</th><th style="border: 1px solid gray; text-align: center; padding: 2px;">$2.60</th><th style="border: 1px solid gray; text-align: center; padding: 2px;">$2.00</th></tr></table><p>Discrepancies like this will cause PayPal to reject the transaction.  This setting, therefore, controls what happens when a situation like this arises:</p><ul style="list-style: disc outside; margin-left: 20px;"><li><strong>Add another line item:</strong> When this option is selected, an extra line item will be sent to PayPal that will represent the difference between the way WooCommerce calculated the price versus the way PayPal would calculate it.  This line item will appear on the PayPal checkout as "Line Item Amount Offset".</li><li><strong>Don\'t send line items to PayPal:</strong> When this option is selected, line items will not be passed to PayPal.  The buyer will not see the amount they are paying or any of the items they are paying for in the PayPal checkout.</li></ul><p><strong>Note:</strong> Regardless of which option you select, the total of the buyer\'s purchase will not change.</p>', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</p>
			</div>
			<fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Subtotal mismatch behavior', 'woocommerce-gateway-paypal-express-checkout' ); ?></span></legend>
				<select name="woo_pp_subtotal_mismatch_behavior" id="woo_pp_subtotal_mismatch_behavior">
					<option value="<?php echo WC_Gateway_PPEC_Settings::subtotalMismatchBehaviorAddLineItem; ?>"<?php selected( $subtotal_mismatch_behavior, WC_Gateway_PPEC_Settings::subtotalMismatchBehaviorAddLineItem ); ?>><?php _e( 'Add another line item', 'woocommerce-gateway-paypal-express-checkout' ); ?></option>
					<option value="<?php echo WC_Gateway_PPEC_Settings::subtotalMismatchBehaviorDropLineItems; ?>"<?php selected( $subtotal_mismatch_behavior, WC_Gateway_PPEC_Settings::subtotalMismatchBehaviorDropLineItems ); ?>><?php _e( 'Don\'t send line items to PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>'</option>
				</select>
			</fieldset>
		</td>
	</tr>
</table>

<script type="text/javascript" src="<?php echo esc_url( wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-admin-settings.js' ); ?>"></script>
