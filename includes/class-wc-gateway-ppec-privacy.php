<?php
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Gateway_PPEC_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( __( 'PayPal Checkout', 'woocommerce-gateway-paypal-express-checkout' ) );

		$this->add_exporter( 'woocommerce-gateway-paypal-express-checkout-order-data', __( 'WooCommerce PPEC Order Data', 'woocommerce-gateway-paypal-express-checkout' ), array( $this, 'order_data_exporter' ) );
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$this->add_exporter( 'woocommerce-gateway-paypal-express-checkout-subscriptions-data', __( 'WooCommerce PPEC Subscriptions Data', 'woocommerce-gateway-paypal-express-checkout' ), array( $this, 'subscriptions_data_exporter' ) );
		}

		$this->add_eraser( 'woocommerce-gateway-paypal-express-checkout-order-data', __( 'WooCommerce PPEC Data', 'woocommerce-gateway-paypal-express-checkout' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders that are using one of PPEC's payment methods.
	 *
	 * @param string  $email_address
	 * @param int     $page
	 *
	 * @return array WP_Post
	 */
	protected function get_ppec_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = array(
			'payment_method' => array( 'ppec_paypal' ),
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_privacy_message() {
		// Translators: placeholder is the URL of WC's privacy statement (PPEC section).
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-gateway-paypal-express-checkout' ), 'https://docs.woocommerce.com/document/privacy-payments/#woocommerce-gateway-paypal-express-checkout' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_ppec_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-gateway-paypal-express-checkout' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'PPEC Refundable transaction data', 'woocommerce-gateway-paypal-express-checkout' ),
							'value' => wp_json_encode( get_post_meta( $order->get_id(), '_woo_pp_txnData', true ) ),
						),
						array(
							'name'  => __( 'PPEC Billing agreement id', 'woocommerce-gateway-paypal-express-checkout' ),
							'value' => get_post_meta( $order->get_id(), '_ppec_billing_agreement_id', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Handle exporting data for Subscriptions.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function subscriptions_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$page           = (int) $page;
		$data_to_export = array();

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_payment_method',
				'value'   => array( 'ppec_paypal' ),
				'compare' => 'IN',
			),
			array(
				'key'     => '_billing_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		);

		$subscription_query = array(
			'posts_per_page' => 10,
			'page'           => $page,
			'meta_query'     => $meta_query, /* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query */
		);

		$subscriptions = wcs_get_subscriptions( $subscription_query );

		$done = true;

		if ( 0 < count( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_subscriptions',
					'group_label' => __( 'Subscriptions', 'woocommerce-gateway-paypal-express-checkout' ),
					'item_id'     => 'subscription-' . $subscription->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'PPEC Refundable transaction data', 'woocommerce-gateway-paypal-express-checkout' ),
							'value' => wp_json_encode( get_post_meta( $subscription->get_id(), '_woo_pp_txnData', true ) ),
						),
						array(
							'name'  => __( 'PPEC Billing agreement id', 'woocommerce-gateway-paypal-express-checkout' ),
							'value' => get_post_meta( $subscription->get_id(), '_ppec_billing_agreement_id', true ),
						),
					),
				);
			}

			$done = 10 > count( $subscriptions );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_ppec_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );

			$items_removed  |= $removed;
			$items_retained |= $retained;
			$messages        = array_merge( $messages, $msgs );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_subscription( $order );

			$items_removed  |= $removed;
			$items_retained |= $retained;
			$messages        = array_merge( $messages, $msgs );
		}

		// Tell core if we have more orders to work on still
		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Handle eraser of data tied to Subscriptions
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function maybe_handle_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return array( false, false, array() );
		}

		if ( ! wcs_order_contains_subscription( $order ) ) {
			return array( false, false, array() );
		}

		$subscription    = current( wcs_get_subscriptions_for_order( $order->get_id() ) );
		$subscription_id = $subscription->get_id();

		$ppec_billing = get_post_meta( $subscription_id, '_ppec_billing_agreement_id', true );

		if ( empty( $ppec_billing ) ) {
			return array( false, false, array() );
		}

		if ( $subscription->has_status( apply_filters( 'woocommerce_paypal_express_checkout_privacy_eraser_subs_statuses', array( 'on-hold', 'active' ) ) ) ) {
			// Translators: placeholder is an order number.
			return array( false, true, array( sprintf( __( 'Order ID %d contains an active Subscription', 'woocommerce-gateway-paypal-express-checkout' ), $order->get_id() ) ) );
		}

		$renewal_orders = WC_Subscriptions_Renewal_Order::get_renewal_orders( $order->get_id() );

		foreach ( $renewal_orders as $renewal_order_id ) {
			delete_post_meta( $renewal_order_id, '_woo_pp_txnData' );
			delete_post_meta( $renewal_order_id, '_ppec_billing_agreement_id' );
			delete_post_meta( $renewal_order_id, '_paypal_status' );
		}

		delete_post_meta( $subscription_id, '_woo_pp_txnData' );
		delete_post_meta( $subscription_id, '_ppec_billing_agreement_id' );
		delete_post_meta( $subscription_id, '_paypal_status' );

		return array( true, false, array( __( 'PayPal Checkout Subscriptions Data Erased.', 'woocommerce-gateway-paypal-express-checkout' ) ) );
	}

	/**
	 * Handle eraser of data tied to Orders
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function maybe_handle_order( $order ) {
		$order_id      = $order->get_id();
		$ppec_txn_data = get_post_meta( $order_id, '_woo_pp_txnData', true );
		$ppec_billing  = get_post_meta( $order_id, '_ppec_billing_agreement_id', true );
		$ppec_status   = get_post_meta( $order_id, '_paypal_status', true );

		if ( empty( $ppec_txn_data ) && empty( $ppec_billing ) && empty( $ppec_status ) ) {
			return array( false, false, array() );
		}

		delete_post_meta( $order_id, '_woo_pp_txnData' );
		delete_post_meta( $order_id, '_ppec_billing_agreement_id' );
		delete_post_meta( $order_id, '_paypal_status' );

		return array( true, false, array( __( 'PayPal Checkout Order Data Erased.', 'woocommerce-gateway-paypal-express-checkout' ) ) );
	}
}

new WC_Gateway_PPEC_Privacy();
