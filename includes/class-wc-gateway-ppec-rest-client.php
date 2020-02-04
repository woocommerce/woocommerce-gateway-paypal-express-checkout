<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_REST_Client {

	/**
	 * Client credential.
	 *
	 * @var WC_Gateway_PPEC_REST_Client_Credential
	 */
	protected $_credential;

	/**
	 * PayPal environment. Either 'sandbox' or 'live'.
	 *
	 * @var string
	 */
	protected $_environment;

	/**
	 * Constructor.
	 *
	 * @param mixed  $credential  Client's credential.
	 * @param string $environment Client's environment. Either 'sandbox' or 'live'.
	 */
	public function __construct( $credential, $environment = 'live' ) {
		$this->_environment = $environment;

		if ( is_a( $credential, 'WC_Gateway_PPEC_REST_Client_Credential' ) ) {
			$this->set_credential( $credential );
		}
	}

	/**
	 * Set credential for the client.
	 *
	 * @param WC_Gateway_PPEC_REST_Client_Credential $credential
	 */
	public function set_credential( WC_Gateway_PPEC_REST_Client_Credential $credential ) {
		$this->_credential = $credential;
	}

	/**
	 * Set the environment for the client.
	 *
	 * @param string $environment Client's environment. Either 'sandbox' or 'live'.
	 */
	public function set_environment( $environment ) {
		$this->_environment = ( in_array( $environment, array( 'live', 'sandbox' ) ) ) ? $environment : 'live';
	}

	/**
	 * Tests the given REST API credentials.
	 *
	 * @param WC_Gateway_PPEC_REST_Client_Credential $credential
	 * @param string $environment
	 */
	public function test_api_credentials( $credential, $environment = 'live' ) {
		$this->set_credential( $credential );
		$this->set_environment( $environment );

		$base_url = ( 'sandbox' === $this->_environment ) ? 'https://api.sandbox.paypal.com/' : 'https://api.paypal.com/';
		$url      = $base_url . 'v1/oauth2/token';

		$args = array(
			'method'     => 'POST',
			'timeout'    => 30,
			'user-agent' => __CLASS__,
			'headers'    => array(
				'Authorization' => sprintf( 'Basic %s', $this->_credential->get_basic_auth_header_string() ),
			),
			'body'       => array(
				'grant_type' => 'client_credentials',
			),
		);

		$response      = wp_remote_request( $url, $args );
		$error_message = '';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		}

		if ( ! $error_message ) {
			$body = json_decode( $response['body'] );

			if ( ! empty( $body->error ) ) {
				$error_message = ( ! empty( $body->error_description ) ) ? $body->error_description : $body->error;
			}
		}

		if ( ! $error_message ) {
			set_transient(
				'woocommerce_ppec_rest_api_oauth_token_' . $this->_environment . '_' . md5( $this->_credential->get_client_id() . ':' . $this->_credential->get_client_secret() ),
				array(
					'token'  => $body->access_token,
					'app_id' => $body->app_id,
				),
				$body->expires_in
			);
		}

		return ( ! $error_message ) ? true : new WP_Error( 'ppec_rest_credentials_validation_error', $error_message );
	}

}
