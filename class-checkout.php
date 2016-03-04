<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once( 'lib/class-checkout.php'        );
require_once( 'class-cart.php'                );
require_once( 'lib/class-api.php'             );
require_once( 'class-settings.php'            );
require_once( 'class-session.php'             );
require_once( 'lib/class-checkoutdetails.php' );
require_once( 'lib/class-exception.php'       );
require_once( 'lib/class-paymentdetails.php'  );
require_once( 'lib/class-address.php'         );

class WooCommerce_PayPal_Checkout extends PayPal_Checkout {

	public function __construct() {
		parent::__construct();

		$this->_cart = new WooCommerce_PayPal_Cart();
	}

	protected function getReturnUrl() {
		global $woocommerce;

		$url = $woocommerce->cart->get_checkout_url();
		if ( strpos( $url, '?' ) ) {
			$url .= '&';
		} else {
			$url .= '?';
		}

		$url .= 'woo-paypal-return=true';

		return $url;
	}

	protected function getCancelUrl() {
		global $woocommerce;

		$url = $woocommerce->cart->get_cart_url();
		if ( strpos( $url, '?' ) ) {
			$url .= '&';
		} else {
			$url .= '?';
		}

		$url .= 'woo-paypal-cancel=true';

		return $url;
	}

	public function startCheckoutFromCart() {
		global $woocommerce;

		$this->_cart->loadCartDetails();

		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();

		$needs_shipping = $woocommerce->cart->needs_shipping();
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
			$woocommerce->session->paypal = new WooCommerce_PayPal_Session_Data(
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
		global $woocommerce;

		$this->_cart->loadOrderDetails( $order_id );

		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();

		//new wc order > get address from that order > new pp address > assign address from order to new pp address > $this->setShippingAddress(pp address object)
		$getAddress = new WC_Order($order_id);
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

		$needs_shipping = $woocommerce->cart->needs_shipping();
		$this->suppressShippingAddress( $needs_shipping );

		$response = $api->SetExpressCheckout( $params );


		if ( $this->isSuccess( $response ) ) {
			// Save some data to the session.
			$woocommerce->session->paypal = new WooCommerce_PayPal_Session_Data(
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
		global $woocommerce;

		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();

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

			$session_data = $woocommerce->session->paypal;
			if ( null === $session_data ) {
				throw new PayPal_Missing_Session_Exception();
			}

			if ( is_a( $session_data, 'WooCommerce_PayPal_Session_Data' ) && $token == $session_data->token ) {
				$session_data->checkout_details = $checkout_details;
				$woocommerce->session->paypal = $session_data;
			} else {
				throw new PayPal_Missing_Session_Exception();
			}

			return $checkout_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	public function completePayment( $order_id, $token, $payerID ) {
		global $woocommerce;

		// Make sure our session data is there before we do something we might regret later
		$session_data = $woocommerce->session->paypal;
		if ( null === $session_data ) {
			throw new PayPal_Missing_Session_Exception();
		}

		if ( is_a( $session_data, 'WooCommerce_PayPal_Session_Data' ) && $token == $session_data->token ) {
			$woocommerce->session->paypal = $session_data;
		} else {
			throw new PayPal_Missing_Session_Exception();
		}

		// Now make sure we have the GetEC data.  If not, well then we'll just fetch it now, pardner.
		if ( ! $session_data->checkout_details || ! is_a( $session_data->checkout_details, 'PayPal_Checkout_Details' ) ) {
			$this->getCheckoutDetails( $token );
		}

		$this->_cart->loadOrderDetails( $order_id );

		$settings = new WooCommerce_PayPal_Settings();
		$settings->loadSettings();

		$order = new WC_Order($order_id);
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
