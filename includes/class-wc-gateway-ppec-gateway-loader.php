<?php
/**
 * Plugin bootstrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_PPEC_Gateway_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$includes_path = wc_gateway_ppec()->includes_path;

		require_once( $includes_path . 'class-wc-gateway-ppec-refund.php' );
		require_once( $includes_path . 'abstracts/abstract-wc-gateway-ppec.php' );
		require_once( $includes_path . 'class-wc-gateway-ppec-with-paypal.php' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	/**
	 * Init gateways
	 */
	public function payment_gateways( $methods ) {
		$methods[] = 'WC_Gateway_PPEC_With_PayPal';
		return $methods;
	}
}
