<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_Credit_Gateway extends PayPal_Express_Checkout_Gateway {
	public function __construct() {
		parent::__construct();

		$this->id = 'paypal_credit';

		$settings = new WC_Gateway_PPEC_Settings();
		$settings->loadSettings();

		$this->icon               = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-' . $settings->markSize . '.png';
		$this->enabled            = $settings->ppcEnabled ? 'yes' : 'no';
		$this->method_title       = __( 'PayPal Credit', 'woocommerce-gateway-ppec' );
		$this->method_description = __( 'Make checkout quick and easy for your buyers, and give them an easy way to finance their purchases at the same time.', 'woocommerce-gateway-ppec' );
	}

	protected function set_payment_title() {
		$this->title = __( 'PayPal Credit', 'woocommerce-gateway-ppec' );
	}
}