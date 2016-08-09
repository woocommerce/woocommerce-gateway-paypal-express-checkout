<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_username         = $this->get_option( 'api_username' );
$sandbox_api_username = $this->get_option( 'sandbox_api_username' );

$needs_creds         = empty( $api_username );
$needs_sandbox_creds = empty( $sandbox_api_username );
$enable_ips          = wc_gateway_ppec()->ips->is_supported();

if ( $enable_ips && $needs_creds ) {
	$ips_button         = '<a href="' . esc_url( wc_gateway_ppec()->ips->get_signup_url( 'live' ) ) . '" class="button button-primary">' . __( 'Setup or link an existing PayPal account', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
	$api_creds_text = sprintf( __( '%s or <a href="#" class="ppec-toggle-settings">click here to toggle manual API credential input</a>.', 'woocommerce-gateway-paypal-express-checkout' ), $ips_button );
} else {
	$api_creds_text         = '';
}

if ( $enable_ips && $needs_sandbox_creds ) {
	$sandbox_ips_button = '<a href="' . esc_url( wc_gateway_ppec()->ips->get_signup_url( 'sandbox' ) ) . '" class="button button-primary">' . __( 'Setup or link an existing PayPal Sandbox account', 'woocommerce-gateway-paypal-express-checkout' ) . '</a>';
	$sandbox_api_creds_text = sprintf( __( '%s or <a href="#" class="ppec-toggle-sandbox-settings">click here to toggle manual API credential input</a>.', 'woocommerce-gateway-paypal-express-checkout' ), $sandbox_ips_button );
} else {
	$sandbox_api_creds_text = '';
}

wc_enqueue_js( "
	jQuery( function( $ ) {
		var ppec_mark_fields      = '#woocommerce_ppec_paypal_title, #woocommerce_ppec_paypal_description';
		var ppec_live_fields      = '#woocommerce_ppec_paypal_api_username, #woocommerce_ppec_paypal_api_password, #woocommerce_ppec_paypal_api_signature, #woocommerce_ppec_paypal_api_certificate, #woocommerce_ppec_paypal_api_subject';
		var ppec_sandbox_fields   = '#woocommerce_ppec_paypal_sandbox_api_username, #woocommerce_ppec_paypal_sandbox_api_password, #woocommerce_ppec_paypal_sandbox_api_signature, #woocommerce_ppec_paypal_sandbox_api_certificate, #woocommerce_ppec_paypal_sandbox_api_subject';

		var enable_toggle         = $( 'a.ppec-toggle-settings' ).length > 0;
		var enable_sandbox_toggle = $( 'a.ppec-toggle-sandbox-settings' ).length > 0;

		$( '#woocommerce_ppec_paypal_environment' ).change(function(){
			$( ppec_sandbox_fields + ',' + ppec_live_fields ).closest( 'tr' ).hide();

			if ( 'live' === $( this ).val() ) {
				$( '#woocommerce_ppec_paypal_api_credentials, #woocommerce_ppec_paypal_api_credentials + p' ).show();
				$( '#woocommerce_ppec_paypal_sandbox_api_credentials, #woocommerce_ppec_paypal_sandbox_api_credentials + p' ).hide();

				if ( ! enable_toggle ) {
					$( ppec_live_fields ).closest( 'tr' ).show();
				}
			} else {
				$( '#woocommerce_ppec_paypal_api_credentials, #woocommerce_ppec_paypal_api_credentials + p' ).hide();
				$( '#woocommerce_ppec_paypal_sandbox_api_credentials, #woocommerce_ppec_paypal_sandbox_api_credentials + p' ).show();

				if ( ! enable_sandbox_toggle ) {
					$( ppec_sandbox_fields ).closest( 'tr' ).show();
				}
			}
		}).change();

		$( '#woocommerce_ppec_paypal_mark_enabled' ).change(function(){
			if ( $( this ).is( ':checked' ) ) {
				$( ppec_mark_fields ).closest( 'tr' ).show();
			} else {
				$( ppec_mark_fields ).closest( 'tr' ).hide();
			}
		}).change();

		$( '#woocommerce_ppec_paypal_paymentaction' ).change(function(){
			if ( 'sale' === $( this ).val() ) {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).show();
			} else {
				$( '#woocommerce_ppec_paypal_instant_payments' ).closest( 'tr' ).hide();
			}
		}).change();

		if ( enable_toggle ) {
			$( document ).on( 'click', '.ppec-toggle-settings', function() {
				$( ppec_live_fields ).closest( 'tr' ).toggle();
			} );
		}
		if ( enable_sandbox_toggle ) {
			$( document ).on( 'click', '.ppec-toggle-sandbox-settings', function() {
				$( ppec_sandbox_fields ).closest( 'tr' ).toggle();
			} );
		}
	});
" );

/**
 * Settings for PayPal Gateway.
 */
return array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' ),
		'description' => __( 'This enables PayPal Express Checkout which allows customers to checkout directly via PayPal from your cart page.', 'woocommerce-gateway-paypal-express-checkout' ),
		'desc_tip'    => true,
		'default'     => 'yes'
	),
	'button_size' => array(
		'title'       => __( 'Button Size', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'PayPal offers different sizes of the "PayPal Checkout" buttons, allowing you to select a size that best fits your site\'s theme. This setting will allow you to choose which size button(s) appear on your cart page.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'large',
		'desc_tip'    => true,
		'options'     => array(
			'small'  => __( 'Small', 'woocommerce-gateway-paypal-express-checkout' ),
			'medium' => __( 'Medium', 'woocommerce-gateway-paypal-express-checkout' ),
			'large'  => __( 'Large', 'woocommerce-gateway-paypal-express-checkout' )
		)
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
		)
	),
	'mark_enabled' => array(
		'title'       => __( 'PayPal Mark', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable the PayPal Mark on regular checkout', 'woocommerce-gateway-paypal-express-checkout' ),
		'description' => __( 'This enables the PayPal mark, which can be shown on regular WooCommerce checkout to use PayPal Express Checkout like a regular WooCommerce gateway.', 'woocommerce-gateway-paypal-express-checkout' ),
		'desc_tip'    => true,
		'default'     => 'no'
	),
	'title' => array(
		'title'       => __( 'Title', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => __( 'PayPal Express Checkout', 'woocommerce-gateway-paypal-express-checkout' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => __( 'Pay using either your PayPal account or credit card. All credit card payments will be processed by PayPal.', 'woocommerce-gateway-paypal-express-checkout' )
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
		'placeholder' => __( 'Optional if you provide a certificate below', 'woocommerce-gateway-paypal-express-checkout' )
	),
	'api_certificate' => array(
		'title'       => __( 'Live API Certificate', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'file',
		'description' => $this->get_certificate_info( $this->get_option( 'api_certificate' ) ),
		'default'     => '',
	),
	'api_subject' => array(
		'title'       => __( 'Live API Subject', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'If you\'re processing transactions on behalf of someone else\'s PayPal account, enter their email address or Secure Merchant Account ID (also known as a Payer ID) here. Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' )
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
	),
	'sandbox_api_certificate' => array(
		'title'       => __( 'Sandbox API Certificate', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'file',
		'description' => __( 'Get your API credentials from PayPal.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'sandbox_api_subject' => array(
		'title'       => __( 'Sandbox API Subject', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'If you\'re processing transactions on behalf of someone else\'s PayPal account, enter their email address or Secure Merchant Account ID (also known as a Payer ID) here. Generally, you must have API permissions in place with the other account in order to process anything other than "sale" transactions for them.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' )
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
		'desc_tip'    => true,
		'description' => __( 'PayPal does not share buyer billing details with you. However, there are times when you must collect the buyer billing address to fulfill an essential business function (such as determining whether you must charge the buyer tax). Enable this function to collect the address before payment is taken.', 'woocommerce-gateway-paypal-express-checkout' ),
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
			'authorization' => __( 'Authorize', 'woocommerce-gateway-paypal-express-checkout' )
		)
	),
	'instant_payments' => array(
		'title'       => __( 'Instant Payments', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Require Instant Payment', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => 'no',
		'desc_tip'    => true,
		'description' => __( 'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.', 'woocommerce-gateway-paypal-express-checkout' ),
	),
	'logo_image_url' => array(
		'title'       => __( 'Logo Image URL', 'woocommerce-gateway-paypal-express-checkout' ),
		'type'        => 'text',
		'description' => __( 'If you want PayPal to co-brand the checkout page with your logo, enter the URL of your logo image here.<br/>The image must be no larger than 190x60, GIF, PNG, or JPG format, and should be served over HTTPS.', 'woocommerce-gateway-paypal-express-checkout' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'Optional', 'woocommerce-gateway-paypal-express-checkout' ),
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
		)
	),
);
