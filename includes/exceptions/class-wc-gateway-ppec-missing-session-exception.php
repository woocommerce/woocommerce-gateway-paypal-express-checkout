<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Missing session exception.
 */
class PayPal_Missing_Session_Exception extends Exception {

	/**
	 * Constructor.
	 *
	 * @param string $message Exception message
	 */
	public function __construct( $message = '' ) {
		if ( empty( $message ) ) {
			$message = __( 'The buyer\'s session information could not be found.', 'woocommerce-gateway-paypal-express-checkout' );
		}

		parent::__construct( $message );
	}
}
