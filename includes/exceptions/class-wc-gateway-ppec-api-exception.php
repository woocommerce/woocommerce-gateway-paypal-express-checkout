<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_API_Exception extends Exception {
	public $errors;
	public $correlation_id;

	// This constructor takes the API response received from PayPal, parses out the errors in the response,
	// then places those errors into the $errors property.  It also captures correlation ID and places that
	// in the $correlation_id property.
	public function __construct( $response ) {
		parent::__construct( 'An error occurred while calling the PayPal API.' );

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
}
