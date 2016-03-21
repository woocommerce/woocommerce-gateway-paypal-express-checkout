<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Refund {

	public static function refund_order( $order, $amount, $refundType, $reason, $currency ) {

		// add refund params
		$params['TRANSACTIONID'] = $order->get_transaction_id();
		$params['REFUNDTYPE']    = $refundType;
		$params['AMT']           = $amount;
		$params['CURRENCYCODE']  = $currency;
		$params['NOTE']          = $reason;

		// do API call
		$response = wc_gateway_ppec()->client->refund_transaction( $params );

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
