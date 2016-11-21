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
		require_once( $includes_path . 'class-wc-gateway-ppec-with-paypal-addons.php' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	/**
	 * Register the PPEC payment methods.
	 *
	 * @param array $methods Payment methods.
	 *
	 * @return array Payment methods
	 */
	public function payment_gateways( $methods ) {
		if ( $this->can_use_addons() ) {
			$methods[] = 'WC_Gateway_PPEC_With_PayPal_Addons';
		} else {
			$methods[] = 'WC_Gateway_PPEC_With_PayPal';
		}

		return $methods;
	}

	/**
	 * Checks whether gateway addons can be used.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if gateway addons can be used
	 */
	public function can_use_addons() {
		return ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) );
	}
}
