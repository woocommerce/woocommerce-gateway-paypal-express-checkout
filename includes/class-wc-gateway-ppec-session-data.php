<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Session_Data {

	public $source             = false; // 'cart' or 'order'
	public $order_id           = false; // if $source is 'order', this should be the order ID
	public $checkout_details   = false; // Will be populated with the GetEC details later
	public $needs_shipping     = false; // True if a shipping address is required for this transaction, false otherwise
	public $checkout_completed = false; // True if the buyer has just returned from PayPal and we should select PayPal as the payment method
	public $token              = false; // The EC token
	public $payerID            = false; // The buyer's payer ID, once they come back from PayPal
	public $expiry_time        = false; // The time at which the token will expire

	/**
	 * Constructor
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'token'          => '',
			'source'         => 'cart',
			'order_id'       => false,
			'needs_shipping' => true,
			'expires_in'     => 10800,
		) );

		$this->token          = $args['token'];
		$this->source         = $args['source'];
		$this->needs_shipping = $args['needs_shipping'];
		$this->expiry_time    = time() + $args['expires_in'];

		if ( 'order' === $this->source ) {
			$this->order_id = $args['order_id'];
		}
	}
}
