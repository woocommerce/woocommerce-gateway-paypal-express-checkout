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
		add_filter( 'woocommerce_payment_gateways_renewal_support_status_html', array( $this, 'subscription_tooltip' ), 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'get_available_gataways_for_subscriptions' ), 20 );
	}

	/**
	 * Filter for Subscriptions info tooltip html for this gateway
	 *
	 * @since 1.6.11
	 *
	 * @param string             $html HTML of the tooltip
	 * @param WC_Payment_Gateway $gateway Payment gateway to filter for
	 *
	 * @return string Filtered HTML
	 */
	public function subscription_tooltip( $html, $gateway ) {
		if ( $gateway->id !== $this->id ) {
			return $html;
		}
		if ( 'no' === $gateway->get_option( 'require_billing', 'no' ) ) {
			$tool_tip = esc_attr__( 'You must enable the "Require billing address" option to support this gateway\'s features for virtual subscriptions.', 'woocommerce-gateway-paypal-express-checkout' );
			$status = esc_html__( 'Maybe', 'woocommerce-gateway-paypal-express-checkout' );
			$html = sprintf( '<span class="payment-method-features-info tips" data-tip="%1$s">%2$s</span>',
				$tool_tip,
				$status );
		}
		return $html;
	}

	/**
	 * Filter determining whether to show this gateway during checkout
	 *
	 * @since 1.6.11
	 *
	 * @param array $gateways Array of payment gateways
	 *
	 * @return array Filtered array of payment gateways
	 */
	public function get_available_gataways_for_subscriptions( $gateways ) {
		if ( ! $this->should_display_buttons_at_checkout() ) {
			unset( $gateways['ppec_paypal'] );
		}
		return $gateways;
	}

	/**
	 * Checks if smart payment buttons can be displayed during the checkout
	 *
	 * @since 1.6.11
	 *
	 * @return bool True if buttons can be displayed
	 */
	public function should_display_buttons_at_checkout() {
		if ( ! class_exists( 'WC_Subscriptions_Product' )
			|| ! WC()->cart
			|| 'yes' === $this->get_option( 'require_billing', 'no' ) ) {
			return true;
		}
		$cart_contents = WC()->cart->cart_contents;
		if ( empty( $cart_contents ) ) {
			return true;
		}
		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) && ! $cart_item['data']->needs_shipping() ) {
				return false;
			}
		}
		return true;
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
}
