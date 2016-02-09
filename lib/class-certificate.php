<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

require_once( 'class-credentials.php' );

class PayPal_Certificate_Credentials extends PayPal_Credentials {
	protected $_apiCertificate = null;
	private $_apiCertificateFilename = null;
	private $_apiCertificatePassword = null;
	private $_isUsingSecureTransport = false;

	public function setApiCertificateFromFile( $filename ) {
		$this->apiCertificate = file_get_contents( $filename );
		
		if ( FALSE === $this->apiCertificate ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Retrieves a list of credentialing parameters that should be supplied to PayPal.
	 * @return array An array of name-value pairs containing the API credentials from this object.
	 */
	public function getApiCredentialParameters() {
		return parent::getApiCredentialParameters();
	}
	
	/**
	 * Configures a cURL handle to make it usable with the type of credentials this object will store.
	 * @param resource &$curl The cURL handle to which the settings will be applied.
	 * @return bool Returns true if configuration succeeded, or false if it did not.
	 */
	public function configureCurlHandle( &$curl ) {
		// Fail if the certificate hasn't been set.
		if ( ! $this->apiCertificate ) {
			// TODO: Add some logging
			return false;
		}

		if ( ! $this->_apiCertificateFilename ) {
			// Dump the certificate out to a temporary file, because cURL can't accept it any other way.
			$tempFile = tempnam( sys_get_temp_dir(), 'pptmp_' );
			if ( ! $tempFile ) {
				// Something went wrong while creating a temporary file.
				// TODO: Add some logging
				return false;
			}
			
			// If we're using SecureTransport, we have to translate the certificate to PKCS12 before passing it to cURL.
			// Damn you SecureTransport...

			$curl_version = curl_version();
			if ( FALSE !== strpos( $curl_version['ssl_version'], 'SecureTransport' ) ) {
				$password = uniqid();
				$private_key = openssl_pkey_get_private( $this->apiCertificate );
				if ( FALSE === $private_key ) {
					// Failed to retrieve the private key
					// TODO: Add some logging
					return false;
				}
				
				if ( ! openssl_pkcs12_export_to_file( $this->apiCertificate, $tempFile, $private_key, $password ) ) {
					// Failed to export the PKCS12 file
					// TODO: Add some logging
					return false;
				}

				$this->_isUsingSecureTransport = true;
				$this->_apiCertificatePassword = $password;

			} else {
				if ( FALSE === file_put_contents( $tempFile, $this->apiCertificate ) ) {
					// Something went wrong while writing the certificate out to a file.
					// TODO: Add some logging
					return false;
				}
			}
		
			$this->_apiCertificateFilename = $tempFile;
			
		}
			
		if ( FALSE === curl_setopt( $curl, CURLOPT_SSLCERT, $this->_apiCertificateFilename ) ) {
			// cURL didn't accept the cert for some reason
			// TODO: Add some logging
			return false;
		}
		
		if ( $this->_isUsingSecureTransport ) {
			if ( FALSE === curl_setopt( $curl, CURLOPT_SSLCERTPASSWD, $this->_apiCertificatePassword ) ) {
				// cURL didn't accept the cert password...for some reason
				// TODO: Add some logging
				return false;
			}
		}
		
		return true;
		
	}
	
	/**
	 * Retrieves the hostname of the endpoint which should be used for this type of credentials.
	 * @return string The unqualified hostname of the appropriate endpoint.  This object always returns "api".
	 */
	public function getApiEndpoint() {
		return 'api';
	}
	
	public function __construct( $username, $password, $certString, $subject = false ) {
		parent::__construct();
		
		$this->validParams[]  = 'apiCertificate';
		$this->apiUsername    = $username;
		$this->apiPassword    = $password;
		$this->apiCertificate = $certString;
		$this->subject        = $subject;
	}
	
	function __destruct() {
		if ( $_apiCertificateFilename ) {
			unlink( $_apiCertificateFilename );
		}
	}

}
