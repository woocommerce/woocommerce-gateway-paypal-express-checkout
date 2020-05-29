<?php

/**
 * TODO: Move each class into its own file and group them under one dir, payment-details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_Payment_Details {
	public $token                = false;
	public $billing_agreement_id = false;
	public $redirect_required    = false;
	public $redirect_requested   = false;
	public $note                 = false;

	public $payments = false;

	public $shipping_option_details = false;

	public function loadFromDoECResponse( $doECResponse ) {
		$map = array(
			'TOKEN'                        => 'token',
			'BILLINGAGREEMENTID'           => 'billing_agreement_id',
			'REDIRECTREQUIRED'             => 'redirect_required',
			'SUCCESSPAGEREDIRECTREQUESTED' => 'redirect_requested',
			'NOTE'                         => 'note',
		);

		$max_payment_num = -1;

		foreach ( $doECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$key        = $map[ $index ];
				$this->$key = $value;
			}
			// Figure out the highest payment number
			if ( preg_match( '/^PAYMENTINFO_(\d)_(TRANSACTIONID|EBAYITEMAUCTIONTXNID|PARENTTRANSACTIONID|RECEIPTID|TRANSACTIONTYPE|PAYMENTTYPE|EXPECTEDECHECKCLEARDATE|ORDERTIME|AMT|CURRENCYCODE|FEEAMT|SETTLEAMT|TAXAMT|EXCHANGERATE|PAYMENTSTATUS|PENDINGREASON|REASONCODE|HOLDDECISION|SHIPPINGMETHOD|PROTECTIONELIGIBILITY|PROTECTIONELIGIBILITYTYPE|RECEIPTREFERENCENUMBER|SHIPPINGAMT|HANDLINGAMT|PAYMENTREQUESTID|INSTRUMENTCATEGORY|INSTRUMENTID|OFFERCODE|OFFERTRACKINGID|SHORTMESSAGE|LONGMESSAGE|ERRORCODE|SEVERITYCODE|ACK|SELLERPAYPALACCOUNTID|SECUREMERCHANTACCOUNTID|SELLERID|SELLERUSERNAME|SELLERREGISTRATIONDATE)$/', $index, $matches ) ) {
				if ( $matches[1] > $max_payment_num ) {
					$max_payment_num = $matches[1];
				}
			}
		}

		if ( $max_payment_num >= 0 ) {
			$this->payments = array();
			for ( $i = 0; $i <= $max_payment_num; $i++ ) {
				$this->payments[ $i ] = new PayPal_Payment_Payment_Details();
				$this->payments[ $i ]->loadFromDoECResponse( $doECResponse, $i );
			}
		}

		$this->shipping_option_details = new PayPal_Payment_Shipping_Option_Details();
		if ( ! $this->shipping_option_details->loadFromDoECResponse( $doECResponse ) ) {
			$this->shipping_option_details = false;
		}

	}
}

class PayPal_Payment_Payment_FMF_Details {
	public $filters = false;

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$max_filter_num = array(
			'PENDING' => -1,
			'REPORT'  => -1,
			'DENY'    => -1,
			'ACCEPT'  => -1,
		);

		$found_any = false;
		foreach ( $doECResponse as $index => $value ) {
			if ( preg_match( '/^L_PAYMENTINFO_' . $bucketNum . '_FMF(PENDING|REPORT|DENY|ACCEPT)(ID|NAME)(\d+)$/', $index, $matches ) ) {
				$found_any = true;
				if ( $matches[3] > $max_filter_num[ $matches[1] ] ) {
					$max_filter_num[ $matches[1] ] = $matches[3];
				}
			}
		}

		// If we didn't find anything in the initial scan, bail out now.
		if ( ! $found_any ) {
			return false;
		}

		$this->filters = array();
		foreach ( $max_filter_num as $index => $value ) {
			for ( $i = 0; $i <= $value; $i++ ) {
				$prefix = 'L_PAYMENTINFO_' . $bucketNum . '_FMF' . $index;
				if ( array_key_exists( $prefix . 'NAME' . $i, $doECResponse ) && array_key_exists( $prefix . 'ID' . $i, $doECResponse ) ) {
					$filters[] = new PayPal_Payment_Fraud_Management_Filter( $doECResponse[ $prefix . 'NAME' . $i ], $doECResponse[ $prefix . 'ID' . $i ], $index );
				}
			}
		}

		return true;
	}
}

class PayPal_Payment_Fraud_Management_Filter {
	public $name;
	public $id;
	public $status;

	const FraudManagementFilterPending = 'PENDING';
	const FraudManagementFilterReport  = 'REPORT';
	const FraudManagementFilterDeny    = 'DENY';
	const FraudManagementFilterAccept  = 'ACCEPT';

	public function __construct( $name, $id, $status ) {
		$this->name   = $name;
		$this->id     = $id;
		$this->status = $status;
	}
}

class PayPal_Payment_Shipping_Option_Details {
	public $calculation_mode           = false;
	public $insurance_option_selected  = false;
	public $shipping_option_is_default = false;
	public $shipping_option_amount     = false;
	public $shipping_option_name       = false;

	public function loadFromDoECResponse( $doECResponse ) {
		$map = array(
			'SHIPPINGCALCULATIONMODE' => 'calculation_mode',
			'INSURANCEOPTIONSELECTED' => 'insurance_option_selected',
			'SHIPPINGOPTIONISDEFAULT' => 'shipping_option_is_default',
			'SHIPPINGOPTIONAMOUNT'    => 'shipping_option_amount',
			'SHIPPINGOPTIONNAME'      => 'shipping_option_name',
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			if ( array_key_exists( $index, $doECResponse ) ) {
				$this->$value = $doECResponse[ $index ];
				$found_any    = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Payment_Payment_Details {
	public $transaction_id                   = false;
	public $ebay_item_auction_transaction_id = false;
	public $parent_transaction_id            = false;
	public $receipt_id                       = false;
	public $transaction_type                 = false;

	const TransactionTypeCart            = 'cart';
	const TransactionTypeExpressCheckout = 'express-checkout';

	public $payment_type = false;

	const PaymentTypeNone    = 'none';
	const PaymentTypeEcheck  = 'echeck';
	const PaymentTypeInstant = 'instant';

	public $expected_echeck_clear_date = false;
	public $order_time                 = false;
	public $amount                     = false;
	public $currency_code              = false;
	public $fee_amount                 = false;
	public $settlement_amount          = false;
	public $tax_amount                 = false;
	public $exchange_rate              = false;
	public $payment_status             = false;

	const PaymentStatusNone               = 'None';
	const PaymentStatusCanceledReversal   = 'Canceled-Reversal';
	const PaymentStatusCompleted          = 'Completed';
	const PaymentStatusDenied             = 'Denied';
	const PaymentStatusExpired            = 'Expired';
	const PaymentStatusFailed             = 'Failed';
	const PaymentStatusInProgress         = 'In-Progress';
	const PaymentStatusPartiallyRefunded  = 'Partially-Refunded';
	const PaymentStatusPending            = 'Pending';
	const PaymentStatusRefunded           = 'Refunded';
	const PaymentStatusReversed           = 'Reversed';
	const PaymentStatusProcessed          = 'Processed';
	const PaymentStatusVoided             = 'Voided';
	const PaymentStatusCompletedFundsHeld = 'Completed-Funds-Held';

	public $pending_reason = false;

	const PendingReasonNone             = 'none';
	const PendingReasonAddress          = 'address';
	const PendingReasonAuthorization    = 'authorization';
	const PendingReasonEcheck           = 'echeck';
	const PendingReasonInternational    = 'intl';
	const PendingReasonMultiCurrency    = 'multi-currency';
	const PendingReasonOrder            = 'order';
	const PendingReasonPaymentReview    = 'payment-review';
	const PendingReasonRegulatoryReview = 'regulatory-review';
	const PendingReasonUnilateral       = 'unilateral';
	const PendingReasonVerify           = 'verify';
	const PendingReasonOther            = 'other';

	public $reason_code = false;

	const ReasonCodeNone           = 'none';
	const ReasonCodeChargeback     = 'chargeback';
	const ReasonCodeGuarantee      = 'guarantee';
	const ReasonCodeBuyerComplaint = 'buyer-complaint';
	const ReasonCodeRefund         = 'refund';
	const ReasonCodeOther          = 'other';

	public $hold_decision = false;

	const HoldDecisionNewSellerPaymentHold = 'newsellerpaymenthold';
	const HoldDecisionPaymentHold          = 'paymenthold';

	public $shipping_method = false;

	public $protection_eligibility_details = false;
	public $receipt_reference_number       = false;
	public $shipping_amount                = false;

	public $handling_amount = false;

	public $payment_request_id = false;
	public $instrument_details = false;

	public $offer_details  = false;
	public $error_details  = false;
	public $seller_details = false;
	public $fmf_details    = false;

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map = array(
			'TRANSACTIONID'           => 'transaction_id',
			'EBAYITEMAUCTIONTXNID'    => 'ebay_item_auction_transaction_id',
			'PARENTTRANSACTIONID'     => 'parent_transaction_id',
			'RECEIPTID'               => 'receipt_id',
			'TRANSACTIONTYPE'         => 'transaction_type',
			'PAYMENTTYPE'             => 'payment_type',
			'EXPECTEDECHECKCLEARDATE' => 'expected_echeck_clear_date',
			'ORDERTIME'               => 'order_time',
			'AMT'                     => 'amount',
			'CURRENCYCODE'            => 'currency_code',
			'FEEAMT'                  => 'fee_amount',
			'SETTLEAMT'               => 'settlement_amount',
			'TAXAMT'                  => 'tax_amount',
			'EXCHANGERATE'            => 'exchange_rate',
			'PAYMENTSTATUS'           => 'payment_status',
			'PENDINGREASON'           => 'pending_reason',
			'REASONCODE'              => 'reason_code',
			'HOLDDECISION'            => 'hold_decision',
			'SHIPPINGMETHOD'          => 'shipping_method',
			'RECEIPTREFERENCENUMBER'  => 'receipt_reference_number',
			'SHIPPINGAMT'             => 'shipping_amount',
			'HANDLINGAMT'             => 'handling_amount',
			'PAYMENTREQUESTID'        => 'payment_request_id',
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTINFO_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $doECResponse ) ) {
				$this->$value = $doECResponse[ $var_name ];
				$found_any    = true;
			}
		}

		$this->protection_eligibility_details = new PayPal_Payment_Payment_Protection_Eligibility_Details();
		if ( ! $this->protection_eligibility_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->protection_eligibility_details = false;
		}

		$this->instrument_details = new PayPal_Payment_Payment_Instrument_Details();
		if ( ! $this->instrument_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->instrument_details = false;
		}

		$this->offer_details = new PayPal_Payment_Payment_Offer_Details();
		if ( ! $this->offer_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->offer_details = false;
		}

		$this->error_details = new PayPal_Payment_Payment_Error_Details();
		if ( ! $this->error_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->error_details = false;
		}

		$this->seller_details = new PayPal_Payment_Payment_Seller_Details();
		if ( ! $this->seller_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->seller_details = false;
		}

		$this->fmf_details = new PayPal_Payment_Payment_FMF_Details();
		if ( ! $this->fmf_details->loadFromDoECResponse( $doECResponse, $bucketNum ) ) {
			$this->fmf_details = false;
		}
	}

}

class PayPal_Payment_Payment_Protection_Eligibility_Details {
	public $protection_eligibility = false;

	const ProtectionEligibilityEligible          = 'Eligible';
	const ProtectionEligibilityPartiallyEligible = 'PartiallyEligible';
	const ProtectionEligibilityIneligible        = 'Ineligible';

	public $protection_eligibility_type = false;

	const ProtectionEligibilityTypeItemNotReceivedEligible     = 'ItemNotReceivedEligible';
	const ProtectionEligibilityTypeUnauthorizedPaymentEligible = 'UnauthorizedPaymentEligible';
	const ProtectionEligibilityTypeIneligible                  = 'Ineligible';

	public function isItemNotReceivedEligible() {
		$types = explode( ',', $this->protection_eligibility_type );
		foreach ( $types as $value ) {
			if ( self::ProtectionEligibilityTypeItemNotReceivedEligible == $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				return true;
			}
		}
		return false;
	}

	public function isUnauthorizedPaymentEligible() {
		$types = explode( ',', $this->protection_eligibility_type );
		foreach ( $types as $value ) {
			if ( self::ProtectionEligibilityTypeUnauthorizedPaymentEligible == $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				return true;
			}
		}
		return false;
	}

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map = array(
			'PROTECTIONELIGIBILITY'     => 'protection_eligibility',
			'PROTECTIONELIGIBILITYTYPE' => 'protection_eligibility_type',
		);

		$found_any = false;

		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTINFO_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $doECResponse ) ) {
				$this->$value = $doECResponse[ $var_name ];
				$found_any    = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Payment_Payment_Instrument_Details {
	public $instrument_category = false;

	const InstrumentCategoryPayPalCredit = '1';
	const InstrumentCategoryPrivateCard  = '2';

	public $instrument_id = false;

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map       = array(
			'INSTRUMENTCATEGORY' => 'instrument_category',
			'INSTRUMENTID'       => 'instrument_id',
		);
		$found_any = false;

		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTINFO_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $doECResponse ) ) {
				$this->$value = $doECResponse[ $var_name ];
				$found_any    = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Payment_Payment_Offer_Details {
	public $offer_code        = false;
	public $offer_tracking_id = false;

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map = array(
			'OFFERCODE'       => 'offer_code',
			'OFFERTRACKINGID' => 'offer_tracking_id',
		);
	}
}

class PayPal_Payment_Payment_Error_Details {
	public $short_message = false;
	public $long_message  = false;
	public $error_code    = false;
	public $severity_code = false;
	public $ack           = false;

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map = array(
			'SHORTMESSAGE' => 'short_message',
			'LONGMESSAGE'  => 'long_message',
			'ERRORCODE'    => 'error_code',
			'SEVERITYCODE' => 'severity_code',
			'ACK'          => 'ack',
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTINFO_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $doECResponse ) ) {
				$this->$value = $doECResponse[ $var_name ];
				$found_any    = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Payment_Payment_Seller_Details {
	public $paypal_account_id          = false;
	public $secure_merchant_account_id = false;
	public $seller_id                  = false;
	public $user_name                  = false;
	public $registration_date          = false;

	public function loadFromDoECResponse( $doECResponse, $bucketNum ) {
		$map = array(
			'SELLERPAYPALACCOUNTID'   => 'paypal_account_id',
			'SECUREMERCHANTACCOUNTID' => 'secure_merchant_account_id',
			'SELLERID'                => 'seller_id',
			'SELLERUSERNAME'          => 'user_name',
			'SELLERREGISTRATIONDATE'  => 'registration_date',
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTINFO_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $doECResponse ) ) {
				$this->$value = $doECResponse[ $var_name ];
				$found_any    = true;
			}
		}

		return $found_any;
	}
}
