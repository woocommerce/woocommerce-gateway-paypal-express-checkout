<?php
/**
 * PayPal Express Integrated PayPal Signup Handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_IPS_Handler {

	const ONBOARDING_START_URL = 'http://ipsis-vip.ext.external.paypalc3.com/onboarding/start';
	const ONBOARDING_END_URL = 'http://ipsis-vip.ext.external.paypalc3.com/onboarding/end';

	public function __construct() {
		add_action( 'woocommerce_init', array( $this, 'generate_private_key_after_activated' ) );
		add_action( 'woocommerce_init', array( $this, 'generate_private_key_request' ) );
		add_action( 'woocommerce_init', array( $this, 'start_buffer' ) );
	}

	/**
	 * If the plugin was just activated, generate a private/public key pair for
	 * use with Easy Setup.
	 */
	public function generate_private_key_after_activated() {

		if ( get_option( 'pp_woo_justActivated' ) ) {
			delete_option( 'pp_woo_justActivated' );
			woo_pp_async_generate_private_key();
		}
	}

	/**
	 * @TODO: Instead load a whole WP, we could register it as AJAX handler or
	 * WC-API handler.
	 */
	public function generate_private_key_request() {
		if ( isset( $_GET['start-ips-keygen'] ) && 'true' == $_GET['start-ips-keygen'] ) {
			woo_pp_generate_private_key();
			exit;
		}
	}

	/**
	 * Start buffering if we're on an admin page and the merchant is trying to
	 * use Easy Signup.
	 *
	 * @TODO: Not sure if this is needed
	 */
	public function start_buffer() {

		$is_ips_signup = isset( $_GET['ips-signup'] ) && 'true' == $_GET['ips-signup'];
		$is_ips_return = isset( $_GET['ips-return'] ) && 'true' == $_GET['ips-return'];
		if ( is_admin() && ( $is_ips_signup || $is_ips_return ) ) {
			ob_start();
		}
	}

	public function get_ips_enabled_countries() {
		return array( 'AT', 'BE', 'CH', 'DE', 'DK', 'ES', 'FR', 'GB', 'IT', 'NL', 'NO', 'PL', 'SE', 'TR', 'US' );
	}

	public function ips_signup() {
		$enable_ips = in_array( WC()->countries->get_base_country(), $this->get_ips_enabled_countries() );
		if ( ! $enable_ips ) {
			WC_Admin_Settings::add_error( __( 'Sorry, Easy Setup isn\'t available in your country.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			WC_Admin_Settings::add_error( __( 'Easy Setup requires OpenSSL, but your copy of PHP doesn\'t support it.  Please contact your website administrator for assistance.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		$settings = wc_gateway_ppec()->settings->loadSettings();

		if ( ! $settings->ipsPrivateKey ) {
			// For some reason, the private key isn't there...at all.  Try to generate a new one and bail out.
			woo_pp_async_generate_private_key();
			WC_Admin_Settings::add_error( __( 'Sorry, Easy Setup isn\'t available right now.  Please try again in a few minutes.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		} elseif ( 'not_generated' == $settings->ipsPrivateKey ) {
			woo_pp_async_generate_private_key();
			WC_Admin_Settings::add_error( __( 'Sorry, Easy Setup isn\'t available right now.  Please try again in a few minutes.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		} elseif ( 'generation_started' == $settings->ipsPrivateKey ) {
			WC_Admin_Settings::add_error( __( 'Sorry, Easy Setup isn\'t available right now.  Please try again in a few minutes.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		} elseif ( 'generation_failed' == $settings->ipsPrivateKey ) {
			woo_pp_async_generate_private_key();
			WC_Admin_Settings::add_error( __( 'Easy Setup encountered an error while trying to initialize.  Easy Setup will try to initialize again; however, if you continue to encounter this error, you may want to ask your website administrator to verify that OpenSSL is working properly on your server.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		$private_key = openssl_pkey_get_private( $settings->ipsPrivateKey );
		if ( false === $private_key ) {
			woo_pp_async_generate_private_key();
			WC_Admin_Settings::add_error( __( 'Sorry, Easy Setup isn\'t available right now.  Please try again in a few minutes.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		$details = openssl_pkey_get_details( $private_key );
		$public_key = $details['key'];

		// Build our request.
		$request = new stdClass();
		$request->product = 'express_checkout';
		$request->country = WC()->countries->get_base_country();
		$request->display_mode = 'regular';

		if ( 'certificate' == $_GET['mode'] ) {
			$request->credential_type = 'certificate';
		} else {
			$request->credential_type = 'signature';
		}

		$request->public_key = $public_key;

		if ( 'live' == $_GET['env'] ) {
			$request->environment = 'live';
		} else {
			$request->environment = 'sandbox';
		}

		$request->return_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_ppec_with_paypal&ips-return=true&env=' . $request->environment );

		$request_args = array(
			'method'      => 'POST',
			'body'        => json_encode( $request ),
			'user-agent'  => __CLASS__,
			'httpversion' => '1.1',
		);

		$response = wp_safe_remote_post( WC_Gateway_PPEC_IPS_Handler::ONBOARDING_START_URL, $request_args );

		if ( is_wp_error( $response ) ) {
			WC_Admin_Settings::add_error( __( 'Sorry, an error occurred while initializing Easy Setup.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();

			return;
		}

		$resp_obj = json_decode( wp_remote_retrieve_body( $response ) );

		if ( false === $resp_obj ) {
			WC_Admin_Settings::add_error( __( 'Sorry, an error occurred while initializing Easy Setup.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		if ( ! property_exists( $resp_obj, 'result' ) || 'success' != $resp_obj->result ) {
			WC_Admin_Settings::add_error( __( 'Sorry, an error occurred while initializing Easy Setup.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		if ( ! property_exists( $resp_obj, 'merchant_id' ) || ! property_exists( $resp_obj, 'redirect_url' ) || ! property_exists( $resp_obj, 'expires_in' ) ) {
			WC_Admin_Settings::add_error( __( 'Sorry, an error occurred while initializing Easy Setup.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			ob_end_flush();
			return;
		}

		// Store the private key in a transient.
		set_transient( 'ppips_' . $resp_obj->merchant_id, $settings->ipsPrivateKey, $resp_obj->expires_in );

		// Redirect the merchant.
		wp_safe_redirect( $resp_obj->redirect_url );
		exit;
	}

	protected function ips_redirect_and_die( $error_msg ) {
		$redirect_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_ppec_with_paypal' );
		if ( ! is_array( $error_msg ) ) {
			$error_msgs = array( array(
				'error' => $error_msg
			) );
		} else {
			$error_msgs = $error_msg;
		}

		add_option( 'woo_pp_admin_error', $error_msgs );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function ips_return() {

		$settings = wc_gateway_ppec()->settings->loadSettings();

		// Make sure we have the merchant ID.
		if ( empty( $_GET['merchantId'] ) || empty( $_GET[ 'merchantIdInPayPal'] ) || empty( $_GET['env'] ) ) {
			$this->ips_redirect_and_die( __( 'Some required information that was needed to complete Easy Setup is missing.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		$merchant_id = trim( $_GET['merchantId'] );
		$payer_id    = trim( $_GET['merchantIdInPayPal'] );
		$env         = trim( $_GET['env'] );

		// Validate the merchant ID.
		if ( strlen( $merchant_id ) != 32 || ! preg_match( '/^[0-9a-f]+$/', $merchant_id ) ) {
			$this->ips_redirect_and_die( __( 'Some required information that was needed to complete Easy Setup is invalid.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// Validate the payer ID.
		if ( strlen( $payer_id ) != 13 || ! preg_match( '/^[0-9A-Z]+$/', $payer_id ) ) {
			$this->ips_redirect_and_die( __( 'Some required information that was needed to complete Easy Setup is invalid.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// Validate the environment.
		if ( 'live' != $env && 'sandbox' != $env ) {
			$this->ips_redirect_and_die( __( 'Some required information that was needed to complete Easy Setup is invalid.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// Grab the private key for the merchant.
		$raw_key = get_transient( 'ppips_' . $merchant_id );
		if ( false === $raw_key ) {
			$this->ips_redirect_and_die( __( 'Your Easy Setup session is invalid or has expired.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// Validate that we can still read the key.
		$key = openssl_pkey_get_private( $raw_key );
		if ( false === $key ) {
			$this->ips_redirect_and_die( __( 'Sorry, an internal error occurred.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		// Make a request out to the IPS service to get a merchant ID.
		$request = new stdClass();
		$request->merchant_id = $merchant_id;
		$request->merchant_payer_id = $payer_id;
		$request->environment = $env;

		$request_args = array(
			'method'      => 'POST',
			'body'        => json_encode( $request ),
			'user-agent'  => __CLASS__,
			'httpversion' => '1.1',
		);

		$response = wp_safe_remote_post( WC_Gateway_PPEC_IPS_Handler::ONBOARDING_END_URL, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		$resp_obj = json_decode( wp_remote_retrieve_body( $response ) );

		if ( false === $resp_obj ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! property_exists( $resp_obj, 'result' ) || 'success' != $resp_obj->result ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! property_exists( $resp_obj, 'credentials' ) || ! property_exists( $resp_obj, 'key' ) ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! openssl_open( base64_decode( $resp_obj->credentials ), $credentials_json, base64_decode( $resp_obj->key ), $key ) ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		$credentials = json_decode( $credentials_json );
		if ( false === $credentials ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! property_exists( $credentials, 'style' ) ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( 'certificate' != $credentials->style && 'signature' != $credentials->style ) {
			$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( 'signature' == $credentials->style ) {
			if ( ! property_exists( $credentials, 'username' ) || ! property_exists( $credentials, 'password' ) || ! property_exists( $credentials, 'signature' ) ) {
				$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			}

			$creds = new WC_Gateway_PPEC_Client_Credential_Signature( $credentials->username, $credentials->password, $credentials->signature );
		} elseif ( 'certificate' == $credentials->style ) {
			if ( ! property_exists( $credentials, 'username' ) || ! property_exists( $credentials, 'password' ) || ! property_exists( $credentials, 'certificate' ) ) {
				$this->ips_redirect_and_die( __( 'Sorry, Easy Setup encountered an error.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			}

			$creds = new WC_Gateway_PPEC_Client_Credential_Certificate( $credentials->username, $credentials->password, $credentials->certificate );
		}

		$error_msgs = array();

		try {
			$payer_id = wc_gateway_ppec()->client->test_api_credentials( $creds, $env );
			if ( ! $payer_id ) {
				$this->ips_redirect_and_die( __( 'Easy Setup was able to obtain your API credentials, but was unable to verify that they work correctly.  Please make sure your PayPal account is set up properly and try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' ) );
			}
			$creds->set_payer_id( $payer_id );
		} catch( PayPal_API_Exception $ex ) {
			$error_msgs[] = array(
				'warning' => __( 'Easy Setup was able to obtain your API credentials, but an error occurred while trying to verify that they work correctly.  Please try Easy Setup again.', 'woocommerce-gateway-paypal-express-checkout' )
			);
		}

		$is_enabled_for_billing_address = false;
		try {
			$is_enabled_for_billing_address = wc_gateway_ppec()->client->test_for_billing_address_enabled( $creds, $env );
		} catch( PayPal_API_Exception $ex ) {
			$error_msgs[] = array(
				'warning' => __( 'Easy Setup encountered an error while trying to determine which features are enabled on your PayPal account.  You may not see all of the features below that are enabled for your PayPal account.  To try again, click "Save Changes".', 'woocommerce-gateway-paypal-express-checkout' )
			);
		}

		if ( ! empty( $_GET['returnMessage'] ) && strlen( trim( $_GET['returnMessage'] ) ) ) {
			$error_msgs[] = array(
				'success' => sprintf( __( 'PayPal has the following message for you: %s', 'woocommerce-gateway-paypal-express-checkout' ), $_GET['returnMessage'] )
			);
		}

		$error_msgs[] = array(
			'success' => __( 'Success!  Your PayPal account has been set up successfully.', 'woocommerce-gateway-paypal-express-checkout' )
		);

		if ( ! $settings->enabled ) {
			$error_msgs[] = array(
				'warning' => __( 'PayPal Express Checkout is not enabled.  To allow your buyers to pay with PayPal, make sure "Enable PayPal Express Checkout" is checked.', 'woocommerce-gateway-paypal-express-checkout' )
			);
		}

		$settings->environment = $env;
		if ( 'live' == $env ) {
			$settings->liveApiCredentials = $creds;
		} else {
			$settings->sandboxApiCredentials = $creds;
		}

		$settings->saveSettings();

		$this->ips_redirect_and_die( $error_msgs );
	}
}
