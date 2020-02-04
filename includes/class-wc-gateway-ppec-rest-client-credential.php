<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_REST_Client_Credential {

	/**
	 * Client ID.
	 *
	 * @var string
	 */
	protected $_client_id;

	/**
	 * Client secret.
	 *
	 * @var string
	 */
	protected $_client_secret;


	/**
	 * Creates a new instance of a REST API credential.
	 *
	 * @param string $client_id       The API client ID that will be set on this object.
	 * @param string $client_secret   The API client secret that will be set on this object.
	 */
	public function __construct( $client_id, $client_secret ) {
		$this->_client_id     = $client_id;
		$this->_client_secret = $client_secret;
	}

	/**
	 * Returns the REST API Client ID.
	 *
	 * @return string
	 */
	public function get_client_id() {
		return $this->_client_id;
	}

	/**
	 * Returns the REST API Client Secret.
	 *
	 * @return string
	 */
	public function get_client_secret() {
		return $this->_client_secret;
	}

	/**
	 * Returns a base64-encoded string to be used for basic HTTP authorization using this credential.
	 *
	 * @return string
	 */
	public function get_basic_auth_header_string() {
		return base64_encode( $this->get_client_id() . ':' . $this->get_client_secret() );
	}

}
