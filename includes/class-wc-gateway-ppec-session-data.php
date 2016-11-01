<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PayPal Express Checkout session wrapper.
 */
class WC_Gateway_PPEC_Session_Data {

	/**
	 * Source where the session is set from. Valid value is either 'cart' or
	 * 'order'.
	 *
	 * If source is 'cart' then buyer starts it from cart page, otherwise it
	 * starts from checkout page or flow that has order created (e.g. from cart
	 * then the process_payment encountered error).
	 *
	 * @var string
	 */
	public $source;

	/**
	 * WooCommerce Order ID.
	 *
	 * If self::$source is 'order', this must be set to order ID.
	 *
	 * @var int
	 */
	public $order_id;

	/**
	 * Whether the buyer has returned from PayPal.
	 *
	 * If checkout_completed is true PPEC should be selected as the payment
	 * method.
	 *
	 * @var bool
	 */
	public $checkout_completed = false;

	/**
	 * Express checkout token.
	 *
	 * @var string
	 */
	public $token;

	/**
	 * The buyer's payer ID.
	 *
	 * Retrieved after buyer comes back from PayPal in-context dialog.
	 *
	 * @var string
	 */
	public $payer_id;

	/**
	 * How long the token will expires (in seconds).
	 *
	 * @var int
	 */
	public $expiry_time;

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments for session data
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'token'      => '',
			'source'     => 'cart',
			'order_id'   => false,
			'expires_in' => 10800,
		) );

		$this->token       = $args['token'];
		$this->source      = $args['source'];
		$this->expiry_time = time() + $args['expires_in'];

		if ( 'order' === $this->source ) {
			$this->order_id = $args['order_id'];
		}
	}
}
