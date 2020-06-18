<?php
// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$live_credentials       = wc_gateway_ppec()->settings->get_live_api_credentials();
$sandbox_credentials    = wc_gateway_ppec()->settings->get_sandbox_api_credentials();
$has_live_credential    = is_a( $live_credentials, 'WC_Gateway_PPEC_Client_Credential_Signature' ) ? (bool) $live_credentials->get_signature() : (bool) $live_credentials->get_certificate();
$has_sandbox_credential = is_a( $sandbox_credentials, 'WC_Gateway_PPEC_Client_Credential_Signature' ) ? (bool) $sandbox_credentials->get_signature() : (bool) $sandbox_credentials->get_certificate();

$needs_creds         = ! $has_live_credential && ! (bool) $live_credentials->get_username() && ! (bool) $live_credentials->get_password();
$needs_sandbox_creds = ! $has_sandbox_credential && ! (bool) $sandbox_credentials->get_username() && ! (bool) $sandbox_credentials->get_password();
$enable_ips          = wc_gateway_ppec()->ips->is_supported();

if ( $enable_ips && $needs_creds ) {
	$ips_button = '<a href="' . esc_url( wc_gateway_ppec()->ips->get_signup_url( 'live' ) ) . '" class="button button-primary">' . __( 'Setup or link an existing PayPal account', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
	// Translators: placeholder is the button "Setup or link an existing PayPal account".
	$api_creds_text = sprintf( __( '%s or <a href="#" class="ppec-toggle-settings">click here to toggle manual API credential input</a>.', 'woocommerce-gateway-paypal-express-checkout' ), $ips_button );
} else {
	$reset_link = add_query_arg(
		array(
			'reset_ppec_api_credentials' => 'true',
			'environment'                => 'live',
			'reset_nonce'                => wp_create_nonce( 'reset_ppec_api_credentials' ),
		),
		wc_gateway_ppec()->get_admin_setting_link()
	);

	$api_creds_text = sprintf(
		// Translators: Placeholders are opening an closing link HTML tags.
		__( 'To reset current credentials and use another account %1$sclick here%2$s. %3$sLearn more about your API Credentials%2$s.', 'woocommerce-gateway-paypal-express-checkout' ),
		'<a href="' . $reset_link . '" title="' . __( 'Reset current credentials', 'woocommerce-gateway-paypal-express-checkout' ) . '">',
		'</a>',
		'<a href="https://docs.woocommerce.com/document/paypal-express-checkout/#section-4" title="' . __( 'Learn more', 'woocommerce-gateway-paypal-express-checkout' ) . '">'
	);
}

if ( $enable_ips && $needs_sandbox_creds ) {
	$sandbox_ips_button = '<a href="' . esc_url( wc_gateway_ppec()->ips->get_signup_url( 'sandbox' ) ) . '" class="button button-primary">' . __( 'Setup or link an existing PayPal Sandbox account', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
	// Translators: placeholder is the button "Setup or link an existing PayPal sandbox account".
	$sandbox_api_creds_text = sprintf( __( '%s or <a href="#" class="ppec-toggle-sandbox-settings">click here to toggle manual API credential input</a>.', 'woocommerce-gateway-paypal-express-checkout' ), $sandbox_ips_button );
} else {
	$reset_link = add_query_arg(
		array(
			'reset_ppec_api_credentials' => 'true',
			'environment'                => 'sandbox',
			'reset_nonce'                => wp_create_nonce( 'reset_ppec_api_credentials' ),
		),
		wc_gateway_ppec()->get_admin_setting_link()
	);

	$sandbox_api_creds_text = sprintf(
		// Translators: Placeholders are opening and closing link HTML tags.
		__( 'Your account setting is set to sandbox, no real charging takes place. To accept live payments, switch your environment to live and connect your PayPal account. To reset current credentials and use other sandbox account %1$sclick here%2$s. %3$sLearn more about your API Credentials%2$s.', 'woocommerce-gateway-paypal-express-checkout' ),
		'<a href="' . $reset_link . '" title="' . __( 'Reset current credentials', 'woocommerce-gateway-paypal-express-checkout' ) . '">',
		'</a>',
		'<a href="https://docs.woocommerce.com/document/paypal-express-checkout/#section-4" title="' . __( 'Learn more', 'woocommerce-gateway-paypal-express-checkout' ) . '">'
	);
}

$credit_enabled_label = __( 'Enable PayPal Credit to eligible customers', 'woocommerce-gateway-paypal-express-checkout' );
if ( ! wc_gateway_ppec_is_credit_supported() ) {
	$credit_enabled_label .= '<p><em>' . __( 'This option is disabled. Currently PayPal Credit only available for U.S. merchants using USD currency.', 'woocommerce-gateway-paypal-express-checkout' ) . '</em></p>';
}

$credit_enabled_description = __( 'This enables PayPal Credit, which displays a PayPal Credit button next to the primary PayPal Checkout button. PayPal Checkout lets you give customers access to financing through PayPal Credit® - at no additional cost to you. You get paid up front, even though customers have more time to pay. A pre-integrated payment button shows up next to the PayPal Button, and lets customers pay quickly with PayPal Credit®. (Should be unchecked for stores involved in Real Money Gaming.)', 'woocommerce-gateway-paypal-express-checkout' );

/**
 * Settings for PayPal Gateway.
 */
$settings = array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable PayPal Checkout', 'woocommerce-gateway-paypal-express-checkout' ),
		'description' => __( 'This enables PayPal Checkout which allows customers to checkout directly via PayPal from your cart page.', 'woocommerce-gateway-paypal-express-checkout' ),
		'desc_tip'    => true,
		'default'     => 'yes',
	),

	'title' => array(
		'title'       => __( 'Title', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => __( 'PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => __( 'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.', 'woocommerce-gateway-paypal-express-checkout' ),
	),

	'account_settings' => array(
		'title'       => __( 'Account Settings', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => '',
	),
	'environment' => array(
		'title'       => __( 'Environment', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'This setting specifies whether you will process live transactions, or whether you will process simulated transactions using the PayPal Sandbox.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'live',
		'desc_tip'    => true,
		'options'     => array(
			'live'    => __( 'Live', 'woocommerce-gateway-paypal-express-checkout' ),
			'sandbox' => __( 'Sandbox', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),

	'api_credentials' => array(
		'title'       => __( 'API Credentials', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => $api_creds_text,
	),
	'api_username' => array(
		'title'       => __( 'Live API Username', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'api_password' => array(
		'title'       => __( 'Live API Password', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'password',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'api_signature' => array(
		'title'       => __( 'Live API Signature', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional if you provide a certificate below', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'api_certificate' => array(
		'title'       => __( 'Live API Certificate', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'file',
		'description' => $this->get_certificate_setting_description(),
		'default'     => '',
	),
	'api_subject' => array(
		'title'       => __( 'Live API Subject', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'If you\'re processing transactions on behalf of someone else\'s PayPal account, enter their email address or Secure Merchant Account ID (also known as a Payer ID) here. Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'sandbox_api_credentials' => array(
		'title'       => __( 'Sandbox API Credentials', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => $sandbox_api_creds_text,
	),
	'sandbox_api_username' => array(
		'title'       => __( 'Sandbox API Username', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'sandbox_api_password' => array(
		'title'       => __( 'Sandbox API Password', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'password',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'sandbox_api_signature' => array(
		'title'       => __( 'Sandbox API Signature', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional if you provide a certificate below', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'sandbox_api_certificate' => array(
		'title'       => __( 'Sandbox API Certificate', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'file',
		'description' => $this->get_certificate_setting_description( 'sandbox' ),
		'default'     => '',
	),
	'sandbox_api_subject' => array(
		'title'       => __( 'Sandbox API Subject', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'If you\'re processing transactions on behalf of someone else\'s PayPal account, enter their email address or Secure Merchant Account ID (also known as a Payer ID) here. Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'paypal_hosted_settings' => array(
		'title'       => __( 'PayPal-hosted Checkout Settings', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => __( 'Customize the appearance of PayPal Checkout on the PayPal side.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'brand_name' => array(
		'title'       => __( 'Brand Name', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'A label that overrides the business name in the PayPal account on the PayPal hosted checkout pages.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => get_bloginfo( 'name', 'display' ),
		'desc_tip'    => true,
	),
	'logo_image_url' => array(
		'title'       => __( 'Logo Image (190×60)', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'image',
		'description' => __( 'If you want PayPal to co-brand the checkout page with your logo, enter the URL of your logo image here.<br/>The image must be no larger than 190x60, GIF, PNG, or JPG format, and should be served over HTTPS.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'header_image_url' => array(
		'title'       => __( 'Header Image (750×90)', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'image',
		'description' => __( 'If you want PayPal to co-brand the checkout page with your header, enter the URL of your header image here.<br/>The image must be no larger than 750x90, GIF, PNG, or JPG format, and should be served over HTTPS.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'page_style' => array(
		'title'       => __( 'Page Style', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Optionally enter the name of the page style you wish to use. These are defined within your PayPal account.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'landing_page' => array(
		'title'       => __( 'Landing Page', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Type of PayPal page to display.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'Login',
		'desc_tip'    => true,
		'options'     => array(
			'Billing' => _x( 'Billing (Non-PayPal account)', 'Type of PayPal page', 'woocommerce-gateway-paypal-express-checkout' ),
			'Login'   => _x( 'Login (PayPal account login)', 'Type of PayPal page', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),

	'advanced' => array(
		'title'       => __( 'Advanced Settings', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => '',
	),
	'debug' => array(
		'title'       => __( 'Debug Log', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable Logging', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'no',
		'desc_tip'    => true,
		'description' => __( 'Log PayPal events, such as IPN requests.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'invoice_prefix' => array(
		'title'       => __( 'Invoice Prefix', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'WC-',
		'desc_tip'    => true,
	),
	'require_billing' => array(
		'title'       => __( 'Billing Addresses', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Require Billing Address', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'no',
		'description' => sprintf(
			/* Translators: 1) is an <a> tag linking to PayPal's contact info, 2) is the closing </a> tag. */
			__( 'PayPal only returns a shipping address back to the website. To make sure billing address is returned as well, please enable this functionality on your PayPal account by calling %1$sPayPal Technical Support%2$s.', 'woocommerce-gateway-paypal-express-checkout' ),
			'<a href="https://www.paypal.com/us/selfhelp/contact/call">',
			'</a>'
		),
	),
	'require_phone_number' => array(
		'title'       => __( 'Require Phone Number', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Require Phone Number', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'no',
		'description' => __( 'Require buyer to enter their telephone number during checkout if none is provided by PayPal. Disabling this option doesn\'t affect direct Debit or Credit Card payments offered by PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'paymentaction' => array(
		'title'       => __( 'Payment Action', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'sale',
		'desc_tip'    => true,
		'options'     => array(
			'sale'          => __( 'Sale', 'woocommerce-gateway-paypal-express-checkout' ),
			'authorization' => __( 'Authorize', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'instant_payments' => array(
		'title'       => __( 'Instant Payments', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Require Instant Payment', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'no',
		'desc_tip'    => true,
		'description' => __( 'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'subtotal_mismatch_behavior' => array(
		'title'       => __( 'Subtotal Mismatch Behavior', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Internally, WC calculates line item prices and taxes out to four decimal places; however, PayPal can only handle amounts out to two decimal places (or, depending on the currency, no decimal places at all). Occasionally, this can cause discrepancies between the way WooCommerce calculates prices versus the way PayPal calculates them. If a mismatch occurs, this option controls how the order is dealt with so payment can still be taken.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'add',
		'desc_tip'    => true,
		'options'     => array(
			'add'  => __( 'Add another line item', 'woocommerce-gateway-paypal-express-checkout' ),
			'drop' => __( 'Do not send line items to PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),

	'button_settings' => array(
		'title'       => __( 'Button Settings', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'title',
		'description' => __( 'Customize the appearance of PayPal Checkout on your site.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'use_spb' => array(
		'title'       => __( 'Smart Payment Buttons', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'default'     => $this->get_option( 'button_size' ) ? 'no' : 'yes', // A 'button_size' value having been set indicates that settings have been initialized before, requiring merchant opt-in to SPB.
		'label'       => __( 'Use Smart Payment Buttons', 'woocommerce-gateway-paypal-express-checkout' ),
		'description' => sprintf(
			/* Translators: %s is the URL of the Smart Payment Buttons integration docs. */
			__( 'PayPal Checkout\'s Smart Payment Buttons provide a variety of button customization options, such as color, language, shape, and multiple button layout. <a href="%s">Learn more about Smart Payment Buttons</a>.', 'woocommerce-gateway-paypal-express-checkout' ),
			'https://developer.paypal.com/docs/integration/direct/express-checkout/integration-jsv4/#smart-payment-buttons'
		),
	),
	'button_color' => array(
		'title'       => __( 'Button Color', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb',
		'default'     => 'gold',
		'desc_tip'    => true,
		'description' => __( 'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'gold'   => __( 'Gold (Recommended)', 'woocommerce-gateway-paypal-express-checkout' ),
			'blue'   => __( 'Blue', 'woocommerce-gateway-paypal-express-checkout' ),
			'silver' => __( 'Silver', 'woocommerce-gateway-paypal-express-checkout' ),
			'black'  => __( 'Black', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'button_shape' => array(
		'title'       => __( 'Button Shape', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb',
		'default'     => 'rect',
		'desc_tip'    => true,
		'description' => __( 'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'pill' => __( 'Pill', 'woocommerce-gateway-paypal-express-checkout' ),
			'rect' => __( 'Rectangle', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'button_label' => array(
		'title'       => __( 'Button Label', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb',
		'default'     => 'paypal',
		'desc_tip'    => true,
		'description' => __( 'This controls the label on the primary button.', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'paypal'   => __( 'PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
			'checkout' => __( 'PayPal Checkout', 'woocommerce-gateway-paypal-express-checkout' ),
			'buynow'   => __( 'PayPal Buy Now', 'woocommerce-gateway-paypal-express-checkout' ),
			'pay'      => __( 'Pay with PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
);

/**
 * Settings that are copied to context-specific sections.
 */
$per_context_settings = array(
	'button_layout' => array(
		'title'       => __( 'Button Layout', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb woocommerce_ppec_paypal_button_layout',
		'default'     => 'vertical',
		'desc_tip'    => true,
		'description' => __( 'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'vertical'   => __( 'Vertical', 'woocommerce-gateway-paypal-express-checkout' ),
			'horizontal' => __( 'Horizontal', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'button_size' => array(
		'title'       => __( 'Button Size', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_button_size',
		'default'     => 'yes' === $this->get_option( 'use_spb', 'yes' ) ? 'responsive' : 'large',
		'desc_tip'    => true,
		'description' => __( 'PayPal offers different sizes of the "PayPal Checkout" buttons, allowing you to select a size that best fits your site\'s theme. This setting will allow you to choose which size button(s) appear on your cart page. (The "Responsive" option adjusts to container size, and is available and recommended for Smart Payment Buttons.)', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'responsive' => __( 'Responsive', 'woocommerce-gateway-paypal-express-checkout' ),
			'small'      => __( 'Small', 'woocommerce-gateway-paypal-express-checkout' ),
			'medium'     => __( 'Medium', 'woocommerce-gateway-paypal-express-checkout' ),
			'large'      => __( 'Large', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'button_label' => array(
		'title'       => __( 'Button Label', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb',
		'default'     => 'paypal',
		'desc_tip'    => true,
		'description' => __( 'PayPal offers different labels on the "PayPal Checkout" buttons, allowing you to select a suitable label.)', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'paypal'   => __( 'PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
			'checkout' => __( 'PayPal Checkout', 'woocommerce-gateway-paypal-express-checkout' ),
			'buynow'   => __( 'PayPal Buy Now', 'woocommerce-gateway-paypal-express-checkout' ),
			'pay'      => __( 'Pay with PayPal', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'hide_funding_methods' => array(
		'title'       => 'Hide Funding Method(s)',
		'type'        => 'multiselect',
		'class'       => 'wc-enhanced-select woocommerce_ppec_paypal_spb woocommerce_ppec_funding_methods_select woocommerce_ppec_paypal_vertical',
		'default'     => array( 'CARD' ),
		'desc_tip'    => true,
		'description' => __( 'Hides the specified funding methods.', 'woocommerce-gateway-paypal-express-checkout' ),
		'options'     => array(
			'CREDIT'     => __( 'PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ),
			'ELV'        => __( 'ELV', 'woocommerce-gateway-paypal-express-checkout' ),
			'CARD'       => __( 'Credit or debit cards', 'woocommerce-gateway-paypal-express-checkout' ),
			'VENMO'      => __( 'Venmo', 'woocommerce-gateway-paypal-express-checkout' ),
			'SEPA'       => __( 'SEPA-Lastschrift', 'woocommerce-gateway-paypal-express-checkout' ),
			'BANCONTACT' => __( 'Bancontact', 'woocommerce-gateway-paypal-express-checkout' ),
			'EPS'        => __( 'eps', 'woocommerce-gateway-paypal-express-checkout' ),
			'GIROPAY'    => __( 'giropay', 'woocommerce-gateway-paypal-express-checkout' ),
			'IDEAL'      => __( 'iDEAL', 'woocommerce-gateway-paypal-express-checkout' ),
			'MYBANK'     => __( 'MyBank', 'woocommerce-gateway-paypal-express-checkout' ),
			'P24'        => __( 'Przelewy24', 'woocommerce-gateway-paypal-express-checkout' ),
			'SOFORT'     => __( 'Sofort', 'woocommerce-gateway-paypal-express-checkout' ),
		),
	),
	'credit_enabled' => array(
		'title'       => __( 'Enable PayPal Credit to eligible customers', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => $credit_enabled_label,
		'disabled'    => ! wc_gateway_ppec_is_credit_supported(),
		'class'       => 'woocommerce_ppec_paypal_horizontal',
		'default'     => 'yes',
		'desc_tip'    => true,
		'description' => $credit_enabled_description,
	),
);

/**
 * Cart / global button settings.
 */
$settings = array_merge( $settings, $per_context_settings );

$per_context_settings['button_size']['class']    .= ' woocommerce_ppec_paypal_spb';
$per_context_settings['credit_enabled']['class'] .= ' woocommerce_ppec_paypal_spb';

$settings['cart_checkout_enabled'] = array(
	'title'       => __( 'Checkout on cart page', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_visibility_toggle',
	'label'       => __( 'Enable PayPal Checkout on the cart page', 'woocommerce-gateway-paypal-express-checkout' ),
	'description' => __( 'This shows or hides the PayPal Checkout button on the cart page.', 'woocommerce-gateway-paypal-express-checkout' ),
	'desc_tip'    => true,
	'default'     => 'yes',
);

/**
 * Mini-cart button settings.
 */
$settings['mini_cart_settings'] = array(
	'title' => __( 'Mini-cart Button Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'  => 'title',
	'class' => 'woocommerce_ppec_paypal_spb',
);

$settings['mini_cart_settings_toggle'] = array(
	'title'       => __( 'Configure Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'label'       => __( 'Configure settings specific to mini-cart', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_spb woocommerce_ppec_paypal_visibility_toggle',
	'default'     => 'no',
	'desc_tip'    => true,
	'description' => __( 'Optionally override global button settings above and configure buttons for this context.', 'woocommerce-gateway-paypal-express-checkout' ),
);
foreach ( $per_context_settings as $key => $value ) {
	$value['class']                 .= ' woocommerce_ppec_paypal_mini_cart';
	$settings[ 'mini_cart_' . $key ] = $value;
}

/**
 * Single product button settings.
 */
$settings['single_product_settings'] = array(
	'title' => __( 'Single Product Button Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'  => 'title',
	'class' => 'woocommerce_ppec_paypal_spb',
);

$settings['checkout_on_single_product_enabled'] = array(
	'title'       => __( 'Checkout on Single Product', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_visibility_toggle',
	'label'       => __( 'Checkout on Single Product', 'woocommerce-gateway-paypal-express-checkout' ),
	'default'     => 'yes',
	'desc_tip'    => true,
	'description' => __( 'Enable PayPal Checkout on Single Product view.', 'woocommerce-gateway-paypal-express-checkout' ),
);

$settings['single_product_settings_toggle'] = array(
	'title'       => __( 'Configure Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'label'       => __( 'Configure settings specific to Single Product view', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_spb woocommerce_ppec_paypal_visibility_toggle',
	'default'     => 'yes',
	'desc_tip'    => true,
	'description' => __( 'Optionally override global button settings above and configure buttons for this context.', 'woocommerce-gateway-paypal-express-checkout' ),
);
foreach ( $per_context_settings as $key => $value ) {
	$value['class']                      .= ' woocommerce_ppec_paypal_single_product';
	$settings[ 'single_product_' . $key ] = $value;
}
$settings['single_product_button_layout']['default'] = 'horizontal';

/**
 * Regular checkout button settings.
 */
$settings['mark_settings'] = array(
	'title' => __( 'Regular Checkout Button Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'  => 'title',
	'class' => 'woocommerce_ppec_paypal_spb',
);

$settings['mark_enabled'] = array(
	'title'       => __( 'PayPal Mark', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_visibility_toggle',
	'label'       => __( 'Enable the PayPal Mark on regular checkout', 'woocommerce-gateway-paypal-express-checkout' ),
	'description' => __( 'This enables the PayPal mark, which can be shown on regular WooCommerce checkout to use PayPal Checkout like a regular WooCommerce gateway.', 'woocommerce-gateway-paypal-express-checkout' ),
	'desc_tip'    => true,
	'default'     => 'yes',
);

$settings['mark_settings_toggle'] = array(
	'title'       => __( 'Configure Settings', 'woocommerce-gateway-paypal-express-checkout' ),
	'label'       => __( 'Configure settings specific to regular checkout', 'woocommerce-gateway-paypal-express-checkout' ),
	'type'        => 'checkbox',
	'class'       => 'woocommerce_ppec_paypal_spb woocommerce_ppec_paypal_visibility_toggle',
	'default'     => 'no',
	'desc_tip'    => true,
	'description' => __( 'Optionally override global button settings above and configure buttons for this context.', 'woocommerce-gateway-paypal-express-checkout' ),
);
foreach ( $per_context_settings as $key => $value ) {
	$value['class']            .= ' woocommerce_ppec_paypal_mark';
	$settings[ 'mark_' . $key ] = $value;
}

return apply_filters( 'woocommerce_paypal_express_checkout_settings', $settings );

// phpcs:enable
