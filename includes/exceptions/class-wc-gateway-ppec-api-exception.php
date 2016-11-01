<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PayPal API Exception.
 */
class PayPal_API_Exception extends Exception {

	/**
	 * List of errors from PayPal API.
	 *
	 * @var array
	 */
	public $errors;

	/**
	 * Unique identifier of PayPal transaction.
	 *
	 * This identifies the PayPal application that processed the request and
	 * must be provided to Merchant Technical Support if you need their assistance
	 * with a specific transaction.
	 *
	 * @var string
	 */
	public $correlation_id;

	/**
	 * Constructor.
	 *
	 * This constructor takes the API response received from PayPal, parses out the
	 * errors in the response, then places those errors into the $errors property.
	 * It also captures correlation ID and places that in the $correlation_id property.
	 *
	 * @param array $response Response from PayPal API
	 */
	public function __construct( $response ) {
		parent::__construct( __( 'An error occurred while calling the PayPal API.', 'woocommerce-gateway-paypal-express-checkout' ) );

		$errors = array();
		foreach ( $response as $index => $value ) {
			if ( preg_match( '/^L_ERRORCODE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['code'] = $value;
			} elseif ( preg_match( '/^L_SHORTMESSAGE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['message'] = $value;
			} elseif ( preg_match( '/^L_LONGMESSAGE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['long'] = $value;
			} elseif ( preg_match( '/^L_SEVERITYCODE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['severity'] = $value;
			} elseif ( 'CORRELATIONID' == $index ) {
				$this->correlation_id = $value;
			}
		}

		$error_objects = array();
		foreach ( $errors as $value ) {
			$error_objects[] = new PayPal_API_Error( $value['code'], $value['message'], $value['long'], $value['severity'] );
		}

		$this->errors = $error_objects;
	}

	/**
	 * Returns the errors as WP_Error.
	 *
	 * @param string $code WP_Error code
	 *
	 * @return WP_Error Returns the errors as WP_Error
	 */
	public function to_wp_error( $code ) {
		$error_messages = array();
		foreach ( $this->errors as $error ) {
			/* translators: error code and error message about refund from PayPal API. */
			$error_messages[] = sprintf( __( 'Error: %1$s - %2$s.', 'woocommerce-gateway-paypal-express-checkout' ), $error->error_code, $error->long_message );
		}

		if ( empty( $error_messages ) ) {
			$error_messages[] = __( 'An error occurred while calling the PayPal API.', 'woocommerce-gateway-paypal-express-checkout' );
		}

		return new WP_Error( $code, implode( PHP_EOL, $error_messages ) );
	}
}
