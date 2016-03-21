<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Session_Data {

	public $leftFrom                  = false; // 'cart' or 'order'
	public $order_id                  = false; // if $leftFrom is 'order', this should be the order ID
	public $billingAgreementRequested = false; // true if a billing agreement was requested in the SetEC call, false otherwise
	public $checkout_details          = false; // Will be populated with the GetEC details later
	public $shipping_required         = false; // True if a shipping address is required for this transaction, false otherwise
	public $checkout_completed        = false; // True if the buyer has just returned from PayPal and we should select PayPal as the payment method
	public $token                     = false; // The EC token
	public $payerID                   = false; // The buyer's payer ID, once they come back from PayPal
	public $expiry_time               = false; // The time at which the token will expire
	public $using_ppc                 = false; // Whether the buyer is checking out with PayPal Credit

	public function __construct( $token, $leftFrom = 'cart', $order_id = false, $shipping_required = true, $billingAgreementRequested = false, $expires_in = 10800, $using_ppc = false ) {
		if ( 'cart' == $leftFrom || 'order' == $leftFrom ) {
			$this->leftFrom = $leftFrom;
		}
		if ( 'order' == $leftFrom ) {
			$this->order_id = $order_id;
		}

		$this->token = $token;
		$this->shipping_required = $shipping_required;
		$this->billingAgreementRequested = $billingAgreementRequested;

		$this->expiry_time = time() + $expires_in;

		$this->using_ppc = $using_ppc;
	}
}
