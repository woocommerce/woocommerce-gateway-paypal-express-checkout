<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WC_Gateway_PPEC_Client_Credential {

	/**
	 * API username.
	 *
	 * @var string
	 */
	protected $_username;

	/**
	 * API password.
	 *
	 * @var string
	 */
	protected $_password;

	/**
	 * API subject.
	 *
	 * @var string
	 */
	protected $_subject;

	/**
	 * Get API username.
	 *
	 * @return string API username
	 */
	public function get_username() {
		return $this->_username;
	}

	/**
	 * Get API password.
	 *
	 * @return string API password
	 */
	public function get_password() {
		return $this->_password;
	}

	/**
	 * Get API subject.
	 *
	 * @return string API subject
	 */
	public function get_subject() {
		return $this->_subject;
	}

	/**
	 * Retrieves the subdomain of the endpoint which should be used for this type
	 * of credentials.
	 *
	 * @return string The appropriate endpoint, e.g. https://api.paypal.com/nvp
	 *                in this case the subdomain is 'api'
	 */
	abstract public function get_endpoint_subdomain();

	/**
	 * Retrieves a list of credentialing parameters that should be supplied to
	 * PayPal.
	 *
	 * @return array An array of name-value pairs containing the API credentials
	 *               from this object.
	 */
	public function get_request_params() {
		$params = array(
			'USER' => $this->_username,
			'PWD'  => $this->_password,
		);

		if ( ! empty( $this->_subject ) ) {
			$params['SUBJECT'] = $this->_subject;
		}

		return $params;
	}

	/**
	 * Allow certificate-based credential to configure cURL, especially
	 * to set CURLOPT_SSLCERT and CURLOPT_SSLCERTPASSWD.
	 *
	 * @throws Exception
	 *
	 * @param resource &$handle The cURL handle returned by curl_init().
	 * @param array    $r       The HTTP request arguments.
	 * @param string   $url     The request URL.
	 *
	 * @return void
	 */
	public function configure_curl( $handle, $r, $url ) {
		curl_setopt( $handle, CURLOPT_CAINFO, wc_gateway_ppec()->includes_path . 'pem/bundle.pem' );
	}
}
