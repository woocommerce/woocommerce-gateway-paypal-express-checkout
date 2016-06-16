<?php
/**
 * Cart handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$includes_path = wc_gateway_ppec()->includes_path;

// TODO: Use spl autoload to require on-demand maybe?

require_once( $includes_path . 'class-wc-gateway-ppec-settings.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-session-data.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-checkout-details.php' );

require_once( $includes_path . 'class-wc-gateway-ppec-api-error.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ppec-api-exception.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ppec-missing-session-exception.php' );

require_once( $includes_path . 'class-wc-gateway-ppec-payment-details.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-address.php' );

class WC_Gateway_PPEC_Checkout_Handler {

	protected $_suppressShippingAddress;

	// $_shippingAddress can be a single PayPal_Address object, or an array of PayPal_Address objects
	// (for the purposes of doing parallel payments).
	protected $_shippingAddress;
	protected $_requestBillingAgreement;
	protected $_enablePayPalCredit;

	public function __construct() {
		$this->_suppressShippingAddress = false;
		$this->_shippingAddress         = false;
		$this->_requestBillingAgreement = false;

		add_action( 'woocommerce_init', array( $this, 'init' ) );

		add_action( 'wp', array( $this, 'maybe_return_from_paypal' ) );
		add_action( 'wp', array( $this, 'maybe_cancel_checkout_with_paypal' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'maybe_clear_session_data' ) );

		add_action( 'woocommerce_before_checkout_process', array( $this, 'before_checkout_process' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'make_billing_address_optional' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'after_checkout_form' ) );
		add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_other_gateways' ) );
		add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_paypal_credit' ) );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'maybe_render_cancel_link' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function init() {
		// If the buyer clicked on the "Check Out with PayPal" button, we need to wait for the cart
		// totals to be available.  Unfortunately that doesn't happen until
		// woocommerce_before_cart_totals executes, and there is already output sent to the browser by
		// this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
		// sending anything back to the browser.
		if ( isset( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			ob_start();
		}
	}

	public function maybe_return_from_paypal() {
		if ( empty( $_GET['woo-paypal-return'] ) ) {
			return;
		}

		// If the token and payer ID aren't there, just ignore this request
		if ( empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return;
		}

		$token    = $_GET['token'];
		$payer_id = $_GET['PayerID'];

		try {
			$checkout_details = $this->getCheckoutDetails( $token );
		} catch( PayPal_API_Exception $e ) {
			wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from PayPal.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			return;
		} catch( PayPal_Missing_Session_Exception $e ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			return;
		}

		if ( $this->session_has_expired( $token ) ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired.  Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			return;
		}

		$session                     = WC()->session->paypal;
		$session->checkout_completed = true;
		$session->payerID            = $payer_id;

		WC()->session->paypal = $session;

		if ( $session->using_ppc ) {
			WC()->session->chosen_payment_method = 'ppec_paypal_credit';
		} else {
			WC()->session->chosen_payment_method = 'ppec_paypal';
		}

		if ( 'order' == $session->leftFrom && $session->order_id ) {
			// Try to complete the payment now.
			try {
				$order_id        = $session->order_id;
				$payment_details = $this->completePayment( $order_id, $session->token, $session->payerID );
				$transaction_id  = $payment_details->payments[0]->transaction_id;

				// TODO: Handle things like eChecks, giropay, etc.
				$order = wc_get_order( $order_id );
				$order->payment_complete( $transaction_id );
				$order->add_order_note( sprintf( __( 'PayPal transaction completed; transaction ID = %s', 'woocommerce-gateway-paypal-express-checkout' ), $transaction_id ) );
				$order->reduce_order_stock();
				WC()->cart->empty_cart();
				unset( WC()->session->paypal );

				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;

			} catch( PayPal_Missing_Session_Exception $e ) {

				// For some reason, our session data is missing.  Generally,
				// if we've made it this far, this shouldn't happen.
				wc_add_notice( __( 'Sorry, an error occurred while trying to process your payment.  Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );

			} catch( PayPal_API_Exception $e ) {

				// Did we get a 10486 or 10422 back from PayPal?  If so,
				// this means we need to send the buyer back over to PayPal
				// to have them pick out a new funding method.
				$need_to_redirect_back = false;
				foreach ( $e->errors as $error ) {
					if ( '10486' == $error->error_code || '10422' == $error->error_code ) {
						$need_to_redirect_back = true;
					}
				}

				if ( $need_to_redirect_back ) {
					$settings = wc_gateway_ppec()->settings->loadSettings();
					$session->checkout_completed = false;
					$session->leftFrom = 'order';
					$session->order_id = $order_id;
					WC()->session->paypal = $session;
					wp_safe_redirect( $settings->getPayPalRedirectUrl( $session->token, true ) );
					exit;
				} else {
					$final_output = '<ul>';
					foreach ( $e->errors as $error ) {
						$final_output .= '<li>' . __( $error->maptoBuyerFriendlyError(), 'woocommerce-gateway-paypal-express-checkout' ) . '</li>';
					}
					$final_output .= '</ul>';
					wc_add_notice( __( 'Payment error:', 'woocommerce-gateway-paypal-express-checkout' ) . $final_output, 'error' );
					return;
				}
			}
		}
	}

	/**
	 * Before checkout process.
	 *
	 * Turn off use of the buyer email in the payment method title so that it
	 * doesn't appear in emails.
	 *
	 * @return void
	 */
	public function before_checkout_process() {
		WC_Gateway_PPEC::$use_buyer_email = false;
	}

	public function make_billing_address_optional( $checkout_fields ) {
		$session = WC()->session->paypal;
		if ( is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->checkout_completed && $session->expiry_time >= time() && $session->payerID ) {
			$checkout_fields['billing']['billing_address_1']['required'] = false;
			$checkout_fields['billing']['billing_address_1']['class'][] = 'ppec-bypass';
			$checkout_fields['billing']['billing_address_1']['class'][] = 'hidden';

			$checkout_fields['billing']['billing_address_2']['required'] = false;
			$checkout_fields['billing']['billing_address_2']['class'][] = 'ppec-bypass';
			$checkout_fields['billing']['billing_address_2']['class'][] = 'hidden';

			$checkout_fields['billing']['billing_city']['required'] = false;
			$checkout_fields['billing']['billing_city']['class'][] = 'ppec-bypass';
			$checkout_fields['billing']['billing_city']['class'][] = 'hidden';

			$checkout_fields['billing']['billing_state']['required'] = false;
			$checkout_fields['billing']['billing_state']['class'][] = 'ppec-bypass';
			$checkout_fields['billing']['billing_state']['class'][] = 'hidden';

			$checkout_fields['billing']['billing_postcode' ]['required'] = false;
			$checkout_fields['billing']['billing_postcode']['class'][] = 'ppec-bypass';
			$checkout_fields['billing']['billing_postcode']['class'][] = 'hidden';
		}

		return $checkout_fields;
	}

	/**
	 * After checkout form.
	 */
	public function after_checkout_form() {
		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( ! $settings->enabled ) {
			return;
		}

		$api_credentials = $settings->getActiveApiCredentials();
		if ( ! is_callable( array( $api_credentials, 'get_payer_id' ) ) ) {
			return;
		}

		if ( $settings->enableInContextCheckout ) {
			$session = WC()->session->paypal;

			// Make sure no session being set from cart.
			if ( $session && is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) ) {
				if ( $session->checkout_completed ) {
					return;
				}

				if ( $session->expiry_time > time() ) {
					return;
				}

				if ( ! empty( $session->payerID ) ) {
					return;
				}
			}

			// This div is necessary for PayPal to properly display its lightbox.
			?>
			<div id="woo_pp_icc_container" style="display: none;"></div>
			<?php
		}
	}

	/**
	 * Enqueue front-end scripts on checkout page.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( ! $settings->enabled ) {
			return;
		}

		$api_credentials = $settings->getActiveApiCredentials();
		if ( ! is_callable( array( $api_credentials, 'get_payer_id' ) ) ) {
			return;
		}

		wp_enqueue_style( 'wc-gateway-ppec-frontend-checkout', wc_gateway_ppec()->plugin_url . 'assets/css/wc-gateway-ppec-frontend-checkout.css', array(), wc_gateway_ppec()->version );

		// On the checkout page, only load the JS if we plan on sending them over to PayPal.
		$payer_id = $api_credentials->get_payer_id();
		if ( $settings->enableInContextCheckout && ! empty( $payer_id )  ) {
			$session = WC()->session->paypal;
			if ( ! $session
				|| ! is_a( $session, 'WC_Gateway_PPEC_Session_Data' )
				|| ! $session->checkout_completed || $session->expiry_time < time()
				|| ! $session->payerID ) {

				wp_enqueue_script( 'wc-gateway-ppec-frontend-checkout', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-frontend-checkout.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
				wp_localize_script( 'wc-gateway-ppec-frontend-checkout', 'wc_ppec', array( 'payer_id' => $payer_id ) );

				wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
			}
		}
	}

	/**
	 * Maybe disable other gateways.
	 *
	 * @since 1.0.0
	 * @param array $gateways Available gateways
	 *
	 * @return array Available gateways
	 */
	public function maybe_disable_other_gateways( $gateways ) {
		// Unset all other gateways after checking out from cart.
		if ( $this->has_active_session() ) {
			foreach ( $gateways as $id => $gateway ) {
				if ( 'ppec_paypal' !== $id ) {
					unset( $gateways[ $id ] );
				}
			}
		}

		// If PPEC is enabled, removed PayPal standard.
		if ( wc_gateway_ppec()->settings->loadSettings()->enabled ) {
			unset( $gateways['paypal'] );
		}

		return $gateways;
	}

	/**
	 * If base location is not US, disable PayPal Credit.
	 *
	 * @since 1.0.0
	 * @param array $gateways Available gateways
	 *
	 * @return array Available gateways
	 */
	public function maybe_disable_paypal_credit( $gateways ) {
		if ( isset( $gateways['ppec_paypal_credit'] ) && 'US' !== WC()->countries->get_base_country() ) {
			unset( $gateways['ppec_paypal_credit'] );
		}

		return $gateways;
	}

	/**
	 * When cart based Checkout with PPEC is in effect, we need to include
	 * a Cancel button on the checkout form to give the user a means to throw
	 * away the session provided and possibly select a different payment
	 * gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_render_cancel_link() {
		if ( $this->has_active_session() ) {
			printf(
				'<a href="%s" class="wc-gateway-ppec-cancel">%s</a>',
				esc_url( add_query_arg( 'wc-gateway-ppec-clear-session', true, WC()->cart->get_cart_url() ) ),
				esc_html__( 'Cancel', 'woocommerce-gateway-paypal-express-checkout' )
			);
		}
	}

	public function maybe_cancel_checkout_with_paypal() {
		if ( is_cart() && ! empty( $_GET['wc-gateway-ppec-clear-session'] ) ) {
			$this->maybe_clear_session_data();
			wc_add_notice( __( 'You have cancelled Checkout with PayPal. Please try to process your order again.', 'woocommerce-gateway-paypal-express-checkout' ), 'notice' );
		}
	}

	/**
	 * Used when cart based Checkout with PayPal is in effect. Hooked to woocommerce_cart_emptied
	 * Also called by WC_PayPal_Braintree_Loader::possibly_cancel_checkout_with_paypal
	 *
	 * @since 1.0.0
	 */
	public function maybe_clear_session_data() {
		if (  $this->has_active_session() ) {
			unset( WC()->session->paypal );
		}
	}

	/**
	 * Checks whether session with passed token has expired.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token
	 *
	 * @return bool
	 */
	public function session_has_expired( $token ) {
		$session = WC()->session->paypal;

		return (
			! $session
			||
			! is_a( $session, 'WC_Gateway_PPEC_Session_Data' )
			||
			$session->expiry_time < time()
			||
			$token !== $session->token
		);
	}

	/**
	 * Checks whether there's active session from cart-based checkout with PPEC.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Returns true if PPEC session exists and still valid
	 */
	public function has_active_session() {
		$session = WC()->session->paypal;

		return ( is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->payerID && $session->expiry_time > time() );
	}

	/**
	 * Get token from session.
	 *
	 * @since 1.0.0
	 *
	 * @return string Token from session
	 */
	public function get_token_from_session() {
		$token   = '';
		$session = WC()->session->paypal;

		if ( is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->token ) {
			$token = $session->token;
		}

		return $token;
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
		$params = wc_gateway_ppec()->cart->setECParams();

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
		$params = wc_gateway_ppec()->cart->setECParams();

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

	/**
	 * Get return URL.
	 *
	 * The URL to return from express checkout.
	 *
	 * @return string Return URL
	 */
	protected function get_return_url() {
		return add_query_arg( 'woo-paypal-return', 'true', WC()->cart->get_checkout_url() );
	}

	/**
	 * Get cancel URL.
	 *
	 * The URL to return when canceling the express checkout.
	 *
	 * @return string Cancel URL
	 */
	protected function get_cancel_url() {
		return add_query_arg( 'woo-paypal-cancel', 'true', WC()->cart->get_cart_url() );
	}

	public function start_checkout_from_cart() {

		wc_gateway_ppec()->cart->loadCartDetails();

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

		$brand_name = get_bloginfo( 'name', 'display' );
		if ( ! empty( $brand_name ) ) {
			$brand_name          = substr( $brand_name, 0, 127 );
			$params['BRANDNAME'] = $brand_name;
		}

		$params['RETURNURL'] = $this->get_return_url();
		$params['CANCELURL'] = $this->get_cancel_url();

		if ( $this->_requestBillingAgreement ) {
			$params['BILLINGTYPE'] = 'MerchantInitiatedBilling';
		}

		$response = wc_gateway_ppec()->client->set_express_checkout( $params );
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

	public function start_checkout_from_checkout( $order_id, $use_ppc = false ) {

		wc_gateway_ppec()->cart->loadOrderDetails( $order_id );

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

		$brand_name = get_bloginfo( 'name', 'display' );
		if ( ! empty( $brand_name ) ) {
			$brand_name          = substr( $brand_name, 0, 127 );
			$params['BRANDNAME'] = $brand_name;
		}

		$params['RETURNURL'] = $this->get_return_url();
		$params['CANCELURL'] = $this->get_cancel_url();

		if ( $this->_requestBillingAgreement ) {
			$params['BILLINGTYPE'] = 'MerchantInitiatedBilling';
		}

		$params['ADDROVERRIDE'] = '1';

		$needs_shipping = WC()->cart->needs_shipping();
		$this->suppressShippingAddress( $needs_shipping );

		$response = wc_gateway_ppec()->client->set_express_checkout( $params );

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

		if ( false === $token ) {
			$token = $_GET['token'];
		}

		$response = wc_gateway_ppec()->client->get_express_checkout_details( $token );

		if ( 'Success' == $response['ACK'] || 'SuccessWithWarning' == $response['ACK'] ) {
			$checkout_details = new PayPal_Checkout_Details();
			$checkout_details->loadFromGetECResponse( $response );

			$session_data = WC()->session->paypal;
			if ( null === $session_data ) {
				throw new PayPal_Missing_Session_Exception();
			}

			if ( is_a( $session_data, 'WC_Gateway_PPEC_Session_Data' ) && $token === $session_data->token ) {
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

		wc_gateway_ppec()->cart->loadOrderDetails( $order_id );

		$settings = wc_gateway_ppec()->settings->loadSettings();

		$order = wc_get_order( $order_id );

		if ( $session_data->shipping_required ) {
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
		}

		$params = array_merge(
			$settings->getDoECParameters(),
			$this->getDoExpressCheckoutParameters( $token, $payerID )
		);

		$params['PAYMENTREQUEST_0_INVNUM'] = $order->get_order_number();

		$response = wc_gateway_ppec()->client->do_express_checkout_payment( $params );

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
			}

			$txnData['txn_type'] = $paymentAction;

			update_post_meta( $order_id, '_woo_pp_txnData', $txnData );

			return $payment_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}
}
