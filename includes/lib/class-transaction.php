<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'class-api.php' );

class PayPal_Transaction {

	public $txnID;
	public $settings;

	public function __construct( $txnID, $settings ) {
		$this->txnID = $txnID;
		$this->settings = $settings;
	}

	public function doRefund( $amount, $refundType, $reason, $currency ) {

		// create API object
		$api = new PayPal_API( $this->settings->getActiveApiCredentials(), $this->settings->getActiveEnvironment() );

		// add refund params
		$params['TRANSACTIONID'] = $this->txnID;
		$params['REFUNDTYPE']    = $refundType;
		$params['AMT']           = $amount;
		$params['CURRENCYCODE']  = $currency;
		$params['NOTE']          = $reason;

		// do API call
		$response = $api->RefundTransaction( $params );
		// look at ACK to see if success or failure
		// if success return the transaction ID of the refund
		// if failure then do 'throw new PayPal_API_Exception( $response );'

		if ( 'Success' == $response['ACK'] || 'SuccessWithWarning' == $response['ACK'] ) {
			return $response['REFUNDTRANSACTIONID'];
		} else {
			throw new PayPal_API_Exception( $response );
		}

	}

}
