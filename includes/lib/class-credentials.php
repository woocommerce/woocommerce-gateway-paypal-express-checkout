<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Interface for storing PayPal API credentials.
 */
abstract class PayPal_Credentials {

	protected $params;
	protected $validParams = array( 'apiUsername', 'apiPassword', 'subject', 'payerID' );

	public function __set( $name, $value ) {
		if ( in_array( $name, $this->validParams ) ) {
			$this->params[ $name ] = $value;
		}
	}

	public function __get( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			return $this->params[ $name ];
		} else {
			return null;
		}
	}

	public function __isset( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Configures a cURL handle to make it usable with the type of credentials this object will store.
	 * @param resource &$curl The cURL handle to which the settings will be applied.
	 * @return bool Returns true if configuration succeeded, or false if it did not.
	 */
	abstract public function configureCurlHandle( &$curl );

	/**
	 * Retrieves the hostname of the endpoint which should be used for this type of credentials.
	 * @return string The unqualified hostname of the appropriate endpoint, such as "api-3t" or "api".
	 */
	abstract public function getApiEndpoint();

	/**
	 * Retrieves a list of credentialing parameters that should be supplied to PayPal.
	 * @return array An array of name-value pairs containing the API credentials from this object.
	 */
	protected function getApiCredentialParameters() {
		$params = array(
			'USER' => $this->apiUsername,
			'PWD'  => $this->apiPassword
		);

		if ( $this->subject ) {
			$params['SUBJECT'] = $this->subject;
		}

		return $params;
	}

	public function __construct() {}

}
