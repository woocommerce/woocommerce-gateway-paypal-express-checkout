<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$includes_path = wc_gateway_ppec()->includes_path;

require_once( $includes_path . 'class-cart.php'                );
require_once( $includes_path . 'lib/class-api.php'             );
require_once( $includes_path . 'class-wc-gateway-ppec-settings.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-session-data.php' );
require_once( $includes_path . 'lib/class-checkoutdetails.php' );
require_once( $includes_path . 'lib/class-exception.php'       );
require_once( $includes_path . 'lib/class-paymentdetails.php'  );
require_once( $includes_path . 'lib/class-address.php'         );

class WooCommerce_PayPal_Checkout {

	protected $_cart;
	protected $_suppressShippingAddress;

	// $_shippingAddress can be a single PayPal_Address object, or an array of PayPal_Address objects
	// (for the purposes of doing parallel payments).
	protected $_shippingAddress;
	protected $_requestBillingAgreement;
	protected $_enablePayPalCredit;

	public function __construct() {
		$this->_cart = false;
		$this->_suppressShippingAddress = false;
		$this->_shippingAddress = false;
		$this->_requestBillingAgreement = false;

		$this->_cart = new WooCommerce_PayPal_Cart();
	}

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

	protected function getReturnUrl() {

		$url = WC()->cart->get_checkout_url();
		if ( strpos( $url, '?' ) ) {
			$url .= '&';
		} else {
			$url .= '?';
		}

		$url .= 'woo-paypal-return=true';

		return $url;
	}

	protected function getCancelUrl() {

		$url = WC()->cart->get_cart_url();
		if ( strpos( $url, '?' ) ) {
			$url .= '&';
		} else {
			$url .= '?';
		}

		$url .= 'woo-paypal-cancel=true';

		return $url;
	}

	public function startCheckoutFromCart() {

		$this->_cart->loadCartDetails();

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$needs_shipping = WC()->cart->needs_shipping();
		$this->suppressShippingAddress( ! $needs_shipping );

		$using_ppc = false;

		if ( array_key_exists( 'use-ppc', $_GET ) && 'true' == $_GET['use-ppc'] ) {
			$this->enablePayPalCredit();
			$using_ppc = true;
		}

		$params = array_merge(
			$settings->getSetECShortcutParameters(),
			$this->getSetExpressCheckoutParameters()
		);

		$api = new PayPal_API( $settings->getActiveApiCredentials(), $settings->environment );

		$params['RETURNURL'] = $this->getReturnUrl();
		$params['CANCELURL'] = $this->getCancelUrl();

		if ( $this->_requestBillingAgreement ) {
			$params['BILLINGTYPE'] = 'MerchantInitiatedBilling';
		}

		$response = $api->SetExpressCheckout( $params );

		if ( $this->isSuccess( $response ) ) {
			// Save some data to the session.
			WC()->session->paypal = new WC_Gateway_PPEC_Session_Data(
				$response['TOKEN'],
				'cart',
				false,
				$needs_shipping,
				$this->_requestBillingAgreement,
				$settings->getECTokenSessionLength(),
				$using_ppc
			);

			return $settings->getPayPalRedirectUrl( $response['TOKEN'], false );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	public function startCheckoutFromCheckout( $order_id, $use_ppc = false ) {

		$this->_cart->loadOrderDetails( $order_id );

		$settings = wc_gateway_ppec()->settings->loadSettings();

		//new wc order > get address from that order > new pp address > assign address from order to new pp address > $this->setShippingAddress(pp address object)
		$getAddress = wc_get_order( $order_id );
		$shipAddressName = $getAddress->shipping_first_name . ' ' . $getAddress->shipping_last_name;

		$shipAddress = new PayPal_Address;
		$shipAddress->setName($shipAddressName);
		$shipAddress->setStreet1($getAddress->shipping_address_1);
		$shipAddress->setStreet2($getAddress->shipping_address_2);
		$shipAddress->setCity($getAddress->shipping_city);
		$shipAddress->setState($getAddress->shipping_state);
		$shipAddress->setZip($getAddress->shipping_postcode);
		$shipAddress->setCountry($getAddress->shipping_country);

		$this->setShippingAddress( $shipAddress );
		$this->enablePayPalCredit( $use_ppc );

		// Do we also need to grab the phone number and pass it through?

		$params = array_merge(
			$settings->getSetECMarkParameters(),
			$this->getSetExpressCheckoutParameters()
		);

		$api = new PayPal_API( $settings->getActiveApiCredentials(), $settings->environment );

		$params['RETURNURL'] = $this->getReturnUrl();
		$params['CANCELURL'] = $this->getCancelUrl();

		if ( $this->_requestBillingAgreement ) {
			$params['BILLINGTYPE'] = 'MerchantInitiatedBilling';
		}

		$needs_shipping = WC()->cart->needs_shipping();
		$this->suppressShippingAddress( $needs_shipping );

		$response = $api->SetExpressCheckout( $params );


		if ( $this->isSuccess( $response ) ) {
			// Save some data to the session.
			WC()->session->paypal = new WC_Gateway_PPEC_Session_Data(
				$response['TOKEN'],
				'order',
				$order_id,
				$needs_shipping,
				$this->_requestBillingAgreement,
				$settings->getECTokenSessionLength(),
				$use_ppc
			);

			return $settings->getPayPalRedirectUrl( $response['TOKEN'], true );
		} else {
			throw new PayPal_API_Exception( $response );
		}

	}

	public function getCheckoutDetails( $token = false ) {

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$api = new PayPal_API(
			$settings->getActiveApiCredentials(),
			$settings->environment
		);

		if ( false === $token ) {
			$token = $_GET['token'];
		}

		$response = $api->GetExpressCheckoutDetails( $token );

		if ( 'Success' == $response['ACK'] || 'SuccessWithWarning' == $response['ACK'] ) {
			$checkout_details = new PayPal_Checkout_Details();
			$checkout_details->loadFromGetECResponse( $response );

			$session_data = WC()->session->paypal;
			if ( null === $session_data ) {
				throw new PayPal_Missing_Session_Exception();
			}

			if ( is_a( $session_data, 'WC_Gateway_PPEC_Session_Data' ) && $token == $session_data->token ) {
				$session_data->checkout_details = $checkout_details;
				WC()->session->paypal = $session_data;
			} else {
				throw new PayPal_Missing_Session_Exception();
			}

			return $checkout_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	public function completePayment( $order_id, $token, $payerID ) {

		// Make sure our session data is there before we do something we might regret later
		$session_data = WC()->session->paypal;
		if ( null === $session_data ) {
			throw new PayPal_Missing_Session_Exception();
		}

		if ( is_a( $session_data, 'WC_Gateway_PPEC_Session_Data' ) && $token == $session_data->token ) {
			WC()->session->paypal = $session_data;
		} else {
			throw new PayPal_Missing_Session_Exception();
		}

		// Now make sure we have the GetEC data.  If not, well then we'll just fetch it now, pardner.
		if ( ! $session_data->checkout_details || ! is_a( $session_data->checkout_details, 'PayPal_Checkout_Details' ) ) {
			$this->getCheckoutDetails( $token );
		}

		$this->_cart->loadOrderDetails( $order_id );

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$order = wc_get_order( $order_id );
		$shipAddressName = $order->shipping_first_name . ' ' . $order->shipping_last_name;

		$shipAddress = new PayPal_Address;
		$shipAddress->setName($shipAddressName);
		$shipAddress->setStreet1($order->shipping_address_1);
		$shipAddress->setStreet2($order->shipping_address_2);
		$shipAddress->setCity($order->shipping_city);
		$shipAddress->setState($order->shipping_state);
		$shipAddress->setZip($order->shipping_postcode);
		$shipAddress->setCountry($order->shipping_country);

		$this->setShippingAddress( $shipAddress );

		$params = array_merge(
			$settings->getDoECParameters(),
			$this->getDoExpressCheckoutParameters( $token, $payerID )
		);

		$api = new PayPal_API( $settings->getActiveApiCredentials(), $settings->environment );

		$response = $api->DoExpressCheckoutPayment( $params );

		if ( $this->isSuccess( $response ) ) {
			$payment_details = new PayPal_Payment_Details();
			$payment_details->loadFromDoECResponse( $response );

			$meta = get_post_meta( $order_id, '_woo_pp_txnData', true );
			if ( ! empty($meta) ) {
				$txnData = $meta;
			} else {
				$txnData = array( 'refundable_txns' => array() );
			}

			$paymentAction = $settings->paymentAction;
			if ( 'Sale' == $paymentAction ) {
				$txn = array(
					'txnID' => $payment_details->payments[0]->transaction_id,
					'amount' => $order->get_total(),
					'refunded_amount' => 0
				);
				if ( 'Completed' == $payment_details->payments[0]->payment_status ) {
					$txn['status'] = 'Completed';
				} else {
					$txn['status'] = $payment_details->payments[0]->payment_status . '_' . $payment_details->payments[0]->pending_reason;
				}
				$txnData['refundable_txns'][] = $txn;

			} elseif ( 'Authorization' == $paymentAction ) {
				$txnData['auth_status'] = 'NotCompleted';

			} elseif ( 'Order' == $paymentAction ) {
				$txnData['order'] = array(
					'order_id' => $payment_details->payments[0]->transaction_id,
					'amount' => $order->get_total(),
					'status' => 'NotCompleted',
					'auths' => array()
				);
			}
			$txnData['txn_type'] = $paymentAction;

			update_post_meta( $order_id, '_woo_pp_txnData', $txnData );

			return $payment_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}
}
