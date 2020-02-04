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

}
