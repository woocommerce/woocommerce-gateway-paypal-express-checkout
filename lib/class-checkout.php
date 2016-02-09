<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

abstract class PayPal_Checkout {
	protected $_cart;
	protected $_suppressShippingAddress;
	
	// $_shippingAddress can be a single PayPal_Address object, or an array of PayPal_Address objects
	// (for the purposes of doing parallel payments).
	protected $_shippingAddress;
	protected $_requestBillingAgreement;
	protected $_enablePayPalCredit;
	
	public function enablePayPalCredit( $enable = true ) {
		$this->_enablePayPalCredit = $enable;
	}

	public function suppressShippingAddress( $suppress = true ) {
		if ( $suppress ) {
			$this->_suppressShippingAddress = true;
		} else {
			$this->_suppressShippingAddress = false;
		}
	}
	
	public function setShippingAddress( $address ) {
		if ( is_a( $address, 'PayPal_Address' ) ) {
			$this->_shippingAddress = $address;
		}
		if ( is_array( $address ) ) {
			// Check each of the elements to make sure they're all PayPal_Address objects as well
			foreach ( $address as $index => $value ) {
				if ( ! is_a( $value, 'PayPal_Address' ) ) {
					return;
				}
				// And also check to make sure we're not exceeding the maximum number of parallel
				// payments PayPal will allow
				if ( ! is_int( $index ) || $value > 9 ) {
					return;
				}
			}
			
			$this->_shippingAddress = $address;
		}
	}
	
	public function requestBillingAgreement( $request = true ) {
		if ( $request ) {
			$this->_requestBillingAgreement = true;
		} else {
			$this->_requestBillingAgreement = false;
		}
	}
	
	public function __construct() {
		$this->_cart = false;
		$this->_suppressShippingAddress = false;
		$this->_shippingAddress = false;
		$this->_requestBillingAgreement = false;
	}
	
	public function getSetExpressCheckoutParameters() {
		// First off, get the cart parameters
		$params = $this->_cart->setECParams();
		
		// Now work through the checkout-level variables.
		if ( $this->_suppressShippingAddress ) {
			$params['NOSHIPPING'] = 1;
		}
		
		if ( $this->_requestBillingAgreement ) {
			$params['BILLINGTYPE'] = 'MerchantInitiatedBilling';
		}
		
		if ( $this->_enablePayPalCredit ) {
			$params['USERSELECTEDFUNDINGSOURCE'] = 'Finance';
		}
		
		if ( false !== $this->_shippingAddress ) {
			if ( is_array( $this->_shippingAddress ) ) {
				foreach ( $this->_shippingAddress as $index => $value ) {
					$params = array_merge( $params, $value->getAddressParams( 'PAYMENTREQUEST_' . $index . '_SHIPTO' ) );
				}
			} else {
				$params = array_merge( $params, $this->_shippingAddress->getAddressParams( 'PAYMENTREQUEST_0_SHIPTO' ) );
			}
		}

		return $params;
	}
	
	public function getDoExpressCheckoutParameters( $token, $payer_id ) {
		$params = $this->_cart->setECParams();
		
		if ( false !== $this->_shippingAddress ) {
			if ( is_array( $this->_shippingAddress ) ) {
				foreach ( $this->_shippingAddress as $index => $value ) {
					$params = array_merge( $params, $value->getAddressParams( 'PAYMENTREQUEST_' . $index . '_SHIPTO' ) );
				}
			} else {
				$params = array_merge( $params, $this->_shippingAddress->getAddressParams( 'PAYMENTREQUEST_0_SHIPTO' ) );
			}
		}
		
		$params['TOKEN'] = $token;
		$params['PAYERID'] = $payer_id;
		
		return $params;
	}
	
	protected function isSuccess( $response ) {
		if ( 'Success' == $response['ACK'] || 'SuccessWithWarning' == $response['ACK'] ) {
			return true;
		} else {
			return false;
		}
	}
	
	abstract protected function GetReturnUrl();
	abstract protected function GetCancelUrl();

}