<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Implementation is duplicated in WC_Gateway_PPEC_With_SPB.
 */
class WC_Gateway_PPEC_With_SPB extends WC_Gateway_PPEC_With_PayPal {
	public function __construct() {
		parent::__construct();

		add_action( 'woocommerce_review_order_after_submit', array( $this, 'display_paypal_button' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * Display PayPal button on the checkout page order review.
	 */
	public function display_paypal_button() {
		if ( ! $this->should_display_buttons_at_checkout() ) {
			return;
		}
		wp_enqueue_script( 'wc-gateway-ppec-smart-payment-buttons' );
		?>
		<div id="woo_pp_ec_button_checkout"></div>
		<?php
	}

	/**
	 * Output script for conditionally showing Smart Payment Buttons on regular checkout.
	 *
	 * @since 1.6.0
	 */
	public function payment_scripts() {
		if ( ! wc_gateway_ppec()->checkout->has_active_session() ) {
			wp_enqueue_script( 'wc-gateway-ppec-order-review', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-order-review.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
		}
	}

	/**
	 * Save data necessary for authorizing payment to session, in order to
	 * go ahead with processing payment and bypass redirecting to PayPal.
	 *
	 * @param int $order_id Order ID
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( isset( $_POST['payerID'] ) && isset( $_POST['paymentToken'] ) ) {
			$session = WC()->session->get( 'paypal' );

			$session->checkout_completed = true;
			$session->payer_id           = $_POST['payerID'];
			$session->token              = $_POST['paymentToken'];

			WC()->session->set( 'paypal', $session );
		}

		return parent::process_payment( $order_id );
	}

}
