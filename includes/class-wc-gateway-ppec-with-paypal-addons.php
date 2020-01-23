<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_With_PayPal_Addons extends WC_Gateway_PPEC_With_PayPal {

	public function __construct() {
		parent::__construct();

		$this->supports = array_merge(
			$this->supports,
			array(
				'subscriptions',
				'subscription_cancellation',
				'subscription_reactivation',
				'subscription_suspension',
				'multiple_subscriptions',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'subscription_amount_changes',
				'subscription_date_changes',
			)
		);

		$this->_maybe_register_callback_in_subscriptions();
	}

	/**
	 * Maybe register callback in WooCommerce Subscription hooks.
	 *
	 * @since 1.2.0
	 */
	protected function _maybe_register_callback_in_subscriptions() {
		if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
			return;
		}

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_' . $this->id, array( $this, 'update_failing_payment_method' ) );

		// When changing the payment method for a WooCommerce Subscription to PayPal Checkout, let WooCommerce Subscription
		// know that the payment method for that subscription should not be changed immediately. Instead, it should
		// wait for the IPN notification, after the user confirmed the payment method change with PayPal.
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'indicate_async_payment_method_update' ), 10, 2 );

		// Add extra parameter when updating the subscription payment method to PayPal.
		add_filter( 'woocommerce_paypal_express_checkout_set_express_checkout_params_get_return_url', array( $this, 'add_query_param_to_url_subscription_payment_method_change' ), 10, 2 );
		add_filter( 'woocommerce_paypal_express_checkout_set_express_checkout_params_get_cancel_url', array( $this, 'add_query_param_to_url_subscription_payment_method_change' ), 10, 2 );
	}

	/**
	 * Checks whether order is part of subscription.
	 *
	 * @since 1.2.0
	 *
	 * @param int $order_id Order ID
	 *
	 * @return bool Returns true if order is part of subscription
	 */
	public function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Checks whether the order associated with the given order_id is
	 * for changing a payment method for a WooCommerce Subscription.
	 *
	 * @since 1.7.0
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return bool Returns true if the order is changing the payment method for a subscription.
	 */
	private function is_order_changing_payment_method_for_subscription( $order_id ) {
		$order = wc_get_order( $order_id );
		return (
			is_callable( array( $order, 'get_type' ) )
			&& 'shop_subscription' === $order->get_type()
			&& isset( $_POST['_wcsnonce'] )
			&& wp_verify_nonce( sanitize_key( $_POST['_wcsnonce'] ), 'wcs_change_payment_method' )
			&& isset( $_POST['woocommerce_change_payment'] )
			&& $order->get_id() === absint( $_POST['woocommerce_change_payment'] )
			&& isset( $_GET['key'] )
			&& $order->get_order_key() === $_GET['key']
			&& 0 === $order->get_total() // WooCommerce Subscriptions uses $0 orders to update payment method for the subscription.
		);
	}

	/**
	 * Process payment.
	 *
	 * @since 1.2.0
	 *
	 * @param int $order_id Order ID
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( $this->is_subscription( $order_id ) ) {
			// Is this a subscription payment method change?
			if ( $this->is_order_changing_payment_method_for_subscription( $order_id ) ) {
				return $this->change_subscription_payment_method( $order_id );
			}
			// Otherwise, it's a subscription payment.
			return $this->process_subscription( $order_id );
		}

		return parent::process_payment( $order_id );
	}

	/**
	 * Process initial subscription.
	 *
	 * @since 1.2.0
	 *
	 * @param int $order_id Order ID
	 *
	 * @return array
	 */
	public function process_subscription( $order_id ) {
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$resp   = parent::process_payment( $order_id );
		$order  = wc_get_order( $order_id );

		$subscriptions = array();
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		}

		$billing_agreement_id = $old_wc ? get_post_meta( $order_id, '_ppec_billing_agreement_id', true ) : $order->get_meta( '_ppec_billing_agreement_id', true );

		// Shipping / billing addresses and billing agreement were not copied
		// because it's not available during subscription creation.
		foreach ( $subscriptions as $subscription ) {
			wcs_copy_order_address( $order, $subscription );
			update_post_meta( is_callable( array( $subscription, 'get_id' ) ) ? $subscription->get_id() : $subscription->id, '_ppec_billing_agreement_id', $billing_agreement_id );
		}

		return $resp;
	}

	/**
	 * Process scheduled subscription payment.
	 *
	 * @since 1.2.0
	 *
	 * @param float        $amount Subscription amount
	 * @param int|WC_Order $order  Order ID or order object
	 */
	public function scheduled_subscription_payment( $amount, $order ) {
		$old_wc               = version_compare( WC_VERSION, '3.0', '<' );
		$order                = wc_get_order( $order );
		$order_id             = $old_wc ? $order->id : $order->get_id();
		$billing_agreement_id = $old_wc ? get_post_meta( $order_id, '_ppec_billing_agreement_id', true ) : $order->get_meta( '_ppec_billing_agreement_id', true );

		if ( empty( $billing_agreement_id ) ) {
			wc_gateway_ppec_log( sprintf( '%s: Could not found billing agreement. Skip reference transaction', __METHOD__ ) );
			return;
		}

		if ( 0 == $amount ) {
			$order->payment_complete();
			return;
		}

		$client = wc_gateway_ppec()->client;
		$params = $client->get_do_reference_transaction_params( array(
			'reference_id' => $billing_agreement_id,
			'amount'       => $amount,
			'order_id'     => $order_id,
		) );

		$resp = $client->do_reference_transaction( $params );

		$this->_process_reference_transaction_response( $order, $resp );
	}

	/**
	 * Process reference transaction response used when creating payment for
	 * scheduled subscription.
	 *
	 * @since 1.2.0
	 *
	 * @param WC_Order $order    Order object
	 * @param array    $response Response from DoReferenceTransaction
	 */
	protected function _process_reference_transaction_response( $order, $response ) {
		$client = wc_gateway_ppec()->client;

		try {
			if ( ! $client->response_has_success_status( $response ) ) {
				throw new Exception( __( 'PayPal API error', 'woocommerce-gateway-paypal-express-checkout' ) );
			}

			$status = ! empty( $response['PAYMENTSTATUS'] ) ? $response['PAYMENTSTATUS'] : '';

			switch ( $status ) {
				case 'Pending':
					/* translators: placeholder is pending reason from PayPal API. */
					$order_note = sprintf( __( 'PayPal transaction held: %s', 'woocommerce-gateway-paypal-express-checkout' ), $response['PENDINGREASON'] );
					if ( ! $order->has_status( 'on-hold' ) ) {
						$order->update_status( 'on-hold', $order_note );
					} else {
						$order->add_order_note( $order_note );
					}
					break;
				case 'Completed':
				case 'Processed':
				case 'In-Progress':
					$transaction_id = $response['TRANSACTIONID'];
					$order->add_order_note( sprintf( __( 'PayPal payment approved (ID: %s)', 'woocommerce-gateway-paypal-express-checkout' ), $transaction_id ) );
					$order->payment_complete( $transaction_id );
					break;
				default:
					throw new Exception( __( 'PayPal payment declined', 'woocommerce-gateway-paypal-express-checkout' ) );
			}

		} catch ( Exception $e ) {
			$order->update_status( 'failed', $e->getMessage() );
		}
	}

	/**
	 * Update billing agreement ID for a subscription after using PPEC to complete
	 * a payment to make up for an automatic renewal payment which previously
	 * failed.
	 *
	 * @since 1.2.0
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing
	 *                                       payment method relates
	 * @param WC_Order        $renewal_order The order which recorded the successful
	 *                                       payment (to make up for the failed
	 *                                       automatic payment)
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( is_callable( array( $subscription, 'get_id' ) ) ? $subscription->get_id() : $subscription->id, '_ppec_billing_agreement_id', $renewal_order->ppec_billing_agreement_id );
	}

	/**
	 * Indicate to WooCommerce Subscriptions that the payment method change for PayPal Checkout
	 * should be asynchronous.
	 *
	 * WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode uses the
	 * result to decide whether or not to change the payment method information on the subscription
	 * right away or not.
	 *
	 * In our case, the payment method will not be updated until after the user confirms the
	 * payment method change with PayPal. Once that's done, we'll take care of finishing
	 * the payment method update with the subscription.
	 *
	 * @since 1.7.0
	 *
	 * @param bool   $should_update Current value of whether the payment method should be updated immediately.
	 * @param string $new_payment_method The new payment method name.
	 *
	 * @return bool Whether the subscription's payment method should be updated on checkout or async when a response is returned.
	 */
	public function indicate_async_payment_method_update( $should_update, $new_payment_method ) {
		if ( 'ppec_paypal' === $new_payment_method ) {
			$should_update = false;
		}
		return $should_update;
	}

	/**
	 * Start the process to update the payment method for a WooCommerce Subscriptions.
	 *
	 * This function is called by `process_payment` when changing a payment method for WooCommerce Subscriptions.
	 * When it's successful, `WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode` will
	 * redirect to the redirect URL provided and the user will be prompted to confirm the payment update.
	 *
	 * @since 1.7.0
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Array used by WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode.
	 */
	public function change_subscription_payment_method( $order_id ) {
		try {
			return array(
				'result'   => 'success',
				'redirect' => wc_gateway_ppec()->checkout->start_checkout_from_order( $order_id, false ),
			);
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return array(
				'result' => 'failure',
			);
		}
	}

	/**
	 * Add query param to return and cancel URLs when making a payment change for
	 * a WooCommerce Subscription.
	 *
	 * @since 1.7.0
	 *
	 * @param string $url The original URL.
	 * @param int    $order_id Order ID.
	 *
	 * @return string The new URL.
	 */
	public function add_query_param_to_url_subscription_payment_method_change( $url, $order_id ) {
		if ( $this->is_order_changing_payment_method_for_subscription( $order_id ) ) {
			return add_query_arg( 'update_subscription_payment_method', 'true', $url );
		}
		return $url;
	}
}
