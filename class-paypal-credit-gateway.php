<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

require_once 'class-paypal-gateway.php';

function woo_pp_credit_plugins_loaded() {
	class PayPal_Credit_Gateway extends PayPal_Express_Checkout_Gateway {
		public function __construct() {
			parent::__construct();
			$this->id = 'paypal_credit';
		
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();

			$this->icon = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppc-acceptance-' . $settings->markSize . '.png';
		
			$this->enabled = $settings->ppcEnabled ? 'yes' : 'no';
			$this->method_title = __( 'PayPal Credit', 'woo_pp' );
			$this->method_description = __( 'Make checkout quick and easy for your buyers, and give them an easy way to finance their purchases at the same time.', 'woo_pp' );		
		}
		
		protected function set_payment_title() {
			$this->title = __( 'PayPal Credit', 'woo_pp' );
		}
	}
}
add_action( 'plugins_loaded', 'woo_pp_credit_plugins_loaded' );