<?php
/**
 * Cart handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Checkout_Handler {

	public function __construct() {
		add_action( 'woocommerce_before_checkout_process', array( $this, 'before_checkout_process' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'checkout_process' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'after_checkout_form' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Before checkout process.
	 *
	 * Turn off use of the buyer email in the payment method title so that it
	 * doesn't appear in emails.
	 *
	 * @return void
	 */
	public function before_checkout_process() {
		WC_Gateway_PPEC::$use_buyer_email = false;
	}

	/**
	 * Checkout process.
	 */
	public function checkout_process() {
		$session = WC()->session->paypal;
		if ( null != $session && is_a( $session, 'WooCommerce_PayPal_Session_Data' ) && $session->checkout_completed && $session->expiry_time >= time() && $session->payerID ) {
			if ( ! $session->checkout_details->payer_details->billing_address ) {
				WC()->checkout()->checkout_fields['billing']['billing_address_1']['required'] = false;
				WC()->checkout()->checkout_fields['billing']['billing_city'     ]['required'] = false;
				WC()->checkout()->checkout_fields['billing']['billing_state'    ]['required'] = false;
				WC()->checkout()->checkout_fields['billing']['billing_postcode' ]['required'] = false;
			}
		}
	}

	/**
	 * After checkout form.
	 */
	public function after_checkout_form() {
		$settings = wc_gateway_ppec()->settings->loadSettings();

		if ( $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$session = WC()->session->paypal;
			if ( ! $session ) {
				return;
			}

			if ( ! is_a( $session, 'WooCommerce_PayPal_Session_Data' ) ) {
				return;
			}

			if ( ! $session->checkout_completed || $session->expiry_time < time() || ! $session->payerID ) {
				$payer_id = $settings->getActiveApiCredentials()->payerID;

				// This div is necessary for PayPal to properly display its lightbox.
				echo '<div id="woo_pp_icc_container" style="display: none;"></div>';
			}
		}
	}

	/**
	 * Enqueue front-end scripts on checkout page.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( ! $settings->enabled ) {
			return;
		}

		// On the checkout page, only load the JS if we plan on sending them over to PayPal.
		if ( $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$session = WC()->session->paypal;
			if ( ! $session
				|| ! is_a( $session, 'WooCommerce_PayPal_Session_Data' )
				|| ! $session->checkout_completed || $session->expiry_time < time()
				|| ! $session->payerID ) {

				wp_enqueue_script( 'wc-gateway-ppec-frontend-checkout', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-frontend-checkout.js', array( 'jquery' ), false, true );

				wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
			}
		}
	}
}
