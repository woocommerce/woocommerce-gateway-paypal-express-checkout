<?php
/**
 * Plugin bootstrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Gateway_Loader {

	public function __construct() {
		$includes_path = wc_gateway_ppec()->includes_path;

		require_once( $includes_path . 'class-wc-gateway-ppec.php' );
		require_once( $includes_path . 'class-wc-gateway-ppec-with-paypal.php' );
		require_once( $includes_path . 'class-wc-gateway-ppec-with-card.php' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	public function payment_gateways( $methods ) {
		// If the buyer already went through the PP checkout, then filter out the
		// option they didn't select.
		$session = is_admin() ? false : WC()->session->get( 'paypal' );
		if ( ( is_checkout() || is_ajax() ) && $session && is_a( $session, 'WooCommerce_PayPal_Session_Data' ) &&
				$session->checkout_completed && $session->expiry_time >= time() &&
				$session->payerID ) {
			if ( $session->using_ppc ) {
				$methods[] = 'WC_Gateway_PPEC_With_Card';
			} else {
				$methods[] = 'WC_Gateway_PPEC_With_PayPal';
			}
		} else {
			$methods[] = 'WC_Gateway_PPEC_With_PayPal';
			$methods[] = 'WC_Gateway_PPEC_With_Card';
		}
		return $methods;
	}
}
