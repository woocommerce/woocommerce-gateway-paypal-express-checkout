<?php
/**
 * Plugin Name: WooCommerce PayPal Express Checkout Gateway
 * Plugin URI: *
 * Description: A payment gateway for PayPal Express Checkout ( https://www.paypal.com/sg/webapps/mpp/express-checkout ). Requires WC 2.5+
 * Version: 1.0.0
 * Author: Automattic/WooCommerce
 * Author URI: https://woocommerce.com
 * Copyright: © 2016 WooCommerce / PayPal.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
/**
 * Copyright (c) 2015 PayPal, Inc.
 *
 * The name of the PayPal may not be used to endorse or promote products derived from this
 * software without specific prior written permission. THIS SOFTWARE IS PROVIDED ``AS IS'' AND
 * WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '', '' );

/**
 * Main PayPal Express Checkout class which sets the gateway up for us
 */
class WC_PPEC {

	/**
	 * Constructor
	 */
	public function __construct() {

		define( 'WC_PPEC_VERSION', '1.0.0' );
		define( 'WC_PPEC_TEMPLATE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
		define( 'WC_PPEC_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_PPEC_MAIN_FILE', __FILE__ );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
	}

	/**
	 * Add relevant links to plugins page
	 * @param  array $links
	 * @return array
	 */
	public function plugin_action_links( $links ) {

		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paypal_express_checkout_gateway' ) . '">' . __( 'Settings', 'woocommerce-gateway-ppec' ) . '</a>',
			'<a href="http://support.woothemes.com/">' . __( 'Support', 'woocommerce-gateway-ppec' ) . '</a>',
			'<a href="http://docs.woothemes.com/">' . __( 'Docs', 'woocommerce-gateway-ppec' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );

	}

	/**
	 * Init localizations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Includes
		include_once( 'includes/admin/class-wc-gateway-ppec-credential-validation.php' );
		include_once( 'includes/class-wc-gateway-ppec.php' );

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-ppec', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register the gateway for use
	 */
	public function register_gateway( $methods ) {

		$methods[] = 'WC_Gateway_PPEC';

		return $methods;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {

	}

	/**
	 * Cancel pre-auth on refund/cancellation
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {

	}
}

new WC_PPEC();
