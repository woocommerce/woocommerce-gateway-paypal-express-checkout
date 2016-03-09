<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'class-credentials.php' );

class PayPal_API {

	protected $_credentials; // PayPal_Credentials
	protected $_environment; // 'sandbox' or 'live'

	public function setCredentials( $credentials ) {
		if ( is_a( $credentials, 'PayPal_Credentials' ) ) {
			$this->_credentials = $credentials;
		}
	}

	public function setEnvironment( $environment = 'sandbox' ) {
		if ( 'sandbox' == $environment || 'live' == $environment ) {
			$this->_environment = $environment;
		}
	}

	public function __construct( $credentials, $environment = 'sandbox' ) {
		$this->setCredentials( $credentials );
		$this->setEnvironment( $environment );
	}

	protected function runAPICall( $params ) {
		// Make sure $_credentials and $_environment have been configured.
		if ( ! $this->_credentials ) {
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-2',
				'L_SHORTMESSAGE0' => 'Invalid configuration',
				'L_LONGMESSAGE0'  => 'The PayPal_API object has not been properly configured.',
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		if ( ! is_a( $this->_credentials, 'PayPal_Credentials' ) ) {
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-3',
				'L_SHORTMESSAGE0' => 'Invalid configuration',
				'L_LONGMESSAGE0'  => 'The PayPal_API object has not been properly configured.',
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		if ( 'sandbox' != $this->_environment && 'live' != $this->_environment ) {
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-4',
				'L_SHORTMESSAGE0' => 'Invalid configuration',
				'L_LONGMESSAGE0'  => 'The PayPal_API object has not been properly configured.',
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		// First, add in the necessary credential parameters.
		$params = array_merge( $params, $this->_credentials->getApiCredentialParameters() );

		// What endpoint are we going to be talking to?
		$endpoint = 'https://' . $this->_credentials->getApiEndpoint() . ( $this->_environment == 'sandbox' ? '.sandbox' : '' ) . '.paypal.com/nvp';

		// Start building the cURL handle.
		$curl = curl_init( $endpoint );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $curl, CURLOPT_POST, TRUE );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $params ) );
		curl_setopt( $curl, CURLOPT_CAINFO, __DIR__ . '/pem/bundle.pem' );
		curl_setopt( $curl, CURLOPT_SSL_CIPHER_LIST, 'TLSv1' );

		// Let the credentials object set its settings
		if ( ! $this->_credentials->configureCurlHandle( $curl ) ) {
			// TODO: Add some logging
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-1',
				'L_SHORTMESSAGE0' => 'Communication Error',
				'L_LONGMESSAGE0'  => 'Unable to configure cURL handle for connection to server.',
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		$response = curl_exec( $curl );

		if ( ! $response ) {
			// TODO: Add some logging
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-1',
				'L_SHORTMESSAGE0' => 'Communication Error',
				'L_LONGMESSAGE0'  => 'An error occurred while trying to connect to PayPal: ' . curl_error( $curl ),
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		parse_str( $response, $result );

		if ( ! array_key_exists( 'ACK', $result ) ) {
			// TODO: Add some logging
			return array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => '-1',
				'L_SHORTMESSAGE0' => 'Communication Error',
				'L_LONGMESSAGE0'  => 'Malformed response received from PayPal',
				'L_SEVERITYCODE0' => 'Error'
			);
		}

		// Ok, let the caller deal with the response.
		return $result;

	}

	public function SetExpressCheckout( $params ) {
		$params['METHOD'] = 'SetExpressCheckout';
		$params['VERSION'] = '120.0';

		return $this->runAPICall( $params );
	}

	// Since GetExpressCheckoutDetails only requires a token, it doesn't make a whole lot of sense to require an array of parameters
	// like the other calls.
	public function GetExpressCheckoutDetails( $token ) {
		$params = array(
			'METHOD'  => 'GetExpressCheckoutDetails',
			'VERSION' => '120.0',
			'TOKEN'   => $token
		);

		return $this->runAPICall( $params );
	}

	public function DoExpressCheckoutPayment( $params ) {
		$params['METHOD'] = 'DoExpressCheckoutPayment';
		$params['VERSION'] = '120.0';

		return $this->runAPICall( $params );
	}

	public function GetPalDetails() {
		$params['METHOD'] = 'GetPalDetails';
		$params['VERSION'] = '120.0';

		return $this->runAPICall( $params );
	}

	public function RefundTransaction( $params ) {
		$params['METHOD'] = 'RefundTransaction';
		$params['VERSION'] = '120.0';

		return $this->runAPICall( $params );
	}

}
