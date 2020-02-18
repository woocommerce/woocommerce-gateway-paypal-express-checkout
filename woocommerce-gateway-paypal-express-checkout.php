<?php
/**
 * Plugin Name: WooCommerce PayPal Checkout Gateway
 * Plugin URI: https://woocommerce.com/products/woocommerce-gateway-paypal-express-checkout/
 * Description: Accept all major credit and debit cards, plus Venmo and PayPal Credit in the US, presenting options in a customizable stack of payment buttons. Fast, seamless, and flexible.
 * Version: 1.6.20
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Copyright: © 2019 WooCommerce / PayPal.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-paypal-express-checkout
 * Domain Path: /languages
 * WC tested up to: 3.9
 * WC requires at least: 2.6
 */
/**
 * Copyright (c) 2019 PayPal, Inc.
 *
 * The name of the PayPal may not be used to endorse or promote products derived from this
 * software without specific prior written permission. THIS SOFTWARE IS PROVIDED ``AS IS'' AND
 * WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'WC_GATEWAY_PPEC_VERSION', '1.6.20' );

/**
 * Return instance of WC_Gateway_PPEC_Plugin.
 *
 * @return WC_Gateway_PPEC_Plugin
 */
function wc_gateway_ppec() {
	static $plugin;

	if ( ! isset( $plugin ) ) {
		require_once( 'includes/class-wc-gateway-ppec-plugin.php' );

		$plugin = new WC_Gateway_PPEC_Plugin( __FILE__, WC_GATEWAY_PPEC_VERSION );
	}

	return $plugin;
}

wc_gateway_ppec()->maybe_run();
