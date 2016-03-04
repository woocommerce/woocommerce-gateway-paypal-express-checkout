<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class PayPal_Cart {

	protected $orderTotal;
	protected $orderTax;
	protected $shipping;
	protected $insurance;
	protected $handling;
	protected $items;
	protected $totalItemAmount;
	protected $currency;
	protected $custom;
	protected $invoiceNumber;
	protected $shipDiscountAmount;

	public function setECParams() {

		$stdParams = array (
			'PAYMENTREQUEST_0_AMT' => $this->orderTotal,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
			'PAYMENTREQUEST_0_ITEMAMT' => $this->totalItemAmount,
			'PAYMENTREQUEST_0_SHIPPINGAMT' => $this->shipping,
			'PAYMENTREQUEST_0_INSURANCEAMT' => $this->insurance,
			'PAYMENTREQUEST_0_HANDLINGAMT' => $this->handling,
			'PAYMENTREQUEST_0_TAXAMT' => $this->orderTax,
			'PAYMENTREQUEST_0_CUSTOM' => $this->custom,
			'PAYMENTREQUEST_0_INVNUM' => $this->invoiceNumber,
			'PAYMENTREQUEST_0_SHIPDISCAMT' => $this->shipDiscountAmount
		);

		if ( ! empty( $this->items ) ) {
			$count = 0;
			foreach ( $this->items as $line_item_key => $values ) {
				$lineItemParams = array(
					'L_PAYMENTREQUEST_0_NAME' . $count => $values['name'],
					'L_PAYMENTREQUEST_0_DESC' . $count => ! empty( $values['description'] ) ? $values['description'] : '',
					'L_PAYMENTREQUEST_0_QTY' . $count => $values['quantity'],
					'L_PAYMENTREQUEST_0_AMT' . $count => $values['amount']
				);

				$stdParams = array_merge( $stdParams, $lineItemParams );
				$count++;
			}
		}
		return $stdParams;
	}

}
