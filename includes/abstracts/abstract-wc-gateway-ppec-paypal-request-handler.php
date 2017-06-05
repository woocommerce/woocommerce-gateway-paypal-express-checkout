<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class to handle request from PayPal.
 *
 * @since 1.1.2
 */
abstract class WC_Gateway_PPEC_PayPal_Request_Handler {

	/**
	 * Gateway instance.
	 *
	 * @var WC_Gateway_PPEC
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_PPEC $gateway PPEC gateway instance
	 */
	public function __construct( WC_Gateway_PPEC $gateway ) {
		$this->gateway = $gateway;
	}

	abstract protected function handle();

	/**
	 * Get the order from the PayPal 'Custom' variable.
	 *
	 * @param  string $raw_custom JSON Data passed back by PayPal
	 * @return bool|WC_Order      Order object or false
	 */
	protected function get_paypal_order( $raw_custom ) {
		// We have the data in the correct format, so get the order.
		if ( ( $custom = json_decode( $raw_custom ) ) && is_object( $custom ) ) {
			$order_id  = $custom->order_id;
			$order_key = $custom->order_key;
		} else {
			wc_gateway_ppec_log( sprintf( '%s: %s', __FUNCTION__, 'Error: Order ID and key were not found in "custom".' ) );
			return false;
		}

		if ( ! $order = wc_get_order( $order_id ) ) {
			// We have an invalid $order_id, probably because invoice_prefix has changed.
			$order_id = wc_get_order_id_by_order_key( $order_key );
			$order    = wc_get_order( $order_id );
		}

		if ( $order ) {
			$order_key_from_order = version_compare( WC_VERSION, '3.0', '<' ) ? $order->order_key : $order->get_order_key();
		} else {
			$order_key_from_order = '';
		}

		if ( ! $order || $order_key_from_order !== $order_key ) {
			wc_gateway_ppec_log( sprintf( '%s: %s', __FUNCTION__, 'Error: Order Keys do not match.' ) );
			return false;
		}
		return $order;
	}

	/**
	 * Complete order, add transaction ID and note.
	 *
	 * @param  WC_Order $order  Order object
	 * @param  string   $txn_id Transaction ID
	 * @param  string   $note   Order note
	 */
	protected function payment_complete( $order, $txn_id = '', $note = '' ) {
		$order->add_order_note( $note );
		$order->payment_complete( $txn_id );
	}

	/**
	 * Hold order and add note.
	 *
	 * @param  WC_Order $order  Order object
	 * @param  string   $reason On-hold reason
	 */
	protected function payment_on_hold( $order, $reason = '' ) {
		$order->update_status( 'on-hold', $reason );
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			if ( ! get_post_meta( $order->id, '_order_stock_reduced', true ) ) {
				$order->reduce_order_stock();
			}
		} else {
			wc_maybe_reduce_stock_levels( $order->get_id() );
		}
		WC()->cart->empty_cart();
	}
}
