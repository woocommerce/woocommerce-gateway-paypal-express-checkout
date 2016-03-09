<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_API_Error {
	public $error_code;
	public $short_message;
	public $long_message;
	public $severity_code;

	public function __construct( $error_code, $short_message, $long_message, $severity_code ) {
		$this->error_code    = $error_code;
		$this->short_message = $short_message;
		$this->long_message  = $long_message;
		$this->severity_code = $severity_code;
	}

	public function mapToBuyerFriendlyError() {
		switch ( $this->error_code ) {
			case '-1':    return 'Unable to communicate with PayPal.  Please try your payment again.';
			case '10407': return 'PayPal rejected your email address because it is not valid.  Please double-check your email address and try again.';
			case '10409':
			case '10421':
			case '10410': return 'Your PayPal checkout session is invalid.  Please check out again.';
			case '10411': return 'Your PayPal checkout session has expired.  Please check out again.';
			case '11607':
			case '10415': return 'Your PayPal payment has already been completed.  Please contact the store owner for more information.';
			case '10416': return 'Your PayPal payment could not be processed.  Please check out again or contact PayPal for assistance.';
			case '10417': return 'Your PayPal payment could not be processed.  Please select an alternative method of payment or contact PayPal for assistance.';
			case '10486':
			case '10422': return 'Your PayPal payment could not be processed.  Please return to PayPal and select a new method of payment.';
			case '10485':
			case '10435': return 'You have not approved this transaction on the PayPal website.  Please check out again and be sure to complete all steps of the PayPal checkout process.';
			case '10474': return 'Your shipping address may not be in a different country than your country of residence.  Please double-check your shipping address and try again.';
			case '10537': return 'This store does not accept transactions from buyers in your country.  Please contact the store owner for assistance.';
			case '10538': return 'The transaction is over the threshold allowed by this store.  Please contact the store owner for assistance.';
			case '11611':
			case '10539': return 'Your transaction was declined.  Please contact the store owner for assistance.';
			case '10725': return 'The country in your shipping address is not valid.  Please double-check your shipping address and try again.';
			case '10727': return 'The street address in your shipping address is not valid.  Please double-check your shipping address and try again.';
			case '10728': return 'The city in your shipping address is not valid.  Please double-check your shipping address and try again.';
			case '10729': return 'The state in your shipping address is not valid.  Please double-check your shipping address and try again.';
			case '10730': return 'The ZIP code or postal code in your shipping address is not valid.  Please double-check your shipping address and try again.';
			case '10736': return 'PayPal rejected your shipping address because the city, state, and/or ZIP code are incorrect.  Please double-check that they are all spelled correctly and try again.';
			case '13113':
			case '11084': return 'Your PayPal payment could not be processed.  Please contact PayPal for assistance.';
			case '12126':
			case '12125': return 'The redemption code(s) you entered on PayPal cannot be used at this time.  Please return to PayPal and remove them.';
			case '17203':
			case '17204':
			case '17200': return 'Your funding instrument is invalid.  Please check out again and select a new funding source.';
			default:      return 'An error occurred while processing your PayPal payment.  Please contact the store owner for assistance.';
		}
	}
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

class PayPal_Missing_Session_Exception extends Exception {
	public function __construct() {
		parent::__construct( 'The buyer\'s session information could not be found.' );
	}
}
