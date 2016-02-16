<?php

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

require_once( 'class-signature.php' );
require_once( 'class-certificate.php' );

abstract class PayPal_Settings {
	
	protected $params;
	protected $validParams = array(
		'liveApiCredentials',
		'sandboxApiCredentials',
		'environment',
		'logoImageUrl',
		'ipnUrl',
		'paymentAction',
		'allowGuestCheckout',
		'blockEChecks',
		'requireBillingAddress',
		'enableInContextCheckout'
	);
	
	const PaymentActionSale          = 'Sale';
	const PaymentActionAuthorization = 'Authorization';
	const PaymentActionOrder         = 'Order';
	
	// START: Compatibility with previous versions
	public function __call( $name, $arguments ) {
		if ( 'get' == substr( $name, 0, 3 ) ) {
			$varname = lcfirst( substr( $name, 3 ) );
			if ( 'activeEnvironment' == $varname ) {
				return $this->__get( 'environment' );
			} else {
				return $this->__get( $varname );
			}
		}
		if ( 'set' == substr( $name, 0, 3 ) ) {
			$varname = lcfirst( substr( $name, 3 ) );
			$this->__set( $varname, $arguments[0] );
		}
	}
	// END: Compatibility with previous versions
	
	public function __get( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			// Run the value through sanitization functions, if they exist
			$func_name = '_sanitize_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				return $this->$func_name( $this->params[ $name ] );
			} else if ( array_key_exists( $name, $this->params ) ) {
				return $this->params[ $name ];
			} else {
				return null;
			}
		} else {
			// START: Compatibility with previous versions
			if ( '_' == substr( $name, 0, 1 ) ) {
				$actual_name = substr( $name, 1 );
				return $this->__get( $actual_name );
			}
			// END: Compatibility with previous versions
			return null;
		}
	}
	
	public function __set( $name, $value ) {
		if ( in_array( $name, $this->validParams ) ) {
			// Run the value through sanitization and validation functions, if they exist
			$func_name = '_sanitize_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				$value = $this->$func_name( $value );
			}
			
			$func_name = '_validate_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				if ( $this->$func_name( $value ) ) {
					$this->params[ $name ] = $value;
				}
			} else {
				$this->params[ $name ] = $value;
			}
		}
	}
	
	public function __isset( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 *
	 * $_liveApiCredentials and $_sandboxApiCredentials accessors
	 *
	 **/
	
	public function setApiSignatureCredentials( $username, $password, $signature, $subject = false, $environment = 'sandbox' ) {
		if ( 'live' == $environment ) {
			$this->liveApiCredentials = new PayPal_Signature_Credentials( $username, $password, $signature, $subject );
		} else {
			$this->sandboxApiCredentials = new PayPal_Signature_Credentials( $username, $password, $signature, $subject );
		}
	}
	
	public function setApiCertificateCredentialsFromFile( $username, $password, $certFile, $subject = false, $environment = 'sandbox' ) {
		$certString = file_get_contents( $certFile );
		if ( FALSE === $cert ) {
			// Failed to load the certificate
			// TODO: Add some logging
			return false;
		}
		
		$this->setApiCertificateCredentialsFromString( $username, $password, $certString, $subject, $environment );
		
		return true;
		
	}
	
	public function setApiCertificateCredentialsFromString( $username, $password, $certString, $subject = false, $environment = 'sandbox' ) {
		if ( 'live' == $environment ) {
			$this->liveApiCredentials = new PayPal_Certificate_Credentials( $username, $password, $certString, $subject );
		} else {
			$this->sandboxApiCredentials = new PayPal_Certificate_Credentials( $username, $password, $certString, $subject );
		}
	}
		
	public function getActiveApiCredentials() {
		if ( $this->environment == 'live' ) {
			return $this->liveApiCredentials;
		} else {
			return $this->sandboxApiCredentials;
		}
	}
	
	protected function _validate_paymentAction( $value ) {
		if ( self::PaymentActionSale != $value && self::PaymentActionAuthorization != $value && self::PaymentActionOrder != $value ) {
			return false;
		} else {
			return true;
		}
	}
	
	public function getPayPalRedirectUrl( $token, $commit = false ) {
		$url = 'https://www.';
		
		if ( $this->environment != 'live' ) {
			$url .= 'sandbox.';
		}
		
		$url .= 'paypal.com/';
		
		if ( $this->enableInContextCheckout ) {
			$url .= 'checkoutnow?';
		} else {
			$url .= 'cgi-bin/webscr?cmd=_express-checkout&';
		}
		
		$url .= 'token=' . urlencode( $token );
		
		if ( $commit ) {
			$url .= '&useraction=commit';
		}
		
		return $url;
	}
	
	abstract public function getSetECShortcutParameters();
	abstract public function getSetECMarkParameters();
	abstract public function getDoECParameters();
	
	protected function getBaseSetECShortcutParameters( $buckets = 1 ) {
		$params = array();
		
		if ( $this->logoImageUrl ) {
			$params['LOGOIMG'] = $this->logoImageUrl;
		}
		
		if ( $this->allowGuestCheckout ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}
		
		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}
		
		if ( $this->requireBillingAddress ) {
			$params['REQBILLINGADDRESS'] = '1';
		}
		
		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
			if ( $this->blockEChecks ) $params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
		}
		
		return $params;
	}
	
	protected function getBaseSetECMarkParameters( $buckets = 1 ) {
		$params = array();
		
		if ( $this->logoImageUrl ) {
			$params['LOGOIMG'] = $this->logoImageUrl;
		}
		
		if ( $this->allowGuestCheckout ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}
		
		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}
		
		if ( $this->requireBillingAddress ) {
			$params['REQBILLINGADDRESS'] = '1';
		}
		
		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
			if ( $this->blockEChecks ) {
				$params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
			}
		}
		
		return $params;
	}
	
	protected function getBaseDoECParameters( $buckets = 1 ) {
		$params = array();
		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}
		
		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_NOTIFYURL' ] = $this->ipnUrl;
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
		}

		return $params;
	}
	
	abstract public function getECTokenSessionLength();

}
