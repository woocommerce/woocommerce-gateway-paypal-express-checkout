<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PayPal NVP (Name-Value Pair) API client. This client supports both certificate
 * and signature for authentication.
 *
 * @see https://developer.paypal.com/docs/classic/api/#ec
 */
class WC_Gateway_PPEC_Client {

	/**
	 * Client credential.
	 *
	 * @var WC_Gateway_PPEC_Client_Credential
	 */
	protected $_credential;

	/**
	 * PayPal environment. Either 'sandbox' or 'live'.
	 *
	 * @var string
	 */
	protected $_environment;

	const INVALID_CREDENTIAL_ERROR  = 1;
	const INVALID_ENVIRONMENT_ERROR = 2;
	const REQUEST_ERROR             = 3;
	const API_VERSION               = '120.0';

	/**
	 * Constructor.
	 *
	 * @param mixed  $credential  Client's credential
	 * @param string $environment Client's environment
	 *
	 */
	public function __construct( $credential, $environment = 'live' ) {
		$this->_credential  = $credential;
		$this->_environment = $environment;
	}

	/**
	 * Set credential for the client.
	 *
	 * @param WC_Gateway_PPEC_Client_Credential $credential Client's credential
	 */
	public function set_credential( WC_Gateway_PPEC_Client_Credential $credential ) {
		$this->_credential = $credential;
	}

	/**
	 * Set environment for the client.
	 *
	 * @param string $environment Environment. Either 'live' or 'sandbox'
	 */
	public function set_environment( $environment ) {
		if ( ! in_array( $environment, array( 'live', 'sandbox' ) ) ) {
			$environment = 'live';
		}

		$this->_environment = $environment;
	}

	/**
	 * Get PayPal endpoint.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/#ec
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return sprintf(
			'https://%s%s.paypal.com/nvp',

			$this->_credential->get_endpoint_subdomain(),
			'sandbox' === $this->_environment ? '.sandbox' : ''
		);
	}

	/**
	 * Make a remote request to PayPal API.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/NVPAPIOverview/#creating-an-nvp-request
	 *
	 * @param  array $params NVP request parameters
	 * @return array         NVP response
	 */
	protected function _request( array $params ) {
		try {
			wc_gateway_ppec_log( sprintf( '%s: trying to make a request to PayPal with params: %s', __METHOD__, print_r( $params, true ) ) );

			// Make sure $_credential and $_environment have been configured.
			if ( ! $this->_credential ) {
				throw new Exception( __( 'Missing credential', 'woocommerce-gateway-ppec' ), self::INVALID_CREDENTIAL_ERROR );
			}

			if ( ! is_a( $this->_credential, 'WC_Gateway_PPEC_Client_Credential' ) ) {
				throw new Exception( __( 'Invalid credential object', 'woocommerce-gateway-ppec' ), self::INVALID_CREDENTIAL_ERROR );
			}

			if ( ! in_array( $this->_environment, array( 'live', 'sandbox' ) ) ) {
				throw new Exception( __( 'Invalid environment', 'woocommerce-gateway-ppec' ), self::INVALID_ENVIRONMENT_ERROR );
			}

			// First, add in the necessary credential parameters.
			$body = array_merge( $params, $this->_credential->get_request_params() );
			$args = array(
				'method'      => 'POST',
				'body'        => $body,
				'user-agent'  => __CLASS__,
				'httpversion' => '1.1',
			);

			// For cURL transport.
			add_action( 'http_api_curl', array( $this->_credential, 'configure_curl' ), 10, 3 );

			wc_gateway_ppec_log( sprintf( '%s: remote request to %s with args: %s', __METHOD__, $this->get_endpoint(), print_r( $args, true ) ) );

			$resp = wp_safe_remote_post( $this->get_endpoint(), $args );

			wc_gateway_ppec_log( sprintf( '%s: response from remote request to %s: %s', __METHOD__, $this->get_endpoint(), print_r( $resp, true ) ) );

			if ( is_wp_error( $resp ) ) {
				throw new Exception( sprintf( __( 'An error occurred while trying to connect to PayPal: %s', 'woocommerce-gateway-ppec' ), $resp->get_error_message() ), self::REQUEST_ERROR );
			}

			parse_str( wp_remote_retrieve_body( $resp ), $result );

			if ( ! array_key_exists( 'ACK', $result ) ) {
				throw new Exception( __( 'Malformed response received from PayPal', 'woocommerce-gateway-ppec' ), self::REQUEST_ERROR );
			}

			wc_gateway_ppec_log( sprintf( '%s: acknowleged response body: %s', __METHOD__, print_r( $result, true ) ) );

			remove_action( 'http_api_curl', array( $this->_credential, 'configure_curl' ), 10 );

			// Let the caller deals with the response.
			return $result;

		} catch ( Exception $e ) {

			remove_action( 'http_api_curl', array( $this->_credential, 'configure_curl' ), 10 );

			// TODO: Maybe returns WP_Error ?
			$error = array(
				'ACK'             => 'Failure',
				'L_ERRORCODE0'    => $e->getCode(),
				'L_SHORTMESSAGE0' => 'Error in ' . __METHOD__,
				'L_LONGMESSAGE0'  => $e->getMessage(),
				'L_SEVERITYCODE0' => 'Error'
			);

			wc_gateway_ppec_log( sprintf( '%s: exception is thrown while trying to make a request to PayPal: %s', __METHOD__, $e->getMessage() ) );
			wc_gateway_ppec_log( sprintf( '%s: returns error: %s', __METHOD__, print_r( $error, true ) ) );

			return $error;

		}
	}

	/**
	 * Initiates an Express Checkout transaction.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
	 *
	 * @param  array $params NVP params
	 * @return array         NVP response
	 */
	public function set_express_checkout( array $params ) {
		$params['METHOD']  = 'SetExpressCheckout';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	/**
	 * Get details from a given token.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/GetExpressCheckoutDetails_API_Operation_NVP/
	 *
	 * @param  array $params NVP params
	 * @return array         NVP response
	 */
	public function get_express_checkout_details( $token ) {
		$params = array(
			'METHOD'  => 'GetExpressCheckoutDetails',
			'VERSION' => self::API_VERSION,
			'TOKEN'   => $token,
		);

		return $this->_request( $params );
	}

	/**
	 * Completes an Express Checkout transaction. If you set up a billing agreement
	 * in your 'SetExpressCheckout' API call, the billing agreement is created
	 * when you call the DoExpressCheckoutPayment API operation.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
	 *
	 * @param  array $params NVP params
	 * @return array         NVP response
	 */
	public function do_express_checkout_payment( $params ) {
		$params['METHOD']       = 'DoExpressCheckoutPayment';
		$params['VERSION']      = self::API_VERSION;
		$params['BUTTONSOURCE'] = 'WooThemes_EC';

		return $this->_request( $params );
	}

	public function do_express_checkout_capture( $params ) {
		$params['METHOD']  = 'DoCapture';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	public function do_express_checkout_void( $params ) {
		$params['METHOD']  = 'DoVoid';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	public function get_transaction_details( $params ) {
		$params['METHOD']  = 'GetTransactionDetails';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	/**
	 * Obtain your Pal ID, which is the PayPal–assigned merchant account number,
	 * and other informaton about your account.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/GetPalDetails_API_Operation_NVP/
	 *
	 * @return array NVP response
	 */
	public function get_pal_details() {
		$params['METHOD']  = 'GetPalDetails';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	/**
	 * Issues a refund to the PayPal account holder associated with a transaction.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/RefundTransaction_API_Operation_NVP/
	 *
	 * @param  array $params NVP params
	 * @return array         NVP response
	 */
	public function refund_transaction( $params ) {
		$params['METHOD']  = 'RefundTransaction';
		$params['VERSION'] = self::API_VERSION;

		return $this->_request( $params );
	}

	public function test_api_credentials( $credentials, $environment = 'sandbox' ) {
		$this->set_credential( $credentials );
		$this->set_environment( $environment );

		$result = $this->get_pal_details();

		if ( 'Success' != $result['ACK'] && 'SuccessWithWarning' != $result['ACK'] ) {
			// Look at the result a little more closely to make sure it's a credentialing issue.
			$found_10002 = false;
			foreach ( $result as $index => $value ) {
				if ( preg_match( '/^L_ERRORCODE\d+$/', $index ) ) {
					if ( '10002' == $value ) {
						$found_10002 = true;
					}
				}
			}

			if ( $found_10002 ) {
				return false;
			} else {
				// Call failed for some other reason.
				throw new PayPal_API_Exception( $result );
			}
		}

		return $result['PAL'];
	}

	// Probe to see whether the merchant has the billing address feature enabled.  We do this
	// by running a SetExpressCheckout call with REQBILLINGADDRESS set to 1; if the merchant has
	// this feature enabled, the call will complete successfully; if they do not, the call will
	// fail with error code 11601.
	public function test_for_billing_address_enabled( $credentials, $environment = 'sandbox' ) {
		$this->set_credential( $credentials );
		$this->set_environment( $environment );

		$req = array(
			'RETURNURL'         => home_url( '/' ),
			'CANCELURL'         => home_url( '/' ),
			'REQBILLINGADDRESS' => '1',
			'AMT'               => '1.00'
		);
		$result = $this->set_express_checkout( $req );

		if ( 'Success' != $result['ACK'] && 'SuccessWithWarning' != $result['ACK'] ) {
			$found_11601 = false;
			foreach ( $result as $index => $value ) {
				if ( preg_match( '/^L_ERRORCODE\d+$/', $index ) ) {
					if ( '11601' == $value ) {
						$found_11601 = true;
					}
				}
			}

			if ( $found_11601 ) {
				return false;
			} else {
				throw new PayPal_API_Exception( $result );
			}
		}

		return true;
	}
}
