<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_API_Error {
	public $error_code;
	public $short_message;
	public $long_message;
	public $severity_code;

	public function __construct( $error_code, $short_message, $long_message, $severity_code ) {
		$this->error_code    = $error_code;
		$this->short_message = $short_message;
		$this->long_message  = $long_message;
		$this->severity_code = $severity_code;
	}

	public function mapToBuyerFriendlyError() {
		switch ( $this->error_code ) {
			// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.BodyOnNextLineCASE
			case '-1':    return esc_html__( 'Unable to communicate with PayPal.  Please try your payment again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10407': return esc_html__( 'PayPal rejected your email address because it is not valid.  Please double-check your email address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10409':
			case '10421':
			case '10410': return esc_html__( 'Your PayPal checkout session is invalid.  Please check out again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10411': return esc_html__( 'Your PayPal checkout session has expired.  Please check out again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '11607':
			case '10415': return esc_html__( 'Your PayPal payment has already been completed.  Please contact the store owner for more information.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10416': return esc_html__( 'Your PayPal payment could not be processed.  Please check out again or contact PayPal for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10417': return esc_html__( 'Your PayPal payment could not be processed.  Please select an alternative method of payment or contact PayPal for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10486':
			case '10422': return esc_html__( 'Your PayPal payment could not be processed.  Please return to PayPal and select a new method of payment.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10485':
			case '10435': return esc_html__( 'You have not approved this transaction on the PayPal website.  Please check out again and be sure to complete all steps of the PayPal checkout process.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10474': return esc_html__( 'Your shipping address may not be in a different country than your country of residence.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10537': return esc_html__( 'This store does not accept transactions from buyers in your country.  Please contact the store owner for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10538': return esc_html__( 'The transaction is over the threshold allowed by this store.  Please contact the store owner for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '11611':
			case '10539': return esc_html__( 'Your transaction was declined.  Please contact the store owner for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10725': return esc_html__( 'The country in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10727': return esc_html__( 'The street address in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10728': return esc_html__( 'The city in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10729': return esc_html__( 'The state in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10730': return esc_html__( 'The ZIP code or postal code in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10731': return esc_html__( 'The country in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '10736': return esc_html__( 'PayPal rejected your shipping address because the city, state, and/or ZIP code are incorrect.  Please double-check that they are all spelled correctly and try again.', 'woocommerce-gateway-paypal-express-checkout' );
			case '13113':
			case '11084': return esc_html__( 'Your PayPal payment could not be processed.  Please contact PayPal for assistance.', 'woocommerce-gateway-paypal-express-checkout' );
			case '12126':
			case '12125': return esc_html__( 'The redemption code(s) you entered on PayPal cannot be used at this time.  Please return to PayPal and remove them.', 'woocommerce-gateway-paypal-express-checkout' );
			case '17203':
			case '17204':
			case '17200': return esc_html__( 'Your funding instrument is invalid.  Please check out again and select a new funding source.', 'woocommerce-gateway-paypal-express-checkout' );
			default:
				/* Translators: placeholder is an error code. */
				return sprintf( esc_html__( 'An error (%s) occurred while processing your PayPal payment.  Please contact the store owner for assistance.', 'woocommerce-gateway-paypal-express-checkout' ), $this->error_code );
			// phpcs:enable
		}
	}
}
