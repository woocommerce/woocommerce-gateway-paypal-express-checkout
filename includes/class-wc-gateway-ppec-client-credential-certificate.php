<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Client_Credential_Certificate extends WC_Gateway_PPEC_Client_Credential {

	/**
	 * Certificate string.
	 *
	 * @var string
	 */
	protected $_certificate;

	/**
	 * Creates a new instance of certificate-based credential.
	 *
	 * @param string $username    The API username that will be set on this object.
	 * @param string $password    The API password that will be set on this object.
	 * @param string $certificate The API certificate that will be set on this object.
	 * @param string $subject     The API subject that will be set on this object, or false if there is no subject.
	 */
	public function __construct( $username, $password, $certificate, $subject = '' ) {
		$this->_username    = $username;
		$this->_password    = $password;
		$this->_certificate = $certificate;
		$this->_subject     = $subject;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_endpoint_subdomain() {
		return 'api';
	}

	/**
	 * Get certificate.
	 *
	 * @return string
	 */
	public function get_certificate() {
		return $this->_certificate;
	}
}
