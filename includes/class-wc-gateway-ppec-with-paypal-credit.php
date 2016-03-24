<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_With_PayPal_Credit extends WC_Gateway_PPEC {
	public function __construct() {

		$this->id = 'ppec_paypal_credit';

		parent::__construct();

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$this->icon        = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-' . $settings->markSize . '.png';
		$this->enabled     = $settings->ppcEnabled ? 'yes' : 'no';
		$this->title       = __( 'PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' );
		$this->description = __( 'Make checkout quick and easy for your buyers, and give them an easy way to finance their purchases at the same time.', 'woocommerce-gateway-paypal-express-checkout' );
	}
}

