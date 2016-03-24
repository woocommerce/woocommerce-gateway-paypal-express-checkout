<?php

/**
 * TODO: Move each class into its own file and group them under one dir, checkout-details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$includes_path = wc_gateway_ppec()->includes_path;

require_once( $includes_path . 'class-wc-gateway-ppec-address.php' );

class PayPal_Checkout_Details {
	public $token                           = false;
	public $custom                          = false;
	public $invnum                          = false;
	public $phone_number                    = false;
	public $billing_agreement_accepted      = false;

	const BillingAgreementNotAccepted       = '0';
	const BillingAgreementAccepted          = '1';

	public $paypal_adjustment               = false;
	public $redirect_required_after_payment = false;
	public $checkout_status                 = false;

	const PaymentNotAttempted               = 'PaymentActionNotInitiated';
	const PaymentFailed                     = 'PaymentActionFailed';
	const PaymentInProgress                 = 'PaymentActionInProgress';
	const PaymentCompleted                  = 'PaymentActionCompleted';

	public $gift_details                    = false;

	public $buyer_marketing_email           = false;
	public $survey_question                 = false;
	public $survey_choice_selected          = false;

	public $payer_details                   = false;
	public $wallets                         = false;

	public $instrument_details              = false;

	public $shipping_option_details         = false;

	public $payments                        = false;

	public function loadFromGetECResponse( $getECResponse ) {
		$map = array(
			'TOKEN'                          => 'token',
			'CUSTOM'                         => 'custom',
			'INVNUM'                         => 'invnum',
			'PHONENUM'                       => 'phone_number',
			'BILLINGAGREEMENTACCEPTEDSTATUS' => 'billing_agreement_accepted',
			'PAYPALADJUSTMENT'               => 'paypal_adjustment',
			'REDIRECTREQUIRED'               => 'redirect_required_after_payment',
			'CHECKOUTSTATUS'                 => 'checkout_status',
			'BUYERMARKETINGEMAIL'            => 'buyer_marketing_email',
			'SURVEYQUESTION'                 => 'survey_question',
			'SURVEYCHOICESELECTED'           => 'survey_choice_selected'
		);

		foreach ( $getECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$this->$map[ $index ] = $value;
			}
		}

		$this->gift_details = new PayPal_Checkout_Gift_Details();
		if ( ! $this->gift_details->loadFromGetECResponse( $getECResponse ) ) {
			$this->gift_details = false;
		}

		$this->payer_details = new PayPal_Checkout_Payer_Details();
		if ( ! $this->payer_details->loadFromGetECResponse( $getECResponse ) ) {
			$this->payer_details = false;
		}

		$this->instrument_details = new PayPal_Checkout_Instrument_Details();
		if ( ! $this->instrument_details->loadFromGetECResponse( $getECResponse ) ) {
			$this->instrument_details = false;
		}

		$this->shipping_option_details = new PayPal_Checkout_Shipping_Option_Details();
		if ( ! $this->shipping_option_details->loadFromGetECResponse( $getECResponse ) ) {
			$this->shipping_option_details = false;
		}

		$max_wallet_num = -1;
		$max_payment_num = -1;
		foreach ( $getECResponse as $index => $value ) {
			if ( preg_match( '/^(WALLETTYPE|WALLETID|WALLETDESCRIPTION)(\d+)$/', $index, $matches ) ) {
				if ( $matches[2] > $max_wallet_num ) {
					$max_wallet_num = $matches[2];
				}
			} elseif ( preg_match( '/^PAYMENTREQUEST_(\d)_(AMT|CURRENCYCODE|ITEMAMT|SHIPPINGAMT|INSURANCEAMT|SHIPDISCAMT|INSURANCEOPTIONOFFERED|HANDLINGAMT|TAXAMT|DESC|CUSTOM|INVNUM|NOTIFYURL|NOTETEXT|TRANSACTIONID|ALLOWEDPAYMENTMETHOD|PAYMENTREQUESTID|BUCKETCATEGORYTYPE)$/', $index, $matches )
			|| preg_match( '/^L_PAYMENTREQUEST_(\d)_(NAME|DESC|AMT|NUMBER|QTY|TAXAMT|ITEMWEIGHTVALUE|ITEMWEIGHTUNIT|ITEMLENGTHVALUE|ITEMLENGTHUNIT|ITEMWIDTHVALUE|ITEMWIDTHUNIT|ITEMHEIGHTVALUE|ITEMHEIGHTUNIT)\d+$/', $index, $matches ) ) {
				if ( $matches[1] > $max_payment_num ) {
					$max_payment_num = $matches[1];
				}
			}
		}

		if ( $max_wallet_num > -1 ) {
			$this->wallets = array();
			for ( $i = 0; $i <= $max_wallet_num; $i++ ) {
				$this->wallets[ $i ] = new PayPal_Checkout_Wallet_Details();
				$this->wallets[ $i ]->loadFromGetECResponse( $getECResponse, $i );
			}
		}

		if ( $max_payment_num > -1 ) {
			$this->payments = array();
			for ( $i = 0; $i <= $max_payment_num; $i++ ) {
				$this->payments[ $i ] = new PayPal_Checkout_Payment_Details();
				$this->payments[ $i ]->loadFromGetECResponse( $getECResponse, $i );
			}
		}

	}

}

class PayPal_Checkout_Payment_Details {
	public $shipping_address                      = false;
	public $shipping_address_confirmed            = false;

	public $shipping_address_normalization_status = false;

	const AddressNormalizationNone                = 'None';
	const AddressNormalizationNormalized          = 'Normalized';
	const AddressNormalizationUnnormalized        = 'Unnormalized';
	const AddressNormalizationUserPreferred       = 'UserPreferred';

	public $amount                                = false;
	public $currency_code                         = false;

	public $item_amount                           = false;
	public $shipping_amount                       = false;
	public $insurance_amount                      = false;
	public $shipping_discount_amount              = false;
	public $insurance_option_offered              = false;
	public $handling_amount                       = false;
	public $tax_amount                            = false;
	public $description                           = false;
	public $custom                                = false;
	public $invoice_number                        = false;
	public $notify_url                            = false;
	public $note_text                             = false;
	public $transaction_id                        = false;
	public $allowed_payment_method                = false;

	const AllowedPaymentMethodInstantPaymentOnly  = 'InstantPaymentOnly';

	public $payment_request_id                    = false;
	public $bucket_category_type                  = false;

	const BucketCategoryInternationalShipping     = '1';
	const BucketCategoryLocalDelivery             = '2';

	public $items                                 = false;

	public function loadFromGetECResponse( $getECResponse, $bucketNum ) {
		$map = array(
			'AMT'                        => 'amount',
			'CURRENCYCODE'               => 'currency_code',
			'ITEMAMT'                    => 'item_subtotal',
			'SHIPPINGAMT'                => 'shipping_amount',
			'INSURANCEAMT'               => 'insurance_amount',
			'SHIPDISCAMT'                => 'shipping_discount_amount',
			'INSURANCEOPTIONOFFERED'     => 'insurance_option_offered',
			'HANDLINGAMT'                => 'handling_amount',
			'TAXAMT'                     => 'tax_amount',
			'DESC'                       => 'description',
			'CUSTOM'                     => 'custom',
			'INVNUM'                     => 'invoice_number',
			'NOTIFYURL'                  => 'notify_url',
			'NOTETEXT'                   => 'note_text',
			'TRANSACTIONID'              => 'transaction_id',
			'ALLOWEDPAYMENTMETHOD'       => 'allowed_payment_method',
			'PAYMENTREQUESTID'           => 'payment_request_id',
			'BUCKETCATEGORYTYPE'         => 'bucket_category_type',
			'ADDRESSNORMALIZATIONSTATUS' => 'shipping_address_normalization_status'
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			$var_name = 'PAYMENTREQUEST_' . $bucketNum . '_' . $index;
			if ( array_key_exists( $var_name, $getECResponse ) ) {
				$this->$value = $getECResponse[ $var_name ];
				$found_any = true;
			}
		}

		// See if we have any line items that need to be parsed
		$max_line_item_num = -1;
		foreach ( $getECResponse as $index => $value ) {
			if ( preg_match( '/^L_PAYMENTREQUEST_' . $bucketNum . '_(NAME|DESC|AMT|NUMBER|QTY|TAXAMT|ITEMWEIGHTVALUE|ITEMWEIGHTUNIT|ITEMLENGTHVALUE|ITEMLENGTHUNIT|ITEMWIDTHVALUE|ITEMWIDTHUNIT|ITEMHEIGHTVALUE|ITEMHEIGHTUNIT|ITEMCATEGORY|EBAYITEMNUMBER|EBAYITEMAUCTIONTXNID|EBAYITEMORDERID|EBAYITEMCARTID)(\d+)$/', $index, $matches ) ) {
				if ( isset( $matches[2] ) && $matches[2] > $max_line_item_num ) {
					$max_line_item_num = $matches[2];
				}
			}
		}

		if ( $max_line_item_num > -1 ) {
			$found_any = true;
			$this->items = array();
			for ( $i = 0; $i <= $max_line_item_num; $i++ ) {
				$items[ $i ] = new PayPal_Checkout_Payment_Item_Details();
				$items[ $i ]->loadFromGetECResponse( $getECResponse, $bucketNum, $i );
			}
		}

		$this->shipping_address = new PayPal_Address();
		if ( ! $this->shipping_address->loadFromGetECResponse( $getECResponse, 'PAYMENTREQUEST_' . $bucketNum . '_SHIPTO' ) ) {
			$this->shipping_address = false;
		} else {
			$found_any = true;
		}

		return $found_any;
	}
}

class PayPal_Checkout_Payment_Item_Details {
	public $name = false;
	public $description = false;
	public $amount = false;
	public $item_number = false;
	public $quantity = false;
	public $tax_amount = false;

	public $physical_details = false;
	public $ebay_item_details = false;

	public function loadFromGetECResponse( $getECResponse, $bucketNum, $itemNum ) {
		$map = array(
			'NAME' => 'name',
			'DESC' => 'description',
			'AMT' => 'amount',
			'NUMBER' => 'item_number',
			'QTY' => 'quantity',
			'TAXAMT' => 'tax_amount'
		);

		foreach ( $map as $index => $value ) {
			$var_name = 'L_PAYMENTREQUEST_' . $bucketNum . '_' . $index . $itemNum;
			if ( array_key_exists( $var_name, $getECResponse ) ) {
				$this->$value = $getECResponse[ $var_name ];
			}
		}

		$this->physical_details = new PayPal_Checkout_Payment_Item_Physical_Details();
		if ( ! $this->physical_details->loadFromGetECResponse( $getECResponse, $bucketNum, $itemNum ) ) {
			$this->physical_details = false;
		}

		$this->ebay_item_details = new PayPal_Checkout_Payment_Item_Ebay_Item_Details();
		if ( ! $this->ebay_item_details->loadFromGetECResponse( $getECResponse, $bucketNum, $itemNum ) ) {
			$this->ebay_item_details = false;
		}
	}
}

class PayPal_Checkout_Payment_Item_Physical_Details {
	public $weight;
	public $weight_units;

	public $length;
	public $length_units;

	public $width;
	public $width_units;

	public $height;
	public $height_units;

	public function loadFromGetECResponse( $getECResponse, $bucketNum, $itemNum ) {
		$map = array(
			'WEIGHTVALUE' => 'weight',
			'WEIGHTUNIT'  => 'weight_units',
			'LENGTHVALUE' => 'length',
			'LENGTHUNIT'  => 'length_units',
			'WIDTHVALUE'  => 'width',
			'WIDTHUNIT'   => 'width_units',
			'HEIGHTVALUE' => 'height',
			'HEIGHTUNIT'  => 'height_units'
		);
		$found_any = false;

		foreach ( $map as $index => $value ) {
			$var_name = 'L_PAYMENTREQUEST_' . $bucketNum . '_ITEM' . $index . $itemNum;
			if ( array_key_exists( $var_name, $getECResponse ) ) {
				$this->$value = $getECResponse[ $var_name ];
				$found_any = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Checkout_Payment_Item_Ebay_Item_Details {
	public $item_number            = false;
	public $auction_transaction_id = false;
	public $order_id               = false;
	public $cart_id                = false;

	public function loadFromGetECResponse( $getECResponse, $bucketNum, $itemNum ) {
		$map = array(
			'ITEMNUMBER'   => 'item_number',
			'AUCTIONTXNID' => 'auction_transaction_id',
			'ORDERID'      => 'order_id',
			'CARTID'       => 'cart_id'
		);

		$found_any = false;
		foreach ( $map as $index => $value ) {
			$var_name = 'L_PAYMENTREQUEST_' . $bucketNum . '_' . $index . $itemNum;
			if ( array_key_exists( $var_name, $getECResponse ) ) {
				$this->$value = $getECResponse[ $var_name ];
				$found_any = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Checkout_Shipping_Option_Details {
	public $calculation_mode  = false;

	const CalculationModeCallback = 'Callback';
	const CalculationModeFlatrate = 'FlatRate';

	public $insurance_option_selected  = false;
	public $shipping_option_is_default = false;
	public $shipping_option_amount     = false;
	public $shipping_option_name       = false;

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromGetECResponse( $getECResponse ) {
		$map = array(
			'SHIPPINGCALCULATIONMODE' => 'calculation_mode',
			'INSURANCEOPTIONSELECTED' => 'insurance_option_selected',
			'SHIPPINGOPTIONISDEFAULT' => 'shipping_option_is_default',
			'SHIPPINGOPTIONAMOUNT'    => 'shipping_option_amount',
			'SHIPPINGOPTIONNAME'      => 'shipping_option_name'
		);
		$found_any = false;
		foreach ( $getECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$this->$map[ $index ] = $value;
				$found_any = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Checkout_Instrument_Details {
	public $instrument_category          = false;

	const InstrumentCategoryPayPalCredit = '1';
	const InstrumentCategoryPrivateCard  = '2';

	public $instrument_id                = false;

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromGetECResponse( $getECResponse ) {
		$map = array(
			'INSTRUMENTCATEGORY' => 'instrument_category',
			'INSTRUMENTID'       => 'instrument_id'
		);
		$found_any = false;

		foreach ( $getECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$this->$map[ $index ] = $value;
				$found_any = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Checkout_Wallet_Details {
	public $wallet_type                     = false;

	const WalletTypeLoyaltyCard             = 'LOYALTY_CARD';
	const WalletTypeMerchantCoupon          = 'MERCHANT_COUPON';
	const WalletTypeMerchantClosedLoopOffer = 'MERCHANT_CLOSED_LOOP_OFFER';

	public $wallet_id                       = false;
	public $wallet_description              = false;

	public function __construct( $wallet_type = false, $wallet_id = false, $wallet_description = false ) {
		$this->wallet_type        = $wallet_type;
		$this->wallet_id          = $wallet_id;
		$this->wallet_description = $wallet_description;
	}

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromGetECResponse( $getECResponse, $wallet_num ) {
		$found_any = false;
		foreach ( $getECResponse as $index => $value ) {
			if ( ( 'WALLETTYPE' . $wallet_num ) == $index ) {
				$this->wallet_type = $value;
				$found_any = true;
			} elseif ( ( 'WALLETID' . $wallet_num ) == $index ) {
				$this->wallet_id = $value;
				$found_any = true;
			} elseif ( ( 'WALLETDESCRIPTION' . $wallet_num ) == $index ) {
				$this->wallet_description = $value;
				$found_any = true;
			}
		}

		return $found_any;
	}
}

class PayPal_Checkout_Payer_Details {
	public $phone_number        = false;
	public $email               = false;
	public $payer_id            = false;
	public $payer_status        = false;

	const PayerStatusVerified   = 'verified';
	const PayerStatusUnverified = 'unverified';

	public $country             = false;
	public $business_name       = false;
	public $first_name          = false;
	public $last_name           = false;
	public $middle_name         = false;
	public $suffix              = false;

	public $billing_address     = false;

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromGetECResponse( $getECResponse ) {
		$map = array(
			'PHONENUM'    => 'phone_number',
			'EMAIL'       => 'email',
			'PAYERID'     => 'payer_id',
			'PAYERSTATUS' => 'payer_status',
			'COUNTRYCODE' => 'country',
			'BUSINESS'    => 'business_name',
			'FIRSTNAME'   => 'first_name',
			'MIDDLENAME'  => 'middle_name',
			'LASTNAME'    => 'last_name',
			'SUFFIX'      => 'suffix'
		);
		$found_any = false;

		// At the same time, see if we have a billing address that needs to be parsed out.
		$billing_address_present = false;

		foreach ( $getECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$this->$map[ $index ] = $value;
				$found_any = true;
			}
			if ( preg_match( '/^BILLTONAME|STREET|STREET2|CITY|STATE|ZIP|COUNTRY|COUNTRYNAME|ADDRESSOWNER|ADDRESSSTATUS$/', $index ) ) {
				$billing_address_present = true;
			}
		}

		if ( $billing_address_present ) {
			$this->billing_address = new PayPal_Address();
			if ( $this->billing_address->loadFromGetECResponse( $getECResponse, '', true ) ) {
				$found_any = true;
			} else {
				$this->billing_address = false;
			}
		}

		return $found_any;
	}

}

class PayPal_Checkout_Gift_Details {
	public $gift_message         = false;
	public $gift_receipt_enabled = false;
	public $gift_wrap_name       = false;
	public $gift_wrap_amount     = false;

	// Returns true to indicate that the getECResponse array contained variables that were pertinent to this object.
	// If not, it returns false to indicate that the caller can destroy this object.
	public function loadFromGetECResponse( $getECResponse ) {
		$map = array(
			'GIFTMESSAGE'       => 'gift_message',
			'GIFTWRAPNAME'      => 'gift_wrap_name',
			'GIFTRECEIPTENABLE' => 'gift_receipt_enabled',
			'GIFTWRAPAMOUNT'    => 'gift_wrap_amount'
		);
		$found_any = false;

		foreach ( $getECResponse as $index => $value ) {
			if ( array_key_exists( $index, $map ) ) {
				$this->$map[ $index ] = $value;
				$found_any = true;
			}
		}

		return $found_any;
	}
}
