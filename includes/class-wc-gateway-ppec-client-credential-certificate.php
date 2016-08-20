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
		parent::configure_curl( $handle, $r, $url );

		$password         = uniqid();
		$certificate_file = $this->_maybe_create_certificate_file( $password );

		if ( false === curl_setopt( $handle, CURLOPT_SSLCERT, $certificate_file ) ) {
			throw new Exception( __( 'Unable to accept certificate during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}

		if ( $this->_use_secure_transport() && false === curl_setopt( $handle, CURLOPT_SSLCERTPASSWD, $password ) ) {
			throw new Exception( __( 'Unable to accept certificate password during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}
	}

	/**
	 * Dump the certificate out to a temporary file, because cURL can't accept
	 * it any other way.
	 *
	 * @throws Exception
	 *
	 * @param string $password Password for certificate when using secure transport
	 *
	 * @return string Filepath of certificate file
	 */
	protected function _maybe_create_certificate_file( $password ) {
		$temp_file = tempnam( sys_get_temp_dir(), 'pptmp_' );
		if ( ! $temp_file ) {
			throw new Exception( sprintf( __( 'Unable to write certificate file %s during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), $temp_file ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}

		if ( $this->_use_secure_transport() ) {
			$this->_maybe_create_secure_certificate_file( $temp_file, $password );
		} else {
			$this->_maybe_create_non_secure_certificate_file( $temp_file );
		}

		return $temp_file;
	}

	/**
	 * If we're using SecureTransport, we have to translate the certificate to
	 * PKCS12 before passing it to cURL.
	 *
	 * @throws Exception
	 *
	 * @param string $temp_file Filepath to temporary certificate file
	 *
	 * @return void
	 */
	protected function _maybe_create_secure_certificate_file( $temp_file, $password ) {
		$private_key = openssl_pkey_get_private( $this->_certificate );

		if ( false === $private_key ) {
			throw new Exception( __( 'Failed to retrieve private key during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}

		if ( ! openssl_pkcs12_export_to_file( $this->_certificate, $temp_file, $private_key, $password ) ) {
			throw new Exception( __( 'Failed to export PKCS12 file during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}
	}

	/**
	 * Create non-password certificate file. Basically just dump the certificate
	 * string to temporary file.
	 *
	 * @throws Exception
	 *
	 * @param string $temp_file Filepath to temporary certificate file
	 *
	 * @return void
	 */
	protected function _maybe_create_non_secure_certificate_file( $temp_file ) {
		if ( false === file_put_contents( $temp_file, $this->_certificate ) ) {
			throw new Exception( sprintf( __( 'Unable to write certificate file %s during cURL configuration', 'woocommerce-gateway-paypal-express-checkout' ), $temp_file ), WC_Gateway_PPEC_Client::INVALID_ENVIRONMENT_ERROR );
		}
	}

	/**
	 * Returns true if secure transport is available in current cURL.
	 *
	 * @return bool
	 */
	protected function _use_secure_transport() {
		$curl_version = curl_version();
		return false !== strpos( $curl_version['ssl_version'], 'SecureTransport' );
	}
}
