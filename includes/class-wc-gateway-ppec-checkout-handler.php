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

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );

		add_action( 'wp', array( $this, 'maybe_return_from_paypal' ) );
		add_action( 'wp', array( $this, 'maybe_cancel_checkout_with_paypal' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'maybe_clear_session_data' ) );

		add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_other_gateways' ) );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'maybe_render_cancel_link' ) );

		add_action( 'woocommerce_cart_shipping_packages', array( $this, 'maybe_add_shipping_information' ) );
	}

	/**
	 * If the buyer clicked on the "Check Out with PayPal" button, we need to wait for the cart
	 * totals to be available.  Unfortunately that doesn't happen until
	 * woocommerce_before_cart_totals executes, and there is already output sent to the browser by
	 * this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
	 * sending anything back to the browser.
	 */
	public function init() {
		if ( isset( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			ob_start();
		}
	}

	/**
	 * Handle endpoint page title
	 * @param  string $title
	 * @return string
	 */
	public function endpoint_page_titles( $title ) {
		if ( is_main_query() && in_the_loop() && is_page() && is_checkout() && $this->has_active_session() ) {
			$title = __( 'Confirm your PayPal order', 'woocommerce-gateway-paypal-express-checkout' );
			remove_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		}
		return $title;
	}

	/**
	 * Prepare billing and shipping details if there's active sesssion during checkout.
	 *
	 * @param WC_Checkout $checkout
	 */
	function checkout_init( $checkout ) {
		global $wp_query, $wp;

		if ( $this->has_active_session() ) {
			// We don't neeed billing and shipping to confirm a paypal order.
			$checkout->checkout_fields['billing']  = array();
			$checkout->checkout_fields['shipping'] = array();

			remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ) );
			remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ) );
			add_action( 'woocommerce_checkout_billing', array( $this, 'paypal_billing_details' ) );
			add_action( 'woocommerce_checkout_shipping', array( $this, 'paypal_shipping_details' ) );
		}
	}

	/**
	 * Show billing information.
	 */
	public function paypal_billing_details() {
		$session          = WC()->session->get( 'paypal' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
		$checkout_details = $this->get_checkout_details( $token );
		?>
		<h3><?php _e( 'Billing details', 'woocommerce-gateway-paypal-express-checkout' ); ?></h3>
		<ul>
			<?php if ( $checkout_details->payer_details->billing_address ) : ?>
				<li><strong><?php _e( 'Address:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong></br><?php echo WC()->countries->get_formatted_address( $this->get_mapped_billing_address( $checkout_details ) ); ?></li>
			<?php else : ?>
				<li><strong><?php _e( 'Name:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->first_name . ' ' . $checkout_details->payer_details->last_name ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->email ) ) : ?>
				<li><strong><?php _e( 'Email:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->email ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->phone_number ) ) : ?>
				<li><strong><?php _e( 'Tel:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->phone_number ); ?></li>
			<?php endif; ?>
		</ul>
		<?php
	}

	/**
	 * Show shipping information.
	 */
	public function paypal_shipping_details() {
		$session          = WC()->session->get( 'paypal' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
		$checkout_details = $this->get_checkout_details( $token );

		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}
		?>
		<h3><?php _e( 'Shipping details', 'woocommerce-gateway-paypal-express-checkout' ); ?></h3>
		<?php
		echo WC()->countries->get_formatted_address( $this->get_mapped_shipping_address( $checkout_details ) );
	}

	/**
	 * Map PayPal billing address to WC shipping address
	 * @param  object $checkout_details
	 * @return array
	 */
	public function get_mapped_billing_address( $checkout_details ) {
		if ( empty( $checkout_details->payer_details ) ) {
			return array();
		}
		return array(
			'first_name' => $checkout_details->payer_details->first_name,
			'last_name'  => $checkout_details->payer_details->last_name,
			'company'    => $checkout_details->payer_details->business_name,
			'address_1'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet1() : '',
			'address_2'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet2() : '',
			'city'       => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCity() : '',
			'state'      => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getState() : '',
			'postcode'   => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getZip() : '',
			'country'    => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCountry() : $checkout_details->payer_details->country,
			'phone'      => $checkout_details->payer_details->phone_number,
			'email'      => $checkout_details->payer_details->email,
		);
	}

	/**
	 * Map PayPal shipping address to WC shipping address.
	 *
	 * @param  object $checkout_details Checkout details
	 * @return array
	 */
	public function get_mapped_shipping_address( $checkout_details ) {
		if ( empty( $checkout_details->payments[0] ) || empty( $checkout_details->payments[0]->shipping_address ) ) {
			return array();
		}
		$name       = explode( ' ', $checkout_details->payments[0]->shipping_address->getName() );
		$first_name = array_shift( $name );
		$last_name  = implode( ' ', $name );
		return array(
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'company'       => $checkout_details->payer_details->business_name,
			'address_1'     => $checkout_details->payments[0]->shipping_address->getStreet1(),
			'address_2'     => $checkout_details->payments[0]->shipping_address->getStreet2(),
			'city'          => $checkout_details->payments[0]->shipping_address->getCity(),
			'state'         => $checkout_details->payments[0]->shipping_address->getState(),
			'postcode'      => $checkout_details->payments[0]->shipping_address->getZip(),
			'country'       => $checkout_details->payments[0]->shipping_address->getCountry(),
		);
	}

	/**
	 * Checks data is correctly set when returning from PayPal Express Checkout
	 */
	public function maybe_return_from_paypal() {
		if ( empty( $_GET['woo-paypal-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return;
		}

		$token    = $_GET['token'];
		$payer_id = $_GET['PayerID'];
		$session  = WC()->session->get( 'paypal' );

		if ( empty( $session ) || $this->session_has_expired( $token ) ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired. Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			return;
		}

		// Store values in session.
		$session->checkout_completed = true;
		$session->payer_id           = $payer_id;
		WC()->session->set( 'paypal', $session );

		try {
			$checkout_details = $this->get_checkout_details( $token );

			// If commit was true, take payment right now
			if ( 'order' === $session->source && $session->order_id ) {

				// Get order
				$order = wc_get_order( $session->order_id );

				// Store address given by PayPal
				$order->set_address( $this->get_mapped_shipping_address( $checkout_details ), 'shipping' );

				// Complete the payment now.
				$this->do_payment( $order, $session->token, $session->payer_id );

				// Clear Cart
				WC()->cart->empty_cart();

				// Redirect
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from PayPal. Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		} catch ( PayPal_Missing_Session_Exception $e ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired. Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
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

		// If using PayPal standard (this is admin choice) we don't need to also show PayPal EC on checkout.
		} elseif ( is_checkout() && ( isset( $gateways['paypal'] ) || 'no' === wc_gateway_ppec()->settings->mark_enabled ) ) {
			unset( $gateways['ppec_paypal'] );
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
			if ( ! wc_has_notice( __( 'You have cancelled Checkout with PayPal. Please try to process your order again.', 'woocommerce-gateway-paypal-express-checkout' ), 'notice' ) ) {
				wc_add_notice( __( 'You have cancelled Checkout with PayPal. Please try to process your order again.', 'woocommerce-gateway-paypal-express-checkout' ), 'notice' );
			}
		}
	}

	/**
	 * Used when cart based Checkout with PayPal is in effect. Hooked to woocommerce_cart_emptied
	 * Also called by WC_PayPal_Braintree_Loader::possibly_cancel_checkout_with_paypal
	 *
	 * @since 1.0.0
	 */
	public function maybe_clear_session_data() {
		if ( $this->has_active_session() ) {
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
		return ( ! $session || ! is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) || $session->expiry_time < time() || $token !== $session->token );
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
		return ( is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->payer_id && $session->expiry_time > time() );
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

	/**
	 * @deprecated
	 */
	public function setShippingAddress( $address ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getSetExpressCheckoutParameters() {
		// No replacement because WC_Gateway_PPEC_Client::get_set_express_checkout_params
		// needs context from where the buyer starts checking out.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getDoExpressCheckoutParameters( $token, $payer_id ) {
		// No replacement because WC_Gateway_PPEC_Client::get_do_express_checkout_params
		// needs order_id to return properly.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * Whether PayPal response indicates an okay message.
	 *
	 * @param array $response Response from PayPal
	 *
	 * @return bool True if it's okay
	 */
	protected function is_success( $response ) {
		return (
			isset( $response['ACK'] )
			&&
			in_array( $response['ACK'], array( 'Success', 'SuccessWithWarning' ) )
		);
	}

	/**
	 * Handler when buyer is checking out from cart page.
	 *
	 * @throws PayPal_API_Exception
	 */
	public function start_checkout_from_cart() {
		$settings = wc_gateway_ppec()->settings;
		$client   = wc_gateway_ppec()->client;
		$params   = $client->get_set_express_checkout_params( array( 'start_from' => 'cart' ) );

		$response = $client->set_express_checkout( $params );
		if ( $this->is_success( $response ) ) {
			// Save some data to the session.
			WC()->session->paypal = new WC_Gateway_PPEC_Session_Data(
				array(
					'token'             => $response['TOKEN'],
					'source'            => 'cart',
					'expires_in'        => $settings->get_token_session_length(),
					'use_paypal_credit' => wc_gateway_ppec_is_using_credit(),
				)
			);

			return $settings->get_paypal_redirect_url( $response['TOKEN'], false );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Handler when buyer is checking out from checkout page.
	 *
	 * @throws PayPal_API_Exception
	 *
	 * @param int $order_id Order ID
	 */
	public function start_checkout_from_checkout( $order_id ) {
		$settings = wc_gateway_ppec()->settings;
		$client   = wc_gateway_ppec()->client;
		$params   = $client->get_set_express_checkout_params( array(
			'start_from' => 'cart',
			'order_id'   => $order_id,
		) );

		$response = $client->set_express_checkout( $params );

		if ( $this->is_success( $response ) ) {
			// Save some data to the session.
			WC()->session->paypal = new WC_Gateway_PPEC_Session_Data(
				array(
					'token'      => $response['TOKEN'],
					'source'     => 'order',
					'order_id'   => $order_id,
					'expires_in' => $settings->get_token_session_length()
				)
			);

			return $settings->get_paypal_redirect_url( $response['TOKEN'], true );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * @deprecated
	 */
	public function getCheckoutDetails( $token = false ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Checkout_Handler::get_checkout_details' );
		return $this->get_checkout_details( $token );
	}

	/**
	 * Get checkout details from token.
	 *
	 * @since 1.2.0
	 *
	 * @throws \Exception
	 *
	 * @param bool|string $token Express Checkout token
	 */
	public function get_checkout_details( $token = false ) {
		if ( false === $token && ! empty( $_GET['token'] ) ) {
			$token = $_GET['token'];
		}

		$response = wc_gateway_ppec()->client->get_express_checkout_details( $token );

		if ( $this->is_success( $response ) ) {
			$checkout_details = new PayPal_Checkout_Details();
			$checkout_details->loadFromGetECResponse( $response );
			return $checkout_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Complete a payment that has been authorized via PPEC.
	 */
	public function do_payment( $order, $token, $payer_id ) {
		$settings     = wc_gateway_ppec()->settings;
		$session_data = WC()->session->get( 'paypal', null );

		if ( ! $order || null === $session_data || $this->session_has_expired( $token ) || empty( $payer_id ) ) {
			throw new PayPal_Missing_Session_Exception();
		}

		$client = wc_gateway_ppec()->client;
		$params = $client->get_do_express_checkout_params( array(
			'order_id' => $order->id,
			'token'    => $token,
			'payer_id' => $payer_id,
		) );

		$response = $client->do_express_checkout_payment( $params );

		if ( $this->is_success( $response ) ) {
			$payment_details = new PayPal_Payment_Details();
			$payment_details->loadFromDoECResponse( $response );

			$meta = get_post_meta( $order->id, '_woo_pp_txnData', true );
			if ( ! empty( $meta ) ) {
				$txnData = $meta;
			} else {
				$txnData = array( 'refundable_txns' => array() );
			}

			$paymentAction = $settings->get_paymentaction();
			if ( 'sale' == $paymentAction ) {
				$txn = array(
					'txnID'           => $payment_details->payments[0]->transaction_id,
					'amount'          => $order->get_total(),
					'refunded_amount' => 0
				);
				if ( 'Completed' == $payment_details->payments[0]->payment_status ) {
					$txn['status'] = 'Completed';
				} else {
					$txn['status'] = $payment_details->payments[0]->payment_status . '_' . $payment_details->payments[0]->pending_reason;
				}
				$txnData['refundable_txns'][] = $txn;

			} elseif ( 'authorization' == $paymentAction ) {
				$txnData['auth_status'] = 'NotCompleted';
			}

			$txnData['txn_type'] = $paymentAction;

			update_post_meta( $order->id, '_woo_pp_txnData', $txnData );

			// Payment was taken so clear session
			$this->maybe_clear_session_data();

			// Handle order
			$this->handle_payment_response( $order, $payment_details->payments[0] );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Handle result of do_payment
	 */
	public function handle_payment_response( $order, $payment ) {
		// Store meta data to order
		update_post_meta( $order->id, '_paypal_status', strtolower( $payment->payment_status ) );
		update_post_meta( $order->id, '_transaction_id', $payment->transaction_id );

		// Handle $payment response
		if ( 'completed' === strtolower( $payment->payment_status ) ) {
			$order->payment_complete( $payment->transaction_id );
		} else {
			if ( 'authorization' === $payment->pending_reason ) {
				$order->update_status( 'on-hold', __( 'Payment authorized. Change payment status to processing or complete to capture funds.', 'woocommerce-gateway-paypal-express-checkout' ) );
			} else {
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending (%s).', 'woocommerce-gateway-paypal-express-checkout' ), $payment->pending_reason ) );
			}
			$order->reduce_order_stock();
		}
	}

	/**
	 * This function filter the packages adding shipping information from PayPal on the checkout page
	 * after the user is authenticated by PayPal.
	 *
	 * @since 1.9.13 Introduced
	 * @param array $packages
	 *
	 * @return mixed
	 */
	public function maybe_add_shipping_information( $packages ) {
		if ( empty( $_GET['woo-paypal-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return $packages;
		}
		// Shipping details from PayPal
		$checkout_details = $this->get_checkout_details( wc_clean( $_GET['token'] ) );
		$destination = $this->get_mapped_shipping_address( $checkout_details );

		$packages[0]['destination']['country']   = $destination['country'];
		$packages[0]['destination']['state']     = $destination['state'];
		$packages[0]['destination']['postcode']  = $destination['postcode'];
		$packages[0]['destination']['city']      = $destination['city'];
		$packages[0]['destination']['address']   = $destination['address_1'];
		$packages[0]['destination']['address_2'] = $destination['address_2'];

		return $packages;
	}
}
