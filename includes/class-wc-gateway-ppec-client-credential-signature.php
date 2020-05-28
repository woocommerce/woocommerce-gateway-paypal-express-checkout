<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Client_Credential_Signature extends WC_Gateway_PPEC_Client_Credential {

	/**
	 * Signature string.
	 *
	 * @var string
	 */
	protected $_signature;

	/**
	 * Creates a new instance of signature-based credential.
	 *
	 * @param string $username  The API username that will be set on this object.
	 * @param string $password  The API password that will be set on this object.
	 * @param string $signature The API signature that will be set on this object.
	 * @param string $subject   The API subject that will be set on this object, or false if there is no subject.
	 */
	public function __construct( $username, $password, $signature, $subject = '' ) {
		$this->_username  = $username;
		$this->_password  = $password;
		$this->_signature = $signature;
		$this->_subject   = $subject;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_request_params() {
		$params              = parent::get_request_params();
		$params['SIGNATURE'] = $this->_signature;

		return $params;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_endpoint_subdomain() {
		return 'api-3t';
	}

	/**
	 * Get signature.
	 *
	 * @return string
	 */
	public function get_signature() {
		return $this->_signature;
	}
}
