<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'class-credentials.php' );

/**
 * Class for storing PayPal signature credentials.
 */
class PayPal_Signature_Credentials extends PayPal_Credentials {

	/**
	 * Retrieves a list of credentialing parameters that should be supplied to PayPal.
	 * @return array An array of name-value pairs containing the API credentials from this object.
	 */
	public function getApiCredentialParameters() {
		$params = parent::getApiCredentialParameters();
		$params['SIGNATURE'] = $this->apiSignature;

		return $params;
	}

	/**
	 * Configures a cURL handle to make it usable with the type of credentials this object will store.
	 * @param resource &$curl The cURL handle to which the settings will be applied.
	 * @return bool Returns true if configuration succeeded, or false if it did not.
	 */
	public function configureCurlHandle( &$curl ) {
		// Signatures don't need any special configuration
		return true;
	}

	/**
	 * Retrieves the hostname of the endpoint which should be used for this type of credentials.
	 * @return string The unqualified hostname of the appropriate endpoint.  This object always returns "api-3t".
	 */
	public function getApiEndpoint() {
		return 'api-3t';
	}

	/**
	 * Creates a new instance of PayPal_Signature_Credentials.
	 * @param string $username The API username that will be set on this object.
	 * @param string $password The API password that will be set on this object.
	 * @param string $signature The API signature that will be set on this object.
	 * @param string|bool $subject = false The API subject that will be set on this object, or false if there is no subject.
	 */
	public function __construct( $username, $password, $signature, $subject = false ) {
		parent::__construct();

		$this->validParams[] = 'apiSignature';
		$this->apiUsername   = $username;
		$this->apiPassword   = $password;
		$this->apiSignature  = $signature;
		$this->subject       = $subject;

	}

}
